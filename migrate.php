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

echo '<pre>Migration results: ' . implode("\n", $msgs) . '</pre>';
echo '<a href="index.php">Go to app</a>';
