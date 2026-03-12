<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Sign In</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <link rel="stylesheet" href="assets/css/signin.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
</head>
<body class="auth-body">

<div class="auth-layout">

  <div class="auth-left" aria-hidden="true">
    <div class="al-shapes">
      <div class="als als-1"></div>
      <div class="als als-2"></div>
      <div class="als als-3"></div>
    </div>
    <div class="al-content">
      <div class="al-brand">
        <img src="assets/img/logo.svg" alt="DepEd Logo" class="al-logo"/>
        <div>
          <p class="al-system">Department of Education</p>
          <p class="al-name">Learning Resource Management<br/>&amp; Development System</p>
        </div>
      </div>
      <blockquote class="al-quote">
        "Quality learning resources for every Filipino learner, in every corner of the Philippines."
      </blockquote>
      <div class="al-stats">
        <div class="als-stat">
          <strong>50,000+</strong>
          <span>Resources</span>
        </div>
        <div class="als-stat">
          <strong>K – 12</strong>
          <span>Grade Levels</span>
        </div>
        <div class="als-stat">
          <strong>17</strong>
          <span>Regions</span>
        </div>
      </div>
    </div>
    <p class="al-footer">© 2026 DepEd LRMDS · Prototype</p>
  </div>

  <div class="auth-right">
    <div class="ar-inner">

      <div class="ar-mobile-brand">
        <img src="assets/img/logo.svg" alt="" width="32"/>
        <span>LRMDS</span>
      </div>

      <div class="ar-header">
        <h1>Welcome back</h1>
        <p>Sign in to your LRMDS account to access learning resources.</p>
      </div>

      <div class="demo-pill" role="note">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
        Prototype — any credentials will work
      </div>

      <form id="signin-form" novalidate autocomplete="on">

        <div class="af-group" id="fg-email">
          <label class="af-label" for="email">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 7 10-7"/></svg>
            Email or Employee ID
          </label>
          <input class="af-input" type="text" id="email" name="email"
            placeholder="yourname@deped.gov.ph"
            autocomplete="username" aria-describedby="email-err" required/>
          <span class="af-error" id="email-err" role="alert"></span>
        </div>

        <div class="af-group" id="fg-password">
          <label class="af-label" for="password">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Password
          </label>
          <div class="af-pw-wrap">
            <input class="af-input" type="password" id="password" name="password"
              placeholder="Enter your password"
              autocomplete="current-password" aria-describedby="pw-err" required/>
            <button type="button" class="af-pw-toggle" id="pw-toggle" aria-label="Toggle password visibility">
              <svg class="icon-eye" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
              </svg>
              <svg class="icon-eye-off" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="display:none">
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-10-8-10-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
              </svg>
            </button>
          </div>
          <span class="af-error" id="pw-err" role="alert"></span>
        </div>

        <div class="af-inline-row">
          <label class="af-check-label">
            <input type="checkbox" id="remember" name="remember"/>
            <span class="af-checkmark"></span>
            Keep me signed in
          </label>
          <a class="af-link" href="#">Forgot password?</a>
        </div>

        <button type="submit" class="af-btn af-btn-primary" id="signin-btn">
          <span class="btn-label">Sign In</span>
          <svg class="btn-arrow" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
          <svg class="btn-spin" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        </button>

        <div class="af-divider"><span>or sign in with</span></div>

        <div class="af-sso-row">
          <button type="button" class="af-sso-btn" id="btn-deped-sso">
            <img src="assets/img/logo.svg" alt="" width="20" height="20"/>
            DepEd SSO
          </button>
          <button type="button" class="af-sso-btn" id="btn-google">
            <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
              <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09Z"/>
              <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23Z"/>
              <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62Z"/>
              <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53Z"/>
            </svg>
            Google Workspace
          </button>
        </div>

      </form>

      <p class="ar-switch">Don't have an account? <a class="af-link" href="register.php">Register here</a></p>
      <p class="ar-help">Need help? <a class="af-link" href="#">Contact LRMDS Helpdesk</a></p>

    </div>
  </div>

</div>

<script src="assets/js/signin.js"></script>
</body>
</html>