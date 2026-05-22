<?php
/**
 * DepEd LRMDS – forgot_password.php
 *
 * Step 1 (REQUEST)  – user enters their email
 * Step 2 (OTP)      – user enters the 6-digit code emailed to them
 *                     TOTP roles also enter their authenticator code here
 * Step 3 (RESET)    – user sets a new password
 * Step 4 (DONE)     – success, redirect to sign-in
 *
 * No session login required — this is the unauthenticated reset flow.
 * Uses the same send_password_otp / verify_password_otp helpers as
 * change_password.php so no new DB tables are needed.
 */

session_start();

require_once __DIR__ . '/../lib/env.php';
define('DB_CHARSET', 'utf8mb4');
define('FP_OTP_COOLDOWN', 60);   // seconds between resend attempts

// Roles that require TOTP in addition to the email OTP
define('FP_TOTP_ROLES', ['teacher', 'school-head', 'psds', 'eps', 'eps-sgod',
                          'ces', 'ces-sgod', 'specialist', 'specialist-sgod',
                          'asds', 'sds', 'pdo', 'developer', 'admin']);

/* ── DB ─────────────────────────────────────────────────────── */
try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s',
            env('DB_HOST','localhost'), env('DB_NAME','lrmds'), DB_CHARSET),
        env('DB_USER','root'), env('DB_PASS',''),
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
$step         = $_SESSION['fp_step']    ?? 'request';   // request | otp | reset | done
$fp           = $_SESSION['fp']         ?? [];           // carries data across steps
$errors       = [];
$otp_resent   = false;

// Helper: rate-limit check
function fp_cooldown_wait(string $email): int {
    $key  = 'fp_otp_ts_' . md5($email);
    $last = $_SESSION[$key] ?? 0;
    return max(0, FP_OTP_COOLDOWN - (time() - $last));
}
function fp_record_send(string $email): void {
    $_SESSION['fp_otp_ts_' . md5($email)] = time();
}

/* ══════════════════════════════════════════════════════════════
   POST HANDLERS
══════════════════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db_ok) {

    $action = $_POST['_action'] ?? '';

    /* ── STEP 1: submit email ─────────────────────────────────── */
    if ($action === 'request_reset') {
        $email = strtolower(trim($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            // Look up user — always show same message to prevent enumeration
            $stmt = $pdo->prepare('
                SELECT id, first_name, email, role, status,
                       totp_enabled, totp_secret, password_hash
                FROM users WHERE email = ? LIMIT 1
            ');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Block Google-only accounts (no password_hash set)
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
                            'user_id'      => $user['id'],
                            'email'        => $user['email'],
                            'first_name'   => $user['first_name'],
                            'role'         => $user['role'],
                            'totp_enabled' => $user['totp_enabled'],
                            'totp_secret'  => $user['totp_secret'],
                            'lookup_token' => $lookup_token,
                            'expires_at'   => time() + 600,
                            'totp_verified'=> false,
                        ];
                        header('Location: forgot_password.php');
                        exit;
                    } else {
                        error_log("FP OTP send fail: $err");
                        $errors['email'] = 'Could not send the verification email. Please try again.';
                    }
                }
            } else {
                // User not found — fake success to prevent enumeration
                $_SESSION['fp_step'] = 'otp';
                $_SESSION['fp'] = [
                    'user_id'      => 0,
                    'email'        => $email,
                    'first_name'   => 'there',
                    'role'         => '',
                    'lookup_token' => '',
                    'expires_at'   => time() + 600,
                    'totp_verified'=> false,
                ];
                header('Location: forgot_password.php');
                exit;
            }
        }
        $step = 'request';
    }

    /* ── STEP 2a: verify OTP (and optionally TOTP) ─────────────── */
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

            // --- Verify email OTP ---
            if ($fp['user_id'] === 0) {
                // Fake user — always fail silently, but show OTP error
                $errors['otp'] = 'Incorrect code. Please try again.';
            } else {
                require_once __DIR__ . '/../lib/send_password_otp.php';
                [$ok, $uid, $err] = verify_password_otp($pdo, $fp['lookup_token'], $submitted_otp);

                if (!$ok) {
                    $errors['otp'] = $err ?: 'Incorrect code. Please try again.';
                } elseif ($needs_totp && !$fp['totp_verified']) {
                    // --- Also verify TOTP if required ---
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
                        // Re-issue a new OTP since we consumed the old one
                        [$ok2, $new_token,] = send_password_otp($pdo, $fp['user_id'], $fp['email'], $fp['first_name']);
                        if ($ok2) { $_SESSION['fp']['lookup_token'] = $new_token; $_SESSION['fp']['expires_at'] = time() + 600; fp_record_send($fp['email']); }
                    } elseif (!$tfa->verifyCode($fp['totp_secret'], $submitted_totp)) {
                        $errors['totp'] = 'Incorrect authenticator code. Please try again.';
                        [$ok2, $new_token,] = send_password_otp($pdo, $fp['user_id'], $fp['email'], $fp['first_name']);
                        if ($ok2) { $_SESSION['fp']['lookup_token'] = $new_token; $_SESSION['fp']['expires_at'] = time() + 600; fp_record_send($fp['email']); }
                    } else {
                        // Both verified!
                        $_SESSION['fp']['totp_verified'] = true;
                        $_SESSION['fp_step'] = 'reset';
                        header('Location: forgot_password.php');
                        exit;
                    }
                } else {
                    // OTP ok, no TOTP required
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
            $step = 'otp'; // Stay on page, do nothing for fake users
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

// Sync step from session after a GET (redirect after POST)
if (empty($_POST)) {
    $step = $_SESSION['fp_step'] ?? 'request';
    $fp   = $_SESSION['fp']      ?? [];
}

// Guard: if we're on otp/reset but session is gone or expired, restart
if (in_array($step, ['otp','reset']) && (empty($fp) || time() > ($fp['expires_at'] ?? 0))) {
    unset($_SESSION['fp_step'], $_SESSION['fp']);
    $step = 'request';
}

// On done page, clear session
if ($step === 'done') {
    unset($_SESSION['fp_step'], $_SESSION['fp']);
}

/* ── View helpers ─────────────────────────────────────────── */
$needs_totp = in_array($fp['role'] ?? '', FP_TOTP_ROLES, true)
           && !empty($fp['totp_enabled'])
           && !empty($fp['totp_secret']);

$masked_email = '';
if (!empty($fp['email'])) {
    $masked_email = preg_replace_callback('/^(.)(.*?)(@.+)$/', function($m) {
        return $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3];
    }, $fp['email']);
}

$wait_left = (!empty($fp['email'])) ? fp_cooldown_wait($fp['email']) : 0;
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

    /* ── Brand bar ── */
    .fp-brand {
      display: flex; align-items: center; gap: 10px;
      margin-bottom: 24px; justify-content: center;
    }
    .fp-brand-name { font-size: 15px; font-weight: 800; color: var(--brand); }
    .fp-brand-sub  { font-size: 11px; color: var(--text-muted); margin-top: 1px; }

    /* ── Card ── */
    .fp-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    /* ── Step pill ── */
    .fp-steps {
      display: flex; align-items: center; padding: 20px 28px 0;
    }
    .fp-step-dot {
      width: 26px; height: 26px; border-radius: 50%; font-size: 11px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      background: #E5E7EB; color: #9CA3AF; transition: .2s;
    }
    .fp-step-dot.active { background: var(--brand); color: #fff; }
    .fp-step-dot.done   { background: var(--success); color: #fff; }
    .fp-step-line { flex: 1; height: 2px; background: #E5E7EB; margin: 0 6px; }
    .fp-step-line.done { background: var(--success); }

    /* ── Card sections ── */
    .fp-header { padding: 20px 28px 0; }
    .fp-title  { font-size: 20px; font-weight: 800; color: var(--text); margin-bottom: 5px; }
    .fp-desc   { font-size: 13.5px; color: var(--text-muted); line-height: 1.6; }
    .fp-body   { padding: 24px 28px 28px; }

    /* ── Alert ── */
    .fp-alert {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 11px 14px; border-radius: var(--radius-sm);
      font-size: 13px; line-height: 1.55; margin-bottom: 18px;
      animation: fadeIn .2s ease;
    }
    .fp-alert-error   { background: var(--error-bg);   color: #B91C1C; border: 1px solid #FECACA; }
    .fp-alert-success { background: var(--success-bg); color: #065F46; border: 1px solid #A7F3D0; }
    .fp-alert-info    { background: var(--brand-light); color: #1E40AF; border: 1px solid #BFDBFE; }
    .fp-alert-warning { background: var(--warning-bg); color: #92400E; border: 1px solid #FDE68A; }
    .fp-alert svg { flex-shrink: 0; margin-top: 1px; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(-4px); } to { opacity:1; transform:translateY(0); } }

    /* ── Form fields ── */
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
    .fp-input::placeholder { color: var(--text-subtle); }
    .fp-input:focus  { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-input.invalid { border-color: var(--error);   background: var(--error-bg); }
    .fp-input.valid   { border-color: var(--success); }
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

    /* ── Strength ── */
    .fp-strength { margin-top: 6px; }
    .fp-strength-bar { height: 4px; background: var(--border); border-radius: 2px; overflow: hidden; }
    .fp-strength-fill { height: 100%; border-radius: 2px; width: 0%; transition: width .3s, background .3s; }
    .fp-strength-label { font-size: 11px; color: var(--text-subtle); margin-top: 4px; }
    .fp-strength-label span { font-weight: 700; }
    .fp-reqs { list-style: none; display: flex; flex-direction: column; gap: 3px; margin-top: 8px; }
    .fp-req-item { font-size: 12px; color: var(--text-subtle); display: flex; align-items: center; gap: 5px; transition: color .2s; }
    .fp-req-item.met { color: var(--success); }
    .fp-req-item .dot   { display: block; }
    .fp-req-item .check { display: none; }
    .fp-req-item.met .dot   { display: none; }
    .fp-req-item.met .check { display: block; }

    /* ── OTP digit boxes ── */
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
    .fp-otp-digit.filled { border-color: var(--brand); background: var(--brand-light); }
    .fp-otp-digit.invalid { border-color: var(--error); box-shadow: 0 0 0 3px rgba(220,38,38,.12); }
    #fp-otp-hidden { position: absolute; opacity: 0; pointer-events: none; width: 1px; }
    .fp-otp-timer { font-size: 12px; color: var(--text-subtle); text-align: center; margin-bottom: 4px; }
    .fp-otp-timer span { font-weight: 700; color: var(--text-muted); }

    /* ── Email chip ── */
    .fp-email-chip {
      display: inline-flex; align-items: center; gap: 6px;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: 999px; padding: 4px 12px;
      font-size: 13px; font-weight: 600; color: var(--text); margin: 8px 0 16px;
    }

    /* ── TOTP section ── */
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
    .fp-totp-input:focus { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .fp-totp-input.invalid { border-color: var(--error); box-shadow: 0 0 0 3px rgba(220,38,38,.12); }
    .fp-totp-refresh { font-size: 12px; color: var(--text-subtle); text-align: center; margin-top: 6px; }
    .fp-totp-refresh span { font-weight: 700; color: var(--brand); }

    /* ── Buttons ── */
    .fp-btn {
      width: 100%; padding: 12px 16px; border: none; border-radius: var(--radius-sm);
      font-family: var(--font); font-size: 14px; font-weight: 700; cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: 8px;
      transition: background .15s, box-shadow .15s, transform .1s;
      min-height: 48px;
    }
    .fp-btn-primary { background: var(--brand); color: #fff; }
    .fp-btn-primary:hover { background: var(--brand-hover); box-shadow: 0 4px 14px rgba(11,79,156,.25); }
    .fp-btn-primary:active { transform: scale(.98); }
    .fp-btn-primary:disabled { opacity: .55; cursor: not-allowed; transform: none; }
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

    /* ── Success ── */
    .fp-success { text-align: center; padding: 40px 28px; }
    .fp-success-icon {
      width: 72px; height: 72px; border-radius: 50%; background: var(--success-bg);
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 20px; color: var(--success);
      animation: popIn .4s cubic-bezier(.34,1.56,.64,1);
    }
    @keyframes popIn { from { transform:scale(.4); opacity:0; } to { transform:scale(1); opacity:1; } }
    .fp-success-title { font-size: 22px; font-weight: 800; margin-bottom: 10px; }
    .fp-success-desc  { font-size: 14px; color: var(--text-muted); line-height: 1.65; margin-bottom: 28px; }

    /* ── Footer ── */
    .fp-footer { text-align: center; font-size: 13px; color: var(--text-muted); margin-top: 18px; }
    .fp-footer a { color: var(--brand); font-weight: 600; text-decoration: none; }
    .fp-footer a:hover { text-decoration: underline; }

    @media (max-width: 480px) {
      body { padding: 20px 12px 48px; align-items: flex-start; }
      .fp-body { padding: 20px; }
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
    <!-- ══════════════════════ DONE ══════════════════════════════ -->
    <div class="fp-success">
      <div class="fp-success-icon">
        <svg width="34" height="34" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
      </div>
      <div class="fp-success-title">Password Reset!</div>
      <p class="fp-success-desc">
        Your password has been updated successfully.<br/>
        You can now sign in with your new password.
      </p>
      <a href="signin.php" class="fp-btn fp-btn-primary" style="text-decoration:none;display:inline-flex;max-width:240px;margin:0 auto">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>
        </svg>
        Sign In Now
      </a>
    </div>

    <?php else: ?>
    <!-- Progress steps (3 steps: Request → Verify → Reset) -->
    <div class="fp-steps">
      <div class="fp-step-dot <?= $step === 'request' ? 'active' : 'done' ?>">
        <?= $step === 'request' ? '1' : '✓' ?>
      </div>
      <div class="fp-step-line <?= in_array($step, ['otp','reset']) ? 'done' : '' ?>"></div>
      <div class="fp-step-dot <?= $step === 'otp' ? 'active' : ($step === 'reset' ? 'done' : '') ?>">
        <?= $step === 'reset' ? '✓' : '2' ?>
      </div>
      <div class="fp-step-line <?= $step === 'reset' ? 'done' : '' ?>"></div>
      <div class="fp-step-dot <?= $step === 'reset' ? 'active' : '' ?>">3</div>
    </div>

    <?php if ($step === 'request'): ?>
    <!-- ══════════════════════ STEP 1: REQUEST ═══════════════════ -->
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
    <!-- ══════════════════════ STEP 2: OTP ═══════════════════════ -->
    <div class="fp-header" style="margin-top:18px">
      <div class="fp-title">Verify your identity</div>
      <p class="fp-desc">
        We've sent a 6-digit code to your email.<?php if ($needs_totp): ?>
        Since your account has two-factor authentication, you'll also need your authenticator app code.<?php endif; ?>
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

      <form method="POST" id="fp-otp-form" novalidate>
        <input type="hidden" name="_action" value="verify_otp"/>
        <input type="hidden" name="otp" id="fp-otp-hidden"/>

        <label style="font-size:13px;font-weight:600;color:var(--text);display:block;margin-bottom:2px">
          Email verification code
        </label>
        <div class="fp-otp-inputs" role="group" aria-label="6-digit code">
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1" autocomplete="one-time-code"/>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2"/>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3"/>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4"/>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5"/>
          <input class="fp-otp-digit <?= !empty($errors['otp']) ? 'invalid' : '' ?>" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6"/>
        </div>
        <p class="fp-otp-timer">Code expires in <span id="fp-countdown">10:00</span></p>

        <?php if ($needs_totp): ?>
        <!-- ── TOTP section ── -->
        <div class="fp-totp-section">
          <div class="fp-totp-label">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
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
                 placeholder="000 000" maxlength="6"
                 inputmode="numeric" pattern="\d{6}"
                 autocomplete="one-time-code"/>
          <p class="fp-totp-refresh">Refreshes every <span id="fp-totp-cd">30</span>s</p>
        </div>
        <?php endif; ?>

        <button type="submit" class="fp-btn fp-btn-primary" id="fp-otp-btn" style="margin-top:18px" disabled>
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
          Confirm &amp; Continue
        </button>
      </form>

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
    <!-- ══════════════════════ STEP 3: RESET ═════════════════════ -->
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

        <!-- New password -->
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

        <!-- Confirm password -->
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
    <?php endif; /* done vs rest */ ?>

  </div><!-- /.fp-card -->

  <div class="fp-footer">
    <a href="signin.php">← Back to Sign In</a>
    &nbsp;·&nbsp;
    <a href="../registration/register.php">Create an account</a>
  </div>

</div><!-- /.fp-wrap -->

<script>
/* ── Password show/hide ── */
document.querySelectorAll('.fp-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const inp  = document.getElementById(btn.dataset.target);
    const show = inp.type === 'password';
    inp.type   = show ? 'text' : 'password';
    btn.querySelector('.eye-show').style.display = show ? 'none'  : '';
    btn.querySelector('.eye-hide').style.display = show ? ''      : 'none';
  });
});

/* ── Password strength ── */
const newPw  = document.getElementById('fp-new-pw');
const confPw = document.getElementById('fp-confirm-pw');
if (newPw) {
  const strDiv  = document.getElementById('fp-strength');
  const strFill = document.getElementById('fp-str-fill');
  const strText = document.getElementById('fp-str-text');
  const reqBox  = document.getElementById('fp-reqs');
  const reqs = {
    len:  { el: document.getElementById('fpq-len'), fn: v => v.length >= 8 },
    up:   { el: document.getElementById('fpq-up'),  fn: v => /[A-Z]/.test(v) },
    num:  { el: document.getElementById('fpq-num'), fn: v => /[0-9]/.test(v) },
    sp:   { el: document.getElementById('fpq-sp'),  fn: v => /[\W_]/.test(v) },
  };
  const levels = [
    {l:'Very Weak',c:'#EF4444',w:'15%'},
    {l:'Weak',     c:'#F97316',w:'35%'},
    {l:'Fair',     c:'#EAB308',w:'60%'},
    {l:'Strong',   c:'#22C55E',w:'85%'},
    {l:'Very Strong',c:'#059669',w:'100%'},
  ];
  function calcStr(v) {
    let s=0;
    if(v.length>=8)s++;if(v.length>=12)s++;
    if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[\W_]/.test(v))s++;
    return Math.min(s,4);
  }
  newPw.addEventListener('input', () => {
    const val = newPw.value;
    if (!val) { strDiv.style.display=reqBox.style.display='none'; newPw.classList.remove('valid','invalid'); return; }
    strDiv.style.display=reqBox.style.display='block';
    let met=0;
    Object.values(reqs).forEach(r=>{ const m=r.fn(val); r.el.classList.toggle('met',m); if(m)met++; });
    const lv=levels[calcStr(val)];
    strFill.style.width=lv.w; strFill.style.background=lv.c;
    strText.textContent=lv.l; strText.style.color=lv.c;
    newPw.classList.toggle('valid',  met===4);
    newPw.classList.toggle('invalid', val.length>3 && met<4);
    if(confPw&&confPw.value) syncConf();
  });
  function syncConf(){
    const ok = confPw.value===newPw.value&&confPw.value!=='';
    confPw.classList.toggle('valid',  ok);
    confPw.classList.toggle('invalid', !ok&&confPw.value.length>0);
  }
  if(confPw) confPw.addEventListener('input', syncConf);
}

/* ── OTP digit boxes ── */
(function(){
  const digits = Array.from(document.querySelectorAll('.fp-otp-digit'));
  const hidden = document.getElementById('fp-otp-hidden');
  const submitBtn = document.getElementById('fp-otp-btn');
  const totpInput = document.getElementById('fp-totp-input');
  if (!digits.length || !hidden) return;

  const needsTotp = <?= $needs_totp ? 'true' : 'false' ?>;

  digits[0]?.focus();

  function sync() {
    const val = digits.map(d=>d.value).join('');
    hidden.value = val;
    digits.forEach(d=>d.classList.toggle('filled', d.value!==''));
    const otpOk  = val.length===6;
    const totpOk = !needsTotp || (totpInput && totpInput.value.replace(/\D/g,'').length===6);
    if (submitBtn) submitBtn.disabled = !(otpOk && totpOk);
  }

  digits.forEach((box,i)=>{
    box.addEventListener('input',e=>{
      box.value=box.value.replace(/\D/g,'').slice(-1);
      sync();
      if(box.value&&i<digits.length-1) digits[i+1].focus();
    });
    box.addEventListener('keydown',e=>{
      if(e.key==='Backspace'){
        if(box.value){ box.value=''; sync(); }
        else if(i>0){ digits[i-1].focus(); digits[i-1].value=''; sync(); }
        e.preventDefault();
      }
      if(e.key==='ArrowLeft'&&i>0)            digits[i-1].focus();
      if(e.key==='ArrowRight'&&i<digits.length-1) digits[i+1].focus();
    });
    box.addEventListener('paste',e=>{
      e.preventDefault();
      const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'').slice(0,6);
      p.split('').forEach((ch,j)=>{ if(digits[j]) digits[j].value=ch; });
      sync();
      digits[Math.min(p.length,digits.length-1)].focus();
    });
  });

  if(totpInput){
    totpInput.addEventListener('input',()=>{
      totpInput.value=totpInput.value.replace(/\D/g,'').slice(0,6);
      sync();
    });
  }

  // 10-min countdown
  let secs = 10*60;
  const cdEl = document.getElementById('fp-countdown');
  function tick(){
    if(!cdEl) return;
    const m=Math.floor(secs/60).toString().padStart(2,'0');
    const s=(secs%60).toString().padStart(2,'0');
    cdEl.textContent=m+':'+s;
    if(secs<=60) cdEl.style.color='#DC2626';
    if(secs<=0){
      cdEl.textContent='Expired';
      if(submitBtn) submitBtn.disabled=true;
      digits.forEach(d=>{d.disabled=true;d.classList.add('invalid');});
      return;
    }
    secs--;
    setTimeout(tick,1000);
  }
  tick();

  // TOTP counter
  const totpCd = document.getElementById('fp-totp-cd');
  if(totpCd){
    function totpTick(){
      const s=30-(Math.floor(Date.now()/1000)%30);
      totpCd.textContent=s;
      totpCd.style.color=s<=5?'#DC2626':'#0B4F9C';
    }
    totpTick(); setInterval(totpTick,1000);
  }

  // Resend cooldown
  const resendBtn = document.getElementById('fp-resend-btn');
  if(resendBtn){
    let cd = <?= $wait_left ?>;
    let iv = null;
    function startCd(s){
      cd=s; resendBtn.disabled=true;
      iv=setInterval(()=>{
        cd--;
        resendBtn.textContent=`Resend code (${cd}s)`;
        if(cd<=0){ clearInterval(iv); resendBtn.disabled=false; resendBtn.textContent='Resend code'; }
      },1000);
    }
    if(cd>0) startCd(cd);
    document.getElementById('fp-resend-form')?.addEventListener('submit',()=>startCd(<?= FP_OTP_COOLDOWN ?>));
  }

  // Loading state on submit
  document.getElementById('fp-otp-form')?.addEventListener('submit',function(){
    if(submitBtn){ submitBtn.disabled=true; submitBtn.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Verifying…'; }
  });
})();

/* ── Step 1 submit loading ── */
document.getElementById('fp-req-btn')?.closest('form')?.addEventListener('submit',function(){
  const btn=document.getElementById('fp-req-btn');
  if(btn){ btn.disabled=true; btn.innerHTML='<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg> Sending…'; }
});
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>
</body>
</html>