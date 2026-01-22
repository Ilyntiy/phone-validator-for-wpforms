<?php
/**
 * Logs Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats = WPFPV_Logger::get_stats();
$logs = WPFPV_Logger::get_logs();
$settings = WPFPV_Settings::get();
$display_logs = array_slice(array_reverse($logs), 0, 100);
?>

<div class="wpfpv-wrap">
    
    <!-- Page Header -->
    <div class="wpfpv-header">
        <div class="wpfpv-header-content">
            <h1 class="wpfpv-title">
                <span class="dashicons dashicons-phone"></span>
                <?php esc_html_e('Phone Validation History', 'phone-validator-for-wpforms'); ?>
            </h1>
            <p class="wpfpv-subtitle"><?php esc_html_e('View and analyze form validation errors', 'phone-validator-for-wpforms'); ?></p>
        </div>
    </div>
    
    <!-- FINAL STATISTICS -->
    <div class="wpfpv-stats-grid">
        <!-- 1. Total validation errors -->
        <div class="wpfpv-stat-card">
            <div class="wpfpv-stat-icon wpfpv-stat-icon-error">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="wpfpv-stat-content">
                <div class="wpfpv-stat-value"><?php echo esc_html(number_format($stats['total_errors'])); ?></div>
                <div class="wpfpv-stat-label"><?php esc_html_e('Total Errors', 'phone-validator-for-wpforms'); ?></div>
            </div>
        </div>
        
        <!-- 2. Total log entries -->
        <div class="wpfpv-stat-card">
            <div class="wpfpv-stat-icon wpfpv-stat-icon-today">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div class="wpfpv-stat-content">
                <div class="wpfpv-stat-value"><?php echo esc_html(number_format($stats['total'])); ?></div>
                <div class="wpfpv-stat-label"><?php esc_html_e('Total Entries', 'phone-validator-for-wpforms'); ?></div>
            </div>
        </div>
        
        <!-- 3. Unique IPs -->
        <div class="wpfpv-stat-card">
            <div class="wpfpv-stat-icon wpfpv-stat-icon-users">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            <div class="wpfpv-stat-content">
                <div class="wpfpv-stat-value"><?php echo esc_html(number_format($stats['unique_ips'])); ?></div>
                <div class="wpfpv-stat-label"><?php esc_html_e('Unique IPs', 'phone-validator-for-wpforms'); ?></div>
            </div>
        </div>
        
        <!-- 4. Logs size -->
        <div class="wpfpv-stat-card">
            <div class="wpfpv-stat-icon wpfpv-stat-icon-storage">
                <span class="dashicons dashicons-database"></span>
            </div>
            <div class="wpfpv-stat-content">
                <div class="wpfpv-stat-value"><?php echo esc_html($stats['file_size']); ?></div>
                <div class="wpfpv-stat-label"><?php esc_html_e('Log Size', 'phone-validator-for-wpforms'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Logs Journal -->
    <div class="wpfpv-card">
        <div class="wpfpv-card-header">
            <h2 class="wpfpv-card-title">
                <span class="dashicons dashicons-list-view"></span>
                <?php esc_html_e('Validation Error Log', 'phone-validator-for-wpforms'); ?>
            </h2>
            <div class="wpfpv-card-actions">
                <?php if ($stats['total'] > 0): ?>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('wpfpv_clear_logs'); ?>
                        <button type="submit" 
                                name="wpfpv_clear_logs" 
                                class="wpfpv-btn wpfpv-btn-secondary"
                                onclick="return confirm('<?php esc_attr_e('Are you sure you want to clear all logs?', 'phone-validator-for-wpforms'); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear Logs', 'phone-validator-for-wpforms'); ?>
                        </button>
                    </form>
                    <a href="<?php echo esc_url(WPFPV_LOG_FILE); ?>" 
                       class="wpfpv-btn wpfpv-btn-primary" 
                       download="wpforms-phone-validation.log">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Download Logs', 'phone-validator-for-wpforms'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="wpfpv-card-body">
            <?php if ($stats['total'] > 0): ?>
                <div class="wpfpv-logs-container">
                    <?php foreach ($display_logs as $entry): 
                        if (trim($entry)):
                            $entry_type = 'error';
                            if (strpos($entry, 'SUCCESS') !== false) {
                                $entry_type = 'success';
                            } elseif (strpos($entry, 'THROTTLE') !== false) {
                                $entry_type = 'warning';
                            }
                    ?>
                        <div class="wpfpv-log-entry wpfpv-log-<?php echo esc_html($entry_type); ?>">
                            <span class="wpfpv-log-icon">
                                <?php if ($entry_type === 'success'): ?>
                                    <span class="dashicons dashicons-yes-alt"></span>
                                <?php elseif ($entry_type === 'warning'): ?>
                                    <span class="dashicons dashicons-clock"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning"></span>
                                <?php endif; ?>
                            </span>
                            <span class="wpfpv-log-text"><?php echo esc_html($entry); ?></span>
                        </div>
                    <?php endif; endforeach; ?>
                    
                    <?php if ($stats['total'] > 100): ?>
                        <div class="wpfpv-log-notice">
                            <span class="dashicons dashicons-info"></span>
                            <?php printf(esc_html__('Showing last 100 of %s entries.', 'phone-validator-for-wpforms'), esc_html(number_format($stats['total']))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="wpfpv-empty-state">
                    <div class="wpfpv-empty-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h3><?php esc_html_e('Logs are empty', 'phone-validator-for-wpforms'); ?></h3>
                    <p><?php esc_html_e('Submit a test form with an invalid number to check.', 'phone-validator-for-wpforms'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Info Panel -->
    <div class="wpfpv-card wpfpv-info-card">
        <div class="wpfpv-card-header">
            <h2 class="wpfpv-card-title">
                <span class="dashicons dashicons-info-outline"></span>
                <?php esc_html_e('Active Validation Rules', 'phone-validator-for-wpforms'); ?>
            </h2>
        </div>
        <div class="wpfpv-card-body">
            <div class="wpfpv-info-grid">
                <div class="wpfpv-info-item">
                    <span class="wpfpv-info-icon"><span class="dashicons dashicons-yes"></span></span>
                    <?php printf(esc_html__('Min length: %d digits', 'phone-validator-for-wpforms'), esc_html($settings['min_length'])); ?>
                </div>
                <div class="wpfpv-info-item">
                    <span class="wpfpv-info-icon"><span class="dashicons dashicons-yes"></span></span>
                    <?php printf(esc_html__('Max length: %d digits', 'phone-validator-for-wpforms'), esc_html($settings['max_length'])); ?>
                </div>
                <?php if ($settings['max_repeats'] > 0): ?>
                <div class="wpfpv-info-item">
                    <span class="wpfpv-info-icon"><span class="dashicons dashicons-yes"></span></span>
                    <?php printf(esc_html__('Max repeats: %d', 'phone-validator-for-wpforms'), esc_html($settings['max_repeats'])); ?>
                </div>
                <?php endif; ?>
                <?php if ($settings['throttle_time'] > 0): ?>
                <div class="wpfpv-info-item">
                    <span class="wpfpv-info-icon"><span class="dashicons dashicons-yes"></span></span>
                    <?php printf(esc_html__('Throttling: %d min', 'phone-validator-for-wpforms'), esc_html($settings['throttle_time'])); ?>
                </div>
                <?php endif; ?>
                <div class="wpfpv-info-item wpfpv-info-item-path">
                    <span class="wpfpv-info-icon"><span class="dashicons dashicons-media-code"></span></span>
                    <code><?php echo esc_html(WPFPV_LOG_FILE); ?></code>
                </div>
            </div>
        </div>
    </div>
    
</div>