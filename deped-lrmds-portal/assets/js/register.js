// DepEd LRMDS – register.js  (Role → Profile → Account → Review)
// Handles: multi-step nav, role-adaptive fields, advisory class builder,
// CID/SGOD separate role fields, form submission, review panel.

(function () {
  'use strict';

  const qs  = sel => document.querySelector(sel);
  const qsa = sel => Array.from(document.querySelectorAll(sel));

  let currentStep  = 0;
  let selectedRole = '';
  const TOTAL_STEPS = 4;

  const panels   = qsa('.reg-panel:not(#reg-panel-success):not(#reg-panel-totp-handoff)');
  const success  = qs('#reg-panel-success');
  const stepEls  = qsa('.rp-step');
  const lines    = qsa('.rp-line');
  const switchEl = qs('.rm-switch');

  /* ════════════════════════════════════
     ROLE LABELS  (for badge + review)
     Map new granular roles → display names
  ════════════════════════════════════ */
  const ROLE_LABELS = {
    teacher:          'Teacher',
    learner:          'Learner',
    parent:           'Parent / Guardian',
    'school-head':    'School Head (Principal / Head Teacher)',
    psds:             'Public Schools District Supervisor (PSDS)',
    eps:              'Education Program Supervisor (EPS) — CID',
    'eps-sgod':       'Education Program Supervisor (EPS) — SGOD',
    ces:              'Chief Education Supervisor (CES) — CID',
    'ces-sgod':       'Chief Education Supervisor (CES) — SGOD',
    specialist:       'CID Specialist',
    'specialist-sgod':'SGOD Specialist',
    asds:             'Assistant Schools Division Superintendent (ASDS)',
    sds:              'Schools Division Superintendent (SDS)',
    pdo:              'Project Development Officer (PDO)',
    developer:        'Content Developer / Partner',
  };

  /* Roles that need TOTP setup (maps to what register_handler.php expects) */
  const TOTP_ROLES = ['teacher','school-head','psds','eps','eps-sgod','ces','ces-sgod',
                      'specialist','specialist-sgod','asds','sds','pdo','developer'];

  /* ════════════════════════════════════
     ADVISORY CLASS BUILDER
  ════════════════════════════════════ */
  // advisoryClasses = [{ grade: 'g7', gradeLabel: 'Grade 7', section: 'Mabini' }, ...]
  let advisoryClasses = [];

  function renderAdvisoryList() {
    const list   = qs('#advisory-list');
    const empty  = qs('#advisory-empty');
    const badge  = qs('#advisory-count');
    if (!list) return;

    // Clear non-empty rows
    qsa('.advisory-row').forEach(r => r.remove());

    if (advisoryClasses.length === 0) {
      if (empty) empty.style.display = '';
    } else {
      if (empty) empty.style.display = 'none';
      advisoryClasses.forEach(function (cls, idx) {
        const row = document.createElement('div');
        row.className = 'advisory-row';
        row.innerHTML =
          '<span class="advisory-row-grade">' + cls.gradeLabel + '</span>' +
          '<input class="advisory-row-section" type="text" placeholder="Section name (optional)" ' +
          'value="' + escHtml(cls.section) + '" aria-label="Section for ' + cls.gradeLabel + '"/>' +
          '<button type="button" class="advisory-row-remove" aria-label="Remove ' + cls.gradeLabel + '">✕</button>';
        // Section input sync
        row.querySelector('.advisory-row-section').addEventListener('input', function () {
          advisoryClasses[idx].section = this.value;
          syncAdvisoryHidden();
        });
        // Remove button
        row.querySelector('.advisory-row-remove').addEventListener('click', function () {
          advisoryClasses.splice(idx, 1);
          // Unmark button
          const btn = qs('.advisory-grade-btn[data-grade="' + cls.grade + '"]');
          if (btn) btn.classList.remove('active');
          renderAdvisoryList();
        });
        list.appendChild(row);
      });
    }

    if (badge) badge.textContent = advisoryClasses.length;
    syncAdvisoryHidden();

    // Update grade button states
    qsa('.advisory-grade-btn').forEach(function (btn) {
      const hasIt = advisoryClasses.some(c => c.grade === btn.dataset.grade);
      btn.classList.toggle('active', hasIt);
    });
  }

  function syncAdvisoryHidden() {
    const hidden = qs('#reg-advisory-data');
    if (!hidden) return;
    // Serialize as JSON string: [{"grade":"g7","label":"Grade 7","section":"Mabini"}, ...]
    hidden.value = advisoryClasses.length
      ? JSON.stringify(advisoryClasses.map(c => ({ grade: c.grade, label: c.gradeLabel, section: c.section })))
      : '';
  }

  function escHtml(str) {
    return (str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // Grade button click handler
  qsa('.advisory-grade-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const grade = btn.dataset.grade;
      const label = btn.dataset.label;
      const exists = advisoryClasses.findIndex(c => c.grade === grade);
      if (exists >= 0) {
        // Toggle off — remove
        advisoryClasses.splice(exists, 1);
      } else {
        // Toggle on — add
        advisoryClasses.push({ grade: grade, gradeLabel: label, section: '' });
      }
      renderAdvisoryList();
    });
  });

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
    // Scroll to top of inner on mobile
    const inner = qs('.rm-inner') || qs('#main-content');
    if (inner) inner.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ════════════════════════════════════
     FIELD HELPERS
  ════════════════════════════════════ */
  function err(id, msg)   { const el = qs('#' + id); if (el) el.textContent = msg; }
  function clearErr(id)   { err(id, ''); }
  function markInvalid(el){ el.classList.add('invalid'); el.classList.remove('valid'); }
  function markValid(el)  { el.classList.remove('invalid'); el.classList.add('valid'); }

  /* ════════════════════════════════════
     ROLE-ADAPTIVE PROFILE STEP
  ════════════════════════════════════ */
  function applyRoleToProfileStep(role) {
    qsa('.role-fields').forEach(el => el.classList.remove('visible'));
    const section = qs('#fields-' + role);
    if (section) section.classList.add('visible');
    const badge     = qs('#role-badge');
    const badgeText = qs('#role-badge-text');
    if (badge && badgeText) {
      badgeText.textContent = ROLE_LABELS[role] || role;
      badge.style.display   = 'inline-flex';
    }
  }

  /* ════════════════════════════════════
     CHECKBOX ITEMS (visual highlight)
  ════════════════════════════════════ */
  qsa('.rf-check-item').forEach(item => {
    const cb = item.querySelector('input[type="checkbox"]');
    if (!cb) return;
    cb.addEventListener('change', () => item.classList.toggle('checked', cb.checked));
  });

  /* ════════════════════════════════════
     STAFF ROLES TOGGLE
  ════════════════════════════════════ */
  const staffToggleBtn  = qs('#staff-toggle-btn');
  const staffRolesPanel = qs('#staff-roles-panel');

  staffToggleBtn?.addEventListener('click', () => {
    const isOpen = staffRolesPanel.classList.toggle('open');
    staffToggleBtn.classList.toggle('open', isOpen);
    staffToggleBtn.setAttribute('aria-expanded', isOpen);
    staffRolesPanel.setAttribute('aria-hidden', !isOpen);
  });

  /* ════════════════════════════════════
     ROLE CARDS (public roles)
  ════════════════════════════════════ */
  qsa('.rf-role-card').forEach(card => {
    card.addEventListener('click', () => {
      qsa('.rf-role-card, .staff-role-item').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      const radio = card.querySelector('input[type="radio"]');
      if (radio) { radio.checked = true; selectedRole = radio.value; }
      clearErr('reg-role-err');
    });
    card.setAttribute('tabindex', '0');
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); card.click(); }
    });
  });

  /* ════════════════════════════════════
     STAFF ROLE ITEMS
  ════════════════════════════════════ */
  qsa('.staff-role-item').forEach(item => {
    item.addEventListener('click', () => {
      qsa('.rf-role-card, .staff-role-item').forEach(c => c.classList.remove('selected'));
      item.classList.add('selected');
      const radio = item.querySelector('input[type="radio"]');
      if (radio) { radio.checked = true; selectedRole = radio.value; }
      clearErr('reg-role-err');
    });
    item.setAttribute('tabindex', '0');
    item.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); item.click(); }
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
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
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
    if (pwsLabel) { pwsLabel.textContent = s.label; pwsLabel.style.color = s.color; }
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
  ════════════════════════════════════ */
  function validateStep1() {
    let valid = true;
    const fname  = qs('#reg-fname');
    const lname  = qs('#reg-lname');
    const region = qs('#reg-region');

    if (!fname.value.trim()) { markInvalid(fname); err('reg-fname-err', 'First name is required.'); valid = false; }
    else                     { markValid(fname);   clearErr('reg-fname-err'); }

    if (!lname.value.trim()) { markInvalid(lname); err('reg-lname-err', 'Last name is required.'); valid = false; }
    else                     { markValid(lname);   clearErr('reg-lname-err'); }

    if (!region.value)       { markInvalid(region); err('reg-region-err', 'Please select your region.'); valid = false; }
    else                     { markValid(region);  clearErr('reg-region-err'); }

    // Division — now a text input
    const division = qs('#reg-division');
    if (division && !division.value.trim()) {
      markInvalid(division); err('reg-division-err', 'Please enter your division.'); valid = false;
    } else if (division) { markValid(division); clearErr('reg-division-err'); }

    // Role-specific validation
    if (selectedRole === 'teacher') {
      const school = qs('#reg-teacher-school');
      if (school && !school.value.trim()) { markInvalid(school); err('reg-teacher-school-err', 'School name is required.'); valid = false; }
      else if (school) { markValid(school); clearErr('reg-teacher-school-err'); }
      // Advisory / grade levels — must have at least one
      if (advisoryClasses.length === 0) {
        err('reg-grade-err', 'Please select at least one grade level or advisory class.');
        valid = false;
      } else {
        clearErr('reg-grade-err');
      }
    }

    if (selectedRole === 'learner') {
      const grade = qs('#reg-learner-grade');
      if (grade && !grade.value) { markInvalid(grade); valid = false; }
      else if (grade) markValid(grade);
    }

    if (selectedRole === 'parent') {
      const grade = qs('#reg-child-grade');
      if (grade && !grade.value) { markInvalid(grade); valid = false; }
      else if (grade) markValid(grade);
    }

    if (selectedRole === 'school-head') {
      const empId    = qs('#reg-employee-id-sh');
      const position = qs('#reg-position-sh');
      const school   = qs('#reg-sh-school');
      if (empId    && !empId.value.trim())   { markInvalid(empId);    valid = false; } else if (empId)    markValid(empId);
      if (position && !position.value)       { markInvalid(position); valid = false; } else if (position) markValid(position);
      if (school   && !school.value.trim())  { markInvalid(school);   valid = false; } else if (school)   markValid(school);
    }

    // Generic staff role validation map
    const staffValidation = {
      psds:             [['#reg-employee-id-psds', true], ['#reg-psds-office', true], ['#reg-psds-cluster', true]],
      eps:              [['#reg-employee-id-eps', true],  ['#reg-eps-office', true]],
      'eps-sgod':       [['#reg-employee-id-eps-sgod', true], ['#reg-eps-sgod-office', true]],
      ces:              [['#reg-employee-id-ces', true],  ['#reg-ces-office', true]],
      'ces-sgod':       [['#reg-employee-id-ces-sgod', true], ['#reg-ces-sgod-office', true]],
      specialist:       [['#reg-employee-id-spec', true], ['#reg-spec-office', true]],
      'specialist-sgod':[['#reg-employee-id-spec-sgod', true], ['#reg-spec-sgod-office', true]],
      asds:             [['#reg-employee-id-asds', true], ['#reg-asds-office', true]],
      sds:              [['#reg-employee-id-sds', true],  ['#reg-sds-office', true]],
      pdo:              [['#reg-employee-id-pdo', true],  ['#reg-pdo-office', true]],
    };
    if (staffValidation[selectedRole]) {
      staffValidation[selectedRole].forEach(([sel, required]) => {
        const el = qs(sel);
        if (!el) return;
        if (required && !el.value.trim()) { markInvalid(el); valid = false; }
        else markValid(el);
      });
    }

    if (selectedRole === 'developer') {
      const affil = qs('#reg-affiliation');
      if (affil && !affil.value.trim()) { markInvalid(affil); valid = false; }
      else if (affil) markValid(affil);
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
  qs('#reg-next-2')?.addEventListener('click', () => { if (validateStep2()) { buildReviewPanel(); goTo(3); } });
  qs('#reg-back-3')?.addEventListener('click', () => goTo(2));

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
     COLLECT FORM DATA
  ════════════════════════════════════ */
  function buildFormData() {
    const fd = new FormData();
    fd.append('email',    qs('#reg-email').value.trim());
    fd.append('password', qs('#reg-pw').value);
    fd.append('fname',    qs('#reg-fname').value.trim());
    fd.append('lname',    qs('#reg-lname').value.trim());
    fd.append('region',   qs('#reg-region').value);
    fd.append('division', qs('#reg-division').value.trim());
    fd.append('role',     selectedRole);
    const contactNum = (qs('#reg-contact')?.value || '').trim();
    if (contactNum) fd.append('contact_number', contactNum);

    // Advisory / grade levels for teacher
    if (selectedRole === 'teacher' && advisoryClasses.length > 0) {
      // Send as JSON in grade_levels and also a flat comma list for compatibility
      fd.append('grade_levels', JSON.stringify(advisoryClasses.map(c => ({ grade: c.grade, label: c.gradeLabel, section: c.section }))));
      // Flat grade list for backward compat
      fd.append('grade_level', advisoryClasses.map(c => c.grade).join(','));
    }

    // Subjects (teacher / developer)
    const subjects = [...document.querySelectorAll('input[name="subjects[]"]:checked')].map(c => c.value);
    if (subjects.length) fd.append('subjects', subjects.join(','));

    // Dev types
    const devTypes = [...document.querySelectorAll('input[name="dev_types[]"]:checked')].map(c => c.value);
    if (devTypes.length) fd.append('dev_types', devTypes.join(','));

    const pick = (id, key) => {
      const el = qs('#' + id);
      if (el && el.value && el.value.trim()) fd.append(key, el.value.trim());
    };

    switch (selectedRole) {
      case 'teacher':
        pick('reg-employee-id',     'employee_id');
        pick('reg-teacher-school',  'school_name');
        pick('reg-teacher-cluster', 'cluster');
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
        pick('reg-position-sh',    'position');
        pick('reg-sh-school',      'school_name');
        pick('reg-sh-cluster',     'cluster');
        break;
      case 'psds':
        pick('reg-employee-id-psds', 'employee_id');
        pick('reg-psds-office',      'school_name');
        pick('reg-psds-cluster',     'cluster');
        break;
      case 'eps':
        pick('reg-employee-id-eps', 'employee_id');
        pick('reg-eps-office',      'school_name');
        pick('reg-eps-area',        'affiliation');
        fd.append('division_unit', 'cid');
        break;
      case 'eps-sgod':
        pick('reg-employee-id-eps-sgod', 'employee_id');
        pick('reg-eps-sgod-office',      'school_name');
        pick('reg-eps-sgod-area',        'affiliation');
        fd.append('division_unit', 'sgod');
        break;
      case 'ces':
        pick('reg-employee-id-ces', 'employee_id');
        pick('reg-ces-office',      'school_name');
        fd.append('division_unit', 'cid');
        break;
      case 'ces-sgod':
        pick('reg-employee-id-ces-sgod', 'employee_id');
        pick('reg-ces-sgod-office',      'school_name');
        fd.append('division_unit', 'sgod');
        break;
      case 'specialist':
        pick('reg-employee-id-spec', 'employee_id');
        pick('reg-spec-position',    'position');
        pick('reg-spec-office',      'school_name');
        fd.append('division_unit', 'cid');
        break;
      case 'specialist-sgod':
        pick('reg-employee-id-spec-sgod', 'employee_id');
        pick('reg-spec-sgod-position',    'position');
        pick('reg-spec-sgod-office',      'school_name');
        fd.append('division_unit', 'sgod');
        break;
      case 'asds':
        pick('reg-employee-id-asds', 'employee_id');
        pick('reg-asds-office',      'school_name');
        break;
      case 'sds':
        pick('reg-employee-id-sds', 'employee_id');
        pick('reg-sds-office',      'school_name');
        break;
      case 'pdo':
        pick('reg-employee-id-pdo', 'employee_id');
        pick('reg-pdo-office',      'school_name');
        pick('reg-pdo-program',     'affiliation');
        break;
      case 'developer':
        pick('reg-affiliation',     'affiliation');
        pick('reg-dev-position',    'dev_position');
        pick('reg-dev-specify',     'dev_position_specify');
        pick('reg-employee-id-dev', 'employee_id');
        break;
    }
    return fd;
  }

  /* ════════════════════════════════════
     BUILD REVIEW PANEL
  ════════════════════════════════════ */
  const GRADE_LABELS = {
    kinder:'Kindergarten',g1:'Grade 1',g2:'Grade 2',g3:'Grade 3',g4:'Grade 4',
    g5:'Grade 5',g6:'Grade 6',g7:'Grade 7',g8:'Grade 8',g9:'Grade 9',g10:'Grade 10',
    g11:'Grade 11 (SHS)',g12:'Grade 12 (SHS)',
  };
  const POSITION_LABELS = {
    'principal-1':'Principal I','principal-2':'Principal II','principal-3':'Principal III',
    'principal-4':'Principal IV','head-teacher-1':'Head Teacher I','head-teacher-2':'Head Teacher II',
    'head-teacher-3':'Head Teacher III','head-teacher-4':'Head Teacher IV',
    'head-teacher-5':'Head Teacher V','head-teacher-6':'Head Teacher VI',
    'school-head-other':'Other School Head Position',
  };
  const DIV_UNIT_LABELS = {
    cid:'CID – Curriculum Implementation Division',
    sgod:'SGOD – School Governance and Operations Division',
  };
  const DEV_POSITION_LABELS = {
    pdo:'Project Development Officer (PDO)',
    'teacher-dev':'Teacher / Content Author','eps-dev':'Education Program Supervisor',
    'curriculum-writer':'Curriculum Writer','illustrator':'Illustrator / Graphic Artist',
    'instructional-designer':'Instructional Designer','ict-coordinator':'ICT Coordinator',
    'partner-org':'Partner Organization Representative','other':'Other',
  };
  const SUBJECT_LABELS = {
    english:'English',filipino:'Filipino',math:'Mathematics',science:'Science',
    ap:'Araling Panlipunan',mapeh:'MAPEH',esp:'EsP',tle:'EPP / TLE / TVL',shs:'SHS Core / Applied',
  };
  const DEV_TYPE_LABELS = {
    slm:'SLMs',dll:'DLL / DLP','tg-lm':'TG / LM',assessment:'Assessments',
    video:'Video Lessons',interactive:'Interactive / SCORM',
  };

  function setRV(id, value) {
    const el = qs('#' + id);
    if (el) el.textContent = value || '—';
  }
  function showRVRow(rowId, value) {
    const row = qs('#' + rowId);
    if (!row) return;
    if (value) row.classList.add('show');
    else        row.classList.remove('show');
  }

  function buildReviewPanel() {
    setRV('rv-role', ROLE_LABELS[selectedRole] || selectedRole);

    const fname = (qs('#reg-fname')?.value || '').trim();
    const lname = (qs('#reg-lname')?.value || '').trim();
    setRV('rv-name', [fname, lname].filter(Boolean).join(' '));
    setRV('rv-region', qs('#reg-region')?.value || '');

    const division = (qs('#reg-division')?.value || '').trim();
    setRV('rv-division', division);
    showRVRow('rv-row-division', division);

    // Clear all optional rows
    ['rv-row-employee-id','rv-row-grade-level','rv-row-subjects','rv-row-school-name',
     'rv-row-cluster','rv-row-division-unit','rv-row-lrn','rv-row-child-grade',
     'rv-row-child-school','rv-row-position','rv-row-affiliation',
     'rv-row-dev-position','rv-row-dev-types','rv-row-contact'].forEach(id => {
      qs('#' + id)?.classList.remove('show');
    });

    if (selectedRole === 'teacher') {
      const empId   = (qs('#reg-employee-id')?.value || '').trim();
      const school  = (qs('#reg-teacher-school')?.value || '').trim();
      const cluster = (qs('#reg-teacher-cluster')?.value || '').trim();
      const subs    = [...document.querySelectorAll('input[name="subjects[]"]:checked')]
                     .map(c => SUBJECT_LABELS[c.value] || c.value).join(', ');
      // Advisory summary
      let advisorySummary = '';
      if (advisoryClasses.length > 0) {
        advisorySummary = advisoryClasses.map(c => c.gradeLabel + (c.section ? ' – ' + c.section : '')).join(', ');
      }
      if (empId)           { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (advisorySummary) { setRV('rv-grade-level', advisorySummary); showRVRow('rv-row-grade-level', advisorySummary); }
      if (school)          { setRV('rv-school-name', school); showRVRow('rv-row-school-name', school); }
      if (cluster)         { setRV('rv-cluster', 'CLUSTER-' + cluster); showRVRow('rv-row-cluster', cluster); }
      if (subs)            { setRV('rv-subjects', subs); showRVRow('rv-row-subjects', subs); }
    }
    if (selectedRole === 'learner') {
      const grade  = qs('#reg-learner-grade')?.value;
      const school = (qs('#reg-learner-school')?.value || '').trim();
      const lrn    = (qs('#reg-learner-lrn')?.value || '').trim();
      if (grade)  { setRV('rv-grade-level', GRADE_LABELS[grade] || grade); showRVRow('rv-row-grade-level', grade); }
      if (school) { setRV('rv-school-name', school); showRVRow('rv-row-school-name', school); }
      if (lrn)    { setRV('rv-lrn', lrn); showRVRow('rv-row-lrn', lrn); }
    }
    if (selectedRole === 'parent') {
      const grade  = qs('#reg-child-grade')?.value;
      const school = (qs('#reg-child-school')?.value || '').trim();
      if (grade)  { setRV('rv-child-grade', GRADE_LABELS[grade] || grade); showRVRow('rv-row-child-grade', grade); }
      if (school) { setRV('rv-child-school', school); showRVRow('rv-row-child-school', school); }
    }
    if (selectedRole === 'school-head') {
      const empId    = (qs('#reg-employee-id-sh')?.value || '').trim();
      const position = qs('#reg-position-sh')?.value;
      const school   = (qs('#reg-sh-school')?.value || '').trim();
      const cluster  = (qs('#reg-sh-cluster')?.value || '').trim();
      if (empId)    { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (position) { setRV('rv-position', POSITION_LABELS[position] || position); showRVRow('rv-row-position', position); }
      if (school)   { setRV('rv-school-name', school); showRVRow('rv-row-school-name', school); }
      if (cluster)  { setRV('rv-cluster', 'CLUSTER-' + cluster); showRVRow('rv-row-cluster', cluster); }
    }
    if (selectedRole === 'psds') {
      const empId   = (qs('#reg-employee-id-psds')?.value || '').trim();
      const office  = (qs('#reg-psds-office')?.value || '').trim();
      const cluster = (qs('#reg-psds-cluster')?.value || '').trim();
      if (empId)   { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (office)  { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
      if (cluster) { setRV('rv-cluster', 'CLUSTER-' + cluster); showRVRow('rv-row-cluster', cluster); }
    }
    if (selectedRole === 'eps') {
      const empId  = (qs('#reg-employee-id-eps')?.value || '').trim();
      const office = (qs('#reg-eps-office')?.value || '').trim();
      const area   = (qs('#reg-eps-area')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.cid); showRVRow('rv-row-division-unit', true);
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
      if (area)   { setRV('rv-affiliation', area); showRVRow('rv-row-affiliation', area); }
    }
    if (selectedRole === 'eps-sgod') {
      const empId  = (qs('#reg-employee-id-eps-sgod')?.value || '').trim();
      const office = (qs('#reg-eps-sgod-office')?.value || '').trim();
      const area   = (qs('#reg-eps-sgod-area')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.sgod); showRVRow('rv-row-division-unit', true);
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
      if (area)   { setRV('rv-affiliation', area); showRVRow('rv-row-affiliation', area); }
    }
    if (selectedRole === 'ces') {
      const empId  = (qs('#reg-employee-id-ces')?.value || '').trim();
      const office = (qs('#reg-ces-office')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.cid); showRVRow('rv-row-division-unit', true);
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'ces-sgod') {
      const empId  = (qs('#reg-employee-id-ces-sgod')?.value || '').trim();
      const office = (qs('#reg-ces-sgod-office')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.sgod); showRVRow('rv-row-division-unit', true);
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'specialist') {
      const empId    = (qs('#reg-employee-id-spec')?.value || '').trim();
      const position = (qs('#reg-spec-position')?.value || '').trim();
      const office   = (qs('#reg-spec-office')?.value || '').trim();
      if (empId)    { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.cid); showRVRow('rv-row-division-unit', true);
      if (position) { setRV('rv-position', position); showRVRow('rv-row-position', position); }
      if (office)   { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'specialist-sgod') {
      const empId    = (qs('#reg-employee-id-spec-sgod')?.value || '').trim();
      const position = (qs('#reg-spec-sgod-position')?.value || '').trim();
      const office   = (qs('#reg-spec-sgod-office')?.value || '').trim();
      if (empId)    { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      setRV('rv-division-unit', DIV_UNIT_LABELS.sgod); showRVRow('rv-row-division-unit', true);
      if (position) { setRV('rv-position', position); showRVRow('rv-row-position', position); }
      if (office)   { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'asds') {
      const empId  = (qs('#reg-employee-id-asds')?.value || '').trim();
      const office = (qs('#reg-asds-office')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'sds') {
      const empId  = (qs('#reg-employee-id-sds')?.value || '').trim();
      const office = (qs('#reg-sds-office')?.value || '').trim();
      if (empId)  { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (office) { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
    }
    if (selectedRole === 'pdo') {
      const empId   = (qs('#reg-employee-id-pdo')?.value || '').trim();
      const office  = (qs('#reg-pdo-office')?.value || '').trim();
      const program = (qs('#reg-pdo-program')?.value || '').trim();
      if (empId)   { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (office)  { setRV('rv-school-name', office); showRVRow('rv-row-school-name', office); }
      if (program) { setRV('rv-affiliation', program); showRVRow('rv-row-affiliation', program); }
    }
    if (selectedRole === 'developer') {
      const affil    = (qs('#reg-affiliation')?.value || '').trim();
      const devPos   = qs('#reg-dev-position')?.value;
      const devSpec  = (qs('#reg-dev-specify')?.value || '').trim();
      const empId    = (qs('#reg-employee-id-dev')?.value || '').trim();
      const devTypes = [...document.querySelectorAll('input[name="dev_types[]"]:checked')]
                       .map(c => DEV_TYPE_LABELS[c.value] || c.value).join(', ');
      if (affil)   { setRV('rv-affiliation', affil); showRVRow('rv-row-affiliation', affil); }
      const devPosDisplay = (DEV_POSITION_LABELS[devPos] || devPos || '') + (devSpec ? ' (' + devSpec + ')' : '');
      if (devPosDisplay.trim()) { setRV('rv-dev-position', devPosDisplay); showRVRow('rv-row-dev-position', devPosDisplay); }
      if (empId)   { setRV('rv-employee-id', empId); showRVRow('rv-row-employee-id', empId); }
      if (devTypes) { setRV('rv-dev-types', devTypes); showRVRow('rv-row-dev-types', devTypes); }
    }

    setRV('rv-email', (qs('#reg-email')?.value || '').trim());

    // Contact number (optional, shown for all roles if provided)
    const contactVal = (qs('#reg-contact')?.value || '').trim();
    if (contactVal) { setRV('rv-contact', contactVal); showRVRow('rv-row-contact', contactVal); }

    qsa('.rv-edit-btn').forEach(btn => {
      btn.onclick = () => goTo(parseInt(btn.dataset.goto, 10));
    });

    // Reset confirm checkbox
    const cb = qs('#rv-confirm');
    if (cb) cb.checked = false;
    qs('#rv-confirm-label')?.classList.remove('error-label');
    const rvErr = qs('#rv-confirm-err');
    if (rvErr) rvErr.textContent = '';
  }

  /* ════════════════════════════════════
     SUBMIT → register_handler.php
     NOTE: new granular roles (eps-sgod, ces-sgod, specialist-sgod)
     must map to a role value register_handler.php accepts.
     The handler uses these role values — update TOTP_ROLES and
     allowed_roles in register_handler.php to include these new values.
  ════════════════════════════════════ */
  const submitBtn = qs('#reg-submit');
  const sBtnLabel = submitBtn?.querySelector('.btn-label');
  const sBtnArrow = submitBtn?.querySelector('.btn-arrow');
  const sBtnSpin  = submitBtn?.querySelector('.btn-spin');

  submitBtn?.addEventListener('click', () => {
    const confirmCb    = qs('#rv-confirm');
    const confirmLabel = qs('#rv-confirm-label');
    const confirmErr   = qs('#rv-confirm-err');
    if (!confirmCb?.checked) {
      confirmLabel?.classList.add('error-label');
      if (confirmErr) confirmErr.textContent = 'Please confirm your details are correct before submitting.';
      return;
    }
    confirmLabel?.classList.remove('error-label');
    if (confirmErr) confirmErr.textContent = '';

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
          if (data.requires_totp) {
            // Hide all panels, show TOTP handoff
            panels.forEach(p => { p.hidden = true; });
            if (switchEl) switchEl.style.display = 'none';
            stepEls.forEach(s => { s.classList.remove('active'); s.classList.add('done'); });
            lines.forEach(l => l.classList.add('done'));
            const totpHandoff = qs('#reg-panel-totp-handoff');
            if (totpHandoff) {
              totpHandoff.hidden = false;
              totpHandoff.removeAttribute('hidden');
              totpHandoff.scrollIntoView({ behavior: 'smooth', block: 'center' });
              const bar = qs('#totp-handoff-bar');
              if (bar) requestAnimationFrame(() => { bar.style.width = '100%'; });
            }
            setTimeout(() => { window.location.href = data.redirect || 'totp_setup.php'; }, 3000);
          } else if (data.requires_verify) {
            window.location.href = data.redirect || 'registration_pending.php';
          } else if (data.pending) {
            const t = qs('#success-title'), m = qs('#success-msg');
            if (t) t.textContent = 'Registration Submitted!';
            if (m) m.textContent = 'Your account is pending administrator verification. You will receive an email once approved.';
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
              if (field === 'email') err('reg-submit-err', 'Email error: ' + msg + ' — click Edit on Account to change it.');
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
        err('reg-submit-err', 'Cannot reach the server. Please ensure XAMPP (Apache + MySQL) is running, then try again.');
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
  renderAdvisoryList();
  goTo(0);

})();