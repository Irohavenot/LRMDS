<?php
/**
 * DepEd LRMDS – site_stats.php  (unified stats backend)
 *
 * Used by BOTH index.php (via site-stats.js) AND manage.php dashboard.
 *
 * Actions:
 *   POST ?action=heartbeat    → upsert session row, return { ok, sid }
 *   GET  ?action=get_stats    → { ok, online, logins_today, total_visits }
 *   POST ?action=online_stats → heartbeat + get_stats combined
 *                               (drop-in replacement for manage.php's
 *                                user_api.php?action=online_stats call)
 *   GET  ?action=debug        → plain-text diagnostics
 *
 * Tables
 * ──────
 *   site_sessions  – one row per browser session (guests + logged-in)
 *   user_sessions  – one row per logged-in user_id (kept for compat)
 *   site_visits    – one row per session per day → cumulative visit count
 *
 * "Online" (index.php widget) = all sessions active within last 5 min.
 * "Online" (manage.php KPIs)  = authenticated sessions only (user_sessions).
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache');

define('SS_HOST',    'localhost');
define('SS_DBNAME',  'lrmds');
define('SS_USER',    'root');
define('SS_PASS',    '');
define('SS_CHARSET', 'utf8mb4');
define('ONLINE_SEC', 300); // 5 minutes

// ── Route ──────────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_stats');

if ($action === 'debug') { runDebug(); exit; }

try {
    $pdo = makePDO();
    ensureTables($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'DB error: ' . $e->getMessage()]);
    exit;
}

// manage.php calls action=online_stats via POST → heartbeat + manage stats
if ($action === 'online_stats' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    doHeartbeat($pdo, false);  // write session, no echo
    doGetStats($pdo, true);    // manage mode: online = auth sessions
    exit;
}

if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    doHeartbeat($pdo, true);   // write session + echo JSON
    exit;
}

// Default GET → index.php widget
doGetStats($pdo, false);

// ═══════════════════════════════════════════════════════════════

function makePDO(): PDO {
    return new PDO(
        'mysql:host=' . SS_HOST . ';dbname=' . SS_DBNAME . ';charset=' . SS_CHARSET,
        SS_USER, SS_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

function ensureTables(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_sessions (
        session_id  VARCHAR(128) NOT NULL,
        user_id     INT          DEFAULT NULL,
        last_seen   DATETIME     NOT NULL,
        PRIMARY KEY (session_id),
        INDEX idx_ls (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        user_id    INT      NOT NULL,
        last_seen  DATETIME NOT NULL,
        PRIMARY KEY (user_id),
        INDEX idx_us_ls (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id  VARCHAR(128)    NOT NULL,
        visit_date  DATE            NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_sd (session_id, visit_date),
        INDEX idx_vd (visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Write (or refresh) the current session into both tracking tables.
 * @param bool $respond  true → echo JSON response; false → silent (caller will echo)
 */
function doHeartbeat(PDO $pdo, bool $respond): void {
    $sid   = session_id();
    $uid   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $now   = date('Y-m-d H:i:s');
    $today = date('Y-m-d');

    // 1. Upsert site_sessions (everyone)
    $pdo->prepare("
        INSERT INTO site_sessions (session_id, user_id, last_seen)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            user_id   = VALUES(user_id),
            last_seen = VALUES(last_seen)
    ")->execute([$sid, $uid, $now]);

    // 2. Upsert user_sessions for logged-in users (keeps manage.php in sync)
    if ($uid !== null) {
        $pdo->prepare("
            INSERT INTO user_sessions (user_id, last_seen)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)
        ")->execute([$uid, $now]);
    }

    // 3. Record visit (once per session per calendar day)
    $pdo->prepare("
        INSERT IGNORE INTO site_visits (session_id, visit_date)
        VALUES (?, ?)
    ")->execute([$sid, $today]);

    // 4. Prune stale site_sessions rows (> 15 min idle)
    $pdo->exec("DELETE FROM site_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

    if ($respond) {
        echo json_encode(['ok' => true, 'sid' => $sid, 'uid' => $uid]);
    }
}

/**
 * Fetch and output stats as JSON.
 * @param bool $manage  false (index.php) → online = ALL sessions incl. guests
 *                      true  (manage.php) → online = authenticated only
 */
function doGetStats(PDO $pdo, bool $manage): void {
    // 1. Online count
    if ($manage) {
        // manage.php KPI: logged-in users only
        $online = 0;
        try {
            $online = (int) $pdo->query("
                SELECT COUNT(DISTINCT user_id) FROM user_sessions
                WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ")->fetchColumn();
        } catch (PDOException $e) {}
    } else {
        // index.php widget: everyone (guests + logged-in)
        $st = $pdo->prepare("
            SELECT COUNT(*) FROM site_sessions
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL :s SECOND)
        ");
        $st->execute([':s' => ONLINE_SEC]);
        $online = (int) $st->fetchColumn();
    }

    // 2. Logins today
    $loginsToday = 0;
    try {
        $loginsToday = (int) $pdo->query("
            SELECT COUNT(*) FROM users WHERE DATE(last_login) = CURDATE()
        ")->fetchColumn();
    } catch (PDOException $e) {}

    // 3. Total visits
    $totalVisits = (int) $pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();

    echo json_encode([
        'ok'           => true,
        'online'       => $online,
        'logins_today' => $loginsToday,
        'total_visits' => $totalVisits,
        'today'        => $loginsToday,  // alias for manage.php (reads data.today)
    ]);
}

function runDebug(): void {
    header('Content-Type: text/plain');
    echo "=== LRMDS site_stats.php DEBUG ===\n\n";
    echo "PHP session_id : " . session_id() . "\n";
    echo "Session user_id: " . ($_SESSION['user_id'] ?? '(guest / not set)') . "\n\n";

    try {
        $pdo = makePDO();
        echo "DB connection  : OK (database=" . SS_DBNAME . ")\n";
        ensureTables($pdo);
        echo "Tables         : OK\n\n";

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM site_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL :s SECOND)");
        $cnt->execute([':s' => ONLINE_SEC]);
        echo "Currently online (" . ONLINE_SEC . "s window): " . $cnt->fetchColumn() . "\n\n";

        echo "site_sessions table (all rows):\n";
        $rows = $pdo->query("SELECT session_id, user_id, last_seen FROM site_sessions ORDER BY last_seen DESC")->fetchAll();
        if (!$rows) echo "  (empty — heartbeat not yet received)\n";
        foreach ($rows as $r) {
            echo "  SID=" . substr($r['session_id'], 0, 20) . "...  uid=" . ($r['user_id'] ?? 'guest') . "  last_seen=" . $r['last_seen'] . "\n";
        }

        echo "\nuser_sessions table (logged-in only):\n";
        $rows2 = $pdo->query("SELECT user_id, last_seen FROM user_sessions ORDER BY last_seen DESC")->fetchAll();
        if (!$rows2) echo "  (empty)\n";
        foreach ($rows2 as $r) echo "  uid=" . $r['user_id'] . "  last_seen=" . $r['last_seen'] . "\n";

        echo "\nTotal visit rows : " . $pdo->query("SELECT COUNT(*) FROM site_visits")->fetchColumn() . "\n";
        try {
            echo "Logins today     : " . $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(last_login) = CURDATE()")->fetchColumn() . "\n";
        } catch (PDOException $e) {
            echo "Logins today     : ERROR — " . $e->getMessage() . "\n";
        }
    } catch (PDOException $e) {
        echo "\nDB ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n=== END ===\n";
}