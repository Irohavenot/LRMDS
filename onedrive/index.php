<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>DepEd Carcar City LRMDS – Resources</title>

<!-- MSAL 2 — Microsoft Authentication Library (CDN, no server needed) -->
<script src="https://cdn.jsdelivr.net/npm/@azure/msal-browser@2.38.3/lib/msal-browser.min.js"></script>

<link rel="stylesheet" href="../assets/css/onedrive.css"/>
</head>
<body>

<!-- ══════════════════════════════════════════
     LOGIN SCREEN  (shown before auth)
══════════════════════════════════════════ -->
<div id="login-screen">
  <button class="login-back-btn" id="login-back-btn" onclick="history.back()" title="Go back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>
    </svg>
    Back
  </button>
  <div class="login-card">
    <!-- DepEd logo — same as main site hero -->
    <div class="logo-deped">
      <img class="logo-deped-img"
           src="../assets/img/depedcarcarlogo.jpg"
           alt="DepEd Carcar City logo"
           onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
      <!-- Fallback shield rendered via CSS, hidden by default -->
      <div class="logo-shield" style="display:none;">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
    </div>
    <h1>DepEd Carcar City LRMDS</h1>
    <p>Sign in with your Microsoft / DepEd account to browse and download learning resources.</p>
    <button class="ms-login-btn" id="login-btn">
      <svg width="20" height="20" viewBox="0 0 21 21" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="1"  y="1"  width="9" height="9" fill="#f25022"/>
        <rect x="11" y="1"  width="9" height="9" fill="#7fba00"/>
        <rect x="1"  y="11" width="9" height="9" fill="#00a4ef"/>
        <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
      </svg>
      Sign in with Microsoft
    </button>
  </div>
</div>

<!-- ══════════════════════════════════════════
     APP SHELL  (hidden until signed in)
══════════════════════════════════════════ -->
<div id="app-shell">

  <!-- ── Header ── -->
  <header class="site-header">

    <!-- Logo — matches main site branding -->
    <a class="logo" href="#" onclick="event.preventDefault();clearSearch();" title="Return to home">
      <div class="logo-img-wrap">
        <img class="logo-img"
             src="../assets/img/depedcarcarlogo.jpg"
             alt="DepEd Carcar City"
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"/>
        <!-- Fallback shield -->
        <div class="logo-shield" style="display:none;">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
      </div>
      <div class="logo-text">
        <span class="logo-title">DepEd Carcar City LRMDS</span>
        <span class="logo-sub">LEARNING RESOURCES</span>
      </div>
    </a>

    <div class="header-right">
      <nav class="header-nav">
        <!-- "Home" replaced with "Return to Main Site" -->
        <a class="nav-link return-link"
           href="http://localhost/deped-lrmds-portal/index.php"
           title="Go back to the main LRMDS portal">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>
          </svg>
          Main Site
        </a>

        <!--
          The "My Library" nav link is injected here automatically
          by analytics.js after the user signs in (initLibraryTab).
          No static entry needed — it will appear once currentUser is set.
        -->
      </nav>

      <div class="user-pill">
        <div class="user-avatar" id="user-initial">?</div>
        <span id="user-name">Loading…</span>
        <button class="logout-btn" id="logout-btn" title="Sign out">✕</button>
      </div>
    </div>
  </header>

  <!-- ── Filter / search bar ── -->
  <div class="filter-bar">
    <div class="filters">
      <input  class="input"  id="search-input"   type="search"
              placeholder="Search by keyword, MELC code, filename…" aria-label="Search"/>

      <select class="select" id="filter-grade" aria-label="Grade level">
        <option value="">All Grades</option>
        <option value="Kinder">Kinder</option>
        <option value="Grade 1">Grade 1</option>
        <option value="Grade 2">Grade 2</option>
        <option value="Grade 3">Grade 3</option>
        <option value="Grade 4">Grade 4</option>
        <option value="Grade 5">Grade 5</option>
        <option value="Grade 6">Grade 6</option>
        <option value="Grade 7">Grade 7</option>
        <option value="Grade 8">Grade 8</option>
        <option value="Grade 9">Grade 9</option>
        <option value="Grade 10">Grade 10</option>
        <option value="Grade 11">Grade 11</option>
        <option value="Grade 12">Grade 12</option>
      </select>

      <select class="select" id="filter-subject" aria-label="Subject">
        <option value="">All Subjects</option>
        <option value="English">English</option>
        <option value="Filipino">Filipino</option>
        <option value="Mathematics">Mathematics</option>
        <option value="Science">Science</option>
        <option value="Araling Panlipunan">Araling Panlipunan</option>
        <option value="MAPEH">MAPEH</option>
        <option value="EPP/TLE">EPP/TLE</option>
      </select>

      <select class="select" id="filter-quarter" aria-label="Quarter">
        <option value="">All Quarters</option>
        <option value="Quarter 1">Quarter 1</option>
        <option value="Quarter 2">Quarter 2</option>
        <option value="Quarter 3">Quarter 3</option>
        <option value="Quarter 4">Quarter 4</option>
      </select>

      <select class="select" id="filter-type" aria-label="File type">
        <option value="">All File Types</option>
        <option value="pdf">PDF</option>
        <option value="docx">Word (DOCX)</option>
        <option value="doc">Word (DOC)</option>
        <option value="pptx">PowerPoint (PPTX)</option>
        <option value="ppt">PowerPoint (PPT)</option>
        <option value="xlsx">Excel (XLSX)</option>
        <option value="xls">Excel (XLS)</option>
        <option value="mp4">Video (MP4)</option>
        <option value="mp3">Audio (MP3)</option>
        <option value="jpg">Image (JPG)</option>
        <option value="png">Image (PNG)</option>
      </select>

      <button class="button primary" onclick="applySearch()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        Search
      </button>
      <button class="button ghost" onclick="clearSearch()">Clear</button>
    </div>
  </div>

  <!-- ── Page body ── -->
  <div class="page-body">

    <!-- Info banner -->
    <div class="note-banner">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2" stroke-linecap="round">
        <circle cx="12" cy="12" r="10"/>
        <line x1="12" y1="8" x2="12" y2="12"/>
        <line x1="12" y1="16" x2="12.01" y2="16"/>
      </svg>
      Files load live from OneDrive via Microsoft Graph API. Search scans all subfolders automatically.
      Use the <strong>Save to My Library</strong> button (📄 icon) on any file card to save it for quick access.
    </div>

    <!-- Breadcrumb -->
    <nav class="breadcrumb-row" id="breadcrumb" aria-label="Breadcrumb"></nav>

    <!-- ── Results ── -->
    <section class="results-pane">

      <!-- Back bar -->
      <div class="back-bar" id="back-bar">
        <button onclick="goBack()">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.5" stroke-linecap="round">
            <path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>
          </svg>
          Back
        </button>
        <span id="current-folder-name"></span>
      </div>

      <!-- Results header -->
      <div class="results-header">
        <div>
          <div class="results-title" id="results-title">Resources</div>
          <div class="results-meta" id="results-meta"></div>
        </div>
        <div class="view-toggle">
          <button class="view-btn active" id="btn-grid-view" title="Grid view">⊞</button>
          <button class="view-btn"        id="btn-list-view" title="List view">☰</button>
        </div>
      </div>

      <!-- Results grid (populated by JS) -->
      <div class="results-grid" id="results" aria-live="polite">
        <div class="loading-state">
          <div class="spinner"></div>
          <span>Waiting for sign-in…</span>
        </div>
      </div>

    </section>
  </div><!-- /page-body -->

</div><!-- /app-shell -->

<div class="toast" id="toast"></div>

<!-- App logic — load onedrive first, then library, then analytics -->
<script src="../assets/js/onedrive.js"></script>
<script src="../assets/js/mylibrary.js"></script>
<script src="../assets/js/analytics.js"></script>
<script>
/* ── Auto-preview: triggered when navigating from admin dashboard top-files ── */
(function () {
  const raw = sessionStorage.getItem('lrmds_preview_item');
  if (!raw) return;
  // Only consume if the URL hash matches
  if (!window.location.hash.startsWith('#preview=')) return;
  sessionStorage.removeItem('lrmds_preview_item');
  try {
    const item = JSON.parse(raw);
    // Wait until onedrive.js has initialised openPreview (after sign-in)
    const MAX_WAIT = 15000;
    const start    = Date.now();
    const poll     = setInterval(() => {
      if (typeof window.openPreview === 'function' && window.currentUser) {
        clearInterval(poll);
        // Small delay so the folder renders first
        setTimeout(() => window.openPreview(item), 800);
      } else if (Date.now() - start > MAX_WAIT) {
        clearInterval(poll);
      }
    }, 200);
  } catch (_) {}
})();
</script>
</body>
</html>