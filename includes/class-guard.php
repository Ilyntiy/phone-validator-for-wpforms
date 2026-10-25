<?php
/**
 * Browser check: a hidden field that only a real browser adds.
 *
 * A small script adds the field to every WPForms form while the page runs in the
 * visitor's browser. Simple bots send the form without running scripts and
 * never have the field. The value carries the time the form was loaded and the
 * time it was sent, so a submission that is far too fast can be rejected too.
 * Everything is decided on the server.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Browser check on the server side.
 */
class WPFPV_Guard {

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Guard|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Guard
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
		// At the very start of the processing, right after the honeypot: a rejected
		// submission then never reaches the checks that store entries as spam.
		add_action( 'wpforms_process_before', array( $this, 'check' ), 2, 2 );
	}

	/**
	 * Judge the value of the browser-check field.
	 *
	 * The value is "<load>-<send>", both times in seconds since 1970 written in base 36.
	 *
	 * @param mixed $raw         Submitted value, null when the field is missing.
	 * @param int   $min_seconds Minimum seconds between load and send, 0 turns the check off.
	 * @return string One of: ok, missing, tampered, fast.
	 */
	public static function evaluate( $raw, $min_seconds = 0 ) {
		if ( null === $raw ) {
			return 'missing';
		}

		if ( ! is_string( $raw ) || 1 !== preg_match( '/^([0-9a-z]{1,8})-([0-9a-z]{1,8})$/', $raw, $match ) ) {
			return '' === $raw ? 'missing' : 'tampered';
		}

		$loaded = (int) base_convert( $match[1], 36, 10 );
		$sent   = (int) base_convert( $match[2], 36, 10 );

		// Times before the year 2001 or a send before the load cannot come from the script.
		if ( $loaded < 1000000000 || $sent < $loaded ) {
			return 'tampered';
		}

		if ( $min_seconds > 0 && ( $sent - $loaded ) < $min_seconds ) {
			return 'fast';
		}

		return 'ok';
	}

	/**
	 * WPForms hook: reject a submission that has no valid browser check.
	 *
	 * @param array $entry     Submitted data.
	 * @param array $form_data Form data.
	 */
	public function check( $entry, $form_data ) {
		// AMP verification requests expect field errors only and carry no script.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( isset( $_POST['__amp_form_verify'] ) ) {
			return;
		}

		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$settings = WPFPV_Settings::get( $form_id );
		if ( empty( $settings['honeypot_enabled'] ) ) {
			return;
		}

		// Another check has already rejected the submission.
		if ( ! empty( wpforms()->process->errors[ $form_id ] ) ) {
			return;
		}

		$name = WPFPV_Settings::guard_name();

		// WPForms submissions carry no nonce (pages are cached), the value is only parsed.
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $_POST[ $name ] ) ) {
			$raw = wp_unslash( $_POST[ $name ] );
		} elseif ( is_array( $entry ) && isset( $entry[ $name ] ) ) {
			$raw = $entry[ $name ];
		} else {
			$raw = null;
		}
		// phpcs:enable

		/**
		 * Minimum seconds between loading and sending the form. 0 (default) turns the check off.
		 * Not an option in the settings: browser autofill can send a form within a second or two.
		 *
		 * @param int $seconds Minimum seconds.
		 * @param int $form_id Form ID.
		 */
		$min_seconds = max( 0, (int) apply_filters( 'wpfpv_guard_min_seconds', 0, $form_id ) );

		$status = self::evaluate( $raw, $min_seconds );

		if ( 'ok' === $status ) {
			return;
		}

		if ( 'missing' === $status ) {
			if ( empty( $settings['guard_required'] ) ) {
				return;
			}

			$this->reject(
				$form_id,
				'NOJS',
				__( 'Please turn on JavaScript in your browser and send the form again.', 'phone-validator-for-wpforms' ),
				__( 'Browser check is missing', 'phone-validator-for-wpforms' ),
				false
			);
			return;
		}

		if ( 'fast' === $status ) {
			$this->reject(
				$form_id,
				'BOTCHECK',
				__( 'Please wait a moment and send the form again.', 'phone-validator-for-wpforms' ),
				__( 'Form was sent too fast', 'phone-validator-for-wpforms' ),
				true
			);
			return;
		}

		$this->reject(
			$form_id,
			'BOTCHECK',
			__( 'Your submission could not be processed.', 'phone-validator-for-wpforms' ),
			__( 'Browser check value is not valid', 'phone-validator-for-wpforms' ),
			true
		);
	}

	/**
	 * Reject the submission, write the history and count a bot when it is one.
	 *
	 * @param int    $form_id Form ID.
	 * @param string $type    History entry type.
	 * @param string $notice  Message for the visitor.
	 * @param string $note    Message for the history.
	 * @param bool   $is_bot  Count it in the blocked bots counter.
	 */
	private function reject( $form_id, $type, $notice, $note, $is_bot ) {
		wpforms()->process->errors[ $form_id ]['header'] = $notice;

		if ( $is_bot ) {
			WPFPV_Honeypot::count_bot();
		}

		WPFPV_Logger::log(
			$type,
			array(
				'form'    => $form_id,
				'message' => $note,
			)
		);
	}
}
