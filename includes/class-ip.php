<?php
/**
 * Client IP detection and CIDR helpers.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the visitor IP address, honouring reverse proxies only when the
 * administrator explicitly trusts them.
 */
class WPFPV_IP {

	/**
	 * Cloudflare edge ranges, https://www.cloudflare.com/ips/ (as of plugin release).
	 * Filterable with `wpfpv_cloudflare_ranges`.
	 */
	const CLOUDFLARE_RANGES = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Headers that may carry the client address when behind a trusted proxy.
	 *
	 * @return string[]
	 */
	public static function allowed_headers() {
		return array( 'X-Forwarded-For', 'X-Real-IP', 'True-Client-IP' );
	}

	/**
	 * Get the visitor IP using current settings.
	 *
	 * @param array|null $settings Plugin settings (defaults to saved settings).
	 * @return string Normalized IP, or empty string when unknown.
	 */
	public static function get( $settings = null ) {
		if ( null === $settings ) {
			$settings = WPFPV_Settings::get();
		}

		$remote = '';
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$remote = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$mode   = isset( $settings['proxy_mode'] ) ? $settings['proxy_mode'] : 'none';
		$header = '';
		$list   = array();

		if ( 'cloudflare' === $mode ) {
			$header = 'CF-Connecting-IP';
			$list   = (array) apply_filters( 'wpfpv_cloudflare_ranges', self::CLOUDFLARE_RANGES );
		} elseif ( 'custom' === $mode ) {
			$header = isset( $settings['proxy_header'] ) ? $settings['proxy_header'] : 'X-Forwarded-For';
			$parsed = self::parse_list( isset( $settings['trusted_proxies'] ) ? $settings['trusted_proxies'] : '' );
			$list   = $parsed['valid'];
		}

		$value = '';
		if ( '' !== $header ) {
			$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $header ) );
			if ( isset( $_SERVER[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			}
		}

		return self::resolve( $remote, $value, $mode, $header, $list );
	}

	/**
	 * Pure resolver (no globals), used by get() and by unit tests.
	 *
	 * @param string   $remote REMOTE_ADDR value.
	 * @param string   $value  Value of the proxy header, if any.
	 * @param string   $mode   none|cloudflare|custom.
	 * @param string   $header Header name used for $value.
	 * @param string[] $trusted Trusted proxy ranges (IPs or CIDRs).
	 * @return string
	 */
	public static function resolve( $remote, $value, $mode, $header, array $trusted ) {
		$remote = self::normalize( $remote );
		if ( '' === $remote ) {
			return '';
		}

		if ( 'none' === $mode || '' === $value || ! self::in_ranges( $remote, $trusted ) ) {
			// The header is only honoured when the request really came from a trusted proxy.
			return $remote;
		}

		if ( 'X-Forwarded-For' !== $header ) {
			$client = self::normalize( trim( $value ) );
			return '' !== $client ? $client : $remote;
		}

		// X-Forwarded-For: walk the chain from the right, skipping trusted proxies.
		$chain = array_reverse( array_map( 'trim', explode( ',', $value ) ) );
		foreach ( $chain as $candidate ) {
			$candidate = self::normalize( $candidate );
			if ( '' === $candidate ) {
				return $remote;
			}
			if ( ! self::in_ranges( $candidate, $trusted ) ) {
				return $candidate;
			}
		}

		return $remote;
	}

	/**
	 * Validate and normalize an IP address (IPv4-mapped IPv6 becomes IPv4).
	 *
	 * @param string $ip Raw address.
	 * @return string Normalized address or empty string when invalid.
	 */
	public static function normalize( $ip ) {
		$ip = trim( (string) $ip );
		if ( '' === $ip || false === filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return '';
		}

		$packed = inet_pton( $ip );
		if ( false === $packed ) {
			return '';
		}

		// IPv4-mapped IPv6 address (::ffff:a.b.c.d).
		if ( 16 === strlen( $packed ) && 0 === strncmp( $packed, str_repeat( "\0", 10 ) . "\xff\xff", 12 ) ) {
			$packed = substr( $packed, 12 );
		}

		$normalized = inet_ntop( $packed );
		return false === $normalized ? '' : $normalized;
	}

	/**
	 * Is the address inside any of the given ranges?
	 *
	 * @param string   $ip     Address.
	 * @param string[] $ranges IPs or CIDR blocks.
	 * @return bool
	 */
	public static function in_ranges( $ip, array $ranges ) {
		foreach ( $ranges as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the address inside the given IP or CIDR block?
	 *
	 * @param string $ip   Address.
	 * @param string $cidr IP or CIDR block.
	 * @return bool
	 */
	public static function in_range( $ip, $cidr ) {
		$ip = self::normalize( $ip );
		if ( '' === $ip ) {
			return false;
		}

		$parts = explode( '/', trim( (string) $cidr ), 2 );
		$net   = self::normalize( $parts[0] );
		if ( '' === $net ) {
			return false;
		}

		$ip_bin  = inet_pton( $ip );
		$net_bin = inet_pton( $net );
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}

		$max_bits = strlen( $net_bin ) * 8;
		$bits     = $max_bits;
		if ( isset( $parts[1] ) ) {
			if ( '' === $parts[1] || ! ctype_digit( $parts[1] ) ) {
				return false;
			}
			$bits = (int) $parts[1];
			if ( $bits > $max_bits ) {
				return false;
			}
		}

		$full_bytes = (int) floor( $bits / 8 );
		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $net_bin, 0, $full_bytes ) ) {
			return false;
		}

		$rest = $bits % 8;
		if ( $rest > 0 ) {
			$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $net_bin[ $full_bytes ] ) & $mask ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Parse a textarea with one IP or CIDR block per line.
	 *
	 * @param string $text Raw text.
	 * @return array{valid: string[], invalid: string[]}
	 */
	public static function parse_list( $text ) {
		$valid   = array();
		$invalid = array();

		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$parts = explode( '/', $line, 2 );
			$addr  = self::normalize( $parts[0] );
			$ok    = '' !== $addr;

			if ( $ok && isset( $parts[1] ) ) {
				$max = false === strpos( $addr, ':' ) ? 32 : 128;
				$ok  = '' !== $parts[1] && ctype_digit( $parts[1] ) && (int) $parts[1] <= $max;
			}

			if ( $ok ) {
				$valid[] = $addr . ( isset( $parts[1] ) ? '/' . (int) $parts[1] : '' );
			} else {
				$invalid[] = $line;
			}
		}

		return array(
			'valid'   => array_values( array_unique( $valid ) ),
			'invalid' => $invalid,
		);
	}
}
