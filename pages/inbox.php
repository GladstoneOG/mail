<?php
/**
 * Inbox Page - Outlook-style table with sortable column headers
 */
$userId = auth_user_id();
$pg = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$offset = ($pg - 1) * ITEMS_PER_PAGE;

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';
// Sanitize
if (!in_array($sort, array('date', 'name', 'subject'))) $sort = 'date';
if (!in_array($dir, array('asc', 'desc'))) $dir = 'desc';

$countSql = "SELECT COUNT(DISTINCT m.id) FROM mail_recipients mr
             JOIN mail_messages m ON mr.message_id = m.id
             WHERE mr.recipient_id = ? AND mr.is_deleted = 0 AND m.is_draft = 0";
$countParams = array($userId);

$sql = "SELECT m.id, m.subject, m.body, m.has_attachments, m.created_at, m.sent_at,
               MIN(CAST(mr.is_read AS INT)) AS is_read, MAX(CAST(mr.is_starred AS INT)) AS is_starred,
               u.display_name AS sender_name, u.username AS sender_username
        FROM mail_recipients mr
        JOIN mail_messages m ON mr.message_id = m.id
        JOIN mail_users u ON m.sender_id = u.id
        WHERE mr.recipient_id = ? AND mr.is_deleted = 0 AND m.is_draft = 0";
$params = array($userId);

if ($search) {
    $sql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.display_name LIKE ?)";
    $searchLike = '%' . $search . '%';
    $params[] = $searchLike;
    $params[] = $searchLike;
    $params[] = $searchLike;
    $countSql .= " AND (m.subject LIKE ? OR m.body LIKE ? OR u.display_name LIKE ?)";
    $countParams[] = $searchLike;
    $countParams[] = $searchLike;
    $countParams[] = $searchLike;
}

$sql .= " GROUP BY m.id, m.subject, m.body, m.has_attachments, m.created_at, m.sent_at, u.display_name, u.username";

$total = intval(db_fetch_scalar($conn, $countSql, $countParams));
$totalPages = max(1, ceil($total / ITEMS_PER_PAGE));

$orderMap = array(
    'date'    => 'm.sent_at',
    'name'    => 'u.display_name',
    'subject' => 'm.subject',
);
$orderCol = $orderMap[$sort];
$orderDir = strtoupper($dir);
$sql .= " ORDER BY $orderCol $orderDir OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";
$params[] = $offset;
$params[] = ITEMS_PER_PAGE;

$messages = db_fetch_all($conn, $sql, $params);

// Helper for sort link
function inbox_sort_link($col, $label, $currentSort, $currentDir, $search) {
    $newDir = ($currentSort === $col && $currentDir === 'asc') ? 'desc' : 'asc';
    $arrow = '';
    if ($currentSort === $col) {
        $arrow = $currentDir === 'asc' ? ' &#x25B2;' : ' &#x25BC;';
    }
    $qs = 'page=inbox&sort=' . $col . '&dir=' . $newDir;
    if ($search) $qs .= '&q=' . urlencode($search);
    return '<a href="index.php?' . $qs . '" class="col-sort' . ($currentSort === $col ? ' col-sort-active' : '') . '">' . $label . $arrow . '</a>';
}
?>

<div class="page-header">
    <h2>Inbox</h2>
    <div class="page-actions">
        <form class="search-form" method="GET">
            <input type="hidden" name="page" value="inbox">
            <input type="text" name="q" placeholder="Search messages..." value="<?php echo e($search); ?>" class="search-input" style="border-radius:var(--radius-sm) 0 0 var(--radius-sm)">
            <button type="submit" class="search-btn">&#x1F50D;</button>
        </form>
    </div>
</div>

<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F4ED;</div>
        <h3><?php echo $search ? 'No messages found' : 'Your inbox is empty'; ?></h3>
        <p><?php echo $search ? 'Try a different search term.' : 'Messages sent to you will appear here.'; ?></p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table">
            <thead>
                <tr>
                    <th class="col-star" style="width:36px">&#x2606;</th>
                    <th class="col-from"><?php echo inbox_sort_link('name', 'From', $sort, $dir, $search); ?></th>
                    <th class="col-subject"><?php echo inbox_sort_link('subject', 'Subject', $sort, $dir, $search); ?></th>
                    <th class="col-attach" style="width:40px" title="Attachments">&#x1F4CE;</th>
                    <th class="col-date" style="width:130px"><?php echo inbox_sort_link('date', 'Received', $sort, $dir, $search); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row <?php echo $msg['is_read'] ? '' : 'unread'; ?>"
                        onclick="window.location='index.php?page=view&id=<?php echo $msg['id']; ?>'" style="cursor:pointer">
                        <td class="col-star-cell">
                            <button class="star-btn <?php echo $msg['is_starred'] ? 'starred' : ''; ?>"
                                    onclick="event.stopPropagation();toggleStar(<?php echo $msg['id']; ?>,this)"
                                    title="Star">
                                <?php echo $msg['is_starred'] ? '&#x2605;' : '&#x2606;'; ?>
                            </button>
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
            <?php if ($pg > 1): ?>
                <a href="index.php?page=inbox&pg=<?php echo $pg - 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="page-link">&laquo; Prev</a>
            <?php endif; ?>
            <span class="page-info">Page <?php echo $pg; ?> of <?php echo $totalPages; ?> (<?php echo $total; ?> messages)</span>
            <?php if ($pg < $totalPages): ?>
                <a href="index.php?page=inbox&pg=<?php echo $pg + 1; ?>&sort=<?php echo $sort; ?>&dir=<?php echo $dir; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="page-link">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
