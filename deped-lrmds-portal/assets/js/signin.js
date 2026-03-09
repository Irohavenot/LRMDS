// DepEd LRMDS – signin.js
// Handles sign-in form validation, password toggle, SSO simulation
(function () {
  'use strict';

  const qs = sel => document.querySelector(sel);

  const form     = qs('#signin-form');
  const emailEl  = qs('#email');
  const pwEl     = qs('#password');
  const pwToggle = qs('#pw-toggle');
  const signinBtn = qs('#signin-btn');
  const btnLabel  = signinBtn?.querySelector('.btn-label');
  const btnArrow  = signinBtn?.querySelector('.btn-arrow');
  const btnSpin   = signinBtn?.querySelector('.btn-spin');

  /* ── Password toggle ── */
  pwToggle?.addEventListener('click', () => {
    const isPassword = pwEl.type === 'password';
    pwEl.type = isPassword ? 'text' : 'password';
    qs('.icon-eye').style.display  = isPassword ? 'none' : '';
    qs('.icon-eye-off').style.display = isPassword ? '' : 'none';
    pwToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
  });

  /* ── Field validation helpers ── */
  function setError(inputEl, errId, msg) {
    inputEl.classList.add('invalid');
    inputEl.classList.remove('valid');
    const err = qs('#' + errId);
    if (err) err.textContent = msg;
    return false;
  }

  function clearError(inputEl, errId) {
    inputEl.classList.remove('invalid');
    inputEl.classList.add('valid');
    const err = qs('#' + errId);
    if (err) err.textContent = '';
  }

  function validateEmail() {
    const val = emailEl.value.trim();
    if (!val) return setError(emailEl, 'email-err', 'Email or Employee ID is required.');
    clearError(emailEl, 'email-err');
    return true;
  }

  function validatePassword() {
    const val = pwEl.value;
    if (!val) return setError(pwEl, 'pw-err', 'Password is required.');
    clearError(pwEl, 'pw-err');
    return true;
  }

  /* Live validation on blur */
  emailEl?.addEventListener('blur', validateEmail);
  pwEl?.addEventListener('blur', validatePassword);
  emailEl?.addEventListener('input', () => { if (emailEl.classList.contains('invalid')) validateEmail(); });
  pwEl?.addEventListener('input',    () => { if (pwEl.classList.contains('invalid'))   validatePassword(); });

  /* ── Form submit ── */
  form?.addEventListener('submit', e => {
    e.preventDefault();
    const ok = validateEmail() & validatePassword();
    if (!ok) return;

    // Show loading state
    btnLabel.textContent = 'Signing in…';
    btnArrow.style.display = 'none';
    btnSpin.style.display  = '';
    signinBtn.disabled = true;

    // Simulate async sign-in (redirect after 1.4s)
    setTimeout(() => {
      window.location.href = 'index.html';
    }, 1400);
  });

  /* ── SSO buttons (dummy) ── */
  qs('#btn-deped-sso')?.addEventListener('click', () => {
    alert('DepEd SSO would open here in a real implementation.');
  });
  qs('#btn-google')?.addEventListener('click', () => {
    alert('Google Workspace OAuth would open here in a real implementation.');
  });

})();