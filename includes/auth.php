<?php
/**
 * Authentication helpers for LAN Mail.
 * PHP 5.6 compatible.
 */

function auth_start_session() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_is_logged_in() {
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
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

function auth_login($conn, $username, $password) {
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
    return true;
}

function auth_logout() {
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
