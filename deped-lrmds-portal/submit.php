
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Submit Resource</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/submit.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="submit-hero">
  <div class="container">
    <p class="submit-eyebrow">Resource Development</p>
    <h1 class="submit-title">Submit a Learning Resource</h1>
    <p class="submit-sub">Contribute quality materials aligned to MELCs for learners and teachers across the Philippines.</p>
  </div>
</div>

<div class="wizard-progress" role="navigation" aria-label="Submission steps">
  <div class="container">
    <ol class="progress-steps" id="progress-steps">
      <li class="ps-item active" data-step="0">
        <span class="ps-num">1</span>
        <span class="ps-label">Upload</span>
      </li>
      <li class="ps-divider" aria-hidden="true"></li>
      <li class="ps-item" data-step="1">
        <span class="ps-num">2</span>
        <span class="ps-label">Metadata</span>
      </li>
      <li class="ps-divider" aria-hidden="true"></li>
      <li class="ps-item" data-step="2">
        <span class="ps-num">3</span>
        <span class="ps-label">MELCs Mapping</span>
      </li>
      <li class="ps-divider" aria-hidden="true"></li>
      <li class="ps-item" data-step="3">
        <span class="ps-num">4</span>
        <span class="ps-label">Authors &amp; Rights</span>
      </li>
      <li class="ps-divider" aria-hidden="true"></li>
      <li class="ps-item" data-step="4">
        <span class="ps-num">5</span>
        <span class="ps-label">Review &amp; Submit</span>
      </li>
    </ol>
  </div>
</div>

<section class="section container">
  <div class="wizard-wrap">

    <div class="wizard-panel active" id="panel-0" role="tabpanel" aria-labelledby="step-0">
      <div class="panel-header">
        <h2>Upload File</h2>
        <p class="panel-desc">Upload the resource file. Accepted formats: PDF, DOCX, PPTX, MP4, MP3, ZIP (SCORM), HTML.</p>
      </div>

      <div class="dropzone" id="dropzone" tabindex="0" role="button" aria-label="Click or drag to upload file">
        <div class="dz-icon">
          <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
          </svg>
        </div>
        <p class="dz-text"><strong>Drag &amp; drop your file here</strong><br><span>or click to browse</span></p>
        <p class="dz-hint">Max file size: 100 MB</p>
        <input type="file" id="file-input" accept=".pdf,.docx,.pptx,.mp4,.mp3,.zip,.html" hidden aria-label="Upload file"/>
      </div>

      <div class="file-preview" id="file-preview" hidden>
        <div class="fp-icon" id="fp-icon">📄</div>
        <div class="fp-info">
          <p class="fp-name" id="fp-name">filename.pdf</p>
          <p class="fp-size" id="fp-size">0 KB</p>
        </div>
        <button class="fp-remove" id="fp-remove" aria-label="Remove file">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="field mt-20">
        <label for="resource-url">Or provide a URL (optional)</label>
        <input class="input" type="url" id="resource-url" placeholder="https://drive.google.com/…" />
      </div>

      <div class="field mt-12">
        <label for="resource-version">Version</label>
        <input class="input" type="text" id="resource-version" placeholder="e.g. 1.0" style="max-width:160px"/>
      </div>
    </div>

    <div class="wizard-panel" id="panel-1" role="tabpanel" hidden>
      <div class="panel-header">
        <h2>Resource Metadata</h2>
        <p class="panel-desc">Provide descriptive information to help teachers and learners find this resource.</p>
      </div>

      <div class="form-row">
        <div class="field flex-2">
          <label for="meta-title">Title <span class="req">*</span></label>
          <input class="input" type="text" id="meta-title" placeholder="e.g., SLM – Mathematics 6: Fractions" required/>
        </div>
        <div class="field">
          <label for="meta-type">Resource Type <span class="req">*</span></label>
          <select class="select" id="meta-type" required>
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
              <option>Item Bank</option>
              <option>Rubric</option>
              <option>Worksheet / Activity Sheet</option>
            </optgroup>
            <optgroup label="Multimedia">
              <option>Video Lesson</option>
              <option>Audio Lesson</option>
              <option>Interactive (HTML/SCORM)</option>
              <option>Slide Presentation</option>
            </optgroup>
            <optgroup label="Reference">
              <option>Reader / Primer</option>
              <option>OER Collection</option>
            </optgroup>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="meta-grade">Grade Level <span class="req">*</span></label>
          <select class="select" id="meta-grade" required>
            <option value="">Select…</option>
            <option>Kinder</option>
            <option>1</option><option>2</option><option>3</option><option>4</option>
            <option>5</option><option>6</option><option>7</option><option>8</option>
            <option>9</option><option>10</option><option>11</option><option>12</option>
          </select>
        </div>
        <div class="field flex-2">
          <label for="meta-subject">Learning Area <span class="req">*</span></label>
          <select class="select" id="meta-subject" required>
            <option value="">Select…</option>
            <optgroup label="Elementary / JHS">
              <option>English</option>
              <option>Filipino</option>
              <option>Mathematics</option>
              <option>Science</option>
              <option>Araling Panlipunan</option>
              <option>MAPEH</option>
              <option>Edukasyon sa Pagpapakatao (EsP)</option>
              <option>EPP / TLE</option>
              <option>MTB-MLE</option>
            </optgroup>
            <optgroup label="Senior High School">
              <option>SHS Core</option>
              <option>SHS Applied</option>
              <option>SHS Specialized – STEM</option>
              <option>SHS Specialized – HUMSS</option>
              <option>SHS Specialized – ABM</option>
              <option>TLE / TVL</option>
            </optgroup>
          </select>
        </div>
        <div class="field">
          <label for="meta-lang">Language <span class="req">*</span></label>
          <select class="select" id="meta-lang" required>
            <option value="">Select…</option>
            <option>English</option>
            <option>Filipino</option>
            <option>Cebuano / Bisaya</option>
            <option>Ilocano</option>
            <option>Hiligaynon</option>
            <option>Waray</option>
            <option>Kapampangan</option>
            <option>Pangasinan</option>
            <option>Tagalog (MTB)</option>
            <option>Other MTB-MLE</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="meta-quarter">Quarter</label>
          <select class="select" id="meta-quarter">
            <option value="">All / N/A</option>
            <option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option>
          </select>
        </div>
        <div class="field">
          <label for="meta-week">Week(s)</label>
          <input class="input" type="text" id="meta-week" placeholder="e.g. Week 1–3"/>
        </div>
        <div class="field">
          <label for="meta-sy">School Year</label>
          <select class="select" id="meta-sy">
            <option>2024-2025</option>
            <option>2023-2024</option>
            <option>2022-2023</option>
          </select>
        </div>
        <div class="field">
          <label for="meta-track">SHS Track (if applicable)</label>
          <select class="select" id="meta-track">
            <option value="">N/A</option>
            <option>STEM</option><option>HUMSS</option><option>ABM</option>
            <option>TVL</option><option>Sports</option><option>Arts &amp; Design</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label for="meta-desc">Description / Abstract <span class="req">*</span></label>
        <textarea class="input" id="meta-desc" rows="4" placeholder="Briefly describe what this resource covers, its learning objectives, and intended users…" required></textarea>
        <p class="field-hint"><span id="desc-count">0</span> / 500 characters</p>
      </div>

      <div class="field">
        <label for="meta-keywords">Keywords</label>
        <input class="input" type="text" id="meta-keywords" placeholder="fractions, multiplication, number sense (comma-separated)"/>
        <p class="field-hint">Separate tags with commas. These improve discoverability.</p>
      </div>

      <div class="field">
        <label for="meta-thumb">Cover / Thumbnail Image (optional)</label>
        <input class="input" type="file" id="meta-thumb" accept="image/*"/>
        <p class="field-hint">Recommended: 400×300 px, JPG or PNG.</p>
      </div>
    </div>

    <div class="wizard-panel" id="panel-2" role="tabpanel" hidden>
      <div class="panel-header">
        <h2>MELCs Mapping</h2>
        <p class="panel-desc">Link this resource to one or more Most Essential Learning Competencies. Add as many MELCs as apply.</p>
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
          <span class="melc-chip">F5PT-IIIa-2</span>
        </div>
      </div>
    </div>

    <div class="wizard-panel" id="panel-3" role="tabpanel" hidden>
      <div class="panel-header">
        <h2>Authors &amp; Rights</h2>
        <p class="panel-desc">Identify the creator(s) and set the licensing terms for this resource.</p>
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
          <label for="rights-region">Region</label>
          <select class="select" id="rights-region">
            <option value="">Select…</option>
            <option>NCR</option><option>CAR</option><option>Region I</option><option>Region II</option>
            <option>Region III</option><option>Region IV-A</option><option>Region IV-B</option>
            <option>Region V</option><option>Region VI</option><option>Region VII</option>
            <option>Region VIII</option><option>Region IX</option><option>Region X</option>
            <option>Region XI</option><option>Region XII</option><option>CARAGA</option><option>BARMM</option>
          </select>
        </div>
        <div class="field flex-2">
          <label for="rights-division">Division / School</label>
          <input class="input" type="text" id="rights-division" placeholder="e.g. DepEd Division of Cebu City"/>
        </div>
      </div>

      <hr class="divider"/>

      <h3 class="sub-heading">License &amp; Permissions</h3>
      <div class="license-cards" id="license-cards">
        <label class="license-card active" data-license="DepEd">
          <input type="radio" name="license" value="DepEd" checked hidden/>
          <span class="lc-icon">🏛️</span>
          <span class="lc-name">DepEd Proprietary</span>
          <span class="lc-desc">Restricted to DepEd use only</span>
        </label>
        <label class="license-card" data-license="CC-BY">
          <input type="radio" name="license" value="CC-BY" hidden/>
          <span class="lc-icon">🌐</span>
          <span class="lc-name">CC BY 4.0</span>
          <span class="lc-desc">Attribution required</span>
        </label>
        <label class="license-card" data-license="CC-BY-SA">
          <input type="radio" name="license" value="CC-BY-SA" hidden/>
          <span class="lc-icon">🔄</span>
          <span class="lc-name">CC BY-SA 4.0</span>
          <span class="lc-desc">ShareAlike required</span>
        </label>
        <label class="license-card" data-license="CC-BY-NC">
          <input type="radio" name="license" value="CC-BY-NC" hidden/>
          <span class="lc-icon">🚫💰</span>
          <span class="lc-name">CC BY-NC 4.0</span>
          <span class="lc-desc">Non-commercial only</span>
        </label>
        <label class="license-card" data-license="OER">
          <input type="radio" name="license" value="OER" hidden/>
          <span class="lc-icon">♾️</span>
          <span class="lc-name">Open OER</span>
          <span class="lc-desc">Fully open, no restrictions</span>
        </label>
      </div>

      <div class="field mt-20">
        <label>
          <input type="checkbox" id="rights-original" class="checkbox"/> &nbsp;
          I confirm this is original work or I have rights/permission to submit this material.
        </label>
      </div>
      <div class="field">
        <label>
          <input type="checkbox" id="rights-privacy" class="checkbox"/> &nbsp;
          This resource does not contain personally identifiable information (PII) of learners.
        </label>
      </div>
    </div>

    <div class="wizard-panel" id="panel-4" role="tabpanel" hidden>
      <div class="panel-header">
        <h2>Review &amp; Submit</h2>
        <p class="panel-desc">Review the information below before submitting for QA review.</p>
      </div>

      <div class="review-grid" id="review-grid"></div>

      <div class="review-notice">
        <svg width="20" height="20" fill="none" stroke="#1D4ED8" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/></svg>
        <p>After submission, your resource will enter the <strong>QA Review Queue</strong>. You will receive a notification when the review is complete. Approved resources are published to the LRMDS repository.</p>
      </div>

      <div class="field">
        <label>
          <input type="checkbox" id="review-agree" class="checkbox"/> &nbsp;
          I agree to the <a href="#">Terms of Use</a> and <a href="#">DepEd Content Standards</a>.
        </label>
      </div>

      <div style="margin-top:24px">
        <button class="button primary large" id="submit-final" type="button">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px"><path d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
          Submit for QA Review
        </button>
      </div>
    </div>

    <div class="wizard-panel" id="panel-success" role="tabpanel" hidden>
      <div class="success-state">
        <div class="success-icon">
          <svg width="48" height="48" fill="none" stroke="#059669" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        </div>
        <h2>Resource Submitted!</h2>
        <p>Your resource has been submitted for QA review. You will be notified once it is approved.</p>
        <p><strong>Reference ID:</strong> <code id="ref-id">LRMDS-2026-00000</code></p>
        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
          <a class="button ghost" href="submit.php">Submit another</a>
          <a class="button primary" href="search.php">Browse resources</a>
        </div>
      </div>
    </div>

    <div class="wizard-nav" id="wizard-nav">
      <button class="button ghost" id="prev-btn" type="button" disabled>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19 8 12l7-7"/></svg>
        Back
      </button>
      <div class="nav-spacer"></div>
      <button class="button primary" id="next-btn" type="button">
        Next
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/submit.js"></script>
</body>
</html>