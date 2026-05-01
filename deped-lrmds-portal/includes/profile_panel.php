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
               totp_enabled, meta, avatar
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

$pu_avatar = !empty($panel_user['avatar']) ? htmlspecialchars($panel_user['avatar']) : null;

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
      <div class="pp-avatar" id="pp-avatar-circle" aria-hidden="true">
        <?php if ($pu_avatar): ?>
          <img src="<?= $pu_avatar ?>" alt="Profile photo"
               style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <div class="pp-avatar-status <?= $pu_status === 'active' ? 'is-active' : '' ?>" aria-hidden="true"></div>

      <!-- Hidden file input -->
      <input type="file" id="pp-avatar-input" accept="image/jpeg,image/png,image/gif,image/webp"
             style="display:none">

      <!-- Pencil button — triggers file picker -->
      <button type="button" class="pp-avatar-edit-btn" id="pp-avatar-edit-btn"
              title="Change profile photo" aria-label="Change profile photo">
        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
      </button>
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

<style>
/* ── Avatar camera button ── */
.pp-avatar-wrap { position: relative; display: inline-block; }
.pp-avatar { overflow: hidden; }
.pp-avatar-edit-btn {
  position: absolute;
  bottom: 2px; right: 2px;
  width: 22px; height: 22px;
  border-radius: 50%;
  background: #4F46E5;
  color: #fff;
  border: 2.5px solid #fff;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer;
  transition: background .15s, transform .1s;
  box-shadow: 0 1px 5px rgba(0,0,0,.25);
  z-index: 2;
}
.pp-avatar-edit-btn:hover { background: #4338CA; transform: scale(1.12); }
.pp-avatar-edit-btn svg { pointer-events: none; }

/* ── Crop Modal ── */
.av-modal-bg {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.65);
  z-index: 9999;
  align-items: center; justify-content: center;
}
.av-modal-bg.open { display: flex; }
.av-modal {
  background: #fff; border-radius: 14px;
  width: min(480px, 95vw);
  box-shadow: 0 20px 60px rgba(0,0,0,.35);
  overflow: hidden;
  animation: av-pop .2s ease;
}
@keyframes av-pop {
  from { opacity:0; transform: scale(.93) translateY(10px); }
  to   { opacity:1; transform: scale(1)   translateY(0); }
}
.av-modal-head {
  padding: .9rem 1.2rem;
  border-bottom: 1px solid #E5E7EB;
  display: flex; align-items: center; justify-content: space-between;
}
.av-modal-head h3 { font-size: 1rem; font-weight: 600; color: #111827; margin:0; font-family: sans-serif; }
.av-modal-x {
  background: none; border: none; cursor: pointer;
  color: #6B7280; padding: 4px; border-radius: 6px;
  display: flex; align-items: center; transition: background .12s;
}
.av-modal-x:hover { background: #F3F4F6; color: #111; }
.av-crop-wrap {
  position: relative;
  width: 100%; height: 300px;
  background: #111;
  overflow: hidden;
  cursor: grab;
}
.av-crop-wrap:active { cursor: grabbing; }
.av-crop-img {
  position: absolute;
  transform-origin: center center;
  user-select: none; pointer-events: none;
}
/* circle overlay */
.av-crop-wrap::after {
  content: '';
  position: absolute; inset: 0;
  border-radius: 0;
  box-shadow: 0 0 0 9999px rgba(0,0,0,.55);
  clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);
  pointer-events: none;
}
.av-circle-mask {
  position: absolute;
  top: 50%; left: 50%;
  width: 220px; height: 220px;
  transform: translate(-50%, -50%);
  border-radius: 50%;
  box-shadow: 0 0 0 9999px rgba(0,0,0,.55);
  pointer-events: none;
  z-index: 1;
}
.av-tip {
  position: absolute; bottom: 10px; left: 50%; transform: translateX(-50%);
  background: rgba(0,0,0,.6); color: #fff;
  font-size: .72rem; padding: .3rem .75rem; border-radius: 99px;
  pointer-events: none; z-index: 2; white-space: nowrap; font-family: sans-serif;
}
.av-modal-foot {
  display: flex; align-items: center; justify-content: flex-end;
  gap: .6rem; padding: .85rem 1.2rem;
}
.av-btn {
  padding: .55rem 1.2rem; border-radius: 8px;
  font-size: .88rem; font-weight: 600; cursor: pointer;
  border: none; font-family: sans-serif; transition: background .15s;
}
.av-btn-cancel { background: #F3F4F6; color: #374151; }
.av-btn-cancel:hover { background: #E5E7EB; }
.av-btn-save {
  background: #4F46E5; color: #fff;
  box-shadow: 0 1px 4px rgba(79,70,229,.3);
}
.av-btn-save:hover { background: #4338CA; }
.av-btn-save:disabled { opacity:.6; cursor:default; }
</style>

<!-- Crop Modal (shared, only one instance needed) -->
<div class="av-modal-bg" id="av-modal-bg">
  <div class="av-modal">
    <div class="av-modal-head">
      <h3>Choose profile picture</h3>
      <button class="av-modal-x" id="av-modal-x" aria-label="Close">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M18 6 6 18M6 6l12 12"/>
        </svg>
      </button>
    </div>
    <div class="av-crop-wrap" id="av-crop-wrap">
      <img class="av-crop-img" id="av-crop-img" src="" alt="">
      <div class="av-circle-mask"></div>
      <div class="av-tip">Drag to reposition · Arrow keys for fine control</div>
    </div>
    <div class="av-modal-foot">
      <button class="av-btn av-btn-cancel" id="av-btn-cancel">Cancel</button>
      <button class="av-btn av-btn-save"   id="av-btn-save">Save</button>
    </div>
  </div>
</div>

<script>
(function () {
  /* ── wiring ── */
  const camBtn    = document.getElementById('pp-avatar-edit-btn');
  const fileIn    = document.getElementById('pp-avatar-input');
  const circle    = document.getElementById('pp-avatar-circle');
  const modalBg   = document.getElementById('av-modal-bg');
  const modalX    = document.getElementById('av-modal-x');
  const cropImg   = document.getElementById('av-crop-img');
  const cropWrap  = document.getElementById('av-crop-wrap');
  const btnCancel = document.getElementById('av-btn-cancel');
  const btnSave   = document.getElementById('av-btn-save');

  if (!camBtn || !fileIn) return;

  /* ── state ── */
  const CIRCLE = 220;   // diameter of the crop circle in px
  let imgW = 0, imgH = 0;   // natural image dimensions
  let scale = 1;            // cover-fit scale (fixed, no zoom)
  let ox = 0, oy = 0;       // pan offset in px (rendered pixels)
  let dragging = false, startX = 0, startY = 0, startOx = 0, startOy = 0;

  /* ── open file picker ── */
  camBtn.addEventListener('click', () => fileIn.click());

  /* ── file chosen → compute cover scale → open modal ── */
  fileIn.addEventListener('change', function () {
    const file = fileIn.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) { alert('Image must be 5 MB or smaller.'); return; }
    const url = URL.createObjectURL(file);
    cropImg.onload = function () {
      imgW = cropImg.naturalWidth;
      imgH = cropImg.naturalHeight;
      ox = 0; oy = 0;
      computeCoverScale();
      applyTransform();
      modalBg.classList.add('open');
    };
    cropImg.src = url;
    fileIn.value = '';
  });

  /* cover-fit: image fills the circle completely, no empty edges */
  function computeCoverScale() {
    const ww = cropWrap.clientWidth, wh = cropWrap.clientHeight;
    // scale so the shorter side of the image equals the circle diameter
    const scaleX = CIRCLE / imgW;
    const scaleY = CIRCLE / imgH;
    scale = Math.max(scaleX, scaleY);
  }

  /* ── close ── */
  function closeModal() { modalBg.classList.remove('open'); }
  modalX.addEventListener('click', closeModal);
  btnCancel.addEventListener('click', closeModal);
  modalBg.addEventListener('click', e => { if (e.target === modalBg) closeModal(); });

  /* ── drag to pan ── */
  cropWrap.addEventListener('mousedown', dragStart);
  cropWrap.addEventListener('touchstart', dragStart, { passive: true });
  window.addEventListener('mousemove', dragMove);
  window.addEventListener('touchmove', dragMove, { passive: false });
  window.addEventListener('mouseup', dragEnd);
  window.addEventListener('touchend', dragEnd);

  function getXY(e) {
    return e.touches ? { x: e.touches[0].clientX, y: e.touches[0].clientY }
                     : { x: e.clientX, y: e.clientY };
  }
  function dragStart(e) {
    dragging = true;
    const p = getXY(e);
    startX = p.x; startY = p.y; startOx = ox; startOy = oy;
  }
  function dragMove(e) {
    if (!dragging) return;
    if (e.cancelable) e.preventDefault();
    const p = getXY(e);
    ox = startOx + (p.x - startX);
    oy = startOy + (p.y - startY);
    clampOffset();
    applyTransform();
  }
  function dragEnd() { dragging = false; }

  /* ── arrow key fine control ── */
  document.addEventListener('keydown', function (e) {
    if (!modalBg.classList.contains('open')) return;
    const step = 6;
    if (e.key === 'ArrowLeft')  { ox -= step; e.preventDefault(); }
    if (e.key === 'ArrowRight') { ox += step; e.preventDefault(); }
    if (e.key === 'ArrowUp')    { oy -= step; e.preventDefault(); }
    if (e.key === 'ArrowDown')  { oy += step; e.preventDefault(); }
    clampOffset();
    applyTransform();
  });

  /* clamp so the image never exposes a gap inside the circle */
  function clampOffset() {
    const renderedW = imgW * scale;
    const renderedH = imgH * scale;
    // maximum pan = half the overflow on each axis
    const maxOx = Math.max(0, (renderedW - CIRCLE) / 2);
    const maxOy = Math.max(0, (renderedH - CIRCLE) / 2);
    ox = Math.max(-maxOx, Math.min(maxOx, ox));
    oy = Math.max(-maxOy, Math.min(maxOy, oy));
  }

  /* position the image centred in the wrap, shifted by pan offset */
  function applyTransform() {
    const ww = cropWrap.clientWidth, wh = cropWrap.clientHeight;
    const renderedW = imgW * scale;
    const renderedH = imgH * scale;
    const left = (ww - renderedW) / 2 + ox;
    const top  = (wh - renderedH) / 2 + oy;
    cropImg.style.width       = renderedW + 'px';
    cropImg.style.height      = renderedH + 'px';
    cropImg.style.left        = left + 'px';
    cropImg.style.top         = top  + 'px';
    cropImg.style.transform   = 'none';
  }

  /* ── save: crop the circle region → canvas → upload ── */
  btnSave.addEventListener('click', function () {
    btnSave.disabled = true;
    btnSave.textContent = 'Saving…';

    const SIZE = 400;
    const canvas = document.createElement('canvas');
    canvas.width = SIZE; canvas.height = SIZE;
    const ctx = canvas.getContext('2d');
    ctx.beginPath();
    ctx.arc(SIZE / 2, SIZE / 2, SIZE / 2, 0, Math.PI * 2);
    ctx.clip();

    /* map the circle (centre of crop-wrap) back to source image coordinates */
    const ww = cropWrap.clientWidth, wh = cropWrap.clientHeight;
    const renderedW = imgW * scale;
    const renderedH = imgH * scale;
    const imgLeft = (ww - renderedW) / 2 + ox;   // rendered image left edge
    const imgTop  = (wh - renderedH) / 2 + oy;   // rendered image top edge
    const circleLeft = (ww - CIRCLE) / 2;
    const circleTop  = (wh - CIRCLE) / 2;
    // source rect in natural-image pixels
    const sx = (circleLeft - imgLeft) / scale;
    const sy = (circleTop  - imgTop)  / scale;
    const sw = CIRCLE / scale;
    const sh = CIRCLE / scale;

    ctx.drawImage(cropImg, sx, sy, sw, sh, 0, 0, SIZE, SIZE);

    canvas.toBlob(function (blob) {
      const fd = new FormData();
      fd.append('avatar', blob, 'avatar.jpg');
      fetch('upload_avatar.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(function (data) {
          if (data.success) {
            const url = data.url + '?t=' + Date.now();
            circle.innerHTML = '<img src="' + url + '" alt="Profile photo" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">';
            document.querySelectorAll('.hdr-avatar-mini, .mob-avatar-mini').forEach(function (el) {
              el.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">';
            });
            closeModal();
          } else {
            alert(data.error || 'Upload failed.');
          }
        })
        .catch(() => alert('Upload failed. Please try again.'))
        .finally(() => { btnSave.disabled = false; btnSave.textContent = 'Save'; });
    }, 'image/jpeg', 0.92);
  });
})();
</script>