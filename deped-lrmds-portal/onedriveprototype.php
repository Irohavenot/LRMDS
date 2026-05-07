<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>DepEd LRMDS – Resources (OneDrive Prototype)</title>
<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --primary:   #1D4ED8;
  --primary-lt:#EFF6FF;
  --text:      #111827;
  --muted:     #6B7280;
  --border:    #E5E7EB;
  --bg-soft:   #F9FAFB;
  --success-bg:#D1FAE5; --success-fg:#065F46;
  --warn-bg:   #FEF3C7; --warn-fg:   #92400E;
  --radius:    10px;
  --font: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

body {
  font-family: var(--font);
  color: var(--text);
  background: var(--bg-soft);
  min-height: 100vh;
}

/* ── Header ── */
.site-header {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  height: 56px;
  position: sticky;
  top: 0;
  z-index: 100;
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 700;
  font-size: 15px;
  color: var(--primary);
  text-decoration: none;
}
.logo-shield {
  width: 32px; height: 32px;
  background: var(--primary);
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
}
.logo-shield svg { width: 18px; height: 18px; }
.header-nav { display: flex; gap: 4px; }
.nav-link {
  padding: 6px 14px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  color: var(--muted);
  text-decoration: none;
  transition: background .15s, color .15s;
  cursor: pointer;
}
.nav-link:hover, .nav-link.active { background: var(--primary-lt); color: var(--primary); }

/* ── Filter bar ── */
.filter-bar {
  background: #fff;
  border-bottom: 1px solid var(--border);
  padding: 14px 24px;
}
.filters {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  max-width: 1400px;
  margin: 0 auto;
}
.input, .select {
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  font-size: 13px;
  font-family: var(--font);
  color: var(--text);
  background: #fff;
  outline: none;
  transition: border-color .15s;
}
.input:focus, .select:focus { border-color: var(--primary); }
.input { flex: 1; min-width: 180px; }
.select { min-width: 140px; }
.button {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 18px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  font-family: var(--font);
  cursor: pointer;
  border: 1px solid transparent;
  transition: all .15s;
  text-decoration: none;
}
.button.primary { background: var(--primary); color: #fff; }
.button.primary:hover { background: #1e40af; }
.button.ghost {
  background: #fff;
  color: var(--text);
  border-color: var(--border);
}
.button.ghost:hover { background: var(--bg-soft); }
.button.small { padding: 5px 12px; font-size: 12px; }

/* ── Main layout ── */
.page-body {
  max-width: 1400px;
  margin: 0 auto;
  padding: 24px;
  display: grid;
  grid-template-columns: 220px 1fr;
  gap: 24px;
  align-items: start;
}

/* ── Breadcrumb ── */
.breadcrumb-row {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--muted);
  flex-wrap: wrap;
}
.breadcrumb-row a, .bc-crumb {
  color: var(--muted);
  text-decoration: none;
  cursor: pointer;
  transition: color .15s;
}
.breadcrumb-row a:hover, .bc-crumb:hover { color: var(--primary); }
.breadcrumb-row .bc-current { color: var(--text); font-weight: 600; }
.bc-sep { color: var(--border); font-size: 15px; }

/* ── Sidebar ── */
.sidebar {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px;
}
.sidebar-heading {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--muted);
  margin-bottom: 8px;
  margin-top: 16px;
}
.sidebar-heading:first-child { margin-top: 0; }
.facet-list { list-style: none; display: flex; flex-direction: column; gap: 2px; }
.facet-btn {
  display: flex;
  justify-content: space-between;
  align-items: center;
  width: 100%;
  background: none;
  border: none;
  padding: 5px 8px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-family: var(--font);
  color: #374151;
  transition: background .15s;
  text-align: left;
}
.facet-btn:hover { background: #F3F4F6; }
.facet-btn.active { background: var(--primary-lt); color: var(--primary); font-weight: 600; }
.facet-count {
  font-size: 11px;
  color: #9CA3AF;
  background: #F3F4F6;
  border-radius: 9px;
  padding: 1px 7px;
}
.facet-btn.active .facet-count { background: #DBEAFE; color: var(--primary); }

/* ── Results pane ── */
.results-pane { min-width: 0; }

.results-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 14px;
  flex-wrap: wrap;
  gap: 8px;
}
.results-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--text);
}
.results-meta {
  font-size: 13px;
  color: var(--muted);
}
.view-toggle {
  display: flex;
  gap: 4px;
}
.view-btn {
  padding: 5px 8px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  font-size: 14px;
  color: var(--muted);
  transition: all .15s;
}
.view-btn.active { background: var(--primary-lt); color: var(--primary); border-color: #BFDBFE; }

/* ── Results grid ── */
.results-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
}

/* ── FOLDER card ── */
.folder-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 14px;
  cursor: pointer;
  transition: box-shadow .2s, border-color .2s;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.folder-card:hover {
  box-shadow: 0 4px 16px rgba(18,7,145,.12);
  border-color: #BFDBFE;
}
.folder-icon-wrap {
  width: 44px; height: 44px;
  background: #FFF7ED;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
}
.folder-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.35;
  word-break: break-word;
}
.folder-meta {
  font-size: 11px;
  color: var(--muted);
  display: flex;
  align-items: center;
  gap: 8px;
}
.folder-chip {
  background: #FFF7ED;
  color: #C2410C;
  font-size: 10px;
  font-weight: 700;
  border-radius: 4px;
  padding: 2px 6px;
  border: 1px solid #FED7AA;
}

/* ── FILE / resource card ── */
.result-card {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 10px;
  display: flex;
  gap: 10px;
  transition: box-shadow .2s;
  cursor: pointer;
}
.result-card:hover { box-shadow: 0 4px 16px rgba(18,7,145,.18); }

.thumb-wrap {
  position: relative;
  flex-shrink: 0;
  width: 76px; height: 76px;
  border-radius: 8px;
  background: #F3F4F6;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  overflow: hidden;
}
.thumb-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 8px;
}
.type-badge {
  position: absolute;
  bottom: 4px; left: 4px;
  background: rgba(0,0,0,.6);
  color: #fff;
  font-size: 9px;
  font-weight: 700;
  border-radius: 3px;
  padding: 2px 4px;
}

.card-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }

.tag-row { display: flex; gap: 4px; flex-wrap: wrap; }
.tag {
  background: var(--primary-lt);
  color: var(--primary);
  font-size: 10px;
  font-weight: 700;
  border-radius: 4px;
  padding: 2px 6px;
}
.tag.secondary { background: #F3F4F6; color: #6B7280; }
.tag.success { background: var(--success-bg); color: var(--success-fg); }
.tag.warn { background: var(--warn-bg); color: var(--warn-fg); }

.card-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--text);
  line-height: 1.4;
  /* Clamp to 2 lines */
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.card-detail {
  font-size: 11px;
  color: var(--muted);
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  align-items: center;
}
.card-detail code {
  font-size: 10px;
  background: #F3F4F6;
  padding: 1px 4px;
  border-radius: 3px;
  font-family: monospace;
}
.sep { color: var(--border); }
.card-actions {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
  margin-top: 2px;
}

/* ── Empty state ── */
.empty-state {
  grid-column: 1 / -1;
  text-align: center;
  padding: 48px 24px;
  color: var(--muted);
}
.empty-state .empty-icon { font-size: 40px; margin-bottom: 12px; }
.empty-state p { font-size: 13px; margin-top: 6px; }

/* ── Back bar (shown when inside a folder) ── */
.back-bar {
  display: none;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  background: var(--primary-lt);
  border: 1px solid #BFDBFE;
  border-radius: 8px;
  margin-bottom: 14px;
  font-size: 13px;
  color: var(--primary);
  font-weight: 600;
}
.back-bar.visible { display: flex; }
.back-bar button {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  color: var(--primary);
  display: flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font);
  padding: 4px 10px;
  border-radius: 6px;
  transition: background .15s;
}
.back-bar button:hover { background: #DBEAFE; }

/* ── Notification toast ── */
.toast {
  position: fixed;
  bottom: 24px; right: 24px;
  background: #111827;
  color: #fff;
  font-size: 13px;
  padding: 10px 18px;
  border-radius: 8px;
  z-index: 999;
  opacity: 0;
  transform: translateY(10px);
  transition: all .25s;
  pointer-events: none;
}
.toast.show { opacity: 1; transform: translateY(0); }

/* ── Note banner ── */
.note-banner {
  background: #FFF7ED;
  border: 1px solid #FED7AA;
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 12px;
  color: #C2410C;
  margin-bottom: 16px;
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ── Responsive ── */
@media (max-width: 900px) {
  .page-body { grid-template-columns: 1fr; }
  .sidebar { display: none; }
  .results-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 560px) {
  .results-grid { grid-template-columns: 1fr; }
}
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

<!-- ── Filter bar ── -->
<div class="filter-bar">
  <div class="filters">
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
      <option>English</option>
      <option>Filipino</option>
      <option>Mathematics</option>
      <option>Science</option>
      <option>Araling Panlipunan</option>
      <option>MAPEH</option>
      <option>EPP/TLE</option>
    </select>
    <select class="select" id="filter-type" aria-label="Type">
      <option value="">All Types</option>
      <option>SLM</option>
      <option>TG</option>
      <option>DLL</option>
      <option>Video</option>
      <option>Assessment</option>
    </select>
    <button class="button primary" onclick="applySearch()">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      Search
    </button>
    <button class="button ghost" onclick="clearSearch()">Clear</button>
  </div>
</div>

<!-- ── Main ── -->
<div class="page-body">

  <!-- Prototype notice -->
  <div class="note-banner">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <strong>Prototype:</strong>&nbsp;This uses dummy data shaped exactly like what Microsoft Graph API returns from OneDrive. In production, folders and files here will come live from your OneDrive.
  </div>

  <!-- Breadcrumb -->
  <nav class="breadcrumb-row" id="breadcrumb" aria-label="Breadcrumb">
    <a onclick="navigateTo(null)">Home</a>
    <span class="bc-sep">›</span>
    <span class="bc-current">Resources</span>
  </nav>

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
    <div class="back-bar" id="back-bar">
      <button onclick="goBack()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M19 12H5"/><path d="m12 5-7 7 7 7"/></svg>
        Back
      </button>
      <span id="current-folder-name">Folder</span>
    </div>

    <div class="results-header">
      <div>
        <div class="results-title" id="results-title">All Resources</div>
        <div class="results-meta" id="results-meta">Browsing from OneDrive · Carcar City Division</div>
      </div>
      <div class="view-toggle">
        <button class="view-btn active" title="Grid view">⊞</button>
        <button class="view-btn" title="List view">☰</button>
      </div>
    </div>

    <div class="results-grid" id="results" aria-live="polite"></div>
  </section>

</div>

<div class="toast" id="toast"></div>

<script>
/* ═══════════════════════════════════════════════════════
   DATA — shaped exactly like Microsoft Graph API response
   In production, this comes from: GET /v1.0/users/{email}/drive/root:/{path}:/children
═══════════════════════════════════════════════════════ */

const DRIVE_DATA = {
  // Root level — what Graph API returns for the base folder
  null: {
    items: [
      // FOLDERS (Graph: item.folder exists, item.file is undefined)
      { id: 'f001', name: 'Grade 6 – Mathematics',    folder: { childCount: 14 }, subject:'Mathematics', grade:'Grade 6' },
      { id: 'f002', name: 'Grade 6 – Science',         folder: { childCount: 9  }, subject:'Science',     grade:'Grade 6' },
      { id: 'f003', name: 'Grade 6 – English',         folder: { childCount: 11 }, subject:'English',     grade:'Grade 6' },
      { id: 'f004', name: 'Grade 5 – Mathematics',    folder: { childCount: 8  }, subject:'Mathematics', grade:'Grade 5' },
      { id: 'f005', name: 'Grade 5 – Science',         folder: { childCount: 6  }, subject:'Science',     grade:'Grade 5' },
      { id: 'f006', name: 'Grade 4 – Filipino',        folder: { childCount: 7  }, subject:'Filipino',    grade:'Grade 4' },
      { id: 'f007', name: 'Grade 7 – Araling Panlipunan', folder: { childCount: 5 }, subject:'Araling Panlipunan', grade:'Grade 7' },
      { id: 'f008', name: 'Grade 8 – Science',         folder: { childCount: 10 }, subject:'Science',     grade:'Grade 8' },
    ]
  },

  // Inside Grade 6 – Mathematics
  'f001': {
    parentId: null,
    parentName: 'All Resources',
    items: [
      { id: 'f001a', name: 'Quarter 1 – Fractions',     folder: { childCount: 5 } },
      { id: 'f001b', name: 'Quarter 2 – Decimals',      folder: { childCount: 4 } },
      { id: 'f001c', name: 'Quarter 3 – Geometry',      folder: { childCount: 6 } },
      { id: 'f001d', name: 'Quarter 4 – Statistics',    folder: { childCount: 3 } },
    ]
  },

  // Inside Quarter 1 – Fractions (actual files)
  'f001a': {
    parentId: 'f001',
    parentName: 'Grade 6 – Mathematics',
    items: [
      {
        id: 'r001',
        name: 'SLM_G6_Math_Q1_W1_Fractions-and-Mixed-Numbers.pdf',
        file: { mimeType: 'application/pdf' },
        size: 3145728,
        webUrl: '#',
        meta: { title: 'Fractions and Mixed Numbers – Grade 6 SLM', grade:'Grade 6', subject:'Mathematics', type:'SLM', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id: 'r002',
        name: 'TG_G6_Math_Q1_W1_Teacher-Guide.pdf',
        file: { mimeType: 'application/pdf' },
        size: 2097152,
        webUrl: '#',
        meta: { title: "Teacher's Guide – Fractions Q1 W1", grade:'Grade 6', subject:'Mathematics', type:'TG', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id: 'r003',
        name: 'DLL_G6_Math_Q1_W1.docx',
        file: { mimeType: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' },
        size: 524288,
        webUrl: '#',
        meta: { title: 'Daily Lesson Log – G6 Math Q1 W1', grade:'Grade 6', subject:'Mathematics', type:'DLL', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'pending' }
      },
      {
        id: 'r004',
        name: 'Assessment_G6_Math_Q1_W1_Fractions.pdf',
        file: { mimeType: 'application/pdf' },
        size: 1048576,
        webUrl: '#',
        meta: { title: 'Weekly Assessment – Fractions Grade 6', grade:'Grade 6', subject:'Mathematics', type:'Assessment', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
      {
        id: 'r005',
        name: 'VIDEO_G6_Math_Q1_W1_Fractions-Lesson.mp4',
        file: { mimeType: 'video/mp4' },
        size: 52428800,
        webUrl: '#',
        meta: { title: 'Video Lesson – Fractions and Mixed Numbers', grade:'Grade 6', subject:'Mathematics', type:'Video', melc:'M6NS-Ia-1', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  // Inside Grade 6 – Science (subfolder + files)
  'f002': {
    parentId: null,
    parentName: 'All Resources',
    items: [
      { id: 'f002a', name: 'Quarter 1 – Living Things',  folder: { childCount: 4 } },
      { id: 'f002b', name: 'Quarter 2 – Matter',         folder: { childCount: 5 } },
      {
        id: 'r010',
        name: 'SLM_G6_Sci_Q1_Introduction.pdf',
        file: { mimeType: 'application/pdf' },
        size: 2621440,
        webUrl: '#',
        meta: { title: 'Introduction to Grade 6 Science – SLM', grade:'Grade 6', subject:'Science', type:'SLM', melc:'S6LT-Ia-b-1', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  // Inside Grade 6 – English
  'f003': {
    parentId: null,
    parentName: 'All Resources',
    items: [
      { id: 'f003a', name: 'Quarter 1 – Reading Comprehension', folder: { childCount: 6 } },
      { id: 'f003b', name: 'Quarter 2 – Grammar',               folder: { childCount: 4 } },
      {
        id: 'r020',
        name: 'SLM_G6_Eng_Q1_W1_Reading-Skills.pdf',
        file: { mimeType: 'application/pdf' },
        size: 1835008,
        webUrl: '#',
        meta: { title: 'Reading Skills – Grade 6 English SLM Q1', grade:'Grade 6', subject:'English', type:'SLM', melc:'EN6RC-Ia-2.2.2', quarter:'Quarter 1', qa:'passed' }
      },
    ]
  },

  // Placeholder for other folders
  'f004': { parentId: null, parentName: 'All Resources', items: [{ id: 'f004a', name: 'Quarter 1', folder:{ childCount: 4 } }, { id: 'f004b', name: 'Quarter 2', folder:{ childCount: 3 } }] },
  'f005': { parentId: null, parentName: 'All Resources', items: [{ id: 'f005a', name: 'Quarter 1', folder:{ childCount: 3 } }] },
  'f006': { parentId: null, parentName: 'All Resources', items: [{ id: 'f006a', name: 'Quarter 1', folder:{ childCount: 4 } }] },
  'f007': { parentId: null, parentName: 'All Resources', items: [{ id: 'f007a', name: 'Quarter 1', folder:{ childCount: 3 } }] },
  'f008': { parentId: null, parentName: 'All Resources', items: [{ id: 'f008a', name: 'Quarter 1', folder:{ childCount: 5 } }] },
  'f001b': { parentId: 'f001', parentName: 'Grade 6 – Mathematics', items: [] },
  'f001c': { parentId: 'f001', parentName: 'Grade 6 – Mathematics', items: [] },
  'f001d': { parentId: 'f001', parentName: 'Grade 6 – Mathematics', items: [] },
  'f002a': { parentId: 'f002', parentName: 'Grade 6 – Science', items: [] },
  'f002b': { parentId: 'f002', parentName: 'Grade 6 – Science', items: [] },
  'f003a': { parentId: 'f003', parentName: 'Grade 6 – English', items: [] },
  'f003b': { parentId: 'f003', parentName: 'Grade 6 – English', items: [] },
  'f004a': { parentId: 'f004', parentName: 'Grade 5 – Mathematics', items: [] },
  'f004b': { parentId: 'f004', parentName: 'Grade 5 – Mathematics', items: [] },
  'f005a': { parentId: 'f005', parentName: 'Grade 5 – Science', items: [] },
  'f006a': { parentId: 'f006', parentName: 'Grade 4 – Filipino', items: [] },
  'f007a': { parentId: 'f007', parentName: 'Grade 7 – Araling Panlipunan', items: [] },
  'f008a': { parentId: 'f008', parentName: 'Grade 8 – Science', items: [] },
};

/* ═══════════════════════════════════════════════════════
   STATE
═══════════════════════════════════════════════════════ */
let currentFolder    = null;      // null = root
let folderHistory    = [];        // navigation stack
let activeFilters    = { subject: '', grade: '', type: '', q: '' };

/* ═══════════════════════════════════════════════════════
   HELPERS
═══════════════════════════════════════════════════════ */
function fileIcon(mime) {
  if (!mime) return '📁';
  if (mime.includes('pdf'))  return '📄';
  if (mime.includes('video')) return '🎬';
  if (mime.includes('word') || mime.includes('docx')) return '📝';
  if (mime.includes('sheet') || mime.includes('xlsx')) return '📊';
  if (mime.includes('presentation') || mime.includes('pptx')) return '📑';
  if (mime.includes('image')) return '🖼️';
  if (mime.includes('audio')) return '🎵';
  return '📎';
}

function fileExt(name) {
  return name.split('.').pop().toUpperCase();
}

function formatSize(bytes) {
  if (!bytes) return '';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(0) + ' KB';
  return (bytes / 1024 / 1024).toFixed(1) + ' MB';
}

function extractType(name) {
  const n = name.toUpperCase();
  if (n.startsWith('SLM')) return 'SLM';
  if (n.startsWith('TG'))  return 'TG';
  if (n.startsWith('DLL')) return 'DLL';
  if (n.startsWith('ASSESSMENT')) return 'Assessment';
  if (n.startsWith('VIDEO')) return 'Video';
  return 'Resource';
}

function showToast(msg) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}

/* ═══════════════════════════════════════════════════════
   NAVIGATION
═══════════════════════════════════════════════════════ */
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
  if (folderHistory.length === 0) return;
  const prev = folderHistory.pop();
  currentFolder = prev.id;
  renderAll(prev.name || 'All Resources');
}

/* ═══════════════════════════════════════════════════════
   SEARCH
═══════════════════════════════════════════════════════ */
function applySearch() {
  activeFilters.q       = document.getElementById('search-input').value.trim().toLowerCase();
  activeFilters.grade   = document.getElementById('filter-grade').value;
  activeFilters.subject = document.getElementById('filter-subject').value;
  activeFilters.type    = document.getElementById('filter-type').value;
  // If searching, start from root and show matching files
  currentFolder = null;
  folderHistory = [];
  renderAll('Search Results');
}

function clearSearch() {
  document.getElementById('search-input').value = '';
  document.getElementById('filter-grade').value = '';
  document.getElementById('filter-subject').value = '';
  document.getElementById('filter-type').value = '';
  activeFilters = { subject: '', grade: '', type: '', q: '' };
  currentFolder = null;
  folderHistory = [];
  renderAll('All Resources');
}

document.getElementById('search-input').addEventListener('keydown', e => {
  if (e.key === 'Enter') applySearch();
});

/* ═══════════════════════════════════════════════════════
   FILTER ITEMS
═══════════════════════════════════════════════════════ */
function matchesFilters(item) {
  const { q, grade, subject, type } = activeFilters;
  if (!q && !grade && !subject && !type) return true;

  // For files with meta
  if (item.file && item.meta) {
    const m = item.meta;
    if (q && !m.title.toLowerCase().includes(q) && !item.name.toLowerCase().includes(q) && !(m.melc||'').toLowerCase().includes(q)) return false;
    if (grade   && m.grade   !== grade)   return false;
    if (subject && m.subject !== subject) return false;
    if (type    && m.type    !== type)    return false;
    return true;
  }

  // For folders — check folder name / subject/grade tags
  if (item.folder) {
    const name = item.name.toLowerCase();
    if (q && !name.includes(q)) {
      if (subject && !(item.subject || '').includes(subject)) return false;
      if (!q) return true;
      return false;
    }
    if (grade   && item.grade   && item.grade   !== grade)   return false;
    if (subject && item.subject && item.subject !== subject) return false;
    return true;
  }

  return true;
}

/* Search recursively through all files for search mode */
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

/* ═══════════════════════════════════════════════════════
   RENDER
═══════════════════════════════════════════════════════ */
function renderAll(titleText) {
  updateBreadcrumb();
  updateBackBar(titleText);
  document.getElementById('results-title').textContent = titleText || 'All Resources';

  const data   = DRIVE_DATA[currentFolder];
  const isSearch = activeFilters.q || activeFilters.grade || activeFilters.subject || activeFilters.type;

  let items;
  if (isSearch) {
    items = collectAllFiles().filter(matchesFilters);
  } else {
    items = data ? data.items.filter(matchesFilters) : [];
  }

  const grid = document.getElementById('results');
  grid.innerHTML = '';

  if (items.length === 0) {
    grid.innerHTML = `
      <div class="empty-state">
        <div class="empty-icon">🔍</div>
        <strong>No resources found</strong>
        <p>Try adjusting your filters or browse a different folder.</p>
      </div>`;
    document.getElementById('results-meta').textContent = '0 items found';
    updateSidebar(items);
    return;
  }

  document.getElementById('results-meta').textContent =
    isSearch ? `${items.length} file${items.length !== 1 ? 's' : ''} found across all folders`
             : `${items.length} item${items.length !== 1 ? 's' : ''} in this folder`;

  for (const item of items) {
    const card = item.folder ? renderFolderCard(item) : renderFileCard(item);
    grid.appendChild(card);
  }

  updateSidebar(items);
}

function renderFolderCard(item) {
  const div = document.createElement('div');
  div.className = 'folder-card';
  div.setAttribute('role', 'button');
  div.setAttribute('tabindex', '0');
  div.setAttribute('aria-label', `Open folder: ${item.name}`);

  const subjectChip = item.subject
    ? `<span class="folder-chip">${item.subject}</span>`
    : '';
  const gradeChip = item.grade
    ? `<span style="font-size:10px;color:var(--muted);">${item.grade}</span>`
    : '';

  div.innerHTML = `
    <div class="folder-icon-wrap">📁</div>
    <div class="folder-name">${item.name}</div>
    <div class="folder-meta">
      ${subjectChip}
      ${gradeChip}
      <span style="margin-left:auto;">${item.folder.childCount} items ›</span>
    </div>
  `;

  const open = () => navigateTo(item.id, item.name);
  div.addEventListener('click', open);
  div.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') open(); });
  return div;
}

function renderFileCard(item) {
  const div  = document.createElement('div');
  div.className = 'result-card';

  const meta  = item.meta || {};
  const ext   = fileExt(item.name);
  const icon  = fileIcon(item.file?.mimeType);
  const title = meta.title || item.name.replace(/\.[^.]+$/, '').replace(/_/g, ' ');
  const qa    = meta.qa === 'passed' ? `<span class="tag success">✔ QA</span>`
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
        <a href="${item.webUrl}" target="_blank" class="button ghost small" onclick="event.stopPropagation()">Open ↗</a>
        <a href="${item.webUrl}" class="button primary small" download onclick="event.stopPropagation();showToast('Download starting…')">⬇ Download</a>
      </div>
    </div>
  `;

  div.addEventListener('click', () => {
    // In production: window.location.href = `resource.php?id=${item.id}`
    showToast(`Opening: ${title}`);
  });

  return div;
}

/* ═══════════════════════════════════════════════════════
   BREADCRUMB
═══════════════════════════════════════════════════════ */
function updateBreadcrumb() {
  const bc = document.getElementById('breadcrumb');
  let html = `<a onclick="navigateTo(null)">Home</a><span class="bc-sep">›</span>`;

  if (folderHistory.length === 0 && currentFolder === null) {
    html += `<span class="bc-current">Resources</span>`;
  } else {
    html += `<span class="bc-crumb" onclick="navigateTo(null)">Resources</span>`;
    for (let i = 0; i < folderHistory.length; i++) {
      const h = folderHistory[i];
      html += `<span class="bc-sep">›</span>`;
      const idx = i;
      html += `<span class="bc-crumb" data-idx="${idx}">${h.name || 'Folder'}</span>`;
    }
    if (currentFolder) {
      const data = DRIVE_DATA[currentFolder];
      html += `<span class="bc-sep">›</span><span class="bc-current" id="bc-active"></span>`;
    }
  }

  bc.innerHTML = html;

  // Attach click handlers for history items
  bc.querySelectorAll('[data-idx]').forEach(el => {
    const idx = parseInt(el.getAttribute('data-idx'));
    el.addEventListener('click', () => {
      // Navigate back to that history index
      const target = folderHistory[idx];
      folderHistory = folderHistory.slice(0, idx);
      currentFolder = target.id;
      renderAll(target.name || 'All Resources');
    });
  });

  // Set current folder name in breadcrumb
  if (currentFolder) {
    const nameEl = document.getElementById('bc-active');
    if (nameEl) {
      // Get from parent data
      const parentData = DRIVE_DATA[DRIVE_DATA[currentFolder]?.parentId ?? null];
      const match = parentData?.items.find(i => i.id === currentFolder);
      if (nameEl) nameEl.textContent = match?.name || 'Folder';
    }
  }
}

/* ═══════════════════════════════════════════════════════
   BACK BAR
═══════════════════════════════════════════════════════ */
function updateBackBar(titleText) {
  const bar = document.getElementById('back-bar');
  const nameEl = document.getElementById('current-folder-name');
  if (currentFolder !== null) {
    bar.classList.add('visible');
    nameEl.textContent = titleText || 'Folder';
  } else {
    bar.classList.remove('visible');
  }
}

/* ═══════════════════════════════════════════════════════
   SIDEBAR FACETS
═══════════════════════════════════════════════════════ */
function updateSidebar(items) {
  buildFacet('facet-subject', items, i => i.meta?.subject || i.subject, 'subject');
  buildFacet('facet-grade',   items, i => i.meta?.grade   || i.grade,   'grade');
  buildFacet('facet-type',    items, i => i.meta?.type,                  'type', true);
}

function buildFacet(elId, items, extract, filterKey, filesOnly = false) {
  const counts = {};
  for (const item of items) {
    if (filesOnly && !item.file) continue;
    const val = extract(item);
    if (val) counts[val] = (counts[val] || 0) + 1;
  }

  const el = document.getElementById(elId);
  el.innerHTML = '';

  if (Object.keys(counts).length === 0) {
    el.innerHTML = `<li><span style="font-size:12px;color:var(--muted);padding:4px 8px;display:block">None</span></li>`;
    return;
  }

  for (const [val, count] of Object.entries(counts)) {
    const li  = document.createElement('li');
    const btn = document.createElement('button');
    btn.className = 'facet-btn' + (activeFilters[filterKey] === val ? ' active' : '');
    btn.innerHTML = `<span>${val}</span><span class="facet-count">${count}</span>`;
    btn.addEventListener('click', () => {
      activeFilters[filterKey] = activeFilters[filterKey] === val ? '' : val;
      // reflect in dropdown too
      const sel = document.getElementById('filter-' + filterKey);
      if (sel) sel.value = activeFilters[filterKey];
      renderAll(document.getElementById('results-title').textContent);
    });
    li.appendChild(btn);
    el.appendChild(li);
  }
}

/* ═══════════════════════════════════════════════════════
   INIT
═══════════════════════════════════════════════════════ */
renderAll('All Resources');
</script>
</body>
</html>