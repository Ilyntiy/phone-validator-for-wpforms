<?php
/**
 * Front-end assets: the phone input mask and the browser check.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the scripts on pages that contain a WPForms form.
 */
class WPFPV_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Has the mask script already been queued on this page?
	 *
	 * @var bool
	 */
	private $mask_queued = false;

	/**
	 * Has the browser-check script already been queued on this page?
	 *
	 * @var bool
	 */
	private $guard_queued = false;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Frontend
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
		// Fires while a form is printed, so the script is only loaded where a form exists.
		add_action( 'wpforms_frontend_output', array( $this, 'enqueue' ), 5, 1 );
	}

	/**
	 * Enqueue the scripts the form needs.
	 *
	 * @param array $form_data Form data.
	 */
	public function enqueue( $form_data ) {
		$settings = WPFPV_Settings::get( isset( $form_data['id'] ) ? $form_data['id'] : 0 );

		$this->enqueue_mask( $settings );
		$this->enqueue_guard( $settings );
	}

	/**
	 * Enqueue the mask script.
	 *
	 * @param array $settings Settings of the form.
	 */
	private function enqueue_mask( array $settings ) {
		if ( $this->mask_queued || empty( $settings['mask_enabled'] ) ) {
			return;
		}

		$parsed = WPFPV_Mask::parse( $settings['masks'] );
		if ( empty( $parsed['masks'] ) ) {
			return;
		}

		$this->mask_queued = true;

		wp_enqueue_script(
			'wpfpv-mask',
			WPFPV_PLUGIN_URL . 'assets/js/mask.js',
			array(),
			WPFPV_VERSION,
			true
		);

		$config = array(
			'masks' => wp_list_pluck( $parsed['masks'], 'mask' ),
			'rules' => WPFPV_Phone::rules_for_js( $settings ),
		);

		wp_add_inline_script(
			'wpfpv-mask',
			'window.wpfpvMaskConfig = ' . wp_json_encode( $config ) . ';',
			'before'
		);
	}

	/**
	 * Enqueue the browser-check script.
	 *
	 * @param array $settings Settings of the form.
	 */
	private function enqueue_guard( array $settings ) {
		if ( $this->guard_queued || empty( $settings['honeypot_enabled'] ) ) {
			return;
		}

		$this->guard_queued = true;

		wp_enqueue_script(
			'wpfpv-guard',
			WPFPV_PLUGIN_URL . 'assets/js/guard.js',
			array(),
			WPFPV_VERSION,
			true
		);

		wp_add_inline_script(
			'wpfpv-guard',
			'window.wpfpvGuardConfig = ' . wp_json_encode( array( 'name' => WPFPV_Settings::guard_name() ) ) . ';',
			'before'
		);
	}
}
