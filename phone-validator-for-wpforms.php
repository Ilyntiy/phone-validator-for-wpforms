<?php
/**
 * Plugin Name:       Phone Validator for WPForms
 * Plugin URI:        https://github.com/Ilyntiy/phone-validator-for-wpforms
 * Description:       Filters junk and bot leads in WPForms: phone validation for any country, input mask, honeypot, per-IP limits and a submission log.
 * Version:           1.1.1
 * Requires at least: 5.6
 * Requires PHP:      7.4
 * Author:            Ilyntiy
 * Author URI:        https://profiles.wordpress.org/gogicher/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       phone-validator-for-wpforms
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPFPV_VERSION', '1.1.1' );
define( 'WPFPV_PLUGIN_FILE', __FILE__ );
define( 'WPFPV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPFPV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'WPFPV_';
		if ( 0 !== strncmp( $prefix, $class_name, strlen( $prefix ) ) ) {
			return;
		}

		$relative = strtolower( str_replace( '_', '-', substr( $class_name, strlen( $prefix ) ) ) );
		$paths    = array(
			WPFPV_PLUGIN_DIR . 'includes/class-' . $relative . '.php',
			WPFPV_PLUGIN_DIR . 'admin/class-' . $relative . '.php',
		);

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				return;
			}
		}
	}
);

register_activation_hook( __FILE__, array( 'WPFPV_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPFPV_Core', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPFPV_Core', 'instance' ) );
