/* ============================================================
   LRMDS Admin Dashboard – DepEd Carcar City
   admindashboard.js  (v1.1)

   CONFIG:
     DEMO        = true   → shows realistic sample data (safe default)
     DEMO        = false  → fetches live data from tracker.php
     TRACKER_URL          → auto-derived from the page URL so this JS
                            file can live in assets/js/ without breaking
                            the path to tracker.php in onedrive/

   FOLDER PATHS:
     analytics.js stores the full breadcrumb trail as the folder_path,
     e.g. "Resources › Grade 6 › Science › Quarter 1 › SLMs".
     renderFolders() now renders these deep paths properly — the deepest
     folder (last segment) is bolded, and › separators are styled.
   ============================================================ */

// ── Config ────────────────────────────────────────────────────
const DEMO = false;

// Resolve tracker.php relative to the *page* (admindashboard.php),
// not relative to this JS file's location in assets/js/.
const TRACKER_URL = (function () {
  const loc = window.location.pathname;            // e.g. /onedrive/admindashboard.php
  const dir = loc.substring(0, loc.lastIndexOf('/') + 1); // /onedrive/
  return dir + 'tracker.php';                      // /onedrive/tracker.php
})();

// ── State ─────────────────────────────────────────────────────
let _topData    = { downloads: [], views: [] };
let _trendChart, _typeChart, _subjectChart;
let _topTab     = 'downloads';

// ═══════════════════════════════════════════════════════════════
//  DEMO DATA  — realistic sample shown when DEMO = true
//  Includes deep folder paths (4+ levels) to demonstrate path rendering
// ═══════════════════════════════════════════════════════════════
function demoData() {
  function trend(days) {
    const labels = [], dl = [], vw = [];
    for (let i = days - 1; i >= 0; i--) {
      const d = new Date();
      d.setDate(d.getDate() - i);
      labels.push(d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }));
      dl.push(Math.round(18 + Math.random() * 45));
      vw.push(Math.round(35 + Math.random() * 85));
    }
    return { labels, dl, vw };
  }
  return {
    summary: { unique_users: 142, total_sessions: 389, total_views: 1847, total_downloads: 934, total_searches: 276 },
    top_downloads: [
      { item_name: 'Grade6_Science_Q1_SLM.pdf',  item_id: 'a1', item_type: 'SLM',        views: 210, downloads: 98 },
      { item_name: 'Grade4_Math_Q2_TG.pdf',       item_id: 'a2', item_type: 'TG',         views: 185, downloads: 87 },
      { item_name: 'Grade8_English_DLL_Q1.docx',  item_id: 'a3', item_type: 'DLL',        views: 160, downloads: 74 },
      { item_name: 'Grade10_AP_Q3_SLM.pdf',       item_id: 'a4', item_type: 'SLM',        views: 140, downloads: 61 },
      { item_name: 'Kinder_Filipino_SLM.pdf',     item_id: 'a5', item_type: 'SLM',        views: 130, downloads: 55 },
      { item_name: 'Grade7_MAPEH_Q2_Video.mp4',   item_id: 'a6', item_type: 'Video',      views: 112, downloads: 48 },
      { item_name: 'Grade11_Math_Assessment.pdf', item_id: 'a7', item_type: 'Assessment', views: 95,  downloads: 42 },
      { item_name: 'Grade3_Science_Q1_TG.pdf',    item_id: 'a8', item_type: 'TG',         views: 87,  downloads: 39 },
    ],
    top_views: [
      { item_name: 'Grade6_Science_Q1_SLM.pdf',  item_id: 'a1', item_type: 'SLM',        views: 210, downloads: 98 },
      { item_name: 'Grade4_Math_Q2_TG.pdf',       item_id: 'a2', item_type: 'TG',         views: 185, downloads: 87 },
      { item_name: 'Grade8_English_DLL_Q1.docx',  item_id: 'a3', item_type: 'DLL',        views: 160, downloads: 74 },
      { item_name: 'Grade10_AP_Q3_SLM.pdf',       item_id: 'a4', item_type: 'SLM',        views: 140, downloads: 61 },
      { item_name: 'Kinder_Filipino_SLM.pdf',     item_id: 'a5', item_type: 'SLM',        views: 130, downloads: 55 },
      { item_name: 'Grade7_MAPEH_Q2_Video.mp4',   item_id: 'a6', item_type: 'Video',      views: 112, downloads: 48 },
      { item_name: 'Grade11_Math_Assessment.pdf', item_id: 'a7', item_type: 'Assessment', views: 95,  downloads: 42 },
      { item_name: 'Grade3_Science_Q1_TG.pdf',    item_id: 'a8', item_type: 'TG',         views: 87,  downloads: 39 },
    ],
    // Deep folder paths — mirrors what analytics.js actually records
    // (full breadcrumb: "Grade Level › Subject › Quarter › Resource Type")
    folders: [
      { folder_path: 'Grade 6 › Science › Quarter 1 › SLMs',                   views: 210, downloads: 98 },
      { folder_path: 'Grade 4 › Mathematics › Quarter 2 › Teachers Guide',      views: 185, downloads: 87 },
      { folder_path: 'Grade 8 › English › Quarter 1 › Daily Lesson Logs',       views: 160, downloads: 74 },
      { folder_path: 'Grade 10 › Araling Panlipunan › Quarter 3 › SLMs',        views: 140, downloads: 61 },
      { folder_path: 'Kinder › Filipino › Quarter 1 › SLMs',                    views: 130, downloads: 55 },
      { folder_path: 'Grade 7 › MAPEH › Quarter 2 › Videos',                    views: 112, downloads: 48 },
      { folder_path: 'Grade 11 › Mathematics › Quarter 1 › Assessments',        views: 95,  downloads: 42 },
    ],
    types: [
      { item_type: 'SLM',        downloads: 420 },
      { item_type: 'TG',         downloads: 218 },
      { item_type: 'DLL',        downloads: 142 },
      { item_type: 'Video',      downloads: 98  },
      { item_type: 'Assessment', downloads: 56  },
    ],
    grades: [
      { grade: 'Kinder',   downloads: 55 }, { grade: 'Grade 1',  downloads: 31 },
      { grade: 'Grade 2',  downloads: 28 }, { grade: 'Grade 3',  downloads: 39 },
      { grade: 'Grade 4',  downloads: 87 }, { grade: 'Grade 5',  downloads: 62 },
      { grade: 'Grade 6',  downloads: 98 }, { grade: 'Grade 7',  downloads: 48 },
      { grade: 'Grade 8',  downloads: 74 }, { grade: 'Grade 9',  downloads: 44 },
      { grade: 'Grade 10', downloads: 61 }, { grade: 'Grade 11', downloads: 42 },
      { grade: 'Grade 12', downloads: 35 },
    ],
    subjects: [
      { subject: 'Mathematics',        downloads: 214 },
      { subject: 'Science',            downloads: 187 },
      { subject: 'English',            downloads: 162 },
      { subject: 'Filipino',           downloads: 140 },
      { subject: 'Araling Panlipunan', downloads: 118 },
      { subject: 'MAPEH',              downloads: 76  },
      { subject: 'EPP/TLE',            downloads: 37  },
    ],
    searches: [
      { search_query: 'science melc',       count: 42 },
      { search_query: 'grade 6 q1',         count: 38 },
      { search_query: 'math tg',            count: 31 },
      { search_query: 'araling panlipunan', count: 27 },
      { search_query: 'dll week 1',         count: 24 },
      { search_query: 'kinder slm',         count: 21 },
      { search_query: 'q3 assessment',      count: 18 },
      { search_query: 'english grade 8',    count: 15 },
      { search_query: 'mapeh grade 7',      count: 13 },
      { search_query: 'grade 10 tg',        count: 11 },
    ],
    trend: trend(30),
  };
}

// ═══════════════════════════════════════════════════════════════
//  FETCH HELPERS
// ═══════════════════════════════════════════════════════════════
async function get(endpoint) {
  const r = await fetch(TRACKER_URL + endpoint);
  return r.ok ? r.json() : null;
}

// Returns the currently-selected day window (0 = all time)
function getDays() {
  const el = document.getElementById('date-range');
  return el ? parseInt(el.value, 10) || 0 : 30;
}

// Append &days=N (or nothing for all-time) to any endpoint that supports it
function dq(base, days) {
  if (!days) return base; // 0 = all time, no filter
  const sep = base.includes('?') ? '&' : '?';
  return `${base}${sep}days=${days}`;
}

async function fetchAllData() {
  const days = getDays();

  if (DEMO) {
    // Re-generate demo trend data for the selected window
    const d = demoData();
    d.trend = (function () {
      const n = days || 90;
      const labels = [], dl = [], vw = [];
      for (let i = n - 1; i >= 0; i--) {
        const dt = new Date();
        dt.setDate(dt.getDate() - i);
        labels.push(dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' }));
        dl.push(Math.round(18 + Math.random() * 45));
        vw.push(Math.round(35 + Math.random() * 85));
      }
      return { labels, dl, vw };
    })();
    // Scale summary counts proportionally for demo when day window changes
    const scale = days ? Math.min(days / 30, 1) : 1;
    if (days && days < 30) {
      d.summary.total_views     = Math.round(1847 * scale);
      d.summary.total_downloads = Math.round(934  * scale);
      d.summary.total_searches  = Math.round(276  * scale);
      d.summary.total_sessions  = Math.round(389  * scale);
    }
    return d;
  }

  // ── Live mode: pass days to every endpoint that supports it ──
  const [summary, topDl, topVw, folders, types, grades, subjects, searches, trendRaw] = await Promise.all([
    get(dq('',                        days)),   // summary (tracker.php supports ?days via WHERE ts>=)
    get(dq('?top&by=downloads&limit=8&withpath=1', days)),
    get(dq('?top&by=views&limit=8&withpath=1',     days)),
    get(dq('?folders',                  days)),
    get(dq('?by_type',                  days)),
    get(dq('?by_grade',                 days)),
    get(dq('?by_subject',               days)),
    get(dq('?searches',                 days)),
    get(dq('?trend',                    days)),
  ]);

  // Convert daily trend rows → chart-ready arrays
  const trend = { labels: [], dl: [], vw: [] };
  if (Array.isArray(trendRaw)) {
    trendRaw.forEach(r => {
      trend.labels.push(r.day);
      trend.dl.push(r.downloads);
      trend.vw.push(r.views);
    });
  }

  return { summary, top_downloads: topDl, top_views: topVw, folders, types, grades, subjects, searches, trend };
}

// ═══════════════════════════════════════════════════════════════
//  RENDERERS
// ═══════════════════════════════════════════════════════════════
function renderMetrics(s) {
  if (!s) return;
  document.getElementById('m-users').textContent     = (s.unique_users    ?? 0).toLocaleString();
  document.getElementById('m-sessions').textContent  = (s.total_sessions  ?? 0).toLocaleString();
  document.getElementById('m-views').textContent     = (s.total_views     ?? 0).toLocaleString();
  document.getElementById('m-downloads').textContent = (s.total_downloads ?? 0).toLocaleString();
  document.getElementById('m-searches').textContent  = (s.total_searches  ?? 0).toLocaleString();
}

function typeBadge(type) {
  const safe = (type || '').replace(/[^a-zA-Z]/g, '');
  const cls  = ['SLM', 'TG', 'DLL', 'Video', 'Assessment'].includes(safe) ? `badge-${safe}` : 'badge-default';
  return `<span class="file-type-badge ${cls}">${type || '—'}</span>`;
}

function renderTopFiles(data, by) {
  if (!Array.isArray(data) || !data.length) {
    document.getElementById('top-files-body').innerHTML = '<tr><td colspan="5" class="empty">No data</td></tr>';
    return;
  }
  const max     = Math.max(...data.map(r => r[by] ?? 0));
  const color   = by === 'downloads' ? '#2563EB' : '#16A34A';
  const pillCls = by === 'downloads' ? 'pill-dl'  : 'pill-vw';

  document.getElementById('top-files-body').innerHTML = data.map((r, i) => {
    const name    = r.item_name || '—';
    const shortName = name.length > 34 ? name.slice(0, 32) + '…' : name;
    const val     = r[by] ?? 0;
    const pct     = max > 0 ? Math.round(val / max * 100) : 0;
    // folder path — strip the leading root segment for brevity
    const rawPath = r.folder_path || '';
    const pathParts = rawPath.split(' › ');
    const shortPath = pathParts.length > 1 ? pathParts.slice(1).join(' › ') : rawPath;
    const pathHtml  = rawPath
      ? `<div class="file-path" title="${escHtml(rawPath)}">
           <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--amber)"
                stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;vertical-align:-1px">
             <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
           </svg>
           ${escHtml(shortPath.length > 42 ? '…' + shortPath.slice(-40) : shortPath)}
         </div>`
      : '';
    // ext badge
    const ext    = r.file_ext ? r.file_ext.toUpperCase() : '';
    const extBadge = ext ? `<span class="ext-badge ext-${ext.toLowerCase()}">${ext}</span>` : '';
    return `<tr>
      <td class="rank">${i + 1}</td>
      <td>
        <span class="file-name" title="${escHtml(name)}">${escHtml(shortName)}</span>
        ${pathHtml}
      </td>
      <td>${typeBadge(r.item_type)}${extBadge}</td>
      <td><span class="count-pill ${pillCls}">${val}</span></td>
      <td style="width:90px">
        <div class="inline-bar">
          <div class="bar-track"><div class="bar-fill" style="background:${color};width:${pct}%"></div></div>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function switchTopTab(tab, el) {
  _topTab = tab;
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  el.classList.add('active');
  renderTopFiles(_topData[tab], tab);
}

/**
 * renderFolders — renders the top-folders table.
 *
 * folder_path is stored by analytics.js as the full breadcrumb trail,
 * e.g.  "Grade 6 › Science › Quarter 1 › SLMs"
 * This works whether the folder is 1 level or 6 levels deep — the full
 * path is always recorded, so nested subfolders are never lost.
 *
 * Display strategy:
 *   • Split on " › " to get individual segments
 *   • Show ALL segments (no truncation of the path itself)
 *   • Bold the last segment (the deepest/most-specific folder)
 *   • If the whole string is very long, let it word-wrap gracefully
 */
function renderFolders(folders) {
  if (!Array.isArray(folders) || !folders.length) {
    document.getElementById('folder-body').innerHTML =
      '<tr><td colspan="3" class="empty">No folder data yet</td></tr>';
    return;
  }
  const maxV = Math.max(...folders.map(f => f.views ?? 0));
  const maxD = Math.max(...folders.map(f => f.downloads ?? 0));

  document.getElementById('folder-body').innerHTML = folders.map(f => {
    const pV   = maxV > 0 ? Math.round((f.views     ?? 0) / maxV * 100) : 0;
    const pD   = maxD > 0 ? Math.round((f.downloads ?? 0) / maxD * 100) : 0;
    const raw  = f.folder_path || f.folder || '—';

    // Build styled path HTML: segments joined by styled › separators
    const segments = raw.split(' › ');
    const pathHtml = segments.map((seg, idx) => {
      const escaped = escHtml(seg.trim());
      const isLast  = idx === segments.length - 1;
      const part    = isLast
        ? `<span class="path-leaf">${escaped}</span>`
        : `<span>${escaped}</span>`;
      return idx < segments.length - 1
        ? part + `<span class="path-sep"> › </span>`
        : part;
    }).join('');

    return `<tr>
      <td>
        <span class="folder-path" title="${escHtml(raw)}">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--amber)"
               stroke-width="2" stroke-linecap="round" class="folder-icon"
               style="display:inline-block;vertical-align:-2px;margin-right:4px;flex-shrink:0">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>${pathHtml}
        </span>
      </td>
      <td style="text-align:right;vertical-align:middle">
        <div class="inline-bar" style="justify-content:flex-end">
          <div class="bar-track"><div class="bar-fill" style="background:#16A34A;width:${pV}%"></div></div>
          <span style="font-size:12px;font-family:'DM Mono',monospace;min-width:28px;text-align:right">${f.views ?? 0}</span>
        </div>
      </td>
      <td style="text-align:right;vertical-align:middle">
        <div class="inline-bar" style="justify-content:flex-end">
          <div class="bar-track"><div class="bar-fill" style="background:#2563EB;width:${pD}%"></div></div>
          <span style="font-size:12px;font-family:'DM Mono',monospace;min-width:28px;text-align:right">${f.downloads ?? 0}</span>
        </div>
      </td>
    </tr>`;
  }).join('');
}

function renderGradeBars(grades) {
  if (!Array.isArray(grades) || !grades.length) {
    document.getElementById('grade-bars').innerHTML = '<div class="empty">No grade data</div>';
    return;
  }
  const max = Math.max(...grades.map(g => g.downloads ?? 0));
  document.getElementById('grade-bars').innerHTML = grades.map(g => {
    const pct   = max > 0 ? Math.round((g.downloads ?? 0) / max * 100) : 0;
    const label = (g.grade || '—').replace('Grade ', 'G');
    return `<div class="grade-row">
      <span class="grade-label">${label}</span>
      <div class="grade-bar-track"><div class="grade-bar-fill" style="width:${pct}%"></div></div>
      <span class="grade-count">${g.downloads ?? 0}</span>
    </div>`;
  }).join('');
}

function renderSearchTags(searches) {
  if (!Array.isArray(searches) || !searches.length) {
    document.getElementById('search-tags').innerHTML = '<div class="empty">No search data</div>';
    return;
  }
  document.getElementById('search-tags').innerHTML = searches.map(s =>
    `<span class="search-tag">
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0">
        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
      </svg>
      ${escHtml(s.search_query)}
      <span class="tag-count">${s.count}</span>
    </span>`
  ).join('');
}

// ═══════════════════════════════════════════════════════════════
//  CHARTS
// ═══════════════════════════════════════════════════════════════

// Safely destroy a chart and reset its canvas so Chart.js won't
// complain about "canvas already in use" after a resize cycle.
function destroyChart(chart, canvasId) {
  if (chart) {
    chart.destroy();
  }
  // Reset canvas dimensions so it repaints cleanly
  const canvas = document.getElementById(canvasId);
  if (canvas) {
    canvas.style.width  = '';
    canvas.style.height = '';
    canvas.removeAttribute('width');
    canvas.removeAttribute('height');
  }
  return null;
}

function buildTrendChart(trend) {
  _trendChart = destroyChart(_trendChart, 'trend-chart');
  _trendChart = new Chart(document.getElementById('trend-chart'), {
    type: 'line',
    data: {
      labels: trend.labels,
      datasets: [
        {
          label: 'Downloads', data: trend.dl,
          borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,.07)',
          tension: .35, pointRadius: 0, fill: true, borderWidth: 1.5,
        },
        {
          label: 'Views', data: trend.vw,
          borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,.05)',
          tension: .35, pointRadius: 0, fill: true, borderWidth: 1.5,
          borderDash: [5, 3],
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { font: { size: 10, family: 'DM Mono' }, maxTicksLimit: 8, color: '#9B9A95' }, grid: { display: false }, border: { display: false } },
        y: { ticks: { font: { size: 10, family: 'DM Mono' }, color: '#9B9A95' }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } },
      },
    },
  });
}

function buildTypeChart(types) {
  if (!Array.isArray(types) || !types.length) return;
  _typeChart = destroyChart(_typeChart, 'type-chart');
  const COLORS = { SLM: '#2563EB', TG: '#16A34A', DLL: '#D97706', Video: '#7C3AED', Assessment: '#DC2626' };
  const labels = types.map(t => t.item_type || t.type);
  const vals   = types.map(t => t.downloads ?? 0);
  const colors = labels.map(l => COLORS[l] || '#9B9A95');
  const total  = vals.reduce((a, b) => a + b, 0);

  document.getElementById('type-legend').innerHTML = labels.map((l, i) =>
    `<div class="legend-item">
      <span class="legend-dot" style="background:${colors[i]}"></span>
      ${escHtml(l)} ${total > 0 ? Math.round(vals[i] / total * 100) : 0}%
    </div>`
  ).join('');

  _typeChart = new Chart(document.getElementById('type-chart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '66%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} downloads` } },
      },
    },
  });
}

function buildSubjectChart(subjects) {
  if (!Array.isArray(subjects) || !subjects.length) return;
  _subjectChart = destroyChart(_subjectChart, 'subject-chart');
  const labels = subjects.map(s => s.subject || '—');
  const vals   = subjects.map(s => s.downloads ?? 0);
  _subjectChart = new Chart(document.getElementById('subject-chart'), {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Downloads', data: vals,
        backgroundColor: 'rgba(37,99,235,.15)',
        borderColor: '#2563EB', borderWidth: 1.5,
        borderRadius: 5,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { font: { size: 10, family: 'DM Mono' }, color: '#9B9A95' }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } },
        y: { ticks: { font: { size: 11 }, color: '#6B6A65' }, grid: { display: false }, border: { display: false } },
      },
    },
  });
}

// ═══════════════════════════════════════════════════════════════
//  UTILITIES
// ═══════════════════════════════════════════════════════════════
function escHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

// ═══════════════════════════════════════════════════════════════
//  MAIN LOADER
// ═══════════════════════════════════════════════════════════════
async function loadAll() {
  document.getElementById('last-updated').textContent = 'Loading…';
  document.getElementById('demo-banner').style.display = DEMO ? 'flex' : 'none';

  try {
    const d = await fetchAllData();

    renderMetrics(d.summary);

    _topData = {
      downloads: d.top_downloads || [],
      views:     d.top_views     || d.top_downloads || [],
    };
    renderTopFiles(_topData[_topTab], _topTab);

    if (d.folders)  renderFolders(d.folders);
    if (d.grades)   renderGradeBars(d.grades);
    if (d.searches) renderSearchTags(d.searches);
    if (d.trend)    buildTrendChart(d.trend);
    if (d.types)    buildTypeChart(d.types);
    if (d.subjects) buildSubjectChart(d.subjects);

    const now = new Date();
    document.getElementById('last-updated').textContent =
      'Updated ' + now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });

  } catch (err) {
    console.error('Dashboard load error:', err);
    document.getElementById('last-updated').textContent = 'Load failed — check console';
  }
}

// Run on page load
loadAll();
// ═══════════════════════════════════════════════════════════════
//  DOWNLOAD LOG — who downloaded what and when
// ═══════════════════════════════════════════════════════════════
var _logData = [];
var _logQuery = '';

async function loadDownloadLog() {
  var days = getDays();
  var tbody = document.getElementById('log-body');
  var countEl = document.getElementById('log-count');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="5" class="empty"><div class="spinner" style="display:inline-block;margin-right:6px"></div>Loading\u2026</td></tr>';

  try {
    var url = TRACKER_URL + dq('?log&limit=500', days);
    var r = await fetch(url);
    _logData = r.ok ? await r.json() : [];
  } catch (e) {
    _logData = [];
  }

  renderLogTable(_logQuery);
  if (countEl) countEl.textContent = _logData.length;
}

function renderLogTable(query) {
  var tbody = document.getElementById('log-body');
  if (!tbody) return;

  var q = (query || '').toLowerCase().trim();
  var filtered = q
    ? _logData.filter(function(r) {
        return (r.user_name   || '').toLowerCase().includes(q) ||
               (r.item_name   || '').toLowerCase().includes(q) ||
               (r.item_type   || '').toLowerCase().includes(q) ||
               (r.folder_path || '').toLowerCase().includes(q);
      })
    : _logData;

  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="empty">' + (q ? 'No matching records' : 'No downloads recorded yet') + '</td></tr>';
    return;
  }

  tbody.innerHTML = filtered.map(function(r) {
    var dt = r.downloaded_at || '';
    var display = dt;
    try {
      var d = new Date(dt.replace(' ', 'T'));
      display = d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
              + ' · '
              + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    } catch(e2) {}

    var name = r.item_name || '—';
    var shortName = name.length > 40 ? name.slice(0, 38) + '…' : name;

    // Full name + email for accountability
    var user    = r.user_name  || '—';
    var email   = r.user_email || '';
    var initial = (user !== '—' ? user : email).charAt(0).toUpperCase() || '?';
    var userHtml = '<div class="user-chip">'
      + '<span class="user-avatar-sm">' + escHtml(initial) + '</span>'
      + '<div class="user-chip-info">'
      + '<span class="user-chip-name">' + escHtml(user) + '</span>'
      + (email ? '<span class="user-chip-email">' + escHtml(email) + '</span>' : '')
      + '</div></div>';

    // Resource type badge + actual file extension badge
    var ext = r.file_ext ? r.file_ext.toUpperCase() : '';
    var extBadge = ext ? '<span class="ext-badge ext-' + r.file_ext.toLowerCase() + '">' + ext + '</span>' : '';
    var typeCombined = typeBadge(r.item_type) + extBadge;

    // Folder path breadcrumb with folder icon
    var path = r.folder_path || '—';
    var pathParts = path.split(' › ');
    var shortPath = pathParts.length > 1 ? pathParts.slice(1).join(' › ') : path;
    if (shortPath.length > 50) shortPath = '…' + shortPath.slice(-48);
    var pathHtml = path !== '—'
      ? '<span class="folder-path-sm" title="' + escHtml(path) + '">'
        + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" style="margin-right:3px;vertical-align:-1px;flex-shrink:0"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>'
        + escHtml(shortPath) + '</span>'
      : '<span style="color:var(--text-3)">—</span>';

    return '<tr>' +
      '<td style="white-space:nowrap;font-size:11px;font-family:\'DM Mono\',monospace;color:var(--text-3)">' + escHtml(display) + '</td>' +
      '<td>' + userHtml + '</td>' +
      '<td><span class="file-name" title="' + escHtml(name) + '">' + escHtml(shortName) + '</span></td>' +
      '<td>' + typeCombined + '</td>' +
      '<td>' + pathHtml + '</td>' +
      '</tr>';
  }).join('');
}

// Wire log search input
(function() {
  var si = document.getElementById('log-search');
  if (si) {
    si.addEventListener('input', function(e) {
      _logQuery = e.target.value;
      renderLogTable(_logQuery);
    });
  }
})();

// Also load log on page init
loadDownloadLog();