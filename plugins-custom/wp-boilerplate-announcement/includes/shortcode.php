<?php
/**
 * Dynamic shortcode: [wpba_announcement]
 *
 * Attributes:
 *   id     = post ID spesifik dari CPT wpba_announcement
 *   latest = jumlah pengumuman terbaru (dipakai kalau id kosong; default 1)
 *   class  = CSS class tambahan
 *
 * Contoh pakai:
 *   [wpba_announcement]                       → tampilkan 1 pengumuman terbaru
 *   [wpba_announcement latest="3"]            → 3 pengumuman terbaru
 *   [wpba_announcement id="42"]               → pengumuman ID 42
 *   [wpba_announcement id="42" class="promo"] → dengan CSS class extra
 *
 * @package WpBoilerplateAnnouncement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'wpba_announcement', 'wpba_render_shortcode' );

/**
 * @param array<string,string>|string $atts
 */
function wpba_render_shortcode( $atts = array(), ?string $content = null ): string {
	$atts = shortcode_atts(
		array(
			'id'     => '',
			'latest' => '1',
			'class'  => '',
		),
		is_array( $atts ) ? $atts : array(),
		'wpba_announcement'
	);

	$posts = wpba_query_announcements( $atts );
	if ( empty( $posts ) ) {
		return '';
	}

	$extra_class = sanitize_html_class( $atts['class'] );
	$html        = '';
	foreach ( $posts as $post ) {
		$html .= wpba_render_announcement( $post, $extra_class );
	}

	return $html;
}

/**
 * @param array{id:string,latest:string,class:string} $atts
 * @return \WP_Post[]
 */
function wpba_query_announcements( array $atts ): array {
	$id = absint( $atts['id'] );
	if ( $id > 0 ) {
		$post = get_post( $id );
		if ( $post instanceof \WP_Post && WPBA_POST_TYPE === $post->post_type && 'publish' === $post->post_status ) {
			return array( $post );
		}
		return array();
	}

	$limit = max( 1, min( 10, absint( $atts['latest'] ) ) );
	$query = new \WP_Query(
		array(
			'post_type'      => WPBA_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		)
	);

	return $query->posts;
}

function wpba_render_announcement( \WP_Post $post, string $extra_class = '' ): string {
	$color = (string) get_post_meta( $post->ID, WPBA_META_COLOR, true );
	$link  = (string) get_post_meta( $post->ID, WPBA_META_LINK, true );
	$color = '' !== $color ? $color : '#0073aa';

	$title   = get_the_title( $post );
	$content = apply_filters( 'the_content', $post->post_content );

	$class     = trim( 'wpba-announcement ' . $extra_class );
	$style     = sprintf(
		'background:%s;color:#fff;padding:16px 20px;margin:0 0 12px;border-radius:6px;font-family:sans-serif;',
		esc_attr( $color )
	);
	$aria      = esc_attr__( 'Site announcement', 'wp-boilerplate-announcement' );
	$read_more = esc_html__( 'Read more', 'wp-boilerplate-announcement' );

	$cta_html = '';
	if ( '' !== $link ) {
		$cta_html = sprintf(
			' <a href="%s" style="color:#fff;text-decoration:underline;font-weight:600;">%s &rarr;</a>',
			esc_url( $link ),
			$read_more
		);
	}

	return sprintf(
		'<div class="%1$s" role="note" aria-label="%2$s" style="%3$s"><strong>%4$s</strong> %5$s%6$s</div>',
		esc_attr( $class ),
		$aria,
		$style,
		esc_html( $title ),
		wp_kses_post( $content ),
		$cta_html
	);
}
