<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>News & Advisories – DepEd LRMDS</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/news.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="news-hero">
  <div class="container">
    <div class="news-hero-inner">
      <div>
        <span class="news-eyebrow">DepEd LRMDS</span>
        <h1>News &amp; Advisories</h1>
        <p>Stay updated with system announcements, release notes, program updates, and upcoming events.</p>
      </div>

      <div class="news-tabs" role="tablist" aria-label="News categories">
        <button class="tab-btn active" role="tab" aria-selected="true" data-cat="all">All</button>
        <button class="tab-btn" role="tab" aria-selected="false" data-cat="announcement">
          <img src="assets/icons/megaphone.svg" alt=""> System Announcements
        </button>
        <button class="tab-btn" role="tab" aria-selected="false" data-cat="memo">
          <img src="assets/icons/seal-check.svg" alt=""> Memorandums
        </button>
        <button class="tab-btn" role="tab" aria-selected="false" data-cat="program">
          <img src="assets/icons/globe.svg" alt=""> Program Updates
        </button>
        <button class="tab-btn" role="tab" aria-selected="false" data-cat="event">
          <img src="assets/icons/calendar.svg" alt=""> Events &amp; Webinars
        </button>
      </div>
    </div>
  </div>
</section>

<main class="container news-layout" id="news-main">
  <section aria-label="News articles" id="news-list-section">
    <div class="news-toolbar">
      <span id="news-count" class="news-count"></span>
      <div class="news-search-wrap" role="search">
        <input type="search" id="news-search" placeholder="Search news…" aria-label="Search news"/>
        <img src="assets/icons/magnifying-glass.svg" alt="" class="news-search-icon">
      </div>
    </div>

    <div id="news-chips" class="chips-bar" aria-live="polite"></div>

    <div id="news-list" role="list">
      <!-- Rendered by news.js -->
    </div>

    <div id="news-empty" class="empty-state" hidden>
      <svg width="44" height="44" fill="none" stroke="#9CA3AF" stroke-width="1.5" viewBox="0 0 24 24">
        <circle cx="11" cy="11" r="8"/>
        <path d="m21 21-4.35-4.35"/>
      </svg>
      <p>No articles match your filters.</p>
      <button class="button ghost" id="news-clear">Clear filters</button>
    </div>
  </section>

  <aside class="news-sidebar" aria-label="Upcoming events">
    <div class="sidebar-card">
      <h3 class="sidebar-heading"><img src="assets/icons/calendar.svg" alt=""> Upcoming Events</h3>
      <ul class="event-list" id="event-sidebar">
        <!-- Rendered by news.js -->
      </ul>
    </div>

    <div class="sidebar-card" style="margin-top:16px">
      <h3 class="sidebar-heading"><img src="assets/icons/megaphone.svg" alt=""> Latest Advisory</h3>
      <div id="latest-advisory"><!-- Rendered by news.js --></div>
    </div>
  </aside>
</main>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/news.js"></script>
</body>
</html>