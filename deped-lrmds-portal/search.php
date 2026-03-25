<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Search</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/search.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="section" style="background:#F9FAFB;border-bottom:1px solid var(--border)">
  <div class="container">
    <form data-search aria-label="Filter results">
      <div class="filters">
        <input class="input" type="search" name="q" placeholder="Keywords or MELC…" aria-label="Keywords or MELC code"/>
        <select class="select" name="grade" aria-label="Grade level">
          <option value="">All Grades</option>
          <option>Kinder</option>
          <option>1</option><option>2</option><option>3</option><option>4</option>
          <option>5</option><option>6</option><option>7</option><option>8</option>
          <option>9</option><option>10</option><option>11</option><option>12</option>
        </select>
        <select class="select" name="subject" aria-label="Learning area">
          <option value="">All Learning Areas</option>
          <option>English</option>
          <option>Filipino</option>
          <option>Mathematics</option>
          <option>Science</option>
          <option>Araling Panlipunan</option>
        </select>
        <select class="select" name="type" aria-label="Resource type">
          <option value="">All Types</option>
          <option>SLM</option>
          <option>TG</option>
          <option>DLL</option>
          <option>Video</option>
          <option>Assessment</option>
        </select>
        <input class="input" type="text" name="melc" placeholder="MELC code" aria-label="MELC code"/>
        <button class="button primary" type="submit">
          <img src="assets/icons/magnifying-glass.svg" alt="" style="vertical-align:middle;margin-right:6px"/>Apply
        </button>
      </div>
    </form>
  </div>
</section>

<section class="section container">
  <h2>Search Results</h2>
  <div id="results" class="results" aria-live="polite"></div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/search.js"></script>
</body>
</html>