<?php
/**
 * Protection against repeated submissions: minimum interval and daily limit per IP.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate limiting by visitor IP address.
 */
class WPFPV_Throttle {

	/**
	 * Singleton instance.
	 *
	 * @var WPFPV_Throttle|null
	 */
	private static $instance = null;

	/**
	 * Get the instance.
	 *
	 * @return WPFPV_Throttle
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
		add_action( 'wpforms_process', array( $this, 'check' ), 5, 3 );
		add_action( 'wpforms_process_complete', array( $this, 'record' ), 10, 3 );
	}

	/**
	 * WPForms hook: refuse the submission when a limit is reached.
	 *
	 * @param array $fields    Sanitized fields.
	 * @param array $entry     Submitted data.
	 * @param array $form_data Form data.
	 */
	public function check( $fields, $entry, $form_data ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$settings = WPFPV_Settings::get( $form_id );
		$interval = absint( $settings['throttle_time'] );
		$limit    = absint( $settings['daily_limit'] );

		if ( empty( $settings['limits_enabled'] ) || ( $interval <= 0 && $limit <= 0 ) ) {
			return;
		}

		$ip = WPFPV_IP::get( $settings );
		if ( '' === $ip || self::is_exempt( $ip, $settings ) ) {
			return;
		}

		// 1. Minimum interval between successful submissions.
		if ( $interval > 0 ) {
			$last = get_transient( self::interval_key( $ip, $form_id ) );

			if ( false !== $last ) {
				$remaining = (int) ceil( ( $interval * MINUTE_IN_SECONDS - ( time() - (int) $last ) ) / MINUTE_IN_SECONDS );
				$remaining = max( 1, $remaining );

				wpforms()->process->errors[ $form_id ]['header'] = sprintf(
					/* translators: %d: number of minutes. */
					__( 'You have recently submitted this form. Please wait %d min. before next submission.', 'phone-validator-for-wpforms' ),
					$remaining
				);

				WPFPV_Logger::log(
					'THROTTLE',
					array(
						'ip'      => $ip,
						'form'    => $form_id,
						'message' => sprintf(
							/* translators: %d: number of minutes. */
							__( 'Wait %d min.', 'phone-validator-for-wpforms' ),
							$remaining
						),
					)
				);
				return;
			}
		}

		// 2. Daily limit (rolling 24 hours).
		if ( $limit > 0 ) {
			$stamps = self::recent_stamps( self::daily_key( $ip, $form_id, $settings ) );

			if ( count( $stamps ) >= $limit ) {
				wpforms()->process->errors[ $form_id ]['header'] = __( 'Too many submissions from your connection. Please try again later.', 'phone-validator-for-wpforms' );

				WPFPV_Logger::log(
					'LIMIT',
					array(
						'ip'      => $ip,
						'form'    => $form_id,
						'message' => sprintf(
							/* translators: %d: allowed number of submissions per 24 hours. */
							__( 'Limit of %d per 24 hours reached', 'phone-validator-for-wpforms' ),
							$limit
						),
					)
				);
			}
		}
	}

	/**
	 * WPForms hook: remember a successful submission.
	 *
	 * @param array $fields    Sanitized fields.
	 * @param array $entry     Submitted data.
	 * @param array $form_data Form data.
	 */
	public function record( $fields, $entry, $form_data ) {
		$form_id  = isset( $form_data['id'] ) ? absint( $form_data['id'] ) : 0;
		$settings = WPFPV_Settings::get( $form_id );
		$interval = absint( $settings['throttle_time'] );
		$limit    = absint( $settings['daily_limit'] );

		if ( empty( $settings['limits_enabled'] ) || ( $interval <= 0 && $limit <= 0 ) ) {
			return;
		}

		if ( ! empty( wpforms()->process->errors[ $form_id ] ) ) {
			return;
		}

		$ip = WPFPV_IP::get( $settings );
		if ( '' === $ip || self::is_exempt( $ip, $settings ) ) {
			return;
		}

		if ( $interval > 0 ) {
			set_transient( self::interval_key( $ip, $form_id ), time(), $interval * MINUTE_IN_SECONDS );
		}

		if ( $limit > 0 ) {
			$key      = self::daily_key( $ip, $form_id, $settings );
			$stamps   = self::recent_stamps( $key );
			$stamps[] = time();
			set_transient( $key, $stamps, DAY_IN_SECONDS );
		}

		WPFPV_Logger::log(
			'SUCCESS',
			array(
				'ip'      => $ip,
				'form'    => $form_id,
				'message' => __( 'Submission accepted', 'phone-validator-for-wpforms' ),
			)
		);
	}

	/**
	 * Are limits skipped for this visitor? (whitelisted IP or a logged-in administrator)
	 *
	 * @param string $ip       Visitor IP.
	 * @param array  $settings Plugin settings.
	 * @return bool
	 */
	public static function is_exempt( $ip, array $settings ) {
		if ( ! empty( $settings['exempt_admins'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
			return true;
		}

		$whitelist = WPFPV_IP::parse_list( $settings['whitelist_ips'] );
		return ! empty( $whitelist['valid'] ) && WPFPV_IP::in_ranges( $ip, $whitelist['valid'] );
	}

	/**
	 * Transient key for the interval check.
	 *
	 * @param string $ip      Visitor IP.
	 * @param int    $form_id Form ID.
	 * @return string
	 */
	private static function interval_key( $ip, $form_id ) {
		return 'wpfpv_throttle_' . md5( $ip . '_' . $form_id );
	}

	/**
	 * Transient key for the daily limit; one counter for the whole site or one per form.
	 *
	 * @param string $ip       Visitor IP.
	 * @param int    $form_id  Form ID.
	 * @param array  $settings Plugin settings.
	 * @return string
	 */
	private static function daily_key( $ip, $form_id, array $settings ) {
		$scope = ( 'form' === $settings['limit_scope'] ) ? (string) $form_id : 'site';
		return 'wpfpv_daily_' . substr( wp_hash( $ip . '|' . $scope ), 0, 32 );
	}

	/**
	 * Timestamps of submissions within the last 24 hours.
	 *
	 * @param string $key Transient key.
	 * @return int[]
	 */
	private static function recent_stamps( $key ) {
		$stamps = get_transient( $key );
		if ( ! is_array( $stamps ) ) {
			return array();
		}

		$since = time() - DAY_IN_SECONDS;
		return array_values(
			array_filter(
				array_map( 'intval', $stamps ),
				static function ( $stamp ) use ( $since ) {
					return $stamp > $since;
				}
			)
		);
	}
}
