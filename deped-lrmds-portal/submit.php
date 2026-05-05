<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Submit / Develop</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/submit.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- ── Gateway: choose what you want to submit ───────────────────────────── -->
<div id="gateway-screen">
  <div class="submit-hero">
    <div class="container">
      <p class="submit-eyebrow">Resource Development &amp; Publishing</p>
      <h1 class="submit-title">What would you like to submit?</h1>
      <p class="submit-sub">Choose a submission type to get started. Each has its own guided form.</p>
    </div>
  </div>

  <section class="section container">
    <div class="gateway-grid">

      <button class="gateway-card" data-goto="resource" type="button">
        <div class="gc-icon">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/>
          </svg>
        </div>
        <div class="gc-body">
          <h2>Learning Resource</h2>
          <p>SLMs, TGs, DLLs, videos, assessments, or any MELC-aligned material.</p>
          <ul class="gc-examples">
            <li>📄 Self-Learning Modules</li>
            <li>🎬 Video Lessons</li>
            <li>📝 Worksheets &amp; Assessments</li>
            <li>📊 Slide Presentations</li>
          </ul>
        </div>
        <span class="gc-arrow">→</span>
      </button>

      <button class="gateway-card" data-goto="news" type="button">
        <div class="gc-icon">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z"/>
          </svg>
        </div>
        <div class="gc-body">
          <h2>News or Memorandum</h2>
          <p>Post system announcements, advisories, program updates, or official DepEd memorandums with a file attachment.</p>
          <ul class="gc-examples">
            <li>📣 System Announcements</li>
            <li>📋 Official Memorandums (with PDF)</li>
            <li>🌐 Program Updates</li>
            <li>📅 Events &amp; Webinars</li>
          </ul>
        </div>
        <span class="gc-arrow">→</span>
      </button>

    </div>
  </section>
</div><!-- /gateway-screen -->


<!-- ══════════════════════════════════════════════════════════════════════════
     SCREEN 2 – LEARNING RESOURCE (existing multi-step wizard)
══════════════════════════════════════════════════════════════════════════ -->
<div id="resource-screen" hidden>
  <div class="submit-hero">
    <div class="container" style="display:flex;align-items:center;gap:16px">
      <button class="back-to-gateway" type="button" aria-label="Back to submission type">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19 8 12l7-7"/></svg>
        Back
      </button>
      <div>
        <p class="submit-eyebrow">Learning Resource</p>
        <h1 class="submit-title">Submit a Learning Resource</h1>
        <p class="submit-sub">Contribute quality materials aligned to MELCs for learners and teachers across the Philippines.</p>
      </div>
    </div>
  </div>

  <div class="wizard-progress" role="navigation" aria-label="Submission steps">
    <div class="container">
      <ol class="progress-steps" id="progress-steps">
        <li class="ps-item active" data-step="0"><span class="ps-num"><span>1</span></span><span class="ps-label">Upload</span></li>
        <li class="ps-divider" aria-hidden="true"></li>
        <li class="ps-item" data-step="1"><span class="ps-num"><span>2</span></span><span class="ps-label">Metadata</span></li>
        <li class="ps-divider" aria-hidden="true"></li>
        <li class="ps-item" data-step="2"><span class="ps-num"><span>3</span></span><span class="ps-label">MELCs</span></li>
        <li class="ps-divider" aria-hidden="true"></li>
        <li class="ps-item" data-step="3"><span class="ps-num"><span>4</span></span><span class="ps-label">Authors</span></li>
        <li class="ps-divider" aria-hidden="true"></li>
        <li class="ps-item" data-step="4"><span class="ps-num"><span>5</span></span><span class="ps-label">Review</span></li>
      </ol>
    </div>
  </div>

  <section class="section container">
    <div class="wizard-wrap">

      <!-- Panel 0: Upload -->
      <div class="wizard-panel active" id="res-panel-0" role="tabpanel">
        <div class="panel-header">
          <h2>Upload File</h2>
          <p class="panel-desc">Upload the resource file. Accepted: PDF, DOCX, PPTX, MP4, MP3, ZIP, HTML.</p>
        </div>
        <div class="dropzone" id="res-dropzone" tabindex="0" role="button" aria-label="Click or drag to upload file">
          <div class="dz-icon">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
            </svg>
          </div>
          <p class="dz-text"><strong>Drag &amp; drop your file here</strong><br><span>or click to browse</span></p>
          <p class="dz-hint">Max file size: 100 MB · PDF, DOCX, PPTX, MP4, MP3, ZIP, HTML</p>
          <input type="file" id="res-file-input" accept=".pdf,.docx,.pptx,.mp4,.mp3,.zip,.html" hidden/>
        </div>
        <div class="file-preview" id="res-file-preview" hidden>
          <div class="fp-icon" id="res-fp-icon">📄</div>
          <div class="fp-info">
            <p class="fp-name" id="res-fp-name">filename.pdf</p>
            <p class="fp-size" id="res-fp-size">0 KB</p>
          </div>
          <button class="fp-remove" id="res-fp-remove" aria-label="Remove file">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="field mt-20">
          <label for="res-url">Or provide a URL (optional)</label>
          <input class="input" type="url" id="res-url" placeholder="https://drive.google.com/…"/>
        </div>
        <div class="field mt-12">
          <label for="res-version">Version</label>
          <input class="input" type="text" id="res-version" placeholder="e.g. 1.0" style="max-width:160px"/>
        </div>
      </div>

      <!-- Panel 1: Metadata -->
      <div class="wizard-panel" id="res-panel-1" role="tabpanel" hidden>
        <div class="panel-header">
          <h2>Resource Metadata</h2>
          <p class="panel-desc">Provide descriptive information to help teachers and learners find this resource.</p>
        </div>
        <div class="form-row">
          <div class="field flex-2">
            <label for="res-title">Title <span class="req">*</span></label>
            <input class="input" type="text" id="res-title" placeholder="e.g., SLM – Mathematics 6: Fractions" required/>
          </div>
          <div class="field">
            <label for="res-type">Resource Type <span class="req">*</span></label>
            <select class="select" id="res-type" required>
              <option value="">Select type…</option>
              <optgroup label="Print / Modular">
                <option>Textbook</option>
                <option>Learner's Material (LM)</option>
                <option>Teacher's Guide (TG)</option>
                <option>Self-Learning Module (SLM)</option>
                <option>Curriculum Guide</option>
              </optgroup>
              <optgroup label="Lesson Plans">
                <option>Daily Lesson Log (DLL)</option>
                <option>Daily Lesson Plan (DLP)</option>
              </optgroup>
              <optgroup label="Assessment">
                <option>Formative Assessment</option>
                <option>Summative Assessment</option>
                <option>Worksheet / Activity Sheet</option>
              </optgroup>
              <optgroup label="Multimedia">
                <option>Video Lesson</option>
                <option>Audio Lesson</option>
                <option>Slide Presentation</option>
              </optgroup>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="res-grade">Grade Level <span class="req">*</span></label>
            <select class="select" id="res-grade" required>
              <option value="">Select…</option>
              <option>Kinder</option>
              <option>1</option><option>2</option><option>3</option><option>4</option>
              <option>5</option><option>6</option><option>7</option><option>8</option>
              <option>9</option><option>10</option><option>11</option><option>12</option>
            </select>
          </div>
          <div class="field flex-2">
            <label for="res-subject">Learning Area <span class="req">*</span></label>
            <select class="select" id="res-subject" required>
              <option value="">Select…</option>
              <option>English</option><option>Filipino</option><option>Mathematics</option>
              <option>Science</option><option>Araling Panlipunan</option><option>MAPEH</option>
              <option>EsP</option><option>EPP / TLE</option><option>MTB-MLE</option>
            </select>
          </div>
          <div class="field">
            <label for="res-lang">Language <span class="req">*</span></label>
            <select class="select" id="res-lang" required>
              <option value="">Select…</option>
              <option>English</option><option>Filipino</option><option>Mother Tongue</option><option>Cebuano</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="field">
            <label for="res-quarter">Quarter</label>
            <select class="select" id="res-quarter">
              <option value="">All Quarters</option>
              <option>Quarter 1</option><option>Quarter 2</option><option>Quarter 3</option><option>Quarter 4</option>
            </select>
          </div>
          <div class="field">
            <label for="res-sy">School Year</label>
            <input class="input" type="text" id="res-sy" placeholder="e.g. 2025–2026"/>
          </div>
        </div>
        <div class="field">
          <label for="res-desc">Description <span class="req">*</span></label>
          <textarea class="input" id="res-desc" rows="4" placeholder="Briefly describe the resource, its purpose, and how it can be used…" style="resize:vertical" maxlength="500"></textarea>
          <p class="field-hint"><span id="res-desc-count">0</span>/500 characters</p>
        </div>
      </div>

      <!-- Panel 2: MELCs -->
      <div class="wizard-panel" id="res-panel-2" role="tabpanel" hidden>
        <div class="panel-header">
          <h2>MELCs Mapping</h2>
          <p class="panel-desc">Link this resource to one or more Most Essential Learning Competencies.</p>
        </div>
        <div id="melc-list"></div>
        <button class="button ghost add-melc-btn" id="add-melc" type="button">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m-8-8h16"/></svg>
          Add another MELC
        </button>
        <div class="melc-helper">
          <h4>MELC Code Format</h4>
          <p>Codes follow the pattern: <code>SubjectGrade-Quarter-Week-Number</code></p>
          <div class="melc-examples">
            <span class="melc-chip">M6NS-Ia-1</span>
            <span class="melc-chip">EN10RC-Ic-4</span>
            <span class="melc-chip">S8LT-IIb-3</span>
          </div>
        </div>
      </div>

      <!-- Panel 3: Authors & Rights -->
      <div class="wizard-panel" id="res-panel-3" role="tabpanel" hidden>
        <div class="panel-header">
          <h2>Authors &amp; Rights</h2>
          <p class="panel-desc">Identify the creator(s) and set the licensing terms.</p>
        </div>
        <div id="author-list"></div>
        <button class="button ghost add-melc-btn" id="add-author" type="button">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m-8-8h16"/></svg>
          Add another author
        </button>
        <hr class="divider"/>
        <h3 class="sub-heading">Institution / Division</h3>
        <div class="form-row">
          <div class="field">
            <label for="res-region">Region</label>
            <select class="select" id="res-region">
              <option value="">Select…</option>
              <option>NCR</option><option>CAR</option><option>Region I</option><option>Region II</option>
              <option>Region III</option><option>Region IV-A</option><option>Region IV-B</option>
              <option>Region V</option><option>Region VI</option><option>Region VII</option>
              <option>Region VIII</option><option>Region IX</option><option>Region X</option>
              <option>Region XI</option><option>Region XII</option><option>CARAGA</option><option>BARMM</option>
            </select>
          </div>
          <div class="field flex-2">
            <label for="res-division">Division / School</label>
            <input class="input" type="text" id="res-division" placeholder="e.g. DepEd Division of Cebu City"/>
          </div>
        </div>
        <hr class="divider"/>
        <h3 class="sub-heading">License &amp; Permissions</h3>
        <div class="license-cards">
          <label class="license-card active" data-license="DepEd">
            <input type="radio" name="license" value="DepEd" checked hidden/>
            <span class="lc-icon">🏛️</span><span class="lc-name">DepEd Proprietary</span><span class="lc-desc">Restricted to DepEd use only</span>
          </label>
          <label class="license-card" data-license="CC-BY">
            <input type="radio" name="license" value="CC-BY" hidden/>
            <span class="lc-icon">🌐</span><span class="lc-name">CC BY 4.0</span><span class="lc-desc">Attribution required</span>
          </label>
          <label class="license-card" data-license="CC-BY-NC">
            <input type="radio" name="license" value="CC-BY-NC" hidden/>
            <span class="lc-icon">🚫💰</span><span class="lc-name">CC BY-NC 4.0</span><span class="lc-desc">Non-commercial only</span>
          </label>
          <label class="license-card" data-license="OER">
            <input type="radio" name="license" value="OER" hidden/>
            <span class="lc-icon">♾️</span><span class="lc-name">Open OER</span><span class="lc-desc">Fully open, no restrictions</span>
          </label>
        </div>
        <div class="field mt-20">
          <label><input type="checkbox" id="res-original" class="checkbox"/> &nbsp; I confirm this is original work or I have permission to submit it.</label>
        </div>
        <div class="field">
          <label><input type="checkbox" id="res-privacy" class="checkbox"/> &nbsp; This resource does not contain personally identifiable information (PII) of learners.</label>
        </div>
      </div>

      <!-- Panel 4: Review -->
      <div class="wizard-panel" id="res-panel-4" role="tabpanel" hidden>
        <div class="panel-header">
          <h2>Review &amp; Submit</h2>
          <p class="panel-desc">Review the information below before submitting for QA review.</p>
        </div>
        <div class="review-grid" id="res-review-grid"></div>
        <div class="review-notice">
          <svg width="20" height="20" fill="none" stroke="#1D4ED8" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
          <p>After submission, your resource will enter the <strong>QA Review Queue</strong>. Approved resources are published to the LRMDS repository.</p>
        </div>
        <div class="field">
          <label><input type="checkbox" id="res-agree" class="checkbox"/> &nbsp; I agree to the <a href="#">Terms of Use</a> and <a href="#">DepEd Content Standards</a>.</label>
        </div>
        <div style="margin-top:24px">
          <button class="button primary large" id="res-submit-final" type="button">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
            Submit for QA Review
          </button>
        </div>
      </div>

      <!-- Success -->
      <div class="wizard-panel" id="res-panel-success" role="tabpanel" hidden>
        <div class="success-state">
          <div class="success-icon">
            <svg width="48" height="48" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          </div>
          <h2>Resource Submitted!</h2>
          <p>Your resource has been submitted for QA review. You will be notified once it is approved.</p>
          <p><strong>Reference ID:</strong> <code id="res-ref-id">LRMDS-2026-00000</code></p>
          <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
            <a class="button ghost" href="submit.php">Submit another</a>
            <a class="button primary" href="search.php">Browse resources</a>
          </div>
        </div>
      </div>

      <div class="wizard-nav" id="res-wizard-nav">
        <button class="button ghost" id="res-prev-btn" type="button" disabled>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19 8 12l7-7"/></svg>
          Back
        </button>
        <div class="nav-spacer"></div>
        <button class="button primary" id="res-next-btn" type="button">
          Next
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>

    </div>
  </section>
</div><!-- /resource-screen -->


<!-- ══════════════════════════════════════════════════════════════════════════
     SCREEN 3 – NEWS / MEMORANDUM
══════════════════════════════════════════════════════════════════════════ -->
<div id="news-screen" hidden>
  <div class="submit-hero submit-hero--news">
    <div class="container" style="display:flex;align-items:center;gap:16px">
      <button class="back-to-gateway" type="button" aria-label="Back to submission type">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19 8 12l7-7"/></svg>
        Back
      </button>
      <div>
        <p class="submit-eyebrow">News &amp; Publishing</p>
        <h1 class="submit-title">Post News or Memorandum</h1>
        <p class="submit-sub">Publish an official announcement, memo, program update, or event to the LRMDS portal.</p>
      </div>
    </div>
  </div>

  <section class="section container">
    <div class="wizard-wrap">

      <!-- News type selector -->
      <div class="panel-header">
        <h2>What are you posting?</h2>
        <p class="panel-desc">Select the type of post, then fill in the details.</p>
      </div>

      <div class="news-type-cards" id="news-type-cards">
        <button class="ntc active" data-ntype="announcement" type="button">
          <span class="ntc-icon">📣</span>
          <span class="ntc-label">System Announcement</span>
        </button>
        <button class="ntc" data-ntype="memo" type="button">
          <span class="ntc-icon">📋</span>
          <span class="ntc-label">Memorandum</span>
        </button>
        <button class="ntc" data-ntype="program" type="button">
          <span class="ntc-icon">🌐</span>
          <span class="ntc-label">Program Update</span>
        </button>
        <button class="ntc" data-ntype="event" type="button">
          <span class="ntc-icon">📅</span>
          <span class="ntc-label">Event / Webinar</span>
        </button>
      </div>

      <hr class="divider"/>

      <!-- ── Shared fields ── -->
      <div class="form-row">
        <div class="field flex-2">
          <label for="news-title">Title / Subject <span class="req">*</span></label>
          <input class="input" type="text" id="news-title" placeholder="e.g., Advisory No. 01 s. 2026 – LRMDS System Maintenance" required/>
        </div>
        <div class="field">
          <label for="news-date">Date <span class="req">*</span></label>
          <input class="input" type="date" id="news-date" required/>
        </div>
      </div>

      <div class="field">
        <label for="news-summary">Summary / Body <span class="req">*</span></label>
        <textarea class="input" id="news-summary" rows="5" placeholder="Write the full content of the announcement, program update, or advisory…" style="resize:vertical" maxlength="2000"></textarea>
        <p class="field-hint"><span id="news-char-count">0</span>/2000 characters</p>
      </div>

      <!-- ── Memorandum-specific fields (shown/hidden by type) ── -->
      <div id="memo-fields" style="display:none">
        <hr class="divider"/>
        <h3 class="sub-heading">Memorandum Details</h3>

        <div class="form-row">
          <div class="field">
            <label for="memo-number">Memorandum No. <span class="req">*</span></label>
            <input class="input" type="text" id="memo-number" placeholder="e.g., DM-CI-2026-001"/>
          </div>
          <div class="field">
            <label for="memo-series">Series (Year)</label>
            <input class="input" type="text" id="memo-series" placeholder="e.g., s. 2026" style="max-width:160px"/>
          </div>
          <div class="field flex-2">
            <label for="memo-to">To (Recipients)</label>
            <input class="input" type="text" id="memo-to" placeholder="e.g., All Schools Division Superintendents"/>
          </div>
        </div>
        <div class="form-row">
          <div class="field flex-2">
            <label for="memo-from">From</label>
            <input class="input" type="text" id="memo-from" placeholder="e.g., USEC for Curriculum and Instruction"/>
          </div>
          <div class="field">
            <label for="memo-urgency">Urgency</label>
            <select class="select" id="memo-urgency">
              <option value="routine">Routine</option>
              <option value="priority">Priority</option>
              <option value="urgent">Urgent</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ── Event-specific fields ── -->
      <div id="event-fields" style="display:none">
        <hr class="divider"/>
        <h3 class="sub-heading">Event Details</h3>
        <div class="form-row">
          <div class="field">
            <label for="event-start">Start Date/Time</label>
            <input class="input" type="datetime-local" id="event-start"/>
          </div>
          <div class="field">
            <label for="event-end">End Date/Time</label>
            <input class="input" type="datetime-local" id="event-end"/>
          </div>
          <div class="field flex-2">
            <label for="event-venue">Venue / Platform</label>
            <input class="input" type="text" id="event-venue" placeholder="e.g., Zoom, Google Meet, DepEd LRMDS HQ"/>
          </div>
        </div>
        <div class="field">
          <label for="event-register">Registration Link (optional)</label>
          <input class="input" type="url" id="event-register" placeholder="https://…"/>
        </div>
      </div>

      <hr class="divider"/>

      <!-- ── File attachment ── -->
      <h3 class="sub-heading">
        Attachment
        <span id="attach-required-note" class="attach-note memo-only" style="display:none">
          <span class="req">*</span> Required for memorandums (softcopy PDF)
        </span>
      </h3>

      <div class="dropzone" id="news-dropzone" tabindex="0" role="button" aria-label="Click or drag to upload attachment">
        <div class="dz-icon">
          <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13"/>
          </svg>
        </div>
        <p class="dz-text"><strong>Drag &amp; drop file here</strong><br><span>or click to browse</span></p>
        <p class="dz-hint">PDF, DOCX, PPTX · Max 50 MB</p>
        <input type="file" id="news-file-input" accept=".pdf,.docx,.pptx,.jpg,.png" hidden/>
      </div>

      <div class="file-preview" id="news-file-preview" hidden>
        <div class="fp-icon" id="news-fp-icon">📄</div>
        <div class="fp-info">
          <p class="fp-name" id="news-fp-name">filename.pdf</p>
          <p class="fp-size" id="news-fp-size">0 KB</p>
        </div>
        <button class="fp-remove" id="news-fp-remove" aria-label="Remove file">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <hr class="divider"/>

      <!-- ── Visibility ── -->
      <h3 class="sub-heading">Visibility &amp; Tags</h3>
      <div class="form-row">
        <div class="field">
          <label for="news-audience">Target Audience</label>
          <select class="select" id="news-audience">
            <option value="all">All Users</option>
            <option value="teachers">Teachers</option>
            <option value="admins">School Administrators</option>
            <option value="devs">Content Developers</option>
          </select>
        </div>
        <div class="field">
          <label for="news-pin">Pin to top?</label>
          <select class="select" id="news-pin">
            <option value="0">No</option>
            <option value="1">Yes – pin this post</option>
          </select>
        </div>
        <div class="field flex-2">
          <label for="news-tags">Tags (comma-separated)</label>
          <input class="input" type="text" id="news-tags" placeholder="e.g., maintenance, lrmds, 2026"/>
        </div>
      </div>

      <!-- ── Posted by ── -->
      <hr class="divider"/>
      <h3 class="sub-heading">Posted By</h3>
      <div class="form-row">
        <div class="field">
          <label for="news-poster-name">Name <span class="req">*</span></label>
          <input class="input" type="text" id="news-poster-name" placeholder="Juan dela Cruz" required/>
        </div>
        <div class="field">
          <label for="news-poster-role">Position / Role <span class="req">*</span></label>
          <input class="input" type="text" id="news-poster-role" placeholder="LRMDS Coordinator" required/>
        </div>
        <div class="field flex-2">
          <label for="news-poster-email">Email <span class="req">*</span></label>
          <input class="input" type="email" id="news-poster-email" placeholder="jdelacruz@deped.gov.ph" required/>
        </div>
      </div>

      <!-- ── Submit area ── -->
      <div id="news-submit-area" style="margin-top:32px">
        <div class="review-notice">
          <svg width="20" height="20" fill="none" stroke="#1D4ED8" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
          <p>This post will go to an admin for review before it appears publicly on the LRMDS news page.</p>
        </div>
        <div class="field">
          <label><input type="checkbox" id="news-agree" class="checkbox"/> &nbsp; I confirm this information is accurate and authorized for publication on DepEd LRMDS.</label>
        </div>
        <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap">
          <button class="button primary large" id="news-submit-btn" type="button">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
            Publish Post
          </button>
          <button class="button ghost large" id="news-draft-btn" type="button">Save as Draft</button>
        </div>
      </div>

      <!-- News success -->
      <div id="news-success" hidden>
        <div class="success-state">
          <div class="success-icon">
            <svg width="48" height="48" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
          </div>
          <h2 id="news-success-title">Post Submitted!</h2>
          <p id="news-success-msg">Your post has been sent for admin review and will appear on the news page once approved.</p>
          <p><strong>Reference ID:</strong> <code id="news-ref-id">NEWS-2026-00000</code></p>
          <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
            <a class="button ghost" href="submit.php">Post another</a>
            <a class="button primary" href="news.php">View News page</a>
          </div>
        </div>
      </div>

    </div>
  </section>
</div><!-- /news-screen -->


<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/submit.js"></script>
</body>
</html>