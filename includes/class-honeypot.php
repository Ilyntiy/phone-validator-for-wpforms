<?php
/**
 * Honeypot anti-bot field.
 *
 * An invisible field is added to every WPForms form. People never see or fill
 * it, simple bots fill every field they find. A non-empty value rejects the
 * submission. The field name is unique per site and can be changed, so bots
 * written for the default WPForms honeypot do not know it.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Honeypot field output, check and counter.
 */
class WPFPV_Honeypot {

	const COUNTER_OPTION = 'wpfpv_honeypot_blocked';

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Honeypot|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Honeypot
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
		// Inside the <form>, after the fields.
		add_action( 'wpforms_frontend_output', array( $this, 'render_field' ), 16, 1 );
		// Below the form: a note that only administrators see.
		add_action( 'wpforms_frontend_output_after', array( $this, 'render_admin_note' ), 10, 1 );
		// Before the other checks, so a bot is stopped first.
		add_action( 'wpforms_process', array( $this, 'check' ), 1, 3 );
	}

	/**
	 * Number of bots stopped by the honeypot.
	 *
	 * @return int
	 */
	public static function get_count() {
		return absint( get_option( self::COUNTER_OPTION, 0 ) );
	}

	/**
	 * Reset the counter.
	 */
	public static function reset_count() {
		update_option( self::COUNTER_OPTION, 0, false );
	}

	/**
	 * Output the hidden field.
	 *
	 * @param array $form_data Form data.
	 */
	public function render_field( $form_data ) {
		$settings = WPFPV_Settings::get( isset( $form_data['id'] ) ? $form_data['id'] : 0 );
		if ( empty( $settings['honeypot_enabled'] ) ) {
			return;
		}

		$name  = WPFPV_Settings::honeypot_name();
		$id    = $name . '-' . ( isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0 );
		$style = 'position:absolute!important;left:-9999px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;';

		// Out of sight, out of the tab order, ignored by assistive technology and autofill.
		// The label is a lure that looks like a normal optional field: a bot must not be told to skip it.
		// It is not a phone or name label, so browser autofill does not put real data into it.
		printf(
			'<div class="wpfpv-hp" aria-hidden="true" style="%1$s"><label for="%2$s">%3$s</label><input type="text" id="%2$s" name="%4$s" value="" tabindex="-1" autocomplete="off" data-lpignore="true" data-1p-ignore="true" data-form-type="other" /></div>',
			esc_attr( $style ),
			esc_attr( $id ),
			esc_html__( 'Website', 'phone-validator-for-wpforms' ),
			esc_attr( $name )
		);
	}

	/**
	 * Note for logged-in administrators below the form.
	 *
	 * @param array $form_data Form data.
	 */
	public function render_admin_note( $form_data ) {
		$settings = WPFPV_Settings::get( isset( $form_data['id'] ) ? $form_data['id'] : 0 );
		if ( empty( $settings['honeypot_enabled'] ) || empty( $settings['honeypot_indicator'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url   = admin_url( 'admin.php?page=phone-validator-for-wpf-settings' );
		$style = 'margin:8px 0;padding:8px 12px;border-left:4px solid #d97706;background:#fff7ed;color:#7c2d12;font-size:13px;line-height:1.5;';

		// Inline and important: site themes often recolor links, which can make this one unreadable.
		$link_style = 'color:#1d4ed8!important;text-decoration:underline!important;font-weight:600!important;';

		printf(
			'<div class="wpfpv-admin-note" style="%1$s"><strong>%2$s</strong> %3$s <a href="%4$s" style="%6$s">%5$s</a></div>',
			esc_attr( $style ),
			esc_html__( 'Phone Validator:', 'phone-validator-for-wpforms' ),
			esc_html__( 'this form is protected by a honeypot. Only logged-in administrators see this note; it is not shown to visitors. You can turn it off in the plugin settings.', 'phone-validator-for-wpforms' ),
			esc_url( $url ),
			esc_html__( 'Open settings', 'phone-validator-for-wpforms' ),
			esc_attr( $link_style )
		);
	}

	/**
	 * WPForms hook: reject the submission when the honeypot field is filled.
	 *
	 * @param array $fields    Sanitized fields.
	 * @param array $entry     Submitted data.
	 * @param array $form_data Form data.
	 */
	public function check( $fields, $entry, $form_data ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$settings = WPFPV_Settings::get( $form_id );
		if ( empty( $settings['honeypot_enabled'] ) ) {
			return;
		}

		$name = WPFPV_Settings::honeypot_name();

		// WPForms submissions carry no nonce (pages are cached), the value is only tested for emptiness.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST[ $name ] ) ) {
			$value = wp_unslash( $_POST[ $name ] );
		} elseif ( is_array( $entry ) && isset( $entry[ $name ] ) ) {
			$value = $entry[ $name ];
		} else {
			return;
		}
		// phpcs:enable

		if ( ! is_array( $value ) && '' === trim( (string) $value ) ) {
			return;
		}

		wpforms()->process->errors[ $form_id ]['header'] = __( 'Your submission could not be processed.', 'phone-validator-for-wpforms' );

		update_option( self::COUNTER_OPTION, self::get_count() + 1, false );

		WPFPV_Logger::log(
			'HONEYPOT',
			array(
				'form'    => $form_id,
				'message' => __( 'Honeypot field was filled', 'phone-validator-for-wpforms' ),
			)
		);
	}
}
