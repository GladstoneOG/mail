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

// Load user folders for sidebar
$_userFolders = array();
if (db_table_exists($conn, 'mail_folders')) {
    $_userFolders = db_fetch_all($conn,
        "SELECT f.*, (SELECT COUNT(*) FROM mail_recipients mr JOIN mail_messages m ON mr.message_id=m.id WHERE mr.recipient_id=? AND mr.folder_id=f.id AND mr.is_deleted=0 AND m.is_draft=0) AS msg_count
         FROM mail_folders f WHERE f.user_id = ? ORDER BY f.sort_order, f.name",
        array(auth_user_id(), auth_user_id()));
}
$_activeFolderId = ($_activePage === 'folder' && isset($_GET['fid'])) ? intval($_GET['fid']) : 0;
$_foldersExpanded = isset($_COOKIE['folders_expanded']) ? $_COOKIE['folders_expanded'] : '0';
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
            <div class="sidebar-scroll-area">
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
                <!-- Collapsible Folders Section -->
                <div class="nav-folders-section" id="folders-section">
                    <button class="nav-folders-toggle" id="folders-toggle" onclick="toggleFoldersSection()" title="Folders">
                        <span class="nav-icon">&#x1F4C1;</span>
                        <span class="nav-label">Folders</span>
                        <span class="folders-chevron" id="folders-chevron"><?php echo $_foldersExpanded === '1' ? '&#x25BC;' : '&#x25B6;'; ?></span>
                    </button>
                    <div class="nav-folders-list" id="folders-list" style="display:<?php echo $_foldersExpanded === '1' ? 'block' : 'none'; ?>">
                        <?php foreach ($_userFolders as $f): ?>
                        <a href="index.php?page=folder&fid=<?php echo $f['id']; ?>" class="nav-item nav-folder-item <?php echo $_activeFolderId === intval($f['id']) ? 'active' : ''; ?>"
                           data-folder-id="<?php echo $f['id']; ?>"
                           oncontextmenu="event.preventDefault();showFolderContextMenu(event,<?php echo $f['id']; ?>,'<?php echo e(addslashes($f['name'])); ?>')">
                            <span class="nav-folder-dot" style="background:<?php echo e($f['color']); ?>"></span>
                            <span class="nav-label"><?php echo e($f['name']); ?></span>
                            <?php if (intval($f['msg_count']) > 0): ?>
                                <span class="badge badge-muted"><?php echo intval($f['msg_count']); ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                        <button class="nav-item nav-add-folder" onclick="promptCreateFolder()" title="Create new folder">
                            <span class="nav-icon" style="font-size:13px">+</span>
                            <span class="nav-label" style="font-size:12px;color:var(--text3)">New folder</span>
                        </button>
                    </div>
                </div>
                <div class="nav-divider"></div>
                <a href="index.php?page=calendar" class="nav-item <?php echo $_activePage === 'calendar' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F4C5;</span><span class="nav-label">Calendar</span>
                    <?php if ($_calTodayCount > 0): ?><span class="badge"><?php echo $_calTodayCount; ?></span><?php endif; ?>
                </a>
                <div class="nav-divider"></div>
                <a href="javascript:void(0)" class="nav-item" onclick="openRulesManager()">
                    <span class="nav-icon">&#x2699;&#xFE0F;</span><span class="nav-label">Inbox Rules</span>
                </a>
                <?php if (auth_is_admin()): ?>
                <div class="nav-divider"></div>
                <a href="index.php?page=admin" class="nav-item <?php echo $_activePage === 'admin' ? 'active' : ''; ?>">
                    <span class="nav-icon">&#x1F6E0;&#xFE0F;</span><span class="nav-label">Admin</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-mini-cal" id="sidebar-mini-cal"></div>
            </div>
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
                <div class="topbar-title"><?php
                    if ($_activePage === 'folder' && isset($folder)) {
                        echo '&#x1F4C1; ' . e($folder['name']);
                    } else {
                        echo e(ucfirst($_activePage));
                    }
                ?></div>
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
$_listPages = array('inbox', 'starred', 'sent', 'trash', 'drafts', 'folder');
$_isListPage = in_array($_activePage, $_listPages);
?>
<?php if ($_isListPage): ?>
            <div class="action-bar" id="action-bar">
                <div class="action-buttons" id="action-buttons">
<?php if ($_activePage === 'drafts'): ?>
                    <!-- DRAFTS PAGE: only search + batch delete, no refresh/move -->
                    <button class="btn btn-action btn-sm action-btn" id="trash-toggle-btn" onclick="toggleTrashMode()" title="Select to delete drafts">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span class="action-label">Delete</span>
                    </button>
                    <button class="btn btn-danger btn-sm action-btn" id="trash-confirm-btn" onclick="confirmDraftDelete()" style="display:none" title="Confirm delete">
                        &#x2714;&#xFE0F; Confirm
                    </button>
                    <button class="btn btn-ghost btn-sm action-btn" id="trash-cancel-btn" onclick="cancelTrashMode()" style="display:none" title="Cancel">
                        &#x274C; Cancel
                    </button>
<?php else: ?>
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
<?php if ($_activePage === 'trash'): ?>
                    <!-- TRASH PAGE: delete permanently -->
                    <button class="btn btn-action btn-sm action-btn" id="trash-toggle-btn" onclick="toggleTrashMode()" title="Select to delete permanently">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span class="action-label">Delete</span>
                    </button>
                    <button class="btn btn-danger btn-sm action-btn" id="trash-confirm-btn" onclick="confirmTrashAction()" style="display:none" title="Confirm">
                        &#x2714;&#xFE0F; Confirm
                    </button>
                    <button class="btn btn-ghost btn-sm action-btn" id="trash-cancel-btn" onclick="cancelTrashMode()" style="display:none" title="Cancel">
                        &#x274C; Cancel
                    </button>
<?php else: ?>
                    <!-- NON-TRASH/NON-DRAFT PAGES: "Move to" button -->
                    <button class="btn btn-action btn-sm action-btn" id="trash-toggle-btn" onclick="toggleTrashMode()" title="Select to move">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>
                            <line x1="12" y1="11" x2="12" y2="17"></line>
                            <polyline points="9 14 12 17 15 14"></polyline>
                        </svg>
                        <span class="action-label">Move to</span>
                    </button>
                    <button class="btn btn-primary btn-sm action-btn" id="trash-confirm-btn" onclick="openMoveToModal()" style="display:none" title="Move selected">
                        &#x1F4C2; Move
                    </button>
                    <button class="btn btn-ghost btn-sm action-btn" id="trash-cancel-btn" onclick="cancelTrashMode()" style="display:none" title="Cancel">
                        &#x274C; Cancel
                    </button>
<?php if ($_activePage === 'folder' && isset($folder)): ?>
                    <!-- FOLDER PAGE: Rename + Delete folder buttons -->
                    <span class="action-separator"></span>
                    <button class="btn btn-action btn-sm action-btn" onclick="toolbarRenameFolder()" title="Rename this folder">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                        <span class="action-label">Rename</span>
                    </button>
                    <button class="btn btn-action btn-sm action-btn folder-delete-toolbar-btn" onclick="toolbarDeleteFolder()" title="Delete this folder">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                        <span class="action-label">Delete Folder</span>
                    </button>
                    <script>
                        var _toolbarFolderId = <?php echo intval($folder['id']); ?>;
                        var _toolbarFolderName = <?php echo json_encode($folder['name']); ?>;
                    </script>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
                </div>
                <div style="margin-left:auto; display:flex; align-items:center; gap:8px;">
                    <button class="btn btn-action btn-sm action-btn" onclick="openTagManager()" title="Manage Tags">
                        <span style="font-size:13px">🏷️</span>
                        <span class="action-label">Tags</span>
                    </button>
                    <form class="search-form action-search" method="GET" id="global-search-form" onkeydown="advSearchEnter(event)">
                        <input type="hidden" name="page" value="<?php echo e($_activePage); ?>">
                        <?php if ($_activePage === 'folder' && isset($_GET['fid'])): ?>
                            <input type="hidden" name="fid" value="<?php echo intval($_GET['fid']); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="sf" id="search-field-input" value="<?php echo isset($_GET['sf']) ? e($_GET['sf']) : ''; ?>">
                        <input type="text" name="q" placeholder="Search messages..." value="<?php echo isset($_GET['q']) ? e($_GET['q']) : ''; ?>" class="search-input" id="search-q-input">
                        <button type="button" class="adv-search-toggle" onclick="toggleAdvancedSearch()" title="Advanced Search">▾</button>
                        <button type="submit" class="search-btn"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></button>
                        <div class="adv-search-dropdown" id="adv-search-dropdown">
                            <div class="adv-search-header">🔍 Advanced Search</div>
                            <div class="adv-search-field">
                                <label>Sender</label>
                                <input type="text" id="adv-sender" placeholder="Username or name...">
                            </div>
                            <div class="adv-search-field">
                                <label>Subject</label>
                                <input type="text" id="adv-subject" placeholder="Subject contains...">
                            </div>
                            <div class="adv-search-field">
                                <label><?php echo $_activePage === 'sent' ? 'Sent' : 'Received'; ?> after</label>
                                <input type="date" id="adv-date-from">
                            </div>
                            <div class="adv-search-field">
                                <label><?php echo $_activePage === 'sent' ? 'Sent' : 'Received'; ?> before</label>
                                <input type="date" id="adv-date-to">
                            </div>
                            <?php
                            $headerTags = [];
                            if (db_table_exists($conn, 'mail_tags')) {
                                $headerTags = db_fetch_all($conn, "SELECT id, name, color FROM mail_tags WHERE user_id = ? ORDER BY name ASC", array($_SESSION['user']['id']));
                            }
                            if (!empty($headerTags)):
                            ?>
                            <div class="adv-search-field">
                                <label>Tags</label>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                <?php foreach ($headerTags as $ht): ?>
                                    <label class="tag-filter-label" style="display:inline-flex;align-items:center;font-size:11px;padding:2px 6px;border:1px solid var(--border);border-radius:4px;cursor:pointer;">
                                        <input type="checkbox" class="adv-tag-checkbox" value="<?php echo $ht['id']; ?>" style="margin:0 4px 0 0">
                                        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo e($ht['color']); ?>;margin-right:4px;"></span>
                                        <?php echo e($ht['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="adv-search-field">
                                <label><input type="checkbox" id="adv-attachment"> Has attachment</label>
                            </div>
                            <div class="adv-search-field">
                                <label>Content</label>
                                <input type="text" id="adv-content" placeholder="Body contains...">
                            </div>
                            <div class="adv-search-actions">
                                <button type="button" class="btn btn-ghost btn-sm" onclick="clearAdvSearch()">Clear</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="submitAdvSearch()">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
<?php endif; ?>
            <div class="content-area">
