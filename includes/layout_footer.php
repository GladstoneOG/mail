
            </div><!-- /.content-area -->
        </main>
    </div><!-- /.app-container -->

    <div class="toast-container" id="toast-container"></div>

<!-- Move-to Modal -->
<div class="modal-overlay" id="move-to-modal" style="display:none;z-index:9800" data-current-page="<?php echo e($_activePage); ?>" data-current-folder="<?php echo $_activeFolderId; ?>" onclick="if(event.target===this)closeMoveToModal()">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h3>&#x1F4C2; Move to...</h3>
            <button class="modal-close" onclick="closeMoveToModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:12px 20px">
            <div class="moveto-options" id="moveto-options">
                <button class="moveto-option" id="moveto-inbox-opt" data-target="inbox" onclick="selectMoveTarget(this, '')">
                    <span class="moveto-icon">&#x1F4E5;</span>
                    <span>Inbox</span>
                </button>
                <div class="moveto-divider"></div>
                <div class="moveto-folders-label">Folders</div>
                <div id="moveto-folder-list"></div>
                <div class="moveto-divider"></div>
                <button class="moveto-option moveto-trash" id="moveto-trash-opt" data-target="trash" onclick="selectMoveTarget(this, 'trash')">
                    <span class="moveto-icon">&#x1F6AE;</span>
                    <span>Trash</span>
                </button>
            </div>
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-ghost btn-sm" onclick="closeMoveToModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="moveto-confirm-btn" onclick="confirmMoveTo()" disabled>Move</button>
        </div>
    </div>
</div>

<!-- Folder Input Prompt Modal (replaces browser prompt) -->
<div class="modal-overlay" id="folder-input-modal" style="display:none;z-index:9900" onclick="if(event.target===this)closeFolderInputModal()">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <h3 id="folder-input-title">&#x1F4C1; New Folder</h3>
            <button class="modal-close" onclick="closeFolderInputModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:16px 20px">
            <div class="form-group">
                <label id="folder-input-label" style="display:block;font-size:13px;color:var(--text2);margin-bottom:6px">Folder name</label>
                <input type="text" id="folder-input-field" placeholder="Enter folder name..." maxlength="100" style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:14px">
            </div>
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-ghost btn-sm" onclick="closeFolderInputModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" id="folder-input-ok" onclick="submitFolderInput()">Create</button>
        </div>
    </div>
</div>

<!-- Folder Delete Confirmation Modal (type-to-confirm) -->
<div class="modal-overlay" id="folder-delete-modal" style="display:none;z-index:9900">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <h3>&#x1F5D1; Delete Folder</h3>
            <button class="modal-close" onclick="closeFolderDeleteModal()">&times;</button>
        </div>
        <div class="modal-body" style="padding:16px 20px">
            <div style="padding:10px 14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:var(--radius-sm);margin-bottom:14px;font-size:13px;color:var(--danger)">
                <strong>⚠ Warning:</strong> This will permanently delete the folder. Messages inside will be moved back to Inbox.
            </div>
            <p style="color:var(--text2);font-size:13px;margin-bottom:10px">To confirm, type the folder name <strong id="folder-delete-name-display" style="color:var(--text)"></strong> below:</p>
            <input type="text" id="folder-delete-confirm-input" placeholder="Type folder name to confirm..." style="width:100%;padding:10px 14px;background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);color:var(--text);font-size:14px" oninput="checkFolderDeleteConfirm()">
        </div>
        <div style="padding:12px 20px 16px;border-top:1px solid var(--border);display:flex;gap:8px;justify-content:flex-end">
            <button class="btn btn-ghost btn-sm" onclick="closeFolderDeleteModal()">Cancel</button>
            <button class="btn btn-danger btn-sm" id="folder-delete-confirm-btn" onclick="executeDeleteFolder()" disabled>Delete Folder</button>
        </div>
    </div>
</div>

<!-- Folder Context Menu -->
<div class="folder-ctx-menu" id="folder-ctx-menu" style="display:none">
    <button onclick="renameFolderAction()">&#x270F;&#xFE0F; Rename</button>
    <button onclick="deleteFolderAction()" class="ctx-danger">&#x1F5D1; Delete</button>
</div>

<?php if (!empty($_calTodayEvents)): ?>
    <div class="modal-overlay" id="cal-reminder-modal" style="display:flex;z-index:9500">
        <div class="modal cal-reminder-modal" style="max-width:460px;text-align:center">
            <div class="modal-body" style="padding:28px">
                <div style="font-size:48px;margin-bottom:8px">📅</div>
                <h3 style="margin-bottom:4px">Today's Agenda</h3>
                <p style="color:var(--text2);font-size:13px;margin-bottom:20px"><?php echo date('l, F j, Y'); ?></p>
                <div class="cal-reminder-list">
                <?php foreach ($_calTodayEvents as $te): ?>
                    <div class="cal-reminder-item">
                        <div class="cal-reminder-color" style="background:<?php echo e($te['color']); ?>"></div>
                        <div class="cal-reminder-info">
                            <div class="cal-reminder-title"><?php echo e($te['title']); ?></div>
                            <div class="cal-reminder-time">
                                <?php if ($te['all_day']): ?>All Day
                                <?php else: echo date('g:i A', strtotime($te['start_time'])) . ' – ' . date('g:i A', strtotime($te['end_time'])); endif; ?>
                                <?php if ($te['location']): ?> · 📍 <?php echo e($te['location']); endif; ?>
                            </div>
                        </div>
                        <?php $impIcon = $te['importance']==='high'?'🔴':($te['importance']==='low'?'🟢':'🟡'); ?>
                        <span style="font-size:14px"><?php echo $impIcon; ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;justify-content:center">
                    <a href="index.php?page=calendar" class="btn btn-primary">Open Calendar</a>
                    <button class="btn btn-secondary" onclick="document.getElementById('cal-reminder-modal').style.display='none'">Dismiss</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

    <script>
        var APP_CONFIG = {
            csrfToken: '<?php echo get_csrf_token(); ?>',
            userId: <?php echo auth_user_id(); ?>,
            pollInterval: <?php echo NOTIFICATION_POLL_INTERVAL; ?>,
            calWidgetRefresh: <?php echo CALENDAR_WIDGET_REFRESH_INTERVAL; ?>,
            baseUrl: 'index.php',
            currentPage: '<?php echo isset($page) ? $page : ""; ?>'
        };
    </script>
    <script src="assets/js/app.js"></script>
    <script>
    // Sidebar mini calendar with navigation + auto-refresh
    (function(){
        var container = document.getElementById('sidebar-mini-cal');
        if(!container) return;
        
        var initDate = new Date();
        var urlParams = new URLSearchParams(window.location.search);
        var urlDate = urlParams.get('date');
        if (urlDate && /^\d{4}-\d{2}-\d{2}$/.test(urlDate)) {
            var parts = urlDate.split('-');
            initDate = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        }
        var miniMonth = initDate.getMonth();
        var miniYear = initDate.getFullYear();

        function pad(n){return n<10?'0'+n:''+n;}

        function renderMiniCal(){
            var now = new Date();
            var first=new Date(miniYear,miniMonth,1), last=new Date(miniYear,miniMonth+1,0);
            var months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var html = '<div class="mini-cal-header">';
            html += '<span class="mini-cal-arrow" id="mini-cal-prev">\u276e</span>';
            html += '<span class="mini-cal-title">'+months[miniMonth]+' '+miniYear+'</span>';
            html += '<span class="mini-cal-arrow" id="mini-cal-next">\u276f</span>';
            html += '</div>';
            html += '<div class="mini-cal-grid">';
            var dows=['S','M','T','W','T','F','S'];
            for(var i=0;i<7;i++) html += '<div class="mini-cal-dow">'+dows[i]+'</div>';
            for(var i=0;i<first.getDay();i++) html += '<div class="mini-cal-day mini-cal-empty"></div>';
            for(var d=1;d<=last.getDate();d++){
                var isToday = (d===now.getDate()&&miniMonth===now.getMonth()&&miniYear===now.getFullYear());
                var dateStr = miniYear+'-'+pad(miniMonth+1)+'-'+pad(d);
                html += '<div class="mini-cal-day'+(isToday?' mini-cal-today':'')+'" data-date="'+dateStr+'">'+d+'</div>';
            }
            // Pad to always have 42 cells (6 rows)
            var totalCells = first.getDay() + last.getDate();
            for(var i=totalCells;i<42;i++) html += '<div class="mini-cal-day mini-cal-empty"></div>';
            html += '</div>';
            container.innerHTML = html;

            // Attach nav events
            document.getElementById('mini-cal-prev').addEventListener('click',function(e){
                e.stopPropagation();
                miniMonth--;
                if(miniMonth<0){miniMonth=11;miniYear--;}
                renderMiniCal();
            });
            document.getElementById('mini-cal-next').addEventListener('click',function(e){
                e.stopPropagation();
                miniMonth++;
                if(miniMonth>11){miniMonth=0;miniYear++;}
                renderMiniCal();
            });

            // Click on a day → go to calendar daily view
            var dayCells = container.querySelectorAll('.mini-cal-day:not(.mini-cal-empty)');
            for(var j=0;j<dayCells.length;j++){
                dayCells[j].addEventListener('click',function(){
                    var ds = this.getAttribute('data-date');
                    window.location = 'index.php?page=calendar&view=day&date='+ds;
                });
            }

            // Load event markers for the current view
            refreshMiniCalEvents();
        }

        // Refresh only the event markers (dots) without re-rendering the entire calendar
        function refreshMiniCalEvents(){
            var last = new Date(miniYear,miniMonth+1,0);
            var s = miniYear+'-'+pad(miniMonth+1)+'-01';
            var e = miniYear+'-'+pad(miniMonth+1)+'-'+pad(last.getDate());
            fetch('api/calendar.php?action=get_events&start='+s+'&end='+e)
                .then(function(r){return r.json();})
                .then(function(data){
                    if(!data.events) return;
                    var eventDates = {};
                    for(var i=0;i<data.events.length;i++) eventDates[data.events[i].start_date] = true;
                    var cells = container.querySelectorAll('.mini-cal-day[data-date]');
                    for(var k=0;k<cells.length;k++){
                        var hasEvent = !!eventDates[cells[k].getAttribute('data-date')];
                        cells[k].classList.toggle('mini-cal-has-event', hasEvent);
                    }
                }).catch(function(){});
        }

        renderMiniCal();

        // Auto-refresh event markers periodically (preserves user's month/year position)
        var refreshInterval = (typeof APP_CONFIG !== 'undefined' && APP_CONFIG.calWidgetRefresh) ? APP_CONFIG.calWidgetRefresh : 30000;
        setInterval(refreshMiniCalEvents, refreshInterval);
    })();
    </script>
</body>
</html>
