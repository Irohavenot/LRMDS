<?php
/**
 * DepEd LRMDS – google_oauth.php
 * Redirects the user to Google's OAuth 2.0 consent screen.
 * No library needed — just builds the URL manually.
 *
 * HOW TO SET UP (one-time, takes ~5 minutes):
 *   1. Go to https://console.cloud.google.com/
 *   2. Create a project (or pick an existing one)
 *   3. APIs & Services → Credentials → Create Credentials → OAuth client ID
 *   4. Application type: Web application
 *   5. Authorized redirect URIs: http://localhost/lrmds/google_callback.php
 *      (change to your actual domain in production)
 *   6. Copy the Client ID and Client Secret into the constants below
 */
require __DIR__ . '/env_loader.php';
loadEnv(__DIR__ . '/.env');
session_start();

// ─────────────────────────────────────────────
//  ★  FILL THESE IN FROM GOOGLE CLOUD CONSOLE  ★
// ─────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));

// Must exactly match what you registered in Google Cloud Console
define('GOOGLE_REDIRECT_URI',  'http://localhost/LRMDS/deped-lrmds-portal/google_callback.php');
// ─────────────────────────────────────────────

// CSRF protection: random state token stored in session
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = http_build_query([
    'client_id'             => GOOGLE_CLIENT_ID,
    'redirect_uri'          => GOOGLE_REDIRECT_URI,
    'response_type'         => 'code',
    'scope'                 => 'openid email profile',
    'state'                 => $state,
    'access_type'           => 'online',
    'prompt'                => 'select_account',   // always show account picker
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;