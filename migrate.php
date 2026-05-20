<?php
require_once __DIR__ . '/koneksi.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
$msgs = array();

// ── Existing migration: is_retracted column ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_messages','is_retracted') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_messages ADD is_retracted BIT NOT NULL DEFAULT 0");
    $msgs[] = $r ? 'Added is_retracted column' : 'FAILED is_retracted';
    if ($r) sqlsrv_free_stmt($r);
} else { $msgs[] = 'is_retracted already exists'; }

// ── Calendar tables ──

// cal_events
if (!db_table_exists($conn, 'cal_events')) {
    $sql = "CREATE TABLE cal_events (
        id INT IDENTITY(1,1) PRIMARY KEY,
        creator_id INT NOT NULL,
        title NVARCHAR(500) NOT NULL,
        description NVARCHAR(MAX) NULL,
        location NVARCHAR(500) NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        all_day BIT NOT NULL DEFAULT 0,
        importance NVARCHAR(10) NOT NULL DEFAULT 'normal',
        color NVARCHAR(20) NOT NULL DEFAULT '#6366f1',
        recurrence_rule NVARCHAR(200) NULL,
        recurrence_end DATETIME NULL,
        recurrence_parent_id INT NULL,
        reminder_minutes INT NOT NULL DEFAULT 15,
        is_cancelled BIT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_cal_events_creator FOREIGN KEY (creator_id) REFERENCES mail_users(id),
        CONSTRAINT FK_cal_events_parent FOREIGN KEY (recurrence_parent_id) REFERENCES cal_events(id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: cal_events' : 'FAILED cal_events: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);

    // Indexes for cal_events
    $idxs = array(
        "CREATE INDEX IX_cal_events_creator ON cal_events(creator_id)",
        "CREATE INDEX IX_cal_events_start ON cal_events(start_time)",
        "CREATE INDEX IX_cal_events_end ON cal_events(end_time)",
        "CREATE INDEX IX_cal_events_parent ON cal_events(recurrence_parent_id)",
        "CREATE INDEX IX_cal_events_cancelled ON cal_events(is_cancelled)"
    );
    foreach ($idxs as $ix) {
        $r = sqlsrv_query($conn, $ix);
        if ($r) sqlsrv_free_stmt($r);
    }
    $msgs[] = 'Created indexes for cal_events';
} else {
    $msgs[] = 'cal_events already exists';
}

// cal_attendees
if (!db_table_exists($conn, 'cal_attendees')) {
    $sql = "CREATE TABLE cal_attendees (
        id INT IDENTITY(1,1) PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        status NVARCHAR(20) NOT NULL DEFAULT 'pending',
        notified BIT NOT NULL DEFAULT 0,
        responded_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_cal_att_event FOREIGN KEY (event_id) REFERENCES cal_events(id) ON DELETE CASCADE,
        CONSTRAINT FK_cal_att_user FOREIGN KEY (user_id) REFERENCES mail_users(id),
        CONSTRAINT UQ_cal_attendee UNIQUE(event_id, user_id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: cal_attendees' : 'FAILED cal_attendees: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);

    $idxs = array(
        "CREATE INDEX IX_cal_att_event ON cal_attendees(event_id)",
        "CREATE INDEX IX_cal_att_user ON cal_attendees(user_id)",
        "CREATE INDEX IX_cal_att_status ON cal_attendees(status)"
    );
    foreach ($idxs as $ix) {
        $r = sqlsrv_query($conn, $ix);
        if ($r) sqlsrv_free_stmt($r);
    }
    $msgs[] = 'Created indexes for cal_attendees';
} else {
    $msgs[] = 'cal_attendees already exists';
}

// cal_reminders_sent
if (!db_table_exists($conn, 'cal_reminders_sent')) {
    $sql = "CREATE TABLE cal_reminders_sent (
        id INT IDENTITY(1,1) PRIMARY KEY,
        event_id INT NOT NULL,
        user_id INT NOT NULL,
        reminder_time DATETIME NOT NULL,
        sent_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_cal_rem_event FOREIGN KEY (event_id) REFERENCES cal_events(id) ON DELETE CASCADE,
        CONSTRAINT FK_cal_rem_user FOREIGN KEY (user_id) REFERENCES mail_users(id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: cal_reminders_sent' : 'FAILED cal_reminders_sent: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);

    $idxs = array(
        "CREATE INDEX IX_cal_rem_event ON cal_reminders_sent(event_id)",
        "CREATE INDEX IX_cal_rem_user ON cal_reminders_sent(user_id)"
    );
    foreach ($idxs as $ix) {
        $r = sqlsrv_query($conn, $ix);
        if ($r) sqlsrv_free_stmt($r);
    }
    $msgs[] = 'Created indexes for cal_reminders_sent';
} else {
    $msgs[] = 'cal_reminders_sent already exists';
}

// ── Folders table ──
if (!db_table_exists($conn, 'mail_folders')) {
    $sql = "CREATE TABLE mail_folders (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        name NVARCHAR(100) NOT NULL,
        color NVARCHAR(20) NOT NULL DEFAULT '#6366f1',
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_folder_user FOREIGN KEY (user_id) REFERENCES mail_users(id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: mail_folders' : 'FAILED mail_folders: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
    $idxs = array(
        "CREATE INDEX IX_folder_user ON mail_folders(user_id)",
        "CREATE UNIQUE INDEX IX_folder_user_name ON mail_folders(user_id, name)"
    );
    foreach ($idxs as $ix) { $r = sqlsrv_query($conn, $ix); if ($r) sqlsrv_free_stmt($r); }
    $msgs[] = 'Created indexes for mail_folders';
} else {
    $msgs[] = 'mail_folders already exists';
}

// ── folder_id column on mail_recipients ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_recipients','folder_id') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_recipients ADD folder_id INT NULL");
    $msgs[] = $r ? 'Added folder_id column to mail_recipients' : 'FAILED folder_id';
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_recip_folder ON mail_recipients(folder_id)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'folder_id already exists on mail_recipients'; }

// ── reply_to_id column on mail_messages ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_messages','reply_to_id') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_messages ADD reply_to_id INT NULL");
    $msgs[] = $r ? 'Added reply_to_id column to mail_messages' : 'FAILED reply_to_id';
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_msg_reply_to ON mail_messages(reply_to_id)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'reply_to_id already exists on mail_messages'; }

// ── forwarded_from_id column on mail_messages ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_messages','forwarded_from_id') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_messages ADD forwarded_from_id INT NULL");
    $msgs[] = $r ? 'Added forwarded_from_id column to mail_messages' : 'FAILED forwarded_from_id';
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_msg_forwarded_from ON mail_messages(forwarded_from_id)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'forwarded_from_id already exists on mail_messages'; }


// ── Tags table ──
if (!db_table_exists($conn, 'mail_tags')) {
    $sql = "CREATE TABLE mail_tags (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        name NVARCHAR(50) NOT NULL,
        color NVARCHAR(20) NOT NULL DEFAULT '#6366f1',
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_tag_user FOREIGN KEY (user_id) REFERENCES mail_users(id),
        CONSTRAINT UQ_tag_user_name UNIQUE(user_id, name)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: mail_tags' : 'FAILED mail_tags: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_tag_user ON mail_tags(user_id)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'mail_tags already exists'; }

// ── Message-Tag join table ──
if (!db_table_exists($conn, 'mail_message_tags')) {
    $sql = "CREATE TABLE mail_message_tags (
        id INT IDENTITY(1,1) PRIMARY KEY,
        message_id INT NOT NULL,
        tag_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_msgtag_msg FOREIGN KEY (message_id) REFERENCES mail_messages(id),
        CONSTRAINT FK_msgtag_tag FOREIGN KEY (tag_id) REFERENCES mail_tags(id) ON DELETE CASCADE,
        CONSTRAINT FK_msgtag_user FOREIGN KEY (user_id) REFERENCES mail_users(id),
        CONSTRAINT UQ_msgtag UNIQUE(message_id, tag_id, user_id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: mail_message_tags' : 'FAILED mail_message_tags: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
    $idxs = array(
        "CREATE INDEX IX_msgtag_msg ON mail_message_tags(message_id)",
        "CREATE INDEX IX_msgtag_tag ON mail_message_tags(tag_id)",
        "CREATE INDEX IX_msgtag_user ON mail_message_tags(user_id)"
    );
    foreach ($idxs as $ix) { $r = sqlsrv_query($conn, $ix); if ($r) sqlsrv_free_stmt($r); }
    $msgs[] = 'Created indexes for mail_message_tags';
} else { $msgs[] = 'mail_message_tags already exists'; }

// ── Inbox Rules table ──
if (!db_table_exists($conn, 'mail_rules')) {
    $sql = "CREATE TABLE mail_rules (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        name NVARCHAR(200) NOT NULL,
        is_active BIT NOT NULL DEFAULT 1,
        match_type NVARCHAR(10) NOT NULL DEFAULT 'all',
        conditions NVARCHAR(MAX) NOT NULL,
        actions NVARCHAR(MAX) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        updated_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_rule_user FOREIGN KEY (user_id) REFERENCES mail_users(id)
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: mail_rules' : 'FAILED mail_rules: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_rule_user ON mail_rules(user_id)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'mail_rules already exists'; }

// ── scheduled_at column on mail_messages ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_messages','scheduled_at') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_messages ADD scheduled_at DATETIME NULL");
    $msgs[] = $r ? 'Added scheduled_at column to mail_messages' : 'FAILED scheduled_at';
    if ($r) sqlsrv_free_stmt($r);
    $r2 = sqlsrv_query($conn, "CREATE INDEX IX_msg_scheduled ON mail_messages(scheduled_at)");
    if ($r2) sqlsrv_free_stmt($r2);
} else { $msgs[] = 'scheduled_at already exists on mail_messages'; }

// ── email_footer column on mail_users ──
$check = db_fetch_one($conn, "SELECT COL_LENGTH('mail_users','email_footer') AS len");
if (!$check || $check['len'] === null) {
    $r = sqlsrv_query($conn, "ALTER TABLE mail_users ADD email_footer NVARCHAR(MAX) NULL");
    $msgs[] = $r ? 'Added email_footer column to mail_users' : 'FAILED email_footer: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
} else { $msgs[] = 'email_footer already exists on mail_users'; }

// ── Remember Me tokens table ──
if (!db_table_exists($conn, 'mail_remember_tokens')) {
    $sql = "CREATE TABLE mail_remember_tokens (
        id INT IDENTITY(1,1) PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash NVARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_remember_user FOREIGN KEY (user_id) REFERENCES mail_users(id) ON DELETE CASCADE
    )";
    $r = sqlsrv_query($conn, $sql);
    $msgs[] = $r ? 'Created table: mail_remember_tokens' : 'FAILED mail_remember_tokens: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
    $idxs = array(
        "CREATE INDEX IX_remember_user ON mail_remember_tokens(user_id)",
        "CREATE INDEX IX_remember_token ON mail_remember_tokens(token_hash)",
        "CREATE INDEX IX_remember_expires ON mail_remember_tokens(expires_at)"
    );
    foreach ($idxs as $ix) { $r = sqlsrv_query($conn, $ix); if ($r) sqlsrv_free_stmt($r); }
    $msgs[] = 'Created indexes for mail_remember_tokens';
} else { $msgs[] = 'mail_remember_tokens already exists'; }

// ── Index on mail_attachments(created_at) ──
$check = db_fetch_one($conn, "SELECT 1 FROM sys.indexes WHERE name = 'IX_attach_created_at' AND object_id = OBJECT_ID('mail_attachments')");
if (!$check) {
    $r = sqlsrv_query($conn, "CREATE INDEX IX_attach_created_at ON mail_attachments(created_at)");
    $msgs[] = $r ? 'Created index IX_attach_created_at' : 'FAILED IX_attach_created_at: ' . print_r(sqlsrv_errors(), true);
    if ($r) sqlsrv_free_stmt($r);
} else {
    $msgs[] = 'IX_attach_created_at already exists';
}

echo '<pre>Migration results: ' . implode("\n", $msgs) . '</pre>';
echo '<a href="index.php">Go to app</a>';

