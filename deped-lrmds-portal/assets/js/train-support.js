/**
 * train-support.js
 * DepEd LRMDS – Train & Support page interactions
 * Handles: mega-menu toggle, FAQ accordion, FAQ tab filter,
 *          sticky side nav, mobile section navigator pill
 */

(function () {
  'use strict';

  /* ── Mega-menu ───────────────────────────────────────────── */
  var trainNav = document.getElementById('trainNav');
  var trainBtn = document.getElementById('trainBtn');
  var megaMenu = document.getElementById('megaMenu');

  if (trainBtn && megaMenu) {

    trainBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = megaMenu.classList.toggle('show');
      trainBtn.setAttribute('aria-expanded', String(isOpen));
    });

    document.addEventListener('click', function (e) {
      if (trainNav && !trainNav.contains(e.target)) {
        megaMenu.classList.remove('show');
        trainBtn.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && megaMenu.classList.contains('show')) {
        megaMenu.classList.remove('show');
        trainBtn.setAttribute('aria-expanded', 'false');
        trainBtn.focus();
      }
    });
  }

  /* ── FAQ accordion ───────────────────────────────────────── */
  var faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(function (item) {
    var btn = item.querySelector('.faq-q');
    if (!btn) return;

    btn.addEventListener('click', function () {
      var isOpen = item.classList.contains('open');
      faqItems.forEach(function (i) { i.classList.remove('open'); });
      if (!isOpen) {
        item.classList.add('open');
      }
    });
  });

  /* ── FAQ tab filter ──────────────────────────────────────── */
  var faqTabs = document.querySelectorAll('.faq-tab');

  faqTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      faqTabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');

      var filter = tab.dataset.tab;

      faqItems.forEach(function (item) {
        var tags = item.dataset.tags || '';
        if (filter === 'all' || tags.indexOf(filter) !== -1) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
          item.classList.remove('open');
        }
      });
    });
  });

  /* ── Sticky side nav ─────────────────────────────────────── */

  var sections = [
    { id: 'getting-started', label: 'Getting Started' },
    { id: 'tutorials',       label: 'Tutorials'       },
    { id: 'guides',          label: 'User Guides'     },
    { id: 'faq',             label: 'FAQs'            },
    { id: 'helpdesk',        label: 'Helpdesk'        },
    { id: 'certification',   label: 'Certification'   },
    { id: 'community',       label: 'Community'       }
  ];

  var sideNav = document.createElement('nav');
  sideNav.className = 'side-nav';
  sideNav.setAttribute('aria-label', 'Page sections');

  var navItems = [];

  sections.forEach(function (sec) {
    var el = document.getElementById(sec.id);
    if (!el) return;

    var a = document.createElement('a');
    a.className = 'side-nav-item';
    a.href = '#' + sec.id;
    a.setAttribute('aria-label', sec.label);

    a.innerHTML =
      '<span class="side-nav-label">' + sec.label + '</span>' +
      '<span class="side-nav-dot"></span>';

    a.addEventListener('click', function (e) {
      e.preventDefault();
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.replaceState(null, '', '#' + sec.id);
    });

    sideNav.appendChild(a);
    navItems.push({ el: el, navEl: a });
  });

  document.body.appendChild(sideNav);

  var headerHeight = 80;

  var observer = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      var match = navItems.find(function (n) { return n.el === entry.target; });
      if (!match) return;
      if (entry.isIntersecting) {
        navItems.forEach(function (n) { n.navEl.classList.remove('active'); });
        match.navEl.classList.add('active');
      }
    });
  }, {
    rootMargin: '-' + headerHeight + 'px 0px -60% 0px',
    threshold: 0
  });

  navItems.forEach(function (n) { observer.observe(n.el); });


  /* ── Mobile section navigator ────────────────────────────── */
  // Floating pill with ↑ / ↓ arrows + section label + dot indicators.
  // Shown only on ≤ 900px (CSS hides it on wider screens).

  var SECTION_IDS = [
    'getting-started',
    'tutorials',
    'guides',
    'faq',
    'helpdesk',
    'certification',
    'community'
  ];

  var SECTION_LABELS = {
    'getting-started': 'Getting Started',
    'tutorials':       'Tutorials',
    'guides':          'User Guides',
    'faq':             'FAQs',
    'helpdesk':        'Helpdesk',
    'certification':   'Certification',
    'community':       'Community'
  };

  var mobSections = SECTION_IDS.map(function (id) {
    return document.getElementById(id);
  }).filter(Boolean);

  if (mobSections.length === 0) return;

  // ── Build the floating pill ──────────────────────────────
  var pill = document.createElement('div');
  pill.className = 'mob-sec-nav';
  pill.setAttribute('aria-label', 'Page section navigation');
  pill.setAttribute('role', 'navigation');

  pill.innerHTML =
    '<button class="mob-sec-btn" id="mob-sec-prev" aria-label="Go to previous section">' +
      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
        '<path d="M18 15l-6-6-6 6"/>' +
      '</svg>' +
    '</button>' +
    '<div class="mob-sec-center">' +
      '<span class="mob-sec-label" id="mob-sec-label">Getting Started</span>' +
      '<span class="mob-sec-dots" id="mob-sec-dots"></span>' +
    '</div>' +
    '<button class="mob-sec-btn" id="mob-sec-next" aria-label="Go to next section">' +
      '<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">' +
        '<path d="M6 9l6 6 6-6"/>' +
      '</svg>' +
    '</button>';

  document.body.appendChild(pill);

  var mobPrevBtn = document.getElementById('mob-sec-prev');
  var mobNextBtn = document.getElementById('mob-sec-next');
  var mobLabelEl = document.getElementById('mob-sec-label');
  var mobDotsEl  = document.getElementById('mob-sec-dots');
  var mobCurrent = 0;

  // ── Dot indicators ───────────────────────────────────────
  function renderMobDots(active) {
    mobDotsEl.innerHTML = mobSections.map(function (_, i) {
      return '<span class="mob-sec-dot' + (i === active ? ' on' : '') + '"></span>';
    }).join('');
  }

  // ── Update pill state ────────────────────────────────────
  function updateMobPill(idx) {
    var id = mobSections[idx] ? mobSections[idx].id : '';
    mobLabelEl.textContent = SECTION_LABELS[id] || '';
    mobPrevBtn.disabled = idx <= 0;
    mobNextBtn.disabled = idx >= mobSections.length - 1;
    renderMobDots(idx);
  }

  // ── Navigate to a section ────────────────────────────────
  function goToSection(idx) {
    if (idx < 0 || idx >= mobSections.length) return;
    mobCurrent = idx;
    mobSections[idx].scrollIntoView({ behavior: 'smooth', block: 'start' });
    updateMobPill(idx);
    pill.classList.add('mob-sec-pulse');
    setTimeout(function () { pill.classList.remove('mob-sec-pulse'); }, 400);
  }

  mobPrevBtn.addEventListener('click', function () { goToSection(mobCurrent - 1); });
  mobNextBtn.addEventListener('click', function () { goToSection(mobCurrent + 1); });

  // ── Track which section is in view ───────────────────────
  var mobIntersect = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var idx = mobSections.indexOf(entry.target);
      if (idx === -1) return;
      mobCurrent = idx;
      updateMobPill(idx);
    });
  }, {
    rootMargin: '-20% 0px -55% 0px',
    threshold: 0
  });

  mobSections.forEach(function (el) { mobIntersect.observe(el); });

  // ── Show / hide pill based on scroll position ────────────
  var heroSection = document.querySelector('.page-hero');

  function syncMobPill() {
    var scrollY  = window.scrollY || window.pageYOffset;
    var heroEnd  = heroSection
      ? heroSection.offsetTop + heroSection.offsetHeight - 60
      : 300;
    var atBottom = (window.innerHeight + scrollY) >= document.body.scrollHeight - 60;

    if (scrollY > heroEnd && !atBottom) {
      pill.classList.add('mob-sec-visible');
    } else {
      pill.classList.remove('mob-sec-visible');
    }
  }

  window.addEventListener('scroll', syncMobPill, { passive: true });
  syncMobPill();

  updateMobPill(0);

})();