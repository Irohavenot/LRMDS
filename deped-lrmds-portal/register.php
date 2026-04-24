<?php /* DepEd LRMDS – register.php | HTML registration form. Submits to register_handler.php */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Create Account</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    /* ── Staff roles reveal ── */
    .rf-staff-toggle {
      display: block; margin-top: 12px; font-size: 0.8rem; color: #6B7280;
      text-align: center; cursor: pointer; background: none; border: none;
      width: 100%; padding: 4px 0; text-decoration: underline;
      text-underline-offset: 3px; font-family: inherit;
    }
    .rf-staff-toggle:hover { color: #2563EB; }
    .rf-staff-roles {
      display: none; margin-top: 12px; padding: 12px 14px;
      border: 1.5px dashed #CBD5E1; border-radius: 10px; background: #F8FAFC;
    }
    .rf-staff-roles.open { display: block; }
    .rf-staff-roles .staff-notice {
      font-size: 0.75rem; color: #64748B; margin: 0 0 10px; line-height: 1.5;
    }
    .rf-staff-roles .staff-notice strong { color: #374151; }
    .rf-staff-roles .rf-role-card {
      flex-direction: row; align-items: center; gap: 10px;
      padding: 10px 12px; border-radius: 8px;
    }
    .rf-staff-roles .rf-role-card .rrc-icon { flex-shrink: 0; width: 36px; height: 36px; }
    .rf-staff-roles .rf-role-card .rrc-name { font-size: 0.85rem; }
    .rf-staff-roles .rf-role-card .rrc-desc { font-size: 0.75rem; }

    /* ── Role-specific field sections ── */
    .role-fields { display: none; }
    .role-fields.visible { display: block; }

    /* Role badge shown on profile step header */
    .rp-role-badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: #EFF6FF; border: 1px solid #BFDBFE;
      color: #1D4ED8; border-radius: 999px;
      padding: 3px 10px; font-size: 12px; font-weight: 600;
      margin-top: 6px;
    }

    /* Checkbox group for multi-select (subjects, resource types) */
    .rf-check-group {
      display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 4px;
    }
    .rf-check-item {
      display: flex; align-items: center; gap: 8px;
      font-size: 13px; color: #374151; cursor: pointer;
      padding: 7px 10px; border: 1.5px solid #E5E7EB;
      border-radius: 8px; background: #fff; transition: border-color .15s, background .15s;
    }
    .rf-check-item:hover { border-color: #93C5FD; background: #F0F9FF; }
    .rf-check-item input[type="checkbox"] { accent-color: #0B4F9C; width: 15px; height: 15px; }
    .rf-check-item.checked { border-color: #0B4F9C; background: #EFF6FF; }

    .rf-section-title {
      font-size: 12px; font-weight: 700; color: #6B7280;
      text-transform: uppercase; letter-spacing: .05em;
      margin: 20px 0 8px;
    }

    /* -- Review panel -- */
    .rv-section {
      border: 1.5px solid #E5E7EB; border-radius: 12px;
      overflow: hidden; margin-bottom: 16px; background: #fff;
    }
    .rv-section-header {
      display: flex; align-items: center; justify-content: space-between;
      padding: 10px 16px; background: #F8FAFC;
      border-bottom: 1.5px solid #E5E7EB;
    }
    .rv-section-title {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; font-weight: 700; color: #374151;
      text-transform: uppercase; letter-spacing: .05em;
    }
    .rv-edit-btn {
      font-size: 12px; font-weight: 700; color: #0B4F9C;
      background: none; border: none; cursor: pointer;
      padding: 2px 6px; border-radius: 4px; font-family: inherit;
      text-decoration: underline; text-underline-offset: 2px;
      transition: color .15s;
    }
    .rv-edit-btn:hover { color: #0A4489; }
    .rv-row {
      display: flex; justify-content: space-between; align-items: baseline;
      gap: 12px; padding: 9px 16px;
      border-bottom: 1px solid #F3F4F6; font-size: 13.5px;
    }
    .rv-row:last-child { border-bottom: none; }
    .rv-label { color: #6B7280; font-weight: 500; flex-shrink: 0; }
    .rv-value { color: #111827; font-weight: 600; text-align: right; word-break: break-word; }
    .rv-optional { display: none; }
    .rv-optional.show { display: flex; }
    .rv-confirm-wrap {
      background: #FFFBEB; border: 1.5px solid #FDE68A;
      border-radius: 12px; padding: 14px 16px; margin-bottom: 4px;
    }
  </style>
</head>
<body class="reg-body">
<div class="reg-layout">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="reg-sidebar" aria-label="LRMDS branding">
    <div class="rs-top">
      <div class="rs-logos">
        <div class="rs-logo-wrap" title="DepEd Logo">
          <img src="assets/img/ww.png" alt="DepEd Logo" class="rs-deped-logo"
               onerror="this.parentElement.classList.add('logo-missing')"/>
        </div>
        <div class="rs-logo-divider" aria-hidden="true"></div>
        <div class="rs-lrmds-name">
          <span class="rs-lrmds-abbr">LRMDS</span>
          <span class="rs-lrmds-full">Learning Resource Management<br/>&amp; Development System</span>
        </div>
      </div>
      <div class="rs-dept">
        <p class="rs-dept-name">Republic of the Philippines</p>
        <p class="rs-dept-sub">Department of Education</p>
      </div>
    </div>
    <div class="rs-middle">
      <h2 class="rs-headline">Join the LRMDS Community</h2>
      <p class="rs-body-text">Create your account to browse, download, and contribute quality K–12 learning materials aligned to the Most Essential Learning Competencies.</p>
      <ul class="rs-benefits">
        <li><span class="rs-ben-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12.75l4 4L20 7"/></svg></span>Access 50,000+ curated resources</li>
        <li><span class="rs-ben-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12.75l4 4L20 7"/></svg></span>Download SLMs, TGs, DLLs, and more</li>
        <li><span class="rs-ben-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12.75l4 4L20 7"/></svg></span>Submit &amp; share your own materials</li>
        <li><span class="rs-ben-icon"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 12.75l4 4L20 7"/></svg></span>MELCs-aligned collections by quarter</li>
      </ul>
    </div>
    <div class="rs-shapes" aria-hidden="true">
      <div class="rss rss-1"></div>
      <div class="rss rss-2"></div>
    </div>
    <p class="rs-footer">© 2026 DepEd LRMDS · Prototype · For demonstration purposes only.</p>
  </aside>

  <!-- ═══════════ MAIN ═══════════ -->
  <main class="reg-main" id="main-content">
    <div class="rm-inner">

      <!-- Progress -->
      <div class="reg-progress" role="navigation" aria-label="Registration steps">
        <div class="rp-step active" data-step="0"><span class="rp-num">1</span><span class="rp-label">Role</span></div>
        <div class="rp-line"></div>
        <div class="rp-step" data-step="1"><span class="rp-num">2</span><span class="rp-label">Profile</span></div>
        <div class="rp-line"></div>
        <div class="rp-step" data-step="2"><span class="rp-num">3</span><span class="rp-label">Account</span></div>
        <div class="rp-line"></div>
        <div class="rp-step" data-step="3"><span class="rp-num">4</span><span class="rp-label">Review</span></div>
      </div>

      <!-- ══════════════════════════════
           STEP 0 · ROLE
      ══════════════════════════════ -->
      <div class="reg-panel active" id="reg-panel-0">
        <div class="rp-header">
          <h1>Who are you?</h1>
          <p>Choose the role that best describes you. This personalizes your resource feed.</p>
        </div>

        <div class="rf-role-grid" id="role-grid">
          <label class="rf-role-card" data-role="teacher">
            <input type="radio" name="role" value="teacher" hidden/>
            <span class="rrc-icon"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8M12 18v2"/><path d="M7 9h10M7 12h6"/></svg></span>
            <span class="rrc-name">Teacher</span>
            <span class="rrc-desc">Discover MELCs-aligned DLL/DLP, TG/LM, SLMs &amp; assessments. Save to your library.</span>
          </label>
          <label class="rf-role-card" data-role="learner">
            <input type="radio" name="role" value="learner" hidden/>
            <span class="rrc-icon"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg></span>
            <span class="rrc-name">Learner</span>
            <span class="rrc-desc">Access modules, videos, and practice tasks aligned to your grade level.</span>
          </label>
          <label class="rf-role-card" data-role="parent">
            <input type="radio" name="role" value="parent" hidden/>
            <span class="rrc-icon"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="9" cy="7" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M2 21v-1a7 7 0 0 1 14 0v1"/><path d="M17 14a5 5 0 0 1 5 5v1h-4.5"/></svg></span>
            <span class="rrc-name">Parent / Guardian</span>
            <span class="rrc-desc">Find learner-friendly materials and study guides by your child's grade level.</span>
          </label>
        </div>

        <span class="rf-error" id="reg-role-err" role="alert" style="margin-top:4px;display:block"></span>

        <button type="button" class="rf-staff-toggle" id="staff-toggle-btn" aria-expanded="false" aria-controls="staff-roles-panel">
          Are you DepEd staff or a content partner? ▾
        </button>
        <div class="rf-staff-roles" id="staff-roles-panel" aria-hidden="true">
          <p class="staff-notice"><strong>Note:</strong> Staff and content partner accounts require verification. Your account will be reviewed before full access is granted.</p>
          <div class="rf-role-grid">
            <label class="rf-role-card" data-role="school-head">
              <input type="radio" name="role" value="school-head" hidden/>
              <span class="rrc-icon"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
              <span class="rrc-name">School Head / Curriculum</span>
              <span class="rrc-desc">Access policies, QA templates, tracking, and analytics summaries.</span>
            </label>
            <label class="rf-role-card" data-role="developer">
              <input type="radio" name="role" value="developer" hidden/>
              <span class="rrc-icon"><svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg></span>
              <span class="rrc-name">Content Developer / Partner</span>
              <span class="rrc-desc">Access submission guidelines, QA rubrics, templates, and the upload workflow.</span>
            </label>
          </div>
        </div>

        <button type="button" class="rf-btn rf-btn-primary" id="reg-next-0" style="margin-top:20px">
          Continue
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
        </button>
      </div>

      <!-- ══════════════════════════════
           STEP 1 · PROFILE  (role-adaptive)
      ══════════════════════════════ -->
      <div class="reg-panel" id="reg-panel-1" hidden>
        <div class="rp-header">
          <h1>Your profile</h1>
          <p>Tell us a bit about yourself.</p>
          <div class="rp-role-badge" id="role-badge" style="display:none">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span id="role-badge-text"></span>
          </div>
        </div>

        <!-- ── Common to ALL roles ── -->
        <div class="rf-row">
          <div class="rf-group" id="rfg-fname">
            <label class="rf-label" for="reg-fname">First Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-fname" name="fname" placeholder="Juan" autocomplete="given-name" required/>
            <span class="rf-error" id="reg-fname-err" role="alert"></span>
          </div>
          <div class="rf-group" id="rfg-lname">
            <label class="rf-label" for="reg-lname">Last Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-lname" name="lname" placeholder="dela Cruz" autocomplete="family-name" required/>
            <span class="rf-error" id="reg-lname-err" role="alert"></span>
          </div>
        </div>

        <div class="rf-row">
          <div class="rf-group" id="rfg-region">
            <label class="rf-label" for="reg-region">Region <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-region" name="region" required>
              <option value="">Select region…</option>
              <option>NCR</option><option>CAR</option><option>Region I</option>
              <option>Region II</option><option>Region III</option><option>Region IV-A</option>
              <option>Region IV-B</option><option>Region V</option><option>Region VI</option>
              <option>Region VII</option><option>Region VIII</option><option>Region IX</option>
              <option>Region X</option><option>Region XI</option><option>Region XII</option>
              <option>CARAGA</option><option>BARMM</option>
            </select>
            <span class="rf-error" id="reg-region-err" role="alert"></span>
          </div>
          <div class="rf-group">
            <label class="rf-label" for="reg-division">Division / School</label>
            <input class="rf-input" type="text" id="reg-division" name="division" placeholder="e.g. Division of Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             TEACHER-specific fields
        ════════════════════════════ -->
        <div class="role-fields" id="fields-teacher">
          <p class="rf-section-title">Teaching Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id">Teacher / Employee ID</label>
            <input class="rf-input" type="text" id="reg-employee-id" name="employee_id" placeholder="e.g. 1234567"/>
            <span class="rf-hint">Optional. Leave blank if not yet assigned.</span>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-grade-level">Grade Level(s) You Teach <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-grade-level" name="grade_level">
              <option value="">Select grade level…</option>
              <option value="kinder">Kindergarten</option>
              <option value="g1">Grade 1</option><option value="g2">Grade 2</option>
              <option value="g3">Grade 3</option><option value="g4">Grade 4</option>
              <option value="g5">Grade 5</option><option value="g6">Grade 6</option>
              <option value="g7">Grade 7</option><option value="g8">Grade 8</option>
              <option value="g9">Grade 9</option><option value="g10">Grade 10</option>
              <option value="g11">Grade 11 (SHS)</option><option value="g12">Grade 12 (SHS)</option>
              <option value="multi">Multiple / Advisory</option>
            </select>
            <span class="rf-hint">We use this to personalize your resource feed.</span>
          </div>

          <div class="rf-group">
            <label class="rf-label">Learning Area(s) You Teach</label>
            <span class="rf-hint" style="margin-bottom:4px">Select all that apply.</span>
            <div class="rf-check-group" id="teacher-subjects">
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="english"/> English</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="filipino"/> Filipino</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="math"/> Mathematics</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="science"/> Science</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="ap"/> Araling Panlipunan</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="mapeh"/> MAPEH</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="esp"/> EsP</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="tle"/> EPP / TLE / TVL</label>
              <label class="rf-check-item"><input type="checkbox" name="subjects[]" value="shs"/> SHS Core / Applied</label>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════
             LEARNER-specific fields
        ════════════════════════════ -->
        <div class="role-fields" id="fields-learner">
          <p class="rf-section-title">Student Details</p>

          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="reg-learner-grade">Grade Level <span class="rf-req">*</span></label>
              <select class="rf-select" id="reg-learner-grade" name="learner_grade">
                <option value="">Select grade…</option>
                <option value="kinder">Kindergarten</option>
                <option value="g1">Grade 1</option><option value="g2">Grade 2</option>
                <option value="g3">Grade 3</option><option value="g4">Grade 4</option>
                <option value="g5">Grade 5</option><option value="g6">Grade 6</option>
                <option value="g7">Grade 7</option><option value="g8">Grade 8</option>
                <option value="g9">Grade 9</option><option value="g10">Grade 10</option>
                <option value="g11">Grade 11 (SHS)</option><option value="g12">Grade 12 (SHS)</option>
              </select>
              <span class="rf-hint">Shows resources at your level.</span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="reg-learner-school">School Name</label>
              <input class="rf-input" type="text" id="reg-learner-school" name="learner_school" placeholder="e.g. Calinog NHS"/>
              <span class="rf-hint">Optional.</span>
            </div>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-learner-lrn">Learner Reference Number (LRN)</label>
            <input class="rf-input" type="text" id="reg-learner-lrn" name="lrn"
                   placeholder="12-digit LRN (optional)" maxlength="12" pattern="\d{12}"/>
            <span class="rf-hint">Optional. Found on your report card or Form 138.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             PARENT-specific fields
        ════════════════════════════ -->
        <div class="role-fields" id="fields-parent">
          <p class="rf-section-title">About Your Child / Ward</p>

          <div class="rf-row">
            <div class="rf-group">
              <label class="rf-label" for="reg-child-grade">Child's Grade Level <span class="rf-req">*</span></label>
              <select class="rf-select" id="reg-child-grade" name="child_grade">
                <option value="">Select grade…</option>
                <option value="kinder">Kindergarten</option>
                <option value="g1">Grade 1</option><option value="g2">Grade 2</option>
                <option value="g3">Grade 3</option><option value="g4">Grade 4</option>
                <option value="g5">Grade 5</option><option value="g6">Grade 6</option>
                <option value="g7">Grade 7</option><option value="g8">Grade 8</option>
                <option value="g9">Grade 9</option><option value="g10">Grade 10</option>
                <option value="g11">Grade 11 (SHS)</option><option value="g12">Grade 12 (SHS)</option>
                <option value="multi">Multiple children</option>
              </select>
              <span class="rf-hint">We use this to show relevant materials.</span>
            </div>
            <div class="rf-group">
              <label class="rf-label" for="reg-child-school">Child's School</label>
              <input class="rf-input" type="text" id="reg-child-school" name="child_school" placeholder="e.g. Calinog Central ES"/>
              <span class="rf-hint">Optional.</span>
            </div>
          </div>
        </div>

        <!-- ════════════════════════════
             SCHOOL HEAD / CURRICULUM
        ════════════════════════════ -->
        <div class="role-fields" id="fields-school-head">
          <p class="rf-section-title">Position &amp; Assignment</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-sh">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-sh" name="employee_id" placeholder="e.g. 1234567"/>
            <span class="rf-hint">Required for staff account verification.</span>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-position">Position / Designation <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-position" name="position">
              <option value="">Select position…</option>
              <option value="principal-1">Principal I</option>
              <option value="principal-2">Principal II</option>
              <option value="principal-3">Principal III</option>
              <option value="principal-4">Principal IV</option>
              <option value="head-teacher-1">Head Teacher I</option>
              <option value="head-teacher-2">Head Teacher II</option>
              <option value="head-teacher-3">Head Teacher III</option>
              <option value="head-teacher-4">Head Teacher IV</option>
              <option value="head-teacher-5">Head Teacher V</option>
              <option value="head-teacher-6">Head Teacher VI</option>
              <option value="eps">Education Program Supervisor (EPS)</option>
              <option value="chief-eps">Chief EPS / Curriculum</option>
              <option value="asds">ASDS / SDS</option>
              <option value="other-admin">Other Administrative</option>
            </select>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-sh-school">School / Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-sh-school" name="school_name" placeholder="e.g. Calinog NHS / SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             CONTENT DEVELOPER / PARTNER
        ════════════════════════════ -->
        <div class="role-fields" id="fields-developer">
          <p class="rf-section-title">Contributor Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-affiliation">Organization / Affiliation <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-affiliation" name="affiliation"
                   placeholder="e.g. SDO Iloilo, CHED, NGO Name"/>
            <span class="rf-hint">DepEd office, school, university, or partner organization.</span>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-dev-position">Role / Designation</label>
            <select class="rf-select" id="reg-dev-position" name="dev_position">
              <option value="">Select…</option>
              <option value="teacher-dev">Teacher / Content Author</option>
              <option value="eps-dev">Education Program Supervisor</option>
              <option value="curriculum-writer">Curriculum Writer</option>
              <option value="illustrator">Illustrator / Graphic Artist</option>
              <option value="instructional-designer">Instructional Designer</option>
              <option value="ict-coordinator">ICT Coordinator</option>
              <option value="partner-org">Partner Organization Representative</option>
              <option value="other">Other</option>
            </select>
          </div>

          <div class="rf-group">
            <label class="rf-label">Resource Types You Plan to Submit</label>
            <span class="rf-hint" style="margin-bottom:4px">Select all that apply.</span>
            <div class="rf-check-group">
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="slm"/> SLMs</label>
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="dll"/> DLL / DLP</label>
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="tg-lm"/> TG / LM</label>
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="assessment"/> Assessments</label>
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="video"/> Video Lessons</label>
              <label class="rf-check-item"><input type="checkbox" name="dev_types[]" value="interactive"/> Interactive / SCORM</label>
            </div>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-dev">Employee ID</label>
            <input class="rf-input" type="text" id="reg-employee-id-dev" name="employee_id" placeholder="If DepEd employee (optional)"/>
          </div>
        </div>

        <div class="rf-nav">
          <button type="button" class="rf-btn rf-btn-ghost" id="reg-back-1">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
          </button>
          <button type="button" class="rf-btn rf-btn-primary" id="reg-next-1">
            Continue
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>

      <!-- ══════════════════════════════
           STEP 2 · ACCOUNT CREDENTIALS
      ══════════════════════════════ -->
      <div class="reg-panel" id="reg-panel-2" hidden>
        <div class="rp-header">
          <h1>Create your account</h1>
          <p>Set up your login credentials. Use your DepEd email if you have one.</p>
        </div>

        <div class="rf-group" id="rfg-email">
          <label class="rf-label" for="reg-email">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
            Email Address <span class="rf-req">*</span>
          </label>
          <input class="rf-input" type="email" id="reg-email" name="email"
                 placeholder="yourname@deped.gov.ph"
                 autocomplete="email" aria-describedby="reg-email-err" required/>
          <span class="rf-hint">Use your official DepEd email if available.</span>
          <span class="rf-error" id="reg-email-err" role="alert"></span>
        </div>

        <div class="rf-group" id="rfg-pw">
          <label class="rf-label" for="reg-pw">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Password <span class="rf-req">*</span>
          </label>
          <div class="rf-pw-wrap">
            <input class="rf-input" type="password" id="reg-pw" name="password"
                   placeholder="Minimum 8 characters"
                   autocomplete="new-password" aria-describedby="reg-pw-err" required/>
            <button type="button" class="rf-pw-toggle" aria-label="Toggle password" data-target="reg-pw">
              <svg class="icon-eye" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <div class="pw-strength" id="pw-strength" aria-live="polite">
            <div class="pws-bar"><div class="pws-fill" id="pws-fill"></div></div>
            <span class="pws-label" id="pws-label"></span>
          </div>
          <span class="rf-error" id="reg-pw-err" role="alert"></span>
        </div>

        <div class="rf-group" id="rfg-pw2">
          <label class="rf-label" for="reg-pw2">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Confirm Password <span class="rf-req">*</span>
          </label>
          <div class="rf-pw-wrap">
            <input class="rf-input" type="password" id="reg-pw2" name="password2"
                   placeholder="Re-enter your password"
                   autocomplete="new-password" aria-describedby="reg-pw2-err" required/>
            <button type="button" class="rf-pw-toggle" aria-label="Toggle confirm password" data-target="reg-pw2">
              <svg class="icon-eye" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <span class="rf-error" id="reg-pw2-err" role="alert"></span>
        </div>

        <div class="rf-terms">
          <label class="rf-check-label" id="terms-label">
            <input type="checkbox" id="reg-terms" name="terms"/>
            <span class="rf-checkmark"></span>
            I have read and agree to the <a href="#" class="af-link">Terms of Use</a> and <a href="#" class="af-link">Privacy Policy</a>.
          </label>
          <span class="rf-error" id="reg-terms-err" role="alert"></span>
        </div>

        <div class="rf-nav">
          <button type="button" class="rf-btn rf-btn-ghost" id="reg-back-2">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
          </button>
          <button type="button" class="rf-btn rf-btn-primary" id="reg-next-2">
            Review Details
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>

      <!-- ══════════════════════════════
           STEP 3 - REVIEW AND CONFIRM
      ══════════════════════════════ -->
      <div class="reg-panel" id="reg-panel-3" hidden>
        <div class="rp-header">
          <h1>Review your details</h1>
          <p>Please check everything carefully before submitting. Use the edit buttons to go back and make changes.</p>
        </div>

        <div class="rv-section">
          <div class="rv-section-header">
            <span class="rv-section-title">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              Role
            </span>
            <button type="button" class="rv-edit-btn" data-goto="0">Edit</button>
          </div>
          <div class="rv-row"><span class="rv-label">Account Type</span><span class="rv-value" id="rv-role">-</span></div>
        </div>

        <div class="rv-section">
          <div class="rv-section-header">
            <span class="rv-section-title">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M8 2v4M16 2v4M3 10h18"/></svg>
              Profile
            </span>
            <button type="button" class="rv-edit-btn" data-goto="1">Edit</button>
          </div>
          <div class="rv-row"><span class="rv-label">Full Name</span><span class="rv-value" id="rv-name">-</span></div>
          <div class="rv-row"><span class="rv-label">Region</span><span class="rv-value" id="rv-region">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-division"><span class="rv-label">Division</span><span class="rv-value" id="rv-division">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-employee-id"><span class="rv-label">Employee ID</span><span class="rv-value" id="rv-employee-id">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-grade-level"><span class="rv-label">Grade Level</span><span class="rv-value" id="rv-grade-level">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-subjects"><span class="rv-label">Subjects</span><span class="rv-value" id="rv-subjects">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-school-name"><span class="rv-label">School / Office</span><span class="rv-value" id="rv-school-name">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-lrn"><span class="rv-label">LRN</span><span class="rv-value" id="rv-lrn">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-child-grade"><span class="rv-label">Child's Grade</span><span class="rv-value" id="rv-child-grade">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-child-school"><span class="rv-label">Child's School</span><span class="rv-value" id="rv-child-school">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-position"><span class="rv-label">Position</span><span class="rv-value" id="rv-position">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-affiliation"><span class="rv-label">Affiliation</span><span class="rv-value" id="rv-affiliation">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-dev-position"><span class="rv-label">Designation</span><span class="rv-value" id="rv-dev-position">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-dev-types"><span class="rv-label">Resource Types</span><span class="rv-value" id="rv-dev-types">-</span></div>
        </div>

        <div class="rv-section">
          <div class="rv-section-header">
            <span class="rv-section-title">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              Account
            </span>
            <button type="button" class="rv-edit-btn" data-goto="2">Edit</button>
          </div>
          <div class="rv-row"><span class="rv-label">Email Address</span><span class="rv-value" id="rv-email">-</span></div>
          <div class="rv-row"><span class="rv-label">Password</span><span class="rv-value">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</span></div>
        </div>

        <div class="rv-confirm-wrap">
          <label class="rf-check-label" id="rv-confirm-label">
            <input type="checkbox" id="rv-confirm"/>
            <span class="rf-checkmark"></span>
            I confirm that all the details above are accurate and belong to me.
          </label>
          <span class="rf-error" id="rv-confirm-err" role="alert"></span>
        </div>

        <div class="rf-nav">
          <button type="button" class="rf-btn rf-btn-ghost" id="reg-back-3">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
            Back
          </button>
          <button type="button" class="rf-btn rf-btn-primary" id="reg-submit">
            <span class="btn-label">Create Account</span>
            <svg class="btn-arrow" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            <svg class="btn-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
          </button>
        </div>
        <span class="rf-error" id="reg-submit-err" role="alert" style="display:block;margin-top:8px;text-align:center"></span>
      </div>

      <!-- SUCCESS — two states, only one shown at a time via JS -->

      <!--
        State A: TOTP roles (teacher / school-head / developer)
        No sign-in button — they CANNOT sign in yet (account is pending admin approval).
        JS hides all panels and shows this, then redirects to totp_setup.php after 3 s.
      -->
      <div class="reg-panel" id="reg-panel-totp-handoff" hidden>
        <div class="reg-success">
          <div class="rs-icon-wrap" style="background:#ECFDF5;">
            <svg width="44" height="44" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
          </div>
          <h2 style="font-size:22px;font-weight:800;color:#111827;margin:0 0 10px;">
            Details saved — one more step!
          </h2>
          <p style="font-size:14px;color:#6B7280;line-height:1.65;margin:0 0 6px;">
            You are being taken to set up a <strong>security code on your phone</strong>.<br>
            This is the <strong>last step</strong> before your account is submitted for approval.
          </p>
          <p style="font-size:13px;color:#9CA3AF;margin:0 0 24px;">
            Do not close this page — you will be redirected automatically.
          </p>
          <!-- Progress bar -->
          <div style="background:#E5E7EB;border-radius:999px;height:6px;overflow:hidden;max-width:240px;margin:0 auto 10px;">
            <div id="totp-handoff-bar"
                 style="height:100%;width:0;background:#059669;border-radius:999px;transition:width 3s linear;"></div>
          </div>
          <p style="font-size:12px;color:#9CA3AF;margin:0;">Redirecting in 3 seconds…</p>
        </div>
      </div>

      <!--
        State B: learner / parent — generic success, sign-in link is fine
        (they go to registration_pending.php first anyway, this is a fallback)
      -->
      <div class="reg-panel" id="reg-panel-success" hidden>
        <div class="reg-success">
          <div class="rs-icon-wrap">
            <svg width="44" height="44" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
            </svg>
          </div>
          <h2 id="success-title" style="font-size:22px;font-weight:800;color:#111827;margin:0 0 10px;">
            Account Created!
          </h2>
          <p id="success-msg" style="font-size:14px;color:#6B7280;margin:0 0 20px;line-height:1.65;">
            Welcome to LRMDS. You can now sign in and start accessing learning resources.
          </p>
          <a href="signin.php" id="success-signin-link"
             class="rf-btn rf-btn-primary"
             style="display:inline-flex;text-decoration:none;">
            Go to Sign In
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </a>
        </div>
      </div>

      <p class="rm-switch">Already have an account? <a class="af-link" href="signin.php">Sign in</a></p>
    </div>
  </main>
</div>

<script src="assets/js/register.js"></script>
</body>
</html>