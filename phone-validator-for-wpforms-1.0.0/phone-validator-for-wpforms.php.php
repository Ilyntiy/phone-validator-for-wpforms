<?php
/**
 * Plugin Name:       Phone Validator for WPForms
 * Plugin URI:        https://wordpress.org/plugins/phone-validator-for-wpforms/
 * Description:       Advanced phone number validation, country code checks, and anti-spam protection for WPForms fields.
 * Version:           1.0.0
 * Author:            Advertsales
 * Author URI:        https://profiles.wordpress.org/advertsales/
 * Text Domain:       phone-validator-for-wpforms
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP:      7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('WPFPV_VERSION', '4.0.0');
define('WPFPV_PLUGIN_FILE', __FILE__);
define('WPFPV_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPFPV_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPFPV_LOG_FILE', WP_CONTENT_DIR . '/wpforms-phone-validation.log');

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
    load_plugin_textdomain('phone-validator-for-wpforms', false, dirname(plugin_basename(__FILE__)) . '/languages');
    WPFPV_Core::instance();
}
add_action('plugins_loaded', 'wpfpv_init');

// Plugin Activation
register_activation_hook(__FILE__, array('WPFPV_Core', 'activate'));

// Plugin Deactivation
register_deactivation_hook(__FILE__, array('WPFPV_Core', 'deactivate'));