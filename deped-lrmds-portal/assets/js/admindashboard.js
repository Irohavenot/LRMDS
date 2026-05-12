/* ============================================================
   LRMDS Admin Dashboard – DepEd Carcar City
   admindashboard.js

   CONFIG:
     DEMO        = true   → shows realistic sample data (safe default)
     DEMO        = false  → fetches live data from tracker.php
     TRACKER_URL          → auto-derived from the page URL so this JS
                            file can live in assets/js/ without breaking
                            the path to tracker.php in onedrive/
   ============================================================ */

// ── Config ────────────────────────────────────────────────────
const DEMO = true;

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
    folders: [
      { folder_path: 'Grade 6 › Science',             views: 210, downloads: 98 },
      { folder_path: 'Grade 4 › Mathematics',         views: 185, downloads: 87 },
      { folder_path: 'Grade 8 › English',             views: 160, downloads: 74 },
      { folder_path: 'Grade 10 › Araling Panlipunan', views: 140, downloads: 61 },
      { folder_path: 'Kinder › Filipino',             views: 130, downloads: 55 },
      { folder_path: 'Grade 7 › MAPEH',               views: 112, downloads: 48 },
      { folder_path: 'Grade 11 › Mathematics',        views: 95,  downloads: 42 },
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

async function fetchAllData() {
  if (DEMO) return demoData();

  const days = document.getElementById('date-range').value;
  const [summary, topDl, topVw, folders, types, grades, subjects, searches, trendRaw] = await Promise.all([
    get(''),
    get('?top&by=downloads&limit=8'),
    get('?top&by=views&limit=8'),
    get('?folders'),
    get('?by_type'),
    get('?by_grade'),
    get('?by_subject'),
    get('?searches'),
    get(`?trend&days=${days || 30}`),
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
    const name = (r.item_name || '—').length > 30 ? r.item_name.slice(0, 28) + '…' : r.item_name;
    const val  = r[by] ?? 0;
    const pct  = max > 0 ? Math.round(val / max * 100) : 0;
    return `<tr>
      <td class="rank">${i + 1}</td>
      <td><span class="file-name" title="${r.item_name}">${name}</span></td>
      <td>${typeBadge(r.item_type)}</td>
      <td><span class="count-pill ${pillCls}">${val}</span></td>
      <td style="width:100px">
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

function renderFolders(folders) {
  if (!Array.isArray(folders) || !folders.length) {
    document.getElementById('folder-body').innerHTML = '<tr><td colspan="3" class="empty">No folder data</td></tr>';
    return;
  }
  const maxV = Math.max(...folders.map(f => f.views ?? 0));
  const maxD = Math.max(...folders.map(f => f.downloads ?? 0));
  document.getElementById('folder-body').innerHTML = folders.map(f => {
    const pV   = maxV > 0 ? Math.round((f.views     ?? 0) / maxV * 100) : 0;
    const pD   = maxD > 0 ? Math.round((f.downloads ?? 0) / maxD * 100) : 0;
    const name = f.folder_path || f.folder || '—';
    return `<tr>
      <td><span class="folder-path" title="${name}">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--amber)"
             stroke-width="2" stroke-linecap="round" style="vertical-align:-2px;margin-right:5px">
          <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
        </svg>${name}</span></td>
      <td style="text-align:right">
        <div class="inline-bar" style="justify-content:flex-end">
          <div class="bar-track"><div class="bar-fill" style="background:#16A34A;width:${pV}%"></div></div>
          <span style="font-size:12px;font-family:'DM Mono',monospace;min-width:28px;text-align:right">${f.views ?? 0}</span>
        </div>
      </td>
      <td style="text-align:right">
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
      ${s.search_query}
      <span class="tag-count">${s.count}</span>
    </span>`
  ).join('');
}

// ═══════════════════════════════════════════════════════════════
//  CHARTS
// ═══════════════════════════════════════════════════════════════
function buildTrendChart(trend) {
  if (_trendChart) _trendChart.destroy();
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
      responsive: true, maintainAspectRatio: false,
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
  if (_typeChart) _typeChart.destroy();
  const COLORS = { SLM: '#2563EB', TG: '#16A34A', DLL: '#D97706', Video: '#7C3AED', Assessment: '#DC2626' };
  const labels = types.map(t => t.item_type || t.type);
  const vals   = types.map(t => t.downloads ?? 0);
  const colors = labels.map(l => COLORS[l] || '#9B9A95');
  const total  = vals.reduce((a, b) => a + b, 0);

  document.getElementById('type-legend').innerHTML = labels.map((l, i) =>
    `<div class="legend-item">
      <span class="legend-dot" style="background:${colors[i]}"></span>
      ${l} ${total > 0 ? Math.round(vals[i] / total * 100) : 0}%
    </div>`
  ).join('');

  _typeChart = new Chart(document.getElementById('type-chart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: vals, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '66%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} downloads` } },
      },
    },
  });
}

function buildSubjectChart(subjects) {
  if (!Array.isArray(subjects) || !subjects.length) return;
  if (_subjectChart) _subjectChart.destroy();
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
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { font: { size: 10, family: 'DM Mono' }, color: '#9B9A95' }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } },
        y: { ticks: { font: { size: 11 }, color: '#6B6A65' }, grid: { display: false }, border: { display: false } },
      },
    },
  });
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