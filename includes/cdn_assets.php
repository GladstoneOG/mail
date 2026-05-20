<?php
/**
 * CDN Assets Configuration for Air-Gapped Network Deployment.
 * 
 * This file gathers all external CDN URLs in one central place.
 * 
 * TO DEPLOY ON AN AIR-GAPPED SYSTEM:
 * 1. Set AIRGAPPED_MODE to true.
 * 2. Download the external files listed below.
 * 3. Place them in your local web root directory (we recommend assets/vendor/).
 * 4. Update the LOCAL_* constants below to point to your offline files.
 */

// Toggle this to true when deploying on an air-gapped system.
if (!defined('AIRGAPPED_MODE')) {
    define('AIRGAPPED_MODE', true);
}

// -----------------------------------------------------------------------------
// 1. Google Font - Inter
// -----------------------------------------------------------------------------
// External CDN URL:
define('CDN_FONT_INTER', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
// Local Offline Path (if you downloaded the fonts and created a local stylesheet):
define('LOCAL_FONT_INTER', 'assets/css/fonts.css');

// -----------------------------------------------------------------------------
// 2. Quill Rich Text Editor - CSS
// -----------------------------------------------------------------------------
// External CDN URL:
define('CDN_QUILL_CSS', 'https://cdn.quilljs.com/1.3.7/quill.snow.css');
// Local Offline Path:
define('LOCAL_QUILL_CSS', 'assets/vendor/quill/quill.snow.css');

// -----------------------------------------------------------------------------
// 3. Quill Rich Text Editor - JavaScript
// -----------------------------------------------------------------------------
// External CDN URL:
define('CDN_QUILL_JS', 'https://cdn.quilljs.com/1.3.7/quill.min.js');
// Local Offline Path:
define('LOCAL_QUILL_JS', 'assets/vendor/quill/quill.min.js');


// =============================================================================
// Helper Functions to Render Asset Tags (Self-contained)
// =============================================================================

/**
 * Renders the stylesheet link tag for the Inter font.
 */
function render_font_assets() {
    if (AIRGAPPED_MODE) {
        $local = LOCAL_FONT_INTER;
        if (!empty($local)) {
            echo '    <link rel="stylesheet" href="' . htmlspecialchars($local, ENT_QUOTES, 'UTF-8') . '">' . "\n";
        }
        // If LOCAL_FONT_INTER is empty, we fall back to system fonts (defined in style.css).
    } else {
        echo '    <link rel="stylesheet" href="' . htmlspecialchars(CDN_FONT_INTER, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}

/**
 * Renders the stylesheet link tag for Quill.
 */
function render_quill_css() {
    $url = AIRGAPPED_MODE ? LOCAL_QUILL_CSS : CDN_QUILL_CSS;
    echo '<link rel="stylesheet" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

/**
 * Renders the script tag for Quill JS.
 */
function render_quill_js() {
    $url = AIRGAPPED_MODE ? LOCAL_QUILL_JS : CDN_QUILL_JS;
    echo '<script src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
