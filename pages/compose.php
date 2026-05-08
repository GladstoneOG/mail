<?php
/**
 * Compose Message - with Address Book Modal + Rich Text Editor
 */
$userId = auth_user_id();
$replyId = isset($_GET['reply']) ? intval($_GET['reply']) : 0;
$replyAllId = isset($_GET['replyall']) ? intval($_GET['replyall']) : 0;
$forwardId = isset($_GET['forward']) ? intval($_GET['forward']) : 0;
$draftId = isset($_GET['draft']) ? intval($_GET['draft']) : 0;

$prefillTo = ''; $prefillCc = ''; $prefillSubject = ''; $prefillBody = '';
$forwardAttIds = array();
$replyToId = 0;

$user = db_fetch_one($conn, "SELECT email_footer FROM mail_users WHERE id = ?", array($userId));
$footerHtml = '';
if (false && $user && !empty($user['email_footer'])) {
    $footerHtml = '<br><br><hr style="border:none;border-top:1px solid var(--border);margin:10px 0;"><div class="email-footer">' . $user['email_footer'] . '</div>';
}

if ($replyId || $replyAllId) {
    $origId = $replyId ? $replyId : $replyAllId;
    $orig = db_fetch_one($conn, "SELECT m.*, u.display_name AS sender_name, u.username AS sender_username
                                  FROM mail_messages m JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?", array($origId));
    if ($orig) {
        $replyToId = $origId;
        $prefillTo = $orig['sender_username'];
        $prefillSubject = 'Re: ' . preg_replace('/^Re:\s*/i', '', $orig['subject']);
        $prefillBody = $footerHtml . '<br><br><div style="border-left:3px solid #555;padding-left:12px;color:#888;">--- Original Message from ' . e($orig['sender_name']) . ' ---<br>' . $orig['body'] . '</div>';
        if ($replyAllId) {
            $others = db_fetch_all($conn, "SELECT u.username FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
                                            WHERE mr.message_id = ? AND mr.recipient_id != ? AND mr.recipient_type = 'to'", array($origId, $userId));
            foreach ($others as $o) $prefillTo .= ', ' . $o['username'];
            $ccU = db_fetch_all($conn, "SELECT u.username FROM mail_recipients mr JOIN mail_users u ON mr.recipient_id = u.id
                                        WHERE mr.message_id = ? AND mr.recipient_type = 'cc'", array($origId));
            $ccNames = array();
            foreach ($ccU as $c) $ccNames[] = $c['username'];
            $prefillCc = implode(', ', $ccNames);
        }
    }
}

if ($forwardId) {
    $orig = db_fetch_one($conn, "SELECT m.*, u.display_name AS sender_name FROM mail_messages m
                                  JOIN mail_users u ON m.sender_id = u.id WHERE m.id = ?", array($forwardId));
    if ($orig) {
        $prefillSubject = 'Fwd: ' . preg_replace('/^Fwd:\s*/i', '', $orig['subject']);
        $prefillBody = $footerHtml . '<br><br><div style="border-left:3px solid #555;padding-left:12px;color:#888;">--- Forwarded from ' . e($orig['sender_name']) . ' ---<br>' . $orig['body'] . '</div>';
        $fwdAtts = db_fetch_all($conn, "SELECT id, original_name, file_size FROM mail_attachments WHERE message_id = ?", array($forwardId));
        foreach ($fwdAtts as $fa) $forwardAttIds[] = $fa;
    }
}

if ($draftId) {
    $draft = db_fetch_one($conn, "SELECT * FROM mail_messages WHERE id = ? AND sender_id = ? AND is_draft = 1", array($draftId, $userId));
    if ($draft) {
        $prefillSubject = $draft['subject'];
        $prefillBody = $draft['body'];
        $recipients = db_fetch_all($conn, "SELECT u.username, mr.recipient_type FROM mail_recipients mr
                                            JOIN mail_users u ON mr.recipient_id = u.id WHERE mr.message_id = ?", array($draftId));
        $toL = array(); $ccL = array();
        foreach ($recipients as $r) {
            if ($r['recipient_type'] === 'cc') $ccL[] = $r['username'];
            else $toL[] = $r['username'];
        }
        $prefillTo = implode(', ', $toL);
        $prefillCc = implode(', ', $ccL);
    }
}

if (!$replyId && !$replyAllId && !$forwardId && !$draftId) {
    $prefillBody = $footerHtml;
}

if (isset($_GET['to'])) $prefillTo = $_GET['to'];

// Get all users for address book
$allUsers = db_fetch_all($conn, "SELECT id, username, display_name FROM mail_users WHERE id != ? AND is_active = 1 ORDER BY display_name", array($userId));
?>

<!-- Address Book Modal (#1) -->
<div class="modal-overlay" id="ab-modal" style="display:none" onclick="if(event.target===this)closeAddressBook()">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h3>&#x1F4D6; Address Book</h3>
            <button class="modal-close" onclick="closeAddressBook()">&times;</button>
        </div>
        <div style="padding:12px 20px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="text" id="ab-filter" placeholder="Search users..." oninput="filterAddressBook(this.value)" style="flex:1;padding:8px 12px;background:var(--bg);border:1px solid var(--border);border-radius:6px;color:var(--text);font-size:13px">
        </div>
        <div class="modal-body" style="padding:12px 20px">
            <table class="data-table ab-table" id="ab-list">
                <thead>
                    <tr>
                        <th style="width:36px"><input type="checkbox" id="ab-select-all" onchange="abToggleAll(this.checked)"></th>
                        <th>User</th>
                        <th>Username</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allUsers as $u): ?>
                        <tr class="ab-row" data-search="<?php echo e(strtolower($u['display_name'] . ' ' . $u['username'])); ?>" onclick="abRowClick(this, event)">
                            <td><input type="checkbox" class="ab-check" value="<?php echo e($u['username']); ?>" onclick="event.stopPropagation()"></td>
                            <td>
                                <div class="user-cell">
                                    <div class="avatar-xs" style="background:<?php echo get_avatar_color($u['display_name']); ?>"><?php echo e(get_initials($u['display_name'])); ?></div>
                                    <?php echo e($u['display_name']); ?>
                                </div>
                            </td>
                            <td>@<?php echo e($u['username']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn btn-sm btn-primary" onclick="addCheckedAs('to')">Add as To</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addCheckedAs('cc')">Add as CC</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="addCheckedAs('bcc')">Add as BCC</button>
            <!-- <button type="button" class="btn btn-sm btn-ghost" onclick="closeAddressBook()" style="margin-left:auto">Done</button> -->
        </div>
    </div>
</div>

<form id="compose-form" class="compose-form" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo e($csrfToken); ?>">
    <input type="hidden" name="draft_id" value="<?php echo $draftId; ?>">
    <input type="hidden" name="reply_to_id" value="<?php echo $replyToId; ?>">
    <input type="hidden" name="forward_attachments" id="forward_attachments" value="<?php echo implode(',', array_map(function($a){ return $a['id']; }, $forwardAttIds)); ?>">

    <div class="form-group">
        <label for="to">To</label>
        <div class="to-input-row">
            <button type="button" class="ab-open-btn" onclick="openAddressBook()" title="Open Address Book">&#x1F4D6;</button>
            <input type="text" id="to" name="to" placeholder="Type usernames separated by commas..." value="<?php echo e($prefillTo); ?>" class="recipient-input" autocomplete="off" autofocus>
        </div>
        <div class="autocomplete-dropdown" id="to-dropdown"></div>
    </div>
    <div class="form-row-toggle">
        <a href="#" onclick="toggleCcBcc();return false;" class="toggle-link" id="cc-bcc-toggle">Show CC / BCC</a>
    </div>
    <div id="cc-bcc-fields" style="display:<?php echo $prefillCc ? 'block' : 'none'; ?>">
        <div class="form-group">
            <label for="cc">CC</label>
            <input type="text" id="cc" name="cc" placeholder="CC recipients..." value="<?php echo e($prefillCc); ?>" class="recipient-input" autocomplete="off">
            <div class="autocomplete-dropdown" id="cc-dropdown"></div>
        </div>
        <div class="form-group">
            <label for="bcc">BCC</label>
            <input type="text" id="bcc" name="bcc" placeholder="BCC recipients..." class="recipient-input" autocomplete="off">
            <div class="autocomplete-dropdown" id="bcc-dropdown"></div>
        </div>
    </div>
    <div class="form-group">
        <label for="subject">Subject</label>
        <input type="text" id="subject" name="subject" placeholder="Message subject..." value="<?php echo e($prefillSubject); ?>">
    </div>
    <div class="form-group">
        <label>Message</label>
        <!-- Rich Text Editor Toolbar (#6 improved) -->
        <div class="editor-toolbar" id="editor-toolbar">
            <button type="button" id="tb-bold" onclick="execCmd('bold')" title="Bold (Ctrl+B)" class="tb-btn"><b>B</b></button>
            <button type="button" id="tb-italic" onclick="execCmd('italic')" title="Italic (Ctrl+I)" class="tb-btn"><i>I</i></button>
            <button type="button" id="tb-underline" onclick="execCmd('underline')" title="Underline (Ctrl+U)" class="tb-btn"><u>U</u></button>
            <span class="toolbar-sep"></span>
            <select onchange="execCmd('fontSize',this.value);this.selectedIndex=0" title="Font Size">
                <option value="">Size</option>
                <option value="1">Small</option>
                <option value="3">Normal</option>
                <option value="5">Large</option>
                <option value="7">Huge</option>
            </select>
            <input type="color" value="#e2e8f0" onchange="execCmd('foreColor',this.value)" title="Text Color" class="color-pick">
            <span class="toolbar-sep"></span>
            <button type="button" id="tb-justifyLeft" onclick="execCmd('justifyLeft')" title="Align Left" class="tb-btn tb-active">&#x2190;</button>
            <button type="button" id="tb-justifyCenter" onclick="execCmd('justifyCenter')" title="Center" class="tb-btn">&#x2194;</button>
            <button type="button" id="tb-justifyRight" onclick="execCmd('justifyRight')" title="Align Right" class="tb-btn">&#x2192;</button>
            <span class="toolbar-sep"></span>
            <button type="button" id="tb-insertUnorderedList" onclick="execCmd('insertUnorderedList')" title="Bullet List" class="tb-btn">&#x2022;</button>
            <button type="button" id="tb-insertOrderedList" onclick="execCmd('insertOrderedList')" title="Numbered List" class="tb-btn">1.</button>
        </div>
        <div id="editor" class="rich-editor" contenteditable="true"><?php echo $prefillBody; ?></div>
        <input type="hidden" name="body" id="body-hidden">
    </div>
    <div class="form-group">
        <label>Attachments</label>
        <?php if (!empty($forwardAttIds)): ?>
            <div class="fwd-att-list">
                <strong>Forwarded attachments:</strong>
                <?php foreach ($forwardAttIds as $fa): ?>
                    <span class="fwd-att-item">&#x1F4CE; <?php echo e($fa['original_name']); ?> (<?php echo format_size($fa['file_size']); ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <div class="file-upload-area" id="file-upload-area">
            <input type="file" id="attachments" name="attachments[]" multiple class="file-input">
            <div class="file-upload-label">
                <span class="upload-icon">&#x1F4CE;</span>
                <span>Drop files here or <strong>click to browse</strong></span>
            </div>
        </div>
        <div id="file-list" class="file-list"></div>
    </div>
    <div class="compose-actions">
        <div class="schedule-send-wrap">
            <button type="submit" class="btn btn-primary" id="send-btn"><span>&#x1F4E8;</span> Send Message</button>
            <button type="button" class="schedule-dropdown-toggle" onclick="toggleScheduleDropdown()" title="Schedule Send">&#x25B2;</button>
            <div class="schedule-dropdown" id="schedule-dropdown">
                <div class="schedule-header">&#x1F552; Schedule Send</div>
                <div class="schedule-presets">
                    <button type="button" class="schedule-preset" onclick="scheduleSendPreset('today_evening')">
                        <span class="schedule-preset-icon">&#x1F307;</span> Later today <span style="margin-left:auto;color:var(--text3);font-size:11px" id="schedule-today-hint"></span>
                    </button>
                    <button type="button" class="schedule-preset" onclick="scheduleSendPreset('tomorrow_morning')">
                        <span class="schedule-preset-icon">&#x2600;&#xFE0F;</span> Tomorrow morning <span style="margin-left:auto;color:var(--text3);font-size:11px">8:00 AM</span>
                    </button>
                    <button type="button" class="schedule-preset" onclick="scheduleSendPreset('next_monday')">
                        <span class="schedule-preset-icon">&#x1F4C5;</span> Next Monday <span style="margin-left:auto;color:var(--text3);font-size:11px">8:00 AM</span>
                    </button>
                </div>
                <div class="schedule-custom">
                    <label>Custom date & time</label>
                    <div class="schedule-custom-row">
                        <input type="date" id="schedule-date">
                        <input type="time" id="schedule-time" value="08:00">
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" style="width:100%" onclick="scheduleSendCustom()">&#x1F4E8; Schedule Send</button>
                </div>
            </div>
        </div>
        <input type="hidden" name="scheduled_at" id="scheduled-at-input" value="">
        <button type="button" class="btn btn-secondary" id="draft-btn" onclick="saveDraft()"><span>&#x1F4DD;</span> Save Draft</button>
        <button type="button" class="btn btn-ghost" onclick="customConfirm('Discard message?', function() { window.hasUnsavedChanges = false; window.location='index.php?page=inbox'; })">Discard</button>
    </div>
</form>

<script>
function toggleCcBcc() {
    var el = document.getElementById('cc-bcc-fields');
    var link = document.getElementById('cc-bcc-toggle');
    if (el.style.display === 'none') { el.style.display = 'block'; link.textContent = 'Hide CC / BCC'; }
    else { el.style.display = 'none'; link.textContent = 'Show CC / BCC'; }
}

// Address book modal
function openAddressBook() { document.getElementById('ab-modal').style.display = 'flex'; }
function closeAddressBook() { document.getElementById('ab-modal').style.display = 'none'; }

function filterAddressBook(q) {
    q = q.toLowerCase();
    var rows = document.querySelectorAll('.ab-row');
    for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = (!q || rows[i].getAttribute('data-search').indexOf(q) !== -1) ? '' : 'none';
    }
}
function addCheckedAs(type) {
    var checks = document.querySelectorAll('.ab-check:checked');
    if (checks.length === 0) { customAlert('Select at least one user'); return; }
    var field = document.getElementById(type === 'bcc' || type === 'cc' ? type : 'to');
    if (type === 'cc' || type === 'bcc') {
        document.getElementById('cc-bcc-fields').style.display = 'block';
        document.getElementById('cc-bcc-toggle').textContent = 'Hide CC / BCC';
    }
    var current = field.value.trim();
    var existing = current ? current.split(',').map(function(s){ return s.trim(); }) : [];
    for (var i = 0; i < checks.length; i++) {
        var uname = checks[i].value;
        if (existing.indexOf(uname) === -1) existing.push(uname);
        checks[i].checked = false;
        var row = checks[i].closest('.ab-row');
        if (row) row.classList.remove('selected');
    }
    field.value = existing.join(', ');
    window.hasUnsavedChanges = true;
    document.getElementById('ab-select-all').checked = false;
    closeAddressBook();
}
function abToggleAll(checked) {
    var rows = document.querySelectorAll('.ab-row');
    for (var i = 0; i < rows.length; i++) {
        if (rows[i].style.display !== 'none') {
            rows[i].querySelector('.ab-check').checked = checked;
            rows[i].classList.toggle('selected', checked);
        }
    }
}
function abRowClick(row, event) {
    var cb = row.querySelector('.ab-check');
    if (cb) {
        cb.checked = !cb.checked;
        row.classList.toggle('selected', cb.checked);
    }
}

// Rich text editor with toggle state tracking (#6)
function execCmd(cmd, val) {
    var editor = document.getElementById('editor');
    // Must focus editor BEFORE execCommand for first-click to work
    editor.focus();
    document.execCommand(cmd, false, val || null);
    updateToolbarState();
}

function updateToolbarState() {
    var toggleCmds = ['bold', 'italic', 'underline'];
    for (var i = 0; i < toggleCmds.length; i++) {
        var btn = document.getElementById('tb-' + toggleCmds[i]);
        if (btn) {
            if (document.queryCommandState(toggleCmds[i])) {
                btn.classList.add('tb-active');
            } else {
                btn.classList.remove('tb-active');
            }
        }
    }
    // Alignment
    var aligns = ['justifyLeft', 'justifyCenter', 'justifyRight'];
    for (var j = 0; j < aligns.length; j++) {
        var abtn = document.getElementById('tb-' + aligns[j]);
        if (abtn) {
            if (document.queryCommandState(aligns[j])) {
                abtn.classList.add('tb-active');
            } else {
                abtn.classList.remove('tb-active');
            }
        }
    }
    // Lists
    var lists = ['insertUnorderedList', 'insertOrderedList'];
    for (var k = 0; k < lists.length; k++) {
        var lbtn = document.getElementById('tb-' + lists[k]);
        if (lbtn) {
            if (document.queryCommandState(lists[k])) {
                lbtn.classList.add('tb-active');
            } else {
                lbtn.classList.remove('tb-active');
            }
        }
    }
}

// Track selection changes to update toolbar state
var editor = document.getElementById('editor');
if (editor) {
    editor.addEventListener('keyup', function(e) { updateToolbarState(); checkEmptyForm(); });
    editor.addEventListener('mouseup', updateToolbarState);
    editor.addEventListener('focus', updateToolbarState);
}

// Item 4: Disable Send/Draft buttons if completely clean
function checkEmptyForm() {
    var to = document.getElementById('to').value.trim();
    var cc = document.getElementById('cc').value.trim();
    var bcc = document.getElementById('bcc').value.trim();
    var subj = document.getElementById('subject').value.trim();
    var body = editor ? editor.textContent.trim() : '';
    var hasFiles = (window.composeFiles && window.composeFiles.length > 0);
    var isEmpty = (!to && !cc && !bcc && !subj && !body && !hasFiles);

    var sendBtn = document.getElementById('send-btn');
    var draftBtn = document.getElementById('draft-btn');
    var schedBtn = document.getElementById('schedule-send-toggle');
    if (sendBtn) sendBtn.classList.toggle('btn-disabled', isEmpty);
    if (draftBtn) draftBtn.classList.toggle('btn-disabled', isEmpty);
    if (schedBtn) schedBtn.classList.toggle('btn-disabled', isEmpty);
    return isEmpty;
}

// Attach listeners to fields for checkEmptyForm
var fields = ['to', 'cc', 'bcc', 'subject'];
for(var i=0;i<fields.length;i++){
    var el = document.getElementById(fields[i]);
    if(el) el.addEventListener('input', checkEmptyForm);
}
// Run once on load
setTimeout(checkEmptyForm, 100);

// Ensure "To" field gets focus when page loads
var toField = document.getElementById('to');
if (toField) {
    toField.focus();
    // Move cursor to end if there's prefilled text
    var val = toField.value;
    toField.value = '';
    toField.value = val;
}

// ── Schedule Send ──
function toggleScheduleDropdown() {
    if (typeof checkEmptyForm === 'function' && checkEmptyForm()) {
        showToast('Please enter at least a recipient, subject, or message', 'error');
        return;
    }
    var dd = document.getElementById('schedule-dropdown');
    var isOpen = dd.classList.contains('show');
    dd.classList.toggle('show', !isOpen);
    if (!isOpen) {
        // Set today hint
        var now = new Date();
        var hint = document.getElementById('schedule-today-hint');
        if (hint) {
            var evening = new Date(now); evening.setHours(18, 0, 0, 0);
            if (now.getHours() >= 18) evening.setDate(evening.getDate() + 1);
            hint.textContent = evening.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
        }
        // Set min date for custom picker
        var dateInput = document.getElementById('schedule-date');
        if (dateInput) {
            var y = now.getFullYear(), m = String(now.getMonth()+1).padStart(2,'0'), d = String(now.getDate()).padStart(2,'0');
            dateInput.min = y + '-' + m + '-' + d;
            if (!dateInput.value) dateInput.value = y + '-' + m + '-' + d;
        }
    }
}

function scheduleSendPreset(preset) {
    var now = new Date();
    var target;
    if (preset === 'today_evening') {
        target = new Date(now); target.setHours(18, 0, 0, 0);
        if (now.getHours() >= 18) target.setDate(target.getDate() + 1);
    } else if (preset === 'tomorrow_morning') {
        target = new Date(now); target.setDate(target.getDate() + 1); target.setHours(8, 0, 0, 0);
    } else if (preset === 'next_monday') {
        target = new Date(now);
        var daysUntilMon = (8 - target.getDay()) % 7;
        if (daysUntilMon === 0) daysUntilMon = 7;
        target.setDate(target.getDate() + daysUntilMon);
        target.setHours(8, 0, 0, 0);
    }
    if (target) doScheduleSend(target);
}

function scheduleSendCustom() {
    var dateVal = document.getElementById('schedule-date').value;
    var timeVal = document.getElementById('schedule-time').value;
    if (!dateVal || !timeVal) { showToast('Please select date and time', 'error'); return; }
    var target = new Date(dateVal + 'T' + timeVal);
    if (target <= new Date()) { showToast('Scheduled time must be in the future', 'error'); return; }
    doScheduleSend(target);
}

function doScheduleSend(target) {
    var y = target.getFullYear();
    var m = String(target.getMonth() + 1).padStart(2, '0');
    var d = String(target.getDate()).padStart(2, '0');
    var h = String(target.getHours()).padStart(2, '0');
    var min = String(target.getMinutes()).padStart(2, '0');
    var formatted = y + '-' + m + '-' + d + ' ' + h + ':' + min + ':00';
    document.getElementById('scheduled-at-input').value = formatted;
    document.getElementById('schedule-dropdown').classList.remove('show');
    // Update send button text
    var btn = document.getElementById('send-btn');
    btn.innerHTML = '<span>&#x1F552;</span> Scheduled: ' + target.toLocaleDateString('en-US', {month:'short', day:'numeric'}) + ' ' + target.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'});
    showToast('Email will be sent ' + target.toLocaleDateString() + ' at ' + target.toLocaleTimeString('en-US', {hour:'numeric', minute:'2-digit'}), 'info');
}

// Close schedule dropdown on outside click
document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.schedule-send-wrap');
    var dd = document.getElementById('schedule-dropdown');
    if (dd && wrap && !wrap.contains(e.target)) dd.classList.remove('show');
});
</script>

