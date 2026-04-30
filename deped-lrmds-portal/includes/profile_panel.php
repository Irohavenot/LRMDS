<?php
/**
 * profile_panel.php
 * Slide-in profile panel (TikTok-style) shown when signed-in users
 * click their avatar/account button.
 *
 * Include ONCE per page, after session_start() has been called.
 * Requires: $_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['user']
 *
 * Fetches fresh user data from DB on every page load so the panel
 * always shows up-to-date info.
 */

if (!isset($_SESSION['user']) || !$_SESSION['user']) return;

// ── DB fetch ──────────────────────────────────────────────────
$panel_user = null;
try {
    $panel_pdo = new PDO(
        'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
    $s = $panel_pdo->prepare('
        SELECT id, first_name, last_name, email, role, status,
               region, division, employee_id, created_at, last_login,
               totp_enabled, meta
        FROM   users
        WHERE  id = ?
        LIMIT  1
    ');
    $s->execute([$_SESSION['user_id'] ?? 0]);
    $panel_user = $s->fetch();
} catch (PDOException $e) {
    // Graceful fallback — panel will render with session data only
}

// ── Helpers ───────────────────────────────────────────────────
$pu_first  = htmlspecialchars($panel_user['first_name'] ?? $_SESSION['user_name'] ?? 'User');
$pu_last   = htmlspecialchars($panel_user['last_name']  ?? '');
$pu_email  = htmlspecialchars($panel_user['email']      ?? $_SESSION['user'] ?? '');
$pu_role   = $panel_user['role']   ?? $_SESSION['user_role'] ?? '';
$pu_status = $panel_user['status'] ?? 'active';
$pu_region = htmlspecialchars($panel_user['region']   ?? '');
$pu_div    = htmlspecialchars($panel_user['division'] ?? '');
$pu_empid  = htmlspecialchars($panel_user['employee_id'] ?? '');
$pu_joined = $panel_user['created_at'] ? date('M j, Y', strtotime($panel_user['created_at'])) : '—';
$pu_login  = $panel_user['last_login']  ? date('M j, Y g:i A', strtotime($panel_user['last_login'])) : '—';
$pu_totp   = !empty($panel_user['totp_enabled']);

// Parse meta JSON for extra profile fields
$pu_meta = [];
if (!empty($panel_user['meta'])) {
    $pu_meta = json_decode($panel_user['meta'], true) ?: [];
}

// Role display label + color
$role_map = [
    'admin'       => ['label' => 'Administrator',  'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'developer'   => ['label' => 'Developer',      'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'school-head' => ['label' => 'School Head',    'color' => '#059669', 'bg' => '#ECFDF5'],
    'teacher'     => ['label' => 'Teacher',        'color' => '#D97706', 'bg' => '#FFFBEB'],
    'learner'     => ['label' => 'Learner',        'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'parent'      => ['label' => 'Parent',         'color' => '#DB2777', 'bg' => '#FDF2F8'],
    'partner'     => ['label' => 'Partner',        'color' => '#EA580C', 'bg' => '#FFF7ED'],
];
$role_info = $role_map[$pu_role] ?? ['label' => ucfirst($pu_role ?: 'User'), 'color' => '#6B7280', 'bg' => '#F9FAFB'];

// Status badge
$status_map = [
    'active'        => ['label' => 'Active',          'color' => '#065F46', 'bg' => '#D1FAE5'],
    'pending'       => ['label' => 'Pending Approval','color' => '#92400E', 'bg' => '#FEF3C7'],
    'email_pending' => ['label' => 'Email Unverified', 'color' => '#1E40AF', 'bg' => '#DBEAFE'],
    'suspended'     => ['label' => 'Suspended',       'color' => '#991B1B', 'bg' => '#FEE2E2'],
];
$status_info = $status_map[$pu_status] ?? ['label' => ucfirst($pu_status), 'color' => '#374151', 'bg' => '#F3F4F6'];

// Initials for avatar
$initials = strtoupper(
    substr($panel_user['first_name'] ?? $_SESSION['user_name'] ?? 'U', 0, 1) .
    substr($panel_user['last_name'] ?? '', 0, 1)
);

// Role-specific extra fields
$extra_fields = [];
if (!empty($pu_meta['grade_level']))  $extra_fields['Grade Level'] = strtoupper($pu_meta['grade_level']);
if (!empty($pu_meta['subjects']))     $extra_fields['Subjects']    = ucwords(str_replace(',', ', ', $pu_meta['subjects']));
if (!empty($pu_meta['school_name']))  $extra_fields['School']      = htmlspecialchars($pu_meta['school_name']);
if (!empty($pu_meta['lrn']))          $extra_fields['LRN']         = htmlspecialchars($pu_meta['lrn']);
if (!empty($pu_meta['child_grade']))  $extra_fields["Child's Grade"] = strtoupper($pu_meta['child_grade']);
if (!empty($pu_meta['child_school'])) $extra_fields["Child's School"] = htmlspecialchars($pu_meta['child_school']);
?>

<!-- ══════════════════════════════════════
     PROFILE PANEL OVERLAY + DRAWER
══════════════════════════════════════ -->
<div id="profile-overlay" class="pp-overlay" aria-hidden="true"></div>

<aside id="profile-panel" class="pp-panel" role="dialog" aria-modal="true" aria-label="Your profile" aria-hidden="true">

  <!-- ── Header strip ── -->
  <div class="pp-header">
    <div class="pp-header-bg" aria-hidden="true"></div>
    <button class="pp-close" id="pp-close" aria-label="Close profile panel">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M18 6 6 18M6 6l12 12"/>
      </svg>
    </button>

    <!-- Avatar -->
    <div class="pp-avatar-wrap">
      <div class="pp-avatar" aria-hidden="true"><?= $initials ?></div>
      <div class="pp-avatar-status <?= $pu_status === 'active' ? 'is-active' : '' ?>" aria-hidden="true"></div>
    </div>

    <div class="pp-identity">
      <h2 class="pp-name"><?= $pu_first ?> <?= $pu_last ?></h2>
      <p class="pp-email"><?= $pu_email ?></p>
      <div class="pp-badges">
        <span class="pp-badge" style="color:<?= $role_info['color'] ?>;background:<?= $role_info['bg'] ?>">
          <?= $role_info['label'] ?>
        </span>
        <span class="pp-badge" style="color:<?= $status_info['color'] ?>;background:<?= $status_info['bg'] ?>">
          <?= $status_info['label'] ?>
        </span>
        <?php if ($pu_totp): ?>
        <span class="pp-badge" style="color:#065F46;background:#D1FAE5" title="Two-factor authentication is enabled">
          <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="vertical-align:middle">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          2FA On
        </span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ── Body ── -->
  <div class="pp-body">

    <!-- Account details -->
    <section class="pp-section">
      <h3 class="pp-section-title">Account Details</h3>
      <ul class="pp-info-list">
        <?php if ($pu_empid): ?>
        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="4" width="20" height="16" rx="2"/><path d="M8 4v16M16 4v16"/>
            </svg>
          </span>
          <span class="pp-info-label">Employee ID</span>
          <span class="pp-info-value"><?= $pu_empid ?></span>
        </li>
        <?php endif; ?>

        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
            </svg>
          </span>
          <span class="pp-info-label">Region</span>
          <span class="pp-info-value"><?= $pu_region ?: '—' ?></span>
        </li>

        <?php if ($pu_div): ?>
        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </span>
          <span class="pp-info-label">Division</span>
          <span class="pp-info-value"><?= $pu_div ?></span>
        </li>
        <?php endif; ?>

        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
          </span>
          <span class="pp-info-label">Member Since</span>
          <span class="pp-info-value"><?= $pu_joined ?></span>
        </li>

        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </span>
          <span class="pp-info-label">Last Login</span>
          <span class="pp-info-value"><?= $pu_login ?></span>
        </li>
      </ul>
    </section>

    <!-- Role-specific fields -->
    <?php if (!empty($extra_fields)): ?>
    <section class="pp-section">
      <h3 class="pp-section-title">
        <?= $role_info['label'] ?> Info
      </h3>
      <ul class="pp-info-list">
        <?php foreach ($extra_fields as $k => $v): ?>
        <li class="pp-info-row">
          <span class="pp-info-icon">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2Zm0 4v4l3 3"/>
            </svg>
          </span>
          <span class="pp-info-label"><?= htmlspecialchars($k) ?></span>
          <span class="pp-info-value"><?= $v ?></span>
        </li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <!-- Quick actions -->
    <section class="pp-section">
      <h3 class="pp-section-title">Quick Actions</h3>
      <div class="pp-actions-grid">
        <a href="profile_edit.php" class="pp-action-btn">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>
          </svg>
          Edit Profile
        </a>
        <a href="change_password.php" class="pp-action-btn">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Change Password
        </a>
        <a href="totp_setup.php?manage=1" class="pp-action-btn">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
          </svg>
          <?= $pu_totp ? 'Manage 2FA' : 'Enable 2FA' ?>
        </a>
        <?php if ($pu_role === 'admin' || $pu_role === 'developer'): ?>
        <a href="manage.php" class="pp-action-btn">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>
          </svg>
          Manage
        </a>
        <?php endif; ?>
      </div>
    </section>

  </div>

  <!-- ── Footer sign-out ── -->
  <div class="pp-footer">
    <button class="pp-signout-btn" id="pp-signout-trigger">
      <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
      Sign Out
    </button>
  </div>

</aside>