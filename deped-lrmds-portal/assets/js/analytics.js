/* ============================================================
   DepEd Carcar City LRMDS – Analytics Badge Module
   analytics.js  (v4)
   ============================================================
   Responsibilities:
     1. Attach 👁/⬇ count badges to file cards.

   My Library is now handled by mylibrary.js.
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
  // My Library key kept here only for backwards-compat reading by mylibrary.js
  // (analytics.js no longer manages library — see mylibrary.js)

  // ── In-memory counts cache ───────────────────────────────────
  const _countsCache = new Map();

  // ── Pending badge items ──────────────────────────────────────
  const _pendingBadge = new Map();
  let   _badgeFetchTimer = null;

  // ═══════════════════════════════════════════════════════════
  //  LIBRARY — delegated to mylibrary.js
  //  analytics.js no longer manages bookmarks, panel, or badge.
  // ═══════════════════════════════════════════════════════════

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
  //  HOOKS: renderItems (for badge attachment)
  // ═══════════════════════════════════════════════════════════

  function wrapWhenReady(fnName, wrapper, retries = 40) {
    if (typeof window[fnName] === 'function') {
      window[fnName] = wrapper(window[fnName]);
    } else if (retries > 0) {
      setTimeout(() => wrapWhenReady(fnName, wrapper, retries - 1), 100);
    }
  }

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
      });
    }, 0);
    return result;
  });
  // showApp is wrapped by mylibrary.js to inject the library tab

  // ═══════════════════════════════════════════════════════════
  //  STYLES  (counts badge only — library styles in mylibrary.js)
  // ═══════════════════════════════════════════════════════════
  (function injectStyles() {
    if (document.getElementById('lrmds-analytics-styles')) return;
    const s = document.createElement('style');
    s.id = 'lrmds-analytics-styles';
    s.textContent = `
      /* Analytics count badge on cards */
      .analytics-badge {
        display: inline-flex; align-items: center;
        font-size: 11px; color: #9CA3AF;
        margin-left: auto; opacity: 0; transition: opacity .3s;
      }
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

  window.LRMDS_Analytics = { fetchCounts };
  // Library API is exposed by mylibrary.js as window.LRMDS_Library

})();