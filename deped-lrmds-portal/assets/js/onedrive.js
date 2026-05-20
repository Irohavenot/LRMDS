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
let activeFilters    = { subject: '', grade: '', type: '', quarter: '', q: '' };
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
const filterQuarter  = document.getElementById('filter-quarter');
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

logoutBtn.addEventListener('click', () => {
  if (!confirm('Sign out of DepEd Carcar City LRMDS?\n\nYou will be returned to the sign-in page.')) return;
  // Clear MSAL session without hitting Microsoft's logout page,
  // then redirect to our own login screen.
  msalInstance.clearCache();
  currentUser  = null;
  accessToken  = null;
  // Hard redirect to the sign-in page
  window.location.href = window.location.pathname;
});

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
      'No share link configured. Paste your OneDrive "Anyone" link into SHARED_FOLDER_URL in onedrive.js.'
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
  // Search both the filename AND the full folder path so files inside a
  // "Grade 1" or "English" folder are matched even if the filename is generic.
  const name     = item.name || '';
  const pathStr  = item._folderPathStr || '';
  const combined = (pathStr + ' ' + name).replace(/[_\-]/g, ' ');

  // ── Grade ─────────────────────────────────────────────────────
  // Matches: G1, G 1, Grade1, Grade 1, Grade-1, Gr1, Gr 1 (1-12), or Kinder/Kindergarten
  let grade = '';
  const gradeM = combined.match(/\bGr(?:ade)?\s*[-.]?\s*(\d{1,2})\b|\bG\s*(\d{1,2})\b/i);
  if (gradeM) {
    const n = gradeM[1] || gradeM[2];
    grade = `Grade ${parseInt(n, 10)}`;
  } else if (/\bKinder(?:garten)?\b/i.test(combined)) {
    grade = 'Kinder';
  }

  // ── Subject ───────────────────────────────────────────────────
  // Match by full word/phrase first, then abbreviation. Order = most specific first.
  const subjectPatterns = [
    [/\bAraling\s*Panlipunan\b/i,  'Araling Panlipunan'],
    [/\bA\.?\s*P\.?\b/,            'Araling Panlipunan'],
    [/\bMAPEH\b/i,                 'MAPEH'],
    [/\bEPP\b|\bTLE\b/i,           'EPP/TLE'],
    [/\bMath(?:ematics)?\b/i,      'Mathematics'],
    [/\bScience\b|\bSci\b/i,       'Science'],
    [/\bEnglish\b|\bEng\b/i,       'English'],
    [/\bFilipino\b|\bFil\b/i,      'Filipino'],
  ];
  let subject = '';
  for (const [re, label] of subjectPatterns) {
    if (re.test(combined)) { subject = label; break; }
  }

  // ── Quarter ───────────────────────────────────────────────────
  // Matches: Q1, Q 1, Quarter1, Quarter 1, Qtr1, Qtr 1 (1-4)
  let quarter = '';
  const qM = combined.match(/\bQ(?:uarter|tr)?\.?\s*([1-4])\b/i);
  if (qM) quarter = `Quarter ${qM[1]}`;

  // ── MELC code ─────────────────────────────────────────────────
  const melcM = name.match(/([A-Z]{1,4}\d[A-Z]{1,4}-[IVXa-z]+-[\d.]+)/i);
  const melc  = melcM ? melcM[1] : '';
  const title = name.replace(/\.[^.]+$/, '').replace(/[_\-]+/g, ' ').trim();

  return { grade, subject, quarter, melc, title };
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
            <div class="preview-title-info">
              <div class="preview-filename" id="preview-filename"></div>
              <div class="preview-path-row" id="preview-path-row"></div>
              <div class="preview-filemeta" id="preview-filemeta"></div>
            </div>
          </div>
          <div class="preview-actions">
            <span class="preview-cache-badge" id="preview-cache-badge" title="Instant — loaded from cache">⚡ Cached</span>
            <button class="button ghost small preview-save-btn" id="preview-save-btn" title="Save to My Library">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg>
              Save to Library
            </button>
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

  // ── Full folder path row with collapsible overflow ──────────
  const pathRow = document.getElementById('preview-path-row');
  const pathSegments = item._folderPath || (meta.grade || meta.subject
    ? [ONEDRIVE_ROOT_FOLDER].concat([meta.grade, meta.subject].filter(Boolean))
    : [ONEDRIVE_ROOT_FOLDER]);

  if (pathSegments.length > 3) {
    // Show first + ellipsis chevron + last two
    const collapsed = pathSegments.slice(1, -2);
    const visible   = pathSegments.slice(-2);
    pathRow.innerHTML = `
      <span class="ppath-seg">${escHtml(pathSegments[0])}</span>
      <span class="ppath-sep">›</span>
      <button class="ppath-more" id="ppath-more-btn" title="${escHtml(collapsed.join(' › '))}">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
        ${collapsed.length} more
      </button>
      <span class="ppath-sep">›</span>
      ${visible.map((s, i) => `<span class="ppath-seg">${escHtml(s)}</span>${i < visible.length - 1 ? '<span class="ppath-sep">›</span>' : ''}`).join('')}
      <div class="ppath-expanded hidden" id="ppath-expanded">
        ${pathSegments.map((s, i) => `<span class="ppath-seg">${escHtml(s)}</span>${i < pathSegments.length - 1 ? '<span class="ppath-sep">›</span>' : ''}`).join('')}
      </div>`;
    const moreBtn = document.getElementById('ppath-more-btn');
    if (moreBtn) moreBtn.addEventListener('click', () => {
      const exp = document.getElementById('ppath-expanded');
      if (exp) exp.classList.toggle('hidden');
      moreBtn.classList.toggle('active');
    });
  } else {
    pathRow.innerHTML = pathSegments
      .map((s, i) => `<span class="ppath-seg">${escHtml(s)}</span>${i < pathSegments.length - 1 ? '<span class="ppath-sep">›</span>' : ''}`)
      .join('');
  }

  document.getElementById('preview-filemeta').textContent =
    [meta.quarter, formatSize(item.size)].filter(Boolean).join(' · ');
  document.getElementById('preview-open-btn').href        = item.webUrl;
  document.getElementById('preview-dl-btn').onclick       = () => downloadFile(item.id, item.name);

  // ── Save to Library button in preview ───────────────────────
  const saveBtn = document.getElementById('preview-save-btn');
  if (saveBtn) {
    const _updateSaveBtn = (saved) => {
      saveBtn.classList.toggle('preview-save-btn--saved', saved);
      saveBtn.innerHTML = saved
        ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> Saved`
        : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> Save to Library`;
      saveBtn.title = saved ? 'Remove from My Library' : 'Save to My Library';
    };
    const isSaved = window.LRMDS_Library?.isBookmarked?.(item.id) || false;
    _updateSaveBtn(isSaved);
    // Replace old listener cleanly
    const newSaveBtn = saveBtn.cloneNode(true);
    saveBtn.parentNode.replaceChild(newSaveBtn, saveBtn);
    _updateSaveBtn(isSaved); // re-apply to new node
    newSaveBtn.addEventListener('click', () => {
      if (window.LRMDS_Library?.toggleBookmark) {
        window.LRMDS_Library.toggleBookmark(item);
        const nowSaved = window.LRMDS_Library.isBookmarked(item.id);
        newSaveBtn.classList.toggle('preview-save-btn--saved', nowSaved);
        newSaveBtn.innerHTML = nowSaved
          ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> Saved`
          : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16z"/></svg> Save to Library`;
        newSaveBtn.title = nowSaved ? 'Remove from My Library' : 'Save to My Library';
      }
    });
  }

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
    const url  = await buildChildrenUrl(itemId);
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

async function collectAllItemsDeepParallel(itemId, _pathSegments, _pathIds) {
  const pathSegments = _pathSegments || [];
  const url    = await buildChildrenUrl(itemId);
  const data   = await graphGet(url);
  const items  = data.value || [];
  const files  = items.filter(i => i.file);
  const folders = items.filter(i => i.folder);

  files.forEach(f => {
    f._folderPath     = pathSegments.length ? pathSegments : [ONEDRIVE_ROOT_FOLDER];
    f._folderPathStr  = f._folderPath.join(' › ');
    f._parentFolderId = itemId;           // parent folder's Graph ID (kept for compat)
    f._folderId       = itemId;           // same — the folder that directly contains this file
    f._folderPathIds  = _pathIds || [];   // ancestor Graph IDs for rebuilding back-history
  });

  folders.forEach(d => {
    d._parentPath    = pathSegments.length ? pathSegments : [ONEDRIVE_ROOT_FOLDER];
    d._parentPathStr = d._parentPath.join(' › ');
    d._parentId      = itemId;
    d._fullPath      = [...d._parentPath, d.name];
    d._fullPathStr   = d._fullPath.join(' › ');
    d._pathIds       = _pathIds || [];    // ancestor IDs up to (but not including) this folder
  });

  const sub = await Promise.all(
    folders.map(f => collectAllItemsDeepParallel(f.id, [...pathSegments, f.name], [...(_pathIds || []), itemId]))
  );
  return files.concat(folders, ...sub);
}

// ── Filter / search ───────────────────────────────────────────
function getFileExt(item) {
  return (item.name || '').split('.').pop().toLowerCase();
}

function itemMatchesFilters(item) {
  const { q, grade, subject, type, quarter } = activeFilters;
  if (!q && !grade && !subject && !type && !quarter) return true;

  if (item.folder) {
    // Folders only show up for keyword searches — structural filters are files-only
    if (grade || subject || type || quarter) return false;
    if (!q) return false;
    return item.name.toLowerCase().includes(q.toLowerCase());
  }

  // File — parse meta (uses filename + folder path)
  const meta = item._meta || parseFileMeta(item);
  item._meta = meta;

  // Keyword: check title, filename, MELC code, and full folder path
  const searchStr = (meta.title + ' ' + item.name + ' ' + meta.melc + ' ' + (item._folderPathStr || '')).toLowerCase();
  if (q && !searchStr.includes(q.toLowerCase())) return false;

  // Structural filters: exact match against parsed meta
  if (grade   && meta.grade   !== grade)   return false;
  if (subject && meta.subject !== subject) return false;
  if (quarter && meta.quarter !== quarter) return false;
  if (type    && getFileExt(item) !== type) return false;

  return true;
}

async function applySearch() {
  activeFilters.q       = searchInput.value.trim();
  activeFilters.grade   = filterGrade.value;
  activeFilters.subject = filterSubject.value;
  activeFilters.type    = filterType.value;
  activeFilters.quarter = filterQuarter ? filterQuarter.value : '';

  const isFiltering = activeFilters.q || activeFilters.grade || activeFilters.subject || activeFilters.type || activeFilters.quarter;
  if (!isFiltering) { loadFolder(currentItemId, resultsTitleEl.textContent); return; }

  if (allItemsCache) {
    renderItems(allItemsCache.filter(itemMatchesFilters), 'Search Results', true);
    return;
  }

  renderLoading('Searching all folders…');
  try {
    if (deepCachePromise) await deepCachePromise;
    else allItemsCache = await collectAllItemsDeepParallel(null);
    renderItems(allItemsCache.filter(itemMatchesFilters), 'Search Results', true);
  } catch (e) { renderError(e.message); }
}

function clearSearch() {
  searchInput.value = filterGrade.value = filterSubject.value = filterType.value = '';
  if (filterQuarter) filterQuarter.value = '';
  activeFilters = { subject:'', grade:'', type:'', quarter:'', q:'' };
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
  if (prev.isSearch) {
    // Restore search results — don't change currentItemId, just re-run the search
    // (filters are still set from when the user searched)
    applySearch();
  } else {
    loadFolder(prev.id, prev.name);
  }
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
            pathArr:      item._folderPath    || [ONEDRIVE_ROOT_FOLDER],
            folderId:     item._folderId      || null,   // the folder's own Graph ID
            folderPathIds: item._folderPathIds || [],    // ancestor IDs for back-history
            files:        []
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
          <button class="button ghost small sfg-open-btn">Open folder ›</button>`;
        header.querySelector('.sfg-open-btn').addEventListener('click', () => {
          // folderPathIds = Graph IDs of ancestor folders from root down to parent of this folder
          // e.g. for path  root › A › B › ThisFolder :
          //   folderPathIds = [null, A.id, B.id]   (length = pathArr.length - 1)
          //   ancestorNames = ['deped', 'A', 'B']  (pathArr.slice(0,-1))
          //
          // folderHistory is a stack of "states to go back TO".
          // Each entry: { id: <that-folder's-id>, name: <that-folder's-name> }
          // goBack() pops the top entry and calls loadFolder(entry.id, entry.name).
          //
          // Stack (bottom → top) we need:
          //   { isSearch:true, id:null, name:'Search Results' }   ← go back to search
          //   { id: folderPathIds[0], name: ancestorNames[0] }    ← root level (id=null)
          //   { id: folderPathIds[1], name: ancestorNames[1] }    ← A
          //   { id: folderPathIds[2], name: ancestorNames[2] }    ← B
          // then we navigate into ThisFolder (group.folderId).

          const ancestorIds   = group.folderPathIds;   // [null, A.id, B.id, …]
          const ancestorNames = group.pathArr.slice(0, -1); // ['deped', 'A', 'B', …]

          const newHistory = [
            { id: null, name: resultsTitleEl.textContent, isSearch: true }, // search results
          ];

          for (let i = 0; i < ancestorNames.length; i++) {
            newHistory.push({
              id:   ancestorIds[i] !== undefined ? ancestorIds[i] : null,
              name: ancestorNames[i],
            });
          }

          folderHistory = newHistory;
          loadFolder(group.folderId, folderName);
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

  const open = () => {
    if (isSearch && item._pathIds !== undefined && item._parentPath) {
      // _parentPath  = name segments of ancestors above this folder e.g. ['deped','A','B']
      // _pathIds     = Graph IDs of those same ancestors            e.g. [null, A.id, B.id]
      //
      // History stack (bottom → top):
      //   search-results entry  { isSearch:true, id:null, name:'Search Results' }
      //   root entry            { id: _pathIds[0], name: _parentPath[0] }
      //   …
      //   immediate-parent      { id: _pathIds[n-1], name: _parentPath[n-1] }
      // then we navigate INTO this folder.
      const ancestorNames = item._parentPath;
      const ancestorIds   = item._pathIds;   // [null, A.id, B.id, …]

      const newHistory = [
        { id: null, name: resultsTitleEl.textContent, isSearch: true },
      ];
      for (let i = 0; i < ancestorNames.length; i++) {
        newHistory.push({
          id:   ancestorIds[i] !== undefined ? ancestorIds[i] : null,
          name: ancestorNames[i],
        });
      }
      folderHistory = newHistory;
      loadFolder(item.id, item.name);
    } else {
      navigateTo(item.id, item.name);
    }
  };
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
  div.dataset.itemId = item.id;   // used by analytics.js badge attachment
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
  // Guard SYNCHRONOUSLY before any await so concurrent clicks can't both slip through.
  if (_dlTracked.has(itemId)) return;
  _dlTracked.add(itemId);

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
    // NOTE: _dlTracked.add() was moved to BEFORE the await in downloadFile()
    //       to prevent a race where two simultaneous clicks both pass the check.
    _track('file_download', {
      item_id:   itemId,
      item_name: fileName,
      item_type: _guessType(fileName),
      file_ext:  _fileExt(fileName),
    });
  } catch (e) {
    // If the download failed, un-guard so the user can retry
    _dlTracked.delete(itemId);
    showToast(`Download failed: ${e.message}`);
  }
}

// ── Main Site link — confirm before leaving ───────────────────
document.addEventListener('click', e => {
  const link = e.target.closest('.return-link');
  if (!link) return;
  e.preventDefault();
  if (confirm('Leave DepEd Carcar City LRMDS and return to the main site?')) {
    window.location.href = link.href;
  }
});

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
  // Exclude "Search Results" — it is not a real path segment
  if (cur && cur !== ONEDRIVE_ROOT_FOLDER && cur !== 'Search Results' && cur !== parts[parts.length - 1]) {
    parts.push(cur);
  }
  return parts.join(' \u203a ');
}
// Expose on window so mylibrary.js (loaded after onedrive.js) can call them
window._folderPath = _folderPath;
window._track      = _track;

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
    })
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      // tracker.php returns updated counts for file_view / file_download events.
      // Dispatch to analytics.js so the card badge refreshes immediately.
      if (data && data.counts && extra && extra.item_id) {
        document.dispatchEvent(new CustomEvent('lrmds:counts', {
          detail: { itemId: extra.item_id, counts: data.counts }
        }));
      }
    })
    .catch(function(){});
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

// Fire search event — deduped: same query+filters within 500 ms only fires once.
var _lastSearchSig = '';
var _lastSearchTs  = 0;
var _origApplySearch = applySearch;
applySearch = function () {
  _origApplySearch();
  var q = (searchInput && searchInput.value && searchInput.value.trim()) || '';
  var g = (filterGrade && filterGrade.value) || '';
  var s = (filterSubject && filterSubject.value) || '';
  var t = (filterType && filterType.value) || '';
  if (q || g || s || t) {
    // Collapse duplicate fires (e.g. Enter key + button click on same frame)
    var sig = q + '|' + g + '|' + s + '|' + t;
    var now = Date.now();
    if (sig === _lastSearchSig && now - _lastSearchTs < 500) return;
    _lastSearchSig = sig;
    _lastSearchTs  = now;
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