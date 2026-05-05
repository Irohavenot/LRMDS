<?php
/**
 * api/upload-resource.php
 * DepEd LRMDS – Learning Resource submission handler
 *
 * Files are uploaded to OneDrive under:
 *   Documents/deped/Resources/{Subject}/{Grade}/{Quarter}/{filename}
 *
 * Examples:
 *   Resources/Mathematics/Grade-6/Q1/20260504_SLM_Fractions.pdf
 *   Resources/Araling-Panlipunan/Grade-7/Q2/20260504_Video_Asya.mp4
 *   Resources/MTB-MLE/Grade-Kinder/All-Quarters/20260504_SLM_Salita.pdf
 *   Resources/SHS-Core/Grade-11/Q1/20260504_SLM_Earth-Science.pdf
 */

header('Content-Type: application/json');
require_once __DIR__ . '/onedrive-helper.php';

define('RES_LOG_FILE',   __DIR__ . '/../data/resource-submissions.json');
define('RES_MAX_SIZE',   100 * 1024 * 1024);
define('RES_ALLOWED_EXT', ['pdf','docx','pptx','mp4','mp3','zip','html']);

function json_out(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'message' => $msg], $data));
    exit;
}
function sanitize(string $v): string {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false, 'Method not allowed.');

/* ── Text fields ── */
$title    = sanitize($_POST['title']    ?? '');
$type     = sanitize($_POST['type']     ?? '');
$grade    = sanitize($_POST['grade']    ?? '');    // "6", "Kinder", "11"
$subject  = sanitize($_POST['subject']  ?? '');    // "Mathematics", "Araling Panlipunan" etc.
$language = sanitize($_POST['language'] ?? '');
$quarter  = sanitize($_POST['quarter']  ?? '');    // "Q1"–"Q4" or ""
$sy       = sanitize($_POST['sy']       ?? '');
$desc     = sanitize($_POST['desc']     ?? '');
$url      = sanitize($_POST['url']      ?? '');
$version  = sanitize($_POST['version']  ?? '1.0');
$melcs    = $_POST['melcs']   ?? [];
$authors  = $_POST['authors'] ?? [];
$license  = sanitize($_POST['license']  ?? 'DepEd');
$region   = sanitize($_POST['region']   ?? '');
$division = sanitize($_POST['division'] ?? '');

if (!$title || !$type || !$grade || !$subject || !$language || !$desc) {
    json_out(false, 'Missing required fields.');
}
if (empty($_FILES['file']) && !$url) {
    json_out(false, 'A file upload or URL is required.');
}

/* ── File upload → OneDrive ── */
$oneDriveItemId = null;
$oneDriveFile   = null;
$oneDrivePath   = null;

if (!empty($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) json_out(false, 'Upload error: ' . $file['error']);
    if ($file['size'] > RES_MAX_SIZE)     json_out(false, 'File exceeds the 100 MB limit.');

    $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    if (!in_array($ext, RES_ALLOWED_EXT, true)) {
        json_out(false, 'File type not allowed. Accepted: ' . implode(', ', RES_ALLOWED_EXT));
    }

    $safeBase       = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
    $remoteFileName = date('Ymd_His') . '_' . $safeBase . '.' . $ext;

    // Build subfolder: Resources/Mathematics/Grade-6/Q1/
    $subfolder = buildResourcePath($subject, $grade, $quarter);

    try {
        $oneDriveItemId = uploadToOneDrive($file['tmp_name'], $subfolder, $remoteFileName);
        $oneDriveFile   = $remoteFileName;
        $oneDrivePath   = $subfolder . '/' . $remoteFileName;
    } catch (RuntimeException $e) {
        json_out(false, 'OneDrive upload failed: ' . $e->getMessage());
    }
}

/* ── Build record ── */
$refId  = 'LRMDS-' . date('Y') . '-' . str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
$record = [
    'ref_id'        => $refId,
    'title'         => $title,
    'type'          => $type,
    'grade'         => $grade,
    'subject'       => $subject,
    'language'      => $language,
    'quarter'       => $quarter,
    'school_year'   => $sy,
    'description'   => $desc,
    'onedrive_file' => $oneDriveFile,
    'onedrive_path' => $oneDrivePath,   // full path for easy reference
    'onedrive_id'   => $oneDriveItemId,
    'url'           => $url,
    'version'       => $version,
    'melcs'         => array_filter((array)$melcs),
    'authors'       => array_filter((array)$authors),
    'license'       => $license,
    'region'        => $region,
    'division'      => $division,
    'qa_status'     => 'pending',
    'submitted_at'  => date('c'),
];

/* ── Persist ── */
$dir = dirname(RES_LOG_FILE);
if (!is_dir($dir)) mkdir($dir, 0755, true);
$existing   = file_exists(RES_LOG_FILE) ? (json_decode(file_get_contents(RES_LOG_FILE), true) ?? []) : [];
$existing[] = $record;
file_put_contents(RES_LOG_FILE, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

json_out(true, 'Resource submitted for QA review.', [
    'ref_id'        => $refId,
    'onedrive_path' => $oneDrivePath,
]);