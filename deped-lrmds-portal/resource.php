<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Resource Detail</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="section container">
  <nav aria-label="Breadcrumb" style="margin-bottom:8px;color:#6B7280;font-size:14px">
    <a href="index.php">Home</a> / <a href="search.php">Resources</a> / <span>Resource Title</span>
  </nav>

  <div class="detail">
    <div class="panel">
      <img src="assets/img/placeholder-thumb.svg" alt="Resource preview" style="width:100%;height:auto;border-radius:8px;border:1px solid var(--border)"/>
      <div class="actions">
        <button class="button primary">
          <img src="assets/icons/download-white.svg" alt="" style="vertical-align:middle;margin-right:6px">Download
        </button>
        <button class="button ghost">
          <img src="assets/icons/bookmark.svg" alt="" style="vertical-align:middle;margin-right:6px">Save to Library
        </button>
      </div>
    </div>

    <div class="panel">
      <h1 style="margin-top:0">Lorem Ipsum – Sample Learning Module Title</h1>

      <div style="display:flex;gap:10px;flex-wrap:wrap;margin:6px 0 12px">
        <span class="tag">Grade 6</span>
        <span class="tag">Mathematics</span>
        <span class="badge success">
          <img src="assets/icons/seal-check.svg" alt="" style="margin-right:6px">QA Passed
        </span>
      </div>

      <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio. Praesent libero. Sed cursus ante dapibus diam.</p>

      <h3>Metadata</h3>
      <ul class="meta-list">
        <li><strong>MELC:</strong> M6NS-Ia-1</li>
        <li><strong>Quarter:</strong> 1</li>
        <li><strong>Language:</strong> English</li>
        <li><strong>Type:</strong> SLM (PDF)</li>
        <li><strong>License:</strong> CC BY-NC</li>
        <li><strong>Version:</strong> 1.0 (Jan 2026)</li>
      </ul>

      <h3>Related Resources</h3>
      <ul>
        <li><a href="#">Worksheets – Fractions practice</a></li>
        <li><a href="#">Video – Fractions explained</a></li>
      </ul>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
</body>
</html>