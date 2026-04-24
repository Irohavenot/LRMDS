<?php
/**
 * lib/send_password_otp.php
 *
 * Generates a 6-digit OTP for the "change password" confirmation flow,
 * persists a hashed copy to `password_otps`, and emails the code via
 * PHPMailer + Mailtrap (same transport used by send_verification_email.php).
 *
 * Usage:
 *   require_once __DIR__ . '/lib/send_password_otp.php';
 *   [$ok, $token, $err] = send_password_otp($pdo, $user_id, $email, $first_name);
 *
 * Returns:
 *   [true,  $lookup_token, '']           on success
 *   [false, '',            $error_msg]   on failure
 *
 * $lookup_token is a random hex string stored in the session so the
 * verify step can look up the right OTP row without exposing user_id
 * directly in the HTML.
 *
 * DB table required (run db/create_password_otps.sql once):
 *   password_otps (id, user_id, token_hash, otp_hash, expires_at,
 *                  used, created_at)
 */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/../vendor/autoload.php';

defined('OTP_EXPIRY_SECONDS') || define('OTP_EXPIRY_SECONDS', 600); // 10 minutes

function send_password_otp(PDO $pdo, int $user_id, string $email, string $first_name): array
{
    /* ── 1. Generate codes ─────────────────────────────────────── */
    $otp         = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $lookup_token = bin2hex(random_bytes(24));   // stored in session, used to find the row

    $otp_hash   = password_hash($otp,          PASSWORD_BCRYPT);
    $token_hash = hash('sha256', $lookup_token);

    /* ── 2. Invalidate any prior unused OTPs for this user ─────── */
    $pdo->prepare('
        UPDATE password_otps SET used = 1 WHERE user_id = ? AND used = 0
    ')->execute([$user_id]);

    /* ── 3. Persist new OTP ────────────────────────────────────── */
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_SECONDS);
    $pdo->prepare('
        INSERT INTO password_otps (user_id, token_hash, otp_hash, expires_at, used, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ')->execute([$user_id, $token_hash, $otp_hash, $expires]);

    /* ── 4. Send email via PHPMailer (Mailtrap sandbox) ────────── */
    try {
        require_once __DIR__ . '/../vendor/autoload.php'; // adjust path if needed

        $mail = new PHPMailer(true);

$mail->isSMTP();
$mail->Host       = env('MAIL_HOST',     'sandbox.smtp.mailtrap.io');
$mail->SMTPAuth   = true;
$mail->Username   = env('MAIL_USERNAME', '');
$mail->Password   = env('MAIL_PASSWORD', '');
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = (int) env('MAIL_PORT', '2525');

$mail->setFrom(
    env('MAIL_FROM_ADDR', 'noreply@lrmds.deped.gov.ph'),  // ← ADDR not ADDRESS
    env('MAIL_FROM_NAME', 'DepEd LRMDS')
);
        $mail->addAddress($email, $first_name);

        $mail->isHTML(true);
        $mail->Subject = 'Your Password Change Code — DepEd LRMDS';
        $mail->Body    = _otp_email_html($first_name, $otp);
        $mail->AltBody = _otp_email_text($first_name, $otp);

        $mail->send();

    } catch (Throwable $e) {
        // Roll back the DB row so a retry can insert a fresh one
        $pdo->prepare('DELETE FROM password_otps WHERE token_hash = ?')->execute([$token_hash]);
        return [false, '', $e->getMessage()];
    }

    return [true, $lookup_token, ''];
}

/* ── Verify an OTP submitted by the user ──────────────────────────────────
 *
 * Usage:
 *   [$ok, $user_id, $err] = verify_password_otp($pdo, $lookup_token, $submitted_otp);
 *
 * Marks the row as used on success so it cannot be reused.
 */
function verify_password_otp(PDO $pdo, string $lookup_token, string $submitted_otp): array
{
    if ($lookup_token === '' || $submitted_otp === '') {
        return [false, 0, 'Missing token or code.'];
    }

    $token_hash = hash('sha256', $lookup_token);

    $stmt = $pdo->prepare('
        SELECT id, user_id, otp_hash, expires_at, used
        FROM   password_otps
        WHERE  token_hash = ?
        LIMIT  1
    ');
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [false, 0, 'Invalid or expired verification session.'];
    }
    if ($row['used']) {
        return [false, 0, 'This code has already been used. Please request a new one.'];
    }
    if (strtotime($row['expires_at']) < time()) {
        return [false, 0, 'This code has expired. Please request a new one.'];
    }
    if (!password_verify($submitted_otp, $row['otp_hash'])) {
        return [false, 0, 'Incorrect code. Please try again.'];
    }

    // Mark used
    $pdo->prepare('UPDATE password_otps SET used = 1 WHERE id = ?')->execute([$row['id']]);

    return [true, (int) $row['user_id'], ''];
}

/* ── Email templates ────────────────────────────────────────────────────── */

function _otp_email_html(string $name, string $otp): string
{
    $otp_safe  = htmlspecialchars($otp);
    $name_safe = htmlspecialchars($name);
    $expires   = OTP_EXPIRY_SECONDS / 60;

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Password Change Code</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#F8FAFC;padding:40px 16px;">
    <tr><td align="center">
      <table width="100%" style="max-width:520px;background:#fff;border-radius:16px;
             box-shadow:0 4px 24px rgba(0,0,0,.08);overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#0B3D91 0%,#1565C0 60%,#1976D2 100%);
                     padding:28px 36px;text-align:center;">
            <p style="margin:0;font-size:13px;font-weight:600;letter-spacing:.08em;
                      text-transform:uppercase;color:rgba(255,255,255,.75);">DepEd LRMDS</p>
            <h1 style="margin:6px 0 0;font-size:22px;font-weight:800;color:#fff;">
              Password Change Request
            </h1>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:36px 36px 28px;">
            <p style="margin:0 0 20px;font-size:15px;color:#374151;line-height:1.6;">
              Hi <strong>{$name_safe}</strong>,
            </p>
            <p style="margin:0 0 28px;font-size:14px;color:#6B7280;line-height:1.65;">
              We received a request to change the password on your LRMDS account.
              Use the code below to confirm this action. Do <strong>not</strong> share
              this code with anyone.
            </p>

            <!-- OTP box -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
              <tr><td align="center" style="padding:0 0 28px;">
                <div style="display:inline-block;background:#EFF6FF;border:2px solid #BFDBFE;
                            border-radius:14px;padding:22px 36px;text-align:center;">
                  <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.1em;
                            text-transform:uppercase;color:#1D4ED8;">Your verification code</p>
                  <p style="margin:0;font-size:42px;font-weight:800;letter-spacing:.22em;
                            color:#0B3D91;font-family:'Courier New',monospace;">{$otp_safe}</p>
                  <p style="margin:8px 0 0;font-size:12px;color:#6B7280;">
                    Expires in <strong>{$expires} minutes</strong>
                  </p>
                </div>
              </td></tr>
            </table>

            <p style="margin:0 0 12px;font-size:13.5px;color:#374151;line-height:1.6;">
              Enter this code on the password change page to continue.
            </p>

            <!-- Warning box -->
            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px;">
              <tr>
                <td style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;">
                  <p style="margin:0;font-size:13px;color:#92400E;line-height:1.55;">
                    ⚠ <strong>Didn't request this?</strong> Your password has <em>not</em> been changed.
                    You can safely ignore this email. If you're concerned, contact your administrator.
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 36px 28px;border-top:1px solid #F3F4F6;text-align:center;">
            <p style="margin:0;font-size:11.5px;color:#9CA3AF;line-height:1.7;">
              This is an automated message from the DepEd Learning Resource Management and Delivery System.<br/>
              Please do not reply to this email.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}

function _otp_email_text(string $name, string $otp): string
{
    $expires = OTP_EXPIRY_SECONDS / 60;
    return <<<TEXT
DepEd LRMDS — Password Change Verification
==========================================

Hi {$name},

We received a request to change the password on your LRMDS account.

Your verification code: {$otp}

This code expires in {$expires} minutes. Enter it on the password change
page to confirm your request.

If you did NOT request a password change, your password has not been
changed. You can safely ignore this email.

— DepEd LRMDS Team
TEXT;
}   