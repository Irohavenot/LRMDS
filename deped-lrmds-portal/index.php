<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Landing</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="hero">
  <div class="container inner">
    <div>
      <h1>Find DepEd K–12 Learning Resources</h1>
      <p>Browse MELCs-aligned materials by grade, subject, or resource type. Download, save, and share.</p>
      <form data-search aria-label="Resource search">
        <div class="filters">
          <input class="input" type="search" name="q" placeholder="Search by keyword or MELC code…" aria-label="Keywords or MELC code"/>
          <select class="select" name="grade" aria-label="Grade level">
            <option value="">All Grades</option>
            <option value="K">Kindergarten</option>
            <option value="1">1</option><option value="2">2</option><option value="3">3</option>
            <option value="4">4</option><option value="5">5</option><option value="6">6</option>
            <option value="7">7</option><option value="8">8</option><option value="9">9</option><option value="10">10</option>
            <option value="11">11</option><option value="12">12</option>
          </select>
          <select class="select" name="subject" aria-label="Learning area">
            <option value="">All Learning Areas</option>
            <option>English</option><option>Filipino</option><option>Mathematics</option><option>Science</option>
            <option>Araling Panlipunan</option><option>MAPEH</option><option>EsP</option><option>EPP/TLE</option>
            <option>SHS Core</option>
          </select>
          <select class="select" name="type" aria-label="Resource type">
            <option value="">All Types</option>
            <option value="SLM">Self-Learning Module (SLM)</option>
            <option value="TG">Teacher's Guide (TG)</option>
            <option value="DLL">DLL/DLP</option>
            <option value="Video">Video</option>
            <option value="Assessment">Assessment</option>
          </select>
          <input class="input" type="text" name="melc" placeholder="MELC code (optional)" aria-label="MELC code"/>
          <button class="button primary" type="submit">
            <img src="assets/icons/magnifying-glass.svg" alt="" style="vertical-align:middle;margin-right:6px">Search
          </button>
        </div>
      </form>

      <div class="tiles" role="navigation" aria-label="Quick browse">
        <a class="tile" href="browse-grade.php">
          <span class="icon"><img src="assets/icons/student.svg" alt=""></span>
          <div>
            <div class="label">Browse by Grade</div>
            <div class="desc">Kindergarten to SHS</div>
          </div>
        </a>
        <a class="tile" href="search.php?subject=English">
          <span class="icon"><img src="assets/icons/book-open.svg" alt=""></span>
          <div>
            <div class="label">By Learning Area</div>
            <div class="desc">English, Science, Math, Filipino…</div>
          </div>
        </a>
        <a class="tile" href="search.php?type=SLM">
          <span class="icon"><img src="assets/icons/folders.svg" alt=""></span>
          <div>
            <div class="label">By Resource Type</div>
            <div class="desc">SLMs, TG/LM, DLL, Assessments</div>
          </div>
        </a>
        <a class="tile" href="search.php?melc=M6">
          <span class="icon"><img src="assets/icons/check-circle.svg" alt=""></span>
          <div>
            <div class="label">By MELCs</div>
            <div class="desc">Quarter & competency codes</div>
          </div>
        </a>
      </div>
    </div>

    <div>
      <img src="assets/img/lrmds.png" alt="Illustration placeholder" style="width:100%;height:auto;border:1px solid var(--border);border-radius:10px"/>
    </div>
  </div>
</section>

<section class="section container" data-carousel>
  <h2>Featured Collections</h2>
  <div style="display:flex;gap:8px;margin-bottom:8px">
    <button class="button ghost" type="button" data-left>◀</button>
    <button class="button ghost" type="button" data-right>▶</button>
  </div>
  <div class="carousel" aria-label="Featured collections">
    <article class="home-card">
      <img src="assets/img/QUARTER1.png" alt="MELCs Q1">
      <div class="body">
        <span class="tag">MELCs Q1</span>
        <div class="title">Quarter 1 – Curated Set</div>
        <a class="link" href="search.php?melc=Q1">View Collection →</a>
      </div>
    </article>
    <article class="home-card">
      <img src="assets/img/STEM1.png" alt="SHS STEM">
      <div class="body">
        <span class="tag">SHS</span>
        <div class="title">STEM Track Resources</div>
        <a class="link" href="search.php?subject=Science">View Collection →</a>
      </div>
    </article>
    <article class="home-card">
      <img src="assets/img/popular.png" alt="Most Downloaded">
      <div class="body">
        <span class="tag">Popular</span>
        <div class="title">Most Downloaded This Month</div>
        <a class="link" href="search.php?sort=downloads">View Collection →</a>
      </div>
    </article>
  </div>
</section>

<section class="section container">
  <h2>Quick Actions</h2>
  <div class="grid">
    <a class="action" href="search.php?type=DLL"><img src="assets/icons/calendar.svg" alt=""/><div><div class="title">DLL / DLP</div><div class="desc">Daily lesson plans</div></div></a>
    <a class="action" href="search.php?type=SLM"><img src="assets/icons/file-text.svg" alt=""/><div><div class="title">Self-Learning Modules</div><div class="desc">Download SLMs</div></div></a>
    <a class="action" href="search.php?type=TG"><img src="assets/icons/chalkboard-teacher.svg" alt=""/><div><div class="title">Teacher's Guides</div><div class="desc">TG/LM</div></div></a>
    <a class="action" href="search.php?type=Assessment"><img src="assets/icons/clipboard-text.svg" alt=""/><div><div class="title">Assessment Banks</div><div class="desc">Formative & summative</div></div></a>
    <a class="action" href="search.php?type=Video"><img src="assets/icons/play-circle.svg" alt=""/><div><div class="title">Video Lessons</div><div class="desc">Stream & download</div></div></a>
    <a class="action" href="search.php?type=OER"><img src="assets/icons/globe.svg" alt=""/><div><div class="title">OER</div><div class="desc">Open resources</div></div></a>
    <a class="action" href="submit.php"><img src="assets/icons/upload.svg" alt=""/><div><div class="title">Submit Resource</div><div class="desc">Upload & track</div></div></a>
    <a class="action" href="qa-tools.php"><img src="assets/icons/seal-check.svg" alt=""/><div><div class="title">QA Tools</div><div class="desc">Rubrics & templates</div></div></a>
    <a class="action" href="helpdesk.php"><img src="assets/icons/life-ring.svg" alt=""/><div><div class="title">Helpdesk</div><div class="desc">We're here to help</div></div></a>
  </div>
</section>

<section class="section container">
  <h2>Announcements & Advisories</h2>
  <div class="announcements">
    <div class="announcement">
      <div class="left"><img src="assets/icons/megaphone.svg" alt=""/><div><div class="title">System maintenance</div><div class="desc">Lorem ipsum dolor sit amet, consectetuer adipiscing elit.</div></div></div>
      <div class="right">Jan 15, 2026</div>
    </div>
    <div class="announcement">
      <div class="left"><img src="assets/icons/megaphone.svg" alt=""/><div><div class="title">New MELCs-aligned sets</div><div class="desc">Aenean commodo ligula eget dolor. Aenean massa.</div></div></div>
      <div class="right">Jan 12, 2026</div>
    </div>
  </div>
</section>

<section class="section container" aria-label="Regions & Divisions selector">
  <h2>Regions & Divisions</h2>
  <div class="region-select">
    <label for="region">Region</label>
    <select id="region" name="region">
      <option>Region I</option><option>Region II</option><option>Region III</option>
      <option>Region IV-A</option><option>Region V</option><option>Region VI</option>
      <option selected>Region VII</option><option>Region VIII</option>
    </select>
    <label for="division">Division</label>
    <select id="division" name="division">
      <option>Cebu Province</option><option selected>Carcar City</option><option>Cebu City</option>
    </select>
    <a href="search.php?region=VII&division=Carcar" class="button ghost">
      <img src="assets/icons/map-pin.svg" alt="" style="vertical-align:middle;margin-right:6px">View Resources
    </a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>

<!-- Auto-open sign-in modal if redirected here from a protected page -->
<script>
  (function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('signin') === '1') {
      const dest = params.get('dest') || '';
      window.addEventListener('load', function () {
        if (typeof window.openSignin === 'function') {
          window.openSignin(dest);
        }
      });
    }
  })();
</script>
</body>
</html>