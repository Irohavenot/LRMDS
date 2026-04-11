<?php
/**
 * DepEd LRMDS – user_api.php
 * REST-style JSON API for user management in manage.php
 *
 * Actions (POST param 'action'):
 *   list_pending  – pending applications the current user can approve
 *   list_users    – paginated user list (filterable)
 *   approve       – approve a pending user
 *   reject        – delete a pending user
 *   update_user   – update profile / role / status
 *   disable_totp  – reset TOTP for a user
 *   get_user      – fetch one user's full profile
 *   stats         – summary counts
 *   online_stats  – online users + logins today (for dashboard KPIs)
 */

session_start();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/* ── Auth guard ──────────────────────────────────────────── */
if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    exit;
}

$actor_role = $_SESSION['user_role'] ?? '';
$actor_id   = (int) ($_SESSION['user_id'] ?? 0);

/* ── Hierarchy rules ─────────────────────────────────────
 *  admin        → can approve/manage everyone
 *  developer    → can approve school-head, developer; manage teacher/learner/parent/guest
 *  school-head  → can approve teacher; manage teacher within same division
 *
 *  Returns which roles the actor is ALLOWED to approve.
 */
function approvable_roles(string $role): array {
    return match($role) {
        'admin'       => ['teacher', 'school-head', 'developer', 'admin'],
        'developer'   => ['school-head', 'developer'],
        'school-head' => ['teacher'],
        default       => [],
    };
}

function manageable_roles(string $role): array {
    return match($role) {
        'admin'       => ['teacher', 'school-head', 'developer', 'admin', 'learner', 'parent', 'guest'],
        'developer'   => ['teacher', 'school-head', 'developer', 'learner', 'parent', 'guest'],
        'school-head' => ['teacher'],
        default       => [],
    };
}

/* ── DB ─────────────────────────────────────────────────── */
define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET),
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('LRMDS user_api DB: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ════════════════════════════════════════════════════════
   ACTION: stats
════════════════════════════════════════════════════════ */
if ($action === 'stats') {
    $roles = approvable_roles($actor_role);

    $total     = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $active    = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $suspended = $pdo->query("SELECT COUNT(*) FROM users WHERE status='suspended'")->fetchColumn();
    $guests    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='guest'")->fetchColumn();

    // Only count pending users that this actor can approve
    $pending = 0;
    if (!empty($roles)) {
        $in  = implode(',', array_fill(0, count($roles), '?'));
        $pending = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status='pending' AND role IN ($in)");
        $pending->execute($roles);
        $pending = $pending->fetchColumn();
    }

    echo json_encode([
        'ok'        => true,
        'total'     => (int) $total,
        'active'    => (int) $active,
        'pending'   => (int) $pending,
        'suspended' => (int) $suspended,
        'guests'    => (int) $guests,
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: online_stats
   Tracks presence via a lightweight session-heartbeat table.
   Falls back gracefully if the table doesn't exist yet.
════════════════════════════════════════════════════════ */
if ($action === 'online_stats') {
    // Heartbeat: upsert current user's activity
    try {
        $pdo->prepare('
            INSERT INTO user_sessions (user_id, last_seen)
            VALUES (?, NOW())
            ON DUPLICATE KEY UPDATE last_seen = NOW()
        ')->execute([$actor_id]);
    } catch (PDOException) { /* table may not exist yet */ }

    // Online = active within the last 5 minutes
    $online = 0;
    try {
        $online = (int) $pdo->query("
            SELECT COUNT(DISTINCT user_id) FROM user_sessions
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->fetchColumn();
    } catch (PDOException) {}

    // Logins today = last_login >= today 00:00:00
    $today = 0;
    try {
        $today = (int) $pdo->query("
            SELECT COUNT(*) FROM users
            WHERE DATE(last_login) = CURDATE()
        ")->fetchColumn();
    } catch (PDOException) {}

    echo json_encode(['ok' => true, 'online' => $online, 'today' => $today]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: list_pending
════════════════════════════════════════════════════════ */
if ($action === 'list_pending') {
    $roles = approvable_roles($actor_role);
    if (empty($roles)) {
        echo json_encode(['ok' => true, 'users' => [], 'total' => 0]);
        exit;
    }

    $search = '%' . trim($_POST['search'] ?? '') . '%';
    $role_f = $_POST['role'] ?? '';

    $where_roles = implode(',', array_fill(0, count($roles), '?'));
    $params      = $roles;

    $where = "WHERE status='pending' AND role IN ($where_roles)";

    if ($role_f && in_array($role_f, $roles, true)) {
        $where  .= ' AND role = ?';
        $params[] = $role_f;
    }

    // school-head: scope to own division
    if ($actor_role === 'school-head') {
        $actor_row = $pdo->prepare('SELECT division FROM users WHERE id = ? LIMIT 1');
        $actor_row->execute([$actor_id]);
        $div = $actor_row->fetchColumn();
        if ($div) {
            $where  .= ' AND (division = ? OR division IS NULL)';
            $params[] = $div;
        }
    }

    if (trim($_POST['search'] ?? '') !== '') {
        $where  .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, role, region, division,
               employee_id, totp_enabled, created_at, meta
        FROM users $where
        ORDER BY created_at ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : [];
    }

    echo json_encode(['ok' => true, 'users' => $rows, 'total' => count($rows)]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: list_users
════════════════════════════════════════════════════════ */
if ($action === 'list_users') {
    $allowed = manageable_roles($actor_role);
    if (empty($allowed)) {
        echo json_encode(['ok' => true, 'users' => [], 'total' => 0]);
        exit;
    }

    $search    = '%' . trim($_POST['search'] ?? '') . '%';
    $role_f    = $_POST['role']   ?? '';
    $status_f  = $_POST['status'] ?? '';
    $page      = max(1, (int) ($_POST['page'] ?? 1));
    $per_page  = 50;
    $offset    = ($page - 1) * $per_page;

    $in     = implode(',', array_fill(0, count($allowed), '?'));
    $params = $allowed;
    $where  = "WHERE role IN ($in)";

    if ($role_f && in_array($role_f, $allowed, true)) {
        $where  .= ' AND role = ?';
        $params[] = $role_f;
    }
    if ($status_f) {
        $where  .= ' AND status = ?';
        $params[] = $status_f;
    }
    if (trim($_POST['search'] ?? '') !== '') {
        $where  .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
        $params = array_merge($params, [$search, $search, $search, $search]);
    }

    // school-head scoped to division
    if ($actor_role === 'school-head') {
        $actor_row = $pdo->prepare('SELECT division FROM users WHERE id = ? LIMIT 1');
        $actor_row->execute([$actor_id]);
        $div = $actor_row->fetchColumn();
        if ($div) {
            $where  .= ' AND division = ?';
            $params[] = $div;
        }
    }

    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
    $count_stmt->execute($params);
    $total = (int) $count_stmt->fetchColumn();

    $params[] = $per_page;
    $params[] = $offset;

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, role, status, region, division,
               employee_id, totp_enabled, last_login, created_at
        FROM users $where
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'ok'       => true,
        'users'    => $rows,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
    ]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: get_user
════════════════════════════════════════════════════════ */
if ($action === 'get_user') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('
        SELECT id, first_name, last_name, email, role, status, region, division,
               employee_id, totp_enabled, last_login, created_at, meta
        FROM users WHERE id = ? LIMIT 1
    ');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['ok' => false, 'msg' => 'User not found.']);
        exit;
    }
    $user['meta'] = $user['meta'] ? json_decode($user['meta'], true) : [];
    echo json_encode(['ok' => true, 'user' => $user]);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: approve
════════════════════════════════════════════════════════ */
if ($action === 'approve') {
    $id = (int) ($_POST['id'] ?? 0);

    // Fetch the target user
    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        echo json_encode(['ok' => false, 'msg' => 'User not found.']);
        exit;
    }
    if ($target['status'] !== 'pending') {
        echo json_encode(['ok' => false, 'msg' => 'User is not pending.']);
        exit;
    }
    if (!in_array($target['role'], approvable_roles($actor_role), true)) {
        echo json_encode(['ok' => false, 'msg' => 'You do not have permission to approve this role.']);
        exit;
    }

    $pdo->prepare("UPDATE users SET status='active', approved_by=?, approved_at=NOW() WHERE id=?")
        ->execute([$actor_id, $id]);

    echo json_encode(['ok' => true, 'msg' => 'User approved and account activated.']);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: reject
════════════════════════════════════════════════════════ */
if ($action === 'reject') {
    $id     = (int) ($_POST['id'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        echo json_encode(['ok' => false, 'msg' => 'User not found.']);
        exit;
    }
    if ($target['status'] !== 'pending') {
        echo json_encode(['ok' => false, 'msg' => 'User is not pending.']);
        exit;
    }
    if (!in_array($target['role'], approvable_roles($actor_role), true)) {
        echo json_encode(['ok' => false, 'msg' => 'You do not have permission to reject this role.']);
        exit;
    }

    // Log rejection before deleting
    error_log("LRMDS user rejected: id={$id} role={$target['role']} by actor={$actor_id} reason={$reason}");

    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

    echo json_encode(['ok' => true, 'msg' => 'Application rejected and removed.']);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: update_user
════════════════════════════════════════════════════════ */
if ($action === 'update_user') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $target = $stmt->fetch();

    if (!$target) {
        echo json_encode(['ok' => false, 'msg' => 'User not found.']);
        exit;
    }
    if (!in_array($target['role'], manageable_roles($actor_role), true) && $actor_role !== 'admin') {
        echo json_encode(['ok' => false, 'msg' => 'Insufficient permissions.']);
        exit;
    }

    $fname       = trim($_POST['fname']       ?? '');
    $lname       = trim($_POST['lname']       ?? '');
    $email       = trim($_POST['email']       ?? '');
    $role        = trim($_POST['role']        ?? '');
    $status      = trim($_POST['status']      ?? '');
    $region      = trim($_POST['region']      ?? '');
    $division    = trim($_POST['division']    ?? '');
    $employee_id = trim($_POST['employee_id'] ?? '');
    $new_pw      = $_POST['new_password']     ?? '';

    // Only admin can change roles to admin
    $allowed_new_roles = manageable_roles($actor_role);
    if ($actor_role === 'admin') $allowed_new_roles[] = 'admin';
    if (!in_array($role, $allowed_new_roles, true)) {
        $role = $target['role']; // silently keep existing role
    }

    $params = [
        ':fname'       => $fname,
        ':lname'       => $lname,
        ':email'       => strtolower($email),
        ':role'        => $role,
        ':status'      => $status,
        ':region'      => $region,
        ':division'    => $division ?: null,
        ':employee_id' => $employee_id ?: null,
        ':id'          => $id,
    ];

    $pw_clause = '';
    if ($new_pw !== '' && strlen($new_pw) >= 8) {
        $pw_clause  = ', password_hash = :pw_hash';
        $params[':pw_hash'] = password_hash($new_pw, PASSWORD_BCRYPT);
    }

    $pdo->prepare("
        UPDATE users
        SET first_name=:fname, last_name=:lname, email=:email,
            role=:role, status=:status, region=:region,
            division=:division, employee_id=:employee_id
            $pw_clause
        WHERE id=:id
    ")->execute($params);

    echo json_encode(['ok' => true, 'msg' => 'User updated successfully.']);
    exit;
}

/* ════════════════════════════════════════════════════════
   ACTION: disable_totp
════════════════════════════════════════════════════════ */
if ($action === 'disable_totp') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($actor_role !== 'admin' && $actor_role !== 'developer') {
        echo json_encode(['ok' => false, 'msg' => 'Only admins or developers can disable 2FA.']);
        exit;
    }
    $pdo->prepare("UPDATE users SET totp_enabled=0, totp_secret=NULL WHERE id=?")
        ->execute([$id]);
    echo json_encode(['ok' => true, 'msg' => '2FA disabled. User must re-enroll on next login.']);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
exit;