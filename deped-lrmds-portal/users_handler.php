<?php
/**
 * DepEd LRMDS – users_handler.php
 *
 * Handles AJAX actions for the User Management panel in manage.php.
 * Actions: approve, reject, suspend, reactivate, change_role, list_pending, list_users
 *
 * Only accessible to school-head and developer roles.
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Roles allowed to manage users
define('ADMIN_ROLES', ['school-head', 'developer']);

/* ── Auth guard ─────────────────────────────────────────────── */
if (empty($_SESSION['user']) || empty($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Not authenticated.']);
    exit;
}

if (!in_array($_SESSION['user_role'], ADMIN_ROLES, true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Insufficient permissions.']);
    exit;
}

/* ── DB ─────────────────────────────────────────────────────── */
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
    error_log('LRMDS users_handler DB: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed.']);
    exit;
}

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    /* ── LIST PENDING REGISTRATIONS ────────────────────────── */
    case 'list_pending':
        $role_filter   = trim($_GET['role']   ?? '');
        $search_filter = trim($_GET['search'] ?? '');

        $sql = 'SELECT id, first_name, last_name, email, role, region, division,
                       employee_id, meta, created_at
                FROM   users
                WHERE  status = "pending"';
        $params = [];

        if ($role_filter) {
            $sql .= ' AND role = ?';
            $params[] = $role_filter;
        }
        if ($search_filter) {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
            $like = '%' . $search_filter . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 100';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Decode meta JSON for display
        foreach ($rows as &$r) {
            $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : [];
            $r['created_at_human'] = date('M j, Y', strtotime($r['created_at']));
        }
        unset($r);

        echo json_encode(['ok' => true, 'data' => $rows, 'count' => count($rows)]);
        break;

    /* ── LIST ALL USERS ────────────────────────────────────── */
    case 'list_users':
        $role_filter   = trim($_GET['role']   ?? '');
        $status_filter = trim($_GET['status'] ?? '');
        $search_filter = trim($_GET['search'] ?? '');

        $sql = 'SELECT id, first_name, last_name, email, role, status,
                       region, division, employee_id, totp_enabled,
                       last_login, created_at
                FROM   users
                WHERE  1=1';
        $params = [];

        if ($role_filter) {
            $sql .= ' AND role = ?';
            $params[] = $role_filter;
        }
        if ($status_filter) {
            $sql .= ' AND status = ?';
            $params[] = $status_filter;
        }
        if ($search_filter) {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)';
            $like = '%' . $search_filter . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 200';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['created_at_human']  = date('M j, Y', strtotime($r['created_at']));
            $r['last_login_human']  = $r['last_login']
                ? date('M j, Y', strtotime($r['last_login']))
                : 'Never';
            // Prevent exposing acting admin's own ID accidentally
        }
        unset($r);

        echo json_encode(['ok' => true, 'data' => $rows, 'count' => count($rows)]);
        break;

    /* ── APPROVE ───────────────────────────────────────────── */
    case 'approve':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID.']); exit; }

        // Confirm it's actually pending
        $check = $pdo->prepare('SELECT id, role FROM users WHERE id = ? AND status = "pending" LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'User not found or already processed.']);
            exit;
        }

        $pdo->prepare('UPDATE users SET status = "active" WHERE id = ?')->execute([$id]);
        error_log("LRMDS: User {$id} approved by {$_SESSION['user']}");
        echo json_encode(['ok' => true, 'msg' => 'Account approved and activated.']);
        break;

    /* ── REJECT / DELETE PENDING ───────────────────────────── */
    case 'reject':
        $id     = (int)($_POST['id']     ?? 0);
        $reason = trim($_POST['reason']  ?? '');
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID.']); exit; }

        $check = $pdo->prepare('SELECT id FROM users WHERE id = ? AND status = "pending" LIMIT 1');
        $check->execute([$id]);
        if (!$check->fetch()) {
            echo json_encode(['ok' => false, 'msg' => 'User not found or already processed.']);
            exit;
        }

        // Hard-delete pending registrations on rejection (they never fully activated)
        $pdo->prepare('DELETE FROM users WHERE id = ? AND status = "pending"')->execute([$id]);
        error_log("LRMDS: User {$id} rejected by {$_SESSION['user']}. Reason: {$reason}");
        echo json_encode(['ok' => true, 'msg' => 'Application rejected and removed.']);
        break;

    /* ── SUSPEND ACTIVE USER ───────────────────────────────── */
    case 'suspend':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID.']); exit; }

        // Can't suspend yourself
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['ok' => false, 'msg' => 'You cannot suspend your own account.']);
            exit;
        }

        $pdo->prepare('UPDATE users SET status = "suspended" WHERE id = ? AND status = "active"')
            ->execute([$id]);
        error_log("LRMDS: User {$id} suspended by {$_SESSION['user']}");
        echo json_encode(['ok' => true, 'msg' => 'Account suspended.']);
        break;

    /* ── REACTIVATE SUSPENDED USER ─────────────────────────── */
    case 'reactivate':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok'=>false,'msg'=>'Missing ID.']); exit; }

        $pdo->prepare('UPDATE users SET status = "active" WHERE id = ? AND status = "suspended"')
            ->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Account reactivated.']);
        break;

    /* ── CHANGE ROLE ───────────────────────────────────────── */
    case 'change_role':
        $id      = (int)($_POST['id']   ?? 0);
        $new_role = trim($_POST['role'] ?? '');
        $allowed  = ['teacher','learner','parent','school-head','developer','guest'];

        if (!$id || !in_array($new_role, $allowed, true)) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid ID or role.']);
            exit;
        }
        if ($id === (int)$_SESSION['user_id']) {
            echo json_encode(['ok' => false, 'msg' => 'You cannot change your own role.']);
            exit;
        }

        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$new_role, $id]);
        error_log("LRMDS: User {$id} role changed to {$new_role} by {$_SESSION['user']}");
        echo json_encode(['ok' => true, 'msg' => "Role updated to {$new_role}."]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
}