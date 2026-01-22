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
        // Use REMOTE_ADDR as primary (most reliable)
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        
        // Only trust X-Forwarded-For if behind a known proxy (e.g., Cloudflare, load balancer)
        // and REMOTE_ADDR is a trusted proxy IP
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded_ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            // Use first IP in chain (original client IP)
            if (!empty($forwarded_ips[0]) && filter_var($forwarded_ips[0], FILTER_VALIDATE_IP)) {
                // Only use if you're behind a trusted proxy (optional check)
                // For basic security, prefer REMOTE_ADDR
                // $ip = $forwarded_ips[0];
            }
        }
        
        return sanitize_text_field($ip);
    }
}