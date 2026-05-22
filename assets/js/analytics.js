/* ============================================================
   DepEd Carcar City LRMDS – Analytics Badge Module
   analytics.js  (v4.1)
   ============================================================
   Responsibilities:
     1. Attach 👁/⬇ count badges to file cards.
     2. Fire the 'search' tracking event AFTER renderItems paints
        results — fixes the Search Success Rate always reading 0.

   My Library is now handled by mylibrary.js.
   All other event tracking (page_view, file_view, file_download,
   folder_open, search, session_end) is handled by onedrive.js

   v4.1 fix:
     The old applySearch wrapper in onedrive.js read the DOM for
     result cards immediately after calling _origApplySearch(),
     but that call is async (Graph API fetch) so the cards were
     never in the DOM yet — result_count was always 0.

     Fix: onedrive.js now stores the pending search context in
     window._pendingSearchMeta instead of firing _track() itself.
     This renderItems hook reads that context once results are
     actually painted and fires _track('search', …) with the
     correct result count.

     IMPORTANT: also remove (or comment out) the _track('search',…)
     call inside the applySearch wrapper in onedrive.js, otherwise
     you will get duplicate search events. Replace that block with
     the _pendingSearchMeta assignment shown in the comment below.
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

  // ── In-memory counts cache ───────────────────────────────────
  const _countsCache = new Map();

  // ── Pending badge items ──────────────────────────────────────
  const _pendingBadge = new Map();
  let   _badgeFetchTimer = null;

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
  //  HOOKS: renderItems
  //  — attaches 👁/⬇ badges to every file card
  //  — fires the 'search' tracking event with the REAL result
  //    count, after the DOM has been updated (fixes the bug
  //    where result_count was always 0 in the dashboard)
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

    // ── 1. Badge attachment (unchanged) ──────────────────────
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

    // ── 2. Search tracking — fires only for search renders ───
    //
    // onedrive.js applySearch wrapper must be updated to STOP
    // calling _track('search', …) directly and instead just set:
    //
    //   window._pendingSearchMeta = { q, g, s, t };
    //
    // (and remove the _track call + resultCount DOM read from there)
    //
    // This hook then picks up that context here, where items.length
    // is the true result count available synchronously.
    if (isSearch) {
      const meta = window._pendingSearchMeta;
      if (meta) {
        window._pendingSearchMeta = null; // consume — prevent double-fire
        const resultCount = Array.isArray(items) ? items.length : 0;
        if (typeof window._track === 'function') {
          window._track('search', {
            search_query: meta.q,
            filters:      { grade: meta.g, subject: meta.s, type: meta.t },
            result_count: resultCount,
            has_results:  resultCount > 0 ? 1 : 0,
          });
        }
      }
    }

    return result;
  });

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