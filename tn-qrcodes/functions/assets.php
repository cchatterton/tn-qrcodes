<?php
/**
 * Admin asset loading for TN QR Codes.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_enqueue_scripts', 'tn_qr_enqueue_admin_assets');

function tn_qr_enqueue_admin_assets($hook_suffix) {
    $screen = get_current_screen();

    if (!$screen || 'post' !== $screen->base) {
        return;
    }

    wp_enqueue_style(
        'tn-qrcodes-admin',
        TN_QR_PLUGIN_URL . 'styles/tn-qrcodes.css',
        array(),
        TN_QR_VERSION
    );

    wp_enqueue_script(
        'tn-qrcodes-admin',
        TN_QR_PLUGIN_URL . 'scripts/tn-qrcodes.js',
        array(),
        TN_QR_VERSION,
        true
    );
}
