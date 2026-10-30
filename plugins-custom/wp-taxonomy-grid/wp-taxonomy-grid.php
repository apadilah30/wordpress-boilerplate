<?php
/**
 * Plugin Name: Taxonomy Grid
 * Description: Elementor widget — dynamic master-detail layout for any CPT with any taxonomy.
 * Version:     4.0.0
 * Author:      Apadilah
 * Text Domain: wp-taxonomy-grid
 * Requires Plugins: elementor
 *
 * Elementor widget "Taxonomy Grid" with two layout modes:
 *
 *   "detail" (default) — Government-service style layout:
 *     Header banner + breadcrumb + search + sidebar accordion (category > posts)
 *     + right panel showing the FULL content of the currently active post.
 *
 *   "grid" — Legacy layout:
 *     Sidebar category list + right panel showing a GRID of posts (excerpts)
 *     belonging to the active category.
 *
 * Drag "Taxonomy Grid" from the Elementor widgets panel — no shortcode needed.
 * All settings (post type, taxonomy, mode, header/breadcrumb/search text,
 * sidebar width, colors) are configurable from the Elementor Content/Style
 * tabs, with live preview in the editor.
 *
 * Taxonomy naming convention for this project: each post type gets its
 * OWN dedicated taxonomy (not the shared built-in "category"), named
 * "{post_type}_category" — e.g. "services_category", "news_category".
 * Create these via CPT UI, attached to only that one post type, then pick
 * them from the widget's Taxonomy dropdown.
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPTG_PATH', plugin_dir_path(__FILE__));
define('WPTG_URL', plugin_dir_url(__FILE__));

// Admin-only concern, zero Elementor dependency — loaded unconditionally
// (unlike class-elementor-widget.php, which is gated behind the
// elementor/widgets/register hook).
require_once WPTG_PATH . 'includes/class-video-meta-box.php';

/**
 * Enqueue scripts and styles.
 *
 * Also fires inside the Elementor editor's preview iframe, since that iframe
 * loads the normal front-end template (and therefore wp_enqueue_scripts).
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wptg-styles',
        WPTG_URL . 'assets/css/taxonomy-grid.css',
        [],
        '4.0.0'
    );

    wp_enqueue_script(
        'wptg-modal',
        WPTG_URL . 'assets/js/modal.js',
        [],
        '4.0.0',
        true
    );

    wp_enqueue_script(
        'wptg-scripts',
        WPTG_URL . 'assets/js/taxonomy-grid.js',
        ['wptg-modal'],
        '4.0.0',
        true
    );

    wp_localize_script('wptg-scripts', 'wptg_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wptg_nonce'),
    ]);
});

/**
 * Register a dedicated Elementor widget category so "Taxonomy Grid" always
 * has a valid, predictable category regardless of Elementor's built-in
 * category list changing between versions.
 */
add_action('elementor/elements/categories_registered', static function ($elements_manager): void {
    $elements_manager->add_category('wptg-category', [
        'title' => __('Taxonomy Grid', 'wp-taxonomy-grid'),
        'icon'  => 'fa fa-folder-open',
    ]);
});

/**
 * Register the "Taxonomy Grid" Elementor widget.
 *
 * Loaded lazily inside this hook, which only fires when Elementor itself is
 * active — so the Widget_Base-dependent class file never loads (and can
 * never fatal-error) if Elementor is deactivated.
 */
add_action('elementor/widgets/register', static function ($widgets_manager): void {
    require_once WPTG_PATH . 'includes/class-elementor-widget.php';
    $widgets_manager->register(new WPTG_Elementor_Widget());
});

/**
 * Post types eligible as a "Source" option in the widget (public, with at
 * least one registered taxonomy — otherwise there's nothing to browse by).
 *
 * @return array<string,string> slug => label
 */
function wptg_get_post_type_options(): array
{
    $options = [];
    foreach (get_post_types(['public' => true], 'objects') as $post_type) {
        $options[$post_type->name] = sprintf('%s (%s)', $post_type->labels->name, $post_type->name);
    }
    return $options;
}

/**
 * Taxonomies eligible as a "Source" option in the widget.
 *
 * @return array<string,string> slug => label
 */
function wptg_get_taxonomy_options(): array
{
    $options = [];
    foreach (get_taxonomies([], 'objects') as $taxonomy) {
        $options[$taxonomy->name] = sprintf('%s (%s)', $taxonomy->labels->name, $taxonomy->name);
    }
    return $options;
}

/**
 * Render an Elementor icon control value, falling back to raw HTML if no
 * icon was picked (or Elementor's icon renderer isn't available).
 *
 * @param mixed  $icon_value   Value from an Elementor ICONS control (['value' => ..., 'library' => ...]).
 * @param string $fallback_html Raw HTML/entity to use when no icon is set.
 */
function wptg_render_icon($icon_value, string $fallback_html = ''): string
{
    if (is_array($icon_value) && !empty($icon_value['value']) && class_exists('\Elementor\Icons_Manager')) {
        ob_start();
        \Elementor\Icons_Manager::render_icon($icon_value, ['aria-hidden' => 'true']);
        $rendered = ob_get_clean();
        if ($rendered !== '') {
            return $rendered;
        }
    }

    return $fallback_html;
}

/**
 * Resolve widget settings into final markup. Shared by the Elementor widget
 * so validation, defaults, and both render modes live in exactly one place.
 *
 * Note: shortcode_atts() is a generic "merge with defaults" WP core helper —
 * reused here purely for that behavior, not because this is a shortcode.
 *
 * @param array<string,mixed> $raw_atts            Settings coming from the Elementor widget.
 * @param string|null         $forced_instance_id   Stable instance id to use instead of generating
 *                                                   a new uuid (Elementor passes its own widget id
 *                                                   here so Style-tab CSS selectors stay in sync).
 */
function wptg_resolve_and_render(array $raw_atts, ?string $forced_instance_id = null): string
{
    $atts = shortcode_atts([
        'post_type'          => '',
        'taxonomy'           => '',
        'mode'               => 'detail',
        'active_first'       => 'true',
        'class'              => '',
        // grid mode
        'show_posts'         => -1,
        'title'              => '',
        'columns'            => 'auto',
        // detail mode
        'show_header'        => 'true',
        'header_subtitle'    => '',
        'header_title'       => '',
        'show_breadcrumb'    => 'true',
        'breadcrumb_home'    => __('Beranda', 'wp-taxonomy-grid'),
        'breadcrumb_middle'  => '',
        'breadcrumb_current' => '',
        'show_search'        => 'true',
        'search_placeholder' => __('Cari...', 'wp-taxonomy-grid'),
        // icons (Elementor ICONS control value arrays, or null for CSS/HTML fallback)
        'breadcrumb_icon'    => null,
        'search_icon'        => null,
    ], $raw_atts);

    if (empty($atts['post_type'])) {
        return '<p class="wptg-error">' . esc_html__('Error: post_type is required.', 'wp-taxonomy-grid') . '</p>';
    }

    if (empty($atts['taxonomy'])) {
        return '<p class="wptg-error">' . esc_html__('Error: taxonomy is required.', 'wp-taxonomy-grid') . '</p>';
    }

    $post_type_obj = get_post_type_object($atts['post_type']);
    if (!$post_type_obj) {
        return '<p class="wptg-error">' . sprintf(
            esc_html__('Error: Post type "%s" not found.', 'wp-taxonomy-grid'),
            esc_html($atts['post_type'])
        ) . '</p>';
    }

    if (!taxonomy_exists($atts['taxonomy'])) {
        return '<p class="wptg-error">' . sprintf(
            esc_html__('Error: Taxonomy "%s" not found.', 'wp-taxonomy-grid'),
            esc_html($atts['taxonomy'])
        ) . '</p>';
    }

    $taxonomy_obj = get_taxonomy($atts['taxonomy']);
    if (!in_array($atts['post_type'], $taxonomy_obj->object_type, true)) {
        return '<p class="wptg-error">' . sprintf(
            esc_html__('Error: Taxonomy "%1$s" is not registered for post type "%2$s".', 'wp-taxonomy-grid'),
            esc_html($atts['taxonomy']),
            esc_html($atts['post_type'])
        ) . '</p>';
    }

    // Terms scoped to THIS post type only (see wptg_get_terms_for_post_type()).
    $terms = wptg_get_terms_for_post_type($atts['taxonomy'], $atts['post_type']);

    if (empty($terms)) {
        return '<p class="wptg-empty">' . esc_html__('No items found.', 'wp-taxonomy-grid') . '</p>';
    }

    $instance_id = $forced_instance_id ?? ('wptg-' . wp_generate_uuid4());
    $mode        = $atts['mode'] === 'grid' ? 'grid' : 'detail';

    if ($mode === 'grid') {
        return wptg_render_grid_mode($atts, $terms, $taxonomy_obj, $instance_id);
    }

    return wptg_render_detail_mode($atts, $terms, $post_type_obj, $instance_id);
}

/**
 * Get taxonomy terms that have at least one published post of a specific post type.
 *
 * WordPress core get_terms() returns the term count across ALL post types
 * attached to that taxonomy, not scoped to one post type. This matters a lot
 * when a taxonomy (e.g. "category") is shared between default posts and a
 * custom post type: without scoping, the category list would include terms
 * that only have blog posts and zero items of the current CPT.
 *
 * @param string $taxonomy  Taxonomy slug.
 * @param string $post_type Post type slug.
 * @return array<int,object{term_id:int,name:string,slug:string,count:int}>
 */
function wptg_get_terms_for_post_type(string $taxonomy, string $post_type): array
{
    global $wpdb;

    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT p.ID) AS count
             FROM {$wpdb->terms} t
             INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
             INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
             WHERE tt.taxonomy = %s
               AND p.post_type = %s
               AND p.post_status = 'publish'
             GROUP BY t.term_id
             HAVING count > 0
             ORDER BY t.name ASC",
            $taxonomy,
            $post_type
        )
    );

    return $results ?: [];
}

/**
 * Check whether a post's name effectively matches its own category's name
 * (compared as normalized slugs — case/accents/extra spaces ignored).
 *
 * Used to decide whether the sidebar should merge "category row" + "single
 * post row" into one combined row, instead of showing a category heading
 * with exactly one redundant child that repeats the same name.
 *
 * @param string $term_name Taxonomy term name.
 * @param string $post_title Post title.
 */
function wptg_term_post_names_match(string $term_name, string $post_title): bool
{
    return sanitize_title($term_name) === sanitize_title($post_title);
}

/**
 * Get posts (ID + title only) belonging to a term, for sidebar listing.
 *
 * @param int    $term_id   Term ID.
 * @param string $post_type Post type slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array<int,object{ID:int,title:string}>
 */
function wptg_get_posts_for_term(int $term_id, string $post_type, string $taxonomy): array
{
    $query = new WP_Query([
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ],
        ],
    ]);

    $posts = [];
    foreach ($query->posts as $post_id) {
        $posts[] = (object) [
            'ID'    => $post_id,
            'title' => get_the_title($post_id),
        ];
    }

    return $posts;
}

/* -------------------------------------------------------------------------
 * DETAIL MODE — header + breadcrumb + search + sidebar accordion + single
 * post full content on the right. Matches the government-service layout.
 * ---------------------------------------------------------------------- */

/**
 * Render full "detail" mode markup.
 *
 * @param array<string,mixed>    $atts          Shortcode attributes (already merged with defaults).
 * @param array<int,object>      $terms         Terms scoped to post type.
 * @param \WP_Post_Type          $post_type_obj Post type object.
 * @param string                 $instance_id   Unique instance id for this shortcode render.
 */
function wptg_render_detail_mode(array $atts, array $terms, \WP_Post_Type $post_type_obj, string $instance_id): string
{
    $show_header     = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
    $show_breadcrumb = filter_var($atts['show_breadcrumb'], FILTER_VALIDATE_BOOLEAN);
    $show_search     = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $active_first    = filter_var($atts['active_first'], FILTER_VALIDATE_BOOLEAN);

    // Sensible defaults: subtitle = post type label ("Layanan"),
    // title = the current page/post title (the page the shortcode sits on).
    $header_subtitle = $atts['header_subtitle'] !== '' ? $atts['header_subtitle'] : $post_type_obj->labels->name;
    $header_title     = $atts['header_title'] !== '' ? $atts['header_title'] : get_the_title();

    $breadcrumb_middle  = $atts['breadcrumb_middle'] !== '' ? $atts['breadcrumb_middle'] : $header_subtitle;
    $breadcrumb_current = $atts['breadcrumb_current'] !== '' ? $atts['breadcrumb_current'] : $header_title;

    $breadcrumb_sep_html = wptg_render_icon($atts['breadcrumb_icon'] ?? null, '&rarr;');
    $search_icon_html    = wptg_render_icon($atts['search_icon'] ?? null, '&#128269;');

    // Fetch term -> posts pairs first (without deciding the active post yet).
    $term_posts_pairs = [];
    foreach ($terms as $term) {
        $posts = wptg_get_posts_for_term((int) $term->term_id, $atts['post_type'], $atts['taxonomy']);
        if (empty($posts)) {
            continue;
        }
        $term_posts_pairs[] = ['term' => $term, 'posts' => $posts];
    }

    // Decide the default active post with correct priority:
    //   1) The post actually being viewed right now, if this widget's
    //      post_type matches the current singular query (e.g. the widget
    //      sits on that post's own single template/page). This MUST win
    //      over "first post" — otherwise opening a post directly shows the
    //      sidebar highlighting a completely unrelated item (looks "dead").
    //   2) Otherwise, the first post overall, if active_first is enabled.
    $default_active_id = 0;
    $current_post_id   = (is_singular($atts['post_type']) && get_queried_object_id())
        ? (int) get_queried_object_id()
        : 0;

    if ($current_post_id > 0) {
        foreach ($term_posts_pairs as $pair) {
            foreach ($pair['posts'] as $post) {
                if ((int) $post->ID === $current_post_id) {
                    $default_active_id = $current_post_id;
                    break 2;
                }
            }
        }
    }

    if ($default_active_id === 0 && $active_first && !empty($term_posts_pairs)) {
        $default_active_id = (int) $term_posts_pairs[0]['posts'][0]->ID;
    }

    // Build sidebar groups, flagging ones that should merge category+post
    // into a single row.
    $groups = [];
    foreach ($term_posts_pairs as $pair) {
        $term  = $pair['term'];
        $posts = $pair['posts'];

        // Merge category row + post row into one when there's exactly one
        // post and its name matches the category's name (e.g. category
        // "Perubahan Data Paspor" containing only post "Perubahan Data
        // Paspor") — avoids showing the same label twice in the sidebar.
        $is_merged = count($posts) === 1 && wptg_term_post_names_match($term->name, $posts[0]->title);

        $groups[] = [
            'term'      => $term,
            'posts'     => $posts,
            'is_merged' => $is_merged,
        ];
    }

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-detail <?php echo esc_attr($atts['class']); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($atts['taxonomy']); ?>"
         data-mode="detail">

        <?php if ($show_header): ?>
            <div class="wptg-section-top">
                <header class="wptg-header">
                    <div class="wptg-header-flex">
                        <span class="wptg-header-line wptg-header-line-start" aria-hidden="true"></span>
                        <div class="wptg-header-inner">
                            <?php if ($header_subtitle !== ''): ?>
                                <span class="wptg-header-subtitle"><?php echo esc_html($header_subtitle); ?></span>
                            <?php endif; ?>
                            <?php if ($header_title !== ''): ?>
                                <h1 class="wptg-header-title"><?php echo esc_html($header_title); ?></h1>
                            <?php endif; ?>
                        </div>
                        <span class="wptg-header-line wptg-header-line-end" aria-hidden="true"></span>
                    </div>
                </header>
            </div>
        <?php endif; ?>

        <div class="wptg-section-body">
            <?php if ($show_breadcrumb): ?>
                <nav class="wptg-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'wp-taxonomy-grid'); ?>">
                    <ol>
                        <li class="wptg-breadcrumb-home"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($atts['breadcrumb_home']); ?></a></li>
                        <?php if ($breadcrumb_middle !== ''): ?>
                            <li class="wptg-breadcrumb-sep" aria-hidden="true"><?php echo $breadcrumb_sep_html; ?></li>
                            <li class="wptg-breadcrumb-middle"><?php echo esc_html($breadcrumb_middle); ?></li>
                        <?php endif; ?>
                        <?php if ($breadcrumb_current !== ''): ?>
                            <li class="wptg-breadcrumb-sep" aria-hidden="true"><?php echo $breadcrumb_sep_html; ?></li>
                            <li class="wptg-breadcrumb-current" aria-current="page"><?php echo esc_html($breadcrumb_current); ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
            <?php endif; ?>

            <div class="wptg-body">
                <aside class="wptg-sidebar">
                    <?php if ($show_search): ?>
                        <div class="wptg-search">
                            <input type="search" class="wptg-search-input"
                                   placeholder="<?php echo esc_attr($atts['search_placeholder']); ?>"
                                   aria-label="<?php echo esc_attr($atts['search_placeholder']); ?>">
                            <span class="wptg-search-icon" aria-hidden="true"><?php echo $search_icon_html; ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="wptg-terms">
                        <?php foreach ($groups as $group): ?>
                            <?php
                            $term      = $group['term'];
                            $term_slug = esc_attr(strtolower($term->name));
                            ?>
                            <?php if ($group['is_merged']): ?>
                                <?php
                                $post      = $group['posts'][0];
                                $is_active = ((int) $post->ID === $default_active_id);
                                ?>
                                <div class="wptg-term-group wptg-term-merged <?php echo $is_active ? 'active' : ''; ?>"
                                     data-term-id="<?php echo esc_attr($term->term_id); ?>"
                                     data-term-name="<?php echo $term_slug; ?>"
                                     data-post-id="<?php echo esc_attr($post->ID); ?>">
                                    <div class="wptg-term-head">
                                        <button type="button" class="wptg-post-trigger wptg-term-merged-trigger" aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                                            <span class="wptg-term-divider wptg-term-divider-merged" aria-hidden="true"></span>
                                            <span class="wptg-term-name"><?php echo esc_html($term->name); ?></span>
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="wptg-term-group open" data-term-id="<?php echo esc_attr($term->term_id); ?>" data-term-name="<?php echo $term_slug; ?>">
                                    <div class="wptg-term-head">
                                        <button type="button" class="wptg-term-toggle" aria-expanded="true">
                                            <span class="wptg-term-head-left">
                                                <span class="wptg-term-divider wptg-term-divider-accordion" aria-hidden="true"></span>
                                                <span class="wptg-term-name"><?php echo esc_html($term->name); ?></span>
                                            </span>
                                            <span class="wptg-term-chevron" aria-hidden="true"></span>
                                        </button>
                                    </div>
                                    <ul class="wptg-term-posts">
                                    <?php foreach ($group['posts'] as $post): ?>
                                        <?php $is_active = ((int) $post->ID === $default_active_id); ?>
                                        <li class="wptg-post-li <?php echo $is_active ? 'active' : ''; ?>" data-post-id="<?php echo esc_attr($post->ID); ?>">
                                            <button type="button" class="wptg-post-trigger" aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                                                <?php echo esc_html($post->title); ?>
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <p class="wptg-no-results" style="display:none;"><?php esc_html_e('Tidak ditemukan.', 'wp-taxonomy-grid'); ?></p>
                    </div>
                </aside>

                <main class="wptg-detail-container">
                    <?php if ($default_active_id > 0): ?>
                        <?php echo wptg_render_single_post_detail($default_active_id); ?>
                    <?php else: ?>
                        <div class="wptg-placeholder">
                            <p><?php esc_html_e('Pilih salah satu item di samping untuk melihat detailnya.', 'wp-taxonomy-grid'); ?></p>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the full content (title + body) of a single post.
 *
 * $include_meta defaults to false so the legacy detail-mode call site
 * (wptg_render_detail_mode()) keeps producing byte-identical output to
 * before this parameter existed. Sections-mode callers (single_detail
 * content style, the Pengumuman/video modal fetch) pass true to also get
 * the hero image and date/author meta block.
 *
 * @param int  $post_id      Post ID.
 * @param bool $include_meta Whether to render the hero image + date/author meta block.
 * @return string
 */
function wptg_render_single_post_detail(int $post_id, bool $include_meta = false): string
{
    $elementor_content = '';
    if (class_exists('\Elementor\Plugin')) {
        $elementor_content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id);
    }

    ob_start();
    ?>
    <article class="wptg-detail-post" data-post-id="<?php echo esc_attr($post_id); ?>">
        <?php if ($include_meta && has_post_thumbnail($post_id)): ?>
            <div class="wptg-detail-hero"><?php echo get_the_post_thumbnail($post_id, 'large'); ?></div>
        <?php endif; ?>

        <h2 class="wptg-detail-title"><?php echo esc_html(get_the_title($post_id)); ?></h2>

        <?php if ($include_meta): ?>
            <div class="wptg-detail-meta">
                <span class="wptg-detail-date"><?php echo esc_html(get_the_date('', $post_id)); ?></span>
                <span class="wptg-detail-author"><?php echo esc_html(get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id))); ?></span>
            </div>
        <?php endif; ?>

        <div class="wptg-detail-content">
            <?php if (!empty($elementor_content)): ?>
                <?php echo $elementor_content; ?>
            <?php else: ?>
                <?php echo apply_filters('the_content', get_post_field('post_content', $post_id)); ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler: Get a single post's full content (detail mode).
 */
function wptg_ajax_get_single_post(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');

    $post_id      = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post_type    = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
    // Legacy JS call site never sends this field, so it stays false and the
    // AJAX response is byte-identical to before for legacy detail mode.
    $include_meta = isset($_POST['include_meta']) && $_POST['include_meta'] === '1';

    if ($post_id === 0 || empty($post_type)) {
        wp_send_json_error(['message' => 'Invalid parameters.']);
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== $post_type || $post->post_status !== 'publish') {
        wp_send_json_error(['message' => 'Post not found.']);
    }

    $html = wptg_render_single_post_detail($post_id, $include_meta);

    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_wptg_get_single_post', 'wptg_ajax_get_single_post');
add_action('wp_ajax_nopriv_wptg_get_single_post', 'wptg_ajax_get_single_post');

/* -------------------------------------------------------------------------
 * GRID MODE — legacy layout: category list on the left, grid of post
 * excerpts for the active category on the right.
 * ---------------------------------------------------------------------- */

/**
 * Render "grid" mode markup.
 *
 * @param array<string,mixed> $atts         Shortcode attributes.
 * @param array<int,object>   $terms        Terms scoped to post type.
 * @param \WP_Taxonomy         $taxonomy_obj Taxonomy object.
 * @param string               $instance_id  Unique instance id.
 */
function wptg_render_grid_mode(array $atts, array $terms, \WP_Taxonomy $taxonomy_obj, string $instance_id): string
{
    $active_first  = filter_var($atts['active_first'], FILTER_VALIDATE_BOOLEAN);
    $first_term_id = (int) ($terms[0]->term_id ?? 0);

    $title = $atts['title'] !== '' ? $atts['title'] : $taxonomy_obj->labels->name;

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-grid <?php echo esc_attr($atts['class']); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($atts['taxonomy']); ?>"
         data-mode="grid">

        <div class="wptg-categories">
            <h3 class="wptg-title"><?php echo esc_html($title); ?></h3>
            <ul class="wptg-categories-list">
                <?php foreach ($terms as $index => $term): ?>
                    <?php
                    $is_active    = $active_first && $index === 0;
                    $active_class = $is_active ? 'active' : '';
                    ?>
                    <li class="wptg-category-item <?php echo esc_attr($active_class); ?>"
                        data-term-id="<?php echo esc_attr($term->term_id); ?>">
                        <button type="button" class="wptg-category-btn" aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>">
                            <span class="wptg-category-name"><?php echo esc_html($term->name); ?></span>
                            <span class="wptg-count">(<?php echo esc_html($term->count); ?>)</span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="wptg-posts-container">
            <?php if ($active_first && $first_term_id): ?>
                <?php echo wptg_get_posts_html($first_term_id, $atts['post_type'], $atts['taxonomy'], (int) $atts['show_posts'], $atts['columns']); ?>
            <?php else: ?>
                <div class="wptg-placeholder">
                    <p><?php esc_html_e('Select a category to view items.', 'wp-taxonomy-grid'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Get posts HTML (grid) by term ID.
 *
 * @param int    $term_id   Term ID.
 * @param string $post_type Post type slug.
 * @param string $taxonomy  Taxonomy slug.
 * @param int    $limit     Number of posts (-1 for all).
 * @param string $columns   Grid columns.
 * @return string
 */
function wptg_get_posts_html(int $term_id, string $post_type, string $taxonomy, int $limit = -1, string $columns = 'auto'): string
{
    $args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'menu_order date',
        'order'          => 'ASC',
        'tax_query'      => [
            [
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ],
        ],
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<div class="wptg-empty"><p>' . esc_html__('No items in this category.', 'wp-taxonomy-grid') . '</p></div>';
    }

    $grid_style = '';
    if ($columns !== 'auto' && is_numeric($columns)) {
        $grid_style = 'style="grid-template-columns: repeat(' . absint($columns) . ', 1fr);"';
    }

    ob_start();
    ?>
    <div class="wptg-posts-grid" <?php echo $grid_style; ?>>
        <?php while ($query->have_posts()): $query->the_post(); ?>
            <?php
            $post_id = get_the_ID();

            $elementor_content = '';
            if (class_exists('\Elementor\Plugin')) {
                $elementor_content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id);
            }
            ?>
            <article class="wptg-post-item" data-post-id="<?php echo esc_attr($post_id); ?>">
                <?php if (!empty($elementor_content)): ?>
                    <?php echo $elementor_content; ?>
                <?php else: ?>
                    <div class="wptg-post-fallback">
                        <?php if (has_post_thumbnail()): ?>
                            <div class="wptg-post-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('medium_large'); ?>
                                </a>
                            </div>
                        <?php endif; ?>

                        <h4 class="wptg-post-title">
                            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                        </h4>

                        <div class="wptg-post-excerpt">
                            <?php echo wp_trim_words(get_the_excerpt(), 20); ?>
                        </div>

                        <a href="<?php the_permalink(); ?>" class="wptg-post-link">
                            <?php esc_html_e('View Details', 'wp-taxonomy-grid'); ?> &rarr;
                        </a>
                    </div>
                <?php endif; ?>
            </article>
        <?php endwhile; ?>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

/**
 * AJAX handler: Get posts grid by category (grid mode).
 */
function wptg_ajax_get_posts(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');

    $term_id   = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
    $taxonomy  = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
    $limit     = isset($_POST['limit']) ? (int) $_POST['limit'] : -1;

    if ($term_id === 0 || empty($post_type) || empty($taxonomy)) {
        wp_send_json_error(['message' => 'Invalid parameters.']);
    }

    if (!get_post_type_object($post_type) || !taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => 'Invalid post type or taxonomy.']);
    }

    $html = wptg_get_posts_html($term_id, $post_type, $taxonomy, $limit);

    wp_send_json_success(['html' => $html]);
}

add_action('wp_ajax_wptg_get_posts', 'wptg_ajax_get_posts');
add_action('wp_ajax_nopriv_wptg_get_posts', 'wptg_ajax_get_posts');

/* -------------------------------------------------------------------------
 * SECTIONS MODE — "Content Navigator": N independent sidebar sections
 * (flat_list or taxonomy_accordion), each with its own content style and
 * click behavior, sharing ONE content panel. See wptg_render_sections_widget().
 * ---------------------------------------------------------------------- */

/**
 * Normalize one "Sections" repeater row into a validated struct, or null
 * if its post_type/taxonomy no longer exist (e.g. deleted after the
 * widget was configured) — invalid rows are skipped entirely rather than
 * rendered broken.
 *
 * Note: an empty `section_post_type` falls into the same "unresolvable
 * post type" null-return branch below as any other invalid value. This is
 * safe because the repeater control itself (see
 * WPTG_Elementor_Widget::register_sections_controls()) gives
 * `section_post_type` a non-empty `default` of `'post'`, so Elementor
 * always saves a non-empty value for every NEW row added through the
 * editor UI from now on — an empty value here can only happen for a row
 * saved before that default existed, and treating it the same as any
 * other unresolvable post type (rather than special-casing it) is the
 * simplest correct behavior.
 *
 * @param array<string,mixed> $row   Raw repeater row from Elementor settings.
 * @param int                 $index Row's position in the repeater (stable per save).
 * @return array<string,mixed>|null
 */
function wptg_normalize_section_row(array $row, int $index): ?array
{
    $post_type     = sanitize_key($row['section_post_type'] ?? '');
    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->public) {
        return null;
    }

    $type = ($row['section_type'] ?? 'flat_list') === 'taxonomy_accordion' ? 'taxonomy_accordion' : 'flat_list';

    $taxonomy = '';
    if ($type === 'taxonomy_accordion') {
        $taxonomy     = sanitize_key($row['section_taxonomy'] ?? '');
        $taxonomy_obj = taxonomy_exists($taxonomy) ? get_taxonomy($taxonomy) : null;
        if (!$taxonomy_obj || !in_array($post_type, $taxonomy_obj->object_type, true)) {
            return null;
        }
    }

    // Enforce the fallback documented in section_content_style's own
    // 'description' text (see register_sections_controls()) — this is the
    // ONLY place that does so.
    $content_style = sanitize_key($row['section_content_style'] ?? '');
    if ($type === 'flat_list') {
        $content_style = 'numbered_feed';
    } elseif (!in_array($content_style, ['grid_thumbnail', 'list_thumbnail', 'single_detail'], true)) {
        $content_style = 'grid_thumbnail';
    }

    $click_behavior = sanitize_key($row['section_click_behavior'] ?? 'inline_detail');
    if (!in_array($click_behavior, ['inline_detail', 'modal', 'redirect_or_popup_player'], true)) {
        $click_behavior = 'inline_detail';
    }

    return [
        'index'          => $index,
        'label'          => sanitize_text_field($row['section_label'] ?? ''),
        'type'           => $type,
        'post_type'      => $post_type,
        'taxonomy'       => $taxonomy,
        'content_style'  => $content_style,
        'click_behavior' => $click_behavior,
        'enable_search'  => ($row['section_enable_search'] ?? 'yes') === 'yes',
        'posts_per_page' => min(48, max(1, absint($row['section_posts_per_page'] ?? 6))),
    ];
}

/**
 * Count of 'publish' posts for a post type — just enough to know whether a
 * flat_list section has anything to show, without fetching the posts
 * themselves (that happens later via wptg_query_section_items()).
 *
 * @param string $post_type Post type slug.
 */
function wptg_count_published_posts(string $post_type): int
{
    $counts = (array) wp_count_posts($post_type);
    return (int) ($counts['publish'] ?? 0);
}

/**
 * Given sections already annotated with 'has_items' (flat_list) or
 * 'terms'/'first_post_id' (taxonomy_accordion), pick the first one with
 * real content to show by default. Pure — no I/O.
 *
 * Generalizes the legacy $default_active_id priority logic in
 * wptg_render_detail_mode() (first post of the first term) across N
 * heterogeneous sections.
 *
 * @param array<int,array<string,mixed>> $sections Normalized + annotated sections.
 * @return array{section_index:int,term_id:int,post_id:int}|null
 */
function wptg_resolve_default_active(array $sections): ?array
{
    foreach ($sections as $section) {
        if ($section['type'] === 'flat_list') {
            if ($section['has_items']) {
                return ['section_index' => $section['index'], 'term_id' => 0, 'post_id' => 0];
            }
            continue;
        }

        if (empty($section['terms'])) {
            continue;
        }
        $first_term_id = (int) $section['terms'][0]->term_id;

        if ($section['content_style'] === 'single_detail') {
            if (($section['first_post_id'] ?? 0) > 0) {
                return ['section_index' => $section['index'], 'term_id' => $first_term_id, 'post_id' => (int) $section['first_post_id']];
            }
            continue; // this term has no posts; try the next section.
        }

        return ['section_index' => $section['index'], 'term_id' => $first_term_id, 'post_id' => 0];
    }

    return null;
}

/**
 * Query posts for one section's active term/page/search — backs both the
 * SSR content panel and the wptg_query_items AJAX action. single_detail
 * never calls this (it reuses wptg_get_posts_for_term() + the existing
 * single-post AJAX action instead).
 *
 * @param string $post_type      Post type slug.
 * @param string $taxonomy       Taxonomy slug, or '' for a flat_list section.
 * @param int    $term_id        Term ID, or 0 when $taxonomy is ''.
 * @param string $content_style  Unused here (kept for call-site symmetry with the renderer), but part of the signature per design.md.
 * @param int    $page           1-indexed page number.
 * @param int    $posts_per_page Page size.
 * @param string $search         Optional search term.
 */
function wptg_query_section_items(
    string $post_type,
    string $taxonomy,
    int $term_id,
    string $content_style,
    int $page,
    int $posts_per_page,
    string $search
): WP_Query {
    $args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }
    if ($taxonomy !== '' && $term_id > 0) {
        $args['tax_query'] = [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ]];
    }
    return new WP_Query($args);
}

/**
 * Pure — no I/O. Shared by the SSR content-panel renderer and the AJAX handler.
 *
 * @param int $page           1-indexed page number already fetched.
 * @param int $posts_per_page Page size.
 * @param int $found_posts    WP_Query::$found_posts for the same query.
 */
function wptg_has_more_pages(int $page, int $posts_per_page, int $found_posts): bool
{
    return ($page * $posts_per_page) < $found_posts;
}

/**
 * Maps a content_style to its list wrapper CSS class — single source of
 * truth, mirrored client-side by wptgListClassFor() in taxonomy-grid.js.
 *
 * @param string $content_style One of 'grid_thumbnail'|'list_thumbnail'|'numbered_feed'.
 */
function wptg_section_list_class(string $content_style): string
{
    return match ($content_style) {
        'grid_thumbnail' => 'wptg-posts-grid', // reuses the EXISTING grid CSS, no new grid layout rules needed.
        'list_thumbnail' => 'wptg-list-thumbnail',
        default          => 'wptg-numbered-feed',
    };
}

/**
 * Build the shared data-* attribute string for one clickable item.
 *
 * Consistent attribute rule for every per-item renderer below: the single
 * clickable element for one item ALWAYS carries data-post-id,
 * data-post-type, and data-click-behavior directly on itself — never left
 * for the browser to infer from an ancestor, since a clicked item can be
 * deeply nested while its ancestor .wptg-section-group describes the
 * section in general, not the one clicked post.
 *
 * @param int                   $post_id        Post ID.
 * @param string                $post_type      Post type slug.
 * @param string                $click_behavior 'inline_detail'|'modal'|'redirect_or_popup_player'.
 * @param array<string,string>  $extra          Additional raw `name="value"` pairs, already escaped by the caller.
 */
function wptg_build_item_attrs(int $post_id, string $post_type, string $click_behavior, array $extra = []): string
{
    $attrs = sprintf(
        ' data-post-id="%d" data-post-type="%s" data-click-behavior="%s"',
        $post_id,
        esc_attr($post_type),
        esc_attr($click_behavior)
    );

    foreach ($extra as $name => $value) {
        $attrs .= sprintf(' %s="%s"', $name, $value);
    }

    return $attrs;
}

/**
 * Numbered feed item — Berita Terbaru / Pengumuman (content_style = numbered_feed).
 * Thumbnail (left) + sequential badge number + date + clamped title +
 * "Baca Selengkapnya" affordance. The whole <article> is the single
 * clickable element (click_behavior lives on it directly), so a screen
 * reader / keyboard user has one unambiguous target — matching how
 * .wptg-post-trigger already behaves as a single full-row button in the
 * legacy sidebar.
 *
 * @param \WP_Post $post              Post to render.
 * @param int      $display_number   Sequential display position (not a post field).
 * @param bool     $is_first_overall Whether this is the first item on page 1 of an unfiltered query (renders visually larger).
 * @param string   $click_behavior   'inline_detail'|'modal'|'redirect_or_popup_player'.
 */
function wptg_render_item_numbered_feed(\WP_Post $post, int $display_number, bool $is_first_overall, string $click_behavior): string
{
    $extra = [];
    if ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-feed-item <?php echo $is_first_overall ? 'wptg-feed-item--featured' : ''; ?>" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-feed-thumb">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
            <?php endif; ?>
            <span class="wptg-feed-badge"><?php echo esc_html(sprintf('%02d', $display_number)); ?></span>
        </div>
        <div class="wptg-feed-body">
            <span class="wptg-feed-date"><?php echo esc_html(get_the_date('', $post->ID)); ?></span>
            <h3 class="wptg-feed-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
            <span class="wptg-feed-readmore"><?php esc_html_e('Baca Selengkapnya', 'wp-taxonomy-grid'); ?></span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Grid thumbnail item — Galeri Foto (content_style = grid_thumbnail).
 * Reuses the EXISTING `.wptg-post-item` box class from grid mode (border/
 * radius/hover transform already defined in taxonomy-grid.css) with a
 * `--photo` modifier for the caption layout, instead of a new unrelated
 * box class.
 *
 * @param \WP_Post $post           Post to render.
 * @param string   $click_behavior 'inline_detail'|'modal'|'redirect_or_popup_player'.
 */
function wptg_render_item_grid_thumbnail(\WP_Post $post, string $click_behavior): string
{
    $extra = [];
    if ($click_behavior === 'modal') {
        $image_src = wp_get_attachment_image_url(get_post_thumbnail_id($post->ID), 'large') ?: '';
        $extra['data-modal-type'] = 'image';
        $extra['data-image-src']  = esc_url($image_src);
        $extra['data-item-title'] = esc_attr(get_the_title($post->ID));
        $extra['data-item-date']  = esc_attr(get_the_date('', $post->ID));
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-post-item wptg-post-item--photo" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-photo-thumb">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium_large'); ?>
            <?php endif; ?>
        </div>
        <div class="wptg-photo-caption">
            <span class="wptg-photo-title"><?php echo esc_html(get_the_title($post->ID)); ?></span>
            <span class="wptg-photo-date"><?php echo esc_html(get_the_date('', $post->ID)); ?></span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Vertical list item with play-icon overlay — Galeri Video
 * (content_style = list_thumbnail). This is the ONLY renderer that
 * branches on click_behavior = redirect_or_popup_player's 3-way logic
 * (external URL -> attached video -> graceful post-detail fallback), per
 * FR-6/AC9 in requirements.md.
 *
 * @param \WP_Post $post           Post to render.
 * @param string   $click_behavior 'inline_detail'|'modal'|'redirect_or_popup_player'.
 */
function wptg_render_item_list_thumbnail(\WP_Post $post, string $click_behavior): string
{
    $extra = [];

    if ($click_behavior === 'redirect_or_popup_player') {
        $external_url = get_post_meta($post->ID, '_video_external_url', true);

        if (is_string($external_url) && $external_url !== '') {
            $extra['data-external-url'] = esc_url($external_url);
        } else {
            $video_attachments = get_attached_media('video', $post->ID);
            $video_attachment  = !empty($video_attachments) ? reset($video_attachments) : null;

            if ($video_attachment instanceof \WP_Post) {
                $extra['data-modal-type'] = 'video';
                $extra['data-video-src']  = esc_url(wp_get_attachment_url($video_attachment->ID));
                $poster_url               = get_the_post_thumbnail_url($post->ID, 'large');
                if ($poster_url) {
                    $extra['data-poster'] = esc_url($poster_url);
                }
            } else {
                // Graceful fallback (FR-6 point 4 / AC9): neither an
                // external URL nor a self-hosted attachment is set —
                // show the normal post-detail modal instead of a dead click.
                $extra['data-modal-type'] = 'post';
            }
        }
    } elseif ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-list-thumbnail-item" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-list-thumbnail-media">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
            <?php endif; ?>
            <span class="wptg-list-thumbnail-play" aria-hidden="true"></span>
        </div>
        <div class="wptg-list-thumbnail-body">
            <h3 class="wptg-list-thumbnail-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
            <span class="wptg-list-thumbnail-meta">
                <?php echo esc_html(get_the_date('', $post->ID)); ?>
                &middot;
                <?php echo esc_html(get_the_author_meta('display_name', (int) $post->post_author)); ?>
            </span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Dispatch to the right per-item renderer for one post.
 *
 * @param \WP_Post $post              Post to render.
 * @param string   $content_style     'numbered_feed'|'grid_thumbnail'|'list_thumbnail'.
 * @param int      $display_number    Sequential display position (numbered_feed only).
 * @param bool     $is_first_overall  Whether this is the first item overall (numbered_feed only).
 * @param string   $click_behavior    'inline_detail'|'modal'|'redirect_or_popup_player'.
 */
function wptg_render_section_item(\WP_Post $post, string $content_style, int $display_number, bool $is_first_overall, string $click_behavior): string
{
    return match ($content_style) {
        'grid_thumbnail' => wptg_render_item_grid_thumbnail($post, $click_behavior),
        'list_thumbnail' => wptg_render_item_list_thumbnail($post, $click_behavior),
        default          => wptg_render_item_numbered_feed($post, $display_number, $is_first_overall, $click_behavior),
    };
}

/**
 * Render one sidebar entry: either a flat nav button (flat_list) or a
 * taxonomy accordion (taxonomy_accordion). For content_style =
 * single_detail, the accordion nests individual POSTS per term — the same
 * shape as the legacy detail-mode sidebar — instead of just term buttons.
 *
 * The `open` class is applied to the ANCESTOR of each collapsible list
 * (.wptg-section-group for a taxonomy_accordion section's own
 * .wptg-section-terms, and .wptg-section-term-li for a single_detail
 * term's own .wptg-term-posts) — never on the <ul> itself — matching the
 * legacy `.wptg-term-group:not(.open) .wptg-term-posts` CSS convention
 * and bindAccordionToggle()'s own toggle target in taxonomy-grid.js.
 *
 * @param array<string,mixed>                                     $section        Normalized section (see wptg_normalize_section_row()).
 * @param array{section_index:int,term_id:int,post_id:int}|null   $default_active Output of wptg_resolve_default_active().
 */
function wptg_render_section_sidebar_group(array $section, ?array $default_active): string
{
    $is_section_active = $default_active !== null && $default_active['section_index'] === $section['index'];
    $is_accordion_open  = $section['type'] !== 'flat_list' && $is_section_active;

    ob_start();
    ?>
    <div class="wptg-section-group wptg-section-group--<?php echo esc_attr($section['type'] === 'flat_list' ? 'flat' : 'accordion'); ?><?php echo $is_accordion_open ? ' open' : ''; ?>"
         data-section-index="<?php echo esc_attr($section['index']); ?>"
         data-section-type="<?php echo esc_attr($section['type']); ?>"
         data-post-type="<?php echo esc_attr($section['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($section['taxonomy']); ?>"
         data-content-style="<?php echo esc_attr($section['content_style']); ?>"
         data-click-behavior="<?php echo esc_attr($section['click_behavior']); ?>"
         data-posts-per-page="<?php echo esc_attr($section['posts_per_page']); ?>">

        <?php if ($section['enable_search']): ?>
            <div class="wptg-section-search">
                <input type="search" class="wptg-section-search-input"
                       placeholder="<?php esc_attr_e('Cari...', 'wp-taxonomy-grid'); ?>"
                       aria-label="<?php esc_attr_e('Cari...', 'wp-taxonomy-grid'); ?>">
            </div>
            <?php // flat_list's search is AJAX-only and never toggles this
                  // element — only render it for taxonomy_accordion, whose
                  // search box stays a DOM filter (filterWithinSectionGroup()). ?>
            <?php if ($section['type'] !== 'flat_list'): ?>
                <p class="wptg-section-no-results" style="display:none;"><?php esc_html_e('Tidak ditemukan.', 'wp-taxonomy-grid'); ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($section['type'] === 'flat_list'): ?>
            <button type="button" class="wptg-section-nav-trigger <?php echo $is_section_active ? 'active' : ''; ?>"
                    aria-pressed="<?php echo $is_section_active ? 'true' : 'false'; ?>">
                <?php echo esc_html($section['label']); ?>
            </button>
        <?php else: ?>
            <button type="button" class="wptg-section-toggle" aria-expanded="<?php echo $is_accordion_open ? 'true' : 'false'; ?>">
                <span class="wptg-term-name"><?php echo esc_html($section['label']); ?></span>
                <span class="wptg-term-chevron" aria-hidden="true"></span>
            </button>
            <ul class="wptg-section-terms">
                <?php foreach ($section['terms'] as $term): ?>
                    <?php
                    $is_term_active = $is_section_active && $default_active['term_id'] === (int) $term->term_id;
                    $is_term_open   = $section['content_style'] === 'single_detail' && $is_term_active;
                    ?>
                    <li class="wptg-section-term-li <?php echo $is_term_active ? 'active' : ''; ?><?php echo $is_term_open ? ' open' : ''; ?>"
                        data-term-id="<?php echo esc_attr($term->term_id); ?>"
                        data-term-name="<?php echo esc_attr(strtolower($term->name)); ?>">
                        <?php if ($section['content_style'] === 'single_detail'): ?>
                            <?php
                            // Nests individual POSTS under each term — same
                            // shape as legacy detail mode. Reuses the cached
                            // query for the active term (built in
                            // wptg_render_sections_widget()) to avoid a
                            // double query; queries fresh for any other term.
                            $posts = $section['term_posts_cache'][(int) $term->term_id]
                                ?? wptg_get_posts_for_term((int) $term->term_id, $section['post_type'], $section['taxonomy']);
                            ?>
                            <button type="button" class="wptg-term-toggle" aria-expanded="<?php echo $is_term_open ? 'true' : 'false'; ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                            <ul class="wptg-term-posts">
                                <?php foreach ($posts as $post): ?>
                                    <?php $is_post_active = $is_term_active && $default_active['post_id'] === (int) $post->ID; ?>
                                    <li class="wptg-post-li <?php echo $is_post_active ? 'active' : ''; ?>">
                                        <button type="button" class="wptg-post-trigger"
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-post-type="<?php echo esc_attr($section['post_type']); ?>"
                                                data-click-behavior="inline_detail"
                                                aria-pressed="<?php echo $is_post_active ? 'true' : 'false'; ?>">
                                            <?php echo esc_html($post->title); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <button type="button" class="wptg-section-term-trigger" aria-pressed="<?php echo $is_term_active ? 'true' : 'false'; ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the active section's content panel for the INITIAL page load.
 * The HTML shape produced here (the `.wptg-section-items-wrap` +
 * `.wptg-load-more` button) is exactly what the client reconstructs
 * around `wptg_ajax_query_section_items()`'s JSON `html` payload on a
 * later AJAX switch/search — one shape, two call sites (SSR here, AJAX in
 * `wptg_ajax_query_section_items()` below), not two shapes.
 *
 * @param array<string,mixed>                                   $section Normalized + annotated section.
 * @param array{section_index:int,term_id:int,post_id:int}      $active  The resolved default-active entry for THIS section.
 */
function wptg_render_section_content_panel(array $section, array $active): string
{
    if ($section['content_style'] === 'single_detail') {
        return $active['post_id'] > 0
            ? wptg_render_single_post_detail($active['post_id'], true)
            : '<div class="wptg-placeholder"><p>' . esc_html__('Pilih salah satu item di samping.', 'wp-taxonomy-grid') . '</p></div>';
    }

    $query = wptg_query_section_items(
        $section['post_type'],
        $section['taxonomy'],
        $active['term_id'],
        $section['content_style'],
        1,
        $section['posts_per_page'],
        ''
    );

    $items_html = '';
    $i          = 0;
    foreach ($query->posts as $post) {
        $items_html .= wptg_render_section_item($post, $section['content_style'], $i + 1, $i === 0, $section['click_behavior']);
        $i++;
    }
    wp_reset_postdata();

    $has_more   = wptg_has_more_pages(1, $section['posts_per_page'], $query->found_posts);
    $list_class = wptg_section_list_class($section['content_style']);

    ob_start();
    ?>
    <div class="wptg-section-items-wrap"
         data-section-index="<?php echo esc_attr($section['index']); ?>"
         data-term-id="<?php echo esc_attr($active['term_id']); ?>"
         data-page="1">
        <div class="<?php echo esc_attr($list_class); ?>"><?php echo $items_html; ?></div>
        <?php if ($has_more): ?>
            <button type="button" class="wptg-load-more"><?php esc_html_e('Lihat Lebih Banyak', 'wp-taxonomy-grid'); ?></button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render the full "Sections" (Content Navigator) markup: a sidebar with N
 * sections (flat nav buttons or taxonomy accordions) + ONE shared content
 * panel showing whichever section/term/post is active.
 *
 * SSR vs AJAX-lazy-load: only the DEFAULT ACTIVE section's content panel
 * is rendered on the initial page load (mirrors the legacy detail mode's
 * `active_first` behavior — one section gets real content immediately for
 * perceived performance and a working no-JS fallback). Every other
 * section's items are fetched on first click via `wptg_query_items`
 * (flat_list/grid_thumbnail/list_thumbnail) or `wptg_get_single_post`
 * (single_detail).
 *
 * Deliberately reuses the legacy `.wptg-wrapper`/`.wptg-body`/
 * `.wptg-sidebar` class names (not new section-specific class names) for
 * the outermost structural elements — this is exactly what lets
 * register_style_container_controls() stay unconditional across both
 * Source Modes.
 *
 * @param array<string,mixed> $settings    Elementor get_settings_for_display() output.
 * @param string              $instance_id Stable instance id (same convention as wptg_resolve_and_render()).
 */
function wptg_render_sections_widget(array $settings, string $instance_id): string
{
    $rows     = $settings['wptg_sections'] ?? [];
    $sections = [];

    foreach ($rows as $i => $row) {
        $section = wptg_normalize_section_row($row, (int) $i);
        if ($section === null) {
            continue;
        }

        if ($section['type'] === 'flat_list') {
            $section['has_items'] = wptg_count_published_posts($section['post_type']) > 0;
        } else {
            $section['terms'] = wptg_get_terms_for_post_type($section['taxonomy'], $section['post_type']);

            if ($section['content_style'] === 'single_detail' && !empty($section['terms'])) {
                $first_term_id              = (int) $section['terms'][0]->term_id;
                $first_term_posts           = wptg_get_posts_for_term($first_term_id, $section['post_type'], $section['taxonomy']);
                $section['first_post_id']   = (int) ($first_term_posts[0]->ID ?? 0);
                // Cached so wptg_render_section_sidebar_group() doesn't re-query
                // the same term's posts a second time just below.
                $section['term_posts_cache'] = [$first_term_id => $first_term_posts];
            }
        }

        $sections[] = $section;
    }

    if (empty($sections)) {
        return '<p class="wptg-empty">' . esc_html__('No valid sections configured.', 'wp-taxonomy-grid') . '</p>';
    }

    $default_active = wptg_resolve_default_active($sections);

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-sections <?php echo esc_attr($settings['wptg_sections_class'] ?? ''); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-mode="sections">
        <div class="wptg-body wptg-sections-body">
            <aside class="wptg-sidebar wptg-sections-sidebar">
                <?php foreach ($sections as $section): ?>
                    <?php echo wptg_render_section_sidebar_group($section, $default_active); ?>
                <?php endforeach; ?>
            </aside>

            <main class="wptg-sections-content"
                  data-section-loaded="<?php echo $default_active !== null ? 'true' : 'false'; ?>">
                <?php if ($default_active !== null): ?>
                    <?php echo wptg_render_section_content_panel($sections[$default_active['section_index']], $default_active); ?>
                <?php else: ?>
                    <div class="wptg-placeholder">
                        <p><?php esc_html_e('Belum ada konten.', 'wp-taxonomy-grid'); ?></p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * AJAX handler: query one page of section items (switch section/term,
 * "Lihat Lebih Banyak" pagination, or a flat_list section's search-box
 * re-query). single_detail never calls this action — it has no
 * pagination/search-re-query UI and instead reuses wptg_get_single_post
 * directly, same as legacy detail mode.
 *
 * Mirrors wptg_ajax_get_posts()'s existing validation style
 * (get_post_type_object() + taxonomy_exists() + "is this taxonomy
 * actually registered for this post type") plus an additional
 * term-belongs-to-taxonomy check that wptg_ajax_get_posts() does NOT
 * already perform — this action writes every one of those checks fresh,
 * it does not assume wptg_ajax_get_posts() already covers them.
 */
function wptg_ajax_query_section_items(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');

    $post_type      = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    $taxonomy       = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
    $term_id        = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $content_style  = isset($_POST['content_style']) ? sanitize_key(wp_unslash($_POST['content_style'])) : 'numbered_feed';
    $click_behavior = isset($_POST['click_behavior']) ? sanitize_key(wp_unslash($_POST['click_behavior'])) : 'inline_detail';
    $page           = max(1, isset($_POST['page']) ? absint($_POST['page']) : 1);
    $posts_per_page = min(48, max(1, isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 6));
    $search         = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->public) {
        wp_send_json_error(['message' => 'Invalid post type.']);
    }

    // single_detail is intentionally NOT in this whitelist — it never
    // reaches this action (see the Sections repeater schema + client
    // architecture in design.md).
    if (!in_array($content_style, ['numbered_feed', 'grid_thumbnail', 'list_thumbnail'], true)) {
        wp_send_json_error(['message' => 'Invalid content style.']);
    }

    if ($taxonomy !== '') {
        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error(['message' => 'Invalid taxonomy.']);
        }
        $taxonomy_obj = get_taxonomy($taxonomy);
        if (!in_array($post_type, $taxonomy_obj->object_type, true)) {
            wp_send_json_error(['message' => 'Taxonomy not registered for this post type.']);
        }
        if ($term_id > 0 && !(get_term($term_id, $taxonomy) instanceof WP_Term)) {
            wp_send_json_error(['message' => 'Term not found for this taxonomy.']);
        }
    } else {
        $term_id = 0; // flat_list sections never take a term.
    }

    $query = wptg_query_section_items($post_type, $taxonomy, $term_id, $content_style, $page, $posts_per_page, $search);

    $display_offset = ($page - 1) * $posts_per_page;
    $html           = '';
    $i              = 0;
    foreach ($query->posts as $post) {
        $is_first_overall = ($page === 1 && $search === '' && $i === 0);
        $html             .= wptg_render_section_item($post, $content_style, $display_offset + $i + 1, $is_first_overall, $click_behavior);
        $i++;
    }
    wp_reset_postdata();

    wp_send_json_success([
        'html'     => $html,
        'has_more' => wptg_has_more_pages($page, $posts_per_page, $query->found_posts),
        'page'     => $page,
    ]);
}
add_action('wp_ajax_wptg_query_items', 'wptg_ajax_query_section_items');
add_action('wp_ajax_nopriv_wptg_query_items', 'wptg_ajax_query_section_items');
