<?php
/**
 * profile_edit.php
 * Full profile editor — companion to profile_panel.php.
 * Allows signed-in users to update their own account details.
 *
 * Requires: session_start() already called, $_SESSION['user_id'] set.
 */

session_start();
if (!isset($_SESSION['user']) || !$_SESSION['user']) {
    header('Location: login.php');
    exit;
}

// ── PDO helper ────────────────────────────────────────────────
function get_pdo(): PDO {
    return new PDO(
        'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
}

$uid      = (int) ($_SESSION['user_id'] ?? 0);
$errors   = [];
$success  = false;
$user     = null;

// ── Fetch current user ────────────────────────────────────────
try {
    $pdo = get_pdo();
    $s = $pdo->prepare('
        SELECT id, first_name, last_name, email, role, status,
               region, division, employee_id, created_at, last_login,
               totp_enabled, meta
        FROM   users WHERE id = ? LIMIT 1
    ');
    $s->execute([$uid]);
    $user = $s->fetch();
} catch (PDOException $e) {
    $errors[] = 'Could not load your profile. Please try again later.';
}

if (!$user) {
    $errors[] = 'User record not found.';
}

$meta = [];
if (!empty($user['meta'])) {
    $meta = json_decode($user['meta'], true) ?: [];
}

// ── Handle POST ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user && empty($errors)) {
    // Sanitise scalar fields
    $first_name  = trim($_POST['first_name']  ?? '');
    $last_name   = trim($_POST['last_name']   ?? '');
    $email       = trim($_POST['email']       ?? '');
    $region      = trim($_POST['region']      ?? '');
    $division    = trim($_POST['division']    ?? '');
    $employee_id = trim($_POST['employee_id'] ?? '');

    // Role-specific meta
    $new_meta = $meta; // preserve any existing keys we don't touch
    $new_meta['grade_level']  = trim($_POST['grade_level']  ?? '');
    $new_meta['subjects']     = trim($_POST['subjects']      ?? '');
    $new_meta['school_name']  = trim($_POST['school_name']  ?? '');
    $new_meta['lrn']          = trim($_POST['lrn']           ?? '');
    $new_meta['child_grade']  = trim($_POST['child_grade']  ?? '');
    $new_meta['child_school'] = trim($_POST['child_school'] ?? '');
    // Remove blank meta keys to keep JSON tidy
    $new_meta = array_filter($new_meta, fn($v) => $v !== '');

    // Validation
    if ($first_name === '') $errors[] = 'First name is required.';
    if ($last_name  === '') $errors[] = 'Last name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';

    // Email uniqueness (allow keeping own email)
    if (empty($errors)) {
        try {
            $chk = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $chk->execute([$email, $uid]);
            if ($chk->fetch()) $errors[] = 'That email address is already in use by another account.';
        } catch (PDOException $e) {
            $errors[] = 'Could not verify email uniqueness.';
        }
    }

    if (empty($errors)) {
        try {
            $upd = $pdo->prepare('
                UPDATE users
                SET    first_name  = ?,
                       last_name   = ?,
                       email       = ?,
                       region      = ?,
                       division    = ?,
                       employee_id = ?,
                       meta        = ?
                WHERE  id = ?
            ');
            $upd->execute([
                $first_name, $last_name, $email,
                $region, $division, $employee_id,
                json_encode($new_meta),
                $uid
            ]);

            // Refresh session display name
            $_SESSION['user']      = $email;
            $_SESSION['user_name'] = $first_name;

            // Re-fetch updated user
            $s->execute([$uid]);
            $user    = $s->fetch();
            $meta    = json_decode($user['meta'], true) ?: [];
            $success = true;
        } catch (PDOException $e) {
            $errors[] = 'Could not save changes. Please try again.';
        }
    }
}

// ── View helpers ──────────────────────────────────────────────
$role     = $user['role'] ?? $_SESSION['user_role'] ?? '';
$role_map = [
    'admin'       => ['label' => 'Administrator',  'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'developer'   => ['label' => 'Developer',      'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'school-head' => ['label' => 'School Head',    'color' => '#059669', 'bg' => '#ECFDF5'],
    'teacher'     => ['label' => 'Teacher',        'color' => '#D97706', 'bg' => '#FFFBEB'],
    'learner'     => ['label' => 'Learner',        'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'parent'      => ['label' => 'Parent',         'color' => '#DB2777', 'bg' => '#FDF2F8'],
    'partner'     => ['label' => 'Partner',        'color' => '#EA580C', 'bg' => '#FFF7ED'],
];
$role_info = $role_map[$role] ?? ['label' => ucfirst($role ?: 'User'), 'color' => '#6B7280', 'bg' => '#F9FAFB'];

$initials = strtoupper(
    substr($user['first_name'] ?? $_SESSION['user_name'] ?? 'U', 0, 1) .
    substr($user['last_name'] ?? '', 0, 1)
);

$v = fn(string $field, string $fallback = '') =>
    htmlspecialchars($_POST[$field] ?? $user[$field] ?? $fallback);

$vm = fn(string $key) =>
    htmlspecialchars($_POST[$key] ?? $meta[$key] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile — LRMDS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ── Reset & base ───────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --brand:      #4F46E5;
  --brand-soft: #EEF2FF;
  --surface:    #FFFFFF;
  --bg:         #F5F6FA;
  --border:     #E5E7EB;
  --text:       #111827;
  --muted:      #6B7280;
  --danger:     #DC2626;
  --danger-bg:  #FEF2F2;
  --success:    #059669;
  --success-bg: #ECFDF5;
  --radius:     12px;
  --shadow:     0 1px 3px rgba(0,0,0,.08), 0 4px 16px rgba(0,0,0,.06);
  --shadow-lg:  0 8px 32px rgba(0,0,0,.12);
  --font:       'DM Sans', sans-serif;
  --mono:       'DM Mono', monospace;
}

html { font-size: 15px; -webkit-font-smoothing: antialiased; }
body {
  font-family: var(--font);
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
}

/* ── Layout ─────────────────────────────────────────────── */
.pe-shell {
  max-width: 780px;
  margin: 0 auto;
  padding: 2rem 1rem 4rem;
}

/* ── Back link ──────────────────────────────────────────── */
.pe-back {
  display: inline-flex;
  align-items: center;
  gap: .4rem;
  font-size: .82rem;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  margin-bottom: 1.5rem;
  transition: color .15s;
}
.pe-back:hover { color: var(--brand); }
.pe-back svg { flex-shrink: 0; }

/* ── Page title bar ─────────────────────────────────────── */
.pe-title-bar {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 2rem;
}

.pe-avatar {
  width: 58px; height: 58px;
  border-radius: 50%;
  background: var(--brand);
  color: #fff;
  font-size: 1.3rem;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  letter-spacing: -.5px;
  box-shadow: 0 0 0 3px var(--brand-soft), 0 0 0 5px rgba(79,70,229,.15);
}

.pe-title-info h1 {
  font-size: 1.35rem;
  font-weight: 600;
  line-height: 1.2;
}
.pe-title-info p {
  font-size: .82rem;
  color: var(--muted);
  margin-top: .2rem;
}
.pe-role-pill {
  display: inline-block;
  padding: .2rem .65rem;
  border-radius: 99px;
  font-size: .72rem;
  font-weight: 600;
  letter-spacing: .02em;
  margin-top: .35rem;
}

/* ── Alerts ─────────────────────────────────────────────── */
.pe-alert {
  border-radius: var(--radius);
  padding: .85rem 1rem;
  margin-bottom: 1.5rem;
  font-size: .88rem;
  display: flex;
  align-items: flex-start;
  gap: .6rem;
  animation: slideDown .25s ease;
}
.pe-alert.is-error   { background: var(--danger-bg);  color: var(--danger);  border: 1px solid #FECACA; }
.pe-alert.is-success { background: var(--success-bg); color: var(--success); border: 1px solid #A7F3D0; }
.pe-alert ul { list-style: disc; padding-left: 1rem; }
.pe-alert ul li + li { margin-top: .25rem; }

@keyframes slideDown {
  from { opacity: 0; transform: translateY(-6px); }
  to   { opacity: 1; transform: translateY(0); }
}

/* ── Card ───────────────────────────────────────────────── */
.pe-card {
  background: var(--surface);
  border-radius: var(--radius);
  border: 1px solid var(--border);
  box-shadow: var(--shadow);
  overflow: hidden;
  margin-bottom: 1.25rem;
}

.pe-card-header {
  padding: 1rem 1.4rem;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: .6rem;
  background: #FAFAFA;
}
.pe-card-header svg { color: var(--brand); flex-shrink: 0; }
.pe-card-header h2 {
  font-size: .9rem;
  font-weight: 600;
  color: var(--text);
}
.pe-card-header span {
  font-size: .75rem;
  color: var(--muted);
  margin-left: auto;
}

.pe-card-body { padding: 1.4rem; }

/* ── Form grid ──────────────────────────────────────────── */
.pe-grid { display: grid; gap: 1rem; }
.pe-grid-2 { grid-template-columns: 1fr 1fr; }
@media (max-width: 560px) { .pe-grid-2 { grid-template-columns: 1fr; } }
.pe-col-full { grid-column: 1 / -1; }

/* ── Field ──────────────────────────────────────────────── */
.pe-field { display: flex; flex-direction: column; gap: .35rem; }

.pe-label {
  font-size: .78rem;
  font-weight: 600;
  color: var(--text);
  letter-spacing: .02em;
}
.pe-label .req { color: var(--brand); margin-left: 2px; }

.pe-input, .pe-select, .pe-textarea {
  width: 100%;
  padding: .6rem .85rem;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font: inherit;
  font-size: .9rem;
  color: var(--text);
  background: var(--surface);
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  appearance: none;
}
.pe-input:focus, .pe-select:focus, .pe-textarea:focus {
  border-color: var(--brand);
  box-shadow: 0 0 0 3px rgba(79,70,229,.12);
}
.pe-input::placeholder, .pe-textarea::placeholder { color: #C4C8D4; }
.pe-input.is-readonly {
  background: #F9FAFB;
  color: var(--muted);
  cursor: default;
}
.pe-select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right .75rem center;
  padding-right: 2.2rem;
  cursor: pointer;
}
.pe-textarea { resize: vertical; min-height: 80px; }

.pe-hint {
  font-size: .73rem;
  color: var(--muted);
  line-height: 1.4;
}

/* ── Readonly info row ──────────────────────────────────── */
.pe-readonly-row {
  display: flex;
  align-items: center;
  gap: .5rem;
  padding: .55rem .85rem;
  background: #F9FAFB;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-size: .88rem;
  color: var(--muted);
  font-family: var(--mono);
  font-size: .82rem;
}
.pe-readonly-row svg { flex-shrink: 0; }

/* ── Security links ─────────────────────────────────────── */
.pe-security-links {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: .75rem;
  margin-top: .25rem;
}
.pe-sec-link {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .7rem 1rem;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  text-decoration: none;
  color: var(--text);
  font-size: .85rem;
  font-weight: 500;
  transition: border-color .15s, background .15s, transform .1s;
}
.pe-sec-link:hover {
  border-color: var(--brand);
  background: var(--brand-soft);
  color: var(--brand);
  transform: translateY(-1px);
}
.pe-sec-link svg { flex-shrink: 0; }
.pe-sec-link .pe-sec-sub {
  font-size: .72rem;
  font-weight: 400;
  color: var(--muted);
  display: block;
  margin-top: .1rem;
}
.pe-sec-link:hover .pe-sec-sub { color: var(--brand); opacity: .8; }

/* ── 2FA badge ──────────────────────────────────────────── */
.pe-2fa-on {
  display: inline-flex;
  align-items: center;
  gap: .3rem;
  padding: .2rem .6rem;
  background: #D1FAE5;
  color: #065F46;
  border-radius: 99px;
  font-size: .72rem;
  font-weight: 600;
  margin-left: .5rem;
}

/* ── Footer actions ─────────────────────────────────────── */
.pe-form-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: .75rem;
  padding: 1rem 1.4rem;
  border-top: 1px solid var(--border);
  background: #FAFAFA;
}

.pe-btn {
  display: inline-flex;
  align-items: center;
  gap: .45rem;
  padding: .6rem 1.3rem;
  border-radius: 8px;
  font: inherit;
  font-size: .88rem;
  font-weight: 600;
  cursor: pointer;
  border: none;
  transition: background .15s, transform .1s, box-shadow .15s;
  text-decoration: none;
}
.pe-btn:active { transform: scale(.97); }

.pe-btn-primary {
  background: var(--brand);
  color: #fff;
  box-shadow: 0 1px 4px rgba(79,70,229,.25);
}
.pe-btn-primary:hover { background: #4338CA; box-shadow: 0 3px 10px rgba(79,70,229,.3); }

.pe-btn-ghost {
  background: transparent;
  color: var(--muted);
  border: 1.5px solid var(--border);
}
.pe-btn-ghost:hover { background: var(--bg); color: var(--text); }

/* ── Spinner (submit state) ─────────────────────────────── */
.pe-spinner {
  width: 15px; height: 15px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .6s linear infinite;
  display: none;
}
@keyframes spin { to { transform: rotate(360deg); } }
.is-loading .pe-spinner { display: block; }
.is-loading .pe-btn-label { display: none; }
</style>
</head>
<body>

<div class="pe-shell">

  <!-- Back -->
  <a href="javascript:history.back()" class="pe-back">
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
      <path d="m15 18-6-6 6-6"/>
    </svg>
    Back
  </a>

  <!-- Title bar -->
  <div class="pe-title-bar">
    <div class="pe-avatar"><?= $initials ?></div>
    <div class="pe-title-info">
      <h1>Edit Profile</h1>
      <p><?= htmlspecialchars($user['email'] ?? '') ?></p>
      <span class="pe-role-pill" style="color:<?= $role_info['color'] ?>;background:<?= $role_info['bg'] ?>">
        <?= $role_info['label'] ?>
      </span>
    </div>
  </div>

  <!-- Alerts -->
  <?php if ($success): ?>
  <div class="pe-alert is-success" role="alert">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px">
      <path d="M20 6 9 17l-5-5"/>
    </svg>
    <span>Your profile has been updated successfully.</span>
  </div>
  <?php elseif (!empty($errors)): ?>
  <div class="pe-alert is-error" role="alert">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px">
      <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
    </svg>
    <div>
      <?php if (count($errors) === 1): ?>
        <?= htmlspecialchars($errors[0]) ?>
      <?php else: ?>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <form method="POST" id="pe-form" novalidate>

    <!-- ── Personal Information ── -->
    <div class="pe-card">
      <div class="pe-card-header">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
        <h2>Personal Information</h2>
      </div>
      <div class="pe-card-body">
        <div class="pe-grid pe-grid-2">
          <div class="pe-field">
            <label class="pe-label" for="first_name">First Name <span class="req">*</span></label>
            <input id="first_name" name="first_name" type="text" class="pe-input"
                   value="<?= $v('first_name') ?>" placeholder="e.g. Maria" required>
          </div>
          <div class="pe-field">
            <label class="pe-label" for="last_name">Last Name <span class="req">*</span></label>
            <input id="last_name" name="last_name" type="text" class="pe-input"
                   value="<?= $v('last_name') ?>" placeholder="e.g. Santos" required>
          </div>
          <div class="pe-field pe-col-full">
            <label class="pe-label" for="email">Email Address <span class="req">*</span></label>
            <input id="email" name="email" type="email" class="pe-input"
                   value="<?= $v('email') ?>" placeholder="you@example.com" required>
            <span class="pe-hint">Changing your email will update your login credentials.</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Organisation Details ── -->
    <div class="pe-card">
      <div class="pe-card-header">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <h2>Organisation Details</h2>
      </div>
      <div class="pe-card-body">
        <div class="pe-grid pe-grid-2">

          <div class="pe-field">
            <label class="pe-label" for="region">Region</label>
            <select id="region" name="region" class="pe-select">
              <option value="">— Select region —</option>
              <?php
              $regions = [
                'Region I'    => 'Region I – Ilocos Region',
                'Region II'   => 'Region II – Cagayan Valley',
                'Region III'  => 'Region III – Central Luzon',
                'Region IV-A' => 'Region IV-A – CALABARZON',
                'Region IV-B' => 'Region IV-B – MIMAROPA',
                'Region V'    => 'Region V – Bicol Region',
                'Region VI'   => 'Region VI – Western Visayas',
                'Region VII'  => 'Region VII – Central Visayas',
                'Region VIII' => 'Region VIII – Eastern Visayas',
                'Region IX'   => 'Region IX – Zamboanga Peninsula',
                'Region X'    => 'Region X – Northern Mindanao',
                'Region XI'   => 'Region XI – Davao Region',
                'Region XII'  => 'Region XII – SOCCSKSARGEN',
                'NCR'         => 'NCR – National Capital Region',
                'CAR'         => 'CAR – Cordillera Administrative Region',
                'BARMM'       => 'BARMM',
                'Region XIII' => 'Region XIII – CARAGA',
              ];
              $sel_region = $v('region');
              foreach ($regions as $val => $label): ?>
                <option value="<?= htmlspecialchars($val) ?>"
                  <?= $sel_region === $val ? 'selected' : '' ?>>
                  <?= htmlspecialchars($label) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="pe-field">
            <label class="pe-label" for="division">Division / District</label>
            <input id="division" name="division" type="text" class="pe-input"
                   value="<?= $v('division') ?>" placeholder="e.g. Bacolod City Division">
          </div>

          <div class="pe-field">
            <label class="pe-label" for="employee_id">Employee ID</label>
            <input id="employee_id" name="employee_id" type="text" class="pe-input"
                   value="<?= $v('employee_id') ?>" placeholder="e.g. EMP-12345">
          </div>

          <!-- Role (read-only) -->
          <div class="pe-field">
            <label class="pe-label">Account Role</label>
            <div class="pe-readonly-row">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
              </svg>
              <?= $role_info['label'] ?>
            </div>
            <span class="pe-hint">Role is managed by administrators.</span>
          </div>

        </div>
      </div>
    </div>

    <!-- ── Role-specific extra fields ── -->
    <?php
    $show_teacher     = in_array($role, ['teacher', 'school-head']);
    $show_learner     = $role === 'learner';
    $show_parent      = $role === 'parent';
    $show_extra       = $show_teacher || $show_learner || $show_parent;
    if ($show_extra):
    ?>
    <div class="pe-card">
      <div class="pe-card-header">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>
        </svg>
        <h2><?= $role_info['label'] ?> Information</h2>
      </div>
      <div class="pe-card-body">
        <div class="pe-grid pe-grid-2">

          <?php if ($show_teacher): ?>
          <div class="pe-field">
            <label class="pe-label" for="school_name">School Name</label>
            <input id="school_name" name="school_name" type="text" class="pe-input"
                   value="<?= $vm('school_name') ?>" placeholder="e.g. Bacolod City National High School">
          </div>
          <div class="pe-field">
            <label class="pe-label" for="grade_level">Grade Level Handled</label>
            <select id="grade_level" name="grade_level" class="pe-select">
              <option value="">— Select —</option>
              <?php
              $grades = ['Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6',
                         'Grade 7','Grade 8','Grade 9','Grade 10','Grade 11','Grade 12'];
              $sel_gl = $vm('grade_level');
              foreach ($grades as $g):
                  $v_g = strtolower(str_replace(' ', '-', $g));
              ?>
                <option value="<?= $v_g ?>" <?= strcasecmp($sel_gl, $v_g) === 0 ? 'selected' : '' ?>>
                  <?= $g ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pe-field pe-col-full">
            <label class="pe-label" for="subjects">Subjects Taught</label>
            <input id="subjects" name="subjects" type="text" class="pe-input"
                   value="<?= $vm('subjects') ?>" placeholder="e.g. Math, Science, English (comma-separated)">
            <span class="pe-hint">Separate multiple subjects with commas.</span>
          </div>
          <?php endif; ?>

          <?php if ($show_learner): ?>
          <div class="pe-field">
            <label class="pe-label" for="lrn">Learner Reference Number (LRN)</label>
            <input id="lrn" name="lrn" type="text" class="pe-input"
                   value="<?= $vm('lrn') ?>" placeholder="12-digit LRN" maxlength="12">
          </div>
          <div class="pe-field">
            <label class="pe-label" for="grade_level">Grade Level</label>
            <select id="grade_level" name="grade_level" class="pe-select">
              <option value="">— Select —</option>
              <?php
              $sel_gl = $vm('grade_level');
              foreach ($grades as $g):
                  $v_g = strtolower(str_replace(' ', '-', $g));
              ?>
                <option value="<?= $v_g ?>" <?= strcasecmp($sel_gl, $v_g) === 0 ? 'selected' : '' ?>>
                  <?= $g ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pe-field pe-col-full">
            <label class="pe-label" for="school_name">School Enrolled In</label>
            <input id="school_name" name="school_name" type="text" class="pe-input"
                   value="<?= $vm('school_name') ?>" placeholder="e.g. Rizal Elementary School">
          </div>
          <?php endif; ?>

          <?php if ($show_parent): ?>
          <div class="pe-field">
            <label class="pe-label" for="child_grade">Child's Grade Level</label>
            <select id="child_grade" name="child_grade" class="pe-select">
              <option value="">— Select —</option>
              <?php
              $sel_cg = $vm('child_grade');
              foreach ($grades as $g):
                  $v_g = strtolower(str_replace(' ', '-', $g));
              ?>
                <option value="<?= $v_g ?>" <?= strcasecmp($sel_cg, $v_g) === 0 ? 'selected' : '' ?>>
                  <?= $g ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="pe-field">
            <label class="pe-label" for="child_school">Child's School</label>
            <input id="child_school" name="child_school" type="text" class="pe-input"
                   value="<?= $vm('child_school') ?>" placeholder="e.g. Rizal Elementary School">
          </div>
          <?php endif; ?>

        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Account Security ── -->
    <div class="pe-card">
      <div class="pe-card-header">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
        <h2>Account Security</h2>
        <?php if ($user['totp_enabled']): ?>
        <span class="pe-2fa-on">
          <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
          2FA Enabled
        </span>
        <?php endif; ?>
      </div>
      <div class="pe-card-body">
        <p style="font-size:.85rem;color:var(--muted);margin-bottom:1rem;line-height:1.6">
          Manage your password and two-factor authentication from the links below.
          These are handled on separate, dedicated pages for security.
        </p>
        <div class="pe-security-links">
          <a href="change_password.php" class="pe-sec-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <span>
              Change Password
              <span class="pe-sec-sub">Update your login password</span>
            </span>
          </a>
          <a href="totp_setup.php" class="pe-sec-link">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
            </svg>
            <span>
              <?= $user['totp_enabled'] ? 'Manage 2FA' : 'Enable 2FA' ?>
              <span class="pe-sec-sub"><?= $user['totp_enabled'] ? 'View / revoke authenticator' : 'Add authenticator app' ?></span>
            </span>
          </a>
        </div>
      </div>
    </div>

    <!-- ── Read-only system info ── -->
    <div class="pe-card">
      <div class="pe-card-header">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>
        </svg>
        <h2>Account Info</h2>
        <span>Read-only</span>
      </div>
      <div class="pe-card-body">
        <div class="pe-grid pe-grid-2">
          <div class="pe-field">
            <label class="pe-label">Member Since</label>
            <div class="pe-readonly-row">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
              </svg>
              <?= $user['created_at'] ? date('F j, Y', strtotime($user['created_at'])) : '—' ?>
            </div>
          </div>
          <div class="pe-field">
            <label class="pe-label">Last Login</label>
            <div class="pe-readonly-row">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
              </svg>
              <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : '—' ?>
            </div>
          </div>
          <div class="pe-field">
            <label class="pe-label">Account Status</label>
            <?php
            $status_map = [
                'active'        => ['label' => 'Active',           'color' => '#065F46', 'bg' => '#D1FAE5'],
                'pending'       => ['label' => 'Pending Approval', 'color' => '#92400E', 'bg' => '#FEF3C7'],
                'email_pending' => ['label' => 'Email Unverified', 'color' => '#1E40AF', 'bg' => '#DBEAFE'],
                'suspended'     => ['label' => 'Suspended',        'color' => '#991B1B', 'bg' => '#FEE2E2'],
            ];
            $si = $status_map[$user['status'] ?? ''] ?? ['label' => ucfirst($user['status'] ?? ''), 'color' => '#374151', 'bg' => '#F3F4F6'];
            ?>
            <div class="pe-readonly-row" style="font-family:var(--font);font-size:.82rem">
              <span style="display:inline-block;padding:.15rem .55rem;border-radius:99px;
                           color:<?= $si['color'] ?>;background:<?= $si['bg'] ?>;font-size:.75rem;font-weight:600">
                <?= $si['label'] ?>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Footer ── -->
    <div class="pe-card" style="margin-bottom:0">
      <div class="pe-form-footer">
        <a href="javascript:history.back()" class="pe-btn pe-btn-ghost">
          Cancel
        </a>
        <button type="submit" class="pe-btn pe-btn-primary" id="pe-submit">
          <span class="pe-spinner" id="pe-spinner"></span>
          <span class="pe-btn-label">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2Z"/>
              <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
            </svg>
            Save Changes
          </span>
        </button>
      </div>
    </div>

  </form>

</div>

<script>
// Show loading state on submit
document.getElementById('pe-form').addEventListener('submit', function(e) {
  const btn = document.getElementById('pe-submit');
  btn.classList.add('is-loading');
  btn.disabled = true;
});

// Auto-dismiss success alert after 5s
const alert = document.querySelector('.pe-alert.is-success');
if (alert) {
  setTimeout(() => {
    alert.style.transition = 'opacity .4s ease, max-height .4s ease, margin .4s ease';
    alert.style.opacity = '0';
    alert.style.maxHeight = '0';
    alert.style.overflow = 'hidden';
    alert.style.marginBottom = '0';
  }, 5000);
}
</script>

</body>
</html>