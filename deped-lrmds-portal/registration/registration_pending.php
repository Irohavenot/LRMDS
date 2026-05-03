<?php
/**
 * DepEd LRMDS – registration_pending.php
 *
 * Shown to learner / parent after successful registration.
 * register_handler.php redirects here with JS (see register.js).
 * The user just needs to check their email and click the link.
 */

session_start();

// If someone navigates here directly without a session hint, still show the page
// (it's a public informational page — no sensitive data is on it).
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Check Your Email</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/register.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <style>
    .rp-wrap {
      min-height: 100vh; display: flex; align-items: center; justify-content: center;
      padding: 40px 20px; background: #F8FAFC;
      font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
    }
    .rp-card {
      background: #fff; border-radius: 20px;
      box-shadow: 0 8px 40px rgba(0,0,0,.08);
      padding: 52px 48px; max-width: 480px; width: 100%;
      text-align: center;
    }
    .rp-envelope {
      width: 80px; height: 80px; border-radius: 50%; background: #EFF6FF;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 24px;
      animation: popIn .4s cubic-bezier(0.34,1.56,.64,1);
    }
    @keyframes popIn {
      from { transform: scale(0); opacity: 0; }
      to   { transform: scale(1); opacity: 1; }
    }
    .rp-steps {
      text-align: left; margin: 28px 0;
      display: flex; flex-direction: column; gap: 14px;
    }
    .rp-step {
      display: flex; gap: 14px; align-items: flex-start;
      font-size: 14px; color: #374151; line-height: 1.5;
    }
    .rp-step-num {
      width: 26px; height: 26px; background: #0B4F9C; color: #fff;
      border-radius: 50%; display: flex; align-items: center; justify-content: center;
      font-size: 12px; font-weight: 700; flex-shrink: 0; margin-top: 1px;
    }
    .rp-tip {
      background: #FFFBEB; border: 1px solid #FDE68A; border-radius: 10px;
      padding: 12px 16px; font-size: 13px; color: #92400E;
      text-align: left; margin-bottom: 24px; line-height: 1.55;
    }
    .rp-tip strong { color: #78350F; }
  </style>
</head>
<body class="reg-body" style="background:#F8FAFC;">
<div class="rp-wrap">
  <div class="rp-card">

    <div class="rp-envelope">
      <svg width="40" height="40" fill="none" stroke="#0B4F9C" stroke-width="1.6" viewBox="0 0 24 24">
        <rect x="2" y="4" width="20" height="16" rx="2"/>
        <path d="m2 7 10 7 10-7"/>
      </svg>
    </div>

    <h1 style="font-size:24px;font-weight:800;color:#111827;margin:0 0 10px;">
      Check Your Email
    </h1>
    <p style="font-size:14px;color:#6B7280;margin:0;line-height:1.65;">
      We've sent a verification link to your email address.
      Follow the steps below to activate your account.
    </p>

    <div class="rp-steps">
      <div class="rp-step">
        <span class="rp-step-num">1</span>
        <span>Open your email inbox and look for a message from <strong>DepEd LRMDS</strong>.</span>
      </div>
      <div class="rp-step">
        <span class="rp-step-num">2</span>
        <span>Click the <strong>"Verify My Email Address"</strong> button inside the email.</span>
      </div>
      <div class="rp-step">
        <span class="rp-step-num">3</span>
        <span>You'll be taken to a confirmation page — then you can sign in and start using LRMDS.</span>
      </div>
    </div>

    <div class="rp-tip">
      <strong>⚠ Don't see the email?</strong> Check your <strong>spam or junk folder</strong>.
      The email may take a minute or two to arrive. The verification link is valid for <strong>24 hours</strong>.
    </div>

    <a href="resend_verification.php"
       class="rf-btn rf-btn-ghost"
       style="display:inline-flex;text-decoration:none;width:100%;justify-content:center;margin-bottom:12px;">
      <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
        <path d="M21 12a9 9 0 0 1-9 9m9-9a9 9 0 0 0-9-9m9 9H3m9 9a9 9 0 0 1-9-9m9 9c1.66 0 3-4.03 3-9s-1.34-9-3-9m0 18c-1.66 0-3-4.03-3-9s1.34-9 3-9"/>
      </svg>
      Resend Verification Email
    </a>

    <p style="font-size:12px;color:#9CA3AF;margin:16px 0 0;line-height:1.6;">
      Wrong email address?
      <a href="register.php" style="color:#0B4F9C;font-weight:600;text-decoration:none;">Register again</a>
      · Already verified?
      <a href="../auth/signin.php" style="color:#0B4F9C;font-weight:600;text-decoration:none;">Sign in</a>
    </p>

  </div>
</div>
</body>
</html>