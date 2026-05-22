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
 *   5. Authorized redirect URIs: http://localhost/deped-lrmds-portal/auth/google_callback.php
 *      (change to your actual domain in production)
 *   6. Copy the Client ID and Client Secret into the constants below
 */
require __DIR__ . '/../env_loader.php';
loadEnv(__DIR__ . '/../.env');
session_start();

// ─────────────────────────────────────────────
//  ★  FILL THESE IN FROM GOOGLE CLOUD CONSOLE  ★
// ─────────────────────────────────────────────
define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));

// Build the redirect URI from the actual request host so the same code
// works from localhost (laptop browser) AND from your LAN IP (mobile/tablet).
// Google Cloud Console: register BOTH of these as Authorized Redirect URIs:
//   http://localhost/deped-lrmds-portal/auth/google_callback.php
//   http://192.168.x.x/deped-lrmds-portal/auth/google_callback.php
//   (replace 192.168.x.x with your laptop's actual local IP)
define('GOOGLE_REDIRECT_URI', (function () {
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';   // e.g. 192.168.1.5 or localhost
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . '/deped-lrmds-portal/auth/google_callback.php';
})());
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