<?php
/**
 * Authentication helpers for LAN Mail.
 * PHP 5.6 compatible.
 */

function auth_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Use a unique session name to prevent conflicts with other apps on the same server
        session_name('RSPIK_INBOX_SID');
        // Determine the base path for the cookie (e.g. /development/INBOX/)
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        // Normalize: ensure trailing slash
        $cookiePath = rtrim($scriptDir, '/') . '/';
        session_set_cookie_params(0, $cookiePath);
        session_start();
        // Validate session belongs to this app (extra safety)
        if (!isset($_SESSION['_app_id'])) {
            $_SESSION['_app_id'] = 'rspik_inbox';
        } elseif ($_SESSION['_app_id'] !== 'rspik_inbox') {
            // Session hijacked from another app - destroy and restart
            session_destroy();
            session_start();
            $_SESSION['_app_id'] = 'rspik_inbox';
        }
    }
}

function auth_is_logged_in() {
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        return true;
    }
    // Try remember-me cookie auto-login
    return auth_try_remember_login();
}

function auth_user_id() {
    return isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
}

function auth_user() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function auth_is_admin() {
    $user = auth_user();
    return $user && isset($user['role']) && $user['role'] === 'admin';
}

function auth_require_login() {
    if (!auth_is_logged_in()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function auth_require_admin() {
    auth_require_login();
    if (!auth_is_admin()) {
        header('Location: index.php?page=inbox&error=access_denied');
        exit;
    }
}

function auth_login($conn, $username, $password, $rememberMe = false) {
    $sql = "SELECT * FROM mail_users WHERE username = ? AND is_active = 1";
    $user = db_fetch_one($conn, $sql, array($username));
    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    // Update last login
    db_execute($conn, "UPDATE mail_users SET last_login = GETDATE() WHERE id = ?", array($user['id']));

    // Set session
    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user'] = array(
        'id' => intval($user['id']),
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role']
    );

    // Handle remember me
    if ($rememberMe) {
        auth_create_remember_token($conn, intval($user['id']));
    }

    return true;
}

function auth_logout() {
    // Clear remember-me token if present
    if (isset($_COOKIE['rspik_remember'])) {
        global $conn;
        if (!$conn) {
            require_once __DIR__ . '/../koneksi.php';
            require_once __DIR__ . '/db.php';
        }
        $parts = explode(':', $_COOKIE['rspik_remember'], 2);
        if (count($parts) === 2) {
            $userId = intval($parts[0]);
            $tokenHash = hash('sha256', $parts[1]);
            if ($conn) {
                db_execute($conn, "DELETE FROM mail_remember_tokens WHERE user_id = ? AND token_hash = ?", array($userId, $tokenHash));
            }
        }
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $cookiePath = rtrim($scriptDir, '/') . '/';
        setcookie('rspik_remember', '', time() - 42000, $cookiePath);
    }

    $_SESSION = array();
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function auth_register($conn, $username, $password, $displayName) {
    // Check if username exists
    $existing = db_fetch_one($conn, "SELECT id FROM mail_users WHERE username = ?", array($username));
    if ($existing) return array('error' => 'Username already taken');

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // First user becomes admin
    $count = db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_users");
    $role = (intval($count) === 0) ? 'admin' : 'user';

    $sql = "INSERT INTO mail_users (username, display_name, password_hash, role, is_active, created_at)
            VALUES (?, ?, ?, ?, 1, GETDATE())";
    $id = db_insert_get_id($conn, $sql, array($username, $displayName, $hash, $role));
    if (!$id) return array('error' => 'Registration failed');

    return array('success' => true, 'id' => $id, 'role' => $role);
}

/**
 * Create a remember-me token and set the cookie.
 */
function auth_create_remember_token($conn, $userId) {
    $token = bin2hex(openssl_random_pseudo_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', time() + 30 * 24 * 3600); // 30 days

    // Clean up old tokens for this user (keep max 5)
    db_execute($conn, "DELETE FROM mail_remember_tokens WHERE user_id = ? AND expires_at < GETDATE()", array($userId));
    $tokenCount = intval(db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_remember_tokens WHERE user_id = ?", array($userId)));
    if ($tokenCount >= 5) {
        db_execute($conn, "DELETE FROM mail_remember_tokens WHERE id = (SELECT TOP 1 id FROM mail_remember_tokens WHERE user_id = ? ORDER BY created_at ASC)", array($userId));
    }

    db_execute($conn, "INSERT INTO mail_remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)",
        array($userId, $tokenHash, $expiresAt));

    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $cookiePath = rtrim($scriptDir, '/') . '/';
    setcookie('rspik_remember', $userId . ':' . $token, time() + 30 * 24 * 3600, $cookiePath, '', false, true);
}

/**
 * Try to auto-login using the remember-me cookie.
 * Returns true if successful, false otherwise.
 */
function auth_try_remember_login() {
    static $tried = false;
    if ($tried) return false;
    $tried = true;

    if (!isset($_COOKIE['rspik_remember'])) return false;

    $parts = explode(':', $_COOKIE['rspik_remember'], 2);
    if (count($parts) !== 2) return false;

    $userId = intval($parts[0]);
    $token = $parts[1];
    if ($userId <= 0 || !$token) return false;

    $tokenHash = hash('sha256', $token);

    global $conn;
    if (!$conn) return false;

    // Check if tokens table exists
    if (!db_table_exists($conn, 'mail_remember_tokens')) return false;

    // Find valid token
    $row = db_fetch_one($conn, "SELECT id FROM mail_remember_tokens WHERE user_id = ? AND token_hash = ? AND expires_at > GETDATE()",
        array($userId, $tokenHash));
    if (!$row) {
        // Invalid token - clear cookie
        $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
        $cookiePath = rtrim($scriptDir, '/') . '/';
        setcookie('rspik_remember', '', time() - 42000, $cookiePath);
        return false;
    }

    // Token valid - load user and set session
    $user = db_fetch_one($conn, "SELECT * FROM mail_users WHERE id = ? AND is_active = 1", array($userId));
    if (!$user) return false;

    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user'] = array(
        'id' => intval($user['id']),
        'username' => $user['username'],
        'display_name' => $user['display_name'],
        'role' => $user['role']
    );

    // Rotate token for security
    db_execute($conn, "DELETE FROM mail_remember_tokens WHERE id = ?", array($row['id']));
    auth_create_remember_token($conn, intval($user['id']));

    // Update last login
    db_execute($conn, "UPDATE mail_users SET last_login = GETDATE() WHERE id = ?", array($user['id']));

    return true;
}
