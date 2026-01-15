<?php
/**
 * Plugin Uninstallation
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin settings
delete_option('wpfpv_settings');

// Delete log file (optional)
$log_file = WP_CONTENT_DIR . '/wpforms-phone-validation.log';
if (file_exists($log_file)) {
    unlink($log_file);
}

// Clear all plugin transients
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpfpv_%' OR option_name LIKE '_transient_timeout_wpfpv_%'");