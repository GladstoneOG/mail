<?php
/**
 * Contacts / Address Book Page
 */
$userId = auth_user_id();
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// My contacts
$contactsSql = "SELECT c.id, c.nickname, c.notes, c.created_at,
                       u.id AS user_id, u.username, u.display_name, u.is_active
                FROM mail_contacts c
                JOIN mail_users u ON c.contact_user_id = u.id
                WHERE c.owner_id = ?";
$contactParams = array($userId);
if ($search) {
    $contactsSql .= " AND (u.display_name LIKE ? OR u.username LIKE ? OR c.nickname LIKE ?)";
    $s = '%' . $search . '%';
    $contactParams[] = $s;
    $contactParams[] = $s;
    $contactParams[] = $s;
}
$contactsSql .= " ORDER BY u.display_name";
$contacts = db_fetch_all($conn, $contactsSql, $contactParams);

// All users (for adding contacts)
$allUsers = db_fetch_all($conn,
    "SELECT id, username, display_name FROM mail_users WHERE id != ? AND is_active = 1 ORDER BY display_name",
    array($userId));
?>

<div class="page-header">
    <h2>&#x1F4D6; Address Book</h2>
    <div class="page-actions">
        <form class="search-form" method="GET">
            <input type="hidden" name="page" value="contacts">
            <input type="text" name="q" placeholder="Search contacts..." value="<?php echo e($search); ?>" class="search-input">
            <button type="submit" class="search-btn">&#x1F50D;</button>
        </form>
    </div>
</div>

<div class="contacts-grid">
    <div class="contacts-section">
        <div class="section-header">
            <h3>My Contacts (<?php echo count($contacts); ?>)</h3>
            <button class="btn btn-sm btn-primary" onclick="document.getElementById('add-contact-modal').style.display='flex'">+ Add Contact</button>
        </div>

        <?php if (empty($contacts)): ?>
            <div class="empty-state empty-sm">
                <p>No contacts yet. Add users from the directory.</p>
            </div>
        <?php else: ?>
            <div class="contact-list">
                <?php foreach ($contacts as $c): ?>
                    <div class="contact-card">
                        <div class="contact-avatar" style="background:<?php echo get_avatar_color($c['display_name']); ?>">
                            <?php echo e(get_initials($c['display_name'])); ?>
                        </div>
                        <div class="contact-info">
                            <div class="contact-name"><?php echo e($c['nickname'] ? $c['nickname'] : $c['display_name']); ?></div>
                            <div class="contact-username">@<?php echo e($c['username']); ?></div>
                            <?php if ($c['notes']): ?>
                                <div class="contact-notes"><?php echo e($c['notes']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="contact-actions">
                            <a href="index.php?page=compose&to=<?php echo urlencode($c['username']); ?>" class="btn btn-xs btn-primary" title="Send message">&#x270F;</a>
                            <button class="btn btn-xs btn-danger" onclick="removeContact(<?php echo $c['id']; ?>)" title="Remove">&#x2716;</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Contact Modal -->
<div class="modal-overlay" id="add-contact-modal" style="display:none" onclick="if(event.target===this)this.style.display='none'">
    <div class="modal">
        <div class="modal-header">
            <h3>Add Contact</h3>
            <button class="modal-close" onclick="this.closest('.modal-overlay').style.display='none'">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="contact-search" placeholder="Search users..." oninput="filterUsers(this.value)">
            </div>
            <div class="user-directory" id="user-directory">
                <?php
                $existingIds = array();
                foreach ($contacts as $c) $existingIds[] = $c['user_id'];
                foreach ($allUsers as $u):
                    $isContact = in_array($u['id'], $existingIds);
                ?>
                    <div class="user-dir-item" data-name="<?php echo e(strtolower($u['display_name'] . ' ' . $u['username'])); ?>">
                        <div class="contact-avatar" style="background:<?php echo get_avatar_color($u['display_name']); ?>">
                            <?php echo e(get_initials($u['display_name'])); ?>
                        </div>
                        <div class="contact-info">
                            <div class="contact-name"><?php echo e($u['display_name']); ?></div>
                            <div class="contact-username">@<?php echo e($u['username']); ?></div>
                        </div>
                        <?php if ($isContact): ?>
                            <span class="badge badge-muted">Added</span>
                        <?php else: ?>
                            <button class="btn btn-xs btn-primary" onclick="addContact(<?php echo $u['id']; ?>,this)">Add</button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
function filterUsers(q) {
    q = q.toLowerCase();
    var items = document.querySelectorAll('.user-dir-item');
    for (var i = 0; i < items.length; i++) {
        var name = items[i].getAttribute('data-name');
        items[i].style.display = (!q || name.indexOf(q) !== -1) ? 'flex' : 'none';
    }
}
</script>
