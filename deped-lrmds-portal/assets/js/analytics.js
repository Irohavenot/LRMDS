/* ============================================================
   DepEd Carcar City LRMDS – Analytics Module  (analytics.js)
   ============================================================
   Tracks user activity for OneDrive files without touching
   prototype2.js.  Hooks into the existing global functions
   by wrapping them after the page loads.

   Events sent to tracker.php:
     page_view     – on load (session start)
     file_view     – when a preview is opened
     file_download – when a download is triggered
     folder_open   – when a folder is navigated into
     search        – when search / filters are applied
     session_end   – on page unload (with session duration)

   Counts returned by tracker.php are rendered as small
   "👁 N  ⬇ N" badges on each file card.
   ============================================================ */

(function () {
  'use strict';

  // ── Config ──────────────────────────────────────────────────
  // Resolve tracker.php relative to the *page* URL (not this JS file).
  // analytics.js lives in assets/js/ but tracker.php is next to index.php
  // in the onedrive/ folder, so we derive the base from the page's own URL.
  const TRACKER_URL = (function () {
    const loc = window.location.pathname;           // e.g. /onedrive/index.php
    const dir = loc.substring(0, loc.lastIndexOf('/') + 1); // /onedrive/
    return dir + 'tracker.php';                     // /onedrive/tracker.php
  })();

  // How long to wait (ms) before fetching counts for visible cards
  // after a folder renders (avoids hammering the server).
  const COUNT_FETCH_DELAY = 800;

  // ── Session ──────────────────────────────────────────────────
  // Generate a random session ID once per page load.
  const SESSION_ID = 'lrmds-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
  const SESSION_START = Date.now();

  // ── User info (filled once MSAL resolves) ───────────────────
  let _userOid  = null;
  let _userName = null;

  // ── In-memory counts cache to avoid re-fetching ─────────────
  const _countsCache = new Map();

  // ── Pending items waiting for count badges ───────────────────
  // Maps item_id → { card DOM element, fetched: bool }
  const _pendingBadge = new Map();
  let   _badgeFetchTimer = null;

  // ═══════════════════════════════════════════════════════════
  //  CORE: send an event to tracker.php (fire-and-forget)
  // ═══════════════════════════════════════════════════════════
  function track(eventName, payload = {}) {
    const body = {
      event:      eventName,
      session_id: SESSION_ID,
      user_oid:   _userOid,
      user_name:  _userName,
      ...payload,
    };

    // Use sendBeacon for session_end (works even during page unload)
    if (eventName === 'session_end' && navigator.sendBeacon) {
      navigator.sendBeacon(TRACKER_URL, JSON.stringify(body));
      return;
    }

    fetch(TRACKER_URL, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(body),
      // keepalive allows the request to outlive the page
      keepalive: true,
    })
    .then(r => r.ok ? r.json() : null)
    .then(data => {
      // If tracker returned updated counts, cache and update the badge
      if (data?.counts && payload.item_id) {
        _countsCache.set(payload.item_id, data.counts);
        refreshBadgeForItem(payload.item_id, data.counts);
      }
    })
    .catch(() => {}); // Tracking is non-critical; silent fail is fine
  }

  // ═══════════════════════════════════════════════════════════
  //  COUNTS: fetch + render view/download badges on file cards
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

  // Inject a badge placeholder into a file card, then queue the fetch
  function attachBadgeToCard(card, itemId) {
    if (card.querySelector('.analytics-badge')) return; // already attached

    const badge = document.createElement('span');
    badge.className = 'analytics-badge';
    badge.title     = 'Views · Downloads';
    badge.style.cssText = `
      display: inline-flex;
      align-items: center;
      font-size: 11px;
      color: #9CA3AF;
      margin-left: auto;
      opacity: 0;
      transition: opacity .3s;
    `;
    badge.innerHTML = '👁 … ⬇ …';

    // Try to insert at the end of .card-actions, or card-detail, or card itself
    const actions = card.querySelector('.card-actions') || card.querySelector('.card-detail') || card;
    actions.appendChild(badge);

    _pendingBadge.set(itemId, { card, fetched: false });
    scheduleBadgeFetch();
  }

  function scheduleBadgeFetch() {
    clearTimeout(_badgeFetchTimer);
    _badgeFetchTimer = setTimeout(() => {
      for (const [itemId, info] of _pendingBadge.entries()) {
        if (!info.fetched) {
          info.fetched = true;
          fetchCounts(itemId);
        }
      }
    }, COUNT_FETCH_DELAY);
  }

  // ═══════════════════════════════════════════════════════════
  //  HOOKING into prototype2.js globals
  //  We wrap the existing functions rather than editing them,
  //  so prototype2.js stays untouched.
  // ═══════════════════════════════════════════════════════════

  // Helper: wait for a global function to exist, then wrap it.
  function wrapWhenReady(fnName, wrapper, retries = 40) {
    if (typeof window[fnName] === 'function') {
      const original = window[fnName];
      window[fnName] = wrapper(original);
    } else if (retries > 0) {
      setTimeout(() => wrapWhenReady(fnName, wrapper, retries - 1), 100);
    }
  }

  // ── 1. showApp → capture user identity ─────────────────────
  wrapWhenReady('showApp', function (original) {
    return function (...args) {
      const result = original.apply(this, args);
      // currentUser is set by prototype2.js before showApp returns
      try {
        const u = window.currentUser;
        if (u) {
          _userOid  = u.localAccountId || u.homeAccountId || u.username;
          _userName = u.name           || u.username;
        }
      } catch (_) {}
      track('page_view');
      return result;
    };
  });

  // ── 2. downloadFile → track download ───────────────────────
  wrapWhenReady('downloadFile', function (original) {
    return async function (itemId, fileName) {
      // Get meta from the deep cache if available
      const meta  = getMetaFromCache(itemId);
      const fPath = getCurrentFolderPath();
      track('file_download', {
        item_id:     itemId,
        item_name:   fileName,
        item_type:   meta?.type   || null,
        folder_path: fPath,
      });
      return original.call(this, itemId, fileName);
    };
  });

  // ── 3. openPreview → track file view ───────────────────────
  wrapWhenReady('openPreview', function (original) {
    return async function (item) {
      const fPath = getCurrentFolderPath();
      track('file_view', {
        item_id:     item.id,
        item_name:   item.name,
        item_type:   item._meta?.type || null,
        folder_path: fPath,
      });
      return original.call(this, item);
    };
  });

  // ── 4. navigateTo → track folder navigation ─────────────────
  wrapWhenReady('navigateTo', function (original) {
    return function (itemId, folderName) {
      track('folder_open', {
        item_id:     itemId,
        item_name:   folderName,
        folder_path: getCurrentFolderPath(),
      });
      return original.call(this, itemId, folderName);
    };
  });

  // ── 5. applySearch → track search + filter actions ──────────
  wrapWhenReady('applySearch', function (original) {
    return async function (...args) {
      // Read values before original() resets them
      const q       = (document.getElementById('search-input')?.value || '').trim();
      const grade   = document.getElementById('filter-grade')?.value   || '';
      const subject = document.getElementById('filter-subject')?.value || '';
      const type    = document.getElementById('filter-type')?.value    || '';

      if (q || grade || subject || type) {
        track('search', {
          search_query: q,
          filters: { grade, subject, type },
        });
      }
      return original.apply(this, args);
    };
  });

  // ── 6. renderItems → attach badges after render ─────────────
  wrapWhenReady('renderItems', function (original) {
    return function (items, titleText, isSearch) {
      const result = original.call(this, items, titleText, isSearch);
      // After DOM is updated, find all file cards and attach badges
      _pendingBadge.clear();
      setTimeout(() => {
        const grid = document.getElementById('results');
        if (!grid) return;
        // file cards have data items; match by button onclick containing itemId
        const files = (items || []).filter(i => i.file);
        files.forEach(item => {
          // Find this item's card by searching for its download button
          const cards = grid.querySelectorAll('.result-card');
          cards.forEach(card => {
            const dlBtn = card.querySelector(`[onclick*="${item.id}"]`);
            if (dlBtn) attachBadgeToCard(card, item.id);
          });
        });
      }, 0);
      return result;
    };
  });

  // ═══════════════════════════════════════════════════════════
  //  SESSION END
  // ═══════════════════════════════════════════════════════════
  function sendSessionEnd() {
    const duration = Math.round((Date.now() - SESSION_START) / 1000);
    track('session_end', { duration_sec: duration });
  }

  window.addEventListener('pagehide', sendSessionEnd);
  // Fallback for browsers that don't support pagehide reliably
  window.addEventListener('beforeunload', sendSessionEnd);

  // ═══════════════════════════════════════════════════════════
  //  HELPERS
  // ═══════════════════════════════════════════════════════════

  // Try to read metadata from prototype2's deep cache
  function getMetaFromCache(itemId) {
    try {
      const cache = window.allItemsCache;
      if (!Array.isArray(cache)) return null;
      const item = cache.find(i => i.id === itemId);
      return item?._meta || null;
    } catch (_) { return null; }
  }

  // Build a breadcrumb string from prototype2's folderHistory
  function getCurrentFolderPath() {
    try {
      const history = window.folderHistory || [];
      const current = document.getElementById('results-title')?.textContent || '';
      const parts   = history.map(h => h.name).concat(current).filter(Boolean);
      return parts.join(' › ');
    } catch (_) { return ''; }
  }

  // ── Expose a small public API in case you want to call track() manually ────
  window.LRMDS_Analytics = {
    track,
    fetchCounts,
    sessionId: SESSION_ID,
  };

})();