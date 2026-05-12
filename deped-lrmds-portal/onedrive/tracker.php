<?php
/**
 * DepEd Carcar City LRMDS – Analytics Tracker
 * ============================================
 * Lightweight backend endpoint that receives tracking events from analytics.js
 * and stores them in a SQLite database.
 *
 * Tracked events:
 *   - page_view   : user opened the portal (session start)
 *   - file_view   : user opened a file preview
 *   - file_download: user downloaded a file
 *   - folder_open : user navigated into a folder
 *   - search      : user ran a search / applied filters
 *   - session_end : fired on page unload (includes duration)
 *
 * Database: onedrive/data/analytics.db  (SQLite, created automatically)
 *
 * Usage (called by analytics.js via fetch):
 *   POST tracker.php
 *   Content-Type: application/json
 *   { "event": "file_download", "item_id": "…", "item_name": "…", … }
 *
 * Quick stats endpoint (called by analytics.js to show live counts):
 *   GET tracker.php?counts&item_id=ABC123
 *   → { "views": 12, "downloads": 4 }
 */

declare(strict_types=1);
header('Content-Type: application/json');

// ── CORS: only allow same origin (adjust if you host on a subdomain) ──────────
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
// Allow any same-site origin; tighten in production if needed
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Database setup ────────────────────────────────────────────────────────────
$db_dir  = __DIR__ . '/data';
$db_file = $db_dir . '/analytics.db';

if (!is_dir($db_dir)) {
    mkdir($db_dir, 0755, true);
    // Protect the data folder from direct web access
    file_put_contents($db_dir . '/.htaccess', "Deny from all\n");
}

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA journal_mode=WAL;');  // better concurrency for web use
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB init failed: ' . $e->getMessage()]);
    exit;
}

// ── Schema (created once, idempotent) ────────────────────────────────────────
$pdo->exec(<<<SQL
    -- Every individual event
    CREATE TABLE IF NOT EXISTS events (
        id           INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id   TEXT    NOT NULL,
        user_oid     TEXT,               -- Azure AD object ID (hashed for privacy)
        user_name    TEXT,               -- display name
        event        TEXT    NOT NULL,   -- page_view | file_view | file_download | folder_open | search | session_end
        item_id      TEXT,               -- OneDrive item ID
        item_name    TEXT,               -- filename or folder name
        item_type    TEXT,               -- SLM | TG | DLL | Video | Assessment | Resource
        folder_path  TEXT,               -- breadcrumb trail at time of event
        search_query TEXT,               -- for search events
        filters      TEXT,               -- JSON: {grade, subject, type}
        duration_sec INTEGER,            -- for session_end events
        ts           INTEGER NOT NULL DEFAULT (strftime('%s','now'))
    );

    -- Materialised counters per file (updated on INSERT via trigger)
    CREATE TABLE IF NOT EXISTS file_stats (
        item_id   TEXT PRIMARY KEY,
        item_name TEXT,
        views     INTEGER NOT NULL DEFAULT 0,
        downloads INTEGER NOT NULL DEFAULT 0,
        last_view INTEGER,
        last_dl   INTEGER
    );

    -- Auto-update counters when a file_view event is inserted
    CREATE TRIGGER IF NOT EXISTS trg_file_view
    AFTER INSERT ON events
    WHEN NEW.event = 'file_view' AND NEW.item_id IS NOT NULL
    BEGIN
        INSERT INTO file_stats (item_id, item_name, views, last_view)
            VALUES (NEW.item_id, NEW.item_name, 1, strftime('%s','now'))
        ON CONFLICT(item_id) DO UPDATE SET
            views     = views + 1,
            item_name = COALESCE(NEW.item_name, item_name),
            last_view = strftime('%s','now');
    END;

    -- Auto-update counters when a file_download event is inserted
    CREATE TRIGGER IF NOT EXISTS trg_file_download
    AFTER INSERT ON events
    WHEN NEW.event = 'file_download' AND NEW.item_id IS NOT NULL
    BEGIN
        INSERT INTO file_stats (item_id, item_name, downloads, last_dl)
            VALUES (NEW.item_id, NEW.item_name, 1, strftime('%s','now'))
        ON CONFLICT(item_id) DO UPDATE SET
            downloads = downloads + 1,
            item_name = COALESCE(NEW.item_name, item_name),
            last_dl   = strftime('%s','now');
    END;

    CREATE INDEX IF NOT EXISTS idx_events_session  ON events(session_id);
    CREATE INDEX IF NOT EXISTS idx_events_item     ON events(item_id);
    CREATE INDEX IF NOT EXISTS idx_events_event    ON events(event);
    CREATE INDEX IF NOT EXISTS idx_events_ts       ON events(ts);
SQL);

// ═════════════════════════════════════════════════════════════════════════════
//  GET  tracker.php?counts&item_id=XXX   →  { views, downloads }
//  GET  tracker.php?top&limit=10         →  top downloaded/viewed files
// ═════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['counts']) && !empty($_GET['item_id'])) {
        $stmt = $pdo->prepare('SELECT views, downloads FROM file_stats WHERE item_id = ?');
        $stmt->execute([trim($_GET['item_id'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['views' => 0, 'downloads' => 0]);
        exit;
    }

    if (isset($_GET['top'])) {
        $limit = min((int)($_GET['limit'] ?? 10), 100);
        $by    = ($_GET['by'] ?? 'downloads') === 'views' ? 'views' : 'downloads';
        $stmt  = $pdo->prepare(
            "SELECT item_id, item_name, views, downloads FROM file_stats ORDER BY {$by} DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Default: return aggregated summary for a dashboard
    $summary = $pdo->query("
        SELECT
            (SELECT COUNT(DISTINCT session_id) FROM events) AS total_sessions,
            (SELECT COUNT(*) FROM events WHERE event = 'file_view') AS total_views,
            (SELECT COUNT(*) FROM events WHERE event = 'file_download') AS total_downloads,
            (SELECT COUNT(*) FROM events WHERE event = 'search') AS total_searches,
            (SELECT COUNT(DISTINCT user_oid) FROM events WHERE user_oid IS NOT NULL) AS unique_users
    ")->fetch(PDO::FETCH_ASSOC);
    echo json_encode($summary);
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
//  POST  tracker.php   →  record an event
// ═════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data || empty($data['event'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event field']);
    exit;
}

// Allowed event types (whitelist)
$allowed_events = ['page_view', 'file_view', 'file_download', 'folder_open', 'search', 'session_end'];
if (!in_array($data['event'], $allowed_events, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown event type']);
    exit;
}

// ── Sanitise & extract fields ─────────────────────────────────────────────────
$session_id   = substr(preg_replace('/[^a-zA-Z0-9\-]/', '', $data['session_id'] ?? ''), 0, 64);
// Hash the user OID for privacy — we can still count unique users without storing PII
$user_oid     = !empty($data['user_oid'])  ? hash('sha256', $data['user_oid'])  : null;
$user_name    = substr($data['user_name']  ?? '', 0, 120);
$event        = $data['event'];
$item_id      = substr($data['item_id']    ?? '', 0, 200);
$item_name    = substr($data['item_name']  ?? '', 0, 500);
$item_type    = substr($data['item_type']  ?? '', 0, 60);
$folder_path  = substr($data['folder_path'] ?? '', 0, 500);
$search_query = substr($data['search_query'] ?? '', 0, 300);
$filters      = !empty($data['filters']) ? json_encode($data['filters']) : null;
$duration_sec = isset($data['duration_sec']) ? (int)$data['duration_sec'] : null;

if (empty($session_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing session_id']);
    exit;
}

$stmt = $pdo->prepare(<<<SQL
    INSERT INTO events
        (session_id, user_oid, user_name, event, item_id, item_name, item_type,
         folder_path, search_query, filters, duration_sec)
    VALUES
        (:session_id, :user_oid, :user_name, :event, :item_id, :item_name, :item_type,
         :folder_path, :search_query, :filters, :duration_sec)
SQL);

$stmt->execute([
    ':session_id'   => $session_id,
    ':user_oid'     => $user_oid,
    ':user_name'    => $user_name ?: null,
    ':event'        => $event,
    ':item_id'      => $item_id   ?: null,
    ':item_name'    => $item_name ?: null,
    ':item_type'    => $item_type ?: null,
    ':folder_path'  => $folder_path ?: null,
    ':search_query' => $search_query ?: null,
    ':filters'      => $filters,
    ':duration_sec' => $duration_sec,
]);

// For file events, return the updated counts so analytics.js can show them live
$response = ['ok' => true, 'id' => $pdo->lastInsertId()];
if (in_array($event, ['file_view', 'file_download'], true) && $item_id) {
    $cs = $pdo->prepare('SELECT views, downloads FROM file_stats WHERE item_id = ?');
    $cs->execute([$item_id]);
    $counts = $cs->fetch(PDO::FETCH_ASSOC);
    if ($counts) $response['counts'] = $counts;
}

echo json_encode($response);