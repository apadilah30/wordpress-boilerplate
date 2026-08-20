<?php
/**
 * Uninstall handler — dijalankan saat plugin di-uninstall dari WP admin.
 * Bersihkan semua data:
 * - Option 'wpba_settings'
 * - Semua post CPT 'wpba_announcement' beserta meta-nya
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wpba_settings' );

$posts = get_posts(
	array(
		'post_type'      => 'wpba_announcement',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $posts as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}
