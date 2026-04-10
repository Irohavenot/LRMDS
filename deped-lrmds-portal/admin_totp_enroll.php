<?php
/**
 * DepEd LRMDS – admin_totp_enroll.php
 * ─────────────────────────────────────
 * ONE-TIME USE: Enroll the admin account in TOTP.
 * Run this from localhost only, then DELETE this file immediately after.
 *
 * Access: http://localhost/lrmds/admin_totp_enroll.php
 */

// ── Localhost-only guard ──────────────────────────────────────────────────────
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden — this tool is only accessible from localhost.');
}

session_start();

require_once __DIR__ . '/lib/TwoFactorAuthException.php';
require_once __DIR__ . '/lib/Algorithm.php';
require_once __DIR__ . '/lib/Providers/Rng/IRNGProvider.php';
require_once __DIR__ . '/lib/Providers/Rng/CSRNGProvider.php';
require_once __DIR__ . '/lib/Providers/Time/ITimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/LocalMachineTimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/NTPTimeProvider.php';
require_once __DIR__ . '/lib/Providers/Time/HttpTimeProvider.php';
require_once __DIR__ . '/lib/Providers/Qr/IQRCodeProvider.php';
require_once __DIR__ . '/lib/Providers/Qr/BaseHTTPQRCodeProvider.php';
require_once __DIR__ . '/lib/Providers/Qr/QRException.php';
require_once __DIR__ . '/lib/Providers/Qr/QRServerProvider.php';
require_once __DIR__ . '/lib/TwoFactorAuth.php';

define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// ── DB ────────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

// ── Find admin account ────────────────────────────────────────────────────────
$admin_email = $_POST['admin_email'] ?? $_GET['email'] ?? '';
$error       = '';
$success     = false;
$qr_url      = '';
$secret      = '';
$admin       = null;

if ($admin_email) {
    $stmt = $pdo->prepare("SELECT id, email, first_name, role, totp_enabled FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$admin_email]);
    $admin = $stmt->fetch();

    if (!$admin) {
        $error = "No admin account found with email: " . htmlspecialchars($admin_email);
    }
}

// ── Generate secret once per session ─────────────────────────────────────────
$tfa = new RobThree\Auth\TwoFactorAuth(new RobThree\Auth\Providers\Qr\QRServerProvider(), 'DepEd LRMDS');

if ($admin && empty($_SESSION['enroll_secret'])) {
    $_SESSION['enroll_secret']       = $tfa->createSecret();
    $_SESSION['enroll_admin_email']  = $admin['email'];
}

if ($admin && !empty($_SESSION['enroll_secret']) && $_SESSION['enroll_admin_email'] === $admin['email']) {
    $secret  = $_SESSION['enroll_secret'];
    try {
        $qr_url = $tfa->getQRCodeImageAsDataUri(rawurlencode($admin['email']), $secret, 240);
    } catch (Throwable $e) {
        $qr_url = '';
    }
}

// ── Handle verification POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['totp_code']) && $admin) {
    $code = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

    if (strlen($code) !== 6) {
        $error = 'Enter the full 6-digit code.';
    } elseif (empty($secret)) {
        $error = 'Session expired. Refresh and try again.';
    } elseif (!$tfa->verifyCode($secret, $code)) {
        $error = 'Incorrect code — make sure your phone clock is accurate.';
    } else {
        // Save secret to DB and enable TOTP
        $pdo->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1 WHERE id = ?")
            ->execute([$secret, $admin['id']]);
        unset($_SESSION['enroll_secret'], $_SESSION['enroll_admin_email']);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Admin TOTP Enrollment – LRMDS</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      font-family: system-ui, sans-serif;
      background: #F1F5F9;
      margin: 0; padding: 40px 16px;
      color: #1E293B;
    }
    .card {
      max-width: 480px; margin: 0 auto;
      background: #fff; border-radius: 16px;
      border: 1px solid #E2E8F0;
      padding: 32px;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }
    h1 { font-size: 20px; font-weight: 800; margin: 0 0 4px; }
    .warn-banner {
      background: #FFF7ED; border: 1px solid #FED7AA;
      border-radius: 8px; padding: 10px 14px;
      font-size: 13px; color: #C2410C;
      font-weight: 600; margin-bottom: 24px;
    }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
    input[type=email], input[type=text] {
      width: 100%; padding: 10px 14px; border: 1.5px solid #CBD5E1;
      border-radius: 8px; font-size: 14px; outline: none;
      transition: border-color .15s;
    }
    input:focus { border-color: #0B4F9C; box-shadow: 0 0 0 3px rgba(11,79,156,.1); }
    .btn {
      display: block; width: 100%; margin-top: 12px;
      background: #0B4F9C; color: #fff; border: none;
      padding: 11px; border-radius: 8px; font-size: 14px;
      font-weight: 700; cursor: pointer;
    }
    .btn:hover { background: #0A3F80; }
    .error {
      background: #FEF2F2; border: 1px solid #FECACA;
      border-radius: 8px; padding: 10px 14px;
      font-size: 13px; color: #B91C1C; margin: 12px 0;
    }
    .success {
      background: #F0FDF4; border: 1px solid #BBF7D0;
      border-radius: 8px; padding: 16px;
      font-size: 14px; color: #15803D; text-align: center;
    }
    .success strong { display: block; font-size: 18px; margin-bottom: 6px; }
    .qr-wrap {
      text-align: center; padding: 16px;
      border: 1.5px solid #E2E8F0; border-radius: 10px;
      margin: 16px 0; background: #F8FAFC;
    }
    .secret-box {
      font-family: monospace; letter-spacing: .1em;
      background: #F1F5F9; border: 1px solid #E2E8F0;
      border-radius: 6px; padding: 8px 12px;
      font-size: 14px; text-align: center;
      word-break: break-all; user-select: all;
      margin: 8px 0;
    }
    .code-input {
      width: 100%; padding: 14px; font-size: 28px;
      font-weight: 700; letter-spacing: .25em;
      text-align: center; font-family: monospace;
      border: 2px solid #CBD5E1; border-radius: 10px;
      outline: none; transition: border-color .15s;
    }
    .code-input:focus { border-color: #0B4F9C; }
    .muted { font-size: 12px; color: #94A3B8; text-align: center; margin: 6px 0 0; }
    hr { border: none; border-top: 1px solid #E2E8F0; margin: 20px 0; }
  </style>
</head>
<body>
<div class="card">

  <h1>🔐 Admin TOTP Enrollment</h1>
  <p style="font-size:13px;color:#64748B;margin:4px 0 20px">One-time setup — delete this file after use.</p>

  <div class="warn-banner">
    ⚠️ This page is for localhost only. Delete <code>admin_totp_enroll.php</code> immediately after completing setup.
  </div>

  <?php if ($success): ?>
    <div class="success">
      <strong>✅ TOTP Enrolled Successfully</strong>
      The admin account <strong><?= htmlspecialchars($admin['email']) ?></strong>
      now requires a 6-digit code at every sign-in.<br><br>
      <strong style="color:#B91C1C;font-size:13px">Delete this file now!</strong>
    </div>

  <?php elseif (!$admin): ?>
    <!-- Step 1: find the admin account -->
    <form method="GET">
      <label for="email">Admin account email</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($admin_email) ?>"
             placeholder="admin@lrmds.deped.gov.ph" required/>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <button class="btn" type="submit">Look up admin account →</button>
    </form>

  <?php else: ?>
    <!-- Step 2: scan QR + verify code -->
    <p style="font-size:14px;margin:0 0 16px">
      Setting up TOTP for <strong><?= htmlspecialchars($admin['email']) ?></strong>
      <?php if ($admin['totp_enabled']): ?>
        <span style="color:#D97706;font-size:12px">(⚠ already enrolled — this will replace the existing secret)</span>
      <?php endif; ?>
    </p>

    <?php if ($qr_url): ?>
      <div class="qr-wrap">
        <img src="<?= $qr_url ?>" alt="QR code" width="240" height="240"/>
      </div>
    <?php else: ?>
      <div class="error">Could not load QR code — use the manual key below.</div>
    <?php endif; ?>

    <p class="muted">Can't scan? Add this key manually in your app:</p>
    <div class="secret-box"><?= wordwrap(htmlspecialchars($secret), 4, ' ', true) ?></div>
    <p class="muted">Select all above to copy · Keep this key private</p>

    <hr/>

    <form method="POST">
      <input type="hidden" name="admin_email" value="<?= htmlspecialchars($admin['email']) ?>"/>
      <label for="totp_code">Enter the 6-digit code from your app to confirm:</label>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <input type="text" name="totp_code" id="totp_code"
             class="code-input"
             placeholder="000000" maxlength="6"
             inputmode="numeric" pattern="\d{6}"
             autofocus autocomplete="one-time-code"/>
      <button class="btn" type="submit" style="margin-top:14px">Confirm &amp; Enable TOTP</button>
    </form>

  <?php endif; ?>

</div>
<script>
  const inp = document.getElementById('totp_code');
  inp?.addEventListener('input', () => {
    inp.value = inp.value.replace(/\D/g, '').slice(0, 6);
    if (inp.value.length === 6) inp.closest('form').submit();
  });
</script>
</body>
</html>
