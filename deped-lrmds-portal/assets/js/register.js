// DepEd LRMDS – register.js
// Handles multi-step registration: validation, password strength, role selection
(function () {
  'use strict';

  const qs  = sel => document.querySelector(sel);
  const qsa = sel => Array.from(document.querySelectorAll(sel));

  let currentStep = 0;
  const TOTAL_STEPS = 3;

  const panels   = qsa('.reg-panel:not(#reg-panel-success)');
  const success  = qs('#reg-panel-success');
  const stepEls  = qsa('.rp-step');
  const lines    = qsa('.rp-line');
  const switchEl = qs('.rm-switch');

  /* ════════════════════
     STEP NAVIGATION
  ════════════════════ */
  function goTo(idx) {
    if (idx < 0 || idx >= TOTAL_STEPS) return;
    currentStep = idx;
    panels.forEach((p, i) => {
      p.hidden = i !== idx;
      if (i === idx) p.removeAttribute('hidden');
    });
    stepEls.forEach((s, i) => {
      s.classList.toggle('active', i === idx);
      s.classList.toggle('done', i < idx);
    });
    lines.forEach((l, i) => {
      l.classList.toggle('done', i < idx);
    });
  }

  /* ════════════════════
     FIELD HELPERS
  ════════════════════ */
  function err(id, msg) {
    const el = qs('#' + id);
    if (el) el.textContent = msg;
  }
  function clearErr(id) { err(id, ''); }

  function markInvalid(inputEl) {
    inputEl.classList.add('invalid');
    inputEl.classList.remove('valid');
  }
  function markValid(inputEl) {
    inputEl.classList.remove('invalid');
    inputEl.classList.add('valid');
  }

  /* ════════════════════
     PASSWORD TOGGLES
  ════════════════════ */
  qsa('.rf-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.dataset.target;
      const input = qs('#' + targetId);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.querySelector('.icon-eye').style.display     = show ? 'none' : '';
      btn.querySelector('.icon-eye-off').style.display = show ? '' : 'none';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  /* ════════════════════
     PASSWORD STRENGTH
  ════════════════════ */
  const pwInput  = qs('#reg-pw');
  const pwsFill  = qs('#pws-fill');
  const pwsLabel = qs('#pws-label');

  function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)  score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
  }

  const STRENGTH_MAP = [
    { label: '',        color: '#E5E7EB', pct: '0%'   },
    { label: 'Weak',    color: '#EF4444', pct: '20%'  },
    { label: 'Fair',    color: '#F97316', pct: '50%'  },
    { label: 'Good',    color: '#EAB308', pct: '75%'  },
    { label: 'Strong',  color: '#22C55E', pct: '90%'  },
    { label: 'Excellent', color: '#059669', pct: '100%' },
  ];

  pwInput?.addEventListener('input', () => {
    const score = pwInput.value ? Math.min(checkStrength(pwInput.value), 5) : 0;
    const s = STRENGTH_MAP[score];
    if (pwsFill) { pwsFill.style.width = s.pct; pwsFill.style.background = s.color; }
    if (pwsLabel) { pwsLabel.textContent = s.label; pwsLabel.style.color = s.color; }
  });

  /* ════════════════════
     ROLE CARDS
  ════════════════════ */
  qsa('.rf-role-card').forEach(card => {
    card.addEventListener('click', () => {
      qsa('.rf-role-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      card.querySelector('input[type="radio"]').checked = true;
      clearErr('reg-role-err');
    });
    // Keyboard support
    card.setAttribute('tabindex', '0');
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
  });

  /* ════════════════════
     STEP 0 VALIDATION
  ════════════════════ */
  function validateStep0() {
    let valid = true;
    const email = qs('#reg-email');
    const pw    = qs('#reg-pw');
    const pw2   = qs('#reg-pw2');

    // Email
    if (!email.value.trim()) {
      markInvalid(email); err('reg-email-err', 'Email is required.'); valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      markInvalid(email); err('reg-email-err', 'Enter a valid email address.'); valid = false;
    } else { markValid(email); clearErr('reg-email-err'); }

    // Password
    if (!pw.value) {
      markInvalid(pw); err('reg-pw-err', 'Password is required.'); valid = false;
    } else if (pw.value.length < 8) {
      markInvalid(pw); err('reg-pw-err', 'Password must be at least 8 characters.'); valid = false;
    } else { markValid(pw); clearErr('reg-pw-err'); }

    // Confirm
    if (!pw2.value) {
      markInvalid(pw2); err('reg-pw2-err', 'Please confirm your password.'); valid = false;
    } else if (pw2.value !== pw.value) {
      markInvalid(pw2); err('reg-pw2-err', 'Passwords do not match.'); valid = false;
    } else { markValid(pw2); clearErr('reg-pw2-err'); }

    return valid;
  }

  qs('#reg-next-0')?.addEventListener('click', () => {
    if (validateStep0()) goTo(1);
  });

  // Live clear on input
  ['reg-email','reg-pw','reg-pw2'].forEach(id => {
    qs('#' + id)?.addEventListener('input', () => {
      const el = qs('#' + id);
      if (el.classList.contains('invalid')) {
        el.classList.remove('invalid');
        clearErr(id + '-err');
      }
    });
  });

  /* ════════════════════
     STEP 1 VALIDATION
  ════════════════════ */
  function validateStep1() {
    let valid = true;
    const fname  = qs('#reg-fname');
    const lname  = qs('#reg-lname');
    const region = qs('#reg-region');

    if (!fname.value.trim()) {
      markInvalid(fname); err('reg-fname-err', 'First name is required.'); valid = false;
    } else { markValid(fname); clearErr('reg-fname-err'); }

    if (!lname.value.trim()) {
      markInvalid(lname); err('reg-lname-err', 'Last name is required.'); valid = false;
    } else { markValid(lname); clearErr('reg-lname-err'); }

    if (!region.value) {
      markInvalid(region); err('reg-region-err', 'Please select your region.'); valid = false;
    } else { markValid(region); clearErr('reg-region-err'); }

    return valid;
  }

  qs('#reg-next-1')?.addEventListener('click', () => {
    if (validateStep1()) goTo(2);
  });
  qs('#reg-back-1')?.addEventListener('click', () => goTo(0));

  /* ════════════════════
     STEP 2 VALIDATION & SUBMIT
  ════════════════════ */
  function validateStep2() {
    let valid = true;
    const role  = document.querySelector('input[name="role"]:checked');
    const terms = qs('#reg-terms');
    const termsLabel = qs('#terms-label');

    if (!role) {
      err('reg-role-err', 'Please select your role.'); valid = false;
    } else { clearErr('reg-role-err'); }

    if (!terms.checked) {
      termsLabel.classList.add('error-label');
      err('reg-terms-err', 'You must agree to the Terms of Use to continue.');
      valid = false;
    } else {
      termsLabel.classList.remove('error-label');
      clearErr('reg-terms-err');
    }

    return valid;
  }

  const submitBtn  = qs('#reg-submit');
  const sBtnLabel  = submitBtn?.querySelector('.btn-label');
  const sBtnArrow  = submitBtn?.querySelector('.btn-arrow');
  const sBtnSpin   = submitBtn?.querySelector('.btn-spin');

  qs('#reg-submit')?.addEventListener('click', () => {
    if (!validateStep2()) return;

    // Loading state
    sBtnLabel.textContent = 'Creating account…';
    sBtnArrow.style.display = 'none';
    sBtnSpin.style.display  = '';
    submitBtn.disabled = true;

    // Simulate API call
    setTimeout(() => {
      panels.forEach(p => { p.hidden = true; });
      success.hidden = false;
      if (switchEl) switchEl.style.display = 'none';
      // Mark all steps done
      stepEls.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });
      lines.forEach(l => l.classList.add('done'));
      success.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 1600);
  });

  qs('#reg-back-2')?.addEventListener('click', () => goTo(1));

  /* ── Terms live clear ── */
  qs('#reg-terms')?.addEventListener('change', () => {
    if (qs('#reg-terms').checked) {
      qs('#terms-label')?.classList.remove('error-label');
      clearErr('reg-terms-err');
    }
  });

  /* ── Init ── */
  goTo(0);

})();