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
/* ── Export button style ── */
.btn.btn-secondary {
  background: var(--surface);
  color: var(--text-2);
  border: 1px solid var(--border-md);
}
.btn.btn-secondary:hover {
  background: var(--surface2);
  color: var(--text-1);
  border-color: var(--blue);
}

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

/* ── Redesigned date-range picker ── */
.dr-wrap {
  display: inline-flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 6px;
}
.dr-pill {
  display: inline-flex;
  align-items: center;
  background: var(--surface);
  border: 1.5px solid var(--border-md);
  border-radius: 999px;
  padding: 3px 4px 3px 12px;
  gap: 6px;
  transition: border-color .15s, box-shadow .15s;
  cursor: pointer;
}
.dr-pill:hover,
.dr-pill:focus-within {
  border-color: var(--blue);
  box-shadow: 0 0 0 3px rgba(37,99,235,.10);
}
.dr-icon {
  color: var(--blue);
  display: flex;
  align-items: center;
  flex-shrink: 0;
}
.dr-label-text {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-1);
  white-space: nowrap;
  user-select: none;
  letter-spacing: .01em;
}
.dr-select-hidden {
  appearance: none;
  -webkit-appearance: none;
  background: transparent;
  border: none;
  outline: none;
  font-family: inherit;
  font-size: 12px;
  font-weight: 600;
  color: var(--text-1);
  cursor: pointer;
  padding: 4px 26px 4px 2px;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 4px center;
}
.dr-select-hidden:focus { outline: none; }

.dr-custom-row {
  display: none;
  align-items: center;
  gap: 6px;
  background: var(--surface2);
  border: 1.5px solid var(--blue);
  border-radius: 10px;
  padding: 8px 12px;
  box-shadow: 0 2px 8px rgba(37,99,235,.08);
  flex-wrap: wrap;
}
.dr-custom-row.visible { display: flex; }
.dr-custom-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-2);
  white-space: nowrap;
  letter-spacing: .03em;
  text-transform: uppercase;
}
.dr-date-input {
  appearance: none;
  -webkit-appearance: none;
  border: 1.5px solid var(--border-md);
  border-radius: 7px;
  background: var(--surface);
  color: var(--text-1);
  font-size: 12px;
  font-family: 'DM Mono', monospace;
  font-weight: 500;
  padding: 5px 8px;
  cursor: pointer;
  outline: none;
  transition: border-color .15s;
}
.dr-date-input:focus,
.dr-date-input:hover { border-color: var(--blue); }
.dr-sep {
  font-size: 11px;
  color: var(--text-3);
  font-weight: 600;
}
.dr-apply-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 5px 12px;
  border-radius: 6px;
  border: none;
  background: var(--blue);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  font-family: inherit;
  cursor: pointer;
  transition: opacity .15s;
  white-space: nowrap;
}
.dr-apply-btn:hover { opacity: .87; }

/* ── Report guide modal ── */
.rg-backdrop {
  position: fixed; inset: 0; z-index: 9998;
  background: rgba(0,0,0,.5); backdrop-filter: blur(3px);
  display: flex; align-items: center; justify-content: center;
  opacity: 0; transition: opacity .2s;
  pointer-events: none;
}
.rg-backdrop.visible { opacity: 1; pointer-events: all; }
.rg-dialog {
  background: #fff; border-radius: 16px;
  width: min(90vw, 680px);
  box-shadow: 0 24px 64px rgba(0,0,0,.28), 0 4px 16px rgba(0,0,0,.12);
  overflow: hidden;
  transform: translateY(12px) scale(.98); transition: transform .2s;
  display: flex; flex-direction: column;
}
.rg-backdrop.visible .rg-dialog { transform: translateY(0) scale(1); }
.rg-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 16px 20px; background: #1a5c2a; color: #fff;
  font-family: 'DM Sans', sans-serif;
}
.rg-header-left { display: flex; align-items: center; gap: 10px; }
.rg-header-title { font-size: 14px; font-weight: 700; }
.rg-header-sub   { font-size: 11px; opacity: .75; margin-top: 1px; }
.rg-close-btn {
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
  color: #fff; border-radius: 6px; width: 28px; height: 28px;
  cursor: pointer; font-size: 13px; display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; transition: background .15s;
}
.rg-close-btn:hover { background: rgba(255,255,255,.25); }
.rg-body { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
.rg-hint {
  font-size: 13px; color: #374151; line-height: 1.6;
  background: #f0f7f2; border: 1px solid #b6ddc2;
  border-radius: 8px; padding: 10px 14px;
  font-family: 'DM Sans', sans-serif;
}
.rg-hint strong { color: #1a5c2a; }
.rg-img-wrap {
  border: 1.5px solid #D1D5DB; border-radius: 10px; overflow: hidden;
  box-shadow: 0 2px 12px rgba(0,0,0,.1);
}
.rg-img-wrap img { width: 100%; display: block; }
.rg-footer {
  display: flex; align-items: center; justify-content: flex-end; gap: 10px;
  padding: 14px 20px; border-top: 1px solid #E5E7EB;
  background: #fafaf8; font-family: 'DM Sans', sans-serif;
}
.rg-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 9px 20px; border-radius: 8px; border: none;
  font-family: inherit; font-size: 13px; font-weight: 600;
  cursor: pointer; transition: opacity .15s;
}
.rg-btn:hover { opacity: .87; }
.rg-btn-cancel { background: #F3F4F6; color: #6B7280; }
.rg-btn-open   { background: #1a5c2a; color: #fff; }
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
      <a class="nav-item" href="#search-queries-card" onclick="navScrollTo('search-queries-card',event)">
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
      <a class="nav-item" href="#users-card" onclick="navScrollTo('users-card',event)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
        Users
      </a>
      <a class="nav-item" href="#" onclick="event.preventDefault();exportCSV()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
          <polyline points="7 10 12 15 17 10"/>
          <line x1="12" y1="15" x2="12" y2="3"/>
        </svg>
        Export CSV
      </a>
      <a class="nav-item" href="#" onclick="event.preventDefault();showReportGuide()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
          <polyline points="10 9 9 9 8 9"/>
        </svg>
        Print Report
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
        <!-- Date-range picker: pill preset + custom inline row -->
        <div class="dr-wrap" id="dr-wrap">
          <!-- Pill -->
          <div class="dr-pill" title="Change date range">
            <span class="dr-icon">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                <line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8"  y1="2" x2="8"  y2="6"/>
                <line x1="3"  y1="10" x2="21" y2="10"/>
              </svg>
            </span>
            <select class="dr-select-hidden" id="date-range" onchange="onDateRangeChange()" aria-label="Date range">
              <option value="1">Today</option>
              <option value="7">Last 7 days</option>
              <option value="30" selected>Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="0">All time</option>
              <option value="custom">Custom range…</option>
            </select>
          </div>

          <!-- Custom date row — revealed only when "Custom range…" is selected -->
          <div id="custom-range-row" class="dr-custom-row">
            <span class="dr-custom-label">From</span>
            <input type="date" id="custom-from" class="dr-date-input" aria-label="From date"/>
            <span class="dr-sep">→</span>
            <span class="dr-custom-label">To</span>
            <input type="date" id="custom-to"   class="dr-date-input" aria-label="To date"/>
            <button class="dr-apply-btn" onclick="applyCustomRange()">
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                   stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              Apply
            </button>
          </div>
        </div>
        <button class="btn btn-secondary" onclick="exportCSV()" title="Download analytics as CSV">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
               stroke-linecap="round">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7 10 12 15 17 10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          <span>CSV</span>
        </button>
        <button class="btn btn-secondary" onclick="showReportGuide()" title="Open printable report / Save as PDF">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
               stroke-linecap="round">
            <polyline points="6 9 6 2 18 2 18 9"/>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
            <rect x="6" y="14" width="12" height="8"/>
          </svg>
          <span>Print / Save PDF</span>
        </button>
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
          <div class="table-wrap" style="max-height:360px;overflow-y:auto;">
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
          <div class="table-wrap" style="max-height:360px;overflow-y:auto;">
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
            <div class="card-subtitle">Files most saved to My Library by teachers · in the selected period</div>
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
              <div class="card-title">Downloads by grade <span class="proto-tag">Prototype</span></div>
              <div class="card-subtitle">Distribution across grade levels · inferred from active filters at download time</div>
            </div>
          </div>
          <div id="grade-bars"></div>
        </div>

        <div class="card" id="search-queries-card">
          <div class="card-head">
            <div>
              <div class="card-title">Top search queries</div>
              <div class="card-subtitle">What teachers are looking for</div>
            </div>
          </div>
          <div class="tag-cloud" id="search-tags" style="max-height:260px;overflow-y:auto;"></div>
        </div>

        <div class="card">
          <div class="card-head">
            <div>
              <div class="card-title">Downloads by subject <span class="proto-tag">Prototype</span></div>
              <div class="card-subtitle">Inferred from active filters at download time</div>
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
        <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
          <table class="data-table">
            <thead style="position:sticky;top:0;z-index:2;background:var(--surface);">
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
              · <strong>tap a name</strong> to view bookmarks, downloads &amp; previews
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
        <div class="table-wrap" style="max-height:400px;overflow-y:auto;">
          <table class="data-table">
            <thead style="position:sticky;top:0;z-index:2;background:var(--surface);">
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

/* Navigate to a section by card id — smooth scroll + highlight flash */
function navScrollTo(id, e) {
  if (e) e.preventDefault();
  closeSidebar();
  var el = document.getElementById(id);
  if (!el) return;
  el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  /* Brief highlight pulse so the user knows where they landed */
  el.style.transition = 'box-shadow .15s';
  el.style.boxShadow  = '0 0 0 3px rgba(37,99,235,.35)';
  setTimeout(function() { el.style.boxShadow = ''; }, 1200);
}
</script>

<!-- ── Report Guide Modal ── -->
<div class="rg-backdrop" id="rg-backdrop" onclick="hideReportGuide(event)">
  <div class="rg-dialog" onclick="event.stopPropagation()">
    <div class="rg-header">
      <div class="rg-header-left">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"/>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
          <rect x="6" y="14" width="12" height="8"/>
        </svg>
        <div>
          <div class="rg-header-title">Print or Save as PDF</div>
          <div class="rg-header-sub">Quick guide before opening the report</div>
        </div>
      </div>
      <button class="rg-close-btn" onclick="hideReportGuide()" title="Close">✕</button>
    </div>
    <div class="rg-body">
      <div class="rg-hint">
        When the report opens, click <strong>Print / Save as PDF</strong>.
        In the browser print dialog, set the <strong>Destination</strong> to
        <strong>Save as PDF</strong> — then click <strong>Save</strong>.
        To print physically, choose your printer instead.
      </div>
      <div class="rg-img-wrap">
        <img src="../assets/img/Save_Guide.png" alt="Save as PDF guide screenshot"/>
      </div>
    </div>
    <div class="rg-footer">
      <button class="rg-btn rg-btn-cancel" onclick="hideReportGuide()">Cancel</button>
      <button class="rg-btn rg-btn-open" onclick="hideReportGuide();exportReport()">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="6 9 6 2 18 2 18 9"/>
          <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
          <rect x="6" y="14" width="12" height="8"/>
        </svg>
        Got it — Open Report
      </button>
    </div>
  </div>
</div>

<script>
function showReportGuide() {
  document.getElementById('rg-backdrop').classList.add('visible');
}
function hideReportGuide(e) {
  if (e && e.target !== document.getElementById('rg-backdrop')) return;
  document.getElementById('rg-backdrop').classList.remove('visible');
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') document.getElementById('rg-backdrop').classList.remove('visible');
});
</script>

<!-- Dashboard logic -->
<script src="../assets/js/admindashboard.js"></script>
<!-- Export / Report module (must load after admindashboard.js) -->
<script src="../assets/js/dashboard-export.js"></script>
<!-- Per-user activity drill-down + print/CSV (after dashboard-export.js) -->
<script src="../assets/js/user-activity-report.js"></script>
</body>
</html>