<?php
/**
 * DepEd LRMDS – site_stats.php
 *
 * POST action=heartbeat    → track visitor, return { ok, sid, uid }
 * GET  action=get_stats    → { ok, online, logins_today, total_visits }
 * POST action=online_stats → manage.php: heartbeat + full stats
 * GET  action=debug        → diagnostics
 */

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache');

// ── DB config ────────────────────────────────────────────────
define('SS_HOST',   'localhost');
define('SS_DB',     'lrmds');
define('SS_USER',   'root');
define('SS_PASS',   '');
define('SS_CHAR',   'utf8mb4');
define('ONLINE_SEC', 300);  // 5 minutes

// ── Get logged-in user ID (works for all login flows) ────────
function uid(): ?int {
    // signin_handler.php and totp_verify.php both set $_SESSION['user_id']
    if (!empty($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
    // Google OAuth or other flows may use $_SESSION['user']['id']
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    return null;
}

// ── DB connection ─────────────────────────────────────────────
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host='.SS_HOST.';dbname='.SS_DB.';charset='.SS_CHAR,
        SS_USER, SS_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    return $pdo;
}

// ── Ensure tables exist ───────────────────────────────────────
function ensureTables(): void {
    $p = db();
    $p->exec("CREATE TABLE IF NOT EXISTS site_sessions (
        session_id VARCHAR(128) NOT NULL,
        user_id    INT          DEFAULT NULL,
        last_seen  DATETIME     NOT NULL,
        PRIMARY KEY (session_id),
        INDEX idx_ls (last_seen),
        INDEX idx_uid (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $p->exec("CREATE TABLE IF NOT EXISTS site_visits (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(128) NOT NULL,
        visit_date DATE         NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_sd (session_id, visit_date),
        INDEX idx_vd (visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $p->exec("CREATE TABLE IF NOT EXISTS user_sessions (
        user_id   INT      NOT NULL,
        last_seen DATETIME NOT NULL,
        PRIMARY KEY (user_id),
        INDEX idx_ls (last_seen)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ── Heartbeat: record this visitor ────────────────────────────
function heartbeat(): void {
    $p     = db();
    $sid   = session_id();
    $uid   = uid();
    $now   = date('Y-m-d H:i:s');

    // Track session (guest or logged-in)
    $p->prepare("INSERT INTO site_sessions (session_id, user_id, last_seen)
                 VALUES (?,?,?)
                 ON DUPLICATE KEY UPDATE user_id=VALUES(user_id), last_seen=VALUES(last_seen)")
      ->execute([$sid, $uid, $now]);

    // Track logged-in user (mirrors user_api.php behaviour exactly)
    if ($uid !== null) {
        $p->prepare("INSERT INTO user_sessions (user_id, last_seen)
                     VALUES (?,?)
                     ON DUPLICATE KEY UPDATE last_seen=VALUES(last_seen)")
          ->execute([$uid, $now]);
    }

    // Record visit (once per session per day)
    $p->prepare("INSERT IGNORE INTO site_visits (session_id, visit_date) VALUES (?,?)")
      ->execute([$sid, date('Y-m-d')]);

    // Prune sessions idle > 15 min
    $p->exec("DELETE FROM site_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
}

// ── Stats ─────────────────────────────────────────────────────
function stats(bool $manage): array {
    $p = db();

    // Authenticated online: use user_sessions — written by BOTH user_api.php and heartbeat()
    $onlineAuth = (int) $p->query("
        SELECT COUNT(DISTINCT user_id) FROM user_sessions
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)
    ")->fetchColumn();

    // Guest online: site_sessions rows with no user_id
    $onlineGuest = (int) $p->query("
        SELECT COUNT(*) FROM site_sessions
        WHERE user_id IS NULL
          AND last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)
    ")->fetchColumn();

    $loginsToday = 0;
    try { $loginsToday = (int)$p->query("SELECT COUNT(*) FROM users WHERE DATE(last_login)=CURDATE()")->fetchColumn(); } catch(Exception $e){}

    $totalVisits = (int)$p->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();

    $out = [
        'ok'           => true,
        'online'       => $manage ? $onlineAuth : ($onlineAuth + $onlineGuest),
        'online_auth'  => $onlineAuth,
        'online_guest' => $onlineGuest,
        'logins_today' => $loginsToday,
        'total_visits' => $totalVisits,
        'today'        => $loginsToday,
    ];

    if ($manage) {
        try {
            $row = $p->query("SELECT
                COUNT(*) AS total,
                SUM(status='active') AS active,
                SUM(status IN ('pending','email_pending')) AS pending,
                SUM(status='suspended') AS suspended,
                SUM(role='guest') AS guests
            FROM users")->fetch();
            $out['users_total']     = (int)($row['total']??0);
            $out['users_active']    = (int)($row['active']??0);
            $out['users_pending']   = (int)($row['pending']??0);
            $out['users_suspended'] = (int)($row['suspended']??0);
            $out['users_guests']    = (int)($row['guests']??0);
        } catch(Exception $e){}
    }
    return $out;
}

// ── Route ─────────────────────────────────────────────────────
$action = $_POST['action'] ?? $_GET['action'] ?? 'get_stats';

if ($action === 'debug') {
    header('Content-Type: text/plain');
    try { ensureTables(); } catch(Exception $e){ echo "DB ERROR: ".$e->getMessage(); exit; }
    $p = db();

    echo "=== LRMDS site_stats.php DEBUG ===\n\n";
    echo "--- Session ---\n";
    echo "session_id()      : ".session_id()."\n";
    echo "uid()             : ".var_export(uid(),true)."\n";
    echo "\$_SESSION keys:\n";
    foreach($_SESSION as $k=>$v){
        if(str_starts_with((string)$k,'login_')||str_starts_with((string)$k,'totp_')) continue;
        echo "  [$k] => ".var_export($v,true)."\n";
    }

    echo "\n--- Tables ---\n";
    echo "site_sessions:\n";
    foreach($p->query("SELECT session_id,user_id,last_seen FROM site_sessions ORDER BY last_seen DESC LIMIT 20")->fetchAll() as $r)
        echo "  ".substr($r['session_id'],0,26)."  uid=".($r['user_id']??'NULL')."  ".($r['last_seen'])."\n";

    echo "\nuser_sessions:\n";
    foreach($p->query("SELECT user_id,last_seen FROM user_sessions ORDER BY last_seen DESC LIMIT 10")->fetchAll() as $r)
        echo "  uid=".$r['user_id']."  ".$r['last_seen']."\n";

    echo "\n--- Online counts (user_sessions, ".ONLINE_SEC."s window) ---\n";
    $a = (int)$p->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)")->fetchColumn();
    $g = (int)$p->query("SELECT COUNT(*) FROM site_sessions WHERE user_id IS NULL AND last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)")->fetchColumn();
    echo "  Auth  : $a\n  Guest : $g\n\n";

    echo "--- Simulated online_stats JSON ---\n";
    echo json_encode(stats(true), JSON_PRETTY_PRINT)."\n";
    exit;
}

try {
    ensureTables();
} catch(PDOException $e){
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'DB: '.$e->getMessage()]);
    exit;
}

if ($action === 'online_stats' && $_SERVER['REQUEST_METHOD']==='POST') {
    heartbeat();
    echo json_encode(stats(true));
    exit;
}

if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD']==='POST') {
    heartbeat();
    echo json_encode(['ok'=>true,'sid'=>session_id(),'uid'=>uid()]);
    exit;
}

// Default: GET get_stats (index.php widget)
echo json_encode(stats(false));