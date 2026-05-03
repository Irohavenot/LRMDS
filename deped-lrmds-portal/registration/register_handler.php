<?php
/**
 * DepEd LRMDS – register_handler.php
 * Handles POST from register.php.
 * Returns JSON: { "success": true, "redirect": "..." }
 *                or { "success": false, "errors": {...} }
 *
 * Registration status matrix:
 *   learner / parent  → status = 'email_pending'  (must verify email to activate)
 *   teacher / school-head / developer → status = 'pending' + TOTP setup (admin activates)
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../lib/env.php';

define('DB_CHARSET', 'utf8mb4');

// Roles that go through TOTP setup (inserted to DB after TOTP confirmed)
define('TOTP_ROLES', ['teacher', 'school-head', 'developer']);

// Roles that must verify their email before their account becomes active
define('EMAIL_VERIFY_ROLES', ['learner', 'parent']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

/* ── Collect & sanitize ─────────────────────────────────────────────────── */
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

/* ── Validation ─────────────────────────────────────────────────────────── */
$errors = [];

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Enter a valid email address.';
} else {
    // MX / A record check — rejects domains that literally cannot receive email
    $email_domain = substr(strrchr($email, '@'), 1);
    if (!checkdnsrr($email_domain, 'MX') && !checkdnsrr($email_domain, 'A')) {
        $errors['email'] = 'This email domain does not appear to be valid. Please use a real email address.';
    }
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

/* ── DB connection ──────────────────────────────────────────────────────── */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', env('DB_HOST','localhost'), env('DB_NAME','lrmds'), DB_CHARSET),
        env('DB_USER','root'), env('DB_PASS',''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('LRMDS DB connect: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

/* ── Duplicate email check ──────────────────────────────────────────────── */
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'errors' => ['email' => 'An account with this email already exists.']]);
    exit;
}

/* ── Determine status & hash password ──────────────────────────────────── */
// TOTP roles → 'pending' (admin must approve after TOTP setup)
// Email-verify roles → 'email_pending' (must click link in email)
// (No other roles exist, but guard anyway)
if (in_array($role, TOTP_ROLES, true)) {
    $status = 'pending';
} elseif (in_array($role, EMAIL_VERIFY_ROLES, true)) {
    $status = 'email_pending';
} else {
    $status = 'active';
}

$password_hash = password_hash($password, PASSWORD_BCRYPT);

/* ── Meta JSON ──────────────────────────────────────────────────────────── */
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

/* ══════════════════════════════════════════════════════════════════════════
   PATH A – TOTP roles: hold data in session, insert after TOTP confirmed
══════════════════════════════════════════════════════════════════════════ */
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
        'redirect'      => '../auth/totp_setup.php',
        'message'       => 'Please set up two-factor authentication to complete registration.',
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════
   PATH B – Insert user to DB now (learner / parent)
══════════════════════════════════════════════════════════════════════════ */
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
    $new_user_id = (int) $pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('LRMDS insert: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not create account. Please try again.']);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════
   PATH B1 – Email-verify roles: send verification email
══════════════════════════════════════════════════════════════════════════ */
if (in_array($role, EMAIL_VERIFY_ROLES, true)) {
    require_once __DIR__ . '/../lib/send_verification_email.php';
    [$mail_ok, $mail_err] = send_verification_email($pdo, $new_user_id, $email, $fname);

    if (!$mail_ok) {
        // Account was created but email failed.
        // Don't block the user — they can request a resend from the confirmation page.
        error_log("LRMDS: verification email failed for user {$new_user_id}: {$mail_err}");
    }

    echo json_encode([
        'success'          => true,
        'requires_verify'  => true,
        'redirect'         => 'registration_pending.php',
        'message'          => 'Account created! Please check your inbox and click the verification link to activate your account.',
        'email_sent'       => $mail_ok,
    ]);
    exit;
}

/* ══════════════════════════════════════════════════════════════════════════
   PATH B2 – Any other non-TOTP role: redirect to sign in
   (Safety fallback — currently no roles reach here)
══════════════════════════════════════════════════════════════════════════ */
echo json_encode([
    'success'  => true,
    'pending'  => ($status === 'pending'),
    'redirect' => '../auth/signin.php',
    'message'  => 'Account created successfully. You can now sign in.',
]);
exit;