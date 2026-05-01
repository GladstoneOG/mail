<?php
/**
 * Users API - search/autocomplete
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_start_session();

if (!auth_is_logged_in()) json_response(array('error' => 'Not authenticated'), 401);

$userId = auth_user_id();
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'search') {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    if (strlen($q) < 1) json_response(array('users' => array()));

    $like = '%' . $q . '%';
    $users = db_fetch_all($conn,
        "SELECT TOP 10 id, username, display_name FROM mail_users
         WHERE is_active = 1 AND id != ? AND (username LIKE ? OR display_name LIKE ?)
         ORDER BY display_name",
        array($userId, $like, $like));

    json_response(array('users' => $users));
} else {
    json_response(array('error' => 'Invalid action'), 400);
}
