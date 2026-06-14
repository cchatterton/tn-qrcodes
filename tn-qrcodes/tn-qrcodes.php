<?php
/**
 * Plugin Name: TN QR Codes
 * Plugin URI: https://github.com/cchatterton/tn-qrcodes
 * Description: Adds QR codes to public post types and allows tracked QR code downloads.
 * Version: 1.5
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Techn
 * Author URI: https://techn.com.au
 * Text Domain: tn-qrcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TN_QR_VERSION', '1.5');
define('TN_QR_PLUGIN_FILE', __FILE__);
define('TN_QR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TN_QR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TN_QR_META_SOURCE', '_tn_qr_utm_source');
define('TN_QR_META_MEDIUM', '_tn_qr_utm_medium');
define('TN_QR_META_CAMPAIGN', '_tn_qr_utm_campaign');

require_once TN_QR_PLUGIN_DIR . 'phpqrcode/qrlib.php';
require_once TN_QR_PLUGIN_DIR . 'functions/assets.php';
require_once TN_QR_PLUGIN_DIR . 'functions/qrcodes.php';
require_once TN_QR_PLUGIN_DIR . 'functions/github-updater.php';
