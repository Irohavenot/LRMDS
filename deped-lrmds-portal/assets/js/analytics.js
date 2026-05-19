/* ============================================================
   DepEd Carcar City LRMDS – Analytics Badge + My Library Module
   analytics.js  (v3)
   ============================================================
   Responsibilities:
     1. Attach 👁/⬇ count badges to file cards.
     2. My Library tab — persist bookmarks in localStorage, render
        the library panel with full Preview + Download + Open actions,
        fire bookmark_add / bookmark_remove events to tracker.php.

   All other event tracking (page_view, file_view, file_download,
   folder_open, search, session_end) is handled by onedrive.js
   ============================================================ */

(function () {
  'use strict';

  // ── Config ──────────────────────────────────────────────────
  const TRACKER_URL = (function () {
    const loc = window.location.pathname;
    const dir = loc.substring(0, loc.lastIndexOf('/') + 1);
    return dir + 'tracker.php';
  })();

  const COUNT_FETCH_DELAY = 800;
  const LIBRARY_KEY       = 'lrmds_library_v1';

  // ── In-memory counts cache ───────────────────────────────────
  const _countsCache = new Map();

  // ── Pending badge items ──────────────────────────────────────
  const _pendingBadge = new Map();
  let   _badgeFetchTimer = null;

  // ═══════════════════════════════════════════════════════════
  //  LIBRARY  — localStorage-backed bookmarks
  // ═══════════════════════════════════════════════════════════

  /*
   * Each saved entry stores everything needed to Preview + Download:
   * { id, name, webUrl, mimeType, size, meta, savedAt }
   *
   * _entryToItem() reconstructs the shape onedrive.js expects:
   * { id, name, webUrl, size, file: { mimeType }, _meta }
   */
  function loadLibrary() {
    try { return JSON.parse(localStorage.getItem(LIBRARY_KEY) || '{}'); }
    catch (e) { return {}; }
  }

  function saveLibrary(lib) {
    try { localStorage.setItem(LIBRARY_KEY, JSON.stringify(lib)); }
    catch (e) {}
  }

  function isBookmarked(itemId) { return !!loadLibrary()[itemId]; }

  function _entryToItem(entry) {
    return {
      id:     entry.id,
      name:   entry.name,
      webUrl: entry.webUrl,
      size:   entry.size || 0,
      file:   { mimeType: entry.mimeType || '' },
      _meta:  entry.meta || {},
    };
  }

  function toggleBookmark(item) {
    const lib = loadLibrary();
    const wasSaved = !!lib[item.id];

    if (wasSaved) {
      delete lib[item.id];
    } else {
      lib[item.id] = {
        id:       item.id,
        name:     item.name,
        webUrl:   item.webUrl,
        mimeType: item.file?.mimeType || '',
        size:     item.size || 0,
        meta:     item._meta || {},
        savedAt:  Date.now(),
      };
    }
    saveLibrary(lib);

    _trackBookmark(item, wasSaved ? 'bookmark_remove' : 'bookmark_add');
    updateBookmarkButtons(item.id, !wasSaved);
    refreshLibraryPanel();
    updateLibraryBadge();
  }

  function _trackBookmark(item, eventName) {
    if (typeof window._track === 'function') {
      window._track(eventName, {
        item_id:   item.id,
        item_name: item.name,
        item_type: (item._meta && item._meta.type) || '',
        file_ext:  (item.name || '').split('.').pop().toLowerCase(),
      });
    }
  }

  // ═══════════════════════════════════════════════════════════
  //  MY LIBRARY NAV TAB + PANEL
  // ═══════════════════════════════════════════════════════════

  let _libraryTabInjected = false;
  let _libraryPanelEl     = null;

  function initLibraryTab() {
    if (_libraryTabInjected) return;
    _libraryTabInjected = true;

    // Inject nav link
    const nav = document.querySelector('.header-nav');
    if (nav) {
      const link = document.createElement('a');
      link.className = 'nav-link';
      link.href      = '#';
      link.id        = 'library-nav-link';
      link.innerHTML = '🔖 My Library <span class="lib-badge" id="lib-badge"></span>';
      link.addEventListener('click', e => { e.preventDefault(); toggleLibraryPanel(); });
      nav.appendChild(link);
      updateLibraryBadge();
    }

    // Create slide-in panel
    _libraryPanelEl = document.createElement('div');
    _libraryPanelEl.id        = 'library-panel';
    _libraryPanelEl.className = 'library-panel';
    _libraryPanelEl.innerHTML = `
      <div class="lib-panel-header">
        <span class="lib-panel-title">🔖 My Library</span>
        <button class="lib-panel-close" id="lib-panel-close" title="Close">✕</button>
      </div>
      <div class="lib-panel-body" id="lib-panel-body"></div>`;
    document.body.appendChild(_libraryPanelEl);

    document.getElementById('lib-panel-close').addEventListener('click', closeLibraryPanel);

    // Close on outside click
    document.addEventListener('click', e => {
      if (_libraryPanelEl && _libraryPanelEl.classList.contains('open') &&
          !_libraryPanelEl.contains(e.target) &&
          !e.target.closest('#library-nav-link')) {
        closeLibraryPanel();
      }
    });

    refreshLibraryPanel();
  }

  function toggleLibraryPanel() {
    if (!_libraryPanelEl) return;
    _libraryPanelEl.classList.toggle('open');
    if (_libraryPanelEl.classList.contains('open')) refreshLibraryPanel();
  }

  function closeLibraryPanel() {
    if (_libraryPanelEl) _libraryPanelEl.classList.remove('open');
  }

  function updateLibraryBadge() {
    const badge = document.getElementById('lib-badge');
    if (!badge) return;
    const count = Object.keys(loadLibrary()).length;
    badge.textContent = count > 0 ? String(count) : '';
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
  }

  // ── Library panel renderer ───────────────────────────────────
  function refreshLibraryPanel() {
    const body = document.getElementById('lib-panel-body');
    if (!body) return;

    const lib   = loadLibrary();
    const saved = Object.values(lib).sort((a, b) => b.savedAt - a.savedAt);

    if (!saved.length) {
      body.innerHTML = '<div class="lib-empty">No saved files yet.<br>Click the 🔖 button on any file card to save it here.</div>';
      return;
    }

    body.innerHTML = '';

    saved.forEach(entry => {
      // Reconstruct item so onedrive.js functions can use it
      const item = _entryToItem(entry);

      const ext  = (entry.name.split('.').pop() || '').toUpperCase();
      const size = entry.size > 1048576
        ? (entry.size / 1048576).toFixed(1) + ' MB'
        : entry.size > 0 ? Math.round(entry.size / 1024) + ' KB' : '';
      const meta      = entry.meta || {};
      const savedDate = new Date(entry.savedAt).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric'
      });

      // Ask onedrive.js if this file type supports in-app preview
      const canPreview = typeof window.getPreviewType === 'function'
        ? !!window.getPreviewType(item)
        : false;

      const el = document.createElement('div');
      el.className      = 'lib-item';
      el.dataset.itemId = entry.id;

      el.innerHTML = `
        <div class="lib-item-top">
          <span class="lib-item-icon">${_mimeIcon(entry.mimeType)}</span>
          <div class="lib-item-info">
            <div class="lib-item-name" title="${_escHtml(entry.name)}">${_escHtml(entry.name)}</div>
            <div class="lib-item-meta">
              ${meta.grade   ? `<span class="lib-tag">${_escHtml(meta.grade)}</span>`   : ''}
              ${meta.subject ? `<span class="lib-tag">${_escHtml(meta.subject)}</span>` : ''}
              ${ext          ? `<span class="lib-tag">${_escHtml(ext)}</span>`           : ''}
              ${size         ? `<span class="lib-tag-muted">${_escHtml(size)}</span>`   : ''}
            </div>
            <div class="lib-item-saved">Saved ${_escHtml(savedDate)}</div>
          </div>
        </div>
        <div class="lib-item-actions">
          ${canPreview
            ? `<button class="lib-btn lib-preview-btn" title="Preview this file">👁 Preview</button>`
            : ''}
          <button class="lib-btn lib-dl-btn" title="Download this file">⬇ Download</button>
          <a href="${_escHtml(entry.webUrl)}" target="_blank" rel="noopener"
             class="lib-btn lib-open-btn" title="Open in OneDrive">↗ Open</a>
          <button class="lib-btn lib-remove-btn" data-id="${_escHtml(entry.id)}"
                  title="Remove from My Library">🗑</button>
        </div>`;

      // Preview — closes panel then opens prototype2's preview modal
      if (canPreview) {
        el.querySelector('.lib-preview-btn').addEventListener('click', () => {
          closeLibraryPanel();
          window.openPreview(item);
        });
      }

      // Download — calls prototype2's downloadFile directly
      el.querySelector('.lib-dl-btn').addEventListener('click', () => {
        if (typeof window.downloadFile === 'function') {
          window.downloadFile(item.id, item.name);
        } else {
          window.open(entry.webUrl, '_blank', 'noopener');
        }
      });

      // Remove bookmark
      el.querySelector('.lib-remove-btn').addEventListener('click', () => {
        const current = loadLibrary();
        if (!current[entry.id]) return;
        delete current[entry.id];
        saveLibrary(current);
        _trackBookmark(item, 'bookmark_remove');
        updateBookmarkButtons(entry.id, false);
        refreshLibraryPanel();
        updateLibraryBadge();
      });

      body.appendChild(el);
    });
  }

  // ═══════════════════════════════════════════════════════════
  //  BOOKMARK BUTTONS on file cards
  // ═══════════════════════════════════════════════════════════

  function attachBookmarkButton(card, item) {
    if (card.querySelector('.bm-btn')) return;

    const btn = document.createElement('button');
    btn.className      = 'bm-btn';
    btn.dataset.itemId = item.id;
    _syncBmButton(btn, isBookmarked(item.id));
    btn.addEventListener('click', e => { e.stopPropagation(); toggleBookmark(item); });

    const actions = card.querySelector('.card-actions') || card;
    actions.insertBefore(btn, actions.firstChild);
  }

  function _syncBmButton(btn, bookmarked) {
    btn.textContent = '🔖';
    btn.classList.toggle('bm-btn--saved', bookmarked);
    btn.title = bookmarked ? 'Remove from My Library' : 'Save to My Library';
  }

  function updateBookmarkButtons(itemId, bookmarked) {
    document.querySelectorAll(`.bm-btn[data-item-id="${CSS.escape(itemId)}"]`)
      .forEach(btn => _syncBmButton(btn, bookmarked));
  }

  // ═══════════════════════════════════════════════════════════
  //  COUNTS: view/download badges
  // ═══════════════════════════════════════════════════════════

  function fetchCounts(itemId) {
    if (_countsCache.has(itemId)) {
      refreshBadgeForItem(itemId, _countsCache.get(itemId));
      return;
    }
    fetch(`${TRACKER_URL}?counts&item_id=${encodeURIComponent(itemId)}`)
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if (!data) return;
        _countsCache.set(itemId, data);
        refreshBadgeForItem(itemId, data);
      })
      .catch(() => {});
  }

  function refreshBadgeForItem(itemId, counts) {
    const info = _pendingBadge.get(itemId);
    if (!info) return;
    const badge = info.card.querySelector('.analytics-badge');
    if (badge) renderBadge(badge, counts);
  }

  function renderBadge(el, counts) {
    el.innerHTML =
      `<span title="Views">👁 ${counts.views ?? 0}</span>` +
      `<span title="Downloads" style="margin-left:8px">⬇ ${counts.downloads ?? 0}</span>`;
    el.style.opacity = '1';
  }

  function attachBadgeToCard(card, itemId) {
    if (card.querySelector('.analytics-badge')) return;

    const badge = document.createElement('span');
    badge.className = 'analytics-badge';
    badge.title     = 'Views · Downloads';
    badge.style.cssText = `
      display: inline-flex; align-items: center;
      font-size: 11px; color: #9CA3AF;
      margin-left: auto; opacity: 0; transition: opacity .3s;`;
    badge.innerHTML = '👁 … ⬇ …';

    const target = card.querySelector('.card-actions') || card.querySelector('.card-detail') || card;
    target.appendChild(badge);

    _pendingBadge.set(itemId, { card, fetched: false });
    scheduleBadgeFetch();
  }

  function scheduleBadgeFetch() {
    clearTimeout(_badgeFetchTimer);
    _badgeFetchTimer = setTimeout(() => {
      for (const [itemId, info] of _pendingBadge.entries()) {
        if (!info.fetched) { info.fetched = true; fetchCounts(itemId); }
      }
    }, COUNT_FETCH_DELAY);
  }

  document.addEventListener('lrmds:counts', function (e) {
    const { itemId, counts } = e.detail || {};
    if (itemId && counts) {
      _countsCache.set(itemId, counts);
      refreshBadgeForItem(itemId, counts);
    }
  });

  // ═══════════════════════════════════════════════════════════
  //  HOOKS: renderItems + buildFileCard + showApp
  // ═══════════════════════════════════════════════════════════

  function wrapWhenReady(fnName, wrapper, retries = 40) {
    if (typeof window[fnName] === 'function') {
      window[fnName] = wrapper(window[fnName]);
    } else if (retries > 0) {
      setTimeout(() => wrapWhenReady(fnName, wrapper, retries - 1), 100);
    }
  }

  // Registry: itemId → full item object (so bookmark toggle gets full data)
  const _itemRegistry = new Map();

  wrapWhenReady('buildFileCard', original => function (item, isSearch) {
    _itemRegistry.set(item.id, item);
    return original.call(this, item, isSearch);
  });

  wrapWhenReady('renderItems', original => function (items, titleText, isSearch) {
    const result = original.call(this, items, titleText, isSearch);
    _pendingBadge.clear();
    setTimeout(() => {
      const grid = document.getElementById('results');
      if (!grid) return;
      grid.querySelectorAll('.result-card[data-item-id]').forEach(card => {
        const itemId = card.dataset.itemId;
        if (!itemId) return;
        attachBadgeToCard(card, itemId);
        const item = _itemRegistry.get(itemId);
        if (item) attachBookmarkButton(card, item);
      });
    }, 0);
    return result;
  });

  // Inject library tab after sign-in (onedrive.js wraps showApp first for page_view)
  wrapWhenReady('showApp', original => function () {
    const result = original.call(this);
    initLibraryTab();
    return result;
  });

  // ═══════════════════════════════════════════════════════════
  //  STYLES
  // ═══════════════════════════════════════════════════════════
  (function injectStyles() {
    if (document.getElementById('lrmds-analytics-styles')) return;
    const s = document.createElement('style');
    s.id = 'lrmds-analytics-styles';
    s.textContent = `
      /* Bookmark button on file cards */
      .bm-btn {
        display: inline-flex; align-items: center; justify-content: center;
        border: none; background: transparent; cursor: pointer;
        font-size: 14px; padding: 2px 4px; border-radius: 4px;
        opacity: 0.4; transition: opacity .15s, transform .12s; flex-shrink: 0;
      }
      .bm-btn:hover  { opacity: 1; transform: scale(1.18); }
      .bm-btn--saved { opacity: 1; }

      /* Nav badge */
      .lib-badge {
        display: none; background: var(--primary, #1D4ED8); color: #fff;
        border-radius: 10px; font-size: 10px; font-weight: 700;
        padding: 1px 6px; margin-left: 4px; vertical-align: middle;
      }

      /* Slide-in panel */
      .library-panel {
        position: fixed; top: 0; right: 0;
        width: 400px; max-width: 100vw; height: 100vh;
        background: var(--surface, #fff);
        box-shadow: -4px 0 28px rgba(0,0,0,.14);
        z-index: 1100; display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform .25s cubic-bezier(.4,0,.2,1);
        border-left: 1px solid var(--border, #E5E7EB);
      }
      .library-panel.open { transform: translateX(0); }

      .lib-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 18px; border-bottom: 1px solid var(--border, #E5E7EB);
        flex-shrink: 0; background: var(--bg-soft, #F9FAFB);
      }
      .lib-panel-title { font-weight: 700; font-size: 15px; color: var(--text, #111827); }
      .lib-panel-close {
        border: none; background: none; cursor: pointer; font-size: 16px;
        color: var(--muted, #6B7280); padding: 2px 6px; border-radius: 4px;
        transition: background .12s;
      }
      .lib-panel-close:hover { background: var(--border, #E5E7EB); }

      .lib-panel-body {
        flex: 1; overflow-y: auto; padding: 12px;
        display: flex; flex-direction: column; gap: 10px;
      }

      .lib-empty {
        text-align: center; color: var(--muted, #6B7280);
        font-size: 13px; margin-top: 48px; line-height: 1.7;
      }

      /* Individual library item */
      .lib-item {
        border: 1px solid var(--border, #E5E7EB);
        border-radius: 10px; background: var(--bg-soft, #F9FAFB);
        padding: 12px; transition: background .12s, box-shadow .12s;
      }
      .lib-item:hover {
        background: var(--primary-lt, #EFF6FF);
        box-shadow: 0 2px 8px rgba(29,78,216,.07);
      }

      .lib-item-top {
        display: flex; gap: 10px; align-items: flex-start; margin-bottom: 10px;
      }
      .lib-item-icon { font-size: 22px; flex-shrink: 0; margin-top: 2px; }
      .lib-item-info { flex: 1; min-width: 0; }

      .lib-item-name {
        font-size: 12px; font-weight: 600; color: var(--text, #111827);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        margin-bottom: 5px;
      }
      .lib-item-meta { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
      .lib-tag {
        font-size: 10px; padding: 1px 7px; border-radius: 20px;
        background: var(--primary-lt, #EFF6FF); color: var(--primary, #1D4ED8);
        font-weight: 500;
      }
      .lib-tag-muted { font-size: 10px; color: var(--muted, #6B7280); padding: 1px 4px; }
      .lib-item-saved { font-size: 10px; color: var(--muted, #9CA3AF); }

      /* Action buttons row */
      .lib-item-actions {
        display: flex; flex-wrap: wrap; gap: 5px;
      }
      .lib-btn {
        display: inline-flex; align-items: center; gap: 4px;
        border: 1px solid var(--border, #E5E7EB);
        background: var(--surface, #fff); border-radius: 5px;
        cursor: pointer; font-size: 11px; font-weight: 500;
        padding: 5px 10px; color: var(--text, #111827);
        text-decoration: none; transition: background .12s, border-color .12s;
        white-space: nowrap; font-family: inherit;
      }
      .lib-btn:hover       { background: #F3F4F6; }

      .lib-preview-btn     { background: #EFF6FF; border-color: #BFDBFE; color: #1D4ED8; }
      .lib-preview-btn:hover { background: #DBEAFE; }

      .lib-dl-btn          { background: #ECFDF5; border-color: #6EE7B7; color: #065F46; }
      .lib-dl-btn:hover    { background: #D1FAE5; }

      .lib-open-btn        { color: var(--muted, #6B7280); }

      .lib-remove-btn      { background: #FFF5F5; border-color: #FECACA; color: #DC2626; margin-left: auto; }
      .lib-remove-btn:hover { background: #FEE2E2; }
    `;
    document.head.appendChild(s);
  })();

  // ── Utilities ────────────────────────────────────────────────
  function _escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }
  function _mimeIcon(mime) {
    if (!mime) return '📎';
    if (mime.includes('pdf'))          return '📄';
    if (mime.includes('video'))        return '🎬';
    if (mime.includes('word'))         return '📝';
    if (mime.includes('sheet'))        return '📊';
    if (mime.includes('presentation')) return '📑';
    if (mime.includes('image'))        return '🖼️';
    if (mime.includes('audio'))        return '🎵';
    return '📎';
  }

  window.LRMDS_Analytics = { fetchCounts, initLibraryTab, toggleBookmark, isBookmarked, loadLibrary };

})();