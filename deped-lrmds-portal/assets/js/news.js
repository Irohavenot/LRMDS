/* ================================
   LRMDS – News & Advisories JS
   assets/js/news.js
================================= */
(function () {
  'use strict';

  // ── Dataset ─────────────────────────────────────────────
  const NEWS = [
    // System Announcements
    {
      id: 1, cat: 'announcement', pinned: true,
      title: 'Scheduled System Maintenance – March 14, 2026',
      desc: 'The LRMDS portal will be unavailable from 10:00 PM to 2:00 AM (PHT) for routine database maintenance and security patching.',
      date: '2026-03-10',
    },
    {
      id: 2, cat: 'announcement', pinned: false,
      title: 'Updated Data Privacy Policy Effective April 1, 2026',
      desc: 'In compliance with RA 10173, LRMDS has updated its Data Privacy Policy. All users are encouraged to review the changes before April 1.',
      date: '2026-03-05',
    },
    {
      id: 3, cat: 'announcement', pinned: false,
      title: 'Service Advisory: Slow Upload Speeds Resolved',
      desc: 'The intermittent slow upload issue reported by some regional users has been resolved as of March 3, 2026, 3:00 PM PHT.',
      date: '2026-03-03',
    },

    // Release Notes
    {
      id: 4, cat: 'release', pinned: true,
      title: 'LRMDS v2.4.0 Release Notes – March 2026',
      desc: 'New: Faceted search filters, bulk download improvements, QA dashboard redesign, and over 15 bug fixes including the DLL preview loading issue.',
      date: '2026-03-01',
    },
    {
      id: 5, cat: 'release', pinned: false,
      title: 'Hotfix v2.3.5 – Login & Session Timeout Fix',
      desc: 'Addresses a critical bug where users were logged out prematurely during resource download on mobile devices.',
      date: '2026-02-18',
    },
    {
      id: 6, cat: 'release', pinned: false,
      title: 'v2.3.0 – MELCs Competency Code Search & SLM Preview',
      desc: 'Introduces direct MELC code search, in-browser SLM PDF preview, and improved accessibility features across all pages.',
      date: '2026-01-20',
    },

    // Program Updates & Campaigns
    {
      id: 7, cat: 'program', pinned: false,
      title: 'SY 2025–2026 MELCs-Aligned SLM Upload Drive Now Open',
      desc: 'All divisions are requested to submit finalized SY 2025–2026 SLMs via the LRMDS portal no later than March 31, 2026.',
      date: '2026-03-07',
    },
    {
      id: 8, cat: 'program', pinned: false,
      title: 'New OER Partnership with DepEd TV and GMA Pinoy TV',
      desc: 'Video lessons from DepEd TV and select GMA Pinoy TV educational programs are now indexed in the LRMDS resource library.',
      date: '2026-02-25',
    },
    {
      id: 9, cat: 'program', pinned: false,
      title: 'Quality Assurance (QA) Campaign: Batch 3 Review Now Ongoing',
      desc: 'Batch 3 QA review covers Grades 7–10 Science and Mathematics SLMs. Regional QA coordinators are requested to complete reviews by March 20.',
      date: '2026-02-14',
    },
    {
      id: 10, cat: 'program', pinned: false,
      title: 'MTB-MLE Resource Localization Initiative – Visayas Pilot',
      desc: 'LRMDS and the Bureau of Curriculum Development launch a pilot to localize MTB-MLE materials for 12 languages in the Visayas region.',
      date: '2026-01-30',
    },

    // Events & Webinars
    {
      id: 11, cat: 'event', pinned: false,
      title: 'Webinar: Using LRMDS for Classroom Instruction (For Teachers)',
      desc: 'A 2-hour orientation webinar for public school teachers on how to search, download, and integrate LRMDS materials into lessons.',
      date: '2026-03-20',
      eventDate: { day: '20', mon: 'Mar' }, eventTime: '2:00 PM – 4:00 PM PHT',
    },
    {
      id: 12, cat: 'event', pinned: false,
      title: "LRMDS Division Coordinators' Summit \u2013 Region VI",
      desc: 'Annual summit for LRMDS Division Coordinators in Region VI covering SY 2025–2026 targets, QA processes, and portal updates.',
      date: '2026-03-27',
      eventDate: { day: '27', mon: 'Mar' }, eventTime: '8:00 AM – 5:00 PM PHT',
    },
    {
      id: 13, cat: 'event', pinned: false,
      title: 'Webinar: LRMDS Submission Guidelines for LR Developers',
      desc: 'Step-by-step walkthrough of the resource submission, metadata tagging, and QA endorsement workflow for learning resource developers.',
      date: '2026-04-03',
      eventDate: { day: '3', mon: 'Apr' }, eventTime: '9:00 AM – 11:00 AM PHT',
    },
    {
      id: 14, cat: 'event', pinned: false,
      title: 'DepEd EdTech Fair 2026 – LRMDS Booth',
      desc: 'Visit the LRMDS booth at the DepEd EdTech Fair 2026 for live demos, resource exhibits, and Q&A with the development team.',
      date: '2026-04-22',
      eventDate: { day: '22', mon: 'Apr' }, eventTime: 'April 22–24, 2026',
    },
  ];

  // Upcoming events for sidebar (future dates, sorted)
  const TODAY = new Date('2026-03-09');
  const EVENTS = NEWS
    .filter(n => n.cat === 'event' && new Date(n.date) >= TODAY)
    .sort((a, b) => new Date(a.date) - new Date(b.date))
    .slice(0, 4);

  // Latest system announcement for sidebar
  const LATEST_ADV = NEWS
    .filter(n => n.cat === 'announcement')
    .sort((a, b) => new Date(b.date) - new Date(a.date))[0];

  // ── State ────────────────────────────────────────────────
  let activeCat = 'all';
  let searchQ = '';

  // ── DOM refs ────────────────────────────────────────────
  const listEl       = document.getElementById('news-list');
  const emptyEl      = document.getElementById('news-empty');
  const countEl      = document.getElementById('news-count');
  const chipsEl      = document.getElementById('news-chips');
  const searchEl     = document.getElementById('news-search');
  const sideEvents   = document.getElementById('event-sidebar');
  const sideAdvisory = document.getElementById('latest-advisory');

  // ── Helpers ──────────────────────────────────────────────
  function fmt(dateStr) {
    return new Date(dateStr).toLocaleDateString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
    });
  }

  const CAT_ICON = {
    announcement: 'assets/icons/megaphone.svg',
    release:      'assets/icons/seal-check.svg',
    program:      'assets/icons/globe.svg',
    event:        'assets/icons/calendar.svg',
  };

  const CAT_LABEL = {
    announcement: 'System Announcement',
    release:      'Release Notes',
    program:      'Program Update',
    event:        'Event / Webinar',
  };

  // ── Filter ───────────────────────────────────────────────
  function filtered() {
    return NEWS.filter(n => {
      const catOk  = activeCat === 'all' || n.cat === activeCat;
      const termOk = !searchQ || n.title.toLowerCase().includes(searchQ) || n.desc.toLowerCase().includes(searchQ);
      return catOk && termOk;
    // pinned first, then newest
    }).sort((a, b) => (b.pinned - a.pinned) || (new Date(b.date) - new Date(a.date)));
  }

  // ── Render list ──────────────────────────────────────────
  function render() {
    const items = filtered();

    // Count
    countEl.textContent = items.length + ' article' + (items.length !== 1 ? 's' : '');

    // Empty
    const empty = items.length === 0;
    emptyEl.hidden = !empty;
    listEl.hidden  = empty;

    if (empty) return;

    listEl.innerHTML = items.map(n => `
      <article class="news-card${n.pinned ? ' pinned' : ''}" role="listitem" tabindex="0">
        <div class="news-card-icon ic-${n.cat}">
          <img src="${CAT_ICON[n.cat]}" alt="">
        </div>
        <div class="news-card-body">
          <div class="news-card-meta">
            <span class="news-cat-badge cat-${n.cat}">${CAT_LABEL[n.cat]}</span>
            <span class="news-date">${fmt(n.date)}</span>
            ${n.pinned ? '<span class="news-pin-badge">📌 Pinned</span>' : ''}
            ${n.eventDate ? `<span class="news-date">${n.eventTime}</span>` : ''}
          </div>
          <h3>${n.title}</h3>
          <p>${n.desc}</p>
        </div>
      </article>`).join('');

    // Chips
    const chips = [];
    if (activeCat !== 'all') chips.push({ field: 'cat', label: CAT_LABEL[activeCat] });
    if (searchQ)             chips.push({ field: 'q',   label: `"${searchQ}"` });

    chipsEl.innerHTML = chips.map(c =>
      `<span class="chip" data-field="${c.field}">${c.label} <button aria-label="Remove filter">✕</button></span>`
    ).join('');

    chipsEl.querySelectorAll('.chip button').forEach(btn => {
      btn.addEventListener('click', () => {
        const f = btn.parentElement.dataset.field;
        if (f === 'cat') { activeCat = 'all'; syncTabs(); }
        if (f === 'q')   { searchQ = ''; if (searchEl) searchEl.value = ''; }
        render();
      });
    });
  }

  // ── Sidebar ──────────────────────────────────────────────
  function renderSidebar() {
    if (sideEvents) {
      sideEvents.innerHTML = EVENTS.map(e => `
        <li class="event-item">
          <div class="event-date-box">
            <div class="eday">${e.eventDate.day}</div>
            <div class="emon">${e.eventDate.mon}</div>
          </div>
          <div class="event-info">
            <div class="etitle">${e.title}</div>
            <div class="etime">${e.eventTime}</div>
          </div>
        </li>`).join('');
    }

    if (sideAdvisory && LATEST_ADV) {
      sideAdvisory.innerHTML = `
        <div class="advisory-box">
          <div class="adv-title">${LATEST_ADV.title}</div>
          <div class="adv-desc">${LATEST_ADV.desc.slice(0, 100)}…</div>
          <div class="adv-date">${fmt(LATEST_ADV.date)}</div>
        </div>`;
    }
  }

  // ── Tab sync ─────────────────────────────────────────────
  function syncTabs() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.cat === activeCat);
      btn.setAttribute('aria-selected', btn.dataset.cat === activeCat);
    });
  }

  // ── Events ───────────────────────────────────────────────
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      activeCat = btn.dataset.cat;
      syncTabs();
      render();
    });
  });

  if (searchEl) {
    searchEl.addEventListener('input', () => {
      searchQ = searchEl.value.trim().toLowerCase();
      render();
    });
  }

  document.getElementById('news-clear')?.addEventListener('click', () => {
    activeCat = 'all'; searchQ = '';
    if (searchEl) searchEl.value = '';
    syncTabs(); render();
  });

  // ── Init ─────────────────────────────────────────────────
  render();
  renderSidebar();

  // Honour ?cat= URL param
  const urlCat = new URLSearchParams(location.search).get('cat');
  if (urlCat && ['announcement','release','program','event'].includes(urlCat)) {
    activeCat = urlCat;
    syncTabs();
    render();
  }

})();