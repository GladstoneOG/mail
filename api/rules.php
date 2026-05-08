<?php
/**
 * Rules API - CRUD inbox rules, execute rules on incoming messages
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
    if (!db_table_exists($conn, 'mail_rules')) {
        json_response(array('rules' => array()));
        break;
    }
    $rules = db_fetch_all($conn, "SELECT * FROM mail_rules WHERE user_id = ? ORDER BY sort_order, name", array($userId));
    foreach ($rules as &$r) {
        $r['conditions'] = json_decode($r['conditions'], true);
        $r['actions'] = json_decode($r['actions'], true);
    }
    json_response(array('rules' => $rules));
    break;

case 'create':
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $matchType = isset($_POST['match_type']) ? $_POST['match_type'] : 'all';
    $conditions = isset($_POST['conditions']) ? $_POST['conditions'] : '[]';
    $actions = isset($_POST['actions']) ? $_POST['actions'] : '[]';
    if (!$name) json_response(array('error' => 'Rule name is required'), 400);
    if (!in_array($matchType, array('all', 'any'))) $matchType = 'all';
    // Validate JSON
    $condArr = json_decode($conditions, true);
    $actArr = json_decode($actions, true);
    if (!is_array($condArr) || empty($condArr)) json_response(array('error' => 'At least one condition is required'), 400);
    if (!is_array($actArr) || empty($actArr)) json_response(array('error' => 'At least one action is required'), 400);
    $ruleId = db_insert_get_id($conn,
        "INSERT INTO mail_rules (user_id, name, match_type, conditions, actions) VALUES (?, ?, ?, ?, ?)",
        array($userId, $name, $matchType, $conditions, $actions));
    if (!$ruleId) json_response(array('error' => 'Failed to create rule'), 500);
    json_response(array('success' => true, 'id' => $ruleId));
    break;

case 'update':
    $ruleId = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
    if (!$ruleId) json_response(array('error' => 'Missing rule ID'), 400);
    $rule = db_fetch_one($conn, "SELECT id FROM mail_rules WHERE id = ? AND user_id = ?", array($ruleId, $userId));
    if (!$rule) json_response(array('error' => 'Rule not found'), 404);
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $matchType = isset($_POST['match_type']) ? $_POST['match_type'] : 'all';
    $conditions = isset($_POST['conditions']) ? $_POST['conditions'] : '[]';
    $actions = isset($_POST['actions']) ? $_POST['actions'] : '[]';
    $isActive = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    if (!in_array($matchType, array('all', 'any'))) $matchType = 'all';
    db_execute($conn,
        "UPDATE mail_rules SET name=?, match_type=?, conditions=?, actions=?, is_active=?, updated_at=GETDATE() WHERE id=?",
        array($name, $matchType, $conditions, $actions, $isActive, $ruleId));
    json_response(array('success' => true));
    break;

case 'delete':
    $ruleId = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
    if (!$ruleId) json_response(array('error' => 'Missing rule ID'), 400);
    $rule = db_fetch_one($conn, "SELECT id FROM mail_rules WHERE id = ? AND user_id = ?", array($ruleId, $userId));
    if (!$rule) json_response(array('error' => 'Rule not found'), 404);
    db_execute($conn, "DELETE FROM mail_rules WHERE id = ?", array($ruleId));
    json_response(array('success' => true));
    break;

case 'toggle':
    $ruleId = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
    if (!$ruleId) json_response(array('error' => 'Missing rule ID'), 400);
    $rule = db_fetch_one($conn, "SELECT id, is_active FROM mail_rules WHERE id = ? AND user_id = ?", array($ruleId, $userId));
    if (!$rule) json_response(array('error' => 'Rule not found'), 404);
    $newState = $rule['is_active'] ? 0 : 1;
    db_execute($conn, "UPDATE mail_rules SET is_active = ? WHERE id = ?", array($newState, $ruleId));
    json_response(array('success' => true, 'is_active' => $newState));
    break;

default:
    json_response(array('error' => 'Invalid action'), 400);
}
