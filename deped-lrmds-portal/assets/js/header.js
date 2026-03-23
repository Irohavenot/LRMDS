/**
 * header.js
 * Handles:
 *   - Sign-in modal (open, close, validation, submit, SSO)
 *   - Sign-out confirmation modal (open, close, confirm)
 *   - Protected nav link interception
 */
(function () {
  'use strict';


  /* ════════════════════════════════
     SIGN-OUT MODAL
  ════════════════════════════════ */

  const signoutModal  = document.getElementById('signout-modal');
  const soutCancel    = document.getElementById('sout-cancel');

  function openSignout() {
    if (!signoutModal) return;
    signoutModal.hidden = false;
    document.body.style.overflow = 'hidden';
    soutCancel && soutCancel.focus();
  }

  function closeSignout() {
    if (!signoutModal) return;
    signoutModal.hidden = true;
    document.body.style.overflow = '';
  }

  // Intercept the Sign Out button in the header
  document.getElementById('hdr-signout-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openSignout();
  });

  // Cancel button closes modal
  soutCancel?.addEventListener('click', closeSignout);

  // Click outside card closes modal
  signoutModal?.addEventListener('click', function (e) {
    if (e.target === signoutModal) closeSignout();
  });

  // Escape key closes modal
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && signoutModal && !signoutModal.hidden) closeSignout();
  });


  /* ════════════════════════════════
     SIGN-IN MODAL
  ════════════════════════════════ */

  const signinModal = document.getElementById('signin-modal');
  const closeBtn    = document.getElementById('sim-close');
  const form        = document.getElementById('sim-form');
  const destIn      = document.getElementById('sim-dest');
  const notice      = document.getElementById('sim-notice');
  const noticeT     = document.getElementById('sim-notice-text');

  // Human-readable labels for protected page destinations
  const PAGE_LABELS = {
    'submit.php':        'Develop / Submit',
    'manage.php':        'Manage',
    'train-support.php': 'Train & Support',
  };

  function openSignin(dest) {
    if (!signinModal) return;
    destIn.value = dest || '';
    const label = PAGE_LABELS[dest] || dest;
    if (dest) {
      notice.hidden = false;
      noticeT.textContent = 'Sign in to access "' + label + '".';
    } else {
      notice.hidden = true;
    }
    signinModal.hidden = false;
    document.body.style.overflow = 'hidden';
    setTimeout(function () {
      document.getElementById('sim-email')?.focus();
    }, 60);
  }

  function closeSignin() {
    if (!signinModal) return;
    signinModal.hidden = true;
    document.body.style.overflow = '';
    destIn.value = '';
    // Clear all validation states
    document.querySelectorAll('.sim-input').forEach(function (i) {
      i.classList.remove('invalid');
    });
    document.querySelectorAll('.sim-err').forEach(function (e) {
      e.textContent = '';
    });
    // Reset submit button if it was mid-loading
    const btn = document.getElementById('sim-submit');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML =
        '<span>Sign In</span>' +
        '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
          '<path d="M5 12h14M12 5l7 7-7 7"/>' +
        '</svg>';
    }
  }

  // Open on protected nav links
  document.querySelectorAll('[data-protected="true"]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      e.preventDefault();
      openSignin(this.dataset.dest || '');
    });
  });

  // Open on header Sign In button
  document.getElementById('hdr-signin-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openSignin('');
  });

  // Close on ✕ button
  closeBtn?.addEventListener('click', closeSignin);

  // Close on backdrop click
  signinModal?.addEventListener('click', function (e) {
    if (e.target === signinModal) closeSignin();
  });

  // Close on Escape (only if sign-out modal is not open)
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && signinModal && !signinModal.hidden) closeSignin();
  });

  // Password visibility toggle
  document.querySelector('.sim-pw-toggle')?.addEventListener('click', function () {
    const pw = document.getElementById('sim-password');
    const isText = pw.type === 'text';
    pw.type = isText ? 'password' : 'text';
    this.querySelector('.eye-on').style.display  = isText ? ''     : 'none';
    this.querySelector('.eye-off').style.display = isText ? 'none' : '';
  });


  /* ── Form validation helpers ── */

  function setError(inputEl, errId, msg) {
    inputEl.classList.add('invalid');
    const err = document.getElementById(errId);
    if (err) err.textContent = msg;
    return false;
  }

  function clearError(inputEl, errId) {
    inputEl.classList.remove('invalid');
    const err = document.getElementById(errId);
    if (err) err.textContent = '';
    return true;
  }

  function validateEmail() {
    const el = document.getElementById('sim-email');
    if (!el) return true;
    return el.value.trim()
      ? clearError(el, 'sim-email-err')
      : setError(el, 'sim-email-err', 'Email or Employee ID is required.');
  }

  function validatePassword() {
    const el = document.getElementById('sim-password');
    if (!el) return true;
    return el.value
      ? clearError(el, 'sim-pw-err')
      : setError(el, 'sim-pw-err', 'Password is required.');
  }

  // Live validation on blur / input
  document.getElementById('sim-email')?.addEventListener('blur', validateEmail);
  document.getElementById('sim-password')?.addEventListener('blur', validatePassword);
  document.getElementById('sim-email')?.addEventListener('input', function () {
    if (this.classList.contains('invalid')) validateEmail();
  });
  document.getElementById('sim-password')?.addEventListener('input', function () {
    if (this.classList.contains('invalid')) validatePassword();
  });


  /* ── Form submit ── */

  form?.addEventListener('submit', function (e) {
    e.preventDefault();

    const ok = validateEmail() & validatePassword();
    if (!ok) return;

    // Show loading state
    const btn = document.getElementById('sim-submit');
    btn.disabled = true;
    btn.innerHTML =
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" ' +
           'style="animation:simSpin .7s linear infinite">' +
        '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>' +
      '</svg> Signing in\u2026';

    const dest = destIn.value || 'index.php';

    fetch('signin_action.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        email:    document.getElementById('sim-email').value,
        password: document.getElementById('sim-password').value,
      }),
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (data.ok) {
        // Session confirmed set — safe to redirect
        window.location.href = dest;
      } else {
        btn.disabled = false;
        btn.innerHTML =
          '<span>Sign In</span>' +
          '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
            '<path d="M5 12h14M12 5l7 7-7 7"/>' +
          '</svg>';
        const err = document.getElementById('sim-email-err');
        if (err) err.textContent = 'Sign in failed. Please try again.';
      }
    })
    .catch(function () {
      // Network error — redirect anyway in prototype mode
      window.location.href = dest;
    });
  });


  /* ── SSO buttons ── */

  document.querySelectorAll('.sim-sso').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const dest = destIn ? (destIn.value || 'index.php') : 'index.php';
      fetch('signin_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ email: 'sso@deped.gov.ph', password: 'sso' }),
      })
      .then(function ()  { window.location.href = dest; })
      .catch(function () { window.location.href = dest; });
    });
  });

})();