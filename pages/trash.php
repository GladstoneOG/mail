<?php
/**
 * Trash Page - Outlook-style table
 */
$userId = auth_user_id();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT id, subject, body, has_attachments, sent_at, created_at, sender_name, deleted_at FROM (
    SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at,
            u.display_name AS sender_name, MAX(mr.deleted_at) AS deleted_at
     FROM mail_recipients mr
     JOIN mail_messages m ON mr.message_id = m.id
     JOIN mail_users u ON m.sender_id = u.id
     WHERE mr.recipient_id = ? AND mr.is_deleted = 1 AND m.is_draft = 0";
$params = array($userId);

if ($search) {
    $sql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.display_name LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " GROUP BY m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at, u.display_name

    UNION

    SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at,
            u.display_name AS sender_name, m.sent_at AS deleted_at
     FROM mail_messages m
     JOIN mail_users u ON m.sender_id = u.id
     WHERE m.sender_id = ? AND m.sender_deleted = 1 AND m.is_draft = 0
     AND m.id NOT IN (SELECT mr2.message_id FROM mail_recipients mr2 WHERE mr2.recipient_id = ? AND mr2.is_deleted = 1)";
$params[] = $userId;
$params[] = $userId;

if ($search) {
    $sql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.display_name LIKE ?)";
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= ") AS trash_union ORDER BY deleted_at DESC";

$messages = db_fetch_all($conn, $sql, $params);
?>
<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F5D1;</div>
        <h3>Trash is empty</h3>
        <p>Deleted messages will appear here.</p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table" id="msg-table">
            <thead>
                <tr>
                    <th class="col-select"><input type="checkbox" class="select-all-cb" onchange="toggleSelectAll(this)"></th>
                    <th class="col-from">From</th>
                    <th class="col-subject">Subject</th>
                    <th class="col-date" style="width:130px">Deleted</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row" data-msg-id="<?php echo $msg['id']; ?>"
                        onclick="handleRowClick(event, <?php echo $msg['id']; ?>, 'trash')" style="cursor:pointer">
                        <td class="col-select-cell"><input type="checkbox" class="msg-select-cb" value="<?php echo $msg['id']; ?>" onclick="event.stopPropagation()"></td>
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
