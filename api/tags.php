<?php
/**
 * Tags API - CRUD tags, assign/remove tags from messages
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

case 'list':
    $tags = db_fetch_all($conn, "SELECT * FROM mail_tags WHERE user_id = ? ORDER BY name", array($userId));
    json_response(array('tags' => $tags));
    break;

case 'create':
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '#6366f1';
    if (!$name) json_response(array('error' => 'Tag name is required'), 400);
    if (strlen($name) > 50) json_response(array('error' => 'Tag name too long'), 400);
    $existing = db_fetch_one($conn, "SELECT id FROM mail_tags WHERE user_id = ? AND name = ?", array($userId, $name));
    if ($existing) json_response(array('error' => 'Tag already exists'), 400);
    $tagId = db_insert_get_id($conn, "INSERT INTO mail_tags (user_id, name, color) VALUES (?, ?, ?)", array($userId, $name, $color));
    if (!$tagId) json_response(array('error' => 'Failed to create tag'), 500);
    json_response(array('success' => true, 'id' => $tagId));
    break;

case 'update':
    $tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $color = isset($_POST['color']) ? trim($_POST['color']) : '';
    if (!$tagId) json_response(array('error' => 'Missing tag ID'), 400);
    $tag = db_fetch_one($conn, "SELECT id FROM mail_tags WHERE id = ? AND user_id = ?", array($tagId, $userId));
    if (!$tag) json_response(array('error' => 'Tag not found'), 404);
    if ($name) db_execute($conn, "UPDATE mail_tags SET name = ? WHERE id = ?", array($name, $tagId));
    if ($color) db_execute($conn, "UPDATE mail_tags SET color = ? WHERE id = ?", array($color, $tagId));
    json_response(array('success' => true));
    break;

case 'delete':
    $tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    if (!$tagId) json_response(array('error' => 'Missing tag ID'), 400);
    $tag = db_fetch_one($conn, "SELECT id FROM mail_tags WHERE id = ? AND user_id = ?", array($tagId, $userId));
    if (!$tag) json_response(array('error' => 'Tag not found'), 404);
    db_execute($conn, "DELETE FROM mail_tags WHERE id = ?", array($tagId));
    json_response(array('success' => true));
    break;

case 'assign':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    if (!$msgId || !$tagId) json_response(array('error' => 'Missing data'), 400);
    $tag = db_fetch_one($conn, "SELECT id FROM mail_tags WHERE id = ? AND user_id = ?", array($tagId, $userId));
    if (!$tag) json_response(array('error' => 'Tag not found'), 404);
    $exists = db_fetch_one($conn, "SELECT id FROM mail_message_tags WHERE message_id = ? AND tag_id = ? AND user_id = ?", array($msgId, $tagId, $userId));
    if (!$exists) {
        db_execute($conn, "INSERT INTO mail_message_tags (message_id, tag_id, user_id) VALUES (?, ?, ?)", array($msgId, $tagId, $userId));
    }
    json_response(array('success' => true));
    break;

case 'unassign':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $tagId = isset($_POST['tag_id']) ? intval($_POST['tag_id']) : 0;
    if (!$msgId || !$tagId) json_response(array('error' => 'Missing data'), 400);
    db_execute($conn, "DELETE FROM mail_message_tags WHERE message_id = ? AND tag_id = ? AND user_id = ?", array($msgId, $tagId, $userId));
    json_response(array('success' => true));
    break;

case 'get_message_tags':
    $msgId = isset($_GET['message_id']) ? intval($_GET['message_id']) : 0;
    if (!$msgId) json_response(array('error' => 'Missing message ID'), 400);
    $tags = db_fetch_all($conn,
        "SELECT t.* FROM mail_tags t JOIN mail_message_tags mt ON t.id = mt.tag_id WHERE mt.message_id = ? AND mt.user_id = ? ORDER BY t.name",
        array($msgId, $userId));
    json_response(array('tags' => $tags));
    break;

default:
    json_response(array('error' => 'Invalid action'), 400);
}
