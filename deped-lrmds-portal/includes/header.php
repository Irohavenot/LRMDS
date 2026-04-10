<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isSignedIn = isset($_SESSION['user']) && $_SESSION['user'];
?>
<link rel="stylesheet" href="assets/css/header.css"/>
<link rel="stylesheet" href="assets/css/header_responsive.css"/>

<header class="header" role="banner">
  <div class="container inner">

    <!-- Brand -->
    <a class="brand" href="index.php">
      <img src="assets/img/logo.svg" alt="DepEd LRMDS Logo"/>
      <span class="title">LRMDS</span>
    </a>

    <!-- Desktop nav (hidden ≤768px, replaced by drawer) -->
    <nav class="nav" aria-label="Main">
      <a href="index.php">Home</a>
      <a href="search.php">Resources</a>
      <?php if ($isSignedIn): ?>
        <a href="submit.php">Develop</a>
        <a href="manage.php">Manage</a>
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
      <!-- Search bar (hidden on mobile; use hero search instead) -->
      <div class="search-wrap" role="search">
        <input type="search" name="hdr-q" placeholder="Search resources…" aria-label="Search resources"/>
        <span class="icon">
          <img src="assets/icons/magnifying-glass.svg" alt="">
        </span>
      </div>

      <!-- Submit button -->
      <?php if ($isSignedIn): ?>
        <a class="button ghost" href="submit.php">
          <img src="assets/icons/upload.svg" alt="" style="vertical-align:middle;margin-right:6px">
          <span class="submit-label">Submit</span>
        </a>
      <?php else: ?>
        <a class="button ghost" href="#"
           data-protected="true"
           data-dest="submit.php">
          <img src="assets/icons/upload.svg" alt="" style="vertical-align:middle;margin-right:6px">
          <span class="submit-label">Submit</span>
        </a>
      <?php endif; ?>

      <?php if ($isSignedIn): ?>
        <a href="#" class="button primary" id="hdr-signout-btn">Sign Out</a>
      <?php else: ?>
        <a href="#" class="button primary" id="hdr-signin-btn">Sign In</a>
      <?php endif; ?>

      <!-- Hamburger (shown ≤768px) -->
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

  <!-- Drawer search -->
  <div class="drawer-search" role="search">
    <input type="search" name="mob-q" placeholder="Search resources…" aria-label="Search resources"/>
    <span class="icon">
      <img src="assets/icons/magnifying-glass.svg" alt="">
    </span>
  </div>

  <!-- Drawer links (icon + label) -->
  <div class="drawer-links">
    <a href="index.php">
      <img src="assets/icons/house.svg" alt=""> Home
    </a>
    <a href="search.php">
      <img src="assets/icons/folders.svg" alt=""> Resources
    </a>
    <?php if ($isSignedIn): ?>
      <a href="submit.php">
        <img src="assets/icons/chalkboard-teacher.svg" alt=""> Develop
      </a>
      <a href="manage.php">
        <img src="assets/icons/calendar.svg" alt=""> Manage
      </a>
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

  <!-- Submit CTA inside drawer -->
  <?php if ($isSignedIn): ?>
    <a class="drawer-cta" href="submit.php">
      <img src="assets/icons/upload.svg" alt=""> Submit a Resource
    </a>
  <?php else: ?>
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
    <span class="mob-icon-wrap">
      <img src="assets/icons/house.svg" alt="">
    </span>
    <span>Home</span>
  </a>

  <a class="mob-nav-item" href="search.php">
    <span class="mob-icon-wrap">
      <img src="assets/icons/magnifying-glass.svg" alt="">
    </span>
    <span>Search</span>
  </a>

  <?php if ($isSignedIn): ?>
    <a class="mob-nav-item" href="submit.php">
      <span class="mob-icon-wrap">
        <img src="assets/icons/upload.svg" alt="">
      </span>
      <span>Submit</span>
    </a>
  <?php else: ?>
    <a class="mob-nav-item" href="#"
       data-protected="true"
       data-dest="submit.php">
      <span class="mob-icon-wrap">
        <img src="assets/icons/upload.svg" alt="">
      </span>
      <span>Submit</span>
    </a>
  <?php endif; ?>

  <a class="mob-nav-item" href="news.php">
    <span class="mob-icon-wrap">
      <img src="assets/icons/megaphone.svg" alt="">
    </span>
    <span>News</span>
  </a>

  <?php if ($isSignedIn): ?>
  <a class="mob-nav-item" href="#" id="mob-signout-btn">
    <span class="mob-icon-wrap">
      <img src="assets/icons/seal-check.svg" alt="">
    </span>
    <span>Account</span>
  </a>
  <?php else: ?>
  <a class="mob-nav-item" href="#" id="mob-signin-btn">
    <span class="mob-icon-wrap">
      <img src="assets/icons/student.svg" alt="">
    </span>
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
     Sign-In Modal
══════════════════════════════════════ -->
<?php if (!$isSignedIn): ?>
<div id="signin-modal" class="sim-backdrop" role="dialog" aria-modal="true"
     aria-labelledby="sim-title" hidden>
  <div class="sim-card">

    <button class="sim-close" id="sim-close" aria-label="Close sign-in dialog">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
           viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>

    <div class="sim-accent" aria-hidden="true">
      <div class="sim-shapes">
        <div class="sim-s sim-s1"></div>
        <div class="sim-s sim-s2"></div>
      </div>
      <div class="sim-accent-content">
        <img src="assets/img/logo.svg" alt="" class="sim-logo"/>
        <p class="sim-system">Department of Education</p>
        <p class="sim-name">Learning Resource Management<br/>&amp; Development System</p>
        <div class="sim-stats">
          <div><strong>50,000+</strong><span>Resources</span></div>
          <div><strong>K – 12</strong><span>Grade Levels</span></div>
          <div><strong>17</strong><span>Regions</span></div>
        </div>
      </div>
    </div>

    <div class="sim-body">
      <div class="sim-notice" id="sim-notice" hidden>
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
        <span id="sim-notice-text">Sign in to access this page.</span>
      </div>

      <h2 class="sim-title" id="sim-title">Welcome back</h2>
      <p class="sim-sub">Sign in to your LRMDS account.</p>

      <div class="sim-demo-pill" role="note">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2"
             viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        Prototype — any credentials will work
      </div>

      <form id="sim-form" novalidate autocomplete="on">
        <input type="hidden" id="sim-dest" value=""/>

        <div class="sim-group">
          <label class="sim-label" for="sim-email">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                 viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m2 7 10 7 10-7"/></svg>
            Email or Employee ID
          </label>
          <input class="sim-input" type="text" id="sim-email" name="email"
                 placeholder="yourname@deped.gov.ph"
                 autocomplete="username" required/>
          <span class="sim-err" id="sim-email-err" role="alert"></span>
        </div>

        <div class="sim-group">
          <label class="sim-label" for="sim-password">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"
                 viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/>
              <path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Password
          </label>
          <div class="sim-pw-wrap">
            <input class="sim-input" type="password" id="sim-password" name="password"
                   placeholder="Enter your password"
                   autocomplete="current-password" required/>
            <button type="button" class="sim-pw-toggle" aria-label="Toggle password visibility">
              <svg class="eye-on" width="16" height="16" fill="none" stroke="currentColor"
                   stroke-width="2" viewBox="0 0 24 24">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="eye-off" width="16" height="16" fill="none" stroke="currentColor"
                   stroke-width="2" viewBox="0 0 24 24" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8
                         a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4
                         c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07
                         a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <span class="sim-err" id="sim-pw-err" role="alert"></span>
        </div>

        <div class="sim-inline">
          <label class="sim-check">
            <input type="checkbox" id="sim-remember"/>
            <span class="sim-checkmark"></span>
            Keep me signed in
          </label>
          <a class="sim-link" href="#">Forgot password?</a>
        </div>

        <button type="submit" class="sim-btn" id="sim-submit">
          <span>Sign In</span>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"
               viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>

        <div class="sim-divider"><span>or</span></div>

        <div class="sim-sso-row">
          <button type="button" class="sim-sso">
            <img src="assets/img/logo.svg" alt="" width="18"/>DepEd SSO
          </button>
          <button type="button" class="sim-sso">
            <svg width="18" height="18" viewBox="0 0 24 24">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23Z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62Z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 2.18 2.18 5.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z"/>
            </svg>
            Google Workspace
          </button>
        </div>
      </form>

      <p class="sim-switch">No account? <a class="sim-link" href="register.php">Register here</a></p>
    </div>

  </div>
</div>
<?php endif; ?>

<script src="assets/js/header.js"></script>
<script src="assets/js/header_mobile.js"></script>