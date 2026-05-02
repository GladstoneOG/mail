<?php
/**
 * Calendar API - CRUD events, RSVP, reminders
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../koneksi.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
auth_start_session();
if (!auth_is_logged_in()) json_response(array('error' => 'Not authenticated'), 401);

$userId = auth_user_id();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {

case 'get_events':
    $start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-01');
    $end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-t');
    $events = db_fetch_all($conn,
        "SELECT e.*, u.display_name AS creator_name,
                (SELECT COUNT(*) FROM cal_attendees WHERE event_id = e.id) AS attendee_count,
                ca.status AS my_status
         FROM cal_events e
         JOIN mail_users u ON e.creator_id = u.id
         LEFT JOIN cal_attendees ca ON ca.event_id = e.id AND ca.user_id = ?
         WHERE e.is_cancelled = 0
           AND e.start_time < ? AND e.end_time > ?
           AND (e.creator_id = ? OR EXISTS (SELECT 1 FROM cal_attendees a2 WHERE a2.event_id = e.id AND a2.user_id = ? AND a2.status != 'declined'))
         ORDER BY e.start_time",
        array($userId, $end . ' 23:59:59', $start . ' 00:00:00', $userId, $userId));
    $result = array();
    foreach ($events as $ev) {
        $result[] = format_event($ev);
    }
    json_response(array('events' => $result));
    break;

case 'get_event':
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$id) json_response(array('error' => 'Missing event ID'), 400);
    $ev = db_fetch_one($conn,
        "SELECT e.*, u.display_name AS creator_name, u.username AS creator_username
         FROM cal_events e JOIN mail_users u ON e.creator_id = u.id WHERE e.id = ?", array($id));
    if (!$ev) json_response(array('error' => 'Event not found'), 404);
    $attendees = db_fetch_all($conn,
        "SELECT ca.*, u.display_name, u.username FROM cal_attendees ca
         JOIN mail_users u ON ca.user_id = u.id WHERE ca.event_id = ? ORDER BY u.display_name", array($id));
    $data = format_event($ev);
    $data['description'] = $ev['description'];
    $data['creator_username'] = $ev['creator_username'];
    $data['attendees'] = array();
    foreach ($attendees as $a) {
        $data['attendees'][] = array(
            'id' => $a['id'], 'user_id' => intval($a['user_id']),
            'display_name' => $a['display_name'], 'username' => $a['username'],
            'status' => $a['status'], 'initials' => get_initials($a['display_name']),
            'color' => get_avatar_color($a['display_name'])
        );
    }
    json_response(array('event' => $data));
    break;

case 'create_event':
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    if (!$title) json_response(array('error' => 'Title is required'), 400);
    $desc = isset($_POST['description']) ? $_POST['description'] : '';
    $location = isset($_POST['location']) ? trim($_POST['location']) : '';
    $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : '';
    $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : '';
    $allDay = isset($_POST['all_day']) ? intval($_POST['all_day']) : 0;
    $importance = isset($_POST['importance']) ? $_POST['importance'] : 'normal';
    $color = isset($_POST['color']) ? $_POST['color'] : '#6366f1';
    $recRule = isset($_POST['recurrence_rule']) ? $_POST['recurrence_rule'] : '';
    $recEnd = isset($_POST['recurrence_end']) ? $_POST['recurrence_end'] : '';
    $reminder = isset($_POST['reminder_minutes']) ? intval($_POST['reminder_minutes']) : 15;
    $attendeeStr = isset($_POST['attendees']) ? trim($_POST['attendees']) : '';

    if (!$startTime || !$endTime) json_response(array('error' => 'Start and end times are required'), 400);
    if (!in_array($importance, array('low','normal','high'))) $importance = 'normal';

    $eventId = db_insert_get_id($conn,
        "INSERT INTO cal_events (creator_id,title,description,location,start_time,end_time,all_day,importance,color,recurrence_rule,recurrence_end,reminder_minutes,created_at,updated_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,GETDATE(),GETDATE())",
        array($userId, $title, $desc, $location, $startTime, $endTime, $allDay, $importance, $color,
              $recRule ? $recRule : null, $recEnd ? $recEnd : null, $reminder));
    if (!$eventId) json_response(array('error' => 'Failed to create event'), 500);

    // Generate recurrence occurrences
    if ($recRule) {
        generate_recurrences($conn, $eventId, $title, $desc, $location, $startTime, $endTime, $allDay, $importance, $color, $recRule, $recEnd, $reminder, $userId);
    }

    // Add attendees and send invitations
    $attendeeIds = array();
    if ($attendeeStr) {
        $usernames = array_map('trim', explode(',', $attendeeStr));
        $creator = auth_user();
        foreach ($usernames as $uname) {
            if (!$uname) continue;
            $u = db_fetch_one($conn, "SELECT id FROM mail_users WHERE username = ? AND is_active = 1", array($uname));
            if (!$u || intval($u['id']) === $userId) continue;
            $attendeeIds[] = intval($u['id']);
            db_execute($conn, "INSERT INTO cal_attendees (event_id,user_id,status,notified) VALUES (?,?,'pending',1)", array($eventId, $u['id']));
            send_event_invitation($conn, $eventId, $title, $startTime, $endTime, $location, $creator['display_name'], $userId, intval($u['id']));
        }
    }

    // Copy attendees to recurrence children
    if ($recRule && !empty($attendeeIds)) {
        $children = db_fetch_all($conn, "SELECT id FROM cal_events WHERE recurrence_parent_id = ?", array($eventId));
        foreach ($children as $child) {
            foreach ($attendeeIds as $auid) {
                db_execute($conn, "INSERT INTO cal_attendees (event_id,user_id,status,notified) VALUES (?,?,'pending',0)", array($child['id'], $auid));
            }
        }
    }
    json_response(array('success' => true, 'id' => $eventId));
    break;

case 'update_event':
    $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$id) json_response(array('error' => 'Missing event ID'), 400);
    $ev = db_fetch_one($conn, "SELECT * FROM cal_events WHERE id = ? AND creator_id = ?", array($id, $userId));
    if (!$ev) json_response(array('error' => 'Not authorized or not found'), 403);

    $title = isset($_POST['title']) ? trim($_POST['title']) : $ev['title'];
    $desc = isset($_POST['description']) ? $_POST['description'] : $ev['description'];
    $location = isset($_POST['location']) ? trim($_POST['location']) : $ev['location'];
    $startTime = isset($_POST['start_time']) ? $_POST['start_time'] : $ev['start_time'];
    $endTime = isset($_POST['end_time']) ? $_POST['end_time'] : $ev['end_time'];
    $allDay = isset($_POST['all_day']) ? intval($_POST['all_day']) : $ev['all_day'];
    $importance = isset($_POST['importance']) ? $_POST['importance'] : $ev['importance'];
    $color = isset($_POST['color']) ? $_POST['color'] : $ev['color'];
    $reminder = isset($_POST['reminder_minutes']) ? intval($_POST['reminder_minutes']) : $ev['reminder_minutes'];

    db_execute($conn,
        "UPDATE cal_events SET title=?,description=?,location=?,start_time=?,end_time=?,all_day=?,importance=?,color=?,reminder_minutes=?,updated_at=GETDATE() WHERE id=?",
        array($title, $desc, $location, $startTime, $endTime, $allDay, $importance, $color, $reminder, $id));

    // Update attendees if provided
    $attendeeStr = isset($_POST['attendees']) ? trim($_POST['attendees']) : '';
    if ($attendeeStr !== '') {
        $newUsernames = array_filter(array_map('trim', explode(',', $attendeeStr)));
        $existing = db_fetch_all($conn, "SELECT ca.user_id, u.username FROM cal_attendees ca JOIN mail_users u ON ca.user_id=u.id WHERE ca.event_id=?", array($id));
        $existingMap = array();
        foreach ($existing as $ex) $existingMap[$ex['username']] = intval($ex['user_id']);
        // Remove attendees not in new list
        foreach ($existingMap as $uname => $uid) {
            if (!in_array($uname, $newUsernames)) {
                db_execute($conn, "DELETE FROM cal_attendees WHERE event_id=? AND user_id=?", array($id, $uid));
            }
        }
        // Add new attendees
        $creator = auth_user();
        foreach ($newUsernames as $uname) {
            if (!$uname || isset($existingMap[$uname])) continue;
            $u = db_fetch_one($conn, "SELECT id FROM mail_users WHERE username=? AND is_active=1", array($uname));
            if (!$u || intval($u['id']) === $userId) continue;
            db_execute($conn, "INSERT INTO cal_attendees (event_id,user_id,status,notified) VALUES (?,?,'pending',1)", array($id, $u['id']));
            send_event_invitation($conn, $id, $title, $startTime, $endTime, $location, $creator['display_name'], $userId, intval($u['id']));
        }
    }
    json_response(array('success' => true));
    break;

case 'delete_event':
    $id = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    if (!$id) json_response(array('error' => 'Missing event ID'), 400);
    $ev = db_fetch_one($conn, "SELECT id, recurrence_parent_id FROM cal_events WHERE id = ? AND creator_id = ?", array($id, $userId));
    if (!$ev) json_response(array('error' => 'Not authorized'), 403);
    // Always delete entire series: cancel this event + all children + siblings
    db_execute($conn, "UPDATE cal_events SET is_cancelled = 1 WHERE id = ?", array($id));
    // Cancel all children (if this is a parent)
    db_execute($conn, "UPDATE cal_events SET is_cancelled = 1 WHERE recurrence_parent_id = ?", array($id));
    // If this is a child, cancel parent + all siblings too
    if ($ev['recurrence_parent_id']) {
        $parentId = intval($ev['recurrence_parent_id']);
        db_execute($conn, "UPDATE cal_events SET is_cancelled = 1 WHERE id = ?", array($parentId));
        db_execute($conn, "UPDATE cal_events SET is_cancelled = 1 WHERE recurrence_parent_id = ?", array($parentId));
    }
    json_response(array('success' => true));
    break;

case 'respond':
    $eventId = isset($_POST['event_id']) ? intval($_POST['event_id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    if (!$eventId || !in_array($status, array('accepted','declined','tentative'))) {
        json_response(array('error' => 'Invalid request'), 400);
    }
    db_execute($conn, "UPDATE cal_attendees SET status=?, responded_at=GETDATE() WHERE event_id=? AND user_id=?",
        array($status, $eventId, $userId));
    json_response(array('success' => true, 'status' => $status));
    break;

case 'get_today_reminders':
    $today = date('Y-m-d');
    $events = db_fetch_all($conn,
        "SELECT e.*, u.display_name AS creator_name
         FROM cal_events e JOIN mail_users u ON e.creator_id = u.id
         WHERE e.is_cancelled = 0 AND CONVERT(DATE, e.start_time) = ?
           AND (e.creator_id = ? OR EXISTS (SELECT 1 FROM cal_attendees a2 WHERE a2.event_id = e.id AND a2.user_id = ? AND a2.status != 'declined'))
         ORDER BY e.start_time",
        array($today, $userId, $userId));
    $result = array();
    foreach ($events as $ev) $result[] = format_event($ev);
    json_response(array('events' => $result, 'count' => count($result)));
    break;

case 'check_reminders':
    $now = date('Y-m-d H:i:s');
    // Find events starting soon that need reminders
    $events = db_fetch_all($conn,
        "SELECT e.id, e.title, e.start_time, e.reminder_minutes
         FROM cal_events e
         WHERE e.is_cancelled = 0 AND e.reminder_minutes > 0
           AND DATEADD(MINUTE, -e.reminder_minutes, e.start_time) <= ?
           AND e.start_time > ?
           AND (e.creator_id = ? OR EXISTS (SELECT 1 FROM cal_attendees a2 WHERE a2.event_id = e.id AND a2.user_id = ? AND a2.status != 'declined'))
           AND NOT EXISTS (SELECT 1 FROM cal_reminders_sent rs WHERE rs.event_id = e.id AND rs.user_id = ?)
         ORDER BY e.start_time",
        array($now, $now, $userId, $userId, $userId));
    $reminders = array();
    foreach ($events as $ev) {
        $minutesUntil = max(0, intval((strtotime($ev['start_time']) - time()) / 60));
        $reminders[] = array('id' => intval($ev['id']), 'title' => $ev['title'],
            'start_time' => $ev['start_time'], 'minutes_until' => $minutesUntil);
        // Mark as sent
        db_execute($conn, "INSERT INTO cal_reminders_sent (event_id,user_id,reminder_time,sent_at) VALUES (?,?,?,GETDATE())",
            array($ev['id'], $userId, $now));
    }
    json_response(array('reminders' => $reminders));
    break;

case 'get_agenda':
    $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime("+{$days} days"));
    $events = db_fetch_all($conn,
        "SELECT e.*, u.display_name AS creator_name,
                (SELECT COUNT(*) FROM cal_attendees WHERE event_id = e.id) AS attendee_count,
                ca.status AS my_status
         FROM cal_events e JOIN mail_users u ON e.creator_id = u.id
         LEFT JOIN cal_attendees ca ON ca.event_id = e.id AND ca.user_id = ?
         WHERE e.is_cancelled = 0 AND e.start_time >= ? AND e.start_time <= ?
           AND (e.creator_id = ? OR EXISTS (SELECT 1 FROM cal_attendees a2 WHERE a2.event_id = e.id AND a2.user_id = ? AND a2.status != 'declined'))
         ORDER BY e.start_time",
        array($userId, $from . ' 00:00:00', $to . ' 23:59:59', $userId, $userId));
    $result = array();
    foreach ($events as $ev) $result[] = format_event($ev);
    json_response(array('events' => $result));
    break;

default:
    json_response(array('error' => 'Invalid action'), 400);
}

// ── Helper functions ──

function format_event($ev) {
    $startTs = strtotime($ev['start_time']);
    $endTs = strtotime($ev['end_time']);
    return array(
        'id' => intval($ev['id']),
        'title' => $ev['title'],
        'location' => isset($ev['location']) ? $ev['location'] : '',
        'start_time' => $ev['start_time'],
        'end_time' => $ev['end_time'],
        'start_date' => date('Y-m-d', $startTs),
        'start_hour' => date('H:i', $startTs),
        'end_hour' => date('H:i', $endTs),
        'all_day' => intval($ev['all_day']),
        'importance' => $ev['importance'],
        'color' => $ev['color'],
        'reminder_minutes' => intval($ev['reminder_minutes']),
        'creator_id' => intval($ev['creator_id']),
        'creator_name' => isset($ev['creator_name']) ? $ev['creator_name'] : '',
        'attendee_count' => isset($ev['attendee_count']) ? intval($ev['attendee_count']) : 0,
        'my_status' => isset($ev['my_status']) ? $ev['my_status'] : null,
        'recurrence_rule' => isset($ev['recurrence_rule']) ? $ev['recurrence_rule'] : null,
        'recurrence_parent_id' => isset($ev['recurrence_parent_id']) ? $ev['recurrence_parent_id'] : null,
        'is_mine' => intval($ev['creator_id']) === $GLOBALS['userId'] ? 1 : 0
    );
}

function send_event_invitation($conn, $eventId, $title, $startTime, $endTime, $location, $creatorName, $senderId, $recipientId) {
    $startFmt = date('l, M j, Y g:i A', strtotime($startTime));
    $endFmt = date('g:i A', strtotime($endTime));
    $eTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $eLoc = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
    $eCreator = htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8');
    $locHtml = $location ? "<p style='margin:6px 0;color:#e2e8f0;font-size:14px'><b style='color:#94a3b8'>Where:</b> {$eLoc}</p>" : '';
    $body = "<div style='padding:20px 24px;background:#12121f;border:1px solid #2a2a45;border-radius:12px;margin:8px 0;color:#e2e8f0;font-family:Inter,Segoe UI,sans-serif'>"
          . "<div style='font-size:28px;margin-bottom:4px'>📅</div>"
          . "<h3 style='margin:0 0 16px;color:#818cf8;font-size:18px;font-weight:700'>Event Invitation</h3>"
          . "<div style='border-left:3px solid #6366f1;padding-left:14px;margin-bottom:12px'>"
          . "<p style='margin:6px 0;color:#e2e8f0;font-size:14px'><b style='color:#94a3b8'>What:</b> {$eTitle}</p>"
          . "<p style='margin:6px 0;color:#e2e8f0;font-size:14px'><b style='color:#94a3b8'>When:</b> {$startFmt} – {$endFmt}</p>"
          . $locHtml
          . "<p style='margin:6px 0;color:#e2e8f0;font-size:14px'><b style='color:#94a3b8'>From:</b> {$eCreator}</p>"
          . "</div>"
          . "<p style='margin-top:16px;font-size:13px;color:#64748b'>Open the Calendar to respond to this invitation.</p>"
          . "<p style='margin-top:10px'><a href='index.php?page=calendar' style='display:inline-block;padding:8px 20px;background:linear-gradient(135deg,#6366f1,#7c3aed);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px'>Open Calendar →</a></p>"
          . "</div>";
    $subject = "📅 Event Invitation: " . $title;
    $msgId = db_insert_get_id($conn,
        "INSERT INTO mail_messages (sender_id,subject,body,is_draft,has_attachments,sender_deleted,created_at,sent_at) VALUES (?,?,?,0,0,0,GETDATE(),GETDATE())",
        array($senderId, $subject, $body));
    if ($msgId) {
        db_execute($conn, "INSERT INTO mail_recipients (message_id,recipient_id,recipient_type,is_read) VALUES (?,?,'to',0)",
            array($msgId, $recipientId));
    }
}

function generate_recurrences($conn, $parentId, $title, $desc, $location, $startTime, $endTime, $allDay, $importance, $color, $rule, $recEnd, $reminder, $creatorId) {
    $startTs = strtotime($startTime);
    $endTs = strtotime($endTime);
    $duration = $endTs - $startTs;
    $maxEnd = $recEnd ? strtotime($recEnd) : strtotime('+1 year', $startTs);
    $dates = array();
    $parts = explode(':', $rule);
    $type = strtoupper($parts[0]);
    $param = isset($parts[1]) ? $parts[1] : '';

    $current = $startTs;
    $count = 0;
    $maxOccurrences = 365;
    while ($count < $maxOccurrences) {
        if ($type === 'DAILY') {
            $current = strtotime('+1 day', $current);
        } elseif ($type === 'WEEKLY') {
            $current = strtotime('+1 day', $current);
            if ($param) {
                $allowedDays = array_map('intval', explode(',', $param));
                while (!in_array(intval(date('w', $current)), $allowedDays)) {
                    $current = strtotime('+1 day', $current);
                }
            } else {
                $current = strtotime('+7 days', $current - 6 * 86400);
                $current = strtotime('+6 days', $current);
            }
        } elseif ($type === 'MONTHLY') {
            $current = strtotime('+1 month', $current);
        } elseif ($type === 'YEARLY') {
            $current = strtotime('+1 year', $current);
        } else {
            break;
        }
        if ($current > $maxEnd) break;
        $dates[] = $current;
        $count++;
    }
    foreach ($dates as $d) {
        $newStart = date('Y-m-d H:i:s', $d);
        $newEnd = date('Y-m-d H:i:s', $d + $duration);
        db_insert_get_id($conn,
            "INSERT INTO cal_events (creator_id,title,description,location,start_time,end_time,all_day,importance,color,recurrence_parent_id,reminder_minutes,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,GETDATE(),GETDATE())",
            array($creatorId, $title, $desc, $location, $newStart, $newEnd, $allDay, $importance, $color, $parentId, $reminder));
    }
}
