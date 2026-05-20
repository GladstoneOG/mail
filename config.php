<?php
date_default_timezone_set('Asia/Jakarta');
define('APP_NAME', 'RSPIK Mail');
define('APP_VERSION', '1.0.0');
define('MAX_UPLOAD_SIZE', 0); // 0 = no limit (PHP/IIS config controls actual max)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ITEMS_PER_PAGE', 25);
define('NOTIFICATION_POLL_INTERVAL', 10000); // 15 seconds in ms
define('CALENDAR_WIDGET_REFRESH_INTERVAL', 10000); // 30 seconds in ms – sidebar mini calendar auto-refresh
define('ALLOW_SELF_REGISTRATION', false); // true = anyone can create an account; false = admin-only registration
define('AIRGAPPED_MODE', false); // true = run on an air-gapped system (uses local assets); false = use online CDNs
