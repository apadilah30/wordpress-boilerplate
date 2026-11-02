<?php
/**
 * Plugin Name: Content Grid
 * Description: Elementor widget — featured news grid (2 large + small, numbered) and video grid (uniform + play icon) for any post type.
 * Version:     1.2.0
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
 * Item links (since 1.1.0): by default a card links to the post's own page.
 * With "Link Mode: Content Navigator page" it links to a page holding a
 * Taxonomy Grid (Sections) widget, as
 *   {target}?wptg_section={post_type}&wptg_term={term_slug}
 * so that page opens the matching section/category. This plugin only knows
 * the two param names, it does not depend on the Taxonomy Grid code.
 *
 * Media Popup (since 1.2.0): with "Link Mode: Media Popup" a card is still a
 * normal link to the post, plus `data-wptg-media="<post_id>"`. The Taxonomy
 * Grid plugin (4.3.0+) listens for that attribute and opens a video / photo
 * popup. This is a SOFT dependency: when Taxonomy Grid is not active the card
 * simply links to the post. See wpcg_normalize_link_options().
 *
 * @package WpContentGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPCG_PATH', plugin_dir_path(__FILE__));
define('WPCG_URL', plugin_dir_url(__FILE__));
define('WPCG_VERSION', '1.2.0');

/**
 * Enqueue styles. This plugin has no JS of its own: cards are plain links.
 * The Media Popup script and styles belong to Taxonomy Grid and are requested
 * by the widget (see WPCG_Elementor_Widget::get_script_depends()).
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wpcg-styles',
        WPCG_URL . 'assets/css/content-grid.css',
        [],
        WPCG_VERSION
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
 * Clean up the "Item Link" settings once per render, so every card uses the
 * same values (see wpcg_build_item_url()).
 *
 * A "navigator" link without a Target Page URL would be a dead link, so it
 * quietly falls back to the normal post permalink. The same happens for
 * "media_popup" when the Taxonomy Grid plugin (which owns the popup) is not
 * active; 'popup_missing' then tells the editor note to show up.
 *
 * @param array<string,mixed> $atts Needs post_type; link_mode, target_url, link_taxonomy, target_external, target_nofollow are optional.
 * @return array{mode:string,target_url:string,taxonomy:string,external:bool,nofollow:bool,popup_missing:bool}
 */
function wpcg_normalize_link_options(array $atts): array
{
    $target_url = trim((string) ($atts['target_url'] ?? ''));
    $mode       = (string) ($atts['link_mode'] ?? 'permalink');
    $mode       = in_array($mode, ['navigator', 'media_popup'], true) ? $mode : 'permalink';

    if ($mode === 'navigator' && $target_url === '') {
        $mode = 'permalink';
    }

    // Checked at render time (not at load time), because plugins load in
    // alphabetical order and Taxonomy Grid comes after this one.
    $popup_missing = $mode === 'media_popup' && !function_exists('wptg_build_media_modal_payload');
    if ($popup_missing) {
        $mode = 'permalink';
    }

    return [
        'mode'          => $mode,
        'target_url'    => $target_url,
        'taxonomy'      => $mode === 'navigator'
            ? wpcg_resolve_link_taxonomy((string) ($atts['post_type'] ?? ''), (string) ($atts['link_taxonomy'] ?? ''))
            : '',
        'external'      => !empty($atts['target_external']),
        'nofollow'      => !empty($atts['target_nofollow']),
        'popup_missing' => $popup_missing,
    ];
}

/**
 * Ask WordPress to load the Media Popup script and styles (owned by Taxonomy
 * Grid) when they are registered. Safety net on top of the widget's
 * get_script_depends(): Taxonomy Grid already loads them on every page, and
 * enqueueing a handle that is not registered would be silently ignored anyway.
 */
function wpcg_enqueue_media_popup_assets(): void
{
    if (wp_script_is('wptg-media-popup', 'registered')) {
        wp_enqueue_script('wptg-media-popup');
    }
    if (wp_style_is('wptg-styles', 'registered')) {
        wp_enqueue_style('wptg-styles');
    }
}

/**
 * True while the Elementor editor (or its preview) renders the widget.
 */
function wpcg_is_elementor_editor(): bool
{
    return class_exists('\Elementor\Plugin')
        && isset(\Elementor\Plugin::$instance->editor)
        && \Elementor\Plugin::$instance->editor->is_edit_mode();
}

/**
 * Decide which taxonomy holds the "category" of this post type's items.
 *
 * Order: the taxonomy typed in the widget (if it belongs to the post type),
 * then "{post_type}_category" (this project's naming convention), then the
 * first public hierarchical taxonomy of the post type. Returns '' when the
 * post type has none, and the link then only carries the section.
 *
 * @param string $post_type  Post type slug.
 * @param string $configured Taxonomy typed in the widget, may be ''.
 */
function wpcg_resolve_link_taxonomy(string $post_type, string $configured): string
{
    $configured = sanitize_key($configured);
    if ($configured !== '' && is_object_in_taxonomy($post_type, $configured)) {
        return $configured;
    }

    $conventional = $post_type . '_category';
    if (is_object_in_taxonomy($post_type, $conventional)) {
        return $conventional;
    }

    foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
        if ($taxonomy->hierarchical && $taxonomy->public) {
            return $taxonomy->name;
        }
    }

    return '';
}

/**
 * Slug of the first category of a post, or '' if it has none.
 *
 * WordPress' default "Uncategorized" term is skipped when the post has
 * another term, because it would open a useless category on the target page.
 * If it is the only term, it is still used.
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy slug.
 */
function wpcg_get_first_term_slug(int $post_id, string $taxonomy): string
{
    $terms = get_the_terms($post_id, $taxonomy);
    if (!is_array($terms) || $terms === []) {
        return '';
    }

    $default_category_id = $taxonomy === 'category' ? (int) get_option('default_category') : 0;

    foreach ($terms as $term) {
        if ($term->slug !== 'uncategorized' && (int) $term->term_id !== $default_category_id) {
            return $term->slug;
        }
    }

    $first = reset($terms);
    return $first->slug;
}

/**
 * The one place that decides where a card links to. Every card in every
 * layout (news featured, news small, video) calls this.
 *
 *  - mode "permalink": the post's own page (the behavior before 1.1.0).
 *  - mode "media_popup": also the post's own page. That href is the fallback
 *    for visitors without JavaScript, search engines and "open in new tab";
 *    the popup itself comes from the data attribute added by
 *    wpcg_build_item_link_attrs().
 *  - mode "navigator": {Target Page URL}?wptg_section={post_type}&wptg_term={term_slug}.
 *    The term is left out when the post type has no taxonomy or the post has
 *    no term. The Taxonomy Grid plugin validates both values on arrival.
 *
 * The URL is NOT escaped here: callers pass it through esc_url().
 *
 * @param int                                                                              $post_id Post ID.
 * @param array{mode:string,target_url:string,taxonomy:string,external:bool,nofollow:bool,popup_missing:bool} $link    Output of wpcg_normalize_link_options().
 */
function wpcg_build_item_url(int $post_id, array $link): string
{
    if ($link['mode'] !== 'navigator') {
        $permalink = get_permalink($post_id);
        return is_string($permalink) ? $permalink : '';
    }

    $args = ['wptg_section' => get_post_type($post_id)];

    if ($link['taxonomy'] !== '') {
        $term_slug = wpcg_get_first_term_slug($post_id, $link['taxonomy']);
        if ($term_slug !== '') {
            $args['wptg_term'] = $term_slug;
        }
    }

    return add_query_arg($args, $link['target_url']);
}

/**
 * Extra <a> attributes for a card.
 *  - "navigator" mode: from the URL control's "Open in new window" / "Add
 *    nofollow" checkboxes.
 *  - "media_popup" mode: `data-wptg-media="<post_id>"`, the hook the Taxonomy
 *    Grid popup script listens to.
 *  - permalink mode keeps the old markup (no extra attributes).
 * Built only from fixed strings and an integer, so the result is safe to echo as is.
 *
 * @param array{mode:string,target_url:string,taxonomy:string,external:bool,nofollow:bool,popup_missing:bool} $link    Output of wpcg_normalize_link_options().
 * @param int                                                                                              $post_id Post ID of the card.
 */
function wpcg_build_item_link_attrs(array $link, int $post_id): string
{
    if ($link['mode'] === 'media_popup') {
        return sprintf(' data-wptg-media="%d" aria-haspopup="dialog"', $post_id);
    }

    if ($link['mode'] !== 'navigator') {
        return '';
    }

    $rel = [];
    if ($link['external']) {
        $rel[] = 'noopener';
    }
    if ($link['nofollow']) {
        $rel[] = 'nofollow';
    }

    $attrs = $link['external'] ? ' target="_blank"' : '';
    if ($rel !== []) {
        $attrs .= ' rel="' . implode(' ', $rel) . '"';
    }

    return $attrs;
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
    $link   = wpcg_normalize_link_options($atts);

    if ($link['mode'] === 'media_popup') {
        wpcg_enqueue_media_popup_assets();
    }

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
        <?php if ($link['popup_missing'] && wpcg_is_elementor_editor() && current_user_can('edit_posts')): ?>
            <?php // Only editors see this, never visitors. The cards still work (they link to the post). ?>
            <p class="wpcg-editor-note"><?php esc_html_e('Media Popup butuh plugin Taxonomy Grid aktif. Sementara kartu membuka halaman post.', 'wp-content-grid'); ?></p>
        <?php endif; ?>
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
            <?php echo wpcg_render_news_items($query, (int) $atts['featured_count'], $link); ?>
        <?php else: ?>
            <?php echo wpcg_render_video_items($query, $link); ?>
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
 *
 * @param array{mode:string,target_url:string,taxonomy:string,external:bool,nofollow:bool,popup_missing:bool} $link Output of wpcg_normalize_link_options().
 */
function wpcg_render_news_items(WP_Query $query, int $featured_count, array $link): string
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
                <a class="wpcg-news-card-link" href="<?php echo esc_url(wpcg_build_item_url((int) get_the_ID(), $link)); ?>"<?php echo wpcg_build_item_link_attrs($link, (int) get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings and an integer only. ?>>
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
 *
 * @param array{mode:string,target_url:string,taxonomy:string,external:bool,nofollow:bool,popup_missing:bool} $link Output of wpcg_normalize_link_options().
 */
function wpcg_render_video_items(WP_Query $query, array $link): string
{
    ob_start();
    ?>
    <div class="wpcg-video-grid">
        <?php while ($query->have_posts()): $query->the_post(); ?>
            <article class="wpcg-video-card">
                <a class="wpcg-video-card-link" href="<?php echo esc_url(wpcg_build_item_url((int) get_the_ID(), $link)); ?>"<?php echo wpcg_build_item_link_attrs($link, (int) get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed strings and an integer only. ?>>
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
