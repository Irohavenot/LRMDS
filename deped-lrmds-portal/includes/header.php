<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isSignedIn = isset($_SESSION['user']) && $_SESSION['user'];
$userRole   = $_SESSION['user_role'] ?? '';

// Fetch avatar from DB so header always shows the latest photo
$hdr_avatar = null;
if ($isSignedIn && !empty($_SESSION['user_id'])) {
    try {
        $hdr_pdo = new PDO(
            'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
            'root', '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::ATTR_EMULATE_PREPARES => false]
        );
        $hdr_s = $hdr_pdo->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
        $hdr_s->execute([(int)$_SESSION['user_id']]);
        $hdr_row = $hdr_s->fetch();
        if (!empty($hdr_row['avatar'])) $hdr_avatar = htmlspecialchars($hdr_row['avatar']);
    } catch (PDOException $e) { /* silently fallback to initials */ }
}

// Roles that can see "Develop" (submit resources)
$canDevelop = $isSignedIn && in_array($userRole, ['teacher','school-head','partner','developer','admin'], true);

// Roles that can see "Manage"
$canManage  = $isSignedIn && in_array($userRole, ['school-head','partner','developer','admin'], true);
?>
<link rel="stylesheet" href="assets/css/header.css"/>
<link rel="stylesheet" href="assets/css/header_responsive.css"/>
<link rel="stylesheet" href="assets/css/signin-modal.css"/>
<link rel="stylesheet" href="assets/css/profile_panel.css"/>

<header class="header" role="banner">
  <div class="container inner">

    <!-- Brand -->
    <a class="brand" href="index.php">
      <img src="assets/img/logo.svg" alt="DepEd LRMDS Logo"/>
      <span class="title">LRMDS</span>
    </a>

    <!-- Desktop nav -->
    <nav class="nav" aria-label="Main">
      <a href="index.php">Home</a>
      <a href="search.php">Resources</a>
      <?php if ($canDevelop): ?>
        <a href="submit.php">Develop</a>
      <?php endif; ?>
      <?php if ($canManage): ?>
        <a href="manage.php">Manage</a>
      <?php endif; ?>
      <?php if ($isSignedIn): ?>
        <a href="train-support.php">Train &amp; Support</a>
      <?php else: ?>
        <a href="#"
           class="nav-protected"
           data-protected="true"
           data-dest="train-support.php">Train &amp; Support</a>
      <?php endif; ?>
      <a href="news.php">News</a>
    </nav>

    <!-- Desktop actions -->
    <div class="header-actions">
      <div class="search-wrap" role="search">
        <input type="search" name="hdr-q" placeholder="Search resources…" aria-label="Search resources"/>
        <span class="icon">
          <img src="assets/icons/magnifying-glass.svg" alt="">
        </span>
      </div>

      <?php if ($canDevelop): ?>
        <a class="button ghost" href="submit.php">
          <img src="assets/icons/upload.svg" alt="" style="vertical-align:middle;margin-right:6px">
          <span class="submit-label">Submit</span>
        </a>
      <?php elseif (!$isSignedIn): ?>
        <a class="button ghost" href="#"
           data-protected="true"
           data-dest="submit.php">
          <img src="assets/icons/upload.svg" alt="" style="vertical-align:middle;margin-right:6px">
          <span class="submit-label">Submit</span>
        </a>
      <?php endif; ?>

      <?php if ($isSignedIn):
        $hdr_initials = strtoupper(
          substr($_SESSION['user_name'] ?? $_SESSION['user'] ?? 'U', 0, 1) .
          (isset($_SESSION['user_last']) ? substr($_SESSION['user_last'], 0, 1) : '')
        );
      ?>
        <!-- Hidden sign-out btn kept for JS sign-out modal wiring -->
        <button style="display:none" id="hdr-signout-btn" aria-hidden="true"></button>
        <a href="#" class="hdr-avatar-btn" id="hdr-account-btn" aria-label="Open profile">
          <span class="hdr-avatar-mini" aria-hidden="true">
            <?php if ($hdr_avatar): ?>
              <img src="<?= $hdr_avatar ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
            <?php else: ?>
              <?= $hdr_initials ?>
            <?php endif; ?>
          </span>
          <span><?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?></span>
        </a>
      <?php else: ?>
        <a href="#" class="button primary" id="hdr-signin-btn">Sign In</a>
      <?php endif; ?>

      <button
        class="nav-toggle"
        id="nav-toggle"
        aria-label="Open navigation menu"
        aria-expanded="false"
        aria-controls="nav-drawer">
        <span class="bar bar-1" aria-hidden="true"></span>
        <span class="bar bar-2" aria-hidden="true"></span>
        <span class="bar bar-3" aria-hidden="true"></span>
      </button>
    </div>

  </div>
</header>


<!-- ══════════════════════════════════════
     MOBILE DRAWER NAV  (≤ 768px)
══════════════════════════════════════ -->
<div id="nav-overlay" class="nav-overlay" aria-hidden="true"></div>

<nav id="nav-drawer" class="nav-drawer" aria-label="Mobile navigation" aria-hidden="true">

  <div class="drawer-search" role="search">
    <input type="search" name="mob-q" placeholder="Search resources…" aria-label="Search resources"/>
    <span class="icon">
      <img src="assets/icons/magnifying-glass.svg" alt="">
    </span>
  </div>

  <div class="drawer-links">
    <a href="index.php">
      <img src="assets/icons/house.svg" alt=""> Home
    </a>
    <a href="search.php">
      <img src="assets/icons/folders.svg" alt=""> Resources
    </a>
    <?php if ($canDevelop): ?>
      <a href="submit.php">
        <img src="assets/icons/chalkboard-teacher.svg" alt=""> Develop
      </a>
    <?php endif; ?>
    <?php if ($canManage): ?>
      <a href="manage.php">
        <img src="assets/icons/calendar.svg" alt=""> Manage
      </a>
    <?php endif; ?>
    <?php if ($isSignedIn): ?>
      <a href="train-support.php">
        <img src="assets/icons/life-ring.svg" alt=""> Train &amp; Support
      </a>
    <?php else: ?>
      <a href="#"
         class="nav-protected"
         data-protected="true"
         data-dest="train-support.php">
        <img src="assets/icons/life-ring.svg" alt=""> Train &amp; Support
      </a>
    <?php endif; ?>
    <a href="news.php">
      <img src="assets/icons/megaphone.svg" alt=""> News
    </a>
  </div>

  <div class="drawer-divider"></div>

  <?php if ($canDevelop): ?>
    <a class="drawer-cta" href="submit.php">
      <img src="assets/icons/upload.svg" alt=""> Submit a Resource
    </a>
  <?php elseif (!$isSignedIn): ?>
    <a class="drawer-cta"
       href="#"
       data-protected="true"
       data-dest="submit.php">
      <img src="assets/icons/upload.svg" alt=""> Submit a Resource
    </a>
  <?php endif; ?>

</nav>


<!-- ══════════════════════════════════════
     MOBILE BOTTOM ICON NAV BAR  (≤ 640px)
══════════════════════════════════════ -->
<nav class="mobile-nav-bar" aria-label="Quick navigation">

  <a class="mob-nav-item" href="index.php">
    <span class="mob-icon-wrap"><img src="assets/icons/house.svg" alt=""></span>
    <span>Home</span>
  </a>

  <a class="mob-nav-item" href="search.php">
    <span class="mob-icon-wrap"><img src="assets/icons/magnifying-glass.svg" alt=""></span>
    <span>Search</span>
  </a>

  <?php if ($canDevelop): ?>
    <a class="mob-nav-item" href="submit.php">
      <span class="mob-icon-wrap"><img src="assets/icons/upload.svg" alt=""></span>
      <span>Submit</span>
    </a>
  <?php elseif (!$isSignedIn): ?>
    <a class="mob-nav-item" href="#"
       data-protected="true"
       data-dest="submit.php">
      <span class="mob-icon-wrap"><img src="assets/icons/upload.svg" alt=""></span>
      <span>Submit</span>
    </a>
  <?php endif; ?>

  <a class="mob-nav-item" href="news.php">
    <span class="mob-icon-wrap"><img src="assets/icons/megaphone.svg" alt=""></span>
    <span>News</span>
  </a>

  <?php if ($isSignedIn):
    $mob_initials = strtoupper(
      substr($_SESSION['user_name'] ?? $_SESSION['user'] ?? 'U', 0, 1) .
      (isset($_SESSION['user_last']) ? substr($_SESSION['user_last'], 0, 1) : '')
    );
  ?>
    <a class="mob-nav-item" href="#" id="mob-account-btn">
      <span class="mob-icon-wrap">
        <span class="mob-avatar-mini" aria-hidden="true">
          <?php if ($hdr_avatar): ?>
            <img src="<?= $hdr_avatar ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block">
          <?php else: ?>
            <?= $mob_initials ?>
          <?php endif; ?>
        </span>
      </span>
      <span>Profile</span>
    </a>
  <?php else: ?>
    <a class="mob-nav-item" href="#" id="mob-signin-btn">
      <span class="mob-icon-wrap"><img src="assets/icons/student.svg" alt=""></span>
      <span>Sign In</span>
    </a>
  <?php endif; ?>

</nav>


<!-- ══════════════════════════════════════
     Sign-Out Confirmation Modal
══════════════════════════════════════ -->
<?php if ($isSignedIn): ?>
<div id="signout-modal" class="sout-backdrop" hidden role="dialog" aria-modal="true" aria-labelledby="sout-title">
  <div class="sout-card">
    <div class="sout-icon">
      <svg width="28" height="28" fill="none" stroke="#0B4F9C" stroke-width="2" viewBox="0 0 24 24">
        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
        <polyline points="16 17 21 12 16 7"/>
        <line x1="21" y1="12" x2="9" y2="12"/>
      </svg>
    </div>
    <h2 class="sout-title" id="sout-title">Sign out?</h2>
    <p class="sout-sub">You'll need to sign in again to access your account.</p>
    <div class="sout-actions">
      <button class="sout-btn-cancel" id="sout-cancel">Cancel</button>
      <a href="signout.php" class="sout-btn-confirm">Yes, sign out</a>
    </div>
  </div>
</div>
<?php endif; ?>


<!-- ══════════════════════════════════════
     Scripts — signin-modal.js is the sole
     sign-in handler; no sim-* modal here.
══════════════════════════════════════ -->
<script src="assets/js/header.js"></script>
<script src="assets/js/header_mobile.js"></script>
<?php if (!$isSignedIn): ?>
<script src="assets/js/signin-modal.js"></script>
<?php else: ?>
<?php include_once 'profile_panel.php'; ?>
<script src="assets/js/profile_panel.js"></script>
<?php endif; ?>