<?php
/**
 * Settings screen.
 *
 * @package PhoneValidatorForWPForms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wpfpv_s       = WPFPV_Settings::get();
$wpfpv_option  = WPFPV_Settings::OPTION;
$wpfpv_notice  = isset( $_GET['wpfpv_notice'] ) ? WPFPV_Admin::notice_message( sanitize_key( wp_unslash( $_GET['wpfpv_notice'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only selects a fixed message.
$wpfpv_ip      = WPFPV_IP::get( $wpfpv_s );
$wpfpv_blocked = WPFPV_Honeypot::get_count();

/**
 * Field name for the settings array.
 *
 * @param string $key Setting key.
 * @return string Escaped name attribute.
 */
$wpfpv_name = static function ( $key ) use ( $wpfpv_option ) {
	return esc_attr( $wpfpv_option . '[' . $key . ']' );
};
?>
<div class="wrap wpfpv-wrap">
	<h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e( 'Phone Validator settings', 'phone-validator-for-wpforms' ); ?></h1>
	<hr class="wp-header-end">

	<header class="wpfpv-hero">
		<img class="wpfpv-hero__bear" src="<?php echo esc_url( WPFPV_PLUGIN_URL . 'admin/images/bear.png' ); ?>" width="150" alt="">
		<div>
			<h2 class="wpfpv-hero__title"><?php esc_html_e( 'Phone Validator for WPForms', 'phone-validator-for-wpforms' ); ?></h2>
			<p class="wpfpv-hero__text"><?php esc_html_e( 'Keeps junk numbers and bots away from your leads, so you stop calling fake phones.', 'phone-validator-for-wpforms' ); ?></p>
			<span class="wpfpv-version">v<?php echo esc_html( WPFPV_VERSION ); ?></span>
		</div>
	</header>

	<?php if ( $wpfpv_notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $wpfpv_notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $wpfpv_notice['text'] ); ?></p></div>
	<?php endif; ?>

	<?php settings_errors(); ?>

	<form method="post" action="options.php" class="wpfpv-form">
		<?php settings_fields( WPFPV_Settings::GROUP ); ?>

		<section class="wpfpv-card" data-wpfpv-card>
			<h2><?php esc_html_e( 'Phone checks', 'phone-validator-for-wpforms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-min"><?php esc_html_e( 'Digits: minimum and maximum', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-min" class="small-text" min="3" max="20" name="<?php echo $wpfpv_name( 'min_length' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['min_length'] ); ?>">
						&ndash;
						<input type="number" id="wpfpv-max" class="small-text" min="3" max="20" aria-label="<?php esc_attr_e( 'Maximum digits', 'phone-validator-for-wpforms' ); ?>" name="<?php echo $wpfpv_name( 'max_length' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['max_length'] ); ?>">
						<p class="description"><?php esc_html_e( 'Counted on the full international number, including the country code.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-repeats"><?php esc_html_e( 'Max identical digits in a row', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-repeats" class="small-text" min="0" max="20" name="<?php echo $wpfpv_name( 'max_repeats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['max_repeats'] ); ?>">
						<p class="description"><?php esc_html_e( 'The most identical digits that may stand in a row. A longer run is rejected: with 5, the number 000000 (six zeros) is rejected and 00000 is accepted. 0 turns the check off.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-sequence"><?php esc_html_e( 'Sequences', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-sequence" class="small-text" min="0" max="20" name="<?php echo $wpfpv_name( 'sequence_length' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['sequence_length'] ); ?>">
						<p class="description"><?php esc_html_e( 'Reject ascending or descending runs such as 1234567 or 7654321 of at least this length. 0 turns the check off.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-blacklist"><?php esc_html_e( 'Blacklist', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<textarea id="wpfpv-blacklist" class="large-text code" rows="6" name="<?php echo $wpfpv_name( 'blacklist' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"><?php echo esc_textarea( $wpfpv_s['blacklist'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One number per line. Numbers are normalized before the comparison, so 07700 900123 and +44 7700 900123 are the same entry.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card wpfpv-card--toggle<?php echo empty( $wpfpv_s['countries_enabled'] ) ? ' is-off' : ''; ?>" data-wpfpv-card>
			<div class="wpfpv-card__head">
				<h2><?php esc_html_e( 'Countries and number format', 'phone-validator-for-wpforms' ); ?></h2>
				<label class="wpfpv-switch wpfpv-switch--block">
					<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'countries_enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['countries_enabled'] ) ); ?>>
					<span class="wpfpv-switch__track" aria-hidden="true"></span>
					<span class="wpfpv-switch__text"><?php esc_html_e( 'Understand numbers of any country and filter by country', 'phone-validator-for-wpforms' ); ?></span>
				</label>
			</div>
			<div class="wpfpv-card__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-country"><?php esc_html_e( 'Default country', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<select id="wpfpv-country" name="<?php echo $wpfpv_name( 'default_country' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
							<option value=""><?php esc_html_e( 'Not set', 'phone-validator-for-wpforms' ); ?></option>
							<?php foreach ( WPFPV_Phone::country_options() as $wpfpv_iso => $wpfpv_label ) : ?>
								<option value="<?php echo esc_attr( $wpfpv_iso ); ?>" <?php selected( $wpfpv_s['default_country'], $wpfpv_iso ); ?>><?php echo esc_html( $wpfpv_label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Numbers typed without a country code are treated as numbers of this country (a leading 8 or 0 is understood). The stored number is never changed; this is used for the checks only.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-rules"><?php esc_html_e( 'Own conversion rules', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<textarea id="wpfpv-rules" class="large-text code" rows="3" placeholder="07 =&gt; +447" name="<?php echo $wpfpv_name( 'custom_rules' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"><?php echo esc_textarea( $wpfpv_s['custom_rules'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Optional, one rule per line: "from => to". A number that starts with the left part is checked as if it started with the right part. Rules go before the default country.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-codes"><?php esc_html_e( 'Allowed country codes', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="text" id="wpfpv-codes" class="regular-text" placeholder="+1, +44, +33, +34" name="<?php echo $wpfpv_name( 'allowed_country_codes' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['allowed_country_codes'] ); ?>">
						<p class="description"><?php esc_html_e( 'Only numbers that start with one of these codes are accepted. Leave empty to accept any country.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
			</table>
			</div>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card wpfpv-card--toggle<?php echo empty( $wpfpv_s['mask_enabled'] ) ? ' is-off' : ''; ?>" data-wpfpv-card>
			<div class="wpfpv-card__head">
				<h2><?php esc_html_e( 'Input mask', 'phone-validator-for-wpforms' ); ?></h2>
				<label class="wpfpv-switch wpfpv-switch--block">
					<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'mask_enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['mask_enabled'] ) ); ?>>
					<span class="wpfpv-switch__track" aria-hidden="true"></span>
					<span class="wpfpv-switch__text"><?php esc_html_e( 'Require phone numbers to match a mask', 'phone-validator-for-wpforms' ); ?></span>
				</label>
			</div>
			<div class="wpfpv-card__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-masks"><?php esc_html_e( 'Masks', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<textarea id="wpfpv-masks" class="large-text code" rows="3" placeholder="+1 *** *** ****" name="<?php echo $wpfpv_name( 'masks' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"><?php echo esc_textarea( $wpfpv_s['masks'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One mask per line. * is any digit, digits are fixed, other characters are separators. With several masks the one that fits the typed code is used.', 'phone-validator-for-wpforms' ); ?></p>
						<p class="description"><?php esc_html_e( 'Visitors get the format while typing, and the number is checked again on the server, so the mask cannot be bypassed. Fields with a country selector (Smart phone) format themselves: only the server check applies to them.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
			</table>
			</div>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card wpfpv-card--toggle<?php echo empty( $wpfpv_s['honeypot_enabled'] ) ? ' is-off' : ''; ?>" data-wpfpv-card>
			<div class="wpfpv-card__head">
				<h2><?php esc_html_e( 'Honeypot', 'phone-validator-for-wpforms' ); ?></h2>
				<label class="wpfpv-switch wpfpv-switch--block">
					<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'honeypot_enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['honeypot_enabled'] ) ); ?>>
					<span class="wpfpv-switch__track" aria-hidden="true"></span>
					<span class="wpfpv-switch__text"><?php esc_html_e( 'Add a hidden trap field to every form', 'phone-validator-for-wpforms' ); ?></span>
				</label>
			</div>
			<p class="wpfpv-card__intro"><?php esc_html_e( 'People never see the field; simple bots fill it in and are rejected.', 'phone-validator-for-wpforms' ); ?></p>
			<div class="wpfpv-card__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-hp-name"><?php esc_html_e( 'Honeypot field name', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="text" id="wpfpv-hp-name" class="regular-text code" autocomplete="off" spellcheck="false" name="<?php echo $wpfpv_name( 'honeypot_field_name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( WPFPV_Settings::honeypot_name() ); ?>">
						<button type="button" class="button" id="wpfpv-hp-generate"><?php esc_html_e( 'Generate', 'phone-validator-for-wpforms' ); ?></button>
						<p class="description"><?php esc_html_e( 'A random name is created for your site. Letters, digits and underscore, 6 to 32 characters, starting with a letter. Change it if bots learn it.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Note for administrators', 'phone-validator-for-wpforms' ); ?></th>
					<td>
						<label class="wpfpv-switch">
							<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'honeypot_indicator' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['honeypot_indicator'] ) ); ?>>
							<span class="wpfpv-switch__track" aria-hidden="true"></span>
							<span class="wpfpv-switch__text"><?php esc_html_e( 'Show a note under forms while I am logged in as an administrator', 'phone-validator-for-wpforms' ); ?></span>
						</label>
						<p class="description"><?php esc_html_e( 'Visitors never see this note.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Blocked bots', 'phone-validator-for-wpforms' ); ?></th>
					<td>
						<strong class="wpfpv-big"><?php echo esc_html( number_format_i18n( $wpfpv_blocked ) ); ?></strong>
						<button type="submit" class="button" form="wpfpv-reset-form" data-wpfpv-confirm="reset"><?php esc_html_e( 'Reset counter', 'phone-validator-for-wpforms' ); ?></button>
					</td>
				</tr>
			</table>
			</div>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card wpfpv-card--toggle<?php echo empty( $wpfpv_s['limits_enabled'] ) ? ' is-off' : ''; ?>" data-wpfpv-card>
			<div class="wpfpv-card__head">
				<h2><?php esc_html_e( 'Submission limits', 'phone-validator-for-wpforms' ); ?></h2>
				<label class="wpfpv-switch wpfpv-switch--block">
					<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'limits_enabled' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['limits_enabled'] ) ); ?>>
					<span class="wpfpv-switch__track" aria-hidden="true"></span>
					<span class="wpfpv-switch__text"><?php esc_html_e( 'Limit repeated submissions from one IP address', 'phone-validator-for-wpforms' ); ?></span>
				</label>
			</div>
			<div class="wpfpv-card__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-throttle"><?php esc_html_e( 'Minimum interval, minutes', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-throttle" class="small-text" min="0" max="1440" name="<?php echo $wpfpv_name( 'throttle_time' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['throttle_time'] ); ?>">
						<p class="description"><?php esc_html_e( 'How long one visitor must wait between two submissions of the same form. 0 turns it off.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-daily"><?php esc_html_e( 'Submissions per IP in 24 hours', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-daily" class="small-text" min="0" max="1000" name="<?php echo $wpfpv_name( 'daily_limit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['daily_limit'] ); ?>">
						<p class="description"><?php esc_html_e( 'Maximum accepted submissions from one IP address within the last 24 hours. 0 turns it off. Offices and mobile networks share addresses, so keep the number generous.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-scope"><?php esc_html_e( 'Count', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<select id="wpfpv-scope" name="<?php echo $wpfpv_name( 'limit_scope' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
							<option value="site" <?php selected( $wpfpv_s['limit_scope'], 'site' ); ?>><?php esc_html_e( 'All forms together', 'phone-validator-for-wpforms' ); ?></option>
							<option value="form" <?php selected( $wpfpv_s['limit_scope'], 'form' ); ?>><?php esc_html_e( 'Each form separately', 'phone-validator-for-wpforms' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wpfpv-whitelist"><?php esc_html_e( 'Trusted IP addresses', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<textarea id="wpfpv-whitelist" class="large-text code" rows="3" placeholder="203.0.113.7&#10;198.51.100.0/24" name="<?php echo $wpfpv_name( 'whitelist_ips' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"><?php echo esc_textarea( $wpfpv_s['whitelist_ips'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP address or range (CIDR) per line. The limits do not apply to them, for example your office.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Administrators', 'phone-validator-for-wpforms' ); ?></th>
					<td>
						<label class="wpfpv-switch">
							<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'exempt_admins' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['exempt_admins'] ) ); ?>>
							<span class="wpfpv-switch__track" aria-hidden="true"></span>
							<span class="wpfpv-switch__text"><?php esc_html_e( 'Do not limit logged-in administrators (handy for testing)', 'phone-validator-for-wpforms' ); ?></span>
						</label>
					</td>
				</tr>
			</table>
			</div>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card" data-wpfpv-card>
			<h2><?php esc_html_e( 'Visitor IP address', 'phone-validator-for-wpforms' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-proxy"><?php esc_html_e( 'Site is behind', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<select id="wpfpv-proxy" name="<?php echo $wpfpv_name( 'proxy_mode' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
							<option value="none" <?php selected( $wpfpv_s['proxy_mode'], 'none' ); ?>><?php esc_html_e( 'Nothing: visitors connect directly', 'phone-validator-for-wpforms' ); ?></option>
							<option value="cloudflare" <?php selected( $wpfpv_s['proxy_mode'], 'cloudflare' ); ?>><?php esc_html_e( 'Cloudflare', 'phone-validator-for-wpforms' ); ?></option>
							<option value="custom" <?php selected( $wpfpv_s['proxy_mode'], 'custom' ); ?>><?php esc_html_e( 'Another proxy or load balancer', 'phone-validator-for-wpforms' ); ?></option>
						</select>
						<p class="description">
							<?php
							printf(
								/* translators: %s: IP address of the current visitor as seen by the plugin. */
								esc_html__( 'Your address as the plugin sees it now: %s. If every visitor shows the same address, choose the right option; otherwise the limits would block everyone at once.', 'phone-validator-for-wpforms' ),
								'<code>' . esc_html( '' === $wpfpv_ip ? '?' : $wpfpv_ip ) . '</code>'
							);
							?>
						</p>
					</td>
				</tr>
				<tr class="wpfpv-custom-proxy">
					<th scope="row"><label for="wpfpv-header"><?php esc_html_e( 'Header with the visitor address', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<select id="wpfpv-header" name="<?php echo $wpfpv_name( 'proxy_header' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>">
							<?php foreach ( WPFPV_IP::allowed_headers() as $wpfpv_header ) : ?>
								<option value="<?php echo esc_attr( $wpfpv_header ); ?>" <?php selected( $wpfpv_s['proxy_header'], $wpfpv_header ); ?>><?php echo esc_html( $wpfpv_header ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr class="wpfpv-custom-proxy">
					<th scope="row"><label for="wpfpv-trusted"><?php esc_html_e( 'Trusted proxy addresses', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<textarea id="wpfpv-trusted" class="large-text code" rows="3" name="<?php echo $wpfpv_name( 'trusted_proxies' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>"><?php echo esc_textarea( $wpfpv_s['trusted_proxies'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One IP address or range per line. The header is trusted only when the request comes from one of them; anyone else could forge it.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
			</table>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

		<section class="wpfpv-card wpfpv-card--toggle<?php echo empty( $wpfpv_s['enable_logging'] ) ? ' is-off' : ''; ?>" data-wpfpv-card>
			<div class="wpfpv-card__head">
				<h2><?php esc_html_e( 'History', 'phone-validator-for-wpforms' ); ?></h2>
				<label class="wpfpv-switch wpfpv-switch--block">
					<input type="checkbox" role="switch" name="<?php echo $wpfpv_name( 'enable_logging' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="1" <?php checked( ! empty( $wpfpv_s['enable_logging'] ) ); ?>>
					<span class="wpfpv-switch__track" aria-hidden="true"></span>
					<span class="wpfpv-switch__text"><?php esc_html_e( 'Record rejected and accepted submissions', 'phone-validator-for-wpforms' ); ?></span>
				</label>
			</div>
			<p class="wpfpv-card__intro"><?php esc_html_e( 'The history contains phone numbers and IP addresses of visitors. Keep it only as long as you need it.', 'phone-validator-for-wpforms' ); ?></p>
			<div class="wpfpv-card__body">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wpfpv-clean"><?php esc_html_e( 'Delete entries older than, days', 'phone-validator-for-wpforms' ); ?></label></th>
					<td>
						<input type="number" id="wpfpv-clean" class="small-text" min="0" max="3650" name="<?php echo $wpfpv_name( 'auto_clean_logs' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the closure. ?>" value="<?php echo esc_attr( $wpfpv_s['auto_clean_logs'] ); ?>">
						<p class="description"><?php esc_html_e( '0 keeps entries until the file reaches its size limit.', 'phone-validator-for-wpforms' ); ?></p>
					</td>
				</tr>
			</table>
			</div>
			<div class="wpfpv-card__footer">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'phone-validator-for-wpforms' ); ?></button>
			</div>
		</section>

	</form>

	<form method="post" id="wpfpv-reset-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpfpv_reset_honeypot">
		<?php wp_nonce_field( 'wpfpv_reset_honeypot' ); ?>
	</form>

	<?php WPFPV_Admin::render_support_footer(); ?>
</div>
