<?php
/**
 * DepEd LRMDS – users_handler.php
 * ─────────────────────────────────
 * Handles all admin user-management AJAX calls from manage.js.
 *
 * Actions (GET):
 *   list_pending   – pending registrations
 *   list_users     – all users with filters
 *   get_user       – single user by id
 *
 * Actions (POST):
 *   approve          – approve a pending user
 *   reject           – delete a pending user
 *   suspend          – set status = suspended
 *   reactivate       – set status = active
 *   change_role      – change role (auto-clears TOTP if downgrading to non-2FA role)
 *   edit_user        – full profile edit (role-gated, auto-clears TOTP if needed)
 *   disable_totp     – wipe TOTP secret manually
 *   send_password_reset – stub / mail trigger
 */

session_start();

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

/* ── Auth guard: resolve role from session, with DB fallback for TOTP-login users ── */
$myRole = $_SESSION['user_role'] ?? '';

// TOTP-authenticated users (admin, teacher, school-head, developer) have their full
// session written by totp_verify.php. If user_role is still empty but user_id exists,
// fetch the role directly from the DB so TOTP users aren't locked out.
if ($myRole === '' && !empty($_SESSION['user_id'])) {
    // DB connection must happen first — reuse the block below, so we do a quick inline fetch.
    $tmpDsn = "mysql:host=localhost;dbname=lrmds;charset=utf8mb4";
    try {
        $tmpPdo  = new PDO($tmpDsn, 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $tmpStmt = $tmpPdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $tmpStmt->execute([(int)$_SESSION['user_id']]);
        $tmpRow  = $tmpStmt->fetch();
        if ($tmpRow) {
            $myRole = $tmpRow['role'];
            $_SESSION['user_role'] = $myRole; // repair session for future requests
        }
    } catch (Exception $e) { /* fall through to 403 */ }
}

if (!in_array($myRole, ['admin', 'school-head', 'developer'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Forbidden.']);
    exit;
}

/* ── DB ── */
define('DB_HOST',    'localhost');
define('DB_NAME',    'lrmds');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET,
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('LRMDS users_handler DB: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'msg' => 'Database connection failed.']);
    exit;
}

/* ── Roles that require TOTP (must mirror signin_handler.php) ── */
const TOTP_ROLES    = ['teacher', 'school-head', 'developer', 'admin'];
const NO_TOTP_ROLES = ['guest', 'learner', 'parent'];

/* ── Ensure audit log table exists ── */
$pdo->exec("
  CREATE TABLE IF NOT EXISTS user_change_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_id   INT UNSIGNED NOT NULL,
    actor_id    INT UNSIGNED,
    actor_name  VARCHAR(120),
    actor_role  VARCHAR(40),
    action_type VARCHAR(60) NOT NULL,
    detail      TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target_id),
    INDEX idx_created (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/**
 * Write one row to user_change_log.
 */
function audit_log(PDO $pdo, int $targetId, string $action, string $detail, array $session): void {
    $actorId   = (int)($session['user_id']   ?? 0);
    $actorName = trim(($session['user_name'] ?? '') ?: '');
    $actorRole = $session['user_role'] ?? '';

    // If session name is blank but we have an actor ID, look it up from the DB
    if ($actorName === '' && $actorId > 0) {
        try {
            $s = $pdo->prepare('SELECT first_name, last_name FROM users WHERE id = ? LIMIT 1');
            $s->execute([$actorId]);
            $row = $s->fetch();
            if ($row) {
                $actorName = trim($row['first_name'] . ' ' . $row['last_name']);
            }
        } catch (Exception $e) { /* ignore — best effort */ }
    }

    try {
        $pdo->prepare("
            INSERT INTO user_change_log (target_id, actor_id, actor_name, actor_role, action_type, detail)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$targetId, $actorId ?: null, $actorName ?: null, $actorRole ?: null, $action, $detail]);
    } catch (Exception $e) {
        error_log('LRMDS audit_log: ' . $e->getMessage());
    }
}

/* ── Helpers ── */
function human_time(string $dt): string {
    if (!$dt) return 'Never';
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'Just now';
    if ($diff < 3600)   return round($diff / 60) . 'm ago';
    if ($diff < 86400)  return round($diff / 3600) . 'h ago';
    if ($diff < 604800) return round($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($dt));
}

function json_ok(mixed $data, int $count = 0): void {
    echo json_encode(['ok' => true, 'data' => $data, 'count' => $count]);
}
function json_err(string $msg, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'msg' => $msg]);
}

/**
 * If the new role does NOT require TOTP, wipe the secret so the user
 * isn't blocked by a stale 2FA enrollment on their next login.
 * Returns true if TOTP was cleared, false otherwise.
 */
function maybe_clear_totp(PDO $pdo, int $userId, string $newRole): bool {
    if (in_array($newRole, NO_TOTP_ROLES, true)) {
        $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")
            ->execute([$userId]);
        return true;
    }
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/* ════════════════════════════════════════════════
   GET ACTIONS
════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    /* ── list_pending ── */
    if ($action === 'list_pending') {
        $search = '%' . trim($_GET['search'] ?? '') . '%';
        $role   = trim($_GET['role'] ?? '');

        $sql = "SELECT id, email, first_name, last_name, role, region, division,
                       employee_id, meta, created_at
                FROM users
                WHERE status = 'pending'
                  AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
        $params = [$search, $search, $search, $search];

        if ($role) { $sql .= ' AND role = ?'; $params[] = $role; }
        $sql .= ' ORDER BY created_at ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['meta']             = $r['meta'] ? json_decode($r['meta'], true) : [];
            $r['created_at_human'] = human_time($r['created_at']);
        }
        echo json_encode(['ok' => true, 'data' => $rows, 'count' => count($rows)]);
        exit;
    }

    /* ── list_users ── */
    if ($action === 'list_users') {
        $search = '%' . trim($_GET['search'] ?? '') . '%';
        $role   = trim($_GET['role']   ?? '');
        $status = trim($_GET['status'] ?? '');
        $region = trim($_GET['region'] ?? '');

        $sql = "SELECT id, email, first_name, last_name, role, status, region,
                       division, employee_id, totp_enabled, created_at, last_login
                FROM users
                WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR employee_id LIKE ?)";
        $params = [$search, $search, $search, $search];

        if ($role)   { $sql .= ' AND role = ?';   $params[] = $role; }
        if ($status) { $sql .= ' AND status = ?'; $params[] = $status; }
        if ($region) { $sql .= ' AND region = ?'; $params[] = $region; }
        $sql .= ' ORDER BY created_at DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $r['created_at_human'] = human_time($r['created_at']);
            $r['last_login_human'] = $r['last_login'] ? human_time($r['last_login']) : 'Never';
        }
        echo json_encode(['ok' => true, 'data' => $rows, 'count' => count($rows)]);
        exit;
    }

    /* ── get_user ── */
    if ($action === 'get_user') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { json_err('Invalid user ID.'); exit; }

        $stmt = $pdo->prepare(
            "SELECT id, email, first_name, last_name, role, status, region,
                    division, employee_id, totp_enabled, totp_secret,
                    created_at, last_login, meta
             FROM users WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) { json_err('User not found.', 404); exit; }

        $user['meta']             = $user['meta'] ? json_decode($user['meta'], true) : [];
        $user['created_at_human'] = human_time($user['created_at']);
        $user['last_login_human'] = $user['last_login'] ? human_time($user['last_login']) : 'Never';
        // Never expose the actual secret to the browser
        unset($user['totp_secret']);

        echo json_encode(['ok' => true, 'data' => $user]);
        exit;
    }

    /* ── get_audit_log ── */
    if ($action === 'get_audit_log') {
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) { json_err('Invalid user ID.'); exit; }

        $stmt = $pdo->prepare("
            SELECT l.action_type, l.detail, l.actor_role, l.created_at,
                   COALESCE(NULLIF(TRIM(l.actor_name),''), CONCAT(u.first_name,' ',u.last_name), 'System') AS actor_name
            FROM   user_change_log l
            LEFT JOIN users u ON u.id = l.actor_id
            WHERE  l.target_id = ?
            ORDER  BY l.created_at DESC
            LIMIT  100
        ");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) {
            $r['created_at_human'] = human_time($r['created_at']);
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
        exit;
    }

    json_err('Unknown action.');
    exit;
}

/* ════════════════════════════════════════════════
   POST ACTIONS
════════════════════════════════════════════════ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);

    /* ── approve ── */
    if ($action === 'approve') {
        if (!$id) { json_err('Invalid ID.'); exit; }
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'")
            ->execute([$id]);
        audit_log($pdo, $id, 'approve', 'Account approved and activated.', $_SESSION);
        echo json_encode(['ok' => true, 'msg' => 'User approved.']);
        exit;
    }

    /* ── reject ── */
    if ($action === 'reject') {
        if (!$id) { json_err('Invalid ID.'); exit; }
        $pdo->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'")
            ->execute([$id]);
        echo json_encode(['ok' => true, 'msg' => 'Application rejected.']);
        exit;
    }

    /* ── suspend ── */
    if ($action === 'suspend') {
        if (!$id) { json_err('Invalid ID.'); exit; }
        $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")
            ->execute([$id]);
        audit_log($pdo, $id, 'suspend', 'Account suspended.', $_SESSION);
        echo json_encode(['ok' => true, 'msg' => 'User suspended.']);
        exit;
    }

    /* ── reactivate ── */
    if ($action === 'reactivate') {
        if (!$id) { json_err('Invalid ID.'); exit; }
        $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")
            ->execute([$id]);
        audit_log($pdo, $id, 'reactivate', 'Account reactivated.', $_SESSION);
        echo json_encode(['ok' => true, 'msg' => 'User reactivated.']);
        exit;
    }

    /* ── change_role ── */
    if ($action === 'change_role') {
        if (!$id) { json_err('Invalid ID.'); exit; }
        $allowed = ['teacher','learner','parent','school-head','developer','admin','guest'];
        $role    = $_POST['role'] ?? '';
        if (!in_array($role, $allowed, true)) { json_err('Invalid role.'); exit; }

        // Fetch old role so we can detect a downgrade
        $old = $pdo->prepare('SELECT role, totp_enabled FROM users WHERE id = ? LIMIT 1');
        $old->execute([$id]);
        $oldUser = $old->fetch();

        $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $id]);

        // Auto-clear TOTP if new role doesn't require it
        $totpCleared = maybe_clear_totp($pdo, $id, $role);

        $msg = "Role updated to {$role}.";
        if ($totpCleared && $oldUser && $oldUser['totp_enabled']) {
            $msg .= ' 2FA has been automatically cleared.';
        } elseif (in_array($role, TOTP_ROLES, true) && $oldUser && !in_array($oldUser['role'], TOTP_ROLES, true)) {
            $msg .= ' This role requires 2FA — the user will be prompted to enroll on next login.';
        }

        $oldLabel = $oldUser['role'] ?? '?';
        audit_log($pdo, $id, 'change_role', "Role changed from {$oldLabel} to {$role}." . ($totpCleared ? ' 2FA cleared automatically.' : ''), $_SESSION);
        echo json_encode(['ok' => true, 'msg' => $msg, 'totp_cleared' => $totpCleared]);
        exit;
    }

    /* ── edit_user ── */
    if ($action === 'edit_user') {
        if (!$id) { json_err('Invalid ID.'); exit; }

        /* ── Role-based permission check ── */
        $targetStmt = $pdo->prepare('SELECT id, role, first_name, last_name, email, status FROM users WHERE id = ? LIMIT 1');
        $targetStmt->execute([$id]);
        $targetUser = $targetStmt->fetch();

        if (!$targetUser) { json_err('User not found.'); exit; }

        /**
         * Hierarchy:
         *   admin        -> edit anyone
         *   developer    -> edit school-head, teacher, learner, parent, guest
         *                  (SDS / ASDS are at developer level)
         *   school-head  -> edit teacher, learner, parent, guest
         *   teacher/etc  -> no edit rights (blocked at auth guard above)
         */
        $editable_by = [
            'admin'       => ['admin', 'developer', 'school-head', 'teacher', 'learner', 'parent', 'guest'],
            'developer'   => ['school-head', 'teacher', 'learner', 'parent', 'guest'],
            'school-head' => ['teacher', 'learner', 'parent', 'guest'],
        ];

        $canEdit = in_array($targetUser['role'], $editable_by[$myRole] ?? [], true);

        if (!$canEdit) {
            json_err('You do not have permission to edit this user.'); exit;
        }

        $fname    = trim($_POST['first_name']  ?? '');
        $lname    = trim($_POST['last_name']   ?? '');
        $email    = trim($_POST['email']       ?? '');
        $role     = trim($_POST['role']        ?? '');
        $status   = trim($_POST['status']      ?? '');
        $region   = trim($_POST['region']      ?? '');
        $division = trim($_POST['division']    ?? '');
        $emp_id   = trim($_POST['employee_id'] ?? '');
        $new_pass = $_POST['new_password']     ?? '';

        // Meta JSON from JS (role-specific fields)
        $meta_raw  = $_POST['meta'] ?? null;
        $meta_json = null;
        if ($meta_raw) {
            $meta_decoded = json_decode($meta_raw, true);
            if (is_array($meta_decoded) && !empty($meta_decoded)) {
                $meta_json = json_encode($meta_decoded);
            }
        }

        if (!$fname || !$lname)  { json_err('First and last name are required.'); exit; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { json_err('Enter a valid email address.'); exit; }

        $allowed_roles    = ['teacher','learner','parent','school-head','developer','admin','guest'];
        $allowed_statuses = ['active','pending','suspended'];
        if (!in_array($role,   $allowed_roles,    true)) { json_err('Invalid role.'); exit; }
        if (!in_array($status, $allowed_statuses, true)) { json_err('Invalid status.'); exit; }

        // Determine which roles this actor is allowed to assign
        $assignable_roles_map = [
            'admin'       => ['teacher', 'learner', 'parent', 'school-head', 'developer', 'admin', 'guest'],
            'developer'   => ['school-head', 'teacher', 'learner', 'parent', 'guest'],
            'school-head' => ['teacher', 'learner', 'parent', 'guest'],
        ];
        $assignable = $assignable_roles_map[$myRole] ?? [];

        // school-head: role is locked — always keep the target's existing role
        if ($myRole === 'school-head') {
            $role = $targetUser['role'];
        }

        if (!in_array($role, $assignable, true) && $role !== $targetUser['role']) {
            json_err('You are not allowed to assign this role.'); exit;
        }

        if ($new_pass && strlen($new_pass) < 8) { json_err('Password must be at least 8 characters.'); exit; }

        $dup = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $dup->execute([$email, $id]);
        if ($dup->fetch()) { json_err('That email is already used by another account.'); exit; }

        if ((int)$id === (int)($_SESSION['user_id'] ?? 0) && $status === 'suspended') {
            json_err('You cannot suspend your own account.'); exit;
        }

        if ($new_pass) {
            $hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, email = ?,
                    role = ?, status = ?, region = ?,
                    division = ?, employee_id = ?, password_hash = ?,
                    meta = ?
                WHERE id = ?
            ")->execute([$fname, $lname, $email, $role, $status, $region, $division ?: null, $emp_id ?: null, $hash, $meta_json, $id]);
        } else {
            $pdo->prepare("
                UPDATE users
                SET first_name = ?, last_name = ?, email = ?,
                    role = ?, status = ?, region = ?,
                    division = ?, employee_id = ?,
                    meta = ?
                WHERE id = ?
            ")->execute([$fname, $lname, $email, $role, $status, $region, $division ?: null, $emp_id ?: null, $meta_json, $id]);
        }

        // Auto-clear TOTP if role was changed to a non-2FA role
        $totpCleared = maybe_clear_totp($pdo, $id, $role);

        $msg = "{$fname} {$lname}'s profile updated.";
        if ($totpCleared) {
            $msg .= ' 2FA was automatically cleared because this role does not require it.';
        } elseif (in_array($role, TOTP_ROLES, true) && !in_array($targetUser['role'], TOTP_ROLES, true)) {
            $msg .= ' This role requires 2FA — the user will be prompted to enroll on next login.';
        }

        // Build human-readable audit detail
        $changes = [];
        $oldName = trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? ''));
        $newName = trim("{$fname} {$lname}");
        if ($newName !== $oldName)             $changes[] = 'Name: "' . $oldName . '" -> "' . $newName . '"';
        if ($email !== $targetUser['email'])   $changes[] = 'Email: "' . $targetUser['email'] . '" -> "' . $email . '"';
        if ($role  !== $targetUser['role'])    $changes[] = 'Role: ' . $targetUser['role'] . ' -> ' . $role;
        if ($status !== $targetUser['status']) $changes[] = 'Status: ' . $targetUser['status'] . ' -> ' . $status;
        $auditDetail = empty($changes)
            ? "Profile reviewed (no changes)."
            : implode('; ', $changes) . '.';
        if ($totpCleared) { $auditDetail .= ' 2FA cleared automatically.'; }
        audit_log($pdo, $id, 'edit_user', $auditDetail, $_SESSION);

        echo json_encode(['ok' => true, 'msg' => $msg, 'totp_cleared' => $totpCleared]);
        exit;
    }

    /* ── disable_totp ── */
    if ($action === 'disable_totp') {
        if ($myRole !== 'admin') { json_err('Only admins can disable 2FA.'); exit; }
        if (!$id) { json_err('Invalid ID.'); exit; }
        $pdo->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0 WHERE id = ?")
            ->execute([$id]);
        audit_log($pdo, $id, 'disable_totp', 'Two-factor authentication manually disabled by admin.', $_SESSION);
        echo json_encode(['ok' => true, 'msg' => '2FA disabled. The user will need to re-enroll on next login.']);
        exit;
    }

    /* ── send_password_reset ── */
    if ($action === 'send_password_reset') {
        if ($myRole !== 'admin') { json_err('Only admins can send password reset emails.'); exit; }
        if (!$id) { json_err('Invalid ID.'); exit; }

        $stmt = $pdo->prepare('SELECT email, first_name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if (!$user) { json_err('User not found.'); exit; }

        error_log("LRMDS: Password reset requested for user #{$id} ({$user['email']}) by admin #{$_SESSION['user_id']}");

        echo json_encode([
            'ok'  => true,
            'msg' => "Reset email stub triggered for {$user['email']}. Wire up PHPMailer to send for real.",
        ]);
        exit;
    }

    json_err('Unknown action.');
    exit;
}

json_err('Method not allowed.', 405);