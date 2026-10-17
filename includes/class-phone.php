<?php
/**
 * Phone number normalization (country table and custom rules).
 *
 * The result is used for checks only (length, country code, blacklist, mask).
 * The value stored by WPForms is never changed.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses a phone number into digits, calling code and national part.
 */
class WPFPV_Phone {

	/**
	 * Country table cache.
	 *
	 * @var array|null
	 */
	private static $countries = null;

	/**
	 * Calling code index cache.
	 *
	 * @var array|null
	 */
	private static $codes = null;

	/**
	 * Country table: ISO => array( name, calling code, national prefix, parsing pattern, lengths ).
	 *
	 * @return array
	 */
	public static function countries() {
		if ( null === self::$countries ) {
			self::$countries = (array) include WPFPV_PLUGIN_DIR . 'includes/data/countries.php';
		}
		return self::$countries;
	}

	/**
	 * Index by calling code: code => array( 'regions' => ISO[], 'lengths' => int[] ).
	 *
	 * @return array
	 */
	private static function codes() {
		if ( null === self::$codes ) {
			self::$codes = array();
			foreach ( self::countries() as $iso => $row ) {
				$code = (string) $row[1];
				if ( ! isset( self::$codes[ $code ] ) ) {
					self::$codes[ $code ] = array(
						'regions' => array(),
						'lengths' => array(),
					);
				}
				self::$codes[ $code ]['regions'][] = $iso;
				self::$codes[ $code ]['lengths']   = array_values( array_unique( array_merge( self::$codes[ $code ]['lengths'], $row[4] ) ) );
			}
		}
		return self::$codes;
	}

	/**
	 * Country options for a select: ISO => "Name (+code)", sorted by name.
	 *
	 * @return string[]
	 */
	public static function country_options() {
		$options = array();
		foreach ( self::countries() as $iso => $row ) {
			$options[ $iso ] = self::country_name( $iso ) . ' (+' . $row[1] . ')';
		}
		asort( $options, SORT_NATURAL | SORT_FLAG_CASE );
		return $options;
	}

	/**
	 * Localized country name when PHP intl is available, English otherwise.
	 *
	 * @param string $iso ISO 3166-1 alpha-2 code.
	 * @return string
	 */
	public static function country_name( $iso ) {
		$countries = self::countries();
		if ( ! isset( $countries[ $iso ] ) ) {
			return '';
		}

		if ( class_exists( 'Locale' ) && function_exists( 'get_locale' ) ) {
			$name = Locale::getDisplayRegion( '-' . $iso, str_replace( '_', '-', get_locale() ) );
			if ( is_string( $name ) && '' !== $name && $name !== $iso ) {
				return $name;
			}
		}

		return $countries[ $iso ][0];
	}

	/**
	 * Guess the default country from a WordPress locale such as "ru_RU".
	 *
	 * @param string $locale Locale.
	 * @return string ISO code or empty string.
	 */
	public static function country_from_locale( $locale ) {
		if ( preg_match( '/^[a-z]{2,3}_([A-Z]{2})/', (string) $locale, $matches ) && isset( self::countries()[ $matches[1] ] ) ) {
			return $matches[1];
		}
		return '';
	}

	/**
	 * Parse a number.
	 *
	 * @param string $raw      Number as typed by the visitor.
	 * @param array  $settings Plugin settings (default_country, custom_rules).
	 * @return array {
	 *     @type string $digits   All digits as typed.
	 *     @type string $e164     Full international digits when the country code is known, otherwise as typed.
	 *     @type string $cc       Calling code, or empty when unknown.
	 *     @type string $national Number without calling code (equals $digits when $cc is unknown).
	 * }
	 */
	public static function parse( $raw, array $settings = array() ) {
		$raw    = trim( (string) $raw );
		$digits = preg_replace( '/\D/', '', $raw );
		$digits = null === $digits ? '' : $digits;

		$unknown = array(
			'digits'   => $digits,
			'e164'     => $digits,
			'cc'       => '',
			'national' => $digits,
		);

		if ( '' === $digits ) {
			return $unknown;
		}

		// Explicit international format: +CC...
		if ( 0 === strpos( $raw, '+' ) ) {
			return self::from_international( $digits, $digits );
		}

		// International dial prefix 00CC...
		if ( 0 === strncmp( $digits, '00', 2 ) && strlen( $digits ) > 2 ) {
			return self::from_international( $digits, substr( $digits, 2 ) );
		}

		// Administrator rules ("07 => +447") take precedence over the country table.
		$rules = self::parse_rules( isset( $settings['custom_rules'] ) ? $settings['custom_rules'] : '' );
		foreach ( $rules['rules'] as $rule ) {
			if ( 0 === strncmp( $digits, $rule[0], strlen( $rule[0] ) ) ) {
				return self::from_international( $digits, $rule[1] . substr( $digits, strlen( $rule[0] ) ) );
			}
		}

		// Default country: national format typed without "+".
		$default   = isset( $settings['default_country'] ) ? $settings['default_country'] : '';
		$countries = self::countries();
		if ( '' !== $default && isset( $countries[ $default ] ) ) {
			list( , $code, $trunk, $pattern, $lengths ) = $countries[ $default ];

			$code = (string) $code;

			// Already contains the country code without "+" (e.g. 447700900123).
			if ( 0 === strncmp( $digits, $code, strlen( $code ) ) && in_array( strlen( $digits ) - strlen( $code ), $lengths, true ) ) {
				return self::build( $digits, $code, substr( $digits, strlen( $code ) ) );
			}

			// National prefix present (e.g. 0 7700900123).
			$stripped = self::strip_prefix( $digits, $trunk, $pattern );
			if ( null !== $stripped && in_array( strlen( $stripped ), $lengths, true ) ) {
				return self::build( $digits, $code, $stripped );
			}

			// National number without prefix.
			if ( in_array( strlen( $digits ), $lengths, true ) ) {
				return self::build( $digits, $code, $digits );
			}
		}

		// No default country: recognise a calling code typed without "+" when the length fits.
		$detected = self::split_code( $digits );
		if ( null !== $detected && self::valid_length( $detected[0], strlen( $detected[1] ) ) ) {
			return self::build( $digits, $detected[0], $detected[1] );
		}

		return $unknown;
	}

	/**
	 * Build the result array.
	 *
	 * @param string $digits   Digits as typed.
	 * @param string $cc       Calling code.
	 * @param string $national National part.
	 * @return array
	 */
	private static function build( $digits, $cc, $national ) {
		return array(
			'digits'   => $digits,
			'e164'     => $cc . $national,
			'cc'       => $cc,
			'national' => $national,
		);
	}

	/**
	 * Parse digits that start with a calling code.
	 *
	 * @param string $typed Digits as typed.
	 * @param string $intl  Digits starting with the calling code.
	 * @return array
	 */
	private static function from_international( $typed, $intl ) {
		$split = self::split_code( $intl );
		if ( null === $split ) {
			return array(
				'digits'   => $typed,
				'e164'     => $intl,
				'cc'       => '',
				'national' => $intl,
			);
		}

		list( $cc, $national ) = $split;

		// "+44 (0) 20 7946 0958": drop a trunk prefix that was typed after the country code.
		if ( ! self::valid_length( $cc, strlen( $national ) ) ) {
			$countries = self::countries();
			$regions   = self::codes()[ $cc ]['regions'];
			foreach ( $regions as $iso ) {
				$stripped = self::strip_prefix( $national, $countries[ $iso ][2], $countries[ $iso ][3] );
				if ( null !== $stripped && self::valid_length( $cc, strlen( $stripped ) ) ) {
					$national = $stripped;
					break;
				}
			}
		}

		return self::build( $typed, $cc, $national );
	}

	/**
	 * Split digits into calling code and the rest. Calling codes are prefix-free.
	 *
	 * @param string $digits Digits starting with a calling code.
	 * @return array|null array( code, rest ) or null when no known calling code.
	 */
	private static function split_code( $digits ) {
		$codes = self::codes();
		for ( $length = 1; $length <= 3; $length++ ) {
			$code = substr( $digits, 0, $length );
			if ( strlen( $code ) === $length && isset( $codes[ $code ] ) ) {
				return array( $code, (string) substr( $digits, $length ) );
			}
		}
		return null;
	}

	/**
	 * Is the national number length plausible for any region of the calling code?
	 *
	 * @param string $cc     Calling code.
	 * @param int    $length National number length.
	 * @return bool
	 */
	private static function valid_length( $cc, $length ) {
		$codes = self::codes();
		return isset( $codes[ $cc ] ) && in_array( $length, $codes[ $cc ]['lengths'], true );
	}

	/**
	 * Remove the national (trunk) prefix.
	 *
	 * @param string $digits  National digits possibly starting with the prefix.
	 * @param string $trunk   Plain national prefix.
	 * @param string $pattern Optional pattern made of digits, "|" and "?".
	 * @return string|null Digits without prefix, or null when there is no prefix.
	 */
	private static function strip_prefix( $digits, $trunk, $pattern ) {
		if ( '' !== $pattern && 1 === preg_match( '/^(?:' . $pattern . ')/', $digits, $match ) && '' !== $match[0] ) {
			return (string) substr( $digits, strlen( $match[0] ) );
		}
		if ( '' !== $trunk && 0 === strncmp( $digits, $trunk, strlen( $trunk ) ) ) {
			return (string) substr( $digits, strlen( $trunk ) );
		}
		return null;
	}

	/**
	 * Parse the custom rules textarea ("07 => +447", one per line).
	 *
	 * @param string $text Raw text.
	 * @return array{rules: array<int, array{0: string, 1: string}>, invalid: string[]}
	 */
	public static function parse_rules( $text ) {
		$rules   = array();
		$invalid = array();

		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			if ( 1 === preg_match( '/^\+?(\d{1,6})\s*(?:=>|->|=|:)\s*\+?(\d{1,6})$/', $line, $match ) ) {
				$rules[] = array( $match[1], $match[2] );
			} else {
				$invalid[] = $line;
			}
		}

		return array(
			'rules'   => $rules,
			'invalid' => $invalid,
		);
	}

	/**
	 * Rules that the browser mask should apply while typing: custom rules first,
	 * then the national prefix of the default country.
	 *
	 * @param array $settings Plugin settings.
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function rules_for_js( array $settings ) {
		if ( isset( $settings['countries_enabled'] ) && empty( $settings['countries_enabled'] ) ) {
			return array();
		}

		$parsed    = self::parse_rules( isset( $settings['custom_rules'] ) ? $settings['custom_rules'] : '' );
		$rules     = $parsed['rules'];
		$default   = isset( $settings['default_country'] ) ? $settings['default_country'] : '';
		$countries = self::countries();

		if ( '' !== $default && isset( $countries[ $default ] ) && '' !== $countries[ $default ][2] && (string) $countries[ $default ][1] !== $countries[ $default ][2] ) {
			$rules[] = array( $countries[ $default ][2], (string) $countries[ $default ][1] );
		}

		return $rules;
	}
}
