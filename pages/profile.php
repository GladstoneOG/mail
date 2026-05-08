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
        $emailFooter = isset($_POST['email_footer']) ? $_POST['email_footer'] : '';
        if ($displayName) {
            db_execute($conn, "UPDATE mail_users SET display_name = ?, email_footer = ? WHERE id = ?", array($displayName, $emailFooter, $userId));
            $_SESSION['user']['display_name'] = $displayName;
            $success = 'Profile updated.';
            $user['display_name'] = $displayName;
            $user['email_footer'] = $emailFooter;
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
        <?php if (false && !empty($user['email_footer'])): ?>
            <div style="margin-top:20px;text-align:left">
                <strong style="color:var(--text3);font-size:12px;text-transform:uppercase">Footer Preview:</strong>
                <div class="email-footer-preview"><?php echo strip_tags($user['email_footer'], '<b><i><u><br><a><span><div>'); ?></div>
            </div>
        <?php endif; ?>
    </div>

    <div class="settings-card">
        <h3>Update Profile</h3>
        <form method="POST" id="profile-form">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label>Display Name</label>
                <input type="text" name="display_name" value="<?php echo e($user['display_name']); ?>" required>
            </div>
            
            <div class="form-group" style="margin-top:16px; display:none;">
                <label style="display:block;margin-bottom:8px;font-weight:600;color:var(--text)">Email Footer / Signature</label>
                <div class="editor-toolbar" id="footer-editor-toolbar">
                    <button type="button" onclick="execCmd('bold')" title="Bold" class="tb-btn"><b>B</b></button>
                    <button type="button" onclick="execCmd('italic')" title="Italic" class="tb-btn"><i>I</i></button>
                    <button type="button" onclick="execCmd('underline')" title="Underline" class="tb-btn"><u>U</u></button>
                    <span class="toolbar-sep"></span>
                    <select onchange="execCmd('fontSize',this.value);this.selectedIndex=0;" title="Font Size" class="font-size-sel">
                        <option value="">Size</option>
                        <option value="1">Small</option>
                        <option value="3">Normal</option>
                        <option value="5">Large</option>
                        <option value="7">Huge</option>
                    </select>
                    <input type="color" value="#e2e8f0" onchange="execCmd('foreColor',this.value)" title="Text Color" class="color-pick">
                    <span class="toolbar-sep"></span>
                    <button type="button" onclick="execCmd('justifyLeft')" title="Align Left" class="tb-btn tb-active">&#x2190;</button>
                    <button type="button" onclick="execCmd('justifyCenter')" title="Center" class="tb-btn">&#x2194;</button>
                    <button type="button" onclick="execCmd('justifyRight')" title="Align Right" class="tb-btn">&#x2192;</button>
                    <span class="toolbar-sep"></span>
                    <button type="button" onclick="execCmd('insertUnorderedList')" title="Bullet List" class="tb-btn">&#x2022;</button>
                    <button type="button" onclick="execCmd('insertOrderedList')" title="Numbered List" class="tb-btn">1.</button>
                </div>
                <div class="rich-editor" contenteditable="true" id="footer-editor" style="min-height:100px;"><?php echo isset($user['email_footer']) ? $user['email_footer'] : ''; ?></div>
                <input type="hidden" name="email_footer" id="email_footer_hidden">
            </div>

            <button type="button" class="btn btn-primary" onclick="submitProfile()" style="margin-top:16px">Save Changes</button>
        </form>
        <script>
        function execCmd(command, value) {
            document.execCommand(command, false, value || null);
        }
        function submitProfile(){
            document.getElementById('email_footer_hidden').value = document.getElementById('footer-editor').innerHTML;
            document.getElementById('profile-form').submit();
        }
        </script>

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
