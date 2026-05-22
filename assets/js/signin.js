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
    generalErr = document.createElement('div');
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
    generalErr.innerHTML = msg;  // ← changed
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

  /* ── SSO buttons ── */

  /* DepEd SSO → OneDrive Resource Repository */
  qs('#btn-deped-sso')?.addEventListener('click', () => {

    document.getElementById('onedriveConfirmOverlay')?.remove();

    const overlay = document.createElement('div');
    overlay.id = 'onedriveConfirmOverlay';
    overlay.style.cssText =
      'position:fixed;inset:0;z-index:99999;' +
      'background:rgba(5,20,45,.6);backdrop-filter:blur(4px);' +
      'display:flex;align-items:center;justify-content:center;padding:20px';

    overlay.innerHTML =
      '<style>@keyframes popIn{from{transform:scale(.8);opacity:0}to{transform:scale(1);opacity:1}}</style>' +
      '<div style="background:#fff;border-radius:18px;padding:32px 28px 28px;' +
        'max-width:420px;width:100%;text-align:center;' +
        'box-shadow:0 24px 64px rgba(5,20,45,.3);' +
        'animation:popIn .25s cubic-bezier(.34,1.56,.64,1)">' +

        '<div style="width:56px;height:56px;border-radius:50%;background:#EFF6FF;' +
          'display:flex;align-items:center;justify-content:center;margin:0 auto 16px">' +
          '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0B4F9C" stroke-width="1.8">' +
            '<path d="M2 12.5a5.5 5.5 0 0 1 9-4.2A4 4 0 1 1 14 16H6a4 4 0 0 1-4-3.5Z"/>' +
            '<path d="M20 16h-2"/><path d="M14 12v4"/><path d="M17 14l-3-2-3 2"/>' +
          '</svg>' +
        '</div>' +

        '<h3 style="font-size:17px;font-weight:800;color:#111827;margin:0 0 8px">' +
          'You\'re being redirected' +
        '</h3>' +
        '<p style="font-size:13px;color:#6B7280;margin:0 0 6px;line-height:1.55">You\'ll be taken to the</p>' +
        '<p style="font-size:14px;font-weight:700;color:#0B4F9C;margin:0 0 18px">' +
          'DepEd OneDrive Resource Repository' +
        '</p>' +
        '<p style="font-size:12px;color:#9CA3AF;margin:0 0 24px;line-height:1.5">' +
          'Sign in there using your Microsoft / DepEd account to browse and access learning resources stored on OneDrive.' +
        '</p>' +

        '<div style="display:flex;gap:10px">' +
          '<button id="onedriveCancel" style="flex:1;padding:10px;background:#fff;' +
            'border:1.5px solid #E5E7EB;border-radius:9px;font-size:14px;font-weight:600;' +
            'font-family:inherit;color:#374151;cursor:pointer">Cancel</button>' +
          '<button id="onedriveGo" style="flex:1;padding:10px;background:#0B4F9C;border:none;' +
            'border-radius:9px;font-size:14px;font-weight:600;font-family:inherit;color:#fff;' +
            'cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">' +
              '<path d="M5 12h14M12 5l7 7-7 7"/>' +
            '</svg>Continue' +
          '</button>' +
        '</div>' +
      '</div>';

    document.body.appendChild(overlay);

    document.getElementById('onedriveCancel').addEventListener('click', () => overlay.remove());
    overlay.addEventListener('click', function (e) { if (e.target === this) this.remove(); });
    document.getElementById('onedriveGo').addEventListener('click', () => {
      window.location.href = '../onedrive/index.php';
    });
  });

  // Google is handled by google_oauth.php — no stub needed here

})();