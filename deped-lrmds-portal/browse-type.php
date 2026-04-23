<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Browse by Resource Type</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<section class="section container">
  <h1>Browse by Resource Type</h1>
  <div class="tiles">
    <a class="tile" href="search.php?type=SLM">
      <span class="icon"><img src="assets/icons/folders.svg" alt=""></span>
      <div><div class="label">Self-Learning Modules</div><div class="desc">SLMs for independent study</div></div>
    </a>

    <a class="tile" href="search.php?type=TG">
      <span class="icon"><img src="assets/icons/chalkboard-teacher.svg" alt=""></span>
      <div><div class="label">Teacher's Guides</div><div class="desc">TG / Learner's Materials</div></div>
    </a>

    <a class="tile" href="search.php?type=DLL">
      <span class="icon"><img src="assets/icons/calendar.svg" alt=""></span>
      <div><div class="label">DLL / DLP</div><div class="desc">Daily lesson plans & logs</div></div>
    </a>

    <a class="tile" href="search.php?type=Video">
      <span class="icon"><img src="assets/icons/play-circle.svg" alt=""></span>
      <div><div class="label">Video Lessons</div><div class="desc">Stream & downloadable videos</div></div>
    </a>

    <a class="tile" href="search.php?type=Assessment">
      <span class="icon"><img src="assets/icons/clipboard-text.svg" alt=""></span>
      <div><div class="label">Assessments</div><div class="desc">Formative & summative tests</div></div>
    </a>

    <a class="tile" href="search.php?type=OER">
      <span class="icon"><img src="assets/icons/globe.svg" alt=""></span>
      <div><div class="label">OER</div><div class="desc">Open educational resources</div></div>
    </a>
  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
</body>
</html>