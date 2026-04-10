<?php
/**
 * DepEd LRMDS – Resource Detail Page
 * ------------------------------------
 * Accepts any file type and renders the appropriate preview.
 * In production, $resource would come from a DB query keyed on $_GET['id'].
 */

// ── Placeholder resource data (swap for DB fetch later) ──────────────────────
$resource = [
  'id'           => 1,
  'title'        => 'Fractions and Mixed Numbers – Grade 6 Learning Module',
  'description'  => 'This Self-Learning Module covers the concepts of fractions, mixed numbers, and their operations aligned to the Most Essential Learning Competencies (MELCs) for Grade 6 Mathematics, Quarter 1.',
  'grade'        => 'Grade 6',
  'subject'      => 'Mathematics',
  'type'         => 'SLM',          // SLM | TG | DLL | Video | Assessment | Image | etc.
  'qa_status'    => 'passed',       // passed | pending | flagged
  'melc_code'    => 'M6NS-Ia-1',
  'quarter'      => 'Quarter 1',
  'language'     => 'English',
  'license'      => 'CC BY-NC',
  'version'      => '1.0',
  'version_date' => 'Jan 2026',
  'publisher'    => 'Carcar City Division',
  'school_year'  => '2023–2024',
  'file_type'    => 'pdf',          // pdf | video | image | audio | pptx | docx | gdrive
  // For Google Drive files, store the file ID only:
  'drive_id'     => '1eZ02fRYrI4lb5E89h3XB5MDJU3MpllaN',
  // Direct file URL (used for non-Drive files):
  'file_url'     => '',
  'related'      => [
    ['icon' => 'file-text',   'label' => 'Worksheets – Fractions practice', 'href' => '#'],
    ['icon' => 'play-circle', 'label' => 'Video – Fractions explained',     'href' => '#'],
    ['icon' => 'file-text',   'label' => 'Answer Key – Quarter 1 SLM',      'href' => '#'],
  ],
];

// ── Derive the embed/preview based on file_type ───────────────────────────────
function build_preview(array $r): string {
  $type = strtolower($r['file_type']);

  // Google Drive – works for PDFs, Docs, Slides, Sheets stored on Drive
  if ($type === 'gdrive' || !empty($r['drive_id'])) {
    $id  = htmlspecialchars($r['drive_id'], ENT_QUOTES);
    $src = "https://drive.google.com/file/d/{$id}/preview";
    return <<<HTML
      <div class="preview-wrapper preview-doc">
        <iframe
          class="preview-frame"
          src="{$src}"
          allow="autoplay"
          allowfullscreen
          title="Resource preview"></iframe>
      </div>
HTML;
  }

  $url = htmlspecialchars($r['file_url'] ?? '', ENT_QUOTES);

  switch ($type) {
    case 'pdf':
      return <<<HTML
        <div class="preview-wrapper preview-doc">
          <iframe
            class="preview-frame"
            src="{$url}#toolbar=0&navpanes=0"
            title="PDF preview"></iframe>
        </div>
HTML;

    case 'video':
      // Supports direct MP4 or YouTube/Vimeo embeds
      $is_yt = str_contains($url, 'youtube') || str_contains($url, 'youtu.be');
      $is_vm = str_contains($url, 'vimeo');
      if ($is_yt || $is_vm) {
        return <<<HTML
          <div class="preview-wrapper preview-video">
            <iframe
              class="preview-frame"
              src="{$url}"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
              allowfullscreen
              title="Video preview"></iframe>
          </div>
HTML;
      }
      return <<<HTML
        <div class="preview-wrapper preview-video">
          <video class="preview-video-el" controls>
            <source src="{$url}">
            Your browser does not support the video tag.
          </video>
        </div>
HTML;

    case 'image':
      $alt = htmlspecialchars($r['title'], ENT_QUOTES);
      return <<<HTML
        <div class="preview-wrapper preview-image">
          <img src="{$url}" alt="{$alt}" class="preview-img"/>
        </div>
HTML;

    case 'audio':
      return <<<HTML
        <div class="preview-wrapper preview-audio">
          <div class="audio-placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>
            </svg>
            <p>Audio Resource</p>
          </div>
          <audio class="preview-audio-el" controls>
            <source src="{$url}">
          </audio>
        </div>
HTML;

    default:
      // Generic fallback – show a file icon and open-in-new-tab link
      $title = htmlspecialchars($r['title'], ENT_QUOTES);
      return <<<HTML
        <div class="preview-wrapper preview-generic">
          <div class="generic-placeholder">
            <svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24"
              fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
            </svg>
            <p class="generic-label">{$title}</p>
            <a href="{$url}" target="_blank" rel="noopener" class="button ghost small">
              Open file ↗
            </a>
          </div>
        </div>
HTML;
  }
}

// ── QA badge helper ───────────────────────────────────────────────────────────
function qa_badge(string $status): string {
  return match($status) {
    'passed'  => '<span class="badge success">✔ QA Passed</span>',
    'pending' => '<span class="badge warn">⏳ QA Pending</span>',
    'flagged' => '<span class="badge danger">⚠ Flagged</span>',
    default   => '',
  };
}

// ── Download URL ──────────────────────────────────────────────────────────────
$download_url = !empty($resource['drive_id'])
  ? "https://drive.google.com/uc?export=download&id={$resource['drive_id']}"
  : ($resource['file_url'] ?? '#');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= htmlspecialchars($resource['title']) ?> – DepEd LRMDS</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/resource.css"/>
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- Breadcrumb strip -->
<div class="breadcrumb-bar container">
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="index.php">Home</a>
    <span class="bc-sep">›</span>
    <a href="search.php">Resources</a>
    <span class="bc-sep">›</span>
    <span><?= htmlspecialchars($resource['title']) ?></span>
  </nav>
</div>

<section class="section detail-section">
  <div class="detail-wrap">

  <!-- ── Main detail grid ──────────────────────────────────────────────────── -->
  <div class="detail">

    <!-- ── LEFT: large preview ───────────────────────────────────────────── -->
    <div class="panel panel-preview">

      <?= build_preview($resource) ?>

      <!-- Action buttons sit below the preview -->
      <div class="resource-actions">
        <a
          href="<?= htmlspecialchars($download_url) ?>"
          class="button primary"
          download
          data-download-btn>
          <img src="assets/icons/download-white.svg" alt="" style="vertical-align:middle;margin-right:6px"/>
          Download
        </a>
        <button class="button ghost" data-save-btn>
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

    </div><!-- /panel-preview -->

    <!-- ── RIGHT: metadata panel ─────────────────────────────────────────── -->
    <div class="panel panel-meta">

      <h1 class="resource-heading"><?= htmlspecialchars($resource['title']) ?></h1>

      <div class="resource-title-row">
        <span class="tag primary"><?= htmlspecialchars($resource['grade']) ?></span>
        <span class="tag"><?= htmlspecialchars($resource['subject']) ?></span>
        <span class="tag"><?= htmlspecialchars($resource['type']) ?></span>
        <?= qa_badge($resource['qa_status']) ?>
      </div>

      <p class="resource-description"><?= htmlspecialchars($resource['description']) ?></p>

      <h3>Metadata</h3>
      <ul class="meta-list">
        <li>
          <span class="meta-key">MELC Code</span>
          <span class="meta-val"><?= htmlspecialchars($resource['melc_code']) ?></span>
        </li>
        <li>
          <span class="meta-key">Quarter</span>
          <span class="meta-val"><?= htmlspecialchars($resource['quarter']) ?></span>
        </li>
        <li>
          <span class="meta-key">Language</span>
          <span class="meta-val"><?= htmlspecialchars($resource['language']) ?></span>
        </li>
        <li>
          <span class="meta-key">Resource Type</span>
          <span class="meta-val"><?= htmlspecialchars($resource['type']) ?> (<?= strtoupper(htmlspecialchars($resource['file_type'])) ?>)</span>
        </li>
        <li>
          <span class="meta-key">License</span>
          <span class="meta-val"><?= htmlspecialchars($resource['license']) ?></span>
        </li>
        <li>
          <span class="meta-key">Version</span>
          <span class="meta-val"><?= htmlspecialchars($resource['version']) ?> — <?= htmlspecialchars($resource['version_date']) ?></span>
        </li>
        <li>
          <span class="meta-key">Publisher</span>
          <span class="meta-val"><?= htmlspecialchars($resource['publisher']) ?></span>
        </li>
        <li>
          <span class="meta-key">School Year</span>
          <span class="meta-val"><?= htmlspecialchars($resource['school_year']) ?></span>
        </li>
      </ul>

      <?php if (!empty($resource['related'])): ?>
      <h3>Related Resources</h3>
      <ul class="related-list">
        <?php foreach ($resource['related'] as $rel): ?>
        <li>
          <a href="<?= htmlspecialchars($rel['href']) ?>">
            <img src="assets/icons/<?= htmlspecialchars($rel['icon']) ?>.svg" alt="" width="14"/>
            <?= htmlspecialchars($rel['label']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

    </div><!-- /panel-meta -->

  </div><!-- /detail -->
  </div><!-- /detail-wrap -->
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/resource.js"></script>
</body>
</html>