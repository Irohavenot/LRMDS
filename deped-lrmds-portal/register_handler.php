<?php
/**
 * DepEd LRMDS – register_handler.php
 * Handles POST from register.php.
 * Returns JSON: { "success": true, "redirect": "..." }
 *                or { "success": false, "errors": {...} }
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

define('DB_HOST', 'localhost');
define('DB_NAME', 'lrmds');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Roles that must complete TOTP setup after registering
define('TOTP_ROLES', ['teacher', 'school-head', 'developer']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

/* ── Collect & sanitize ── */
$email       = trim($_POST['email']       ?? '');
$password    = $_POST['password']         ?? '';
$fname       = trim($_POST['fname']       ?? '');
$lname       = trim($_POST['lname']       ?? '');
$region      = trim($_POST['region']      ?? '');
$division    = trim($_POST['division']    ?? '');
$role        = trim($_POST['role']        ?? '');
$employee_id = trim($_POST['employee_id'] ?? '');

// Role-specific extras
$grade_level  = trim($_POST['grade_level']  ?? '');
$subjects     = trim($_POST['subjects']     ?? '');
$school_name  = trim($_POST['school_name']  ?? '');
$lrn          = trim($_POST['lrn']          ?? '');
$child_grade  = trim($_POST['child_grade']  ?? '');
$child_school = trim($_POST['child_school'] ?? '');
$position     = trim($_POST['position']     ?? '');
$affiliation  = trim($_POST['affiliation']  ?? '');
$dev_position = trim($_POST['dev_position'] ?? '');
$dev_types    = trim($_POST['dev_types']    ?? '');

/* ── Validation ── */
$errors = [];

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
}

if ($password === '') {
    $errors['password'] = 'Password is required.';
} elseif (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
}

if ($fname === '') $errors['fname']  = 'First name is required.';
if ($lname === '') $errors['lname']  = 'Last name is required.';
if ($region === '') $errors['region'] = 'Please select your region.';

$allowed_roles = ['teacher', 'learner', 'parent', 'school-head', 'developer'];
if (!in_array($role, $allowed_roles, true)) {
    $errors['role'] = 'Please select a valid role.';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

/* ── DB connection ── */
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

/* ── Duplicate email check ── */
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'errors' => ['email' => 'An account with this email already exists.']]);
    exit;
}

/* ── Status & password hash ── */
$staff_roles   = ['school-head', 'developer'];
$status        = in_array($role, $staff_roles, true) ? 'pending' : 'active';
$password_hash = password_hash($password, PASSWORD_BCRYPT);

/* ── Meta JSON ── */
$meta = [];
if ($grade_level)  $meta['grade_level']  = $grade_level;
if ($subjects)     $meta['subjects']     = $subjects;
if ($school_name)  $meta['school_name']  = $school_name;
if ($lrn)          $meta['lrn']          = $lrn;
if ($child_grade)  $meta['child_grade']  = $child_grade;
if ($child_school) $meta['child_school'] = $child_school;
if ($position)     $meta['position']     = $position;
if ($affiliation)  $meta['affiliation']  = $affiliation;
if ($dev_position) $meta['dev_position'] = $dev_position;
if ($dev_types)    $meta['dev_types']    = $dev_types;
$meta_json = !empty($meta) ? json_encode($meta) : null;

/* ── Insert user ── */
/* ── TOTP roles: store in session, insert to DB only after TOTP confirmed ── */
if (in_array($role, TOTP_ROLES, true)) {
    $_SESSION['pending_registration'] = [
        'email'       => $email,
        'password'    => $password_hash,
        'fname'       => $fname,
        'lname'       => $lname,
        'role'        => $role,
        'status'      => $status,
        'region'      => $region,
        'division'    => $division    ?: null,
        'employee_id' => $employee_id ?: null,
        'meta'        => $meta_json,
        'expires_at'  => time() + 1800,
    ];

    echo json_encode([
        'success'       => true,
        'requires_totp' => true,
        'redirect'      => 'totp_setup.php',
        'message'       => 'Please set up two-factor authentication to complete registration.',
    ]);
    exit;
}

/* ── Non-TOTP roles: insert to DB now ── */
try {
    $insert = $pdo->prepare('
        INSERT INTO users
            (email, password_hash, first_name, last_name, role, status,
             region, division, employee_id, meta, created_at)
        VALUES
            (:email, :password_hash, :first_name, :last_name, :role, :status,
             :region, :division, :employee_id, :meta, NOW())
    ');
    $insert->execute([
        ':email'         => $email,
        ':password_hash' => $password_hash,
        ':first_name'    => $fname,
        ':last_name'     => $lname,
        ':role'          => $role,
        ':status'        => $status,
        ':region'        => $region,
        ':division'      => $division    ?: null,
        ':employee_id'   => $employee_id ?: null,
        ':meta'          => $meta_json,
    ]);
} catch (PDOException $e) {
    error_log('LRMDS insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create account. Please try again.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'pending'  => ($status === 'pending'),
    'redirect' => 'signin.php',
    'message'  => $status === 'pending'
        ? 'Account submitted. An administrator will verify your role before full access is granted.'
        : 'Account created successfully. You can now sign in.',
]);
exit;