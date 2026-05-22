/* ============================================================
   LRMDS Admin Dashboard – Per-User Activity Report
   user-activity-report.js

   Opened when an admin taps a user name in Portal Users.
   Shows bookmarks saved, bookmarks removed, downloads, and previews
   for the selected date range. Supports CSV export and print/PDF
   (same workflow as dashboard-export.js).

   Depends on: admindashboard.js date-range controls (#date-range, etc.)
   ============================================================ */

(function (global) {
  'use strict';

  const UA_TRACKER = (function () {
    const loc = window.location.pathname;
    const dir = loc.substring(0, loc.lastIndexOf('/') + 1);
    return dir + 'tracker.php';
  })();

  let _currentUser = null;
  let _currentData = null;

  // ── Date range (mirrors admindashboard.js / dashboard-export.js) ──
  function _getDays() {
    const el = document.getElementById('date-range');
    if (!el) return 30;
    if (el.value === 'custom') return 'custom';
    return parseInt(el.value, 10) || 0;
  }

  function _getCustomRange() {
    const from = document.getElementById('custom-from');
    const to   = document.getElementById('custom-to');
    if (!from || !to || !from.value || !to.value) return null;
    return { from: from.value, to: to.value };
  }

  function _dq(base, days) {
    if (days === 'custom') {
      const range = _getCustomRange();
      if (!range) return base;
      const sep = base.includes('?') ? '&' : '?';
      return `${base}${sep}date_from=${encodeURIComponent(range.from)}&date_to=${encodeURIComponent(range.to)}`;
    }
    if (!days) return base;
    const sep = base.includes('?') ? '&' : '?';
    return `${base}${sep}days=${days}`;
  }

  function _rangeLabel() {
    const el = document.getElementById('date-range');
    if (!el) return 'Last 30 days';
    if (el.value === 'custom') {
      const range = _getCustomRange();
      if (range) return `${range.from} to ${range.to}`;
      return 'Custom range';
    }
    const map = { '1': 'Today', '7': 'Last 7 days', '30': 'Last 30 days', '90': 'Last 90 days', '0': 'All time' };
    return map[el.value] || 'Custom';
  }

  function _dateLabel() {
    return new Date().toLocaleDateString('en-PH', {
      year: 'numeric', month: 'long', day: 'numeric',
    });
  }

  function _slugDate() { return new Date().toISOString().slice(0, 10); }

  function _escHtml(s) {
    if (s == null) return '';
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function _escAttr(s) {
    return _escHtml(s).replace(/'/g, '&#39;');
  }

  function _escCsv(v) {
    if (v == null) return '';
    const s = String(v);
    return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  }

  function _csvRow(arr) { return arr.map(_escCsv).join(',') + '\r\n'; }

  function _truncFolder(raw, maxSegs) {
    if (!raw || raw === '—') return raw || '—';
    const SEP   = raw.includes(' › ') ? ' › ' : '/';
    const parts = raw.split(SEP);
    if (parts.length <= maxSegs) return raw;
    return parts.slice(0, maxSegs).join(SEP) + SEP + '…';
  }

  function _downloadFile(filename, content, mime) {
    const blob = new Blob([content], { type: mime });
    const url  = URL.createObjectURL(blob);
    const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
  }

  // ── Fetch user activity from tracker.php ─────────────────────
  async function _fetchActivity(user) {
    const days = _getDays();
    let url = UA_TRACKER + '?user_activity&limit=500';
    if (user.user_email) url += '&user_email=' + encodeURIComponent(user.user_email);
    else if (user.user_name) url += '&user_name=' + encodeURIComponent(user.user_name);
    else throw new Error('No user identifier');

    url = _dq(url, days);
    const r = await fetch(url);
    if (!r.ok) throw new Error('Failed to load user activity');
    const data = await r.json();
    if (data.error) throw new Error(data.error);
    return data;
  }

  // ── Table row builders ─────────────────────────────────────────
  function _fileCell(name, path, type, ext) {
    const short = (name || '—').length > 52 ? (name || '—').slice(0, 50) + '…' : (name || '—');
    const p     = path ? _truncFolder(path, 3) : '';
    return `<td>
      <div class="ua-fname" title="${_escAttr(name || '')}">${_escHtml(short)}</div>
      ${type || ext ? `<div class="ua-fmeta">${_escHtml(type || '')}${ext ? ' · ' + _escHtml(String(ext).toUpperCase()) : ''}</div>` : ''}
      ${p ? `<div class="ua-fpath">${_escHtml(p)}</div>` : ''}
    </td>`;
  }

  function _rowsHtml(rows, emptyMsg) {
    if (!rows || !rows.length) {
      return `<tr><td colspan="4" class="empty">${_escHtml(emptyMsg)}</td></tr>`;
    }
    return rows.map((r, i) => `<tr>
      <td class="rank">${i + 1}</td>
      ${_fileCell(r.item_name, r.folder_path, r.item_type, r.file_ext)}
      <td class="ua-time">${_escHtml(r.action_at || '—')}</td>
    </tr>`).join('');
  }

  function _summaryCounts(data) {
    return {
      added:   (data.bookmarks_added   || []).length,
      removed: (data.bookmarks_removed || []).length,
      dl:      (data.downloads         || []).length,
      views:   (data.previews          || []).length,
    };
  }

  // ── Modal UI ───────────────────────────────────────────────────
  function _ensureStyles() {
    if (document.getElementById('ua-styles')) return;
    const s = document.createElement('style');
    s.id = 'ua-styles';
    s.textContent = `
      .ua-backdrop{position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);backdrop-filter:blur(3px);
        display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;pointer-events:none;transition:opacity .2s}
      .ua-backdrop.visible{opacity:1;pointer-events:all}
      .ua-dialog{background:#fff;border-radius:14px;width:min(96vw,920px);max-height:92vh;display:flex;flex-direction:column;
        box-shadow:0 24px 64px rgba(0,0,0,.22);transform:translateY(10px) scale(.98);transition:transform .2s}
      .ua-backdrop.visible .ua-dialog{transform:translateY(0) scale(1)}
      .ua-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:16px 20px;
        background:#1a5c2a;color:#fff;border-radius:14px 14px 0 0}
      .ua-header h2{font-size:16px;font-weight:700;margin:0 0 4px}
      .ua-header p{font-size:12px;opacity:.85;margin:0;font-family:'DM Mono',monospace}
      .ua-close{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;
        border-radius:6px;width:30px;height:30px;cursor:pointer;font-size:14px;flex-shrink:0}
      .ua-close:hover{background:rgba(255,255,255,.25)}
      .ua-toolbar{display:flex;flex-wrap:wrap;gap:8px;padding:12px 20px;border-bottom:1px solid #E5E7EB;background:#fafaf8}
      .ua-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;border:none;
        font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;cursor:pointer}
      .ua-btn-primary{background:#1a5c2a;color:#fff}
      .ua-btn-secondary{background:#fff;color:#374151;border:1px solid #D1D5DB}
      .ua-btn:hover{opacity:.88}
      .ua-body{overflow-y:auto;padding:16px 20px 20px;flex:1}
      .ua-metrics{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
      @media(max-width:640px){.ua-metrics{grid-template-columns:repeat(2,1fr)}}
      .ua-metric{border:1px solid #E5E7EB;border-radius:10px;padding:12px;background:#f0f7f2;text-align:center}
      .ua-metric-val{font-size:22px;font-weight:700;font-family:'DM Mono',monospace;color:#1a5c2a}
      .ua-metric-lbl{font-size:10px;color:#607060;text-transform:uppercase;letter-spacing:.05em;margin-top:4px}
      .ua-section{margin-bottom:20px}
      .ua-section-hd{font-size:12px;font-weight:700;color:#1a5c2a;text-transform:uppercase;letter-spacing:.06em;
        margin-bottom:8px;display:flex;align-items:center;gap:8px}
      .ua-section-hd span{background:#E8F5EC;color:#1a5c2a;font-size:11px;padding:2px 8px;border-radius:20px;font-family:'DM Mono',monospace}
      .ua-table-wrap{border:1px solid #E5E7EB;border-radius:10px;overflow:auto;max-height:220px}
      .ua-table{width:100%;border-collapse:collapse;font-size:12px}
      .ua-table th{text-align:left;padding:8px 10px;background:#f5fbf6;border-bottom:2px solid #b6ddc2;
        font-size:10px;text-transform:uppercase;letter-spacing:.04em;color:#607060;position:sticky;top:0}
      .ua-table td{padding:8px 10px;border-bottom:1px solid #EEF2F0;vertical-align:top}
      .ua-table tr:last-child td{border-bottom:none}
      .ua-table .rank{width:32px;color:#9CA3AF;font-family:'DM Mono',monospace;font-weight:600}
      .ua-fname{font-weight:600;color:#111827}
      .ua-fmeta{font-size:10px;color:#6B7280;margin-top:2px}
      .ua-fpath{font-size:10px;color:#9CA3AF;font-family:'DM Mono',monospace;margin-top:2px;max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
      .ua-time{font-size:11px;font-family:'DM Mono',monospace;color:#6B7280;white-space:nowrap}
      .ua-loading{padding:40px;text-align:center;color:#6B7280}
      .user-activity-link{background:none;border:none;padding:0;font:inherit;font-size:12px;font-weight:500;
        color:var(--blue,#2563EB);cursor:pointer;text-align:left;text-decoration:underline;text-underline-offset:2px}
      .user-activity-link:hover{color:#1D4ED8}
      .ua-backdrop.ua-stack-behind{z-index:9990}
      .ua-print-guide{position:fixed;inset:0;z-index:10050;background:rgba(0,0,0,.5);backdrop-filter:blur(3px);
        display:flex;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s}
      .ua-print-guide.visible{opacity:1;pointer-events:all}
      .ua-print-guide-dialog{background:#fff;border-radius:16px;width:min(90vw,680px);
        box-shadow:0 24px 64px rgba(0,0,0,.28);overflow:hidden;transform:translateY(12px) scale(.98);transition:transform .2s}
      .ua-print-guide.visible .ua-print-guide-dialog{transform:translateY(0) scale(1)}
      .more-note{font-size:12px;color:#607060;font-style:italic;padding:10px 12px 4px;font-family:'DM Mono',monospace}
    `;
    document.head.appendChild(s);
  }

  function _stackBehindUserModal(behind) {
    const ua = document.getElementById('ua-backdrop');
    if (ua) ua.classList.toggle('ua-stack-behind', !!behind);
  }

  function _renderModal(data) {
    const c = _summaryCounts(data);
    const name  = data.user_name  || _currentUser?.user_name  || '—';
    const email = data.user_email || _currentUser?.user_email || '';
    const range = _rangeLabel();

    return `
      <div class="ua-header">
        <div>
          <h2>${_escHtml(name)}</h2>
          <p>${email ? _escHtml(email) + ' · ' : ''}Period: ${_escHtml(range)}</p>
        </div>
        <button type="button" class="ua-close" aria-label="Close">✕</button>
      </div>
      <div class="ua-toolbar">
        <button type="button" class="ua-btn ua-btn-secondary" id="ua-btn-csv">Export CSV</button>
        <button type="button" class="ua-btn ua-btn-primary" id="ua-btn-print">Print / Save PDF</button>
      </div>
      <div class="ua-body">
        <div class="ua-metrics">
          <div class="ua-metric"><div class="ua-metric-val">${c.added}</div><div class="ua-metric-lbl">Saved to Library</div></div>
          <div class="ua-metric"><div class="ua-metric-val">${c.removed}</div><div class="ua-metric-lbl">Removed from Library</div></div>
          <div class="ua-metric"><div class="ua-metric-val">${c.dl}</div><div class="ua-metric-lbl">Downloads</div></div>
          <div class="ua-metric"><div class="ua-metric-val">${c.views}</div><div class="ua-metric-lbl">File Previews</div></div>
        </div>

        <div class="ua-section">
          <div class="ua-section-hd">🔖 Saved to My Library <span>${c.added}</span></div>
          <div class="ua-table-wrap"><table class="ua-table"><thead><tr>
            <th>#</th><th>File</th><th>Date &amp; time</th>
          </tr></thead><tbody>${_rowsHtml(data.bookmarks_added, 'No saves in this period')}</tbody></table></div>
        </div>

        <div class="ua-section">
          <div class="ua-section-hd">🗑 Removed from My Library <span>${c.removed}</span></div>
          <div class="ua-table-wrap"><table class="ua-table"><thead><tr>
            <th>#</th><th>File</th><th>Date &amp; time</th>
          </tr></thead><tbody>${_rowsHtml(data.bookmarks_removed, 'No removals in this period')}</tbody></table></div>
        </div>

        <div class="ua-section">
          <div class="ua-section-hd">⬇ Downloads <span>${c.dl}</span></div>
          <div class="ua-table-wrap"><table class="ua-table"><thead><tr>
            <th>#</th><th>File</th><th>Date &amp; time</th>
          </tr></thead><tbody>${_rowsHtml(data.downloads, 'No downloads in this period')}</tbody></table></div>
        </div>

        <div class="ua-section">
          <div class="ua-section-hd">👁 File Previews <span>${c.views}</span></div>
          <div class="ua-table-wrap"><table class="ua-table"><thead><tr>
            <th>#</th><th>File</th><th>Date &amp; time</th>
          </tr></thead><tbody>${_rowsHtml(data.previews, 'No previews in this period')}</tbody></table></div>
        </div>
      </div>`;
  }

  function _close() {
    const el = document.getElementById('ua-backdrop');
    if (el) {
      el.classList.remove('visible');
      setTimeout(() => el.remove(), 220);
    }
    _currentData = null;
  }

  function _wireModal() {
    const backdrop = document.getElementById('ua-backdrop');
    if (!backdrop) return;

    backdrop.querySelector('.ua-close')?.addEventListener('click', _close);
    backdrop.addEventListener('click', e => { if (e.target === backdrop) _close(); });
    document.getElementById('ua-btn-csv')?.addEventListener('click', () => exportUserCSV());
    document.getElementById('ua-btn-print')?.addEventListener('click', () => showUserPrintGuide());
    document.addEventListener('keydown', function onEsc(e) {
      if (e.key === 'Escape' && document.getElementById('ua-backdrop')) {
        _close();
        document.removeEventListener('keydown', onEsc);
      }
    });
  }

  async function open(user) {
    if (!user || (!user.user_email && !user.user_name)) return;

    _ensureStyles();
    _close();

    _currentUser = {
      user_name:  user.user_name  || '',
      user_email: user.user_email || '',
    };

    const backdrop = document.createElement('div');
    backdrop.id = 'ua-backdrop';
    backdrop.className = 'ua-backdrop';
    backdrop.innerHTML = `<div class="ua-dialog"><div class="ua-loading">Loading activity…</div></div>`;
    document.body.appendChild(backdrop);
    requestAnimationFrame(() => backdrop.classList.add('visible'));

    try {
      _currentData = await _fetchActivity(_currentUser);
      const dialog = backdrop.querySelector('.ua-dialog');
      if (dialog) {
        dialog.innerHTML = _renderModal(_currentData);
        _wireModal();
      }
    } catch (err) {
      const dialog = backdrop.querySelector('.ua-dialog');
      if (dialog) {
        dialog.innerHTML = `
          <div class="ua-header"><div><h2>Error</h2><p>${_escHtml(err.message || 'Could not load data')}</p></div>
          <button type="button" class="ua-close">✕</button></div>
          <div class="ua-body"><p class="empty">Try refreshing the dashboard or choosing a different date range.</p></div>`;
        backdrop.querySelector('.ua-close')?.addEventListener('click', _close);
      }
    }
  }

  // ── CSV export (preview modal like dashboard-export.js) ────────
  function _buildUserCsv(data) {
    const name  = data.user_name  || '—';
    const email = data.user_email || '—';
    const range = _rangeLabel();
    const date  = _dateLabel();
    const slug  = _slugDate();
    const safe  = (name !== '—' ? name : email).replace(/[^\w\-]+/g, '_').slice(0, 40);

    let csv = '';
    csv += _csvRow(['LRMDS User Activity Report – DepEd Carcar City']);
    csv += _csvRow(['Generated', date]);
    csv += _csvRow(['Period', range]);
    csv += _csvRow(['User Name', name]);
    csv += _csvRow(['User Email', email]);
    csv += _csvRow([]);

    function section(title, rows, cols) {
      csv += _csvRow(['=== ' + title + ' ===']);
      csv += _csvRow(cols);
      if (!rows.length) csv += _csvRow(['—', '—', '—', '—'].slice(0, cols.length));
      rows.forEach((r, i) => {
        csv += _csvRow([
          i + 1,
          r.item_name || '—',
          r.item_type || '—',
          r.file_ext  || '—',
          r.folder_path || '—',
          r.action_at || '—',
        ]);
      });
      csv += _csvRow([]);
    }

    const cols = ['#', 'File Name', 'Type', 'Extension', 'Folder Path', 'Date & Time'];
    section('SAVED TO MY LIBRARY', data.bookmarks_added || [], cols);
    section('REMOVED FROM MY LIBRARY', data.bookmarks_removed || [], cols);
    section('DOWNLOADS', data.downloads || [], cols);
    section('FILE PREVIEWS', data.previews || [], cols);

    return { csv, filename: `LRMDS_User_${safe}_${slug}.csv` };
  }

  function exportUserCSV() {
    if (!_currentData) return;
    const { csv, filename } = _buildUserCsv(_currentData);
    if (typeof window._showCsvPreview === 'function') {
      _stackBehindUserModal(true);
      window._csvPreviewData = csv;
      window._showCsvPreview(csv, filename);
      const cpm = document.getElementById('csv-preview-modal');
      if (cpm) {
        cpm.style.zIndex = '10050';
        const restore = () => {
          _stackBehindUserModal(false);
          cpm.style.zIndex = '';
        };
        cpm.querySelector('.cpm-close')?.addEventListener('click', restore, { once: true });
        cpm.querySelector('.cpm-backdrop')?.addEventListener('click', restore, { once: true });
        cpm.querySelector('.cpm-btn-cancel')?.addEventListener('click', restore, { once: true });
        const saveBtn = cpm.querySelector('.cpm-btn-save');
        if (saveBtn) {
          saveBtn.addEventListener('click', () => setTimeout(restore, 300), { once: true });
        }
      }
    } else {
      _downloadFile('\uFEFF' + csv, filename, 'text/csv;charset=utf-8');
    }
  }

  // ── Print / PDF (same pattern as dashboard-export.js) ──────────
  function _activityTableRows(rows, emptyMsg) {
    if (!rows || !rows.length) {
      return `<tr><td colspan="4" class="empty-cell">${_escHtml(emptyMsg)}</td></tr>`;
    }
    return rows.map((r, i) => {
      const path = _truncFolder(r.folder_path || '', 4);
      return `<tr>
        <td class="rank">${i + 1}</td>
        <td>
          <div class="fname">${_escHtml(r.item_name || '—')}</div>
          ${path ? `<div class="fpath">${_escHtml(path)}</div>` : ''}
          ${r.item_type || r.file_ext ? `<div class="fmeta">${_escHtml(r.item_type || '')}${r.file_ext ? ' · ' + _escHtml(String(r.file_ext).toUpperCase()) : ''}</div>` : ''}
        </td>
        <td class="ua-time-col">${_escHtml(r.action_at || '—')}</td>
      </tr>`;
    }).join('');
  }

  function _sortedDownloads(rows) {
    return (rows || []).slice().sort((a, b) => {
      const ta = (a.action_at || '').replace(' ', 'T');
      const tb = (b.action_at || '').replace(' ', 'T');
      return tb.localeCompare(ta);
    });
  }

  function printUserReport() {
    if (!_currentData) return;

    const data     = _currentData;
    const name     = data.user_name  || '—';
    const email    = data.user_email || '';
    const range    = _rangeLabel();
    const date     = _dateLabel();
    const c        = _summaryCounts(data);
    const allDl    = _sortedDownloads(data.downloads);
    const dlShow   = allDl.slice(0, 10);
    const dlMore   = Math.max(0, allDl.length - 10);
    const moreNote = dlMore > 0
      ? `<p class="more-note">and ${dlMore} more item${dlMore !== 1 ? 's' : ''}</p>`
      : '';

    const reportCss = `
:root{--green-dk:#1a5c2a;--green-md:#2d7a3e;--green-bg:#f0f7f2;--border:#cde0d3;--text-1:#0f1a12;--text-3:#607060}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
/* Slightly smaller base font to help content fit on one A4 page when printed */
body{font-family:'Source Serif 4',Georgia,serif;color:var(--text-1);font-size:13px;line-height:1.55}
/* Narrower page margins to gain printable area */
@page{size:A4 portrait;margin:8mm 8mm 10mm 8mm}
/* Preserve table headings and avoid breaking table rows across pages */
@media print{
  html,body{zoom:106%}
  .no-print{display:none!important}
  thead{display:table-header-group}
  tbody tr{page-break-inside:avoid;break-inside:avoid}
}
/* Reduce large paddings so the whole report fits on a single page */
.page{max-width:880px;margin:0 auto;padding:12px 16px 40px}
.print-bar{position:sticky;top:0;z-index:99;background:var(--green-dk);color:#fff;display:flex;align-items:center;justify-content:space-between;padding:8px 12px;font-family:'DM Mono',monospace;font-size:11px}
.pbtn{padding:6px 10px;border-radius:6px;border:none;font-family:'DM Mono',monospace;font-size:11px;cursor:pointer;margin-left:6px}
.pbtn-primary{background:#fff;color:var(--green-dk)}
.pbtn-outline{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.45)}
.cover{border-bottom:2px solid var(--green-dk);padding-bottom:10px;margin-bottom:12px}
.cover h1{font-size:18px;color:var(--green-dk);margin-bottom:4px}
.cover p{font-size:11px;color:var(--text-3);font-family:'DM Mono',monospace}
.metrics-grid-r{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:12px}
.more-note{font-size:10px;color:#607060;font-style:italic;padding:6px 8px 0;font-family:'DM Mono',monospace}
.mc-r{border:1px solid var(--border);border-radius:8px;padding:8px;background:var(--green-bg);text-align:center}
.mc-r-value{font-size:18px;font-weight:700;font-family:'DM Mono',monospace;color:var(--green-dk)}
.mc-r-label{font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:var(--text-3);margin-top:4px}
.section-hd{display:flex;align-items:center;gap:8px;margin:12px 0 8px}
.section-hd::before{content:'';width:3px;height:16px;background:var(--green-md);border-radius:2px}
.section-hd h2{font-size:11px;font-weight:700;color:var(--green-dk);text-transform:uppercase;letter-spacing:.06em}
.card{border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:6px}
/* Avoid breaking cards (tables) across pages when possible */
.card{page-break-inside:avoid;break-inside:avoid;-webkit-break-inside:avoid}
/* Slightly smaller table typography and tighter cells */
table{width:100%;border-collapse:collapse;font-size:11px}
thead tr{border-bottom:2px solid var(--green-md)}
th{text-align:left;padding:6px 8px;font-size:9px;text-transform:uppercase;color:var(--text-3)}
td{padding:6px 8px;border-bottom:1px solid #EEF2F0;vertical-align:top}
.rank{width:26px;color:#9CA3AF;font-family:'DM Mono',monospace}
.fname{font-weight:600}
.fpath{font-size:9px;color:var(--text-3);font-family:'DM Mono',monospace;margin-top:2px}
.fmeta{font-size:9px;color:#6B7280;margin-top:2px}
.ua-time-col{font-size:10px;font-family:'DM Mono',monospace;white-space:nowrap}
.empty-cell{text-align:center;color:var(--text-3);font-style:italic;padding:10px}
/* Ensure table rows don't break inside and footer stays below content */
tbody tr{page-break-inside:avoid;break-inside:avoid;-webkit-page-break-inside:avoid}
.report-footer{margin-top:18px;padding-top:8px;border-top:1px solid var(--border);font-size:10px;color:var(--text-3);font-family:'DM Mono',monospace;display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px;page-break-inside:avoid;break-inside:avoid;-webkit-break-inside:avoid}
`;

    const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"/>
<title>User Activity – ${_escHtml(name)} – ${date}</title>
<link href="https://fonts.googleapis.com/css2?family=Source+Serif+4:wght@400;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<style>${reportCss}</style></head><body>
<div class="print-bar no-print">
  <span>User activity report · ${_escHtml(name)}</span>
  <div>
    <button class="pbtn pbtn-outline" onclick="window.close()">Close</button>
    <button class="pbtn pbtn-primary" onclick="window.print()">Print / Save PDF</button>
  </div>
</div>
<div class="page">
  <div class="cover">
    <h1>User Activity Report</h1>
    <p><strong>${_escHtml(name)}</strong>${email ? ' · ' + _escHtml(email) : ''}</p>
    <p>Period: ${_escHtml(range)} · Generated: ${_escHtml(date)}</p>
  </div>

  <div class="metrics-grid-r">
    <div class="mc-r"><div class="mc-r-value">${c.added}</div><div class="mc-r-label">Total saved to Library</div></div>
    <div class="mc-r"><div class="mc-r-value">${c.removed}</div><div class="mc-r-label">Total removed from Library</div></div>
    <div class="mc-r"><div class="mc-r-value">${c.dl}</div><div class="mc-r-label">Total downloads</div></div>
  </div>

  <div class="section-hd"><h2>Recent downloads (most recent first, up to 10)</h2></div>
  <div class="card"><table><thead><tr><th>#</th><th>File</th><th>Date &amp; time</th></tr></thead>
  <tbody>${_activityTableRows(dlShow, 'No downloads in this period')}</tbody></table></div>
  ${moreNote}

  <div class="report-footer">
    <span><strong>DepEd Carcar City LRMDS</strong> · User accountability record</span>
    <span>${_escHtml(date)} · Period: ${_escHtml(range)}</span>
  </div>
</div>
</body></html>`;

    const win = window.open('', '_blank');
    if (!win) {
      alert('Pop-up blocked! Please allow pop-ups for this site and try again.');
      return;
    }
    win.document.write(html);
    win.document.close();
  }

  function _hideUserPrintGuide() {
    const el = document.getElementById('ua-print-guide');
    if (el) {
      el.classList.remove('visible');
      setTimeout(() => el.remove(), 200);
    }
    _stackBehindUserModal(false);
  }

  function showUserPrintGuide() {
    _hideUserPrintGuide();
    _stackBehindUserModal(true);

    const guide = document.createElement('div');
    guide.id = 'ua-print-guide';
    guide.className = 'ua-print-guide';
    guide.innerHTML = `
      <div class="ua-print-guide-dialog" onclick="event.stopPropagation()">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#1a5c2a;color:#fff">
          <div>
            <div style="font-size:14px;font-weight:700">Print or Save as PDF</div>
            <div style="font-size:11px;opacity:.75;margin-top:2px">Quick guide before opening the report</div>
          </div>
          <button type="button" class="ua-close" id="ua-guide-close" aria-label="Close">✕</button>
        </div>
        <div style="padding:20px;font-family:'DM Sans',sans-serif;font-size:13px;color:#374151;line-height:1.6">
          <div style="background:#f0f7f2;border:1px solid #b6ddc2;border-radius:8px;padding:10px 14px;margin-bottom:14px">
            When the report opens, click <strong>Print / Save as PDF</strong>.
            In the browser print dialog, set <strong>Destination</strong> to
            <strong>Save as PDF</strong> — then click <strong>Save</strong>.
          </div>
          <p style="font-size:12px;color:#607060;margin:0">
            The printable summary shows bookmark totals, removal totals, and up to 10 most recent downloads for the selected period.
          </p>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;border-top:1px solid #E5E7EB;background:#fafaf8">
          <button type="button" class="ua-btn ua-btn-secondary" id="ua-guide-cancel">Cancel</button>
          <button type="button" class="ua-btn ua-btn-primary" id="ua-guide-open">Got it — Open Report</button>
        </div>
      </div>`;

    document.body.appendChild(guide);
    requestAnimationFrame(() => guide.classList.add('visible'));

    guide.addEventListener('click', () => _hideUserPrintGuide());
    document.getElementById('ua-guide-close')?.addEventListener('click', e => { e.stopPropagation(); _hideUserPrintGuide(); });
    document.getElementById('ua-guide-cancel')?.addEventListener('click', e => { e.stopPropagation(); _hideUserPrintGuide(); });
    document.getElementById('ua-guide-open')?.addEventListener('click', e => {
      e.stopPropagation();
      _hideUserPrintGuide();
      printUserReport();
    });
  }

  global.UserActivityReport = {
    open,
    close: _close,
    exportUserCSV,
    printUserReport,
    showUserPrintGuide,
  };

})(window);
