<?php
/**
 * DepEd LRMDS – verify.php
 *
 * Handles the one-click email verification link:
 *   /verify.php?token=<64-char-hex>
 *
 * On success → sets status = 'active', marks token used, redirects to signin.
 * On failure → shows a clear error with a resend option.
 */

session_start();

require_once __DIR__ . '/lib/env.php';
define('DB_CHARSET', 'utf8mb4');

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
} catch (PDOException $e) {
    error_log('LRMDS verify DB: ' . $e->getMessage());
    $state = 'db_error';
}

/* ── Token handling ─────────────────────────────────────────────────────── */
$state      = $state ?? 'unknown';
$user_email = '';

if ($state !== 'db_error') {
    $token = trim($_GET['token'] ?? '');

    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $state = 'invalid';
    } else {
        // Look up token — join to users so we get status in one query
        $stmt = $pdo->prepare('
            SELECT ev.id        AS ev_id,
                   ev.user_id,
                   ev.expires_at,
                   ev.used_at,
                   u.email,
                   u.first_name,
                   u.status     AS user_status
            FROM   email_verifications ev
            JOIN   users u ON u.id = ev.user_id
            WHERE  ev.token = ?
            LIMIT  1
        ');
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            // Token not found at all
            $state = 'invalid';

        } elseif ($row['used_at'] !== null) {
            // Already used — check if account is actually active so we can show a helpful message
            $state      = $row['user_status'] === 'active' ? 'already_verified' : 'used_invalid';
            $user_email = $row['email'];

        } elseif (strtotime($row['expires_at']) < time()) {
            // Expired
            $state      = 'expired';
            $user_email = $row['email'];

        } else {
            // ✅ Valid — activate the account and mark token used
            try {
                $pdo->beginTransaction();

                $pdo->prepare('
                    UPDATE users
                    SET    status = \'active\'
                    WHERE  id = ? AND status = \'email_pending\'
                ')->execute([$row['user_id']]);

                $pdo->prepare('
                    UPDATE email_verifications
                    SET    used_at = NOW()
                    WHERE  id = ?
                ')->execute([$row['ev_id']]);

                $pdo->commit();

                $state      = 'success';
                $user_email = $row['email'];
                $user_fname = $row['first_name'];

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('LRMDS verify activate: ' . $e->getMessage());
                $state = 'db_error';
            }
        }
    }
}

/* ── Auto-redirect on success ───────────────────────────────────────────── */
$redirect_delay = 5; // seconds before auto-redirect to signin
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Email Verification</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <?php if ($state === 'success'): ?>
  <meta http-equiv="refresh" content="<?= $redirect_delay ?>;url=signin.php"/>
  <?php endif; ?>
  <style>
    .vf-wrap {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
      background: #F8FAFC;
    }
    .vf-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 8px 40px rgba(0,0,0,.08);
      padding: 48px 44px;
      max-width: 460px;
      width: 100%;
      text-align: center;
    }
    .vf-icon {
      width: 72px; height: 72px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
    }
    .vf-icon-success  { background: #D1FAE5; }
    .vf-icon-error    { background: #FEE2E2; }
    .vf-icon-warning  { background: #FEF3C7; }
    .vf-title {
      font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 10px;
    }
    .vf-body {
      font-size: 14px; color: #6B7280; line-height: 1.65; margin: 0 0 28px;
    }
    .vf-email-chip {
      display: inline-block;
      background: #EFF6FF; border: 1px solid #BFDBFE;
      color: #1D4ED8; border-radius: 999px;
      padding: 3px 12px; font-size: 13px; font-weight: 600;
      margin-bottom: 16px;
    }
    .vf-countdown {
      font-size: 12px; color: #9CA3AF; margin-top: 12px;
    }
    .vf-countdown span { font-weight: 700; color: #059669; }
    .vf-divider {
      border: none; border-top: 1px solid #F3F4F6; margin: 24px 0;
    }
    .vf-footer {
      font-size: 12px; color: #9CA3AF; margin-top: 24px;
    }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC;">
<div class="vf-wrap">
  <div class="vf-card">

    <!-- LRMDS branding -->
    <p style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9CA3AF;margin:0 0 24px;">
      DepEd LRMDS
    </p>

    <?php if ($state === 'success'): ?>
      <!-- ✅ SUCCESS -->
      <div class="vf-icon vf-icon-success">
        <svg width="36" height="36" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </div>
      <h1 class="vf-title">Email Verified!</h1>
      <?php if (!empty($user_email)): ?>
        <span class="vf-email-chip"><?= htmlspecialchars($user_email) ?></span>
      <?php endif; ?>
      <p class="vf-body">
        Welcome to DepEd LRMDS<?= !empty($user_fname) ? ', <strong>' . htmlspecialchars($user_fname) . '</strong>' : '' ?>!
        Your account is now active. You can sign in and start accessing learning resources.
      </p>
      <a href="signin.php" class="rf-btn rf-btn-primary" style="display:inline-flex;text-decoration:none;">
        Go to Sign In
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>
      <p class="vf-countdown">
        Redirecting automatically in <span id="cd"><?= $redirect_delay ?></span>s…
      </p>

    <?php elseif ($state === 'already_verified'): ?>
      <!-- Already verified -->
      <div class="vf-icon vf-icon-success">
        <svg width="36" height="36" fill="none" stroke="#059669" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </div>
      <h1 class="vf-title">Already Verified</h1>
      <p class="vf-body">This email address has already been verified. Your account is active — you can sign in now.</p>
      <a href="signin.php" class="rf-btn rf-btn-primary" style="display:inline-flex;text-decoration:none;">
        Sign In
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>

    <?php elseif ($state === 'expired'): ?>
      <!-- Expired -->
      <div class="vf-icon vf-icon-warning">
        <svg width="36" height="36" fill="none" stroke="#D97706" stroke-width="1.8" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
      </div>
      <h1 class="vf-title">Link Expired</h1>
      <?php if (!empty($user_email)): ?>
        <span class="vf-email-chip"><?= htmlspecialchars($user_email) ?></span>
      <?php endif; ?>
      <p class="vf-body">
        This verification link has expired. Links are valid for 24 hours after registration.
        Request a new link below and we'll send a fresh one to your email address.
      </p>
      <a href="resend_verification.php?email=<?= urlencode($user_email) ?>"
         class="rf-btn rf-btn-primary" style="display:inline-flex;text-decoration:none;">
        Resend Verification Email
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>

    <?php elseif ($state === 'invalid' || $state === 'used_invalid'): ?>
      <!-- Invalid / tampered token -->
      <div class="vf-icon vf-icon-error">
        <svg width="36" height="36" fill="none" stroke="#DC2626" stroke-width="1.8" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <path stroke-linecap="round" d="M15 9l-6 6M9 9l6 6"/>
        </svg>
      </div>
      <h1 class="vf-title">Invalid Link</h1>
      <p class="vf-body">
        This verification link is not valid. It may have already been used or the URL may be incomplete.
        If you need a new link, you can request one below.
      </p>
      <a href="resend_verification.php" class="rf-btn rf-btn-primary" style="display:inline-flex;text-decoration:none;">
        Request a New Link
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
      </a>

    <?php else: ?>
      <!-- DB error or unknown -->
      <div class="vf-icon vf-icon-error">
        <svg width="36" height="36" fill="none" stroke="#DC2626" stroke-width="1.8" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/>
          <path stroke-linecap="round" d="M12 8v4M12 16h.01"/>
        </svg>
      </div>
      <h1 class="vf-title">Something Went Wrong</h1>
      <p class="vf-body">
        We couldn't process your verification right now. Please try again in a moment.
        If the problem persists, contact your administrator.
      </p>
      <a href="mailto:support@lrmds.deped.gov.ph" class="rf-btn rf-btn-primary" style="display:inline-flex;text-decoration:none;">
        Contact Support
      </a>

    <?php endif; ?>

    <hr class="vf-divider"/>
    <p class="vf-footer">
      © 2026 Department of Education ||LRMDS ·
      <a href="signin.php" style="color:#0B4F9C;font-weight:600;text-decoration:none;">Sign In</a>
    </p>

  </div>
</div>

<?php if ($state === 'success'): ?>
<script>
  const el = document.getElementById('cd');
  let s = <?= $redirect_delay ?>;
  if (el) {
    const t = setInterval(() => {
      s--;
      el.textContent = s;
      if (s <= 0) { clearInterval(t); window.location.href = 'signin.php'; }
    }, 1000);
  }
</script>
<?php endif; ?>
</body>
</html>