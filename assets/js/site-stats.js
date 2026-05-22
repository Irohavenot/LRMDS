/**
 * DepEd LRMDS – site-stats.js
 *
 * Sends a heartbeat to site_stats.php, then immediately fetches stats.
 * This order ensures the current visitor is counted even on first load.
 *
 * Works on both index.php and any other page that includes this script.
 * Updates elements with [data-stat] attributes:
 *   data-stat="online"        → currently online
 *   data-stat="logins-today"  → logins today
 *   data-stat="total-visits"  → total visits
 */
(function () {
  'use strict';

  const API = '/deped-lrmds-portal/site_stats.php';
  const HEARTBEAT_MS     = 30_000;   // 30 seconds
  const STATS_REFRESH_MS = 60_000;   // 60 seconds

  /* ── Heartbeat ─────────────────────────────────────────────
     Sends presence ping. Returns a Promise so we can chain
     fetchStats() after it on the very first load.           */
  function sendHeartbeat() {
    const fd = new FormData();
    fd.append('action', 'heartbeat');
    return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => {
        if (d.ok) console.debug('[LRMDS stats] heartbeat OK, sid:', d.sid);
        return d;
      })
      .catch(e => console.warn('[LRMDS stats] heartbeat failed:', e));
  }

  /* ── Fetch and display stats ───────────────────────────────*/
  function fetchStats() {
    return fetch(API + '?action=get_stats&_=' + Date.now(), { credentials: 'same-origin' })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(data => {
        if (!data.ok) {
          console.warn('[LRMDS stats] server error:', data.msg);
          setAll('Err');
          return;
        }
        setStat('online',        data.online);
        setStat('logins-today',  data.logins_today);
        setStat('total-visits',  data.total_visits);
      })
      .catch(e => {
        console.warn('[LRMDS stats] fetch failed:', e);
        setAll('—');
      });
  }

  /* ── Helpers ───────────────────────────────────────────────*/
  function setStat(key, value) {
    document.querySelectorAll('[data-stat="' + key + '"]').forEach(el => {
      const prev = parseInt(el.textContent.replace(/,/g, ''), 10);
      if (isNaN(prev) || prev === value) {
        el.textContent = Number(value).toLocaleString();
      } else {
        animateCount(el, prev, value);
      }
    });
  }

  function setAll(text) {
    ['online', 'logins-today', 'total-visits'].forEach(key => {
      document.querySelectorAll('[data-stat="' + key + '"]').forEach(el => {
        el.textContent = text;
      });
    });
  }

  function animateCount(el, from, to) {
    const steps    = 20;
    const duration = 600;
    const step     = (to - from) / steps;
    let   count    = 0;
    let   current  = from;
    const iv = setInterval(() => {
      count++;
      current += step;
      el.textContent = Math.round(count >= steps ? to : current).toLocaleString();
      if (count >= steps) clearInterval(iv);
    }, duration / steps);
  }

  /* ── Boot ──────────────────────────────────────────────────
     Send heartbeat FIRST so this visitor is counted, THEN
     fetch stats — that way the widget never shows 0 on a
     cold load when you are the only person online.          */
  document.addEventListener('DOMContentLoaded', function () {
    // Chain: heartbeat → fetchStats so the count includes the current user
    sendHeartbeat().then(fetchStats);

    // Periodic refresh (independent after first load)
    setInterval(sendHeartbeat, HEARTBEAT_MS);
    setInterval(fetchStats,    STATS_REFRESH_MS);
  });

})();