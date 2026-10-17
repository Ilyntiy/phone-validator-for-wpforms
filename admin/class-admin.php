<?php
/**
 * Admin screens: settings, history and the actions behind their buttons.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface.
 */
class WPFPV_Admin {

	const SLUG_LOGS     = 'phone-validator-for-wpf';
	const SLUG_SETTINGS = 'phone-validator-for-wpf-settings';
	const SUPPORT_URL   = 'https://t.me/advertsales';
	const FORUM_URL     = 'https://wordpress.org/support/plugin/phone-validator-for-wpforms/';
	const PER_PAGE      = 50;

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Admin|null
	 */
	private static $instance = null;

	/**
	 * Hook suffixes of our screens.
	 *
	 * @var string[]
	 */
	private $screens = array();

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Admin
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
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wpfpv_download_log', array( $this, 'download_log' ) );
		add_action( 'admin_post_wpfpv_clear_log', array( $this, 'clear_log' ) );
		add_action( 'admin_post_wpfpv_reset_honeypot', array( $this, 'reset_honeypot' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPFPV_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Register the menu.
	 */
	public function add_menu() {
		$this->screens[] = add_menu_page(
			__( 'Phone Validator', 'phone-validator-for-wpforms' ),
			__( 'Phone Validator', 'phone-validator-for-wpforms' ),
			'manage_options',
			self::SLUG_LOGS,
			array( $this, 'render_logs_page' ),
			'dashicons-phone',
			65
		);

		$this->screens[] = add_submenu_page(
			self::SLUG_LOGS,
			__( 'Submission history', 'phone-validator-for-wpforms' ),
			__( 'History', 'phone-validator-for-wpforms' ),
			'manage_options',
			self::SLUG_LOGS,
			array( $this, 'render_logs_page' )
		);

		$this->screens[] = add_submenu_page(
			self::SLUG_LOGS,
			__( 'Settings', 'phone-validator-for-wpforms' ),
			__( 'Settings', 'phone-validator-for-wpforms' ),
			'manage_options',
			self::SLUG_SETTINGS,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Link to the settings on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG_SETTINGS );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'phone-validator-for-wpforms' ) . '</a>' );
		$links[] = '<a href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Support', 'phone-validator-for-wpforms' ) . '</a>';
		return $links;
	}

	/**
	 * Support line at the bottom of the plugin screens.
	 */
	public static function render_support_footer() {
		printf(
			'<p class="wpfpv-support">%s</p>',
			wp_kses(
				sprintf(
					/* translators: 1: link to Telegram, 2: link to the support forum. */
					__( 'Found a bug or have a question? Write on Telegram: %1$s, or open a topic on the %2$s.', 'phone-validator-for-wpforms' ),
					'<a href="' . esc_url( self::SUPPORT_URL ) . '" target="_blank" rel="noopener noreferrer">@advertsales</a>',
					'<a href="' . esc_url( self::FORUM_URL ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'support forum', 'phone-validator-for-wpforms' ) . '</a>'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			)
		);
	}

	/**
	 * Styles and scripts, only on our screens.
	 *
	 * @param string $hook Hook suffix of the current screen.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, $this->screens, true ) ) {
			return;
		}

		// The file time is part of the version, so a changed file is never served from a browser cache.
		$css_version = WPFPV_VERSION . '.' . (int) filemtime( WPFPV_PLUGIN_DIR . 'admin/css/admin-style.css' );
		$js_version  = WPFPV_VERSION . '.' . (int) filemtime( WPFPV_PLUGIN_DIR . 'admin/js/admin-script.js' );

		wp_enqueue_style( 'wpfpv-admin', WPFPV_PLUGIN_URL . 'admin/css/admin-style.css', array(), $css_version );
		wp_enqueue_script( 'wpfpv-admin', WPFPV_PLUGIN_URL . 'admin/js/admin-script.js', array(), $js_version, true );

		wp_localize_script(
			'wpfpv-admin',
			'wpfpvAdmin',
			array(
				'clearConfirm' => __( 'Delete the whole history? This cannot be undone.', 'phone-validator-for-wpforms' ),
				'resetConfirm' => __( 'Reset the blocked bots counter to zero?', 'phone-validator-for-wpforms' ),
			)
		);
	}

	/**
	 * Settings screen.
	 */
	public function render_settings_page() {
		$this->authorize();
		require WPFPV_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * History screen.
	 */
	public function render_logs_page() {
		$this->authorize();
		require WPFPV_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	/**
	 * Stop when the user may not manage the plugin.
	 */
	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'phone-validator-for-wpforms' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Check the capability and the nonce of an admin_post action.
	 *
	 * @param string $action Action name, also the nonce action.
	 */
	private function authorize_action( $action ) {
		$this->authorize();
		check_admin_referer( $action );
	}

	/**
	 * Back to a screen with a notice code.
	 *
	 * @param string $slug   Screen slug.
	 * @param string $notice Notice code, see notice_message().
	 */
	private function redirect( $slug, $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => $slug,
					'wpfpv_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Notice text for a code from the query string; unknown codes show nothing.
	 *
	 * @param string $code Code.
	 * @return array{type: string, text: string}|null
	 */
	public static function notice_message( $code ) {
		$notices = array(
			'cleared'  => array( 'success', __( 'History cleared.', 'phone-validator-for-wpforms' ) ),
			'failed'   => array( 'error', __( 'The history file could not be changed. Check the permissions of the uploads folder.', 'phone-validator-for-wpforms' ) ),
			'hp_reset' => array( 'success', __( 'Blocked bots counter reset.', 'phone-validator-for-wpforms' ) ),
		);

		if ( ! isset( $notices[ $code ] ) ) {
			return null;
		}

		return array(
			'type' => $notices[ $code ][0],
			'text' => $notices[ $code ][1],
		);
	}

	/**
	 * Send the history as a text file.
	 */
	public function download_log() {
		$this->authorize_action( 'wpfpv_download_log' );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="phone-validator-' . gmdate( 'Y-m-d' ) . '.log"' );

		// Plain text file download, not HTML.
		echo WPFPV_Logger::get_contents(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Empty the history.
	 */
	public function clear_log() {
		$this->authorize_action( 'wpfpv_clear_log' );
		$this->redirect( self::SLUG_LOGS, WPFPV_Logger::clear() ? 'cleared' : 'failed' );
	}

	/**
	 * Reset the honeypot counter.
	 */
	public function reset_honeypot() {
		$this->authorize_action( 'wpfpv_reset_honeypot' );
		WPFPV_Honeypot::reset_count();
		$this->redirect( self::SLUG_SETTINGS, 'hp_reset' );
	}
}
