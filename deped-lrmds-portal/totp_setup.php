<?php
/**
 * DepEd LRMDS – totp_setup.php
 *
 * Called after registration for TOTP roles (teacher, school-head, developer).
 * The account has NOT been created in the DB yet at this point.
 *
 * Flow:
 *   1. register_handler.php stores data in $_SESSION['pending_registration']
 *   2. This page generates a TOTP secret + QR code
 *   3. User scans with Google Authenticator / any TOTP app
 *   4. User enters the 6-digit code
 *   5. Code confirmed → account inserted to DB with totp_secret + totp_enabled=1
 *   6. Redirect to signin.php with success message
 *
 * Requires:
 *   lib/TwoFactorAuth.php  — RobThree/TwoFactorAuth
 *   lib/QrProvider.php     — our tiny IQRCodeProvider using api.qrserver.com
 */

session_start();

require_once __DIR__ . '/lib/TwoFactorAuthException.php';
require_once __DIR__ . '/lib/Algorithm.php';

// RNG Providers
require_once __DIR__ . '/lib/Providers/Rng/IRNGProvider.php';
require_once __DIR__ . '/lib/Providers/Rng/CSRNGProvider.php';

// Time Providers
require_once __DIR__ . '/lib/Providers/Time/ITimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/LocalMachineTimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/NTPTimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/HttpTimeProvider.php';

// QR Providers
require_once __DIR__ . '/lib/Providers/Qr/IQRCodeProvider.php';
require_once __DIR__ . '/lib/Providers/Qr/BaseHTTPQRCodeProvider.php';
require_once __DIR__ . '/lib/Providers/Qr/QRException.php';
require_once __DIR__ . '/lib/Providers/Qr/QRServerProvider.php';

// Main library (must be last)
require_once __DIR__ . '/lib/TwoFactorAuth.php';

define('DB_HOST', 'localhost');
define('DB_NAME', 'lrmds');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* ── Guard: must have pending registration in session ── */
if (empty($_SESSION['pending_registration'])) {
    header('Location: register.php');
    exit;
}

$reg = $_SESSION['pending_registration'];

// Check the 30-minute expiry
if (time() > ($reg['expires_at'] ?? 0)) {
    unset($_SESSION['pending_registration']);
    header('Location: register.php?expired=1');
    exit;
}

/* ── Generate or reuse the TOTP secret ── */
// Store secret in session (not DB yet) so it survives page refreshes
if (empty($_SESSION['pending_totp_secret'])) {
    $tfa = new RobThree\Auth\TwoFactorAuth(new RobThree\Auth\Providers\Qr\QRServerProvider(), 'DepEd LRMDS');
    $_SESSION['pending_totp_secret'] = $tfa->createSecret();
}

$secret = $_SESSION['pending_totp_secret'];
$tfa    = new RobThree\Auth\TwoFactorAuth(new RobThree\Auth\Providers\Qr\QRServerProvider(), 'DepEd LRMDS');

/* ── Handle verification POST ── */
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Please enter the 6-digit code from your authenticator app.';
    } elseif (!$tfa->verifyCode($secret, $code)) {
        $error = 'Incorrect code. Make sure your phone clock is accurate and try again.';
    } else {
        /* ✅ Code correct — now create the account in the DB */
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            error_log('LRMDS totp_setup DB: ' . $e->getMessage());
            $error = 'Database connection failed. Make sure XAMPP MySQL is running.';
            goto render_page;
        }

        // Final duplicate email check (edge case: someone registered same email
        // in the 30-minute window while this session was open)
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $dup->execute([$reg['email']]);
        if ($dup->fetch()) {
            unset($_SESSION['pending_registration'], $_SESSION['pending_totp_secret']);
            $_SESSION['flash_error'] = 'An account with that email was just created. Please sign in or use a different email.';
            header('Location: register.php');
            exit;
        }

        try {
            $pdo->prepare('
                INSERT INTO users
                    (email, password_hash, first_name, last_name, role, status,
                     region, division, employee_id, meta,
                     totp_secret, totp_enabled, created_at)
                VALUES
                    (:email, :password_hash, :first_name, :last_name, :role, :status,
                     :region, :division, :employee_id, :meta,
                     :totp_secret, 1, NOW())
            ')->execute([
                ':email'         => $reg['email'],
                ':password_hash' => $reg['password'],
                ':first_name'    => $reg['fname'],
                ':last_name'     => $reg['lname'],
                ':role'          => $reg['role'],
                ':status'        => $reg['status'],
                ':region'        => $reg['region'],
                ':division'      => $reg['division'],
                ':employee_id'   => $reg['employee_id'],
                ':meta'          => $reg['meta'],
                ':totp_secret'   => $secret,
            ]);
        } catch (PDOException $e) {
            error_log('LRMDS totp_setup insert: ' . $e->getMessage());
            $error = 'Could not create your account. Please try again.';
            goto render_page;
        }

        // Clean up session
        unset($_SESSION['pending_registration'], $_SESSION['pending_totp_secret']);

        // Set flash message for signin page
        $_SESSION['flash_success'] = 'Two-factor authentication is set up. Your account is ready — please sign in.';
        header('Location: signin.php');
        exit;
    }
}

render_page:

/* ── Generate QR code data URI ── */
try {
    $qr_url = $tfa->getQRCodeImageAsDataUri(
        rawurlencode($reg['email']),
        $secret,
        220
    );
    $qr_error = '';
} catch (Throwable $e) {
    $qr_url   = '';
    $qr_error = 'Could not load QR code image. Use the manual key below instead.';
}

/* ── Role label ── */
$role_labels = [
    'teacher'    => 'Teacher',
    'school-head'=> 'School Head / Curriculum',
    'developer'  => 'Content Developer / Partner',
];
$role_label = $role_labels[$reg['role']] ?? $reg['role'];
$first_name = htmlspecialchars($reg['fname']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Set Up Two-Factor Authentication</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    .totp-wrap {
      max-width: 500px; margin: 0 auto;
      padding: 48px 24px 64px;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .totp-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: #EFF6FF; border: 1px solid #BFDBFE;
      color: #1D4ED8; border-radius: 999px;
      padding: 4px 12px; font-size: 12px; font-weight: 600; margin-bottom: 20px;
    }
    .totp-card {
      background: #fff; border: 1.5px solid #E5E7EB;
      border-radius: 16px; padding: 28px; margin: 20px 0;
    }
    .totp-steps { list-style: none; margin: 0; padding: 0; counter-reset: steps; }
    .totp-steps li {
      counter-increment: steps;
      display: flex; align-items: flex-start; gap: 12px;
      font-size: 14px; color: #374151;
      margin-bottom: 14px; line-height: 1.55;
    }
    .totp-steps li::before {
      content: counter(steps);
      flex-shrink: 0; width: 24px; height: 24px;
      background: #0B4F9C; color: #fff;
      border-radius: 50%; font-size: 12px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; margin-top: 1px;
    }
    .qr-wrap {
      display: flex; justify-content: center;
      padding: 16px; background: #fff;
      border: 1.5px solid #E5E7EB; border-radius: 12px; margin: 16px 0;
    }
    .qr-wrap img { display: block; }
    .secret-box {
      background: #F3F4F6; border: 1px solid #E5E7EB; border-radius: 8px;
      padding: 10px 14px; font-family: monospace; font-size: 15px;
      letter-spacing: .1em; color: #1F2937; text-align: center;
      word-break: break-all; margin: 10px 0; user-select: all;
    }
    .secret-label { font-size: 12px; color: #6B7280; text-align: center; margin-bottom: 4px; }
    .code-input {
      width: 100%; padding: 14px 20px; font-size: 28px; font-weight: 700;
      letter-spacing: .25em; text-align: center; font-family: monospace;
      border: 2px solid #D1D5DB; border-radius: 12px; outline: none;
      transition: border-color .15s, box-shadow .15s; box-sizing: border-box;
    }
    .code-input:focus { border-color: #0B4F9C; box-shadow: 0 0 0 4px rgba(11,79,156,.12); }
    .code-input.error { border-color: #DC2626; background: #FEF2F2; }
    .totp-error {
      background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px;
      padding: 10px 14px; font-size: 13px; color: #B91C1C;
      display: flex; align-items: flex-start; gap: 8px; margin-bottom: 12px;
    }
    .app-chips { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .app-chip {
      background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 999px;
      padding: 4px 12px; font-size: 12px; font-weight: 600; color: #374151;
    }
    .section-title {
      font-size: 12px; font-weight: 700; color: #6B7280;
      text-transform: uppercase; letter-spacing: .05em; margin: 0 0 8px;
    }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC">
<div class="totp-wrap">

  <div class="totp-badge">
    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
    Two-Factor Authentication Setup
  </div>

  <h1 style="font-size:22px;font-weight:800;color:#111827;margin:0 0 6px">
    Almost done, <?= $first_name ?>!
  </h1>
  <p style="font-size:14px;color:#6B7280;margin:0 0 4px">
    As a <strong><?= htmlspecialchars($role_label) ?></strong>, your account requires two-factor authentication
    before it can be created. This protects access to sensitive resources.
  </p>
  <p style="font-size:13px;color:#9CA3AF;margin:0">
    Your account will only be saved once you complete this step.
  </p>

  <div class="totp-card">
    <p class="section-title">Works with any of these apps</p>
    <div class="app-chips">
      <span class="app-chip">Google Authenticator</span>
      <span class="app-chip">Microsoft Authenticator</span>
      <span class="app-chip">Authy</span>
      <span class="app-chip">Any TOTP app</span>
    </div>
  </div>

  <div class="totp-card">
    <ol class="totp-steps">
      <li>Install an authenticator app on your phone if you haven't already.</li>
      <li>Open the app and tap <strong>Add account</strong> or the <strong>+</strong> button.</li>
      <li>Choose <strong>Scan QR code</strong> and point your camera here:</li>
    </ol>

    <?php if ($qr_error): ?>
      <div class="totp-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($qr_error) ?>
      </div>
    <?php else: ?>
      <div class="qr-wrap">
        <img src="<?= $qr_url ?>" alt="Scan this QR code with your authenticator app" width="220" height="220"/>
      </div>
    <?php endif; ?>

    <p class="secret-label">Can't scan? Enter this key manually in your app instead:</p>
    <div class="secret-box"><?= wordwrap(htmlspecialchars($secret), 4, ' ', true) ?></div>
    <p style="font-size:12px;color:#9CA3AF;text-align:center;margin:6px 0 0">
      Keep this key private — treat it like a password.
    </p>
  </div>

  <form method="POST" action="totp_setup.php" autocomplete="off">
    <p style="font-size:14px;font-weight:700;color:#374151;margin:0 0 4px">
      4. Enter the 6-digit code shown in your app to confirm and create your account:
    </p>
    <p style="font-size:13px;color:#9CA3AF;margin:0 0 12px">
      The code changes every 30 seconds — enter it before it expires.
    </p>

    <?php if ($error): ?>
    <div class="totp-error">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <input
      type="text" name="totp_code"
      class="code-input <?= $error ? 'error' : '' ?>"
      placeholder="000000" maxlength="6"
      inputmode="numeric" pattern="\d{6}"
      autofocus autocomplete="one-time-code"
    />

    <button type="submit" class="rf-btn rf-btn-primary" style="width:100%;margin-top:14px;justify-content:center">
      Confirm &amp; Create Account
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </form>

  <p style="text-align:center;font-size:13px;color:#9CA3AF;margin-top:20px">
    Want to use a different email?
    <a href="register.php" style="color:#0B4F9C;font-weight:600;text-decoration:none">Go back to registration</a>
  </p>

</div>
<script>
  const input = document.querySelector('.code-input');
  input?.addEventListener('input', () => {
    input.value = input.value.replace(/\D/g, '').slice(0, 6);
    if (input.value.length === 6) input.closest('form').submit();
  });
</script>
</body>
</html>