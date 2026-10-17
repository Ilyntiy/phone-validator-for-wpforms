<?php
/**
 * Front-end assets: the phone input mask.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads the mask script on pages that contain a WPForms form.
 */
class WPFPV_Frontend {

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Frontend|null
	 */
	private static $instance = null;

	/**
	 * Has the script already been queued on this page?
	 *
	 * @var bool
	 */
	private $queued = false;

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
	 * Enqueue the mask script.
	 *
	 * @param array $form_data Form data.
	 */
	public function enqueue( $form_data ) {
		if ( $this->queued ) {
			return;
		}

		$settings = WPFPV_Settings::get( isset( $form_data['id'] ) ? $form_data['id'] : 0 );
		if ( empty( $settings['mask_enabled'] ) ) {
			return;
		}

		$parsed = WPFPV_Mask::parse( $settings['masks'] );
		if ( empty( $parsed['masks'] ) ) {
			return;
		}

		$this->queued = true;

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
}
