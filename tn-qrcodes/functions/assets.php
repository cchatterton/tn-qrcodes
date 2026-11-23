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

    if (!$screen || !in_array($screen->base, ['post', 'tools_page_tn-qr-links'], true)) {
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
        TN_QR_PLUGIN_URL . ('post' === $screen->base ? 'scripts/tn-qrcodes.js' : 'scripts/tn-qr-links.js'),
        array(),
        TN_QR_VERSION,
        true
    );
}
