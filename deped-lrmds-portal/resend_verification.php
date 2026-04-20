<?php
/**
 * DepEd LRMDS – resend_verification.php
 *
 * Lets a user with status = 'email_pending' request a fresh verification link.
 * Accessed via:
 *   - verify.php  (expired / invalid link buttons)
 *   - registration_pending.php  ("Resend" button)
 *   - Direct visit with ?email=... pre-filled
 *
 * Rate limited: one resend per 2 minutes (stored in session).
 */

session_start();

require_once __DIR__ . '/lib/env.php';
define('DB_CHARSET', 'utf8mb4');
define('RESEND_COOLDOWN', 120); // seconds between resend attempts

$prefill_email = trim($_GET['email'] ?? '');
$message       = null;   // ['type'=>'success'|'error'|'warning', 'text'=>'...']

/* ── DB ─────────────────────────────────────────────────────────────────── */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', env('DB_HOST','localhost'), env('DB_NAME','lrmds'), DB_CHARSET),
        env('DB_USER','root'), env('DB_PASS',''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $db_ok = true;
} catch (PDOException $e) {
    error_log('LRMDS resend_verification DB: ' . $e->getMessage());
    $db_ok = false;
}

/* ── Handle POST ────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = ['type' => 'error', 'text' => 'Please enter a valid email address.'];

    } else {
        // Rate-limit check (session-based, per email)
        $rl_key   = 'resend_ts_' . md5($email);
        $last_ts  = $_SESSION[$rl_key] ?? 0;
        $wait     = RESEND_COOLDOWN - (time() - $last_ts);

        if ($wait > 0) {
            $message = [
                'type' => 'warning',
                'text' => "Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . " before requesting another link.",
            ];
        } else {
            // Look up user
            $stmt = $pdo->prepare('
                SELECT id, first_name, status FROM users WHERE email = ? LIMIT 1
            ');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Don't reveal whether email exists — same message either way
                $message = ['type' => 'success', 'text' => 'If that email is registered and awaiting verification, a new link has been sent.'];

            } elseif ($user['status'] === 'active') {
                $message = ['type' => 'warning', 'text' => 'This account is already verified. You can sign in now.'];

            } elseif ($user['status'] !== 'email_pending') {
                // pending admin approval, banned, etc.
                $message = ['type' => 'warning', 'text' => 'This account does not need email verification. Contact support if you have questions.'];

            } else {
                require_once __DIR__ . '/lib/send_verification_email.php';
                [$ok, $err] = send_verification_email($pdo, (int)$user['id'], $email, $user['first_name']);

                $_SESSION[$rl_key] = time(); // record timestamp regardless of outcome

                if ($ok) {
                    $message = ['type' => 'success', 'text' => 'A new verification link has been sent to ' . htmlspecialchars($email) . '. Please check your inbox (and spam folder).'];
                } else {
                    error_log("LRMDS resend fail for {$email}: {$err}");
                    $message = ['type' => 'error', 'text' => 'Could not send the email right now. Please try again in a few minutes or contact support.'];
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Resend Verification</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    .rv-wrap {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      padding: 40px 20px; background: #F8FAFC;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .rv-card {
      background: #fff; border-radius: 20px;
      box-shadow: 0 8px 40px rgba(0,0,0,.08);
      padding: 48px 44px; max-width: 440px; width: 100%;
    }
    .rv-icon {
      width: 60px; height: 60px; border-radius: 50%; background: #EFF6FF;
      display: flex; align-items: center; justify-content: center; margin: 0 0 20px;
    }
    .rv-msg {
      border-radius: 10px; padding: 12px 16px; font-size: 13.5px; line-height: 1.55;
      margin-bottom: 20px; display: flex; gap: 10px; align-items: flex-start;
    }
    .rv-msg-success { background: #D1FAE5; color: #065F46; border: 1px solid #A7F3D0; }
    .rv-msg-error   { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
    .rv-msg-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC;">
<div class="rv-wrap">
  <div class="rv-card">

    <div class="rv-icon">
      <svg width="28" height="28" fill="none" stroke="#0B4F9C" stroke-width="1.8" viewBox="0 0 24 24">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <path d="m2 7 10 7 10-7"/>
      </svg>
    </div>

    <h1 style="font-size:22px;font-weight:800;color:#111827;margin:0 0 8px;">Resend Verification Email</h1>
    <p style="font-size:14px;color:#6B7280;margin:0 0 24px;line-height:1.6;">
      Enter the email address you registered with and we'll send a new verification link.
    </p>

    <?php if ($message): ?>
    <div class="rv-msg rv-msg-<?= htmlspecialchars($message['type']) ?>">
      <?php if ($message['type'] === 'success'): ?>
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
      <?php else: ?>
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?php endif; ?>
      <?= $message['text'] /* already htmlspecialchars'd above */ ?>
    </div>
    <?php endif; ?>

    <?php if (!$message || $message['type'] !== 'success'): ?>
    <form method="POST" action="resend_verification.php" autocomplete="off">
      <div class="rf-group" style="margin-bottom:20px;">
        <label class="rf-label" for="resend-email">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
          Email Address <span class="rf-req">*</span>
        </label>
        <input class="rf-input" type="email" id="resend-email" name="email"
               value="<?= htmlspecialchars($prefill_email) ?>"
               placeholder="yourname@deped.gov.ph" required autofocus/>
      </div>
      <button type="submit" class="rf-btn rf-btn-primary" style="width:100%;justify-content:center;">
        Send Verification Link
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </button>
    </form>
    <?php else: ?>
    <a href="signin.php" class="rf-btn rf-btn-primary" style="display:flex;justify-content:center;text-decoration:none;">
      Go to Sign In
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>

    <p style="text-align:center;font-size:13px;color:#9CA3AF;margin-top:20px;">
      <a href="signin.php" style="color:#0B4F9C;font-weight:600;text-decoration:none;">← Back to sign in</a>
    </p>
  </div>
</div>
</body>
</html>