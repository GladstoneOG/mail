/**
 * RSPIK Mail - Client-side JavaScript
 * ES5 compatible for older browsers
 */

// ============ CUSTOM DIALOG ============
(function () {
    var dialogHtml = '<div class="modal-overlay" id="custom-dialog-modal" style="display:none;z-index:10000">' +
        '<div class="modal" style="max-width:400px;text-align:center">' +
        '<div class="modal-body" style="padding:24px">' +
        '<div id="custom-dialog-icon" style="font-size:36px;margin-bottom:12px">&#x26A0;&#xFE0F;</div>' +
        '<h3 id="custom-dialog-title">Confirm</h3>' +
        '<p id="custom-dialog-msg" style="color:var(--text2);margin-top:8px;font-size:14px"></p>' +
        '<div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">' +
        '<button type="button" class="btn btn-primary" id="custom-dialog-ok">OK</button>' +
        '<button type="button" class="btn btn-secondary" id="custom-dialog-cancel" style="display:none">Cancel</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';

    var previewModalHtml = '<div class="modal-overlay" id="preview-modal" style="display:none;z-index:10000">' +
        '<div class="modal" style="max-width:90%;width:800px;height:85vh;display:flex;flex-direction:column;padding:0">' +
        '<div class="modal-header" style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">' +
        '<h3 id="preview-title" style="margin:0;font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Preview</h3>' +
        '<button type="button" class="btn btn-ghost btn-sm" onclick="closePreviewModal()" style="font-size:18px;padding:4px 8px">&#x2715;</button>' +
        '</div>' +
        '<div class="modal-body" id="preview-content" style="flex:1;padding:0;overflow:hidden;background:var(--bg2);display:flex;align-items:center;justify-content:center">' +
        '</div>' +
        '<div class="modal-footer" style="padding:12px 20px;border-top:1px solid var(--border);text-align:right">' +
        '<a href="#" id="preview-download-btn" class="btn btn-primary" download>&#x1F4E5; Download File</a>' +
        '</div>' +
        '</div>' +
        '</div>';

    function injectDialog() {
        if (!document.getElementById('custom-dialog-modal')) {
            document.body.insertAdjacentHTML('beforeend', dialogHtml);
        }
        if (!document.getElementById('preview-modal')) {
            document.body.insertAdjacentHTML('beforeend', previewModalHtml);
        }
    }

    if (document.body) {
        injectDialog();
    } else {
        document.addEventListener('DOMContentLoaded', injectDialog);
    }
})();

window.customAlert = function (msg, title) {
    var modal = document.getElementById('custom-dialog-modal');
    if (!modal) return;
    document.getElementById('custom-dialog-title').textContent = title || 'Alert';
    document.getElementById('custom-dialog-msg').textContent = msg;
    document.getElementById('custom-dialog-icon').innerHTML = '&#x26A0;&#xFE0F;';
    var okBtn = document.getElementById('custom-dialog-ok');
    var cancelBtn = document.getElementById('custom-dialog-cancel');
    cancelBtn.style.display = 'none';
    okBtn.className = 'btn btn-primary';
    okBtn.textContent = 'OK';
    modal.style.display = 'flex';

    okBtn.onclick = function () {
        modal.style.display = 'none';
    };
};

window.customConfirm = function (msg, onConfirm, onCancel, iconHtml) {
    var modal = document.getElementById('custom-dialog-modal');
    if (!modal) return;
    document.getElementById('custom-dialog-title').textContent = 'Confirm';
    document.getElementById('custom-dialog-msg').textContent = msg;
    document.getElementById('custom-dialog-icon').innerHTML = iconHtml || '&#x2753;';
    var okBtn = document.getElementById('custom-dialog-ok');
    var cancelBtn = document.getElementById('custom-dialog-cancel');
    cancelBtn.style.display = 'inline-block';
    okBtn.className = 'btn btn-danger';
    okBtn.textContent = 'Yes';
    cancelBtn.textContent = 'Cancel';
    modal.style.display = 'flex';

    okBtn.onclick = function () {
        modal.style.display = 'none';
        if (onConfirm) onConfirm();
    };
    cancelBtn.onclick = function () {
        modal.style.display = 'none';
        if (onCancel) onCancel();
    };
};

window.openPreview = function(url, title, ext) {
    var modal = document.getElementById('preview-modal');
    if (!modal) return;
    
    document.getElementById('preview-title').textContent = title;
    var content = document.getElementById('preview-content');
    content.innerHTML = '';
    
    var downloadBtn = document.getElementById('preview-download-btn');
    downloadBtn.href = url.replace('action=preview', 'action=download');
    
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(ext) !== -1) {
        content.innerHTML = '<img src="' + url + '" style="max-width:100%;max-height:100%;object-fit:contain;">';
    } else if (ext === 'pdf') {
        content.innerHTML = '<iframe src="' + url + '" style="width:100%;height:100%;border:none;"></iframe>';
    } else if (ext === 'txt') {
        content.innerHTML = '<iframe src="' + url + '" style="width:100%;height:100%;border:none;background:#fff;"></iframe>';
    } else {
        content.innerHTML = '<div style="color:var(--text3);padding:20px;">Preview not supported for this file type.</div>';
    }
    
    modal.style.display = 'flex';
};

window.closePreviewModal = function() {
    var modal = document.getElementById('preview-modal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('preview-content').innerHTML = ''; // Stop media/iframes
    }
};

// ============ COMPOSE FORM ============
(function () {
    var form = document.getElementById('compose-form');
    if (!form) return;

    // Sync rich editor content to hidden input before submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        window.hasUnsavedChanges = false;
        syncEditorContent();
        sendMessage(false);
    });

    // Autocomplete for recipient fields
    var recipientFields = ['to', 'cc', 'bcc'];
    recipientFields.forEach(function (fieldId) {
        var input = document.getElementById(fieldId);
        var dropdown = document.getElementById(fieldId + '-dropdown');
        if (!input || !dropdown) return;

        var debounceTimer = null;
        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var val = input.value;
                var parts = val.split(',');
                var current = parts[parts.length - 1].trim();
                if (current.length < 1) { dropdown.style.display = 'none'; return; }

                fetch('api/users.php?action=search&q=' + encodeURIComponent(current))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.users || data.users.length === 0) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.users.forEach(function (u) {
                            var div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.innerHTML = '<span class="ac-name">' + escHtml(u.display_name) + '</span><span class="ac-username">@' + escHtml(u.username) + '</span>';
                            div.addEventListener('click', function () {
                                parts[parts.length - 1] = u.username;
                                input.value = parts.join(', ') + ', ';
                                dropdown.style.display = 'none';
                                input.focus();
                                window.hasUnsavedChanges = true;
                            });
                            dropdown.appendChild(div);
                        });
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function (e) {
            if (e.target !== input) dropdown.style.display = 'none';
        });
    });

    // File list display and queue
    window.composeFiles = [];
    var fileInput = document.getElementById('attachments');
    
    window.renderFileList = function() {
        var list = document.getElementById('file-list');
        if (!list) return;
        list.innerHTML = '';
        for (var i = 0; i < window.composeFiles.length; i++) {
            var f = window.composeFiles[i];
            var div = document.createElement('div');
            div.className = 'file-list-item';
            div.innerHTML = '<div style="display:flex;align-items:center;width:100%">' +
                '<span style="flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="' + escHtml(f.name) + '">&#x1F4C4; ' + escHtml(f.name) + ' <span style="color:var(--text3);font-size:11px">(' + formatSize(f.size) + ')</span></span>' +
                '<button type="button" class="btn btn-ghost btn-xs" onclick="removeComposeFile(' + i + ')" style="color:var(--danger);padding:2px 6px;flex-shrink:0;">&#x2715;</button>' +
                '</div>';
            list.appendChild(div);
        }
    };

    window.removeComposeFile = function(index) {
        window.composeFiles.splice(index, 1);
        window.renderFileList();
    };

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            for (var i = 0; i < this.files.length; i++) {
                var exists = false;
                for (var j = 0; j < window.composeFiles.length; j++) {
                    if (window.composeFiles[j].name === this.files[i].name && window.composeFiles[j].size === this.files[i].size) {
                        exists = true;
                        break;
                    }
                }
                if (!exists) {
                    window.composeFiles.push(this.files[i]);
                }
            }
            window.renderFileList();
            this.value = ''; // Reset input so same files can be selected again if removed
        });
    }
    // Unsaved changes tracking
    window.hasUnsavedChanges = false;
    form.addEventListener('input', function () { window.hasUnsavedChanges = true; });

    var editor = document.getElementById('editor');
    if (editor) {
        editor.addEventListener('input', function () { window.hasUnsavedChanges = true; });
        editor.addEventListener('keyup', function () { window.hasUnsavedChanges = true; });
    }

    window.addEventListener('beforeunload', function (e) {
        if (window.hasUnsavedChanges) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    var modalHtml = '<div class="modal-overlay" id="unsaved-modal" style="display:none;z-index:9999">' +
        '<div class="modal" style="max-width:400px;text-align:center">' +
        '<div class="modal-body" style="padding:24px">' +
        '<div style="font-size:36px;margin-bottom:12px">&#x26A0;&#xFE0F;</div>' +
        '<h3>Unsaved Changes</h3>' +
        '<p style="color:var(--text2);margin-top:8px;font-size:14px">You have an unfinished message. Would you like to save it as a draft?</p>' +
        '<div style="margin-top:24px;display:flex;gap:12px;justify-content:center;flex-wrap:wrap">' +
        '<button type="button" class="btn btn-primary" id="unsaved-save-btn">Save Draft</button>' +
        '<button type="button" class="btn btn-danger" id="unsaved-leave-btn">Discard</button>' +
        '<button type="button" class="btn btn-secondary" onclick="document.getElementById(\'unsaved-modal\').style.display=\'none\'">Cancel</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>';
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    var pendingNavUrl = '';
    document.getElementById('unsaved-save-btn').addEventListener('click', function () {
        window.hasUnsavedChanges = false;
        saveDraft();
    });
    document.getElementById('unsaved-leave-btn').addEventListener('click', function () {
        window.hasUnsavedChanges = false;
        window.location.href = pendingNavUrl;
    });

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (a && a.href && window.hasUnsavedChanges) {
            if (a.href.indexOf('javascript:') === 0 || a.getAttribute('href').indexOf('#') === 0 || a.target === '_blank') return;
            // Also ignore if it's the logout button, since that has its own confirm
            if (a.classList.contains('logout-btn')) return;

            e.preventDefault();
            pendingNavUrl = a.href;
            document.getElementById('unsaved-modal').style.display = 'flex';
        }
    });
})();

function syncEditorContent() {
    var editor = document.getElementById('editor');
    var hidden = document.getElementById('body-hidden');
    if (editor && hidden) {
        hidden.value = editor.innerHTML;
    }
}

function sendMessage(isDraft) {
    var form = document.getElementById('compose-form');
    if (!form) return;
    syncEditorContent();

    var btn = document.getElementById(isDraft ? 'draft-btn' : 'send-btn');
    if (btn) { btn.disabled = true; btn.textContent = isDraft ? 'Saving...' : 'Sending...'; }

    var formData = new FormData(form);
    
    // Ensure default empty attachments array is cleared if the browser supports it
    if (typeof formData.delete === 'function') {
        formData.delete('attachments[]');
    }
    
    // Append accumulated files
    if (window.composeFiles && window.composeFiles.length > 0) {
        for (var i = 0; i < window.composeFiles.length; i++) {
            formData.append('attachments[]', window.composeFiles[i]);
        }
    }

    formData.append('action', 'send');
    formData.append('is_draft', isDraft ? '1' : '0');

    fetch('api/messages.php?action=send', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                showToast(data.error, 'error');
                if (btn) { btn.disabled = false; btn.textContent = isDraft ? '📝 Save Draft' : '📨 Send Message'; }
            } else {
                showToast(isDraft ? 'Draft saved!' : 'Message sent!', 'success');
                setTimeout(function () {
                    window.location = 'index.php?page=' + (isDraft ? 'drafts' : 'sent');
                }, 800);
            }
        })
        .catch(function (err) {
            showToast('Network error. Please try again.', 'error');
            if (btn) { btn.disabled = false; btn.textContent = isDraft ? '📝 Save Draft' : '📨 Send Message'; }
        });
}

function saveDraft() {
    window.hasUnsavedChanges = false;
    sendMessage(true);
}

// ============ MESSAGE ACTIONS ============
function toggleStar(msgId, btn) {
    fetch('api/messages.php?action=star', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=star&message_id=' + msgId
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                btn.className = data.starred ? 'star-btn starred' : 'star-btn';
                btn.innerHTML = data.starred ? '&#x2605;' : '&#x2606;';
            }
        });
}

function deleteMessage(msgId, backPage) {
    customConfirm('Move this message to trash?', function () {
        fetch('api/messages.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete&message_id=' + msgId
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('Message deleted', 'success');
                    setTimeout(function () { window.location = 'index.php?page=' + (backPage || 'inbox'); }, 500);
                }
            });
    });
}

function restoreMessage(msgId) {
    fetch('api/messages.php?action=restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=restore&message_id=' + msgId
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) { showToast('Message restored', 'success'); setTimeout(function () { location.reload(); }, 500); }
        });
}

function emptyTrash() {
    customConfirm('Permanently delete all messages in trash?', function () {
        fetch('api/messages.php?action=empty_trash', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=empty_trash'
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) { showToast('Trash emptied', 'success'); setTimeout(function () { location.reload(); }, 500); }
            });
    });
}

function deleteDraft(msgId) {
    customConfirm('Delete this draft?', function () {
        fetch('api/messages.php?action=delete_draft', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_draft&message_id=' + msgId
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) { location.reload(); }
            });
    });
}

// ============ RETRACT / UNSEND ============
function retractMessage(msgId) {
    customConfirm('Retract this message? Recipients will no longer be able to read it.', function () {
        fetch('api/messages.php?action=retract', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=retract&message_id=' + msgId
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showToast('Message retracted!', 'success');
                    setTimeout(function () { window.location = 'index.php?page=sent'; }, 800);
                } else {
                    showToast(data.error || 'Failed to retract', 'error');
                }
            });
    });
}

// ============ THEME TOGGLE ============
function toggleTheme() {
    var html = document.documentElement;
    var current = html.getAttribute('data-theme') || 'dark';
    var next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    document.cookie = 'lanmail_theme=' + next + ';path=/;max-age=31536000';
    var btn = document.getElementById('theme-toggle');
    if (btn) btn.innerHTML = next === 'light' ? '&#x1F319;' : '&#x1F506;';
}

// ============ NOTIFICATION DROPDOWN (#3) ============
function toggleNotifDropdown() {
    var dd = document.getElementById('notif-dropdown');
    if (!dd) return;
    var isOpen = dd.classList.contains('open');
    dd.classList.toggle('open');
    if (!isOpen) {
        // Close on outside click
        setTimeout(function () {
            document.addEventListener('click', closeNotifOnOutside);
        }, 10);
    }
}

function closeNotifOnOutside(e) {
    var wrapper = document.getElementById('notif-wrapper');
    if (wrapper && !wrapper.contains(e.target)) {
        var dd = document.getElementById('notif-dropdown');
        if (dd) dd.classList.remove('open');
        document.removeEventListener('click', closeNotifOnOutside);
    }
}

// ============ NOTIFICATIONS + AUTO-REFRESH ============
(function () {
    if (typeof APP_CONFIG === 'undefined') return;

    var lastUnreadCount = -1;
    var originalTitle = document.title;
    var titleFlashInterval = null;
    var notifSoundEnabled = true;

    // ---- Native Notification API (works on HTTPS / localhost only) ----
    function requestNotifPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(function (perm) {
                if (perm === 'granted') {
                    showToast('Desktop notifications enabled!', 'success');
                }
            });
        }
        document.removeEventListener('click', requestNotifPermission);
        document.removeEventListener('keydown', requestNotifPermission);
    }
    document.addEventListener('click', requestNotifPermission);
    document.addEventListener('keydown', requestNotifPermission);

    // ---- Tab title flashing (works on HTTP) ----
    function startTitleFlash(msg) {
        stopTitleFlash();
        var show = true;
        titleFlashInterval = setInterval(function () {
            document.title = show ? ('\uD83D\uDD14 ' + msg) : originalTitle;
            show = !show;
        }, 1000);
    }

    function stopTitleFlash() {
        if (titleFlashInterval) {
            clearInterval(titleFlashInterval);
            titleFlashInterval = null;
        }
        document.title = originalTitle;
    }

    // Stop flashing when user focuses the window
    window.addEventListener('focus', stopTitleFlash);

    // ---- Notification sound (works on HTTP) ----
    function playNotifSound() {
        if (!notifSoundEnabled) return;
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            // Two-tone chime
            function beep(freq, start, dur) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.15, ctx.currentTime + start);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur);
                osc.start(ctx.currentTime + start);
                osc.stop(ctx.currentTime + start + dur);
            }
            beep(880, 0, 0.15);
            beep(1320, 0.18, 0.2);
        } catch (e) { }
    }

    // ---- Main polling ----
    function checkNew() {
        fetch('api/messages.php?action=check_new')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var badge = document.getElementById('notif-badge');
                var sidebarBadge = document.querySelector('.nav-item[href*="page=inbox"] .badge');

                if (badge) {
                    if (data.unread > 0) {
                        badge.style.display = 'inline';
                        badge.textContent = data.unread;
                    } else {
                        badge.style.display = 'none';
                    }
                }

                if (sidebarBadge) {
                    if (data.unread > 0) {
                        sidebarBadge.textContent = data.unread;
                        sidebarBadge.style.display = '';
                    } else {
                        sidebarBadge.style.display = 'none';
                    }
                }

                // Swap favicon based on unread count
                var favicon = document.getElementById('favicon');
                if (favicon) {
                    favicon.href = data.unread > 0 ? 'assets/icon_notif.png' : 'assets/icon.png';
                }

                // New message arrived
                if (lastUnreadCount >= 0 && data.unread > lastUnreadCount) {
                    var newCount = data.unread - lastUnreadCount;
                    var subj = data.latest_subject || 'New message';
                    var alertTitle = newCount + ' new message' + (newCount > 1 ? 's' : '');

                    // 1) In-app toast (always works)
                    showToast('\uD83D\uDD14 ' + alertTitle + ': ' + subj, 'info');

                    // 2) Tab title flash (always works)
                    if (!document.hasFocus()) {
                        startTitleFlash(alertTitle);
                    }

                    // 3) Sound chime (always works after first user interaction)
                    playNotifSound();

                    // 4) Native desktop notification (HTTPS only - bonus)
                    if ('Notification' in window && Notification.permission === 'granted') {
                        try {
                            var notif = new Notification(alertTitle, {
                                body: subj,
                                tag: 'rspik-mail-' + Date.now(),
                                icon: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text y=".9em" font-size="90">📨</text></svg>'
                            });
                            notif.onclick = function () {
                                window.focus();
                                window.location = 'index.php?page=inbox';
                                notif.close();
                            };
                            setTimeout(function () { notif.close(); }, 6000);
                        } catch (e) { }
                    }

                    // Auto-refresh the current page's message table
                    refreshCurrentPage();

                    // Refresh notification dropdown content
                    refreshNotifDropdown();
                }

                lastUnreadCount = data.unread || 0;
            }).catch(function () { });
    }

    // Refresh the message table on the current page without a full reload
    function refreshCurrentPage() {
        var page = APP_CONFIG.currentPage;
        if (!page || page === 'compose' || page === 'view' || page === 'admin' || page === 'profile') return;

        // Get current URL params for sort/search preservation
        var currentUrl = window.location.href;
        fetch(currentUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                // Extract the content-area from the returned HTML
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.querySelector('.content-area');
                var currentContent = document.querySelector('.content-area');
                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }
            }).catch(function () { });
    }

    // Refresh notification dropdown items
    function refreshNotifDropdown() {
        fetch('api/messages.php?action=notif_list')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.items) return;
                var body = document.querySelector('.notif-dropdown-body');
                if (!body) return;

                if (data.items.length === 0) {
                    body.innerHTML = '<div class="notif-empty">No new notifications</div>';
                } else {
                    var html = '';
                    for (var i = 0; i < data.items.length; i++) {
                        var n = data.items[i];
                        html += '<a href="index.php?page=view&id=' + n.id + '" class="notif-item">'
                            + '<div class="notif-avatar" style="background:' + n.color + '">' + escHtml(n.initials) + '</div>'
                            + '<div class="notif-info">'
                            + '<div class="notif-sender">' + escHtml(n.sender_name) + '</div>'
                            + '<div class="notif-subject">' + escHtml(n.subject) + '</div>'
                            + '<div class="notif-time">' + escHtml(n.time_ago) + '</div>'
                            + '</div></a>';
                    }
                    body.innerHTML = html;
                }

                // Update header count
                var hdr = document.querySelector('.notif-count');
                if (hdr && data.unread_count !== undefined) {
                    hdr.textContent = data.unread_count + ' unread';
                }
            }).catch(function () { });
    }

    // ---- Calendar reminder polling ----
    function checkCalendarReminders() {
        fetch('api/calendar.php?action=check_reminders')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.reminders || data.reminders.length === 0) return;
                for (var i = 0; i < data.reminders.length; i++) {
                    var rem = data.reminders[i];
                    var msg = '\uD83D\uDCC5 ' + rem.title + ' in ' + rem.minutes_until + ' min';
                    showToast(msg, 'info');
                    playCalendarSound();
                    if ('Notification' in window && Notification.permission === 'granted') {
                        try {
                            var n = new Notification('Upcoming Event', { body: rem.title + ' starts in ' + rem.minutes_until + ' minutes', tag: 'cal-' + rem.id });
                            n.onclick = function () { window.focus(); window.location = 'index.php?page=calendar'; n.close(); };
                            setTimeout(function () { n.close(); }, 8000);
                        } catch (e) { }
                    }
                    if (!document.hasFocus()) startTitleFlash('\uD83D\uDCC5 ' + rem.title);
                }
            }).catch(function () { });
    }

    function playCalendarSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            function tone(freq, start, dur) {
                var osc = ctx.createOscillator(); var gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.type = 'triangle'; osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.12, ctx.currentTime + start);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur);
                osc.start(ctx.currentTime + start); osc.stop(ctx.currentTime + start + dur);
            }
            tone(660, 0, 0.2); tone(880, 0.22, 0.2); tone(1100, 0.44, 0.25);
        } catch (e) { }
    }

    setInterval(checkCalendarReminders, APP_CONFIG.pollInterval);

    setInterval(checkNew, APP_CONFIG.pollInterval);
    checkNew();
})();

// ============ UTILITIES ============
function showToast(msg, type) {
    var container = document.getElementById('toast-container');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + (type || 'info');
    toast.textContent = msg;
    container.appendChild(toast);
    setTimeout(function () {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .3s';
        setTimeout(function () { toast.remove(); }, 300);
    }, 3500);
}

function escHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

// ============ ACTION BAR FUNCTIONS ============

// Row click handler: navigate normally, or toggle checkbox in trash mode
function handleRowClick(event, msgId, from) {
    if (document.body.classList.contains('trash-mode')) {
        var row = event.currentTarget;
        var cb = row.querySelector('.msg-select-cb');
        if (cb && event.target !== cb) {
            cb.checked = !cb.checked;
        }
        row.classList.toggle('selected', cb && cb.checked);
        return;
    }
    var url = 'index.php?page=view&id=' + msgId;
    if (from) url += '&from=' + from;
    window.location = url;
}

// Refresh the current page
function refreshPage() {
    window.location.reload();
}

// Mark all as read for current page
function markAllAsRead() {
    var page = document.querySelector('.main-content') ? document.querySelector('.main-content').getAttribute('data-page') : 'inbox';
    fetch('api/messages.php?action=mark_all_read', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read&page=' + encodeURIComponent(page)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('All messages marked as read', 'success');
            setTimeout(function() { window.location.reload(); }, 600);
        }
    })
    .catch(function() {
        showToast('Failed to mark messages as read', 'error');
    });
}

// Toggle trash selection mode
function toggleTrashMode() {
    document.body.classList.add('trash-mode');
    var toggleBtn = document.getElementById('trash-toggle-btn');
    var confirmBtn = document.getElementById('trash-confirm-btn');
    var cancelBtn = document.getElementById('trash-cancel-btn');
    if (toggleBtn) toggleBtn.style.display = 'none';
    if (confirmBtn) confirmBtn.style.display = 'inline-flex';
    if (cancelBtn) cancelBtn.style.display = 'inline-flex';
}

// Cancel trash selection mode
function cancelTrashMode() {
    document.body.classList.remove('trash-mode');
    var toggleBtn = document.getElementById('trash-toggle-btn');
    var confirmBtn = document.getElementById('trash-confirm-btn');
    var cancelBtn = document.getElementById('trash-cancel-btn');
    if (toggleBtn) toggleBtn.style.display = 'inline-flex';
    if (confirmBtn) confirmBtn.style.display = 'none';
    if (cancelBtn) cancelBtn.style.display = 'none';
    // Uncheck all
    var cbs = document.querySelectorAll('.msg-select-cb');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = false;
    }
    var rows = document.querySelectorAll('.msg-row.selected, .message-item.selected');
    for (var j = 0; j < rows.length; j++) {
        rows[j].classList.remove('selected');
    }
    var selectAll = document.querySelector('.select-all-cb');
    if (selectAll) selectAll.checked = false;
}

// Get selected message IDs
function getSelectedIds() {
    var cbs = document.querySelectorAll('.msg-select-cb:checked');
    var ids = [];
    for (var i = 0; i < cbs.length; i++) {
        ids.push(cbs[i].value);
    }
    return ids;
}

// Select/deselect all checkboxes
function toggleSelectAll(masterCb) {
    var cbs = document.querySelectorAll('.msg-select-cb');
    for (var i = 0; i < cbs.length; i++) {
        cbs[i].checked = masterCb.checked;
        var row = cbs[i].closest('.msg-row') || cbs[i].closest('.message-item');
        if (row) row.classList.toggle('selected', masterCb.checked);
    }
}

// Confirm trash/delete action
function confirmTrashAction() {
    var ids = getSelectedIds();
    if (ids.length === 0) {
        showToast('No messages selected', 'error');
        return;
    }
    var page = document.querySelector('.main-content') ? document.querySelector('.main-content').getAttribute('data-page') : 'inbox';
    var isTrashPage = (page === 'trash');
    var action = isTrashPage ? 'batch_permanent_delete' : 'batch_delete';
    var confirmMsg = isTrashPage
        ? 'Permanently delete ' + ids.length + ' selected message(s)?'
        : 'Move ' + ids.length + ' selected message(s) to trash?';

    customConfirm(confirmMsg, function() {
        fetch('api/messages.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=' + action + '&ids=' + ids.join(',')
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast(isTrashPage ? 'Messages permanently deleted' : 'Messages moved to trash', 'success');
                setTimeout(function() { window.location.reload(); }, 600);
            } else {
                showToast(data.error || 'Operation failed', 'error');
            }
        })
        .catch(function() {
            showToast('Network error. Please try again.', 'error');
        });
    });
}

// ============ GLOBAL ESCAPE KEY HANDLER ============
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        // 0. Autocomplete dropdowns
        var dropdowns = document.querySelectorAll('.autocomplete-dropdown');
        var dropdownClosed = false;
        for (var i = 0; i < dropdowns.length; i++) {
            if (dropdowns[i].style.display === 'block') {
                dropdowns[i].style.display = 'none';
                dropdownClosed = true;
            }
        }
        if (dropdownClosed) return;

        // 1. Custom Dialog Modal (Alert/Confirm)
        var customDialog = document.getElementById('custom-dialog-modal');
        if (customDialog && customDialog.style.display !== 'none' && customDialog.style.display !== '') {
            var cancelBtn = document.getElementById('custom-dialog-cancel');
            var okBtn = document.getElementById('custom-dialog-ok');
            if (cancelBtn && cancelBtn.style.display !== 'none') {
                cancelBtn.click();
            } else if (okBtn) {
                okBtn.click();
            }
            return;
        }

        // 2. Unsaved Changes Modal
        var unsavedModal = document.getElementById('unsaved-modal');
        if (unsavedModal && unsavedModal.style.display !== 'none' && unsavedModal.style.display !== '') {
            var btns = unsavedModal.querySelectorAll('.btn-secondary');
            if (btns.length > 0) btns[0].click();
            return;
        }

        // 2.5 Preview Modal
        var previewModal = document.getElementById('preview-modal');
        if (previewModal && previewModal.style.display !== 'none' && previewModal.style.display !== '') {
            if (typeof closePreviewModal === 'function') closePreviewModal();
            return;
        }

        // 3. Address Book Modals
        var abModal = document.getElementById('ab-modal');
        if (abModal && abModal.style.display !== 'none' && abModal.style.display !== '') {
            if (typeof closeAddressBook === 'function') closeAddressBook();
            return;
        }
        var calAbModal = document.getElementById('cal-ab-modal');
        if (calAbModal && calAbModal.style.display !== 'none' && calAbModal.style.display !== '') {
            if (typeof closeCalAB === 'function') closeCalAB();
            return;
        }

        // 4. Calendar Event Modal
        var eventModal = document.getElementById('event-modal');
        if (eventModal && eventModal.style.display !== 'none' && eventModal.style.display !== '') {
            if (typeof closeEventModal === 'function') closeEventModal();
            return;
        }
        
        // 5. Contact Modal
        var contactModal = document.getElementById('contact-modal');
        if (contactModal && contactModal.style.display !== 'none' && contactModal.style.display !== '') {
            if (typeof closeContactModal === 'function') closeContactModal();
            return;
        }
    }
});
