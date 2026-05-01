<?php
/**
 * LAN Mail - Database Installation Script
 * Creates all required tables in the INBOX database.
 */
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$messages = array();
$errors = array();

// Check if already installed
if (db_table_exists($conn, 'mail_users')) {
    $messages[] = 'Tables already exist. To reinstall, drop all mail_* tables first.';
} else {
    $tables = array(
        'mail_users' => "
            CREATE TABLE mail_users (
                id INT IDENTITY(1,1) PRIMARY KEY,
                username NVARCHAR(50) NOT NULL,
                display_name NVARCHAR(100) NOT NULL,
                password_hash NVARCHAR(255) NOT NULL,
                role NVARCHAR(20) NOT NULL DEFAULT 'user',
                is_active BIT NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT GETDATE(),
                last_login DATETIME NULL,
                CONSTRAINT UQ_mail_users_username UNIQUE(username)
            )
        ",
        'mail_messages' => "
            CREATE TABLE mail_messages (
                id INT IDENTITY(1,1) PRIMARY KEY,
                sender_id INT NOT NULL,
                subject NVARCHAR(500) NOT NULL DEFAULT '(No Subject)',
                body NVARCHAR(MAX) NULL,
                is_draft BIT NOT NULL DEFAULT 0,
                has_attachments BIT NOT NULL DEFAULT 0,
                sender_deleted BIT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT GETDATE(),
                sent_at DATETIME NULL,
                CONSTRAINT FK_msg_sender FOREIGN KEY (sender_id) REFERENCES mail_users(id)
            )
        ",
        'mail_recipients' => "
            CREATE TABLE mail_recipients (
                id INT IDENTITY(1,1) PRIMARY KEY,
                message_id INT NOT NULL,
                recipient_id INT NOT NULL,
                recipient_type NVARCHAR(3) NOT NULL DEFAULT 'to',
                is_read BIT NOT NULL DEFAULT 0,
                is_starred BIT NOT NULL DEFAULT 0,
                is_deleted BIT NOT NULL DEFAULT 0,
                read_at DATETIME NULL,
                deleted_at DATETIME NULL,
                CONSTRAINT FK_recip_msg FOREIGN KEY (message_id) REFERENCES mail_messages(id),
                CONSTRAINT FK_recip_user FOREIGN KEY (recipient_id) REFERENCES mail_users(id)
            )
        ",
        'mail_attachments' => "
            CREATE TABLE mail_attachments (
                id INT IDENTITY(1,1) PRIMARY KEY,
                message_id INT NOT NULL,
                original_name NVARCHAR(255) NOT NULL,
                stored_name NVARCHAR(255) NOT NULL,
                file_size BIGINT NOT NULL DEFAULT 0,
                mime_type NVARCHAR(100) NOT NULL DEFAULT 'application/octet-stream',
                created_at DATETIME NOT NULL DEFAULT GETDATE(),
                CONSTRAINT FK_attach_msg FOREIGN KEY (message_id) REFERENCES mail_messages(id)
            )
        ",
        'mail_contacts' => "
            CREATE TABLE mail_contacts (
                id INT IDENTITY(1,1) PRIMARY KEY,
                owner_id INT NOT NULL,
                contact_user_id INT NOT NULL,
                nickname NVARCHAR(100) NULL,
                notes NVARCHAR(MAX) NULL,
                created_at DATETIME NOT NULL DEFAULT GETDATE(),
                CONSTRAINT FK_contact_owner FOREIGN KEY (owner_id) REFERENCES mail_users(id),
                CONSTRAINT FK_contact_user FOREIGN KEY (contact_user_id) REFERENCES mail_users(id),
                CONSTRAINT UQ_contacts UNIQUE(owner_id, contact_user_id)
            )
        "
    );

    foreach ($tables as $name => $sql) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            $errors[] = "Failed to create $name: " . print_r(sqlsrv_errors(), true);
        } else {
            $messages[] = "Created table: $name";
            sqlsrv_free_stmt($stmt);
        }
    }

    // Create indexes
    $indexes = array(
        "CREATE INDEX IX_msg_sender ON mail_messages(sender_id)",
        "CREATE INDEX IX_msg_draft ON mail_messages(is_draft)",
        "CREATE INDEX IX_msg_sent ON mail_messages(sent_at)",
        "CREATE INDEX IX_recip_user ON mail_recipients(recipient_id)",
        "CREATE INDEX IX_recip_msg ON mail_recipients(message_id)",
        "CREATE INDEX IX_recip_read ON mail_recipients(is_read)",
        "CREATE INDEX IX_recip_del ON mail_recipients(is_deleted)",
        "CREATE INDEX IX_attach_msg ON mail_attachments(message_id)",
        "CREATE INDEX IX_contact_owner ON mail_contacts(owner_id)"
    );
    foreach ($indexes as $sql) {
        $stmt = sqlsrv_query($conn, $sql);
        if ($stmt === false) {
            $errors[] = "Index error: " . print_r(sqlsrv_errors(), true);
        } else {
            sqlsrv_free_stmt($stmt);
        }
    }

    if (empty($errors)) {
        $messages[] = '';
        $messages[] = 'Installation complete! You can now register your first account.';
        $messages[] = 'The first registered user will automatically become an administrator.';
    }
}

// Create uploads directory
if (!is_dir(__DIR__ . '/uploads')) {
    mkdir(__DIR__ . '/uploads', 0755, true);
    $messages[] = 'Created uploads directory.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>LAN Mail - Installation</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #0f0f1a; color: #e2e8f0; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .box { background: #1a1a2e; border-radius: 16px; padding: 40px; max-width: 600px; width: 100%; box-shadow: 0 8px 32px rgba(0,0,0,0.4); }
        h1 { color: #818cf8; margin-top: 0; }
        .msg { padding: 8px 12px; margin: 4px 0; background: #16213e; border-radius: 8px; font-family: monospace; font-size: 13px; }
        .err { background: #3b1122; color: #f87171; }
        a { color: #818cf8; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        .btn { display: inline-block; margin-top: 20px; padding: 12px 24px; background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; border-radius: 10px; font-weight: 600; }
    </style>
</head>
<body>
<div class="box">
    <h1>&#x1F4E8; LAN Mail Installation</h1>
    <?php foreach ($messages as $m): ?>
        <div class="msg"><?php echo e($m); ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
        <div class="msg err"><?php echo e($e); ?></div>
    <?php endforeach; ?>
    <a class="btn" href="index.php?page=register">Register First Account &rarr;</a>
</div>
</body>
</html>
