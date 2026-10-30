<?php
/**
 * Admin-only concern: the "External Video URL" meta box for the `videos`
 * post type (Content Navigator / Galeri Video, section_click_behavior =
 * redirect_or_popup_player).
 *
 * Plain functions, no class, despite the `class-` filename prefix matching
 * this plugin's include-naming convention — there is no instance state to
 * hold here, unlike `class-elementor-widget.php`.
 *
 * Has zero Elementor dependency, so it is required unconditionally from
 * `wp-taxonomy-grid.php` (not gated behind `elementor/widgets/register`).
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the "External Video URL" meta box on the `videos` post type.
 */
function wptg_register_video_meta_box(): void
{
    add_meta_box(
        'wptg_video_external_url',
        __('External Video URL', 'wp-taxonomy-grid'),
        'wptg_render_video_meta_box',
        'videos',
        'side'
    );
}
add_action('add_meta_boxes', 'wptg_register_video_meta_box');

/**
 * Render the meta box field.
 *
 * @param \WP_Post $post Current post being edited.
 */
function wptg_render_video_meta_box(\WP_Post $post): void
{
    wp_nonce_field('wptg_video_meta_save', 'wptg_video_meta_nonce');
    $value = get_post_meta($post->ID, '_video_external_url', true);
    ?>
    <label for="wptg_video_external_url" class="screen-reader-text"><?php esc_html_e('External Video URL', 'wp-taxonomy-grid'); ?></label>
    <input type="url" id="wptg_video_external_url" name="wptg_video_external_url"
           value="<?php echo esc_attr($value); ?>" class="widefat"
           placeholder="https://youtube.com/watch?v=...">
    <p class="description"><?php esc_html_e('Leave empty for self-hosted videos (attach the video file to this post instead). If filled, clicking this video in the "Galeri Video" section redirects here in a new tab.', 'wp-taxonomy-grid'); ?></p>
    <?php
}

/**
 * Save the "External Video URL" meta box field.
 *
 * @param int $post_id Post ID being saved.
 */
function wptg_save_video_meta_box(int $post_id): void
{
    // Skip autosaves/revisions — the nonce check below already catches a
    // plain autosave request (WordPress's autosave request never includes
    // our nonce field at all), but this guard also protects against an
    // autosave-with-stale-form-data case (e.g. the Heartbeat API
    // re-submitting cached field values) re-writing the same value —
    // a consistency/no-redundant-write guard, not a security boundary.
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!isset($_POST['wptg_video_meta_nonce']) || !wp_verify_nonce(wp_unslash($_POST['wptg_video_meta_nonce']), 'wptg_video_meta_save')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_url = isset($_POST['wptg_video_external_url']) ? wp_unslash($_POST['wptg_video_external_url']) : '';
    // esc_url_raw() is the WP core/WPCS convention for sanitizing a URL
    // immediately before persisting it to the database (e.g. used
    // throughout wp-includes for option/meta values); sanitize_url() is
    // the modern alias for the older clean_url(), conventionally used for
    // OUTPUT/display contexts, not storage.
    $clean_url = $raw_url !== '' ? esc_url_raw($raw_url) : '';
    update_post_meta($post_id, '_video_external_url', $clean_url);
}
add_action('save_post_videos', 'wptg_save_video_meta_box');
