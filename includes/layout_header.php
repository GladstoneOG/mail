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

// Calendar: today's event count for sidebar badge
$_calTodayCount = 0;
$_calTodayEvents = array();
if (db_table_exists($conn, 'cal_events')) {
    $_calTodayCount = intval(db_fetch_scalar($conn,
        "SELECT COUNT(*) FROM cal_events e WHERE e.is_cancelled=0 AND CONVERT(DATE,e.start_time)=CONVERT(DATE,GETDATE())
         AND (e.creator_id=? OR EXISTS(SELECT 1 FROM cal_attendees a WHERE a.event_id=e.id AND a.user_id=? AND a.status!='declined'))",
        array(auth_user_id(), auth_user_id())));
    // Fetch today's events for reminder modal (once per session)
    if (!isset($_SESSION['cal_reminder_shown'])) {
        $_calTodayEvents = db_fetch_all($conn,
            "SELECT e.title, e.start_time, e.end_time, e.all_day, e.importance, e.color, e.location
             FROM cal_events e WHERE e.is_cancelled=0 AND CONVERT(DATE,e.start_time)=CONVERT(DATE,GETDATE())
             AND (e.creator_id=? OR EXISTS(SELECT 1 FROM cal_attendees a WHERE a.event_id=e.id AND a.user_id=? AND a.status!='declined'))
             ORDER BY e.start_time",
            array(auth_user_id(), auth_user_id()));
        $_SESSION['cal_reminder_shown'] = true;
    }
}
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
                    <img src="assets/header_icon.png" class="logo-icon" alt="Logo" style="height: 24px; width: auto; object-fit: contain;">
                    <span class="logo-text"><?php echo e(APP_NAME); ?></span>
                </div>
            </div>
            <a href="index.php?page=compose" class="compose-btn">
                <span class="compose-icon">&#x1F4E7;</span> Compose
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
                <div class="nav-divider"></div>
                <a href="index.php?page=calendar" class="nav-item <?php echo $_activePage === 'calendar' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F4C5;</span><span class="nav-label">Calendar</span>
                    <?php if ($_calTodayCount > 0): ?><span class="badge"><?php echo $_calTodayCount; ?></span><?php endif; ?>
                </a>
                <?php if (auth_is_admin()): ?>
                <div class="nav-divider"></div>
                <a href="index.php?page=admin" class="nav-item <?php echo $_activePage === 'admin' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F6E0;&#xFE0F;</span><span class="nav-label">Admin</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-mini-cal" id="sidebar-mini-cal"></div>
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
                <a href="api/auth.php?action=logout" class="logout-btn" title="Logout" onclick="event.preventDefault(); customConfirm('Are you sure you want to log out?', function() { window.location='api/auth.php?action=logout'; });">Logout</a>
            </div>
        </aside>
        <main class="main-content" data-page="<?php echo e($_activePage); ?>">
            <div class="topbar">
                <button class="mobile-menu-btn" onclick="document.querySelector('.sidebar').classList.toggle('open')">&#x2630;</button>
                <div class="topbar-title"><?php echo e(ucfirst($_activePage)); ?></div>
                <div class="topbar-actions">
                    <button class="theme-toggle" onclick="toggleTheme()" title="Toggle light/dark mode" id="theme-toggle">
                        <?php echo $_theme === 'light' ? '&#x1F319;' : '&#x1F506;'; ?>
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
<?php
$_listPages = array('inbox', 'starred', 'sent', 'trash', 'drafts');
$_isListPage = in_array($_activePage, $_listPages);
?>
<?php if ($_isListPage): ?>
            <div class="action-bar" id="action-bar">
                <div class="action-buttons" id="action-buttons">
                    <button class="btn btn-action btn-sm action-btn" onclick="refreshPage()" title="Refresh">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path></svg>
                        <span class="action-label">Refresh</span>
                    </button>
<?php if (in_array($_activePage, array('inbox', 'starred'))): ?>
                    <button class="btn btn-action btn-sm action-btn" onclick="markAllAsRead()" title="Mark all as read">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        <span class="action-label">Mark all read</span>
                    </button>
<?php endif; ?>
                    <button class="btn btn-action btn-sm action-btn" id="trash-toggle-btn" onclick="toggleTrashMode()" title="<?php echo $_activePage === 'trash' ? 'Select to delete permanently' : 'Select to move to trash'; ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span class="action-label"><?php echo $_activePage === 'trash' ? 'Delete' : 'Trash'; ?></span>
                    </button>
                    <button class="btn btn-danger btn-sm action-btn" id="trash-confirm-btn" onclick="confirmTrashAction()" style="display:none" title="Confirm">
                        &#x2714;&#xFE0F; Confirm
                    </button>
                    <button class="btn btn-ghost btn-sm action-btn" id="trash-cancel-btn" onclick="cancelTrashMode()" style="display:none" title="Cancel">
                        &#x274C; Cancel
                    </button>
                </div>
                <form class="search-form action-search" method="GET" id="global-search-form">
                    <input type="hidden" name="page" value="<?php echo e($_activePage); ?>">
                    <input type="text" name="q" placeholder="Search messages..." value="<?php echo isset($_GET['q']) ? e($_GET['q']) : ''; ?>" class="search-input">
                    <button type="submit" class="search-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
                </form>
            </div>
<?php endif; ?>
            <div class="content-area">
