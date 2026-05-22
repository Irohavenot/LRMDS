<?php /* DepEd LRMDS – register.php | HTML registration form. Submits to register_handler.php */ ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Create Account</title>
  <link rel="stylesheet" href="../assets/css/styles.css"/>
  <link rel="stylesheet" href="../assets/css/register.css"/>
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

    /* ── Auto-capitalize name inputs ── */
    #reg-fname, #reg-lname {
      text-transform: capitalize;
    }

    /* ── Password requirement checklist ── */
    .pw-reqs {
      margin: 8px 0 4px; padding: 0; list-style: none;
      display: grid; grid-template-columns: 1fr 1fr; gap: 4px 10px;
    }
    .pw-reqs li {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: #9CA3AF; transition: color .2s;
    }
    .pw-reqs li .req-icon { width: 14px; height: 14px; flex-shrink: 0; }
    .pw-reqs li.met { color: #059669; }
    .pw-reqs li.unmet { color: #EF4444; }
  </style>
</head>
<body class="reg-body">
<div class="reg-layout">

  <!-- ═══════════ SIDEBAR ═══════════ -->
  <aside class="reg-sidebar" aria-label="LRMDS branding">
    <div class="rs-top">
      <div class="rs-logos">
        <div class="rs-logo-wrap" title="DepEd Logo">
          <a href="../index.php">
          <img src="../assets/img/ww.png" alt="DepEd Logo" class="rs-deped-logo"
               onerror="this.parentElement.classList.add('logo-missing')"/>
               </a>
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
          <p class="staff-notice"><strong>Note:</strong> Staff and content partner accounts require admin verification. Your account will be reviewed before full access is granted.</p>

          <!-- School-Level -->
          <p style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin:8px 0 6px;">School Level</p>
          <div class="rf-role-grid" style="grid-template-columns:1fr 1fr;">
            <label class="rf-role-card" data-role="school-head" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="school-head" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">School Head</span>
                <span class="rrc-desc">Principal or Head Teacher leading a school.</span>
              </span>
            </label>
          </div>

          <!-- District Level -->
          <p style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin:14px 0 6px;">District Level</p>
          <div class="rf-role-grid" style="grid-template-columns:1fr;">
            <label class="rf-role-card" data-role="psds" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="psds" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.22 4.22l2.12 2.12M17.66 17.66l2.12 2.12M2 12h3M19 12h3M4.22 19.78l2.12-2.12M17.66 6.34l2.12-2.12"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">Public Schools District Supervisor (PSDS)</span>
                <span class="rrc-desc">Oversees schools and teachers within a district cluster.</span>
              </span>
            </label>
          </div>

          <!-- Division Level -->
          <p style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin:14px 0 6px;">Division Level (SDO)</p>
          <div class="rf-role-grid" style="grid-template-columns:1fr 1fr;">

            <label class="rf-role-card" data-role="eps" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="eps" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/><path d="M7 8h10M7 11h7"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">Education Program Supervisor (EPS)</span>
                <span class="rrc-desc">CID or SGOD program supervision.</span>
              </span>
            </label>

            <label class="rf-role-card" data-role="ces" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="ces" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">Chief Education Supervisor (CES)</span>
                <span class="rrc-desc">Division chief of CID or SGOD.</span>
              </span>
            </label>

            <label class="rf-role-card" data-role="specialist" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="specialist" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">CID / SGOD Specialist</span>
                <span class="rrc-desc">Specialist role within the division office.</span>
              </span>
            </label>

            <label class="rf-role-card" data-role="asds" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="asds" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/><circle cx="12" cy="12" r="3"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">ASDS</span>
                <span class="rrc-desc">Assistant Schools Division Superintendent.</span>
              </span>
            </label>

            <label class="rf-role-card" data-role="sds" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;grid-column:1/-1;">
              <input type="radio" name="role" value="sds" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.58-7 8-7s8 3 8 7"/><path d="M18 14l2 2 4-4"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">Schools Division Superintendent (SDS)</span>
                <span class="rrc-desc">Head of the Schools Division Office.</span>
              </span>
            </label>
          </div>

          <!-- Content & Partners -->
          <p style="font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin:14px 0 6px;">Content &amp; Partners</p>
          <div class="rf-role-grid" style="grid-template-columns:1fr;">
            <label class="rf-role-card" data-role="developer" style="flex-direction:row;align-items:center;gap:10px;padding:10px 12px;">
              <input type="radio" name="role" value="developer" hidden/>
              <span class="rrc-icon" style="flex-shrink:0;">
                <svg width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
              </span>
              <span style="display:flex;flex-direction:column;gap:2px;">
                <span class="rrc-name">Content Developer / Partner</span>
                <span class="rrc-desc">Submit and manage learning materials. Includes PDOs, curriculum writers, illustrators, and partner organizations.</span>
              </span>
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
            <input class="rf-input" type="text" id="reg-lname" name="lname" placeholder="Dela Cruz" autocomplete="family-name" required/>
            <span class="rf-error" id="reg-lname-err" role="alert"></span>
          </div>
        </div>

        <div class="rf-row">
          <div class="rf-group" id="rfg-region">
            <label class="rf-label" for="reg-region">Region <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-region" name="region" required>
              <option value="">Select region…</option>
              <option value="NCR">NCR – National Capital Region</option>
              <option value="CAR">CAR – Cordillera Administrative Region</option>
              <option value="Region I">Region I – Ilocos Region</option>
              <option value="Region II">Region II – Cagayan Valley</option>
              <option value="Region III">Region III – Central Luzon</option>
              <option value="Region IV-A">Region IV-A – CALABARZON</option>
              <option value="Region IV-B">Region IV-B – MIMAROPA</option>
              <option value="Region V">Region V – Bicol Region</option>
              <option value="Region VI">Region VI – Western Visayas</option>
              <option value="Region VII" selected>Region VII – Central Visayas</option>
              <option value="Region VIII">Region VIII – Eastern Visayas</option>
              <option value="Region IX">Region IX – Zamboanga Peninsula</option>
              <option value="Region X">Region X – Northern Mindanao</option>
              <option value="Region XI">Region XI – Davao Region</option>
              <option value="Region XII">Region XII – SOCCSKSARGEN</option>
              <option value="CARAGA">CARAGA</option>
              <option value="BARMM">BARMM – Bangsamoro</option>
            </select>
            <span class="rf-error" id="reg-region-err" role="alert"></span>
          </div>
          <div class="rf-group">
            <label class="rf-label" for="reg-division">Division <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-division" name="division"
                   list="reg-division-list" autocomplete="off"
                   placeholder="Type or select your division…" required/>
            <datalist id="reg-division-list"></datalist>
            <span class="rf-hint" id="reg-division-hint">Select your region first, or type your division name.</span>
            <span class="rf-error" id="reg-division-err" role="alert"></span>
          </div>
        </div>

        <script>
        /* ── Cascading Region → Division ───────────────────────────────── */
        (function () {
          var DIVISIONS = {
            'NCR': [
              'Manila','Caloocan','Las Piñas','Makati','Malabon','Mandaluyong',
              'Marikina','Muntinlupa','Navotas','Parañaque','Pasay','Pasig',
              'Pateros','Quezon City','San Juan','Taguig','Valenzuela'
            ],
            'CAR': [
              'Abra','Apayao','Benguet','Baguio City','Ifugao','Kalinga','Mountain Province'
            ],
            'Region I': [
              'Ilocos Norte','Ilocos Sur','La Union','Pangasinan I','Pangasinan II'
            ],
            'Region II': [
              'Batanes','Cagayan','Isabela','Nueva Vizcaya','Quirino'
            ],
            'Region III': [
              'Aurora','Bataan','Bulacan','Nueva Ecija','Pampanga','San Jose del Monte City',
              'Tarlac','Zambales'
            ],
            'Region IV-A': [
              'Batangas I','Batangas II','Cavite','Cavite City','Laguna I','Laguna II',
              'Lucena City','Quezon','Rizal','Antipolo City'
            ],
            'Region IV-B': [
              'Marinduque','Occidental Mindoro','Oriental Mindoro','Palawan','Puerto Princesa City','Romblon'
            ],
            'Region V': [
              'Albay','Camarines Norte','Camarines Sur','Catanduanes','Masbate','Naga City','Sorsogon'
            ],
            'Region VI': [
              'Aklan','Antique','Capiz','Guimaras','Iloilo','Iloilo City','Negros Occidental',
              'Bacolod City'
            ],
            'Region VII': [
              'Bohol','Cebu Province','Cebu City','Lapu-Lapu City','Mandaue City',
              'Division of Carcar','Siquijor'
            ],
            'Region VIII': [
              'Biliran','Eastern Samar','Leyte','Northern Samar','Ormoc City','Samar','Southern Leyte',
              'Tacloban City'
            ],
            'Region IX': [
              'Isabela City','Zamboanga City','Zamboanga del Norte','Zamboanga del Sur',
              'Zamboanga Sibugay'
            ],
            'Region X': [
              'Bukidnon','Camiguin','Cagayan de Oro City','Gingoog City','Iligan City',
              'Lanao del Norte','Misamis Occidental','Misamis Oriental'
            ],
            'Region XI': [
              'Compostela Valley','Davao City','Davao del Norte','Davao del Sur',
              'Davao Occidental','Davao Oriental'
            ],
            'Region XII': [
              'Cotabato City','Cotabato','North Cotabato','Sarangani','South Cotabato','Sultan Kudarat'
            ],
            'CARAGA': [
              'Agusan del Norte','Agusan del Sur','Butuan City','Dinagat Islands',
              'Surigao City','Surigao del Norte','Surigao del Sur'
            ],
            'BARMM': [
              'Basilan','Lamitan City','Lanao del Sur','Marawi City',
              'Maguindanao','Sulu','Tawi-Tawi'
            ]
          };

          var DEFAULT_REGION   = 'Region VII';
          var DEFAULT_DIVISION = 'Division of Carcar';

          var regionSel   = document.getElementById('reg-region');
          var divisionInput = document.getElementById('reg-division');
          var divisionList  = document.getElementById('reg-division-list');
          var divHint     = document.getElementById('reg-division-hint');

          function populateDivisions(region, preselectValue) {
            divisionList.innerHTML = '';
            var list = DIVISIONS[region];
            if (!list) {
              divHint.textContent = 'Select your region first, or type your division name.';
              return;
            }
            list.forEach(function (d) {
              var opt = document.createElement('option');
              opt.value = d;
              divisionList.appendChild(opt);
            });
            if (preselectValue) divisionInput.value = preselectValue;
            divHint.textContent = list.length + ' division' + (list.length !== 1 ? 's' : '') + ' available — or type to search.';
          }

          /* Initial load — pre-select Region VII + Division of Carcar */
          populateDivisions(DEFAULT_REGION, DEFAULT_DIVISION);

          regionSel.addEventListener('change', function () {
            divisionInput.value = '';
            populateDivisions(this.value, '');
          });
        })();
        </script>

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
            <label class="rf-label">Advisory / Grade Level(s) <span class="rf-req">*</span></label>
            <span class="rf-hint" style="margin-bottom:8px;">Click a grade to add it. You can type the section name for each advisory class you handle.</span>

            <!-- Grade toggle buttons -->
            <div id="advisory-grade-btns" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;">
              <button type="button" class="advisory-grade-btn" data-grade="kinder" data-label="Kindergarten">Kinder</button>
              <button type="button" class="advisory-grade-btn" data-grade="g1"     data-label="Grade 1">G1</button>
              <button type="button" class="advisory-grade-btn" data-grade="g2"     data-label="Grade 2">G2</button>
              <button type="button" class="advisory-grade-btn" data-grade="g3"     data-label="Grade 3">G3</button>
              <button type="button" class="advisory-grade-btn" data-grade="g4"     data-label="Grade 4">G4</button>
              <button type="button" class="advisory-grade-btn" data-grade="g5"     data-label="Grade 5">G5</button>
              <button type="button" class="advisory-grade-btn" data-grade="g6"     data-label="Grade 6">G6</button>
              <button type="button" class="advisory-grade-btn" data-grade="g7"     data-label="Grade 7">G7</button>
              <button type="button" class="advisory-grade-btn" data-grade="g8"     data-label="Grade 8">G8</button>
              <button type="button" class="advisory-grade-btn" data-grade="g9"     data-label="Grade 9">G9</button>
              <button type="button" class="advisory-grade-btn" data-grade="g10"    data-label="Grade 10">G10</button>
              <button type="button" class="advisory-grade-btn" data-grade="g11"    data-label="Grade 11 (SHS)">G11</button>
              <button type="button" class="advisory-grade-btn" data-grade="g12"    data-label="Grade 12 (SHS)">G12</button>
            </div>

            <!-- Selected advisory list -->
            <div id="advisory-list" style="display:flex;flex-direction:column;gap:6px;min-height:36px;">
              <span id="advisory-empty" style="font-size:12px;color:#9CA3AF;padding:6px 0;">No grades selected yet — tap a grade above to add it.</span>
            </div>

            <div style="font-size:12px;color:#6B7280;margin-top:6px;display:flex;align-items:center;gap:6px;">
              Advisory classes added: <strong id="advisory-count">0</strong>
            </div>
            <input type="hidden" id="reg-advisory-data" name="grade_levels"/>
            <span class="rf-error" id="reg-grade-err" role="alert"></span>
          </div>

          <style>
            .advisory-grade-btn {
              padding: 6px 12px; border: 1.5px solid #D1D5DB; border-radius: 20px;
              background: #fff; font-size: 12px; font-weight: 700; color: #374151;
              cursor: pointer; font-family: inherit; transition: border-color .15s, background .15s, color .15s;
            }
            .advisory-grade-btn:hover { border-color: #93C5FD; background: #F0F9FF; color: #0B4F9C; }
            .advisory-grade-btn.active { border-color: #0B4F9C; background: #0B4F9C; color: #fff; }
            .advisory-row {
              display: grid; grid-template-columns: 110px 1fr 32px; gap: 6px; align-items: center;
            }
            .advisory-row-grade {
              font-size: 12px; font-weight: 700; color: #374151;
              background: #EFF6FF; border-radius: 6px; padding: 5px 8px; text-align: center;
            }
            .advisory-row-section {
              padding: 6px 10px; border: 1.5px solid #E5E7EB; border-radius: 7px;
              font-size: 13px; font-family: inherit; color: #111827; outline: none;
              transition: border-color .15s;
            }
            .advisory-row-section:focus { border-color: #0B4F9C; }
            .advisory-row-remove {
              width: 28px; height: 28px; border: none; background: #FEE2E2; border-radius: 6px;
              color: #DC2626; font-size: 14px; cursor: pointer; display: flex;
              align-items: center; justify-content: center; flex-shrink: 0;
              transition: background .15s;
            }
            .advisory-row-remove:hover { background: #FECACA; }
          </style>

          <div class="rf-group">
            <label class="rf-label" for="reg-teacher-school">School Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-teacher-school" name="school_name" placeholder="e.g. Calinog National High School"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-teacher-cluster">Cluster / District</label>
            <div style="display:flex;align-items:center;gap:0;">
              <span style="padding:10px 12px;background:#F3F4F6;border:1.5px solid #D1D5DB;border-right:none;border-radius:9px 0 0 9px;font-size:14px;color:#6B7280;white-space:nowrap;font-weight:600;">CLUSTER-</span>
              <input class="rf-input" type="text" id="reg-teacher-cluster" name="cluster"
                     placeholder="e.g. 1 or 4B"
                     style="border-radius:0 9px 9px 0;border-left:none;"/>
            </div>
            <span class="rf-hint">Optional. Enter the cluster number or code assigned to your school.</span>
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
            <label class="rf-label" for="reg-learner-lrn">
              Learner Reference Number (LRN)
              <button type="button" id="lrn-help-toggle" aria-expanded="false" aria-controls="lrn-help-box"
                style="display:inline-flex;align-items:center;justify-content:center;
                       width:17px;height:17px;border-radius:50%;border:1.5px solid #93C5FD;
                       background:#EFF6FF;color:#1D4ED8;font-size:11px;font-weight:700;
                       cursor:pointer;margin-left:5px;vertical-align:middle;line-height:1;
                       font-family:inherit;padding:0;"
                title="What is an LRN?">?</button>
            </label>
            <input class="rf-input" type="text" id="reg-learner-lrn" name="lrn"
                   placeholder="e.g. 123456789012 (optional)" maxlength="12" pattern="\d{12}"
                   inputmode="numeric"/>
            <div id="lrn-help-box" hidden
                 style="margin-top:6px;background:#EFF6FF;border:1px solid #BFDBFE;
                        border-radius:8px;padding:10px 12px;font-size:12.5px;
                        color:#1E40AF;line-height:1.6;">
              <strong style="display:block;margin-bottom:4px;">&#128203; Where to find your LRN:</strong>
              <ul style="margin:0;padding-left:16px;">
                <li>Your <strong>Report Card (Form 138)</strong> or <strong>Form 137</strong></li>
                <li>Your <strong>school ID</strong> or any official DepEd document</li>
                <li>Ask your <strong>class adviser or school registrar</strong></li>
              </ul>
              <p style="margin:6px 0 0;">It is a <strong>12-digit number</strong> that starts with your region code (e.g. <code style="background:#DBEAFE;padding:1px 4px;border-radius:3px;">070XXXXXXXXX</code> for Region VII). You can skip this for now.</p>
            </div>
            <span class="rf-hint">Optional &mdash; skip if you don't have it handy.</span>
          </div>
        </div>
        <script>
        (function(){
          var btn = document.getElementById('lrn-help-toggle');
          var box = document.getElementById('lrn-help-box');
          if (!btn || !box) return;
          btn.addEventListener('click', function(){
            var open = !box.hidden;
            box.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            btn.style.background  = open ? '#EFF6FF' : '#BFDBFE';
            btn.style.borderColor = open ? '#93C5FD' : '#1D4ED8';
          });
        })();
        </script>

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
             SCHOOL HEAD
        ════════════════════════════ -->
        <div class="role-fields" id="fields-school-head">
          <p class="rf-section-title">Position &amp; School Assignment</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-sh">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-sh" name="employee_id" placeholder="e.g. 1234567"/>
            <span class="rf-hint">Required for staff account verification.</span>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-position-sh">Designation <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-position-sh" name="position">
              <option value="">Select designation…</option>
              <optgroup label="Principal">
                <option value="principal-1">Principal I</option>
                <option value="principal-2">Principal II</option>
                <option value="principal-3">Principal III</option>
                <option value="principal-4">Principal IV</option>
              </optgroup>
              <optgroup label="Head Teacher">
                <option value="head-teacher-1">Head Teacher I</option>
                <option value="head-teacher-2">Head Teacher II</option>
                <option value="head-teacher-3">Head Teacher III</option>
                <option value="head-teacher-4">Head Teacher IV</option>
                <option value="head-teacher-5">Head Teacher V</option>
                <option value="head-teacher-6">Head Teacher VI</option>
              </optgroup>
              <optgroup label="Other">
                <option value="school-head-other">Other School Head Position</option>
              </optgroup>
            </select>
            <span class="rf-error" id="reg-position-sh-err" role="alert"></span>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-sh-school">School Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-sh-school" name="school_name" placeholder="e.g. Calinog National High School"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-sh-cluster">Cluster / District</label>
            <div style="display:flex;align-items:center;">
              <span style="padding:10px 12px;background:#F3F4F6;border:1.5px solid #D1D5DB;border-right:none;border-radius:9px 0 0 9px;font-size:14px;color:#6B7280;white-space:nowrap;font-weight:600;">CLUSTER-</span>
              <input class="rf-input" type="text" id="reg-sh-cluster" name="cluster"
                     placeholder="e.g. 1 or 3A"
                     style="border-radius:0 9px 9px 0;border-left:none;"/>
            </div>
            <span class="rf-hint">Optional. Enter the cluster or district number your school belongs to.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             PSDS (District Supervisor)
        ════════════════════════════ -->
        <div class="role-fields" id="fields-psds">
          <p class="rf-section-title">District Supervisor Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-psds">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-psds" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-psds-office">Division / SDO Office <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-psds-office" name="school_name" placeholder="e.g. SDO Iloilo – Calinog District"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-psds-cluster">Cluster Handled <span class="rf-req">*</span></label>
            <div style="display:flex;align-items:center;">
              <span style="padding:10px 12px;background:#F3F4F6;border:1.5px solid #D1D5DB;border-right:none;border-radius:9px 0 0 9px;font-size:14px;color:#6B7280;white-space:nowrap;font-weight:600;">CLUSTER-</span>
              <input class="rf-input" type="text" id="reg-psds-cluster" name="cluster"
                     placeholder="e.g. 2"
                     style="border-radius:0 9px 9px 0;border-left:none;"/>
            </div>
            <span class="rf-hint">Enter the cluster number or code assigned to you.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             EPS (Education Program Supervisor)
        ════════════════════════════ -->
        <div class="role-fields" id="fields-eps">
          <p class="rf-section-title">EPS Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-eps">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-eps" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-eps-division">Division / SDO <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-eps-division" name="division_unit">
              <option value="">Select division unit…</option>
              <option value="cid">CID – Curriculum Implementation Division</option>
              <option value="sgod">SGOD – School Governance and Operations Division</option>
            </select>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-eps-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-eps-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-eps-area">Learning Area / Program Focus</label>
            <input class="rf-input" type="text" id="reg-eps-area" name="affiliation" placeholder="e.g. Mathematics, English, SPED"/>
            <span class="rf-hint">Optional. Area or program you supervise.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             CES (Chief Education Supervisor)
        ════════════════════════════ -->
        <div class="role-fields" id="fields-ces">
          <p class="rf-section-title">Chief Education Supervisor Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-ces">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-ces" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-ces-division">Division Headed <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-ces-division" name="division_unit">
              <option value="">Select division…</option>
              <option value="cid">CID – Curriculum Implementation Division</option>
              <option value="sgod">SGOD – School Governance and Operations Division</option>
            </select>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-ces-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-ces-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             SPECIALIST (CID / SGOD)
        ════════════════════════════ -->
        <div class="role-fields" id="fields-specialist">
          <p class="rf-section-title">Specialist Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-spec">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-spec" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-spec-unit">Division Unit <span class="rf-req">*</span></label>
            <select class="rf-select" id="reg-spec-unit" name="division_unit">
              <option value="">Select unit…</option>
              <option value="cid">CID – Curriculum Implementation Division</option>
              <option value="sgod">SGOD – School Governance and Operations Division</option>
            </select>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-spec-position">Specialist Designation</label>
            <input class="rf-input" type="text" id="reg-spec-position" name="position" placeholder="e.g. Administrative Officer II, Librarian I"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-spec-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-spec-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             EPS – SGOD
        ════════════════════════════ -->
        <div class="role-fields" id="fields-eps-sgod">
          <p class="rf-section-title">EPS – SGOD Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-eps-sgod">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-eps-sgod" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-eps-sgod-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-eps-sgod-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-eps-sgod-area">Program / Governance Focus</label>
            <input class="rf-input" type="text" id="reg-eps-sgod-area" name="affiliation" placeholder="e.g. School Safety, DRRM, Child Protection"/>
            <span class="rf-hint">Optional. Governance area or program you oversee.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             CES – SGOD
        ════════════════════════════ -->
        <div class="role-fields" id="fields-ces-sgod">
          <p class="rf-section-title">CES – SGOD Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-ces-sgod">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-ces-sgod" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-ces-sgod-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-ces-sgod-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             SPECIALIST – SGOD
        ════════════════════════════ -->
        <div class="role-fields" id="fields-specialist-sgod">
          <p class="rf-section-title">Specialist – SGOD Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-spec-sgod">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-spec-sgod" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-spec-sgod-position">Specialist Designation</label>
            <input class="rf-input" type="text" id="reg-spec-sgod-position" name="position" placeholder="e.g. Administrative Officer II, Librarian I"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-spec-sgod-office">SDO Office Name <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-spec-sgod-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             PDO (Project Development Officer)
        ════════════════════════════ -->
        <div class="role-fields" id="fields-pdo">
          <p class="rf-section-title">Project Development Officer Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-pdo">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-pdo" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-pdo-office">Office / Division Assignment <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-pdo-office" name="school_name" placeholder="e.g. SDO Iloilo – CID, Regional Office VI"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-pdo-program">Project / Program Focus</label>
            <input class="rf-input" type="text" id="reg-pdo-program" name="affiliation" placeholder="e.g. Learning Resource Development, LRMDS Portal"/>
            <span class="rf-hint">Optional. Describe the project or program you manage.</span>
          </div>
        </div>

        <!-- ════════════════════════════
             ASDS
        ════════════════════════════ -->
        <div class="role-fields" id="fields-asds">
          <p class="rf-section-title">ASDS Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-asds">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-asds" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-asds-office">Schools Division Office <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-asds-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
          </div>
        </div>

        <!-- ════════════════════════════
             SDS
        ════════════════════════════ -->
        <div class="role-fields" id="fields-sds">
          <p class="rf-section-title">SDS Details</p>

          <div class="rf-group">
            <label class="rf-label" for="reg-employee-id-sds">Employee ID <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-employee-id-sds" name="employee_id" placeholder="e.g. 1234567"/>
          </div>

          <div class="rf-group">
            <label class="rf-label" for="reg-sds-office">Schools Division Office <span class="rf-req">*</span></label>
            <input class="rf-input" type="text" id="reg-sds-office" name="school_name" placeholder="e.g. SDO Iloilo"/>
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
              <option value="pdo">Project Development Officer (PDO)</option>
              <option value="teacher-dev">Teacher / Content Author</option>
              <option value="eps-dev">Education Program Supervisor</option>
              <option value="curriculum-writer">Curriculum Writer</option>
              <option value="illustrator">Illustrator / Graphic Artist</option>
              <option value="instructional-designer">Instructional Designer</option>
              <option value="ict-coordinator">ICT Coordinator</option>
              <option value="partner-org">Partner Organization Representative</option>
              <option value="other">Other / Not listed</option>
            </select>
          </div>

          <div class="rf-group" id="rfg-dev-specify">
            <label class="rf-label" for="reg-dev-specify">
              Specify Role <span class="rf-req" id="dev-specify-req" style="display:none">*</span>
            </label>
            <input class="rf-input" type="text" id="reg-dev-specify" name="dev_position_specify"
                   placeholder="e.g. Administrative Officer II, Librarian I, …"/>
            <span class="rf-hint">Type your exact position title if it's not in the list above, or add more detail to your selection.</span>
            <span class="rf-error" id="reg-dev-specify-err" role="alert"></span>
          </div>
          <script>
          (function () {
            var sel    = document.getElementById('reg-dev-position');
            var box    = document.getElementById('rfg-dev-specify');
            var req    = document.getElementById('dev-specify-req');
            var input  = document.getElementById('reg-dev-specify');
            function toggle() {
              var isOther = sel.value === 'other';
              // Always visible, but mark required & highlight when "Other" chosen
              req.style.display   = isOther ? '' : 'none';
              input.placeholder   = isOther
                ? 'Required — describe your position'
                : 'e.g. Administrative Officer II, Librarian I, …';
              if (isOther) input.focus();
            }
            sel.addEventListener('change', toggle);
            toggle();
          })();
          </script>

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

        <div class="rf-group" id="rfg-contact">
          <label class="rf-label" for="reg-contact">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Contact Number
            <span style="font-size:11px;font-weight:400;color:#9CA3AF;margin-left:4px;">(optional)</span>
          </label>
          <input class="rf-input" type="tel" id="reg-contact" name="contact_number"
                 placeholder="e.g. 09171234567 or +63 917 123 4567"
                 autocomplete="tel"/>
          <span class="rf-hint">A mobile number where DepEd can reach you if needed.</span>
          <span class="rf-error" id="reg-contact-err" role="alert"></span>
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
          <ul class="pw-reqs" id="pw-reqs" aria-label="Password requirements">
            <li id="req-length" class="unmet">
              <svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              At least 8 characters
            </li>
            <li id="req-upper" class="unmet">
              <svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              One uppercase letter (A–Z)
            </li>
            <li id="req-lower" class="unmet">
              <svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              One lowercase letter (a–z)
            </li>
            <li id="req-number" class="unmet">
              <svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              One number (0–9)
            </li>
            <li id="req-special" class="unmet">
              <svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
              One special character (!@#$…)
            </li>
          </ul>
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
          <div class="rv-row rv-optional" id="rv-row-grade-level"><span class="rv-label">Grade Level(s)</span><span class="rv-value" id="rv-grade-level">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-subjects"><span class="rv-label">Subjects</span><span class="rv-value" id="rv-subjects">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-school-name"><span class="rv-label">School / Office</span><span class="rv-value" id="rv-school-name">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-cluster"><span class="rv-label">Cluster</span><span class="rv-value" id="rv-cluster">-</span></div>
          <div class="rv-row rv-optional" id="rv-row-division-unit"><span class="rv-label">Division Unit</span><span class="rv-value" id="rv-division-unit">-</span></div>
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
          <div class="rv-row" style="align-items:center;">
            <span class="rv-label">Password</span>
            <span class="rv-value" style="display:flex;align-items:center;gap:8px;">
              <span id="rv-pw-dots">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</span>
              <button type="button" id="rv-pw-peek"
                aria-label="Peek at password"
                style="background:none;border:none;cursor:pointer;padding:2px 4px;color:#9CA3AF;display:flex;align-items:center;border-radius:4px;transition:color .15s;"
                title="Hold to peek">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </span>
          </div>
          <div class="rv-row rv-optional" id="rv-row-contact"><span class="rv-label">Contact No.</span><span class="rv-value" id="rv-contact">-</span></div>
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
          <a href="../auth/signin.php" id="success-signin-link"
             class="rf-btn rf-btn-primary"
             style="display:inline-flex;text-decoration:none;">
            Go to Sign In
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path d="M5 12h14M12 5l7 7-7 7"/>
            </svg>
          </a>
        </div>
      </div>

      <p class="rm-switch">Already have an account? <a class="af-link" href="../auth/signin.php">Sign in</a></p>
    </div>
  </main>
</div>

<script src="../assets/js/register.js"></script>
<script>
/* ── Auto-capitalize: first letter of each word in name fields ── */
(function () {
  function toTitleCase(str) {
    return str.replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
  }

  ['reg-fname', 'reg-lname'].forEach(function (id) {
    var input = document.getElementById(id);
    if (!input) return;

    /* Fix on every input event so it feels live */
    input.addEventListener('input', function () {
      var pos = this.selectionStart;
      var fixed = toTitleCase(this.value);
      if (fixed !== this.value) {
        this.value = fixed;
        /* Restore cursor position */
        this.setSelectionRange(pos, pos);
      }
    });

    /* Also fix on blur (handles paste etc.) */
    input.addEventListener('blur', function () {
      this.value = toTitleCase(this.value);
    });
  });
})();

/* ── Strong password requirement checker (role-aware) ── */
(function () {
  var pwInput   = document.getElementById('reg-pw');
  if (!pwInput) return;

  var CHECK_SVG = '<svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>';
  var WARN_SVG  = '<svg class="req-icon" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>';

  var rules = [
    { id: 'req-length',  test: function (v) { return v.length >= 8; } },
    { id: 'req-upper',   test: function (v) { return /[A-Z]/.test(v); } },
    { id: 'req-lower',   test: function (v) { return /[a-z]/.test(v); } },
    { id: 'req-number',  test: function (v) { return /[0-9]/.test(v); } },
    { id: 'req-special', test: function (v) { return /[^A-Za-z0-9]/.test(v); } },
  ];

  /* Roles that only need 4/5 (Strong) — learner and parent */
  var SIMPLE_ROLES = ['learner', 'parent'];

  function getSelectedRole() {
    var checked = document.querySelector('input[name="role"]:checked');
    return checked ? checked.value : '';
  }

  function getRequiredMet(val) {
    var role = getSelectedRole();
    var required = SIMPLE_ROLES.indexOf(role) !== -1 ? 4 : 5;
    return required;
  }

  function updateReqs(val) {
    var metCount = 0;
    rules.forEach(function (rule) {
      var el   = document.getElementById(rule.id);
      if (!el) return;
      var pass = rule.test(val);
      if (pass) metCount++;
      el.className = pass ? 'met' : (val.length > 0 ? 'unmet' : '');
      el.innerHTML = (pass ? CHECK_SVG : WARN_SVG) + el.textContent.trim();
    });
    return metCount;
  }

  pwInput.addEventListener('input', function () {
    updateReqs(this.value);
  });

  /* Override the Continue/Review button to enforce role-aware password strength */
  var nextBtn = document.getElementById('reg-next-2');
  if (nextBtn) {
    nextBtn.addEventListener('click', function (e) {
      var val = pwInput.value;
      var role = getSelectedRole();
      var needed = (SIMPLE_ROLES.indexOf(role) !== -1) ? 4 : 5;
      var metCount = rules.filter(function (r) { return r.test(val); }).length;
      if (metCount < needed) {
        e.stopImmediatePropagation();
        var errEl = document.getElementById('reg-pw-err');
        if (errEl) {
          errEl.textContent = needed === 4
            ? 'Your password must meet at least 4 of the 5 requirements above (Strong or better).'
            : 'Your password must meet all 5 requirements listed above.';
        }
        pwInput.focus();
      }
    }, true);
  }
})();

/* ── Review panel: password peek blink ── */
(function () {
  var peekBtn = document.getElementById('rv-pw-peek');
  var dotsEl  = document.getElementById('rv-pw-dots');
  var pwInput = document.getElementById('reg-pw');
  if (!peekBtn || !dotsEl || !pwInput) return;

  var hideTimer = null;

  function showPw() {
    dotsEl.textContent = pwInput.value || '(empty)';
    peekBtn.style.color = '#0B4F9C';
    clearTimeout(hideTimer);
    hideTimer = setTimeout(hidePw, 1500); // auto-hide after 1.5s
  }
  function hidePw() {
    dotsEl.innerHTML = '&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;';
    peekBtn.style.color = '#9CA3AF';
  }

  peekBtn.addEventListener('click', function () {
    if (dotsEl.textContent.includes('•') || dotsEl.innerHTML.includes('&#8226;')) {
      showPw();
    } else {
      hidePw();
    }
  });
  /* Also support hold-to-peek */
  peekBtn.addEventListener('mousedown', showPw);
  peekBtn.addEventListener('touchstart', function(e){ e.preventDefault(); showPw(); });
  peekBtn.addEventListener('mouseup', hidePw);
  peekBtn.addEventListener('mouseleave', hidePw);
  peekBtn.addEventListener('touchend', hidePw);
})();
</script>
</body>
</html>