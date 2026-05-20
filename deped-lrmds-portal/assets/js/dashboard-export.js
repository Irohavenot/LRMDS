/* ============================================================
   LRMDS Admin Dashboard – DepEd Carcar City
   dashboard-export.js  (v1.1)

   Changes from v1.0:
     • Removed "Downloads by Resource Type" chart
     • Removed "Downloads by Subject" chart
     • Removed Activity Trend chart — shows data table only
     • Added Most Bookmarked Files table beside trend table
     • Added Most Active Users section
     • Folder paths truncated to max 4 segments + "…"
     • Palette updated to DepEd Carcar green brand
     • Real DepEd Carcar City logo embedded (base64, 120px)

   Depends on: admindashboard.js globals
     _topData, _trendChart, _logData, _usersData
   ============================================================ */

// ─── Helpers ──────────────────────────────────────────────────
function _esc(v) {
  if (v == null) return '';
  const s = String(v);
  return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
}
function _row(arr) { return arr.map(_esc).join(',') + '\r\n'; }

function _dateLabel() {
  return new Date().toLocaleDateString('en-PH', {
    year: 'numeric', month: 'long', day: 'numeric'
  });
}
function _rangeLabel() {
  const el  = document.getElementById('date-range');
  if (!el) return 'Last 30 days';
  if (el.value === 'custom') {
    const from = document.getElementById('custom-from');
    const to   = document.getElementById('custom-to');
    if (from && to && from.value && to.value) {
      return `${from.value} to ${to.value}`;
    }
    return 'Custom range';
  }
  const map = { '1':'Today','7':'Last 7 days','30':'Last 30 days','90':'Last 90 days','0':'All time' };
  return map[el.value] || 'Custom';
}
function _summaryMetric(id) {
  const el = document.getElementById(id);
  return el ? el.textContent.replace(/,/g, '') : '0';
}
function _downloadFile(filename, content, mime) {
  const blob = new Blob([content], { type: mime });
  const url  = URL.createObjectURL(blob);
  const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
}
function _slugDate() { return new Date().toISOString().slice(0, 10); }

/** Truncate a folder path (separator " › ") to at most maxSegs parts. */
function _truncFolder(raw, maxSegs) {
  if (!raw || raw === '—') return raw || '—';
  const SEP   = raw.includes(' › ') ? ' › ' : '/';
  const parts = raw.split(SEP);
  if (parts.length <= maxSegs) return raw;
  return parts.slice(0, maxSegs).join(SEP) + SEP + '…';
}

function _escHtmlR(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── DOM data accessors ───────────────────────────────────────
function _getFolderData() {
  const rows  = [];
  const tbody = document.getElementById('folder-body');
  if (!tbody) return rows;
  tbody.querySelectorAll('tr').forEach(tr => {
    const cells = tr.querySelectorAll('td');
    if (cells.length >= 3) {
      rows.push({
        folder_path: cells[0] ? cells[0].innerText.trim() : '—',
        views:       parseInt((cells[1] ? cells[1].innerText : '0').replace(/\D/g,''), 10) || 0,
        downloads:   parseInt((cells[2] ? cells[2].innerText : '0').replace(/\D/g,''), 10) || 0,
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
    // Clone and remove the count element before reading text — avoids
    // accidentally stripping count digits that appear in the query itself.
    const clone = el.cloneNode(true);
    const cloneCount = clone.querySelector('.tag-count');
    if (cloneCount) cloneCount.remove();
    const text = clone.textContent.trim();
    return { search_query: text, count };
  });
}

function _getBookmarkData() {
  const list = document.getElementById('bookmarks-list');
  if (!list) return [];
  const items = [];
  list.querySelectorAll('.bm-item').forEach(el => {
    const nameEl  = el.querySelector('.bm-item-name');
    const pathEl  = el.querySelector('.bm-item-path');
    const countEl = el.querySelector('.bm-count-badge');
    if (!nameEl) return;
    const nameClone = nameEl.cloneNode(true);
    nameClone.querySelectorAll('span').forEach(s => s.remove());
    items.push({
      item_name:      nameClone.textContent.trim(),
      folder_path:    pathEl  ? pathEl.innerText.trim() : '',
      bookmark_count: countEl ? parseInt(countEl.textContent.replace(/\D/g,''), 10) || 0 : 0,
    });
  });
  return items;
}

// ═══════════════════════════════════════════════════════════════
//  1.  CSV EXPORT
// ═══════════════════════════════════════════════════════════════
function exportCSV() {
  const range = _rangeLabel();
  const date  = _dateLabel();
  const slug  = _slugDate();
  let csv = '';

  csv += _row(['LRMDS Analytics Report – DepEd Carcar City']);
  csv += _row(['Generated', date]);
  csv += _row(['Period', range]);
  csv += _row([]);

  // Summary
  csv += _row(['=== SUMMARY METRICS ===']);
  csv += _row(['Metric','Value']);
  csv += _row(['Unique Users',   _summaryMetric('m-users')]);
  csv += _row(['Sessions',       _summaryMetric('m-sessions')]);
  csv += _row(['File Views',     _summaryMetric('m-views')]);
  csv += _row(['Downloads',      _summaryMetric('m-downloads')]);
  csv += _row(['Searches',       _summaryMetric('m-searches')]);
  csv += _row(['Bookmarks',      _summaryMetric('m-bookmarks')]);
  csv += _row([]);

  // Top 5 Downloads
  const topDl = (_topData && _topData.downloads) ? _topData.downloads.slice(0,5) : [];
  csv += _row(['=== TOP 5 FILES BY DOWNLOADS ===']);
  csv += _row(['Rank','File Name','Type','Extension','Folder Path','Downloads','Views']);
  topDl.forEach((r,i) => csv += _row([i+1,r.item_name||'—',r.item_type||'—',r.file_ext||'—',r.folder_path||'—',r.downloads??0,r.views??0]));
  csv += _row([]);

  // Top 5 Views
  const topVw = (_topData && _topData.views) ? _topData.views.slice(0,5) : [];
  csv += _row(['=== TOP 5 FILES BY VIEWS ===']);
  csv += _row(['Rank','File Name','Type','Extension','Folder Path','Views','Downloads']);
  topVw.forEach((r,i) => csv += _row([i+1,r.item_name||'—',r.item_type||'—',r.file_ext||'—',r.folder_path||'—',r.views??0,r.downloads??0]));
  csv += _row([]);

  // Top Folders (full path in CSV)
  const folderData = _getFolderData().slice(0,5);
  csv += _row(['=== TOP 5 FOLDERS ===']);
  csv += _row(['Rank','Folder Path','Views','Downloads']);
  folderData.forEach((f,i) => csv += _row([i+1,f.folder_path||'—',f.views??0,f.downloads??0]));
  csv += _row([]);

  // Most Bookmarked
  const bmData = _getBookmarkData().slice(0,5);
  csv += _row(['=== MOST BOOKMARKED FILES ===']);
  csv += _row(['Rank','File Name','Folder Path','Bookmarks']);
  bmData.forEach((r,i) => csv += _row([i+1,r.item_name||'—',r.folder_path||'—',r.bookmark_count??0]));
  csv += _row([]);

  // Top Searches
  const searchData = _getSearchData();
  csv += _row(['=== TOP SEARCH QUERIES ===']);
  csv += _row(['Rank','Search Query','Count']);
  searchData.forEach((s,i) => csv += _row([i+1,s.search_query||'—',s.count??0]));
  csv += _row([]);

  // Trend
  if (_trendChart && _trendChart.data) {
    const labels = _trendChart.data.labels || [];
    const dlData = (_trendChart.data.datasets[0]||{}).data || [];
    const vwData = (_trendChart.data.datasets[1]||{}).data || [];
    csv += _row(['=== ACTIVITY TREND ===']);
    csv += _row(['Date','Downloads','Views']);
    labels.forEach((lbl,i) => csv += _row([lbl,dlData[i]??0,vwData[i]??0]));
    csv += _row([]);
  }

  // Most Active Users
  if (Array.isArray(_usersData) && _usersData.length) {
    const sorted = [..._usersData].sort((a,b) => (b.downloads||0)-(a.downloads||0)).slice(0,10);
    csv += _row(['=== MOST ACTIVE USERS ===']);
    csv += _row(['Rank','Name','Email','Sessions','File Views','Downloads','Bookmarks','Searches','Last Seen']);
    sorted.forEach((u,i) => csv += _row([i+1,u.user_name||'—',u.user_email||'—',u.sessions??0,u.file_views??0,u.downloads??0,u.bookmarks??0,u.searches??0,u.last_seen||'—']));
    csv += _row([]);
  }

  // Download Log
  if (Array.isArray(_logData) && _logData.length) {
    csv += _row(['=== DOWNLOAD LOG ===']);
    csv += _row(['Downloaded At','User Name','User Email','File Name','Type','Extension','Folder Path']);
    _logData.forEach(r => csv += _row([r.downloaded_at||'—',r.user_name||'—',r.user_email||'—',r.item_name||'—',r.item_type||'—',r.file_ext||'—',r.folder_path||'—']));
  }

  // ── Show preview modal instead of downloading directly ───────
  _showCsvPreview(csv, `LRMDS_Report_${slug}.csv`);
}

// ═══════════════════════════════════════════════════════════════
//  1b.  CSV PREVIEW MODAL
// ═══════════════════════════════════════════════════════════════
function _showCsvPreview(csv, filename) {
  // Remove any existing modal
  const old = document.getElementById('csv-preview-modal');
  if (old) old.remove();

  // Parse CSV into preview table (first 60 data rows per section)
  const lines  = csv.split(/\r?\n/).filter(l => l.trim());

  // Build a human-readable HTML preview of the sections
  let previewHtml = '';
  let sectionRows = [];
  let sectionHead = '';
  let inSection   = false;

  function flushSection() {
    if (!sectionHead && !sectionRows.length) return;
    const colCount = sectionRows.length ? sectionRows[0].length : 1;
    const headerCells = sectionRows.length
      ? sectionRows[0].map(c => `<th>${_escHtmlM(c)}</th>`).join('')
      : '';
    const bodyRows = sectionRows.slice(1, 51).map(row =>
      '<tr>' + row.map(c => `<td>${_escHtmlM(c)}</td>`).join('') + '</tr>'
    ).join('');
    const more = sectionRows.length > 51
      ? `<tr><td colspan="${colCount}" style="text-align:center;color:#9B9A95;font-style:italic;padding:6px">
           … ${sectionRows.length - 51} more rows …</td></tr>`
      : '';
    previewHtml += `
      <div class="csv-section">
        <div class="csv-section-hd">${_escHtmlM(sectionHead)}</div>
        <div class="csv-table-wrap">
          <table><thead><tr>${headerCells}</tr></thead>
          <tbody>${bodyRows}${more}</tbody></table>
        </div>
      </div>`;
    sectionRows = [];
    sectionHead = '';
    inSection = false;
  }

  // Simple CSV line parser (handles quoted fields)
  function parseLine(line) {
    const out = []; let cur = ''; let inQ = false;
    for (let i = 0; i < line.length; i++) {
      const ch = line[i];
      if (inQ) {
        if (ch === '"' && line[i+1] === '"') { cur += '"'; i++; }
        else if (ch === '"') { inQ = false; }
        else { cur += ch; }
      } else {
        if (ch === '"') { inQ = true; }
        else if (ch === ',') { out.push(cur); cur = ''; }
        else { cur += ch; }
      }
    }
    out.push(cur);
    return out;
  }

  let firstLine = true;
  for (const line of lines) {
    const cells = parseLine(line);
    if (cells.length === 1 && cells[0].startsWith('===')) {
      flushSection();
      sectionHead = cells[0].replace(/===/g, '').trim();
      inSection = true;
      firstLine = false;
      continue;
    }
    if (cells.length === 1 && !cells[0].trim()) continue;
    // Skip the decorative title banner (first single-cell non-=== line)
    if (firstLine && cells.length === 1 && !inSection) {
      firstLine = false;
      continue;
    }
    firstLine = false;
    // Meta rows at top (Generated, Period) — render as a tidy info block
    if (!inSection && cells.length <= 2) {
      if (!sectionHead) {
        sectionHead = 'Report Info';
        // Inject a fake header row so columns display nicely
        sectionRows.push(['Field', 'Value']);
      }
      sectionRows.push(cells);
      continue;
    }
    if (inSection || sectionRows.length) {
      sectionRows.push(cells);
    }
  }
  flushSection();

  function _escHtmlM(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  const modal = document.createElement('div');
  modal.id = 'csv-preview-modal';
  modal.innerHTML = `
    <div class="cpm-backdrop"></div>
    <div class="cpm-dialog">
      <div class="cpm-header">
        <div class="cpm-header-left">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          <span>CSV Preview — <em>${_escHtmlM(filename)}</em></span>
        </div>
        <button class="cpm-close" onclick="document.getElementById('csv-preview-modal').remove()">✕</button>
      </div>
      <div class="cpm-hint">Review the data below before downloading. Scroll to see all sections.</div>
      <div class="cpm-body">${previewHtml}</div>
      <div class="cpm-footer">
        <button class="cpm-btn cpm-btn-cancel" onclick="document.getElementById('csv-preview-modal').remove()">Cancel</button>
        <button class="cpm-btn cpm-btn-save" onclick="_downloadFile('${_escHtmlM(filename)}','\uFEFF'+window._csvPreviewData,'text/csv;charset=utf-8');document.getElementById('csv-preview-modal').remove()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Download CSV
        </button>
      </div>
    </div>`;

  // Store CSV for the download button to access
  window._csvPreviewData = csv;

  // Inject styles if not already present
  if (!document.getElementById('cpm-styles')) {
    const s = document.createElement('style');
    s.id = 'cpm-styles';
    s.textContent = `
      #csv-preview-modal { position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center; }
      .cpm-backdrop { position:absolute;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px); }
      .cpm-dialog {
        position:relative;z-index:1;background:#fff;border-radius:14px;
        width:min(92vw,960px);max-height:86vh;display:flex;flex-direction:column;
        box-shadow:0 20px 60px rgba(0,0,0,.25),0 4px 16px rgba(0,0,0,.12);
        overflow:hidden;
      }
      .cpm-header {
        display:flex;align-items:center;justify-content:space-between;
        padding:14px 20px;border-bottom:1px solid rgba(0,0,0,.08);
        background:#1a5c2a;color:#fff;flex-shrink:0;
        font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;gap:12px;
      }
      .cpm-header-left { display:flex;align-items:center;gap:8px; }
      .cpm-header em { font-style:normal;opacity:.75;font-weight:400; }
      .cpm-close {
        background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
        color:#fff;border-radius:6px;width:28px;height:28px;cursor:pointer;
        font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
      }
      .cpm-close:hover { background:rgba(255,255,255,.22); }
      .cpm-hint {
        padding:8px 20px;font-size:11.5px;color:#607060;background:#f0f7f2;
        border-bottom:1px solid #cde0d3;flex-shrink:0;font-family:'DM Sans',sans-serif;
      }
      .cpm-body { overflow-y:auto;padding:16px 20px;flex:1;display:flex;flex-direction:column;gap:18px; }
      .csv-section {}
      .csv-section-hd {
        font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;
        color:#1a5c2a;margin-bottom:6px;font-family:'DM Sans',sans-serif;
        padding-bottom:4px;border-bottom:2px solid #2d7a3e;
      }
      .csv-table-wrap { overflow-x:auto;border-radius:8px;border:1px solid #cde0d3; }
      .csv-table-wrap table { border-collapse:collapse;width:100%;font-size:11.5px;font-family:'DM Sans',sans-serif; }
      .csv-table-wrap thead th {
        background:#f0f7f2;padding:6px 10px;text-align:left;font-size:10px;
        color:#607060;font-weight:600;letter-spacing:.04em;
        border-bottom:1.5px solid #b6ddc2;white-space:nowrap;
      }
      .csv-table-wrap tbody td { padding:5px 10px;border-bottom:1px solid #e8f3ec;color:#2e4433;vertical-align:middle; }
      .csv-table-wrap tbody tr:last-child td { border-bottom:none; }
      .csv-table-wrap tbody tr:nth-child(even) { background:#f5fbf6; }
      .cpm-footer {
        display:flex;align-items:center;justify-content:flex-end;gap:10px;
        padding:12px 20px;border-top:1px solid rgba(0,0,0,.08);
        background:#fafaf8;flex-shrink:0;
      }
      .cpm-btn {
        display:flex;align-items:center;gap:6px;
        padding:8px 18px;border-radius:8px;border:none;
        font-family:'DM Sans',sans-serif;font-size:13px;font-weight:600;cursor:pointer;
        transition:opacity .15s;
      }
      .cpm-btn:hover { opacity:.85; }
      .cpm-btn-cancel { background:#f0f0ec;color:#6B6A65; }
      .cpm-btn-save { background:#1a5c2a;color:#fff; }
    `;
    document.head.appendChild(s);
  }

  document.body.appendChild(modal);
  // Close on backdrop click
  modal.querySelector('.cpm-backdrop').addEventListener('click', () => modal.remove());
}



// DepEd Carcar City logo — 120×120 px JPEG, base64
const _LOGO_B64 = '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';

function exportReport() {
  const range      = _rangeLabel();
  const date       = _dateLabel();

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
  const bmData     = _getBookmarkData().slice(0, 8);

  // ── Trend table rows (last 14 points) ────────────────────────
  let trendRows = '';
  if (_trendChart && _trendChart.data) {
    const labels = _trendChart.data.labels || [];
    const dlData = (_trendChart.data.datasets[0] || {}).data || [];
    const vwData = (_trendChart.data.datasets[1] || {}).data || [];
    const start  = Math.max(0, labels.length - 14);
    for (let i = start; i < labels.length; i++) {
      trendRows += `<tr>
        <td>${_escHtmlR(labels[i] || '—')}</td>
        <td class="num">${(dlData[i] ?? 0).toLocaleString()}</td>
        <td class="num">${(vwData[i] ?? 0).toLocaleString()}</td>
      </tr>`;
    }
  }

  // ── Bookmarks rows ────────────────────────────────────────────
  const MEDALS = ['🥇','🥈','🥉'];
  let bmRows = bmData.length
    ? bmData.map((r, i) => {
        const path = _truncFolder(r.folder_path || '', 4);
        return `<tr>
          <td class="rank">${MEDALS[i] || i + 1}</td>
          <td>
            <div class="fname">${_escHtmlR(r.item_name || '—')}</div>
            ${path ? `<div class="fpath">${_escHtmlR(path)}</div>` : ''}
          </td>
          <td class="num bm-num">🔖 ${(r.bookmark_count ?? 0).toLocaleString()}</td>
        </tr>`;
      }).join('')
    : '<tr><td colspan="3" class="empty-cell">No bookmarks yet</td></tr>';

  // ── Top Files rows ────────────────────────────────────────────
  function fileRows(arr, metric) {
    if (!arr.length) return '<tr><td colspan="4" class="empty-cell">No data available</td></tr>';
    return arr.map((r, i) => {
      const path = _truncFolder(r.folder_path || '', 4);
      return `<tr>
        <td class="rank">${i + 1}</td>
        <td>
          <div class="fname">${_escHtmlR(r.item_name || '—')}</div>
          ${path ? `<div class="fpath">${_escHtmlR(path)}</div>` : ''}
        </td>
        <td><span class="badge">${_escHtmlR(r.item_type || '—')}</span>
            <span class="ext"> ${(r.file_ext || '').toUpperCase()}</span></td>
        <td class="num strong">${(r[metric] ?? 0).toLocaleString()}</td>
      </tr>`;
    }).join('');
  }

  // ── Top Folders rows ──────────────────────────────────────────
  function folderRows(arr) {
    if (!arr.length) return '<tr><td colspan="4" class="empty-cell">No data available</td></tr>';
    return arr.map((f, i) => {
      const raw  = f.folder_path || '—';
      const disp = _truncFolder(raw, 4);
      return `<tr>
        <td class="rank">${i + 1}</td>
        <td class="fpath-main" title="${_escHtmlR(raw)}">${_escHtmlR(disp)}</td>
        <td class="num">${(f.views ?? 0).toLocaleString()}</td>
        <td class="num strong">${(f.downloads ?? 0).toLocaleString()}</td>
      </tr>`;
    }).join('');
  }

  // ── Most Active Users rows ────────────────────────────────────
  let userRows = '';
  if (Array.isArray(_usersData) && _usersData.length) {
    const sorted = [..._usersData]
      .sort((a, b) => (b.downloads || 0) - (a.downloads || 0))
      .slice(0, 8);
    userRows = sorted.map((u, i) => {
      const name  = u.user_name  || '—';
      const email = u.user_email || '';
      const init  = (name !== '—' ? name : email).charAt(0).toUpperCase() || '?';
      return `<tr>
        <td class="rank">${i + 1}</td>
        <td>
          <div style="display:flex;align-items:center;gap:8px">
            <div class="uavatar">${_escHtmlR(init)}</div>
            <div>
              <div class="fname">${_escHtmlR(name)}</div>
              ${email ? `<div class="fpath">${_escHtmlR(email)}</div>` : ''}
            </div>
          </div>
        </td>
        <td class="num">${(u.sessions    ?? 0).toLocaleString()}</td>
        <td class="num">${(u.file_views  ?? 0).toLocaleString()}</td>
        <td class="num strong">${(u.downloads  ?? 0).toLocaleString()}</td>
        <td class="num">${(u.bookmarks   ?? 0).toLocaleString()}</td>
        <td class="num">${(u.searches    ?? 0).toLocaleString()}</td>
      </tr>`;
    }).join('');
  } else {
    userRows = '<tr><td colspan="7" class="empty-cell">No user data recorded yet</td></tr>';
  }

  // ── Search cloud ──────────────────────────────────────────────
  const maxSrch = searchData.length ? Math.max(...searchData.map(s => s.count)) : 1;
  const searchCloud = searchData.map(s => {
    const size = 13 + Math.round((s.count / maxSrch) * 10);
    return `<span class="stag" style="font-size:${size}px">${_escHtmlR(s.search_query)} <em>${s.count}</em></span>`;
  }).join('') || '<span class="empty-srch">No search data recorded yet</span>';

  // ══════════════════════════════════════════════════════════════
  //  HTML REPORT
  // ══════════════════════════════════════════════════════════════
  const html = `<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>LRMDS Analytics Report – DepEd Carcar City – ${date}</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@300;400;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>
/* ── Design tokens — DepEd Carcar green palette ── */
:root {
  --green-dk:  #1a5c2a;   /* deep forest — primary text, borders */
  --green-md:  #2d7a3e;   /* mid green — section accents         */
  --green-lt:  #3d9950;   /* bright leaf — hover, badges         */
  --green-bg:  #f0f7f2;   /* near-white tint — metric cards      */
  --green-rim: #b6ddc2;   /* muted rim — card borders            */
  --gold:      #c9960f;   /* seal gold — bookmark accents        */
  --text-1:    #0f1a12;
  --text-2:    #2e4433;
  --text-3:    #607060;
  --border:    #cde0d3;
  --row-alt:   #f5fbf6;
  --white:     #ffffff;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: 13px; }
body {
  font-family: 'Source Serif 4', Georgia, serif;
  color: var(--text-1);
  background: var(--white);
  line-height: 1.6;
}

@page { size: A4 portrait; margin: 15mm 13mm 18mm 13mm; }
@media print {
  .no-print              { display: none !important; }
  body                   { font-size: 11px; }
  /* Repeat table headers on each printed page */
  thead                  { display: table-header-group; }
  tfoot                  { display: table-footer-group; }
  /* Prevent rows from splitting across pages */
  tbody tr               { page-break-inside: avoid; break-inside: avoid; }
  /* Keep section headings with the content below them */
  .section-hd            { page-break-after: avoid; break-after: avoid; }
  /* Keep cards together where possible */
  .card, .mc-r           { page-break-inside: avoid; break-inside: avoid; }
  /* Ensure the footer always prints fully — never clip it */
  .report-footer         { page-break-inside: avoid; break-inside: avoid; }
}

/* ── Wrapper ── */
.page { max-width: 880px; margin: 0 auto; padding: 28px 40px 80px; }

/* ── Sticky print bar ── */
.print-bar {
  position: sticky; top: 0; z-index: 99;
  background: var(--green-dk); color: var(--white);
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 20px; gap: 12px; flex-wrap: wrap;
  font-family: 'DM Mono', monospace; font-size: 12px;
  border-bottom: 2px solid var(--green-lt);
}
.print-bar > span { opacity: .7; flex-shrink: 0; }
.print-bar-controls { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.print-setting {
  display: flex; align-items: center; gap: 5px;
  font-size: 11px;
}
.print-setting label { opacity: .65; white-space: nowrap; }
.print-setting select {
  background: rgba(255,255,255,.13); color: var(--white);
  border: 1px solid rgba(255,255,255,.28); border-radius: 5px;
  padding: 4px 8px; font-size: 11px; font-family: 'DM Mono', monospace;
  cursor: pointer; outline: none;
}
.print-setting select option { background: #1a5c2a; color: #fff; }
.print-bar-btns { display: flex; gap: 7px; }
.pbtn {
  padding: 7px 16px; border-radius: 6px; border: none;
  font-family: 'DM Mono', monospace; font-size: 12px;
  font-weight: 500; cursor: pointer; transition: opacity .15s;
  white-space: nowrap;
}
.pbtn:hover { opacity: .82; }
.pbtn-primary   { background: var(--white); color: var(--green-dk); }
.pbtn-outline   { background: transparent; color: var(--white); border: 1.5px solid rgba(255,255,255,.5); }
.pbtn-secondary { background: rgba(255,255,255,.12); color: var(--white); border: 1px solid rgba(255,255,255,.3); }

/* ── Cover header ── */
.cover {
  display: flex; align-items: center; justify-content: space-between;
  gap: 20px; padding-bottom: 20px; margin-bottom: 24px;
  border-bottom: 3px solid var(--green-dk);
}
.cover-logo { display: flex; align-items: center; gap: 14px; }
.cover-logo img {
  width: 72px; height: 72px; border-radius: 50%;
  object-fit: cover; flex-shrink: 0;
  border: 2.5px solid var(--green-rim);
  box-shadow: 0 2px 8px rgba(0,0,0,.12);
}
.cover-title h1 {
  font-size: 18px; font-weight: 700; color: var(--green-dk);
  line-height: 1.2; letter-spacing: -.01em;
}
.cover-title p {
  font-size: 11px; color: var(--text-3);
  font-family: 'DM Mono', monospace;
  margin-top: 3px; text-transform: uppercase; letter-spacing: .05em;
}
.cover-meta {
  text-align: right; flex-shrink: 0;
  font-family: 'DM Mono', monospace; font-size: 11px;
  color: var(--text-3); line-height: 1.9;
}
.cover-meta strong { font-size: 13px; color: var(--text-1); display: block; margin-bottom: 1px; }

/* ── Section header ── */
.section-hd {
  display: flex; align-items: center; gap: 10px;
  margin: 28px 0 12px;
}
.section-hd::before {
  content: ''; display: block;
  width: 4px; height: 20px; flex-shrink: 0;
  background: var(--green-md); border-radius: 2px;
}
.section-hd h2 {
  font-size: 12.5px; font-weight: 700; color: var(--green-dk);
  text-transform: uppercase; letter-spacing: .07em;
}

/* ── Summary metric cards ── */
.metrics-grid-r {
  display: grid; grid-template-columns: repeat(3, 1fr); gap: 11px;
}
.mc-r {
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: 13px 16px; background: var(--green-bg);
}
.mc-r-icon { font-size: 18px; margin-bottom: 6px; }
.mc-r-label {
  font-size: 9px; font-family: 'DM Mono', monospace;
  color: var(--text-3); text-transform: uppercase;
  letter-spacing: .07em; margin-bottom: 4px;
}
.mc-r-value {
  font-size: 26px; font-weight: 700; color: var(--green-dk);
  font-family: 'DM Mono', monospace; line-height: 1;
}
.mc-r-sub { font-size: 9.5px; color: var(--text-3); margin-top: 4px; }

/* ── Two-column layouts ── */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* ── Card wrapper ── */
.card {
  border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden;
}
.card-title {
  font-size: 10px; font-family: 'DM Mono', monospace;
  color: var(--text-3); text-transform: uppercase;
  letter-spacing: .05em; padding: 10px 12px 0;
}

/* ── Tables ── */
table { width: 100%; border-collapse: collapse; font-size: 12px; }
thead tr { border-bottom: 2px solid var(--green-md); }
thead th {
  text-align: left; padding: 8px 10px;
  font-size: 9.5px; font-family: 'DM Mono', monospace;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--text-3); font-weight: 500;
  background: var(--green-bg);
}
thead th.num { text-align: right; }
tbody tr { border-bottom: 1px solid var(--border); }
tbody tr:last-child { border-bottom: none; }
tbody td { padding: 8px 10px; vertical-align: middle; }
tbody tr:nth-child(even) { background: var(--row-alt); }

.rank {
  font-family: 'DM Mono', monospace; font-size: 11px;
  color: var(--text-3); text-align: center; width: 28px;
}
.num   { text-align: right; font-family: 'DM Mono', monospace; white-space: nowrap; }
.strong { font-weight: 700; color: var(--green-dk); }
.fname { font-weight: 600; color: var(--text-1); line-height: 1.3; }
.fpath {
  font-size: 10px; color: var(--text-3); font-family: 'DM Mono', monospace;
  margin-top: 1px; white-space: nowrap; overflow: hidden;
  text-overflow: ellipsis; max-width: 260px;
}
.fpath-main {
  font-family: 'DM Mono', monospace; font-size: 11px; color: var(--text-2);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px;
}
.badge {
  display: inline-block; font-size: 9px; font-family: 'DM Mono', monospace;
  font-weight: 600; padding: 1px 5px; border-radius: 3px;
  background: var(--green-bg); color: var(--green-dk);
  border: 1px solid var(--green-rim); letter-spacing: .02em;
}
.ext { font-size: 9px; font-family: 'DM Mono', monospace; color: var(--text-3); }
.empty-cell {
  text-align: center; color: #b8d0bf; padding: 20px;
  font-style: italic; font-family: 'DM Mono', monospace; font-size: 11px;
}
.bm-num { color: var(--gold); font-weight: 700; }

/* ── User avatar bubble ── */
.uavatar {
  width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
  background: var(--green-md); color: var(--white);
  display: flex; align-items: center; justify-content: center;
  font-family: 'DM Mono', monospace; font-size: 12px; font-weight: 600;
}

/* ── Search word cloud ── */
.search-cloud {
  border: 1.5px solid var(--border); border-radius: 10px;
  padding: 14px 18px; background: var(--green-bg);
  display: flex; flex-wrap: wrap; gap: 8px 14px;
  align-items: baseline; line-height: 2;
}
.stag { font-family: 'Source Serif 4', serif; color: var(--green-dk); font-weight: 600; }
.stag em {
  font-style: normal; font-family: 'DM Mono', monospace;
  font-size: 9.5px; color: var(--text-3); font-weight: 400; margin-left: 3px;
}
.empty-srch { color: #b8d0bf; font-style: italic; font-family: 'DM Mono', monospace; font-size: 12px; }

/* ── Report footer ── */
.report-footer {
  margin-top: 44px; padding-top: 14px; padding-bottom: 20px;
  border-top: 1.5px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
  font-family: 'DM Mono', monospace; font-size: 10px; color: var(--text-3);
  /* Always stay on the page — don't let the browser clip it */
  page-break-inside: avoid; break-inside: avoid;
}
.report-footer strong { color: var(--text-2); }
</style>
</head>
<body>

<div class="print-bar no-print">
  <span>LRMDS Analytics Report &nbsp;·&nbsp; ${_escHtmlR(date)} &nbsp;·&nbsp; ${_escHtmlR(range)}</span>
  <div class="print-bar-controls">
    <div class="print-setting">
      <label>Page size</label>
      <select id="pg-size" onchange="_applyPageSettings()">
        <option value="A4 portrait"  selected>A4 Portrait</option>
        <option value="A4 landscape">A4 Landscape</option>
        <option value="letter portrait">Letter Portrait</option>
        <option value="letter landscape">Letter Landscape</option>
      </select>
    </div>
    <div class="print-setting">
      <label>Margins</label>
      <select id="pg-margin" onchange="_applyPageSettings()">
        <option value="10mm 10mm 14mm 10mm">Narrow (10 mm)</option>
        <option value="15mm 13mm 18mm 13mm" selected>Normal (15 mm)</option>
        <option value="20mm 18mm 22mm 18mm">Wide (20 mm)</option>
        <option value="25mm 20mm 28mm 20mm">Very wide (25 mm)</option>
      </select>
    </div>
    <div class="print-bar-btns">
      <button class="pbtn pbtn-secondary" onclick="window.close()">✕ Close</button>
      <button class="pbtn pbtn-primary"   onclick="_doPrint()">🖨&nbsp; Print / Save as PDF</button>
    </div>
  </div>
</div>
<script>
function _applyPageSettings() {
  const size   = document.getElementById('pg-size').value;
  const margin = document.getElementById('pg-margin').value;
  let st = document.getElementById('_page-style');
  if (!st) { st = document.createElement('style'); st.id = '_page-style'; document.head.appendChild(st); }
  st.textContent = '@page { size: ' + size + '; margin: ' + margin + '; }';
}
function _doPrint() {
  _applyPageSettings();
  window.print();
}
</script>

<div class="page">

  <!-- ══ Cover ══ -->
  <div class="cover">
    <div class="cover-logo">
      <img src="data:image/jpeg;base64,${_LOGO_B64}" alt="DepEd Carcar City Division"/>
      <div class="cover-title">
        <h1>DepEd Carcar City LRMDS</h1>
        <p>Learning Resource Management &amp; Development System</p>
      </div>
    </div>
    <div class="cover-meta">
      <strong>Analytics Report</strong>
      Period: ${_escHtmlR(range)}<br/>
      Generated: ${_escHtmlR(date)}<br/>
      Source: LRMDS Admin Dashboard
    </div>
  </div>

  <!-- ══ 1. Summary Metrics ══ -->
  <div class="section-hd"><h2>Summary Metrics</h2></div>
  <div class="metrics-grid-r">
    <div class="mc-r">
      <div class="mc-r-icon">👥</div>
      <div class="mc-r-label">Unique Users</div>
      <div class="mc-r-value">${parseInt(users).toLocaleString()}</div>
      <div class="mc-r-sub">Microsoft accounts signed in</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-icon">🔗</div>
      <div class="mc-r-label">Sessions</div>
      <div class="mc-r-value">${parseInt(sessions).toLocaleString()}</div>
      <div class="mc-r-sub">Total portal visits</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-icon">👁️</div>
      <div class="mc-r-label">File Views</div>
      <div class="mc-r-value">${parseInt(views).toLocaleString()}</div>
      <div class="mc-r-sub">Previews opened</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-icon">⬇️</div>
      <div class="mc-r-label">Downloads</div>
      <div class="mc-r-value">${parseInt(downloads).toLocaleString()}</div>
      <div class="mc-r-sub">Files downloaded</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-icon">🔍</div>
      <div class="mc-r-label">Searches</div>
      <div class="mc-r-value">${parseInt(searches).toLocaleString()}</div>
      <div class="mc-r-sub">User search queries</div>
    </div>
    <div class="mc-r">
      <div class="mc-r-icon">🔖</div>
      <div class="mc-r-label">Bookmarks</div>
      <div class="mc-r-value">${parseInt(bookmarks).toLocaleString()}</div>
      <div class="mc-r-sub">Resources saved to My Library</div>
    </div>
  </div>

  <!-- ══ 2. Activity Trend + Most Bookmarked ══ -->
  <div class="section-hd"><h2>Activity Trend &amp; Most Bookmarked</h2></div>
  <div class="two-col">

    <div class="card">
      <div class="card-title">Downloads &amp; Views — Recent Dates</div>
      <table>
        <thead><tr>
          <th>Date</th>
          <th class="num">Downloads</th>
          <th class="num">Views</th>
        </tr></thead>
        <tbody>${trendRows || '<tr><td colspan="3" class="empty-cell">No trend data available</td></tr>'}</tbody>
      </table>
    </div>

    <div class="card">
      <div class="card-title">Most Bookmarked Files (All-time)</div>
      <table>
        <thead><tr>
          <th style="width:28px">#</th>
          <th>File</th>
          <th class="num">Saves</th>
        </tr></thead>
        <tbody>${bmRows}</tbody>
      </table>
    </div>

  </div>

  <!-- ══ 3. Top 5 Files ══ -->
  <div class="section-hd"><h2>Top 5 Files by Downloads</h2></div>
  <div class="card">
    <table>
      <thead><tr>
        <th style="width:28px">#</th><th>File Name</th><th>Type</th><th class="num">Downloads</th>
      </tr></thead>
      <tbody>${fileRows(topDl, 'downloads')}</tbody>
    </table>
  </div>

  <div class="section-hd"><h2>Top 5 Files by Views</h2></div>
  <div class="card">
    <table>
      <thead><tr>
        <th style="width:28px">#</th><th>File Name</th><th>Type</th><th class="num">Views</th>
      </tr></thead>
      <tbody>${fileRows(topVw, 'views')}</tbody>
    </table>
  </div>

  <!-- ══ 4. Top Folders ══ -->
  <div class="section-hd"><h2>Top Folders</h2></div>
  <div class="card">
    <table>
      <thead><tr>
        <th style="width:28px">#</th><th>Folder Path</th>
        <th class="num">Views</th><th class="num">Downloads</th>
      </tr></thead>
      <tbody>${folderRows(folderData)}</tbody>
    </table>
  </div>

  <!-- ══ 5. Search Queries ══ -->
  <div class="section-hd"><h2>Top Search Queries</h2></div>
  <div class="search-cloud">${searchCloud}</div>

  <!-- ══ 6. Most Active Users ══ -->
  <div class="section-hd"><h2>Most Active Users</h2></div>
  <div class="card">
    <table>
      <thead><tr>
        <th style="width:28px">#</th>
        <th>User</th>
        <th class="num">Sessions</th>
        <th class="num">Views</th>
        <th class="num">Downloads</th>
        <th class="num">Bookmarks</th>
        <th class="num">Searches</th>
      </tr></thead>
      <tbody>${userRows}</tbody>
    </table>
  </div>

  <!-- ── Footer ── -->
  <div class="report-footer">
    <span><strong>DepEd Carcar City LRMDS</strong> &nbsp;·&nbsp; Analytics Report &nbsp;·&nbsp; ${_escHtmlR(date)}</span>
    <span>Period: ${_escHtmlR(range)} &nbsp;·&nbsp; Generated by LRMDS Admin Dashboard</span>
  </div>

</div><!-- /page -->
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

// Exposed for user-activity-report.js CSV preview
window._showCsvPreview = _showCsvPreview;