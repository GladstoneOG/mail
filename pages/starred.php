<?php
/**
 * Starred Messages Page - Outlook-style table
 */
$userId = auth_user_id();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';
if (!in_array($sort, array('date', 'name', 'subject'))) $sort = 'date';
if (!in_array($dir, array('asc', 'desc'))) $dir = 'desc';

$orderMap = array('date' => 'm.sent_at', 'name' => 'u.display_name', 'subject' => 'm.subject');
$orderCol = $orderMap[$sort];
$orderDir = strtoupper($dir);

$sql = "SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at,
            MIN(CAST(mr.is_read AS INT)) AS is_read, MAX(CAST(mr.is_starred AS INT)) AS is_starred,
            u.display_name AS sender_name, u.username AS sender_username
     FROM mail_recipients mr
     JOIN mail_messages m ON mr.message_id = m.id
     JOIN mail_users u ON m.sender_id = u.id
     WHERE mr.recipient_id = ? AND mr.is_starred = 1 AND mr.is_deleted = 0 AND m.is_draft = 0 AND (m.sent_at IS NULL OR m.sent_at <= GETDATE())";
$params = array($userId);

if ($search) {
    $searchField = isset($_GET['sf']) ? $_GET['sf'] : '';
    $searchLike = '%' . $search . '%';
    if ($searchField === 'sender') {
        $sql .= " AND (u.username LIKE ? OR u.display_name LIKE ?)";
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
        $tagIds = array_filter(array_map('intval', explode(',', $search)));
        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $sql .= " AND m.id IN (SELECT message_id FROM mail_message_tags WHERE tag_id IN ($placeholders) AND user_id = ?)";
            $params = array_merge($params, $tagIds, array($userId));
        }
    } else {
        $sql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.display_name LIKE ?)";
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
    }
}

$sql .= " GROUP BY m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at, u.display_name, u.username
     ORDER BY $orderCol $orderDir";

$messages = db_fetch_all($conn, $sql, $params);

// Check for replies by current user
$replyMap = array();
if (!empty($messages)) {
    $msgIds = array_map(function($m){ return $m['id']; }, $messages);
    $inClause = implode(',', $msgIds);
    $replies = db_fetch_all($conn,
        "SELECT reply_to_id, id AS reply_id, sent_at FROM mail_messages WHERE sender_id = ? AND reply_to_id IN ($inClause) AND is_draft = 0",
        array($userId));
    foreach ($replies as $r) {
        $replyMap[intval($r['reply_to_id'])] = $r;
    }
}

function starred_sort_link($col, $label, $currentSort, $currentDir) {
    $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' &#x25B2;' : ' &#x25BC;';
    }
    $qs = 'page=starred&sort=' . $col . '&dir=' . $newDir;
    return '<a href="index.php?' . $qs . '" class="col-sort' . ($currentSort === $col ? ' col-sort-active' : '') . '">' . $label . $arrow . '</a>';
}
?>
<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x2B50;</div>
        <h3>No starred messages</h3>
        <p>Star important messages to find them here.</p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table" id="msg-table">
            <thead>
                <tr>
                    <th class="col-select"><input type="checkbox" class="select-all-cb" onchange="toggleSelectAll(this)"></th>
                    <th class="col-star" style="width:36px">&#x2606;</th>
                    <th class="col-from"><?php echo starred_sort_link('name', 'From', $sort, $dir); ?></th>
                    <th class="col-subject"><?php echo starred_sort_link('subject', 'Subject', $sort, $dir); ?></th>
                    <th class="col-attach" style="width:40px" title="Attachments">&#x1F4CE;</th>
                    <th class="col-date" style="width:130px"><?php echo starred_sort_link('date', 'Received', $sort, $dir); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row <?php echo $msg['is_read'] ? '' : 'unread'; ?>"
                        data-msg-id="<?php echo $msg['id']; ?>"
                        data-sender-username="<?php echo e($msg['sender_username']); ?>"
                        onclick="handleRowClick(event, <?php echo $msg['id']; ?>, 'starred')" style="cursor:pointer">
                        <td class="col-select-cell"><input type="checkbox" class="msg-select-cb" value="<?php echo $msg['id']; ?>" onclick="event.stopPropagation()"></td>
                        <td class="col-star-cell">
                            <button class="star-btn starred"
                                    onclick="event.stopPropagation();toggleStar(<?php echo $msg['id']; ?>,this)"
                                    title="Star">&#x2605;</button>
                        </td>
                        <td class="col-from-cell">
                            <div class="user-cell">
                                <div class="avatar-xs" style="background:<?php echo get_avatar_color($msg['sender_name']); ?>">
                                    <?php echo e(get_initials($msg['sender_name'])); ?>
                                </div>
                                <span><?php echo e($msg['sender_name']); ?></span>
                            </div>
                        </td>
                        <td class="col-subject-cell">
                            <?php if (isset($replyMap[$msg['id']])): ?>
                                <span class="replied-indicator" title="You replied to this message">&#x21A9;</span>
                            <?php endif; ?>
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
<?php endif; ?>
