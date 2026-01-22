<?php
/**
 * Plugin Uninstallation
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin settings
delete_option('wpfpv_settings');

// Remove log directory and all contents
$upload_dir = wp_upload_dir();
$log_dir = $upload_dir['basedir'] . '/wpfpv-logs';

if (file_exists($log_dir)) {
    // Remove all files in directory
    $files = glob($log_dir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    // Remove directory
    @rmdir($log_dir);
}

// Clear all plugin transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('_transient_wpfpv_') . '%',
        $wpdb->esc_like('_transient_timeout_wpfpv_') . '%'
    )
);
