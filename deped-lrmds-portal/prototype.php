<?php
// ══════════════════════════════════════════════════════════════════
//  OPTION C — Public SharePoint Folder Embed
//  The folder must be shared as "Anyone with the link can view"
//
//  HOW TO GET THE EMBED URL from your supervisor:
//    1. Open the folder in SharePoint
//    2. Click Share → Copy link (set to "Anyone with link")
//    3. The link looks like:
//       https://depedph-my.sharepoint.com/:f:/g/personal/romulo_estrera_deped_gov_ph/XXXXXX
//    4. To convert to embed URL, append ?e=XXXXX and use format below
//
//  For now we use the direct browser URL — works if folder is public.
// ══════════════════════════════════════════════════════════════════

define('SHAREPOINT_BROWSER_URL',
  'https://depedph-my.sharepoint.com/:f:/g/personal/romulo_estrera_deped_gov_ph/IgCc0M0PYXgFSaBgxf3JmfBvAQ2tUJJh9C1cqVRWk3GbVFY?e=CrBUDr' .
  '?id=%2Fpersonal%2Fromulo%5Festrera%5Fdeped%5Fgov%5Fph%2FDocuments%2FCARCARCITYLRMDS%2DPORTAL' .
  '&viewid=efe8a0ba%2D8ecc%2D40d3%2Dbc7f%2Ddc81717a2ea3'
);

// If your supervisor gives you a sharing link (:f:/g/...), paste it here
// and the page will auto-convert it to an embed URL
define('SHAREPOINT_SHARE_LINK', '<iframe src="https://depedph-my.sharepoint.com/personal/romulo_estrera_deped_gov_ph/_layouts/15/embed.aspx?UniqueId=54a08c7a-b001-48e5-9b44-5ec84a01c16d" width="640" height="360" frameborder="0" scrolling="no" allowfullscreen title="Araling Panlipunan CG 2023"></iframe<iframe src="https://depedph-my.sharepoint.com/personal/romulo_estrera_deped_gov_ph/_layouts/15/embed.aspx?UniqueId=54a08c7a-b001-48e5-9b44-5ec84a01c16d" width="640" height="360" frameborder="0" scrolling="no" allowfullscreen title="Araling Panlipunan CG 2023"></iframe>>'); // e.g. https://depedph-my.sharepoint.com/:f:/g/personal/...

define('DIVISION_NAME', 'Carcar City Division');
define('SCHOOL_YEAR',   'SY 2024–2025');
define('PAGE_TITLE',    'DepEd Carcar City LRMDS – Resources');

// Build the best embed URL we can
function getEmbedUrl() {
  $share = SHAREPOINT_SHARE_LINK;
  if ($share) {
    // Convert sharing link to embed URL
    // https://tenant/:f:/g/personal/user/FOLDERID?e=TOKEN
    // → https://tenant/personal/user/_layouts/15/embed.aspx?...
    return $share . (strpos($share, '?') === false ? '?' : '&') . 'action=embedview';
  }
  // Fallback: use browser URL (may or may not embed depending on tenant settings)
  return SHAREPOINT_BROWSER_URL;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars(PAGE_TITLE) ?></title>
  <link rel="stylesheet" href="assets/css/prototype.css"/>
  <style>
    /* ── Embed-page extras ── */
    .embed-wrap {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .embed-toolbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 16px;
      background: #F8FAFC;
      border-bottom: 1px solid var(--border);
      gap: 10px;
      flex-wrap: wrap;
    }
    .embed-toolbar-left {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .embed-label {
      font-size: 13px;
      font-weight: 700;
      color: var(--text);
    }
    .embed-sublabel {
      font-size: 11px;
      color: var(--muted);
    }
    .embed-status {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 99px;
    }
    .embed-status.live   { background: #D1FAE5; color: #065F46; }
    .embed-status.trying { background: #FEF3C7; color: #92400E; }
    .embed-status .dot {
      width: 6px; height: 6px;
      border-radius: 50%;
      background: currentColor;
      animation: pulse 1.5s infinite;
    }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

    .sharepoint-frame {
      width: 100%;
      height: 680px;
      border: none;
      display: block;
    }

    /* Fallback shown if iframe fails */
    .embed-fallback {
      display: none;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 24px;
      text-align: center;
      gap: 16px;
      min-height: 300px;
    }
    .embed-fallback .fallback-icon { font-size: 48px; }
    .embed-fallback h3 { font-size: 16px; font-weight: 700; color: var(--text); }
    .embed-fallback p  { font-size: 13px; color: var(--muted); max-width: 400px; line-height: 1.6; }
    .fallback-actions  { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin-top: 8px; }

    .page-body-embed {
      max-width: 1400px;
      margin: 0 auto;
      padding: 24px;
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    /* Tab switcher */
    .view-tabs {
      display: flex;
      gap: 0;
      border: 1px solid var(--border);
      border-radius: 8px;
      overflow: hidden;
      width: fit-content;
    }
    .tab-btn {
      padding: 8px 20px;
      font-size: 13px;
      font-weight: 600;
      font-family: var(--font);
      background: #fff;
      border: none;
      cursor: pointer;
      color: var(--muted);
      border-right: 1px solid var(--border);
      transition: all .15s;
    }
    .tab-btn:last-child { border-right: none; }
    .tab-btn.active { background: var(--primary); color: #fff; }
    .tab-btn:hover:not(.active) { background: var(--bg-soft); }

    .tab-panel { display: none; }
    .tab-panel.active { display: block; }
  </style>
</head>
<body>

<!-- ── Header ── -->
<header class="site-header">
  <a class="logo" href="#">
    <div class="logo-shield">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>
    DepEd Carcar City LRMDS
  </a>
  <nav class="header-nav">
    <a class="nav-link" href="#">Home</a>
    <a class="nav-link active" href="#">Resources</a>
    <a class="nav-link" href="#">My Library</a>
    <a class="nav-link" href="#">Upload</a>
  </nav>
</header>

<!-- ── Main ── -->
<div class="page-body-embed">

  <!-- Top bar -->
  <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
    <div>
      <h1 style="font-size:20px;font-weight:800;color:var(--text);">LRMDS Resource Portal</h1>
      <p style="font-size:13px;color:var(--muted);margin-top:2px;">
        <?= htmlspecialchars(DIVISION_NAME) ?> · <?= htmlspecialchars(SCHOOL_YEAR) ?>
      </p>
    </div>
    <div class="view-tabs">
      <button class="tab-btn active" onclick="switchTab('embed', this)">📂 Browse Folder</button>
      <button class="tab-btn" onclick="switchTab('catalogue', this)">🗂 Catalogue View</button>
    </div>
  </div>

  <!-- ══════════════════════════════
       TAB 1: SharePoint Embed
  ══════════════════════════════ -->
  <div class="tab-panel active" id="tab-embed">
    <div class="embed-wrap">
      <div class="embed-toolbar">
        <div class="embed-toolbar-left">
          <div style="width:36px;height:36px;background:var(--primary);border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;">📁</div>
          <div>
            <div class="embed-label">CARCARCITYLRMDS-PORTAL</div>
            <div class="embed-sublabel">SharePoint · <?= htmlspecialchars(DIVISION_NAME) ?></div>
          </div>
          <span class="embed-status trying" id="embed-status">
            <span class="dot"></span> Loading…
          </span>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button class="button ghost small" onclick="reloadFrame()">↺ Reload</button>
          <a class="button primary small"
             href="<?= htmlspecialchars(SHAREPOINT_BROWSER_URL) ?>"
             target="_blank" rel="noopener noreferrer">
            Open in SharePoint ↗
          </a>
        </div>
      </div>

      <!-- The actual iframe -->
      <iframe
        id="sp-frame"
        class="sharepoint-frame"
        src="<?= htmlspecialchars(getEmbedUrl()) ?>"
        title="SharePoint Resource Folder"
        allow="clipboard-write"
        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-top-navigation"
        onload="frameLoaded()"
        onerror="frameError()"
      ></iframe>

      <!-- Fallback (shown by JS if iframe fails) -->
      <div class="embed-fallback" id="embed-fallback">
        <div class="fallback-icon">🔒</div>
        <h3>Embedding blocked by Microsoft</h3>
        <p>
          SharePoint is preventing this page from embedding the folder
          (this is a Microsoft security setting). You can still access all files
          by clicking the button below — it opens directly in SharePoint.
        </p>
        <div class="fallback-actions">
          <a class="button primary"
             href="<?= htmlspecialchars(SHAREPOINT_BROWSER_URL) ?>"
             target="_blank" rel="noopener noreferrer">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
              <polyline points="15 3 21 3 21 9"/>
              <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            Open Full Folder in SharePoint ↗
          </a>
          <button class="button ghost" onclick="switchTab('catalogue', document.querySelector('.tab-btn:nth-child(2)'))">
            Use Catalogue View instead
          </button>
        </div>
      </div>
    </div>

    <div style="margin-top:12px;padding:12px 16px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;font-size:12px;color:#92400E;">
      <strong>💡 Tip:</strong>
      If you see a login screen inside the frame, click
      <strong>"Open in SharePoint ↗"</strong> above — the folder is accessible
      to anyone with the link; you may just need to sign in with your DepEd
      Microsoft 365 account once.
      <?php if (!SHAREPOINT_SHARE_LINK): ?>
      &nbsp;|&nbsp; <strong>Admin:</strong> Paste the <code>/:f:/g/</code> sharing link into
      <code>SHAREPOINT_SHARE_LINK</code> in this file for better embed support.
      <?php endif; ?>
    </div>
  </div>

  <!-- ══════════════════════════════
       TAB 2: Catalogue / Card view
  ══════════════════════════════ -->
  <div class="tab-panel" id="tab-catalogue">

    <!-- Filter bar inline -->
    <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius);padding:14px;margin-bottom:16px;">
      <div class="filters" style="max-width:100%;">
        <input class="input" id="search-input" type="search" placeholder="Search by keyword, MELC code, title…" aria-label="Search"/>
        <select class="select" id="filter-grade" aria-label="Grade level">
          <option value="">All Grades</option>
          <option>Kinder</option>
          <option>Grade 1</option><option>Grade 2</option><option>Grade 3</option>
          <option>Grade 4</option><option>Grade 5</option><option>Grade 6</option>
          <option>Grade 7</option><option>Grade 8</option><option>Grade 9</option>
          <option>Grade 10</option><option>Grade 11</option><option>Grade 12</option>
        </select>
        <select class="select" id="filter-subject" aria-label="Subject">
          <option value="">All Subjects</option>
          <option>English</option><option>Filipino</option><option>Mathematics</option>
          <option>Science</option><option>Araling Panlipunan</option>
          <option>MAPEH</option><option>EPP/TLE</option>
        </select>
        <select class="select" id="filter-type" aria-label="Type">
          <option value="">All Types</option>
          <option>SLM</option><option>TG</option><option>DLL</option>
          <option>Video</option><option>Assessment</option>
        </select>
        <button class="button primary" id="btn-search">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
          </svg> Search
        </button>
        <button class="button ghost" id="btn-clear">Clear</button>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:20px;align-items:start;">
      <aside class="sidebar">
        <div class="sidebar-heading">Subject</div>
        <ul class="facet-list" id="facet-subject"></ul>
        <div class="sidebar-heading">Grade Level</div>
        <ul class="facet-list" id="facet-grade"></ul>
        <div class="sidebar-heading">Resource Type</div>
        <ul class="facet-list" id="facet-type"></ul>
      </aside>

      <section class="results-pane">
        <div class="back-bar" id="back-bar">
          <button id="btn-back">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
              <path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>
            </svg> Back
          </button>
          <span id="current-folder-name">Folder</span>
        </div>
        <div class="results-header">
          <div>
            <div class="results-title" id="results-title">All Resources</div>
            <div class="results-meta" id="results-meta">Browse the catalogue</div>
          </div>
          <div class="view-toggle">
            <button class="view-btn active" id="view-grid" title="Grid">⊞</button>
            <button class="view-btn" id="view-list" title="List">☰</button>
          </div>
        </div>
        <div class="results-grid" id="results" aria-live="polite"></div>
      </section>
    </div>

  </div><!-- /tab-catalogue -->

</div><!-- /page-body-embed -->

<div class="toast" id="toast"></div>

<script>
const SHAREPOINT_URL = <?= json_encode(SHAREPOINT_BROWSER_URL) ?>;

/* ── Tab switching ── */
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  btn.classList.add('active');
}

/* ── iframe status detection ── */
let frameTimer;
function frameLoaded() {
  clearTimeout(frameTimer);
  // Try to detect if it actually loaded content vs a blank/login page
  try {
    const f = document.getElementById('sp-frame');
    // If we can read contentDocument, it loaded same-origin (unlikely with SP)
    // If it throws, it's cross-origin but loaded something (good sign)
    const doc = f.contentDocument || f.contentWindow.document;
    // If we get here without error, check if body has content
    if (!doc.body || doc.body.innerHTML.trim() === '') {
      frameError();
      return;
    }
  } catch(e) {
    // Cross-origin = SharePoint loaded — this is actually success
  }
  setStatus('live', '✓ Connected');
}

function frameError() {
  clearTimeout(frameTimer);
  setStatus('trying', 'Embed blocked');
  document.getElementById('sp-frame').style.display = 'none';
  document.getElementById('embed-fallback').style.display = 'flex';
}

function setStatus(cls, text) {
  const el = document.getElementById('embed-status');
  el.className = 'embed-status ' + cls;
  el.innerHTML = cls === 'live'
    ? `<span class="dot"></span> ${text}`
    : text;
}

function reloadFrame() {
  const f = document.getElementById('sp-frame');
  f.style.display = 'block';
  document.getElementById('embed-fallback').style.display = 'none';
  setStatus('trying', '<span class="dot"></span> Loading…');
  f.src = f.src;
  frameTimer = setTimeout(frameError, 12000);
}

// Start timeout — if no load event fires in 12s, show fallback
frameTimer = setTimeout(frameError, 12000);
</script>
<script src="assets/js/prototype.js"></script>
</body>
</html>