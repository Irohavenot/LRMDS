// DepEd LRMDS – Search page JS
(function () {
  const qs = (sel, root = document) => root.querySelector(sel);
  const qsa = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  /* ── Extended dummy dataset ── */
  const DATA = [
    // Mathematics
    { title: 'SLM – Mathematics 6: Fractions', grade: '6', subject: 'Mathematics', type: 'SLM', melc: 'M6NS-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 1820 },
    { title: 'SLM – Mathematics 4: Multiplication', grade: '4', subject: 'Mathematics', type: 'SLM', melc: 'M4NS-IIa-1', quarter: 'Q2', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 950 },
    { title: 'Worksheet – Math 3: Addition & Subtraction', grade: '3', subject: 'Mathematics', type: 'Worksheet', melc: 'M3NS-Ia-2', quarter: 'Q1', lang: 'Filipino', license: 'CC', sy: '2022-2023', qa: 'passed', downloads: 740 },
    { title: 'Video Lesson – Math 7: Integers', grade: '7', subject: 'Mathematics', type: 'Video', melc: 'M7NS-Ia-1', quarter: 'Q1', lang: 'English', license: 'OER', sy: '2023-2024', qa: 'review', downloads: 430 },
    { title: 'Interactive – Math 10: Sequences', grade: '10', subject: 'Mathematics', type: 'Interactive', melc: 'M10AL-Ib-1', quarter: 'Q1', lang: 'English', license: 'OER', sy: '2023-2024', qa: 'passed', downloads: 610 },
    { title: 'Assessment Bank – Math 8: Algebra', grade: '8', subject: 'Mathematics', type: 'Assessment', melc: 'M8AL-IIa-1', quarter: 'Q2', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 380 },
    { title: 'Curriculum Guide – Mathematics SHS', grade: '11', subject: 'Mathematics', type: 'Curriculum Guide', melc: '', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'passed', downloads: 290 },

    // Science
    { title: 'DLL – Science 8 Q1 W2', grade: '8', subject: 'Science', type: 'DLL', melc: 'S8LT-Ib-2', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'review', downloads: 530 },
    { title: 'SLM – Science 5: Ecosystems', grade: '5', subject: 'Science', type: 'SLM', melc: 'S5LT-IIIa-1', quarter: 'Q3', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 860 },
    { title: 'Video – Science 9: Mitosis', grade: '9', subject: 'Science', type: 'Video', melc: 'S9LT-IIa-b-1', quarter: 'Q2', lang: 'English', license: 'OER', sy: '2023-2024', qa: 'passed', downloads: 1100 },
    { title: 'Learner\'s Material – Science 7', grade: '7', subject: 'Science', type: 'LM', melc: 'S7MT-IVa-1', quarter: 'Q4', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'passed', downloads: 470 },
    { title: 'Rubric – Science Lab Report Grade 10', grade: '10', subject: 'Science', type: 'Rubric', melc: 'S10MT-IIa-1', quarter: 'Q2', lang: 'English', license: 'CC', sy: '2023-2024', qa: 'passed', downloads: 220 },

    // English
    { title: 'Teacher\'s Guide – English 10', grade: '10', subject: 'English', type: 'TG', melc: 'EN10RC-Ic-4', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 1340 },
    { title: 'SLM – English 3: Reading Comprehension', grade: '3', subject: 'English', type: 'SLM', melc: 'EN3RC-Ia-2', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 760 },
    { title: 'DLP – English 6: Figurative Language', grade: '6', subject: 'English', type: 'DLP', melc: 'EN6VC-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'review', downloads: 340 },
    { title: 'Slide Presentation – English 8: Poetry', grade: '8', subject: 'English', type: 'Slide', melc: 'EN8LT-IIa-2', quarter: 'Q2', lang: 'English', license: 'CC', sy: '2023-2024', qa: 'passed', downloads: 490 },
    { title: 'Textbook – English 9', grade: '9', subject: 'English', type: 'Textbook', melc: '', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'passed', downloads: 1580 },

    // Filipino
    { title: 'Video Lesson – Filipino: Pangngalan', grade: '3', subject: 'Filipino', type: 'Video', melc: 'F3WG-Ia-3', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 920 },
    { title: 'SLM – Filipino 5: Pabula', grade: '5', subject: 'Filipino', type: 'SLM', melc: 'F5PT-Ia-1', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 670 },
    { title: 'DLL – Filipino 7 Q2', grade: '7', subject: 'Filipino', type: 'DLL', melc: 'F7PN-IIa-1', quarter: 'Q2', lang: 'Filipino', license: 'DepEd', sy: '2022-2023', qa: 'review', downloads: 300 },
    { title: 'Learner\'s Material – Filipino 10', grade: '10', subject: 'Filipino', type: 'LM', melc: 'F10PT-IVb-1', quarter: 'Q4', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 430 },

    // Araling Panlipunan
    { title: 'Assessment Bank – AP 7', grade: '7', subject: 'Araling Panlipunan', type: 'Assessment', melc: 'AP7KSA-Id-5', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'review', downloads: 310 },
    { title: 'SLM – AP 5: Pilipinas sa Mapa', grade: '5', subject: 'Araling Panlipunan', type: 'SLM', melc: 'AP5PKP-Ia-1', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 580 },
    { title: 'Video – AP 9: Asya', grade: '9', subject: 'Araling Panlipunan', type: 'Video', melc: 'AP9PAA-Ia-1', quarter: 'Q1', lang: 'Filipino', license: 'OER', sy: '2023-2024', qa: 'passed', downloads: 420 },

    // MAPEH
    { title: 'SLM – MAPEH 6: Music Elements', grade: '6', subject: 'MAPEH', type: 'SLM', melc: 'MU6ME-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 370 },
    { title: 'DLL – MAPEH 8: Health', grade: '8', subject: 'MAPEH', type: 'DLL', melc: 'H8FH-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'review', downloads: 210 },

    // EsP
    { title: 'SLM – EsP 4: Pagpapahalaga', grade: '4', subject: 'EsP', type: 'SLM', melc: 'EsP4PD-Ia-1', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 490 },

    // EPP/TLE
    { title: 'TG – EPP 5: Home Economics', grade: '5', subject: 'EPP/TLE', type: 'TG', melc: 'EPP5HE-Ia-1', quarter: 'Q1', lang: 'Filipino', license: 'DepEd', sy: '2022-2023', qa: 'passed', downloads: 260 },
    { title: 'SLM – TLE/TVL: Cookery NC II', grade: '11', subject: 'TLE/TVL', type: 'SLM', melc: 'TLE-HECK11-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 730 },

    // MTB / Kinder
    { title: 'SLM – MTB-MLE Kinder: Salita', grade: 'Kinder', subject: 'MTB-MLE', type: 'SLM', melc: 'MTB-Ia-1', quarter: 'Q1', lang: 'MTB-MLE', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 640 },
    { title: 'Audio Lesson – MTB-MLE 1: Pagbabasa', grade: '1', subject: 'MTB-MLE', type: 'Audio', melc: 'MTB1-Ia-1', quarter: 'Q1', lang: 'MTB-MLE', license: 'CC', sy: '2023-2024', qa: 'passed', downloads: 390 },

    // SHS
    { title: 'SLM – SHS Earth Science (STEM)', grade: '11', subject: 'SHS Core', type: 'SLM', melc: 'ES11-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'passed', downloads: 820 },
    { title: 'LM – SHS Oral Communication', grade: '11', subject: 'SHS Core', type: 'LM', melc: 'EN11/12OC-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2022-2023', qa: 'passed', downloads: 950 },
    { title: 'SLM – SHS Empowerment Technologies', grade: '12', subject: 'SHS Applied', type: 'SLM', melc: 'CS_ICT11-12-ICTPT-Ia-1', quarter: 'Q1', lang: 'English', license: 'DepEd', sy: '2023-2024', qa: 'review', downloads: 670 },
  ];

  const SUBJECT_IMAGES = {
    'English': 'assets/img/subjects/english.png',
    'Filipino': 'assets/img/subjects/filipino.png',
    'Mathematics': 'assets/img/subjects/math.png',
    'Science': 'assets/img/subjects/science.png',
    'Araling Panlipunan': 'assets/img/subjects/ap.png',
    'MAPEH': 'assets/img/subjects/mapeh.png',
    'EsP': 'assets/img/subjects/esp.png',
    'EPP/TLE': 'assets/img/subjects/tle.png',
    'TLE/TVL': 'assets/img/subjects/tle.png',
    'MTB-MLE': 'assets/img/subjects/MTB MLE.png',
    'SHS Core': 'assets/img/subjects/SHS EARTH SCIENCE.png',
    'SHS Applied': 'assets/img/subjects/empowerment.png',
    'SHS Specialized': 'assets/img/subjects/shs-specialized.png',
  };

  function getSubjectImage(subject) {
    return SUBJECT_IMAGES[subject] || 'assets/img/subjects/default.jpg';
  }

  /* ── Read all URL params ── */
  const params = new URLSearchParams(location.search);
  const activeFilters = {
    q: params.get('q') || '',
    grade: params.get('grade') || '',
    subject: params.get('subject') || '',
    type: params.get('type') || '',
    melc: params.get('melc') || '',
    quarter: params.get('quarter') || '',
    lang: params.get('lang') || '',
    license: params.get('license') || '',
    sy: params.get('sy') || '',
    sort: params.get('sort') || 'relevance',
  };

  /* ── Inject missing filter controls into the existing filter bar ── */
  const filterBar = qs('.filters');
  if (filterBar) {
    const subjects = [
      'English', 'Filipino', 'Mathematics', 'Science', 'Araling Panlipunan',
      'MAPEH', 'EsP', 'EPP/TLE', 'TLE/TVL', 'MTB-MLE', 'SHS Core', 'SHS Applied', 'SHS Specialized',
    ];
    const subjectEl = qs('[name="subject"]', filterBar);
    if (subjectEl) {
      subjectEl.innerHTML = '<option value="">All Learning Areas</option>' +
        subjects.map(s => `<option${activeFilters.subject === s ? ' selected' : ''}>${s}</option>`).join('');
    }

    const types = [
      'Textbook', 'TG', 'LM', 'SLM', 'Curriculum Guide', 'DLL', 'DLP',
      'Worksheet', 'Assessment', 'Rubric', 'Video', 'Audio', 'Interactive', 'Slide',
    ];
    const typeEl = qs('[name="type"]', filterBar);
    if (typeEl) {
      typeEl.innerHTML = '<option value="">All Types</option>' +
        types.map(t => `<option${activeFilters.type.toLowerCase() === t.toLowerCase() ? ' selected' : ''}>${t}</option>`).join('');
    }

    const makeSelect = (name, placeholder, options) => {
      const sel = document.createElement('select');
      sel.className = 'select';
      sel.name = name;
      sel.innerHTML = `<option value="">${placeholder}</option>` +
        options.map(o => `<option${activeFilters[name] === o ? ' selected' : ''}>${o}</option>`).join('');
      return sel;
    };

    const btn = qs('button[type="submit"]', filterBar);
    [
      makeSelect('quarter', 'All Quarters', ['Q1', 'Q2', 'Q3', 'Q4']),
      makeSelect('lang', 'All Languages', ['English', 'Filipino', 'MTB-MLE']),
      makeSelect('license', 'All Licenses', ['CC', 'DepEd', 'OER']),
      makeSelect('sy', 'All School Years', ['2023-2024', '2022-2023', '2021-2022']),
    ].forEach(el => filterBar.insertBefore(el, btn));

    // Sort — appended after the submit button
    const sortSel = document.createElement('select');
    sortSel.className = 'select';
    sortSel.name = 'sort';
    sortSel.innerHTML = `
      <option value="relevance"${activeFilters.sort === 'relevance' ? ' selected' : ''}>Sort: Relevance</option>
      <option value="downloads"${activeFilters.sort === 'downloads' ? ' selected' : ''}>Most Downloaded</option>
      <option value="newest"${activeFilters.sort === 'newest' ? ' selected' : ''}>Newest</option>
      <option value="title"${activeFilters.sort === 'title' ? ' selected' : ''}>Title A–Z</option>`;
    filterBar.appendChild(sortSel);

    // Pre-fill text inputs
    const fill = (name, val) => { const el = qs(`[name="${name}"]`, filterBar); if (el && val) el.value = val; };
    fill('q', activeFilters.q);
    fill('grade', activeFilters.grade);
    fill('melc', activeFilters.melc);
  }

  /* ── Filter + sort ── */
  function applyFilters() {
    const f = activeFilters;
    let results = DATA.filter(item =>
      (!f.q || item.title.toLowerCase().includes(f.q.toLowerCase()) || item.melc.toLowerCase().includes(f.q.toLowerCase())) &&
      (!f.grade || item.grade === f.grade) &&
      (!f.subject || item.subject.toLowerCase() === f.subject.toLowerCase()) &&
      (!f.type || item.type.toLowerCase() === f.type.toLowerCase()) &&
      (!f.melc || item.melc.toLowerCase().includes(f.melc.toLowerCase())) &&
      (!f.quarter || item.quarter === f.quarter) &&
      (!f.lang || item.lang === f.lang) &&
      (!f.license || item.license === f.license) &&
      (!f.sy || item.sy === f.sy)
    );
    if (f.sort === 'downloads') results.sort((a, b) => b.downloads - a.downloads);
    else if (f.sort === 'title') results.sort((a, b) => a.title.localeCompare(b.title));
    return results;
  }

  /* ── Facet sidebar ── */
  function buildFacets(filteredResults) {
    const container = qs('#facet-sidebar');
    if (!container) return;

    const groups = [
      { field: 'subject', label: 'Learning Area', values: [...new Set(DATA.map(d => d.subject))].sort() },
      { field: 'grade', label: 'Grade Level', values: ['Kinder', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'] },
      { field: 'type', label: 'Resource Type', values: [...new Set(DATA.map(d => d.type))].sort() },
      { field: 'quarter', label: 'Quarter', values: ['Q1', 'Q2', 'Q3', 'Q4'] },
      { field: 'lang', label: 'Language', values: ['English', 'Filipino', 'MTB-MLE'] },
      { field: 'license', label: 'License', values: ['CC', 'DepEd', 'OER'] },
      { field: 'sy', label: 'School Year', values: ['2023-2024', '2022-2023', '2021-2022'] },
    ];

    container.innerHTML = groups.map(g => {
      const items = g.values.map(val => {
        const c = filteredResults.filter(r => r[g.field] === val).length;
        if (c === 0) return '';
        const isActive = activeFilters[g.field] === val;
        return `<li>
          <button class="facet-btn${isActive ? ' active' : ''}" data-field="${g.field}" data-val="${val}">
            <span class="facet-label">${val}</span>
            <span class="facet-count">${c}</span>
          </button>
        </li>`;
      }).filter(Boolean).join('');
      if (!items) return '';
      return `<div class="facet-group">
        <h4 class="facet-heading">${g.label}</h4>
        <ul class="facet-list">${items}</ul>
      </div>`;
    }).join('');

    // Active filter chips
    const chipBar = qs('#active-chips');
    if (chipBar) {
      chipBar.innerHTML = Object.entries(activeFilters)
        .filter(([k, v]) => v && k !== 'sort' && k !== 'q')
        .map(([k, v]) => `<span class="filter-chip" data-field="${k}">${v} <button aria-label="Remove ${v}">✕</button></span>`)
        .join('');

      qs('#active-chips')?.querySelectorAll('.filter-chip button').forEach(btn => {
        btn.addEventListener('click', () => {
          activeFilters[btn.parentElement.dataset.field] = '';
          renderResults();
          history.replaceState(null, '', '?' + new URLSearchParams(activeFilters).toString());
        });
      });
    }

    // Facet button clicks
    container.querySelectorAll('.facet-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const { field, val } = btn.dataset;
        activeFilters[field] = activeFilters[field] === val ? '' : val;
        renderResults();
        history.replaceState(null, '', '?' + new URLSearchParams(activeFilters).toString());
      });
    });
  }

  /* ── Render results ── */
  function renderResults() {
    const results = applyFilters();
    const list = qs('#results');
    if (!list) return;

    const countEl = qs('#result-count');
    if (countEl) countEl.textContent = `${results.length} resource${results.length !== 1 ? 's' : ''} found`;

    if (results.length === 0) {
      list.innerHTML = `<div class="empty-state">
        <svg width="48" height="48" fill="none" stroke="#9CA3AF" stroke-width="1.5" viewBox="0 0 24 24">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <p>No resources matched your filters.</p>
        <button class="button ghost" id="clear-all">Clear all filters</button>
      </div>`;
      qs('#clear-all')?.addEventListener('click', () => {
        Object.keys(activeFilters).forEach(k => { if (k !== 'sort') activeFilters[k] = ''; });
        renderResults();
        history.replaceState(null, '', '?');
      });
    } else {
      list.innerHTML = results.map(r => `
        <article class="result-card">
          <div class="thumb-wrap">
            <img src="${getSubjectImage(r.subject)}" alt="${r.subject} resource image" loading="lazy">
            <span class="type-badge">${r.type}</span>
          </div>
          <div class="meta">
            <div class="tag-row">
              <span class="tag">Grade ${r.grade}</span>
              <span class="tag secondary">${r.quarter}</span>
              <span class="tag secondary">${r.lang}</span>
              <span class="tag secondary">${r.license}</span>
            </div>
            <h3 class="title">${r.title}</h3>
            <div class="detail-row">
              <span>${r.subject}</span><span class="sep">•</span>
              <span>MELC: <code>${r.melc || '—'}</code></span><span class="sep">•</span>
              <span>SY ${r.sy}</span>
            </div>
            <div class="card-footer">
              <div>${r.qa === 'passed'
                ? '<span class="badge success">QA Passed</span>'
                : '<span class="badge warn">Under Review</span>'}</div>
              <div class="dl-count">Downloads ⬇ ${r.downloads.toLocaleString()}</div>
              <div class="actions">
                <a href="resource.php" class="button ghost" aria-label="View ${r.title}">View</a>
                <button class="button primary" aria-label="Download ${r.title}">Download</button>
              </div>
            </div>
          </div>
        </article>`).join('');
    }

    buildFacets(results);
  }

  /* ── Inject sidebar + result count into the DOM ── */
  const section = qs('section.section.container');
  if (section) {
    const h2 = section.querySelector('h2');

    const countSpan = document.createElement('span');
    countSpan.id = 'result-count';
    countSpan.style.cssText = 'font-size:14px;color:#6B7280;margin-left:12px;font-weight:400;';
    h2?.appendChild(countSpan);

    const chipBar = document.createElement('div');
    chipBar.id = 'active-chips';
    chipBar.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;';
    const resultsEl = qs('#results', section);
    section.insertBefore(chipBar, resultsEl);

    const wrapper = document.createElement('div');
    wrapper.className = 'search-layout';
    wrapper.style.cssText = 'display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start;';

    const sidebar = document.createElement('aside');
    sidebar.id = 'facet-sidebar';
    sidebar.className = 'facet-sidebar';

    section.insertBefore(wrapper, resultsEl);
    wrapper.appendChild(sidebar);
    wrapper.appendChild(resultsEl);
  }

  /* ── Initial render ── */
  renderResults();

  /* ── Live re-filter on sort change ── */
  const sortEl = qs('[name="sort"]', filterBar);
  sortEl?.addEventListener('change', () => {
    activeFilters.sort = sortEl.value;
    renderResults();
  });

})();