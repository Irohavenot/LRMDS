/* =============================================================
   prototype2 — app.js  (v3 – blob cache + paginated DOCX viewer)
   Microsoft Graph API + OneDrive folder browser + search + preview
   ============================================================= */

// ── ① CONFIGURATION ───────────────────────────────────────────
const AZURE_CLIENT_ID      = '39df8466-7cab-47d9-93b8-49760dfc2c0e';
const ONEDRIVE_ROOT_FOLDER = 'deped';
const ONEDRIVE_OWNER_UPN   = '';
// ──────────────────────────────────────────────────────────────

const REDIRECT_URI = window.location.origin + window.location.pathname;
const GRAPH_BASE   = 'https://graph.microsoft.com/v1.0';

// ── MSAL config ───────────────────────────────────────────────
const msalConfig = {
  auth: {
    clientId:    AZURE_CLIENT_ID,
    authority:   'https://login.microsoftonline.com/consumers',
    redirectUri: REDIRECT_URI,
  },
  cache: { cacheLocation: 'sessionStorage', storeAuthStateInCookie: false },
};

const msalInstance = new msal.PublicClientApplication(msalConfig);

const loginRequest = {
  scopes: ['User.Read', 'Files.Read', 'Files.Read.All'],
};

// ── State ─────────────────────────────────────────────────────
let currentItemId    = null;
let folderHistory    = [];
let allFilesCache    = null;
let deepCachePromise = null;
let activeFilters    = { subject: '', grade: '', type: '', q: '' };
let accessToken      = null;
let currentUser      = null;
let isListView       = false;

// ── Blob preview cache (itemId → { blobUrl, arrayBuffer?, docxHtml? })
// Survives open/close of modal so re-opening the same file is instant.
// Cleared when navigating to a different folder.
const previewCache = new Map();
const MAX_CACHE    = 20;

// ── DOM refs ──────────────────────────────────────────────────
const loginScreen    = document.getElementById('login-screen');
const appShell       = document.getElementById('app-shell');
const loginBtn       = document.getElementById('login-btn');
const logoutBtn      = document.getElementById('logout-btn');
const userNameEl     = document.getElementById('user-name');
const userInitialEl  = document.getElementById('user-initial');
const resultsGrid    = document.getElementById('results');
const resultsTitleEl = document.getElementById('results-title');
const resultsMetaEl  = document.getElementById('results-meta');
const breadcrumbEl   = document.getElementById('breadcrumb');
const backBar        = document.getElementById('back-bar');
const backFolderName = document.getElementById('current-folder-name');
const searchInput    = document.getElementById('search-input');
const filterGrade    = document.getElementById('filter-grade');
const filterSubject  = document.getElementById('filter-subject');
const filterType     = document.getElementById('filter-type');
const toastEl        = document.getElementById('toast');
const gridViewBtn    = document.getElementById('btn-grid-view');
const listViewBtn    = document.getElementById('btn-list-view');

// ── MSAL init ─────────────────────────────────────────────────
msalInstance.handleRedirectPromise().then(response => {
  if (response) {
    accessToken = response.accessToken;
    currentUser = response.account;
    msalInstance.setActiveAccount(currentUser);
    showApp();
  } else {
    const accounts = msalInstance.getAllAccounts();
    if (accounts.length > 0) {
      currentUser = accounts[0];
      msalInstance.setActiveAccount(currentUser);
      acquireTokenSilently().then(() => showApp());
    }
  }
}).catch(err => console.error('MSAL redirect error:', err));

async function acquireTokenSilently() {
  try {
    const result = await msalInstance.acquireTokenSilent({ ...loginRequest, account: currentUser });
    accessToken = result.accessToken;
  } catch (e) {
    msalInstance.acquireTokenRedirect(loginRequest);
  }
}

loginBtn.addEventListener('click', () => msalInstance.loginRedirect(loginRequest));
logoutBtn.addEventListener('click', () => msalInstance.logoutRedirect({ postLogoutRedirectUri: REDIRECT_URI }));

function showApp() {
  loginScreen.classList.add('hidden');
  appShell.classList.add('visible');
  const name = currentUser.name || currentUser.username;
  userNameEl.textContent = name;
  userInitialEl.textContent = name.charAt(0).toUpperCase();
  loadFolder(null, ONEDRIVE_ROOT_FOLDER);
}

// ── Graph API helpers ─────────────────────────────────────────
async function graphGet(url) {
  await acquireTokenSilently();
  const res = await fetch(url, { headers: { Authorization: `Bearer ${accessToken}` } });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error?.message || `Graph API error ${res.status}`);
  }
  return res.json();
}

function driveBase() {
  return ONEDRIVE_OWNER_UPN
    ? `${GRAPH_BASE}/users/${encodeURIComponent(ONEDRIVE_OWNER_UPN)}/drive`
    : `${GRAPH_BASE}/me/drive`;
}
function buildChildrenUrl(itemId) {
  if (itemId === null)
    return `${driveBase()}/root:/${encodeURIComponent(ONEDRIVE_ROOT_FOLDER)}:/children?$top=500&$orderby=name`;
  return `${driveBase()}/items/${itemId}/children?$top=500&$orderby=name`;
}
function buildDownloadUrl(itemId) { return `${driveBase()}/items/${itemId}/content`; }

// ── Parse metadata from filename ─────────────────────────────
function parseFileMeta(item) {
  const name  = item.name || '';
  const upper = name.toUpperCase();
  let type = 'Resource';
  if (upper.startsWith('SLM'))         type = 'SLM';
  else if (upper.startsWith('TG'))     type = 'TG';
  else if (upper.startsWith('DLL'))    type = 'DLL';
  else if (upper.startsWith('ASSESS')) type = 'Assessment';
  else if (upper.startsWith('VIDEO'))  type = 'Video';

  const gradeMatch = name.match(/\bG(\d{1,2})\b/i) || name.match(/\b(Kinder)\b/i);
  let grade = '';
  if (gradeMatch) grade = gradeMatch[1] ? `Grade ${gradeMatch[1]}` : 'Kinder';

  const subjectMap = {
    MATH:'Mathematics',MTH:'Mathematics',SCI:'Science',SC:'Science',
    ENG:'English',EN:'English',FIL:'Filipino',AP:'Araling Panlipunan',
    MAPEH:'MAPEH',EPP:'EPP/TLE',TLE:'EPP/TLE',
  };
  let subject = '';
  for (const p of name.split(/[_\-\s]/)) {
    if (subjectMap[p.toUpperCase()]) { subject = subjectMap[p.toUpperCase()]; break; }
  }

  const quarterMatch = name.match(/Q(\d)/i);
  const quarter = quarterMatch ? `Quarter ${quarterMatch[1]}` : '';
  const melcMatch = name.match(/([A-Z]{1,4}\d[A-Z]{1,4}-[IVXa-z]+-[\d.]+)/i);
  const melc  = melcMatch ? melcMatch[1] : '';
  const title = name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ').trim();
  return { type, grade, subject, quarter, melc, title };
}

// ══════════════════════════════════════════════════════════════
//   BLOB CACHE  — fetch once, reuse forever (within session)
// ══════════════════════════════════════════════════════════════
async function getOrFetchBlob(itemId, needArrayBuffer = false) {
  if (previewCache.has(itemId)) {
    const cached = previewCache.get(itemId);
    // Refresh LRU position
    previewCache.delete(itemId);
    previewCache.set(itemId, cached);
    if (needArrayBuffer && !cached.arrayBuffer) {
      const res = await fetch(cached.blobUrl);
      cached.arrayBuffer = await res.arrayBuffer();
    }
    return cached;
  }

  await acquireTokenSilently();
  const res = await fetch(buildDownloadUrl(itemId), {
    headers: { Authorization: `Bearer ${accessToken}` }
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const blob    = await res.blob();
  const blobUrl = URL.createObjectURL(blob);
  let arrayBuffer = null;
  if (needArrayBuffer) arrayBuffer = await blob.arrayBuffer();

  const entry = { blobUrl, arrayBuffer };

  // Evict oldest when over limit
  if (previewCache.size >= MAX_CACHE) {
    const oldestKey = previewCache.keys().next().value;
    URL.revokeObjectURL(previewCache.get(oldestKey).blobUrl);
    previewCache.delete(oldestKey);
  }
  previewCache.set(itemId, entry);
  return entry;
}

function clearPreviewCache() {
  for (const e of previewCache.values()) URL.revokeObjectURL(e.blobUrl);
  previewCache.clear();
}

// ══════════════════════════════════════════════════════════════
//   PREVIEW MODAL
// ══════════════════════════════════════════════════════════════
function getPreviewType(item) {
  const mime = item.file?.mimeType || '';
  const ext  = (item.name.split('.').pop() || '').toLowerCase();
  if (mime.includes('image') || ['jpg','jpeg','png','gif','webp','svg','bmp'].includes(ext)) return 'image';
  if (mime.includes('video') || ['mp4','webm','ogg','mov','avi','mkv'].includes(ext))        return 'video';
  if (mime.includes('audio') || ['mp3','wav','ogg','m4a','flac'].includes(ext))              return 'audio';
  if (mime.includes('pdf')   || ext === 'pdf')                                               return 'pdf';
  if (mime.includes('word')  || ext === 'docx' || ext === 'doc')                             return 'docx';
  if (mime.includes('sheet') || ['xls','xlsx'].includes(ext))                                return 'office-sheet';
  if (mime.includes('presentation') || ['ppt','pptx'].includes(ext))                        return 'office-ppt';
  if (['txt','md','csv'].includes(ext))                                                       return 'text';
  return null;
}

async function openPreview(item) {
  const previewType = getPreviewType(item);
  if (!previewType) { showToast('Preview not available for this file type. Use Open ↗ instead.'); return; }

  let modal = document.getElementById('preview-modal');
  if (!modal) {
    modal = document.createElement('div');
    modal.id = 'preview-modal';
    modal.innerHTML = `
      <div class="preview-backdrop" id="preview-backdrop"></div>
      <div class="preview-panel">
        <div class="preview-header">
          <div class="preview-title-wrap">
            <span class="preview-icon" id="preview-icon"></span>
            <div>
              <div class="preview-filename" id="preview-filename"></div>
              <div class="preview-filemeta" id="preview-filemeta"></div>
            </div>
          </div>
          <div class="preview-actions">
            <span class="preview-cache-badge" id="preview-cache-badge" title="Instant — loaded from cache">⚡ Cached</span>
            <a id="preview-open-btn" class="button ghost small" target="_blank" rel="noopener">Open ↗</a>
            <button class="button primary small" id="preview-dl-btn">⬇ Download</button>
            <button class="preview-close" id="preview-close" title="Close">✕</button>
          </div>
        </div>
        <div class="preview-body" id="preview-body"></div>
      </div>`;
    document.body.appendChild(modal);
    document.getElementById('preview-backdrop').addEventListener('click', closePreview);
    document.getElementById('preview-close').addEventListener('click', closePreview);
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closePreview();
    });
  }

  const meta = item._meta || parseFileMeta(item);
  document.getElementById('preview-icon').textContent     = mimeIcon(item.file?.mimeType);
  document.getElementById('preview-filename').textContent = item.name;
  document.getElementById('preview-filemeta').textContent =
    [meta.grade, meta.subject, meta.quarter, formatSize(item.size)].filter(Boolean).join(' · ');
  document.getElementById('preview-open-btn').href        = item.webUrl;
  document.getElementById('preview-dl-btn').onclick       = () => downloadFile(item.id, item.name);

  const fromCache = previewCache.has(item.id);
  const badge     = document.getElementById('preview-cache-badge');
  badge.classList.toggle('visible', fromCache);

  modal.classList.add('visible');
  document.body.style.overflow = 'hidden';

  const previewBody = document.getElementById('preview-body');

  if (fromCache) {
    // Render immediately — no spinner
    try { await renderPreviewContent(previewBody, item, previewType); }
    catch (e) { showPreviewError(previewBody, item, e); }
    return;
  }

  previewBody.innerHTML = `
    <div class="preview-loading">
      <div class="spinner"></div><span>Loading preview…</span>
    </div>`;

  try { await renderPreviewContent(previewBody, item, previewType); }
  catch (e) { showPreviewError(previewBody, item, e); }
}

async function renderPreviewContent(previewBody, item, previewType) {
  if (previewType === 'image') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    previewBody.innerHTML = `
      <div class="preview-img-wrap">
        <img src="${blobUrl}" alt="${escHtml(item.name)}" class="preview-img"
             onload="this.style.opacity=1" style="opacity:0;transition:opacity .3s"/>
      </div>`;

  } else if (previewType === 'video') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    previewBody.innerHTML = `
      <div class="preview-video-wrap">
        <video controls autoplay class="preview-video" src="${blobUrl}"></video>
      </div>`;

  } else if (previewType === 'audio') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    previewBody.innerHTML = `
      <div class="preview-audio-wrap">
        <div class="preview-audio-icon">🎵</div>
        <div class="preview-audio-name">${escHtml(item.name)}</div>
        <audio controls src="${blobUrl}" class="preview-audio"></audio>
      </div>`;

  } else if (previewType === 'pdf') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    previewBody.innerHTML = `
      <object class="preview-iframe" data="${blobUrl}#toolbar=1&navpanes=0" type="application/pdf">
        <div class="preview-error">
          <div style="font-size:40px">📄</div>
          <strong>PDF could not render in browser</strong>
          <p>Your browser may not support inline PDFs.</p>
          <button class="button primary small" onclick="downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download PDF</button>
        </div>
      </object>`;

  } else if (previewType === 'docx') {
    if (!window.mammoth)
      await loadScript('https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js');

    const entry = await getOrFetchBlob(item.id, true);

    // Cache the mammoth conversion result too
    if (entry.docxHtml === undefined) {
      const result       = await mammoth.convertToHtml({ arrayBuffer: entry.arrayBuffer });
      entry.docxHtml     = result.value;
      entry.docxMessages = result.messages;
    }

    renderDocxViewer(previewBody, entry.docxHtml, entry.docxMessages || [], item);

  } else if (previewType === 'office-sheet' || previewType === 'office-ppt') {
    const ext = (item.name.split('.').pop() || '').toUpperCase();
    previewBody.innerHTML = `
      <div class="preview-error">
        <div style="font-size:48px">${previewType === 'office-sheet' ? '📊' : '📑'}</div>
        <strong>${ext} Preview</strong>
        <p>This format requires Microsoft Office. Download it or open in OneDrive.</p>
        <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:8px">
          <button class="button primary small" onclick="downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download</button>
          <a href="${escHtml(item.webUrl)}" target="_blank" rel="noopener" class="button ghost small">Open in OneDrive ↗</a>
        </div>
      </div>`;

  } else if (previewType === 'text') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    const res  = await fetch(blobUrl);
    const text = await res.text();
    previewBody.innerHTML = `<pre class="preview-text">${escHtml(text)}</pre>`;
  }
}

function showPreviewError(previewBody, item, e) {
  previewBody.innerHTML = `
    <div class="preview-error">
      <div style="font-size:40px">⚠️</div>
      <strong>Preview failed</strong>
      <p>${escHtml(e.message)}</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:8px">
        <button class="button primary small" onclick="downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download</button>
        <a href="${escHtml(item.webUrl)}" target="_blank" rel="noopener" class="button ghost small">Open in OneDrive ↗</a>
      </div>
    </div>`;
}

// ══════════════════════════════════════════════════════════════
//   DOCX PAGINATED VIEWER
// ══════════════════════════════════════════════════════════════
function renderDocxViewer(previewBody, html, messages, item) {
  // Parse mammoth HTML into block nodes
  const tmp   = document.createElement('div');
  tmp.innerHTML = html;
  const nodes = Array.from(tmp.childNodes).filter(n =>
    n.nodeType === Node.ELEMENT_NODE || (n.nodeType === Node.TEXT_NODE && n.textContent.trim())
  );

  // Split nodes across simulated pages (~900 px each)
  const pages   = [];
  let pageNodes = [];
  let pageH     = 0;
  const PAGE_H  = 900;

  function estimateH(node) {
    if (node.nodeType !== Node.ELEMENT_NODE) return 0;
    const tag = node.tagName?.toLowerCase();
    if (!tag) return 0;
    if (tag === 'h1' || tag === 'h2') return 60;
    if (tag === 'h3')                 return 44;
    if (tag === 'img')                return 220;
    if (tag === 'table')              return node.querySelectorAll('tr').length * 28 + 24;
    if (tag === 'ul' || tag === 'ol') return node.querySelectorAll('li').length * 22 + 12;
    const chars = (node.textContent || '').length;
    return Math.max(1, Math.ceil(chars / 85)) * 22 + 10;
  }

  for (const node of nodes) {
    const h = estimateH(node);
    if (pageNodes.length > 0 && pageH + h > PAGE_H) {
      pages.push(pageNodes);
      pageNodes = [];
      pageH = 0;
    }
    pageNodes.push(node);
    pageH += h;
  }
  if (pageNodes.length) pages.push(pageNodes);
  if (!pages.length) pages.push([]); // empty doc

  const total = pages.length;

  // ── Toolbar ────────────────────────────────────────────────
  const toolbar = document.createElement('div');
  toolbar.className = 'docx-toolbar';
  toolbar.innerHTML = `
    <div class="docx-toolbar-left">
      <button class="docx-nav-btn" id="docx-prev" disabled>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M15 18l-6-6 6-6"/></svg>
      </button>
      <span class="docx-page-label">Page <strong id="docx-cur">1</strong> of <strong>${total}</strong></span>
      <button class="docx-nav-btn" id="docx-next" ${total <= 1 ? 'disabled' : ''}>
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M9 18l6-6-6-6"/></svg>
      </button>
    </div>
    <div class="docx-toolbar-center">
      <div class="docx-page-dots" id="docx-dots"></div>
    </div>
    <div class="docx-toolbar-right">
      <button class="docx-zoom-btn" id="docx-zoom-out" title="Zoom out">−</button>
      <span class="docx-zoom-label" id="docx-zoom-label">100%</span>
      <button class="docx-zoom-btn" id="docx-zoom-in" title="Zoom in">+</button>
    </div>`;

  // ── Canvas ─────────────────────────────────────────────────
  const canvas = document.createElement('div');
  canvas.className = 'docx-canvas';

  // Build page elements
  const pageEls = pages.map((nodes, idx) => {
    const wrap = document.createElement('div');
    wrap.className = 'docx-page' + (idx === 0 ? ' active' : '');

    // A4 paper shadow card
    const paper = document.createElement('div');
    paper.className = 'docx-paper';

    // Margin guides (top decorative bar)
    const marginBar = document.createElement('div');
    marginBar.className = 'docx-margin-bar';
    marginBar.innerHTML = `
      <div class="docx-margin-label">◀ 1 in margin</div>
      <div class="docx-margin-divider"></div>
      <div class="docx-margin-label">1 in margin ▶</div>`;
    paper.appendChild(marginBar);

    // Content body
    const body = document.createElement('div');
    body.className = 'docx-paper-body';
    for (const n of nodes) body.appendChild(n.cloneNode(true));
    paper.appendChild(body);

    // Page number footer
    const footer = document.createElement('div');
    footer.className = 'docx-page-num';
    footer.innerHTML = `<span>— ${idx + 1} —</span>`;
    paper.appendChild(footer);

    wrap.appendChild(paper);
    canvas.appendChild(wrap);
    return wrap;
  });

  // Dots
  const dotsEl = toolbar.querySelector('#docx-dots');
  const dots = pages.map((_, idx) => {
    const d = document.createElement('button');
    d.className = 'docx-dot' + (idx === 0 ? ' active' : '');
    d.title = `Page ${idx + 1}`;
    d.addEventListener('click', () => goToPage(idx));
    dotsEl.appendChild(d);
    return d;
  });

  // ── State & navigation ─────────────────────────────────────
  let currentPage = 0;
  let zoom = 100;

  function goToPage(idx) {
    if (idx < 0 || idx >= total) return;
    pageEls[currentPage].classList.remove('active');
    dots[currentPage].classList.remove('active');
    currentPage = idx;
    pageEls[currentPage].classList.add('active');
    dots[currentPage].classList.add('active');
    toolbar.querySelector('#docx-cur').textContent = currentPage + 1;
    toolbar.querySelector('#docx-prev').disabled = currentPage === 0;
    toolbar.querySelector('#docx-next').disabled = currentPage === total - 1;
    canvas.scrollTop = 0;
  }

  function setZoom(z) {
    zoom = Math.min(200, Math.max(50, z));
    toolbar.querySelector('#docx-zoom-label').textContent = zoom + '%';
    pageEls.forEach(p => {
      const paper = p.querySelector('.docx-paper');
      paper.style.transform       = `scale(${zoom / 100})`;
      paper.style.transformOrigin = 'top center';
      // Adjust wrap height so scrollable area is correct
      const scaled = 1123 * (zoom / 100); // A4 height px
      p.style.minHeight = scaled + 'px';
    });
  }

  toolbar.querySelector('#docx-prev').addEventListener('click', () => goToPage(currentPage - 1));
  toolbar.querySelector('#docx-next').addEventListener('click', () => goToPage(currentPage + 1));
  toolbar.querySelector('#docx-zoom-out').addEventListener('click', () => setZoom(zoom - 10));
  toolbar.querySelector('#docx-zoom-in').addEventListener('click',  () => setZoom(zoom + 10));

  // Keyboard nav (only while modal is open; avoid hijacking Escape)
  const keyNav = (e) => {
    const modal = document.getElementById('preview-modal');
    if (!modal?.classList.contains('visible')) return;
    if (e.target.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); goToPage(currentPage + 1); }
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); goToPage(currentPage - 1); }
  };
  document.addEventListener('keydown', keyNav);
  canvas._keyNav = keyNav;

  // ── Assemble ───────────────────────────────────────────────
  previewBody.innerHTML = '';
  previewBody.classList.add('docx-mode');
  previewBody.appendChild(toolbar);
  previewBody.appendChild(canvas);

  if (messages.length) {
    const warn = document.createElement('div');
    warn.className = 'preview-docx-warn';
    warn.textContent = `⚠ ${messages.length} formatting notice(s) — some styles may differ from the original.`;
    previewBody.appendChild(warn);
  }
}

function closePreview() {
  const modal = document.getElementById('preview-modal');
  if (!modal) return;
  modal.classList.remove('visible');
  document.body.style.overflow = '';
  modal.querySelectorAll('video, audio').forEach(el => el.pause());

  // Remove docx keyboard nav listener
  const canvas = modal.querySelector('.docx-canvas');
  if (canvas?._keyNav) {
    document.removeEventListener('keydown', canvas._keyNav);
    canvas._keyNav = null;
  }

  const body = document.getElementById('preview-body');
  if (body) {
    body.classList.remove('docx-mode');
    setTimeout(() => { if (body) body.innerHTML = ''; }, 300);
  }
}

// ── Script loader ─────────────────────────────────────────────
function loadScript(src) {
  return new Promise((resolve, reject) => {
    if (document.querySelector(`script[src="${src}"]`)) { resolve(); return; }
    const s = document.createElement('script');
    s.src = src; s.onload = resolve;
    s.onerror = () => reject(new Error(`Failed to load: ${src}`));
    document.head.appendChild(s);
  });
}

// ── Load a folder ─────────────────────────────────────────────
async function loadFolder(itemId, folderName) {
  if (currentItemId !== itemId) clearPreviewCache();
  currentItemId    = itemId;
  allFilesCache    = null;
  deepCachePromise = null;

  renderLoading();
  updateBreadcrumb(folderName);
  updateBackBar(folderName);

  try {
    const data  = await graphGet(buildChildrenUrl(itemId));
    const items = data.value || [];
    renderItems(items, folderName || ONEDRIVE_ROOT_FOLDER);
    startBackgroundDeepCache(itemId);
  } catch (e) {
    renderError(e.message);
  }
}

// ── Background deep-cache ─────────────────────────────────────
function startBackgroundDeepCache(itemId) {
  if (allFilesCache) return;
  deepCachePromise = collectAllFilesDeepParallel(itemId)
    .then(files => {
      allFilesCache = files;
      console.log(`[LRMDS] Cache ready: ${files.length} files indexed.`);
    })
    .catch(err => { console.warn('[LRMDS] Cache failed:', err.message); deepCachePromise = null; });
}

async function collectAllFilesDeepParallel(itemId) {
  const data    = await graphGet(buildChildrenUrl(itemId));
  const items   = data.value || [];
  const files   = items.filter(i => i.file);
  const folders = items.filter(i => i.folder);
  const sub     = await Promise.all(folders.map(f => collectAllFilesDeepParallel(f.id)));
  return files.concat(...sub);
}

// ── Filter / search ───────────────────────────────────────────
function itemMatchesFilters(item) {
  const { q, grade, subject, type } = activeFilters;
  if (!q && !grade && !subject && !type) return true;
  const meta = item._meta || parseFileMeta(item);
  item._meta = meta;
  const s = (meta.title + ' ' + item.name + ' ' + meta.melc).toLowerCase();
  if (q       && !s.includes(q.toLowerCase()))  return false;
  if (grade   && meta.grade   !== grade)         return false;
  if (subject && meta.subject !== subject)       return false;
  if (type    && meta.type    !== type)          return false;
  return true;
}

async function applySearch() {
  activeFilters.q       = searchInput.value.trim();
  activeFilters.grade   = filterGrade.value;
  activeFilters.subject = filterSubject.value;
  activeFilters.type    = filterType.value;

  const isFiltering = activeFilters.q || activeFilters.grade || activeFilters.subject || activeFilters.type;
  if (!isFiltering) { loadFolder(currentItemId, resultsTitleEl.textContent); return; }

  if (allFilesCache) { renderItems(allFilesCache.filter(itemMatchesFilters), 'Search Results', true); return; }

  renderLoading('Searching all folders…');
  try {
    if (deepCachePromise) await deepCachePromise;
    else allFilesCache = await collectAllFilesDeepParallel(null);
    renderItems(allFilesCache.filter(itemMatchesFilters), 'Search Results', true);
  } catch (e) { renderError(e.message); }
}

function clearSearch() {
  searchInput.value = filterGrade.value = filterSubject.value = filterType.value = '';
  activeFilters = { subject:'', grade:'', type:'', q:'' };
  currentItemId = null; folderHistory = []; allFilesCache = null; deepCachePromise = null;
  loadFolder(null, ONEDRIVE_ROOT_FOLDER);
}

searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') applySearch(); });

// ── Navigation ────────────────────────────────────────────────
function navigateTo(itemId, folderName) {
  folderHistory.push({ id: currentItemId, name: resultsTitleEl.textContent });
  loadFolder(itemId, folderName);
}
function goBack() {
  if (!folderHistory.length) return;
  const prev = folderHistory.pop();
  loadFolder(prev.id, prev.name);
}

// ── View toggle ───────────────────────────────────────────────
gridViewBtn.addEventListener('click', () => {
  isListView = false; resultsGrid.classList.remove('list-view');
  gridViewBtn.classList.add('active'); listViewBtn.classList.remove('active');
});
listViewBtn.addEventListener('click', () => {
  isListView = true; resultsGrid.classList.add('list-view');
  listViewBtn.classList.add('active'); gridViewBtn.classList.remove('active');
});

// ── Render ────────────────────────────────────────────────────
function renderLoading(msg = 'Loading from OneDrive…') {
  resultsGrid.innerHTML = `<div class="loading-state"><div class="spinner"></div><span>${msg}</span></div>`;
  resultsMetaEl.textContent = '';
}
function renderError(msg) {
  resultsGrid.innerHTML = `
    <div class="error-banner">⚠️ <span><strong>Could not load files.</strong> ${msg}<br>
    Check your Azure App ID and that the folder exists in OneDrive.</span></div>`;
}

function renderItems(items, titleText, isSearch = false) {
  resultsTitleEl.textContent = titleText || ONEDRIVE_ROOT_FOLDER;
  resultsGrid.innerHTML = '';
  const folders = items.filter(i => i.folder);
  const files   = items.filter(i => i.file);

  if (!items.length) {
    resultsGrid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <strong>${isSearch ? 'No matching resources found' : 'This folder is empty'}</strong>
        <p>${isSearch ? 'Try different keywords or filters.' : 'No files or subfolders here yet.'}</p>
      </div>`;
    resultsMetaEl.textContent = '0 items';
    updateSidebar([]);
    return;
  }

  resultsMetaEl.textContent = isSearch
    ? `${files.length} file${files.length !== 1 ? 's' : ''} matched across all folders`
    : `${items.length} item${items.length !== 1 ? 's' : ''} · ${folders.length} folder${folders.length !== 1 ? 's' : ''}, ${files.length} file${files.length !== 1 ? 's' : ''}`;

  for (const item of items) resultsGrid.appendChild(item.folder ? buildFolderCard(item) : buildFileCard(item));
  updateSidebar(files);
}

function buildFolderCard(item) {
  const div = document.createElement('div');
  div.className = 'folder-card';
  div.setAttribute('role', 'button'); div.setAttribute('tabindex', '0');
  div.innerHTML = `
    <div class="folder-icon-wrap">📁</div>
    <div class="folder-name">${escHtml(item.name)}</div>
    <div class="folder-meta"><span style="margin-left:auto;">${item.folder.childCount ?? '?'} items ›</span></div>`;
  const open = () => navigateTo(item.id, item.name);
  div.addEventListener('click', open);
  div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') open(); });
  return div;
}

function buildFileCard(item) {
  item._meta = item._meta || parseFileMeta(item);
  const meta       = item._meta;
  const ext        = (item.name.split('.').pop() || '').toUpperCase();
  const icon       = mimeIcon(item.file?.mimeType);
  const size       = formatSize(item.size);
  const canPreview = !!getPreviewType(item);

  const div = document.createElement('div');
  div.className = 'result-card' + (canPreview ? ' previewable' : '');
  div.innerHTML = `
    <div class="thumb-wrap">
      <span style="font-size:28px">${icon}</span>
      <span class="type-badge">${escHtml(ext)}</span>
      ${canPreview ? `<div class="preview-hint">👁 Preview</div>` : ''}
    </div>
    <div class="card-body">
      <div class="tag-row">
        ${meta.grade   ? `<span class="tag">${escHtml(meta.grade)}</span>` : ''}
        ${meta.subject ? `<span class="tag secondary">${escHtml(meta.subject)}</span>` : ''}
        ${meta.type    ? `<span class="tag secondary">${escHtml(meta.type)}</span>` : ''}
      </div>
      <div class="card-title">${escHtml(meta.title)}</div>
      <div class="card-detail">
        ${meta.melc    ? `<code>${escHtml(meta.melc)}</code>` : ''}
        ${meta.quarter ? `<span class="sep">·</span><span>${escHtml(meta.quarter)}</span>` : ''}
        ${size         ? `<span class="sep">·</span><span>${size}</span>` : ''}
      </div>
      <div class="card-actions">
        ${canPreview ? `<button class="button secondary small preview-btn">👁 Preview</button>` : ''}
        <a href="${escHtml(item.webUrl)}" target="_blank" rel="noopener"
           class="button ghost small" onclick="event.stopPropagation()">Open ↗</a>
        <button class="button primary small"
          onclick="event.stopPropagation();downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download</button>
      </div>
    </div>`;

  if (canPreview) {
    const openPrev = e => { e.stopPropagation(); openPreview(item); };
    div.querySelector('.preview-btn').addEventListener('click', openPrev);
    div.querySelector('.thumb-wrap').addEventListener('click', openPrev);
  }
  return div;
}

// ── Download ──────────────────────────────────────────────────
async function downloadFile(itemId, fileName) {
  showToast(`Preparing download: ${fileName}`);
  try {
    const { blobUrl } = await getOrFetchBlob(itemId);
    const a = document.createElement('a');
    a.href = blobUrl; a.download = fileName; a.click();
    showToast(`Downloaded: ${fileName}`);
  } catch (e) { showToast(`Download failed: ${e.message}`); }
}

// ── Breadcrumb ────────────────────────────────────────────────
function updateBreadcrumb(currentName) {
  let html = `<a onclick="loadFolder(null,'${ONEDRIVE_ROOT_FOLDER}')">Home</a><span class="bc-sep">›</span>`;
  if (!folderHistory.length && currentItemId === null) {
    html += `<span class="bc-current">${escHtml(ONEDRIVE_ROOT_FOLDER)}</span>`;
  } else {
    html += `<span class="bc-crumb" onclick="clearSearch()">${escHtml(ONEDRIVE_ROOT_FOLDER)}</span>`;
    folderHistory.forEach((h, idx) => {
      html += `<span class="bc-sep">›</span><span class="bc-crumb" data-idx="${idx}">${escHtml(h.name)}</span>`;
    });
    if (currentName) html += `<span class="bc-sep">›</span><span class="bc-current">${escHtml(currentName)}</span>`;
  }
  breadcrumbEl.innerHTML = html;
  breadcrumbEl.querySelectorAll('[data-idx]').forEach(el => {
    const idx = parseInt(el.dataset.idx);
    el.addEventListener('click', () => {
      const target = folderHistory[idx];
      folderHistory = folderHistory.slice(0, idx);
      loadFolder(target.id, target.name);
    });
  });
}

function updateBackBar(folderName) {
  if (currentItemId !== null || folderHistory.length) {
    backBar.classList.add('visible');
    backFolderName.textContent = folderName || 'Folder';
  } else {
    backBar.classList.remove('visible');
  }
}

// ── Sidebar ───────────────────────────────────────────────────
function updateSidebar(files) {
  buildFacet('facet-subject', files, f => (f._meta || parseFileMeta(f)).subject, 'subject');
  buildFacet('facet-grade',   files, f => (f._meta || parseFileMeta(f)).grade,   'grade');
  buildFacet('facet-type',    files, f => (f._meta || parseFileMeta(f)).type,    'type');
}
function buildFacet(elId, items, extract, filterKey) {
  const counts = {};
  for (const item of items) { const v = extract(item); if (v) counts[v] = (counts[v] || 0) + 1; }
  const el = document.getElementById(elId);
  el.innerHTML = '';
  if (!Object.keys(counts).length) {
    el.innerHTML = `<li><span style="font-size:12px;color:var(--muted);padding:4px 8px;display:block">—</span></li>`;
    return;
  }
  for (const [val, count] of Object.entries(counts).sort()) {
    const li = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'facet-btn' + (activeFilters[filterKey] === val ? ' active' : '');
    btn.innerHTML = `<span>${escHtml(val)}</span><span class="facet-count">${count}</span>`;
    btn.addEventListener('click', () => {
      activeFilters[filterKey] = activeFilters[filterKey] === val ? '' : val;
      const sel = document.getElementById('filter-' + filterKey);
      if (sel) sel.value = activeFilters[filterKey];
      applySearch();
    });
    li.appendChild(btn); el.appendChild(li);
  }
}

// ── Utility ───────────────────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function mimeIcon(mime) {
  if (!mime) return '📎';
  if (mime.includes('pdf'))          return '📄';
  if (mime.includes('video'))        return '🎬';
  if (mime.includes('word'))         return '📝';
  if (mime.includes('sheet'))        return '📊';
  if (mime.includes('presentation')) return '📑';
  if (mime.includes('image'))        return '🖼️';
  if (mime.includes('audio'))        return '🎵';
  return '📎';
}
function formatSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1048576) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / 1048576).toFixed(1) + ' MB';
}
function showToast(msg) {
  toastEl.textContent = msg;
  toastEl.classList.add('show');
  setTimeout(() => toastEl.classList.remove('show'), 2800);
}