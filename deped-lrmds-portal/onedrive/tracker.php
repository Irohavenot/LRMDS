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
// Prevent PHP notices/warnings from corrupting JSON output
error_reporting(0);
ini_set('display_errors', '0');
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
        file_ext     TEXT,               -- actual file extension: pdf, docx, mp4 …
        folder_path  TEXT,               -- breadcrumb trail at time of event
        user_email   TEXT,               -- UPN / email from Microsoft account
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
        bookmarks INTEGER NOT NULL DEFAULT 0,
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

    -- Increment bookmark counter when saved to My Library
    CREATE TRIGGER IF NOT EXISTS trg_bookmark_add
    AFTER INSERT ON events
    WHEN NEW.event = 'bookmark_add' AND NEW.item_id IS NOT NULL
    BEGIN
        INSERT INTO file_stats (item_id, item_name, bookmarks)
            VALUES (NEW.item_id, NEW.item_name, 1)
        ON CONFLICT(item_id) DO UPDATE SET
            bookmarks = bookmarks + 1,
            item_name = COALESCE(NEW.item_name, item_name);
    END;

    -- Decrement bookmark counter (floor at 0) when removed from My Library
    CREATE TRIGGER IF NOT EXISTS trg_bookmark_remove
    AFTER INSERT ON events
    WHEN NEW.event = 'bookmark_remove' AND NEW.item_id IS NOT NULL
    BEGIN
        UPDATE file_stats
        SET bookmarks = MAX(0, bookmarks - 1)
        WHERE item_id = NEW.item_id;
    END;

    CREATE INDEX IF NOT EXISTS idx_events_session  ON events(session_id);
    CREATE INDEX IF NOT EXISTS idx_events_item     ON events(item_id);
    CREATE INDEX IF NOT EXISTS idx_events_event    ON events(event);
    CREATE INDEX IF NOT EXISTS idx_events_ts       ON events(ts);
SQL);

// Migrate existing databases — ADD COLUMN is idempotent via try/catch
foreach (['ALTER TABLE events ADD COLUMN file_ext TEXT', 'ALTER TABLE events ADD COLUMN user_email TEXT',
          'ALTER TABLE file_stats ADD COLUMN bookmarks INTEGER NOT NULL DEFAULT 0'] as $_sql) {
    try { $pdo->exec($_sql); } catch (Exception $_e) { /* column already exists */ }
}

// ═════════════════════════════════════════════════════════════════════════════
//  GET  tracker.php?counts&item_id=XXX   →  { views, downloads }
//  GET  tracker.php?top&limit=10         →  top downloaded/viewed files
// ═════════════════════════════════════════════════════════════════════════════

// ── Shared helper: build a WHERE clause fragment for the days filter ─────────
// Returns ['AND ts >= ?', $ts_cutoff] or ['', null] for all-time.
function days_filter(): array {
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 0;
    if ($days <= 0) return ['', null];
    $cutoff = time() - ($days * 86400);
    return ['AND ts >= ?', $cutoff];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (isset($_GET['counts']) && !empty($_GET['item_id'])) {
        $stmt = $pdo->prepare('SELECT views, downloads FROM file_stats WHERE item_id = ?');
        $stmt->execute([trim($_GET['item_id'])]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ?: ['views' => 0, 'downloads' => 0]);
        exit;
    }

    // ── Per-user download log  →  tracker.php?log&days=30&limit=200 ───────────
    // Returns one row per download event with user name, file, timestamp.
    // Used by the "Download Log" tab in the admin dashboard.
    if (isset($_GET['log'])) {
        $limit = min((int)($_GET['limit'] ?? 200), 1000);
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff, $limit] : [$limit];
        $stmt = $pdo->prepare("
            SELECT
                datetime(ts,'unixepoch','localtime') AS downloaded_at,
                ts,
                user_name,
                user_email,
                item_name,
                item_type,
                file_ext,
                folder_path,
                session_id
            FROM events
            WHERE event = 'file_download' {$df}
            ORDER BY ts DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }


    if (isset($_GET['top'])) {
        $limit    = min((int)($_GET['limit'] ?? 10), 100);
        $by       = ($_GET['by'] ?? 'downloads') === 'views' ? 'views' : 'downloads';
        $withpath = isset($_GET['withpath']);   // include folder_path + file_ext when set
        [$df, $cutoff] = days_filter();

        // Extra columns: most-recent folder_path and file_ext for this item
        $extraCols = $withpath
            ? ", (SELECT folder_path FROM events e2 WHERE e2.item_id=e.item_id AND e2.event='file_download' ORDER BY e2.ts DESC LIMIT 1) AS folder_path
               , (SELECT file_ext   FROM events e3 WHERE e3.item_id=e.item_id AND e3.event='file_download' AND e3.file_ext IS NOT NULL ORDER BY e3.ts DESC LIMIT 1) AS file_ext"
            : '';

        if ($cutoff) {
            $stmt = $pdo->prepare("
                SELECT item_id, item_name,
                       SUM(CASE WHEN event='file_view'     THEN 1 ELSE 0 END) AS views,
                       SUM(CASE WHEN event='file_download' THEN 1 ELSE 0 END) AS downloads
                       {$extraCols}
                FROM events e
                WHERE item_id IS NOT NULL {$df}
                GROUP BY item_id, item_name
                ORDER BY {$by} DESC LIMIT ?
            ");
            $stmt->execute([$cutoff, $limit]);
        } else {
            // All-time: join file_stats with a subquery for path/ext
            if ($withpath) {
                $stmt = $pdo->prepare("
                    SELECT fs.item_id, fs.item_name, fs.views, fs.downloads,
                           (SELECT folder_path FROM events e2 WHERE e2.item_id=fs.item_id AND e2.event='file_download' ORDER BY e2.ts DESC LIMIT 1) AS folder_path,
                           (SELECT file_ext   FROM events e3 WHERE e3.item_id=fs.item_id AND e3.event='file_download' AND e3.file_ext IS NOT NULL ORDER BY e3.ts DESC LIMIT 1) AS file_ext
                    FROM file_stats fs ORDER BY {$by} DESC LIMIT ?
                ");
            } else {
                $stmt = $pdo->prepare(
                    "SELECT item_id, item_name, views, downloads FROM file_stats ORDER BY {$by} DESC LIMIT ?"
                );
            }
            $stmt->execute([$limit]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Folder activity  →  tracker.php?folders ───────────────────────────────
    if (isset($_GET['folders'])) {
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff] : [];
        $stmt = $pdo->prepare("
            SELECT folder_path,
                   SUM(CASE WHEN event='file_view'     THEN 1 ELSE 0 END) AS views,
                   SUM(CASE WHEN event='file_download' THEN 1 ELSE 0 END) AS downloads
            FROM events
            WHERE folder_path IS NOT NULL AND folder_path != '' {$df}
            GROUP BY folder_path
            ORDER BY (views + downloads) DESC LIMIT 10
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Daily trend  →  tracker.php?trend&days=30 ─────────────────────────────
    if (isset($_GET['trend'])) {
        $days = isset($_GET['days']) && (int)$_GET['days'] > 0
            ? min((int)$_GET['days'], 365) : 90;
        $stmt = $pdo->prepare("
            SELECT date(ts,'unixepoch') AS day,
                   SUM(CASE WHEN event='file_view'     THEN 1 ELSE 0 END) AS views,
                   SUM(CASE WHEN event='file_download' THEN 1 ELSE 0 END) AS downloads
            FROM events
            WHERE ts >= strftime('%s','now','-'||?||' days')
            GROUP BY day ORDER BY day
        ");
        $stmt->execute([$days]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Resource type breakdown  →  tracker.php?by_type ───────────────────────
    if (isset($_GET['by_type'])) {
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff] : [];
        $stmt = $pdo->prepare("
            SELECT item_type, COUNT(*) AS downloads
            FROM events
            WHERE event='file_download' AND item_type IS NOT NULL AND item_type != '' {$df}
            GROUP BY item_type ORDER BY downloads DESC
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Users who visited  →  tracker.php?users&days=30 ──────────────────────────
    // Returns one row per unique user with visit stats: sessions, views, downloads, last seen.
    if (isset($_GET['users'])) {
        $limit = min((int)($_GET['limit'] ?? 200), 1000);
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff, $limit] : [$limit];
        $stmt = $pdo->prepare("
            SELECT
                user_name,
                user_email,
                COUNT(DISTINCT session_id)                                   AS sessions,
                SUM(CASE WHEN event = 'file_view'     THEN 1 ELSE 0 END)   AS file_views,
                SUM(CASE WHEN event = 'file_download' THEN 1 ELSE 0 END)   AS downloads,
                SUM(CASE WHEN event = 'search'        THEN 1 ELSE 0 END)   AS searches,
                datetime(MAX(ts), 'unixepoch', 'localtime')                 AS last_seen,
                MAX(ts)                                                      AS last_ts
            FROM events
            WHERE user_name IS NOT NULL AND user_name != '' {$df}
            GROUP BY user_name, user_email
            ORDER BY last_ts DESC
            LIMIT ?
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }


    if (isset($_GET['searches'])) {
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff] : [];
        $stmt = $pdo->prepare("
            SELECT search_query, COUNT(*) AS count
            FROM events
            WHERE event='search' AND search_query IS NOT NULL AND search_query != '' {$df}
            GROUP BY search_query ORDER BY count DESC LIMIT 20
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Downloads by grade  →  tracker.php?by_grade ───────────────────────────
    if (isset($_GET['by_grade'])) {
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff] : [];
        $stmt = $pdo->prepare("
            SELECT json_extract(filters,'$.grade') AS grade, COUNT(*) AS downloads
            FROM events
            WHERE event='file_download' AND filters IS NOT NULL
              AND json_extract(filters,'$.grade') IS NOT NULL
              AND json_extract(filters,'$.grade') != '' {$df}
            GROUP BY grade ORDER BY downloads DESC
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Downloads by subject  →  tracker.php?by_subject ───────────────────────
    if (isset($_GET['by_subject'])) {
        [$df, $cutoff] = days_filter();
        $params = $cutoff ? [$cutoff] : [];
        $stmt = $pdo->prepare("
            SELECT json_extract(filters,'$.subject') AS subject, COUNT(*) AS downloads
            FROM events
            WHERE event='file_download' AND filters IS NOT NULL
              AND json_extract(filters,'$.subject') IS NOT NULL
              AND json_extract(filters,'$.subject') != '' {$df}
            GROUP BY subject ORDER BY downloads DESC
        ");
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // ── Most bookmarked files  →  tracker.php?top_bookmarked&limit=8 ──────────
    if (isset($_GET['top_bookmarked'])) {
        $limit = min((int)($_GET['limit'] ?? 8), 100);
        $stmt  = $pdo->prepare("
            SELECT
                fs.item_id,
                fs.item_name,
                fs.bookmarks                                                                                    AS bookmark_count,
                (SELECT e.item_type   FROM events e WHERE e.item_id = fs.item_id AND e.item_type   IS NOT NULL ORDER BY e.ts DESC LIMIT 1) AS item_type,
                (SELECT e.file_ext    FROM events e WHERE e.item_id = fs.item_id AND e.file_ext    IS NOT NULL ORDER BY e.ts DESC LIMIT 1) AS file_ext,
                (SELECT e.folder_path FROM events e WHERE e.item_id = fs.item_id AND e.folder_path IS NOT NULL ORDER BY e.ts DESC LIMIT 1) AS folder_path
            FROM file_stats fs
            WHERE fs.bookmarks > 0
            ORDER BY fs.bookmarks DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    // Default: return aggregated summary for a dashboard (with optional days filter)
    [$df, $cutoff] = days_filter();
    $params = $cutoff ? [$cutoff, $cutoff, $cutoff, $cutoff, $cutoff] : [];
    $w = $df; // WHERE fragment
    $summary = $pdo->prepare("
        SELECT
            (SELECT COUNT(DISTINCT session_id) FROM events WHERE 1=1 {$w}) AS total_sessions,
            (SELECT COUNT(*) FROM events WHERE event = 'file_view' {$w}) AS total_views,
            (SELECT COUNT(*) FROM events WHERE event = 'file_download' {$w}) AS total_downloads,
            (SELECT COUNT(*) FROM events WHERE event = 'search' {$w}) AS total_searches,
            (SELECT COUNT(DISTINCT user_oid) FROM events WHERE user_oid IS NOT NULL {$w}) AS unique_users,
            (SELECT COALESCE(SUM(bookmarks), 0) FROM file_stats) AS total_bookmarks
    ");
    $summary->execute($params);
    echo json_encode($summary->fetch(PDO::FETCH_ASSOC));
    exit;

} // end if GET

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
$allowed_events = ['page_view', 'file_view', 'file_download', 'folder_open', 'search', 'session_end', 'bookmark_add', 'bookmark_remove'];
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

$file_ext   = substr($data['file_ext']   ?? '', 0, 20);
$user_email = substr($data['user_email'] ?? '', 0, 200);

$stmt = $pdo->prepare(<<<SQL
    INSERT INTO events
        (session_id, user_oid, user_name, user_email, event, item_id, item_name, item_type,
         file_ext, folder_path, search_query, filters, duration_sec)
    VALUES
        (:session_id, :user_oid, :user_name, :user_email, :event, :item_id, :item_name, :item_type,
         :file_ext, :folder_path, :search_query, :filters, :duration_sec)
SQL);

$stmt->execute([
    ':session_id'   => $session_id,
    ':user_oid'     => $user_oid,
    ':user_name'    => $user_name ?: null,
    ':user_email'   => $user_email ?: null,
    ':event'        => $event,
    ':item_id'      => $item_id   ?: null,
    ':item_name'    => $item_name ?: null,
    ':item_type'    => $item_type ?: null,
    ':file_ext'     => $file_ext  ?: null,
    ':folder_path'  => $folder_path ?: null,
    ':search_query' => $search_query ?: null,
    ':filters'      => $filters,
    ':duration_sec' => $duration_sec,
]);

// For file events, return the updated counts so analytics.js can show them live
$response = ['ok' => true, 'id' => $pdo->lastInsertId()];
// Return updated counts so the badge refreshes immediately without a second fetch
if (in_array($event, ['file_view', 'file_download', 'bookmark_add', 'bookmark_remove'], true) && $item_id) {
    $cs = $pdo->prepare('SELECT views, downloads, bookmarks FROM file_stats WHERE item_id = ?');
    $cs->execute([$item_id]);
    $counts = $cs->fetch(PDO::FETCH_ASSOC);
    if ($counts) $response['counts'] = $counts;
}
if (in_array($event, ['file_view', 'file_download'], true) && $item_id) {
    $cs = $pdo->prepare('SELECT views, downloads FROM file_stats WHERE item_id = ?');
    $cs->execute([$item_id]);
    $counts = $cs->fetch(PDO::FETCH_ASSOC);
    if ($counts) $response['counts'] = $counts;
}

echo json_encode($response);