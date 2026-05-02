/**
 * Calendar JS - Monthly / Daily / Agenda views + Event CRUD
 */
(function(){
var currentView = 'month';
var viewDate = new Date();
var cachedEvents = [];
var selectedColor = '#6366f1';

window.addEventListener('DOMContentLoaded', function(){
    // Check URL params for initial view/date (from sidebar mini calendar links)
    var params = new URLSearchParams(window.location.search);
    if(params.get('date')){
        viewDate = new Date(params.get('date')+'T00:00:00');
    }
    if(params.get('view')==='day'){
        currentView = 'day';
        document.getElementById('cal-month-view').style.display = 'none';
        document.getElementById('cal-day-view').style.display = '';
        document.getElementById('cal-agenda-view').style.display = 'none';
        document.querySelectorAll('.cal-tab').forEach(function(t){t.classList.remove('active');});
        var dayTab = document.querySelector('.cal-tab[data-view="day"]');
        if(dayTab) dayTab.classList.add('active');
    } else if(params.get('view')==='agenda'){
        currentView = 'agenda';
        document.getElementById('cal-month-view').style.display = 'none';
        document.getElementById('cal-day-view').style.display = 'none';
        document.getElementById('cal-agenda-view').style.display = '';
        document.querySelectorAll('.cal-tab').forEach(function(t){t.classList.remove('active');});
        var agTab = document.querySelector('.cal-tab[data-view="agenda"]');
        if(agTab) agTab.classList.add('active');
    }
    loadEvents();
    setupColorPicker();
    setupAttendeeAutocomplete();
});

// ── Navigation ──
window.calNav = function(dir){
    if(currentView==='month') viewDate.setMonth(viewDate.getMonth()+dir);
    else if(currentView==='day') viewDate.setDate(viewDate.getDate()+dir);
    else viewDate.setDate(viewDate.getDate()+(dir*30));
    loadEvents();
};
window.calToday = function(){ viewDate = new Date(); loadEvents(); };
window.switchView = function(v, btn){
    currentView = v;
    document.querySelectorAll('.cal-tab').forEach(function(t){t.classList.remove('active');});
    if(btn) btn.classList.add('active');
    document.getElementById('cal-month-view').style.display = v==='month'?'':'none';
    document.getElementById('cal-day-view').style.display = v==='day'?'':'none';
    document.getElementById('cal-agenda-view').style.display = v==='agenda'?'':'none';
    renderView();
};

function updateTitle(){
    var t = document.getElementById('cal-title');
    if(!t) return;
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    if(currentView==='month') t.textContent = months[viewDate.getMonth()]+' '+viewDate.getFullYear();
    else if(currentView==='day'){
        var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        t.textContent = days[viewDate.getDay()]+', '+months[viewDate.getMonth()]+' '+viewDate.getDate()+', '+viewDate.getFullYear();
    } else t.textContent = 'Upcoming Events';
}

// ── Data Loading ──
function loadEvents(){
    var start, end;
    if(currentView==='month'){
        var y=viewDate.getFullYear(), m=viewDate.getMonth();
        start = new Date(y,m,1); start.setDate(start.getDate()-start.getDay());
        end = new Date(y,m+1,0); end.setDate(end.getDate()+(6-end.getDay()));
    } else if(currentView==='day'){
        start = new Date(viewDate); start.setHours(0,0,0,0);
        end = new Date(viewDate); end.setHours(23,59,59,999);
    } else {
        start = new Date(); start.setHours(0,0,0,0);
        end = new Date(); end.setDate(end.getDate()+30);
    }
    var s = fmt(start), e = fmt(end);
    fetch('api/calendar.php?action=get_events&start='+s+'&end='+e)
        .then(function(r){return r.json();})
        .then(function(d){
            cachedEvents = d.events||[];
            renderView();
            updateSidebarMini();
        }).catch(function(){});
}
function fmt(d){ return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate()); }
function pad(n){ return n<10?'0'+n:''+n; }

function renderView(){
    updateTitle();
    if(currentView==='month') renderMonth();
    else if(currentView==='day') renderDay();
    else renderAgenda();
}

// ── Monthly View ──
function renderMonth(){
    var y=viewDate.getFullYear(), m=viewDate.getMonth();
    var first = new Date(y,m,1), last = new Date(y,m+1,0);
    var startDay = first.getDay();
    var totalDays = last.getDate();
    var today = new Date(); today.setHours(0,0,0,0);
    var html = '';
    var prevMonth = new Date(y,m,0);
    var prevDays = prevMonth.getDate();
    for(var i=0;i<startDay;i++){
        var d = prevDays-startDay+1+i;
        html += '<div class="cal-day cal-day-other">'+d+'</div>';
    }
    for(var d=1;d<=totalDays;d++){
        var dateStr = y+'-'+pad(m+1)+'-'+pad(d);
        var isToday = (new Date(y,m,d).getTime()===today.getTime());
        var dayEvents = getEventsForDate(dateStr);
        html += '<div class="cal-day'+(isToday?' cal-day-today':'')+'" onclick="calDayClick(\''+dateStr+'\')">';
        html += '<span class="cal-day-num'+(isToday?' cal-today-ring':'')+'">'+d+'</span>';
        if(dayEvents.length>0){
            html += '<div class="cal-day-events">';
            for(var j=0;j<Math.min(dayEvents.length,3);j++){
                html += '<div class="cal-event-chip" style="background:'+dayEvents[j].color+'" title="'+escH(dayEvents[j].title)+'">'+escH(truncStr(dayEvents[j].title,18))+'</div>';
            }
            if(dayEvents.length>3) html += '<div class="cal-event-more">+'+(dayEvents.length-3)+' more</div>';
            html += '</div>';
        }
        html += '</div>';
    }
    var cells = startDay+totalDays;
    var rem = cells%7===0?0:7-(cells%7);
    for(var i=1;i<=rem;i++) html += '<div class="cal-day cal-day-other">'+i+'</div>';
    document.getElementById('cal-days').innerHTML = html;
}

function getEventsForDate(dateStr){
    var result = [];
    for(var i=0;i<cachedEvents.length;i++){
        if(cachedEvents[i].start_date===dateStr) result.push(cachedEvents[i]);
    }
    return result;
}

window.calDayClick = function(dateStr){
    viewDate = new Date(dateStr+'T00:00:00');
    switchView('day', document.querySelector('.cal-tab[data-view="day"]'));
};

// ── Daily View ──
function renderDay(){
    var dateStr = fmt(viewDate);
    var dayEvents = getEventsForDate(dateStr);
    var html = '';
    for(var h=0;h<24;h++){
        var hr = pad(h)+':00';
        var now = new Date();
        var isCurrentHour = (viewDate.toDateString()===now.toDateString() && now.getHours()===h);
        html += '<div class="cal-hour'+(isCurrentHour?' cal-hour-now':'')+'">';
        html += '<div class="cal-hour-label">'+formatHour(h)+'</div>';
        html += '<div class="cal-hour-content" onclick="openEventModalAt(\''+dateStr+'\',\''+hr+'\')">';
        for(var i=0;i<dayEvents.length;i++){
            var ev = dayEvents[i];
            var evH = parseInt(ev.start_hour.split(':')[0],10);
            if(evH===h){
                var endH = parseInt(ev.end_hour.split(':')[0],10);
                var span = Math.max(1,endH-evH);
                html += '<div class="cal-day-event" style="background:'+ev.color+';min-height:'+(span*48)+'px" onclick="event.stopPropagation();showEventDetail('+ev.id+')">';
                html += '<div class="cal-de-title">'+escH(ev.title)+'</div>';
                html += '<div class="cal-de-time">'+ev.start_hour+' – '+ev.end_hour+'</div>';
                if(ev.location) html += '<div class="cal-de-loc">\ud83d\udccd '+escH(ev.location)+'</div>';
                html += '</div>';
            }
        }
        html += '</div></div>';
    }
    document.getElementById('cal-timeline').innerHTML = html;
    var timeline = document.getElementById('cal-timeline');
    if(timeline) timeline.scrollTop = 8*50;
}

function formatHour(h){
    if(h===0) return '12 AM';
    if(h<12) return h+' AM';
    if(h===12) return '12 PM';
    return (h-12)+' PM';
}

// ── Agenda View ──
function renderAgenda(){
    var html = '';
    if(cachedEvents.length===0){
        html = '<div class="empty-state empty-sm"><div class="empty-icon">&#x1F4C5;</div><h3>No upcoming events</h3><p>Create your first event to get started.</p></div>';
    } else {
        var grouped = {};
        for(var i=0;i<cachedEvents.length;i++){
            var d = cachedEvents[i].start_date;
            if(!grouped[d]) grouped[d]=[];
            grouped[d].push(cachedEvents[i]);
        }
        var dates = Object.keys(grouped).sort();
        for(var j=0;j<dates.length;j++){
            var dt = new Date(dates[j]+'T00:00:00');
            var days=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            var months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            html += '<div class="cal-agenda-date">'+days[dt.getDay()]+', '+months[dt.getMonth()]+' '+dt.getDate()+'</div>';
            var items = grouped[dates[j]];
            for(var k=0;k<items.length;k++){
                var ev = items[k];
                var impBadge = ev.importance==='high'?'<span class="cal-imp cal-imp-high">High</span>':
                               ev.importance==='low'?'<span class="cal-imp cal-imp-low">Low</span>':'';
                html += '<div class="cal-agenda-item" onclick="showEventDetail('+ev.id+')">';
                html += '<div class="cal-agenda-color" style="background:'+ev.color+'"></div>';
                html += '<div class="cal-agenda-info">';
                html += '<div class="cal-agenda-title">'+escH(ev.title)+' '+impBadge+'</div>';
                html += '<div class="cal-agenda-meta">'+(ev.all_day?'All Day':ev.start_hour+' – '+ev.end_hour);
                if(ev.location) html += ' \u00b7 \ud83d\udccd '+escH(ev.location);
                if(ev.attendee_count>0) html += ' \u00b7 \ud83d\udc65 '+ev.attendee_count;
                html += '</div></div>';
                if(ev.my_status && !ev.is_mine){
                    var st = ev.my_status;
                    var stCls = st==='accepted'?'cal-rsvp-yes':st==='declined'?'cal-rsvp-no':'cal-rsvp-pending';
                    html += '<span class="cal-rsvp '+stCls+'">'+st.charAt(0).toUpperCase()+st.slice(1)+'</span>';
                }
                html += '</div>';
            }
        }
    }
    document.getElementById('cal-agenda-list').innerHTML = html;
}

// ── Event Detail ──
window.showEventDetail = function(id){
    fetch('api/calendar.php?action=get_event&id='+id)
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.error){showToast(d.error,'error');return;}
            var ev = d.event;
            document.getElementById('detail-title').textContent = ev.title;
            var html = '<div class="cal-detail-color" style="background:'+ev.color+'"></div>';
            html += '<div class="cal-detail-row">\ud83d\udcc5 '+(ev.all_day?'All Day':ev.start_hour+' \u2013 '+ev.end_hour)+'</div>';
            html += '<div class="cal-detail-row">\ud83d\uddd3 '+new Date(ev.start_time).toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'})+'</div>';
            if(ev.location) html += '<div class="cal-detail-row">\ud83d\udccd '+escH(ev.location)+'</div>';
            var impIcon = ev.importance==='high'?'\ud83d\udd34':ev.importance==='low'?'\ud83d\udfe2':'\ud83d\udfe1';
            html += '<div class="cal-detail-row">'+impIcon+' '+ev.importance.charAt(0).toUpperCase()+ev.importance.slice(1)+' priority</div>';
            html += '<div class="cal-detail-row">\ud83d\udc64 Created by '+escH(ev.creator_name)+'</div>';
            if(ev.description) html += '<div class="cal-detail-desc">'+ev.description+'</div>';
            if(ev.attendees && ev.attendees.length>0){
                html += '<div class="cal-detail-section"><h4>Attendees ('+ev.attendees.length+')</h4>';
                for(var i=0;i<ev.attendees.length;i++){
                    var a = ev.attendees[i];
                    var sIcon = a.status==='accepted'?'\u2705':a.status==='declined'?'\u274c':a.status==='tentative'?'\ud83e\udd14':'\u2753';
                    html += '<div class="cal-att-row"><div class="avatar-xs" style="background:'+a.color+'">'+escH(a.initials)+'</div>';
                    html += '<span>'+escH(a.display_name)+'</span><span class="cal-att-status">'+sIcon+' '+a.status+'</span></div>';
                }
                html += '</div>';
            }
            if(ev.my_status && !ev.is_mine){
                html += '<div class="cal-rsvp-actions">';
                html += '<button class="btn btn-sm btn-success'+(ev.my_status==='accepted'?' btn-active':'')+'" onclick="rsvpEvent('+ev.id+',\'accepted\')">\u2705 Accept</button>';
                html += '<button class="btn btn-sm btn-warning'+(ev.my_status==='tentative'?' btn-active':'')+'" onclick="rsvpEvent('+ev.id+',\'tentative\')">\ud83e\udd14 Tentative</button>';
                html += '<button class="btn btn-sm btn-danger'+(ev.my_status==='declined'?' btn-active':'')+'" onclick="rsvpEvent('+ev.id+',\'declined\')">\u274c Decline</button>';
                html += '</div>';
            }
            if(ev.is_mine){
                html += '<div class="cal-detail-actions">';
                html += '<button class="btn btn-sm btn-secondary" onclick="editEvent('+ev.id+')">\u270f\ufe0f Edit</button>';
                html += '<button class="btn btn-sm btn-danger" onclick="deleteEventById('+ev.id+')">\ud83d\uddd1 Delete</button>';
                html += '</div>';
            }
            document.getElementById('detail-body').innerHTML = html;
            document.getElementById('cal-event-detail').style.display = 'block';
        });
};
window.closeDetail = function(){ document.getElementById('cal-event-detail').style.display='none'; };

window.rsvpEvent = function(id, status){
    fetch('api/calendar.php?action=respond',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'event_id='+id+'&status='+status})
        .then(function(r){return r.json();})
        .then(function(d){
            if(d.success){showToast('RSVP: '+status,'success');showEventDetail(id);loadEvents();}
        });
};

// ── Event Modal ──
function getViewDateStr(){ return fmt(viewDate); }

window.openEventModal = function(){
    document.getElementById('ev-edit-id').value = '';
    document.getElementById('ev-title').value = '';
    document.getElementById('ev-desc').value = '';
    document.getElementById('ev-location').value = '';
    // If in daily view, use that date; otherwise use today
    var dateStr = (currentView==='day') ? getViewDateStr() : fmt(new Date());
    document.getElementById('ev-start-date').value = dateStr;
    document.getElementById('ev-end-date').value = dateStr;
    document.getElementById('ev-start-time').value = '08:00';
    document.getElementById('ev-end-time').value = '09:00';
    document.getElementById('ev-start-time-group').style.display = '';
    document.getElementById('ev-end-time-group').style.display = '';
    document.getElementById('ev-allday').checked = false;
    document.getElementById('ev-importance').value = 'normal';
    document.getElementById('ev-reminder').value = '15';
    document.getElementById('ev-recurrence').value = '';
    document.getElementById('ev-rec-end-group').style.display = 'none';
    document.getElementById('ev-attendees').value = '';
    selectedColor = '#6366f1';
    highlightColor();
    document.getElementById('event-modal-title').textContent = '\ud83d\udcc5 New Event';
    document.getElementById('ev-save-btn').textContent = 'Create Event';
    document.getElementById('ev-delete-btn').style.display = 'none';
    document.getElementById('event-modal').style.display = 'flex';
};

window.openEventModalAt = function(dateStr, time){
    openEventModal();
    document.getElementById('ev-start-date').value = dateStr;
    document.getElementById('ev-end-date').value = dateStr;
    document.getElementById('ev-start-time').value = time;
    var h = parseInt(time.split(':')[0],10);
    document.getElementById('ev-end-time').value = pad(h+1)+':00';
};

// Dirty check: returns true if form has been modified
function isModalDirty(){
    var t = document.getElementById('ev-title').value.trim();
    var d = document.getElementById('ev-desc').value.trim();
    var l = document.getElementById('ev-location').value.trim();
    var a = document.getElementById('ev-attendees').value.trim();
    var editId = document.getElementById('ev-edit-id').value;
    // For new events, dirty if any field filled
    if(!editId) return !!(t || d || l || a);
    // For edits, always consider dirty
    return true;
}

window.closeEventModal = function(force){
    if(!force && isModalDirty()){
        customConfirm('You have unsaved changes. Discard?', function(){
            document.getElementById('event-modal').style.display='none';
        });
        return;
    }
    document.getElementById('event-modal').style.display='none';
};

window.editEvent = function(id){
    fetch('api/calendar.php?action=get_event&id='+id)
        .then(function(r){return r.json();})
        .then(function(d){
            var ev = d.event;
            document.getElementById('ev-edit-id').value = ev.id;
            document.getElementById('ev-title').value = ev.title;
            document.getElementById('ev-desc').value = ev.description||'';
            document.getElementById('ev-location').value = ev.location||'';
            document.getElementById('ev-start-date').value = ev.start_date;
            document.getElementById('ev-start-time').value = ev.start_hour;
            // Parse end date from end_time
            var endDate = ev.end_time.substring(0,10);
            var endHour = ev.end_hour;
            document.getElementById('ev-end-date').value = endDate;
            document.getElementById('ev-end-time').value = endHour;
            var isAllDay = !!ev.all_day;
            document.getElementById('ev-allday').checked = isAllDay;
            document.getElementById('ev-start-time-group').style.display = isAllDay?'none':'';
            document.getElementById('ev-end-time-group').style.display = isAllDay?'none':'';
            document.getElementById('ev-importance').value = ev.importance;
            document.getElementById('ev-reminder').value = ev.reminder_minutes;
            selectedColor = ev.color; highlightColor();
            var names = [];
            if(ev.attendees) for(var i=0;i<ev.attendees.length;i++) names.push(ev.attendees[i].username);
            document.getElementById('ev-attendees').value = names.join(', ');
            document.getElementById('event-modal-title').textContent = '\u270f\ufe0f Edit Event';
            document.getElementById('ev-save-btn').textContent = 'Save Changes';
            document.getElementById('ev-delete-btn').style.display = 'inline-flex';
            closeDetail();
            document.getElementById('event-modal').style.display = 'flex';
        });
};

window.saveEvent = function(){
    var id = document.getElementById('ev-edit-id').value;
    var title = document.getElementById('ev-title').value.trim();
    if(!title){showToast('Title is required','error');return;}
    var isAllDay = document.getElementById('ev-allday').checked;
    var startDate = document.getElementById('ev-start-date').value;
    var endDate = document.getElementById('ev-end-date').value;
    var startTime = isAllDay ? '08:00' : document.getElementById('ev-start-time').value;
    var endTime = isAllDay ? '17:00' : document.getElementById('ev-end-time').value;
    var fullStart = startDate + ' ' + startTime + ':00';
    var fullEnd = endDate + ' ' + endTime + ':00';
    // Prevent creating events in the past (only for new events)
    if(!id){
        var now = new Date();
        var evStart = new Date(startDate+'T'+startTime);
        if(evStart < now){
            showToast('Cannot create events in the past','error');
            return;
        }
    }
    if(new Date(fullEnd.replace(' ','T')) <= new Date(fullStart.replace(' ','T'))){
        showToast('End time must be after start time','error');
        return;
    }

    var body = 'title='+enc(title)
        +'&description='+enc(document.getElementById('ev-desc').value)
        +'&location='+enc(document.getElementById('ev-location').value)
        +'&start_time='+enc(fullStart)
        +'&end_time='+enc(fullEnd)
        +'&all_day='+(isAllDay?1:0)
        +'&importance='+enc(document.getElementById('ev-importance').value)
        +'&color='+enc(selectedColor)
        +'&reminder_minutes='+document.getElementById('ev-reminder').value
        +'&recurrence_rule='+enc(document.getElementById('ev-recurrence').value)
        +'&recurrence_end='+enc(document.getElementById('ev-rec-end').value||'')
        +'&attendees='+enc(document.getElementById('ev-attendees').value);
    var action = id ? 'update_event' : 'create_event';
    if(id) body += '&event_id='+id;
    var btn = document.getElementById('ev-save-btn');
    btn.disabled=true; btn.textContent='Saving...';
    fetch('api/calendar.php?action='+action,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body})
        .then(function(r){return r.json();})
        .then(function(d){
            btn.disabled=false;
            if(d.error){showToast(d.error,'error');btn.textContent=id?'Save Changes':'Create Event';return;}
            showToast(id?'Event updated!':'Event created!','success');
            closeEventModal(true);
            loadEvents();
        }).catch(function(){btn.disabled=false;btn.textContent=id?'Save Changes':'Create Event';showToast('Network error','error');});
};

window.deleteEvent = function(){
    var id = document.getElementById('ev-edit-id').value;
    if(!id) return;
    deleteEventById(parseInt(id,10));
};
window.deleteEventById = function(id){
    customConfirm('Delete this event and all its recurrences?',function(){
        fetch('api/calendar.php?action=delete_event',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'event_id='+id})
            .then(function(r){return r.json();})
            .then(function(d){
                if(d.success){showToast('Event deleted','success');closeEventModal();closeDetail();loadEvents();}
            });
    });
};

window.toggleAllDay = function(){
    var c = document.getElementById('ev-allday').checked;
    document.getElementById('ev-start-time-group').style.display = c?'none':'';
    document.getElementById('ev-end-time-group').style.display = c?'none':'';
    if(c){
        // Set to today with 08:00-17:00
        var today = fmt(new Date());
        document.getElementById('ev-start-date').value = today;
        document.getElementById('ev-end-date').value = today;
        document.getElementById('ev-start-time').value = '08:00';
        document.getElementById('ev-end-time').value = '17:00';
    }
};
window.toggleRecEnd = function(){
    document.getElementById('ev-rec-end-group').style.display = document.getElementById('ev-recurrence').value?'block':'none';
};

// ── Color Picker ──
function setupColorPicker(){
    var swatches = document.querySelectorAll('.cal-color-swatch');
    for(var i=0;i<swatches.length;i++){
        swatches[i].addEventListener('click',function(){
            selectedColor = this.getAttribute('data-color');
            highlightColor();
        });
    }
}
function highlightColor(){
    var swatches = document.querySelectorAll('.cal-color-swatch');
    for(var i=0;i<swatches.length;i++){
        swatches[i].classList.toggle('active', swatches[i].getAttribute('data-color')===selectedColor);
    }
}

// ── Attendee autocomplete ──
function setupAttendeeAutocomplete(){
    var input = document.getElementById('ev-attendees');
    var dd = document.getElementById('ev-attendees-dropdown');
    if(!input||!dd) return;
    var timer = null;
    input.addEventListener('input',function(){
        clearTimeout(timer);
        timer = setTimeout(function(){
            var parts = input.value.split(',');
            var cur = parts[parts.length-1].trim();
            if(cur.length<1){dd.style.display='none';return;}
            fetch('api/users.php?action=search&q='+encodeURIComponent(cur))
                .then(function(r){return r.json();})
                .then(function(data){
                    if(!data.users||data.users.length===0){dd.style.display='none';return;}
                    dd.innerHTML='';
                    data.users.forEach(function(u){
                        var div=document.createElement('div');div.className='autocomplete-item';
                        div.innerHTML='<span class="ac-name">'+escH(u.display_name)+'</span><span class="ac-username">@'+escH(u.username)+'</span>';
                        div.addEventListener('click',function(){parts[parts.length-1]=u.username;input.value=parts.join(', ')+', ';dd.style.display='none';input.focus();});
                        dd.appendChild(div);
                    });
                    dd.style.display='block';
                });
        },250);
    });
    document.addEventListener('click',function(e){if(e.target!==input)dd.style.display='none';});
}

// ── Address Book ──
window.openCalAB = function(){ document.getElementById('cal-ab-modal').style.display='flex'; };
window.closeCalAB = function(){ document.getElementById('cal-ab-modal').style.display='none'; };
window.filterCalAB = function(q){
    q=q.toLowerCase();
    var rows=document.querySelectorAll('.cal-ab-row');
    for(var i=0;i<rows.length;i++) rows[i].style.display=(!q||rows[i].getAttribute('data-search').indexOf(q)!==-1)?'':'none';
};
window.calAbToggleAll = function(checked){
    var rows=document.querySelectorAll('.cal-ab-row');
    for(var i=0;i<rows.length;i++){if(rows[i].style.display!=='none')rows[i].querySelector('.cal-ab-check').checked=checked;}
};
window.addCalABChecked = function(){
    var checks=document.querySelectorAll('.cal-ab-check:checked');
    if(checks.length===0){customAlert('Select at least one user');return;}
    var field=document.getElementById('ev-attendees');
    var cur=field.value.trim();
    var existing=cur?cur.split(',').map(function(s){return s.trim();}):[];
    for(var i=0;i<checks.length;i++){
        if(existing.indexOf(checks[i].value)===-1) existing.push(checks[i].value);
        checks[i].checked=false;
    }
    field.value=existing.join(', ');
    document.getElementById('cal-ab-select-all').checked=false;
    closeCalAB();
};

// ── Sidebar mini calendar (updated from calendar.js context) ──
function updateSidebarMini(){
    // Only update if sidebar-mini-cal has event markers on the calendar page
    // The footer script handles the initial render on all pages
}

// ── Utilities ──
function escH(s){var d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML;}
function enc(s){return encodeURIComponent(s);}
function truncStr(s,n){return s.length>n?s.substring(0,n)+'\u2026':s;}

})();
