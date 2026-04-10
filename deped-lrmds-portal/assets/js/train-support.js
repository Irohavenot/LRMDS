/**
 * train-support.js
 * DepEd LRMDS – Train & Support page interactions
 * Handles: mega-menu toggle, FAQ accordion, FAQ tab filter
 */

(function () {
  'use strict';

  /* ── Mega-menu ───────────────────────────────────────────── */
  var trainNav = document.getElementById('trainNav');
  var trainBtn = document.getElementById('trainBtn');
  var megaMenu = document.getElementById('megaMenu');

  if (trainBtn && megaMenu) {

    // Toggle on button click
    trainBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = megaMenu.classList.toggle('show');
      trainBtn.setAttribute('aria-expanded', String(isOpen));
    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
      if (trainNav && !trainNav.contains(e.target)) {
        megaMenu.classList.remove('show');
        trainBtn.setAttribute('aria-expanded', 'false');
      }
    });

    // Close on Escape key
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

      // Close all open items
      faqItems.forEach(function (i) { i.classList.remove('open'); });

      // Open this one unless it was already open
      if (!isOpen) {
        item.classList.add('open');
      }
    });
  });

  /* ── FAQ tab filter ──────────────────────────────────────── */
  var faqTabs = document.querySelectorAll('.faq-tab');

  faqTabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      // Update active tab
      faqTabs.forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');

      var filter = tab.dataset.tab;

      // Show / hide FAQ items based on data-tags attribute
      faqItems.forEach(function (item) {
        var tags = item.dataset.tags || '';
        if (filter === 'all' || tags.indexOf(filter) !== -1) {
          item.style.display = '';
        } else {
          item.style.display = 'none';
          item.classList.remove('open'); // collapse hidden items
        }
      });
    });
  });

})();


  /* ── Sticky side nav ─────────────────────────────────────── */

  var sections = [
    { id: 'getting-started', label: 'Getting Started'    },
    { id: 'tutorials',       label: 'Tutorials'          },
    { id: 'guides',          label: 'User Guides'        },
    { id: 'faq',             label: 'FAQs'               },
    { id: 'helpdesk',        label: 'Helpdesk'           },
    { id: 'certification',   label: 'Certification'      },
    { id: 'community',       label: 'Community'          }
  ];

  // Build the nav element
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

    // Smooth scroll (prevent jump on older browsers gracefully)
    a.addEventListener('click', function (e) {
      e.preventDefault();
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.replaceState(null, '', '#' + sec.id);
    });

    sideNav.appendChild(a);
    navItems.push({ el: el, navEl: a });
  });

  document.body.appendChild(sideNav);

  // Intersection observer – highlight the section in view
  var headerHeight = 80; // approx sticky header height

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
  