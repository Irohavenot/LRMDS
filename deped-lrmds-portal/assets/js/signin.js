// DepEd LRMDS – signin.js
// Handles the standalone signin.php page.
// Sends credentials to signin_handler.php and follows the redirect.
(function () {
  'use strict';

  const qs = sel => document.querySelector(sel);

  const form      = qs('#signin-form');
  const emailEl   = qs('#email');
  const pwEl      = qs('#password');
  const pwToggle  = qs('#pw-toggle');
  const signinBtn = qs('#signin-btn');
  const btnLabel  = signinBtn?.querySelector('.btn-label');
  const btnArrow  = signinBtn?.querySelector('.btn-arrow');
  const btnSpin   = signinBtn?.querySelector('.btn-spin');

  // General error banner (sits below the button — we'll inject it if absent)
  let generalErr = qs('#signin-general-err');
  if (!generalErr && form) {
    generalErr = document.createElement('p');
    generalErr.id = 'signin-general-err';
    generalErr.style.cssText =
      'margin:10px 0 0;font-size:13px;color:#B91C1C;text-align:center;' +
      'background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;' +
      'padding:9px 14px;display:none';
    signinBtn.parentNode.insertBefore(generalErr, signinBtn.nextSibling);
  }

  /* ── Password toggle ── */
  pwToggle?.addEventListener('click', () => {
    const show = pwEl.type === 'password';
    pwEl.type = show ? 'text' : 'password';
    qs('.icon-eye').style.display     = show ? 'none' : '';
    qs('.icon-eye-off').style.display = show ? ''     : 'none';
    pwToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });

  /* ── Validation helpers ── */
  function setError(inputEl, errId, msg) {
    inputEl.classList.add('invalid');
    inputEl.classList.remove('valid');
    const el = qs('#' + errId);
    if (el) el.textContent = msg;
    return false;
  }

  function clearError(inputEl, errId) {
    inputEl.classList.remove('invalid');
    inputEl.classList.add('valid');
    const el = qs('#' + errId);
    if (el) el.textContent = '';
  }

  function showGeneralError(msg) {
    if (!generalErr) return;
    generalErr.textContent = msg;
    generalErr.style.display = 'block';
  }

  function hideGeneralError() {
    if (!generalErr) return;
    generalErr.style.display = 'none';
    generalErr.textContent = '';
  }

  function validateEmail() {
    const val = emailEl.value.trim();
    if (!val) return setError(emailEl, 'email-err', 'Email or Employee ID is required.');
    clearError(emailEl, 'email-err');
    return true;
  }

  function validatePassword() {
    if (!pwEl.value) return setError(pwEl, 'pw-err', 'Password is required.');
    clearError(pwEl, 'pw-err');
    return true;
  }

  /* Live validation on blur */
  emailEl?.addEventListener('blur', validateEmail);
  pwEl?.addEventListener('blur',    validatePassword);
  emailEl?.addEventListener('input', () => {
    if (emailEl.classList.contains('invalid')) validateEmail();
    hideGeneralError();
  });
  pwEl?.addEventListener('input', () => {
    if (pwEl.classList.contains('invalid')) validatePassword();
    hideGeneralError();
  });

  /* ── Loading state helpers ── */
  function setLoading() {
    if (btnLabel) btnLabel.textContent = 'Signing in…';
    if (btnArrow) btnArrow.style.display = 'none';
    if (btnSpin)  btnSpin.style.display  = '';
    if (signinBtn) signinBtn.disabled = true;
  }

  function resetLoading() {
    if (btnLabel) btnLabel.textContent = 'Sign In';
    if (btnArrow) btnArrow.style.display = '';
    if (btnSpin)  btnSpin.style.display  = 'none';
    if (signinBtn) signinBtn.disabled = false;
  }

  /* ── Form submit ── */
  form?.addEventListener('submit', e => {
    e.preventDefault();
    hideGeneralError();

    // Client-side presence check first (fast feedback)
    const ok = validateEmail() & validatePassword();
    if (!ok) return;

    setLoading();

    const fd = new FormData();
    fd.append('email',    emailEl.value.trim());
    fd.append('password', pwEl.value);

    fetch('signin_handler.php', { method: 'POST', body: fd })
      .then(r => {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
      })
      .then(data => {
        if (data.ok) {
          // Show brief success state then follow the redirect
          if (btnLabel) btnLabel.textContent = 'Redirecting…';
          setTimeout(() => {
            window.location.href = data.redirect || 'index.php';
          }, 600);
        } else {
          resetLoading();
          // Route the error to the right field or the general banner
          if (data.field === 'email') {
            setError(emailEl, 'email-err', data.msg);
          } else if (data.field === 'password') {
            setError(pwEl, 'pw-err', data.msg);
          } else {
            showGeneralError(data.msg);
          }
        }
      })
      .catch(() => {
        resetLoading();
        showGeneralError('Cannot reach the server. Make sure XAMPP (Apache + MySQL) is running.');
      });
  });

  /* ── SSO buttons (stubs — not implemented yet) ── */
  /* ── SSO buttons ── */
qs('#btn-deped-sso')?.addEventListener('click', () => {
  alert('DepEd SSO is not yet implemented in this prototype.');
});
// Google is now handled by google_oauth.php — no stub needed here

  /* ── Flash message from register/TOTP flow ── */
  // totp_setup.php sets $_SESSION['flash_success'] on completion
  // We read it via a data attribute injected by signin.php (see below)
  const flash = document.body.dataset.flash;
  if (flash) {
    const pill = document.createElement('div');
    pill.style.cssText =
      'background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46;' +
      'font-size:13px;font-weight:600;border-radius:8px;' +
      'padding:10px 14px;margin-bottom:16px;display:flex;align-items:center;gap:8px;' +
      'font-family:inherit';
    pill.innerHTML =
      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
      '<path d="M20 6 9 17l-5-5"/></svg>' + flash;
    const header = qs('.ar-header');
    if (header) header.insertAdjacentElement('afterend', pill);
  }

})();