<?php
/**
 * Utility functions for LAN Mail.
 * PHP 5.6 compatible.
 */

require_once __DIR__ . '/cdn_assets.php';

function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function time_ago($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    if ($ts === false) return $datetime;
    $diff = time() - $ts;
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return intval($diff / 60) . 'm ago';
    if ($diff < 86400) return intval($diff / 3600) . 'h ago';
    if ($diff < 604800) return intval($diff / 86400) . 'd ago';
    return date('M j, Y', $ts);
}

function format_date($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    return $ts ? date('M j, Y g:i A', $ts) : $datetime;
}

function format_size($bytes) {
    $bytes = intval($bytes);
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

function get_initials($name) {
    $parts = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $i => $p) {
        if ($i >= 2) break;
        if (strlen($p) > 0) $initials .= strtoupper($p[0]);
    }
    return $initials ? $initials : '?';
}

function get_avatar_color($name) {
    $colors = array('#6366f1','#8b5cf6','#ec4899','#f43f5e','#f97316','#eab308','#22c55e','#14b8a6','#06b6d4','#3b82f6');
    $hash = crc32($name);
    return $colors[abs($hash) % count($colors)];
}

function get_unread_count($conn, $userId) {
    $nowStr = date('Y-m-d H:i:s');
    $sql = "SELECT COUNT(DISTINCT m.id) FROM mail_recipients mr
            JOIN mail_messages m ON mr.message_id = m.id
            WHERE mr.recipient_id = ? AND mr.is_read = 0 AND mr.is_deleted = 0 AND m.is_draft = 0 AND (mr.folder_id IS NULL) AND (m.sent_at IS NULL OR m.sent_at <= ?)";
    return intval(db_fetch_scalar($conn, $sql, array($userId, $nowStr)));
}

function get_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function truncate_text($text, $length = 80) {
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function sanitize_filename($name) {
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name);
    return $name ? $name : 'file';
}

function sanitize_html($html) {
    if (!$html) return '';
    $allowed = '<b><i><u><strong><em><br><p><div><span><font><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre><code><a><img><table><tr><td><th><thead><tbody><hr>';
    $clean = strip_tags($html, $allowed);
    // Remove event handlers
    $clean = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $clean);
    $clean = preg_replace('/\s+on\w+\s*=\s*\S+/i', '', $clean);
    return $clean;
}
