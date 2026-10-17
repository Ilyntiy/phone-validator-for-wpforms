<?php
/**
 * Phone number validation.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates WPForms phone fields.
 */
class WPFPV_Validator {

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Validator|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Validator
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
		add_action( 'wpforms_process_validate_phone', array( $this, 'validate_phone' ), 10, 3 );
	}

	/**
	 * WPForms hook: validate one phone field.
	 *
	 * @param int          $field_id     Field ID.
	 * @param string|array $field_submit Submitted value.
	 * @param array        $form_data    Form data.
	 */
	public function validate_phone( $field_id, $field_submit, $form_data ) {
		if ( ! is_scalar( $field_submit ) ) {
			return;
		}

		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$settings = WPFPV_Settings::get( $form_id );
		$result   = self::evaluate( (string) $field_submit, $settings );

		if ( null === $result ) {
			return;
		}

		wpforms()->process->errors[ $form_id ][ $field_id ] = $result['message'];

		WPFPV_Logger::log(
			'VALIDATION',
			array(
				'form'    => $form_id,
				'field'   => $field_id,
				'value'   => (string) $field_submit,
				'message' => $result['message'],
			)
		);
	}

	/**
	 * Check a number against all enabled rules.
	 *
	 * @param string $raw      Number as typed by the visitor.
	 * @param array  $settings Plugin settings.
	 * @return array{code: string, message: string}|null Null when the number is acceptable.
	 */
	public static function evaluate( $raw, array $settings ) {
		$raw = trim( (string) $raw );

		// With the country block switched off the number is checked as typed.
		if ( isset( $settings['countries_enabled'] ) && empty( $settings['countries_enabled'] ) ) {
			$settings['default_country']       = '';
			$settings['custom_rules']          = '';
			$settings['allowed_country_codes'] = '';
		}

		// An empty value is handled by the "required" option of the field itself.
		if ( '' === $raw ) {
			return null;
		}

		$parsed = WPFPV_Phone::parse( $raw, $settings );

		// Digit strings that are inspected by the pattern checks.
		$candidates = array_values( array_unique( array( $parsed['digits'], $parsed['e164'] ) ) );

		// 1. Mask.
		if ( ! empty( $settings['mask_enabled'] ) ) {
			$masks = WPFPV_Mask::parse( $settings['masks'] );
			if ( ! empty( $masks['masks'] ) && ! WPFPV_Mask::matches_any( $candidates, $masks['masks'] ) ) {
				return self::error(
					'mask',
					sprintf(
						/* translators: %s: allowed phone number format(s), for example "+1 *** *** ****". */
						__( 'Please enter the number in the format: %s', 'phone-validator-for-wpforms' ),
						WPFPV_Mask::describe( $masks['masks'] )
					)
				);
			}
		}

		// 2. Length (full international number when the country is known).
		$length = strlen( $parsed['e164'] );
		if ( $length < (int) $settings['min_length'] ) {
			return self::error(
				'min_length',
				sprintf(
					/* translators: %d: minimum number of digits. */
					__( 'The number must contain at least %d digits', 'phone-validator-for-wpforms' ),
					(int) $settings['min_length']
				)
			);
		}
		if ( $length > (int) $settings['max_length'] ) {
			return self::error(
				'max_length',
				sprintf(
					/* translators: %d: maximum number of digits. */
					__( 'The number cannot contain more than %d digits', 'phone-validator-for-wpforms' ),
					(int) $settings['max_length']
				)
			);
		}

		// 3. Identical digits in a row.
		$max_repeats = (int) $settings['max_repeats'];
		if ( $max_repeats > 0 ) {
			foreach ( $candidates as $digits ) {
				if ( 1 === preg_match( '/(\d)\1{' . $max_repeats . ',}/', $digits ) ) {
					return self::error( 'repeats', __( 'The number contains too many identical digits in a row', 'phone-validator-for-wpforms' ) );
				}
			}
		}

		// 4. Ascending or descending sequences (1234567, 7654321).
		$sequence = (int) $settings['sequence_length'];
		if ( $sequence > 0 ) {
			foreach ( $candidates as $digits ) {
				if ( self::has_sequence( $digits, $sequence ) ) {
					return self::error( 'sequence', __( 'The number contains a sequence of consecutive digits', 'phone-validator-for-wpforms' ) );
				}
			}
		}

		// 5. Blacklist.
		if ( '' !== trim( (string) $settings['blacklist'] ) && self::is_blacklisted( $parsed, $settings ) ) {
			return self::error( 'blacklist', __( 'This number is blacklisted', 'phone-validator-for-wpforms' ) );
		}

		// 6. Allowed country codes.
		$allowed = self::parse_codes( $settings['allowed_country_codes'] );
		if ( ! empty( $allowed ) && ! self::has_allowed_code( $parsed['e164'], $allowed ) ) {
			return self::error(
				'country',
				sprintf(
					/* translators: %s: list of allowed country calling codes, for example "+1, +44". */
					__( 'Only numbers with the following codes are allowed: %s', 'phone-validator-for-wpforms' ),
					'+' . implode( ', +', $allowed )
				)
			);
		}

		return null;
	}

	/**
	 * Build an error result.
	 *
	 * @param string $code    Machine readable code.
	 * @param string $message Message shown to the visitor.
	 * @return array{code: string, message: string}
	 */
	private static function error( $code, $message ) {
		return array(
			'code'    => $code,
			'message' => $message,
		);
	}

	/**
	 * Does the digit string contain an ascending or descending run of $min digits?
	 *
	 * @param string $digits Digits.
	 * @param int    $min    Minimum run length.
	 * @return bool
	 */
	public static function has_sequence( $digits, $min ) {
		$length = strlen( $digits );
		if ( $min < 3 || $length < $min ) {
			return false;
		}

		$asc  = 1;
		$desc = 1;
		for ( $i = 1; $i < $length; $i++ ) {
			$diff = (int) $digits[ $i ] - (int) $digits[ $i - 1 ];
			$asc  = ( 1 === $diff ) ? $asc + 1 : 1;
			$desc = ( -1 === $diff ) ? $desc + 1 : 1;
			if ( $asc >= $min || $desc >= $min ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is the number in the blacklist? Entries are normalized like submitted numbers.
	 *
	 * @param array $parsed   Result of WPFPV_Phone::parse().
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	private static function is_blacklisted( array $parsed, array $settings ) {
		foreach ( preg_split( '/\R/', (string) $settings['blacklist'] ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$entry = WPFPV_Phone::parse( $line, $settings );
			if ( '' === $entry['digits'] ) {
				continue;
			}

			$typed = array( $parsed['digits'], $parsed['e164'] );
			if ( in_array( $entry['digits'], $typed, true ) || in_array( $entry['e164'], $typed, true ) ) {
				return true;
			}

			// An entry without a country code also matches the national part of a number.
			if ( '' === $entry['cc'] && '' !== $parsed['cc'] && $entry['digits'] === $parsed['national'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Parse the allowed country codes setting ("+1, +44") into digit strings.
	 *
	 * @param string $text Comma, space or line separated codes.
	 * @return string[]
	 */
	public static function parse_codes( $text ) {
		$codes = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $text ) as $item ) {
			$digits = preg_replace( '/\D/', '', $item );
			if ( '' !== $digits && strlen( $digits ) <= 6 ) {
				$codes[] = $digits;
			}
		}
		return array_values( array_unique( $codes ) );
	}

	/**
	 * Does the international number start with one of the allowed codes?
	 *
	 * @param string   $e164    Digits of the international number.
	 * @param string[] $allowed Allowed codes (digits).
	 * @return bool
	 */
	private static function has_allowed_code( $e164, array $allowed ) {
		foreach ( $allowed as $code ) {
			if ( 0 === strncmp( $e164, $code, strlen( $code ) ) ) {
				return true;
			}
		}
		return false;
	}
}
