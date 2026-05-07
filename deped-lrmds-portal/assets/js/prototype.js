/* ═══════════════════════════════════════════════════════════════════
   app.js — DepEd Carcar City LRMDS
   Catalogue data mirrors Microsoft Graph API shape so swapping in
   live Graph API calls later requires only replacing DRIVE_DATA.
   To connect live data: GET /v1.0/users/{upn}/drive/root:/{path}:/children
═══════════════════════════════════════════════════════════════════ */

/* ── CATALOGUE DATA ──────────────────────────────────────────────── */
const DRIVE_DATA = {

  /* Root — top-level subject/grade folders */
  null: {
    items: [
      { id:'f001', name:'Grade 6 – Mathematics',        folder:{childCount:14}, subject:'Mathematics',       grade:'Grade 6' },
      { id:'f002', name:'Grade 6 – Science',             folder:{childCount:9},  subject:'Science',           grade:'Grade 6' },
      { id:'f003', name:'Grade 6 – English',             folder:{childCount:11}, subject:'English',           grade:'Grade 6' },
      { id:'f004', name:'Grade 5 – Mathematics',        folder:{childCount:8},  subject:'Mathematics',       grade:'Grade 5' },
      { id:'f005', name:'Grade 5 – Science',             folder:{childCount:6},  subject:'Science',           grade:'Grade 5' },
      { id:'f006', name:'Grade 4 – Filipino',            folder:{childCount:7},  subject:'Filipino',          grade:'Grade 4' },
      { id:'f007', name:'Grade 7 – Araling Panlipunan', folder:{childCount:5},  subject:'Araling Panlipunan',grade:'Grade 7' },
      { id:'f008', name:'Grade 8 – Science',             folder:{childCount:10}, subject:'Science',           grade:'Grade 8' },
    ]
  },

  /* Grade 6 – Mathematics → quarter folders */
  f001: {
    parentId: null, parentName: 'All Resources',
    items: [
      { id:'f001a', name:'Quarter 1 – Fractions',  folder:{childCount:5} },
      { id:'f001b', name:'Quarter 2 – Decimals',   folder:{childCount:4} },
      { id:'f001c', name:'Quarter 3 – Geometry',   folder:{childCount:6} },
      { id:'f001d', name:'Quarter 4 – Statistics', folder:{childCount:3} },
    ]
  },

  /* Quarter 1 – Fractions → actual files */
  f001a: {
    parentId: 'f001', parentName: 'Grade 6 – Mathematics',
    items: [
      {
        id:'r001', name:'SLM_G6_Math_Q1_W1_Fractions-and-Mixed-Numbers.pdf',
        file:{mimeType:'application/pdf'}, size:3145728, webUrl: SHAREPOINT_URL,
        meta:{ title:'Fractions and Mixed Numbers – Grade 6 SLM', grade:'Grade 6', subject:'Mathematics', type:'SLM', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id:'r002', name:'TG_G6_Math_Q1_W1_Teacher-Guide.pdf',
        file:{mimeType:'application/pdf'}, size:2097152, webUrl: SHAREPOINT_URL,
        meta:{ title:"Teacher's Guide – Fractions Q1 W1", grade:'Grade 6', subject:'Mathematics', type:'TG', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id:'r003', name:'DLL_G6_Math_Q1_W1.docx',
        file:{mimeType:'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}, size:524288, webUrl: SHAREPOINT_URL,
        meta:{ title:'Daily Lesson Log – G6 Math Q1 W1', grade:'Grade 6', subject:'Mathematics', type:'DLL', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'pending' }
      },
      {
        id:'r004', name:'Assessment_G6_Math_Q1_W1_Fractions.pdf',
        file:{mimeType:'application/pdf'}, size:1048576, webUrl: SHAREPOINT_URL,
        meta:{ title:'Weekly Assessment – Fractions Grade 6', grade:'Grade 6', subject:'Mathematics', type:'Assessment', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id:'r005', name:'VIDEO_G6_Math_Q1_W1_Fractions-Lesson.mp4',
        file:{mimeType:'video/mp4'}, size:52428800, webUrl: SHAREPOINT_URL,
        meta:{ title:'Video Lesson – Fractions and Mixed Numbers', grade:'Grade 6', subject:'Mathematics', type:'Video', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  /* Grade 6 – Science */
  f002: {
    parentId: null, parentName: 'All Resources',
    items: [
      { id:'f002a', name:'Quarter 1 – Living Things', folder:{childCount:4} },
      { id:'f002b', name:'Quarter 2 – Matter',        folder:{childCount:5} },
      {
        id:'r010', name:'SLM_G6_Sci_Q1_Introduction.pdf',
        file:{mimeType:'application/pdf'}, size:2621440, webUrl: SHAREPOINT_URL,
        meta:{ title:'Introduction to Grade 6 Science – SLM', grade:'Grade 6', subject:'Science', type:'SLM', melc:'S6LT-Ia-b-1', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  /* Grade 6 – English */
  f003: {
    parentId: null, parentName: 'All Resources',
    items: [
      { id:'f003a', name:'Quarter 1 – Reading Comprehension', folder:{childCount:6} },
      { id:'f003b', name:'Quarter 2 – Grammar',               folder:{childCount:4} },
      {
        id:'r020', name:'SLM_G6_Eng_Q1_W1_Reading-Skills.pdf',
        file:{mimeType:'application/pdf'}, size:1835008, webUrl: SHAREPOINT_URL,
        meta:{ title:'Reading Skills – Grade 6 English SLM Q1', grade:'Grade 6', subject:'English', type:'SLM', melc:'EN6RC-Ia-2.2.2', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  /* Stub folders */
  f004:  { parentId:null,   parentName:'All Resources',         items:[{id:'f004a',name:'Quarter 1',folder:{childCount:4}},{id:'f004b',name:'Quarter 2',folder:{childCount:3}}] },
  f005:  { parentId:null,   parentName:'All Resources',         items:[{id:'f005a',name:'Quarter 1',folder:{childCount:3}}] },
  f006:  { parentId:null,   parentName:'All Resources',         items:[{id:'f006a',name:'Quarter 1',folder:{childCount:4}}] },
  f007:  { parentId:null,   parentName:'All Resources',         items:[{id:'f007a',name:'Quarter 1',folder:{childCount:3}}] },
  f008:  { parentId:null,   parentName:'All Resources',         items:[{id:'f008a',name:'Quarter 1',folder:{childCount:5}}] },
  f001b: { parentId:'f001', parentName:'Grade 6 – Mathematics', items:[] },
  f001c: { parentId:'f001', parentName:'Grade 6 – Mathematics', items:[] },
  f001d: { parentId:'f001', parentName:'Grade 6 – Mathematics', items:[] },
  f002a: { parentId:'f002', parentName:'Grade 6 – Science',     items:[] },
  f002b: { parentId:'f002', parentName:'Grade 6 – Science',     items:[] },
  f003a: { parentId:'f003', parentName:'Grade 6 – English',     items:[] },
  f003b: { parentId:'f003', parentName:'Grade 6 – English',     items:[] },
  f004a: { parentId:'f004', parentName:'Grade 5 – Mathematics', items:[] },
  f004b: { parentId:'f004', parentName:'Grade 5 – Mathematics', items:[] },
  f005a: { parentId:'f005', parentName:'Grade 5 – Science',     items:[] },
  f006a: { parentId:'f006', parentName:'Grade 4 – Filipino',    items:[] },
  f007a: { parentId:'f007', parentName:'Grade 7 – Araling Panlipunan', items:[] },
  f008a: { parentId:'f008', parentName:'Grade 8 – Science',     items:[] },
};

/* ── STATE ───────────────────────────────────────────────────────── */
let currentFolder = null;
let folderHistory = [];
let activeFilters = { subject:'', grade:'', type:'', q:'' };
let listMode      = false;

/* ── HELPERS ─────────────────────────────────────────────────────── */
function fileIcon(mime) {
  if (!mime) return '📁';
  if (mime.includes('pdf'))    return '📄';
  if (mime.includes('video'))  return '🎬';
  if (mime.includes('word') || mime.includes('docx')) return '📝';
  if (mime.includes('sheet') || mime.includes('xlsx')) return '📊';
  if (mime.includes('presentation') || mime.includes('pptx')) return '📑';
  if (mime.includes('image'))  return '🖼️';
  if (mime.includes('audio'))  return '🎵';
  return '📎';
}
function fileExt(name) { return name.split('.').pop().toUpperCase(); }
function formatSize(bytes) {
  if (!bytes) return '';
  return bytes < 1048576
    ? (bytes / 1024).toFixed(0) + ' KB'
    : (bytes / 1048576).toFixed(1) + ' MB';
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

/* ── NAVIGATION ──────────────────────────────────────────────────── */
function navigateTo(folderId, folderName) {
  if (folderId !== null) {
    folderHistory.push({ id: currentFolder, name: document.getElementById('current-folder-name').textContent });
  } else {
    folderHistory = [];
  }
  currentFolder = folderId;
  renderAll(folderName || 'All Resources');
}

function goBack() {
  if (!folderHistory.length) return;
  const prev = folderHistory.pop();
  currentFolder = prev.id;
  renderAll(prev.name || 'All Resources');
}

/* ── SEARCH / FILTER ─────────────────────────────────────────────── */
function applySearch() {
  activeFilters.q       = document.getElementById('search-input').value.trim().toLowerCase();
  activeFilters.grade   = document.getElementById('filter-grade').value;
  activeFilters.subject = document.getElementById('filter-subject').value;
  activeFilters.type    = document.getElementById('filter-type').value;
  currentFolder = null;
  folderHistory = [];
  renderAll('Search Results');
}

function clearSearch() {
  document.getElementById('search-input').value      = '';
  document.getElementById('filter-grade').value      = '';
  document.getElementById('filter-subject').value    = '';
  document.getElementById('filter-type').value       = '';
  activeFilters = { subject:'', grade:'', type:'', q:'' };
  currentFolder = null;
  folderHistory = [];
  renderAll('All Resources');
}

function matchesFilters(item) {
  const { q, grade, subject, type } = activeFilters;
  if (!q && !grade && !subject && !type) return true;

  if (item.file && item.meta) {
    const m = item.meta;
    if (q && !m.title.toLowerCase().includes(q) &&
             !item.name.toLowerCase().includes(q) &&
             !(m.melc||'').toLowerCase().includes(q)) return false;
    if (grade   && m.grade   !== grade)   return false;
    if (subject && m.subject !== subject) return false;
    if (type    && m.type    !== type)    return false;
    return true;
  }

  if (item.folder) {
    const name = item.name.toLowerCase();
    if (q && !name.includes(q)) return false;
    if (grade   && item.grade   && item.grade   !== grade)   return false;
    if (subject && item.subject && item.subject !== subject) return false;
    return true;
  }

  return true;
}

function collectAllFiles() {
  const files = [];
  function dig(data) {
    if (!data) return;
    for (const item of data.items) {
      if (item.file) files.push(item);
      else if (item.folder && DRIVE_DATA[item.id]) dig(DRIVE_DATA[item.id]);
    }
  }
  dig(DRIVE_DATA[null]);
  return files;
}

/* ── RENDER ──────────────────────────────────────────────────────── */
function renderAll(titleText) {
  updateBreadcrumb();
  updateBackBar(titleText);
  document.getElementById('results-title').textContent = titleText || 'All Resources';

  const isSearch = activeFilters.q || activeFilters.grade || activeFilters.subject || activeFilters.type;
  const items    = isSearch
    ? collectAllFiles().filter(matchesFilters)
    : (DRIVE_DATA[currentFolder]?.items || []).filter(matchesFilters);

  const grid = document.getElementById('results');
  grid.className = 'results-grid' + (listMode ? ' list-view' : '');
  grid.innerHTML = '';

  if (!items.length) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <strong>No resources found</strong>
        <p>Try adjusting your filters or browse a different folder.</p>
        <p style="margin-top:12px">
          <a class="button primary small" href="${SHAREPOINT_URL}" target="_blank" rel="noopener">
            Open SharePoint folder ↗
          </a>
        </p>
      </div>`;
    document.getElementById('results-meta').textContent = '0 items found';
    updateSidebar(items);
    return;
  }

  document.getElementById('results-meta').textContent = isSearch
    ? `${items.length} file${items.length !== 1 ? 's' : ''} found across all folders`
    : `${items.length} item${items.length !== 1 ? 's' : ''} in this folder`;

  items.forEach(item => grid.appendChild(item.folder ? renderFolderCard(item) : renderFileCard(item)));
  updateSidebar(items);
}

function renderFolderCard(item) {
  const div = document.createElement('div');
  div.className = 'folder-card';
  div.setAttribute('role', 'button');
  div.setAttribute('tabindex', '0');
  div.setAttribute('aria-label', `Open folder: ${item.name}`);

  div.innerHTML = `
    <div class="folder-icon-wrap">📁</div>
    <div class="folder-name">${item.name}</div>
    <div class="folder-meta">
      ${item.subject ? `<span class="folder-chip">${item.subject}</span>` : ''}
      ${item.grade   ? `<span style="font-size:10px;color:var(--muted)">${item.grade}</span>` : ''}
      <span style="margin-left:auto">${item.folder.childCount} items ›</span>
    </div>`;

  const open = () => navigateTo(item.id, item.name);
  div.addEventListener('click', open);
  div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') open(); });
  return div;
}

function renderFileCard(item) {
  const div   = document.createElement('div');
  div.className = 'result-card';

  const meta  = item.meta || {};
  const ext   = fileExt(item.name);
  const icon  = fileIcon(item.file?.mimeType);
  const title = meta.title || item.name.replace(/\.[^.]+$/, '').replace(/_/g, ' ');
  const qa    = meta.qa === 'passed'  ? `<span class="tag success">✔ QA</span>`
              : meta.qa === 'pending' ? `<span class="tag warn">⏳ QA</span>` : '';

  div.innerHTML = `
    <div class="thumb-wrap">
      <span style="font-size:28px">${icon}</span>
      <span class="type-badge">${ext}</span>
    </div>
    <div class="card-body">
      <div class="tag-row">
        ${meta.grade   ? `<span class="tag">${meta.grade}</span>` : ''}
        ${meta.subject ? `<span class="tag secondary">${meta.subject}</span>` : ''}
        ${meta.type    ? `<span class="tag secondary">${meta.type}</span>` : ''}
        ${qa}
      </div>
      <div class="card-title">${title}</div>
      <div class="card-detail">
        ${meta.melc    ? `<code>${meta.melc}</code>` : ''}
        ${meta.quarter ? `<span class="sep">·</span><span>${meta.quarter}</span>` : ''}
        ${item.size    ? `<span class="sep">·</span><span>${formatSize(item.size)}</span>` : ''}
      </div>
      <div class="card-actions">
        <a href="${SHAREPOINT_URL}" target="_blank" rel="noopener"
           class="button ghost small" onclick="event.stopPropagation()">Open in SharePoint ↗</a>
        <a href="${SHAREPOINT_URL}" target="_blank" rel="noopener"
           class="button primary small"
           onclick="event.stopPropagation();showToast('Opening SharePoint…')">⬇ Download</a>
      </div>
    </div>`;

  div.addEventListener('click', () => showToast(`Opening SharePoint for: ${title}`));
  return div;
}

/* ── BREADCRUMB ──────────────────────────────────────────────────── */
function updateBreadcrumb() {
  const bc = document.getElementById('breadcrumb');
  let html = `<a onclick="navigateTo(null)">Home</a><span class="bc-sep">›</span>`;

  if (!folderHistory.length && currentFolder === null) {
    html += `<span class="bc-current">Resources</span>`;
  } else {
    html += `<span class="bc-crumb" onclick="navigateTo(null)">Resources</span>`;
    folderHistory.forEach((h, i) => {
      html += `<span class="bc-sep">›</span><span class="bc-crumb" data-idx="${i}">${h.name || 'Folder'}</span>`;
    });
    if (currentFolder) {
      html += `<span class="bc-sep">›</span><span class="bc-current" id="bc-active"></span>`;
    }
  }

  bc.innerHTML = html;

  bc.querySelectorAll('[data-idx]').forEach(el => {
    const idx = parseInt(el.dataset.idx);
    el.addEventListener('click', () => {
      const target  = folderHistory[idx];
      folderHistory = folderHistory.slice(0, idx);
      currentFolder = target.id;
      renderAll(target.name || 'All Resources');
    });
  });

  if (currentFolder) {
    const nameEl   = document.getElementById('bc-active');
    const parentId = DRIVE_DATA[currentFolder]?.parentId ?? null;
    const match    = DRIVE_DATA[parentId]?.items.find(i => i.id === currentFolder);
    if (nameEl) nameEl.textContent = match?.name || 'Folder';
  }
}

/* ── BACK BAR ────────────────────────────────────────────────────── */
function updateBackBar(titleText) {
  const bar = document.getElementById('back-bar');
  document.getElementById('current-folder-name').textContent = titleText || 'Folder';
  bar.classList.toggle('visible', currentFolder !== null);
}

/* ── SIDEBAR FACETS ──────────────────────────────────────────────── */
function updateSidebar(items) {
  buildFacet('facet-subject', items, i => i.meta?.subject || i.subject, 'subject');
  buildFacet('facet-grade',   items, i => i.meta?.grade   || i.grade,   'grade');
  buildFacet('facet-type',    items, i => i.meta?.type,                  'type', true);
}

function buildFacet(elId, items, extract, filterKey, filesOnly = false) {
  const counts = {};
  items.forEach(item => {
    if (filesOnly && !item.file) return;
    const val = extract(item);
    if (val) counts[val] = (counts[val] || 0) + 1;
  });

  const el = document.getElementById(elId);
  el.innerHTML = '';

  if (!Object.keys(counts).length) {
    el.innerHTML = `<li><span style="font-size:12px;color:var(--muted);padding:4px 8px;display:block">None</span></li>`;
    return;
  }

  Object.entries(counts).forEach(([val, count]) => {
    const li  = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'facet-btn' + (activeFilters[filterKey] === val ? ' active' : '');
    btn.innerHTML = `<span>${val}</span><span class="facet-count">${count}</span>`;
    btn.addEventListener('click', () => {
      activeFilters[filterKey] = activeFilters[filterKey] === val ? '' : val;
      const sel = document.getElementById('filter-' + filterKey);
      if (sel) sel.value = activeFilters[filterKey];
      renderAll(document.getElementById('results-title').textContent);
    });
    li.appendChild(btn);
    el.appendChild(li);
  });
}

/* ── EVENT LISTENERS ─────────────────────────────────────────────── */
document.getElementById('btn-search').addEventListener('click', applySearch);
document.getElementById('btn-clear').addEventListener('click', clearSearch);
document.getElementById('btn-back').addEventListener('click', goBack);
document.getElementById('search-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') applySearch();
});

document.getElementById('view-grid').addEventListener('click', function () {
  listMode = false;
  this.classList.add('active');
  document.getElementById('view-list').classList.remove('active');
  renderAll(document.getElementById('results-title').textContent);
});
document.getElementById('view-list').addEventListener('click', function () {
  listMode = true;
  this.classList.add('active');
  document.getElementById('view-grid').classList.remove('active');
  renderAll(document.getElementById('results-title').textContent);
});

/* ── INIT ────────────────────────────────────────────────────────── */
renderAll('All Resources');