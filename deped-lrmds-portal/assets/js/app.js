// DepEd LRMDS – shared site-wide interactivity
(function () {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ── Nav active state ── */
  const path = location.pathname.split('/').pop();
  qsa('.nav a').forEach(a => {
    if (!path || path === '' || path === 'index.html') {
      if (a.getAttribute('href').includes('index')) a.classList.add('active');
    } else if (a.getAttribute('href').includes(path)) {
      a.classList.add('active');
    }
  });

  /* ── Carousel ── */
  qsa('[data-carousel]').forEach(c => {
    const container = c.querySelector('.carousel');
    c.querySelector('[data-left]')?.addEventListener('click', () =>
      container.scrollBy({ left: -300, behavior: 'smooth' })
    );
    c.querySelector('[data-right]')?.addEventListener('click', () =>
      container.scrollBy({ left: 300, behavior: 'smooth' })
    );
  });

  /* ── Search form → search.php ── */
  qsa('form[data-search]').forEach(form => {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const fd = new FormData(form);
      const params = new URLSearchParams(fd).toString();
      location.href = 'search.php?' + params;
    });
  });

})();