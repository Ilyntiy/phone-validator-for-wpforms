<?php
/**
 * Phone number masks, e.g. "+1 *** *** ****".
 *
 * "*" stands for any digit, digits are fixed, everything else is formatting.
 * The browser script formats the input while typing; this class re-checks the
 * submitted value on the server, because a browser-side mask can be bypassed.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mask parsing and matching.
 */
class WPFPV_Mask {

	const MIN_POSITIONS = 5;
	const MAX_POSITIONS = 20;

	/**
	 * Parse a textarea with one mask per line.
	 *
	 * @param string $text Raw text.
	 * @return array{masks: array<int, array{mask: string, pattern: string}>, invalid: string[]}
	 */
	public static function parse( $text ) {
		$masks   = array();
		$invalid = array();

		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$mask = self::parse_line( $line );
			if ( null === $mask ) {
				$invalid[] = $line;
			} else {
				$masks[ $mask['pattern'] . '|' . $mask['mask'] ] = $mask;
			}
		}

		return array(
			'masks'   => array_values( $masks ),
			'invalid' => $invalid,
		);
	}

	/**
	 * Parse one mask line.
	 *
	 * @param string $line Mask such as "+1 *** *** ****".
	 * @return array{mask: string, pattern: string}|null
	 */
	private static function parse_line( $line ) {
		if ( 1 !== preg_match( '/^[0-9*+\s().\-]+$/', $line ) ) {
			return null;
		}

		// Digit positions only: fixed digits and "*" wildcards, in order.
		$pattern = preg_replace( '/[^0-9*]/', '', $line );
		$length  = strlen( $pattern );

		if ( $length < self::MIN_POSITIONS || $length > self::MAX_POSITIONS || false === strpos( $pattern, '*' ) ) {
			return null;
		}

		return array(
			'mask'    => preg_replace( '/\s+/', ' ', $line ),
			'pattern' => $pattern,
		);
	}

	/**
	 * Does any candidate digit string fit any mask?
	 *
	 * @param string[] $candidates Digit strings (as typed, normalized...).
	 * @param array    $masks      Masks from parse().
	 * @return bool
	 */
	public static function matches_any( array $candidates, array $masks ) {
		foreach ( $masks as $mask ) {
			foreach ( $candidates as $digits ) {
				if ( self::matches( $digits, $mask['pattern'] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Match digits against a pattern of fixed digits and "*" wildcards.
	 *
	 * @param string $digits  Digits only.
	 * @param string $pattern Pattern such as "7**********".
	 * @return bool
	 */
	public static function matches( $digits, $pattern ) {
		if ( strlen( $digits ) !== strlen( $pattern ) ) {
			return false;
		}

		for ( $i = 0, $n = strlen( $pattern ); $i < $n; $i++ ) {
			if ( '*' !== $pattern[ $i ] && $pattern[ $i ] !== $digits[ $i ] ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Human readable list of masks for error messages.
	 *
	 * @param array $masks Masks from parse().
	 * @return string
	 */
	public static function describe( array $masks ) {
		$list = array();
		foreach ( $masks as $mask ) {
			$list[] = $mask['mask'];
		}
		return implode( ' / ', $list );
	}
}
