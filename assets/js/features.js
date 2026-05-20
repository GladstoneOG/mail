/**
 * Features JS - Context Menu, Tags, Rules Management
 */
(function(){

var _tags = [];
var _folders = [];

function escH(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;}

// ===== CONTEXT MENU =====
var ctxMenu = null;
var ctxMsgId = null;
var ctxMsgData = {};

document.addEventListener('DOMContentLoaded', function(){
    ctxMenu = document.getElementById('ctx-menu');
    loadUserTags();
    loadFoldersForCtx();
    // Render tags on all visible rows at page load
    renderAllRowTags();
});

document.addEventListener('contextmenu', function(e){
    var row = e.target.closest('.msg-row, .message-item');
    if(!row) return;
    e.preventDefault();
    ctxMsgId = row.getAttribute('data-msg-id') || row.getAttribute('data-id');
    if(!ctxMsgId) return;
    // Get sender username from data attribute
    var senderUser = row.getAttribute('data-sender-username') || '';
    ctxMsgData = {
        id: ctxMsgId,
        starred: row.querySelector('.star-btn.starred') ? true : false,
        senderUsername: senderUser,
        from: (row.querySelector('.col-from-cell span') || {}).textContent || '',
        subject: (row.querySelector('.msg-subj-text') || {}).textContent || '',
        isRead: !row.classList.contains('unread')
    };
    showContextMenu(e.clientX, e.clientY);
});

document.addEventListener('click', function(e){
    if(ctxMenu && !ctxMenu.contains(e.target)) ctxMenu.style.display = 'none';
});

function showContextMenu(x, y){
    if(!ctxMenu) return;
    var currentPage = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.currentPage) ? APP_CONFIG.currentPage : '';
    var html = '';

    if(currentPage === 'drafts') {
        // Drafts: only Edit and Delete
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'edit_draft\')"><span class="ctx-menu-icon">✏️</span>Edit Draft</button>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<button class="ctx-menu-item ctx-danger" onclick="ctxAction(\'delete_draft\')"><span class="ctx-menu-icon">🗑️</span>Delete Draft</button>';
    } else if(currentPage === 'trash') {
        // Trash: only Restore and Delete permanently
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'restore\')"><span class="ctx-menu-icon">↩️</span>Restore to Inbox</button>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<button class="ctx-menu-item ctx-danger" onclick="ctxAction(\'delete\')"><span class="ctx-menu-icon">🗑️</span>Delete Permanently</button>';
    } else {
        // Full context menu for inbox, sent, starred, folder
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'reply\')"><span class="ctx-menu-icon">↩️</span>Reply</button>';
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'forward\')"><span class="ctx-menu-icon">↪️</span>Forward</button>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'star\')"><span class="ctx-menu-icon">' + (ctxMsgData.starred ? '⭐' : '🌟') + '</span>' + (ctxMsgData.starred ? 'Unstar' : 'Star') + '</button>';
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'' + (ctxMsgData.isRead ? 'mark_unread' : 'mark_read') + '\')"><span class="ctx-menu-icon">' + (ctxMsgData.isRead ? '✉️' : '📩') + '</span>Mark as ' + (ctxMsgData.isRead ? 'unread' : 'read') + '</button>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<div class="ctx-menu-sub">Tags</div>';
        html += '<div class="ctx-tag-list" id="ctx-tag-list">';
        if(_tags.length === 0) html += '<span style="font-size:11px;color:var(--text3);padding:2px 4px">No tags yet</span>';
        for(var i=0;i<_tags.length;i++){
            html += '<span class="ctx-tag-chip" style="background:'+escH(_tags[i].color)+'" data-tag-id="'+_tags[i].id+'" onclick="ctxToggleTag('+_tags[i].id+',this)">'+escH(_tags[i].name)+'</span>';
        }
        html += '</div>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'move\')"><span class="ctx-menu-icon">📂</span>Move to...</button>';
        html += '<button class="ctx-menu-item" onclick="ctxAction(\'find_sender\')"><span class="ctx-menu-icon">🔍</span>Find emails from sender</button>';
        html += '<div class="ctx-menu-sep"></div>';
        html += '<button class="ctx-menu-item ctx-danger" onclick="ctxAction(\'delete\')"><span class="ctx-menu-icon">🗑️</span>Delete</button>';
    }

    ctxMenu.innerHTML = html;
    ctxMenu.style.display = 'block';
    var mw = ctxMenu.offsetWidth, mh = ctxMenu.offsetHeight;
    var wx = window.innerWidth, wy = window.innerHeight;
    if(x + mw > wx) x = wx - mw - 8;
    if(y + mh > wy) y = wy - mh - 8;
    if(x < 0) x = 8; if(y < 0) y = 8;
    ctxMenu.style.left = x + 'px';
    ctxMenu.style.top = y + 'px';
    loadMessageTags(ctxMsgId);
}

function loadMessageTags(msgId){
    fetch('api/tags.php?action=get_message_tags&message_id='+msgId)
        .then(function(r){return r.json();})
        .then(function(d){
            if(!d.tags) return;
            var activeIds = {};
            for(var i=0;i<d.tags.length;i++) activeIds[d.tags[i].id] = true;
            var chips = document.querySelectorAll('#ctx-tag-list .ctx-tag-chip');
            for(var j=0;j<chips.length;j++){
                var tid = parseInt(chips[j].getAttribute('data-tag-id'),10);
                if(activeIds[tid]) chips[j].classList.add('ctx-tag-active');
            }
        }).catch(function(){});
}

window.ctxToggleTag = function(tagId, chip){
    var isActive = chip.classList.contains('ctx-tag-active');
    var action = isActive ? 'unassign' : 'assign';
    fetch('api/tags.php?action='+action, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'message_id='+ctxMsgId+'&tag_id='+tagId})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){
                chip.classList.toggle('ctx-tag-active');
                refreshRowTags(ctxMsgId);
            }
        });
};

window.ctxAction = function(action){
    ctxMenu.style.display = 'none';
    var id = ctxMsgId;
    if(action === 'reply') window.location = 'index.php?page=compose&reply='+id;
    else if(action === 'forward') window.location = 'index.php?page=compose&forward='+id;
    else if(action === 'star') toggleStarById(id);
    else if(action === 'mark_read') markRead(id);
    else if(action === 'mark_unread') markUnread(id);
    else if(action === 'move') ctxMoveToFolder(id);
    else if(action === 'find_sender') findFromSender();
    else if(action === 'delete') deleteMsg(id);
    else if(action === 'edit_draft') window.location = 'index.php?page=compose&draft='+id;
    else if(action === 'delete_draft') {
        if(typeof deleteDraft === 'function') deleteDraft(parseInt(id,10));
    }
    else if(action === 'restore') {
        if(typeof restoreMessage === 'function') restoreMessage(parseInt(id,10));
    }
};

function toggleStarById(id){
    var btn = document.querySelector('[data-msg-id="'+id+'"] .star-btn, [data-id="'+id+'"] .star-btn');
    if(btn) btn.click();
}

function markRead(id){
    fetch('api/messages.php?action=mark_read',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'message_id='+id})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){
                showToast('Marked as read','success');
                var row = document.querySelector('[data-msg-id="'+id+'"]');
                if(row) row.classList.remove('unread');
            }
        });
}

function markUnread(id){
    fetch('api/messages.php?action=mark_unread',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'message_id='+id})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){
                showToast('Marked as unread','success');
                var row = document.querySelector('[data-msg-id="'+id+'"]');
                if(row) row.classList.add('unread');
            }
        });
}

// Issue #3: Pre-select the right-clicked message and open the move modal
function ctxMoveToFolder(id){
    // Uncheck all, then check only the target message
    var allCbs = document.querySelectorAll('.msg-select-cb');
    for(var i=0;i<allCbs.length;i++) allCbs[i].checked = false;
    var targetCb = document.querySelector('.msg-select-cb[value="'+id+'"]');
    if(targetCb) targetCb.checked = true;
    // Open the move-to modal (function from app.js)
    if(typeof openMoveToModal === 'function') openMoveToModal();
}

// Issue #4: Search by sender username specifically
function findFromSender(){
    var username = ctxMsgData.senderUsername;
    if(!username){
        // Fallback: use display name
        username = ctxMsgData.from.trim();
    }
    if(username) window.location = 'index.php?page=inbox&sf=sender&q=' + encodeURIComponent(username);
}

function deleteMsg(id){
    if(typeof customConfirm === 'function'){
        customConfirm('Move this message to trash?', function(){
            fetch('api/messages.php?action=delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'message_id='+id})
                .then(function(r){return r.json();})
                .then(function(d){
                    if(d.success){
                        showToast('Moved to trash','success');
                        var row = document.querySelector('[data-msg-id="'+id+'"]');
                        if(row) row.remove();
                    }
                });
        });
    }
}

function refreshRowTags(msgId){
    fetch('api/tags.php?action=get_message_tags&message_id='+msgId)
        .then(function(r){return r.json();})
        .then(function(d){
            var row = document.querySelector('[data-msg-id="'+msgId+'"], [data-id="'+msgId+'"]');
            if(!row) return;
            var subjCell = row.querySelector('.msg-subj-text, .msg-subject');
            if(!subjCell) return;
            var existing = row.querySelector('.msg-tags');
            if(existing) existing.remove();
            if(d.tags && d.tags.length > 0){
                var span = document.createElement('span');
                span.className = 'msg-tags';
                for(var i=0;i<d.tags.length;i++){
                    span.innerHTML += '<span class="msg-tag-chip" style="background:'+escH(d.tags[i].color)+'">'+escH(d.tags[i].name)+'</span>';
                }
                // Insert after subject cell
                subjCell.parentNode.insertBefore(span, subjCell.nextSibling);
            }
        });
}

// Render tags for all visible rows on page load
function renderAllRowTags(){
    var rows = document.querySelectorAll('.msg-row[data-msg-id]');
    if(rows.length === 0) return;
    var ids = [];
    for(var i=0;i<rows.length;i++) ids.push(rows[i].getAttribute('data-msg-id'));
    // Batch fetch - load each one (could be optimized with batch API later)
    for(var j=0;j<ids.length;j++) refreshRowTags(ids[j]);
}

// ===== TAGS MANAGEMENT =====
function loadUserTags(){
    fetch('api/tags.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){ _tags = d.tags || []; })
        .catch(function(){});
}

window.openTagManager = function(){
    document.getElementById('tag-manager-modal').style.display = 'flex';
    renderTagManager();
};
window.closeTagManager = function(){
    document.getElementById('tag-manager-modal').style.display = 'none';
};

function renderTagManager(){
    fetch('api/tags.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){
            _tags = d.tags || [];
            var list = document.getElementById('tag-manager-list');
            if(_tags.length === 0){
                list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text3)">No tags yet. Create one below!</div>';
                return;
            }
            var html = '';
            for(var i=0;i<_tags.length;i++){
                html += '<div class="tag-manager-item">';
                html += '<div class="tag-manager-color" style="background:'+escH(_tags[i].color)+'" title="Change color"></div>';
                html += '<span class="tag-manager-name">'+escH(_tags[i].name)+'</span>';
                html += '<div class="tag-manager-actions">';
                html += '<button class="btn btn-ghost btn-sm" onclick="deleteTag('+_tags[i].id+')" style="color:var(--danger);font-size:14px" title="Delete">×</button>';
                html += '</div></div>';
            }
            list.innerHTML = html;
        });
}

window.createTag = function(){
    var name = document.getElementById('tag-new-name').value.trim();
    var color = document.getElementById('tag-new-color').value;
    if(!name){showToast('Enter a tag name','error');return;}
    fetch('api/tags.php?action=create',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'name='+encodeURIComponent(name)+'&color='+encodeURIComponent(color)})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.error){showToast(d.error,'error');return;}
            showToast('Tag created!','success');
            document.getElementById('tag-new-name').value = '';
            renderTagManager();
            loadUserTags();
        });
};

window.deleteTag = function(id){
    customConfirm('Delete this tag? It will be removed from all messages.', function(){
        fetch('api/tags.php?action=delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'tag_id='+id})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.success){
                    showToast('Tag deleted','success');
                    renderTagManager();
                    loadUserTags();
                    renderAllRowTags();
                }
            });
    });
};

// ===== RULES MANAGEMENT =====
function loadFoldersForCtx(){
    fetch('api/messages.php?action=get_folders')
        .then(function(r){return r.json();})
        .then(function(d){ _folders = d.folders || []; })
        .catch(function(){});
}

window.openRulesManager = function(){
    document.getElementById('rules-manager-modal').style.display = 'flex';
    renderRulesList();
};
window.closeRulesManager = function(){
    document.getElementById('rules-manager-modal').style.display = 'none';
};

function renderRulesList(){
    fetch('api/rules.php?action=list')
        .then(function(r){return r.json();})
        .then(function(d){
            var rules = d.rules || [];
            var list = document.getElementById('rules-list');
            if(rules.length === 0){
                list.innerHTML = '<div style="text-align:center;padding:30px;color:var(--text3)"><div style="font-size:32px;margin-bottom:8px">⚙️</div><p>No rules yet. Create one to automatically manage incoming mail.</p></div>';
                return;
            }
            var html = '';
            for(var i=0;i<rules.length;i++){
                var r = rules[i];
                var conds = r.conditions || [];
                var acts = r.actions || [];
                html += '<div class="rule-card"><div class="rule-card-header">';
                html += '<span class="rule-card-name">'+escH(r.name)+'</span>';
                html += '<div class="rule-card-toggle'+(r.is_active?' active':'')+'" onclick="toggleRule('+r.id+',this)"></div>';
                html += '</div>';
                var summary = 'When ' + r.match_type.toUpperCase() + ' of: ';
                for(var c=0;c<conds.length;c++){
                    if(c>0) summary += ', ';
                    summary += (conds[c].field||'') + ' ' + (conds[c].operator||'').replace('_',' ') + ' "' + (conds[c].value||'') + '"';
                }
                summary += ' → ';
                for(var a=0;a<acts.length;a++){
                    if(a>0) summary += ', ';
                    summary += (acts[a].type||'').replace(/_/g,' ');
                }
                html += '<div class="rule-card-summary">'+escH(summary)+'</div>';
                html += '<div class="rule-card-actions">';
                html += '<button class="btn btn-ghost btn-sm" onclick="editRule('+r.id+')">✏️ Edit</button>';
                html += '<button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="deleteRule('+r.id+')">🗑 Delete</button>';
                html += '</div></div>';
            }
            list.innerHTML = html;
        });
}

window.toggleRule = function(id, el){
    fetch('api/rules.php?action=toggle',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'rule_id='+id})
        .then(function(r){return r.json();})
        .then(function(d){ if(d.success) el.classList.toggle('active', !!d.is_active); });
};

window.deleteRule = function(id){
    customConfirm('Delete this rule?', function(){
        fetch('api/rules.php?action=delete',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'rule_id='+id})
            .then(function(r){return r.json();})
            .then(function(d){if(d.success){showToast('Rule deleted','success');renderRulesList();}});
    });
};

window.openRuleEditor = function(){
    document.getElementById('rule-edit-id').value = '';
    document.getElementById('rule-name').value = '';
    document.getElementById('rule-match-type').value = 'all';
    document.getElementById('rule-conditions-list').innerHTML = '';
    document.getElementById('rule-actions-list').innerHTML = '';
    document.getElementById('rule-editor-title').textContent = '📝 New Rule';
    document.getElementById('rule-save-btn').textContent = 'Create Rule';
    addRuleCondition();
    addRuleAction();
    document.getElementById('rule-editor-modal').style.display = 'flex';
};

window.closeRuleEditor = function(){
    document.getElementById('rule-editor-modal').style.display = 'none';
};

window.addRuleCondition = function(){
    var list = document.getElementById('rule-conditions-list');
    var row = document.createElement('div');
    row.className = 'rule-condition-row';
    row.innerHTML = '<select class="rule-cond-field"><option value="sender">Sender</option><option value="subject">Subject</option><option value="body">Body</option><option value="has_attachment">Has attachment</option></select>'
        + '<select class="rule-cond-op"><option value="contains">contains</option><option value="not_contains">not contains</option><option value="equals">equals</option><option value="starts_with">starts with</option><option value="ends_with">ends with</option></select>'
        + '<input type="text" class="rule-cond-value" placeholder="Value...">'
        + '<button type="button" class="rule-remove-btn" onclick="this.parentElement.remove()">×</button>';
    list.appendChild(row);
};

window.addRuleAction = function(){
    var list = document.getElementById('rule-actions-list');
    var row = document.createElement('div');
    row.className = 'rule-action-row';
    var folderOpts = '';
    for(var i=0;i<_folders.length;i++) folderOpts += '<option value="'+_folders[i].id+'">'+escH(_folders[i].name)+'</option>';
    row.innerHTML = '<select class="rule-act-type" onchange="ruleActionTypeChange(this)">'
        + '<option value="move_to_folder">Move to folder</option><option value="move_to_trash">Move to trash</option>'
        + '<option value="mark_read">Mark as read</option><option value="star">Star</option><option value="add_tag">Add tag</option></select>'
        + '<select class="rule-act-value"><option value="">— Select folder —</option>'+folderOpts+'</select>'
        + '<button type="button" class="rule-remove-btn" onclick="this.parentElement.remove()">×</button>';
    list.appendChild(row);
};

window.ruleActionTypeChange = function(sel){
    var row = sel.closest('.rule-action-row');
    var valSel = row.querySelector('.rule-act-value');
    var type = sel.value;
    if(type === 'move_to_folder'){
        var opts = '<option value="">— Select folder —</option>';
        for(var i=0;i<_folders.length;i++) opts += '<option value="'+_folders[i].id+'">'+escH(_folders[i].name)+'</option>';
        valSel.innerHTML = opts; valSel.style.display = '';
    } else if(type === 'add_tag'){
        var opts = '<option value="">— Select tag —</option>';
        for(var i=0;i<_tags.length;i++) opts += '<option value="'+_tags[i].id+'">'+escH(_tags[i].name)+'</option>';
        valSel.innerHTML = opts; valSel.style.display = '';
    } else { valSel.style.display = 'none'; }
};

window.editRule = function(id){
    fetch('api/rules.php?action=list').then(function(r){return r.json();}).then(function(d){
        var rules = d.rules || [], rule = null;
        for(var i=0;i<rules.length;i++) if(rules[i].id == id) rule = rules[i];
        if(!rule) return;
        openRuleEditor();
        document.getElementById('rule-edit-id').value = rule.id;
        document.getElementById('rule-name').value = rule.name;
        document.getElementById('rule-match-type').value = rule.match_type;
        document.getElementById('rule-editor-title').textContent = '✏️ Edit Rule';
        document.getElementById('rule-save-btn').textContent = 'Save Changes';
        var condList = document.getElementById('rule-conditions-list'); condList.innerHTML = '';
        var conds = rule.conditions || [];
        for(var c=0;c<conds.length;c++){
            addRuleCondition();
            var rows = condList.querySelectorAll('.rule-condition-row');
            var row = rows[rows.length-1];
            row.querySelector('.rule-cond-field').value = conds[c].field || 'sender';
            row.querySelector('.rule-cond-op').value = conds[c].operator || 'contains';
            row.querySelector('.rule-cond-value').value = conds[c].value || '';
        }
        var actList = document.getElementById('rule-actions-list'); actList.innerHTML = '';
        var acts = rule.actions || [];
        for(var a=0;a<acts.length;a++){
            addRuleAction();
            var rows = actList.querySelectorAll('.rule-action-row');
            var row = rows[rows.length-1];
            row.querySelector('.rule-act-type').value = acts[a].type || 'move_to_folder';
            ruleActionTypeChange(row.querySelector('.rule-act-type'));
            var valSel = row.querySelector('.rule-act-value');
            if(valSel && acts[a].value) valSel.value = acts[a].value;
        }
    });
};

window.saveRule = function(){
    var id = document.getElementById('rule-edit-id').value;
    var name = document.getElementById('rule-name').value.trim();
    var matchType = document.getElementById('rule-match-type').value;
    if(!name){showToast('Rule name is required','error');return;}
    var condRows = document.querySelectorAll('#rule-conditions-list .rule-condition-row');
    var conditions = [];
    for(var i=0;i<condRows.length;i++){
        conditions.push({field:condRows[i].querySelector('.rule-cond-field').value, operator:condRows[i].querySelector('.rule-cond-op').value, value:condRows[i].querySelector('.rule-cond-value').value});
    }
    var actRows = document.querySelectorAll('#rule-actions-list .rule-action-row');
    var actions = [];
    for(var i=0;i<actRows.length;i++){
        var type = actRows[i].querySelector('.rule-act-type').value;
        var valSel = actRows[i].querySelector('.rule-act-value');
        actions.push({type:type, value:valSel?valSel.value:''});
    }
    if(conditions.length===0){showToast('Add at least one condition','error');return;}
    if(actions.length===0){showToast('Add at least one action','error');return;}
    var action = id ? 'update' : 'create';
    var body = 'name='+encodeURIComponent(name)+'&match_type='+matchType
        +'&conditions='+encodeURIComponent(JSON.stringify(conditions))
        +'&actions='+encodeURIComponent(JSON.stringify(actions));
    if(id) body += '&rule_id='+id+'&is_active=1';
    fetch('api/rules.php?action='+action,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.error){showToast(d.error,'error');return;}
            showToast(id?'Rule updated!':'Rule created!','success');
            closeRuleEditor();
            renderRulesList();
        });
};

})();
