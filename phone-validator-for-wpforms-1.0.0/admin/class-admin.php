<?php
/**
 * Admin Panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Admin {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add menu
     */
    public function add_menu() {
        add_menu_page(
            __('Phone Validator', 'phone-validator-for-wpforms'),
            __('Phone Validator', 'phone-validator-for-wpforms'),
            'manage_options',
            'phone-validator-for-wpf',
            array($this, 'render_logs_page'),
            'dashicons-phone',
            65
        );
        
        add_submenu_page(
            'phone-validator-for-wpf',
            __('Validation History', 'phone-validator-for-wpforms'),
            __('History', 'phone-validator-for-wpforms'),
            'manage_options',
            'phone-validator-for-wpf',
            array($this, 'render_logs_page')
        );
        
        add_submenu_page(
            'phone-validator-for-wpf',
            __('Settings', 'phone-validator-for-wpforms'),
            __('Settings', 'phone-validator-for-wpforms'),
            'manage_options',
            'phone-validator-for-wpf-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'phone-validator-for-wpf') === false) {
            return;
        }
        
        wp_enqueue_style(
            'wpfpv-admin-style',
            WPFPV_PLUGIN_URL . 'admin/css/admin-style.css',
            array(),
            WPFPV_VERSION
        );
        
        wp_enqueue_script(
            'wpfpv-admin-script',
            WPFPV_PLUGIN_URL . 'admin/js/admin-script.js',
            array('jquery'),
            WPFPV_VERSION,
            true
        );
        
        wp_localize_script('wpfpv-admin-script', 'wpfpvAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpfpv_admin_nonce')
        ));
    }
    
    /**
     * Logs page
     */
    public function render_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'phone-validator-for-wpforms'));
        }
        
        if (isset($_POST['wpfpv_clear_logs']) && check_admin_referer('wpfpv_clear_logs')) {
            WPFPV_Logger::clear_logs();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Logs cleared successfully!', 'phone-validator-for-wpforms') . '</p></div>';
        }
        
        require_once WPFPV_PLUGIN_DIR . 'admin/views/page-logs.php';
    }
    
    /**
     * Страница настроек
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'phone-validator-for-wpforms'));
        }
        
        require_once WPFPV_PLUGIN_DIR . 'admin/views/page-settings.php';
    }
}