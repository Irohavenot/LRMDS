<?php
// Prototype: treat as signed-out unless session says otherwise
session_start();
$isSignedIn = isset($_SESSION['user']) && $_SESSION['user'];
?>
<header class="header" role="banner">
  <div class="container inner">
    <a class="brand" href="index.php">
      <img src="assets/img/logo.svg" alt="DepEd LRMDS Logo"/>
      <span class="title">LRMDS</span>
    </a>

    <nav class="nav" aria-label="Main">
      <a href="index.php">Home</a>
      <a href="search.php">Resources</a>
      <!-- Protected nav links — trigger modal when not signed in -->
      <a href="submit.php"
         class="nav-protected"
         data-protected="true"
         data-dest="submit.php">Develop</a>
      <a href="#"
         class="nav-protected"
         data-protected="true"
         data-dest="manage.php">Manage</a>
      <a href="train-support.php"
         class="nav-protected"
         data-protected="true"
         data-dest="train-support.php">Train &amp; Support</a>
      <a href="news.php">News</a>
    </nav>

    <div class="header-actions">
      <div class="search-wrap" role="search">
        <input type="search" name="hdr-q" placeholder="Search resources…" aria-label="Search resources"/>
        <span class="icon">
          <img src="assets/icons/magnifying-glass.svg" alt="">
        </span>
      </div>
      <a class="button ghost" href="#"
         data-protected="true"
         data-dest="submit.php">
        <img src="assets/icons/upload.svg" alt="" style="vertical-align:middle;margin-right:6px">Submit
      </a>
      <?php if ($isSignedIn): ?>
        <a href="signout.php" class="button primary">Sign Out</a>
      <?php else: ?>
        <a href="#" class="button primary" id="hdr-signin-btn">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<!-- ══════════════════════════════════════
     Sign-In Modal (prototype)
══════════════════════════════════════ -->
<?php if (!$isSignedIn): ?>
<div id="signin-modal" class="sim-backdrop" role="dialog" aria-modal="true"
     aria-labelledby="sim-title" hidden>
  <div class="sim-card">

    <!-- Close -->
    <button class="sim-close" id="sim-close" aria-label="Close sign-in dialog">
      <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5"
           viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>

    <!-- Left accent strip -->
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

    <!-- Right form -->
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
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z"/>
            </svg>
            Google Workspace
          </button>
        </div>
      </form>

      <p class="sim-switch">No account? <a class="sim-link" href="register.php">Register here</a></p>
    </div>

  </div>
</div>

<!-- Modal Styles -->
<style>
/* ── Backdrop ── */
.sim-backdrop {
  position: fixed;
  inset: 0;
  z-index: 9999;
  background: rgba(15, 23, 42, 0.55);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  animation: simFadeIn .18s ease;
}
.sim-backdrop[hidden] { display: none; }

@keyframes simFadeIn  { from { opacity: 0; } to { opacity: 1; } }
@keyframes simSlideUp { from { opacity: 0; transform: translateY(18px) scale(.97); }
                        to   { opacity: 1; transform: none; } }

/* ── Card ── */
.sim-card {
  position: relative;
  display: grid;
  grid-template-columns: 200px 1fr;
  max-width: 680px;
  width: 100%;
  background: #fff;
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 24px 64px rgba(11, 79, 156, .22), 0 4px 16px rgba(0,0,0,.12);
  animation: simSlideUp .22s ease;
}

/* ── Close button ── */
.sim-close {
  position: absolute;
  top: 14px;
  right: 14px;
  z-index: 10;
  background: rgba(255,255,255,.15);
  border: none;
  border-radius: 8px;
  width: 34px;
  height: 34px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: #fff;
  transition: background .15s;
}
.sim-close:hover { background: rgba(255,255,255,.28); }

/* ── Left accent ── */
.sim-accent {
  position: relative;
  background: linear-gradient(160deg, #0B3D91 0%, #0B4F9C 50%, #1565C0 100%);
  color: #fff;
  padding: 28px 22px;
  display: flex;
  flex-direction: column;
  gap: 20px;
  overflow: hidden;
}
.sim-shapes { position: absolute; inset: 0; pointer-events: none; }
.sim-s {
  position: absolute;
  border-radius: 50%;
  opacity: .08;
  background: #fff;
}
.sim-s1 { width: 200px; height: 200px; top: -80px; right: -80px; }
.sim-s2 { width: 120px; height: 120px; bottom: 20px; left: -40px; opacity: .05; }
.sim-accent-content { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 16px; }
.sim-logo {
    width: 183px;
    height: 49px;
  filter: brightness(0) invert(1);
  opacity: .92;
}
.sim-system {
  margin: 0;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: #93C5FD;
}
.sim-name {
  margin: 0;
  font-size: 11px;
  font-weight: 700;
  color: #DBEAFE;
  line-height: 1.4;
}
.sim-stats { display: flex; flex-direction: column; gap: 10px; margin-top: 8px; }
.sim-stats div { display: flex; flex-direction: column; gap: 1px; }
.sim-stats strong { font-size: 18px; font-weight: 800; color: #fff; }
.sim-stats span { font-size: 10px; color: #93C5FD; }

/* ── Right form body ── */
.sim-body {
  padding: 28px 28px 24px;
  overflow-y: auto;
  max-height: 90vh;
}

/* Access notice */
.sim-notice {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #FFF7ED;
  border: 1px solid #FED7AA;
  color: #C2410C;
  font-size: 12px;
  font-weight: 600;
  border-radius: 8px;
  padding: 8px 12px;
  margin-bottom: 14px;
}
.sim-notice[hidden] { display: none; }

.sim-title { font-size: 20px; font-weight: 800; color: #111827; margin: 0 0 4px; }
.sim-sub   { font-size: 13px; color: #6B7280; margin: 0 0 14px; }

.sim-demo-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  background: #FFFBEB;
  border: 1px solid #FDE68A;
  color: #92400E;
  font-size: 11px;
  font-weight: 600;
  border-radius: 20px;
  padding: 4px 10px;
  margin-bottom: 16px;
}

/* Form groups */
.sim-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 14px;
}
.sim-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}
.sim-label svg { color: #9CA3AF; }
.sim-input {
  width: 100%;
  padding: 10px 12px;
  border: 1.5px solid #D1D5DB;
  border-radius: 8px;
  font-size: 13px;
  color: #111827;
  background: #fff;
  transition: border-color .15s, box-shadow .15s;
  outline: none;
  font-family: inherit;
}
.sim-input:focus {
  border-color: #0B4F9C;
  box-shadow: 0 0 0 3px rgba(11,79,156,.1);
}
.sim-input.invalid {
  border-color: #DC2626;
  background: #FEF2F2;
}
.sim-err { font-size: 11px; color: #DC2626; min-height: 14px; }

/* Password */
.sim-pw-wrap { position: relative; }
.sim-pw-wrap .sim-input { padding-right: 38px; }
.sim-pw-toggle {
  position: absolute;
  right: 8px; top: 50%;
  transform: translateY(-50%);
  background: none; border: none;
  cursor: pointer; color: #9CA3AF;
  display: flex; padding: 4px;
  border-radius: 4px;
}
.sim-pw-toggle:hover { color: #374151; }

/* Inline row */
.sim-inline {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}
.sim-check {
  display: flex; align-items: center; gap: 7px;
  font-size: 12px; color: #374151;
  cursor: pointer; user-select: none;
}
.sim-check input[type="checkbox"] { display: none; }
.sim-checkmark {
  width: 15px; height: 15px;
  border: 1.5px solid #D1D5DB;
  border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  transition: background .15s, border-color .15s;
}
.sim-check input:checked + .sim-checkmark {
  background: #0B4F9C;
  border-color: #0B4F9C;
}
.sim-check input:checked + .sim-checkmark::after {
  content: '';
  display: block;
  width: 8px; height: 4px;
  border-left: 2px solid #fff;
  border-bottom: 2px solid #fff;
  transform: rotate(-45deg) translateY(-1px);
}
.sim-link {
  color: #0B4F9C; font-size: 12px;
  font-weight: 600; text-decoration: none;
}
.sim-link:hover { text-decoration: underline; }

/* Submit button */
.sim-btn {
  width: 100%;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  padding: 11px 18px;
  background: #0B4F9C;
  color: #fff;
  border: none; border-radius: 9px;
  font-size: 14px; font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(11,79,156,.25);
  transition: background .15s, transform .1s, box-shadow .15s;
}
.sim-btn:hover {
  background: #0A4489;
  box-shadow: 0 4px 14px rgba(11,79,156,.35);
  transform: translateY(-1px);
}
.sim-btn:active { transform: none; }

/* Divider */
.sim-divider {
  display: flex; align-items: center; gap: 10px;
  margin: 14px 0;
  color: #9CA3AF; font-size: 11px; font-weight: 600;
}
.sim-divider::before, .sim-divider::after {
  content: ''; flex: 1; height: 1px; background: #E5E7EB;
}

/* SSO */
.sim-sso-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.sim-sso {
  display: flex; align-items: center; justify-content: center; gap: 7px;
  padding: 9px 10px;
  border: 1.5px solid #E5E7EB;
  border-radius: 8px;
  background: #fff;
  font-size: 12px; font-weight: 600;
  font-family: inherit; color: #374151;
  cursor: pointer;
  transition: border-color .15s, background .15s;
}
.sim-sso:hover { border-color: #0B4F9C; background: #F0F6FF; }

.sim-switch {
  text-align: center; font-size: 12px;
  color: #6B7280; margin: 14px 0 0;
}

/* ── Responsive ── */
@media (max-width: 600px) {
  .sim-card { grid-template-columns: 1fr; }
  .sim-accent { display: none; }
  .sim-close { color: #374151; background: rgba(0,0,0,.06); }
}
</style>

<!-- Modal Script -->
<script>
(function () {
  const modal    = document.getElementById('signin-modal');
  const closeBtn = document.getElementById('sim-close');
  const form     = document.getElementById('sim-form');
  const destIn   = document.getElementById('sim-dest');
  const notice   = document.getElementById('sim-notice');
  const noticeT  = document.getElementById('sim-notice-text');

  /* Labels for protected pages */
  const PAGE_LABELS = {
    'submit.php':        'Develop / Submit',
    'manage.php':        'Manage',
    'train-support.php': 'Train & Support',
  };

  function openModal(dest) {
    destIn.value = dest || '';
    const label = PAGE_LABELS[dest] || dest;
    if (dest) {
      notice.hidden = false;
      noticeT.textContent = `Sign in to access "${label}".`;
    } else {
      notice.hidden = true;
    }
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    document.getElementById('sim-email')?.focus();
  }

  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
    destIn.value = '';
    // Clear validation states
    document.querySelectorAll('.sim-input').forEach(i => i.classList.remove('invalid'));
    document.querySelectorAll('.sim-err').forEach(e => e.textContent = '');
  }

  /* Open on protected nav / submit button clicks */
  document.querySelectorAll('[data-protected="true"]').forEach(el => {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openModal(this.dataset.dest || '');
    });
  });

  /* Open on header Sign In button */
  document.getElementById('hdr-signin-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openModal('');
  });

  /* Close on ✕ button */
  closeBtn?.addEventListener('click', closeModal);

  /* Close on backdrop click */
  modal?.addEventListener('click', function (e) {
    if (e.target === modal) closeModal();
  });

  /* Close on Escape */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  /* Password toggle */
  document.querySelector('.sim-pw-toggle')?.addEventListener('click', function () {
    const pw = document.getElementById('sim-password');
    const isText = pw.type === 'text';
    pw.type = isText ? 'password' : 'text';
    this.querySelector('.eye-on').style.display  = isText ? '' : 'none';
    this.querySelector('.eye-off').style.display = isText ? 'none' : '';
  });

  /* Form submit — prototype: any credentials work, store session flag via PHP fetch */
  form?.addEventListener('submit', function (e) {
    e.preventDefault();
    const email = document.getElementById('sim-email');
    const pw    = document.getElementById('sim-password');
    let valid = true;

    if (!email.value.trim()) {
      document.getElementById('sim-email-err').textContent = 'Email or Employee ID is required.';
      email.classList.add('invalid');
      valid = false;
    } else {
      document.getElementById('sim-email-err').textContent = '';
      email.classList.remove('invalid');
    }

    if (!pw.value) {
      document.getElementById('sim-pw-err').textContent = 'Password is required.';
      pw.classList.add('invalid');
      valid = false;
    } else {
      document.getElementById('sim-pw-err').textContent = '';
      pw.classList.remove('invalid');
    }

    if (!valid) return;

    const btn = document.getElementById('sim-submit');
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:simSpin .7s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Signing in…';

    /* Prototype: set session via lightweight endpoint, then redirect */
    fetch('signin_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ email: email.value, password: pw.value }),
    })
    .then(r => r.ok ? r.json() : Promise.reject())
    .then(() => {
      const dest = destIn.value || 'index.php';
      window.location.href = dest;
    })
    .catch(() => {
      /* Fallback: just redirect in prototype mode if endpoint missing */
      const dest = destIn.value || 'index.php';
      window.location.href = dest;
    });
  });

  /* SSO buttons (prototype) */
  document.querySelectorAll('.sim-sso').forEach(btn => {
    btn.addEventListener('click', function () {
      const dest = destIn.value || 'index.php';
      window.location.href = dest;
    });
  });
})();
</script>
<style>@keyframes simSpin { to { transform: rotate(360deg); } }</style>
<?php endif; ?>