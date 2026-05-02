<?php
/**
 * Messages API - send, delete, star, read, retract, forward attachments
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

case 'send':
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';
    $cc = isset($_POST['cc']) ? trim($_POST['cc']) : '';
    $bcc = isset($_POST['bcc']) ? trim($_POST['bcc']) : '';
    $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '(No Subject)';
    $body = isset($_POST['body']) ? $_POST['body'] : '';
    $draftId = isset($_POST['draft_id']) ? intval($_POST['draft_id']) : 0;
    $isDraft = isset($_POST['is_draft']) ? intval($_POST['is_draft']) : 0;
    $forwardAttachments = isset($_POST['forward_attachments']) ? trim($_POST['forward_attachments']) : '';

    if (!$subject) $subject = '(No Subject)';
    $toUsers = parse_recipients($conn, $to);
    $ccUsers = parse_recipients($conn, $cc);
    $bccUsers = parse_recipients($conn, $bcc);

    if (empty($toUsers) && !$isDraft) {
        json_response(array('error' => 'No valid recipients found.'), 400);
    }

    // Delete old draft if editing
    if ($draftId > 0) {
        $existingDraft = db_fetch_one($conn, "SELECT id FROM mail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1", array($draftId, $userId));
        if ($existingDraft) {
            db_execute($conn, "DELETE FROM mail_recipients WHERE message_id = ?", array($draftId));
            db_execute($conn, "DELETE FROM mail_attachments WHERE message_id = ?", array($draftId));
            db_execute($conn, "DELETE FROM mail_messages WHERE id = ?", array($draftId));
        }
    }

    $hasAttachments = (!empty($_FILES['attachments']) && $_FILES['attachments']['error'][0] !== UPLOAD_ERR_NO_FILE) ? 1 : 0;
    if ($forwardAttachments) $hasAttachments = 1;

    $sql = "INSERT INTO mail_messages (sender_id, subject, body, is_draft, has_attachments, sender_deleted, created_at, sent_at)
            VALUES (?, ?, ?, ?, ?, 0, GETDATE(), " . ($isDraft ? "NULL" : "GETDATE()") . ")";
    $msgId = db_insert_get_id($conn, $sql, array($userId, $subject, $body, $isDraft, $hasAttachments));
    if (!$msgId) json_response(array('error' => 'Failed to create message'), 500);

    // Insert recipients
    $allRecipients = array();
    foreach ($toUsers as $uid) $allRecipients[] = array($uid, 'to');
    foreach ($ccUsers as $uid) $allRecipients[] = array($uid, 'cc');
    foreach ($bccUsers as $uid) $allRecipients[] = array($uid, 'bcc');
    foreach ($allRecipients as $r) {
        db_execute($conn, "INSERT INTO mail_recipients (message_id, recipient_id, recipient_type) VALUES (?, ?, ?)",
                   array($msgId, $r[0], $r[1]));
    }

    // Handle new file uploads (no size limit)
    if (!empty($_FILES['attachments']) && $_FILES['attachments']['error'][0] !== UPLOAD_ERR_NO_FILE) {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
        $fileCount = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $origName = $_FILES['attachments']['name'][$i];
            $storedName = $msgId . '_' . $i . '_' . time() . '_' . sanitize_filename($origName);
            $destPath = UPLOAD_DIR . $storedName;
            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $destPath)) {
                $mime = $_FILES['attachments']['type'][$i] ? $_FILES['attachments']['type'][$i] : 'application/octet-stream';
                db_execute($conn,
                    "INSERT INTO mail_attachments (message_id, original_name, stored_name, file_size, mime_type) VALUES (?, ?, ?, ?, ?)",
                    array($msgId, $origName, $storedName, $_FILES['attachments']['size'][$i], $mime));
            }
        }
    }

    // Copy forwarded attachments (#4)
    if ($forwardAttachments) {
        $fwdIds = array_map('intval', explode(',', $forwardAttachments));
        foreach ($fwdIds as $fwdAttId) {
            $origAtt = db_fetch_one($conn, "SELECT * FROM mail_attachments WHERE id = ?", array($fwdAttId));
            if (!$origAtt) continue;
            $srcPath = UPLOAD_DIR . $origAtt['stored_name'];
            $newStored = $msgId . '_fwd_' . time() . '_' . sanitize_filename($origAtt['original_name']);
            $dstPath = UPLOAD_DIR . $newStored;
            if (file_exists($srcPath)) {
                copy($srcPath, $dstPath);
                db_execute($conn,
                    "INSERT INTO mail_attachments (message_id, original_name, stored_name, file_size, mime_type) VALUES (?, ?, ?, ?, ?)",
                    array($msgId, $origAtt['original_name'], $newStored, $origAtt['file_size'], $origAtt['mime_type']));
            }
        }
    }

    json_response(array('success' => true, 'id' => $msgId, 'is_draft' => $isDraft));
    break;

case 'delete':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if (!$msgId) json_response(array('error' => 'Missing message ID'), 400);
    db_execute($conn, "UPDATE mail_recipients SET is_deleted = 1, deleted_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
        array($msgId, $userId));
    db_execute($conn, "UPDATE mail_messages SET sender_deleted = 1 WHERE id = ? AND sender_id = ?",
        array($msgId, $userId));
    json_response(array('success' => true));
    break;

case 'restore':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    db_execute($conn, "UPDATE mail_recipients SET is_deleted = 0, deleted_at = NULL WHERE message_id = ? AND recipient_id = ?",
        array($msgId, $userId));
    json_response(array('success' => true));
    break;

case 'empty_trash':
    db_execute($conn, "DELETE FROM mail_recipients WHERE recipient_id = ? AND is_deleted = 1", array($userId));
    json_response(array('success' => true));
    break;

case 'star':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    db_execute($conn, "UPDATE mail_recipients SET is_starred = CASE WHEN is_starred = 1 THEN 0 ELSE 1 END WHERE message_id = ? AND recipient_id = ?",
        array($msgId, $userId));
    $starred = db_fetch_scalar($conn, "SELECT is_starred FROM mail_recipients WHERE message_id = ? AND recipient_id = ?",
        array($msgId, $userId));
    json_response(array('success' => true, 'starred' => intval($starred)));
    break;

case 'mark_read':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
        array($msgId, $userId));
    json_response(array('success' => true));
    break;

case 'check_new':
    $count = get_unread_count($conn, $userId);
    $latestSubj = '';
    if ($count > 0) {
        $latestMsg = db_fetch_one($conn, "SELECT m.subject FROM mail_recipients mr JOIN mail_messages m ON mr.message_id = m.id WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.is_deleted = 0 AND m.is_draft = 0 ORDER BY m.sent_at DESC", array($userId));
        if ($latestMsg) $latestSubj = $latestMsg['subject'];
    }
    json_response(array('unread' => $count, 'latest_subject' => $latestSubj));
    break;

case 'notif_list':
    $unread = get_unread_count($conn, $userId);
    $recent = db_fetch_all($conn,
        "SELECT m.id, m.subject, u.display_name AS sender_name, m.sent_at
         FROM mail_recipients mr
         JOIN mail_messages m ON mr.message_id = m.id
         JOIN mail_users u ON m.sender_id = u.id
         WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.is_deleted = 0 AND m.is_draft = 0
         ORDER BY m.sent_at DESC
         OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
        array($userId));
    $items = array();
    foreach ($recent as $r) {
        $items[] = array(
            'id' => $r['id'],
            'subject' => truncate_text($r['subject'], 40),
            'sender_name' => $r['sender_name'],
            'initials' => get_initials($r['sender_name']),
            'color' => get_avatar_color($r['sender_name']),
            'time_ago' => time_ago($r['sent_at'])
        );
    }
    json_response(array('items' => $items, 'unread_count' => $unread));
    break;

case 'delete_draft':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    $draft = db_fetch_one($conn, "SELECT id FROM mail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1", array($msgId, $userId));
    if ($draft) {
        db_execute($conn, "DELETE FROM mail_recipients WHERE message_id = ?", array($msgId));
        db_execute($conn, "DELETE FROM mail_attachments WHERE message_id = ?", array($msgId));
        db_execute($conn, "DELETE FROM mail_messages WHERE id = ?", array($msgId));
    }
    json_response(array('success' => true));
    break;

case 'retract':
    $msgId = isset($_POST['message_id']) ? intval($_POST['message_id']) : 0;
    if (!$msgId) json_response(array('error' => 'Missing message ID'), 400);
    // Only sender can retract
    $msg = db_fetch_one($conn, "SELECT id, sender_id FROM mail_messages WHERE id = ?", array($msgId));
    if (!$msg || intval($msg['sender_id']) !== $userId) {
        json_response(array('error' => 'Not authorized'), 403);
    }
    db_execute($conn, "UPDATE mail_messages SET is_retracted = 1, body = '', subject = '[Message Retracted]' WHERE id = ? AND sender_id = ?",
        array($msgId, $userId));
    // Delete attachment files
    $atts = db_fetch_all($conn, "SELECT stored_name FROM mail_attachments WHERE message_id = ?", array($msgId));
    foreach ($atts as $a) {
        $path = UPLOAD_DIR . $a['stored_name'];
        if (file_exists($path)) @unlink($path);
    }
    db_execute($conn, "DELETE FROM mail_attachments WHERE message_id = ?", array($msgId));
    json_response(array('success' => true));
    break;

case 'mark_all_read':
    $page = isset($_POST['page']) ? $_POST['page'] : 'inbox';
    if ($page === 'inbox') {
        db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE recipient_id = ? AND is_read = 0 AND is_deleted = 0 AND message_id IN (SELECT id FROM mail_messages WHERE is_draft = 0)", array($userId));
    } elseif ($page === 'starred') {
        db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE recipient_id = ? AND is_read = 0 AND is_starred = 1 AND is_deleted = 0", array($userId));
    }
    json_response(array('success' => true));
    break;

case 'batch_delete':
    $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
    if (!$ids) json_response(array('error' => 'No messages selected'), 400);
    $idArr = array_map('intval', explode(',', $ids));
    foreach ($idArr as $mid) {
        if ($mid <= 0) continue;
        db_execute($conn, "UPDATE mail_recipients SET is_deleted = 1, deleted_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
            array($mid, $userId));
        db_execute($conn, "UPDATE mail_messages SET sender_deleted = 1 WHERE id = ? AND sender_id = ?",
            array($mid, $userId));
    }
    json_response(array('success' => true));
    break;

case 'batch_permanent_delete':
    $ids = isset($_POST['ids']) ? $_POST['ids'] : '';
    if (!$ids) json_response(array('error' => 'No messages selected'), 400);
    $idArr = array_map('intval', explode(',', $ids));
    foreach ($idArr as $mid) {
        if ($mid <= 0) continue;
        db_execute($conn, "DELETE FROM mail_recipients WHERE message_id = ? AND recipient_id = ? AND is_deleted = 1",
            array($mid, $userId));
    }
    json_response(array('success' => true));
    break;

default:
    json_response(array('error' => 'Invalid action'), 400);
}

function parse_recipients($conn, $str) {
    if (!$str) return array();
    $names = array_map('trim', explode(',', $str));
    $ids = array();
    foreach ($names as $name) {
        if (!$name) continue;
        $user = db_fetch_one($conn, "SELECT id FROM mail_users WHERE username = ? AND is_active = 1", array($name));
        if ($user) $ids[] = intval($user['id']);
    }
    return array_unique($ids);
}
