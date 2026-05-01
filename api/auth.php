<?php
/**
 * Auth API - logout endpoint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_start_session();

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'logout') {
    auth_logout();
    header('Location: ../index.php?page=login');
    exit;
}
