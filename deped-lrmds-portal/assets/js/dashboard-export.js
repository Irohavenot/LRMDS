/* ============================================================
   LRMDS Admin Dashboard – DepEd Carcar City
   dashboard-export.js  (v1.0)

   Provides two export modes triggered from the dashboard:
     1. exportCSV()   — downloads a multi-sheet CSV bundle (ZIP)
                        or separate named CSV files
     2. exportReport() — opens a full-page printable HTML report
                         in a new tab (with logo, charts, tables)

   Depends on: admindashboard.js globals
     _topData, _trendChart, _typeChart, _subjectChart,
     _logData, _usersData, getDays(), demoData()

   Usage (add to admindashboard.php topbar):
     <button class="btn btn-secondary" onclick="exportCSV()">
       Export CSV
     </button>
     <button class="btn btn-secondary" onclick="exportReport()">
       Print Report
     </button>
   ============================================================ */

// ─── Helpers ──────────────────────────────────────────────────
function _esc(v) {
  if (v == null) return '';
  const s = String(v);
  // RFC 4180 — wrap in quotes if contains comma, quote, or newline
  return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}

function _row(arr) {
  return arr.map(_esc).join(',') + '\r\n';
}

function _dateLabel() {
  const now = new Date();
  return now.toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric',
  });
}

function _rangeLabel() {
  const el = document.getElementById('date-range');
  if (!el) return 'Last 30 days';
  const map = { '1': 'Today', '7': 'Last 7 days', '30': 'Last 30 days', '90': 'Last 90 days', '0': 'All time' };
  return map[el.value] || 'Custom';
}

function _summaryMetric(id) {
  const el = document.getElementById(id);
  return el ? el.textContent.replace(/,/g, '') : '0';
}

function _downloadFile(filename, content, mime) {
  const blob = new Blob([content], { type: mime });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
}

function _slugDate() {
  return new Date().toISOString().slice(0, 10); // YYYY-MM-DD
}

// ─── Chart-to-PNG helper ──────────────────────────────────────
// Returns a data URL from a Chart.js instance, or '' on failure.
function _chartPng(chartInstance) {
  try {
    return chartInstance && chartInstance.canvas
      ? chartInstance.canvas.toDataURL('image/png')
      : '';
  } catch (_) { return ''; }
}

// ═══════════════════════════════════════════════════════════════
//  1.  CSV EXPORT
// ═══════════════════════════════════════════════════════════════
/**
 * exportCSV()
 * Downloads multiple CSV files (one per section) as individual
 * downloads — browsers block multi-download by default so we
 * bundle them into a single well-structured CSV with clear
 * section separators, or split if the user has the JSZip CDN.
 *
 * Strategy: single "report" CSV with section headers.
 */
function exportCSV() {
  const range  = _rangeLabel();
  const date   = _dateLabel();
  const slug   = _slugDate();

  let csv = '';

  // ── Cover ────────────────────────────────────────────────────
  csv += _row(['LRMDS Analytics Report – DepEd Carcar City']);
  csv += _row(['Generated', date]);
  csv += _row(['Period', range]);
  csv += _row([]);

  // ── Summary metrics ──────────────────────────────────────────
  csv += _row(['=== SUMMARY METRICS ===']);
  csv += _row(['Metric', 'Value']);
  csv += _row(['Unique Users',    _summaryMetric('m-users')]);
  csv += _row(['Sessions',        _summaryMetric('m-sessions')]);
  csv += _row(['File Views',      _summaryMetric('m-views')]);
  csv += _row(['Downloads',       _summaryMetric('m-downloads')]);
  csv += _row(['Searches',        _summaryMetric('m-searches')]);
  csv += _row(['Bookmarks',       _summaryMetric('m-bookmarks')]);
  csv += _row([]);

  // ── Top 5 Files by Downloads ─────────────────────────────────
  const topDl = (_topData && _topData.downloads) ? _topData.downloads.slice(0, 5) : [];
  csv += _row(['=== TOP 5 FILES BY DOWNLOADS ===']);
  csv += _row(['Rank', 'File Name', 'Type', 'Extension', 'Folder Path', 'Downloads', 'Views']);
  topDl.forEach((r, i) => {
    csv += _row([
      i + 1,
      r.item_name   || '—',
      r.item_type   || '—',
      r.file_ext    || '—',
      r.folder_path || '—',
      r.downloads   ?? 0,
      r.views       ?? 0,
    ]);
  });
  csv += _row([]);

  // ── Top 5 Files by Views ─────────────────────────────────────
  const topVw = (_topData && _topData.views) ? _topData.views.slice(0, 5) : [];
  csv += _row(['=== TOP 5 FILES BY VIEWS ===']);
  csv += _row(['Rank', 'File Name', 'Type', 'Extension', 'Folder Path', 'Views', 'Downloads']);
  topVw.forEach((r, i) => {
    csv += _row([
      i + 1,
      r.item_name   || '—',
      r.item_type   || '—',
      r.file_ext    || '—',
      r.folder_path || '—',
      r.views       ?? 0,
      r.downloads   ?? 0,
    ]);
  });
  csv += _row([]);

  // ── Top 5 Folders ────────────────────────────────────────────
  const folderRows = _getFolderData().slice(0, 5);
  csv += _row(['=== TOP 5 FOLDERS ===']);
  csv += _row(['Rank', 'Folder Path', 'Views', 'Downloads']);
  folderRows.forEach((f, i) => {
    csv += _row([
      i + 1,
      f.folder_path || f.folder || '—',
      f.views       ?? 0,
      f.downloads   ?? 0,
    ]);
  });
  csv += _row([]);

  // ── Top Searches ─────────────────────────────────────────────
  const searchRows = _getSearchData();
  csv += _row(['=== TOP SEARCH QUERIES ===']);
  csv += _row(['Rank', 'Search Query', 'Count']);
  searchRows.forEach((s, i) => {
    csv += _row([i + 1, s.search_query || '—', s.count ?? 0]);
  });
  csv += _row([]);

  // ── Trend data ───────────────────────────────────────────────
  if (_trendChart && _trendChart.data) {
    const labels   = _trendChart.data.labels || [];
    const dlData   = (_trendChart.data.datasets[0] || {}).data || [];
    const vwData   = (_trendChart.data.datasets[1] || {}).data || [];
    csv += _row(['=== ACTIVITY TREND ===']);
    csv += _row(['Date', 'Downloads', 'Views']);
    labels.forEach((lbl, i) => {
      csv += _row([lbl, dlData[i] ?? 0, vwData[i] ?? 0]);
    });
    csv += _row([]);
  }

  // ── Portal Users ─────────────────────────────────────────────
  if (Array.isArray(_usersData) && _usersData.length) {
    csv += _row(['=== PORTAL USERS ===']);
    csv += _row(['Name', 'Email', 'Sessions', 'File Views', 'Downloads', 'Bookmarks', 'Searches', 'Last Seen']);
    _usersData.forEach(u => {
      csv += _row([
        u.user_name  || '—',
        u.user_email || '—',
        u.sessions   ?? 0,
        u.file_views ?? 0,
        u.downloads  ?? 0,
        u.bookmarks  ?? 0,
        u.searches   ?? 0,
        u.last_seen  || '—',
      ]);
    });
    csv += _row([]);
  }

  // ── Download Log ─────────────────────────────────────────────
  if (Array.isArray(_logData) && _logData.length) {
    csv += _row(['=== DOWNLOAD LOG ===']);
    csv += _row(['Downloaded At', 'User Name', 'User Email', 'File Name', 'Type', 'Extension', 'Folder Path']);
    _logData.forEach(r => {
      csv += _row([
        r.downloaded_at || '—',
        r.user_name     || '—',
        r.user_email    || '—',
        r.item_name     || '—',
        r.item_type     || '—',
        r.file_ext      || '—',
        r.folder_path   || '—',
      ]);
    });
  }

  _downloadFile(`LRMDS_Report_${slug}.csv`, '\uFEFF' + csv, 'text/csv;charset=utf-8');
}

// ─── Data accessors (read from DOM / globals) ─────────────────
function _getFolderData() {
  // Try to read from the rendered folder table body
  const rows = [];
  const tbody = document.getElementById('folder-body');
  if (!tbody) return rows;
  tbody.querySelectorAll('tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 3) {
      rows.push({
        folder_path: cells[0] ? cells[0].innerText.trim() : '—',
        views:       parseInt((cells[1] ? cells[1].innerText : '0').replace(/\D/g, ''), 10) || 0,
        downloads:   parseInt((cells[2] ? cells[2].innerText : '0').replace(/\D/g, ''), 10) || 0,
      });
    }
  });
  return rows;
}

function _getSearchData() {
  const tags = document.getElementById('search-tags');
  if (!tags) return [];
  return Array.from(tags.querySelectorAll('.search-tag')).map(el => {
    const countEl = el.querySelector('.tag-count');
    const count   = countEl ? parseInt(countEl.textContent, 10) || 0 : 0;
    const text    = el.textContent.replace(countEl ? countEl.textContent : '', '').trim();
    return { search_query: text, count };
  });
}

// ═══════════════════════════════════════════════════════════════
//  2.  PRINTABLE HTML REPORT
// ═══════════════════════════════════════════════════════════════
/**
 * exportReport()
 * Opens a professional, print-ready HTML report in a new tab.
 * Includes charts rendered as PNG images (via canvas toDataURL),
 * summary metrics, top-5 files, top-5 folders, top searches,
 * and a trend table.
 */
function exportReport() {
  const range     = _rangeLabel();
  const date      = _dateLabel();
  const trendPng  = _chartPng(_trendChart);
  const typePng   = _chartPng(_typeChart);
  const subjPng   = _chartPng(_subjectChart);

  const users      = _summaryMetric('m-users');
  const sessions   = _summaryMetric('m-sessions');
  const views      = _summaryMetric('m-views');
  const downloads  = _summaryMetric('m-downloads');
  const searches   = _summaryMetric('m-searches');
  const bookmarks  = _summaryMetric('m-bookmarks');

  const topDl      = (_topData && _topData.downloads) ? _topData.downloads.slice(0, 5) : [];
  const topVw      = (_topData && _topData.views)     ? _topData.views.slice(0, 5)     : [];
  const folderData = _getFolderData().slice(0, 5);
  const searchData = _getSearchData().slice(0, 10);

  // ── Trend table rows ─────────────────────────────────────────
  let trendTableRows = '';
  if (_trendChart && _trendChart.data) {
    const labels = _trendChart.data.labels || [];
    const dlData = (_trendChart.data.datasets[0] || {}).data || [];
    const vwData = (_trendChart.data.datasets[1] || {}).data || [];
    // Show last 14 points max to keep table compact
    const start  = Math.max(0, labels.length - 14);
    for (let i = start; i < labels.length; i++) {
      trendTableRows += `<tr>
        <td>${labels[i] || '—'}</td>
        <td class="num">${(dlData[i] ?? 0).toLocaleString()}</td>
        <td class="num">${(vwData[i] ?? 0).toLocaleString()}</td>
      </tr>`;
    }
  }

  // ── Top Files rows ────────────────────────────────────────────
  function fileRows(arr, metric) {
    if (!arr.length) return '<tr><td colspan="4" class="empty-cell">No data available</td></tr>';
    return arr.map((r, i) => `<tr>
      <td class="rank">${i + 1}</td>
      <td>
        <div class="fname">${_escHtmlR(r.item_name || '—')}</div>
        <div class="fpath">${_escHtmlR(r.folder_path || '')}</div>
      </td>
      <td><span class="badge">${r.item_type || '—'}</span> <span class="ext">${(r.file_ext || '').toUpperCase()}</span></td>
      <td class="num strong">${(r[metric] ?? 0).toLocaleString()}</td>
    </tr>`).join('');
  }

  // ── Top Folders rows ──────────────────────────────────────────
  function folderRows(arr) {
    if (!arr.length) return '<tr><td colspan="3" class="empty-cell">No data available</td></tr>';
    return arr.map((f, i) => `<tr>
      <td class="rank">${i + 1}</td>
      <td class="fpath-main">${_escHtmlR(f.folder_path || '—')}</td>
      <td class="num">${(f.views ?? 0).toLocaleString()}</td>
      <td class="num strong">${(f.downloads ?? 0).toLocaleString()}</td>
    </tr>`).join('');
  }

  // ── Search cloud ──────────────────────────────────────────────
  const maxSrch = searchData.length ? Math.max(...searchData.map(s => s.count)) : 1;
  const searchCloud = searchData.map((s, i) => {
    const size = 13 + Math.round((s.count / maxSrch) * 10);
    return `<span class="stag" style="font-size:${size}px">${_escHtmlR(s.search_query)} <em>${s.count}</em></span>`;
  }).join('');

  // ── HTML ─────────────────────────────────────────────────────
  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>LRMDS Analytics Report – DepEd Carcar City – ${date}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:ital,wght@0,300;0,400;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
/* ── Reset & Page Setup ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 13px; }
body {
  font-family: 'Source Serif 4', Georgia, serif;
  color: #1a1a1a;
  background: #fff;
  line-height: 1.55;
}

@page {
  size: A4 portrait;
  margin: 18mm 15mm 18mm 15mm;
}

@media print {
  .no-print { display: none !important; }
  .page-break { page-break-before: always; }
  body { font-size: 11px; }
  .chart-wrap img { max-height: 200px; }
}

/* ── Layout ── */
.page { max-width: 860px; margin: 0 auto; padding: 32px 40px 64px; }

/* ── Print button ── */
.print-bar {
  position: sticky; top: 0; z-index: 99;
  background: #1e3a5f; color: #fff;
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 24px; gap: 12px;
  font-family: 'DM Mono', monospace; font-size: 12px;
}
.print-bar span { opacity: .75; }
.print-bar-btns { display: flex; gap: 8px; }
.pbtn {
  padding: 7px 18px; border-radius: 6px; border: none;
  font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 500;
  cursor: pointer; letter-spacing: .02em;
}
.pbtn-primary { background: #fff; color: #1e3a5f; }
.pbtn-secondary { background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.25); }
.pbtn:hover { opacity: .85; }

/* ── Cover Header ── */
.cover {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding-bottom: 24px;
  border-bottom: 2.5px solid #1e3a5f;
  margin-bottom: 28px;
}
.cover-logo {
  display: flex; align-items: center; gap: 16px;
}
.logo-shield-r {
  width: 56px; height: 56px; border-radius: 12px;
  background: #1e3a5f;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.logo-shield-r svg { width: 30px; height: 30px; }
.logo-text-r h1 {
  font-size: 17px; font-weight: 700; color: #1e3a5f;
  line-height: 1.2; letter-spacing: -.01em;
}
.logo-text-r p {
  font-size: 11px; color: #6b7280; font-family: 'DM Mono', monospace;
  margin-top: 2px; letter-spacing: .04em; text-transform: uppercase;
}
.cover-meta {
  text-align: right;
  font-family: 'DM Mono', monospace; font-size: 11px; color: #6b7280;
  line-height: 1.7;
}
.cover-meta strong { font-size: 13px; color: #1a1a1a; display: block; margin-bottom: 2px; }

/* ── Section header ── */
.section-hd {
  display: flex; align-items: center; gap: 10px;
  margin: 32px 0 14px;
}
.section-hd::before {
  content: '';
  display: block; width: 4px; height: 20px;
  background: #1e3a5f; border-radius: 2px; flex-shrink: 0;
}
.section-hd h2 {
  font-size: 14px; font-weight: 700; color: #1e3a5f;
  text-transform: uppercase; letter-spacing: .06em;
}

/* ── Metrics grid ── */
.metrics-grid-r {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
  margin-bottom: 4px;
}
.mc-r {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 14px 16px;
}
.mc-r-label {
  font-size: 10px; font-family: 'DM Mono', monospace;
  color: #6b7280; text-transform: uppercase; letter-spacing: .06em;
  margin-bottom: 4px;
}
.mc-r-value {
  font-size: 28px; font-weight: 700; color: #1e3a5f;
  font-family: 'DM Mono', monospace; line-height: 1;
}
.mc-r-sub {
  font-size: 10px; color: #9ca3af; margin-top: 4px;
}

/* ── Charts row ── */
.charts-row {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
  margin-bottom: 4px;
}
.chart-wrap {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 14px 16px;
}
.chart-wrap.full { grid-column: 1 / -1; }
.chart-title {
  font-size: 11px; font-family: 'DM Mono', monospace;
  color: #6b7280; text-transform: uppercase; letter-spacing: .05em;
  margin-bottom: 10px;
}
.chart-wrap img {
  width: 100%; max-height: 220px; object-fit: contain;
  display: block;
}
.no-chart {
  height: 140px; display: flex; align-items: center; justify-content: center;
  color: #d1d5db; font-family: 'DM Mono', monospace; font-size: 11px;
  font-style: italic;
}

/* ── Tables ── */
table {
  width: 100%; border-collapse: collapse;
  font-size: 12px; margin-bottom: 4px;
}
thead tr { border-bottom: 2px solid #1e3a5f; }
thead th {
  text-align: left; padding: 8px 10px;
  font-size: 10px; font-family: 'DM Mono', monospace;
  text-transform: uppercase; letter-spacing: .05em;
  color: #6b7280; font-weight: 500;
}
thead th.num { text-align: right; }
tbody tr { border-bottom: 1px solid #f3f4f6; }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 9px 10px; vertical-align: top; }
tbody tr:nth-child(even) { background: #f9fafb; }
.rank {
  font-family: 'DM Mono', monospace; font-size: 11px;
  color: #9ca3af; text-align: center; width: 28px;
}
.num { text-align: right; font-family: 'DM Mono', monospace; }
.strong { font-weight: 700; color: #1e3a5f; }
.fname { font-weight: 600; color: #111827; line-height: 1.3; }
.fpath { font-size: 10px; color: #9ca3af; font-family: 'DM Mono', monospace; margin-top: 2px; }
.fpath-main { font-family: 'DM Mono', monospace; font-size: 11px; color: #374151; }
.badge {
  display: inline-block; font-size: 9px; font-family: 'DM Mono', monospace;
  font-weight: 600; padding: 1px 6px; border-radius: 3px;
  background: #dbeafe; color: #1d4ed8; letter-spacing: .03em;
}
.ext {
  display: inline-block; font-size: 9px; font-family: 'DM Mono', monospace;
  color: #6b7280;
}
.empty-cell {
  text-align: center; color: #d1d5db;
  padding: 20px; font-style: italic;
  font-family: 'DM Mono', monospace; font-size: 11px;
}

/* ── Trend table ── */
.trend-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.trend-chart-box {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 14px 16px; overflow: hidden;
}
.trend-table-box {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  overflow: hidden;
}
.trend-table-box table { margin-bottom: 0; }
.trend-table-box thead th { padding: 10px 12px; }
.trend-table-box tbody td { padding: 7px 12px; font-size: 11px; }

/* ── Search cloud ── */
.search-cloud {
  border: 1.5px solid #e5e7eb; border-radius: 10px;
  padding: 16px 20px;
  display: flex; flex-wrap: wrap; gap: 8px 12px;
  align-items: baseline;
  line-height: 1.8;
}
.stag {
  font-family: 'Source Serif 4', serif;
  color: #1e3a5f; font-weight: 600;
}
.stag em {
  font-style: normal; font-family: 'DM Mono', monospace;
  font-size: 10px; color: #9ca3af; font-weight: 400;
  margin-left: 3px;
}

/* ── Footer ── */
.report-footer {
  margin-top: 48px; padding-top: 16px;
  border-top: 1px solid #e5e7eb;
  display: flex; justify-content: space-between; align-items: center;
  font-family: 'DM Mono', monospace; font-size: 10px; color: #9ca3af;
}
.report-footer strong { color: #6b7280; }
</style>
</head>
<body>

<!-- ── Print bar (hidden on print) ── -->
<div class="print-bar no-print">
  <span>LRMDS Analytics Report · ${date} · ${range}</span>
  <div class="print-bar-btns">
    <button class="pbtn pbtn-secondary" onclick="window.close()">Close</button>
    <button class="pbtn pbtn-primary" onclick="window.print()">
      🖨 Print / Save as PDF
    </button>
  </div>
</div>

<div class="page">

  <!-- ── Cover ── -->
  <div class="cover">
    <div class="cover-logo">
      <div class="logo-shield-r">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"
             stroke-linecap="round" stroke-linejoin="round">
          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
      </div>
      <div class="logo-text-r">
        <h1>DepEd Carcar City LRMDS</h1>
        <p>Learning Resource Management & Development System</p>
      </div>
    </div>
    <div class="cover-meta">
      <strong>Analytics Report</strong>
      Period: ${range}<br/>
      Generated: ${date}<br/>
      Source: LRMDS Admin Dashboard
    </div>
  </div>

  <!-- ══ 1. Summary Metrics ══ -->
  <div class="section-hd"><h2>Summary Metrics</h2></div>
  <div class="metrics-grid-r">
    <div class="mc-r">
      <div class="mc-r-label">Unique Users</div>
      <div class="mc-r-value">${parseInt(users).toLocaleString()}</div>
      <div class="mc-r-sub">Microsoft accounts signed in</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-label">Sessions</div>
      <div class="mc-r-value">${parseInt(sessions).toLocaleString()}</div>
      <div class="mc-r-sub">Total portal visits</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-label">File Views</div>
      <div class="mc-r-value">${parseInt(views).toLocaleString()}</div>
      <div class="mc-r-sub">Previews opened</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-label">Downloads</div>
      <div class="mc-r-value">${parseInt(downloads).toLocaleString()}</div>
      <div class="mc-r-sub">Files downloaded</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-label">Searches</div>
      <div class="mc-r-value">${parseInt(searches).toLocaleString()}</div>
      <div class="mc-r-sub">User search queries</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-label">Bookmarks</div>
      <div class="mc-r-value">${parseInt(bookmarks).toLocaleString()}</div>
      <div class="mc-r-sub">Resources saved to My Library</div>
    </div>
  </div>

  <!-- ══ 2. Activity Trend ══ -->
  <div class="section-hd"><h2>Activity Trend</h2></div>
  <div class="trend-wrap">
    <div class="trend-chart-box">
      <div class="chart-title">Downloads &amp; Views Over Time</div>
      ${trendPng
        ? `<img src="${trendPng}" alt="Trend chart"/>`
        : '<div class="no-chart">Chart not available</div>'
      }
    </div>
    <div class="trend-table-box">
      <table>
        <thead><tr>
          <th>Date</th>
          <th class="num">Downloads</th>
          <th class="num">Views</th>
        </tr></thead>
        <tbody>${trendTableRows || '<tr><td colspan="3" class="empty-cell">No trend data</td></tr>'}</tbody>
      </table>
    </div>
  </div>

  <!-- ══ 3. Charts Row ══ -->
  <div class="section-hd"><h2>Distribution Charts</h2></div>
  <div class="charts-row">
    <div class="chart-wrap">
      <div class="chart-title">Downloads by Resource Type</div>
      ${typePng
        ? `<img src="${typePng}" alt="Resource type chart"/>`
        : '<div class="no-chart">Chart not available</div>'
      }
    </div>
    <div class="chart-wrap">
      <div class="chart-title">Downloads by Subject</div>
      ${subjPng
        ? `<img src="${subjPng}" alt="Subject chart"/>`
        : '<div class="no-chart">Chart not available</div>'
      }
    </div>
  </div>

  <!-- ══ 4. Top 5 Files ══ -->
  <div class="section-hd"><h2>Top 5 Files by Downloads</h2></div>
  <table>
    <thead><tr>
      <th style="width:28px">#</th>
      <th>File Name</th>
      <th>Type</th>
      <th class="num">Downloads</th>
    </tr></thead>
    <tbody>${fileRows(topDl, 'downloads')}</tbody>
  </table>

  <div class="section-hd"><h2>Top 5 Files by Views</h2></div>
  <table>
    <thead><tr>
      <th style="width:28px">#</th>
      <th>File Name</th>
      <th>Type</th>
      <th class="num">Views</th>
    </tr></thead>
    <tbody>${fileRows(topVw, 'views')}</tbody>
  </table>

  <!-- ══ 5. Top 5 Folders ══ -->
  <div class="section-hd"><h2>Top 5 Folders</h2></div>
  <table>
    <thead><tr>
      <th style="width:28px">#</th>
      <th>Folder Path</th>
      <th class="num">Views</th>
      <th class="num">Downloads</th>
    </tr></thead>
    <tbody>${folderRows(folderData)}</tbody>
  </table>

  <!-- ══ 6. Search Queries ══ -->
  <div class="section-hd"><h2>Top Search Queries</h2></div>
  <div class="search-cloud">
    ${searchCloud || '<span style="color:#d1d5db;font-style:italic;font-size:12px">No search data recorded yet</span>'}
  </div>

  <!-- ── Footer ── -->
  <div class="report-footer">
    <span><strong>DepEd Carcar City LRMDS</strong> · Analytics Report · ${date}</span>
    <span>Period: ${range} · Generated by LRMDS Admin Dashboard</span>
  </div>

</div><!-- /page -->

<script>
// Auto-prompt print dialog if opened via exportReport()
// (Disabled — let user choose when to print)
</script>
</body>
</html>`;

  const win = window.open('', '_blank');
  if (!win) {
    alert('Pop-up blocked! Please allow pop-ups for this site and try again.');
    return;
  }
  win.document.write(html);
  win.document.close();
}

// ─── Utility used in report HTML builder ─────────────────────
function _escHtmlR(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}