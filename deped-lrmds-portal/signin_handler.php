<?php
/**
 * DepEd LRMDS – signin_handler.php
 * Receives POST from signin.js (fetch) or signin-modal.js (fetch).
 * Returns JSON so both the standalone page and the modal can use it.
 *
 * Responses:
 *   { "ok": true,  "redirect": "dashboard.php" }          — no TOTP
 *   { "ok": true,  "redirect": "totp_verify.php" }        — needs TOTP
 *   { "ok": false, "field": "email",   "msg": "..." }     — field error
 *   { "ok": false, "field": "password","msg": "..." }     — field error
 *   { "ok": false, "field": "general", "msg": "..." }     — other error
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// ── Config ────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'lrmds');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Roles that must pass TOTP after password check
define('TOTP_ROLES', ['teacher', 'school-head', 'developer']);

// Max failed attempts before lockout
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// ── Only accept POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'field' => 'general', 'msg' => 'Method not allowed.']);
    exit;
}

// ── Collect input ─────────────────────────────────────────────
$email    = trim($_POST['email']    ?? '');
$password =       $_POST['password'] ?? '';

// ── Basic presence validation ─────────────────────────────────
if ($email === '') {
    echo json_encode(['ok' => false, 'field' => 'email', 'msg' => 'Email or Employee ID is required.']);
    exit;
}
if ($password === '') {
    echo json_encode(['ok' => false, 'field' => 'password', 'msg' => 'Password is required.']);
    exit;
}

// ── DB connection ─────────────────────────────────────────────
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('LRMDS signin DB: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'field' => 'general', 'msg' => 'Database connection failed. Make sure XAMPP MySQL is running.']);
    exit;
}

// ── Rate limiting (DB-session hybrid) ────────────────────────
// We use PHP session so we don't need an extra DB table for now.
$attempt_key = 'login_attempts_' . md5(strtolower($email));
$lockout_key = 'login_lockout_'  . md5(strtolower($email));

if (!empty($_SESSION[$lockout_key]) && time() < $_SESSION[$lockout_key]) {
    $mins = ceil(($_SESSION[$lockout_key] - time()) / 60);
    echo json_encode([
        'ok'    => false,
        'field' => 'general',
        'msg'   => "Too many failed attempts. Please wait {$mins} minute(s) before trying again.",
    ]);
    exit;
}

// ── Look up user by email ─────────────────────────────────────
$stmt = $pdo->prepare('
    SELECT id, first_name, last_name, email, password_hash,
           role, status, totp_enabled, totp_secret
    FROM   users
    WHERE  email = ?
    LIMIT  1
');
$stmt->execute([strtolower($email)]);
$user = $stmt->fetch();

// ── Verify password ───────────────────────────────────────────
if (!$user || !password_verify($password, $user['password_hash'])) {
    // Increment attempt counter
    $_SESSION[$attempt_key] = ($_SESSION[$attempt_key] ?? 0) + 1;

    if ($_SESSION[$attempt_key] >= MAX_ATTEMPTS) {
        $_SESSION[$lockout_key] = time() + (LOCKOUT_MINUTES * 60);
        unset($_SESSION[$attempt_key]);
        echo json_encode([
            'ok'    => false,
            'field' => 'general',
            'msg'   => 'Too many failed attempts. Your account is temporarily locked for ' . LOCKOUT_MINUTES . ' minutes.',
        ]);
    } else {
        $left = MAX_ATTEMPTS - $_SESSION[$attempt_key];
        echo json_encode([
            'ok'    => false,
            'field' => 'password',
            'msg'   => "Incorrect email or password. {$left} attempt(s) remaining.",
        ]);
    }
    exit;
}

// ── Reset attempt counter on success ─────────────────────────
unset($_SESSION[$attempt_key], $_SESSION[$lockout_key]);

// ── Check account status ──────────────────────────────────────
if ($user['status'] === 'pending') {
    echo json_encode([
        'ok'    => false,
        'field' => 'general',
        'msg'   => 'Your account is pending administrator verification. You will be notified once approved.',
    ]);
    exit;
}

if ($user['status'] === 'suspended') {
    echo json_encode([
        'ok'    => false,
        'field' => 'general',
        'msg'   => 'Your account has been suspended. Please contact the LRMDS helpdesk.',
    ]);
    exit;
}

// ── Decide redirect based on role + TOTP ─────────────────────
if (in_array($user['role'], TOTP_ROLES, true)) {

    if (!$user['totp_enabled']) {
        // TOTP role but setup was never completed — send them to setup
        $_SESSION['totp_setup_user_id'] = $user['id'];
        echo json_encode(['ok' => true, 'redirect' => 'totp_setup.php']);
        exit;
    }

    // TOTP is enabled — hold credentials in session until code is verified
    $_SESSION['totp_pending_user_id'] = $user['id'];
    echo json_encode(['ok' => true, 'redirect' => 'totp_verify.php']);
    exit;
}

// ── No TOTP needed — sign in directly ────────────────────────
$_SESSION['user_id']    = $user['id'];
$_SESSION['user_role']  = $user['role'];
$_SESSION['user_name']  = $user['first_name'];
$_SESSION['user']       = $user['email'];   // keeps header.php check working

// Update last login timestamp
$pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
    ->execute([$user['id']]);

echo json_encode(['ok' => true, 'redirect' => 'index.php']);
exit;