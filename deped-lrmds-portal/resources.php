<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>DepEd LRMDS – Resources</title>
  <link rel="stylesheet" href="assets/css/styles.css"/>
  <style>
    .submit-hero {
      background: linear-gradient(135deg, #0B4F9C 0%, #1a73e8 100%);
      padding: 48px 0 36px;
    }
    .submit-eyebrow {
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: .1em;
      text-transform: uppercase;
      color: rgba(255,255,255,.65);
      margin: 0 0 8px;
    }
    .submit-title {
      font-size: clamp(1.5rem, 3vw, 2.2rem);
      font-weight: 700;
      margin: 0 0 8px;
      color: #fff;
    }
    .submit-sub {
      font-size: .95rem;
      color: rgba(255,255,255,.75);
      margin: 0;
    }
    .gateway-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
      margin-top: 32px;
      max-width: 720px;
    }
    .gateway-card {
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      position: relative;
      background: #fff;
      border: 1.5px solid var(--border);
      border-radius: 12px;
      padding: 28px 24px 24px;
      cursor: pointer;
      text-decoration: none;
      color: var(--text);
      transition: border-color .2s, box-shadow .2s, transform .15s;
    }
    .gateway-card:hover {
      border-color: var(--primary);
      box-shadow: 0 8px 32px rgba(11,79,156,.13);
      transform: translateY(-2px);
    }
    .gc-icon {
      width: 52px;
      height: 52px;
      border-radius: 12px;
      background: #EEF5FF;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
      flex-shrink: 0;
    }
    .gc-body h2 {
      font-size: 1.1rem;
      font-weight: 700;
      margin: 0 0 6px;
      color: var(--text);
    }
    .gc-body p {
      font-size: .85rem;
      color: var(--muted);
      margin: 0 0 14px;
      line-height: 1.5;
    }
    .gc-examples {
      list-style: none;
      padding: 0;
      margin: 0;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }
    .gc-examples li { font-size: .8rem; color: #4B5563; }
    .gc-arrow {
      position: absolute;
      bottom: 20px;
      right: 20px;
      font-size: 1.1rem;
      color: #9CA3AF;
      transition: color .2s, transform .2s;
    }
    .gateway-card:hover .gc-arrow {
      color: var(--primary);
      transform: translateX(4px);
    }
  </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="submit-hero">
  <div class="container">
    <p class="submit-eyebrow">Learning Resource Management &amp; Delivery System</p>
    <h1 class="submit-title">Where would you like to search?</h1>
    <p class="submit-sub">Choose a storage source to browse and download learning resources.</p>
  </div>
</div>

<section class="section container">
  <div class="gateway-grid">

    <!-- OneDrive → prototype2.php -->
    <a class="gateway-card" href="/LRMDS/deped-lrmds-portal/onedrive/index.php">
      <div class="gc-icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M10.5 13.5H17.5C19.433 13.5 21 11.933 21 10C21 8.29 19.766 6.865 18.136 6.553C17.532 4.522 15.662 3 13.4 3C11.513 3 9.871 4.077 9.038 5.648C7.317 5.965 6 7.476 6 9.25C6 11.321 7.679 13 9.75 13L10.5 13.5Z" fill="#0078D4"/>
          <path d="M3 15.5C2.172 15.5 1.5 16.172 1.5 17C1.5 17.828 2.172 18.5 3 18.5H20C20.828 18.5 21.5 17.828 21.5 17C21.5 16.172 20.828 15.5 20 15.5H3Z" fill="#0078D4" opacity="0.5"/>
        </svg>
      </div>
      <div class="gc-body">
        <h2>OneDrive</h2>
        <p>Browse resources stored on Microsoft OneDrive using your DepEd account.</p>
        <ul class="gc-examples">
          <li>🔐 Sign in with your DepEd Microsoft account</li>
          <li>📂 Browse folders and subfolders live</li>
          <li>⚡ Powered by Microsoft Graph API</li>
          <li>📥 Download files directly</li>
        </ul>
      </div>
      <span class="gc-arrow">→</span>
    </a>

    <!-- Google Drive → search.php -->
    <a class="gateway-card" href="search.php">
      <div class="gc-icon">
        <svg width="30" height="28" viewBox="0 0 87.3 78" xmlns="http://www.w3.org/2000/svg">
          <path d="M6.6 66.85l3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3L27.5 53H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/>
          <path d="M43.65 25L29.9 1.2C28.55.4 27 0 25.45 0L0 43.5h27.5z" fill="#00ac47"/>
          <path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75L87.3 57c0-1.55-.4-3.1-1.2-4.5H59.8L73.55 76.8z" fill="#ea4335"/>
          <path d="M43.65 25L57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2z" fill="#00832d"/>
          <path d="M59.8 53H27.5L13.75 76.8c1.35.8 2.9 1.2 4.45 1.2h50.9c1.55 0 3.1-.4 4.45-1.2z" fill="#2684fc"/>
          <path d="M73.4 26.5l-13.3-23c-.8-1.4-1.95-2.5-3.3-3.3L43.65 25 59.8 53h27.45c0-1.55-.4-3.1-1.2-4.5z" fill="#ffba00"/>
        </svg>
      </div>
      <div class="gc-body">
        <h2>Google Drive</h2>
        <p>Search and filter learning resources from Google Drive — no sign-in required.</p>
        <ul class="gc-examples">
          <li>🔍 Keyword &amp; MELC code search</li>
          <li>📚 Filter by grade, subject &amp; type</li>
          <li>📄 SLMs, TGs, DLLs, Videos &amp; more</li>
          <li>🆓 No sign-in required</li>
        </ul>
      </div>
      <span class="gc-arrow">→</span>
    </a>

  </div>
</section>

<?php include 'includes/footer.php'; ?>

<script src="assets/js/app.js"></script>
</body>
</html>