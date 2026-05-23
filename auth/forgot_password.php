<?php
/**
 * DepEd LRMDS – forgot_password.php  (auth/)
 *
 * Step 1  REQUEST  – user enters their email
 * Step 2  OTP      – email OTP + TOTP (for teacher-and-above roles)
 *                    OR "no authenticator" → retrieval request sub-form
 * Step 3  RESET    – set new password  (skipped for retrieval path)
 * Step 4  DONE     – success
 * Step 5  RETRIEVE – retrieval request submitted confirmation
 *
 * Flow by role
 * ─────────────────────────────────────────────────────────────
 * parent / learner     Email → OTP → New password
 * teacher & above      Email → OTP + TOTP → New password
 * teacher (lost TOTP)  Email → "No authenticator" form → DB request → Admin reviews
 */

session_start();

require_once __DIR__ . '/../lib/env.php';
define('DB_CHARSET', 'utf8mb4');
define('FP_OTP_COOLDOWN', 60);   // seconds between resend attempts

// Roles that require TOTP in addition to the email OTP
define('FP_TOTP_ROLES', [
    'teacher', 'school-head', 'psds', 'eps', 'eps-sgod',
    'ces', 'ces-sgod', 'specialist', 'specialist-sgod',
    'asds', 'sds', 'pdo', 'developer', 'admin',
]);

/* ── DB ─────────────────────────────────────────────────────── */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s',
            env('DB_HOST', 'localhost'), env('DB_NAME', 'lrmds'), DB_CHARSET),
        env('DB_USER', 'root'), env('DB_PASS', ''),
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $db_ok = true;
} catch (PDOException $e) {
    error_log('LRMDS forgot_password DB: ' . $e->getMessage());
    $db_ok = false;
}

/* ── State vars ─────────────────────────────────────────────── */
$step       = $_SESSION['fp_step'] ?? 'request';
$fp         = $_SESSION['fp']      ?? [];
$errors     = [];
$otp_resent = false;

// OTP cooldown helpers
function fp_cooldown_wait(string $email): int
{
    $key  = 'fp_otp_ts_' . md5($email);
    $last = $_SESSION[$key] ?? 0;
    return max(0, FP_OTP_COOLDOWN - (time() - $last));
}
function fp_record_send(string $email): void
{
    $_SESSION['fp_otp_ts_' . md5($email)] = time();
}

/* ══════════════════════════════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {

    $action = $_POST['_action'] ?? '';

    /* ── STEP 1: submit email ────────────────────────────────── */
    if ($action === 'request_reset') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare('
                SELECT id, first_name, last_name, email, role, status,
                       totp_enabled, totp_secret, password_hash
                FROM   users WHERE email = ? LIMIT 1
            ');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && $user['password_hash'] === '') {
                $errors['email'] = 'This account uses Google sign-in. Use the "Sign in with Google" button instead.';
            } elseif ($user && $user['status'] === 'suspended') {
                $errors['email'] = 'Your account has been suspended. Please contact the LRMDS helpdesk.';
            } elseif ($user) {
                $wait = fp_cooldown_wait($email);
                if ($wait > 0) {
                    $errors['email'] = "Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . " before requesting another code.";
                } else {
                    require_once __DIR__ . '/../lib/send_password_otp.php';
                    [$ok, $lookup_token, $err] = send_password_otp(
                        $pdo, (int)$user['id'], $user['email'], $user['first_name']
                    );
                    if ($ok) {
                        fp_record_send($email);
                        $_SESSION['fp_step'] = 'otp';
                        $_SESSION['fp'] = [
                            'user_id'       => $user['id'],
                            'email'         => $user['email'],
                            'first_name'    => $user['first_name'],
                            'last_name'     => $user['last_name'],
                            'role'          => $user['role'],
                            'totp_enabled'  => $user['totp_enabled'],
                            'totp_secret'   => $user['totp_secret'],
                            'lookup_token'  => $lookup_token,
                            'expires_at'    => time() + 600,
                            'totp_verified' => false,
                        ];
                        header('Location: forgot_password.php');
                        exit;
                    } else {
                        error_log("FP OTP send fail: $err");
                        $errors['email'] = 'Could not send the verification email. Please try again.';
                    }
                }
            } else {
                // User not found — fake success to prevent email enumeration
                $_SESSION['fp_step'] = 'otp';
                $_SESSION['fp'] = [
                    'user_id'       => 0,
                    'email'         => $email,
                    'first_name'    => 'there',
                    'last_name'     => '',
                    'role'          => '',
                    'lookup_token'  => '',
                    'expires_at'    => time() + 600,
                    'totp_verified' => false,
                ];
                header('Location: forgot_password.php');
                exit;
            }
        }
        $step = 'request';
    }

    /* ── STEP 2a: verify OTP (+ optional TOTP) ──────────────── */
    elseif ($action === 'verify_otp') {
        if (empty($fp) || time() > ($fp['expires_at'] ?? 0)) {
            unset($_SESSION['fp_step'], $_SESSION['fp']);
            $step = 'request';
            $errors['general'] = 'Your session has expired. Please start over.';
        } else {
            $submitted_otp  = preg_replace('/\D/', '', $_POST['otp']       ?? '');
            $submitted_totp = preg_replace('/\D/', '', $_POST['totp_code'] ?? '');

            $needs_totp = in_array($fp['role'], FP_TOTP_ROLES, true)
                       && !empty($fp['totp_enabled'])
                       && !empty($fp['totp_secret']);

            if ($fp['user_id'] === 0) {
                $errors['otp'] = 'Incorrect code. Please try again.';
            } else {
                require_once __DIR__ . '/../lib/send_password_otp.php';
                [$ok, $uid, $err] = verify_password_otp($pdo, $fp['lookup_token'], $submitted_otp);

                if (!$ok) {
                    $errors['otp'] = $err ?: 'Incorrect code. Please try again.';
                } elseif ($needs_totp && !$fp['totp_verified']) {
                    // Load TOTP library
                    require_once __DIR__ . '/../lib/TwoFactorAuthException.php';
                    require_once __DIR__ . '/../lib/Algorithm.php';
                    require_once __DIR__ . '/../lib/Providers/Rng/IRNGProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Rng/CSRNGProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Time/ITimeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Time/LocalMachineTimeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Time/NTPTimeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Time/HttpTimeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Qr/IQRCodeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Qr/BaseHTTPQRCodeProvider.php';
                    require_once __DIR__ . '/../lib/Providers/Qr/QRException.php';
                    require_once __DIR__ . '/../lib/Providers/Qr/QRServerProvider.php';
                    require_once __DIR__ . '/../lib/TwoFactorAuth.php';

                    $tfa = new RobThree\Auth\TwoFactorAuth(
                        new RobThree\Auth\Providers\Qr\QRServerProvider(), 'DepEd LRMDS'
                    );

                    if (strlen($submitted_totp) !== 6) {
                        $errors['totp'] = 'Please enter the 6-digit code from your authenticator app.';
                        // Re-issue OTP since we consumed it
                        [$ok2, $new_token,] = send_password_otp($pdo, $fp['user_id'], $fp['email'], $fp['first_name']);
                        if ($ok2) {
                            $_SESSION['fp']['lookup_token'] = $new_token;
                            $_SESSION['fp']['expires_at']   = time() + 600;
                            fp_record_send($fp['email']);
                        }
                    } elseif (!$tfa->verifyCode($fp['totp_secret'], $submitted_totp)) {
                        $errors['totp'] = 'Incorrect authenticator code. Please try again.';
                        [$ok2, $new_token,] = send_password_otp($pdo, $fp['user_id'], $fp['email'], $fp['first_name']);
                        if ($ok2) {
                            $_SESSION['fp']['lookup_token'] = $new_token;
                            $_SESSION['fp']['expires_at']   = time() + 600;
                            fp_record_send($fp['email']);
                        }
                    } else {
                        $_SESSION['fp']['totp_verified'] = true;
                        $_SESSION['fp_step'] = 'reset';
                        header('Location: forgot_password.php');
                        exit;
                    }
                } else {
                    // OTP OK, no TOTP required
                    $_SESSION['fp_step'] = 'reset';
                    header('Location: forgot_password.php');
                    exit;
                }
            }
            $step = 'otp';
            $fp   = $_SESSION['fp'] ?? [];
        }
    }

    /* ── STEP 2b: resend OTP ─────────────────────────────────── */
    elseif ($action === 'resend_otp') {
        if (empty($fp) || $fp['user_id'] === 0) {
            $step = 'otp';
        } else {
            $wait = fp_cooldown_wait($fp['email']);
            if ($wait > 0) {
                $errors['otp'] = "Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . " before requesting a new code.";
            } else {
                require_once __DIR__ . '/../lib/send_password_otp.php';
                [$ok, $new_token, $err] = send_password_otp(
                    $pdo, $fp['user_id'], $fp['email'], $fp['first_name']
                );
                if ($ok) {
                    fp_record_send($fp['email']);
                    $_SESSION['fp']['lookup_token'] = $new_token;
                    $_SESSION['fp']['expires_at']   = time() + 600;
                    $otp_resent = true;
                } else {
                    $errors['otp'] = 'Could not resend the code. Please try again.';
                }
            }
            $step = 'otp';
            $fp   = $_SESSION['fp'] ?? [];
        }
    }

    /* ── STEP 2c: account retrieval request (no authenticator) ── */
    elseif ($action === 'submit_retrieval') {
        if (empty($fp) || $fp['user_id'] === 0) {
            unset($_SESSION['fp_step'], $_SESSION['fp']);
            header('Location: forgot_password.php');
            exit;
        }

        $reason = trim($_POST['reason'] ?? '');
        if (strlen($reason) < 20) {
            $errors['reason'] = 'Please describe your situation in at least 20 characters so an administrator can assist you.';
            $step = 'otp';
            $fp   = $_SESSION['fp'] ?? [];
        } else {
            // Check for a duplicate pending request from this user
            $dup = $pdo->prepare('
                SELECT id FROM account_retrieval_requests
                WHERE user_id = ? AND status = "pending"
                LIMIT 1
            ');
            $dup->execute([$fp['user_id']]);

            if ($dup->fetch()) {
                // Already has a pending request — don't insert again
                $_SESSION['fp_step'] = 'retrieve';
                $_SESSION['fp_already_pending'] = true;
                unset($_SESSION['fp'], $_SESSION['fp_step']);
                $_SESSION['fp_step'] = 'retrieve';
                header('Location: forgot_password.php');
                exit;
            }

            $full_name = trim(($fp['first_name'] ?? '') . ' ' . ($fp['last_name'] ?? ''));

            $pdo->prepare('
                INSERT INTO account_retrieval_requests
                    (user_id, email, full_name, role, reason, status, submitted_at)
                VALUES (?, ?, ?, ?, ?, "pending", NOW())
            ')->execute([
                $fp['user_id'],
                $fp['email'],
                $full_name,
                $fp['role'],
                $reason,
            ]);

            unset($_SESSION['fp'], $_SESSION['fp_step']);
            $_SESSION['fp_step'] = 'retrieve';
            header('Location: forgot_password.php');
            exit;
        }
    }

    /* ── STEP 3: set new password ────────────────────────────── */
    elseif ($action === 'set_password') {
        if (empty($fp) || $step !== 'reset') {
            unset($_SESSION['fp_step'], $_SESSION['fp']);
            header('Location: forgot_password.php');
            exit;
        }

        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (empty($new)) {
            $errors['new_password'] = 'Please enter a new password.';
        } elseif (strlen($new) < 8) {
            $errors['new_password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new)) {
            $errors['new_password'] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $new)) {
            $errors['new_password'] = 'Password must contain at least one number.';
        } elseif (!preg_match('/[\W_]/', $new)) {
            $errors['new_password'] = 'Password must contain at least one special character.';
        }

        if (empty($confirm)) {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            try {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([$hash, $fp['user_id']]);

                unset($_SESSION['fp_step'], $_SESSION['fp']);
                $_SESSION['fp_step'] = 'done';
                header('Location: forgot_password.php');
                exit;
            } catch (PDOException $e) {
                $errors['general'] = 'Something went wrong. Please try again.';
            }
        }
        $step = 'reset';
    }
}

/* ── Sync step from session after GET redirect ──────────────── */
if (empty($_POST)) {
    $step = $_SESSION['fp_step'] ?? 'request';
    $fp   = $_SESSION['fp']      ?? [];
}

// Guard: expired or missing session on multi-step pages
if (in_array($step, ['otp', 'reset']) && (empty($fp) || time() > ($fp['expires_at'] ?? 0))) {
    unset($_SESSION['fp_step'], $_SESSION['fp']);
    $step = 'request';
}

// Clear session on terminal steps
if (in_array($step, ['done', 'retrieve'])) {
    unset($_SESSION['fp_step'], $_SESSION['fp']);
}

/* ── View helpers ────────────────────────────────────────────── */
$needs_totp = in_array($fp['role'] ?? '', FP_TOTP_ROLES, true)
           && !empty($fp['totp_enabled'])
           && !empty($fp['totp_secret']);

// TOTP role but device not set up OR unknown (show retrieval link)
$totp_role_no_device = in_array($fp['role'] ?? '', FP_TOTP_ROLES, true)
                    && empty($fp['totp_enabled']);

$masked_email = '';
if (!empty($fp['email'])) {
    $masked_email = preg_replace_callback('/^(.)(.*?)(@.+)$/', function ($m) {
        return $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3];
    }, $fp['email']);
}

$wait_left = (!empty($fp['email'])) ? fp_cooldown_wait($fp['email']) : 0;
$already_pending = $_SESSION['fp_already_pending'] ?? false;
unset($_SESSION['fp_already_pending']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password — DepEd LRMDS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:       #0B4F9C;
      --brand-hover: #0A4489;
      --brand-light: #EFF6FF;
      --brand-ring:  rgba(11,79,156,.13);
      --surface:     #FFFFFF;
      --surface-2:   #F8FAFC;
      --border:      #E2E8F0;
      --text:        #111827;
      --text-muted:  #6B7280;
      --text-subtle: #9CA3AF;
      --success:     #059669;
      --success-bg:  #ECFDF5;
      --error:       #DC2626;
      --error-bg:    #FEF2F2;
      --warning:     #D97706;
      --warning-bg:  #FFFBEB;
      --amber:       #92400E;
      --radius:      12px;
      --radius-sm:   8px;
      --shadow:      0 4px 20px rgba(0,0,0,.08);
      --font:        'Plus Jakarta Sans', system-ui, sans-serif;
    }

    body {
      font-family: var(--font);
      background: var(--surface-2);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 32px 16px 56px;
    }

    .fp-wrap { width: 100%; max-width: 460px; }

    /* Brand bar */
    .fp-brand {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 24px; justify-content: center;
    }
    .fp-brand-name { font-size: 15px; font-weight: 800; color: var(--brand); }
    .fp-brand-sub  { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

    /* Card */
    .fp-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    /* Step dots */
    .fp-steps {
      display: flex; align-items: center; padding: 20px 28px 0;
    }
    .fp-step-dot {
      width: 26px; height: 26px; border-radius: 50%; font-size: 11px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      background: #E5E7EB; color: #9CA3AF; transition: .2s;
    }
    .fp-step-dot.active { background: var(--brand);   color: #fff; }
    .fp-step-dot.done   { background: var(--success);  color: #fff; }
    .fp-step-line { flex: 1; height: 2px; background: #E5E7EB; margin: 0 6px; }
    .fp-step-line.done { background: var(--success); }

    /* Card sections */
    .fp-header { padding: 20px 28px 0; }
    .fp-title  { font-size: 20px; font-weight: 800; color: var(--text); margin-bottom: 5px; }
    .fp-desc   { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; }
    .fp-body   { padding: 24px 28px 28px; }

    /* Alerts */
    .fp-alert {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 11px 14px; border-radius: var(--radius-sm);
      font-size: 13px; line-height: 1.55; margin-bottom: 18px;
      animation: fadeIn .2s ease;
    }
    .fp-alert a { color: inherit; font-weight: 700; }
    .fp-alert-error   { background: var(--error-bg);    color: #B91C1C; border: 1px solid #FECACA; }
    .fp-alert-success { background: var(--success-bg);  color: #065F46; border: 1px solid #A7F3D0; }
    .fp-alert-info    { background: var(--brand-light); color: #1E40AF; border: 1px solid #BFDBFE; }
    .fp-alert-warning { background: var(--warning-bg);  color: var(--amber); border: 1px solid #FDE68A; }
    .fp-alert svg { flex-shrink: 0; margin-top: 1px; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; } }

    /* Form fields */
    .fp-field { display: flex; flex-direction: column; gap: 5px; margin-bottom: 18px; }
    .fp-label { font-size: 13px; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 6px; }
    .fp-req   { color: var(--error); }
    .fp-input-wrap { position: relative; }
    .fp-input {
      width: 100%; padding: 11px 13px 11px 38px;
      font-family: var(--font); font-size: 14px; color: var(--text);
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .fp-input-no-icon { padding-left: 13px; }
    .fp-input::placeholder { color: var(--text-subtle); }
    .fp-input:focus  { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-input.invalid { border-color: var(--error);   background: var(--error-bg); }
    .fp-input.valid   { border-color: var(--success); }
    .fp-textarea {
      width: 100%; padding: 11px 13px; resize: vertical; min-height: 100px;
      font-family: var(--font); font-size: 14px; color: var(--text);
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); outline: none; line-height: 1.6;
      transition: border-color .15s, box-shadow .15s;
    }
    .fp-textarea:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-textarea.invalid { border-color: var(--error); background: var(--error-bg); }
    .fp-input-icon {
      position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
      color: var(--text-subtle); pointer-events: none; transition: color .15s;
    }
    .fp-input-wrap:focus-within .fp-input-icon { color: var(--brand); }
    .fp-toggle {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--text-subtle);
      padding: 4px; border-radius: 4px; display: flex; transition: color .15s;
    }
    .fp-toggle:hover { color: var(--text); }
    .fp-field-error { font-size: 12px; color: var(--error); display: flex; align-items: center; gap: 4px; }

    /* Password strength */
    .fp-strength { margin-top: 6px; }
    .fp-strength-bar  { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
    .fp-strength-fill { height: 100%; border-radius: 2px; width: 0%; transition: width .3s, background .3s; }
    .fp-strength-label { font-size: 11px; color: var(--text-subtle); margin-top: 4px; }
    .fp-reqs { list-style: none; display: flex; flex-direction: column; gap: 3px; margin-top: 8px; }
    .fp-req-item { font-size: 12px; color: var(--text-subtle); display: flex; align-items: center; gap: 5px; transition: color .2s; }
    .fp-req-item.met { color: var(--success); }
    .fp-req-item .dot   { display: block; }
    .fp-req-item .check { display: none; }
    .fp-req-item.met .dot   { display: none; }
    .fp-req-item.met .check { display: block; }

    /* OTP digit boxes */
    .fp-otp-inputs { display: flex; gap: 8px; justify-content: center; margin: 16px 0 4px; }
    .fp-otp-digit {
      width: 50px; height: 58px; text-align: center;
      font-size: 26px; font-weight: 700; font-family: monospace;
      border: 2px solid var(--border); border-radius: 10px;
      background: var(--surface); color: var(--text);
      outline: none; caret-color: transparent;
      transition: border-color .15s, box-shadow .15s;
    }
    .fp-otp-digit:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-otp-digit.filled  { border-color: var(--brand); background: var(--brand-light); }
    .fp-otp-digit.invalid { border-color: var(--error); box-shadow: 0 0 0 3px rgba(220,38,38,.12); }
    #fp-otp-hidden { position: absolute; opacity: 0; pointer-events: none; width: 1px; }
    .fp-otp-timer { font-size: 12px; color: var(--text-subtle); text-align: center; margin-bottom: 4px; }
    .fp-otp-timer span { font-weight: 700; color: var(--text-muted); }

    /* Email chip */
    .fp-email-chip {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: 999px; padding: 4px 12px;
      font-size: 13px; font-weight: 600; color: var(--text); margin: 8px 0 16px;
    }

    /* TOTP section */
    .fp-totp-section {
      border-top: 1px solid var(--border); padding-top: 18px; margin-top: 4px;
    }
    .fp-totp-label {
      font-size: 13px; font-weight: 700; color: var(--text);
      display: flex; align-items: center; gap: 7px; margin-bottom: 8px;
    }
    .fp-totp-input {
      width: 100%; padding: 14px 16px; font-size: 28px; font-weight: 700;
      letter-spacing: .28em; text-align: center; font-family: monospace;
      border: 2px solid var(--border); border-radius: 10px; outline: none;
      transition: border-color .15s, box-shadow .15s;
    }
    .fp-totp-input:focus   { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-totp-input.invalid { border-color: var(--error); box-shadow: 0 0 0 3px rgba(220,38,38,.12); }
    .fp-totp-refresh { font-size: 12px; color: var(--text-subtle); text-align: center; margin-top: 6px; }
    .fp-totp-refresh span { font-weight: 700; color: var(--brand); }

    /* No-authenticator toggle link */
    .fp-no-totp-link {
      display: block; text-align: center; margin-top: 14px;
      font-size: 12.5px; color: var(--text-subtle);
    }
    .fp-no-totp-link button {
      background: none; border: none; cursor: pointer; font-family: var(--font);
      font-size: 12.5px; color: var(--brand); font-weight: 600;
      text-decoration: underline; text-underline-offset: 2px; padding: 0;
    }

    /* Retrieval request panel (hidden until toggle) */
    .fp-retrieval-panel {
      display: none;
      border-top: 1px solid var(--border);
      padding-top: 20px;
      margin-top: 18px;
      animation: fadeIn .2s ease;
    }
    .fp-retrieval-panel.open { display: block; }
    .fp-retrieval-title {
      font-size: 13.5px; font-weight: 700; color: var(--text); margin-bottom: 6px;
      display: flex; align-items: center; gap: 6px;
    }
    .fp-char-count { font-size: 11px; color: var(--text-subtle); text-align: right; margin-top: 4px; }

    /* Buttons */
    .fp-btn {
      width: 100%; padding: 12px 16px; border: none; border-radius: var(--radius-sm);
      font-family: var(--font); font-size: 14px; font-weight: 700; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: background .15s, box-shadow .15s, transform .1s;
      min-height: 48px;
    }
    .fp-btn-primary  { background: var(--brand); color: #fff; box-shadow: 0 2px 8px rgba(11,79,156,.2); }
    .fp-btn-primary:hover    { background: var(--brand-hover); box-shadow: 0 4px 14px rgba(11,79,156,.28); }
    .fp-btn-primary:active   { transform: scale(.98); }
    .fp-btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; box-shadow: none; }
    .fp-btn-amber {
      background: #D97706; color: #fff; box-shadow: 0 2px 8px rgba(217,119,6,.2);
      margin-top: 10px;
    }
    .fp-btn-amber:hover  { background: #B45309; }
    .fp-btn-amber:active { transform: scale(.98); }
    .fp-btn-ghost {
      background: #fff; border: 1.5px solid var(--border); color: var(--text-muted);
      margin-top: 10px;
    }
    .fp-btn-ghost:hover { border-color: #9CA3AF; color: var(--text); }

    .fp-resend {
      background: none; border: none; cursor: pointer; font-family: var(--font);
      font-size: 13px; font-weight: 600; color: var(--brand); padding: 0;
      text-decoration: underline; text-underline-offset: 2px;
    }
    .fp-resend:disabled { color: var(--text-subtle); text-decoration: none; cursor: default; }

    /* Success / retrieve screens */
    .fp-success { text-align: center; padding: 40px 28px; }
    .fp-success-icon {
      width: 72px; height: 72px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px;
      animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
    }
    .fp-success-icon.green  { background: var(--success-bg); color: var(--success); }
    .fp-success-icon.amber  { background: var(--warning-bg); color: var(--warning); }
    @keyframes popIn { from { transform:scale(.4); opacity:0; } to { transform:scale(1); opacity:1; } }
    .fp-success-title { font-size: 22px; font-weight: 800; margin-bottom: 10px; }
    .fp-success-desc  { font-size: 14px; color: var(--text-muted); line-height: 1.65; margin-bottom: 28px; }
    .fp-success-detail {
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: 10px; padding: 14px 16px; font-size: 13px;
      color: var(--text-muted); line-height: 1.6; margin-bottom: 24px; text-align: left;
    }
    .fp-success-detail strong { color: var(--text); }

    /* Footer */
    .fp-footer { text-align: center; font-size: 13px; color: var(--text-muted); margin-top: 18px; }
    .fp-footer a { color: var(--brand); font-weight: 600; text-decoration: none; }
    .fp-footer a:hover { text-decoration: underline; }

    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 480px) {
      body { padding: 20px 12px 48px; align-items: flex-start; }
      .fp-body   { padding: 20px; }
      .fp-header { padding: 16px 20px 0; }
      .fp-steps  { padding: 16px 20px 0; }
      .fp-otp-digit { width: 42px; height: 52px; font-size: 22px; }
    }
  </style>
</head>
<body>
<div class="fp-wrap">

  <!-- Brand -->
  <div class="fp-brand">
    <div>
      <div class="fp-brand-name">DepEd LRMDS</div>
      <div class="fp-brand-sub">Learning Resource Management &amp; Development System</div>
    </div>
  </div>

  <div class="fp-card">

    <?php if ($step === 'done'): ?>
    <!-- ═══════════════════ DONE ═══════════════════════════════ -->
    <div class="fp-success">
      <div class="fp-success-icon green">
        <svg width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </div>
      <div class="fp-success-title">Password Reset!</div>
      <p class="fp-success-desc">
        Your password has been updated successfully.<br/>
        You can now sign in with your new password.
      </p>
      <a href="signin.php" class="fp-btn fp-btn-primary" style="text-decoration:none;max-width:240px;margin:0 auto">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
        </svg>
        Sign In Now
      </a>
    </div>

    <?php elseif ($step === 'retrieve'): ?>
    <!-- ═══════════════════ RETRIEVAL SUBMITTED ════════════════ -->
    <div class="fp-success">
      <div class="fp-success-icon amber">
        <svg width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
        </svg>
      </div>
      <div class="fp-success-title">Request Submitted</div>
      <p class="fp-success-desc">
        <?= $already_pending
            ? 'You already have a pending retrieval request. An administrator will review it shortly.'
            : 'Your account retrieval request has been submitted.' ?>
      </p>
      <div class="fp-success-detail">
        <strong>What happens next?</strong><br/>
        An LRMDS administrator will review your request and verify your identity.
        You'll be notified at your registered email once a decision is made.
        This typically takes <strong>1–3 working days</strong>.
      </div>
      <a href="signin.php" class="fp-btn fp-btn-ghost" style="text-decoration:none;max-width:260px;margin:0 auto">
        ← Back to Sign In
      </a>
    </div>

    <?php else: ?>
    <!-- Progress dots (3 steps) -->
    <div class="fp-steps">
      <div class="fp-step-dot <?= $step === 'request' ? 'active' : 'done' ?>">
        <?= $step === 'request' ? '1' : '✓' ?>
      </div>
      <div class="fp-step-line <?= in_array($step, ['otp', 'reset']) ? 'done' : '' ?>"></div>
      <div class="fp-step-dot <?= $step === 'otp' ? 'active' : ($step === 'reset' ? 'done' : '') ?>">
        <?= $step === 'reset' ? '✓' : '2' ?>
      </div>
      <div class="fp-step-line <?= $step === 'reset' ? 'done' : '' ?>"></div>
      <div class="fp-step-dot <?= $step === 'reset' ? 'active' : '' ?>">3</div>
    </div>

    <?php if ($step === 'request'): ?>
    <!-- ═══════════════════ STEP 1: REQUEST ═══════════════════ -->
    <div class="fp-header" style="margin-top:18px">
      <div class="fp-title">Forgot your password?</div>
      <p class="fp-desc">Enter the email address on your LRMDS account and we'll send you a verification code.</p>
    </div>
    <div class="fp-body">

      <?php if (!$db_ok): ?>
      <div class="fp-alert fp-alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Database connection failed. Make sure XAMPP MySQL is running.
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['general'])): ?>
      <div class="fp-alert fp-alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <input type="hidden" name="_action" value="request_reset"/>

        <div class="fp-field">
          <label class="fp-label" for="fp-email">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
            Email Address <span class="fp-req">*</span>
          </label>
          <div class="fp-input-wrap">
            <span class="fp-input-icon">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
            </span>
            <input class="fp-input <?= !empty($errors['email']) ? 'invalid' : '' ?>"
                   type="email" id="fp-email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   placeholder="yourname@deped.gov.ph" required autofocus/>
          </div>
          <?php if (!empty($errors['email'])): ?>
          <div class="fp-field-error">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($errors['email']) ?>
          </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="fp-btn fp-btn-primary" id="fp-req-btn">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
          Send Verification Code
        </button>
      </form>
    </div>

    <?php elseif ($step === 'otp'): ?>
    <!-- ═══════════════════ STEP 2: OTP (+TOTP) ═══════════════ -->
    <div class="fp-header" style="margin-top:18px">
      <div class="fp-title">Verify your identity</div>
      <p class="fp-desc">
        We've sent a 6-digit code to your email.<?php if ($needs_totp): ?>
        Since your account has two-factor authentication enabled, you'll also need your authenticator app code.<?php endif; ?>
        <?php if ($totp_role_no_device): ?>
        If you don't have access to your authenticator app, you can submit a retrieval request below.<?php endif; ?>
      </p>
    </div>
    <div class="fp-body">

      <?php if ($otp_resent): ?>
      <div class="fp-alert fp-alert-success">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        A new code has been sent to your email.
      </div>
      <?php endif; ?>

      <?php if (!empty($errors['otp'])): ?>
      <div class="fp-alert fp-alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['otp']) ?>
      </div>
      <?php endif; ?>

      <p style="font-size:13px;color:var(--text-muted);margin-bottom:2px">Code sent to:</p>
      <div class="fp-email-chip">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
        <?= htmlspecialchars($masked_email) ?>
      </div>

      <!-- OTP verify form -->
      <form method="POST" id="fp-otp-form" novalidate>
        <input type="hidden" name="_action" value="verify_otp"/>
        <input type="hidden" name="otp" id="fp-otp-hidden"/>

        <label style="font-size:13px;font-weight:600;color:var(--text);display:block;margin-bottom:2px">
          Email verification code
        </label>
        <div class="fp-otp-inputs" role="group" aria-label="6-digit code">
          <?php for ($i = 1; $i <= 6; $i++): ?>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>"
                 type="text" inputmode="numeric" maxlength="1"
                 aria-label="Digit <?= $i ?>"
                 <?= $i === 1 ? 'autocomplete="one-time-code"' : '' ?>/>
          <?php endfor; ?>
        </div>
        <p class="fp-otp-timer">Code expires in <span id="fp-countdown">10:00</span></p>

        <?php if ($needs_totp): ?>
        <!-- TOTP section: role has it enabled -->
        <div class="fp-totp-section">
          <div class="fp-totp-label">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Authenticator App Code
          </div>
          <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:10px;line-height:1.5">
            Open your authenticator app and enter the 6-digit code for <strong>DepEd LRMDS</strong>.
          </p>
          <?php if (!empty($errors['totp'])): ?>
          <div class="fp-alert fp-alert-error" style="margin-bottom:10px">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($errors['totp']) ?>
          </div>
          <?php endif; ?>
          <input type="text" name="totp_code" id="fp-totp-input"
                 class="fp-totp-input <?= !empty($errors['totp']) ? 'invalid' : '' ?>"
                 placeholder="000 000" maxlength="6" inputmode="numeric" pattern="\d{6}"
                 autocomplete="one-time-code"/>
          <p class="fp-totp-refresh">Refreshes every <span id="fp-totp-cd">30</span>s</p>
        </div>

        <!-- "No authenticator" escape hatch -->
        <div class="fp-no-totp-link">
          Don't have access to your authenticator?
          <button type="button" id="fp-toggle-retrieval">Submit a retrieval request</button>
        </div>

        <?php elseif ($totp_role_no_device): ?>
        <!-- TOTP role but never set up — skip OTP verify, go straight to retrieval -->
        <div class="fp-alert fp-alert-warning" style="margin-top:14px">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
          Your account role requires two-factor authentication, but no authenticator has been set up yet.
          Use the form below to request manual account recovery.
        </div>
        <?php endif; ?>

        <?php if (!$totp_role_no_device): ?>
        <button type="submit" class="fp-btn fp-btn-primary" id="fp-otp-btn" style="margin-top:18px" disabled>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
          Confirm &amp; Continue
        </button>
        <?php endif; ?>
      </form>

      <!-- ── Retrieval Request Panel ───────────────────────────── -->
      <?php
        // Auto-open if TOTP role with no device setup; otherwise hidden until toggled
        $panel_open = $totp_role_no_device ? 'open' : '';
        $reason_val = htmlspecialchars($_POST['reason'] ?? '');
      ?>
      <div class="fp-retrieval-panel <?= $panel_open ?>" id="fp-retrieval-panel">

        <div class="fp-retrieval-title">
          <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z"/>
          </svg>
          Account Retrieval Request
        </div>

        <div class="fp-alert fp-alert-info" style="margin-bottom:14px">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          <span>An LRMDS administrator will review your request and verify your identity before restoring access.
          This typically takes <strong>1–3 working days</strong>.</span>
        </div>

        <form method="POST" id="fp-retrieval-form">
          <input type="hidden" name="_action" value="submit_retrieval"/>

          <div class="fp-field">
            <label class="fp-label" for="fp-reason">
              Describe your situation <span class="fp-req">*</span>
            </label>
            <textarea class="fp-textarea <?= !empty($errors['reason']) ? 'invalid' : '' ?>"
                      id="fp-reason" name="reason"
                      placeholder="e.g. I lost my phone and can no longer access my authenticator app. My employee ID is..."
                      maxlength="1000" required><?= $reason_val ?></textarea>
            <div class="fp-char-count"><span id="fp-char-cur">0</span> / 1000</div>
          </div>

          <?php if (!empty($errors['reason'])): ?>
          <div class="fp-alert fp-alert-error" style="margin-top:-10px;margin-bottom:14px">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($errors['reason']) ?>
          </div>
          <?php endif; ?>

          <button type="submit" class="fp-btn fp-btn-amber">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/>
            </svg>
            Submit Retrieval Request
          </button>
        </form>
      </div>

      <!-- Resend + back -->
      <div style="margin-top:14px;text-align:center;font-size:13px;color:var(--text-muted)">
        Didn't receive it?
        <form method="POST" style="display:inline" id="fp-resend-form">
          <input type="hidden" name="_action" value="resend_otp"/>
          <button type="submit" class="fp-resend" id="fp-resend-btn">Resend code</button>
        </form>
      </div>
      <form method="POST" style="margin-top:8px;text-align:center">
        <input type="hidden" name="_action" value="request_reset"/>
        <button type="submit" style="background:none;border:none;cursor:pointer;font-family:var(--font);font-size:12px;color:var(--text-subtle);text-decoration:underline;text-underline-offset:2px">
          ← Use a different email
        </button>
      </form>
    </div>

    <?php elseif ($step === 'reset'): ?>
    <!-- ═══════════════════ STEP 3: RESET PASSWORD ════════════ -->
    <div class="fp-header" style="margin-top:18px">
      <div class="fp-title">Set a new password</div>
      <p class="fp-desc">Choose a strong password for your account. You'll use it to sign in from now on.</p>
    </div>
    <div class="fp-body">

      <?php if (!empty($errors['general'])): ?>
      <div class="fp-alert fp-alert-error">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($errors['general']) ?>
      </div>
      <?php endif; ?>

      <form method="POST" id="fp-reset-form" novalidate autocomplete="off">
        <input type="hidden" name="_action" value="set_password"/>

        <div class="fp-field">
          <label class="fp-label" for="fp-new-pw">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            New Password <span class="fp-req">*</span>
          </label>
          <div class="fp-input-wrap">
            <span class="fp-input-icon">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
            </span>
            <input class="fp-input <?= !empty($errors['new_password']) ? 'invalid' : '' ?>"
                   type="password" id="fp-new-pw" name="new_password"
                   placeholder="Create a strong password" autocomplete="new-password" required/>
            <button type="button" class="fp-toggle" data-target="fp-new-pw" aria-label="Toggle visibility">
              <svg class="eye-show" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-hide" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <?php if (!empty($errors['new_password'])): ?>
          <div class="fp-field-error">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($errors['new_password']) ?>
          </div>
          <?php endif; ?>
          <div class="fp-strength" id="fp-strength" style="display:none">
            <div class="fp-strength-bar"><div class="fp-strength-fill" id="fp-str-fill"></div></div>
            <div class="fp-strength-label">Strength: <span id="fp-str-text">—</span></div>
          </div>
          <ul class="fp-reqs" id="fp-reqs" style="display:none">
            <li class="fp-req-item" id="fpq-len">
              <svg class="dot" width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              <svg class="check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
              At least 8 characters
            </li>
            <li class="fp-req-item" id="fpq-up">
              <svg class="dot" width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              <svg class="check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
              One uppercase letter
            </li>
            <li class="fp-req-item" id="fpq-num">
              <svg class="dot" width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              <svg class="check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
              One number
            </li>
            <li class="fp-req-item" id="fpq-sp">
              <svg class="dot" width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
              <svg class="check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
              One special character
            </li>
          </ul>
        </div>

        <div class="fp-field">
          <label class="fp-label" for="fp-confirm-pw">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
            Confirm Password <span class="fp-req">*</span>
          </label>
          <div class="fp-input-wrap">
            <span class="fp-input-icon">
              <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
            <input class="fp-input <?= !empty($errors['confirm_password']) ? 'invalid' : '' ?>"
                   type="password" id="fp-confirm-pw" name="confirm_password"
                   placeholder="Re-enter your new password" autocomplete="new-password" required/>
            <button type="button" class="fp-toggle" data-target="fp-confirm-pw" aria-label="Toggle visibility">
              <svg class="eye-show" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-hide" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
          <?php if (!empty($errors['confirm_password'])): ?>
          <div class="fp-field-error">
            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <?= htmlspecialchars($errors['confirm_password']) ?>
          </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="fp-btn fp-btn-primary">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Reset Password
        </button>
      </form>
    </div>

    <?php endif; /* step checks */ ?>
    <?php endif; /* done/retrieve vs rest */ ?>

  </div><!-- /.fp-card -->

  <div class="fp-footer">
    <a href="signin.php">← Back to Sign In</a>
    &nbsp;·&nbsp;
    <a href="../registration/register.php">Create an account</a>
  </div>

</div><!-- /.fp-wrap -->

<script>
/* ── Password show/hide toggles ── */
document.querySelectorAll('.fp-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp  = document.getElementById(btn.dataset.target);
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    btn.querySelector('.eye-show').style.display = show ? 'none' : '';
    btn.querySelector('.eye-hide').style.display = show ? ''     : 'none';
  });
});

/* ── Password strength (Step 3) ── */
const newPw  = document.getElementById('fp-new-pw');
const confPw = document.getElementById('fp-confirm-pw');
if (newPw) {
  const strDiv  = document.getElementById('fp-strength');
  const strFill = document.getElementById('fp-str-fill');
  const strText = document.getElementById('fp-str-text');
  const reqBox  = document.getElementById('fp-reqs');
  const reqs = {
    len: { el: document.getElementById('fpq-len'), fn: v => v.length >= 8 },
    up:  { el: document.getElementById('fpq-up'),  fn: v => /[A-Z]/.test(v) },
    num: { el: document.getElementById('fpq-num'), fn: v => /[0-9]/.test(v) },
    sp:  { el: document.getElementById('fpq-sp'),  fn: v => /[\W_]/.test(v) },
  };
  const levels = [
    { l:'Very Weak',   c:'#EF4444', w:'15%'  },
    { l:'Weak',        c:'#F97316', w:'35%'  },
    { l:'Fair',        c:'#EAB308', w:'60%'  },
    { l:'Strong',      c:'#22C55E', w:'85%'  },
    { l:'Very Strong', c:'#059669', w:'100%' },
  ];
  function calcStr(v) {
    let s = 0;
    if (v.length >= 8)  s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v))  s++;
    if (/[0-9]/.test(v))  s++;
    if (/[\W_]/.test(v))  s++;
    return Math.min(s, 4);
  }
  newPw.addEventListener('input', () => {
    const val = newPw.value;
    if (!val) {
      strDiv.style.display = reqBox.style.display = 'none';
      newPw.classList.remove('valid', 'invalid');
      return;
    }
    strDiv.style.display = reqBox.style.display = 'block';
    let met = 0;
    Object.values(reqs).forEach(r => { const m = r.fn(val); r.el.classList.toggle('met', m); if (m) met++; });
    const lv = levels[calcStr(val)];
    strFill.style.width      = lv.w;
    strFill.style.background = lv.c;
    strText.textContent      = lv.l;
    strText.style.color      = lv.c;
    newPw.classList.toggle('valid',   met === 4);
    newPw.classList.toggle('invalid', val.length > 3 && met < 4);
    if (confPw && confPw.value) syncConf();
  });
  function syncConf() {
    const ok = confPw.value === newPw.value && confPw.value !== '';
    confPw.classList.toggle('valid',   ok);
    confPw.classList.toggle('invalid', !ok && confPw.value.length > 0);
  }
  if (confPw) confPw.addEventListener('input', syncConf);
}

/* ── OTP digit boxes (Step 2) ── */
(function () {
  const digits    = Array.from(document.querySelectorAll('.fp-otp-digit'));
  const hidden    = document.getElementById('fp-otp-hidden');
  const submitBtn = document.getElementById('fp-otp-btn');
  const totpInput = document.getElementById('fp-totp-input');
  if (!digits.length || !hidden) return;

  const needsTotp = <?= $needs_totp ? 'true' : 'false' ?>;

  digits[0]?.focus();

  function sync() {
    const val = digits.map(d => d.value).join('');
    hidden.value = val;
    digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
    const otpOk  = val.length === 6;
    const totpOk = !needsTotp || (totpInput && totpInput.value.replace(/\D/g, '').length === 6);
    if (submitBtn) submitBtn.disabled = !(otpOk && totpOk);
  }

  digits.forEach((box, i) => {
    box.addEventListener('input', e => {
      box.value = box.value.replace(/\D/g, '').slice(-1);
      sync();
      if (box.value && i < digits.length - 1) digits[i + 1].focus();
    });
    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (box.value) { box.value = ''; sync(); }
        else if (i > 0) { digits[i - 1].focus(); digits[i - 1].value = ''; sync(); }
        e.preventDefault();
      }
      if (e.key === 'ArrowLeft'  && i > 0)              digits[i - 1].focus();
      if (e.key === 'ArrowRight' && i < digits.length - 1) digits[i + 1].focus();
    });
    box.addEventListener('paste', e => {
      e.preventDefault();
      const p = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
      p.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
      sync();
      digits[Math.min(p.length, digits.length - 1)].focus();
    });
  });

  if (totpInput) {
    totpInput.addEventListener('input', () => {
      totpInput.value = totpInput.value.replace(/\D/g, '').slice(0, 6);
      sync();
    });
  }

  /* 10-min countdown */
  let secs  = 10 * 60;
  const cdEl = document.getElementById('fp-countdown');
  function tick() {
    if (!cdEl) return;
    const m = Math.floor(secs / 60).toString().padStart(2, '0');
    const s = (secs % 60).toString().padStart(2, '0');
    cdEl.textContent = m + ':' + s;
    if (secs <= 60) cdEl.style.color = '#DC2626';
    if (secs <= 0) {
      cdEl.textContent = 'Expired';
      if (submitBtn) submitBtn.disabled = true;
      digits.forEach(d => { d.disabled = true; d.classList.add('invalid'); });
      return;
    }
    secs--;
    setTimeout(tick, 1000);
  }
  tick();

  /* TOTP 30s counter */
  const totpCd = document.getElementById('fp-totp-cd');
  if (totpCd) {
    function totpTick() {
      const s = 30 - (Math.floor(Date.now() / 1000) % 30);
      totpCd.textContent = s;
      totpCd.style.color = s <= 5 ? '#DC2626' : '#0B4F9C';
    }
    totpTick();
    setInterval(totpTick, 1000);
  }

  /* Resend cooldown */
  const resendBtn = document.getElementById('fp-resend-btn');
  if (resendBtn) {
    let cd = <?= $wait_left ?>;
    let iv = null;
    function startCd(s) {
      cd = s; resendBtn.disabled = true;
      iv = setInterval(() => {
        cd--;
        resendBtn.textContent = `Resend code (${cd}s)`;
        if (cd <= 0) { clearInterval(iv); resendBtn.disabled = false; resendBtn.textContent = 'Resend code'; }
      }, 1000);
    }
    if (cd > 0) startCd(cd);
    document.getElementById('fp-resend-form')?.addEventListener('submit', () => startCd(<?= FP_OTP_COOLDOWN ?>));
  }

  /* Loading state on OTP submit */
  document.getElementById('fp-otp-form')?.addEventListener('submit', function () {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Verifying…';
    }
  });
})();

/* ── "No authenticator" toggle ── */
const toggleBtn    = document.getElementById('fp-toggle-retrieval');
const retrievalPanel = document.getElementById('fp-retrieval-panel');
if (toggleBtn && retrievalPanel) {
  toggleBtn.addEventListener('click', () => {
    const isOpen = retrievalPanel.classList.toggle('open');
    toggleBtn.textContent = isOpen ? 'Cancel retrieval request' : 'Submit a retrieval request';
    if (isOpen) retrievalPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
}

/* ── Retrieval textarea char counter ── */
const reasonTa  = document.getElementById('fp-reason');
const charCur   = document.getElementById('fp-char-cur');
if (reasonTa && charCur) {
  function updateCount() { charCur.textContent = reasonTa.value.length; }
  reasonTa.addEventListener('input', updateCount);
  updateCount(); // init on page load (e.g. after validation error re-renders the value)
}

/* ── Step 1 submit loading ── */
document.getElementById('fp-req-btn')?.closest('form')?.addEventListener('submit', function () {
  const btn = document.getElementById('fp-req-btn');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Sending…';
  }
});
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
</body>
</html>