<?php
/**
 * Trash Page - Outlook-style table
 */
$userId = auth_user_id();
$messages = db_fetch_all($conn,
    "SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at,
            u.display_name AS sender_name, MAX(mr.deleted_at) AS deleted_at
     FROM mail_recipients mr
     JOIN mail_messages m ON mr.message_id = m.id
     JOIN mail_users u ON m.sender_id = u.id
     WHERE mr.recipient_id = ? AND mr.is_deleted = 1 AND m.is_draft = 0
     GROUP BY m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at, u.display_name
     ORDER BY MAX(mr.deleted_at) DESC",
    array($userId));
?>
<div class="page-header">
    <h2>Trash</h2>
    <?php if (!empty($messages)): ?>
    <div class="page-actions">
        <button class="btn btn-sm btn-danger" onclick="emptyTrash()">&#x1F5D1; Empty Trash</button>
    </div>
    <?php endif; ?>
</div>
<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F5D1;</div>
        <h3>Trash is empty</h3>
        <p>Deleted messages will appear here.</p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table">
            <thead>
                <tr>
                    <th class="col-from">From</th>
                    <th class="col-subject">Subject</th>
                    <th class="col-date" style="width:130px">Deleted</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row">
                        <td class="col-from-cell">
                            <div class="user-cell">
                                <div class="avatar-xs" style="background:<?php echo get_avatar_color($msg['sender_name']); ?>">
                                    <?php echo e(get_initials($msg['sender_name'])); ?>
                                </div>
                                <span><?php echo e($msg['sender_name']); ?></span>
                            </div>
                        </td>
                        <td class="col-subject-cell"><?php echo e($msg['subject']); ?></td>
                        <td class="col-date-cell"><?php echo time_ago($msg['deleted_at']); ?></td>
                        <td><button class="restore-btn" onclick="restoreMessage(<?php echo $msg['id']; ?>)" title="Restore">&#x21A9;</button></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
