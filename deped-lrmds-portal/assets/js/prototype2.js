/* =============================================================
   prototype2 — app.js  (v4 – Shares API so any MS account works)
   Microsoft Graph API + OneDrive folder browser + search + preview
   ============================================================= */

// ── ① CONFIGURATION ───────────────────────────────────────────
const AZURE_CLIENT_ID      = '39df8466-7cab-47d9-93b8-49760dfc2c0e';
const ONEDRIVE_ROOT_FOLDER = 'deped';
const ONEDRIVE_OWNER_UPN   = 'depedlrmdsonedrive@gmail.com';

// ► Paste your OneDrive share link for the "deped" folder here.
//   In OneDrive: right-click the deped folder → Share →
//   "Anyone with the link" → Copy link  (e.g. https://1drv.ms/f/s!Abc…)
const SHARED_FOLDER_URL = 'https://1drv.ms/f/c/08ca0a09e3f0f5ec/IgDZ4Ku9gqVJS5pwdr72dFqeAb5y0yQuHG3h8Bhva8UVFKs?e=itEVtt';
// ──────────────────────────────────────────────────────────────

const REDIRECT_URI = window.location.origin + window.location.pathname;
const GRAPH_BASE   = 'https://graph.microsoft.com/v1.0';

// ── MSAL config ───────────────────────────────────────────────
const msalConfig = {
  auth: {
    clientId:    AZURE_CLIENT_ID,
    authority:   'https://login.microsoftonline.com/common',
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
let allItemsCache    = null;
let deepCachePromise = null;
let activeFilters    = { subject: '', grade: '', type: '', q: '' };
let accessToken      = null;
let currentUser      = null;
let isListView       = false;

// Cached shared-folder root (driveId + itemId), resolved once on first use
let sharedRootCache  = null;

// ── Blob preview cache ────────────────────────────────────────
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

// ── Shares API helpers ────────────────────────────────────────
// Encodes a share URL into the base64url format Graph requires.
function encodeShareUrl(url) {
  // Graph requires base64url of the raw URL, properly encoded
  const b64 = btoa(unescape(encodeURIComponent(url)))
    .replace(/=/g, '')
    .replace(/\+/g, '-')
    .replace(/\//g, '_');
  return 'u!' + b64;
}

// Resolves the shared folder once, then caches driveId + itemId.
// This is the key fix: we read files via the SHARED item, not the
// owner's personal /users/{upn}/drive — so any signed-in MS account works.
async function getSharedRoot() {
  if (sharedRootCache) return sharedRootCache;

  if (!SHARED_FOLDER_URL || SHARED_FOLDER_URL === 'PASTE_YOUR_SHARE_LINK_HERE') {
    throw new Error(
      'No share link configured. Paste your OneDrive "Anyone" link into SHARED_FOLDER_URL in prototype2.js.'
    );
  }

  const encoded = encodeShareUrl(SHARED_FOLDER_URL);
  const data    = await graphGet(`${GRAPH_BASE}/shares/${encoded}/driveItem`);
// AFTER — handles both same-tenant and cross-tenant
    sharedRootCache = {
      driveId: (data.remoteItem?.parentReference?.driveId) || data.parentReference?.driveId,
      itemId:  data.remoteItem?.id || data.id,
    };
  return sharedRootCache;
}

// Builds the children URL using the shared drive context.
// itemId === null  →  list the shared root folder itself
// itemId !== null  →  list a subfolder by its Graph item ID
async function buildChildrenUrl(itemId) {
  const root = await getSharedRoot();
  const id   = itemId === null ? root.itemId : itemId;
  return `${GRAPH_BASE}/drives/${root.driveId}/items/${id}/children?$top=500&$orderby=name`;
}

// Downloads a file using the same shared drive context.
function buildDownloadUrl(itemId) {
  // We can't use a simple async here (called from sync contexts),
  // so we rely on sharedRootCache being already populated by the
  // time any file card download is triggered.
  if (sharedRootCache) {
    return `${GRAPH_BASE}/drives/${sharedRootCache.driveId}/items/${itemId}/content`;
  }
  // Fallback: encode the share link directly for this item
  const encoded = encodeShareUrl(SHARED_FOLDER_URL);
  return `${GRAPH_BASE}/shares/${encoded}/driveItem/children/${itemId}/content`;
}

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
  // Helper: fire file_view only on first successful load per item per session.
  // Called at the END of each branch, after the blob/content is actually ready.
  function _trackView() {
    if (!_viewTracked.has(item.id)) {
      _viewTracked.add(item.id);
      _track('file_view', {
        item_id:   item.id,
        item_name: item.name,
        item_type: _guessType(item.name),
        file_ext:  _fileExt(item.name),
      });
    }
  }

  if (previewType === 'image') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    // Track after blob is fetched; actual render is near-instant from here
    _trackView();
    previewBody.innerHTML = `
      <div class="preview-img-wrap">
        <img src="${blobUrl}" alt="${escHtml(item.name)}" class="preview-img"
             onload="this.style.opacity=1" style="opacity:0;transition:opacity .3s"/>
      </div>`;

  } else if (previewType === 'video') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    _trackView();
    previewBody.innerHTML = `
      <div class="preview-video-wrap">
        <video controls autoplay class="preview-video" src="${blobUrl}"></video>
      </div>`;

  } else if (previewType === 'audio') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    _trackView();
    previewBody.innerHTML = `
      <div class="preview-audio-wrap">
        <div class="preview-audio-icon">🎵</div>
        <div class="preview-audio-name">${escHtml(item.name)}</div>
        <audio controls src="${blobUrl}" class="preview-audio"></audio>
      </div>`;

  } else if (previewType === 'pdf') {
    const { blobUrl } = await getOrFetchBlob(item.id);
    _trackView();
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

    if (entry.docxHtml === undefined) {
      const result       = await mammoth.convertToHtml({ arrayBuffer: entry.arrayBuffer });
      entry.docxHtml     = result.value;
      entry.docxMessages = result.messages;
    }

    _trackView(); // blob converted and HTML ready
    renderDocxViewer(previewBody, entry.docxHtml, entry.docxMessages || [], item);

  } else if (previewType === 'office-sheet' || previewType === 'office-ppt') {
    // No blob fetch for office formats — track when the error/fallback UI shows
    _trackView();
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
    _trackView(); // full text read into memory
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
  const tmp   = document.createElement('div');
  tmp.innerHTML = html;
  const nodes = Array.from(tmp.childNodes).filter(n =>
    n.nodeType === Node.ELEMENT_NODE || (n.nodeType === Node.TEXT_NODE && n.textContent.trim())
  );

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
  if (!pages.length) pages.push([]);

  const total = pages.length;

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

  const canvas = document.createElement('div');
  canvas.className = 'docx-canvas';

  const pageEls = pages.map((nodes, idx) => {
    const wrap = document.createElement('div');
    wrap.className = 'docx-page' + (idx === 0 ? ' active' : '');

    const paper = document.createElement('div');
    paper.className = 'docx-paper';

    const marginBar = document.createElement('div');
    marginBar.className = 'docx-margin-bar';
    marginBar.innerHTML = `
      <div class="docx-margin-label">◀ 1 in margin</div>
      <div class="docx-margin-divider"></div>
      <div class="docx-margin-label">1 in margin ▶</div>`;
    paper.appendChild(marginBar);

    const body = document.createElement('div');
    body.className = 'docx-paper-body';
    for (const n of nodes) body.appendChild(n.cloneNode(true));
    paper.appendChild(body);

    const footer = document.createElement('div');
    footer.className = 'docx-page-num';
    footer.innerHTML = `<span>— ${idx + 1} —</span>`;
    paper.appendChild(footer);

    wrap.appendChild(paper);
    canvas.appendChild(wrap);
    return wrap;
  });

  const dotsEl = toolbar.querySelector('#docx-dots');
  const dots = pages.map((_, idx) => {
    const d = document.createElement('button');
    d.className = 'docx-dot' + (idx === 0 ? ' active' : '');
    d.title = `Page ${idx + 1}`;
    d.addEventListener('click', () => goToPage(idx));
    dotsEl.appendChild(d);
    return d;
  });

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
      const scaled = 1123 * (zoom / 100);
      p.style.minHeight = scaled + 'px';
    });
  }

  toolbar.querySelector('#docx-prev').addEventListener('click', () => goToPage(currentPage - 1));
  toolbar.querySelector('#docx-next').addEventListener('click', () => goToPage(currentPage + 1));
  toolbar.querySelector('#docx-zoom-out').addEventListener('click', () => setZoom(zoom - 10));
  toolbar.querySelector('#docx-zoom-in').addEventListener('click',  () => setZoom(zoom + 10));

  const keyNav = (e) => {
    const modal = document.getElementById('preview-modal');
    if (!modal?.classList.contains('visible')) return;
    if (e.target.tagName === 'INPUT') return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); goToPage(currentPage + 1); }
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   { e.preventDefault(); goToPage(currentPage - 1); }
  };
  document.addEventListener('keydown', keyNav);
  canvas._keyNav = keyNav;

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
  allItemsCache    = null;
  deepCachePromise = null;

  renderLoading();
  updateBreadcrumb(folderName);
  updateBackBar(folderName);

  try {
    const url  = await buildChildrenUrl(itemId);   // ← now awaited (async)
    const data = await graphGet(url);
    const items = data.value || [];
    renderItems(items, folderName || ONEDRIVE_ROOT_FOLDER);
    startBackgroundDeepCache(itemId);
  } catch (e) {
    renderError(e.message);
  }
}

// ── Background deep-cache ─────────────────────────────────────
function startBackgroundDeepCache(itemId) {
  if (allItemsCache) return;
  deepCachePromise = collectAllItemsDeepParallel(itemId)
    .then(items => {
      allItemsCache = items;
      const fc = items.filter(i => i.file).length;
      const dc = items.filter(i => i.folder).length;
      console.log(`[LRMDS] Cache ready: ${fc} files + ${dc} folders indexed.`);
    })
    .catch(err => { console.warn('[LRMDS] Cache failed:', err.message); deepCachePromise = null; });
}

async function collectAllItemsDeepParallel(itemId, _pathSegments) {
  const pathSegments = _pathSegments || [];
  const url    = await buildChildrenUrl(itemId);
  const data   = await graphGet(url);
  const items  = data.value || [];
  const files  = items.filter(i => i.file);
  const folders = items.filter(i => i.folder);

  files.forEach(f => {
    f._folderPath     = pathSegments.length ? pathSegments : [ONEDRIVE_ROOT_FOLDER];
    f._folderPathStr  = f._folderPath.join(' › ');
    f._parentFolderId = itemId;
  });

  folders.forEach(d => {
    d._parentPath    = pathSegments.length ? pathSegments : [ONEDRIVE_ROOT_FOLDER];
    d._parentPathStr = d._parentPath.join(' › ');
    d._parentId      = itemId;
    d._fullPath      = [...d._parentPath, d.name];
    d._fullPathStr   = d._fullPath.join(' › ');
  });

  const sub = await Promise.all(
    folders.map(f => collectAllItemsDeepParallel(f.id, [...pathSegments, f.name]))
  );
  return files.concat(folders, ...sub);
}

// ── Filter / search ───────────────────────────────────────────
function itemMatchesFilters(item) {
  const { q, grade, subject, type } = activeFilters;
  if (!q && !grade && !subject && !type) return true;

  if (item.folder) {
    if (!q) return false;
    return item.name.toLowerCase().includes(q.toLowerCase());
  }

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

  if (allItemsCache) { renderItems(allItemsCache.filter(itemMatchesFilters), 'Search Results', true); return; }

  renderLoading('Searching all folders…');
  try {
    if (deepCachePromise) await deepCachePromise;
    else allItemsCache = await collectAllItemsDeepParallel(null);
    renderItems(allItemsCache.filter(itemMatchesFilters), 'Search Results', true);
  } catch (e) { renderError(e.message); }
}

function clearSearch() {
  searchInput.value = filterGrade.value = filterSubject.value = filterType.value = '';
  activeFilters = { subject:'', grade:'', type:'', q:'' };
  currentItemId = null; folderHistory = []; allItemsCache = null; deepCachePromise = null;
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

  if (isSearch) {
    const foldersHit = folders.length;
    const filesHit   = files.length;
    const parts = [];
    if (foldersHit) parts.push(`${foldersHit} folder${foldersHit !== 1 ? 's' : ''}`);
    if (filesHit)   parts.push(`${filesHit} file${filesHit !== 1 ? 's' : ''}`);
    resultsMetaEl.textContent = parts.join(' + ') + ' matched';
  } else {
    resultsMetaEl.textContent =
      `${items.length} item${items.length !== 1 ? 's' : ''} · ${folders.length} folder${folders.length !== 1 ? 's' : ''}, ${files.length} file${files.length !== 1 ? 's' : ''}`;
  }

  if (isSearch) {
    if (folders.length) {
      const folderSectionLabel = document.createElement('div');
      folderSectionLabel.className = 'search-section-label';
      folderSectionLabel.innerHTML = `
        <span class="ssl-icon">📁</span>
        Folders
        <span class="ssl-count">${folders.length}</span>`;
      resultsGrid.appendChild(folderSectionLabel);

      for (const folder of folders) {
        resultsGrid.appendChild(buildFolderCard(folder, true));
      }
    }

    if (files.length) {
      const fileSectionLabel = document.createElement('div');
      fileSectionLabel.className = 'search-section-label';
      fileSectionLabel.innerHTML = `
        <span class="ssl-icon">📄</span>
        Files
        <span class="ssl-count">${files.length}</span>`;
      resultsGrid.appendChild(fileSectionLabel);

      const groups = new Map();
      for (const item of files) {
        const key = item._folderPathStr || ONEDRIVE_ROOT_FOLDER;
        if (!groups.has(key)) {
          groups.set(key, {
            pathArr:  item._folderPath  || [ONEDRIVE_ROOT_FOLDER],
            parentId: item._parentFolderId || null,
            files:    []
          });
        }
        groups.get(key).files.push(item);
      }

      for (const [, group] of groups) {
        const header = document.createElement('div');
        header.className = 'search-folder-group-header';
        const folderName = group.pathArr[group.pathArr.length - 1];
        const pathStr    = group.pathArr.join(' › ');
        header.innerHTML = `
          <div class="sfg-left">
            <span class="sfg-icon">📁</span>
            <div class="sfg-path-wrap">
              <span class="sfg-folder-name">${escHtml(folderName)}</span>
              <span class="sfg-path-trail">${escHtml(pathStr)}</span>
            </div>
            <span class="sfg-count">${group.files.length} file${group.files.length !== 1 ? 's' : ''}</span>
          </div>
          <button class="button ghost small sfg-open-btn" data-id="${escHtml(group.parentId || '')}"
                  data-name="${escHtml(folderName)}">Open folder ›</button>`;
        header.querySelector('.sfg-open-btn').addEventListener('click', e => {
          const btn  = e.currentTarget;
          const id   = btn.dataset.id   || null;
          const name = btn.dataset.name || 'Folder';
          folderHistory.push({ id: currentItemId, name: resultsTitleEl.textContent });
          loadFolder(id || null, name);
        });
        resultsGrid.appendChild(header);
        for (const file of group.files) resultsGrid.appendChild(buildFileCard(file, true));
      }
    }

  } else {
    for (const item of items) {
      resultsGrid.appendChild(item.folder ? buildFolderCard(item, false) : buildFileCard(item, false));
    }
  }
  updateSidebar(files);
}

function buildFolderCard(item, isSearch = false) {
  const div = document.createElement('div');
  div.className = 'folder-card';
  div.setAttribute('role', 'button'); div.setAttribute('tabindex', '0');

  const pathTrailHtml = (isSearch && item._parentPathStr)
    ? `<div class="folder-path-trail" title="Inside: ${escHtml(item._parentPathStr)}">
         <span>📍</span><span>${escHtml(item._parentPathStr)}</span>
       </div>`
    : '';

  div.innerHTML = `
    <div class="folder-icon-wrap">📁</div>
    <div class="folder-name">${escHtml(item.name)}</div>
    ${pathTrailHtml}
    <div class="folder-meta"><span style="margin-left:auto;">${item.folder.childCount ?? '?'} items ›</span></div>`;
  const open = () => navigateTo(item.id, item.name);
  div.addEventListener('click', open);
  div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') open(); });
  return div;
}

function buildFileCard(item, isSearch = false) {
  item._meta = item._meta || parseFileMeta(item);
  const meta       = item._meta;
  const ext        = (item.name.split('.').pop() || '').toUpperCase();
  const icon       = mimeIcon(item.file?.mimeType);
  const size       = formatSize(item.size);
  const canPreview = !!getPreviewType(item);

  let locationHtml = '';
  if (isSearch && item._folderPath && item._folderPath.length) {
    const trail = item._folderPath.join(' › ');
    locationHtml = `
      <div class="card-location" title="Located in: ${escHtml(trail)}">
        <span class="card-location-icon">📁</span>
        <span class="card-location-trail">${escHtml(trail)}</span>
      </div>`;
  }

  const div = document.createElement('div');
  div.className = 'result-card' + (canPreview ? ' previewable' : '');
  div.innerHTML = `
    <div class="thumb-wrap">
      <span style="font-size:28px">${icon}</span>
      <span class="type-badge">${escHtml(ext)}</span>
      ${canPreview ? `<div class="preview-hint">👁 Preview</div>` : ''}
    </div>
    <div class="card-body">
      ${locationHtml}
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
// Guards: track each event only once per item per session
const _viewTracked = new Set();   // file_view  — fires after content loads
// Guard: track only once per item, even if the user clicks Download
// from both the card AND the preview modal in the same session.
const _dlTracked = new Set();

async function downloadFile(itemId, fileName) {
  showToast(`Preparing download: ${fileName}`);
  try {
    // Wait until the blob is fully fetched — THEN trigger the save
    const { blobUrl } = await getOrFetchBlob(itemId);
    const a = document.createElement('a');
    a.href = blobUrl; a.download = fileName;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    showToast(`Downloaded: ${fileName}`);

    // Track only after the browser has actually initiated the save,
    // and only once per file per session (prevents card + modal double-fire).
    if (!_dlTracked.has(itemId)) {
      _dlTracked.add(itemId);
      _track('file_download', {
        item_id:   itemId,
        item_name: fileName,
        item_type: _guessType(fileName),
        file_ext:  _fileExt(fileName),
      });
    }
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
// ══════════════════════════════════════════════════════════════
//   ANALYTICS — fires events to tracker.php
//   All events include the real signed-in user (name + OID).
// ══════════════════════════════════════════════════════════════

const _TRACKER_URL = (function () {
  const loc = window.location.pathname;
  const dir = loc.substring(0, loc.lastIndexOf('/') + 1);
  return dir + 'tracker.php';
})();

const _SESSION_ID = (function () {
  let id = sessionStorage.getItem('lrmds_sid');
  if (!id) { id = crypto.randomUUID(); sessionStorage.setItem('lrmds_sid', id); }
  return id;
})();

function _guessType(name) {
  const u = (name || '').toUpperCase();
  if (u.startsWith('SLM'))    return 'SLM';
  if (u.startsWith('TG'))     return 'TG';
  if (u.startsWith('DLL'))    return 'DLL';
  if (u.startsWith('ASSESS')) return 'Assessment';
  if (u.startsWith('VIDEO'))  return 'Video';
  return 'Resource';
}

function _fileExt(name) {
  const parts = (name || '').split('.');
  return parts.length > 1 ? parts.pop().toLowerCase() : '';
}

function _folderPath() {
  const parts = [ONEDRIVE_ROOT_FOLDER];
  folderHistory.forEach(h => { if (h.name) parts.push(h.name); });
  const cur = resultsTitleEl && resultsTitleEl.textContent;
  if (cur && cur !== ONEDRIVE_ROOT_FOLDER && cur !== parts[parts.length - 1]) parts.push(cur);
  return parts.join(' \u203a ');
}

function _track(event, extra) {
  if (!currentUser) return;
  // Prefer the full display name from the MS account token claims
  const _uName = currentUser.idTokenClaims?.name
    || currentUser.name
    || currentUser.username
    || '';
  const payload = Object.assign({
    event,
    session_id:  _SESSION_ID,
    user_oid:    currentUser.localAccountId || currentUser.homeAccountId || '',
    user_name:   _uName,
    user_email:  currentUser.username || '',   // always the UPN/email
    folder_path: _folderPath(),
    filters:     { grade: activeFilters.grade, subject: activeFilters.subject, type: activeFilters.type },
  }, extra || {});

  if (event === 'session_end' && navigator.sendBeacon) {
    navigator.sendBeacon(_TRACKER_URL, JSON.stringify(payload));
  } else {
    fetch(_TRACKER_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch(function(){});
  }
}

// Fire page_view after sign-in
var _origShowApp = showApp;
showApp = function () {
  _origShowApp();
  _track('page_view');
};

// Fire folder_open on navigation
var _origNavigateTo = navigateTo;
navigateTo = function (itemId, folderName) {
  _origNavigateTo(itemId, folderName);
  _track('folder_open', { item_id: itemId, item_name: folderName });
};

// file_view is now tracked from inside renderPreviewContent (after blob loads),
// guarded by _viewTracked so re-opening a cached file doesn't re-count.
// The wrapper is intentionally removed to prevent double-firing.

// Fire search event
var _origApplySearch = applySearch;
applySearch = function () {
  _origApplySearch();
  var q = (searchInput && searchInput.value && searchInput.value.trim()) || '';
  var g = (filterGrade && filterGrade.value) || '';
  var s = (filterSubject && filterSubject.value) || '';
  var t = (filterType && filterType.value) || '';
  if (q || g || s || t) {
    _track('search', {
      search_query: q,
      filters: { grade: g, subject: s, type: t },
    });
  }
};

// Fire session_end on unload
var _sessionStart = Date.now();
window.addEventListener('pagehide', function () {
  _track('session_end', { duration_sec: Math.round((Date.now() - _sessionStart) / 1000) });
});