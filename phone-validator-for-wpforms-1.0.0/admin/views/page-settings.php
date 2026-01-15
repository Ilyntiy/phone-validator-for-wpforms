<?php
/**
 * Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wpfpv-wrap">
    
    <!-- Header -->
    <div class="wpfpv-header">
        <div class="wpfpv-header-content">
            <h1 class="wpfpv-title">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Phone Validator for WPForms', 'phone-validator-for-wpforms'); ?>
            </h1>
            <p class="wpfpv-subtitle"><?php _e('Configure phone number validation rules', 'phone-validator-for-wpforms'); ?></p>
        </div>
    </div>
    
    <!-- Settings Form -->
    <form method="post" action="options.php" class="wpfpv-settings-form">
        <?php
        settings_fields('wpfpv_settings_group');
        ?>
        
        <div class="wpfpv-settings-grid">
            
            <!-- General Rules -->
            <div class="wpfpv-card">
                <div class="wpfpv-card-header">
                    <h2 class="wpfpv-card-title">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php _e('General Rules', 'phone-validator-for-wpforms'); ?>
                    </h2>
                </div>
                <div class="wpfpv-card-body">
                    <?php do_settings_sections('wpfpv-settings'); ?>
                </div>
            </div>
            
        </div>
        
        <!-- Save Button -->
        <div class="wpfpv-form-footer">
            <?php submit_button(__('Save Settings', 'phone-validator-for-wpforms'), 'primary wpfpv-btn-large', 'submit', false); ?>
        </div>
        
    </form>
    
</div>
