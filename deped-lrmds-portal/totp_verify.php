<?php
/**
 * DepEd LRMDS – totp_verify.php
 *
 * Shown during sign-in after email+password passes for TOTP roles.
 * signin_handler.php sets $_SESSION['totp_pending_user_id'] and redirects here.
 * On success → sets full session and redirects to dashboard/index.
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
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_SECONDS', 300);

/* ── Guard ── */
if (empty($_SESSION['totp_pending_user_id'])) {
    header('Location: signin.php');
    exit;
}

$user_id = (int) $_SESSION['totp_pending_user_id'];

/* ── DB ── */
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
    error_log('LRMDS totp_verify DB: ' . $e->getMessage());
    die('Database error. Please make sure XAMPP MySQL is running.');
}

$stmt = $pdo->prepare('SELECT id, first_name, email, role, totp_secret, totp_enabled FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !$user['totp_enabled'] || !$user['totp_secret']) {
    session_destroy();
    header('Location: signin.php');
    exit;
}

/* ── Rate limiting ── */
$attempts_key = 'totp_attempts_' . $user_id;
$lockout_key  = 'totp_lockout_'  . $user_id;
$locked       = false;
$error        = '';

if (!empty($_SESSION[$lockout_key]) && time() < $_SESSION[$lockout_key]) {
    $remaining = $_SESSION[$lockout_key] - time();
    $error     = "Too many incorrect attempts. Please wait {$remaining} seconds before trying again.";
    $locked    = true;
} elseif (!empty($_SESSION[$lockout_key])) {
    unset($_SESSION[$lockout_key], $_SESSION[$attempts_key]);
}

/* ── Handle POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$locked) {
    $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');
    $tfa  = new RobThree\Auth\TwoFactorAuth(new RobThree\Auth\Providers\Qr\QRServerProvider(), 'DepEd LRMDS');

    if (strlen($code) !== 6) {
        $error = 'Please enter the full 6-digit code.';
    } elseif (!$tfa->verifyCode($user['totp_secret'], $code)) {
        $_SESSION[$attempts_key] = ($_SESSION[$attempts_key] ?? 0) + 1;

        if ($_SESSION[$attempts_key] >= MAX_ATTEMPTS) {
            $_SESSION[$lockout_key] = time() + LOCKOUT_SECONDS;
            unset($_SESSION[$attempts_key]);
            $locked = true;
            $error  = 'Too many incorrect attempts. Locked for 5 minutes.';
        } else {
            $left  = MAX_ATTEMPTS - $_SESSION[$attempts_key];
            $error = "Incorrect code. {$left} attempt(s) remaining.";
        }
    } else {
        /* ✅ Valid — complete sign-in */
        unset(
            $_SESSION['totp_pending_user_id'],
            $_SESSION[$attempts_key],
            $_SESSION[$lockout_key]
        );
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'];
        $_SESSION['user']      = $user['email'];

        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
            ->execute([$user['id']]);

        header('Location: index.php');
        exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Two-Factor Verification</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    .totp-wrap {
      max-width: 420px; margin: 0 auto;
      padding: 64px 24px;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .totp-icon-wrap {
      width: 64px; height: 64px; background: #EFF6FF;
      border-radius: 50%; display: flex; align-items: center;
      justify-content: center; margin: 0 auto 24px;
    }
    .code-input {
      width: 100%; padding: 16px 20px; font-size: 32px; font-weight: 700;
      letter-spacing: .3em; text-align: center; font-family: monospace;
      border: 2px solid #D1D5DB; border-radius: 12px; outline: none;
      box-sizing: border-box; transition: border-color .15s, box-shadow .15s;
    }
    .code-input:focus { border-color: #0B4F9C; box-shadow: 0 0 0 4px rgba(11,79,156,.12); }
    .code-input.error  { border-color: #DC2626; background: #FEF2F2; }
    .code-input.locked { border-color: #D1D5DB; background: #F9FAFB; color: #9CA3AF; }
    .totp-error {
      background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px;
      padding: 10px 14px; font-size: 13px; color: #B91C1C;
      display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px;
    }
    .countdown { font-size: 12px; color: #9CA3AF; text-align: center; margin-top: 8px; }
    .countdown span { font-weight: 700; color: #0B4F9C; }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC">
<div class="totp-wrap">

  <div class="totp-icon-wrap">
    <svg width="28" height="28" fill="none" stroke="#0B4F9C" stroke-width="1.8" viewBox="0 0 24 24">
      <rect x="3" y="11" width="18" height="11" rx="2"/>
      <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
    </svg>
  </div>

  <h1 style="font-size:22px;font-weight:800;color:#111827;text-align:center;margin:0 0 8px">
    Two-Factor Verification
  </h1>
  <p style="font-size:14px;color:#6B7280;text-align:center;margin:0 0 28px">
    Welcome back, <strong><?= htmlspecialchars($user['first_name']) ?></strong>.
    Open your authenticator app and enter the 6-digit code for <strong>DepEd LRMDS</strong>.
  </p>

  <?php if ($error): ?>
  <div class="totp-error">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" action="totp_verify.php" autocomplete="off">
    <input
      type="text" name="totp_code"
      class="code-input <?= $error ? 'error' : '' ?> <?= $locked ? 'locked' : '' ?>"
      placeholder="000 000" maxlength="6"
      inputmode="numeric" pattern="\d{6}"
      <?= $locked ? 'disabled' : 'autofocus' ?>
      autocomplete="one-time-code"
    />
    <p class="countdown">Code refreshes every <span id="countdown">30</span>s</p>

    <button type="submit" class="rf-btn rf-btn-primary"
      style="width:100%;margin-top:20px;justify-content:center"
      <?= $locked ? 'disabled' : '' ?>>
      Verify
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>
  </form>

  <div style="text-align:center;margin-top:24px">
    <a href="signin.php" style="font-size:13px;color:#6B7280;text-decoration:none">← Back to sign in</a>
  </div>

  <p style="text-align:center;font-size:12px;color:#9CA3AF;margin-top:32px">
    Lost access to your authenticator?
    <a href="mailto:support@lrmds.deped.gov.ph" style="color:#0B4F9C;font-weight:600;text-decoration:none">Contact your administrator</a>
  </p>

</div>
<script>
  const input = document.querySelector('.code-input');
  input?.addEventListener('input', () => {
    input.value = input.value.replace(/\D/g, '').slice(0, 6);
    if (input.value.length === 6) input.closest('form').submit();
  });

  const el = document.getElementById('countdown');
  if (el) {
    function tick() {
      const s = 30 - (Math.floor(Date.now() / 1000) % 30);
      el.textContent = s;
      el.style.color = s <= 5 ? '#DC2626' : '#0B4F9C';
    }
    tick(); setInterval(tick, 1000);
  }
</script>
</body>
</html>