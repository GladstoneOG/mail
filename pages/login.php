<?php
/**
 * Login Page
 */
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($username && $password) {
        if (auth_login($conn, $username, $password)) {
            redirect('index.php?page=inbox');
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/icon.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">&#x1F4E8;</div>
                <h1><?php echo e(APP_NAME); ?></h1>
                <p class="auth-subtitle">by Johan</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="auth-form">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus
                           value="<?php echo e(isset($_POST['username']) ? $_POST['username'] : ''); ?>"
                           placeholder="Enter your username">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required
                           placeholder="Enter your password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="auth-footer">
                <?php if (defined('ALLOW_SELF_REGISTRATION') && ALLOW_SELF_REGISTRATION): ?>
                    Don't have an account? <a href="index.php?page=register">Create one</a>
                <?php else: ?>
                    Contact your administrator to get an account.
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
