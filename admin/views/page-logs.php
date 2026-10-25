<?php
/**
 * History screen.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Filters and paging are read-only view options, no state is changed.
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$wpfpv_type   = isset( $_GET['type'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['type'] ) ) ) : '';
$wpfpv_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
$wpfpv_notice = isset( $_GET['wpfpv_notice'] ) ? WPFPV_Admin::notice_message( sanitize_key( wp_unslash( $_GET['wpfpv_notice'] ) ) ) : null;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$wpfpv_types   = WPFPV_Logger::types();
$wpfpv_type    = in_array( $wpfpv_type, $wpfpv_types, true ) ? $wpfpv_type : '';
$wpfpv_all     = WPFPV_Logger::get_entries();
$wpfpv_stats   = WPFPV_Logger::get_stats( $wpfpv_all );
$wpfpv_entries = $wpfpv_all;

if ( '' !== $wpfpv_type ) {
	$wpfpv_entries = array_values(
		array_filter(
			$wpfpv_all,
			static function ( $entry ) use ( $wpfpv_type ) {
				return $entry['type'] === $wpfpv_type;
			}
		)
	);
}

$wpfpv_total = count( $wpfpv_entries );
$wpfpv_pages = max( 1, (int) ceil( $wpfpv_total / WPFPV_Admin::PER_PAGE ) );
$wpfpv_page  = min( $wpfpv_page, $wpfpv_pages );
$wpfpv_rows  = array_slice( $wpfpv_entries, ( $wpfpv_page - 1 ) * WPFPV_Admin::PER_PAGE, WPFPV_Admin::PER_PAGE );
$wpfpv_base  = admin_url( 'admin.php?page=' . WPFPV_Admin::SLUG_LOGS );

$wpfpv_labels = array(
	'VALIDATION' => __( 'Invalid number', 'phone-validator-for-wpforms' ),
	'THROTTLE'   => __( 'Too frequent', 'phone-validator-for-wpforms' ),
	'LIMIT'      => __( 'Daily limit', 'phone-validator-for-wpforms' ),
	'HONEYPOT'   => __( 'Bot (honeypot)', 'phone-validator-for-wpforms' ),
	'BOTCHECK'   => __( 'Bot (browser check)', 'phone-validator-for-wpforms' ),
	'NOJS'       => __( 'No JavaScript', 'phone-validator-for-wpforms' ),
	'SUCCESS'    => __( 'Accepted', 'phone-validator-for-wpforms' ),
	'OTHER'      => __( 'Other', 'phone-validator-for-wpforms' ),
);
?>
<div class="wrap wpfpv-wrap">
	<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'Submission history', 'phone-validator-for-wpforms' ); ?></h1>
	<hr class="wp-header-end">

	<header class="wpfpv-hero">
		<img class="wpfpv-hero__bear" src="<?php echo esc_url( WPFPV_PLUGIN_URL . 'admin/images/bear.png' ); ?>" width="150" alt="">
		<div>
			<h2 class="wpfpv-hero__title"><?php esc_html_e( 'Submission history', 'phone-validator-for-wpforms' ); ?></h2>
			<p class="wpfpv-hero__text"><?php esc_html_e( 'What the plugin rejected and accepted, newest first.', 'phone-validator-for-wpforms' ); ?></p>
		</div>
	</header>

	<?php if ( $wpfpv_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $wpfpv_notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $wpfpv_notice['text'] ); ?></p></div>
	<?php endif; ?>

	<ul class="wpfpv-stats">
		<li><strong><?php echo esc_html( number_format_i18n( $wpfpv_stats['total'] ) ); ?></strong><span><?php esc_html_e( 'Entries', 'phone-validator-for-wpforms' ); ?></span></li>
		<li><strong><?php echo esc_html( number_format_i18n( $wpfpv_stats['blocked'] ) ); ?></strong><span><?php esc_html_e( 'Rejected', 'phone-validator-for-wpforms' ); ?></span></li>
		<li><strong><?php echo esc_html( number_format_i18n( $wpfpv_stats['unique_ips'] ) ); ?></strong><span><?php esc_html_e( 'Unique IPs', 'phone-validator-for-wpforms' ); ?></span></li>
		<li><strong><?php echo esc_html( number_format_i18n( WPFPV_Honeypot::get_count() ) ); ?></strong><span><?php esc_html_e( 'Bots stopped', 'phone-validator-for-wpforms' ); ?></span></li>
		<li><strong><?php echo esc_html( $wpfpv_stats['file_size'] ); ?></strong><span><?php esc_html_e( 'File size', 'phone-validator-for-wpforms' ); ?></span></li>
	</ul>

	<div class="wpfpv-toolbar">
		<nav class="wpfpv-filter" aria-label="<?php esc_attr_e( 'Filter by type', 'phone-validator-for-wpforms' ); ?>">
			<a href="<?php echo esc_url( $wpfpv_base ); ?>" class="<?php echo '' === $wpfpv_type ? 'is-active' : ''; ?>"><?php esc_html_e( 'All', 'phone-validator-for-wpforms' ); ?></a>
			<?php foreach ( $wpfpv_types as $wpfpv_item ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'type', strtolower( $wpfpv_item ), $wpfpv_base ) ); ?>" class="<?php echo $wpfpv_type === $wpfpv_item ? 'is-active' : ''; ?>"><?php echo esc_html( $wpfpv_labels[ $wpfpv_item ] ); ?></a>
			<?php endforeach; ?>
		</nav>

		<div class="wpfpv-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpfpv_download_log">
				<?php wp_nonce_field( 'wpfpv_download_log' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Download', 'phone-validator-for-wpforms' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpfpv_clear_log">
				<?php wp_nonce_field( 'wpfpv_clear_log' ); ?>
				<button type="submit" class="button button-link-delete" data-wpfpv-confirm="clear"><?php esc_html_e( 'Clear history', 'phone-validator-for-wpforms' ); ?></button>
			</form>
		</div>
	</div>

	<?php if ( empty( $wpfpv_rows ) ) : ?>
		<div class="wpfpv-card wpfpv-empty">
			<p><?php esc_html_e( 'Nothing here yet. Entries appear when someone submits a form.', 'phone-validator-for-wpforms' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpfpv-card wpfpv-table-wrap">
			<table class="widefat striped wpfpv-table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Time', 'phone-validator-for-wpforms' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'phone-validator-for-wpforms' ); ?></th>
						<th scope="col"><?php esc_html_e( 'IP', 'phone-validator-for-wpforms' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Form', 'phone-validator-for-wpforms' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Number', 'phone-validator-for-wpforms' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Message', 'phone-validator-for-wpforms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wpfpv_rows as $wpfpv_row ) : ?>
						<tr>
							<td class="wpfpv-nowrap"><?php echo esc_html( $wpfpv_row['time'] ? wp_date( 'Y-m-d H:i:s', $wpfpv_row['time'] ) : '' ); ?></td>
							<td><span class="wpfpv-badge wpfpv-badge--<?php echo esc_attr( strtolower( $wpfpv_row['type'] ) ); ?>"><?php echo esc_html( $wpfpv_labels[ $wpfpv_row['type'] ] ); ?></span></td>
							<td><code><?php echo esc_html( $wpfpv_row['ip'] ); ?></code></td>
							<td><?php echo esc_html( $wpfpv_row['form'] ); ?></td>
							<td><?php echo esc_html( $wpfpv_row['value'] ); ?></td>
							<td><?php echo esc_html( $wpfpv_row['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( $wpfpv_pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%', '' !== $wpfpv_type ? add_query_arg( 'type', strtolower( $wpfpv_type ), $wpfpv_base ) : $wpfpv_base ),
								'format'    => '',
								'current'   => $wpfpv_page,
								'total'     => $wpfpv_pages,
								'prev_text' => '&lsaquo;',
								'next_text' => '&rsaquo;',
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php WPFPV_Admin::render_support_footer(); ?>
</div>
