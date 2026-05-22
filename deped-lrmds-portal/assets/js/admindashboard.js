/* ============================================================
   LRMDS Admin Dashboard – DepEd Carcar City
   admindashboard.js  (v1.2)

   Changes from v1.1:
     • Fixed: Today (days=1) now works correctly via getDays()
     • Fixed: Users table colspan updated to 8 (added Bookmarks column)
     • Added: Bookmarks metric card (m-bookmarks)
     • Added: Most Bookmarked Resources section (renderBookmarks)
     • Added: Bookmarks column in Portal Users table
     • Added: Demo bookmark data (top_bookmarked + bookmarks per user)
     • Improved: fetchAllData fetches ?top_bookmarked in parallel
   ============================================================ */

// ── Config ────────────────────────────────────────────────────
const DEMO = false;

const TRACKER_URL = (function () {
  const loc = window.location.pathname;
  const dir = loc.substring(0, loc.lastIndexOf('/') + 1);
  return dir + 'tracker.php';
})();

// ── State ─────────────────────────────────────────────────────
let _topData    = { downloads: [], views: [] };
let _trendChart, _typeChart, _subjectChart;
let _topTab     = 'downloads';

// ═══════════════════════════════════════════════════════════════
//  DEMO DATA
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
    summary: {
      unique_users: 142, total_sessions: 389,
      total_views: 1847, total_downloads: 934,
      total_searches: 276, total_bookmarks: 183,
    },
    top_downloads: [
      { item_name: 'Grade6_Science_Q1_SLM.pdf',  item_id: 'a1', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 6 › Science › Quarter 1 › SLMs',           views: 210, downloads: 98 },
      { item_name: 'Grade4_Math_Q2_TG.pdf',       item_id: 'a2', item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 4 › Mathematics › Quarter 2 › Teachers Guide', views: 185, downloads: 87 },
      { item_name: 'Grade8_English_DLL_Q1.docx',  item_id: 'a3', item_type: 'DLL',        file_ext: 'docx', folder_path: 'Grade 8 › English › Quarter 1 › Daily Lesson Logs', views: 160, downloads: 74 },
      { item_name: 'Grade10_AP_Q3_SLM.pdf',       item_id: 'a4', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 10 › Araling Panlipunan › Quarter 3 › SLMs', views: 140, downloads: 61 },
      { item_name: 'Kinder_Filipino_SLM.pdf',     item_id: 'a5', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Kinder › Filipino › Quarter 1 › SLMs',             views: 130, downloads: 55 },
      { item_name: 'Grade7_MAPEH_Q2_Video.mp4',   item_id: 'a6', item_type: 'Video',      file_ext: 'mp4',  folder_path: 'Grade 7 › MAPEH › Quarter 2 › Videos',             views: 112, downloads: 48 },
      { item_name: 'Grade11_Math_Assessment.pdf', item_id: 'a7', item_type: 'Assessment', file_ext: 'pdf',  folder_path: 'Grade 11 › Mathematics › Quarter 1 › Assessments', views: 95,  downloads: 42 },
      { item_name: 'Grade3_Science_Q1_TG.pdf',    item_id: 'a8', item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 3 › Science › Quarter 1 › Teachers Guide',   views: 87,  downloads: 39 },
    ],
    top_views: [
      { item_name: 'Grade6_Science_Q1_SLM.pdf',  item_id: 'a1', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 6 › Science › Quarter 1 › SLMs',           views: 210, downloads: 98 },
      { item_name: 'Grade4_Math_Q2_TG.pdf',       item_id: 'a2', item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 4 › Mathematics › Quarter 2 › Teachers Guide', views: 185, downloads: 87 },
      { item_name: 'Grade8_English_DLL_Q1.docx',  item_id: 'a3', item_type: 'DLL',        file_ext: 'docx', folder_path: 'Grade 8 › English › Quarter 1 › Daily Lesson Logs', views: 160, downloads: 74 },
      { item_name: 'Grade10_AP_Q3_SLM.pdf',       item_id: 'a4', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 10 › Araling Panlipunan › Quarter 3 › SLMs', views: 140, downloads: 61 },
      { item_name: 'Kinder_Filipino_SLM.pdf',     item_id: 'a5', item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Kinder › Filipino › Quarter 1 › SLMs',             views: 130, downloads: 55 },
      { item_name: 'Grade7_MAPEH_Q2_Video.mp4',   item_id: 'a6', item_type: 'Video',      file_ext: 'mp4',  folder_path: 'Grade 7 › MAPEH › Quarter 2 › Videos',             views: 112, downloads: 48 },
      { item_name: 'Grade11_Math_Assessment.pdf', item_id: 'a7', item_type: 'Assessment', file_ext: 'pdf',  folder_path: 'Grade 11 › Mathematics › Quarter 1 › Assessments', views: 95,  downloads: 42 },
      { item_name: 'Grade3_Science_Q1_TG.pdf',    item_id: 'a8', item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 3 › Science › Quarter 1 › Teachers Guide',   views: 87,  downloads: 39 },
    ],
    // Most-bookmarked files (respects date range like all other sections)
    top_bookmarked: [
      { item_name: 'Grade6_Science_Q1_SLM.pdf',  item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 6 › Science › Quarter 1 › SLMs',             bookmark_count: 38 },
      { item_name: 'Grade4_Math_Q2_TG.pdf',       item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 4 › Mathematics › Quarter 2 › Teachers Guide', bookmark_count: 31 },
      { item_name: 'Kinder_Filipino_SLM.pdf',     item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Kinder › Filipino › Quarter 1 › SLMs',               bookmark_count: 24 },
      { item_name: 'Grade8_English_DLL_Q1.docx',  item_type: 'DLL',        file_ext: 'docx', folder_path: 'Grade 8 › English › Quarter 1 › Daily Lesson Logs',   bookmark_count: 19 },
      { item_name: 'Grade11_Math_Assessment.pdf', item_type: 'Assessment', file_ext: 'pdf',  folder_path: 'Grade 11 › Mathematics › Quarter 1 › Assessments',   bookmark_count: 15 },
      { item_name: 'Grade7_MAPEH_Q2_Video.mp4',   item_type: 'Video',      file_ext: 'mp4',  folder_path: 'Grade 7 › MAPEH › Quarter 2 › Videos',               bookmark_count: 12 },
      { item_name: 'Grade10_AP_Q3_SLM.pdf',       item_type: 'SLM',        file_ext: 'pdf',  folder_path: 'Grade 10 › Araling Panlipunan › Quarter 3 › SLMs',   bookmark_count: 9  },
      { item_name: 'Grade3_Science_Q1_TG.pdf',    item_type: 'TG',         file_ext: 'pdf',  folder_path: 'Grade 3 › Science › Quarter 1 › Teachers Guide',     bookmark_count: 7  },
    ],
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
    search_success: (function() {
      // Last 10 days of representative search data — realistic, not random
      var rawDays = [
        { success: 18, zero: 4 },
        { success: 22, zero: 5 },
        { success: 15, zero: 6 },
        { success: 27, zero: 3 },
        { success: 20, zero: 7 },
        { success: 31, zero: 4 },
        { success: 24, zero: 8 },
        { success: 19, zero: 5 },
        { success: 28, zero: 3 },
        { success: 33, zero: 6 },
      ];
      var trend = rawDays.map(function(row, i) {
        var d = new Date(); d.setDate(d.getDate() - (9 - i));
        return {
          day: d.toISOString().slice(0, 10),
          total_searches: row.success + row.zero,
          success_count:  row.success,
          zero_count:     row.zero,
        };
      });
      var totSuccess = trend.reduce(function(a,r){return a + r.success_count;}, 0);
      var totZero    = trend.reduce(function(a,r){return a + r.zero_count;}, 0);
      return {
        trend: trend,
        totals: { total: totSuccess + totZero, success: totSuccess, zero_results: totZero },
        // Zero-result queries — these are what analysts should add to the repository
        failed_queries: [
          { search_query: 'grade 7 epp video',      count: 8 },
          { search_query: 'kinder mapeh q4 slm',    count: 6 },
          { search_query: 'dll grade 12 q2',        count: 5 },
          { search_query: 'science grade 2 q3 slm', count: 4 },
          { search_query: 'filipino grade 9 dll',   count: 3 },
        ],
      };
    })(),
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

// Returns the currently-selected day window.
// "Today" is stored as value="1" (last 1 day — from midnight today).
// Returns 0 for all-time, or a positive integer for a preset window.
function getDays() {
  const el = document.getElementById('date-range');
  if (!el) return 30;
  if (el.value === 'custom') return 'custom';
  return parseInt(el.value, 10) || 0;
}

// Returns the custom date range if selected, otherwise null.
function getCustomRange() {
  const from = document.getElementById('custom-from');
  const to   = document.getElementById('custom-to');
  if (!from || !to || !from.value || !to.value) return null;
  return { from: from.value, to: to.value };
}

// Appends date filter params to a base URL.
// Supports both preset days and custom date_from/date_to.
function dq(base, days) {
  if (days === 'custom') {
    const range = getCustomRange();
    if (!range) return base;
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}date_from=${encodeURIComponent(range.from)}&date_to=${encodeURIComponent(range.to)}`;
  }
  if (!days) return base;
  const sep = base.includes('?') ? '&' : '?';
  return `${base}${sep}days=${days}`;
}

async function fetchAllData() {
  const days = getDays();

  if (DEMO) {
    const d = demoData();
    // Re-generate trend for selected window
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
    // Scale summary counts for short windows
    if (days && days < 30) {
      const scale = Math.min(days / 30, 1);
      d.summary.total_views      = Math.round(1847 * scale);
      d.summary.total_downloads  = Math.round(934  * scale);
      d.summary.total_searches   = Math.round(276  * scale);
      d.summary.total_sessions   = Math.round(389  * scale);
      d.summary.total_bookmarks  = Math.round(183  * scale);
    }
    return d;
  }

  // Live mode — fetch everything in parallel  // Note: top_bookmarked now respects the selected date range (fixed in tracker.php)
  const [summary, topDl, topVw, folders, types, grades, subjects, searches, trendRaw, topBookmarked, searchSuccess] = await Promise.all([
    get(dq('',                                     days)),
    get(dq('?top&by=downloads&limit=8&withpath=1', days)),
    get(dq('?top&by=views&limit=8&withpath=1',     days)),
    get(dq('?folders',                             days)),
    get(dq('?by_type',                             days)),
    get(dq('?by_grade',                            days)),
    get(dq('?by_subject',                          days)),
    get(dq('?searches',                            days)),
    get(dq('?trend',                               days)),
    get(dq('?top_bookmarked&limit=8', days)),              // respects selected date range
    get(dq('?search_success',                      days)),
  ]);

  const trend = { labels: [], dl: [], vw: [] };
  if (Array.isArray(trendRaw)) {
    trendRaw.forEach(r => {
      trend.labels.push(r.day);
      trend.dl.push(r.downloads);
      trend.vw.push(r.views);
    });
  }

  return { summary, top_downloads: topDl, top_views: topVw, folders, types, grades, subjects, searches, trend, top_bookmarked: topBookmarked, search_success: searchSuccess };
}

// ═══════════════════════════════════════════════════════════════
//  RENDERERS
// ═══════════════════════════════════════════════════════════════
function renderMetrics(s) {
  if (!s) return;
  document.getElementById('m-users').textContent     = (s.unique_users     ?? 0).toLocaleString();
  document.getElementById('m-sessions').textContent  = (s.total_sessions   ?? 0).toLocaleString();
  document.getElementById('m-views').textContent     = (s.total_views      ?? 0).toLocaleString();
  document.getElementById('m-downloads').textContent = (s.total_downloads  ?? 0).toLocaleString();
  document.getElementById('m-searches').textContent  = (s.total_searches   ?? 0).toLocaleString();
  document.getElementById('m-bookmarks').textContent = (s.total_bookmarks  ?? 0).toLocaleString();
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
    const name      = r.item_name || '—';
    const shortName = name.length > 34 ? name.slice(0, 32) + '…' : name;
    const val       = r[by] ?? 0;
    const pct       = max > 0 ? Math.round(val / max * 100) : 0;
    const rawPath   = r.folder_path || '';
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
    const ext      = r.file_ext ? r.file_ext.toUpperCase() : '';
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

function renderFolders(folders) {
  if (!Array.isArray(folders) || !folders.length) {
    document.getElementById('folder-body').innerHTML =
      '<tr><td colspan="3" class="empty">No folder data yet</td></tr>';
    return;
  }
  const maxV = Math.max(...folders.map(f => f.views ?? 0));
  const maxD = Math.max(...folders.map(f => f.downloads ?? 0));

  document.getElementById('folder-body').innerHTML = folders.map(f => {
    const pV  = maxV > 0 ? Math.round((f.views     ?? 0) / maxV * 100) : 0;
    const pD  = maxD > 0 ? Math.round((f.downloads ?? 0) / maxD * 100) : 0;
    const raw = f.folder_path || f.folder || '—';

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
               stroke-width="2" stroke-linecap="round"
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

// ── Most Bookmarked Resources ─────────────────────────────────
function renderBookmarks(data) {
  const el = document.getElementById('bookmarks-list');
  if (!el) return;

  if (!Array.isArray(data) || !data.length) {
    el.innerHTML = '<div class="empty">No bookmarks recorded yet — files appear here once teachers save them to My Library</div>';
    return;
  }

  const max = Math.max(...data.map(r => r.bookmark_count ?? 0));

  el.innerHTML = data.map((r, i) => {
    const name      = r.item_name || '—';
    const shortName = name.length > 50 ? name.slice(0, 48) + '…' : name;
    const count     = r.bookmark_count ?? 0;
    const pct       = max > 0 ? Math.round(count / max * 100) : 0;
    const rawPath   = r.folder_path || '';
    const pathParts = rawPath.split(' › ');
    const shortPath = pathParts.length > 1 ? pathParts.slice(1).join(' › ') : rawPath;
    const ext       = r.file_ext ? r.file_ext.toUpperCase() : '';
    const extBadge  = ext ? `<span class="ext-badge ext-${ext.toLowerCase()}">${ext}</span>` : '';

    // Gold / silver / bronze for top 3
    const rankEmoji = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i + 1}`;

    return `<div class="bm-item">
      <span class="bm-rank-num">${rankEmoji}</span>
      <div class="bm-item-body">
        <div class="bm-item-name" title="${escHtml(name)}">
          ${escHtml(shortName)}
          ${typeBadge(r.item_type)}${extBadge}
        </div>
        ${rawPath ? `<div class="bm-item-path">
          <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--amber)"
               stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;vertical-align:-1px;margin-right:3px">
            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
          </svg>${escHtml(shortPath.length > 60 ? '…' + shortPath.slice(-58) : shortPath)}
        </div>` : ''}
      </div>
      <div class="bm-bar-wrap">
        <div class="bar-track" style="height:6px">
          <div class="bar-fill" style="background:#D97706;width:${pct}%"></div>
        </div>
      </div>
      <span class="bm-count-badge">🔖 ${count}</span>
    </div>`;
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
function destroyChart(chart, canvasId) {
  if (chart) chart.destroy();
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
      responsive: true, maintainAspectRatio: false, cutout: '66%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} downloads` } },
      },
    },
  });
}


// ── Search Success Rate ────────────────────────────────────────
var _searchSuccessChart = null;

function renderSearchSuccess(data) {
  var container = document.getElementById('search-success-wrap');
  if (!container) return;

  if (!data || !data.totals) {
    container.innerHTML = '<div class="empty">No search data yet — run a search in the portal to populate this chart.</div>';
    return;
  }

  var totals = data.totals;
  var total  = parseInt(totals.total   || 0, 10);
  var succ   = parseInt(totals.success || 0, 10);
  var zero   = parseInt(totals.zero_results || 0, 10);
  var rate   = total > 0 ? Math.round(succ / total * 100) : 0;

  // Update KPI pills
  var elRate   = document.getElementById('ssr-rate');
  var elSucc   = document.getElementById('ssr-success');
  var elZero   = document.getElementById('ssr-zero');
  var elTotal  = document.getElementById('ssr-total');
  if (elRate)  elRate.textContent  = rate + '%';
  if (elSucc)  elSucc.textContent  = succ.toLocaleString();
  if (elZero)  elZero.textContent  = zero.toLocaleString();
  if (elTotal) elTotal.textContent = total.toLocaleString();

  // Colour the rate pill based on threshold
  if (elRate) {
    elRate.className = 'ssr-rate-val ' + (rate >= 70 ? 'ssr-good' : rate >= 40 ? 'ssr-mid' : 'ssr-bad');
  }

  // Build daily stacked bar chart
  var trend = Array.isArray(data.trend) ? data.trend : [];
  var labels  = trend.map(function(r) {
    var d = new Date(r.day + 'T00:00:00');
    return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
  });
  var succData = trend.map(function(r) { return parseInt(r.success_count || 0, 10); });
  var zeroData = trend.map(function(r) { return parseInt(r.zero_count    || 0, 10); });

  if (_searchSuccessChart) { _searchSuccessChart.destroy(); _searchSuccessChart = null; }
  var canvas = document.getElementById('search-success-chart');
  if (!canvas) return;
  canvas.style.width = ''; canvas.style.height = '';
  canvas.removeAttribute('width'); canvas.removeAttribute('height');

  _searchSuccessChart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        {
          label: 'Results found',
          data: succData,
          backgroundColor: 'rgba(22,163,74,.75)',
          borderRadius: 3,
          stack: 'searches',
        },
        {
          label: 'No results',
          data: zeroData,
          backgroundColor: 'rgba(220,38,38,.55)',
          borderRadius: 3,
          stack: 'searches',
        },
      ],
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: true, position: 'top', labels: { font: { size: 11, family: 'DM Mono' }, boxWidth: 12 } },
        tooltip: {
          callbacks: {
            label: function(ctx) {
              var total2 = ctx.chart.data.datasets.reduce(function(a, ds) { return a + (ds.data[ctx.dataIndex] || 0); }, 0);
              var pct = total2 > 0 ? Math.round(ctx.parsed.y / total2 * 100) : 0;
              return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y + ' (' + pct + '%)';
            },
          },
        },
      },
      scales: {
        x: { stacked: true, ticks: { font: { size: 10, family: 'DM Mono' }, maxTicksLimit: 10, color: '#9B9A95' }, grid: { display: false }, border: { display: false } },
        y: { stacked: true, ticks: { font: { size: 10, family: 'DM Mono' }, color: '#9B9A95', stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } },
      },
    },
  });

  // Failed queries list
  var failList = document.getElementById('ssr-failed-list');
  if (failList) {
    var failed = Array.isArray(data.failed_queries) ? data.failed_queries : [];
    if (!failed.length) {
      failList.innerHTML = '<div class="empty" style="font-size:12px;padding:8px 0">No zero-result searches yet 🎉</div>';
    } else {
      failList.innerHTML = failed.map(function(f) {
        return '<div class="ssr-fail-row">'
          + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0">'
          + '<circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>'
          + '<span class="ssr-fail-query">' + escHtml(f.search_query) + '</span>'
          + '<span class="ssr-fail-count">' + f.count + '×</span>'
          + '</div>';
      }).join('');
    }
  }
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
        borderColor: '#2563EB', borderWidth: 1.5, borderRadius: 5,
      }],
    },
    options: {
      indexAxis: 'y', responsive: true, maintainAspectRatio: false,
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
function _guessMime(ext) {
  const m = { pdf: 'application/pdf', docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    doc: 'application/msword', mp4: 'video/mp4', mp3: 'audio/mpeg',
    jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', xlsx: 'application/vnd.ms-excel',
    pptx: 'application/vnd.ms-powerpoint' };
  return m[(ext || '').toLowerCase()] || '';
}

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

    if (d.folders)         renderFolders(d.folders);
    if (d.grades)          renderGradeBars(d.grades);
    if (d.searches)        renderSearchTags(d.searches);
    if (d.trend)           buildTrendChart(d.trend);
    if (d.types)           buildTypeChart(d.types);
    if (d.subjects)        buildSubjectChart(d.subjects);
    renderBookmarks(d.top_bookmarked || []);
    renderSearchSuccess(d.search_success || null);

    const now = new Date();
    document.getElementById('last-updated').textContent =
      'Updated ' + now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });

    loadDownloadLog();
    loadUsers();

  } catch (err) {
    console.error('Dashboard load error:', err);
    document.getElementById('last-updated').textContent = 'Load failed — check console';
  }
}

// ── Date-range UI: show/hide custom date inputs ────────────────
function onDateRangeChange() {
  const el  = document.getElementById('date-range');
  const row = document.getElementById('custom-range-row');
  if (!el || !row) return;

  if (el.value === 'custom') {
    row.classList.add('visible');
    // Pre-fill sensible defaults: today as "to", 7 days ago as "from"
    const today = new Date();
    const ago   = new Date(today); ago.setDate(today.getDate() - 6);
    const fmt   = d => d.toISOString().slice(0, 10);
    const fi    = document.getElementById('custom-from');
    const ti    = document.getElementById('custom-to');
    if (fi && !fi.value) fi.value = fmt(ago);
    if (ti && !ti.value) ti.value = fmt(today);
    // Don't call loadAll yet — wait until both dates are filled
    if (fi && ti && fi.value && ti.value) loadAll();
  } else {
    row.classList.remove('visible');
    loadAll();
  }
}

function applyCustomRange() {
  const fi = document.getElementById('custom-from');
  const ti = document.getElementById('custom-to');
  if (!fi || !ti || !fi.value || !ti.value) return;
  if (fi.value > ti.value) {
    // Swap silently so from <= to
    [fi.value, ti.value] = [ti.value, fi.value];
  }
  loadAll();
}

// Run on page load
loadAll();

// ── Live metric refresh ───────────────────────────────────────
// Re-fetch only the summary every 30 s and animate any changed values.
// Full data reload (charts etc.) still happens on date-range change.
(function startLiveRefresh() {
  const INTERVAL = 30_000; // 30 seconds

  function animateCounter(el, newVal) {
    const oldVal = parseInt(el.textContent.replace(/,/g, ''), 10) || 0;
    if (oldVal === newVal || isNaN(newVal)) { el.textContent = newVal.toLocaleString(); return; }
    const step  = newVal > oldVal ? 1 : -1;
    const range = Math.abs(newVal - oldVal);
    const delay = Math.max(8, Math.min(40, Math.round(600 / range)));
    let cur = oldVal;
    const tick = () => {
      cur += step;
      el.textContent = cur.toLocaleString();
      if (cur !== newVal) setTimeout(tick, delay);
    };
    setTimeout(tick, delay);
  }

  async function refreshMetrics() {
    try {
      const days = getDays();
      const r    = await fetch(TRACKER_URL + dq('', days));
      if (!r.ok) return;
      const s = await r.json();
      if (!s) return;

      const ids = {
        'm-users':     s.unique_users,
        'm-sessions':  s.total_sessions,
        'm-views':     s.total_views,
        'm-downloads': s.total_downloads,
        'm-searches':  s.total_searches,
        'm-bookmarks': s.total_bookmarks,
      };
      for (const [id, val] of Object.entries(ids)) {
        const el = document.getElementById(id);
        if (el && val != null) animateCounter(el, parseInt(val, 10) || 0);
      }

      // Also refresh the last-updated timestamp subtly
      const ts = document.getElementById('last-updated');
      if (ts) {
        const now = new Date();
        ts.textContent = 'Updated ' + now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
      }
    } catch (_) {}
  }

  setInterval(refreshMetrics, INTERVAL);
})();

// ═══════════════════════════════════════════════════════════════
//  DOWNLOAD LOG
// ═══════════════════════════════════════════════════════════════
var _logData    = [];
var _logQuery   = '';
var _logPage    = 1;
var LOG_PAGE_SZ = 10;

async function loadDownloadLog() {
  var days    = getDays();
  var tbody   = document.getElementById('log-body');
  var countEl = document.getElementById('log-count');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="5" class="empty"><div class="spinner" style="display:inline-block;margin-right:6px"></div>Loading\u2026</td></tr>';

  if (DEMO) {
    // Generate realistic-looking demo log rows
    var names  = ['Maria Santos','Jose Reyes','Ana Cruz','Pedro Lim','Rosa Garcia','Carlos Mendoza'];
    var emails = ['maria.santos@deped.gov.ph','jose.reyes@deped.gov.ph','ana.cruz@deped.gov.ph',
                  'pedro.lim@deped.gov.ph','rosa.garcia@deped.gov.ph','carlos.mendoza@deped.gov.ph'];
    var files  = ['Grade6_Science_Q1_SLM.pdf','Grade4_Math_Q2_TG.pdf','Grade8_English_DLL_Q1.docx',
                  'Grade10_AP_Q3_SLM.pdf','Kinder_Filipino_SLM.pdf','Grade7_MAPEH_Q2_Video.mp4'];
    var types  = ['SLM','TG','DLL','SLM','SLM','Video'];
    var exts   = ['pdf','pdf','docx','pdf','pdf','mp4'];
    var paths  = [
      'Grade 6 › Science › Quarter 1 › SLMs',
      'Grade 4 › Mathematics › Quarter 2 › Teachers Guide',
      'Grade 8 › English › Quarter 1 › Daily Lesson Logs',
      'Grade 10 › Araling Panlipunan › Quarter 3 › SLMs',
      'Kinder › Filipino › Quarter 1 › SLMs',
      'Grade 7 › MAPEH › Quarter 2 › Videos',
    ];
    _logData = [];
    var limit = days === 1 ? 8 : 30;
    for (var i = 0; i < limit; i++) {
      var fi = i % files.length;
      var ni = i % names.length;
      var d  = new Date();
      d.setMinutes(d.getMinutes() - i * (days === 1 ? 20 : 60));
      _logData.push({
        downloaded_at: d.toISOString().replace('T',' ').slice(0,19),
        user_name:  names[ni], user_email: emails[ni],
        item_name:  files[fi], item_type:  types[fi],
        file_ext:   exts[fi],  folder_path: paths[fi],
      });
    }
  } else {
    try {
      var url = TRACKER_URL + dq('?log&limit=500', days);
      var r   = await fetch(url);
      _logData = r.ok ? await r.json() : [];
    } catch (e) {
      _logData = [];
    }
  }

  _logPage = 1;
  if (countEl) countEl.textContent = _logData.length;
  renderLogTable(_logQuery);
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

  var total   = filtered.length;
  var shown   = _logPage * LOG_PAGE_SZ;
  var visible = filtered.slice(0, shown);

  var rows = visible.map(function(r) {
    var dt = r.downloaded_at || '';
    var display = dt;
    try {
      var d = new Date(dt.replace(' ', 'T'));
      display = d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
              + ' · '
              + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    } catch(e2) {}

    var name      = r.item_name || '—';
    var shortName = name.length > 40 ? name.slice(0, 38) + '…' : name;

    var user    = r.user_name  || '—';
    var email   = r.user_email || '';
    var initial = (user !== '—' ? user : email).charAt(0).toUpperCase() || '?';
    var userHtml = '<div class="user-chip">'
      + '<span class="user-avatar-sm">' + escHtml(initial) + '</span>'
      + '<div class="user-chip-info">'
      + '<span class="user-chip-name">' + escHtml(user) + '</span>'
      + (email ? '<span class="user-chip-email">' + escHtml(email) + '</span>' : '')
      + '</div></div>';

    var ext      = r.file_ext ? r.file_ext.toUpperCase() : '';
    var extBadge = ext ? '<span class="ext-badge ext-' + r.file_ext.toLowerCase() + '">' + ext + '</span>' : '';
    var typeCombined = typeBadge(r.item_type) + extBadge;

    var path      = r.folder_path || '';
    var pathHtml;
    if (!path || path === '—') {
      pathHtml = '<span style="color:var(--text-3)">—</span>';
    } else {
      // Smart path formatter: always show root and the last 1-2 segments,
      // collapse everything in between to "…" so the cell stays compact.
      var parts = path.split(' › ');
      var ext   = r.file_ext ? r.file_ext.toUpperCase() : '';
      var displayPath;
      if (parts.length <= 3) {
        // Short enough — show everything
        displayPath = parts.join(' › ');
      } else {
        // root › … › second-last › last
        displayPath = parts[0] + ' › \u2026 › ' + parts.slice(-2).join(' › ');
      }
      var extPart = ext ? ' · ' + ext : '';
      pathHtml = '<span class="folder-path-sm" title="' + escHtml(path + extPart) + '">'
        + '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2.5" stroke-linecap="round" style="margin-right:3px;vertical-align:-1px;flex-shrink:0"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>'
        + escHtml(displayPath) + '<span style="color:var(--text-3)">' + escHtml(extPart) + '</span></span>';
    }

    return '<tr>'
      + '<td style="white-space:nowrap;font-size:11px;font-family:\'DM Mono\',monospace;color:var(--text-3)">' + escHtml(display) + '</td>'
      + '<td>' + userHtml + '</td>'
      + '<td><span class="file-name" title="' + escHtml(name) + '">' + escHtml(shortName) + '</span></td>'
      + '<td>' + typeCombined + '</td>'
      + '<td>' + pathHtml + '</td>'
      + '</tr>';
  }).join('');

  // Pagination footer row
  var remaining = total - shown;
  var footerHtml = '';
  if (total > LOG_PAGE_SZ) {
    footerHtml = '<tr><td colspan="5" style="text-align:center;padding:10px 12px;border-top:1px solid var(--border)">';
    if (remaining > 0) {
      footerHtml += '<button onclick="_logPage++;renderLogTable(_logQuery)" style="'
        + 'padding:5px 14px;border-radius:var(--radius);border:1px solid var(--border-md);'
        + 'background:var(--surface);color:var(--blue);font-size:12px;font-weight:600;cursor:pointer;margin-right:8px">'
        + 'Show ' + Math.min(remaining, LOG_PAGE_SZ) + ' more <span style="color:var(--text-3);font-weight:400">(' + remaining + ' remaining)</span></button>';
    }
    if (_logPage > 1) {
      footerHtml += '<button onclick="_logPage=1;renderLogTable(_logQuery)" style="'
        + 'padding:5px 14px;border-radius:var(--radius);border:1px solid var(--border-md);'
        + 'background:var(--surface);color:var(--text-2);font-size:12px;cursor:pointer">'
        + 'Collapse to 10</button>';
    }
    footerHtml += '</td></tr>';
  }

  tbody.innerHTML = rows + footerHtml;
}

// Wire log search
(function() {
  var si = document.getElementById('log-search');
  if (si) si.addEventListener('input', function(e) { _logQuery = e.target.value; _logPage = 1; renderLogTable(_logQuery); });
})();

loadDownloadLog();

// ═══════════════════════════════════════════════════════════════
//  USERS TABLE
// ═══════════════════════════════════════════════════════════════
var _usersData    = [];
var _usersQuery   = '';
var _usersPage    = 1;
var USERS_PAGE_SZ = 10;

async function loadUsers() {
  var days    = getDays();
  var tbody   = document.getElementById('users-body');
  var countEl = document.getElementById('users-count');
  if (!tbody) return;

  // colspan is now 8 (added Bookmarks column)
  tbody.innerHTML = '<tr><td colspan="8" class="empty"><div class="spinner" style="display:inline-block;margin-right:6px"></div>Loading\u2026</td></tr>';

  if (DEMO) {
    _usersData = [
      { user_name: 'Maria Santos',   user_email: 'maria.santos@deped.gov.ph',   sessions: 12, file_views: 48, downloads: 21, bookmarks: 9,  searches: 9,  last_seen: '2025-05-12 09:14:00' },
      { user_name: 'Jose Reyes',     user_email: 'jose.reyes@deped.gov.ph',     sessions: 8,  file_views: 31, downloads: 15, bookmarks: 6,  searches: 6,  last_seen: '2025-05-11 14:32:00' },
      { user_name: 'Ana Cruz',       user_email: 'ana.cruz@deped.gov.ph',       sessions: 6,  file_views: 22, downloads: 10, bookmarks: 4,  searches: 4,  last_seen: '2025-05-10 11:05:00' },
      { user_name: 'Pedro Lim',      user_email: 'pedro.lim@deped.gov.ph',      sessions: 5,  file_views: 19, downloads: 8,  bookmarks: 3,  searches: 3,  last_seen: '2025-05-09 16:44:00' },
      { user_name: 'Rosa Garcia',    user_email: 'rosa.garcia@deped.gov.ph',    sessions: 4,  file_views: 14, downloads: 6,  bookmarks: 2,  searches: 2,  last_seen: '2025-05-08 08:21:00' },
      { user_name: 'Carlos Mendoza', user_email: 'carlos.mendoza@deped.gov.ph', sessions: 3,  file_views: 9,  downloads: 4,  bookmarks: 1,  searches: 1,  last_seen: '2025-05-07 13:57:00' },
      { user_name: 'Linda Torres',   user_email: 'linda.torres@deped.gov.ph',   sessions: 2,  file_views: 5,  downloads: 2,  bookmarks: 0,  searches: 0,  last_seen: '2025-05-06 10:30:00' },
    ];
  } else {
    try {
      var url = TRACKER_URL + dq('?users&limit=500', days);
      var r   = await fetch(url);
      _usersData = r.ok ? await r.json() : [];
    } catch (e) {
      _usersData = [];
    }
  }

  _usersPage = 1;
  renderUsersTable(_usersQuery);
  if (countEl) countEl.textContent = _usersData.length;
}

function renderUsersTable(query) {
  var tbody = document.getElementById('users-body');
  if (!tbody) return;

  var q = (query || '').toLowerCase().trim();
  var filtered = q
    ? _usersData.filter(function(r) {
        return (r.user_name  || '').toLowerCase().includes(q) ||
               (r.user_email || '').toLowerCase().includes(q);
      })
    : _usersData;

  if (!filtered.length) {
    tbody.innerHTML = '<tr><td colspan="8" class="empty">'
      + (q ? 'No matching users' : 'No users recorded yet — users appear here once they sign in to the portal')
      + '</td></tr>';
    return;
  }

  var total   = filtered.length;
  var shown   = _usersPage * USERS_PAGE_SZ;
  var visible = filtered.slice(0, shown);

  var rows = visible.map(function(r, i) {
    var user    = r.user_name  || '—';
    var email   = r.user_email || '';
    var initial = (user !== '—' ? user : email).charAt(0).toUpperCase() || '?';

    var userHtml = '<div class="user-chip">'
      + '<span class="user-avatar-sm">' + escHtml(initial) + '</span>'
      + '<div class="user-chip-info">'
      + '<button type="button" class="user-chip-name user-activity-link"'
      + ' data-user-name="' + escHtml(user) + '"'
      + ' data-user-email="' + escHtml(email) + '"'
      + ' title="View activity report">' + escHtml(user) + '</button>'
      + (email ? '<span class="user-chip-email">' + escHtml(email) + '</span>' : '')
      + '</div></div>';

    var ls = r.last_seen || '';
    var lsDisplay = ls;
    try {
      var d = new Date(ls.replace(' ', 'T'));
      lsDisplay = d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
                + ' · '
                + d.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
    } catch(e2) {}

    var sessionClass  = (r.sessions  || 0) >= 3  ? 'count-pill pill-vw' : '';
    var bookmarkCount = Math.max(0, r.bookmarks  || 0);
    var bmClass       = bookmarkCount > 0 ? 'count-pill pill-bm' : '';

    return '<tr>'
      + '<td class="rank">' + (i + 1) + '</td>'
      + '<td>' + userHtml + '</td>'
      + '<td style="text-align:right"><span class="' + sessionClass + '">' + (r.sessions || 0) + '</span></td>'
      + '<td style="text-align:right">' + (r.file_views || 0) + '</td>'
      + '<td style="text-align:right"><span class="count-pill pill-dl">' + (r.downloads || 0) + '</span></td>'
      + '<td style="text-align:right"><span class="' + bmClass + '">' + bookmarkCount + '</span></td>'
      + '<td style="text-align:right">' + (r.searches || 0) + '</td>'
      + '<td style="white-space:nowrap;font-size:11px;font-family:\'DM Mono\',monospace;color:var(--text-3)">' + escHtml(lsDisplay) + '</td>'
      + '</tr>';
  }).join('');

  // Pagination footer row
  var remaining = total - shown;
  var footerHtml = '';
  if (total > USERS_PAGE_SZ) {
    footerHtml = '<tr><td colspan="8" style="text-align:center;padding:10px 12px;border-top:1px solid var(--border)">';
    if (remaining > 0) {
      footerHtml += '<button onclick="_usersPage++;renderUsersTable(_usersQuery)" style="'
        + 'padding:5px 14px;border-radius:var(--radius);border:1px solid var(--border-md);'
        + 'background:var(--surface);color:var(--blue);font-size:12px;font-weight:600;cursor:pointer;margin-right:8px">'
        + 'Show ' + Math.min(remaining, USERS_PAGE_SZ) + ' more <span style="color:var(--text-3);font-weight:400">(' + remaining + ' remaining)</span></button>';
    }
    if (_usersPage > 1) {
      footerHtml += '<button onclick="_usersPage=1;renderUsersTable(_usersQuery)" style="'
        + 'padding:5px 14px;border-radius:var(--radius);border:1px solid var(--border-md);'
        + 'background:var(--surface);color:var(--text-2);font-size:12px;cursor:pointer">'
        + 'Collapse to 10</button>';
    }
    footerHtml += '</td></tr>';
  }

  tbody.innerHTML = rows + footerHtml;
}

// Wire users search
(function() {
  var si = document.getElementById('users-search');
  if (si) si.addEventListener('input', function(e) { _usersQuery = e.target.value; _usersPage = 1; renderUsersTable(_usersQuery); });
})();

// Tap user name → per-user activity report (user-activity-report.js)
(function() {
  var tbody = document.getElementById('users-body');
  if (!tbody) return;
  tbody.addEventListener('click', function(e) {
    var btn = e.target.closest('.user-activity-link');
    if (!btn || !window.UserActivityReport) return;
    e.preventDefault();
    window.UserActivityReport.open({
      user_name:  btn.getAttribute('data-user-name')  || '',
      user_email: btn.getAttribute('data-user-email') || '',
    });
  });
})();

loadUsers();