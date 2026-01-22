<?php
/**
 * Plugin Core
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Core {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->check_dependencies();
        $this->init_components();
    }
    
    /**
     * Check dependencies
     */
    private function check_dependencies() {
        if (!function_exists('wpforms')) {
            add_action('admin_notices', array($this, 'wpforms_missing_notice'));
            return;
        }
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        if (!function_exists('wpforms')) {
            return;
        }
        
        WPFPV_Settings::instance();
        WPFPV_Logger::instance();
        WPFPV_Validator::instance();
        WPFPV_Throttle::instance();
        
        if (is_admin()) {
            WPFPV_Admin::instance();
        }
    }
    
    /**
     * WPForms missing notice
     */
    public function wpforms_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php esc_html_e('Phone Validator for WPForms', 'phone-validator-for-wpforms'); ?></strong> 
                <?php esc_html_e('requires WPForms plugin to be activated!', 'phone-validator-for-wpforms'); ?>
            </p>
        </div>
        <?php
    }
    
    /**
     * Plugin Activation
     */
public static function activate() {
    if (!get_option('wpfpv_settings')) {
        add_option('wpfpv_settings', WPFPV_Settings::get_defaults());
    }
    
    // Create protected log directory
    self::setup_log_directory();
}

/**
 * Setup log directory with protection
 */
    private static function setup_log_directory() {
        // Create directory if not exists
        if (!file_exists(WPFPV_LOG_DIR)) {
            wp_mkdir_p(WPFPV_LOG_DIR);
        }
        
        // Check if writable
        if (!is_writable(WPFPV_LOG_DIR)) {
            return false;
        }
        
        // Create empty log file
        if (!file_exists(WPFPV_LOG_FILE)) {
            file_put_contents(WPFPV_LOG_FILE, '');
        }
        
        // Create .htaccess for Apache protection
        $htaccess_file = WPFPV_LOG_DIR . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Deny all access\n";
            $htaccess_content .= "Order deny,allow\n";
            $htaccess_content .= "Deny from all\n";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create index.php for extra protection
        $index_file = WPFPV_LOG_DIR . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
        
        return true;
    }

    
    /**
     * Plugin Deactivation
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled('wpfpv_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'wpfpv_daily_cleanup');
        }
    }
}