<?php
/**
 * Plugin Name: Phone Validator for WPForms
 * Plugin URI: https://github.com/Ilyntiy/phone-validator-for-wpforms
 * Description: Advanced phone validation for WPForms.
 * Version: 1.0.1
 * Author: Ilyntiy
 * Author URI: https://profiles.wordpress.org/gogicher/
 * License: GPL v2 or later
 * Text Domain: phone-validator-for-wpforms
 * Requires at least: 5.6
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 0
 * WC tested up to: 0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('WPFPV_VERSION', '1.0.1');
define('WPFPV_PLUGIN_FILE', __FILE__);
define('WPFPV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFPV_PLUGIN_URL', plugin_dir_url(__FILE__));

// Determine log directory path
$upload_dir = wp_upload_dir();
define('WPFPV_LOG_DIR', $upload_dir['basedir'] . '/phone-validator-for-wpforms');
define('WPFPV_LOG_FILE', WPFPV_LOG_DIR . '/validation.log');

// Class Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'WPFPV_';
    $base_dir = WPFPV_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load Admin Interface
require_once WPFPV_PLUGIN_DIR . 'admin/class-admin.php';

// Plugin Initialization
function wpfpv_init() {
    WPFPV_Core::instance();
}
add_action('plugins_loaded', 'wpfpv_init');

// Plugin Activation
register_activation_hook(__FILE__, array('WPFPV_Core', 'activate'));

// Plugin Deactivation
register_deactivation_hook(__FILE__, array('WPFPV_Core', 'deactivate'));