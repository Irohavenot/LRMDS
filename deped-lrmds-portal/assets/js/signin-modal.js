/**
 * signin-modal.js
 * DepEd LRMDS – Sign In modal
 *
 * Injects the modal markup into the page, then wires:
 *   • Every  a[href="signin.html"]  or  a[href="signin.php"]  → opens modal
 *   • The "Sign In" button in the header → opens modal
 *   • Overlay click / Escape key → closes modal
 *   • Full form validation + loading + success states
 *   • Password visibility toggle
 *   • SSO button stubs
 */

(function () {
  'use strict';

  /* ─── 1. Inject modal markup ─────────────────────────────── */
  var MODAL_HTML = [
    '<div class="signin-overlay" id="signinOverlay" role="dialog" aria-modal="true" aria-labelledby="smTitle">',

      '<div class="signin-modal">',

        /* Close button */
        '<button class="signin-close" id="signinClose" aria-label="Close sign in">',
          '<svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        '</button>',

        /* ── Left decorative panel ── */
        '<div class="sm-left" aria-hidden="true">',
          '<div class="sm-brand">',
            '<img src="assets/img/logo.svg" alt="DepEd"/>',
            '<div class="sm-brand-text">',
              '<p class="sm-dept">Department of Education</p>',
              '<p class="sm-sys">Learning Resource Management<br>&amp; Development System</p>',
            '</div>',
          '</div>',
          '<blockquote class="sm-quote">',
            '"Quality learning resources for every Filipino learner, in every corner of the Philippines."',
          '</blockquote>',
          '<div class="sm-stats">',
            '<div class="sm-stat"><strong>50,000+</strong><span>Resources</span></div>',
            '<div class="sm-stat"><strong>K – 12</strong><span>Grade Levels</span></div>',
            '<div class="sm-stat"><strong>17</strong><span>Regions</span></div>',
          '</div>',
        '</div>',

        /* ── Right form panel ── */
        '<div class="sm-right">',

          '<div id="smFormArea">',

            '<div class="sm-header">',
              '<h2 id="smTitle">Welcome back</h2>',
              '<p>Sign in to access your LRMDS account.</p>',
            '</div>',

            '<div class="sm-demo-pill">',
              '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>',
              'Prototype — any credentials will work',
            '</div>',

            '<form id="smForm" novalidate autocomplete="on">',

              /* Email */
              '<div class="sm-field" id="smFgEmail">',
                '<label class="sm-label" for="smEmail">',
                  '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>',
                  'Email or Employee ID',
                '</label>',
                '<input class="sm-input" type="text" id="smEmail" name="email" placeholder="yourname@deped.gov.ph" autocomplete="username" required aria-describedby="smEmailErr"/>',
                '<span class="sm-error" id="smEmailErr" role="alert"></span>',
              '</div>',

              /* Password */
              '<div class="sm-field" id="smFgPw">',
                '<label class="sm-label" for="smPassword">',
                  '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
                  'Password',
                '</label>',
                '<div class="sm-pw-wrap">',
                  '<input class="sm-input" type="password" id="smPassword" name="password" placeholder="Enter your password" autocomplete="current-password" required aria-describedby="smPwErr"/>',
                  '<button type="button" class="sm-pw-toggle" id="smPwToggle" aria-label="Show password">',
                    '<svg class="sm-eye-on"  fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>',
                    '<svg class="sm-eye-off" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>',
                  '</button>',
                '</div>',
                '<span class="sm-error" id="smPwErr" role="alert"></span>',
              '</div>',

              /* Remember / Forgot */
              '<div class="sm-inline-row">',
                '<label class="sm-check-label">',
                  '<input type="checkbox" id="smRemember" name="remember"/>',
                  '<span class="sm-checkmark"></span>',
                  'Keep me signed in',
                '</label>',
                '<a class="sm-link" href="#">Forgot password?</a>',
              '</div>',

              /* Submit */
              '<button type="submit" class="sm-btn-primary" id="smSubmitBtn">',
                '<span class="sm-btn-label">Sign In</span>',
                '<svg class="sm-btn-arrow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="width:16px;height:16px"><path d="M5 12h14M12 5l7 7-7 7"/></svg>',
                '<svg class="sm-btn-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none;width:16px;height:16px"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
              '</button>',

              /* Divider */
              '<div class="sm-divider"><span>or sign in with</span></div>',

              /* SSO */
              '<div class="sm-sso-row">',
                '<button type="button" class="sm-sso-btn" id="smBtnDepedSSO">',
                  '<img src="assets/img/logo.svg" alt=""/>',
                  'DepEd SSO',
                '</button>',
                '<button type="button" class="sm-sso-btn" id="smBtnGoogle">',
                  '<svg width="17" height="17" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23Z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62Z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z"/></svg>',
                  'Google Workspace',
                '</button>',
              '</div>',

              '<p class="sm-register">Don\'t have an account? <a class="sm-link" href="register.html">Register here</a></p>',

            '</form>',

          '</div>',

          /* Success state (hidden until sign-in succeeds) */
          '<div class="sm-success" id="smSuccess">',
            '<div class="sm-success-icon">',
              '<svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>',
            '</div>',
            '<h3>Signed in!</h3>',
            '<p>Redirecting you to your dashboard…</p>',
          '</div>',

        '</div>',

      '</div>',

    '</div>'
  ].join('');

  document.body.insertAdjacentHTML('beforeend', MODAL_HTML);

  /* ─── 2. Cache elements ──────────────────────────────────── */
  var overlay   = document.getElementById('signinOverlay');
  var closeBtn  = document.getElementById('signinClose');
  var form      = document.getElementById('smForm');
  var formArea  = document.getElementById('smFormArea');
  var successEl = document.getElementById('smSuccess');
  var emailEl   = document.getElementById('smEmail');
  var pwEl      = document.getElementById('smPassword');
  var pwToggle  = document.getElementById('smPwToggle');
  var submitBtn = document.getElementById('smSubmitBtn');

  /* ─── 3. Open / close helpers ────────────────────────────── */
  function openModal() {
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    // Focus email after animation
    setTimeout(function () { emailEl && emailEl.focus(); }, 260);
  }

  function closeModal() {
    overlay.classList.remove('open');
    document.body.style.overflow = '';
    // Reset form after transition
    setTimeout(resetForm, 250);
  }

  function resetForm() {
    form && form.reset();
    successEl.classList.remove('show');
    formArea.style.display = '';
    [emailEl, pwEl].forEach(function (el) {
      if (el) { el.classList.remove('invalid', 'valid'); }
    });
    clearError(emailEl, 'smEmailErr');
    clearError(pwEl, 'smPwErr');
    resetBtn();
  }

  /* ─── 4. Wire trigger links / buttons ───────────────────── */
  function interceptSignInLinks() {
    // Intercept all <a href="signin.html"> and <a href="signin.php">
    document.querySelectorAll('a[href="signin.html"], a[href="signin.php"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        openModal();
      });
    });
  }

  // Run once DOM ready (already is, we're at end of body) and again after
  // any dynamic header injection
  interceptSignInLinks();

  // Also catch clicks on .button.primary text "Sign In" that may not be <a> tags
  document.addEventListener('click', function (e) {
    var t = e.target.closest('a, button');
    if (!t) return;

    // anchor pointing to signin
    if (t.tagName === 'A' && /signin\.(html|php)$/i.test(t.getAttribute('href') || '')) {
      e.preventDefault();
      openModal();
      return;
    }

    // header "Sign In" button (text match fallback)
    if (t.tagName === 'A' && (t.textContent || '').trim() === 'Sign In' && t.closest('.header')) {
      e.preventDefault();
      openModal();
    }
  });

  /* ─── 5. Close triggers ──────────────────────────────────── */
  closeBtn.addEventListener('click', closeModal);

  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeModal();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && overlay.classList.contains('open')) closeModal();
  });

  /* ─── 6. Password toggle ─────────────────────────────────── */
  pwToggle && pwToggle.addEventListener('click', function () {
    var isHidden = pwEl.type === 'password';
    pwEl.type = isHidden ? 'text' : 'password';
    pwToggle.querySelector('.sm-eye-on').style.display  = isHidden ? 'none' : '';
    pwToggle.querySelector('.sm-eye-off').style.display = isHidden ? '' : 'none';
    pwToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
  });

  /* ─── 7. Validation helpers ──────────────────────────────── */
  function setError(inputEl, errId, msg) {
    inputEl.classList.add('invalid');
    inputEl.classList.remove('valid');
    var err = document.getElementById(errId);
    if (err) err.textContent = msg;
    return false;
  }

  function clearError(inputEl, errId) {
    if (inputEl) {
      inputEl.classList.remove('invalid');
      inputEl.classList.add('valid');
    }
    var err = document.getElementById(errId);
    if (err) err.textContent = '';
  }

  function validateEmail() {
    var val = emailEl.value.trim();
    if (!val) return setError(emailEl, 'smEmailErr', 'Email or Employee ID is required.');
    clearError(emailEl, 'smEmailErr');
    return true;
  }

  function validatePassword() {
    if (!pwEl.value) return setError(pwEl, 'smPwErr', 'Password is required.');
    clearError(pwEl, 'smPwErr');
    return true;
  }

  emailEl && emailEl.addEventListener('blur', validateEmail);
  pwEl    && pwEl.addEventListener('blur', validatePassword);
  emailEl && emailEl.addEventListener('input', function () {
    if (emailEl.classList.contains('invalid')) validateEmail();
  });
  pwEl && pwEl.addEventListener('input', function () {
    if (pwEl.classList.contains('invalid')) validatePassword();
  });

  /* ─── 8. Button loading state ────────────────────────────── */
  function setLoading() {
    submitBtn.disabled = true;
    submitBtn.querySelector('.sm-btn-label').textContent = 'Signing in…';
    submitBtn.querySelector('.sm-btn-arrow').style.display = 'none';
    submitBtn.querySelector('.sm-btn-spin').style.display  = '';
  }

  function resetBtn() {
    submitBtn.disabled = false;
    submitBtn.querySelector('.sm-btn-label').textContent = 'Sign In';
    submitBtn.querySelector('.sm-btn-arrow').style.display = '';
    submitBtn.querySelector('.sm-btn-spin').style.display  = 'none';
  }

  /* ─── 9. Form submit ─────────────────────────────────────── */
  form && form.addEventListener('submit', function (e) {
    e.preventDefault();

    // Validate both fields (use bitwise & so both run)
    var ok = validateEmail() & validatePassword();
    if (!ok) return;

    setLoading();

    // Simulate async authentication (1.4 s)
    setTimeout(function () {
      // Show success state
      formArea.style.display = 'none';
      successEl.classList.add('show');

      // Close modal + optionally redirect after 2 s
      setTimeout(function () {
        closeModal();
        // In a real app you'd do: window.location.href = 'dashboard.html';
      }, 2000);
    }, 1400);
  });

  /* ─── 10. SSO stubs ──────────────────────────────────────── */
  document.getElementById('smBtnDepedSSO') &&
    document.getElementById('smBtnDepedSSO').addEventListener('click', function () {
      alert('DepEd SSO would open here in a real implementation.');
    });

  document.getElementById('smBtnGoogle') &&
    document.getElementById('smBtnGoogle').addEventListener('click', function () {
      alert('Google Workspace OAuth would open here in a real implementation.');
    });

})();