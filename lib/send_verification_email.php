<?php
/**
 * DepEd LRMDS – lib/send_verification_email.php
 *
 * Reusable email verification helper.
 * Called by register_handler.php (first send) and resend_verification.php.
 *
 * Usage:
 *   require_once __DIR__ . '/lib/send_verification_email.php';
 *   [$ok, $error] = send_verification_email($pdo, $user_id, $email, $first_name);
 *
 * Config comes from .env — never hardcoded here.
 * Requires PHPMailer via Composer (vendor/autoload.php).
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

require_once __DIR__ . '/env.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Generate a token, store it, and send the verification email.
 *
 * @return array{0: bool, 1: string|null}
 */
function send_verification_email(PDO $pdo, int $user_id, string $email, string $first_name): array
{
    // 1. Invalidate any previous unused tokens for this user
    $pdo->prepare('
        DELETE FROM email_verifications
        WHERE user_id = ? AND used_at IS NULL
    ')->execute([$user_id]);

    // 2. Generate a cryptographically secure 64-char hex token
    $token      = bin2hex(random_bytes(32));
    $ttl_hours  = (int) env('VERIFY_TOKEN_TTL_HOURS', '24');
    $expires_at = date('Y-m-d H:i:s', strtotime("+{$ttl_hours} hours"));

    // 3. Store token in DB
    try {
        $pdo->prepare('
            INSERT INTO email_verifications (user_id, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ')->execute([$user_id, $token, $expires_at]);
    } catch (PDOException $e) {
        error_log('LRMDS email_verifications insert: ' . $e->getMessage());
        return [false, 'Could not create verification token.'];
    }

    // 4. Build verification URL
    $base_url   = rtrim(env('APP_BASE_URL', 'http://localhost/lrmds'), '/');
    $verify_url = $base_url . '/registration/verify.php?token=' . urlencode($token);

    // 5. Build email content
    $safe_name = htmlspecialchars($first_name, ENT_QUOTES, 'UTF-8');
    $safe_url  = htmlspecialchars($verify_url, ENT_QUOTES, 'UTF-8');
    $ttl_label = $ttl_hours . ' hour' . ($ttl_hours !== 1 ? 's' : '');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
</head>
<body style="margin:0;padding:0;background:#F0F4FA;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4FA;padding:40px 0;">
    <tr><td align="center">
      <table width="540" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr>
          <td style="background:linear-gradient(135deg,#0B3D91,#1565C0);padding:32px 40px;text-align:center;">
            <h1 style="margin:0;color:#fff;font-size:22px;font-weight:800;">DepEd LRMDS</h1>
            <p style="margin:6px 0 0;color:#BFDBFE;font-size:13px;">Learning Resource Management &amp; Development System</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px 28px;">
            <p style="margin:0 0 16px;font-size:15px;color:#374151;">Hi <strong>{$safe_name}</strong>,</p>
            <p style="margin:0 0 16px;font-size:14px;color:#6B7280;line-height:1.65;">
              Thank you for creating an account on <strong>DepEd LRMDS</strong>.
              To complete your registration and activate your account, please verify
              your email address by clicking the button below.
            </p>
            <table cellpadding="0" cellspacing="0" style="margin:28px auto;">
              <tr>
                <td align="center" style="background:#0B4F9C;border-radius:10px;">
                  <a href="{$safe_url}"
                     style="display:inline-block;padding:14px 32px;color:#fff;
                            font-size:15px;font-weight:700;text-decoration:none;">
                    Verify My Email Address
                  </a>
                </td>
              </tr>
            </table>
            <p style="margin:0 0 8px;font-size:13px;color:#9CA3AF;text-align:center;">
              This link expires in <strong style="color:#374151;">{$ttl_label}</strong>.
            </p>
            <p style="margin:0;font-size:12px;color:#9CA3AF;text-align:center;word-break:break-all;">
              Or copy this link into your browser:<br/>
              <a href="{$safe_url}" style="color:#0B4F9C;">{$safe_url}</a>
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:0 40px;">
            <hr style="border:none;border-top:1px solid #F3F4F6;margin:0;"/>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 40px 28px;">
            <p style="margin:0;font-size:12px;color:#9CA3AF;line-height:1.6;">
              If you did not create an account on DepEd LRMDS, you can safely ignore this email.
              Do not share this link with anyone.<br/><br/>
              &copy; 2026 Department of Education || LRMDS
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $plain = "Hi {$first_name},\n\n"
           . "Please verify your email address to activate your DepEd LRMDS account.\n\n"
           . "Verification link (expires in {$ttl_label}):\n{$verify_url}\n\n"
           . "If you did not register, you can safely ignore this email.\n\n"
           . "— DepEd LRMDS";

    // 6. Send via PHPMailer
    try {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = env('MAIL_HOST',     'sandbox.smtp.mailtrap.io');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('MAIL_USERNAME', '');
        $mail->Password   = env('MAIL_PASSWORD', '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) env('MAIL_PORT', '2525');

        $mail->setFrom(
            env('MAIL_FROM_ADDR', 'noreply@lrmds.deped.gov.ph'),
            env('MAIL_FROM_NAME', 'DepEd LRMDS')
        );
        $mail->addAddress($email, $first_name);
        $mail->addReplyTo('support@lrmds.deped.gov.ph', 'DepEd LRMDS Support');

        $mail->isHTML(true);
        $mail->Subject = 'Verify your DepEd LRMDS email address';
        $mail->Body    = $html;
        $mail->AltBody = $plain;

        $mail->send();
        return [true, null];

    } catch (MailerException $e) {
        error_log('LRMDS mailer: ' . $e->getMessage());
        return [false, 'Could not send verification email. Please try again later.'];
    }
}