<?php
/**
 * Legacy settings page + auto-render fallback banner.
 *
 * Kalau CPT `wpba_announcement` masih kosong, plugin tampilin banner sederhana
 * dari option `wpba_settings` (backward compat dengan versi 1.0.0). Setelah lu
 * publish minimal 1 CPT announcement, banner auto render lewat shortcode di
 * theme / Elementor widget.
 *
 * @package WpBoilerplateAnnouncement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'edit.php?post_type=' . WPBA_POST_TYPE,
			__( 'Legacy Banner Settings', 'wp-boilerplate-announcement' ),
			__( 'Legacy Banner', 'wp-boilerplate-announcement' ),
			'manage_options',
			'wpba-legacy',
			'wpba_render_legacy_settings_page'
		);
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'wpba_settings_group',
			WPBA_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => 'wpba_sanitize_legacy_settings',
				'default'           => array(),
			)
		);
	}
);

/**
 * @param mixed $input
 * @return array{legacy_enabled:bool,legacy_message:string,legacy_color:string}
 */
function wpba_sanitize_legacy_settings( $input ): array {
	$input = is_array( $input ) ? $input : array();
	$settings = array(
		'legacy_enabled' => ! empty( $input['legacy_enabled'] ),
		'legacy_message' => isset( $input['legacy_message'] )
			? sanitize_text_field( (string) $input['legacy_message'] )
			: '',
		'legacy_color'   => '',
	);
	$legacy_color = sanitize_hex_color( (string) ( $input['legacy_color'] ?? '' ) );
	$settings['legacy_color'] = '' !== $legacy_color && null !== $legacy_color ? $legacy_color : '#0073aa';

	return $settings;
}

function wpba_render_legacy_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$defaults = array(
		'legacy_enabled' => false,
		'legacy_message' => '',
		'legacy_color'   => '#0073aa',
	);
	$opts     = wp_parse_args( (array) get_option( WPBA_OPTION_KEY, array() ), $defaults );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Legacy Banner Settings', 'wp-boilerplate-announcement' ); ?></h1>
		<p class="description">
			<?php
			esc_html_e(
				'Fallback banner untuk kompatibilitas versi lama. Prefer buat Announcement CPT dan pakai shortcode [wpba_announcement].',
				'wp-boilerplate-announcement'
			);
			?>
		</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'wpba_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="wpba-legacy-enabled"><?php esc_html_e( 'Enable legacy banner', 'wp-boilerplate-announcement' ); ?></label>
					</th>
					<td>
						<input type="checkbox" id="wpba-legacy-enabled"
							name="wpba_settings[legacy_enabled]" value="1"
							<?php checked( $opts['legacy_enabled'] ); ?>>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpba-legacy-message"><?php esc_html_e( 'Message', 'wp-boilerplate-announcement' ); ?></label>
					</th>
					<td>
						<input type="text" id="wpba-legacy-message" class="regular-text"
							name="wpba_settings[legacy_message]"
							value="<?php echo esc_attr( $opts['legacy_message'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpba-legacy-color"><?php esc_html_e( 'Background color', 'wp-boilerplate-announcement' ); ?></label>
					</th>
					<td>
						<input type="text" id="wpba-legacy-color"
							name="wpba_settings[legacy_color]"
							value="<?php echo esc_attr( $opts['legacy_color'] ); ?>"
							placeholder="#0073aa">
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Auto render legacy banner (site-wide) hanya kalau CPT belum ada published post.
 */
add_action(
	'wp_body_open',
	static function (): void {
		$opts = (array) get_option( WPBA_OPTION_KEY, array() );
		if ( empty( $opts['legacy_enabled'] ) || empty( $opts['legacy_message'] ) ) {
			return;
		}

		$count   = wp_count_posts( WPBA_POST_TYPE );
		$has_cpt = isset( $count->publish ) && (int) $count->publish > 0;
		if ( $has_cpt ) {
			return;
		}

		$color = sanitize_hex_color( (string) ( $opts['legacy_color'] ?? '#0073aa' ) );
		$color = '' !== $color && null !== $color ? $color : '#0073aa';
		$message = (string) $opts['legacy_message'];
		printf(
			'<div class="wpba-banner wpba-banner--legacy" role="note" style="background:%s;color:#fff;padding:12px;text-align:center;font-family:sans-serif;">%s</div>',
			esc_attr( $color ),
			esc_html( $message )
		);
	}
);
