// DepEd LRMDS – Resource detail page JS
(function () {
  'use strict';

  const qs  = (sel, root = document) => root.querySelector(sel);

  /* ── Download button ── */
  const dlBtn = qs('[data-download-btn]');
  if (dlBtn) {
    dlBtn.addEventListener('click', function () {
      const btn      = this;
      const original = btn.innerHTML;
      btn.classList.add('loading');
      btn.innerHTML  = '<span style="vertical-align:middle;margin-right:6px">⏳</span>Starting download…';

      // Re-enable after a moment (actual download is handled by the <a href> + download attr)
      setTimeout(() => {
        btn.classList.remove('loading');
        btn.innerHTML = original;
      }, 2200);
    });
  }

  /* ── Save to Library button ── */
  const saveBtn = qs('[data-save-btn]');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      const btn   = this;
      const saved = btn.classList.toggle('saved');
      btn.innerHTML = saved
        ? '<img src="assets/icons/bookmark-fill.svg" alt="" style="vertical-align:middle;margin-right:6px"/>Saved'
        : '<img src="assets/icons/bookmark.svg"      alt="" style="vertical-align:middle;margin-right:6px"/>Save to Library';
      btn.setAttribute('aria-pressed', saved ? 'true' : 'false');
    });
  }

  /* ── Report an issue / Cite ──────────────────────────────────────────────
     These are anchor stubs for now; wire up to a modal or route in production.
  ─────────────────────────────────────────────────────────────────────────── */
  qs('.resource-utility')?.addEventListener('click', function (e) {
    const link = e.target.closest('a');
    if (!link) return;

    const text = link.textContent.trim();

    if (text.includes('Report')) {
      e.preventDefault();
      // TODO: open report modal
      alert('Report feature coming soon.');
    }

    if (text.includes('Cite')) {
      e.preventDefault();
      // TODO: open citation modal / copy APA string
      alert('Citation feature coming soon.');
    }
  });

})();