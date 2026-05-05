/**
 * DepEd LRMDS – submit.js (v3)
 * Handles:
 *   1. Gateway screen (choose resource vs news)
 *   2. Learning Resource multi-step wizard  → api/upload-resource.php
 *   3. News / Memorandum form               → api/submit-news.php
 */
(function () {
  'use strict';

  const qs  = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ═══════════════════════════════════════════
     0.  SCREEN ROUTER
  ═══════════════════════════════════════════ */
  const gatewayScreen  = qs('#gateway-screen');
  const resourceScreen = qs('#resource-screen');
  const newsScreen     = qs('#news-screen');

  function showScreen(screen) {
    [gatewayScreen, resourceScreen, newsScreen].forEach(s => {
      if (!s) return;
      s.hidden = (s !== screen);
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  qsa('[data-goto]').forEach(btn => {
    btn.addEventListener('click', () => {
      if (btn.dataset.goto === 'resource') showScreen(resourceScreen);
      if (btn.dataset.goto === 'news')     showScreen(newsScreen);
    });
  });

  qsa('.back-to-gateway').forEach(btn => {
    btn.addEventListener('click', () => showScreen(gatewayScreen));
  });


  /* ═══════════════════════════════════════════
     1.  LEARNING RESOURCE WIZARD
  ═══════════════════════════════════════════ */
  const RES_TOTAL = 5;
  let resStep = 0;
  let resFile = null;

  const resPanels   = qsa('#resource-screen .wizard-panel:not(#res-panel-success)');
  const resPrgItems = qsa('#resource-screen .ps-item');
  const resPrevBtn  = qs('#res-prev-btn');
  const resNextBtn  = qs('#res-next-btn');
  const resWizNav   = qs('#res-wizard-nav');
  const resSuccess  = qs('#res-panel-success');

  const FILE_ICONS = { pdf:'📄', docx:'📝', pptx:'📊', mp4:'🎬', mp3:'🎵', zip:'📦', html:'🌐' };
  const fmtSize    = b => b < 1048576 ? (b/1024).toFixed(1)+' KB' : (b/1048576).toFixed(1)+' MB';

  function showResFile(file) {
    resFile = file;
    const ext = file.name.split('.').pop().toLowerCase();
    qs('#res-fp-icon').textContent = FILE_ICONS[ext] || '📄';
    qs('#res-fp-name').textContent = file.name;
    qs('#res-fp-size').textContent = fmtSize(file.size);
    qs('#res-dropzone').hidden     = true;
    qs('#res-file-preview').hidden = false;
  }

  const resDZ = qs('#res-dropzone');
  const resFI = qs('#res-file-input');
  if (resDZ && resFI) {
    resDZ.addEventListener('click',   () => resFI.click());
    resDZ.addEventListener('keydown', e => { if (e.key==='Enter'||e.key===' ') resFI.click(); });
    resFI.addEventListener('change',  () => { if (resFI.files[0]) showResFile(resFI.files[0]); });
    resDZ.addEventListener('dragover', e => { e.preventDefault(); resDZ.classList.add('dragover'); });
    resDZ.addEventListener('dragleave', () => resDZ.classList.remove('dragover'));
    resDZ.addEventListener('drop', e => {
      e.preventDefault(); resDZ.classList.remove('dragover');
      if (e.dataTransfer.files[0]) showResFile(e.dataTransfer.files[0]);
    });
  }

  qs('#res-fp-remove')?.addEventListener('click', () => {
    resFile = null;
    if (resFI) resFI.value = '';
    qs('#res-file-preview').hidden = true;
    qs('#res-dropzone').hidden = false;
  });

  // Description counter
  const resDesc = qs('#res-desc');
  const resDC   = qs('#res-desc-count');
  resDesc?.addEventListener('input', () => {
    const len = resDesc.value.length;
    resDC.textContent = len;
    resDC.style.color = len > 450 ? '#DC2626' : '#9CA3AF';
  });

  // MELC entries
  let melcCount = 0;
  function addMelc() {
    melcCount++;
    const id = melcCount;
    const el = document.createElement('div');
    el.className = 'melc-entry';
    el.innerHTML = `
      <button class="remove-btn" type="button" aria-label="Remove MELC" ${id===1?'style="display:none"':''}>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
      <div class="form-row">
        <div class="field flex-2"><label>MELC Code</label><input class="input" type="text" name="melc-code" placeholder="e.g., M6NS-Ia-1"/></div>
        <div class="field"><label>Quarter</label><select class="select" name="melc-quarter"><option value="">All</option><option>Q1</option><option>Q2</option><option>Q3</option><option>Q4</option></select></div>
        <div class="field"><label>Week</label><input class="input" type="text" name="melc-week" placeholder="e.g. Week 1"/></div>
      </div>
      <div class="field"><label>Competency Description</label><input class="input" type="text" name="melc-desc" placeholder="Brief description…"/></div>`;
    qs('#melc-list').appendChild(el);
    el.querySelector('.remove-btn').addEventListener('click', () => {
      el.remove();
      const entries = qsa('.melc-entry');
      if (entries.length === 1) entries[0].querySelector('.remove-btn').style.display = 'none';
    });
  }
  addMelc();
  qs('#add-melc')?.addEventListener('click', () => {
    addMelc();
    qsa('.melc-entry .remove-btn').forEach(b => b.style.display = '');
  });

  // Author entries
  let authorCount = 0;
  function addAuthor() {
    authorCount++;
    const id = authorCount;
    const el = document.createElement('div');
    el.className = 'author-entry';
    el.innerHTML = `
      <button class="remove-btn" type="button" aria-label="Remove author" ${id===1?'style="display:none"':''}>
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
      <div class="form-row">
        <div class="field"><label>First Name</label><input class="input" type="text" name="author-first" placeholder="Juan"/></div>
        <div class="field"><label>Last Name</label><input class="input" type="text" name="author-last" placeholder="dela Cruz"/></div>
        <div class="field flex-2"><label>Email (optional)</label><input class="input" type="email" name="author-email" placeholder="jdelacruz@deped.gov.ph"/></div>
      </div>
      <div class="form-row">
        <div class="field"><label>Role</label><select class="select" name="author-role"><option>Author</option><option>Co-Author</option><option>Editor</option><option>Illustrator</option><option>Reviewer</option><option>Translator</option></select></div>
        <div class="field flex-2"><label>Position / Designation</label><input class="input" type="text" name="author-position" placeholder="e.g. Teacher III"/></div>
      </div>`;
    qs('#author-list').appendChild(el);
    el.querySelector('.remove-btn').addEventListener('click', () => {
      el.remove();
      const entries = qsa('.author-entry');
      if (entries.length === 1) entries[0].querySelector('.remove-btn').style.display = 'none';
    });
  }
  addAuthor();
  qs('#add-author')?.addEventListener('click', () => {
    addAuthor();
    qsa('.author-entry .remove-btn').forEach(b => b.style.display = '');
  });

  // License cards
  qsa('.license-card').forEach(card => {
    card.addEventListener('click', () => {
      qsa('.license-card').forEach(c => c.classList.remove('active'));
      card.classList.add('active');
    });
  });

  // Validation
  function clearResErrors(panel) {
    qsa('.error', panel).forEach(el => el.classList.remove('error'));
    qsa('.error-msg', panel).forEach(el => el.remove());
  }
  function markResError(el, msg) {
    el.classList.add('error');
    const p = document.createElement('p');
    p.className = 'error-msg';
    p.textContent = msg;
    el.parentElement.appendChild(p);
  }
  function validateResPanel(idx) {
    const panel = resPanels[idx];
    clearResErrors(panel);
    let ok = true;
    if (idx === 0) {
      const url = qs('#res-url')?.value?.trim();
      if (!resFile && !url) {
        resDZ.style.borderColor = '#DC2626';
        setTimeout(() => resDZ.style.borderColor = '', 2000);
        ok = false;
      }
    }
    if (idx === 1) {
      ['res-title','res-type','res-grade','res-subject','res-lang','res-desc'].forEach(id => {
        const el = qs('#'+id);
        if (!el?.value?.trim()) { markResError(el, 'This field is required.'); ok = false; }
      });
    }
    if (idx === 3) {
      const orig = qs('#res-original');
      const priv = qs('#res-privacy');
      if (!orig.checked) { orig.closest('label').style.color='#DC2626'; ok=false; }
      if (!priv.checked) { priv.closest('label').style.color='#DC2626'; ok=false; }
    }
    return ok;
  }

  // Build review grid
  function buildResReview() {
    const grid = qs('#res-review-grid');
    if (!grid) return;
    const v  = id => (qs('#'+id)?.value||'').trim();
    const sv = id => { const el=qs('#'+id); return el?.options[el.selectedIndex]?.text||''; };
    const melcCodes = qsa('[name="melc-code"]').map(i=>i.value).filter(Boolean).join(', ');
    const authors   = qsa('.author-entry').map(e=>{
      const f=e.querySelector('[name="author-first"]')?.value||'';
      const l=e.querySelector('[name="author-last"]')?.value||'';
      return (f+' '+l).trim();
    }).filter(Boolean).join('; ');
    const license  = qs('input[name="license"]:checked')?.value||'—';
    const fileName = resFile ? resFile.name : (v('res-url')||'—');
    const rows = [
      ['File / URL', fileName], ['Title', v('res-title')||'—'],
      ['Type', sv('res-type')||'—'], ['Grade', sv('res-grade')||'—'],
      ['Learning Area', sv('res-subject')||'—'], ['Language', sv('res-lang')||'—'],
      ['Quarter', sv('res-quarter')||'—'], ['School Year', v('res-sy')||'—'],
      ['MELC Code(s)', melcCodes||'—'], ['Author(s)', authors||'—'],
      ['License', license], ['Division', v('res-division')||'—'],
    ];
    grid.innerHTML = rows.map(([l,v2])=>`
      <div class="review-label">${l}</div>
      <div class="review-value${v2==='—'?' empty':''}">${v2}</div>
    `).join('');
  }

  // Step navigation
  function resGoTo(idx) {
    if (idx < 0 || idx >= RES_TOTAL) return;
    if (idx > resStep && !validateResPanel(resStep)) return;

    if (idx > resStep) {
      resPrgItems[resStep]?.classList.add('done');
      resPrgItems[resStep]?.classList.remove('active');
    } else {
      resPrgItems[resStep]?.classList.remove('done','active');
    }
    resStep = idx;

    resPanels.forEach((p,i) => { p.classList.toggle('active',i===resStep); p.hidden=i!==resStep; });
    resPrgItems.forEach((item,i) => {
      item.classList.toggle('active',i===resStep);
      if (i<resStep) item.classList.add('done');
    });

    resPrevBtn.disabled = resStep === 0;
    if (resStep === RES_TOTAL - 1) {
      resNextBtn.style.display = 'none';
      buildResReview();
    } else {
      resNextBtn.style.display = '';
      resNextBtn.innerHTML = resStep===RES_TOTAL-2
        ? 'Review <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>'
        : 'Next <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"/></svg>';
    }
    qs('#resource-screen .section')?.scrollIntoView({ behavior:'smooth', block:'start' });
  }

  resPrevBtn?.addEventListener('click', () => resGoTo(resStep-1));
  resNextBtn?.addEventListener('click', () => resGoTo(resStep+1));
  resPrgItems.forEach((item,i) => {
    item.addEventListener('click', () => { if (i<=resStep||item.classList.contains('done')) resGoTo(i); });
  });

  // ── Resource final submit ─────────────────────────────────────────────
  qs('#res-submit-final')?.addEventListener('click', async () => {
    if (!qs('#res-agree')?.checked) {
      qs('#res-agree').closest('label').style.color='#DC2626';
      return;
    }

    const submitBtn = qs('#res-submit-final');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span style="margin-right:8px">⏳</span>Uploading…';

    // Collect MELC entries
    const melcEntries = qsa('.melc-entry').map(e => ({
      code    : e.querySelector('[name="melc-code"]')?.value    || '',
      quarter : e.querySelector('[name="melc-quarter"]')?.value || '',
      week    : e.querySelector('[name="melc-week"]')?.value    || '',
      desc    : e.querySelector('[name="melc-desc"]')?.value    || '',
    })).filter(m => m.code);

    // Collect author entries
    const authorEntries = qsa('.author-entry').map(e => ({
      first    : e.querySelector('[name="author-first"]')?.value    || '',
      last     : e.querySelector('[name="author-last"]')?.value     || '',
      email    : e.querySelector('[name="author-email"]')?.value    || '',
      role     : e.querySelector('[name="author-role"]')?.value     || '',
      position : e.querySelector('[name="author-position"]')?.value || '',
    })).filter(a => a.first || a.last);

    const fd = new FormData();
    if (resFile) fd.append('file', resFile);
    fd.append('title',    qs('#res-title')?.value    || '');
    fd.append('type',     qs('#res-type')?.value     || '');
    fd.append('grade',    qs('#res-grade')?.value    || '');
    fd.append('subject',  qs('#res-subject')?.value  || '');
    fd.append('language', qs('#res-lang')?.value     || '');
    fd.append('quarter',  qs('#res-quarter')?.value  || '');
    fd.append('sy',       qs('#res-sy')?.value       || '');
    fd.append('desc',     qs('#res-desc')?.value     || '');
    fd.append('url',      qs('#res-url')?.value      || '');
    fd.append('version',  qs('#res-version')?.value  || '1.0');
    fd.append('license',  qs('input[name="license"]:checked')?.value || 'DepEd');
    fd.append('region',   qs('#res-region')?.value   || '');
    fd.append('division', qs('#res-division')?.value || '');
    melcEntries.forEach((m, i) => {
      fd.append(`melcs[${i}]`, JSON.stringify(m));
    });
    authorEntries.forEach((a, i) => {
      fd.append(`authors[${i}]`, JSON.stringify(a));
    });

    try {
      const resp = await fetch('api/upload-resource.php', { method: 'POST', body: fd });
      const data = await resp.json();

      if (!data.ok) {
        alert('Upload failed: ' + data.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Submit Resource';
        return;
      }

      // Show success
      resPanels.forEach(p => { p.hidden=true; p.classList.remove('active'); });
      resSuccess.hidden = false;
      resSuccess.classList.add('active');
      resWizNav.style.display = 'none';
      qs('#resource-screen .wizard-progress')?.style.setProperty('display','none');
      qs('#res-ref-id').textContent = data.ref_id || 'LRMDS-' + Date.now();
      resSuccess.scrollIntoView({ behavior:'smooth', block:'center' });

    } catch (err) {
      alert('Network error: ' + err.message);
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Submit Resource';
    }
  });

  resGoTo(0);


  /* ═══════════════════════════════════════════
     2.  NEWS / MEMORANDUM FORM
  ═══════════════════════════════════════════ */
  let newsType = 'announcement';

  qsa('.ntc').forEach(btn => {
    btn.addEventListener('click', () => {
      qsa('.ntc').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      newsType = btn.dataset.ntype;

      const memoFields  = qs('#memo-fields');
      const eventFields = qs('#event-fields');
      const attachNote  = qs('#attach-required-note');

      memoFields.style.display  = newsType==='memo'  ? '' : 'none';
      eventFields.style.display = newsType==='event' ? '' : 'none';
      attachNote.style.display  = newsType==='memo'  ? '' : 'none';
    });
  });

  // News file dropzone
  let newsFile = null;
  const newsDZ = qs('#news-dropzone');
  const newsFI = qs('#news-file-input');

  function showNewsFile(file) {
    newsFile = file;
    const ext = file.name.split('.').pop().toLowerCase();
    qs('#news-fp-icon').textContent = FILE_ICONS[ext] || '📄';
    qs('#news-fp-name').textContent = file.name;
    qs('#news-fp-size').textContent = fmtSize(file.size);
    newsDZ.hidden = true;
    qs('#news-file-preview').hidden = false;
  }

  if (newsDZ && newsFI) {
    newsDZ.addEventListener('click',   () => newsFI.click());
    newsDZ.addEventListener('keydown', e => { if (e.key==='Enter'||e.key===' ') newsFI.click(); });
    newsFI.addEventListener('change',  () => { if (newsFI.files[0]) showNewsFile(newsFI.files[0]); });
    newsDZ.addEventListener('dragover', e => { e.preventDefault(); newsDZ.classList.add('dragover'); });
    newsDZ.addEventListener('dragleave', () => newsDZ.classList.remove('dragover'));
    newsDZ.addEventListener('drop', e => {
      e.preventDefault(); newsDZ.classList.remove('dragover');
      if (e.dataTransfer.files[0]) showNewsFile(e.dataTransfer.files[0]);
    });
  }

  qs('#news-fp-remove')?.addEventListener('click', () => {
    newsFile = null;
    if (newsFI) newsFI.value = '';
    qs('#news-file-preview').hidden = true;
    newsDZ.hidden = false;
  });

  // Character counter
  const newsSummary = qs('#news-summary');
  const newsCCount  = qs('#news-char-count');
  newsSummary?.addEventListener('input', () => {
    const len = newsSummary.value.length;
    newsCCount.textContent = len;
    newsCCount.style.color = len > 1800 ? '#DC2626' : '#9CA3AF';
  });

  // News validation
  function validateNewsForm() {
    let ok = true;
    const required = [
      { el: qs('#news-title'),        msg: 'Title is required.' },
      { el: qs('#news-date'),         msg: 'Date is required.' },
      { el: qs('#news-summary'),      msg: 'Summary / body is required.' },
      { el: qs('#news-poster-name'),  msg: 'Your name is required.' },
      { el: qs('#news-poster-role'),  msg: 'Your role is required.' },
      { el: qs('#news-poster-email'), msg: 'Your email is required.' },
    ];
    required.forEach(({ el, msg }) => {
      if (!el) return;
      el.classList.remove('error');
      el.parentElement.querySelector('.error-msg')?.remove();
      if (!el.value.trim()) {
        el.classList.add('error');
        const p = document.createElement('p');
        p.className = 'error-msg';
        p.textContent = msg;
        el.parentElement.appendChild(p);
        ok = false;
      }
    });

    if (newsType === 'memo') {
      const mn = qs('#memo-number');
      mn.classList.remove('error');
      mn.parentElement.querySelector('.error-msg')?.remove();
      if (!mn.value.trim()) {
        mn.classList.add('error');
        const p = document.createElement('p');
        p.className = 'error-msg';
        p.textContent = 'Memorandum number is required.';
        mn.parentElement.appendChild(p);
        ok = false;
      }
      if (!newsFile) {
        newsDZ.style.borderColor = '#DC2626';
        newsDZ.style.background  = '#FEF2F2';
        setTimeout(() => { newsDZ.style.borderColor=''; newsDZ.style.background=''; }, 2500);
        ok = false;
      }
    }

    if (!qs('#news-agree')?.checked) {
      qs('#news-agree').closest('label').style.color = '#DC2626';
      ok = false;
    }

    return ok;
  }

  // ── News submit handler (real fetch) ─────────────────────────────────
  async function submitNewsForm(isDraft) {
    if (!isDraft && !validateNewsForm()) return;

    const submitArea = qs('#news-submit-area');
    const successDiv = qs('#news-success');
    const submitBtn  = qs('#news-submit-btn');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span style="margin-right:8px">⏳</span>Uploading…';

    const fd = new FormData();
    fd.append('type',      newsType);
    fd.append('title',     qs('#news-title')?.value       || '');
    fd.append('date',      qs('#news-date')?.value        || '');
    fd.append('summary',   qs('#news-summary')?.value     || '');
    fd.append('poster',    qs('#news-poster-name')?.value || '');
    fd.append('email',     qs('#news-poster-email')?.value|| '');
    fd.append('isDraft',   isDraft ? '1' : '0');
    fd.append('audience',  qs('#news-audience')?.value    || 'all');
    fd.append('pin',       qs('#news-pin')?.value         || '0');
    fd.append('tags',      qs('#news-tags')?.value        || '');

    // Memo fields
    if (newsType === 'memo') {
      fd.append('memo_number',  qs('#memo-number')?.value  || '');
      fd.append('memo_series',  qs('#memo-series')?.value  || '');
      fd.append('memo_to',      qs('#memo-to')?.value      || '');
      fd.append('memo_from',    qs('#memo-from')?.value    || '');
      fd.append('memo_urgency', qs('#memo-urgency')?.value || 'routine');
    }

    // Event fields
    if (newsType === 'event') {
      fd.append('event_start',    qs('#event-start')?.value    || '');
      fd.append('event_end',      qs('#event-end')?.value      || '');
      fd.append('event_venue',    qs('#event-venue')?.value    || '');
      fd.append('event_register', qs('#event-register')?.value || '');
    }

    // File attachment
    if (newsFile) fd.append('attachment', newsFile);

    try {
      const resp = await fetch('api/submit-news.php', { method: 'POST', body: fd });
      const data = await resp.json();

      if (!data.ok) {
        alert('Submission failed: ' + data.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Publish Post';
        return;
      }

      submitArea.hidden = true;
      successDiv.hidden = false;
      qs('#news-ref-id').textContent = data.ref_id || 'NEWS-' + Date.now();

      if (isDraft) {
        qs('#news-success-title').textContent = 'Draft Saved!';
        qs('#news-success-msg').textContent   = 'Your post has been saved as a draft. You can edit and publish it from the admin panel.';
      }

      successDiv.scrollIntoView({ behavior:'smooth', block:'center' });

    } catch (err) {
      alert('Network error: ' + err.message);
      submitBtn.disabled = false;
      submitBtn.innerHTML = 'Publish Post';
    }
  }

  qs('#news-submit-btn')?.addEventListener('click', () => submitNewsForm(false));
  qs('#news-draft-btn')?.addEventListener('click',  () => submitNewsForm(true));

  // Default today's date
  const newsDateInput = qs('#news-date');
  if (newsDateInput) newsDateInput.value = new Date().toISOString().slice(0,10);

  // Shake + fade keyframes
  const shakeStyle = document.createElement('style');
  shakeStyle.textContent = `
    @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
    @keyframes fadeSlideIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
  `;
  document.head.appendChild(shakeStyle);

})();