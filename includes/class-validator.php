<?php
/**
 * Phone validation
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Validator {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wpforms_process_validate_phone', array($this, 'validate_phone'), 10, 3);
    }
    
    /**
     * Main phone validation
     */
    public function validate_phone($field_id, $field_submit, $form_data) {
        $settings = WPFPV_Settings::get();
        $phone_digits = preg_replace('/\D/', '', $field_submit);
        $error_message = '';
        
        // 1. Check minimum length
        if (strlen($phone_digits) < $settings['min_length']) {
            $error_message = sprintf(
                __('The number must contain at least %d digits', 'phone-validator-for-wpforms'), 
                $settings['min_length']
            );
        }
        
        // 2. Check maximum length
        elseif (strlen($phone_digits) > $settings['max_length']) {
            $error_message = sprintf(
                __('The number cannot contain more than %d digits', 'phone-validator-for-wpforms'), 
                $settings['max_length']
            );
        }
               
        // 3. Check consecutive identical digits
        elseif ($settings['max_repeats'] > 0) {
            $pattern = '/(\d)\1{' . $settings['max_repeats'] . ',}/';
            if (preg_match($pattern, $phone_digits)) {
                $error_message = __('The number contains too many identical digits in a row', 'phone-validator-for-wpforms');
            }
        }
        
        // 4. Check blacklist
        if (empty($error_message) && !empty($settings['blacklist'])) {
            if ($this->is_blacklisted($phone_digits, $settings['blacklist'])) {
                $error_message = __('This number is blacklisted', 'phone-validator-for-wpforms');
            }
        }
        
        // 5. Check country codes
        if (empty($error_message) && !empty($settings['allowed_country_codes'])) {
            if (!$this->has_valid_country_code($field_submit, $settings['allowed_country_codes'])) {
                $error_message = sprintf(
                    __('Only numbers with the following codes are allowed: %s', 'phone-validator-for-wpforms'), 
                    $settings['allowed_country_codes']
                );
            }
        }
               
        // Log error if exists
        if (!empty($error_message)) {
            wpforms()->process->errors[$form_data['id']][$field_id] = $error_message;
            WPFPV_Logger::log_validation_error($form_data['id'], $field_id, $field_submit, $error_message);
        }
    }
    
    /**
     * Check blacklist
     */
    private function is_blacklisted($phone_digits, $blacklist) {
        $blocked_numbers = array_filter(array_map('trim', explode("\n", $blacklist)));
        foreach ($blocked_numbers as $blocked) {
            // Exact match only
            if ($phone_digits === $blocked) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check country code
     */
    private function has_valid_country_code($field_submit, $allowed_codes) {
        $codes = array_map('trim', explode(',', $allowed_codes));
        
        // Normalize: remove all non-digits except leading +
        $normalized = preg_replace('/[^\d+]/', '', $field_submit);
        
        foreach ($codes as $code) {
            // Check both with + and without
            $code_clean = ltrim($code, '+');
            
            // Match +7xxx or 7xxx
            if (strpos($normalized, '+' . $code_clean) === 0 || 
                strpos($normalized, $code_clean) === 0) {
                return true;
            }
        }
        return false;
    }
}