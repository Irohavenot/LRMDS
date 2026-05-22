<?php
/**
 * api/submit-news.php
 * DepEd LRMDS – News / Memorandum submission handler
 *
 * Files are uploaded to OneDrive under:
 *   Documents/deped/News/{Type}/{Year}/{filename}
 *
 *   announcement → News/Announcements/2026/
 *   memo         → News/Memorandums/2026/
 *   program      → News/Program-Updates/2026/
 *   event        → News/Events/2026/
 */

header('Content-Type: application/json');
require_once __DIR__ . '/onedrive-helper.php';

define('NEWS_LOG_FILE', __DIR__ . '/../data/news-submissions.json');
define('MAX_SIZE',      50 * 1024 * 1024);
define('ALLOWED_EXT',   ['pdf','docx','pptx','jpg','jpeg','png']);

function json_out(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $data));
    exit;
}
function sanitize(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Method not allowed.');

/* ── Text fields ──────────────────────────────────────────────────────── */
$type     = sanitize($_POST['type']    ?? '');   // announcement|memo|program|event
$title    = sanitize($_POST['title']   ?? '');
$date     = sanitize($_POST['date']    ?? '');
$summary  = sanitize($_POST['summary'] ?? '');
$poster   = sanitize($_POST['poster']  ?? '');
$email    = sanitize($_POST['email']   ?? '');
$isDraft  = ($_POST['isDraft'] ?? '0') === '1';

if (!$title || !$date || !$summary || !$poster || !$email) {
    json_out(false, 'Missing required fields.');
}

$memoNumber  = sanitize($_POST['memo_number']  ?? '');
$memoSeries  = sanitize($_POST['memo_series']  ?? '');
$memoTo      = sanitize($_POST['memo_to']      ?? '');
$memoFrom    = sanitize($_POST['memo_from']    ?? '');
$memoUrgency = sanitize($_POST['memo_urgency'] ?? 'routine');

if ($type === 'memo' && !$memoNumber) json_out(false, 'Memorandum number is required.');

$eventStart    = sanitize($_POST['event_start']    ?? '');
$eventEnd      = sanitize($_POST['event_end']      ?? '');
$eventVenue    = sanitize($_POST['event_venue']    ?? '');
$eventRegister = sanitize($_POST['event_register'] ?? '');
$audience      = sanitize($_POST['audience']       ?? 'all');
$pin           = ($_POST['pin'] ?? '0') === '1';
$tags          = sanitize($_POST['tags']           ?? '');

/* ── File upload → OneDrive ───────────────────────────────────────────── */
$oneDriveItemId = null;
$oneDriveFile   = null;
$oneDrivePath   = null;

if (!empty($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['attachment'];

    if ($file['error'] !== UPLOAD_ERR_OK)  json_out(false, 'File upload error: code ' . $file['error']);
    if ($file['size'] > MAX_SIZE)          json_out(false, 'File exceeds the 50 MB limit.');

    $origName = basename($file['name']);
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXT, true)) {
        json_out(false, 'File type not allowed. Accepted: ' . implode(', ', ALLOWED_EXT));
    }

    $safeBase       = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    $remoteFileName = date('Ymd_His') . '_' . $safeBase . '.' . $ext;

    // Build subfolder path: News/Memorandums/2026/ etc.
    $subfolder = buildNewsPath($type, $date);

    try {
        $oneDriveItemId = uploadToOneDrive($file['tmp_name'], $subfolder, $remoteFileName);
        $oneDriveFile   = $remoteFileName;
        $oneDrivePath   = $subfolder . '/' . $remoteFileName;
    } catch (RuntimeException $e) {
        json_out(false, 'OneDrive upload failed: ' . $e->getMessage());
    }

} elseif ($type === 'memo') {
    json_out(false, 'A PDF attachment is required for memorandums.');
}

/* ── Reference ID & record ────────────────────────────────────────────── */
$refId  = 'NEWS-' . date('Y') . '-' . str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
$record = [
    'ref_id'         => $refId,
    'type'           => $type,
    'is_draft'       => $isDraft,
    'title'          => $title,
    'date'           => $date,
    'summary'        => $summary,
    'poster_name'    => $poster,
    'poster_email'   => $email,
    'onedrive_file'  => $oneDriveFile,
    'onedrive_path'  => $oneDrivePath,   // full path for easy reference
    'onedrive_id'    => $oneDriveItemId,
    'audience'       => $audience,
    'pinned'         => $pin,
    'tags'           => array_filter(array_map('trim', explode(',', $tags))),
    'submitted_at'   => date('c'),
    'status'         => $isDraft ? 'draft' : 'pending_review',
    'memo_number'    => $memoNumber,
    'memo_series'    => $memoSeries,
    'memo_to'        => $memoTo,
    'memo_from'      => $memoFrom,
    'memo_urgency'   => $memoUrgency,
    'event_start'    => $eventStart,
    'event_end'      => $eventEnd,
    'event_venue'    => $eventVenue,
    'event_register' => $eventRegister,
];

/* ── Persist ──────────────────────────────────────────────────────────── */
$dataDir  = dirname(NEWS_LOG_FILE);
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);
$existing = file_exists(NEWS_LOG_FILE) ? (json_decode(file_get_contents(NEWS_LOG_FILE), true) ?? []) : [];
$existing[] = $record;
file_put_contents(NEWS_LOG_FILE, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

json_out(true, $isDraft ? 'Draft saved.' : 'Submission received.', [
    'ref_id'       => $refId,
    'onedrive_path'=> $oneDrivePath,
]);