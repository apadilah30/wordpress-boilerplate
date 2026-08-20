<?php
/**
 * Custom Post Type: wpba_announcement
 *
 * Contoh registrasi CPT native (tanpa plugin CPT UI). Kalau lu prefer visual
 * builder, plugin CPT UI dari boilerplate juga bisa dipakai untuk buat CPT
 * lain dengan lebih cepat. File ini untuk kejelasan pattern & i18n.
 *
 * @package WpBoilerplateAnnouncement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', 'wpba_register_post_type' );

function wpba_register_post_type(): void {
	$labels = array(
		'name'               => _x( 'Announcements', 'post type general name', 'wp-boilerplate-announcement' ),
		'singular_name'      => _x( 'Announcement', 'post type singular name', 'wp-boilerplate-announcement' ),
		'menu_name'          => _x( 'Announcements', 'admin menu', 'wp-boilerplate-announcement' ),
		'add_new'            => __( 'Add New', 'wp-boilerplate-announcement' ),
		'add_new_item'       => __( 'Add New Announcement', 'wp-boilerplate-announcement' ),
		'edit_item'          => __( 'Edit Announcement', 'wp-boilerplate-announcement' ),
		'new_item'           => __( 'New Announcement', 'wp-boilerplate-announcement' ),
		'view_item'          => __( 'View Announcement', 'wp-boilerplate-announcement' ),
		'search_items'       => __( 'Search Announcements', 'wp-boilerplate-announcement' ),
		'not_found'          => __( 'No announcements found.', 'wp-boilerplate-announcement' ),
		'not_found_in_trash' => __( 'No announcements found in Trash.', 'wp-boilerplate-announcement' ),
	);

	register_post_type(
		WPBA_POST_TYPE,
		array(
			'labels'            => $labels,
			'description'       => __( 'Site-wide announcement banners.', 'wp-boilerplate-announcement' ),
			'public'            => false,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_rest'      => true,
			'menu_icon'         => 'dashicons-megaphone',
			'menu_position'     => 25,
			'capability_type'   => 'post',
			'supports'          => array( 'title', 'editor', 'excerpt' ),
			'has_archive'       => false,
			'rewrite'           => false,
			'query_var'         => false,
			'delete_with_user'  => false,
		)
	);
}

add_action( 'init', 'wpba_register_post_meta' );

function wpba_register_post_meta(): void {
	register_post_meta(
		WPBA_POST_TYPE,
		WPBA_META_COLOR,
		array(
			'type'              => 'string',
			'description'       => __( 'Background color (hex).', 'wp-boilerplate-announcement' ),
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '#0073aa',
			'sanitize_callback' => static fn( $value ) => sanitize_hex_color( (string) $value ) ?: '#0073aa',
			'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
		)
	);

	register_post_meta(
		WPBA_POST_TYPE,
		WPBA_META_LINK,
		array(
			'type'              => 'string',
			'description'       => __( 'Optional CTA URL.', 'wp-boilerplate-announcement' ),
			'single'            => true,
			'show_in_rest'      => true,
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => static fn(): bool => current_user_can( 'edit_posts' ),
		)
	);
}

add_action( 'add_meta_boxes_' . WPBA_POST_TYPE, 'wpba_add_meta_boxes' );

function wpba_add_meta_boxes(): void {
	add_meta_box(
		'wpba_appearance',
		__( 'Appearance', 'wp-boilerplate-announcement' ),
		'wpba_render_meta_box',
		WPBA_POST_TYPE,
		'side',
		'default'
	);
}

function wpba_render_meta_box( \WP_Post $post ): void {
	wp_nonce_field( 'wpba_save_meta', 'wpba_meta_nonce' );

	$color = (string) get_post_meta( $post->ID, WPBA_META_COLOR, true );
	$link  = (string) get_post_meta( $post->ID, WPBA_META_LINK, true );
	$color = '' !== $color ? $color : '#0073aa';
	?>
	<p>
		<label for="wpba-color">
			<strong><?php esc_html_e( 'Background color', 'wp-boilerplate-announcement' ); ?></strong>
		</label><br>
		<input type="text" id="wpba-color" name="wpba_bg_color"
			value="<?php echo esc_attr( $color ); ?>"
			placeholder="#0073aa" class="widefat">
	</p>
	<p>
		<label for="wpba-link">
			<strong><?php esc_html_e( 'CTA link (optional)', 'wp-boilerplate-announcement' ); ?></strong>
		</label><br>
		<input type="url" id="wpba-link" name="wpba_cta_url"
			value="<?php echo esc_attr( $link ); ?>"
			placeholder="https://example.com" class="widefat">
	</p>
	<?php
}

add_action( 'save_post_' . WPBA_POST_TYPE, 'wpba_save_meta', 10, 2 );

function wpba_save_meta( int $post_id, \WP_Post $post ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['wpba_meta_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpba_meta_nonce'] ) ), 'wpba_save_meta' )
	) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$color_raw = isset( $_POST['wpba_bg_color'] ) ? (string) wp_unslash( $_POST['wpba_bg_color'] ) : '';
	$color     = sanitize_hex_color( $color_raw );
	if ( null !== $color && '' !== $color ) {
		update_post_meta( $post_id, WPBA_META_COLOR, $color );
	}

	$link_raw = isset( $_POST['wpba_cta_url'] ) ? (string) wp_unslash( $_POST['wpba_cta_url'] ) : '';
	update_post_meta( $post_id, WPBA_META_LINK, esc_url_raw( $link_raw ) );
}
