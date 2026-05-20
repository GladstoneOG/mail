<?php
/**
 * Sent Messages Page - Outlook-style table with sortable column headers
 * Shows scheduled-but-not-yet-delivered messages at the top with a different style.
 */
$userId = auth_user_id();
$pg = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$offset = ($pg - 1) * ITEMS_PER_PAGE;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';
if (!in_array($sort, array('date', 'name', 'subject'))) $sort = 'date';
if (!in_array($dir, array('asc', 'desc'))) $dir = 'desc';

// Fetch scheduled (future) messages - always shown at top
$scheduledMessages = array();
if (!$search) {
    $scheduledMessages = db_fetch_all($conn,
        "SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at, m.scheduled_at,
            (SELECT TOP 1 u.display_name FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id WHERE mr.message_id = m.id AND mr.recipient_type = 'to' ORDER BY mr.id) as to_name
         FROM mail_messages m
         WHERE m.sender_id = ? AND m.is_draft = 0 AND m.sender_deleted = 0 AND m.scheduled_at IS NOT NULL AND m.sent_at > GETDATE()
         ORDER BY m.sent_at ASC",
        array($userId));
    foreach ($scheduledMessages as &$smsg) {
        if (!$smsg['to_name']) $smsg['to_name'] = 'Unknown';
        $smsg['recip_count'] = intval(db_fetch_scalar($conn,
            "SELECT COUNT(*) FROM mail_recipients WHERE message_id = ?", array($smsg['id'])));
    }
    unset($smsg);
}

// Count for pagination (exclude scheduled-future from regular list)
$countSql = "SELECT COUNT(*) FROM mail_messages m WHERE m.sender_id = ? AND m.is_draft = 0 AND m.sender_deleted = 0 AND (m.scheduled_at IS NULL OR m.sent_at <= GETDATE())";
$countParams = array($userId);

$searchField = '';
$searchLike = '';
if ($search) {
    $searchField = isset($_GET['sf']) ? $_GET['sf'] : '';
    $searchLike = '%' . $search . '%';
    if ($searchField === 'sender') {
        $countSql .= " AND m.id IN (SELECT message_id FROM mail_recipients WHERE recipient_type='to' AND recipient_id IN (SELECT id FROM mail_users WHERE username LIKE ? OR display_name LIKE ?))";
        $countParams[] = $searchLike; $countParams[] = $searchLike;
    } elseif ($searchField === 'subject') {
        $countSql .= " AND m.subject LIKE ?";
        $countParams[] = $searchLike;
    } elseif ($searchField === 'content') {
        $countSql .= " AND m.body LIKE ?";
        $countParams[] = $searchLike;
    } elseif ($searchField === 'has_attachment') {
        $countSql .= " AND m.has_attachments = 1";
    } elseif ($searchField === 'date_from') {
        $countSql .= " AND m.sent_at >= ?";
        $countParams[] = $search;
    } elseif ($searchField === 'date_to') {
        $countSql .= " AND m.sent_at <= ?";
        $countParams[] = $search . ' 23:59:59';
    } elseif ($searchField === 'tags') {
        $tagIds = array_filter(array_map('intval', explode(',', $search)));
        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $countSql .= " AND m.id IN (SELECT message_id FROM mail_message_tags WHERE tag_id IN ($placeholders) AND user_id = ?)";
            $countParams = array_merge($countParams, $tagIds, array($userId));
        }
    } else {
        $countSql .= " AND (m.subject LIKE ? OR m.body LIKE ?)";
        $countParams[] = $searchLike; $countParams[] = $searchLike;
    }
}

$total = intval(db_fetch_scalar($conn, $countSql, $countParams));
$totalPages = max(1, ceil($total / ITEMS_PER_PAGE));

$orderMap = array(
    'date'    => 'm.sent_at',
    'name'    => 'to_name',
    'subject' => 'm.subject',
);
$orderCol = $orderMap[$sort];
$orderDir = strtoupper($dir);

$sql = "SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at, m.scheduled_at,
            (SELECT TOP 1 u.display_name FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id WHERE mr.message_id = m.id AND mr.recipient_type = 'to' ORDER BY mr.id) as to_name
     FROM mail_messages m
     WHERE m.sender_id = ? AND m.is_draft = 0 AND m.sender_deleted = 0 AND (m.scheduled_at IS NULL OR m.sent_at <= GETDATE())";
$params = array($userId);

if ($search) {
    if ($searchField === 'sender') {
        $sql .= " AND m.id IN (SELECT message_id FROM mail_recipients WHERE recipient_type='to' AND recipient_id IN (SELECT id FROM mail_users WHERE username LIKE ? OR display_name LIKE ?))";
        $params[] = $searchLike; $params[] = $searchLike;
    } elseif ($searchField === 'subject') {
        $sql .= " AND m.subject LIKE ?";
        $params[] = $searchLike;
    } elseif ($searchField === 'content') {
        $sql .= " AND m.body LIKE ?";
        $params[] = $searchLike;
    } elseif ($searchField === 'has_attachment') {
        $sql .= " AND m.has_attachments = 1";
    } elseif ($searchField === 'date_from') {
        $sql .= " AND m.sent_at >= ?";
        $params[] = $search;
    } elseif ($searchField === 'date_to') {
        $sql .= " AND m.sent_at <= ?";
        $params[] = $search . ' 23:59:59';
    } elseif ($searchField === 'tags') {
        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $sql .= " AND m.id IN (SELECT message_id FROM mail_message_tags WHERE tag_id IN ($placeholders) AND user_id = ?)";
            $params = array_merge($params, $tagIds, array($userId));
        }
    } else {
        $sql .= " AND (m.subject LIKE ? OR m.body LIKE ?)";
        $params[] = $searchLike; $params[] = $searchLike;
    }
}

$sql .= " ORDER BY $orderCol $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = ITEMS_PER_PAGE;

$messages = db_fetch_all($conn, $sql, $params);

foreach ($messages as &$msg) {
    if (!$msg['to_name']) $msg['to_name'] = 'Unknown';
    $msg['recip_count'] = intval(db_fetch_scalar($conn,
        "SELECT COUNT(*) FROM mail_recipients WHERE message_id = ?", array($msg['id'])));
}
unset($msg);

function sent_sort_link($col, $label, $currentSort, $currentDir) {
    $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' &#x25B2;' : ' &#x25BC;';
    }
    $qs = 'page=sent&sort=' . $col . '&dir=' . $newDir;
    return '<a href="index.php?' . $qs . '" class="col-sort' . ($currentSort === $col ? ' col-sort-active' : '') . '">' . $label . $arrow . '</a>';
}
?>

<?php if (empty($messages) && empty($scheduledMessages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F4E4;</div>
        <h3>No sent messages</h3>
        <p>Messages you send will appear here.</p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table" id="msg-table">
            <thead>
                <tr>
                    <th class="col-select"><input type="checkbox" class="select-all-cb" onchange="toggleSelectAll(this)"></th>
                    <th class="col-from"><?php echo sent_sort_link('name', 'To', $sort, $dir); ?></th>
                    <th class="col-subject"><?php echo sent_sort_link('subject', 'Subject', $sort, $dir); ?></th>
                    <th class="col-attach" style="width:40px" title="Attachments">&#x1F4CE;</th>
                    <th class="col-date" style="width:130px"><?php echo sent_sort_link('date', 'Sent', $sort, $dir); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php /* Scheduled (future) messages shown first */ ?>
                <?php foreach ($scheduledMessages as $msg): ?>
                    <tr class="msg-row msg-scheduled"
                        data-msg-id="<?php echo $msg['id']; ?>"
                        onclick="handleRowClick(event, <?php echo $msg['id']; ?>, 'sent')" style="cursor:pointer">
                        <td class="col-select-cell"><input type="checkbox" class="msg-select-cb" value="<?php echo $msg['id']; ?>" onclick="event.stopPropagation()"></td>
                        <td class="col-from-cell">
                            <div class="user-cell">
                                <div class="avatar-xs-wrap">
                                    <div class="avatar-xs" style="background:<?php echo get_avatar_color($msg['to_name']); ?>">
                                        <?php echo e(get_initials($msg['to_name'])); ?>
                                    </div>
                                    <?php if ($msg['recip_count'] > 1): ?>
                                        <span class="avatar-badge">+<?php echo $msg['recip_count'] - 1; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="recip-name"><?php echo e($msg['to_name']); ?></span>
                                <?php if ($msg['recip_count'] > 1): ?>
                                    <span class="recip-extra">+<?php echo $msg['recip_count'] - 1; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-subject-cell">
                            <span class="msg-scheduled-badge">&#x1F552; Scheduled</span>
                            <span class="msg-subj-text"><?php echo e($msg['subject']); ?></span>
                            <span class="msg-preview-inline"> &mdash; <?php echo e(truncate_text($msg['body'], 40)); ?></span>
                        </td>
                        <td class="col-attach-cell"><?php echo $msg['has_attachments'] ? '&#x1F4CE;' : ''; ?></td>
                        <td class="col-date-cell">
                            <span class="msg-scheduled-time"><?php echo format_date($msg['sent_at']); ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php /* Regular sent messages */ ?>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row"
                        data-msg-id="<?php echo $msg['id']; ?>"
                        onclick="handleRowClick(event, <?php echo $msg['id']; ?>, 'sent')" style="cursor:pointer">
                        <td class="col-select-cell"><input type="checkbox" class="msg-select-cb" value="<?php echo $msg['id']; ?>" onclick="event.stopPropagation()"></td>
                        <td class="col-from-cell">
                            <div class="user-cell">
                                <div class="avatar-xs-wrap">
                                    <div class="avatar-xs" style="background:<?php echo get_avatar_color($msg['to_name']); ?>">
                                        <?php echo e(get_initials($msg['to_name'])); ?>
                                    </div>
                                    <?php if ($msg['recip_count'] > 1): ?>
                                        <span class="avatar-badge">+<?php echo $msg['recip_count'] - 1; ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="recip-name"><?php echo e($msg['to_name']); ?></span>
                                <?php if ($msg['recip_count'] > 1): ?>
                                    <span class="recip-extra">+<?php echo $msg['recip_count'] - 1; ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="col-subject-cell">
                            <span class="msg-subj-text"><?php echo e($msg['subject']); ?></span>
                            <span class="msg-preview-inline"> &mdash; <?php echo e(truncate_text($msg['body'], 60)); ?></span>
                        </td>
                        <td class="col-attach-cell"><?php echo $msg['has_attachments'] ? '&#x1F4CE;' : ''; ?></td>
                        <td class="col-date-cell"><?php echo time_ago(isset($msg['sent_at']) ? $msg['sent_at'] : $msg['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($pg > 1): ?><a href="index.php?page=sent&pg=<?php echo $pg-1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>" class="page-link">&laquo; Prev</a><?php endif; ?>
            <span class="page-info">Page <?php echo $pg; ?> of <?php echo $totalPages; ?></span>
            <?php if ($pg < $totalPages): ?><a href="index.php?page=sent&pg=<?php echo $pg+1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?>" class="page-link">Next &raquo;</a><?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
