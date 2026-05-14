<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>LRMDS Admin Dashboard – DepEd Carcar City</title>

<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>

<!-- Dashboard styles -->
<link rel="stylesheet" href="../assets/css/admindashboard.css"/>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
/* ── File path under filename in Top Files ── */
.file-path {
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 10px;
  color: var(--text-3);
  font-family: 'DM Mono', monospace;
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 280px;
}

/* ── Extension badge ── */
.ext-badge {
  display: inline-block;
  font-size: 9px;
  font-family: 'DM Mono', monospace;
  font-weight: 600;
  padding: 1px 5px;
  border-radius: 3px;
  margin-left: 4px;
  vertical-align: middle;
  letter-spacing: .03em;
  background: #E5E7EB;
  color: #374151;
  border: 1px solid #D1D5DB;
}
.ext-badge.ext-pdf  { background:#FEE2E2; color:#991B1B; border-color:#FECACA; }
.ext-badge.ext-docx,
.ext-badge.ext-doc  { background:#DBEAFE; color:#1D4ED8; border-color:#BFDBFE; }
.ext-badge.ext-mp4,
.ext-badge.ext-mov,
.ext-badge.ext-mkv  { background:#F3E8FF; color:#7C3AED; border-color:#E9D5FF; }
.ext-badge.ext-pptx,
.ext-badge.ext-ppt  { background:#FFEDD5; color:#C2410C; border-color:#FED7AA; }
.ext-badge.ext-xlsx,
.ext-badge.ext-xls  { background:#DCFCE7; color:#15803D; border-color:#BBF7D0; }
.ext-badge.ext-mp3,
.ext-badge.ext-wav  { background:#FEF3C7; color:#92400E; border-color:#FDE68A; }

/* ── User chip with stacked name + email ── */
.user-chip {
  display: flex;
  align-items: center;
  gap: 7px;
}
.user-chip-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}
.user-chip-name  { font-size: 12px; font-weight: 500; color: var(--text-1); }
.user-chip-email { font-size: 10px; color: var(--text-3); font-family: 'DM Mono', monospace; }

/* ── Folder path in log table ── */
.folder-path-sm {
  display: flex;
  align-items: center;
  font-size: 11px;
  color: var(--text-2);
  font-family: 'DM Mono', monospace;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 240px;
}

/* ── Improved date-range select ── */
.date-range-wrap {
  position: relative;
  display: inline-flex;
  align-items: center;
}
.date-range-wrap::after {
  content: '';
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  width: 0; height: 0;
  border-left: 4px solid transparent;
  border-right: 4px solid transparent;
  border-top: 5px solid var(--text-2);
  pointer-events: none;
}
select.select-styled {
  appearance: none;
  -webkit-appearance: none;
  padding: 7px 30px 7px 12px;
  border-radius: var(--radius);
  border: 1px solid var(--border-md);
  background: var(--surface);
  color: var(--text-1);
  font-size: 13px;
  font-family: 'DM Sans', inherit;
  font-weight: 500;
  cursor: pointer;
  outline: none;
  transition: border-color .15s, box-shadow .15s;
  min-width: 140px;
}
select.select-styled:hover  { border-color: var(--blue); }
select.select-styled:focus  { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(37,99,235,.1); }

/* ── Bookmark pill ── */
.pill-bm {
  background: #FFF7ED;
  color: #D97706;
}

/* ── Bookmarked resources card ── */
.bm-list { display: flex; flex-direction: column; gap: 8px; }
.bm-item {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  background: var(--bg);
  border: 1px solid var(--border);
  border-radius: 8px;
  transition: background .12s;
}
.bm-item:hover { background: var(--surface2); }
.bm-rank-num {
  font-size: 13px; font-weight: 700; color: var(--text-3);
  font-family: 'DM Mono', monospace;
  width: 20px; flex-shrink: 0; text-align: center;
}
.bm-item-body { flex: 1; min-width: 0; }
.bm-item-name {
  font-size: 13px; font-weight: 600; color: var(--text-1);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  max-width: 320px;
}
.bm-item-path {
  font-size: 11px; color: var(--text-3);
  font-family: 'DM Mono', monospace;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  margin-top: 2px;
}
.bm-count-badge {
  display: inline-flex; align-items: center; gap: 4px;
  background: #FFF7ED; color: #D97706;
  border: 1px solid #FDE68A;
  border-radius: 20px; padding: 3px 10px;
  font-size: 12px; font-weight: 700;
  font-family: 'DM Mono', monospace;
  flex-shrink: 0;
}
.bm-bar-wrap {
  width: 70px; flex-shrink: 0;
}
</style>
</head>
<body>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<div class="layout">

  <!-- ══ Sidebar ══ -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-logo">
        <div class="brand-shield">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.5"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <div>
          <div class="brand-name">LRMDS Admin</div>
          <div class="brand-sub">DepEd Carcar City</div>
        </div>
      </div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Analytics</div>
      <a class="nav-item active" href="admindashboard.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
          <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
        </svg>
        Dashboard
      </a>
      <a class="nav-item" href="index.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
        </svg>
        Files
      </a>
      <a class="nav-item" href="/lrmds/deped-lrmds-portal/manage.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5"/><path d="m12 5-7 7 7 7"/>
        </svg>
        Google Drive Admin
      </a>
      <a class="nav-item" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        Searches
      </a>

      <div class="nav-section-label" style="margin-top:12px">Reports</div>
      <a class="nav-item" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <line x1="18" y1="20" x2="18" y2="10"/>
          <line x1="12" y1="20" x2="12" y2="4"/>
          <line x1="6"  y1="20" x2="6"  y2="14"/>
        </svg>
        Trends
      </a>
      <a class="nav-item" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Users
      </a>
      <a class="nav-item" href="#">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export CSV
      </a>
    </nav>

    <div class="sidebar-footer">
      <span class="status-text"><span class="status-dot"></span>Live · tracker.php</span>
      <span style="margin-top:4px">v1.2 · <span id="db-size">—</span></span>
    </div>
  </aside>

  <!-- ══ Main ══ -->
  <div class="main">

    <!-- Topbar -->
    <div class="topbar">
      <button class="hamburger-btn" id="hamburger-btn" onclick="openSidebar()" aria-label="Open menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <line x1="3" y1="6"  x2="21" y2="6"/>
          <line x1="3" y1="12" x2="21" y2="12"/>
          <line x1="3" y1="18" x2="21" y2="18"/>
        </svg>
      </button>
      <div class="topbar-left">
        <h1>Analytics Dashboard</h1>
        <p>Portal usage, file activity, and learning resource trends</p>
      </div>
      <div class="topbar-right">
        <span class="last-updated" id="last-updated">Loading…</span>
        <!-- Improved date-range dropdown -->
        <div class="date-range-wrap">
          <select class="select-styled" id="date-range" onchange="loadAll()">
            <option value="1">Today</option>
            <option value="7">Last 7 days</option>
            <option value="30" selected>Last 30 days</option>
            <option value="90">Last 90 days</option>
            <option value="0">All time</option>
          </select>
        </div>
        <button class="btn btn-primary" onclick="loadAll()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
               stroke-linecap="round">
            <path d="M23 4v6h-6"/>
            <path d="M1 20v-6h6"/>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/>
            <path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/>
          </svg>
          <span>Refresh</span>
        </button>
      </div>
    </div>

    <!-- Content -->
    <div class="content">

      <!-- Demo banner -->
      <div class="demo-banner" id="demo-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round">
          <circle cx="12" cy="12" r="10"/>
          <line x1="12" y1="8" x2="12" y2="12"/>
          <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <div>
          <strong>Demo mode — sample data shown.</strong>
          To connect live data: open <code>assets/js/admindashboard.js</code>,
          set <code>const DEMO = false</code>, and confirm <code>tracker.php</code>
          has the extra query endpoints (see instructions below).
        </div>
      </div>

      <!-- ── Metric cards ── -->
      <div class="metrics-grid">

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Unique users</span>
            <div class="metric-icon" style="background:var(--blue-lt)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-users">—</div>
          <div class="metric-sub">Microsoft accounts signed in</div>
        </div>

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Sessions</span>
            <div class="metric-icon" style="background:var(--teal-lt)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--teal)" stroke-width="2" stroke-linecap="round">
                <rect x="2" y="3" width="20" height="14" rx="2"/>
                <path d="M8 21h8"/><path d="M12 17v4"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-sessions">—</div>
          <div class="metric-sub">Portal visits total</div>
        </div>

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">File views</span>
            <div class="metric-icon" style="background:var(--green-lt)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-views">—</div>
          <div class="metric-sub">Previews opened</div>
        </div>

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Downloads</span>
            <div class="metric-icon" style="background:var(--purple-lt)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--purple)" stroke-width="2" stroke-linecap="round">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7 10 12 15 17 10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-downloads">—</div>
          <div class="metric-sub">Files downloaded</div>
        </div>

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Searches</span>
            <div class="metric-icon" style="background:var(--amber-lt)">
              <svg viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/>
                <path d="m21 21-4.35-4.35"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-searches">—</div>
          <div class="metric-sub">Queries submitted</div>
        </div>

        <div class="metric-card">
          <div class="metric-header">
            <span class="metric-label">Bookmarks</span>
            <div class="metric-icon" style="background:#FFF7ED">
              <svg viewBox="0 0 24 24" fill="none" stroke="#D97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </div>
          </div>
          <div class="metric-value" id="m-bookmarks">—</div>
          <div class="metric-sub">Files saved to My Library</div>
        </div>

      </div><!-- /metrics-grid -->

      <!-- ── Trend + Type ── -->
      <div class="charts-row">

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Activity over time</div>
              <div class="card-subtitle">Daily file views and downloads</div>
            </div>
          </div>
          <div class="legend">
            <div class="legend-item"><span class="legend-dot" style="background:#2563EB"></span>Downloads</div>
            <div class="legend-item"><span class="legend-dot" style="background:#16A34A"></span>Views</div>
          </div>
          <div class="chart-wrap" style="height:200px">
            <canvas id="trend-chart" role="img" aria-label="Line chart showing daily file views and downloads"></canvas>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">By resource type</div>
              <div class="card-subtitle">Share of total downloads</div>
            </div>
          </div>
          <div class="legend" id="type-legend"></div>
          <div class="chart-wrap" style="height:160px">
            <canvas id="type-chart" role="img" aria-label="Donut chart of downloads by resource type"></canvas>
          </div>
        </div>

      </div><!-- /charts-row -->

      <!-- ── Top files + Folders ── -->
      <div class="tables-row">

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Top files</div>
              <div class="card-subtitle">Most accessed learning resources</div>
            </div>
            <div class="tabs">
              <button class="tab active" onclick="switchTopTab('downloads',this)">Downloads</button>
              <button class="tab"        onclick="switchTopTab('views',this)">Views</button>
            </div>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>#</th><th>File &amp; Path</th><th>Type / Ext</th><th>Count</th><th>Bar</th>
                </tr>
              </thead>
              <tbody id="top-files-body"></tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Top folders</div>
              <div class="card-subtitle">
                Folders by activity — shows full path including nested subfolders
              </div>
            </div>
          </div>
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Folder path</th>
                  <th style="text-align:right">Views</th>
                  <th style="text-align:right">DLs</th>
                </tr>
              </thead>
              <tbody id="folder-body"></tbody>
            </table>
          </div>
        </div>

      </div><!-- /tables-row -->

      <!-- ── Bookmarked Resources ── -->
      <div class="card" style="margin-bottom:20px">
        <div class="card-head">
          <div>
            <div class="card-title">
              🔖 Most Bookmarked Resources
            </div>
            <div class="card-subtitle">Files most saved to My Library by teachers · all time</div>
          </div>
        </div>
        <div id="bookmarks-list" class="bm-list">
          <div class="empty">Loading…</div>
        </div>
      </div>

      <!-- ── Grade + Searches + Subject ── -->
      <div class="bottom-row">

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Downloads by grade</div>
              <div class="card-subtitle">Distribution across grade levels</div>
            </div>
          </div>
          <div id="grade-bars"></div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Top search queries</div>
              <div class="card-subtitle">What teachers are looking for</div>
            </div>
          </div>
          <div class="tag-cloud" id="search-tags"></div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Downloads by subject</div>
              <div class="card-subtitle">Horizontal breakdown</div>
            </div>
          </div>
          <div class="chart-wrap" style="height:200px">
            <canvas id="subject-chart" role="img" aria-label="Horizontal bar chart of downloads by subject"></canvas>
          </div>
        </div>

      </div><!-- /bottom-row -->

      <!-- ── Download Log ── -->
      <div class="card" style="margin-bottom:20px" id="download-log-card">
        <div class="card-head">
          <div>
            <div class="card-title">
              Download Log
              <span class="log-live-badge">● Live</span>
            </div>
            <div class="card-subtitle">
              Every file download with the signed-in user's name · filtered by the date range above
              (<span id="log-count">—</span> records)
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <input id="log-search" type="search"
              placeholder="Filter by user, file, type…"
              style="padding:6px 10px;border:1px solid var(--border-md);border-radius:var(--radius);font-size:12px;font-family:inherit;background:var(--surface);color:var(--text-1);width:200px"/>
            <button class="btn" onclick="loadDownloadLog()" title="Refresh log">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/>
                <path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>User (Microsoft Account)</th>
                <th>File</th>
                <th>Type / Ext</th>
                <th>Folder Path</th>
              </tr>
            </thead>
            <tbody id="log-body">
              <tr><td colspan="5" class="empty">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ── Users Tab ── -->
      <div class="card" style="margin-bottom:20px" id="users-card">
        <div class="card-head">
          <div>
            <div class="card-title">
              Portal Users
              <span class="log-live-badge">● Live</span>
            </div>
            <div class="card-subtitle">
              Everyone who has signed in via Microsoft and visited the portal
              · filtered by the date range above
              (<span id="users-count">—</span> users)
            </div>
          </div>
          <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
            <input id="users-search" type="search"
              placeholder="Filter by name or email…"
              style="padding:6px 10px;border:1px solid var(--border-md);border-radius:var(--radius);font-size:12px;font-family:inherit;background:var(--surface);color:var(--text-1);width:200px"/>
            <button class="btn" onclick="loadUsers()" title="Refresh users">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10"/>
                <path d="M20.49 15a9 9 0 0 1-14.85 3.36L1 14"/>
              </svg>
            </button>
          </div>
        </div>
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr>
                <th>#</th>
                <th>User (Microsoft Account)</th>
                <th style="text-align:right">Sessions</th>
                <th style="text-align:right">Views</th>
                <th style="text-align:right">Downloads</th>
                <th style="text-align:right">Bookmarks</th>
                <th style="text-align:right">Searches</th>
                <th>Last Seen</th>
              </tr>
            </thead>
            <tbody id="users-body">
              <tr><td colspan="8" class="empty">Loading…</td></tr>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- /content -->
  </div><!-- /main -->
</div><!-- /layout -->

<!-- Sidebar toggle script -->
<script>
function openSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebar-overlay').classList.add('visible');
  document.body.style.overflow = 'hidden';
}
function closeSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('visible');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeSidebar();
});
</script>

<!-- Dashboard logic -->
<script src="../assets/js/admindashboard.js"></script>
</body>
</html>