<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>DepEd Carcar City LRMDS – Resources</title>

<!-- MSAL 2 — Microsoft Authentication Library (CDN, no server needed) -->
<script src="https://cdn.jsdelivr.net/npm/@azure/msal-browser@2.38.3/lib/msal-browser.min.js"></script>

<link rel="stylesheet" href="assets/css/prototype2.css"/>
</head>
<body>

<!-- ══════════════════════════════════════════
     LOGIN SCREEN  (shown before auth)
══════════════════════════════════════════ -->
<div id="login-screen">
  <div class="login-card">
    <div class="logo-shield">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"
           stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
    </div>
    <h1>DepEd Carcar City LRMDS</h1>
    <p>Sign in with your Microsoft / DepEd account to browse and download learning resources.</p>
    <button class="ms-login-btn" id="login-btn">
      <!-- Microsoft "M" icon (inline SVG, no external IP) -->
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
    <a class="logo" href="#">
      <div class="logo-shield">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      DepEd Carcar City LRMDS
    </a>
    <div class="header-right">
      <nav class="header-nav">
        <a class="nav-link" href="#">Home</a>
        <a class="nav-link active" href="#">Resources</a>
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

      <select class="select" id="filter-grade"   aria-label="Grade level">
        <option value="">All Grades</option>
        <option>Kinder</option>
        <option>Grade 1</option>  <option>Grade 2</option>  <option>Grade 3</option>
        <option>Grade 4</option>  <option>Grade 5</option>  <option>Grade 6</option>
        <option>Grade 7</option>  <option>Grade 8</option>  <option>Grade 9</option>
        <option>Grade 10</option> <option>Grade 11</option> <option>Grade 12</option>
      </select>

      <select class="select" id="filter-subject" aria-label="Subject">
        <option value="">All Subjects</option>
        <option>English</option>
        <option>Filipino</option>
        <option>Mathematics</option>
        <option>Science</option>
        <option>Araling Panlipunan</option>
        <option>MAPEH</option>
        <option>EPP/TLE</option>
      </select>

      <select class="select" id="filter-type" aria-label="Resource type">
        <option value="">All Types</option>
        <option>SLM</option>
        <option>TG</option>
        <option>DLL</option>
        <option>Video</option>
        <option>Assessment</option>
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
    </div>

    <!-- Breadcrumb -->
    <nav class="breadcrumb-row" id="breadcrumb" aria-label="Breadcrumb"></nav>

    <!-- ── Sidebar ── -->
    <aside class="sidebar">
      <div class="sidebar-heading">Subject</div>
      <ul class="facet-list" id="facet-subject"></ul>

      <div class="sidebar-heading">Grade Level</div>
      <ul class="facet-list" id="facet-grade"></ul>

      <div class="sidebar-heading">Resource Type</div>
      <ul class="facet-list" id="facet-type"></ul>
    </aside>

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

<!-- App logic -->
<script src="assets/js/prototype2.js"></script>
</body>
</html>