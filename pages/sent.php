<?php
/**
 * Sent Messages Page - Outlook-style table with sortable column headers
 */
$userId = auth_user_id();
$pg = isset($_GET['pg']) ? max(1, intval($_GET['pg'])) : 1;
$offset = ($pg - 1) * ITEMS_PER_PAGE;

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';
if (!in_array($sort, array('date', 'name', 'subject'))) $sort = 'date';
if (!in_array($dir, array('asc', 'desc'))) $dir = 'desc';

$total = intval(db_fetch_scalar($conn,
    "SELECT COUNT(*) FROM mail_messages WHERE sender_id = ? AND is_draft = 0 AND sender_deleted = 0",
    array($userId)));
$totalPages = max(1, ceil($total / ITEMS_PER_PAGE));

$orderMap = array(
    'date'    => 'm.sent_at',
    'name'    => 'to_name',
    'subject' => 'm.subject',
);
$orderCol = $orderMap[$sort];
$orderDir = strtoupper($dir);

$messages = db_fetch_all($conn,
    "SELECT m.id, m.subject, m.body, m.has_attachments, m.sent_at, m.created_at,
            (SELECT TOP 1 u.display_name FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id WHERE mr.message_id = m.id AND mr.recipient_type = 'to' ORDER BY mr.id) as to_name
     FROM mail_messages m
     WHERE m.sender_id = ? AND m.is_draft = 0 AND m.sender_deleted = 0
     ORDER BY $orderCol $orderDir
     OFFSET ? ROWS FETCH NEXT ? ROWS ONLY",
    array($userId, $offset, ITEMS_PER_PAGE));

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

<div class="page-header">
    <h2>Sent</h2>
</div>

<?php if (empty($messages)): ?>
    <div class="empty-state">
        <div class="empty-icon">&#x1F4E4;</div>
        <h3>No sent messages</h3>
        <p>Messages you send will appear here.</p>
    </div>
<?php else: ?>
    <div class="msg-table-wrap">
        <table class="msg-table">
            <thead>
                <tr>
                    <th class="col-from"><?php echo sent_sort_link('name', 'To', $sort, $dir); ?></th>
                    <th class="col-subject"><?php echo sent_sort_link('subject', 'Subject', $sort, $dir); ?></th>
                    <th class="col-attach" style="width:40px" title="Attachments">&#x1F4CE;</th>
                    <th class="col-date" style="width:130px"><?php echo sent_sort_link('date', 'Sent', $sort, $dir); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                    <tr class="msg-row" onclick="window.location='index.php?page=view&id=<?php echo $msg['id']; ?>&from=sent'" style="cursor:pointer">
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
