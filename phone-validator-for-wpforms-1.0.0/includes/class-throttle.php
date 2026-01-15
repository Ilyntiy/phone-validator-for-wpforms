<?php
/**
 * Protection against repeat submissions
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Throttle {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wpforms_process', array($this, 'check_throttle'), 5, 3);
        add_action('wpforms_process_complete', array($this, 'save_submission'), 10, 4);
    }
    
    /**
     * Check submission interval
     */
    public function check_throttle($fields, $entry, $form_data) {
        $settings = WPFPV_Settings::get();
        $throttle_time = absint($settings['throttle_time']);
        
        if ($throttle_time <= 0) {
            return;
        }
        
        $user_ip = $this->get_user_ip();
        $form_id = $form_data['id'];
        $transient_key = 'wpfpv_throttle_' . md5($user_ip . '_' . $form_id);
        
        $last_submission = get_transient($transient_key);
        
        if ($last_submission !== false) {
            $time_passed = time() - $last_submission;
            $time_remaining = ($throttle_time * 60) - $time_passed;
            $minutes_remaining = ceil($time_remaining / 60);
            
            wpforms()->process->errors[$form_data['id']]['header'] = sprintf(
                __('You have recently submitted this form. Please wait %d min. before next submission.', 'phone-validator-for-wpforms'),
                $minutes_remaining
            );
            
            WPFPV_Logger::log_throttle_block($form_id, $user_ip, $minutes_remaining);
        }
    }
    
    /**
     * Save successful submission
     */
    public function save_submission($fields, $entry, $form_data, $entry_id) {
        $settings = WPFPV_Settings::get();
        $throttle_time = absint($settings['throttle_time']);
        
        if ($throttle_time <= 0) {
            return;
        }
        
        if (!empty(wpforms()->process->errors[$form_data['id']])) {
            return;
        }
        
        $user_ip = $this->get_user_ip();
        $form_id = $form_data['id'];
        $transient_key = 'wpfpv_throttle_' . md5($user_ip . '_' . $form_id);
        
        set_transient($transient_key, time(), $throttle_time * 60);
        
        WPFPV_Logger::log_successful_submission($form_id, $user_ip);
    }
    
    /**
     * Get user IP
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }
}