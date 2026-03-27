// DepEd LRMDS – register.js  (Role → Profile → Account)
(function () {
  'use strict';

  const qs  = sel => document.querySelector(sel);
  const qsa = sel => Array.from(document.querySelectorAll(sel));

  let currentStep = 0;
  let selectedRole = '';
  const TOTAL_STEPS = 3;

  const panels   = qsa('.reg-panel:not(#reg-panel-success)');
  const success  = qs('#reg-panel-success');
  const stepEls  = qsa('.rp-step');
  const lines    = qsa('.rp-line');
  const switchEl = qs('.rm-switch');

  /* ════════════════════════════════════
     ROLE LABELS  (for badge + success msg)
  ════════════════════════════════════ */
  const ROLE_LABELS = {
    teacher:     'Teacher',
    learner:     'Learner',
    parent:      'Parent / Guardian',
    'school-head': 'School Head / Curriculum',
    developer:   'Content Developer / Partner',
  };

  /* ════════════════════════════════════
     STEP NAVIGATION
  ════════════════════════════════════ */
  function goTo(idx) {
    if (idx < 0 || idx >= TOTAL_STEPS) return;
    currentStep = idx;
    panels.forEach((p, i) => {
      p.hidden = i !== idx;
      if (i === idx) p.removeAttribute('hidden');
    });
    stepEls.forEach((s, i) => {
      s.classList.toggle('active', i === idx);
      s.classList.toggle('done',   i < idx);
    });
    lines.forEach((l, i) => l.classList.toggle('done', i < idx));
    qs('.rm-inner')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ════════════════════════════════════
     FIELD HELPERS
  ════════════════════════════════════ */
  function err(id, msg) { const el = qs('#' + id); if (el) el.textContent = msg; }
  function clearErr(id) { err(id, ''); }
  function markInvalid(el) { el.classList.add('invalid');   el.classList.remove('valid'); }
  function markValid(el)   { el.classList.remove('invalid'); el.classList.add('valid');   }

  /* ════════════════════════════════════
     ROLE-ADAPTIVE PROFILE STEP
     Shows the correct field section
     and updates the badge label
  ════════════════════════════════════ */
  function applyRoleToProfileStep(role) {
    // Hide all role-specific sections
    qsa('.role-fields').forEach(el => el.classList.remove('visible'));

    // Show the matching one
    const section = qs('#fields-' + role);
    if (section) section.classList.add('visible');

    // Update the badge
    const badge     = qs('#role-badge');
    const badgeText = qs('#role-badge-text');
    if (badge && badgeText) {
      badgeText.textContent = ROLE_LABELS[role] || role;
      badge.style.display   = 'inline-flex';
    }
  }

  /* ════════════════════════════════════
     CHECKBOX ITEMS  (visual highlight)
  ════════════════════════════════════ */
  qsa('.rf-check-item').forEach(item => {
    const cb = item.querySelector('input[type="checkbox"]');
    if (!cb) return;
    cb.addEventListener('change', () => {
      item.classList.toggle('checked', cb.checked);
    });
  });

  /* ════════════════════════════════════
     STAFF ROLES TOGGLE
  ════════════════════════════════════ */
  const staffToggleBtn  = qs('#staff-toggle-btn');
  const staffRolesPanel = qs('#staff-roles-panel');

  staffToggleBtn?.addEventListener('click', () => {
    const isOpen = staffRolesPanel.classList.toggle('open');
    staffToggleBtn.setAttribute('aria-expanded', isOpen);
    staffRolesPanel.setAttribute('aria-hidden', !isOpen);
    staffToggleBtn.textContent = isOpen
      ? 'Hide staff / partner roles ▴'
      : 'Are you DepEd staff or a content partner? ▾';
  });

  /* ════════════════════════════════════
     ROLE CARDS
  ════════════════════════════════════ */
  qsa('.rf-role-card').forEach(card => {
    card.addEventListener('click', () => {
      qsa('.rf-role-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      const radio = card.querySelector('input[type="radio"]');
      radio.checked = true;
      selectedRole  = radio.value;
      clearErr('reg-role-err');
    });
    card.setAttribute('tabindex', '0');
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
  });

  /* ════════════════════════════════════
     PASSWORD TOGGLES
  ════════════════════════════════════ */
  qsa('.rf-pw-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = qs('#' + btn.dataset.target);
      if (!input) return;
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.querySelector('.icon-eye').style.display     = show ? 'none' : '';
      btn.querySelector('.icon-eye-off').style.display = show ? '' : 'none';
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  /* ════════════════════════════════════
     PASSWORD STRENGTH
  ════════════════════════════════════ */
  const pwInput  = qs('#reg-pw');
  const pwsFill  = qs('#pws-fill');
  const pwsLabel = qs('#pws-label');

  function checkStrength(pw) {
    let score = 0;
    if (pw.length >= 8)          score++;
    if (pw.length >= 12)         score++;
    if (/[A-Z]/.test(pw))        score++;
    if (/[0-9]/.test(pw))        score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;
    return score;
  }

  const STRENGTH_MAP = [
    { label: '',          color: '#E5E7EB', pct: '0%'   },
    { label: 'Weak',      color: '#EF4444', pct: '20%'  },
    { label: 'Fair',      color: '#F97316', pct: '50%'  },
    { label: 'Good',      color: '#EAB308', pct: '75%'  },
    { label: 'Strong',    color: '#22C55E', pct: '90%'  },
    { label: 'Excellent', color: '#059669', pct: '100%' },
  ];

  pwInput?.addEventListener('input', () => {
    const score = pwInput.value ? Math.min(checkStrength(pwInput.value), 5) : 0;
    const s = STRENGTH_MAP[score];
    if (pwsFill)  { pwsFill.style.width = s.pct; pwsFill.style.background = s.color; }
    if (pwsLabel) { pwsLabel.textContent = s.label; pwsLabel.style.color = s.color;   }
  });

  /* ════════════════════════════════════
     STEP 0 – Role validation
  ════════════════════════════════════ */
  function validateStep0() {
    const radio = document.querySelector('input[name="role"]:checked');
    if (!radio) { err('reg-role-err', 'Please select your role to continue.'); return false; }
    selectedRole = radio.value;
    clearErr('reg-role-err');
    return true;
  }

  qs('#reg-next-0')?.addEventListener('click', () => {
    if (validateStep0()) {
      applyRoleToProfileStep(selectedRole);
      goTo(1);
    }
  });

  /* ════════════════════════════════════
     STEP 1 – Profile validation
     Required fields vary by role
  ════════════════════════════════════ */
  function validateStep1() {
    let valid = true;
    const fname  = qs('#reg-fname');
    const lname  = qs('#reg-lname');
    const region = qs('#reg-region');

    if (!fname.value.trim())  { markInvalid(fname);  err('reg-fname-err',  'First name is required.'); valid = false; }
    else                      { markValid(fname);     clearErr('reg-fname-err'); }

    if (!lname.value.trim())  { markInvalid(lname);  err('reg-lname-err',  'Last name is required.'); valid = false; }
    else                      { markValid(lname);     clearErr('reg-lname-err'); }

    if (!region.value)        { markInvalid(region); err('reg-region-err', 'Please select your region.'); valid = false; }
    else                      { markValid(region);   clearErr('reg-region-err'); }

    // Role-specific required fields
    if (selectedRole === 'teacher') {
      const grade = qs('#reg-grade-level');
      if (grade && !grade.value) {
        markInvalid(grade);
        // Show a small inline message — reuse the region error space isn't ideal,
        // so just mark the field invalid; label is enough cue here
        valid = false;
      } else if (grade) { markValid(grade); }
    }

    if (selectedRole === 'learner') {
      const grade = qs('#reg-learner-grade');
      if (grade && !grade.value) { markInvalid(grade); valid = false; }
      else if (grade) { markValid(grade); }
    }

    if (selectedRole === 'parent') {
      const grade = qs('#reg-child-grade');
      if (grade && !grade.value) { markInvalid(grade); valid = false; }
      else if (grade) { markValid(grade); }
    }

    if (selectedRole === 'school-head') {
      const empId    = qs('#reg-employee-id-sh');
      const position = qs('#reg-position');
      const school   = qs('#reg-sh-school');
      if (empId    && !empId.value.trim())    { markInvalid(empId);    valid = false; }
      else if (empId)    { markValid(empId); }
      if (position && !position.value)        { markInvalid(position); valid = false; }
      else if (position) { markValid(position); }
      if (school   && !school.value.trim())   { markInvalid(school);   valid = false; }
      else if (school)   { markValid(school); }
    }

    if (selectedRole === 'developer') {
      const affil = qs('#reg-affiliation');
      if (affil && !affil.value.trim()) { markInvalid(affil); valid = false; }
      else if (affil) { markValid(affil); }
    }

    return valid;
  }

  qs('#reg-next-1')?.addEventListener('click', () => { if (validateStep1()) goTo(2); });
  qs('#reg-back-1')?.addEventListener('click', () => goTo(0));

  ['reg-fname','reg-lname','reg-region'].forEach(id => {
    qs('#' + id)?.addEventListener('input', () => {
      const el = qs('#' + id);
      if (el.classList.contains('invalid')) { el.classList.remove('invalid'); clearErr(id + '-err'); }
    });
  });

  /* ════════════════════════════════════
     STEP 2 – Account + Terms validation
  ════════════════════════════════════ */
  function validateStep2() {
    let valid = true;
    const email      = qs('#reg-email');
    const pw         = qs('#reg-pw');
    const pw2        = qs('#reg-pw2');
    const terms      = qs('#reg-terms');
    const termsLabel = qs('#terms-label');

    if (!email.value.trim()) {
      markInvalid(email); err('reg-email-err', 'Email is required.'); valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
      markInvalid(email); err('reg-email-err', 'Enter a valid email address.'); valid = false;
    } else { markValid(email); clearErr('reg-email-err'); }

    if (!pw.value) {
      markInvalid(pw); err('reg-pw-err', 'Password is required.'); valid = false;
    } else if (pw.value.length < 8) {
      markInvalid(pw); err('reg-pw-err', 'Password must be at least 8 characters.'); valid = false;
    } else { markValid(pw); clearErr('reg-pw-err'); }

    if (!pw2.value) {
      markInvalid(pw2); err('reg-pw2-err', 'Please confirm your password.'); valid = false;
    } else if (pw2.value !== pw.value) {
      markInvalid(pw2); err('reg-pw2-err', 'Passwords do not match.'); valid = false;
    } else { markValid(pw2); clearErr('reg-pw2-err'); }

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

  qs('#reg-back-2')?.addEventListener('click', () => goTo(1));

  ['reg-email','reg-pw','reg-pw2'].forEach(id => {
    qs('#' + id)?.addEventListener('input', () => {
      const el = qs('#' + id);
      if (el.classList.contains('invalid')) { el.classList.remove('invalid'); clearErr(id + '-err'); }
    });
  });

  qs('#reg-terms')?.addEventListener('change', () => {
    if (qs('#reg-terms').checked) {
      qs('#terms-label')?.classList.remove('error-label');
      clearErr('reg-terms-err');
    }
  });

  /* ════════════════════════════════════
     COLLECT ALL FORM DATA (incl. role fields)
  ════════════════════════════════════ */
  function buildFormData() {
    const fd = new FormData();
    fd.append('email',       qs('#reg-email').value.trim());
    fd.append('password',    qs('#reg-pw').value);
    fd.append('fname',       qs('#reg-fname').value.trim());
    fd.append('lname',       qs('#reg-lname').value.trim());
    fd.append('region',      qs('#reg-region').value);
    fd.append('division',    qs('#reg-division').value.trim());
    fd.append('role',        selectedRole);

    // Checkboxes (subjects, dev_types) — send as comma-joined string
    const subjects = [...document.querySelectorAll('input[name="subjects[]"]:checked')].map(c => c.value);
    const devTypes = [...document.querySelectorAll('input[name="dev_types[]"]:checked')].map(c => c.value);
    if (subjects.length) fd.append('subjects', subjects.join(','));
    if (devTypes.length) fd.append('dev_types', devTypes.join(','));

    // Role-specific fields — only append what's relevant
    const pick = (id, key) => { const el = qs('#' + id); if (el && el.value.trim()) fd.append(key, el.value.trim()); };
    switch (selectedRole) {
      case 'teacher':
        pick('reg-grade-level',  'grade_level');
        pick('reg-employee-id',  'employee_id');
        break;
      case 'learner':
        pick('reg-learner-grade',  'grade_level');
        pick('reg-learner-school', 'school_name');
        pick('reg-learner-lrn',    'lrn');
        break;
      case 'parent':
        pick('reg-child-grade',  'child_grade');
        pick('reg-child-school', 'child_school');
        break;
      case 'school-head':
        pick('reg-employee-id-sh', 'employee_id');
        pick('reg-position',       'position');
        pick('reg-sh-school',      'school_name');
        break;
      case 'developer':
        pick('reg-affiliation',    'affiliation');
        pick('reg-dev-position',   'dev_position');
        pick('reg-employee-id-dev','employee_id');
        break;
    }

    return fd;
  }

  /* ════════════════════════════════════
     SUBMIT  →  register_handler.php
  ════════════════════════════════════ */
  const submitBtn = qs('#reg-submit');
  const sBtnLabel = submitBtn?.querySelector('.btn-label');
  const sBtnArrow = submitBtn?.querySelector('.btn-arrow');
  const sBtnSpin  = submitBtn?.querySelector('.btn-spin');

  submitBtn?.addEventListener('click', () => {
    if (!validateStep2()) return;

    sBtnLabel.textContent   = 'Creating account…';
    sBtnArrow.style.display = 'none';
    sBtnSpin.style.display  = '';
    submitBtn.disabled      = true;

    fetch('register_handler.php', { method: 'POST', body: buildFormData() })
      .then(r => {
        if (!r.ok) throw new Error('Server error ' + r.status);
        return r.json();
      })
      .then(data => {
        if (data.success) {
          const t = qs('#success-title');
          const m = qs('#success-msg');
          if (data.requires_totp) {
            if (t) t.textContent = 'Almost there!';
            if (m) m.textContent =
              'Your account is created. You will now be asked to set up ' +
              'two-factor authentication — it only takes 2 minutes.';
            showSuccess();
            setTimeout(() => { window.location.href = data.redirect || 'totp_setup.php'; }, 2200);
          } else if (data.pending) {
            if (t) t.textContent = 'Registration Submitted!';
            if (m) m.textContent =
              'Your account is pending administrator verification. ' +
              'You will receive an email once your role has been approved.';
            showSuccess();
          } else {
            showSuccess();
          }
        } else {
          resetSubmitBtn();
          if (data.errors) {
            const idMap = {
              email: 'reg-email-err', password: 'reg-pw-err',
              fname: 'reg-fname-err', lname: 'reg-lname-err',
              region: 'reg-region-err', role: 'reg-role-err',
            };
            Object.entries(data.errors).forEach(([field, msg]) => {
              if (idMap[field]) err(idMap[field], msg);
              if (['fname','lname','region'].includes(field)) goTo(1);
              if (field === 'role') goTo(0);
            });
          } else {
            err('reg-submit-err', data.error || 'Something went wrong. Please try again.');
          }
        }
      })
      .catch(fetchErr => {
        console.error('Registration error:', fetchErr);
        resetSubmitBtn();
        err('reg-submit-err', 'Cannot reach the server. Make sure XAMPP (Apache + MySQL) is running.');
      });
  });

  function resetSubmitBtn() {
    sBtnLabel.textContent   = 'Create Account';
    sBtnArrow.style.display = '';
    sBtnSpin.style.display  = 'none';
    submitBtn.disabled      = false;
  }

  function showSuccess() {
    panels.forEach(p => { p.hidden = true; });
    success.hidden = false;
    if (switchEl) switchEl.style.display = 'none';
    stepEls.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });
    lines.forEach(l => l.classList.add('done'));
    success.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  /* ── Init ── */
  goTo(0);

})();