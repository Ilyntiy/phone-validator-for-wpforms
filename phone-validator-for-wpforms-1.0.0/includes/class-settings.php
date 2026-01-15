<?php
/**
 * Settings Management
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Settings {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Default settings
     */
    public static function get_defaults() {
        return array(
            'min_length' => 6,
            'max_length' => 15,
            'max_repeats' => 5,
            'allowed_country_codes' => '',
            'enable_logging' => 1,
            'auto_clean_logs' => 30,
            'throttle_time' => 3,
            'blacklist' => "111111\n222222\n333333\n444444\n555555\n666666\n777777\n888888\n999999\n000000\n1234567890\n0987654321"
        );
    }
    
    /**
     * Get settings
     */
    public static function get() {
        $defaults = self::get_defaults();
        $settings = get_option('wpfpv_settings', $defaults);
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wpfpv_settings_group', 'wpfpv_settings', array($this, 'validate'));
        
        // General Settings
        add_settings_section('wpfpv_main', __('General Rules', 'phone-validator-for-wpforms'), 
            array($this, 'section_main'), 'wpfpv-settings');
        
        add_settings_field('min_length', __('Minimum length (digits)', 'phone-validator-for-wpforms'), 
            array($this, 'field_min_length'), 'wpfpv-settings', 'wpfpv_main');
        
        add_settings_field('max_length', __('Maximum length (digits)', 'phone-validator-for-wpforms'), 
            array($this, 'field_max_length'), 'wpfpv-settings', 'wpfpv_main');
        
        add_settings_field('max_repeats', __('Max consecutive identical digits', 'phone-validator-for-wpforms'), 
            array($this, 'field_max_repeats'), 'wpfpv-settings', 'wpfpv_main');
        
        // Advanced Checks
        add_settings_section('wpfpv_advanced', __('Advanced Checks', 'phone-validator-for-wpforms'), 
            array($this, 'section_advanced'), 'wpfpv-settings');
        
        add_settings_field('blacklist', __('Blacklist', 'phone-validator-for-wpforms'), 
            array($this, 'field_blacklist'), 'wpfpv-settings', 'wpfpv_advanced');
        
        add_settings_field('allowed_country_codes', __('Allowed country codes', 'phone-validator-for-wpforms'), 
            array($this, 'field_country_codes'), 'wpfpv-settings', 'wpfpv_advanced');
        
        // Security System
        add_settings_section('wpfpv_system', __('Security System', 'phone-validator-for-wpforms'), 
            array($this, 'section_system'), 'wpfpv-settings');
        
        add_settings_field('throttle_time', __('Submission interval (min)', 'phone-validator-for-wpforms'), 
            array($this, 'field_throttle'), 'wpfpv-settings', 'wpfpv_system');
        
        add_settings_field('enable_logging', __('Enable logging', 'phone-validator-for-wpforms'), 
            array($this, 'field_logging'), 'wpfpv-settings', 'wpfpv_system');
        
        add_settings_field('auto_clean_logs', __('Auto-clean logs (days)', 'phone-validator-for-wpforms'), 
            array($this, 'field_auto_clean'), 'wpfpv-settings', 'wpfpv_system');
    }
    
    /**
     * Section: General Rules
     */
    public function section_main() {
        echo '<p class="wpfpv-section-desc">' . __('Basic phone number validation parameters', 'phone-validator-for-wpforms') . '</p>';
    }
    
    /**
     * Section: Advanced Checks
     */
    public function section_advanced() {
        echo '<p class="wpfpv-section-desc">' . __('Protection against spam and fake numbers', 'phone-validator-for-wpforms') . '</p>';
    }
    
    /**
     * Section: Security System
     */
    public function section_system() {
        echo '<p class="wpfpv-section-desc">' . __('Settings for repeat submission protection and logging', 'phone-validator-for-wpforms') . '</p>';
    }
    
    /**
     * Field: Minimum length
     */
    public function field_min_length() {
        $settings = self::get();
        ?>
        <input type="number" name="wpfpv_settings[min_length]" value="<?php echo esc_attr($settings['min_length']); ?>" min="1" max="20" class="wpfpv-input-number">
        <p class="description"><?php _e('Minimum number of digits (recommended 6-10)', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Field: Maximum length
     */
    public function field_max_length() {
        $settings = self::get();
        ?>
        <input type="number" name="wpfpv_settings[max_length]" value="<?php echo esc_attr($settings['max_length']); ?>" min="5" max="20" class="wpfpv-input-number">
        <p class="description"><?php _e('Maximum number of digits (recommended 11-15)', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Field: Max consecutive identical digits
     */
    public function field_max_repeats() {
        $settings = self::get();
        ?>
        <input type="number" name="wpfpv_settings[max_repeats]" value="<?php echo esc_attr($settings['max_repeats']); ?>" min="2" max="10" class="wpfpv-input-number">
        <p class="description"><?php _e('Blocks entries with too many identical consecutive digits', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
       
    /**
     * Field: Blacklist
     */
    public function field_blacklist() {
        $settings = self::get();
        ?>
        <textarea name="wpfpv_settings[blacklist]" rows="6" class="wpfpv-textarea"><?php echo esc_textarea($settings['blacklist']); ?></textarea>
        <p class="description"><?php _e('One number per line (digits only)', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Field: Country codes
     */
    public function field_country_codes() {
        $settings = self::get();
        ?>
        <input type="text" name="wpfpv_settings[allowed_country_codes]" value="<?php echo esc_attr($settings['allowed_country_codes']); ?>" class="wpfpv-input-text" placeholder="+7, +380, +375">
        <p class="description"><?php _e('Comma separated. Empty = all codes allowed', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Field: Submission interval
     */
    public function field_throttle() {
        $settings = self::get();
        ?>
        <input type="number" name="wpfpv_settings[throttle_time]" value="<?php echo esc_attr($settings['throttle_time']); ?>" min="0" max="60" class="wpfpv-input-number">
        <p class="description"><?php _e('Minimum interval between successful submissions (0 = disabled)', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Field: Enable logging
     */
    public function field_logging() {
        $settings = self::get();
        ?>
        <label class="wpfpv-switch">
            <input type="checkbox" name="wpfpv_settings[enable_logging]" value="1" <?php checked(1, $settings['enable_logging']); ?>>
            <span class="wpfpv-slider"></span>
        </label>
        <span class="wpfpv-switch-label"><?php _e('Log validation errors', 'phone-validator-for-wpforms'); ?></span>
        <?php
    }
    
    /**
     * Field: Auto-clean logs
     */
    public function field_auto_clean() {
        $settings = self::get();
        ?>
        <input type="number" name="wpfpv_settings[auto_clean_logs]" value="<?php echo esc_attr($settings['auto_clean_logs']); ?>" min="0" max="365" class="wpfpv-input-number">
        <p class="description"><?php _e('Delete logs older than specified number of days (0 = disabled)', 'phone-validator-for-wpforms'); ?></p>
        <?php
    }
    
    /**
     * Validate settings
     */
    public function validate($input) {
        $output = array();
        
        $output['min_length'] = absint($input['min_length']);
        $output['max_length'] = absint($input['max_length']);
        $output['max_repeats'] = absint($input['max_repeats']);
        $output['allowed_country_codes'] = sanitize_text_field($input['allowed_country_codes']);
        $output['enable_logging'] = isset($input['enable_logging']) ? 1 : 0;
        $output['auto_clean_logs'] = absint($input['auto_clean_logs']);
        $output['throttle_time'] = absint($input['throttle_time']);
        $output['blacklist'] = sanitize_textarea_field($input['blacklist']);
        
        if ($output['min_length'] > $output['max_length']) {
            add_settings_error('wpfpv_settings', 'invalid_length', 
                __('Minimum length cannot be greater than maximum length!', 'phone-validator-for-wpforms'));
            $output['min_length'] = $output['max_length'];
        }
        
        return $output;
    }
}