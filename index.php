<?php
/**
 * LAN Mail - Main Entry Point / Router
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

auth_start_session();

// Run attachment decay cleanup (throttled to once every 5 minutes per session)
if (!isset($_SESSION['last_attachment_decay_cleanup']) || (time() - $_SESSION['last_attachment_decay_cleanup']) > 300) {
    decay_attachments($conn);
    $_SESSION['last_attachment_decay_cleanup'] = time();
}

// Check if installed
if (!db_table_exists($conn, 'mail_users')) {
    header('Location: install.php');
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'inbox';
$publicPages = array('login');
if (defined('ALLOW_SELF_REGISTRATION') && ALLOW_SELF_REGISTRATION) {
    $publicPages[] = 'register';
}
$validPages = array('login','register','inbox','compose','view','sent','drafts','trash','starred','contacts','profile','admin','outbox','calendar','folder');

if (!in_array($page, $validPages)) {
    $page = 'inbox';
}

// Auth check
if (!in_array($page, $publicPages) && !auth_is_logged_in()) {
    redirect('index.php?page=login');
}

// Register page access control
if ($page === 'register') {
    $selfRegAllowed = defined('ALLOW_SELF_REGISTRATION') && ALLOW_SELF_REGISTRATION;
    // If logged-in non-admin tries to access register, redirect
    if (auth_is_logged_in() && !auth_is_admin()) {
        redirect('index.php?page=inbox');
    }
    // If not logged in and self-registration is disabled, redirect to login
    if (!auth_is_logged_in() && !$selfRegAllowed) {
        redirect('index.php?page=login');
    }
}

// If logged in and trying to access login/register, go to inbox
if (in_array($page, $publicPages) && auth_is_logged_in()) {
    redirect('index.php?page=inbox');
}

$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (!file_exists($pageFile)) {
    $page = 'inbox';
    $pageFile = __DIR__ . '/pages/inbox.php';
}

// Public pages render their own layout
if (in_array($page, $publicPages)) {
    include $pageFile;
    exit;
}

// App pages get the sidebar layout
$currentUser = auth_user();
$unreadCount = get_unread_count($conn, auth_user_id());
$csrfToken = get_csrf_token();

// Pre-load folder info if on folder page (needed by layout_header for toolbar)
if ($page === 'folder' && isset($_GET['fid'])) {
    $folder = db_fetch_one($conn, "SELECT * FROM mail_folders WHERE id = ? AND user_id = ?", array(intval($_GET['fid']), auth_user_id()));
}

include __DIR__ . '/includes/layout_header.php';
include $pageFile;
include __DIR__ . '/includes/layout_footer.php';
