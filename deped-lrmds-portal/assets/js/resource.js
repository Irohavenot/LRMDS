// DepEd LRMDS – Resource detail page JS
(function () {
  const qs = (sel, root = document) => root.querySelector(sel);

  /* ── Download button ── */
  qs('.actions .button.primary')?.addEventListener('click', function () {
    // Prototype: just show a brief label change to confirm the action
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Downloading…';
    setTimeout(() => {
      btn.disabled = false;
      btn.innerHTML = original;
    }, 1800);
  });

  /* ── Save to Library button ── */
  qs('.actions .button.ghost')?.addEventListener('click', function () {
    const btn = this;
    const saved = btn.classList.toggle('saved');
    btn.innerHTML = saved
      ? '<img src="assets/icons/bookmark-fill.svg" alt="" style="vertical-align:middle;margin-right:6px">Saved'
      : '<img src="assets/icons/bookmark.svg" alt="" style="vertical-align:middle;margin-right:6px">Save to Library';
  });

})();