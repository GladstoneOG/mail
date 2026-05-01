<?php
/**
 * Contacts API
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_start_session();

if (!auth_is_logged_in()) json_response(array('error' => 'Not authenticated'), 401);

$userId = auth_user_id();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
case 'add':
    $contactUserId = isset($_POST['contact_user_id']) ? intval($_POST['contact_user_id']) : 0;
    if (!$contactUserId || $contactUserId === $userId) json_response(array('error' => 'Invalid user'), 400);

    // Check if exists
    $existing = db_fetch_one($conn, "SELECT id FROM mail_contacts WHERE owner_id = ? AND contact_user_id = ?", array($userId, $contactUserId));
    if ($existing) json_response(array('error' => 'Already in contacts'), 400);

    db_execute($conn, "INSERT INTO mail_contacts (owner_id, contact_user_id) VALUES (?, ?)", array($userId, $contactUserId));
    json_response(array('success' => true));
    break;

case 'remove':
    $contactId = isset($_POST['contact_id']) ? intval($_POST['contact_id']) : 0;
    db_execute($conn, "DELETE FROM mail_contacts WHERE id = ? AND owner_id = ?", array($contactId, $userId));
    json_response(array('success' => true));
    break;

default:
    json_response(array('error' => 'Invalid action'), 400);
}
