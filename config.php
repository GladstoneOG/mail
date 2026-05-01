<?php
date_default_timezone_set('Asia/Jakarta');
define('APP_NAME', 'RSPIK Mail');
define('APP_VERSION', '1.0.0');
define('MAX_UPLOAD_SIZE', 0); // 0 = no limit (PHP/IIS config controls actual max)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('ITEMS_PER_PAGE', 25);
define('NOTIFICATION_POLL_INTERVAL', 15000); // 15 seconds in ms
