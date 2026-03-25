<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Resource Detail</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/resource.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="section container">

  <!-- Breadcrumb -->
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="index.php">Home</a> /
    <a href="search.php">Resources</a> /
    <span>Resource Title</span>
  </nav>

  <div class="detail">

    <!-- Left: thumbnail + download actions -->
    <div class="panel">
      <img
        class="resource-thumb"
        src="assets/img/placeholder-thumb.svg"
        alt="Resource preview"/>

      <div class="resource-actions">
        <button class="button primary">
          <img src="assets/icons/download-white.svg" alt="" style="vertical-align:middle;margin-right:6px"/>
          Download
        </button>
        <button class="button ghost">
          <img src="assets/icons/bookmark.svg" alt="" style="vertical-align:middle;margin-right:6px"/>
          Save to Library
        </button>
      </div>

      <!-- Utility links -->
      <div class="resource-utility">
        <a href="#">
          <img src="assets/icons/flag.svg" alt="" width="13"/> Report an issue
        </a>
        <a href="#">
          <img src="assets/icons/quote.svg" alt="" width="13"/> Cite this resource
        </a>
      </div>
    </div>

    <!-- Right: metadata + related -->
    <div class="panel">
      <h1 class="resource-heading">Lorem Ipsum – Sample Learning Module Title</h1>

      <div class="resource-title-row">
        <span class="tag primary">Grade 6</span>
        <span class="tag">Mathematics</span>
        <span class="tag">SLM</span>
        <span class="badge success">
          <img src="assets/icons/seal-check.svg" alt="" width="13"/> QA Passed
        </span>
      </div>

      <p class="resource-description">
        Lorem ipsum dolor sit amet, consectetur adipiscing elit.
        Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.
        Sed nisi. Nulla quis sem at nibh elementum imperdiet.
      </p>

      <h3>Metadata</h3>
      <ul class="meta-list">
        <li>
          <span class="meta-key">MELC Code</span>
          <span class="meta-val">M6NS-Ia-1</span>
        </li>
        <li>
          <span class="meta-key">Quarter</span>
          <span class="meta-val">Quarter 1</span>
        </li>
        <li>
          <span class="meta-key">Language</span>
          <span class="meta-val">English</span>
        </li>
        <li>
          <span class="meta-key">Resource Type</span>
          <span class="meta-val">SLM (PDF)</span>
        </li>
        <li>
          <span class="meta-key">License</span>
          <span class="meta-val">CC BY-NC</span>
        </li>
        <li>
          <span class="meta-key">Version</span>
          <span class="meta-val">1.0 — Jan 2026</span>
        </li>
        <li>
          <span class="meta-key">Publisher</span>
          <span class="meta-val">Carcar City Division</span>
        </li>
        <li>
          <span class="meta-key">School Year</span>
          <span class="meta-val">2023–2024</span>
        </li>
      </ul>

      <h3>Related Resources</h3>
      <ul class="related-list">
        <li>
          <a href="#">
            <img src="assets/icons/file-text.svg" alt="" width="14"/>
            Worksheets – Fractions practice
          </a>
        </li>
        <li>
          <a href="#">
            <img src="assets/icons/play-circle.svg" alt="" width="14"/>
            Video – Fractions explained
          </a>
        </li>
      </ul>
    </div>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/resource.js"></script>
</body>
</html>