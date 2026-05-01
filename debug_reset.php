<?php
// Quick debug/reset script - delete after use
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Delete existing admin user so we can re-register
db_execute($conn, "DELETE FROM mail_contacts WHERE owner_id IN (SELECT id FROM mail_users WHERE username='admin') OR contact_user_id IN (SELECT id FROM mail_users WHERE username='admin')");
db_execute($conn, "DELETE FROM mail_recipients WHERE recipient_id IN (SELECT id FROM mail_users WHERE username='admin')");
db_execute($conn, "DELETE FROM mail_messages WHERE sender_id IN (SELECT id FROM mail_users WHERE username='admin')");
db_execute($conn, "DELETE FROM mail_users WHERE username = 'admin'");

echo "Done. Admin user deleted. <a href='index.php?page=register'>Register now</a>";
