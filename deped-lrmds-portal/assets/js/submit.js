// DepEd LRMDS – submit.js
// Handles all interactivity for the multi-step submission wizard.
(function () {
  'use strict';

  if (!location.pathname.endsWith('submit.html')) return;

  /* ── Helpers ── */
  const qs  = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ── State ── */
  let currentStep = 0;
  const TOTAL_STEPS = 5; // panels 0-4, plus panel-success
  let uploadedFile = null;

  /* ── Element refs ── */
  const progressItems = qsa('.ps-item');
  const panels        = qsa('.wizard-panel:not(#panel-success)');
  const successPanel  = qs('#panel-success');
  const prevBtn       = qs('#prev-btn');
  const nextBtn       = qs('#next-btn');
  const wizardNav     = qs('#wizard-nav');

  /* ════════════════════════════════
     STEP NAVIGATION
  ════════════════════════════════ */
  function goTo(idx) {
    if (idx < 0 || idx >= TOTAL_STEPS) return;

    // Validate before advancing
    if (idx > currentStep && !validatePanel(currentStep)) return;

    // Mark old step done
    if (idx > currentStep) {
      progressItems[currentStep]?.classList.add('done');
      progressItems[currentStep]?.classList.remove('active');
    } else {
      // Going back – undo done only for current
      progressItems[currentStep]?.classList.remove('done', 'active');
    }

    currentStep = idx;

    // Update panels
    panels.forEach((p, i) => {
      p.classList.toggle('active', i === currentStep);
      p.hidden = i !== currentStep;
    });

    // Update progress bar
    progressItems.forEach((item, i) => {
      item.classList.toggle('active', i === currentStep);
      if (i < currentStep) item.classList.add('done');
    });

    // Update buttons
    prevBtn.disabled = currentStep === 0;
    if (currentStep === TOTAL_STEPS - 1) {
      nextBtn.style.display = 'none';
      buildReview();
    } else {
      nextBtn.style.display = '';
      nextBtn.innerHTML = currentStep === TOTAL_STEPS - 2
        ? 'Review <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>'
        : 'Next <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>';
    }

    // Scroll to top of section
    qs('section.section').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  prevBtn.addEventListener('click', () => goTo(currentStep - 1));
  nextBtn.addEventListener('click', () => goTo(currentStep + 1));

  // Clicking a completed progress step navigates to it
  progressItems.forEach((item, i) => {
    item.addEventListener('click', () => {
      if (i <= currentStep || item.classList.contains('done')) goTo(i);
    });
  });

  /* ════════════════════════════════
     PANEL 0 – FILE UPLOAD
  ════════════════════════════════ */
  const dropzone   = qs('#dropzone');
  const fileInput  = qs('#file-input');
  const filePreview = qs('#file-preview');
  const fpName     = qs('#fp-name');
  const fpSize     = qs('#fp-size');
  const fpIcon     = qs('#fp-icon');
  const fpRemove   = qs('#fp-remove');

  const FILE_ICONS = { pdf:'📄', docx:'📝', pptx:'📊', mp4:'🎬', mp3:'🎵', zip:'📦', html:'🌐' };
  const formatSize = bytes => bytes < 1024*1024 ? (bytes/1024).toFixed(1)+' KB' : (bytes/(1024*1024)).toFixed(1)+' MB';

  function showFile(file) {
    uploadedFile = file;
    const ext = file.name.split('.').pop().toLowerCase();
    fpIcon.textContent = FILE_ICONS[ext] || '📄';
    fpName.textContent = file.name;
    fpSize.textContent = formatSize(file.size);
    dropzone.hidden = true;
    filePreview.hidden = false;
  }

  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') fileInput.click(); });
  fileInput.addEventListener('change', () => { if (fileInput.files[0]) showFile(fileInput.files[0]); });

  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
  dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('dragover');
    if (e.dataTransfer.files[0]) showFile(e.dataTransfer.files[0]);
  });

  fpRemove.addEventListener('click', () => {
    uploadedFile = null;
    fileInput.value = '';
    filePreview.hidden = true;
    dropzone.hidden = false;
  });

  /* ════════════════════════════════
     PANEL 1 – DESCRIPTION COUNTER
  ════════════════════════════════ */
  const descArea  = qs('#meta-desc');
  const descCount = qs('#desc-count');
  if (descArea && descCount) {
    descArea.addEventListener('input', () => {
      const len = descArea.value.length;
      descCount.textContent = len;
      descCount.style.color = len > 450 ? '#DC2626' : '#9CA3AF';
    });
  }

  /* ════════════════════════════════
     PANEL 2 – MELC ENTRIES
  ════════════════════════════════ */
  let melcCount = 0;

  function addMelcEntry() {
    melcCount++;
    const id = melcCount;
    const entry = document.createElement('div');
    entry.className = 'melc-entry';
    entry.dataset.melcId = id;
    entry.innerHTML = `
      <button class="remove-btn" type="button" aria-label="Remove MELC ${id}" ${id === 1 ? 'style="display:none"' : ''}>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
      <div class="form-row">
        <div class="field flex-2">
          <label>MELC Code</label>
          <input class="input" type="text" name="melc-code" placeholder="e.g., M6NS-Ia-1"/>
        </div>
        <div class="field">
          <label>Quarter</label>
          <select class="select" name="melc-quarter">
            <option value="">All</option>
            <option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option>
          </select>
        </div>
        <div class="field">
          <label>Week</label>
          <input class="input" type="text" name="melc-week" placeholder="e.g. Week 1"/>
        </div>
      </div>
      <div class="field">
        <label>Competency Description</label>
        <input class="input" type="text" name="melc-desc" placeholder="Brief description of the competency…"/>
      </div>`;
    qs('#melc-list').appendChild(entry);
    entry.querySelector('.remove-btn').addEventListener('click', () => {
      entry.remove();
      // Show remove btn on first entry if only one left
      const entries = qsa('.melc-entry');
      if (entries.length === 1) entries[0].querySelector('.remove-btn').style.display = 'none';
    });
  }

  addMelcEntry(); // start with one
  qs('#add-melc').addEventListener('click', () => {
    addMelcEntry();
    // Show remove btn on all if more than one
    qsa('.melc-entry .remove-btn').forEach(b => b.style.display = '');
  });

  /* ════════════════════════════════
     PANEL 3 – AUTHOR ENTRIES
  ════════════════════════════════ */
  let authorCount = 0;

  function addAuthorEntry() {
    authorCount++;
    const id = authorCount;
    const entry = document.createElement('div');
    entry.className = 'author-entry';
    entry.dataset.authorId = id;
    entry.innerHTML = `
      <button class="remove-btn" type="button" aria-label="Remove author ${id}" ${id === 1 ? 'style="display:none"' : ''}>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
      <div class="form-row">
        <div class="field">
          <label>First Name</label>
          <input class="input" type="text" name="author-first" placeholder="Juan"/>
        </div>
        <div class="field">
          <label>Last Name</label>
          <input class="input" type="text" name="author-last" placeholder="dela Cruz"/>
        </div>
        <div class="field flex-2">
          <label>Email (optional)</label>
          <input class="input" type="email" name="author-email" placeholder="jdelacruz@deped.gov.ph"/>
        </div>
      </div>
      <div class="form-row">
        <div class="field">
          <label>Role</label>
          <select class="select" name="author-role">
            <option>Author</option>
            <option>Co-Author</option>
            <option>Editor</option>
            <option>Illustrator</option>
            <option>Reviewer</option>
            <option>Translator</option>
          </select>
        </div>
        <div class="field flex-2">
          <label>Position / Designation</label>
          <input class="input" type="text" name="author-position" placeholder="e.g. Teacher III, T-III"/>
        </div>
      </div>`;
    qs('#author-list').appendChild(entry);
    entry.querySelector('.remove-btn').addEventListener('click', () => {
      entry.remove();
      const entries = qsa('.author-entry');
      if (entries.length === 1) entries[0].querySelector('.remove-btn').style.display = 'none';
    });
  }

  addAuthorEntry();
  qs('#add-author').addEventListener('click', () => {
    addAuthorEntry();
    qsa('.author-entry .remove-btn').forEach(b => b.style.display = '');
  });

  /* ── License card toggle ── */
  qsa('.license-card').forEach(card => {
    card.addEventListener('click', () => {
      qsa('.license-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
    });
  });

 
    //PANEL 4 – REVIEW
  
  function buildReview() {
    const grid = qs('#review-grid');
    if (!grid) return;

    const val  = id => (qs('#' + id)?.value || '').trim();
    const sVal = id => { const el = qs('#' + id); return el?.options[el.selectedIndex]?.text || ''; };

    const melcCodes = qsa('[name="melc-code"]').map(i => i.value).filter(Boolean).join(', ');
    const authors   = qsa('.author-entry').map(e => {
      const f = e.querySelector('[name="author-first"]')?.value || '';
      const l = e.querySelector('[name="author-last"]')?.value || '';
      return (f + ' ' + l).trim();
    }).filter(Boolean).join('; ');

    const license = qs('input[name="license"]:checked')?.value || '—';
    const fileName = uploadedFile ? uploadedFile.name : (val('resource-url') || '—');

    const rows = [
      ['File / URL',      fileName],
      ['Title',           val('meta-title') || '—'],
      ['Type',            sVal('meta-type')  || '—'],
      ['Grade',           sVal('meta-grade') || '—'],
      ['Learning Area',   sVal('meta-subject') || '—'],
      ['Language',        sVal('meta-lang') || '—'],
      ['Quarter',         sVal('meta-quarter') || '—'],
      ['School Year',     val('meta-sy') || '—'],
      ['SHS Track',       sVal('meta-track') || 'N/A'],
      ['MELC Code(s)',    melcCodes || '—'],
      ['Author(s)',       authors || '—'],
      ['License',         license],
      ['Region',          sVal('rights-region') || '—'],
      ['Division/School', val('rights-division') || '—'],
    ];

    grid.innerHTML = rows.map(([label, value]) => `
      <div class="review-label">${label}</div>
      <div class="review-value${value === '—' || value === 'N/A' ? ' empty' : ''}">${value}</div>
    `).join('');
  }

  /* ── Final submit ── */
  qs('#submit-final')?.addEventListener('click', () => {
    const agreeBox = qs('#review-agree');
    if (!agreeBox?.checked) {
      agreeBox.closest('.field').querySelector('label').style.color = '#DC2626';
      agreeBox.focus();
      return;
    }
    // Simulate submission
    panels.forEach(p => { p.hidden = true; p.classList.remove('active'); });
    successPanel.hidden = false;
    successPanel.classList.add('active');
    wizardNav.style.display = 'none';
    qs('#wizard-progress')?.style.setProperty('display', 'none');
    // Generate fake ref ID
    const ref = 'LRMDS-2026-' + String(Math.floor(Math.random() * 90000) + 10000);
    qs('#ref-id').textContent = ref;
    successPanel.scrollIntoView({ behavior: 'smooth', block: 'center' });
  });

  /* ════════════════════════════════
     VALIDATION
  ════════════════════════════════ */
  function clearErrors(panel) {
    qsa('.error', panel).forEach(el => el.classList.remove('error'));
    qsa('.error-msg', panel).forEach(el => el.remove());
  }

  function markError(el, msg) {
    el.classList.add('error');
    const hint = document.createElement('p');
    hint.className = 'error-msg';
    hint.textContent = msg;
    el.parentElement.appendChild(hint);
  }

  function validatePanel(idx) {
    const panel = panels[idx];
    clearErrors(panel);
    let valid = true;

    if (idx === 0) {
      // At least a file or URL
      const url = qs('#resource-url')?.value?.trim();
      if (!uploadedFile && !url) {
        dropzone.style.borderColor = '#DC2626';
        setTimeout(() => dropzone.style.borderColor = '', 2000);
        valid = false;
        // Gentle shake
        dropzone.style.animation = 'none';
        dropzone.offsetHeight; // reflow
        dropzone.style.animation = 'shake .3s ease';
      }
    }

    if (idx === 1) {
      const required = ['meta-title', 'meta-type', 'meta-grade', 'meta-subject', 'meta-lang', 'meta-desc'];
      required.forEach(id => {
        const el = qs('#' + id);
        if (!el?.value?.trim()) { markError(el, 'This field is required.'); valid = false; }
      });
    }

    if (idx === 3) {
      const originalBox = qs('#rights-original');
      const privacyBox  = qs('#rights-privacy');
      if (!originalBox?.checked || !privacyBox?.checked) {
        if (!originalBox?.checked) originalBox.closest('label').style.color = '#DC2626';
        if (!privacyBox?.checked)  privacyBox.closest('label').style.color = '#DC2626';
        valid = false;
      }
    }

    return valid;
  }

  /* ── Add shake keyframe ── */
  const shakeStyle = document.createElement('style');
  shakeStyle.textContent = `@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }`;
  document.head.appendChild(shakeStyle);

  /* ── Init ── */
  goTo(0);

})();