<?php
/**
 * api/onedrive-helper.php
 * DepEd LRMDS – Microsoft Graph / OneDrive helper
 *
 * .env keys:
 *   AZURE_TENANT_ID       – Directory (tenant) ID from Azure portal
 *   AZURE_CLIENT_ID       – Application (client) ID from Azure portal
 *   AZURE_CLIENT_SECRET   – Secret Value (not the Secret ID)
 *   ONEDRIVE_FOLDER_PATH  – Base OneDrive path, e.g. Documents/deped
 *
 * Folder structure created automatically:
 *
 *   Documents/deped/
 *   ├── News/
 *   │   ├── Announcements/2026/
 *   │   ├── Memorandums/2026/
  *   │   ├── Program-Updates/2026/
 *   │   └── Events/2026/
 *   └── Resources/
 *       ├── Mathematics/Grade-6/Q1/
 *       ├── Science/Grade-8/Q2/
 *       └── English/Grade-10/Q1/
 */

/* ── Load .env ───────────────────────────────────────────────────────── */
if (!getenv('AZURE_CLIENT_ID')) {
    $envFile = __DIR__ . '/../.env';
    if (file_exists($envFile)) {
        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            if (str_starts_with(trim($line), '#')) continue;
            [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
            putenv(trim($key) . '=' . trim($val));
        }
    }
}

/* ════════════════════════════════════════════════════════════════════════
   PATH BUILDERS
   ════════════════════════════════════════════════════════════════════════ */

/**
 * Build the OneDrive subfolder path for a NEWS submission.
 *
 * Structure: News/{Type}/{Year}/
 *   Types: Announcements | Memorandums | Program-Updates | Events
 *
 * @param string $type     news type: announcement | memo | program | event
 * @param string $date     submission date string (e.g. "2026-05-04")
 */
function buildNewsPath(string $type, string $date = ''): string
{
    $typeMap = [
        'announcement' => 'Announcements',
        'memo'         => 'Memorandums',
        'program'      => 'Program-Updates',
        'event'        => 'Events',
    ];

    $folder = $typeMap[$type] ?? 'Announcements';
    $year   = $date ? date('Y', strtotime($date)) : date('Y');

    return "News/{$folder}/{$year}";
}

/**
 * Build the OneDrive subfolder path for a RESOURCE submission.
 *
 * Structure: Resources/{Subject}/{Grade}/{Quarter}/
 *   Slugifies each segment to be filesystem-safe.
 *
 * @param string $subject  e.g. "Mathematics", "Araling Panlipunan"
 * @param string $grade    e.g. "6", "Kinder", "11"
 * @param string $quarter  e.g. "Q1", "Q2", "" (empty → All-Quarters)
 */
function buildResourcePath(string $subject, string $grade, string $quarter = ''): string
{
    $slug = fn(string $s): string =>
        preg_replace('/[^a-zA-Z0-9\-]/', '-', trim($s));

    $subjectSlug  = $slug($subject)  ?: 'Uncategorized';
    $gradeSlug    = 'Grade-' . ($slug($grade) ?: 'Unknown');
    $quarterSlug  = $quarter ? $slug($quarter) : 'All-Quarters';

    return "Resources/{$subjectSlug}/{$gradeSlug}/{$quarterSlug}";
}

/* ════════════════════════════════════════════════════════════════════════
   AUTHENTICATION
   ════════════════════════════════════════════════════════════════════════ */

/**
 * Returns a Microsoft Graph access token (client-credentials flow).
 */
function getOneDriveToken(): string
{
    $tenantId     = getenv('AZURE_TENANT_ID')     ?: '';
    $clientId     = getenv('AZURE_CLIENT_ID')     ?: '';
    $clientSecret = getenv('AZURE_CLIENT_SECRET') ?: '';

    if (!$tenantId || $tenantId === 'common') {
        throw new RuntimeException(
            'AZURE_TENANT_ID must be your specific Directory (tenant) ID, not "common". ' .
            'Find it on Azure Portal → App registrations → DepEd LRMDS Portal → Overview.'
        );
    }
    if (!$clientId || !$clientSecret) {
        throw new RuntimeException('AZURE_CLIENT_ID or AZURE_CLIENT_SECRET not configured in .env');
    }

    $url  = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
    $body = http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $clientId,
        'client_secret' => $clientSecret,
        'scope'         => 'https://graph.microsoft.com/.default',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new RuntimeException("Token request failed (HTTP {$httpCode}): {$response}");
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('No access_token in response: ' . $response);
    }

    return $data['access_token'];
}

/* ════════════════════════════════════════════════════════════════════════
   UPLOAD
   ════════════════════════════════════════════════════════════════════════ */

/**
 * Uploads a file to OneDrive.
 *
 * Full remote path = ONEDRIVE_FOLDER_PATH / $subfolder / $remoteFileName
 * e.g. Documents/deped/News/Memorandums/2026/20260504_DM-012.pdf
 *
 * ≤ 4 MB  → simple PUT
 * > 4 MB  → resumable chunked upload session
 *
 * @param string $localPath      Path to the temp file on the server
 * @param string $subfolder      Path segment from buildNewsPath() or buildResourcePath()
 * @param string $remoteFileName Safe filename to store on OneDrive
 * @return string  OneDrive item ID of the uploaded file
 */
function uploadToOneDrive(string $localPath, string $subfolder, string $remoteFileName): string
{
    $token     = getOneDriveToken();
    $fileSize  = filesize($localPath);
    $threshold = 4 * 1024 * 1024; // 4 MB

    $base        = rtrim(getenv('ONEDRIVE_FOLDER_PATH') ?: 'Documents/deped', '/');
    $remotePath  = $base . '/' . trim($subfolder, '/') . '/' . $remoteFileName;
    $encodedPath = implode('/', array_map('rawurlencode', explode('/', $remotePath)));
    $driveUser   = getenv('ONEDRIVE_USER_UPN') ?: '';   // e.g. admin@yourschool.onmicrosoft.com
    if (!$driveUser) {
        throw new RuntimeException('ONEDRIVE_USER_UPN not set in .env — required for app-only auth.');
    }
    $baseUrl     = "https://graph.microsoft.com/v1.0/users/{$driveUser}/drive/root:/{$encodedPath}";

    /* ── Simple upload (≤ 4 MB) ── */
    if ($fileSize <= $threshold) {
        $content = file_get_contents($localPath);
        $ch = curl_init("{$baseUrl}:/content"); 
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                "Authorization: Bearer {$token}",
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $fileSize,
            ],
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201], true)) {
            throw new RuntimeException("OneDrive upload failed (HTTP {$httpCode}): {$response}");
        }
        return json_decode($response, true)['id'] ?? '';
    }

    /* ── Resumable upload (> 4 MB) ── */
    $sessionBody = json_encode([
        'item' => [
            '@microsoft.graph.conflictBehavior' => 'replace',
            'name' => $remoteFileName,
        ],
    ]);

    $ch = curl_init("{$baseUrl}:/createUploadSession");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $sessionBody,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer {$token}",
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);
    $sessionResp = curl_exec($ch);
    $sessionCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($sessionCode !== 200) {
        throw new RuntimeException("Upload session failed (HTTP {$sessionCode}): {$sessionResp}");
    }
    $uploadUrl = json_decode($sessionResp, true)['uploadUrl'] ?? '';
    if (!$uploadUrl) throw new RuntimeException('No uploadUrl in session response.');

    $chunkSize = 5 * 1024 * 1024; // 5 MB chunks
    $handle    = fopen($localPath, 'rb');
    $offset    = 0;
    $itemId    = '';

    while (!feof($handle)) {
        $chunk    = fread($handle, $chunkSize);
        $chunkLen = strlen($chunk);
        $rangeEnd = $offset + $chunkLen - 1;

        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $chunk,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Length: ' . $chunkLen,
                "Content-Range: bytes {$offset}-{$rangeEnd}/{$fileSize}",
            ],
            CURLOPT_TIMEOUT => 120,
        ]);
        $chunkResp = curl_exec($ch);
        $chunkCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (in_array($chunkCode, [200, 201], true)) {
            $itemId = json_decode($chunkResp, true)['id'] ?? '';
        } elseif ($chunkCode !== 202) {
            fclose($handle);
            throw new RuntimeException(
                "Chunk upload failed (HTTP {$chunkCode}, bytes {$offset}-{$rangeEnd}): {$chunkResp}"
            );
        }
        $offset += $chunkLen;
    }
    fclose($handle);
    return $itemId;
}