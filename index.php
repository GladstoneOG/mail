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

// Check if installed
if (!db_table_exists($conn, 'mail_users')) {
    header('Location: install.php');
    exit;
}

$page = isset($_GET['page']) ? $_GET['page'] : 'inbox';
$publicPages = array('login');
$validPages = array('login','register','inbox','compose','view','sent','drafts','trash','starred','contacts','profile','admin','outbox','calendar');

if (!in_array($page, $validPages)) {
    $page = 'inbox';
}

// Auth check
if (!in_array($page, $publicPages) && !auth_is_logged_in()) {
    redirect('index.php?page=login');
}

// Register page is admin-only
if ($page === 'register' && auth_is_logged_in() && !auth_is_admin()) {
    redirect('index.php?page=inbox');
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

include __DIR__ . '/includes/layout_header.php';
include $pageFile;
include __DIR__ . '/includes/layout_footer.php';
