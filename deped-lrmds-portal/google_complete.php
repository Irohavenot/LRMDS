<?php
/**
 * DepEd LRMDS – google_complete.php
 *
 * Shown once to new Google sign-in users to confirm their name before
 * their Guest account is created. Email comes from Google and is read-only.
 *
 * Session key: $_SESSION['google_pending']
 *   google_id, email, fname, lname, picture, expires_at
 */
session_start();

define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

/* ── Guard ──────────────────────────────────────────────────── */
if (empty($_SESSION['google_pending'])) {
    header('Location: signin.php');
    exit;
}

$gp = $_SESSION['google_pending'];

if (time() > ($gp['expires_at'] ?? 0)) {
    unset($_SESSION['google_pending']);
    header('Location: signin.php?expired=1');
    exit;
}

$error  = '';
$errors = [];

/* ── Handle POST ────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname  = trim($_POST['fname']  ?? '');
    $lname  = trim($_POST['lname']  ?? '');
    $region = trim($_POST['region'] ?? '');

    if ($fname === '')  $errors['fname']  = 'First name is required.';
    if ($lname === '')  $errors['lname']  = 'Last name is required.';
    if ($region === '') $errors['region'] = 'Please select your region.';

    if (empty($errors)) {
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
            error_log('LRMDS google_complete DB: ' . $e->getMessage());
            $error = 'Database connection failed. Make sure XAMPP MySQL is running.';
            goto render;
        }

        // Final duplicate check (edge case)
        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? OR google_id = ? LIMIT 1');
        $dup->execute([$gp['email'], $gp['google_id']]);
        if ($existing = $dup->fetch()) {
            // Account appeared while they were on this page — just log them in
            unset($_SESSION['google_pending']);
            $user = $pdo->prepare('SELECT id, first_name, email, role FROM users WHERE id = ? LIMIT 1');
            $user->execute([$existing['id']]);
            $u = $user->fetch();
            $_SESSION['user_id']   = $u['id'];
            $_SESSION['user_role'] = $u['role'];
            $_SESSION['user_name'] = $u['first_name'];
            $_SESSION['user']      = $u['email'];
            header('Location: index.php');
            exit;
        }

        try {
            $stmt = $pdo->prepare('
                INSERT INTO users
                    (email, password_hash, first_name, last_name,
                     role, status, region, google_id, created_at)
                VALUES
                    (:email, :password_hash, :first_name, :last_name,
                     :role, :status, :region, :google_id, NOW())
            ');
            $stmt->execute([
                ':email'         => $gp['email'],
                ':password_hash' => '',                 // no password for OAuth users
                ':first_name'    => $fname,
                ':last_name'     => $lname,
                ':role'          => 'guest',
                ':status'        => 'active',
                ':region'        => $region,
                ':google_id'     => $gp['google_id'],
            ]);
            $new_id = (int) $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('LRMDS google_complete insert: ' . $e->getMessage());
            $error = 'Could not create your account. Please try again.';
            goto render;
        }

        unset($_SESSION['google_pending']);

        $_SESSION['user_id']   = $new_id;
        $_SESSION['user_role'] = 'guest';
        $_SESSION['user_name'] = $fname;
        $_SESSION['user']      = $gp['email'];

        $_SESSION['flash_success'] = 'Welcome to LRMDS! Your guest account is ready.';
        header('Location: index.php');
        exit;
    }
}

render:
$safe_email = htmlspecialchars($gp['email']);
$safe_fname = htmlspecialchars($gp['fname']);
$safe_lname = htmlspecialchars($gp['lname']);
$safe_pic   = htmlspecialchars($gp['picture']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Complete Your Profile</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    .gc-wrap {
      max-width: 480px;
      margin: 0 auto;
      padding: 56px 24px 72px;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }

    /* Google avatar + identity pill */
    .gc-identity {
      display: flex;
      align-items: center;
      gap: 14px;
      background: #fff;
      border: 1.5px solid #E5E7EB;
      border-radius: 14px;
      padding: 14px 18px;
      margin-bottom: 28px;
    }
    .gc-avatar {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      object-fit: cover;
      flex-shrink: 0;
      background: #E5E7EB;
    }
    .gc-avatar-placeholder {
      width: 48px;
      height: 48px;
      border-radius: 50%;
      background: linear-gradient(135deg, #0B4F9C, #3B82F6);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      color: #fff;
      font-size: 20px;
      font-weight: 700;
    }
    .gc-identity-text { flex: 1; min-width: 0; }
    .gc-identity-email {
      font-size: 14px;
      font-weight: 600;
      color: #111827;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .gc-identity-note {
      font-size: 12px;
      color: #6B7280;
      margin-top: 2px;
    }
    .gc-google-badge {
      display: flex;
      align-items: center;
      gap: 5px;
      background: #F9FAFB;
      border: 1px solid #E5E7EB;
      border-radius: 999px;
      padding: 3px 10px;
      font-size: 11px;
      font-weight: 600;
      color: #374151;
      flex-shrink: 0;
    }

    /* Guest role badge */
    .gc-role-card {
      background: #FFFBEB;
      border: 1.5px solid #FDE68A;
      border-radius: 12px;
      padding: 14px 16px;
      margin-bottom: 28px;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }
    .gc-role-icon {
      width: 38px;
      height: 38px;
      background: #FEF3C7;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .gc-role-title {
      font-size: 13px;
      font-weight: 700;
      color: #92400E;
      margin-bottom: 3px;
    }
    .gc-role-desc {
      font-size: 12px;
      color: #A16207;
      line-height: 1.5;
    }
    .gc-role-upgrade {
      font-size: 12px;
      color: #0B4F9C;
      font-weight: 600;
      text-decoration: none;
    }
    .gc-role-upgrade:hover { text-decoration: underline; }

    /* Form fields — match register.css style */
    .gc-error {
      background: #FEF2F2;
      border: 1px solid #FECACA;
      border-radius: 8px;
      padding: 10px 14px;
      font-size: 13px;
      color: #B91C1C;
      margin-bottom: 16px;
      display: flex;
      align-items: flex-start;
      gap: 8px;
    }
    .gc-field-error {
      font-size: 12px;
      color: #DC2626;
      margin-top: 4px;
      display: block;
    }
    .gc-input-locked {
      background: #F9FAFB;
      color: #6B7280;
      cursor: not-allowed;
    }

    .gc-submit {
      width: 100%;
      justify-content: center;
      margin-top: 8px;
    }
    .gc-divider {
      height: 1px;
      background: #E5E7EB;
      margin: 24px 0;
    }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC">
<div class="gc-wrap">

  <!-- Header -->
  <div style="margin-bottom:24px">
    <div style="display:inline-flex;align-items:center;gap:8px;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:999px;padding:4px 12px;font-size:12px;font-weight:600;margin-bottom:16px">
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      Almost there!
    </div>
    <h1 style="font-size:22px;font-weight:800;color:#111827;margin:0 0 6px">Complete your profile</h1>
    <p style="font-size:14px;color:#6B7280;margin:0">
      Just confirm a few details and your LRMDS guest account will be ready.
    </p>
  </div>

  <!-- Google identity pill -->
  <div class="gc-identity">
    <?php if ($safe_pic): ?>
      <img class="gc-avatar" src="<?= $safe_pic ?>" alt="Your Google profile photo"/>
    <?php else: ?>
      <div class="gc-avatar-placeholder">
        <?= mb_strtoupper(mb_substr($gp['fname'] ?: $gp['email'], 0, 1)) ?>
      </div>
    <?php endif; ?>
    <div class="gc-identity-text">
      <div class="gc-identity-email"><?= $safe_email ?></div>
      <div class="gc-identity-note">Signed in with Google · Email cannot be changed here</div>
    </div>
    <div class="gc-google-badge">
      <svg width="14" height="14" viewBox="0 0 24 24">
        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"/>
        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23Z"/>
        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62Z"/>
        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z"/>
      </svg>
      Google
    </div>
  </div>

  <!-- Guest role notice -->
  <div class="gc-role-card">
    <div class="gc-role-icon">
      <svg width="20" height="20" fill="none" stroke="#92400E" stroke-width="1.8" viewBox="0 0 24 24">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
        <circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <div>
      <div class="gc-role-title">You'll be registered as a Guest</div>
      <div class="gc-role-desc">
        Guest accounts can browse and download learning resources.
        You can upgrade to Teacher, Learner, or Parent later from your profile
        to unlock personalized feeds and additional features.
      </div>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="gc-error">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <!-- Form -->
  <form method="POST" action="google_complete.php" autocomplete="off">

    <!-- Email (locked — comes from Google) -->
    <div class="rf-group" style="margin-bottom:16px">
      <label class="rf-label" for="gc-email">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
        Email Address
      </label>
      <input class="rf-input gc-input-locked" type="email" id="gc-email"
             value="<?= $safe_email ?>" readonly tabindex="-1"/>
      <span class="rf-hint">This comes from your Google account and can't be changed here.</span>
    </div>

    <div class="gc-divider"></div>

    <!-- Name row -->
    <div class="rf-row" style="margin-bottom:4px">
      <div class="rf-group">
        <label class="rf-label" for="gc-fname">First Name <span class="rf-req">*</span></label>
        <input class="rf-input <?= isset($errors['fname']) ? 'rf-input-error' : '' ?>"
               type="text" id="gc-fname" name="fname"
               value="<?= htmlspecialchars($_POST['fname'] ?? $safe_fname) ?>"
               placeholder="Juan" autocomplete="given-name" required/>
        <?php if (isset($errors['fname'])): ?>
          <span class="gc-field-error"><?= htmlspecialchars($errors['fname']) ?></span>
        <?php endif; ?>
      </div>
      <div class="rf-group">
        <label class="rf-label" for="gc-lname">Last Name <span class="rf-req">*</span></label>
        <input class="rf-input <?= isset($errors['lname']) ? 'rf-input-error' : '' ?>"
               type="text" id="gc-lname" name="lname"
               value="<?= htmlspecialchars($_POST['lname'] ?? $safe_lname) ?>"
               placeholder="dela Cruz" autocomplete="family-name" required/>
        <?php if (isset($errors['lname'])): ?>
          <span class="gc-field-error"><?= htmlspecialchars($errors['lname']) ?></span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Region -->
    <div class="rf-group" style="margin-top:12px">
      <label class="rf-label" for="gc-region">Region <span class="rf-req">*</span></label>
      <select class="rf-select <?= isset($errors['region']) ? 'rf-input-error' : '' ?>"
              id="gc-region" name="region" required>
        <option value="">Select region…</option>
        <?php
        $regions = ['NCR','CAR','Region I','Region II','Region III','Region IV-A',
                    'Region IV-B','Region V','Region VI','Region VII','Region VIII',
                    'Region IX','Region X','Region XI','Region XII','CARAGA','BARMM'];
        $selected_region = $_POST['region'] ?? '';
        foreach ($regions as $r) {
            $sel = ($selected_region === $r) ? ' selected' : '';
            echo "<option$sel>" . htmlspecialchars($r) . "</option>\n";
        }
        ?>
      </select>
      <?php if (isset($errors['region'])): ?>
        <span class="gc-field-error"><?= htmlspecialchars($errors['region']) ?></span>
      <?php endif; ?>
    </div>

    <button type="submit" class="rf-btn rf-btn-primary gc-submit" style="margin-top:24px">
      Create Guest Account
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
    </button>

  </form>

  <p style="text-align:center;font-size:13px;color:#9CA3AF;margin-top:20px">
    Wrong Google account?
    <a href="signin.php" style="color:#0B4F9C;font-weight:600;text-decoration:none">Go back to sign in</a>
  </p>

</div>
</body>
</html>