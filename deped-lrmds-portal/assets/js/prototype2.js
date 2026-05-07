/* =============================================================
   prototype2 — app.js  (v2 – fixed loading, PDF preview, DOCX preview)
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
let currentItemId   = null;
let folderHistory   = [];
let allFilesCache   = null;        // deep cache (only built on search)
let deepCachePromise = null;       // ongoing background deep-fetch promise
let activeFilters   = { subject: '', grade: '', type: '', q: '' };
let accessToken     = null;
let currentUser     = null;
let isListView      = false;

// ── DOM refs ──────────────────────────────────────────────────
const loginScreen   = document.getElementById('login-screen');
const appShell      = document.getElementById('app-shell');
const loginBtn      = document.getElementById('login-btn');
const logoutBtn     = document.getElementById('logout-btn');
const userNameEl    = document.getElementById('user-name');
const userInitialEl = document.getElementById('user-initial');
const resultsGrid   = document.getElementById('results');
const resultsTitleEl= document.getElementById('results-title');
const resultsMetaEl = document.getElementById('results-meta');
const breadcrumbEl  = document.getElementById('breadcrumb');
const backBar       = document.getElementById('back-bar');
const backFolderName= document.getElementById('current-folder-name');
const searchInput   = document.getElementById('search-input');
const filterGrade   = document.getElementById('filter-grade');
const filterSubject = document.getElementById('filter-subject');
const filterType    = document.getElementById('filter-type');
const toastEl       = document.getElementById('toast');
const gridViewBtn   = document.getElementById('btn-grid-view');
const listViewBtn   = document.getElementById('btn-list-view');

// ── MSAL: handle redirect after login ────────────────────────
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

// ── Auth helpers ──────────────────────────────────────────────
async function acquireTokenSilently() {
  try {
    const result = await msalInstance.acquireTokenSilent({
      ...loginRequest,
      account: currentUser,
    });
    accessToken = result.accessToken;
  } catch (e) {
    msalInstance.acquireTokenRedirect(loginRequest);
  }
}

loginBtn.addEventListener('click', () => {
  msalInstance.loginRedirect(loginRequest);
});

logoutBtn.addEventListener('click', () => {
  msalInstance.logoutRedirect({ postLogoutRedirectUri: REDIRECT_URI });
});

function showApp() {
  loginScreen.classList.add('hidden');
  appShell.classList.add('visible');
  const name = currentUser.name || currentUser.username;
  userNameEl.textContent = name;
  userInitialEl.textContent = name.charAt(0).toUpperCase();
  loadFolder(null, ONEDRIVE_ROOT_FOLDER);
}

// ── Graph API helper ──────────────────────────────────────────
async function graphGet(url) {
  await acquireTokenSilently();
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${accessToken}` }
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error(err.error?.message || `Graph API error ${res.status}`);
  }
  return res.json();
}

// Fetch authenticated blob for a Graph item (used by preview)
async function fetchItemBlob(itemId) {
  await acquireTokenSilently();
  const url = buildDownloadUrl(itemId);
  const res = await fetch(url, {
    headers: { Authorization: `Bearer ${accessToken}` }
  });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.blob();
}

// ── Build Graph URLs ──────────────────────────────────────────
function buildChildrenUrl(itemId) {
  const base = ONEDRIVE_OWNER_UPN
    ? `${GRAPH_BASE}/users/${encodeURIComponent(ONEDRIVE_OWNER_UPN)}/drive`
    : `${GRAPH_BASE}/me/drive`;
  if (itemId === null) {
    return `${base}/root:/${encodeURIComponent(ONEDRIVE_ROOT_FOLDER)}:/children?$top=500&$orderby=name`;
  }
  return `${base}/items/${itemId}/children?$top=500&$orderby=name`;
}

function buildDownloadUrl(itemId) {
  const base = ONEDRIVE_OWNER_UPN
    ? `${GRAPH_BASE}/users/${encodeURIComponent(ONEDRIVE_OWNER_UPN)}/drive`
    : `${GRAPH_BASE}/me/drive`;
  return `${base}/items/${itemId}/content`;
}

function buildItemUrl(itemId) {
  const base = ONEDRIVE_OWNER_UPN
    ? `${GRAPH_BASE}/users/${encodeURIComponent(ONEDRIVE_OWNER_UPN)}/drive`
    : `${GRAPH_BASE}/me/drive`;
  return `${base}/items/${itemId}`;
}

// ── Parse metadata from filename ─────────────────────────────
function parseFileMeta(item) {
  const name = item.name || '';
  const upper = name.toUpperCase();

  let type = 'Resource';
  if (upper.startsWith('SLM'))         type = 'SLM';
  else if (upper.startsWith('TG'))     type = 'TG';
  else if (upper.startsWith('DLL'))    type = 'DLL';
  else if (upper.startsWith('ASSESS')) type = 'Assessment';
  else if (upper.startsWith('VIDEO'))  type = 'Video';

  const gradeMatch = name.match(/\bG(\d{1,2})\b/i) || name.match(/\b(Kinder)\b/i);
  let grade = '';
  if (gradeMatch) {
    grade = gradeMatch[1] ? `Grade ${gradeMatch[1]}` : 'Kinder';
  }

  const subjectMap = {
    MATH: 'Mathematics', MTH: 'Mathematics',
    SCI: 'Science', SC: 'Science',
    ENG: 'English', EN: 'English',
    FIL: 'Filipino',
    AP: 'Araling Panlipunan',
    MAPEH: 'MAPEH',
    EPP: 'EPP/TLE', TLE: 'EPP/TLE',
  };
  let subject = '';
  const parts = name.split(/[_\-\s]/);
  for (const p of parts) {
    const key = p.toUpperCase();
    if (subjectMap[key]) { subject = subjectMap[key]; break; }
  }

  const quarterMatch = name.match(/Q(\d)/i);
  const quarter = quarterMatch ? `Quarter ${quarterMatch[1]}` : '';

  const melcMatch = name.match(/([A-Z]{1,4}\d[A-Z]{1,4}-[IVXa-z]+-[\d.]+)/i);
  const melc = melcMatch ? melcMatch[1] : '';

  const title = name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ').trim();

  return { type, grade, subject, quarter, melc, title };
}

// ── Preview modal ─────────────────────────────────────────────
function getPreviewType(item) {
  const mime = item.file?.mimeType || '';
  const ext  = (item.name.split('.').pop() || '').toLowerCase();

  if (mime.includes('image') || ['jpg','jpeg','png','gif','webp','svg','bmp'].includes(ext))
    return 'image';
  if (mime.includes('video') || ['mp4','webm','ogg','mov','avi','mkv'].includes(ext))
    return 'video';
  if (mime.includes('audio') || ['mp3','wav','ogg','m4a','flac'].includes(ext))
    return 'audio';
  if (mime.includes('pdf') || ext === 'pdf')
    return 'pdf';
  if (mime.includes('word') || ext === 'docx' || ext === 'doc')
    return 'docx';
  if (mime.includes('sheet') || ['xls','xlsx'].includes(ext))
    return 'office-sheet';
  if (mime.includes('presentation') || ['ppt','pptx'].includes(ext))
    return 'office-ppt';
  if (['txt','md','csv'].includes(ext))
    return 'text';
  return null;
}

async function openPreview(item) {
  const previewType = getPreviewType(item);
  if (!previewType) {
    showToast('Preview not available for this file type. Use Open ↗ instead.');
    return;
  }

  // Build or show modal
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
            <a id="preview-open-btn" class="button ghost small" target="_blank" rel="noopener">Open ↗</a>
            <button class="button primary small" id="preview-dl-btn">⬇ Download</button>
            <button class="preview-close" id="preview-close" title="Close">✕</button>
          </div>
        </div>
        <div class="preview-body" id="preview-body">
          <div class="preview-loading">
            <div class="spinner"></div>
            <span>Loading preview…</span>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modal);

    document.getElementById('preview-backdrop').addEventListener('click', closePreview);
    document.getElementById('preview-close').addEventListener('click', closePreview);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });
  }

  // Populate header info
  const meta = item._meta || parseFileMeta(item);
  document.getElementById('preview-icon').textContent = mimeIcon(item.file?.mimeType);
  document.getElementById('preview-filename').textContent = item.name;
  document.getElementById('preview-filemeta').textContent =
    [meta.grade, meta.subject, meta.quarter, formatSize(item.size)].filter(Boolean).join(' · ');
  document.getElementById('preview-open-btn').href = item.webUrl;
  document.getElementById('preview-dl-btn').onclick = () => downloadFile(item.id, item.name);

  // Show modal
  modal.classList.add('visible');
  document.body.style.overflow = 'hidden';

  const previewBody = document.getElementById('preview-body');
  previewBody.innerHTML = `<div class="preview-loading"><div class="spinner"></div><span>Loading preview…</span></div>`;

  // Clear any previous blob URLs
  if (modal._cleanupUrls) {
    modal._cleanupUrls.forEach(u => URL.revokeObjectURL(u));
  }
  modal._cleanupUrls = [];

  try {
    await acquireTokenSilently();

    if (previewType === 'image') {
      const blob = await fetchItemBlob(item.id);
      const url  = URL.createObjectURL(blob);
      modal._cleanupUrls.push(url);
      previewBody.innerHTML = `
        <div class="preview-img-wrap">
          <img src="${url}" alt="${escHtml(item.name)}" class="preview-img"
               onload="this.style.opacity=1" style="opacity:0;transition:opacity .3s"/>
        </div>`;

    } else if (previewType === 'video') {
      const blob = await fetchItemBlob(item.id);
      const url  = URL.createObjectURL(blob);
      modal._cleanupUrls.push(url);
      previewBody.innerHTML = `
        <div class="preview-video-wrap">
          <video controls autoplay class="preview-video" src="${url}"></video>
        </div>`;

    } else if (previewType === 'audio') {
      const blob = await fetchItemBlob(item.id);
      const url  = URL.createObjectURL(blob);
      modal._cleanupUrls.push(url);
      previewBody.innerHTML = `
        <div class="preview-audio-wrap">
          <div class="preview-audio-icon">🎵</div>
          <div class="preview-audio-name">${escHtml(item.name)}</div>
          <audio controls src="${url}" class="preview-audio"></audio>
        </div>`;

    } else if (previewType === 'pdf') {
      // ✅ FIX: Fetch blob → object URL → <object> tag (browser renders inline, no download)
      previewBody.innerHTML = `<div class="preview-loading"><div class="spinner"></div><span>Fetching PDF…</span></div>`;
      const blob = await fetchItemBlob(item.id);
      const url  = URL.createObjectURL(blob);
      modal._cleanupUrls.push(url);
      previewBody.innerHTML = `
        <object class="preview-iframe" data="${url}#toolbar=1&navpanes=0" type="application/pdf">
          <div class="preview-error">
            <div style="font-size:40px">📄</div>
            <strong>PDF could not render in browser</strong>
            <p>Your browser may not support inline PDFs.</p>
            <button class="button primary small" onclick="downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download PDF</button>
          </div>
        </object>`;

    } else if (previewType === 'docx') {
      // ✅ FIX: Use mammoth.js to convert DOCX → HTML and render inline
      previewBody.innerHTML = `<div class="preview-loading"><div class="spinner"></div><span>Converting document…</span></div>`;

      // Dynamically load mammoth if not already loaded
      if (!window.mammoth) {
        await loadScript('https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.6.0/mammoth.browser.min.js');
      }

      const blob = await fetchItemBlob(item.id);
      const arrayBuffer = await blob.arrayBuffer();

      const result = await mammoth.convertToHtml({ arrayBuffer });
      const html   = result.value;

      previewBody.innerHTML = `
        <div class="preview-docx-wrap">
          <div class="preview-docx-body">${html}</div>
          ${result.messages.length ? `<div class="preview-docx-warn">⚠ ${result.messages.length} conversion notice(s) — some formatting may differ from original.</div>` : ''}
        </div>`;

    } else if (previewType === 'office-sheet' || previewType === 'office-ppt') {
      // For XLS/PPT: fetch blob → object URL as best effort; show fallback if browser can't render
      previewBody.innerHTML = `<div class="preview-loading"><div class="spinner"></div><span>Fetching file…</span></div>`;
      const blob = await fetchItemBlob(item.id);
      const url  = URL.createObjectURL(blob);
      modal._cleanupUrls.push(url);

      // Office files can't render in browser natively — show a helpful offline message
      const ext = (item.name.split('.').pop() || '').toUpperCase();
      previewBody.innerHTML = `
        <div class="preview-error">
          <div style="font-size:48px">${previewType === 'office-sheet' ? '📊' : '📑'}</div>
          <strong>${ext} Preview</strong>
          <p>This file format requires Microsoft Office to preview. Download it to open locally, or click Open ↗ to view it in OneDrive.</p>
          <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:8px">
            <button class="button primary small" onclick="downloadFile('${item.id}','${escHtml(item.name)}')">⬇ Download</button>
            <a href="${escHtml(item.webUrl)}" target="_blank" rel="noopener" class="button ghost small">Open in OneDrive ↗</a>
          </div>
        </div>`;

    } else if (previewType === 'text') {
      const blob = await fetchItemBlob(item.id);
      const text = await blob.text();
      previewBody.innerHTML = `<pre class="preview-text">${escHtml(text)}</pre>`;
    }

  } catch (e) {
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
}

function closePreview() {
  const modal = document.getElementById('preview-modal');
  if (!modal) return;
  modal.classList.remove('visible');
  document.body.style.overflow = '';

  // Pause any playing video/audio
  modal.querySelectorAll('video, audio').forEach(el => el.pause());

  // Clean up blob URLs to free memory
  if (modal._cleanupUrls) {
    modal._cleanupUrls.forEach(u => URL.revokeObjectURL(u));
    modal._cleanupUrls = [];
  }

  // Clear content after transition
  setTimeout(() => {
    const body = document.getElementById('preview-body');
    if (body) body.innerHTML = '';
  }, 300);
}

// ── Script loader helper (for mammoth.js) ─────────────────────
function loadScript(src) {
  return new Promise((resolve, reject) => {
    const s = document.createElement('script');
    s.src = src;
    s.onload = resolve;
    s.onerror = () => reject(new Error(`Failed to load script: ${src}`));
    document.head.appendChild(s);
  });
}

// ── Load a folder from Graph API ─────────────────────────────
// PERF FIX: Only fetches the selected folder's direct children (fast).
// Background deep-cache starts quietly after the folder renders.
async function loadFolder(itemId, folderName) {
  currentItemId = itemId;
  allFilesCache = null;
  deepCachePromise = null;

  renderLoading();
  updateBreadcrumb(folderName);
  updateBackBar(folderName);

  try {
    const url   = buildChildrenUrl(itemId);
    const data  = await graphGet(url);
    const items = data.value || [];
    renderItems(items, folderName || ONEDRIVE_ROOT_FOLDER);

    // ⚡ Kick off background deep-cache (for faster search if user searches later)
    startBackgroundDeepCache(itemId);
  } catch (e) {
    renderError(e.message);
  }
}

// ── Background deep-cache: parallel folder fetching ──────────
// Fetches all files in the background so search is instant.
function startBackgroundDeepCache(itemId) {
  // Don't re-fetch if already cached
  if (allFilesCache) return;

  deepCachePromise = collectAllFilesDeepParallel(itemId)
    .then(files => {
      allFilesCache = files;
      console.log(`[LRMDS] Background cache ready: ${files.length} files indexed.`);
    })
    .catch(err => {
      console.warn('[LRMDS] Background cache failed:', err.message);
      deepCachePromise = null; // Allow retry on next search
    });
}

// ── Recursively collect all files — PARALLEL (not serial) ───
// Uses Promise.all so sibling folders are fetched simultaneously.
async function collectAllFilesDeepParallel(itemId) {
  const url  = buildChildrenUrl(itemId);
  const data = await graphGet(url);
  const items = data.value || [];

  const files = items.filter(i => i.file);
  const folders = items.filter(i => i.folder);

  // Fetch all sibling sub-folders in parallel
  const subFilesArrays = await Promise.all(
    folders.map(f => collectAllFilesDeepParallel(f.id))
  );

  return files.concat(...subFilesArrays);
}

// ── Filter logic ──────────────────────────────────────────────
function itemMatchesFilters(item) {
  const { q, grade, subject, type } = activeFilters;
  if (!q && !grade && !subject && !type) return true;

  const meta = item._meta || parseFileMeta(item);
  item._meta = meta;

  const searchStr = (meta.title + ' ' + item.name + ' ' + meta.melc).toLowerCase();

  if (q       && !searchStr.includes(q.toLowerCase())) return false;
  if (grade   && meta.grade   !== grade)   return false;
  if (subject && meta.subject !== subject) return false;
  if (type    && meta.type    !== type)    return false;

  return true;
}

// ── Apply search / filters ────────────────────────────────────
async function applySearch() {
  activeFilters.q       = searchInput.value.trim();
  activeFilters.grade   = filterGrade.value;
  activeFilters.subject = filterSubject.value;
  activeFilters.type    = filterType.value;

  const isFiltering = activeFilters.q || activeFilters.grade || activeFilters.subject || activeFilters.type;

  if (!isFiltering) {
    loadFolder(currentItemId, resultsTitleEl.textContent);
    return;
  }

  // If background cache is already ready, search is instant
  if (allFilesCache) {
    const matched = allFilesCache.filter(itemMatchesFilters);
    renderItems(matched, 'Search Results', true);
    return;
  }

  renderLoading('Searching all folders…');

  try {
    // Wait for background cache if it's already running, else start fresh
    if (deepCachePromise) {
      await deepCachePromise;
    } else {
      allFilesCache = await collectAllFilesDeepParallel(null);
    }
    const matched = allFilesCache.filter(itemMatchesFilters);
    renderItems(matched, 'Search Results', true);
  } catch (e) {
    renderError(e.message);
  }
}

function clearSearch() {
  searchInput.value   = '';
  filterGrade.value   = '';
  filterSubject.value = '';
  filterType.value    = '';
  activeFilters = { subject: '', grade: '', type: '', q: '' };
  currentItemId = null;
  folderHistory = [];
  allFilesCache = null;
  deepCachePromise = null;
  loadFolder(null, ONEDRIVE_ROOT_FOLDER);
}

searchInput.addEventListener('keydown', e => { if (e.key === 'Enter') applySearch(); });

// ── Navigation ────────────────────────────────────────────────
function navigateTo(itemId, folderName) {
  folderHistory.push({ id: currentItemId, name: resultsTitleEl.textContent });
  loadFolder(itemId, folderName);
}

function goBack() {
  if (folderHistory.length === 0) return;
  const prev = folderHistory.pop();
  loadFolder(prev.id, prev.name);
}

// ── View toggle ───────────────────────────────────────────────
gridViewBtn.addEventListener('click', () => {
  isListView = false;
  resultsGrid.classList.remove('list-view');
  gridViewBtn.classList.add('active');
  listViewBtn.classList.remove('active');
});
listViewBtn.addEventListener('click', () => {
  isListView = true;
  resultsGrid.classList.add('list-view');
  listViewBtn.classList.add('active');
  gridViewBtn.classList.remove('active');
});

// ── Render helpers ────────────────────────────────────────────
function renderLoading(msg = 'Loading from OneDrive…') {
  resultsGrid.innerHTML = `
    <div class="loading-state">
      <div class="spinner"></div>
      <span>${msg}</span>
    </div>`;
  resultsMetaEl.textContent = '';
}

function renderError(msg) {
  resultsGrid.innerHTML = `
    <div class="error-banner">
      ⚠️ <span><strong>Could not load files.</strong> ${msg}<br>
      Make sure your Azure App ID and Tenant ID are set correctly, and that the folder exists in OneDrive.</span>
    </div>`;
}

function renderItems(items, titleText, isSearch = false) {
  resultsTitleEl.textContent = titleText || ONEDRIVE_ROOT_FOLDER;
  resultsGrid.innerHTML = '';

  const folders = items.filter(i => i.folder);
  const files   = items.filter(i => i.file);

  if (items.length === 0) {
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

  for (const item of items) {
    const card = item.folder ? buildFolderCard(item) : buildFileCard(item);
    resultsGrid.appendChild(card);
  }

  updateSidebar(files);
}

function buildFolderCard(item) {
  const div = document.createElement('div');
  div.className = 'folder-card';
  div.setAttribute('role', 'button');
  div.setAttribute('tabindex', '0');

  const count = item.folder.childCount ?? '?';

  div.innerHTML = `
    <div class="folder-icon-wrap">📁</div>
    <div class="folder-name">${escHtml(item.name)}</div>
    <div class="folder-meta">
      <span style="margin-left:auto;">${count} items ›</span>
    </div>`;

  const open = () => navigateTo(item.id, item.name);
  div.addEventListener('click', open);
  div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') open(); });
  return div;
}

function buildFileCard(item) {
  item._meta = item._meta || parseFileMeta(item);
  const meta = item._meta;

  const ext       = (item.name.split('.').pop() || '').toUpperCase();
  const icon      = mimeIcon(item.file?.mimeType);
  const size      = formatSize(item.size);
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
        ${canPreview ? `<button class="button secondary small preview-btn" onclick="event.stopPropagation()">👁 Preview</button>` : ''}
        <a href="${escHtml(item.webUrl)}" target="_blank" rel="noopener"
           class="button ghost small" onclick="event.stopPropagation()">Open ↗</a>
        <button class="button primary small" onclick="event.stopPropagation();downloadFile('${item.id}','${escHtml(item.name)}')">
          ⬇ Download
        </button>
      </div>
    </div>`;

  // Preview button click
  if (canPreview) {
    div.querySelector('.preview-btn').addEventListener('click', (e) => {
      e.stopPropagation();
      openPreview(item);
    });
    // Also clicking the thumb opens preview
    div.querySelector('.thumb-wrap').addEventListener('click', (e) => {
      e.stopPropagation();
      openPreview(item);
    });
  }

  return div;
}

// ── Download via Graph ────────────────────────────────────────
async function downloadFile(itemId, fileName) {
  showToast(`Preparing download: ${fileName}`);
  try {
    const blob = await fetchItemBlob(itemId);
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = fileName;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 5000);
    showToast(`Downloaded: ${fileName}`);
  } catch (e) {
    showToast(`Download failed: ${e.message}`);
  }
}

// ── Breadcrumb ────────────────────────────────────────────────
function updateBreadcrumb(currentName) {
  let html = `<a onclick="loadFolder(null,'${ONEDRIVE_ROOT_FOLDER}')">Home</a><span class="bc-sep">›</span>`;

  if (folderHistory.length === 0 && currentItemId === null) {
    html += `<span class="bc-current">${escHtml(ONEDRIVE_ROOT_FOLDER)}</span>`;
  } else {
    html += `<span class="bc-crumb" onclick="clearSearch()">${escHtml(ONEDRIVE_ROOT_FOLDER)}</span>`;
    folderHistory.forEach((h, idx) => {
      html += `<span class="bc-sep">›</span>
               <span class="bc-crumb" data-idx="${idx}">${escHtml(h.name)}</span>`;
    });
    if (currentName) {
      html += `<span class="bc-sep">›</span>
               <span class="bc-current">${escHtml(currentName)}</span>`;
    }
  }

  breadcrumbEl.innerHTML = html;

  breadcrumbEl.querySelectorAll('[data-idx]').forEach(el => {
    const idx = parseInt(el.getAttribute('data-idx'));
    el.addEventListener('click', () => {
      const target = folderHistory[idx];
      folderHistory = folderHistory.slice(0, idx);
      loadFolder(target.id, target.name);
    });
  });
}

function updateBackBar(folderName) {
  if (currentItemId !== null || folderHistory.length > 0) {
    backBar.classList.add('visible');
    backFolderName.textContent = folderName || 'Folder';
  } else {
    backBar.classList.remove('visible');
  }
}

// ── Sidebar facets ────────────────────────────────────────────
function updateSidebar(files) {
  buildFacet('facet-subject', files, f => (f._meta || parseFileMeta(f)).subject, 'subject');
  buildFacet('facet-grade',   files, f => (f._meta || parseFileMeta(f)).grade,   'grade');
  buildFacet('facet-type',    files, f => (f._meta || parseFileMeta(f)).type,    'type');
}

function buildFacet(elId, items, extract, filterKey) {
  const counts = {};
  for (const item of items) {
    const val = extract(item);
    if (val) counts[val] = (counts[val] || 0) + 1;
  }

  const el = document.getElementById(elId);
  el.innerHTML = '';

  if (!Object.keys(counts).length) {
    el.innerHTML = `<li><span style="font-size:12px;color:var(--muted);padding:4px 8px;display:block">—</span></li>`;
    return;
  }

  for (const [val, count] of Object.entries(counts).sort()) {
    const li  = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'facet-btn' + (activeFilters[filterKey] === val ? ' active' : '');
    btn.innerHTML = `<span>${escHtml(val)}</span><span class="facet-count">${count}</span>`;
    btn.addEventListener('click', () => {
      activeFilters[filterKey] = activeFilters[filterKey] === val ? '' : val;
      const sel = document.getElementById('filter-' + filterKey);
      if (sel) sel.value = activeFilters[filterKey];
      applySearch();
    });
    li.appendChild(btn);
    el.appendChild(li);
  }
}

// ── Utility ───────────────────────────────────────────────────
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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