/**
 * header_mobile.js
 * Handles:
 *   - Hamburger ↔ drawer open / close
 *   - Bottom mobile nav active state
 *   - Overlay click to close
 *   - Escape key to close
 *   - Body scroll lock while drawer is open
 *   - Mobile Sign In / Sign Out button hooks
 *     (delegates to existing header.js modal functions)
 */
(function () {
  'use strict';

  /* ── Elements ── */
  const toggle   = document.getElementById('nav-toggle');
  const drawer   = document.getElementById('nav-drawer');
  const overlay  = document.getElementById('nav-overlay');

  if (!toggle || !drawer) return;   // not on a page with the mobile header

  /* ── Open / close ── */
  function openDrawer() {
    drawer.classList.add('open');
    overlay.classList.add('open');
    toggle.setAttribute('aria-expanded', 'true');
    toggle.setAttribute('aria-label', 'Close navigation menu');
    drawer.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Focus first interactive element inside drawer
    const first = drawer.querySelector('input, a, button');
    first && first.focus();
  }

  function closeDrawer() {
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open navigation menu');
    drawer.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    toggle.focus();
  }

  function isDrawerOpen() {
    return drawer.classList.contains('open');
  }

  /* ── Hamburger button click ── */
  toggle.addEventListener('click', function () {
    isDrawerOpen() ? closeDrawer() : openDrawer();
  });

  /* ── Overlay click ── */
  overlay.addEventListener('click', closeDrawer);

  /* ── Escape key ── */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isDrawerOpen()) closeDrawer();
  });

  /* ── Close drawer when a drawer link is clicked ── */
  drawer.querySelectorAll('a').forEach(function (link) {
    link.addEventListener('click', function () {
      // Small delay lets the sign-in modal open before the drawer closes
      setTimeout(closeDrawer, 80);
    });
  });


  /* ── Mobile bottom nav: active state ── */
  const path = location.pathname.split('/').pop() || 'index.php';
  document.querySelectorAll('.mob-nav-item').forEach(function (item) {
    const href = item.getAttribute('href');
    if (href && href !== '#' && href.includes(path)) {
      item.classList.add('active');
    }
  });

  // Home edge case: mark Home active on root / index.php
  if (!path || path === 'index.php' || path === 'index.html') {
    const homeLink = document.querySelector('.mob-nav-item[href="index.php"]');
    if (homeLink) homeLink.classList.add('active');
  }


  /* ── Mobile bottom-bar Sign In / Sign Out ── */
  // These delegate to the modals already wired in header.js

  // Sign In (bottom bar)
  document.getElementById('mob-signin-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    // Trigger the header sign-in button so header.js handles the modal
    document.getElementById('hdr-signin-btn')?.click();
  });

  // Sign Out (bottom bar) — opens the sign-out confirmation modal
  document.getElementById('mob-signout-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    document.getElementById('hdr-signout-btn')?.click();
  });


  /* ── Drawer search: submit to search page ── */
  const drawerSearch = drawer.querySelector('input[type="search"]');
  drawerSearch?.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && drawerSearch.value.trim()) {
      closeDrawer();
      location.href = 'search.php?q=' + encodeURIComponent(drawerSearch.value.trim());
    }
  });

})();