<?php
/**
 * change_password.php
 * Password change page for signed-in users.
 *
 * Requires session_start() to have been called before include/redirect here.
 * Expects: $_SESSION['user_id'], $_SESSION['user'], $_SESSION['user_role']
 */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']) {
    header('Location: index.php?signin=1');
    exit;
}

// ── DB connection ─────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

// ── Fetch current user ────────────────────────────────────────
$user = null;
try {
    $s = $pdo->prepare('SELECT id, first_name, last_name, email, role, password_hash FROM users WHERE id = ? LIMIT 1');
    $s->execute([$_SESSION['user_id'] ?? 0]);
    $user = $s->fetch();
} catch (PDOException $e) { /* handled below */ }

if (!$user) {
    header('Location: index.php?signin=1');
    exit;
}

// ── Handle POST ───────────────────────────────────────────────
$errors   = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password']  ?? '';
    $new      = $_POST['new_password']      ?? '';
    $confirm  = $_POST['confirm_password']  ?? '';

    // Validate current password
    if (empty($current)) {
        $errors['current_password'] = 'Please enter your current password.';
    } elseif (!password_verify($current, $user['password_hash'])) {
        $errors['current_password'] = 'Current password is incorrect.';
    }

    // Validate new password
    if (empty($new)) {
        $errors['new_password'] = 'Please enter a new password.';
    } elseif (strlen($new) < 8) {
        $errors['new_password'] = 'Password must be at least 8 characters.';
    } elseif (!preg_match('/[A-Z]/', $new)) {
        $errors['new_password'] = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[0-9]/', $new)) {
        $errors['new_password'] = 'Password must contain at least one number.';
    } elseif (!preg_match('/[\W_]/', $new)) {
        $errors['new_password'] = 'Password must contain at least one special character.';
    } elseif ($new === $current) {
        $errors['new_password'] = 'New password must be different from your current password.';
    }

    // Validate confirm
    if (empty($confirm)) {
        $errors['confirm_password'] = 'Please confirm your new password.';
    } elseif ($new !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Persist if no errors
    if (empty($errors)) {
        try {
            $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
            $u = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $u->execute([$hash, $user['id']]);
            $success = true;
        } catch (PDOException $e) {
            $errors['general'] = 'Something went wrong. Please try again.';
        }
    }
}

// ── View helpers ──────────────────────────────────────────────
$display_name = htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']));
$display_email = htmlspecialchars($user['email']);

$role_map = [
    'admin'       => ['label' => 'Administrator', 'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'developer'   => ['label' => 'Developer',     'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'school-head' => ['label' => 'School Head',   'color' => '#059669', 'bg' => '#ECFDF5'],
    'teacher'     => ['label' => 'Teacher',       'color' => '#D97706', 'bg' => '#FFFBEB'],
    'learner'     => ['label' => 'Learner',       'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'parent'      => ['label' => 'Parent',        'color' => '#DB2777', 'bg' => '#FDF2F8'],
    'partner'     => ['label' => 'Partner',       'color' => '#EA580C', 'bg' => '#FFF7ED'],
];
$role_info = $role_map[$user['role']] ?? ['label' => ucfirst($user['role'] ?: 'User'), 'color' => '#6B7280', 'bg' => '#F9FAFB'];

$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password — LRMDS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ── Reset & base ───────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:        #2563EB;
      --brand-hover:  #1D4ED8;
      --brand-light:  #EFF6FF;
      --brand-ring:   rgba(37,99,235,.25);
      --surface:      #FFFFFF;
      --surface-2:    #F8FAFC;
      --border:       #E2E8F0;
      --border-focus: #2563EB;
      --text:         #0F172A;
      --text-muted:   #64748B;
      --text-subtle:  #94A3B8;
      --success:      #059669;
      --success-bg:   #ECFDF5;
      --success-ring: rgba(5,150,105,.2);
      --error:        #DC2626;
      --error-bg:     #FEF2F2;
      --error-ring:   rgba(220,38,38,.2);
      --warning:      #D97706;
      --warning-bg:   #FFFBEB;
      --radius:       12px;
      --radius-sm:    8px;
      --shadow-sm:    0 1px 2px rgba(0,0,0,.05);
      --shadow:       0 4px 16px rgba(0,0,0,.08);
      --shadow-lg:    0 8px 32px rgba(0,0,0,.10);
      --font:         'DM Sans', system-ui, sans-serif;
      --font-mono:    'DM Mono', monospace;
    }

    html { font-family: var(--font); font-size: 15px; }

    body {
      background: var(--surface-2);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Page layout ────────────────────────────────────── */
    .cp-page {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 2rem 1rem 4rem;
      gap: 1.5rem;
    }

    /* ── Back link ──────────────────────────────────────── */
    .cp-back {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      color: var(--text-muted);
      font-size: .85rem;
      font-weight: 500;
      text-decoration: none;
      padding: .4rem .6rem;
      border-radius: var(--radius-sm);
      transition: color .15s, background .15s;
      margin-bottom: 1rem;
    }
    .cp-back:hover { color: var(--brand); background: var(--brand-light); }

    /* ── Card wrapper ───────────────────────────────────── */
    .cp-wrap {
      width: 100%;
      max-width: 500px;
    }

    /* ── User summary card ──────────────────────────────── */
    .cp-user-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow-sm);
    }
    .cp-avatar {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      font-size: .95rem;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      letter-spacing: .03em;
    }
    .cp-user-info { flex: 1; min-width: 0; }
    .cp-user-name {
      font-size: 1rem;
      font-weight: 600;
      color: var(--text);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cp-user-email {
      font-size: .8rem;
      color: var(--text-muted);
      margin-top: .1rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cp-role-badge {
      font-size: .72rem;
      font-weight: 600;
      letter-spacing: .03em;
      padding: .25em .65em;
      border-radius: 100px;
      white-space: nowrap;
    }

    /* ── Main card ──────────────────────────────────────── */
    .cp-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .cp-card-header {
      padding: 1.5rem 1.75rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .cp-card-title {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: .6rem;
    }
    .cp-card-title svg { color: var(--brand); }
    .cp-card-desc {
      font-size: .83rem;
      color: var(--text-muted);
      margin-top: .35rem;
      line-height: 1.55;
    }

    .cp-card-body {
      padding: 1.75rem;
    }

    /* ── Alert banners ──────────────────────────────────── */
    .cp-alert {
      display: flex;
      align-items: flex-start;
      gap: .75rem;
      padding: .9rem 1rem;
      border-radius: var(--radius-sm);
      font-size: .85rem;
      line-height: 1.5;
      margin-bottom: 1.5rem;
      animation: fadeSlideIn .25s ease;
    }
    .cp-alert-success {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid rgba(5,150,105,.2);
    }
    .cp-alert-error {
      background: var(--error-bg);
      color: var(--error);
      border: 1px solid rgba(220,38,38,.2);
    }
    .cp-alert svg { flex-shrink: 0; margin-top: .05rem; }
    .cp-alert strong { font-weight: 600; }

    @keyframes fadeSlideIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Form elements ──────────────────────────────────── */
    .cp-form { display: flex; flex-direction: column; gap: 1.25rem; }

    .cp-field { display: flex; flex-direction: column; gap: .4rem; }

    .cp-label {
      font-size: .82rem;
      font-weight: 600;
      color: var(--text);
      letter-spacing: .02em;
    }
    .cp-label-optional {
      font-weight: 400;
      color: var(--text-subtle);
      font-size: .78rem;
      margin-left: .25rem;
    }

    .cp-input-wrap { position: relative; }

    .cp-input {
      width: 100%;
      padding: .7rem .75rem .7rem 2.6rem;
      font-family: var(--font);
      font-size: .9rem;
      color: var(--text);
      background: var(--surface);
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      outline: none;
      transition: border-color .15s, box-shadow .15s;
      appearance: none;
    }
    .cp-input::placeholder { color: var(--text-subtle); }
    .cp-input:focus {
      border-color: var(--border-focus);
      box-shadow: 0 0 0 3px var(--brand-ring);
    }
    .cp-input.has-error {
      border-color: var(--error);
      box-shadow: 0 0 0 3px var(--error-ring);
    }
    .cp-input.is-valid {
      border-color: var(--success);
      box-shadow: 0 0 0 3px var(--success-ring);
    }

    /* input icon */
    .cp-input-icon {
      position: absolute;
      left: .75rem;
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-subtle);
      pointer-events: none;
      transition: color .15s;
    }
    .cp-input-wrap:focus-within .cp-input-icon { color: var(--brand); }

    /* show/hide toggle */
    .cp-toggle-vis {
      position: absolute;
      right: .75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-subtle);
      padding: .2rem;
      border-radius: 4px;
      display: flex;
      transition: color .15s;
    }
    .cp-toggle-vis:hover { color: var(--brand); }

    /* field error message */
    .cp-field-error {
      font-size: .78rem;
      color: var(--error);
      display: flex;
      align-items: center;
      gap: .3rem;
      animation: fadeSlideIn .2s ease;
    }

    /* divider */
    .cp-divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: .25rem 0;
    }

    /* ── Password strength ──────────────────────────────── */
    .cp-strength { margin-top: .5rem; }
    .cp-strength-bar {
      height: 4px;
      border-radius: 100px;
      background: var(--border);
      overflow: hidden;
    }
    .cp-strength-fill {
      height: 100%;
      border-radius: 100px;
      transition: width .3s ease, background .3s ease;
      width: 0%;
    }
    .cp-strength-label {
      font-size: .75rem;
      color: var(--text-subtle);
      margin-top: .3rem;
      display: flex;
      align-items: center;
      gap: .35rem;
    }
    .cp-strength-label span { font-weight: 600; }

    /* ── Requirements checklist ─────────────────────────── */
    .cp-requirements {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: .3rem;
      margin-top: .6rem;
    }
    .cp-req {
      display: flex;
      align-items: center;
      gap: .45rem;
      font-size: .78rem;
      color: var(--text-subtle);
      transition: color .2s;
    }
    .cp-req.met { color: var(--success); }
    .cp-req-icon { flex-shrink: 0; transition: opacity .2s; }
    .cp-req-icon-check { display: none; }
    .cp-req.met .cp-req-icon-dot  { display: none; }
    .cp-req.met .cp-req-icon-check { display: block; }

    /* ── Submit button ──────────────────────────────────── */
    .cp-submit {
      width: 100%;
      padding: .8rem 1rem;
      background: var(--brand);
      color: #fff;
      font-family: var(--font);
      font-size: .9rem;
      font-weight: 600;
      border: none;
      border-radius: var(--radius-sm);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
      transition: background .15s, transform .1s, box-shadow .15s;
      letter-spacing: .01em;
      margin-top: .25rem;
    }
    .cp-submit:hover  { background: var(--brand-hover); box-shadow: 0 4px 12px rgba(37,99,235,.3); }
    .cp-submit:active { transform: scale(.98); }
    .cp-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    /* ── Security tips ──────────────────────────────────── */
    .cp-tips {
      background: var(--warning-bg);
      border: 1px solid rgba(217,119,6,.15);
      border-radius: var(--radius-sm);
      padding: .9rem 1rem;
      margin-top: 1.5rem;
    }
    .cp-tips-title {
      font-size: .8rem;
      font-weight: 700;
      color: var(--warning);
      display: flex;
      align-items: center;
      gap: .4rem;
      margin-bottom: .5rem;
    }
    .cp-tips-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: .3rem;
    }
    .cp-tips-list li {
      font-size: .78rem;
      color: #92400E;
      display: flex;
      align-items: flex-start;
      gap: .4rem;
      line-height: 1.5;
    }
    .cp-tips-list li::before {
      content: '•';
      flex-shrink: 0;
      margin-top: .05rem;
    }

    /* ── Success state (full-card swap) ─────────────────── */
    .cp-success-body {
      padding: 2.5rem 1.75rem;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 1rem;
    }
    .cp-success-icon {
      width: 64px; height: 64px;
      border-radius: 50%;
      background: var(--success-bg);
      display: flex; align-items: center; justify-content: center;
      color: var(--success);
      animation: popIn .35s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes popIn {
      from { transform: scale(.5); opacity: 0; }
      to   { transform: scale(1);  opacity: 1; }
    }
    .cp-success-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text);
    }
    .cp-success-desc {
      font-size: .88rem;
      color: var(--text-muted);
      line-height: 1.6;
      max-width: 300px;
    }
    .cp-success-actions {
      display: flex;
      flex-direction: column;
      gap: .6rem;
      width: 100%;
      max-width: 260px;
      margin-top: .5rem;
    }
    .cp-btn-primary {
      display: flex; align-items: center; justify-content: center; gap: .45rem;
      padding: .75rem 1rem;
      background: var(--brand);
      color: #fff;
      font-family: var(--font);
      font-size: .88rem;
      font-weight: 600;
      text-decoration: none;
      border-radius: var(--radius-sm);
      transition: background .15s;
    }
    .cp-btn-primary:hover { background: var(--brand-hover); }
    .cp-btn-ghost {
      display: flex; align-items: center; justify-content: center; gap: .45rem;
      padding: .72rem 1rem;
      background: transparent;
      color: var(--text-muted);
      font-family: var(--font);
      font-size: .88rem;
      font-weight: 500;
      text-decoration: none;
      border: 1.5px solid var(--border);
      border-radius: var(--radius-sm);
      transition: color .15s, border-color .15s;
    }
    .cp-btn-ghost:hover { color: var(--brand); border-color: var(--brand); }

    /* ── Responsive ─────────────────────────────────────── */
    @media (max-width: 540px) {
      .cp-page { padding: 1.25rem .75rem 3rem; }
      .cp-card-body { padding: 1.25rem; }
      .cp-card-header { padding: 1.25rem 1.25rem 1rem; }
    }
  </style>
</head>
<body>

<div class="cp-page">
  <div class="cp-wrap">

    <!-- Back link -->
    <a href="javascript:history.back()" class="cp-back">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Back
    </a>

    <!-- User summary -->
    <div class="cp-user-card">
      <div class="cp-avatar"><?= $initials ?></div>
      <div class="cp-user-info">
        <div class="cp-user-name"><?= $display_name ?></div>
        <div class="cp-user-email"><?= $display_email ?></div>
      </div>
      <span class="cp-role-badge" style="color:<?= $role_info['color'] ?>;background:<?= $role_info['bg'] ?>">
        <?= $role_info['label'] ?>
      </span>
    </div>

    <!-- Main card -->
    <div class="cp-card">

      <?php if ($success): ?>
      <!-- ── SUCCESS STATE ── -->
      <div class="cp-success-body">
        <div class="cp-success-icon">
          <svg width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
        </div>
        <div class="cp-success-title">Password Changed</div>
        <p class="cp-success-desc">
          Your password has been updated successfully. You'll use your new password the next time you sign in.
        </p>
        <div class="cp-success-actions">
          <a href="index.php" class="cp-btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Go to Dashboard
          </a>
          <a href="profile_edit.php" class="cp-btn-ghost">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>
            </svg>
            Edit Profile
          </a>
        </div>
      </div>

      <?php else: ?>
      <!-- ── FORM STATE ── -->

      <div class="cp-card-header">
        <div class="cp-card-title">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Change Password
        </div>
        <p class="cp-card-desc">Create a strong, unique password to keep your account secure.</p>
      </div>

      <div class="cp-card-body">

        <!-- General error -->
        <?php if (!empty($errors['general'])): ?>
        <div class="cp-alert cp-alert-error">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <div><?= htmlspecialchars($errors['general']) ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" id="cp-form" class="cp-form" novalidate autocomplete="off">

          <!-- Current password -->
          <div class="cp-field">
            <label class="cp-label" for="current_password">Current Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input
                type="password"
                id="current_password"
                name="current_password"
                class="cp-input<?= !empty($errors['current_password']) ? ' has-error' : '' ?>"
                placeholder="Enter your current password"
                autocomplete="current-password"
                required
              >
              <button type="button" class="cp-toggle-vis" data-target="current_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"    width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['current_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <?= htmlspecialchars($errors['current_password']) ?>
            </div>
            <?php endif; ?>
          </div>

          <hr class="cp-divider">

          <!-- New password -->
          <div class="cp-field">
            <label class="cp-label" for="new_password">New Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                </svg>
              </span>
              <input
                type="password"
                id="new_password"
                name="new_password"
                class="cp-input<?= !empty($errors['new_password']) ? ' has-error' : '' ?>"
                placeholder="Create a strong new password"
                autocomplete="new-password"
                id="new_password"
                required
              >
              <button type="button" class="cp-toggle-vis" data-target="new_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"    width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>

            <?php if (!empty($errors['new_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <?= htmlspecialchars($errors['new_password']) ?>
            </div>
            <?php endif; ?>

            <!-- Strength meter -->
            <div class="cp-strength" id="strength-meter" style="display:none">
              <div class="cp-strength-bar">
                <div class="cp-strength-fill" id="strength-fill"></div>
              </div>
              <div class="cp-strength-label">
                Strength: <span id="strength-text">—</span>
              </div>
            </div>

            <!-- Requirements -->
            <ul class="cp-requirements" id="pw-requirements" style="display:none">
              <li class="cp-req" id="req-length">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                At least 8 characters
              </li>
              <li class="cp-req" id="req-upper">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                One uppercase letter
              </li>
              <li class="cp-req" id="req-number">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                One number
              </li>
              <li class="cp-req" id="req-special">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>
                One special character
              </li>
            </ul>
          </div>

          <!-- Confirm password -->
          <div class="cp-field">
            <label class="cp-label" for="confirm_password">Confirm New Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M20 6 9 17l-5-5"/>
                </svg>
              </span>
              <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                class="cp-input<?= !empty($errors['confirm_password']) ? ' has-error' : '' ?>"
                placeholder="Re-enter your new password"
                autocomplete="new-password"
                required
              >
              <button type="button" class="cp-toggle-vis" data-target="confirm_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"    width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['confirm_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
              </svg>
              <?= htmlspecialchars($errors['confirm_password']) ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Submit -->
          <button type="submit" class="cp-submit" id="cp-submit">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Update Password
          </button>

        </form>

        <!-- Security tips -->
        <div class="cp-tips">
          <div class="cp-tips-title">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
            </svg>
            Security Tips
          </div>
          <ul class="cp-tips-list">
            <li>Never reuse passwords across multiple sites.</li>
            <li>Consider using a password manager to generate and store strong passwords.</li>
            <li>Enable two-factor authentication (2FA) for extra protection.</li>
          </ul>
        </div>

      </div><!-- /.cp-card-body -->
      <?php endif; ?>

    </div><!-- /.cp-card -->
  </div><!-- /.cp-wrap -->
</div><!-- /.cp-page -->

<script>
  // ── Show / hide password toggles ─────────────────────────────
  document.querySelectorAll('.cp-toggle-vis').forEach(btn => {
    btn.addEventListener('click', () => {
      const input  = document.getElementById(btn.dataset.target);
      const isText = input.type === 'text';
      input.type   = isText ? 'password' : 'text';
      btn.querySelector('.icon-eye').style.display     = isText ? 'block' : 'none';
      btn.querySelector('.icon-eye-off').style.display = isText ? 'none'  : 'block';
    });
  });

  // ── Password strength + live requirements ────────────────────
  const newInput  = document.getElementById('new_password');
  const confInput = document.getElementById('confirm_password');
  const meter     = document.getElementById('strength-meter');
  const fill      = document.getElementById('strength-fill');
  const label     = document.getElementById('strength-text');
  const reqBox    = document.getElementById('pw-requirements');

  const reqs = {
    length:  { el: document.getElementById('req-length'),  re: v => v.length >= 8 },
    upper:   { el: document.getElementById('req-upper'),   re: v => /[A-Z]/.test(v) },
    number:  { el: document.getElementById('req-number'),  re: v => /[0-9]/.test(v) },
    special: { el: document.getElementById('req-special'), re: v => /[\W_]/.test(v) },
  };

  const levels = [
    { label: 'Very Weak', color: '#EF4444', width: '15%' },
    { label: 'Weak',      color: '#F97316', width: '35%' },
    { label: 'Fair',      color: '#EAB308', width: '60%' },
    { label: 'Strong',    color: '#22C55E', width: '85%' },
    { label: 'Very Strong', color: '#059669', width: '100%' },
  ];

  function calcStrength(v) {
    let score = 0;
    if (v.length >= 8)  score++;
    if (v.length >= 12) score++;
    if (/[A-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (/[\W_]/.test(v)) score++;
    return Math.min(score, 4);
  }

  newInput.addEventListener('input', () => {
    const val = newInput.value;

    if (!val) {
      meter.style.display  = 'none';
      reqBox.style.display = 'none';
      newInput.classList.remove('is-valid', 'has-error');
      return;
    }

    meter.style.display  = 'block';
    reqBox.style.display = 'flex';

    // Requirements
    let metCount = 0;
    Object.values(reqs).forEach(r => {
      const met = r.re(val);
      r.el.classList.toggle('met', met);
      if (met) metCount++;
    });

    // Strength bar
    const lvl = levels[calcStrength(val)];
    fill.style.width      = lvl.width;
    fill.style.background = lvl.color;
    label.textContent     = lvl.label;
    label.style.color     = lvl.color;

    // Input state
    newInput.classList.toggle('is-valid',  metCount === 4);
    newInput.classList.toggle('has-error', newInput.value.length > 3 && metCount < 4);

    // Re-validate confirm if already typed
    if (confInput.value) validateConfirm();
  });

  function validateConfirm() {
    const match = confInput.value === newInput.value && confInput.value !== '';
    confInput.classList.toggle('is-valid',  match);
    confInput.classList.toggle('has-error', !match && confInput.value.length > 0);
  }

  confInput.addEventListener('input', validateConfirm);

  // ── Submit loading state ──────────────────────────────────────
  document.getElementById('cp-form')?.addEventListener('submit', function() {
    const btn = document.getElementById('cp-submit');
    btn.disabled = true;
    btn.innerHTML = `
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite">
        <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
      </svg>
      Updating…`;
  });
</script>

<style>
  @keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>
</html>