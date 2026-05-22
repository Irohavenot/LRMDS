<?php
/**
 * change_password.php
 * Password change page for signed-in users.
 *
 * Learner / Parent roles → 3-step flow:
 *   Step 1 (FORM)   – fill current + new password, hit "Send Code"
 *   Step 2 (VERIFY) – enter 6-digit OTP emailed to them
 *   Step 3 (DONE)   – success state
 *
 * All other roles → original 1-step flow (form → done).
 */

session_start();

if (!isset($_SESSION['user']) || !$_SESSION['user']) {
    header('Location: index.php?signin=1');
    exit;
}

// ── DB connection ─────────────────────────────────────────────
$pdo = null;
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=lrmds;charset=utf8mb4',
        'root', '',
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]
    );
} catch (PDOException $e) {
    die('Database connection failed.');
}

// ── Fetch current user ────────────────────────────────────────
$user = null;
try {
    $s = $pdo->prepare('SELECT id, first_name, last_name, email, role, password_hash FROM users WHERE id = ? LIMIT 1');
    $s->execute([$_SESSION['user_id'] ?? 0]);
    $user = $s->fetch();
} catch (PDOException $e) { /* handled below */ }

if (!$user) {
    header('Location: index.php?signin=1');
    exit;
}

// ── OTP roles ─────────────────────────────────────────────────
define('CP_OTP_ROLES', ['learner', 'parent']);
$needs_otp = in_array($user['role'], CP_OTP_ROLES, true);

// ── State vars ────────────────────────────────────────────────
$errors       = [];
$success      = false;
$otp_sent     = false;   // true → show Step 2 (verify screen)
$otp_error    = '';      // inline error for the OTP input
$otp_resent   = false;

// ── Rate-limit helper (reuse same pattern as resend_verification) ──
define('CP_OTP_COOLDOWN', 60); // seconds
function cp_otp_cooldown_wait(int $user_id): int {
    $key = 'cp_otp_ts_' . $user_id;
    $last = $_SESSION[$key] ?? 0;
    return max(0, CP_OTP_COOLDOWN - (time() - $last));
}
function cp_otp_record_send(int $user_id): void {
    $_SESSION['cp_otp_ts_' . $user_id] = time();
}

/* ══════════════════════════════════════════════════════════════
   POST handlers
══════════════════════════════════════════════════════════════ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['_action'] ?? 'submit_form';

    /* ── ACTION: submit_form ──────────────────────────────────
     * Validates the password fields. For OTP roles, sends the
     * code and moves to Step 2. For other roles, persists now.
     */
    if ($action === 'submit_form') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password']     ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validate current
        if (empty($current)) {
            $errors['current_password'] = 'Please enter your current password.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        // Validate new
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
        } elseif ($new === $current) {
            $errors['new_password'] = 'New password must be different from your current password.';
        }

        // Validate confirm
        if (empty($confirm)) {
            $errors['confirm_password'] = 'Please confirm your new password.';
        } elseif ($new !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (empty($errors)) {

            if ($needs_otp) {
                /* ── Send OTP, stash pending password in session ── */
                $wait = cp_otp_cooldown_wait((int)$user['id']);
                if ($wait > 0) {
                    $errors['general'] = "Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . " before requesting a new code.";
                } else {
                    require_once __DIR__ . '/lib/send_password_otp.php';
                    [$ok, $lookup_token, $err] = send_password_otp(
                        $pdo, (int)$user['id'], $user['email'], $user['first_name']
                    );

                    if ($ok) {
                        cp_otp_record_send((int)$user['id']);
                        // Stash new password hash + lookup token in session
                        $_SESSION['cp_pending'] = [
                            'hash'         => password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]),
                            'lookup_token' => $lookup_token,
                            'expires_at'   => time() + 600,
                        ];
                        $otp_sent = true;
                    } else {
                        error_log("CP OTP send fail uid={$user['id']}: $err");
                        $errors['general'] = 'Could not send verification email. Please try again.';
                    }
                }

            } else {
                /* ── Non-OTP roles: update immediately ── */
                try {
                    $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([$hash, $user['id']]);
                    $success = true;
                } catch (PDOException $e) {
                    $errors['general'] = 'Something went wrong. Please try again.';
                }
            }
        }
    }

    /* ── ACTION: verify_otp ───────────────────────────────────
     * Checks the submitted 6-digit code. On success, applies
     * the password that was stashed in the session.
     */
    elseif ($action === 'verify_otp' && $needs_otp) {

        $pending = $_SESSION['cp_pending'] ?? null;

        if (!$pending || time() > $pending['expires_at']) {
            // Session expired — send them back to Step 1
            unset($_SESSION['cp_pending']);
            $errors['general'] = 'Your session has expired. Please fill in the form again.';
        } else {
            $submitted_otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

            require_once __DIR__ . '/lib/send_password_otp.php';
            [$ok, $uid, $err] = verify_password_otp($pdo, $pending['lookup_token'], $submitted_otp);

            if ($ok && $uid === (int)$user['id']) {
                try {
                    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([$pending['hash'], $user['id']]);
                    unset($_SESSION['cp_pending']);
                    $success = true;
                } catch (PDOException $e) {
                    $otp_error = 'Something went wrong saving your password. Please try again.';
                    $otp_sent  = true;
                }
            } else {
                $otp_error = $err ?: 'Incorrect code. Please try again.';
                $otp_sent  = true; // stay on step 2
            }
        }
    }

    /* ── ACTION: resend_otp ───────────────────────────────────
     * Re-sends a fresh OTP (rate-limited).
     */
    elseif ($action === 'resend_otp' && $needs_otp) {

        $pending = $_SESSION['cp_pending'] ?? null;

        if (!$pending || time() > $pending['expires_at']) {
            unset($_SESSION['cp_pending']);
            $errors['general'] = 'Your session has expired. Please fill in the form again.';
        } else {
            $wait = cp_otp_cooldown_wait((int)$user['id']);
            if ($wait > 0) {
                $otp_error = "Please wait {$wait} second" . ($wait !== 1 ? 's' : '') . " before requesting a new code.";
                $otp_sent  = true;
            } else {
                require_once __DIR__ . '/lib/send_password_otp.php';
                [$ok, $lookup_token, $err] = send_password_otp(
                    $pdo, (int)$user['id'], $user['email'], $user['first_name']
                );

                if ($ok) {
                    cp_otp_record_send((int)$user['id']);
                    $_SESSION['cp_pending']['lookup_token'] = $lookup_token;
                    $_SESSION['cp_pending']['expires_at']  = time() + 600;
                    $otp_resent = true;
                } else {
                    $otp_error = 'Could not resend the code. Please try again.';
                }
                $otp_sent = true;
            }
        }
    }
}

// If there's a pending session from a previous POST (page reload), show step 2
if (!$otp_sent && !$success && empty($errors) && $needs_otp && isset($_SESSION['cp_pending'])) {
    if (time() <= $_SESSION['cp_pending']['expires_at']) {
        $otp_sent = true;
    } else {
        unset($_SESSION['cp_pending']);
    }
}

// ── View helpers ──────────────────────────────────────────────
$display_name  = htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name']));
$display_email = htmlspecialchars($user['email']);

$role_map = [
    'admin'       => ['label' => 'Administrator', 'color' => '#7C3AED', 'bg' => '#F5F3FF'],
    'developer'   => ['label' => 'Developer',     'color' => '#0891B2', 'bg' => '#ECFEFF'],
    'school-head' => ['label' => 'School Head',   'color' => '#059669', 'bg' => '#ECFDF5'],
    'teacher'     => ['label' => 'Teacher',       'color' => '#D97706', 'bg' => '#FFFBEB'],
    'learner'     => ['label' => 'Learner',       'color' => '#2563EB', 'bg' => '#EFF6FF'],
    'parent'      => ['label' => 'Parent',        'color' => '#DB2777', 'bg' => '#FDF2F8'],
    'partner'     => ['label' => 'Partner',       'color' => '#EA580C', 'bg' => '#FFF7ED'],
];
$role_info = $role_map[$user['role']] ?? ['label' => ucfirst($user['role'] ?: 'User'), 'color' => '#6B7280', 'bg' => '#F9FAFB'];

$initials = strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? '', 0, 1));

// Masked email for display in OTP step (e.g. j***@gmail.com)
$masked_email = preg_replace_callback('/^(.)(.*?)(@.+)$/', function($m) {
    return $m[1] . str_repeat('*', max(1, strlen($m[2]))) . $m[3];
}, $user['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password — LRMDS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ── Reset & base ───────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --brand:        #2563EB;
      --brand-hover:  #1D4ED8;
      --brand-light:  #EFF6FF;
      --brand-ring:   rgba(37,99,235,.25);
      --surface:      #FFFFFF;
      --surface-2:    #F8FAFC;
      --border:       #E2E8F0;
      --border-focus: #2563EB;
      --text:         #0F172A;
      --text-muted:   #64748B;
      --text-subtle:  #94A3B8;
      --success:      #059669;
      --success-bg:   #ECFDF5;
      --success-ring: rgba(5,150,105,.2);
      --error:        #DC2626;
      --error-bg:     #FEF2F2;
      --error-ring:   rgba(220,38,38,.2);
      --warning:      #D97706;
      --warning-bg:   #FFFBEB;
      --radius:       12px;
      --radius-sm:    8px;
      --shadow-sm:    0 1px 2px rgba(0,0,0,.05);
      --shadow:       0 4px 16px rgba(0,0,0,.08);
      --shadow-lg:    0 8px 32px rgba(0,0,0,.10);
      --font:         'DM Sans', system-ui, sans-serif;
      --font-mono:    'DM Mono', monospace;
    }

    html { font-family: var(--font); font-size: 15px; }
    body {
      background: var(--surface-2);
      color: var(--text);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    /* ── Page layout ────────────────────────────────────── */
    .cp-page {
      flex: 1;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 2rem 1rem 4rem;
      gap: 1.5rem;
    }

    /* ── Back link ──────────────────────────────────────── */
    .cp-back {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      color: var(--text-muted);
      font-size: .85rem;
      font-weight: 500;
      text-decoration: none;
      padding: .4rem .6rem;
      border-radius: var(--radius-sm);
      transition: color .15s, background .15s;
      margin-bottom: 1rem;
    }
    .cp-back:hover { color: var(--brand); background: var(--brand-light); }

    /* ── Card wrapper ───────────────────────────────────── */
    .cp-wrap { width: 100%; max-width: 500px; }

    /* ── User summary card ──────────────────────────────── */
    .cp-user-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      padding: 1.25rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
      box-shadow: var(--shadow-sm);
    }
    .cp-avatar {
      width: 48px; height: 48px;
      border-radius: 50%;
      background: var(--brand);
      color: #fff;
      font-size: .95rem;
      font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
      letter-spacing: .03em;
    }
    .cp-user-info { flex: 1; min-width: 0; }
    .cp-user-name {
      font-size: 1rem; font-weight: 600; color: var(--text);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cp-user-email {
      font-size: .8rem; color: var(--text-muted); margin-top: .1rem;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .cp-role-badge {
      font-size: .72rem; font-weight: 600; letter-spacing: .03em;
      padding: .25em .65em; border-radius: 100px; white-space: nowrap;
    }

    /* ── Main card ──────────────────────────────────────── */
    .cp-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
    }
    .cp-card-header {
      padding: 1.5rem 1.75rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .cp-card-title {
      font-size: 1.1rem; font-weight: 700; color: var(--text);
      display: flex; align-items: center; gap: .6rem;
    }
    .cp-card-title svg { color: var(--brand); }
    .cp-card-desc { font-size: .83rem; color: var(--text-muted); margin-top: .35rem; line-height: 1.55; }
    .cp-card-body { padding: 1.75rem; }

    /* ── Alert banners ──────────────────────────────────── */
    .cp-alert {
      display: flex; align-items: flex-start; gap: .75rem;
      padding: .9rem 1rem; border-radius: var(--radius-sm);
      font-size: .85rem; line-height: 1.5; margin-bottom: 1.5rem;
      animation: fadeSlideIn .25s ease;
    }
    .cp-alert-success { background: var(--success-bg); color: var(--success); border: 1px solid rgba(5,150,105,.2); }
    .cp-alert-error   { background: var(--error-bg);   color: var(--error);   border: 1px solid rgba(220,38,38,.2); }
    .cp-alert-warning { background: var(--warning-bg); color: #92400E;        border: 1px solid rgba(217,119,6,.25); }
    .cp-alert-info    { background: var(--brand-light); color: #1E40AF;       border: 1px solid rgba(37,99,235,.2); }
    .cp-alert svg { flex-shrink: 0; margin-top: .05rem; }
    .cp-alert strong { font-weight: 600; }

    @keyframes fadeSlideIn {
      from { opacity: 0; transform: translateY(-6px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Form elements ──────────────────────────────────── */
    .cp-form { display: flex; flex-direction: column; gap: 1.25rem; }
    .cp-field { display: flex; flex-direction: column; gap: .4rem; }
    .cp-label { font-size: .82rem; font-weight: 600; color: var(--text); letter-spacing: .02em; }
    .cp-input-wrap { position: relative; }
    .cp-input {
      width: 100%; padding: .7rem .75rem .7rem 2.6rem;
      font-family: var(--font); font-size: .9rem; color: var(--text);
      background: var(--surface); border: 1.5px solid var(--border);
      border-radius: var(--radius-sm); outline: none;
      transition: border-color .15s, box-shadow .15s; appearance: none;
    }
    .cp-input::placeholder { color: var(--text-subtle); }
    .cp-input:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px var(--brand-ring); }
    .cp-input.has-error { border-color: var(--error);   box-shadow: 0 0 0 3px var(--error-ring); }
    .cp-input.is-valid  { border-color: var(--success); box-shadow: 0 0 0 3px var(--success-ring); }
    .cp-input-icon {
      position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
      color: var(--text-subtle); pointer-events: none; transition: color .15s;
    }
    .cp-input-wrap:focus-within .cp-input-icon { color: var(--brand); }
    .cp-toggle-vis {
      position: absolute; right: .75rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; cursor: pointer; color: var(--text-subtle);
      padding: .2rem; border-radius: 4px; display: flex; transition: color .15s;
    }
    .cp-toggle-vis:hover { color: var(--brand); }
    .cp-field-error {
      font-size: .78rem; color: var(--error);
      display: flex; align-items: center; gap: .3rem; animation: fadeSlideIn .2s ease;
    }
    .cp-divider { border: none; border-top: 1px solid var(--border); margin: .25rem 0; }

    /* ── Password strength ──────────────────────────────── */
    .cp-strength { margin-top: .5rem; }
    .cp-strength-bar { height: 4px; border-radius: 100px; background: var(--border); overflow: hidden; }
    .cp-strength-fill { height: 100%; border-radius: 100px; transition: width .3s ease, background .3s ease; width: 0%; }
    .cp-strength-label { font-size: .75rem; color: var(--text-subtle); margin-top: .3rem; display: flex; align-items: center; gap: .35rem; }
    .cp-strength-label span { font-weight: 600; }

    /* ── Requirements ───────────────────────────────────── */
    .cp-requirements { list-style: none; display: flex; flex-direction: column; gap: .3rem; margin-top: .6rem; }
    .cp-req { display: flex; align-items: center; gap: .45rem; font-size: .78rem; color: var(--text-subtle); transition: color .2s; }
    .cp-req.met { color: var(--success); }
    .cp-req-icon { flex-shrink: 0; transition: opacity .2s; }
    .cp-req-icon-check { display: none; }
    .cp-req.met .cp-req-icon-dot   { display: none; }
    .cp-req.met .cp-req-icon-check { display: block; }

    /* ── Submit button ──────────────────────────────────── */
    .cp-submit {
      width: 100%; padding: .8rem 1rem;
      background: var(--brand); color: #fff;
      font-family: var(--font); font-size: .9rem; font-weight: 600;
      border: none; border-radius: var(--radius-sm); cursor: pointer;
      display: flex; align-items: center; justify-content: center; gap: .5rem;
      transition: background .15s, transform .1s, box-shadow .15s;
      letter-spacing: .01em; margin-top: .25rem;
    }
    .cp-submit:hover  { background: var(--brand-hover); box-shadow: 0 4px 12px rgba(37,99,235,.3); }
    .cp-submit:active { transform: scale(.98); }
    .cp-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

    /* ── Security tips ──────────────────────────────────── */
    .cp-tips { background: var(--warning-bg); border: 1px solid rgba(217,119,6,.15); border-radius: var(--radius-sm); padding: .9rem 1rem; margin-top: 1.5rem; }
    .cp-tips-title { font-size: .8rem; font-weight: 700; color: var(--warning); display: flex; align-items: center; gap: .4rem; margin-bottom: .5rem; }
    .cp-tips-list { list-style: none; display: flex; flex-direction: column; gap: .3rem; }
    .cp-tips-list li { font-size: .78rem; color: #92400E; display: flex; align-items: flex-start; gap: .4rem; line-height: 1.5; }
    .cp-tips-list li::before { content: '•'; flex-shrink: 0; margin-top: .05rem; }

    /* ── Success state ──────────────────────────────────── */
    .cp-success-body { padding: 2.5rem 1.75rem; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 1rem; }
    .cp-success-icon { width: 64px; height: 64px; border-radius: 50%; background: var(--success-bg); display: flex; align-items: center; justify-content: center; color: var(--success); animation: popIn .35s cubic-bezier(.34,1.56,.64,1); }
    @keyframes popIn { from { transform: scale(.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .cp-success-title { font-size: 1.15rem; font-weight: 700; color: var(--text); }
    .cp-success-desc { font-size: .88rem; color: var(--text-muted); line-height: 1.6; max-width: 300px; }
    .cp-success-actions { display: flex; flex-direction: column; gap: .6rem; width: 100%; max-width: 260px; margin-top: .5rem; }
    .cp-btn-primary { display: flex; align-items: center; justify-content: center; gap: .45rem; padding: .75rem 1rem; background: var(--brand); color: #fff; font-family: var(--font); font-size: .88rem; font-weight: 600; text-decoration: none; border-radius: var(--radius-sm); transition: background .15s; }
    .cp-btn-primary:hover { background: var(--brand-hover); }
    .cp-btn-ghost { display: flex; align-items: center; justify-content: center; gap: .45rem; padding: .72rem 1rem; background: transparent; color: var(--text-muted); font-family: var(--font); font-size: .88rem; font-weight: 500; text-decoration: none; border: 1.5px solid var(--border); border-radius: var(--radius-sm); transition: color .15s, border-color .15s; }
    .cp-btn-ghost:hover { color: var(--brand); border-color: var(--brand); }

    /* ── OTP Step 2 ─────────────────────────────────────── */
    .otp-header-strip {
      padding: 1.5rem 1.75rem 1.25rem;
      border-bottom: 1px solid var(--border);
    }
    .otp-step-badge {
      display: inline-flex; align-items: center; gap: .4rem;
      background: var(--brand-light); color: var(--brand);
      font-size: .72rem; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; padding: .25em .75em;
      border-radius: 100px; margin-bottom: .75rem;
    }
    .otp-email-chip {
      display: inline-flex; align-items: center; gap: .35rem;
      background: var(--surface-2); border: 1px solid var(--border);
      border-radius: 100px; padding: .3em .85em;
      font-size: .82rem; font-weight: 600; color: var(--text);
      margin: .6rem 0 1rem;
    }
    /* OTP digit boxes */
    .otp-inputs {
      display: flex; gap: .6rem; justify-content: center; margin: 1.5rem 0 .5rem;
    }
    .otp-digit {
      width: 52px; height: 60px;
      text-align: center;
      font-family: var(--font-mono);
      font-size: 1.6rem; font-weight: 700;
      color: var(--text);
      background: var(--surface);
      border: 2px solid var(--border);
      border-radius: 10px;
      outline: none;
      caret-color: transparent;
      transition: border-color .15s, box-shadow .15s;
    }
    .otp-digit:focus  { border-color: var(--brand); box-shadow: 0 0 0 3px var(--brand-ring); }
    .otp-digit.filled { border-color: var(--brand); background: var(--brand-light); }
    .otp-digit.has-error { border-color: var(--error); box-shadow: 0 0 0 3px var(--error-ring); }
    /* Hidden real input that holds the assembled value */
    #otp-hidden { position: absolute; opacity: 0; pointer-events: none; width: 1px; }

    .otp-timer { font-size: .8rem; color: var(--text-subtle); text-align: center; margin-top: .25rem; }
    .otp-timer span { font-weight: 600; color: var(--text-muted); }

    .otp-resend-btn {
      background: none; border: none; cursor: pointer;
      font-family: var(--font); font-size: .82rem;
      font-weight: 600; color: var(--brand); padding: 0;
      text-decoration: underline; text-underline-offset: 2px;
      transition: color .15s;
    }
    .otp-resend-btn:disabled { color: var(--text-subtle); text-decoration: none; cursor: default; }

    .otp-back-link {
      display: inline-flex; align-items: center; gap: .3rem;
      font-size: .8rem; color: var(--text-muted); font-weight: 500;
      background: none; border: none; cursor: pointer; font-family: var(--font);
      padding: 0; margin-top: .25rem; text-decoration: underline; text-underline-offset: 2px;
      transition: color .15s;
    }
    .otp-back-link:hover { color: var(--brand); }

    @media (max-width: 420px) {
      .otp-digit { width: 42px; height: 52px; font-size: 1.3rem; }
      .otp-inputs { gap: .4rem; }
    }

    /* ── Responsive ─────────────────────────────────────── */
    @media (max-width: 540px) {
      .cp-page { padding: 1.25rem .75rem 3rem; }
      .cp-card-body { padding: 1.25rem; }
      .cp-card-header, .otp-header-strip { padding: 1.25rem 1.25rem 1rem; }
    }
  </style>
</head>
<body>

<div class="cp-page">
  <div class="cp-wrap">

    <!-- Back link -->
    <a href="javascript:history.back()" class="cp-back">
      <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M19 12H5M12 5l-7 7 7 7"/>
      </svg>
      Back
    </a>

    <!-- User summary -->
    <div class="cp-user-card">
      <div class="cp-avatar"><?= $initials ?></div>
      <div class="cp-user-info">
        <div class="cp-user-name"><?= $display_name ?></div>
        <div class="cp-user-email"><?= $display_email ?></div>
      </div>
      <span class="cp-role-badge" style="color:<?= $role_info['color'] ?>;background:<?= $role_info['bg'] ?>">
        <?= $role_info['label'] ?>
      </span>
    </div>

    <!-- Main card -->
    <div class="cp-card">

      <?php if ($success): ?>
      <!-- ════════════════════════════════ SUCCESS ══════════════════════════════ -->
      <div class="cp-success-body">
        <div class="cp-success-icon">
          <svg width="30" height="30" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path d="M20 6 9 17l-5-5"/>
          </svg>
        </div>
        <div class="cp-success-title">Password Changed</div>
        <p class="cp-success-desc">
          Your password has been updated successfully. You'll use your new password the next time you sign in.
        </p>
        <div class="cp-success-actions">
          <a href="index.php" class="cp-btn-primary">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
            Go to Home Page
          </a>
          <a href="profile_edit.php" class="cp-btn-ghost">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5Z"/>
            </svg>
            Edit Profile
          </a>
        </div>
      </div>

      <?php elseif ($otp_sent): ?>
      <!-- ════════════════════ STEP 2 — OTP VERIFY ════════════════════════════ -->

      <div class="otp-header-strip">
        <div class="cp-card-title">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/>
          </svg>
          Verify Your Identity
        </div>
        <p class="cp-card-desc">
          We sent a 6-digit code to your email address to confirm this password change.
        </p>
      </div>

      <div class="cp-card-body">

        <?php if ($otp_resent): ?>
        <div class="cp-alert cp-alert-success" role="alert">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
          </svg>
          <div>A new code has been sent to your email.</div>
        </div>
        <?php endif; ?>

        <?php if ($otp_error): ?>
        <div class="cp-alert cp-alert-error" role="alert">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <div><?= htmlspecialchars($otp_error) ?></div>
        </div>
        <?php endif; ?>

        <!-- Sent-to chip -->
        <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.25rem;">Code sent to:</p>
        <div class="otp-email-chip">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/>
          </svg>
          <?= htmlspecialchars($masked_email) ?>
        </div>

        <!-- OTP form -->
        <form method="POST" id="otp-form" novalidate>
          <input type="hidden" name="_action" value="verify_otp">
          <input type="hidden" name="otp" id="otp-hidden" maxlength="6">

          <label style="font-size:.82rem;font-weight:600;color:var(--text);display:block;margin-bottom:.25rem;">
            Enter your 6-digit code
          </label>

          <!-- Visual digit boxes -->
          <div class="otp-inputs" role="group" aria-label="6-digit verification code">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 1" autocomplete="one-time-code">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 2">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 3">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 4">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 5">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" aria-label="Digit 6">
          </div>

          <!-- Countdown timer -->
          <p class="otp-timer">Code expires in <span id="otp-countdown">10:00</span></p>

          <button type="submit" class="cp-submit" id="otp-submit" style="margin-top:1.25rem;" disabled>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path d="M20 6 9 17l-5-5"/>
            </svg>
            Confirm & Change Password
          </button>
        </form>

        <!-- Resend + go-back -->
        <div style="margin-top:1.25rem;display:flex;flex-direction:column;align-items:center;gap:.6rem;">
          <p style="font-size:.82rem;color:var(--text-muted);">
            Didn't receive it?
            <form method="POST" style="display:inline" id="resend-form">
              <input type="hidden" name="_action" value="resend_otp">
              <button type="submit" class="otp-resend-btn" id="resend-btn">Resend code</button>
            </form>
          </p>
          <form method="POST">
            <input type="hidden" name="_action" value="submit_form">
            <button type="submit" class="otp-back-link" formaction="change_password.php">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M19 12H5M12 5l-7 7 7 7"/>
              </svg>
              Use a different email / start over
            </button>
          </form>
        </div>

      </div><!-- /.cp-card-body -->

      <?php else: ?>
      <!-- ════════════════════ STEP 1 — PASSWORD FORM ═════════════════════════ -->

      <div class="cp-card-header">
        <div class="cp-card-title">
          <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
          Change Password
        </div>
        <p class="cp-card-desc">
          Create a strong, unique password to keep your account secure.
          <?php if ($needs_otp): ?>
          A confirmation code will be emailed to you before the change is applied.
          <?php endif; ?>
        </p>
      </div>

      <div class="cp-card-body">

        <!-- General error -->
        <?php if (!empty($errors['general'])): ?>
        <div class="cp-alert cp-alert-error" role="alert">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <div><?= htmlspecialchars($errors['general']) ?></div>
        </div>
        <?php endif; ?>

        <?php if ($needs_otp): ?>
        <!-- Info chip for OTP roles -->
        <div class="cp-alert cp-alert-info" role="note" style="margin-bottom:1.25rem;">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:.05rem">
            <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/>
          </svg>
          <div>
            After filling in the form you'll receive a <strong>6-digit code</strong> at
            <strong><?= htmlspecialchars($masked_email) ?></strong> to confirm the change.
          </div>
        </div>
        <?php endif; ?>

        <form method="POST" id="cp-form" class="cp-form" novalidate autocomplete="off">
          <input type="hidden" name="_action" value="submit_form">

          <!-- Current password -->
          <div class="cp-field">
            <label class="cp-label" for="current_password">Current Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </span>
              <input type="password" id="current_password" name="current_password"
                class="cp-input<?= !empty($errors['current_password']) ? ' has-error' : '' ?>"
                placeholder="Enter your current password" autocomplete="current-password" required>
              <button type="button" class="cp-toggle-vis" data-target="current_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"     width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['current_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <?= htmlspecialchars($errors['current_password']) ?>
            </div>
            <?php endif; ?>
          </div>

          <hr class="cp-divider">

          <!-- New password -->
          <div class="cp-field">
            <label class="cp-label" for="new_password">New Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
                </svg>
              </span>
              <input type="password" id="new_password" name="new_password"
                class="cp-input<?= !empty($errors['new_password']) ? ' has-error' : '' ?>"
                placeholder="Create a strong new password" autocomplete="new-password" required>
              <button type="button" class="cp-toggle-vis" data-target="new_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"     width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['new_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <?= htmlspecialchars($errors['new_password']) ?>
            </div>
            <?php endif; ?>

            <!-- Strength meter -->
            <div class="cp-strength" id="strength-meter" style="display:none">
              <div class="cp-strength-bar"><div class="cp-strength-fill" id="strength-fill"></div></div>
              <div class="cp-strength-label">Strength: <span id="strength-text">—</span></div>
            </div>

            <!-- Requirements -->
            <ul class="cp-requirements" id="pw-requirements" style="display:none">
              <li class="cp-req" id="req-length">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>At least 8 characters
              </li>
              <li class="cp-req" id="req-upper">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>One uppercase letter
              </li>
              <li class="cp-req" id="req-number">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>One number
              </li>
              <li class="cp-req" id="req-special">
                <span class="cp-req-icon">
                  <svg class="cp-req-icon-dot"   width="6" height="6" fill="currentColor" viewBox="0 0 6 6"><circle cx="3" cy="3" r="3"/></svg>
                  <svg class="cp-req-icon-check" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>
                </span>One special character
              </li>
            </ul>
          </div>

          <!-- Confirm password -->
          <div class="cp-field">
            <label class="cp-label" for="confirm_password">Confirm New Password</label>
            <div class="cp-input-wrap">
              <span class="cp-input-icon">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M20 6 9 17l-5-5"/>
                </svg>
              </span>
              <input type="password" id="confirm_password" name="confirm_password"
                class="cp-input<?= !empty($errors['confirm_password']) ? ' has-error' : '' ?>"
                placeholder="Re-enter your new password" autocomplete="new-password" required>
              <button type="button" class="cp-toggle-vis" data-target="confirm_password" aria-label="Toggle password visibility">
                <svg class="icon-eye"     width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="icon-eye-off" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['confirm_password'])): ?>
            <div class="cp-field-error">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <?= htmlspecialchars($errors['confirm_password']) ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Submit -->
          <button type="submit" class="cp-submit" id="cp-submit">
            <?php if ($needs_otp): ?>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/>
            </svg>
            Send Verification Code
            <?php else: ?>
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Update Password
            <?php endif; ?>
          </button>

        </form>

        <!-- Security tips -->
        <div class="cp-tips">
          <div class="cp-tips-title">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
              <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>
            </svg>
            Security Tips
          </div>
          <ul class="cp-tips-list">
            <li>Never reuse passwords across multiple sites.</li>
            <li>Consider using a password manager to generate and store strong passwords.</li>
            <li>Enable two-factor authentication (2FA) for extra protection.</li>
          </ul>
        </div>

      </div><!-- /.cp-card-body -->
      <?php endif; ?>

    </div><!-- /.cp-card -->
  </div><!-- /.cp-wrap -->
</div><!-- /.cp-page -->

<script>
/* ── Password show / hide toggles ──────────────────────────── */
document.querySelectorAll('.cp-toggle-vis').forEach(btn => {
  btn.addEventListener('click', () => {
    const input  = document.getElementById(btn.dataset.target);
    const isText = input.type === 'text';
    input.type   = isText ? 'password' : 'text';
    btn.querySelector('.icon-eye').style.display     = isText ? 'block' : 'none';
    btn.querySelector('.icon-eye-off').style.display = isText ? 'none'  : 'block';
  });
});

/* ── Password strength + live requirements ──────────────────── */
const newInput  = document.getElementById('new_password');
const confInput = document.getElementById('confirm_password');

if (newInput) {
  const meter  = document.getElementById('strength-meter');
  const fill   = document.getElementById('strength-fill');
  const label  = document.getElementById('strength-text');
  const reqBox = document.getElementById('pw-requirements');

  const reqs = {
    length:  { el: document.getElementById('req-length'),  re: v => v.length >= 8 },
    upper:   { el: document.getElementById('req-upper'),   re: v => /[A-Z]/.test(v) },
    number:  { el: document.getElementById('req-number'),  re: v => /[0-9]/.test(v) },
    special: { el: document.getElementById('req-special'), re: v => /[\W_]/.test(v) },
  };

  const levels = [
    { label: 'Very Weak', color: '#EF4444', width: '15%' },
    { label: 'Weak',      color: '#F97316', width: '35%' },
    { label: 'Fair',      color: '#EAB308', width: '60%' },
    { label: 'Strong',    color: '#22C55E', width: '85%' },
    { label: 'Very Strong', color: '#059669', width: '100%' },
  ];

  function calcStrength(v) {
    let s = 0;
    if (v.length >= 8)  s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[\W_]/.test(v)) s++;
    return Math.min(s, 4);
  }

  newInput.addEventListener('input', () => {
    const val = newInput.value;
    if (!val) {
      meter.style.display  = 'none';
      reqBox.style.display = 'none';
      newInput.classList.remove('is-valid', 'has-error');
      return;
    }
    meter.style.display  = 'block';
    reqBox.style.display = 'flex';

    let metCount = 0;
    Object.values(reqs).forEach(r => {
      const met = r.re(val);
      r.el.classList.toggle('met', met);
      if (met) metCount++;
    });

    const lvl = levels[calcStrength(val)];
    fill.style.width      = lvl.width;
    fill.style.background = lvl.color;
    label.textContent     = lvl.label;
    label.style.color     = lvl.color;

    newInput.classList.toggle('is-valid',  metCount === 4);
    newInput.classList.toggle('has-error', newInput.value.length > 3 && metCount < 4);

    if (confInput && confInput.value) validateConfirm();
  });

  function validateConfirm() {
    const match = confInput.value === newInput.value && confInput.value !== '';
    confInput.classList.toggle('is-valid',  match);
    confInput.classList.toggle('has-error', !match && confInput.value.length > 0);
  }
  if (confInput) confInput.addEventListener('input', validateConfirm);
}

/* ── Step 1 submit loading state ────────────────────────────── */
document.getElementById('cp-form')?.addEventListener('submit', function() {
  const btn = document.getElementById('cp-submit');
  if (!btn) return;
  btn.disabled = true;
  btn.innerHTML = `
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite">
      <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
    </svg>
    <?php echo $needs_otp ? 'Sending code…' : 'Updating…'; ?>`;
});

/* ════════════════════════════════════════════════════════════
   OTP digit-box UX  (Step 2 only)
════════════════════════════════════════════════════════════ */
(function () {
  const digits   = Array.from(document.querySelectorAll('.otp-digit'));
  const hidden   = document.getElementById('otp-hidden');
  const submitBtn = document.getElementById('otp-submit');
  if (!digits.length || !hidden) return;

  /* Focus first box on load */
  digits[0].focus();

  function syncHidden() {
    const val = digits.map(d => d.value).join('');
    hidden.value = val;
    digits.forEach(d => d.classList.toggle('filled', d.value !== ''));
    if (submitBtn) submitBtn.disabled = val.length < 6;
  }

  digits.forEach((box, i) => {
    box.addEventListener('input', e => {
      /* Strip non-digits, keep only last char typed */
      box.value = box.value.replace(/\D/g, '').slice(-1);
      syncHidden();
      if (box.value && i < digits.length - 1) digits[i + 1].focus();
    });

    box.addEventListener('keydown', e => {
      if (e.key === 'Backspace') {
        if (box.value) {
          box.value = '';
          syncHidden();
        } else if (i > 0) {
          digits[i - 1].focus();
          digits[i - 1].value = '';
          syncHidden();
        }
        e.preventDefault();
      }
      if (e.key === 'ArrowLeft'  && i > 0)              digits[i - 1].focus();
      if (e.key === 'ArrowRight' && i < digits.length-1) digits[i + 1].focus();
    });

    /* Handle paste of the full code */
    box.addEventListener('paste', e => {
      e.preventDefault();
      const pasted = (e.clipboardData || window.clipboardData)
        .getData('text').replace(/\D/g, '').slice(0, 6);
      pasted.split('').forEach((ch, j) => { if (digits[j]) digits[j].value = ch; });
      syncHidden();
      const next = Math.min(pasted.length, digits.length - 1);
      digits[next].focus();
    });
  });

  /* OTP submit loading state */
  document.getElementById('otp-form')?.addEventListener('submit', function() {
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation:spin .7s linear infinite">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
        Verifying…`;
    }
  });

  /* ── 10-minute countdown ──────────────────────────────────── */
  let remaining = 10 * 60; // seconds
  const countdownEl = document.getElementById('otp-countdown');

  function tick() {
    if (!countdownEl) return;
    const m = Math.floor(remaining / 60).toString().padStart(2, '0');
    const s = (remaining % 60).toString().padStart(2, '0');
    countdownEl.textContent = m + ':' + s;

    if (remaining <= 60) countdownEl.style.color = 'var(--error)';

    if (remaining <= 0) {
      countdownEl.textContent = 'Expired';
      if (submitBtn) submitBtn.disabled = true;
      digits.forEach(d => { d.disabled = true; d.classList.add('has-error'); });
      return;
    }
    remaining--;
    setTimeout(tick, 1000);
  }
  tick();

  /* ── Resend cooldown UI ───────────────────────────────────── */
  const resendBtn = document.getElementById('resend-btn');
  if (resendBtn) {
    let cooldown = <?= CP_OTP_COOLDOWN ?>;
    let interval = null;

    function startCooldown(secs) {
      cooldown = secs;
      resendBtn.disabled = true;
      interval = setInterval(() => {
        cooldown--;
        resendBtn.textContent = `Resend code (${cooldown}s)`;
        if (cooldown <= 0) {
          clearInterval(interval);
          resendBtn.disabled = false;
          resendBtn.textContent = 'Resend code';
        }
      }, 1000);
    }

    /* If the page just loaded after a resend or initial send, start cooldown */
    <?php
    $wait_left = $needs_otp ? cp_otp_cooldown_wait((int)$user['id']) : 0;
    ?>
    const waitLeft = <?= $wait_left ?>;
    if (waitLeft > 0) startCooldown(waitLeft);

    document.getElementById('resend-form')?.addEventListener('submit', function() {
      startCooldown(<?= CP_OTP_COOLDOWN ?>);
    });
  }
})();
</script>

<style>
  @keyframes spin { to { transform: rotate(360deg); } }
</style>

</body>
</html>