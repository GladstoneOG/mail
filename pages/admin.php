<?php
/**
 * Admin Panel
 */
auth_require_admin();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $targetId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    if ($action === 'create_user') {
        $newUser = isset($_POST['new_username']) ? trim($_POST['new_username']) : '';
        $newName = isset($_POST['new_display_name']) ? trim($_POST['new_display_name']) : '';
        $newPass = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $newRole = isset($_POST['new_role']) ? $_POST['new_role'] : 'user';
        if ($newUser && $newName && $newPass) {
            $result = auth_register($conn, $newUser, $newPass, $newName);
            if (isset($result['error'])) {
                $success = 'Error: ' . $result['error'];
            } else {
                if ($newRole === 'admin') {
                    db_execute($conn, "UPDATE mail_users SET role = 'admin' WHERE id = ?", array($result['id']));
                }
                $success = 'User "' . $newUser . '" created successfully.';
            }
        } else {
            $success = 'Error: All fields are required.';
        }
    } elseif ($targetId && $targetId !== auth_user_id()) {
        if ($action === 'toggle_active') {
            db_execute($conn, "UPDATE mail_users SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = ?", array($targetId));
            $success = 'User status updated.';
        } elseif ($action === 'make_admin') {
            db_execute($conn, "UPDATE mail_users SET role = 'admin' WHERE id = ?", array($targetId));
            $success = 'User promoted to admin.';
        } elseif ($action === 'remove_admin') {
            db_execute($conn, "UPDATE mail_users SET role = 'user' WHERE id = ?", array($targetId));
            $success = 'Admin rights removed.';
        } elseif ($action === 'reset_password') {
            $hash = password_hash('password123', PASSWORD_DEFAULT);
            db_execute($conn, "UPDATE mail_users SET password_hash = ? WHERE id = ?", array($hash, $targetId));
            $success = 'Password reset to: password123';
        }
    }
}

$users = db_fetch_all($conn, "SELECT * FROM mail_users ORDER BY created_at DESC");
$totalMessages = intval(db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_messages WHERE is_draft = 0"));
$totalUsers = count($users);
$activeUsers = 0;
foreach ($users as $u) { if ($u['is_active']) $activeUsers++; }
?>

<div class="page-header"><h2>&#x2699; Admin Panel</h2></div>

<?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

<div class="admin-stats">
    <div class="stat-card"><div class="stat-num"><?php echo $totalUsers; ?></div><div class="stat-label">Total Users</div></div>
    <div class="stat-card"><div class="stat-num"><?php echo $activeUsers; ?></div><div class="stat-label">Active Users</div></div>
    <div class="stat-card"><div class="stat-num"><?php echo $totalMessages; ?></div><div class="stat-label">Total Messages</div></div>
</div>

<div class="admin-section" style="margin-bottom:24px">
    <h3>Create New User</h3>
    <form method="POST" class="create-user-form">
        <input type="hidden" name="action" value="create_user">
        <div class="form-row">
            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="new_display_name" required placeholder="Full name">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="new_username" required placeholder="Login username">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="new_password" required placeholder="Initial password">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="new_role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group" style="align-self:flex-end">
                <button type="submit" class="btn btn-primary">+ Create User</button>
            </div>
        </div>
    </form>
</div>

<div class="admin-section">
    <h3>User Management</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Last Login</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar-xs" style="background:<?php echo get_avatar_color($u['display_name']); ?>"><?php echo e(get_initials($u['display_name'])); ?></div>
                            <?php echo e($u['display_name']); ?>
                        </div>
                    </td>
                    <td>@<?php echo e($u['username']); ?></td>
                    <td><span class="role-badge role-<?php echo e($u['role']); ?>"><?php echo e(ucfirst($u['role'])); ?></span></td>
                    <td><span class="status-dot <?php echo $u['is_active'] ? 'active' : 'inactive'; ?>"></span><?php echo $u['is_active'] ? 'Active' : 'Disabled'; ?></td>
                    <td><?php echo time_ago($u['created_at']); ?></td>
                    <td><?php echo $u['last_login'] ? time_ago($u['last_login']) : 'Never'; ?></td>
                    <td>
                        <?php if (intval($u['id']) !== auth_user_id()): ?>
                            <div class="action-btns">
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <button class="btn btn-xs <?php echo $u['is_active'] ? 'btn-warning' : 'btn-success'; ?>"><?php echo $u['is_active'] ? 'Disable' : 'Enable'; ?></button>
                                </form>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="<?php echo $u['role'] === 'admin' ? 'remove_admin' : 'make_admin'; ?>">
                                    <button class="btn btn-xs btn-secondary"><?php echo $u['role'] === 'admin' ? 'Remove Admin' : 'Make Admin'; ?></button>
                                </form>
                                <form method="POST" class="inline-form" onsubmit="return confirm('Reset password to password123?')">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <input type="hidden" name="action" value="reset_password">
                                    <button class="btn btn-xs btn-danger">Reset PW</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <span class="text-muted">You</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
