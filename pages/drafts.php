<?php
/**
 * Drafts Page
 */
$userId = auth_user_id();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT m.id, m.subject, m.body, m.has_attachments, m.created_at
     FROM mail_messages m
     WHERE m.sender_id = ? AND m.is_draft = 1";
$params = array($userId);

if ($search) {
    $sql .= " AND (m.subject LIKE ? OR m.body LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
}

$sql .= " ORDER BY m.created_at DESC";

$messages = db_fetch_all($conn, $sql, $params);
?>
<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F4DD;</div>
        <h3>No drafts</h3>
        <p>Saved drafts will appear here.</p>
    </div>
<?php else: ?>
    <div class="message-list" id="msg-table">
        <?php foreach ($messages as $msg): ?>
            <a href="index.php?page=compose&draft=<?php echo $msg['id']; ?>" class="message-item draft-item" data-msg-id="<?php echo $msg['id']; ?>">
                <div class="col-select-cell draft-select-cell"><input type="checkbox" class="msg-select-cb" value="<?php echo $msg['id']; ?>" onclick="event.stopPropagation();event.preventDefault()"></div>
                <div class="msg-avatar" style="background:#92400e">&#x270F;</div>
                <div class="msg-content">
                    <div class="msg-header-row">
                        <span class="msg-sender" style="color:#fbbf24">Draft</span>
                        <span class="msg-time"><?php echo time_ago($msg['created_at']); ?></span>
                    </div>
                    <div class="msg-subject"><?php echo e($msg['subject'] ? $msg['subject'] : '(No Subject)'); ?></div>
                    <div class="msg-preview"><?php echo e(truncate_text($msg['body'], 100)); ?></div>
                </div>
                <div class="msg-actions-col">
                    <button class="del-btn" onclick="event.preventDefault();deleteDraft(<?php echo $msg['id']; ?>)" title="Delete draft">&#x2716;</button>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
