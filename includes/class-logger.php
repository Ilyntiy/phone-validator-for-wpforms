<?php
/**
 * Logging system.
 *
 * The log is a flat file in the uploads folder. It is protected in layers:
 * an unpredictable file name, a ".php" extension with an "exit" header (a web
 * server that executes PHP outputs nothing), an Apache deny rule and an empty
 * index file. It is capped in size, rotated under a lock and can only be
 * downloaded through an authenticated admin request.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes, reads and maintains the log file.
 */
class WPFPV_Logger {

	const HEADER       = "<?php exit; ?>\n";
	const DIR_NAME     = 'phone-validator-for-wpforms';
	const NAME_OPTION  = 'wpfpv_log_name';
	const DEFAULT_SIZE = 2097152; // 2 MB.

	/**
	 * Entry types.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'VALIDATION', 'THROTTLE', 'LIMIT', 'HONEYPOT', 'BOTCHECK', 'NOJS', 'SUCCESS' );
	}

	/**
	 * Log folder.
	 *
	 * @return string
	 */
	public static function get_dir() {
		$upload = wp_upload_dir( null, false );
		return trailingslashit( $upload['basedir'] ) . self::DIR_NAME;
	}

	/**
	 * Path of the current log file; the name is generated once.
	 *
	 * @return string
	 */
	public static function get_file() {
		$name = get_option( self::NAME_OPTION );

		if ( ! is_string( $name ) || 1 !== preg_match( '/^log-[a-z0-9]{24}\.php$/', $name ) ) {
			$name = 'log-' . strtolower( wp_generate_password( 24, false, false ) ) . '.php';
			update_option( self::NAME_OPTION, $name, false );
		}

		return self::get_dir() . '/' . $name;
	}

	/**
	 * Maximum log size in bytes.
	 *
	 * @return int
	 */
	private static function max_size() {
		/**
		 * Filters the maximum size of the log file in bytes.
		 *
		 * @param int $size Size in bytes (default 2 MB).
		 */
		return max( 65536, (int) apply_filters( 'wpfpv_log_max_size', self::DEFAULT_SIZE ) );
	}

	/**
	 * Create the log folder with its protection files.
	 *
	 * @return bool True when the folder exists and is writable.
	 */
	public static function ensure_dir() {
		static $ready = null;
		if ( null !== $ready ) {
			return $ready;
		}

		$dir = self::get_dir();
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			$ready = false;
			return false;
		}

		$htaccess = "# Deny all access.\n"
			. "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";

		$files = array(
			'.htaccess' => $htaccess,
			'index.php' => "<?php\n// Silence is golden.\n",
		);

		foreach ( $files as $name => $content ) {
			if ( ! file_exists( $dir . '/' . $name ) ) {
				file_put_contents( $dir . '/' . $name, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Protecting the log folder.
			}
		}

		$ready = is_writable( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable -- Plain file logging in uploads.
		return $ready;
	}

	/**
	 * Add an entry.
	 *
	 * @param string $type Entry type, one of self::types().
	 * @param array  $data Keys: ip, form, field, value, message.
	 * @return bool
	 */
	public static function log( $type, array $data = array() ) {
		$settings = WPFPV_Settings::get();
		if ( empty( $settings['enable_logging'] ) || ! in_array( $type, self::types(), true ) || ! self::ensure_dir() ) {
			return false;
		}

		$ip = isset( $data['ip'] ) ? $data['ip'] : WPFPV_IP::get( $settings );

		$fields = array(
			gmdate( 'Y-m-d\TH:i:s\Z' ),
			$type,
			self::clean( $ip, 45 ),
			self::clean( isset( $data['form'] ) ? $data['form'] : '', 20 ),
			self::clean( isset( $data['field'] ) ? $data['field'] : '', 20 ),
			self::clean( isset( $data['value'] ) ? $data['value'] : '', 64 ),
			self::clean( isset( $data['message'] ) ? $data['message'] : '', 200 ),
		);

		return self::append( self::get_file(), implode( "\t", $fields ) . "\n" );
	}

	/**
	 * Make a value safe for a single tab-separated log line.
	 *
	 * @param mixed $value  Value.
	 * @param int   $length Maximum length in characters.
	 * @return string
	 */
	private static function clean( $value, $length ) {
		$value = is_scalar( $value ) ? (string) $value : '';
		$clean = preg_replace( '/[^\P{C}]+/u', ' ', $value );
		if ( null === $clean ) {
			// Invalid UTF-8: fall back to a byte-wise filter.
			$clean = preg_replace( '/[\x00-\x1F\x7F-\xFF]+/', ' ', $value );
		}
		$clean = trim( (string) $clean );

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $clean, 0, $length, 'UTF-8' );
		}
		return substr( $clean, 0, $length );
	}

	/**
	 * Append a line, rotating the file first when it is too large.
	 *
	 * @param string $file Log file.
	 * @param string $line Line including the trailing newline.
	 * @return bool
	 */
	private static function append( $file, $line ) {
		$new    = ! file_exists( $file );
		$handle = fopen( $file, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Locked append to the log file.
		if ( false === $handle ) {
			return false;
		}

		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return false;
		}

		$stat = fstat( $handle );
		$size = is_array( $stat ) ? (int) $stat['size'] : 0;

		if ( 0 === $size ) {
			fwrite( $handle, self::HEADER ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		} elseif ( $size > self::max_size() ) {
			self::rotate( $handle );
		}

		fseek( $handle, 0, SEEK_END );
		$written = fwrite( $handle, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( $new ) {
			chmod( $file, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Keep the log private.
		}

		return false !== $written;
	}

	/**
	 * Drop the oldest half of an oversized log. The handle is already locked.
	 *
	 * @param resource $handle Locked file handle.
	 */
	private static function rotate( $handle ) {
		rewind( $handle );
		$content = stream_get_contents( $handle );
		$content = false === $content ? '' : $content;
		$tail    = substr( $content, -1 * (int) ( self::max_size() / 2 ) );

		// Start at a line boundary.
		$break = strpos( $tail, "\n" );
		$tail  = false === $break ? '' : substr( $tail, $break + 1 );

		ftruncate( $handle, 0 );
		rewind( $handle );
		fwrite( $handle, self::HEADER . $tail ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	}

	/**
	 * Raw log lines in file order.
	 *
	 * @return string[]
	 */
	public static function read_lines() {
		$file = self::get_file();
		if ( ! file_exists( $file ) ) {
			return array();
		}

		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local log file.
		if ( false === $content ) {
			return array();
		}

		if ( 0 === strpos( $content, self::HEADER ) ) {
			$content = substr( $content, strlen( self::HEADER ) );
		}

		return array_values( array_filter( explode( "\n", $content ), 'strlen' ) );
	}

	/**
	 * Log contents as plain text (for the download), without the PHP guard line.
	 *
	 * @return string
	 */
	public static function get_contents() {
		$lines = self::read_lines();
		return empty( $lines ) ? '' : implode( "\n", $lines ) . "\n";
	}

	/**
	 * Parsed entries, newest first.
	 *
	 * @return array[]
	 */
	public static function get_entries() {
		$entries = array();
		foreach ( array_reverse( self::read_lines() ) as $line ) {
			$entries[] = self::parse_line( $line );
		}
		return $entries;
	}

	/**
	 * Parse one line, in the current tab-separated or the 1.0.x format.
	 *
	 * @param string $line Raw line.
	 * @return array{time: int, type: string, ip: string, form: string, field: string, value: string, message: string}
	 */
	public static function parse_line( $line ) {
		$entry = array(
			'time'    => 0,
			'type'    => 'OTHER',
			'ip'      => '',
			'form'    => '',
			'field'   => '',
			'value'   => '',
			'message' => '',
		);

		$parts = explode( "\t", $line );
		if ( count( $parts ) >= 7 ) {
			$entry['time']    = (int) strtotime( $parts[0] );
			$entry['type']    = in_array( $parts[1], self::types(), true ) ? $parts[1] : 'OTHER';
			$entry['ip']      = $parts[2];
			$entry['form']    = $parts[3];
			$entry['field']   = $parts[4];
			$entry['value']   = $parts[5];
			$entry['message'] = $parts[6];
			return $entry;
		}

		// 1.0.x format: [date] TYPE | IP: x | Form: x | Field: x | Value: x | Error: x.
		if ( 1 === preg_match( '/^\[([^\]]+)\]\s+(VALIDATION ERROR|THROTTLE BLOCK|SUCCESS)\s*\|\s*IP:\s*(.*?)\s*\|\s*Form:\s*([^|]*?)\s*(?:\|\s*(.*))?$/', $line, $match ) ) {
			$types = array(
				'VALIDATION ERROR' => 'VALIDATION',
				'THROTTLE BLOCK'   => 'THROTTLE',
				'SUCCESS'          => 'SUCCESS',
			);

			$entry['time'] = (int) strtotime( $match[1] );
			$entry['type'] = $types[ $match[2] ];
			$entry['ip']   = $match[3];
			$entry['form'] = $match[4];

			$rest = isset( $match[5] ) ? $match[5] : '';
			if ( 1 === preg_match( '/Field:\s*(.*?)\s*\|\s*Value:\s*(.*?)\s*\|\s*Error:\s*(.*)$/', $rest, $detail ) ) {
				$entry['field']   = $detail[1];
				$entry['value']   = $detail[2];
				$entry['message'] = $detail[3];
			} else {
				$entry['message'] = $rest;
			}
			return $entry;
		}

		$entry['message'] = self::clean( $line, 200 );
		return $entry;
	}

	/**
	 * Statistics for the history page.
	 *
	 * @param array[] $entries Entries from get_entries().
	 * @return array{total: int, blocked: int, unique_ips: int, file_size: string}
	 */
	public static function get_stats( array $entries ) {
		$blocked = 0;
		$ips     = array();

		foreach ( $entries as $entry ) {
			if ( 'SUCCESS' !== $entry['type'] ) {
				++$blocked;
			}
			if ( '' !== $entry['ip'] ) {
				$ips[ $entry['ip'] ] = true;
			}
		}

		$file = self::get_file();
		$size = file_exists( $file ) ? (int) filesize( $file ) : 0;

		return array(
			'total'      => count( $entries ),
			'blocked'    => $blocked,
			'unique_ips' => count( $ips ),
			'file_size'  => size_format( $size ),
		);
	}

	/**
	 * Empty the log.
	 *
	 * @return bool
	 */
	public static function clear() {
		$file = self::get_file();
		if ( ! file_exists( $file ) ) {
			return true;
		}

		$handle = fopen( $file, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return false;
		}
		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return false;
		}

		ftruncate( $handle, 0 );
		rewind( $handle );
		fwrite( $handle, self::HEADER ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return true;
	}

	/**
	 * Cron callback: remove entries older than the configured number of days.
	 */
	public static function cleanup_old_logs() {
		$settings = WPFPV_Settings::get();
		$days     = absint( $settings['auto_clean_logs'] );
		$file     = self::get_file();

		if ( $days <= 0 || ! file_exists( $file ) ) {
			return;
		}

		$handle = fopen( $file, 'c+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return;
		}
		if ( ! flock( $handle, LOCK_EX ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			return;
		}

		$content = stream_get_contents( $handle );
		$content = false === $content ? '' : $content;
		if ( 0 === strpos( $content, self::HEADER ) ) {
			$content = substr( $content, strlen( self::HEADER ) );
		}

		$cutoff = gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS );
		$keep   = array();
		foreach ( explode( "\n", $content ) as $line ) {
			if ( 1 === preg_match( '/^\[?(\d{4}-\d{2}-\d{2})/', $line, $match ) && $match[1] >= $cutoff ) {
				$keep[] = $line;
			}
		}

		ftruncate( $handle, 0 );
		rewind( $handle );
		fwrite( $handle, self::HEADER . ( empty( $keep ) ? '' : implode( "\n", $keep ) . "\n" ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fflush( $handle );
		flock( $handle, LOCK_UN );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}

	/**
	 * Move a pre-1.1 log (validation.log) to the protected file.
	 */
	public static function migrate_legacy_log() {
		$legacy = self::get_dir() . '/validation.log';
		if ( ! file_exists( $legacy ) || ! self::ensure_dir() ) {
			return;
		}

		$old = file_get_contents( $legacy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local log file.
		if ( false === $old ) {
			return;
		}

		// Older entries go first; entries written by the new version in the meantime are kept.
		$target = self::get_file();
		$new    = '';
		if ( file_exists( $target ) ) {
			$new = (string) file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local log file.
			if ( 0 === strpos( $new, self::HEADER ) ) {
				$new = substr( $new, strlen( self::HEADER ) );
			}
		}

		$old = ( '' === $old || "\n" === substr( $old, -1 ) ) ? $old : $old . "\n";

		if ( false !== file_put_contents( $target, self::HEADER . $old . $new, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Local log file.
			chmod( $target, 0640 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Keep the log private.
			wp_delete_file( $legacy );
		}
	}
}
