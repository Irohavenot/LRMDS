/**
 * profile_panel.js
 * Handles open / close of the TikTok-style profile slide-in panel.
 * Works alongside header.js (sign-out modal) and header_mobile.js.
 */
(function () {
  'use strict';

  const panel   = document.getElementById('profile-panel');
  const overlay = document.getElementById('profile-overlay');

  if (!panel) return; // not rendered (user not signed in)

  /* ── Open / close ── */
  function openPanel() {
    panel.classList.add('open');
    overlay.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    // Focus close button for accessibility
    setTimeout(function () {
      document.getElementById('pp-close')?.focus();
    }, 60);
  }

  function closePanel() {
    panel.classList.remove('open');
    overlay.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  /* ── Triggers ── */

  // Desktop header avatar/account button
  document.getElementById('hdr-account-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openPanel();
  });

  // Mobile bottom bar account button
  document.getElementById('mob-account-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openPanel();
  });

  // Also catch the old mob-signout-btn id if still in markup (graceful)
  document.getElementById('mob-signout-btn')?.addEventListener('click', function (e) {
    e.preventDefault();
    openPanel();
  });

  /* ── Close triggers ── */
  document.getElementById('pp-close')?.addEventListener('click', closePanel);
  overlay.addEventListener('click', closePanel);

  // Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && panel.classList.contains('open')) closePanel();
  });

  /* ── Sign-out trigger inside panel → open sign-out confirmation modal ── */
  document.getElementById('pp-signout-trigger')?.addEventListener('click', function () {
    closePanel();
    // Small delay so panel finishes sliding before modal appears
    setTimeout(function () {
      document.getElementById('hdr-signout-btn')?.click();
    }, 120);
  });

  // Expose for external use (e.g. index.php deep-link)
  window.openProfilePanel = openPanel;
  window.closeProfilePanel = closePanel;

})();