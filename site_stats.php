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
    // Sync MySQL timezone to PHP so date comparisons are consistent
    $offset = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime()) / 3600;
    $sign   = $offset >= 0 ? '+' : '-';
    $pdo->exec("SET time_zone = '{$sign}" . abs($offset) . ":00'");
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
// Roles that REQUIRE TOTP (must mirror signin_handler.php / manage.js)
define('TOTP_ROLES', ['teacher', 'school-head', 'developer', 'admin']);

function stats(bool $manage, ?string $actor_role = null): array {
    $p = db();

    // ── Online counts ──────────────────────────────────────────
    // Authenticated: tracked in user_sessions (written by heartbeat + user_api)
    $onlineAuth = (int) $p->query("
        SELECT COUNT(DISTINCT user_id) FROM user_sessions
        WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)
    ")->fetchColumn();

    // Guests: site_sessions with no user_id
    $onlineGuest = (int) $p->query("
        SELECT COUNT(*) FROM site_sessions
        WHERE user_id IS NULL
          AND last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)
    ")->fetchColumn();

    // ── Basic visit/login stats ────────────────────────────────
    $loginsToday = 0;
    $totalVisits = 0;
    try {
        $loginsToday = (int) $p->query("SELECT COUNT(*) FROM users WHERE DATE(last_login) = CURDATE()")->fetchColumn();
        $totalVisits = (int) $p->query("SELECT COUNT(*) FROM site_visits")->fetchColumn();
    } catch(Exception $e) {}

    $out = [
        'ok'           => true,
        'online'       => $manage ? $onlineAuth : ($onlineAuth + $onlineGuest),
        'online_auth'  => $onlineAuth,
        'online_guest' => $onlineGuest,
        'logins_today' => $loginsToday,
        'total_visits' => $totalVisits,
        'today'        => $loginsToday,  // alias used by manage.js
    ];

    if (!$manage) return $out;

    // ── Extended stats for manage.php dashboard ────────────────
    try {
        // User status/role summary
        $row = $p->query("SELECT
            COUNT(*)                                        AS total,
            SUM(status = 'active')                         AS active,
            SUM(status IN ('pending','email_pending'))     AS pending,
            SUM(status = 'suspended')                      AS suspended,
            SUM(role = 'guest')                            AS guests,
            SUM(DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7  DAY)) AS new_week,
            SUM(DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AS new_month
        FROM users")->fetch();

        $out['users_total']          = (int)($row['total']    ?? 0);
        $out['users_active']         = (int)($row['active']   ?? 0);
        $out['users_pending']        = (int)($row['pending']  ?? 0);
        $out['users_suspended']      = (int)($row['suspended']?? 0);
        $out['users_guests']         = (int)($row['guests']   ?? 0);
        $out['users_new_this_week']  = (int)($row['new_week'] ?? 0);
        $out['users_new_this_month'] = (int)($row['new_month']?? 0);

        // ── Role-scoped pending count (for the nav badge) ──────
        // Uses the caller's role the same way user_api.php did.
        if ($actor_role !== null) {
            $approvable = match($actor_role) {
                'admin'       => ['teacher', 'school-head', 'developer', 'admin'],
                'developer'   => ['school-head', 'developer'],
                'school-head' => ['teacher'],
                default       => [],
            };
            if (!empty($approvable)) {
                $in  = implode(',', array_fill(0, count($approvable), '?'));
                $st  = $p->prepare("SELECT COUNT(*) FROM users WHERE status='pending' AND role IN ($in)");
                $st->execute($approvable);
                $out['users_pending'] = (int) $st->fetchColumn();
            }
        }

        // ── 2FA adoption ───────────────────────────────────────
        $totpRoles = TOTP_ROLES;
        $inTotp    = implode(',', array_fill(0, count($totpRoles), '?'));
        $totpSt    = $p->prepare("SELECT
            SUM(totp_enabled = 1) AS enabled,
            COUNT(*)              AS required
        FROM users WHERE role IN ($inTotp)");
        $totpSt->execute($totpRoles);
        $totpRow = $totpSt->fetch();
        $out['totp_enabled_count']  = (int)($totpRow['enabled']  ?? 0);
        $out['totp_required_count'] = (int)($totpRow['required'] ?? 0);

        // ── Visit breakdown ────────────────────────────────────
        $out['visits_today']     = (int) $p->query("SELECT COUNT(*) FROM site_visits WHERE visit_date = CURDATE()")->fetchColumn();
        $out['visits_this_week'] = (int) $p->query("SELECT COUNT(*) FROM site_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();

        // ── Online breakdown by role ───────────────────────────
        // Join user_sessions (active within ONLINE_SEC) back to users to get role
        $roleRows = $p->query("
            SELECT u.role, COUNT(*) AS cnt
            FROM user_sessions us
            JOIN users u ON u.id = us.user_id
            WHERE us.last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)
            GROUP BY u.role
        ")->fetchAll();

        $byRole = [];
        foreach ($roleRows as $r) {
            $byRole[$r['role']] = (int) $r['cnt'];
        }
        $out['online_by_role'] = $byRole;

    } catch(Exception $e) {
        // Non-fatal: partial stats are still useful
        error_log('LRMDS site_stats extended: ' . $e->getMessage());
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

    echo "\n--- Clock check ---\n";
    echo "  PHP  NOW : ".date('Y-m-d H:i:s')."\n";
    echo "  MySQL NOW: ".$p->query("SELECT NOW()")->fetchColumn()."\n";
    echo "  MySQL TZ : ".$p->query("SELECT @@session.time_zone")->fetchColumn()."\n\n";

    echo "--- Raw user_sessions rows vs MySQL NOW ---\n";
    foreach($p->query("SELECT user_id, last_seen, TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age_sec FROM user_sessions ORDER BY last_seen DESC LIMIT 5")->fetchAll() as $r)
        echo "  uid=".$r['user_id']."  last_seen=".$r['last_seen']."  age=".($r['age_sec'])."s\n";

    echo "\n--- Online counts (user_sessions, ".ONLINE_SEC."s window) ---\n";
    $a = (int)$p->query("SELECT COUNT(DISTINCT user_id) FROM user_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)")->fetchColumn();
    $g = (int)$p->query("SELECT COUNT(*) FROM site_sessions WHERE user_id IS NULL AND last_seen >= DATE_SUB(NOW(), INTERVAL ".ONLINE_SEC." SECOND)")->fetchColumn();
    echo "  Auth  : $a\n  Guest : $g\n\n";

    echo "--- Simulated online_stats JSON ---\n";
    echo json_encode(stats(true, $_SESSION['user_role'] ?? null), JSON_PRETTY_PRINT)."\n";
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
    // Pass the actor's role so pending counts are scoped to their approval level
    $actor_role = $_SESSION['user_role'] ?? null;
    echo json_encode(stats(true, $actor_role));
    exit;
}

if ($action === 'heartbeat' && $_SERVER['REQUEST_METHOD']==='POST') {
    heartbeat();
    echo json_encode(['ok'=>true,'sid'=>session_id(),'uid'=>uid()]);
    exit;
}

// Default: GET get_stats (index.php widget)
echo json_encode(stats(false));