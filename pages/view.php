<?php
/**
 * View Message Page - with retract support and HTML body rendering
 */
$userId = auth_user_id();
$msgId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$msgId) redirect('index.php?page=inbox');

$msg = db_fetch_one($conn,
    "SELECT m.*, u.display_name AS sender_name, u.username AS sender_username
     FROM mail_messages m JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?",
    array($msgId));
if (!$msg) redirect('index.php?page=inbox');

// Check access
$recipRow = db_fetch_one($conn,
    "SELECT id, is_starred, is_read FROM mail_recipients WHERE message_id = ? AND recipient_id = ?",
    array($msgId, $userId));
$isSender = (intval($msg['sender_id']) === $userId);
if (!$recipRow && !$isSender) redirect('index.php?page=inbox');

// Check if retracted
$isRetracted = isset($msg['is_retracted']) && $msg['is_retracted'];

// Mark as read + auto-cleanup attachments
if ($recipRow && !$recipRow['is_read']) {
    db_execute($conn, "UPDATE mail_recipients SET is_read = 1, read_at = GETDATE() WHERE message_id = ? AND recipient_id = ?",
               array($msgId, $userId));
    // Check if ALL recipients have now read — if so, delete physical attachment files
    $unreadLeft = intval(db_fetch_scalar($conn,
        "SELECT COUNT(*) FROM mail_recipients WHERE message_id = ? AND is_read = 0", array($msgId)));
    if ($unreadLeft === 0 && $msg['has_attachments']) {
        $atts = db_fetch_all($conn, "SELECT stored_name FROM mail_attachments WHERE message_id = ?", array($msgId));
        foreach ($atts as $a) {
            $path = UPLOAD_DIR . $a['stored_name'];
            if (file_exists($path)) @unlink($path);
        }
    }
}

// Get recipients
$recipients = db_fetch_all($conn,
    "SELECT u.display_name, u.username, mr.recipient_type
     FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
     WHERE mr.message_id = ? ORDER BY mr.recipient_type", array($msgId));
$toList = array(); $ccList = array();
foreach ($recipients as $r) {
    if ($r['recipient_type'] === 'cc') $ccList[] = $r;
    elseif ($r['recipient_type'] !== 'bcc') $toList[] = $r;
}

$attachments = db_fetch_all($conn, "SELECT * FROM mail_attachments WHERE message_id = ?", array($msgId));
$starred = $recipRow ? $recipRow['is_starred'] : false;
$backPage = isset($_GET['from']) ? $_GET['from'] : 'inbox';
?>

<div class="page-header">
    <a href="index.php?page=<?php echo e($backPage); ?>" class="back-btn">&larr; Back</a>
    <div class="page-actions">
        <?php if (!$isRetracted): ?>
            <a href="index.php?page=compose&reply=<?php echo $msgId; ?>" class="btn btn-sm btn-primary">&#x21A9; Reply</a>
            <a href="index.php?page=compose&replyall=<?php echo $msgId; ?>" class="btn btn-sm btn-secondary">&#x21A9; Reply All</a>
            <a href="index.php?page=compose&forward=<?php echo $msgId; ?>" class="btn btn-sm btn-secondary">&#x21AA; Forward</a>
        <?php endif; ?>
        <?php if ($isSender && !$isRetracted): ?>
            <button class="btn btn-sm btn-warning" onclick="retractMessage(<?php echo $msgId; ?>)">&#x21A9; Unsend</button>
        <?php endif; ?>
        <button class="btn btn-sm btn-danger" onclick="deleteMessage(<?php echo $msgId; ?>,'<?php echo e($backPage); ?>')">&#x1F5D1; Delete</button>
    </div>
</div>

<?php if ($isRetracted): ?>
    <div class="alert alert-error" style="text-align:center;padding:40px;font-size:16px;">
        &#x26A0; This message was retracted by the sender.
    </div>
<?php else: ?>
<div class="message-view">
    <div class="message-view-header">
        <div class="msg-avatar msg-avatar-lg" style="background:<?php echo get_avatar_color($msg['sender_name']); ?>">
            <?php echo e(get_initials($msg['sender_name'])); ?>
        </div>
        <div class="msg-view-info">
            <h2 class="msg-view-subject"><?php echo e($msg['subject']); ?></h2>
            <div class="msg-view-meta">
                <span class="msg-view-sender"><?php echo e($msg['sender_name']); ?></span>
                <span class="msg-view-date"><?php echo format_date(isset($msg['sent_at']) ? $msg['sent_at'] : $msg['created_at']); ?></span>
            </div>
            <div class="msg-view-recipients">
                <span class="label">To:</span>
                <?php foreach ($toList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                <?php if (!empty($ccList)): ?>
                    <br><span class="label">CC:</span>
                    <?php foreach ($ccList as $i => $r): ?><?php echo ($i > 0 ? ', ' : '') . e($r['display_name']); ?><?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="message-body"><?php echo sanitize_html($msg['body']); ?></div>

    <?php if (!empty($attachments)): ?>
        <div class="attachments-section">
            <h4>&#x1F4CE; Attachments (<?php echo count($attachments); ?>)</h4>
            <div class="attachment-list">
                <?php foreach ($attachments as $att):
                    $fileExists = file_exists(UPLOAD_DIR . $att['stored_name']);
                ?>
                    <?php if ($fileExists): ?>
                        <a href="api/attachments.php?action=download&id=<?php echo $att['id']; ?>" class="attachment-item">
                            <span class="att-icon">&#x1F4C4;</span>
                            <span class="att-name"><?php echo e($att['original_name']); ?></span>
                            <span class="att-size"><?php echo format_size($att['file_size']); ?></span>
                        </a>
                    <?php else: ?>
                        <span class="attachment-item att-removed" title="File removed after all recipients read this message">
                            <span class="att-icon">&#x1F6AB;</span>
                            <span class="att-name"><?php echo e($att['original_name']); ?></span>
                            <span class="att-size"><?php echo format_size($att['file_size']); ?> - removed from server</span>
                        </span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>
