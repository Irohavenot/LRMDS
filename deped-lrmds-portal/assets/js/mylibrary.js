/* ============================================================
   DepEd Carcar City LRMDS – My Library Module
   mylibrary.js  (v1)
   ============================================================
   Responsibilities:
     • Persist bookmarks in localStorage.
     • Render the slide-in Library panel.
     • Multi-select: bulk download + bulk remove (with confirm).
     • Single-item remove confirmation before deleting.
     • Panel does NOT close on remove or download actions.
     • Fires bookmark_add / bookmark_remove events to tracker.php
       via window._track (provided by onedrive.js).
     • Improved bookmark SVG icon on file cards.
   ============================================================ */

(function () {
  'use strict';

  // ── Constants ────────────────────────────────────────────────
  const LIBRARY_KEY = 'lrmds_library_v1';

  // ── State ────────────────────────────────────────────────────
  let _libraryTabInjected = false;
  let _libraryPanelEl     = null;
  let _selectedIds        = new Set();   // currently checked item IDs

  // ── Storage helpers ──────────────────────────────────────────
  function loadLibrary() {
    try { return JSON.parse(localStorage.getItem(LIBRARY_KEY) || '{}'); }
    catch (e) { return {}; }
  }

  function saveLibrary(lib) {
    try { localStorage.setItem(LIBRARY_KEY, JSON.stringify(lib)); }
    catch (e) {}
  }

  function isBookmarked(itemId) { return !!loadLibrary()[itemId]; }

  // Reconstruct the shape onedrive.js expects from a stored entry
  function _entryToItem(entry) {
    return {
      id:             entry.id,
      name:           entry.name,
      webUrl:         entry.webUrl,
      size:           entry.size || 0,
      file:           { mimeType: entry.mimeType || '' },
      _meta:          entry.meta || {},
      // Restore folder path so _trackBookmark can send it even when removing from panel
      _folderPathStr: entry.folderPathStr || '',
    };
  }

  // ── Bookmark toggle ──────────────────────────────────────────
  function toggleBookmark(item) {
    const lib     = loadLibrary();
    const wasSaved = !!lib[item.id];

    if (wasSaved) {
      delete lib[item.id];
    } else {
      lib[item.id] = {
        id:            item.id,
        name:          item.name,
        webUrl:        item.webUrl,
        mimeType:      item.file?.mimeType || '',
        size:          item.size || 0,
        meta:          item._meta || {},
        // Persist the folder path so it survives across sessions and panel-remove
        folderPathStr: item._folderPathStr || (typeof window._folderPath === 'function' ? window._folderPath() : ''),
        savedAt:       Date.now(),
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
      // Priority order for folder_path:
      // 1. item._folderPathStr  — set by onedrive.js on every file from the deep-cache
      //    (reliable even when bookmarking from search results, where _folderPath()
      //    would return "deped › Search Results" instead of the real path)
      // 2. window._folderPath() — the current navigation path (correct when browsing folders)
      // 3. Empty string fallback
      const folderPath = item._folderPathStr
        || (typeof window._folderPath === 'function' ? window._folderPath() : '')
        || '';

      window._track(eventName, {
        item_id:     item.id,
        item_name:   item.name,
        item_type:   (item._meta && item._meta.type) || '',
        file_ext:    (item.name || '').split('.').pop().toLowerCase(),
        folder_path: folderPath,
      });
    }
  }

  // ═══════════════════════════════════════════════════════════
  //  PANEL — init, toggle, close, badge
  // ═══════════════════════════════════════════════════════════

  function initLibraryTab() {
    if (_libraryTabInjected) return;
    _libraryTabInjected = true;

    // Inject nav link with improved SVG icon
    const nav = document.querySelector('.header-nav');
    if (nav) {
      const link = document.createElement('a');
      link.className = 'nav-link lib-nav-link';
      link.href      = '#';
      link.id        = 'library-nav-link';
      link.innerHTML = `
        <span class="lib-nav-icon" aria-hidden="true">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
            <polyline points="17 21 17 13 7 13 7 21"/>
            <polyline points="7 3 7 8 15 8"/>
          </svg>
        </span>
        My Library
        <span class="lib-badge" id="lib-badge"></span>`;
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
        <div class="lib-panel-title-wrap">
          <span class="lib-panel-icon" aria-hidden="true">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
          </span>
          <span class="lib-panel-title">My Library</span>
        </div>
        <button class="lib-panel-close" id="lib-panel-close" title="Close panel">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2.5" stroke-linecap="round">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6"  y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <!-- Multi-select toolbar (hidden when nothing selected) -->
      <div class="lib-bulk-toolbar" id="lib-bulk-toolbar">
        <label class="lib-select-all-wrap">
          <input type="checkbox" id="lib-select-all" title="Select / deselect all">
          <span class="lib-sel-label" id="lib-sel-label">0 selected</span>
        </label>
        <div class="lib-bulk-actions">
          <button class="lib-bulk-btn lib-bulk-dl-btn"  id="lib-bulk-dl-btn"  disabled title="Download selected">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
          </button>
          <button class="lib-bulk-btn lib-bulk-rm-btn"  id="lib-bulk-rm-btn"  disabled title="Remove selected">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
              <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
            Remove
          </button>
        </div>
      </div>

      <div class="lib-panel-body" id="lib-panel-body"></div>`;

    document.body.appendChild(_libraryPanelEl);

    document.getElementById('lib-panel-close').addEventListener('click', closeLibraryPanel);

    // Select-all checkbox
    document.getElementById('lib-select-all').addEventListener('change', e => {
      const checked = e.target.checked;
      const lib     = loadLibrary();
      _selectedIds  = checked ? new Set(Object.keys(lib)) : new Set();
      _syncCheckboxes();
      _updateBulkToolbar();
    });

    // Bulk download
    document.getElementById('lib-bulk-dl-btn').addEventListener('click', () => {
      if (!_selectedIds.size) return;
      const lib   = loadLibrary();
      const names = [..._selectedIds].map(id => lib[id]?.name).filter(Boolean);
      const msg   = `Download ${_selectedIds.size} file${_selectedIds.size !== 1 ? 's' : ''}?\n\n${names.map(n => '• ' + n).join('\n')}`;
      if (!confirm(msg)) return;
      _selectedIds.forEach(id => {
        const entry = lib[id];
        if (!entry) return;
        const item = _entryToItem(entry);
        if (typeof window.downloadFile === 'function') {
          window.downloadFile(item.id, item.name);
        } else {
          window.open(entry.webUrl, '_blank', 'noopener');
        }
      });
    });

    // Bulk remove
    document.getElementById('lib-bulk-rm-btn').addEventListener('click', () => {
      if (!_selectedIds.size) return;
      const lib   = loadLibrary();
      const names = [..._selectedIds].map(id => lib[id]?.name).filter(Boolean);
      const msg   = `Remove ${_selectedIds.size} item${_selectedIds.size !== 1 ? 's' : ''} from My Library?\n\n${names.map(n => '• ' + n).join('\n')}\n\nThis cannot be undone.`;
      if (!confirm(msg)) return;
      _selectedIds.forEach(id => {
        const entry = lib[id];
        if (!entry) return;
        delete lib[id];
        _trackBookmark(_entryToItem(entry), 'bookmark_remove');
        updateBookmarkButtons(id, false);
      });
      saveLibrary(lib);
      _selectedIds.clear();
      refreshLibraryPanel();
      updateLibraryBadge();
    });

    // Close on outside click
    document.addEventListener('click', e => {
      if (_libraryPanelEl &&
          _libraryPanelEl.classList.contains('open') &&
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
    badge.textContent   = count > 0 ? String(count) : '';
    badge.style.display = count > 0 ? 'inline-flex' : 'none';
  }

  // ── Multi-select helpers ─────────────────────────────────────

  function _updateBulkToolbar() {
    const toolbar   = document.getElementById('lib-bulk-toolbar');
    const dlBtn     = document.getElementById('lib-bulk-dl-btn');
    const rmBtn     = document.getElementById('lib-bulk-rm-btn');
    const selLabel  = document.getElementById('lib-sel-label');
    const selectAll = document.getElementById('lib-select-all');
    if (!toolbar) return;

    const count    = _selectedIds.size;
    const libCount = Object.keys(loadLibrary()).length;

    selLabel.textContent = count === 0
      ? 'Select all'
      : `${count} selected`;

    dlBtn.disabled = count === 0;
    rmBtn.disabled = count === 0;

    if (selectAll) {
      selectAll.indeterminate = count > 0 && count < libCount;
      selectAll.checked       = libCount > 0 && count === libCount;
    }
  }

  function _syncCheckboxes() {
    if (!_libraryPanelEl) return;
    _libraryPanelEl.querySelectorAll('.lib-item-checkbox').forEach(cb => {
      cb.checked = _selectedIds.has(cb.dataset.id);
      cb.closest('.lib-item')?.classList.toggle('lib-item--selected', cb.checked);
    });
  }

  // ── Panel renderer ───────────────────────────────────────────
  function refreshLibraryPanel() {
    const body = document.getElementById('lib-panel-body');
    if (!body) return;

    const lib   = loadLibrary();
    const saved = Object.values(lib).sort((a, b) => b.savedAt - a.savedAt);

    // Clean up _selectedIds for items no longer in library
    for (const id of [..._selectedIds]) {
      if (!lib[id]) _selectedIds.delete(id);
    }

    if (!saved.length) {
      body.innerHTML = `
        <div class="lib-empty">
          <div class="lib-empty-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round"
                 style="opacity:.3">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
              <polyline points="17 21 17 13 7 13 7 21"/>
              <polyline points="7 3 7 8 15 8"/>
            </svg>
          </div>
          <p>No saved files yet.</p>
          <p class="lib-empty-hint">Click the
            <span class="lib-empty-bm-icon" aria-hidden="true">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" stroke-width="2"
                   stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                <polyline points="17 21 17 13 7 13 7 21"/>
                <polyline points="7 3 7 8 15 8"/>
              </svg>
            </span>
            button on any file card to save it here.</p>
        </div>`;
      _updateBulkToolbar();
      return;
    }

    body.innerHTML = '';

    saved.forEach(entry => {
      const item = _entryToItem(entry);

      const ext  = (entry.name.split('.').pop() || '').toUpperCase();
      const size = entry.size > 1048576
        ? (entry.size / 1048576).toFixed(1) + ' MB'
        : entry.size > 0 ? Math.round(entry.size / 1024) + ' KB' : '';
      const meta      = entry.meta || {};
      const savedDate = new Date(entry.savedAt).toLocaleDateString('en-PH', {
        month: 'short', day: 'numeric', year: 'numeric'
      });

      const canPreview = typeof window.getPreviewType === 'function'
        ? !!window.getPreviewType(item)
        : false;

      const isSelected = _selectedIds.has(entry.id);

      const el = document.createElement('div');
      el.className      = 'lib-item' + (isSelected ? ' lib-item--selected' : '');
      el.dataset.itemId = entry.id;

      el.innerHTML = `
        <div class="lib-item-top">
          <label class="lib-item-check-wrap" title="Select for bulk action">
            <input type="checkbox" class="lib-item-checkbox" data-id="${_escHtml(entry.id)}"
                   ${isSelected ? 'checked' : ''}>
          </label>
          <span class="lib-item-icon">${_mimeIcon(entry.mimeType)}</span>
          <div class="lib-item-info">
            <div class="lib-item-name" title="${_escHtml(entry.name)}">${_escHtml(entry.name)}</div>
            <div class="lib-item-meta">
              ${meta.grade   ? `<span class="lib-tag">${_escHtml(meta.grade)}</span>`   : ''}
              ${meta.subject ? `<span class="lib-tag">${_escHtml(meta.subject)}</span>` : ''}
              ${ext          ? `<span class="lib-tag lib-tag-ext">${_escHtml(ext)}</span>` : ''}
              ${size         ? `<span class="lib-tag-muted">${_escHtml(size)}</span>`   : ''}
            </div>
            <div class="lib-item-saved">Saved ${_escHtml(savedDate)}</div>
          </div>
        </div>
        <div class="lib-item-actions">
          ${canPreview
            ? `<button class="lib-btn lib-preview-btn" title="Preview this file">
                 <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                      stroke-width="2.5" stroke-linecap="round">
                   <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                   <circle cx="12" cy="12" r="3"/>
                 </svg>
                 Preview
               </button>`
            : ''}
          <button class="lib-btn lib-dl-btn" title="Download this file">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round">
              <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
              <polyline points="7 10 12 15 17 10"/>
              <line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Download
          </button>
          <a href="${_escHtml(entry.webUrl)}" target="_blank" rel="noopener"
             class="lib-btn lib-open-btn" title="Open in OneDrive">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round">
              <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
              <polyline points="15 3 21 3 21 9"/>
              <line x1="10" y1="14" x2="21" y2="3"/>
            </svg>
            Open
          </a>
          <button class="lib-btn lib-remove-btn" data-id="${_escHtml(entry.id)}"
                  title="Remove from My Library">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.5" stroke-linecap="round">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
              <path d="M10 11v6"/><path d="M14 11v6"/>
            </svg>
            Remove
          </button>
        </div>`;

      // Checkbox change
      el.querySelector('.lib-item-checkbox').addEventListener('change', e => {
        if (e.target.checked) {
          _selectedIds.add(entry.id);
        } else {
          _selectedIds.delete(entry.id);
        }
        el.classList.toggle('lib-item--selected', e.target.checked);
        _updateBulkToolbar();
        // Sync select-all state
        const all = document.getElementById('lib-select-all');
        if (all) {
          const libCount = Object.keys(loadLibrary()).length;
          all.indeterminate = _selectedIds.size > 0 && _selectedIds.size < libCount;
          all.checked       = libCount > 0 && _selectedIds.size === libCount;
        }
      });

      // Preview — closes panel then opens preview modal
      if (canPreview) {
        el.querySelector('.lib-preview-btn').addEventListener('click', () => {
          closeLibraryPanel();
          if (typeof window.openPreview === 'function') window.openPreview(item);
        });
      }

      // Download — does NOT close panel; asks nothing for single
      el.querySelector('.lib-dl-btn').addEventListener('click', e => {
        e.stopPropagation();
        if (typeof window.downloadFile === 'function') {
          window.downloadFile(item.id, item.name);
        } else {
          window.open(entry.webUrl, '_blank', 'noopener');
        }
      });

      // Remove — asks confirmation; does NOT close panel
      el.querySelector('.lib-remove-btn').addEventListener('click', e => {
        e.stopPropagation();
        if (!confirm(`Remove "${entry.name}" from My Library?`)) return;
        const current = loadLibrary();
        if (!current[entry.id]) return;
        delete current[entry.id];
        saveLibrary(current);
        _trackBookmark(item, 'bookmark_remove');
        updateBookmarkButtons(entry.id, false);
        _selectedIds.delete(entry.id);
        refreshLibraryPanel();   // re-render in place — panel stays open
        updateLibraryBadge();
      });

      body.appendChild(el);
    });

    _updateBulkToolbar();
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

  // Improved SVG — matches resources.php style (file/save icon)
  const _BM_SVG_EMPTY = `
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
      <polyline points="17 21 17 13 7 13 7 21"/>
      <polyline points="7 3 7 8 15 8"/>
    </svg>`;

  const _BM_SVG_FILLED = `
    <svg width="14" height="14" viewBox="0 0 24 24"
         stroke="currentColor" stroke-width="2"
         stroke-linecap="round" stroke-linejoin="round">
      <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"
            fill="currentColor"/>
      <polyline points="17 21 17 13 7 13 7 21" stroke="white" fill="none"/>
      <polyline points="7 3 7 8 15 8" stroke="white" fill="none"/>
    </svg>`;

  function _syncBmButton(btn, bookmarked) {
    btn.innerHTML = bookmarked ? _BM_SVG_FILLED : _BM_SVG_EMPTY;
    btn.classList.toggle('bm-btn--saved', bookmarked);
    btn.title = bookmarked ? 'Remove from My Library' : 'Save to My Library';
  }

  function updateBookmarkButtons(itemId, bookmarked) {
    document.querySelectorAll(`.bm-btn[data-item-id="${CSS.escape(itemId)}"]`)
      .forEach(btn => _syncBmButton(btn, bookmarked));
  }

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

  const _itemRegistry = new Map();

  wrapWhenReady('buildFileCard', original => function (item, isSearch) {
    _itemRegistry.set(item.id, item);
    return original.call(this, item, isSearch);
  });

  wrapWhenReady('renderItems', original => function (items, titleText, isSearch) {
    const result = original.call(this, items, titleText, isSearch);
    setTimeout(() => {
      const grid = document.getElementById('results');
      if (!grid) return;
      grid.querySelectorAll('.result-card[data-item-id]').forEach(card => {
        const itemId = card.dataset.itemId;
        if (!itemId) return;
        const item = _itemRegistry.get(itemId);
        if (item) attachBookmarkButton(card, item);
      });
    }, 0);
    return result;
  });

  wrapWhenReady('showApp', original => function () {
    const result = original.call(this);
    initLibraryTab();
    return result;
  });

  // ═══════════════════════════════════════════════════════════
  //  STYLES
  // ═══════════════════════════════════════════════════════════
  (function injectStyles() {
    if (document.getElementById('lrmds-library-styles')) return;
    const s = document.createElement('style');
    s.id = 'lrmds-library-styles';
    s.textContent = `
      /* ── Nav link ──────────────────────────────────────────── */
      .lib-nav-link {
        display: inline-flex; align-items: center; gap: 5px;
      }
      .lib-nav-icon {
        display: inline-flex; align-items: center;
        color: var(--primary, #0B4F9C);
      }

      /* ── Bookmark button on cards ──────────────────────────── */
      .bm-btn {
        display: inline-flex; align-items: center; justify-content: center;
        border: 1px solid transparent; background: transparent; cursor: pointer;
        width: 28px; height: 28px; border-radius: 6px;
        color: var(--muted, #9CA3AF);
        opacity: 0.55; transition: opacity .15s, color .15s, background .15s,
                       border-color .15s, transform .12s;
        flex-shrink: 0;
      }
      .bm-btn:hover {
        opacity: 1; color: var(--primary, #0B4F9C);
        background: var(--primary-lt, #E8F0FB);
        border-color: var(--primary-md, #B3CFEE);
        transform: scale(1.1);
      }
      .bm-btn--saved {
        opacity: 1; color: var(--primary, #0B4F9C);
      }

      /* ── Nav badge ─────────────────────────────────────────── */
      .lib-badge {
        display: none; background: var(--yellow, #F5C400); color: #0B4F9C;
        border-radius: 10px; font-size: 10px; font-weight: 800;
        padding: 1px 6px; margin-left: 2px; vertical-align: middle;
      }

      /* ── Slide-in panel ────────────────────────────────────── */
      .library-panel {
        position: fixed; top: 0; right: 0;
        width: 420px; max-width: 100vw; height: 100vh;
        background: var(--surface, #fff);
        box-shadow: -6px 0 32px rgba(0,0,0,.15);
        z-index: 1100; display: flex; flex-direction: column;
        transform: translateX(100%);
        transition: transform .25s cubic-bezier(.4,0,.2,1);
        border-left: 1px solid var(--border, #E5E7EB);
      }
      .library-panel.open { transform: translateX(0); }

      /* ── Panel header ──────────────────────────────────────── */
      .lib-panel-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 15px 18px; border-bottom: 1px solid var(--border, #E5E7EB);
        flex-shrink: 0;
        background: linear-gradient(135deg, #0B4F9C 0%, #1565C0 100%);
      }
      .lib-panel-title-wrap {
        display: flex; align-items: center; gap: 8px;
      }
      .lib-panel-icon { display: inline-flex; color: rgba(255,255,255,0.85); }
      .lib-panel-title {
        font-weight: 700; font-size: 15px; color: #fff; letter-spacing: .01em;
      }
      .lib-panel-close {
        border: none; background: rgba(255,255,255,.15); cursor: pointer;
        color: #fff; padding: 5px; border-radius: 6px;
        display: inline-flex; align-items: center;
        transition: background .12s;
      }
      .lib-panel-close:hover { background: rgba(255,255,255,.3); }

      /* ── Bulk toolbar ──────────────────────────────────────── */
      .lib-bulk-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: 8px 14px;
        background: var(--bg-soft, #F9FAFB);
        border-bottom: 1px solid var(--border, #E5E7EB);
        flex-shrink: 0;
        gap: 8px;
      }
      .lib-select-all-wrap {
        display: flex; align-items: center; gap: 6px; cursor: pointer;
        font-size: 12px; color: var(--text, #374151); font-weight: 500;
        user-select: none;
      }
      .lib-select-all-wrap input[type="checkbox"] {
        width: 15px; height: 15px; cursor: pointer; accent-color: var(--primary, #0B4F9C);
      }
      .lib-sel-label { white-space: nowrap; }
      .lib-bulk-actions { display: flex; gap: 6px; }
      .lib-bulk-btn {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 11px; font-weight: 600; padding: 4px 10px;
        border-radius: 5px; cursor: pointer; border: 1px solid;
        transition: background .12s, opacity .12s; font-family: inherit;
        white-space: nowrap;
      }
      .lib-bulk-btn:disabled { opacity: .4; cursor: not-allowed; }
      .lib-bulk-dl-btn {
        background: #ECFDF5; border-color: #6EE7B7; color: #065F46;
      }
      .lib-bulk-dl-btn:not(:disabled):hover { background: #D1FAE5; }
      .lib-bulk-rm-btn {
        background: #FFF5F5; border-color: #FECACA; color: #DC2626;
      }
      .lib-bulk-rm-btn:not(:disabled):hover { background: #FEE2E2; }

      /* ── Panel body ────────────────────────────────────────── */
      .lib-panel-body {
        flex: 1; overflow-y: auto; padding: 12px;
        display: flex; flex-direction: column; gap: 8px;
      }

      /* ── Empty state ───────────────────────────────────────── */
      .lib-empty {
        text-align: center; color: var(--muted, #6B7280);
        font-size: 13px; margin-top: 40px; line-height: 1.8;
        padding: 0 16px;
      }
      .lib-empty-icon { margin-bottom: 12px; }
      .lib-empty p { margin: 0 0 4px; }
      .lib-empty-hint { font-size: 12px; color: var(--muted, #9CA3AF); }
      .lib-empty-bm-icon {
        display: inline-flex; vertical-align: middle;
        color: var(--primary, #0B4F9C); margin: 0 2px;
      }

      /* ── Library item card ─────────────────────────────────── */
      .lib-item {
        border: 1.5px solid var(--border, #E5E7EB);
        border-radius: 10px; background: var(--bg-soft, #F9FAFB);
        padding: 11px 12px; transition: background .12s, box-shadow .12s, border-color .12s;
      }
      .lib-item:hover {
        background: var(--primary-lt, #EFF6FF);
        box-shadow: 0 2px 8px rgba(29,78,216,.07);
        border-color: #BFDBFE;
      }
      .lib-item--selected {
        background: #EFF6FF !important;
        border-color: var(--primary, #0B4F9C) !important;
        box-shadow: 0 0 0 2px rgba(11,79,156,.12) !important;
      }

      .lib-item-top {
        display: flex; gap: 8px; align-items: flex-start; margin-bottom: 9px;
      }
      .lib-item-check-wrap {
        display: flex; align-items: flex-start; padding-top: 3px; cursor: pointer; flex-shrink: 0;
      }
      .lib-item-check-wrap input[type="checkbox"] {
        width: 14px; height: 14px; cursor: pointer; accent-color: var(--primary, #0B4F9C);
      }
      .lib-item-icon { font-size: 22px; flex-shrink: 0; margin-top: 1px; }
      .lib-item-info { flex: 1; min-width: 0; }

      .lib-item-name {
        font-size: 12px; font-weight: 600; color: var(--text, #111827);
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        margin-bottom: 5px;
      }
      .lib-item-meta { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 4px; }
      .lib-tag {
        font-size: 10px; padding: 2px 7px; border-radius: 20px;
        background: var(--primary-lt, #EFF6FF); color: var(--primary, #1D4ED8);
        font-weight: 500; border: 1px solid #BFDBFE;
      }
      .lib-tag-ext {
        background: #F3F4F6; color: var(--muted, #6B7280);
        border-color: var(--border, #E5E7EB);
      }
      .lib-tag-muted { font-size: 10px; color: var(--muted, #6B7280); padding: 2px 4px; }
      .lib-item-saved { font-size: 10px; color: var(--muted, #9CA3AF); }

      /* ── Action buttons ────────────────────────────────────── */
      .lib-item-actions {
        display: flex; flex-wrap: wrap; gap: 5px;
      }
      .lib-btn {
        display: inline-flex; align-items: center; gap: 4px;
        border: 1px solid var(--border, #E5E7EB);
        background: var(--surface, #fff); border-radius: 5px;
        cursor: pointer; font-size: 11px; font-weight: 500;
        padding: 4px 9px; color: var(--text, #111827);
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

  // ── Utilities ─────────────────────────────────────────────────
  function _escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
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

  // ── Public API ────────────────────────────────────────────────
  window.LRMDS_Library = {
    initLibraryTab,
    toggleBookmark,
    isBookmarked,
    loadLibrary,
    updateLibraryBadge,
    refreshLibraryPanel,
    updateBookmarkButtons,
    attachBookmarkButton,
  };

})();