/**
 * Admin panel JavaScript
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Confirm log clearing
        $('.wpfpv-btn-secondary[name="wpfpv_clear_logs"]').on('click', function(e) {
            if (!confirm(wpfpvAdmin.clearConfirm || 'Вы уверены?')) {
                e.preventDefault();
                return false;
            }
        });
        
        // Smooth scroll to errors
        if ($('.settings-error').length) {
            $('html, body').animate({
                scrollTop: $('.settings-error').offset().top - 100
            }, 500);
        }
        
        // Highlight saved settings
        if ($('#setting-error-settings_updated').length) {
            $('.wpfpv-settings-form').addClass('wpfpv-settings-saved');
            setTimeout(function() {
                $('.wpfpv-settings-form').removeClass('wpfpv-settings-saved');
            }, 2000);
        }
        
    });
    
})(jQuery);