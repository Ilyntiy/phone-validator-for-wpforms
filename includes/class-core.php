<?php
/**
 * Plugin bootstrap: components, upgrade routine, activation.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin core.
 */
class WPFPV_Core {

	const SCHEMA_OPTION = 'wpfpv_schema';
	const SCHEMA        = 2;
	const CRON_HOOK     = 'wpfpv_daily_cleanup';

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Core|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Core
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Registered even without WPForms, so old log entries are still removed.
		add_action( self::CRON_HOOK, array( 'WPFPV_Logger', 'cleanup_old_logs' ) );

		WPFPV_Settings::instance();

		if ( is_admin() ) {
			WPFPV_Admin::instance();
			add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		}

		if ( ! function_exists( 'wpforms' ) ) {
			add_action( 'admin_notices', array( $this, 'wpforms_missing_notice' ) );
			return;
		}

		WPFPV_Validator::instance();
		WPFPV_Throttle::instance();
		WPFPV_Honeypot::instance();
		WPFPV_Guard::instance();
		WPFPV_Frontend::instance();
	}

	/**
	 * Run the upgrade steps once after the plugin files were updated.
	 */
	public function maybe_upgrade() {
		if ( (int) get_option( self::SCHEMA_OPTION, 0 ) < self::SCHEMA ) {
			self::upgrade();
		}
	}

	/**
	 * Upgrade steps, safe to run more than once.
	 */
	private static function upgrade() {
		// Settings of earlier versions are kept: missing keys fall back to defaults,
		// so the honeypot is on, the limit and the mask are off.
		WPFPV_Logger::migrate_legacy_log();
		self::drop_redundant_blacklist_entries();
		self::schedule_cleanup();
		update_option( self::SCHEMA_OPTION, self::SCHEMA, false );
	}

	/**
	 * Version 1.0 shipped 111111 ... 000000 in the blacklist. The "identical digits in a row"
	 * check covers them, so they are removed, but only when that check is on and would catch them.
	 */
	private static function drop_redundant_blacklist_entries() {
		$saved = get_option( WPFPV_Settings::OPTION );
		if ( ! is_array( $saved ) || ! isset( $saved['blacklist'] ) || ! is_string( $saved['blacklist'] ) ) {
			return;
		}

		$repeats = isset( $saved['max_repeats'] ) ? (int) $saved['max_repeats'] : 5;
		if ( $repeats < 1 || $repeats > 5 ) {
			return;
		}

		$redundant = array( '111111', '222222', '333333', '444444', '555555', '666666', '777777', '888888', '999999', '000000' );
		$kept      = array();
		foreach ( preg_split( '/\R/', $saved['blacklist'] ) as $line ) {
			if ( ! in_array( trim( $line ), $redundant, true ) ) {
				$kept[] = $line;
			}
		}

		$blacklist = implode( "\n", $kept );
		if ( $blacklist !== $saved['blacklist'] ) {
			$saved['blacklist'] = trim( $blacklist );
			update_option( WPFPV_Settings::OPTION, $saved );
		}
	}

	/**
	 * Notice shown when WPForms is not active.
	 */
	public function wpforms_missing_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Phone Validator for WPForms', 'phone-validator-for-wpforms' ); ?></strong>
				<?php esc_html_e( 'requires the WPForms plugin (Lite or Pro) to be installed and activated.', 'phone-validator-for-wpforms' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		if ( false === get_option( WPFPV_Settings::OPTION ) ) {
			$defaults = WPFPV_Settings::get_defaults();

			// A fresh install guesses the default country from the site language.
			$defaults['default_country'] = WPFPV_Phone::country_from_locale( get_locale() );

			// The browser check is on for new sites. Sites updated from 1.1.0 keep it off until the owner turns it on.
			$defaults['guard_required'] = 1;
			add_option( WPFPV_Settings::OPTION, $defaults, '', false );
		}

		WPFPV_Logger::ensure_dir();
		self::upgrade();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Make sure the daily cleanup is scheduled.
	 */
	private static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}
}
