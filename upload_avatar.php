<?php
/**
 * upload_avatar.php
 * Handles profile photo uploads for both profile_panel.php and profile_edit.php.
 * Called via fetch() POST — always returns JSON.
 *
 * Requires: session_start() already called, $_SESSION['user_id'] set.
 */

session_start();
header('Content-Type: application/json');

// Must be signed in
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

// Must be a POST with a file
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$uid = (int) $_SESSION['user_id'];
$f   = $_FILES['avatar'];

// ── Validate ──────────────────────────────────────────────────
if ($f['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $f['error']]);
    exit;
}

$allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($f['tmp_name']);

if (!in_array($mime, $allowed_mime, true)) {
    echo json_encode(['success' => false, 'error' => 'Only JPEG, PNG, GIF, or WebP images are allowed.']);
    exit;
}

if ($f['size'] > 2 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Image must be 2 MB or smaller.']);
    exit;
}

// ── Save file ─────────────────────────────────────────────────
$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'jpg',
};

$upload_dir = __DIR__ . '/uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename    = 'avatar_' . $uid . '_' . time() . '.' . $ext;
$destination = $upload_dir . $filename;
$avatar_path = 'uploads/avatars/' . $filename;

if (!move_uploaded_file($f['tmp_name'], $destination)) {
    echo json_encode(['success' => false, 'error' => 'Could not save the file. Check server permissions.']);
    exit;
}

// ── Update database ───────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );

    // Delete old avatar file first
    $old = $pdo->prepare('SELECT avatar FROM users WHERE id = ? LIMIT 1');
    $old->execute([$uid]);
    $old_row = $old->fetch();
    if (!empty($old_row['avatar'])) {
        $old_file = __DIR__ . '/' . ltrim($old_row['avatar'], '/');
        if (file_exists($old_file)) @unlink($old_file);
    }

    // Save new path
    $upd = $pdo->prepare('UPDATE users SET avatar = ? WHERE id = ?');
    $upd->execute([$avatar_path, $uid]);

} catch (PDOException $e) {
    // File was saved but DB failed — clean up the uploaded file
    @unlink($destination);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

echo json_encode(['success' => true, 'url' => $avatar_path]);