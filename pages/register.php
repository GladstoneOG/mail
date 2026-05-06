<?php
/**
 * Registration Page
 */
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $displayName = isset($_POST['display_name']) ? trim($_POST['display_name']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (!$username || !$displayName || !$password) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters.';
    } elseif (strlen($password) < 4) {
        $error = 'Password must be at least 4 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, dots, hyphens, and underscores.';
    } else {
        $result = auth_register($conn, $username, $password, $displayName);
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            // Auto-login after registration
            auth_login($conn, $username, $password);
            redirect('index.php?page=inbox');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo"><img src="assets/header_icon.png" alt="Logo" style="height:60px; width:auto;"></div>
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="auth-subtitle">Create your account</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" id="display_name" name="display_name" required autofocus
                           value="<?php echo e(isset($_POST['display_name']) ? $_POST['display_name'] : ''); ?>"
                           placeholder="Your full name">
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required
                           value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>"
                           placeholder="Choose a username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Choose a password">
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Confirm your password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Create Account</button>
            </form>

            <div class="auth-footer">
                Already have an account? <a href="index.php?page=login">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>
