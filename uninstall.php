<?php
/**
 * Removes all plugin data on uninstall.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the data of the current site.
 */
function wpfpv_uninstall_site() {
	global $wpdb;

	foreach ( array( 'wpfpv_settings', 'wpfpv_db_version', 'wpfpv_schema', 'wpfpv_log_name', 'wpfpv_honeypot_blocked' ) as $option ) {
		delete_option( $option );
	}

	wp_clear_scheduled_hook( 'wpfpv_daily_cleanup' );

	// Throttle and daily limit counters.
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_wpfpv_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_wpfpv_' ) . '%'
		)
	);

	// Log folder, including the hidden files.
	$upload = wp_upload_dir( null, false );
	$dir    = trailingslashit( $upload['basedir'] ) . 'phone-validator-for-wpforms';

	if ( is_dir( $dir ) ) {
		$items = scandir( $dir );
		if ( is_array( $items ) ) {
			foreach ( array_diff( $items, array( '.', '..' ) ) as $item ) {
				$path = $dir . '/' . $item;
				if ( is_file( $path ) ) {
					wp_delete_file( $path );
				}
			}
		}
		rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing our own empty folder.
	}
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $wpfpv_site_id ) {
		switch_to_blog( $wpfpv_site_id );
		wpfpv_uninstall_site();
		restore_current_blog();
	}
} else {
	wpfpv_uninstall_site();
}
