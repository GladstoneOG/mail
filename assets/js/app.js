/**
 * RSPIK Mail - Client-side JavaScript
 * ES5 compatible for older browsers
 */

// ============ COMPOSE FORM ============
(function() {
    var form = document.getElementById('compose-form');
    if (!form) return;

    // Sync rich editor content to hidden input before submit
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        window.hasUnsavedChanges = false;
        syncEditorContent();
        sendMessage(false);
    });

    // Autocomplete for recipient fields
    var recipientFields = ['to', 'cc', 'bcc'];
    recipientFields.forEach(function(fieldId) {
        var input = document.getElementById(fieldId);
        var dropdown = document.getElementById(fieldId + '-dropdown');
        if (!input || !dropdown) return;

        var debounceTimer = null;
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var val = input.value;
                var parts = val.split(',');
                var current = parts[parts.length - 1].trim();
                if (current.length < 1) { dropdown.style.display = 'none'; return; }

                fetch('api/users.php?action=search&q=' + encodeURIComponent(current))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.users || data.users.length === 0) { dropdown.style.display = 'none'; return; }
                        dropdown.innerHTML = '';
                        data.users.forEach(function(u) {
                            var div = document.createElement('div');
                            div.className = 'autocomplete-item';
                            div.innerHTML = '<span class="ac-name">' + escHtml(u.display_name) + '</span><span class="ac-username">@' + escHtml(u.username) + '</span>';
                            div.addEventListener('click', function() {
                                parts[parts.length - 1] = u.username;
                                input.value = parts.join(', ') + ', ';
                                dropdown.style.display = 'none';
                                input.focus();
                            });
                            dropdown.appendChild(div);
                        });
                        dropdown.style.display = 'block';
                    });
            }, 250);
        });

        document.addEventListener('click', function(e) {
            if (e.target !== input) dropdown.style.display = 'none';
        });
    });

    // File list display
    var fileInput = document.getElementById('attachments');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            var list = document.getElementById('file-list');
            list.innerHTML = '';
            for (var i = 0; i < this.files.length; i++) {
                var f = this.files[i];
                var div = document.createElement('div');
                div.className = 'file-list-item';
                div.innerHTML = '<span>&#x1F4C4; ' + escHtml(f.name) + '</span><span style="color:var(--text3);font-size:11px">' + formatSize(f.size) + '</span>';
                list.appendChild(div);
            }
        });
    }
    // Unsaved changes tracking
    window.hasUnsavedChanges = false;
    form.addEventListener('input', function() { window.hasUnsavedChanges = true; });
    
    var editor = document.getElementById('editor');
    if (editor) {
        editor.addEventListener('input', function() { window.hasUnsavedChanges = true; });
        editor.addEventListener('keyup', function() { window.hasUnsavedChanges = true; });
    }
    
    window.addEventListener('beforeunload', function(e) {
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
    document.getElementById('unsaved-save-btn').addEventListener('click', function() {
        window.hasUnsavedChanges = false;
        saveDraft();
    });
    document.getElementById('unsaved-leave-btn').addEventListener('click', function() {
        window.hasUnsavedChanges = false;
        window.location.href = pendingNavUrl;
    });

    document.addEventListener('click', function(e) {
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
    formData.append('action', 'send');
    formData.append('is_draft', isDraft ? '1' : '0');

    fetch('api/messages.php?action=send', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.error) {
                showToast(data.error, 'error');
                if (btn) { btn.disabled = false; btn.textContent = isDraft ? '📝 Save Draft' : '📨 Send Message'; }
            } else {
                showToast(isDraft ? 'Draft saved!' : 'Message sent!', 'success');
                setTimeout(function() {
                    window.location = 'index.php?page=' + (isDraft ? 'drafts' : 'sent');
                }, 800);
            }
        })
        .catch(function(err) {
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
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            btn.className = data.starred ? 'star-btn starred' : 'star-btn';
            btn.innerHTML = data.starred ? '&#x2605;' : '&#x2606;';
        }
    });
}

function deleteMessage(msgId, backPage) {
    if (!confirm('Move this message to trash?')) return;
    fetch('api/messages.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete&message_id=' + msgId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('Message deleted', 'success');
            setTimeout(function() { window.location = 'index.php?page=' + (backPage || 'inbox'); }, 500);
        }
    });
}

function restoreMessage(msgId) {
    fetch('api/messages.php?action=restore', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=restore&message_id=' + msgId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Message restored', 'success'); setTimeout(function() { location.reload(); }, 500); }
    });
}

function emptyTrash() {
    if (!confirm('Permanently delete all messages in trash?')) return;
    fetch('api/messages.php?action=empty_trash', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=empty_trash'
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { showToast('Trash emptied', 'success'); setTimeout(function() { location.reload(); }, 500); }
    });
}

function deleteDraft(msgId) {
    if (!confirm('Delete this draft?')) return;
    fetch('api/messages.php?action=delete_draft', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_draft&message_id=' + msgId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { location.reload(); }
    });
}

// ============ RETRACT / UNSEND ============
function retractMessage(msgId) {
    if (!confirm('Retract this message? Recipients will no longer be able to read it.')) return;
    fetch('api/messages.php?action=retract', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=retract&message_id=' + msgId
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast('Message retracted!', 'success');
            setTimeout(function() { window.location = 'index.php?page=sent'; }, 800);
        } else {
            showToast(data.error || 'Failed to retract', 'error');
        }
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
    if (btn) btn.innerHTML = next === 'light' ? '&#x1F319;' : '&#x2600;';
}

// ============ NOTIFICATION DROPDOWN (#3) ============
function toggleNotifDropdown() {
    var dd = document.getElementById('notif-dropdown');
    if (!dd) return;
    var isOpen = dd.classList.contains('open');
    dd.classList.toggle('open');
    if (!isOpen) {
        // Close on outside click
        setTimeout(function() {
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
(function() {
    if (typeof APP_CONFIG === 'undefined') return;

    var lastUnreadCount = -1;
    var originalTitle = document.title;
    var titleFlashInterval = null;
    var notifSoundEnabled = true;

    // ---- Native Notification API (works on HTTPS / localhost only) ----
    function requestNotifPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(function(perm) {
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
        titleFlashInterval = setInterval(function() {
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
        } catch (e) {}
    }

    // ---- Main polling ----
    function checkNew() {
        fetch('api/messages.php?action=check_new')
            .then(function(r) { return r.json(); })
            .then(function(data) {
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
                            notif.onclick = function() {
                                window.focus();
                                window.location = 'index.php?page=inbox';
                                notif.close();
                            };
                            setTimeout(function() { notif.close(); }, 6000);
                        } catch (e) {}
                    }

                    // Auto-refresh the current page's message table
                    refreshCurrentPage();

                    // Refresh notification dropdown content
                    refreshNotifDropdown();
                }

                lastUnreadCount = data.unread || 0;
            }).catch(function() {});
    }

    // Refresh the message table on the current page without a full reload
    function refreshCurrentPage() {
        var page = APP_CONFIG.currentPage;
        if (!page || page === 'compose' || page === 'view' || page === 'admin' || page === 'profile') return;

        // Get current URL params for sort/search preservation
        var currentUrl = window.location.href;
        fetch(currentUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.text(); })
            .then(function(html) {
                // Extract the content-area from the returned HTML
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, 'text/html');
                var newContent = doc.querySelector('.content-area');
                var currentContent = document.querySelector('.content-area');
                if (newContent && currentContent) {
                    currentContent.innerHTML = newContent.innerHTML;
                }
            }).catch(function() {});
    }

    // Refresh notification dropdown items
    function refreshNotifDropdown() {
        fetch('api/messages.php?action=notif_list')
            .then(function(r) { return r.json(); })
            .then(function(data) {
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
            }).catch(function() {});
    }

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
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .3s';
        setTimeout(function() { toast.remove(); }, 300);
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
