<?php
/**
 * DepEd LRMDS – google_callback.php
 *
 * Google redirects here after the user approves (or denies) OAuth consent.
 * Uses raw curl — no Composer / Google SDK needed.
 *
 * Flow:
 *   A) Exchange auth code → access token  (curl POST to Google)
 *   B) Fetch user info    → email, name   (curl GET  to Google)
 *   C) Look up email in DB
 *      → FOUND  : set session, go to index.php
 *      → NOT FOUND: store Google data in session, go to google_complete.php
 */
require __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/.env');
session_start();

// // TEMPORARY DEBUG — remove after fixing
// ini_set('display_errors', 1);
// error_reporting(E_ALL);


// ── Same constants as google_oauth.php ──────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI',  'http://localhost/LRMDS/deped-lrmds-portal/google_callback.php');

// ── DB ───────────────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Roles that require TOTP — must match signin_handler.php
define('TOTP_ROLES', ['teacher', 'school-head', 'developer']);

/* ── Helper: redirect with an error flash ─────────────────── */
function fail(string $msg): never {
    die('<h2 style="color:red">OAuth Error:</h2><pre>' . htmlspecialchars($msg) . '</pre>'
      . '<pre>SESSION: ' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>'
      . '<pre>GET: ' . htmlspecialchars(print_r($_GET, true)) . '</pre>');
}

/* ── 1. CSRF check ─────────────────────────────────────────── */
$state = $_GET['state'] ?? '';
if (empty($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $state)) {
    fail('OAuth state mismatch. Please try signing in again.');
}
unset($_SESSION['oauth_state']);

/* ── 2. Check for user denial ──────────────────────────────── */
if (isset($_GET['error'])) {
    fail('Google sign-in was cancelled or denied.');
}

$code = $_GET['code'] ?? '';
if ($code === '') {
    fail('No authorisation code received from Google.');
}

/* ── 3. Exchange code for tokens ───────────────────────────── */
$token_response = curl_json_post('https://oauth2.googleapis.com/token', [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code',
]);

if (empty($token_response['access_token'])) {
    error_log('LRMDS Google token error: ' . json_encode($token_response));
    fail('Could not retrieve access token from Google. Please try again.');
}

$access_token = $token_response['access_token'];

/* ── 4. Fetch Google user profile ──────────────────────────── */
$google_user = curl_json_get(
    'https://www.googleapis.com/oauth2/v3/userinfo',
    $access_token
);

if (empty($google_user['email'])) {
    error_log('LRMDS Google userinfo error: ' . json_encode($google_user));
    fail('Could not retrieve your profile from Google. Please try again.');
}

$google_id    = $google_user['sub']            ?? '';
$google_email = strtolower($google_user['email'] ?? '');
$google_fname = $google_user['given_name']     ?? '';
$google_lname = $google_user['family_name']    ?? '';
$google_pic   = $google_user['picture']        ?? '';

/* ── 5. DB connection ──────────────────────────────────────── */
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
    error_log('LRMDS google_callback DB: ' . $e->getMessage());
    fail('Database connection failed. Make sure XAMPP MySQL is running.');
}

/* ── 6. Look up existing user ──────────────────────────────── */
// Match first by google_id (most reliable), then fall back to email
// Also fetch totp_enabled + totp_secret so we can mirror signin_handler.php logic
$stmt = $pdo->prepare('
    SELECT id, first_name, email, role, status, totp_enabled, totp_secret
    FROM   users
    WHERE  google_id = ?
       OR  email     = ?
    LIMIT  1
');
$stmt->execute([$google_id, $google_email]);
$user = $stmt->fetch();

/* ── 7a. RETURNING USER ────────────────────────────────────── */
if ($user) {
    // If found by email but google_id not yet stored, save it now
    if ($google_id) {
        $pdo->prepare('UPDATE users SET google_id = ?, last_login = NOW() WHERE id = ?')
            ->execute([$google_id, $user['id']]);
    } else {
        $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')
            ->execute([$user['id']]);
    }

    // Check account status — same messages as signin_handler.php
    if ($user['status'] === 'suspended') {
        fail('Your account has been suspended. Please contact the LRMDS helpdesk.');
    }
    if ($user['status'] === 'pending') {
        fail('Your account is pending administrator verification. You will be notified once approved.');
    }

    // ── TOTP check — mirrors signin_handler.php exactly ──────
    if (in_array($user['role'], TOTP_ROLES, true)) {

        if (!$user['totp_enabled'] || !$user['totp_secret']) {
            // Role needs TOTP but it was never set up — send to setup
            // We store user_id in session; totp_setup.php will read it
            // NOTE: totp_setup.php normally expects pending_registration,
            // so we route via a lightweight bridge key instead.
            $_SESSION['totp_setup_user_id'] = $user['id'];
            fail(
                'Your account requires two-factor authentication to be configured. ' .
                'Please sign in with your password to complete TOTP setup.'
            );
        }

        // TOTP enabled — hold in pending session, redirect to verify page
        $_SESSION['totp_pending_user_id'] = $user['id'];
        header('Location: totp_verify.php');
        exit;
    }

    // ── No TOTP needed — sign in directly ────────────────────
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['first_name'];
    $_SESSION['user']      = $user['email'];

    header('Location: index.php');
    exit;
}

/* ── 7b. NEW USER — send to completion form ────────────────── */
$_SESSION['google_pending'] = [
    'google_id' => $google_id,
    'email'     => $google_email,
    'fname'     => $google_fname,
    'lname'     => $google_lname,
    'picture'   => $google_pic,
    'expires_at'=> time() + 900,   // 15-minute window to complete
];

header('Location: google_complete.php');
exit;


/* ═══════════════════════════════════════════════════════════════
   Utility functions — raw curl, no library needed
═══════════════════════════════════════════════════════════════ */

function curl_json_post(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}

function curl_json_get(string $url, string $access_token): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return json_decode($body ?: '{}', true) ?? [];
}