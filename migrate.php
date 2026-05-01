<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$msgs = array();
// Add is_retracted column
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_messages','is_retracted') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_messages ADD is_retracted BIT NOT NULL DEFAULT 0");
    $msgs[] = $r ? 'Added is_retracted column' : 'FAILED is_retracted';
    if ($r) sqlsrv_free_stmt($r);
} else { $msgs[] = 'is_retracted already exists'; }
echo '<pre>Migration results: ' . implode("\n", $msgs) . '</pre>';
echo '<a href="index.php">Go to app</a>';
