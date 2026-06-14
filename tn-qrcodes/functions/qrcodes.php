<?php
/**
 * QR code generation, metadata, preview, and download handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/
function tn_qr_get_public_post_types() {
    return get_post_types([
        'public' => true,
    ]);
}

function tn_qr_get_saved_tracking_fields($post_id) {
    return [
        'utm_source'   => trim((string) get_post_meta($post_id, TN_QR_META_SOURCE, true)),
        'utm_medium'   => trim((string) get_post_meta($post_id, TN_QR_META_MEDIUM, true)),
        'utm_campaign' => trim((string) get_post_meta($post_id, TN_QR_META_CAMPAIGN, true)),
    ];
}

function tn_qr_clean_tracking_fields($fields) {
    return [
        'utm_source'   => isset($fields['utm_source']) ? sanitize_text_field(wp_unslash($fields['utm_source'])) : '',
        'utm_medium'   => isset($fields['utm_medium']) ? sanitize_text_field(wp_unslash($fields['utm_medium'])) : '',
        'utm_campaign' => isset($fields['utm_campaign']) ? sanitize_text_field(wp_unslash($fields['utm_campaign'])) : '',
    ];
}

function tn_qr_build_tracked_url($post_id, $fields = []) {
    $url = get_permalink($post_id);
    if (!$url) {
        return '';
    }

    $fields = tn_qr_clean_tracking_fields($fields);

    $args = [];
    foreach ($fields as $key => $value) {
        if ($value !== '') {
            $args[$key] = $value;
        }
    }

    if (!empty($args)) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

function tn_qr_get_qr_dir() {
    $upload_dir = wp_upload_dir();
    $dir = trailingslashit($upload_dir['basedir']) . 'qr-codes/';
    $url = trailingslashit($upload_dir['baseurl']) . 'qr-codes/';

    if (!file_exists($dir)) {
        wp_mkdir_p($dir);
    }

    return [
        'dir' => $dir,
        'url' => $url,
    ];
}

function tn_qr_get_qr_file_data($url) {
    $paths = tn_qr_get_qr_dir();
    $file_name = md5($url) . '.png';

    return [
        'file_name' => $file_name,
        'file_path' => $paths['dir'] . $file_name,
        'file_url'  => $paths['url'] . $file_name,
    ];
}

function tn_qr_generate_qr_code($url, $force_refresh = false) {
    if (empty($url)) {
        return '';
    }

    $file = tn_qr_get_qr_file_data($url);

    if ($force_refresh && file_exists($file['file_path'])) {
        @unlink($file['file_path']);
    }

    if (!file_exists($file['file_path'])) {
        QRcode::png($url, $file['file_path'], QR_ECLEVEL_L, 10);
    }

    return $file['file_url'];
}

/*
|--------------------------------------------------------------------------
| Save UTM Fields
|--------------------------------------------------------------------------
*/
add_action('save_post', 'tn_qr_save_meta_fields');
function tn_qr_save_meta_fields($post_id) {
    $nonce = isset($_POST['tn_qr_meta_nonce']) ? sanitize_text_field(wp_unslash($_POST['tn_qr_meta_nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'tn_qr_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $source   = isset($_POST['tn_qr_utm_source']) ? sanitize_text_field(wp_unslash($_POST['tn_qr_utm_source'])) : '';
    $medium   = isset($_POST['tn_qr_utm_medium']) ? sanitize_text_field(wp_unslash($_POST['tn_qr_utm_medium'])) : '';
    $campaign = isset($_POST['tn_qr_utm_campaign']) ? sanitize_text_field(wp_unslash($_POST['tn_qr_utm_campaign'])) : '';

    update_post_meta($post_id, TN_QR_META_SOURCE, $source);
    update_post_meta($post_id, TN_QR_META_MEDIUM, $medium);
    update_post_meta($post_id, TN_QR_META_CAMPAIGN, $campaign);
}

/*
|--------------------------------------------------------------------------
| QR Ajax Preview / Refresh
|--------------------------------------------------------------------------
*/
add_action('wp_ajax_tn_qr_preview', 'tn_qr_preview_ajax');
function tn_qr_preview_ajax() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'tn_qr_preview_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce'], 403);
    }

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => 'Access denied'], 403);
    }

    $fields = tn_qr_clean_tracking_fields([
        'utm_source'   => $_POST['utm_source'] ?? '',
        'utm_medium'   => $_POST['utm_medium'] ?? '',
        'utm_campaign' => $_POST['utm_campaign'] ?? '',
    ]);

    $force_refresh = !empty($_POST['force_refresh']);
    $tracked_url   = tn_qr_build_tracked_url($post_id, $fields);
    $qr_url        = tn_qr_generate_qr_code($tracked_url, $force_refresh);

    $download_url = add_query_arg([
        'action'       => 'download_qr_code',
        'post_id'      => $post_id,
        'utm_source'   => $fields['utm_source'],
        'utm_medium'   => $fields['utm_medium'],
        'utm_campaign' => $fields['utm_campaign'],
        '_wpnonce'     => wp_create_nonce('tn_qr_download_' . $post_id),
    ], admin_url('admin-ajax.php'));

    wp_send_json_success([
        'qr_url'       => $qr_url . '?v=' . time(),
        'tracked_url'  => $tracked_url,
        'download_url' => $download_url,
    ]);
}

/*
|--------------------------------------------------------------------------
| Download QR
|--------------------------------------------------------------------------
*/
add_action('wp_ajax_download_qr_code', 'tn_qr_handle_download');
add_action('wp_ajax_nopriv_download_qr_code', 'tn_qr_handle_download');

function tn_qr_handle_download() {
    if (!isset($_GET['post_id']) || !is_numeric(wp_unslash($_GET['post_id']))) {
        wp_die('Invalid QR Code Request');
    }

    $post_id = absint($_GET['post_id']);

    $is_admin_user = is_user_logged_in() && current_user_can('edit_post', $post_id);

    if ($is_admin_user) {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (!wp_verify_nonce($nonce, 'tn_qr_download_' . $post_id)) {
            wp_die('Invalid download request');
        }

        $fields = tn_qr_clean_tracking_fields([
            'utm_source'   => $_GET['utm_source'] ?? '',
            'utm_medium'   => $_GET['utm_medium'] ?? '',
            'utm_campaign' => $_GET['utm_campaign'] ?? '',
        ]);
    } else {
        $fields = tn_qr_get_saved_tracking_fields($post_id);
    }

    $url         = tn_qr_build_tracked_url($post_id, $fields);
    $qr_code_url = tn_qr_generate_qr_code($url);

    if (!$qr_code_url) {
        wp_die('QR could not be generated');
    }

    $upload_dir = wp_upload_dir();
    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $qr_code_url);

    if (!file_exists($file_path)) {
        wp_die('QR file not found');
    }

    $post_type = sanitize_file_name((string) get_post_type($post_id));
    $slug = sanitize_file_name((string) get_post_field('post_name', $post_id));
    $file_name = sanitize_file_name("qr-{$post_type}-{$slug}.png");

    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $file_name . '"');
    header('Content-Length: ' . filesize($file_path));
    readfile($file_path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}

/*
|--------------------------------------------------------------------------
| Meta Box
|--------------------------------------------------------------------------
*/
add_action('add_meta_boxes', 'tn_qr_add_meta_boxes');
function tn_qr_add_meta_boxes() {
    $post_types = tn_qr_get_public_post_types();

    foreach ($post_types as $post_type) {
        add_meta_box(
            'tn_qr_code_meta_box',
            'QR Code',
            'tn_qr_display_meta_box',
            $post_type,
            'side',
            'default'
        );
    }
}

function tn_qr_display_meta_box($post) {
    wp_nonce_field('tn_qr_save_meta', 'tn_qr_meta_nonce');

    $source   = get_post_meta($post->ID, TN_QR_META_SOURCE, true);
    $medium   = get_post_meta($post->ID, TN_QR_META_MEDIUM, true);
    $campaign = get_post_meta($post->ID, TN_QR_META_CAMPAIGN, true);

    $tracked_url = tn_qr_build_tracked_url($post->ID, [
        'utm_source'   => $source,
        'utm_medium'   => $medium,
        'utm_campaign' => $campaign,
    ]);

    $qr_code = tn_qr_generate_qr_code($tracked_url);

    $download_link = add_query_arg([
        'action'       => 'download_qr_code',
        'post_id'      => $post->ID,
        'utm_source'   => $source,
        'utm_medium'   => $medium,
        'utm_campaign' => $campaign,
        '_wpnonce'     => wp_create_nonce('tn_qr_download_' . $post->ID),
    ], admin_url('admin-ajax.php'));

    echo '<div class="tn-qr-box">';
    echo '<div class="tn-qr-fields">';
    echo '<p><label for="tn_qr_utm_source">utm_source</label><input type="text" id="tn_qr_utm_source" name="tn_qr_utm_source" value="' . esc_attr($source) . '"></p>';
    echo '<p><label for="tn_qr_utm_medium">utm_medium</label><input type="text" id="tn_qr_utm_medium" name="tn_qr_utm_medium" value="' . esc_attr($medium) . '"></p>';
    echo '<p><label for="tn_qr_utm_campaign">utm_campaign</label><input type="text" id="tn_qr_utm_campaign" name="tn_qr_utm_campaign" value="' . esc_attr($campaign) . '"></p>';
    echo '</div>';

    if (in_array($post->post_status, ['publish', 'future', 'pending'], true)) {
        echo '<div class="tn-qr-image-wrap">';
        echo '<img id="tn-qr-image" src="' . esc_url($qr_code) . '?v=' . time() . '" alt="QR Code">';
        echo '</div>';
        echo '<div class="tn-qr-actions">';
        echo '<button type="button" class="button button-secondary" id="tn-qr-refresh">Refresh</button>';
        echo '<a class="button button-primary" id="tn-qr-download" href="' . esc_url($download_link) . '">Download</a>';
        echo '</div>';
        echo '<input type="hidden" id="tn-qr-post-id" value="' . esc_attr($post->ID) . '">';
        echo '<input type="hidden" id="tn-qr-ajax-nonce" value="' . esc_attr(wp_create_nonce('tn_qr_preview_nonce')) . '">';
    }

    echo '</div>';
}
