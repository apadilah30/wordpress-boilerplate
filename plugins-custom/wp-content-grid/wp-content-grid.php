<?php
/**
 * Plugin Name: Content Grid
 * Description: Elementor widget — featured news grid (2 large + small, numbered) and video grid (uniform + play icon) for any post type.
 * Version:     1.0.0
 * Author:      Apadilah
 * Text Domain: wp-content-grid
 * Requires Plugins: elementor
 *
 * Drag "Content Grid" from the Elementor widgets panel. Pick a "Layout
 * Style":
 *   - "news"  — first N posts shown as 2 large featured cards + the rest
 *               as small cards, each with a numbered badge (01, 02, ...).
 *   - "video" — uniform grid, each card with a centered play icon overlay
 *               on the featured image.
 *
 * Both layouts just query the chosen post type's latest posts by date —
 * no taxonomy grouping involved (unlike the Taxonomy Grid plugin).
 *
 * @package WpContentGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPCG_PATH', plugin_dir_path(__FILE__));
define('WPCG_URL', plugin_dir_url(__FILE__));

/**
 * Enqueue styles. No custom JS needed — cards just link to the single
 * post permalink, no AJAX/lightbox behavior.
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wpcg-styles',
        WPCG_URL . 'assets/css/content-grid.css',
        [],
        '1.0.0'
    );
});

/**
 * Dedicated Elementor widget category, same reasoning as Taxonomy Grid's
 * own category — predictable regardless of Elementor's built-in list.
 */
add_action('elementor/elements/categories_registered', static function ($elements_manager): void {
    $elements_manager->add_category('wpcg-category', [
        'title' => __('Content Grid', 'wp-content-grid'),
        'icon'  => 'fa fa-th-large',
    ]);
});

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    require_once WPCG_PATH . 'includes/class-elementor-widget.php';
    $widgets_manager->register(new WPCG_Elementor_Widget());
});

/**
 * Post types eligible as a "Source" option (public post types).
 *
 * @return array<string,string> slug => label
 */
function wpcg_get_post_type_options(): array
{
    $options = [];
    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
        $options[$post_type->name] = sprintf('%s (%s)', $post_type->labels->name, $post_type->name);
    }
    return $options;
}

/**
 * Render the whole widget: section header (title + "view all" link) plus
 * whichever grid layout is selected.
 *
 * @param array<string,mixed> $atts
 */
function wpcg_render_grid(array $atts): string
{
    if (empty($atts['post_type']) || !get_post_type_object($atts['post_type'])) {
        return '<p class="wpcg-error">' . esc_html__('Error: a valid Post Type must be selected.', 'wp-content-grid') . '</p>';
    }

    $layout = $atts['layout'] === 'video' ? 'video' : 'news';
    $count  = max(1, (int) $atts['count']);

    $query = new WP_Query([
        'post_type'      => $atts['post_type'],
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);

    if (!$query->have_posts()) {
        return '<p class="wpcg-empty">' . esc_html__('No items found.', 'wp-content-grid') . '</p>';
    }

    ob_start();
    ?>
    <div class="wpcg-wrapper wpcg-layout-<?php echo esc_attr($layout); ?> <?php echo esc_attr($atts['class']); ?>">
        <?php if ($atts['show_header'] === 'true' && ($atts['title'] !== '' || $atts['view_all_text'] !== '')): ?>
            <div class="wpcg-header">
                <?php if ($atts['title'] !== ''): ?>
                    <h2 class="wpcg-title"><?php echo esc_html($atts['title']); ?></h2>
                <?php endif; ?>
                <?php if ($atts['view_all_text'] !== '' && $atts['view_all_url'] !== ''): ?>
                    <a class="wpcg-view-all" href="<?php echo esc_url($atts['view_all_url']); ?>">
                        <?php echo esc_html($atts['view_all_text']); ?>
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($layout === 'news'): ?>
            <?php echo wpcg_render_news_items($query, (int) $atts['featured_count']); ?>
        <?php else: ?>
            <?php echo wpcg_render_video_items($query); ?>
        <?php endif; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

/**
 * News layout: first $featured_count posts as large cards (image +
 * content side by side), the rest as small cards — both with a numbered
 * badge based purely on display order (01, 02, ...).
 */
function wpcg_render_news_items(WP_Query $query, int $featured_count): string
{
    $index = 0;

    ob_start();
    ?>
    <div class="wpcg-news-grid">
        <?php while ($query->have_posts()): $query->the_post(); ?>
            <?php
            $index++;
            $is_featured = $index <= max(0, $featured_count);
            $badge       = sprintf('%02d', $index);
            ?>
            <article class="wpcg-news-card <?php echo $is_featured ? 'wpcg-news-card-featured' : 'wpcg-news-card-regular'; ?>">
                <a class="wpcg-news-card-link" href="<?php the_permalink(); ?>">
                    <?php if ($is_featured && has_post_thumbnail()): ?>
                        <div class="wpcg-news-thumb">
                            <?php the_post_thumbnail('medium_large'); ?>
                        </div>
                    <?php endif; ?>

                    <div class="wpcg-news-body">
                        <span class="wpcg-news-badge"><?php echo esc_html($badge); ?></span>
                        <span class="wpcg-news-date"><?php echo esc_html(get_the_date('j F Y')); ?></span>
                        <h3 class="wpcg-news-card-title"><?php the_title(); ?></h3>
                        <span class="wpcg-news-read-more">
                            <?php esc_html_e('Baca Selengkapnya', 'wp-content-grid'); ?>
                            <span aria-hidden="true">&rarr;</span>
                        </span>
                    </div>
                </a>
            </article>
        <?php endwhile; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Video layout: uniform grid, thumbnail with a centered play icon
 * overlay, title + meta below.
 */
function wpcg_render_video_items(WP_Query $query): string
{
    ob_start();
    ?>
    <div class="wpcg-video-grid">
        <?php while ($query->have_posts()): $query->the_post(); ?>
            <article class="wpcg-video-card">
                <a class="wpcg-video-card-link" href="<?php the_permalink(); ?>">
                    <div class="wpcg-video-thumb">
                        <?php if (has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium_large'); ?>
                        <?php endif; ?>
                        <span class="wpcg-video-play" aria-hidden="true">
                            <svg viewBox="0 0 24 24" focusable="false"><path d="M8 5v14l11-7z"/></svg>
                        </span>
                    </div>
                    <div class="wpcg-video-body">
                        <h3 class="wpcg-video-card-title"><?php the_title(); ?></h3>
                        <span class="wpcg-video-meta">
                            <?php echo esc_html(get_the_date('j F Y')); ?>
                            &middot;
                            <?php echo esc_html(get_the_author()); ?>
                        </span>
                    </div>
                </a>
            </article>
        <?php endwhile; ?>
    </div>
    <?php
    return ob_get_clean();
}
