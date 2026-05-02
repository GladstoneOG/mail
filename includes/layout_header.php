<?php
$_activePage = isset($page) ? $page : 'inbox';
$_draftCount = intval(db_fetch_scalar($conn, "SELECT COUNT(*) FROM mail_messages WHERE sender_id = ? AND is_draft = 1", array(auth_user_id())));
$_theme = isset($_COOKIE['lanmail_theme']) ? $_COOKIE['lanmail_theme'] : 'dark';

// Recent messages for notification dropdown (#3)
$_recentUnread = db_fetch_all($conn,
    "SELECT m.id, m.subject, u.display_name AS sender_name, m.sent_at
     FROM mail_recipients mr
     JOIN mail_messages m ON mr.message_id = m.id
     JOIN mail_users u ON m.sender_id = u.id
     WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.is_deleted = 0 AND m.is_draft = 0
     ORDER BY m.sent_at DESC
     OFFSET 0 ROWS FETCH NEXT 10 ROWS ONLY",
    array(auth_user_id()));
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo e($_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" type="image/png" href="assets/icon.png" id="favicon">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="logo-icon">&#x1F4E8;</span>
                    <span class="logo-text"><?php echo e(APP_NAME); ?></span>
                </div>
            </div>
            <a href="index.php?page=compose" class="compose-btn">
                <span class="compose-icon">&#x270F;</span> Compose
            </a>
            <nav class="sidebar-nav">
                <a href="index.php?page=inbox" class="nav-item <?php echo $_activePage === 'inbox' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F4E5;</span><span class="nav-label">Inbox</span>
                    <?php if ($unreadCount > 0): ?><span class="badge"><?php echo $unreadCount; ?></span><?php endif; ?>
                </a>
                <a href="index.php?page=starred" class="nav-item <?php echo $_activePage === 'starred' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x2B50;</span><span class="nav-label">Starred</span>
                </a>
                <a href="index.php?page=sent" class="nav-item <?php echo $_activePage === 'sent' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F4E4;</span><span class="nav-label">Sent</span>
                </a>
                <a href="index.php?page=drafts" class="nav-item <?php echo $_activePage === 'drafts' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F4DD;</span><span class="nav-label">Drafts</span>
                    <?php if ($_draftCount > 0): ?><span class="badge badge-muted"><?php echo $_draftCount; ?></span><?php endif; ?>
                </a>
                <a href="index.php?page=trash" class="nav-item <?php echo $_activePage === 'trash' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F6AE;</span><span class="nav-label">Trash</span>
                </a>
                <?php if (auth_is_admin()): ?>
                <div class="nav-divider"></div>
                <a href="index.php?page=admin" class="nav-item <?php echo $_activePage === 'admin' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F6E0;&#xFE0F;</span><span class="nav-label">Admin</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <a href="index.php?page=profile" class="user-card <?php echo $_activePage === 'profile' ? 'active' : ''; ?>">
                    <div class="avatar-sm" style="background:<?php echo get_avatar_color($currentUser['display_name']); ?>">
                        <?php echo e(get_initials($currentUser['display_name'])); ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?php echo e($currentUser['display_name']); ?></div>
                        <div class="user-role"><?php echo e(ucfirst($currentUser['role'])); ?></div>
                    </div>
                </a>
                <a href="api/auth.php?action=logout" class="logout-btn" title="Logout" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </div>
        </aside>
        <main class="main-content">
            <div class="topbar">
                <button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#x2630;</button>
                <div class="topbar-title"><?php echo e(ucfirst($_activePage)); ?></div>
                <div class="topbar-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle light/dark mode" id="theme-toggle">
                        <?php echo $_theme === 'light' ? '&#x1F319;' : '&#x2600;'; ?>
                    </button>
                    <div class="notif-wrapper" id="notif-wrapper">
                        <button class="notification-bell" id="notification-bell" title="Notifications" onclick="toggleNotifDropdown()">
                            &#x1F514;<span class="notif-badge" id="notif-badge" style="<?php echo $unreadCount > 0 ? '' : 'display:none'; ?>"><?php echo $unreadCount; ?></span>
                        </button>
                        <div class="notif-dropdown" id="notif-dropdown">
                            <div class="notif-dropdown-header">
                                <strong>Notifications</strong>
                                <?php if ($unreadCount > 0): ?><span class="notif-count"><?php echo $unreadCount; ?> unread</span><?php endif; ?>
                            </div>
                            <div class="notif-dropdown-body">
                                <?php if (empty($_recentUnread)): ?>
                                    <div class="notif-empty">No new notifications</div>
                                <?php else: ?>
                                    <?php foreach ($_recentUnread as $n): ?>
                                        <a href="index.php?page=view&id=<?php echo $n['id']; ?>" class="notif-item">
                                            <div class="notif-avatar" style="background:<?php echo get_avatar_color($n['sender_name']); ?>">
                                                <?php echo e(get_initials($n['sender_name'])); ?>
                                            </div>
                                            <div class="notif-info">
                                                <div class="notif-sender"><?php echo e($n['sender_name']); ?></div>
                                                <div class="notif-subject"><?php echo e(truncate_text($n['subject'], 40)); ?></div>
                                                <div class="notif-time"><?php echo time_ago($n['sent_at']); ?></div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($unreadCount > 0): ?>
                                <a href="index.php?page=inbox" class="notif-dropdown-footer">View all in Inbox</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content-area">
