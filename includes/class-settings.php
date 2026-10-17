<?php
/**
 * Settings management.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Defaults, retrieval and sanitization of plugin settings.
 */
class WPFPV_Settings {

	const OPTION = 'wpfpv_settings';
	const GROUP  = 'wpfpv_settings_group';

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Settings|null
	 */
	private static $instance = null;

	/**
	 * Request-level cache of saved settings merged with defaults.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Settings
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
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		foreach ( array( 'add_option_', 'update_option_', 'delete_option_' ) as $prefix ) {
			add_action( $prefix . self::OPTION, array( __CLASS__, 'flush_cache' ) );
		}
	}

	/**
	 * Forget the cached settings.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			// General rules.
			'min_length'            => 6,
			'max_length'            => 15,
			'max_repeats'           => 5,
			'sequence_length'       => 7,

			// Number normalization.
			'countries_enabled'     => 1,
			'default_country'       => '',
			'custom_rules'          => '',

			// Advanced checks.
			'blacklist'             => "1234567890\n0987654321",
			'allowed_country_codes' => '',

			// Input mask.
			'mask_enabled'          => 0,
			'masks'                 => '',

			// Honeypot.
			'honeypot_enabled'      => 1,
			'honeypot_field_name'   => '',
			'honeypot_indicator'    => 1,

			// Rate limiting.
			'limits_enabled'        => 1,
			'throttle_time'         => 3,
			'daily_limit'           => 0,
			'limit_scope'           => 'site',
			'whitelist_ips'         => '',
			'exempt_admins'         => 1,

			// Visitor IP detection.
			'proxy_mode'            => 'none',
			'proxy_header'          => 'X-Forwarded-For',
			'trusted_proxies'       => '',

			// Logging.
			'enable_logging'        => 1,
			'auto_clean_logs'       => 30,
		);
	}

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array
	 */
	private static function get_saved() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get settings.
	 *
	 * @param int $form_id Optional form ID, reserved for per-form overrides via the filter.
	 * @return array
	 */
	public static function get( $form_id = 0 ) {
		/**
		 * Filters the settings used for a request (and optionally a single form).
		 *
		 * @param array $settings Settings.
		 * @param int   $form_id  WPForms form ID, 0 when unknown.
		 */
		return apply_filters( 'wpfpv_settings', self::get_saved(), absint( $form_id ) );
	}

	/**
	 * Name of the honeypot field: the saved name, or a stable per-site default.
	 *
	 * @return string
	 */
	public static function honeypot_name() {
		$settings = self::get_saved();
		$name     = $settings['honeypot_field_name'];

		if ( ! self::is_valid_field_name( $name ) ) {
			$name = 'h' . substr( wp_hash( 'wpfpv-honeypot' ), 0, 11 );
		}

		return $name;
	}

	/**
	 * Is the string usable as honeypot field name?
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function is_valid_field_name( $name ) {
		$reserved = array( 'wpforms', 'hp', 'action', 'submit', 'id', 'form_id', 'nonce' );

		return is_string( $name )
			&& 1 === preg_match( '/^[a-z][a-z0-9_]{5,31}$/', $name )
			&& ! in_array( $name, $reserved, true );
	}

	/**
	 * Register the setting.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::get_defaults(),
			)
		);
	}

	/**
	 * Sanitize and validate submitted settings.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$previous = self::get_saved();
		$defaults = self::get_defaults();
		$out      = array();

		// General rules.
		$out['min_length']      = self::int_between( $input, 'min_length', 1, 20, $previous['min_length'] );
		$out['max_length']      = self::int_between( $input, 'max_length', 5, 20, $previous['max_length'] );
		$out['max_repeats']     = self::int_between( $input, 'max_repeats', 0, 10, $previous['max_repeats'] );
		$out['sequence_length'] = self::int_between( $input, 'sequence_length', 0, 10, $previous['sequence_length'] );

		if ( $out['max_repeats'] > 0 && $out['max_repeats'] < 2 ) {
			$out['max_repeats'] = 2;
		}
		if ( $out['sequence_length'] > 0 && $out['sequence_length'] < 4 ) {
			$out['sequence_length'] = 4;
		}
		if ( $out['min_length'] > $out['max_length'] ) {
			add_settings_error( self::OPTION, 'invalid_length', __( 'Minimum length cannot be greater than maximum length. The previous values were kept.', 'phone-validator-for-wpforms' ) );
			$out['min_length'] = (int) $previous['min_length'];
			$out['max_length'] = (int) $previous['max_length'];
		}

		// Number normalization.
		$out['countries_enabled'] = empty( $input['countries_enabled'] ) ? 0 : 1;

		$countries              = WPFPV_Phone::countries();
		$country                = strtoupper( self::text( $input, 'default_country' ) );
		$out['default_country'] = isset( $countries[ $country ] ) ? $country : '';

		$rules = WPFPV_Phone::parse_rules( self::textarea( $input, 'custom_rules' ) );
		/* translators: %s: rejected lines, one after another. */
		self::report_invalid( $rules['invalid'], 'invalid_rules', __( 'These conversion rules were ignored (use the form "07 => +447"): %s', 'phone-validator-for-wpforms' ) );
		$lines = array();
		foreach ( $rules['rules'] as $rule ) {
			$lines[ $rule[0] . '=>' . $rule[1] ] = $rule[0] . ' => +' . $rule[1];
		}
		$out['custom_rules'] = implode( "\n", $lines );

		// Advanced checks.
		$out['blacklist']             = self::sanitize_blacklist( self::textarea( $input, 'blacklist' ) );
		$codes                        = WPFPV_Validator::parse_codes( self::text( $input, 'allowed_country_codes' ) );
		$out['allowed_country_codes'] = empty( $codes ) ? '' : '+' . implode( ', +', $codes );

		// Input mask.
		$out['mask_enabled'] = empty( $input['mask_enabled'] ) ? 0 : 1;
		$masks               = WPFPV_Mask::parse( self::textarea( $input, 'masks' ) );
		/* translators: %s: rejected lines, one after another. */
		self::report_invalid( $masks['invalid'], 'invalid_masks', __( 'These masks were ignored (use digits and "*" for any digit, 5 to 20 positions, for example "+1 *** *** ****"): %s', 'phone-validator-for-wpforms' ) );
		$mask_lines = array();
		foreach ( $masks['masks'] as $mask ) {
			$mask_lines[] = $mask['mask'];
		}
		$out['masks'] = implode( "\n", $mask_lines );
		if ( $out['mask_enabled'] && empty( $mask_lines ) ) {
			add_settings_error( self::OPTION, 'mask_empty', __( 'The input mask is enabled but no valid mask is defined, so it stays inactive.', 'phone-validator-for-wpforms' ), 'warning' );
		}

		// Honeypot.
		$out['honeypot_enabled']   = empty( $input['honeypot_enabled'] ) ? 0 : 1;
		$out['honeypot_indicator'] = empty( $input['honeypot_indicator'] ) ? 0 : 1;
		$name                      = strtolower( self::text( $input, 'honeypot_field_name' ) );
		if ( '' !== $name && ! self::is_valid_field_name( $name ) ) {
			add_settings_error( self::OPTION, 'invalid_honeypot_name', __( 'The honeypot field name must be 6 to 32 characters: lowercase letters, digits and underscores, starting with a letter. The previous name was kept.', 'phone-validator-for-wpforms' ) );
			$name = $previous['honeypot_field_name'];
		}
		$out['honeypot_field_name'] = $name;

		// Rate limiting.
		$out['limits_enabled'] = empty( $input['limits_enabled'] ) ? 0 : 1;
		$out['throttle_time']  = self::int_between( $input, 'throttle_time', 0, 1440, $previous['throttle_time'] );
		$out['daily_limit']    = self::int_between( $input, 'daily_limit', 0, 1000, $previous['daily_limit'] );
		$out['limit_scope']    = ( 'form' === self::text( $input, 'limit_scope' ) ) ? 'form' : 'site';
		$out['exempt_admins']  = empty( $input['exempt_admins'] ) ? 0 : 1;

		$whitelist = WPFPV_IP::parse_list( self::textarea( $input, 'whitelist_ips' ) );
		/* translators: %s: rejected lines, one after another. */
		self::report_invalid( $whitelist['invalid'], 'invalid_whitelist', __( 'These addresses are not valid IPs or CIDR ranges and were ignored: %s', 'phone-validator-for-wpforms' ) );
		$out['whitelist_ips'] = implode( "\n", $whitelist['valid'] );

		// Visitor IP detection.
		$mode                = sanitize_key( self::text( $input, 'proxy_mode' ) );
		$out['proxy_mode']   = in_array( $mode, array( 'none', 'cloudflare', 'custom' ), true ) ? $mode : 'none';
		$header              = self::text( $input, 'proxy_header' );
		$out['proxy_header'] = in_array( $header, WPFPV_IP::allowed_headers(), true ) ? $header : $defaults['proxy_header'];

		$proxies = WPFPV_IP::parse_list( self::textarea( $input, 'trusted_proxies' ) );
		/* translators: %s: rejected lines, one after another. */
		self::report_invalid( $proxies['invalid'], 'invalid_proxies', __( 'These addresses are not valid IPs or CIDR ranges and were ignored: %s', 'phone-validator-for-wpforms' ) );
		$out['trusted_proxies'] = implode( "\n", $proxies['valid'] );
		if ( 'custom' === $out['proxy_mode'] && empty( $proxies['valid'] ) ) {
			add_settings_error( self::OPTION, 'proxy_empty', __( 'Add at least one trusted proxy address, otherwise the proxy header cannot be trusted. The IP detection mode was reset to "REMOTE_ADDR only".', 'phone-validator-for-wpforms' ) );
			$out['proxy_mode'] = 'none';
		}

		// Logging.
		$out['enable_logging']  = empty( $input['enable_logging'] ) ? 0 : 1;
		$out['auto_clean_logs'] = self::int_between( $input, 'auto_clean_logs', 0, 365, $previous['auto_clean_logs'] );

		return $out;
	}

	/**
	 * Single-line text value from the submitted array.
	 *
	 * @param array  $input Submitted values.
	 * @param string $key   Key.
	 * @return string
	 */
	private static function text( array $input, $key ) {
		return ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) ? trim( sanitize_text_field( (string) $input[ $key ] ) ) : '';
	}

	/**
	 * Multi-line text value from the submitted array.
	 *
	 * @param array  $input Submitted values.
	 * @param string $key   Key.
	 * @return string
	 */
	private static function textarea( array $input, $key ) {
		return ( isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ) ? sanitize_textarea_field( (string) $input[ $key ] ) : '';
	}

	/**
	 * Integer in a range, falling back when missing or not numeric.
	 *
	 * @param array  $input    Submitted values.
	 * @param string $key      Key.
	 * @param int    $min      Minimum.
	 * @param int    $max      Maximum.
	 * @param int    $fallback Value used when the key is missing or invalid.
	 * @return int
	 */
	private static function int_between( array $input, $key, $min, $max, $fallback ) {
		if ( ! isset( $input[ $key ] ) || ! is_numeric( $input[ $key ] ) ) {
			return (int) $fallback;
		}
		return max( $min, min( $max, (int) $input[ $key ] ) );
	}

	/**
	 * Keep only digits and common phone punctuation in the blacklist, one entry per line.
	 *
	 * @param string $text Raw text.
	 * @return string
	 */
	private static function sanitize_blacklist( $text ) {
		$entries = array();
		foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
			$line = trim( preg_replace( '/[^0-9+\s().\-]/', '', $line ) );
			if ( 1 === preg_match( '/\d/', $line ) ) {
				$entries[ $line ] = $line;
			}
			if ( count( $entries ) >= 5000 ) {
				break;
			}
		}
		return implode( "\n", $entries );
	}

	/**
	 * Add a settings error listing ignored lines.
	 *
	 * @param string[] $invalid  Ignored lines.
	 * @param string   $code     Error code.
	 * @param string   $template Message with one %s placeholder.
	 */
	private static function report_invalid( array $invalid, $code, $template ) {
		if ( empty( $invalid ) ) {
			return;
		}
		add_settings_error( self::OPTION, $code, sprintf( $template, esc_html( implode( ', ', array_slice( $invalid, 0, 5 ) ) ) ) );
	}
}
