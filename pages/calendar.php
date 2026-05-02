<?php
/**
 * Calendar Page - Monthly / Daily / Agenda views
 */
$userId = auth_user_id();

// Generate 24h time options (15-min increments)
function cal_time_options($selected = '') {
    $html = '';
    for ($h = 0; $h < 24; $h++) {
        for ($m = 0; $m < 60; $m += 15) {
            $val = sprintf('%02d:%02d', $h, $m);
            $sel = ($val === $selected) ? ' selected' : '';
            $html .= '<option value="' . $val . '"' . $sel . '>' . $val . '</option>';
        }
    }
    return $html;
}
$allUsers = db_fetch_all($conn, "SELECT id, username, display_name FROM mail_users WHERE id != ? AND is_active = 1 ORDER BY display_name", array($userId));

// Count today's events for badge
$todayCount = intval(db_fetch_scalar($conn,
    "SELECT COUNT(*) FROM cal_events e WHERE e.is_cancelled=0 AND CONVERT(DATE,e.start_time)=CONVERT(DATE,GETDATE())
     AND (e.creator_id=? OR EXISTS(SELECT 1 FROM cal_attendees a WHERE a.event_id=e.id AND a.user_id=? AND a.status!='declined'))",
    array($userId, $userId)));
?>

<!-- View Tabs -->
<div class="cal-header">
    <div class="cal-nav">
        <button class="btn btn-action btn-sm" id="cal-prev" onclick="calNav(-1)">&#x276E;</button>
        <button class="btn btn-action btn-sm" id="cal-today-btn" onclick="calToday()">Today</button>
        <button class="btn btn-action btn-sm" id="cal-next" onclick="calNav(1)">&#x276F;</button>
        <h2 id="cal-title" class="cal-title"></h2>
    </div>
    <div class="cal-tabs">
        <button class="cal-tab active" data-view="month" onclick="switchView('month',this)">Month</button>
        <button class="cal-tab" data-view="day" onclick="switchView('day',this)">Day</button>
        <button class="cal-tab" data-view="agenda" onclick="switchView('agenda',this)">Agenda</button>
        <button class="btn btn-primary btn-sm" onclick="openEventModal()" style="margin-left:12px">+ New Event</button>
    </div>
</div>

<!-- Monthly View -->
<div id="cal-month-view" class="cal-view">
    <div class="cal-grid">
        <div class="cal-dow">Sun</div><div class="cal-dow">Mon</div><div class="cal-dow">Tue</div>
        <div class="cal-dow">Wed</div><div class="cal-dow">Thu</div><div class="cal-dow">Fri</div><div class="cal-dow">Sat</div>
    </div>
    <div class="cal-days" id="cal-days"></div>
</div>

<!-- Daily View -->
<div id="cal-day-view" class="cal-view" style="display:none">
    <div class="cal-timeline" id="cal-timeline"></div>
</div>

<!-- Agenda View -->
<div id="cal-agenda-view" class="cal-view" style="display:none">
    <div id="cal-agenda-list" class="cal-agenda-list"></div>
</div>

<!-- Event Detail Sidebar -->
<div id="cal-event-detail" class="cal-event-detail" style="display:none">
    <div class="cal-detail-header">
        <h3 id="detail-title"></h3>
        <button class="modal-close" onclick="closeDetail()">&times;</button>
    </div>
    <div class="cal-detail-body" id="detail-body"></div>
</div>

<!-- Event Create/Edit Modal -->
<div class="modal-overlay" id="event-modal" style="display:none" onclick="if(event.target===this)closeEventModal()">
    <div class="modal" style="max-width:580px">
        <div class="modal-header">
            <h3 id="event-modal-title">&#x1F4C5; New Event</h3>
            <button class="modal-close" onclick="closeEventModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:16px 20px;max-height:70vh;overflow-y:auto">
            <input type="hidden" id="ev-edit-id" value="">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" id="ev-title" placeholder="Event title...">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="ev-desc" rows="3" style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-family:inherit;font-size:14px;resize:vertical"></textarea>
            </div>
            <div class="form-group">
                <label>Location</label>
                <input type="text" id="ev-location" placeholder="Location (optional)">
            </div>
            <div class="ev-datetime-grid">
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="ev-start-date">
                </div>
                <div class="form-group" id="ev-start-time-group">
                    <label>Start Time</label>
                    <input type="text" id="ev-start-time" value="08:00" placeholder="HH:MM" maxlength="5" class="time-input-24h">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="ev-end-date">
                </div>
                <div class="form-group" id="ev-end-time-group">
                    <label>End Time</label>
                    <input type="text" id="ev-end-time" value="09:00" placeholder="HH:MM" maxlength="5" class="time-input-24h">
                </div>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:14px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer">
                    <input type="checkbox" id="ev-allday" onchange="toggleAllDay()" style="width:16px;height:16px;accent-color:var(--accent)"> All Day
                </label>
            </div>
            <div class="ev-datetime-grid">
                <div class="form-group">
                    <label>Importance</label>
                    <select id="ev-importance">
                        <option value="low">🟢 Low</option>
                        <option value="normal" selected>🟡 Normal</option>
                        <option value="high">🔴 High</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reminder</label>
                    <select id="ev-reminder">
                        <option value="0">None</option>
                        <option value="5">5 minutes</option>
                        <option value="15" selected>15 minutes</option>
                        <option value="30">30 minutes</option>
                        <option value="60">1 hour</option>
                        <option value="1440">1 day</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Color</label>
                <div class="cal-color-picker" id="ev-color-picker">
                    <span class="cal-color-swatch active" data-color="#6366f1" style="background:#6366f1"></span>
                    <span class="cal-color-swatch" data-color="#8b5cf6" style="background:#8b5cf6"></span>
                    <span class="cal-color-swatch" data-color="#ec4899" style="background:#ec4899"></span>
                    <span class="cal-color-swatch" data-color="#f43f5e" style="background:#f43f5e"></span>
                    <span class="cal-color-swatch" data-color="#f97316" style="background:#f97316"></span>
                    <span class="cal-color-swatch" data-color="#eab308" style="background:#eab308"></span>
                    <span class="cal-color-swatch" data-color="#22c55e" style="background:#22c55e"></span>
                    <span class="cal-color-swatch" data-color="#06b6d4" style="background:#06b6d4"></span>
                    <span class="cal-color-swatch" data-color="#3b82f6" style="background:#3b82f6"></span>
                    <span class="cal-color-swatch" data-color="#64748b" style="background:#64748b"></span>
                </div>
            </div>
            <div class="form-group">
                <label>Recurrence</label>
                <select id="ev-recurrence" onchange="toggleRecEnd()">
                    <option value="">None</option>
                    <option value="DAILY">Daily</option>
                    <option value="WEEKLY">Weekly</option>
                    <option value="MONTHLY">Monthly</option>
                    <option value="YEARLY">Yearly</option>
                </select>
            </div>
            <div class="form-group" id="ev-rec-end-group" style="display:none">
                <label>Recurrence End Date</label>
                <input type="date" id="ev-rec-end">
            </div>
            <div class="form-group">
                <label>Invite Attendees</label>
                <div class="to-input-row">
                    <button type="button" class="ab-open-btn" onclick="openCalAB()" title="Address Book">&#x1F4D6;</button>
                    <input type="text" id="ev-attendees" placeholder="Usernames separated by commas..." class="recipient-input" autocomplete="off">
                </div>
                <div class="autocomplete-dropdown" id="ev-attendees-dropdown"></div>
            </div>
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-primary" id="ev-save-btn" onclick="saveEvent()">Create Event</button>
            <button class="btn btn-danger" id="ev-delete-btn" onclick="deleteEvent()" style="display:none">Delete</button>
            <button class="btn btn-ghost" onclick="closeEventModal()" style="margin-left:auto">Cancel</button>
        </div>
    </div>
</div>

<!-- Address Book Modal for Calendar -->
<div class="modal-overlay" id="cal-ab-modal" style="display:none" onclick="if(event.target===this)closeCalAB()">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h3>&#x1F4D6; Address Book</h3>
            <button class="modal-close" onclick="closeCalAB()">&times;</button>
        </div>
        <div style="padding:12px 20px 0">
            <input type="text" id="cal-ab-filter" placeholder="Search users..." oninput="filterCalAB(this.value)" style="width:100%;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px">
        </div>
        <div class="modal-body" style="padding:12px 20px">
            <table class="data-table ab-table" id="cal-ab-list">
                <thead><tr><th style="width:36px"><input type="checkbox" id="cal-ab-select-all" onchange="calAbToggleAll(this.checked)"></th><th>User</th><th>Username</th></tr></thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                    <tr class="cal-ab-row" data-search="<?php echo e(strtolower($u['display_name'].' '.$u['username'])); ?>">
                        <td><input type="checkbox" class="cal-ab-check" value="<?php echo e($u['username']); ?>"></td>
                        <td><div class="user-cell"><div class="avatar-xs" style="background:<?php echo get_avatar_color($u['display_name']); ?>"><?php echo e(get_initials($u['display_name'])); ?></div><?php echo e($u['display_name']); ?></div></td>
                        <td>@<?php echo e($u['username']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border)">
            <button class="btn btn-sm btn-primary" onclick="addCalABChecked()">Add Selected</button>
        </div>
    </div>
</div>

<script src="assets/js/calendar.js"></script>
