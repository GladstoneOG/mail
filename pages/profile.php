<?php
/**
 * Profile Page
 */
$userId = auth_user_id();
$user = db_fetch_one($conn, "SELECT * FROM mail_users WHERE id = ?", array($userId));
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_profile') {
        $displayName = trim(isset($_POST['display_name']) ? $_POST['display_name'] : '');
        if ($displayName) {
            db_execute($conn, "UPDATE mail_users SET display_name = ? WHERE id = ?", array($displayName, $userId));
            $_SESSION['user']['display_name'] = $displayName;
            $success = 'Profile updated.';
            $user['display_name'] = $displayName;
        }
    } elseif ($action === 'change_password') {
        $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
        $newPass = isset($_POST['new_password']) ? $_POST['new_password'] : '';
        $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 4) {
            $error = 'New password must be at least 4 characters.';
        } elseif ($newPass !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            db_execute($conn, "UPDATE mail_users SET password_hash = ? WHERE id = ?", array($hash, $userId));
            $success = 'Password changed successfully.';
        }
    }
}

$sentCount = intval(db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_messages WHERE sender_id = ? AND is_draft = 0", array($userId)));
$receivedCount = intval(db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_recipients WHERE recipient_id = ?", array($userId)));
?>

<div class="page-header"><h2>Profile</h2></div>

<?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>

<div class="profile-grid">
    <div class="profile-card">
        <div class="profile-avatar" style="background:<?php echo get_avatar_color($user['display_name']); ?>">
            <?php echo e(get_initials($user['display_name'])); ?>
        </div>
        <h3><?php echo e($user['display_name']); ?></h3>
        <p class="text-muted">@<?php echo e($user['username']); ?> &middot; <?php echo e(ucfirst($user['role'])); ?></p>
        <div class="profile-stats">
            <div class="stat"><span class="stat-num"><?php echo $sentCount; ?></span><span class="stat-label">Sent</span></div>
            <div class="stat"><span class="stat-num"><?php echo $receivedCount; ?></span><span class="stat-label">Received</span></div>
        </div>
    </div>

    <div class="settings-card">
        <h3>Update Profile</h3>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="display_name" value="<?php echo e($user['display_name']); ?>" required>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>

        <hr class="divider">

        <h3>Change Password</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="form-group">
                <label>Current Password</label>
                <input type="password" name="current_password" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn btn-primary">Change Password</button>
        </form>
    </div>
</div>
