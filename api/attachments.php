<?php
/**
 * Attachments API - download files
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_start_session();

if (!auth_is_logged_in()) { http_response_code(401); exit('Unauthorized'); }

$userId = auth_user_id();
$action = isset($_GET['action']) ? $_GET['action'] : '';
$attachId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (($action === 'download' || $action === 'preview') && $attachId) {
    $att = db_fetch_one($conn, "SELECT a.*, m.sender_id FROM mail_attachments a JOIN mail_messages m ON a.message_id = m.id WHERE a.id = ?", array($attachId));
    if (!$att) { http_response_code(404); exit('Not found'); }

    // Check access
    $isRecipient = db_fetch_one($conn, "SELECT id FROM mail_recipients WHERE message_id = ? AND recipient_id = ?", array($att['message_id'], $userId));
    $isSender = (intval($att['sender_id']) === $userId);
    if (!$isRecipient && !$isSender) { http_response_code(403); exit('Forbidden'); }

    $filePath = UPLOAD_DIR . $att['stored_name'];
    if (!file_exists($filePath)) { http_response_code(404); exit('File not found'); }

    header('Content-Type: ' . $att['mime_type']);
    $disposition = ($action === 'preview') ? 'inline' : 'attachment';
    header('Content-Disposition: ' . $disposition . '; filename="' . str_replace('"', '', $att['original_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache');
    readfile($filePath);
    exit;
}

http_response_code(400);
echo 'Invalid request';
