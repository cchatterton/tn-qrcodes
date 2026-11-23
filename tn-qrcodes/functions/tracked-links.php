<?php
/** Persistent, counted QR links for arbitrary destinations. */

if (!defined('ABSPATH')) {
    exit;
}

function tn_qr_links_table() {
    global $wpdb;
    return $wpdb->prefix . 'tn_qr_links';
}

add_action('plugins_loaded', 'tn_qr_install_links_table');
function tn_qr_install_links_table() {
    if (get_option('tn_qr_links_db_version') === '1') {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table = tn_qr_links_table();
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        destination text NOT NULL,
        visits bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id)
    ) $charset;");
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        update_option('tn_qr_links_db_version', '1', false);
    }
}

function tn_qr_link_url($id) {
    return add_query_arg('tn_qr_link', absint($id), home_url('/'));
}

/** Merge the supplied parameters with an existing query string and fragment. */
function tn_qr_link_destination($url, $input) {
    if (!is_string($url)) {
        return new WP_Error('invalid_url', 'Enter a complete http or https URL.');
    }
    $url = trim((string) $url);
    if (strlen($url) > 2000) {
        return new WP_Error('url_too_long', 'Use a destination URL shorter than 2,000 characters.');
    }
    $parts = wp_parse_url($url);
    if (!$parts || !isset($parts['scheme'], $parts['host']) ||
        !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
        return new WP_Error('invalid_url', 'Enter a complete http or https URL.');
    }

    $query = [];
    if (!empty($parts['query'])) {
        wp_parse_str($parts['query'], $query);
    }

    $keys = isset($input['extra_key']) && is_array($input['extra_key']) ? $input['extra_key'] : [];
    $values = isset($input['extra_value']) && is_array($input['extra_value']) ? $input['extra_value'] : [];
    if (count($keys) > 20) {
        return new WP_Error('too_many_fields', 'Use no more than 20 additional query parameters.');
    }
    foreach ($keys as $index => $raw_key) {
        if (!is_string($raw_key) || (isset($values[$index]) && !is_string($values[$index]))) {
            return new WP_Error('invalid_key', 'Enter text values for additional query parameters.');
        }
        $key = trim(sanitize_text_field(wp_unslash($raw_key)));
        if ($key === '') {
            continue;
        }
        if (!preg_match('/^[A-Za-z0-9_.~-]+$/', $key)) {
            return new WP_Error('invalid_key', 'Query parameter names may contain letters, numbers, underscores, dots, hyphens and tildes.');
        }
        $query[$key] = isset($values[$index]) ? sanitize_text_field(wp_unslash($values[$index])) : '';
    }

    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'] as $key) {
        if (isset($input[$key]) && !is_string($input[$key])) {
            return new WP_Error('invalid_utm', 'Enter text values for UTM parameters.');
        }
        $value = isset($input[$key]) ? trim(sanitize_text_field(wp_unslash($input[$key]))) : '';
        if ($value !== '') {
            $query[$key] = $value;
        }
    }

    $destination = remove_query_arg(array_keys($query), $url);
    if ($query) {
        $destination = add_query_arg($query, $destination);
    }
    $destination = esc_url_raw($destination, ['http', 'https']);
    if (strlen($destination) > 2000) {
        return new WP_Error('url_too_long', 'The destination with query parameters must be shorter than 2,000 characters.');
    }
    return $destination;
}

add_action('template_redirect', 'tn_qr_visit_link', 0);
function tn_qr_visit_link() {
    if (!isset($_GET['tn_qr_link'])) {
        return;
    }
    $id = absint(wp_unslash($_GET['tn_qr_link']));
    if (!$id) {
        status_header(404);
        exit;
    }
    global $wpdb;
    $table = tn_qr_links_table();
    $destination = $wpdb->get_var($wpdb->prepare("SELECT destination FROM $table WHERE id = %d", $id));
    if (!$destination) {
        status_header(404);
        exit;
    }

    // Increment in SQL so simultaneous scans cannot overwrite each other's counts.
    $wpdb->query($wpdb->prepare("UPDATE $table SET visits = visits + 1 WHERE id = %d", $id));
    nocache_headers();
    wp_redirect($destination, 302, 'TN QR Codes');
    exit;
}

add_action('admin_menu', 'tn_qr_add_links_page');
function tn_qr_add_links_page() {
    add_management_page('Trackable QR Codes', 'Trackable QR Codes', 'manage_options', 'tn-qr-links', 'tn_qr_render_links_page');
}

add_action('admin_post_tn_qr_create_link', 'tn_qr_create_link');
function tn_qr_create_link() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied', '', ['response' => 403]);
    }
    check_admin_referer('tn_qr_create_link');
    $destination = tn_qr_link_destination(isset($_POST['destination']) ? wp_unslash($_POST['destination']) : '', $_POST);
    if (is_wp_error($destination) || !$destination) {
        $message = is_wp_error($destination) ? $destination->get_error_message() : 'Enter a valid destination URL.';
        wp_die(esc_html($message), 'Invalid QR code', ['response' => 400, 'back_link' => true]);
    }

    global $wpdb;
    $saved = $wpdb->insert(tn_qr_links_table(), [
        'destination' => $destination,
        'visits' => 0,
        'created_at' => current_time('mysql', true),
    ], ['%s', '%d', '%s']);
    if (!$saved) {
        wp_die('Could not save the QR code.', '', ['response' => 500]);
    }

    wp_safe_redirect(add_query_arg('created', $wpdb->insert_id, admin_url('tools.php?page=tn-qr-links')));
    exit;
}

add_action('admin_post_tn_qr_download_link', 'tn_qr_download_link');
function tn_qr_download_link() {
    if (!current_user_can('manage_options')) {
        wp_die('Access denied', '', ['response' => 403]);
    }
    $id = isset($_GET['id']) ? absint(wp_unslash($_GET['id'])) : 0;
    check_admin_referer('tn_qr_download_link_' . $id);
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . tn_qr_links_table() . ' WHERE id = %d', $id));
    if (!$exists) {
        wp_die('QR code not found.', '', ['response' => 404]);
    }
    $file = tn_qr_get_qr_file_data(tn_qr_link_url($id));
    if (!file_exists($file['file_path'])) {
        tn_qr_generate_qr_code(tn_qr_link_url($id));
    }
    if (!file_exists($file['file_path'])) {
        wp_die('QR image could not be generated.', '', ['response' => 500]);
    }
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="trackable-qr-' . $id . '.png"');
    header('Content-Length: ' . filesize($file['file_path']));
    readfile($file['file_path']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
    exit;
}

function tn_qr_render_links_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $table = tn_qr_links_table();
    $page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
    $per_page = 25;
    $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, ($page - 1) * $per_page));
    $created = isset($_GET['created']) ? absint($_GET['created']) : 0;

    echo '<div class="wrap tn-qr-links"><h1>Trackable QR Codes</h1>';
    echo '<p>Create a QR code for any web URL. Each scan visits a tracking link, increments its counter, then redirects to the destination.</p>';
    if ($created) {
        echo '<div class="notice notice-success is-dismissible"><p>QR code created. Its image and tracking link appear below.</p></div>';
    }
    echo '<h2>Create a QR code</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="tn_qr_create_link">';
    wp_nonce_field('tn_qr_create_link');
    echo '<table class="form-table" role="presentation"><tbody>';
    echo '<tr><th><label for="tn-qr-destination">Destination URL</label></th><td><input class="regular-text" type="url" id="tn-qr-destination" name="destination" required placeholder="https://example.com/page"><p class="description">Existing query parameters are preserved.</p></td></tr>';
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'] as $field) {
        echo '<tr><th><label for="tn-qr-' . esc_attr($field) . '">' . esc_html($field) . '</label></th><td><input class="regular-text" type="text" id="tn-qr-' . esc_attr($field) . '" name="' . esc_attr($field) . '"></td></tr>';
    }
    echo '<tr><th>Other query parameters</th><td><div id="tn-qr-extra-fields"><div class="tn-qr-extra-row"><input type="text" name="extra_key[]" placeholder="Parameter name" aria-label="Parameter name"> <input type="text" name="extra_value[]" placeholder="Value" aria-label="Parameter value"> <button type="button" class="button tn-qr-remove-extra">Remove</button></div></div><button type="button" class="button" id="tn-qr-add-extra">Add parameter</button><p class="description">Up to 20 additional parameters. A matching name replaces a value already in the URL.</p></td></tr>';
    echo '</tbody></table>';
    submit_button('Create QR code');
    echo '</form><h2>Saved QR codes</h2>';
    if (!$rows) {
        echo '<p>No trackable QR codes yet.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>QR code</th><th>Destination</th><th>Tracking link</th><th>Visits</th><th>Created</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $link = tn_qr_link_url($row->id);
            $download = wp_nonce_url(add_query_arg(['action' => 'tn_qr_download_link', 'id' => $row->id], admin_url('admin-post.php')), 'tn_qr_download_link_' . $row->id);
            echo '<tr' . ($created === (int) $row->id ? ' class="tn-qr-new-row"' : '') . '>';
            echo '<td><img class="tn-qr-list-image" src="' . esc_url(tn_qr_generate_qr_code($link)) . '" alt="QR code ' . esc_attr($row->id) . '"><br><a href="' . esc_url($download) . '">Download PNG</a></td>';
            echo '<td class="tn-qr-long-url"><a href="' . esc_url($row->destination) . '" target="_blank" rel="noopener noreferrer">' . esc_html($row->destination) . '</a></td>';
            echo '<td class="tn-qr-long-url"><a href="' . esc_url($link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link) . '</a></td>';
            echo '<td>' . esc_html(number_format_i18n($row->visits)) . '</td><td>' . esc_html(get_date_from_gmt($row->created_at, 'Y-m-d H:i')) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
    $pages = (int) ceil($total / $per_page);
    if ($pages > 1) {
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%', admin_url('tools.php?page=tn-qr-links')), 'format' => '', 'current' => $page, 'total' => $pages]));
        echo '</div></div>';
    }
    echo '</div>';
}
