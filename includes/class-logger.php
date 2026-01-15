<?php
/**
 * Logging System
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPFPV_Logger {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('wpfpv_daily_cleanup', array($this, 'cleanup_old_logs'));
        
        if (!wp_next_scheduled('wpfpv_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'wpfpv_daily_cleanup');
        }
    }
    
    /**
     * Log validation error
     */
    public static function log_validation_error($form_id, $field_id, $field_value, $error_message) {
        $settings = WPFPV_Settings::get();
        
        if (!$settings['enable_logging']) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] VALIDATION ERROR | IP: %s | Form: %s | Field: %s | Value: %s | Error: %s\n",
            date('Y-m-d H:i:s'),
            self::get_user_ip(),
            $form_id,
            $field_id,
            $field_value,
            $error_message
        );
        
        self::write_to_file($log_entry);
    }
    
    /**
     * Log throttle block
     */
    public static function log_throttle_block($form_id, $ip, $minutes_remaining) {
        $settings = WPFPV_Settings::get();
        
        if (!$settings['enable_logging']) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] THROTTLE BLOCK | IP: %s | Form: %s | Remaining: %d min\n",
            date('Y-m-d H:i:s'),
            $ip,
            $form_id,
            $minutes_remaining
        );
        
        self::write_to_file($log_entry);
    }
    
    /**
     * Log successful submission
     */
    public static function log_successful_submission($form_id, $ip) {
        $settings = WPFPV_Settings::get();
        
        if (!$settings['enable_logging']) {
            return;
        }
        
        $log_entry = sprintf(
            "[%s] SUCCESS | IP: %s | Form: %s | Throttle activated\n",
            date('Y-m-d H:i:s'),
            $ip,
            $form_id
        );
        
        self::write_to_file($log_entry);
    }
    
    /**
     * Write to file
     */
    private static function write_to_file($log_entry) {
        file_put_contents(WPFPV_LOG_FILE, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get IP address
     */
    private static function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } else {
            return sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
    }
    
    /**
     * Get all logs
     */
    public static function get_logs() {
        if (!file_exists(WPFPV_LOG_FILE)) {
            return array();
        }
        
        $content = file_get_contents(WPFPV_LOG_FILE);
        return array_filter(explode("\n", $content));
    }
    
    /**
     * Clear logs
     */
    public static function clear_logs() {
        if (file_exists(WPFPV_LOG_FILE)) {
            file_put_contents(WPFPV_LOG_FILE, '');
            return true;
        }
        return false;
    }
    
    /**
     * Final statistics
     */
    public static function get_stats() {
        $logs = self::get_logs();
        $total = count($logs);
        $total_errors = 0;
        $today_all = 0;
        $today_errors = 0;
        $unique_ips = array();
        
        foreach ($logs as $entry) {
            if (strpos($entry, 'VALIDATION ERROR') !== false) {
                $total_errors++;
            }
            
            if (strpos($entry, 'VALIDATION ERROR') !== false && 
                strpos($entry, date('Y-m-d')) !== false) {
                $today_errors++;
            }
            
            if (strpos($entry, date('Y-m-d')) !== false) {
                $today_all++;
            }
            
            if (preg_match('/IP:\s*([\d\.:]+)/', $entry, $matches)) {
                $unique_ips[$matches[1]] = true;
            }
        }
        
        $file_size = file_exists(WPFPV_LOG_FILE) ? filesize(WPFPV_LOG_FILE) : 0;
        
        return array(
            'total' => $total,
            'total_errors' => $total_errors,
            'today_all' => $today_all,
            'today_errors' => $today_errors,
            'unique_ips' => count($unique_ips),
            'file_size' => size_format($file_size)
        );
    }
    
    /**
     * Automatic cleanup of old logs
     */
    public function cleanup_old_logs() {
        $settings = WPFPV_Settings::get();
        $auto_clean = absint($settings['auto_clean_logs']);
        
        if ($auto_clean <= 0 || !file_exists(WPFPV_LOG_FILE)) {
            return;
        }
        
        $cutoff_date = date('Y-m-d', strtotime('-' . $auto_clean . ' days'));
        $logs = self::get_logs();
        $new_logs = array();
        
        foreach ($logs as $line) {
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoff_date) {
                    $new_logs[] = $line;
                }
            }
        }
        
        file_put_contents(WPFPV_LOG_FILE, implode("\n", $new_logs));
    }
}