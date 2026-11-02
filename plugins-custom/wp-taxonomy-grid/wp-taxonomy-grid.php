<?php
/**
 * Plugin Name: Taxonomy Grid
 * Description: Elementor widget — dynamic master-detail layout for any CPT with any taxonomy.
 * Version:     4.6.0
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
 * Deep link (Sections mode, since 4.2.0): a page URL like
 *   /berita/?wptg_section=videos&wptg_term=profil-kantor-imigrasi
 * opens the section whose Post Type is "videos" with that term (slug)
 * active. The Content Grid plugin builds such links; the two plugins only
 * share these two param names, not code. See wptg_resolve_deep_link().
 *
 * Media Popup (since 4.3.0): a card with `data-wptg-media="<post_id>"` opens
 * ONE popup for videos and photos (see includes/media-popup.php and
 * assets/js/media-popup.js). Sections "Item Click Behavior = Media Popup"
 * and the Content Grid plugin ("Link Mode = Media Popup") both use it.
 *
 * Empty State (since 4.4.0): the widget ALWAYS draws its full layout (header,
 * breadcrumb, search, sidebar, content panel). Parts that have no data show
 * grey skeleton blocks, never an error or "empty" text. See
 * wptg_render_skeleton(). Only logged-in users who can edit posts also see
 * one small note that says why it is empty (wptg_render_editor_note()).
 *
 * Document List + Document Preview (since 4.5.0): the "Informasi" page
 * lists documents (Standar Pelayanan, Maklumat Pelayanan). Content Style
 * "Document List" draws one card per post; Item Click Behavior "Document
 * Preview" opens the SAME popup as Media Popup, with a file viewer inside
 * (PDF through the bundled PDF.js, images, Word through Microsoft's viewer).
 * The files of a post are edited in the "Document Files" meta box (see
 * includes/class-document-meta-box.php). The post types themselves
 * (service-standards, service-pledges) are created in CPT UI, not here.
 *
 * Document card layout (since 4.6.0): head row = icon (left, changeable in
 * Content > Document Card) + title + upload date; cover image BELOW the head
 * row with the "Lihat" / "Unduh" pills on its top-right corner. See
 * wptg_render_item_document_list().
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPTG_PATH', plugin_dir_path(__FILE__));
define('WPTG_URL', plugin_dir_url(__FILE__));
// One place for the version, used as the cache-busting `ver` of every asset below.
define('WPTG_VERSION', '4.6.0');

// Admin-only concern, zero Elementor dependency — loaded unconditionally
// (unlike class-elementor-widget.php, which is gated behind the
// elementor/widgets/register hook).
require_once WPTG_PATH . 'includes/class-video-meta-box.php';
// "Document Files" meta box + wptg_get_document_files(), the one reader of that meta.
require_once WPTG_PATH . 'includes/class-document-meta-box.php';
// Popup data (video / photo / post) + its AJAX action. No Elementor dependency either.
require_once WPTG_PATH . 'includes/media-popup.php';

/**
 * Enqueue scripts and styles.
 *
 * Also fires inside the Elementor editor's preview iframe, since that iframe
 * loads the normal front-end template (and therefore wp_enqueue_scripts).
 *
 * Everything loads on every page. That is also what lets another plugin
 * (Content Grid) open the Media Popup on pages without a Taxonomy Grid
 * widget: the handles `wptg-styles` and `wptg-media-popup` are always
 * registered, so they can be listed as dependencies.
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wptg-styles',
        WPTG_URL . 'assets/css/taxonomy-grid.css',
        [],
        WPTG_VERSION
    );

    wp_enqueue_script(
        'wptg-modal',
        WPTG_URL . 'assets/js/modal.js',
        [],
        WPTG_VERSION,
        true
    );

    wp_enqueue_script(
        'wptg-scripts',
        WPTG_URL . 'assets/js/taxonomy-grid.js',
        ['wptg-modal'],
        WPTG_VERSION,
        true
    );

    wp_localize_script('wptg-scripts', 'wptg_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wptg_nonce'),
    ]);

    // The document viewer (PDF.js loader, file tabs, image, Word) draws inside
    // the popup. It only defines window.WptgDocumentViewer; PDF.js itself is
    // downloaded later, the first time a PDF is opened.
    wp_enqueue_script(
        'wptg-document-viewer',
        WPTG_URL . 'assets/js/document-viewer.js',
        ['wptg-modal'],
        WPTG_VERSION,
        true
    );

    wp_enqueue_script(
        'wptg-media-popup',
        WPTG_URL . 'assets/js/media-popup.js',
        ['wptg-modal', 'wptg-document-viewer'],
        WPTG_VERSION,
        true
    );

    // Own object (not wptg_ajax) so the popup also works when only this
    // handle is enqueued. Texts live here so they can be translated.
    wp_localize_script('wptg-media-popup', 'wptg_media', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wptg_nonce'),
        'i18n'     => [
            'loading'            => __('Memuat...', 'wp-taxonomy-grid'),
            'error'              => __('Konten tidak bisa dimuat. Coba lagi.', 'wp-taxonomy-grid'),
            'close'              => __('Tutup', 'wp-taxonomy-grid'),
            'files'              => __('Daftar file', 'wp-taxonomy-grid'),
            'download'           => __('Unduh', 'wp-taxonomy-grid'),
            'openFile'           => __('Buka file', 'wp-taxonomy-grid'),
            'pdfLoading'         => __('Memuat dokumen...', 'wp-taxonomy-grid'),
            'pdfError'           => __('Dokumen ini tidak bisa ditampilkan di sini.', 'wp-taxonomy-grid'),
            'pdfPassword'        => __('Dokumen ini dilindungi password, jadi tidak bisa ditampilkan di sini.', 'wp-taxonomy-grid'),
            'pdfCorrupt'         => __('File dokumen ini rusak atau bukan PDF yang valid.', 'wp-taxonomy-grid'),
            'officeUnavailable'  => __('Pratinjau Word hanya tersedia di situs yang bisa diakses publik.', 'wp-taxonomy-grid'),
            'otherFile'          => __('File ini tidak bisa dipratinjau di sini.', 'wp-taxonomy-grid'),
            'page'               => __('Halaman %d', 'wp-taxonomy-grid'),
        ],
        // PDF.js is an ES module loaded on demand (dynamic import) from the plugin
        // folder. Its worker, wasm decoders and fonts sit next to it. Folder URLs end with "/".
        'pdfjs'    => [
            'lib'       => WPTG_URL . 'assets/vendor/pdfjs/pdf.min.mjs',
            'worker'    => WPTG_URL . 'assets/vendor/pdfjs/pdf.worker.min.mjs',
            'wasmUrl'   => WPTG_URL . 'assets/vendor/pdfjs/wasm/',
            'fontsUrl'  => WPTG_URL . 'assets/vendor/pdfjs/standard_fonts/',
            'iccUrl'    => WPTG_URL . 'assets/vendor/pdfjs/iccs/',
        ],
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
 * Empty-state skeleton: grey placeholder blocks shaped like the real content.
 * The ONE component used everywhere the widget has no data (Sections, Single
 * detail, Single grid, AJAX answers), so the empty look cannot drift apart.
 *
 * Variants (unknown value = 'feed'):
 *   feed          one big card + compact cards (Numbered Feed)
 *   grid          image + 2 lines (Grid of Thumbnails / Single grid)
 *   list          small thumbnail + lines (Vertical List of Thumbnails)
 *   detail        title + long text block (Single Post Full Detail / Single detail)
 *   document      icon + 2 lines, wide cover below with 2 small pills (Document List, since 4.5.0; layout 4.6.0)
 *   sidebar_terms 3-4 short lines for a term list
 *
 * It reuses the real layout classes (.wptg-numbered-feed, .wptg-feed-item,
 * .wptg-posts-grid ...) next to the .wptg-skeleton-* ones, so image heights,
 * gaps and radius follow the same CSS variables / Elementor Style controls as
 * the real cards, and it stays responsive without extra media queries.
 *
 * Accessibility: every shape is aria-hidden. The content variants add ONE
 * visually hidden text ("Belum ada konten") inside a role="status" wrapper so
 * a screen reader knows the state. 'sidebar_terms' is purely decorative, so
 * the screen reader does not hear the same text twice.
 *
 * @param string $variant 'feed'|'grid'|'list'|'detail'|'document'|'sidebar_terms'.
 * @param int    $count   How many cards / rows (clamped 1-12). Ignored by 'detail'.
 */
function wptg_render_skeleton(string $variant, int $count = 3): string
{
    $variant = in_array($variant, ['feed', 'grid', 'list', 'detail', 'document', 'sidebar_terms'], true) ? $variant : 'feed';
    $count   = min(12, max(1, $count));

    // One grey bar. $modifier decides its width/height in taxonomy-grid.css.
    $line = static fn (string $modifier): string => '<span class="wptg-skeleton-block wptg-skeleton-line ' . $modifier . '"></span>';

    if ($variant === 'sidebar_terms') {
        $rows = str_repeat('<span class="wptg-skeleton-block wptg-skeleton-term"></span>', $count);

        return '<div class="wptg-skeleton wptg-skeleton--sidebar-terms" aria-hidden="true">' . $rows . '</div>';
    }

    if ($variant === 'detail') {
        $list_class = 'wptg-skeleton-detail';
        $items      = $line('wptg-skeleton-line--heading')
            . $line('wptg-skeleton-line--meta')
            . '<div class="wptg-skeleton-paragraph">' . str_repeat($line('wptg-skeleton-line--text'), 8) . '</div>';
    } else {
        $list_class = match ($variant) {
            'grid'     => 'wptg-posts-grid',
            'list'     => 'wptg-list-thumbnail',
            'document' => 'wptg-doc-list',
            default    => 'wptg-numbered-feed',
        };

        $items = '';
        for ($i = 0; $i < $count; $i++) {
            if ($variant === 'grid') {
                $items .= '<div class="wptg-post-item wptg-post-item--photo wptg-skeleton-card">'
                    . '<div class="wptg-photo-thumb wptg-skeleton-block"></div>'
                    . '<div class="wptg-photo-caption">' . $line('wptg-skeleton-line--title') . $line('wptg-skeleton-line--meta') . '</div>'
                    . '</div>';
            } elseif ($variant === 'list') {
                $items .= '<div class="wptg-list-thumbnail-item wptg-skeleton-card">'
                    . '<div class="wptg-list-thumbnail-media wptg-skeleton-block"></div>'
                    . '<div class="wptg-list-thumbnail-body">' . $line('wptg-skeleton-line--title') . $line('wptg-skeleton-line--meta') . '</div>'
                    . '</div>';
            } elseif ($variant === 'document') {
                // Same layout classes as the real card: head row (icon + 2 lines), cover below it, 2 small pills on the cover corner.
                $pill = '<span class="wptg-doc-btn wptg-skeleton-block wptg-skeleton-pill"></span>';
                $items .= '<div class="wptg-doc-card wptg-skeleton-card">'
                    . '<div class="wptg-doc-head">'
                    . '<span class="wptg-doc-icon wptg-skeleton-block"></span>'
                    . '<div class="wptg-doc-body">' . $line('wptg-skeleton-line--title') . $line('wptg-skeleton-line--meta') . '</div>'
                    . '</div>'
                    . '<div class="wptg-doc-cover wptg-skeleton-block"><div class="wptg-doc-card-actions">' . $pill . $pill . '</div></div>'
                    . '</div>';
            } elseif ($i === 0) {
                // Same as the default feed layout: first card big (with excerpt lines), the rest compact.
                $items .= '<div class="wptg-feed-item wptg-feed-item--featured wptg-skeleton-card">'
                    . '<div class="wptg-feed-thumb wptg-skeleton-block"></div>'
                    . '<div class="wptg-feed-body">'
                    . '<span class="wptg-skeleton-block wptg-skeleton-number"></span>'
                    . '<div class="wptg-feed-text">'
                    . $line('wptg-skeleton-line--meta')
                    . $line('wptg-skeleton-line--title')
                    . $line('wptg-skeleton-line--title-short')
                    . str_repeat($line('wptg-skeleton-line--text'), 3)
                    . $line('wptg-skeleton-line--readmore')
                    . '</div></div></div>';
            } else {
                $items .= '<div class="wptg-feed-item wptg-feed-item--compact wptg-skeleton-card">'
                    . '<div class="wptg-feed-thumb wptg-skeleton-block"></div>'
                    . '<div class="wptg-feed-body">'
                    . '<span class="wptg-skeleton-block wptg-skeleton-number"></span>'
                    . '<div class="wptg-feed-text">'
                    . $line('wptg-skeleton-line--meta')
                    . $line('wptg-skeleton-line--title')
                    . $line('wptg-skeleton-line--readmore')
                    . '</div></div></div>';
            }
        }
    }

    return '<div class="wptg-skeleton wptg-skeleton--' . $variant . '" role="status">'
        . '<span class="wptg-sr-only">' . esc_html__('Belum ada konten', 'wp-taxonomy-grid') . '</span>'
        . '<div class="' . $list_class . '" aria-hidden="true">' . $items . '</div>'
        . '</div>';
}

/**
 * Skeleton that matches a Sections content style, so the empty panel has the
 * same shape as the cards that will show up once there is data.
 *
 * @param string $content_style 'numbered_feed'|'grid_thumbnail'|'list_thumbnail'|'single_detail'|'document_list'.
 */
function wptg_render_skeleton_for_style(string $content_style): string
{
    return match ($content_style) {
        'grid_thumbnail' => wptg_render_skeleton('grid', 6),
        'list_thumbnail' => wptg_render_skeleton('list', 3),
        'single_detail'  => wptg_render_skeleton('detail'),
        'document_list'  => wptg_render_skeleton('document', 4),
        default          => wptg_render_skeleton('feed', 3),
    };
}

/**
 * One small line that explains WHY the widget is empty. Only for people who
 * can edit posts, so visitors only ever see the skeleton ("skeleton only").
 * Also visible inside the Elementor editor, which helps while building.
 *
 * @param string $message Reason, plain text (escaped here). Empty = nothing is printed.
 */
function wptg_render_editor_note(string $message): string
{
    if ($message === '' || !current_user_can('edit_posts')) {
        return '';
    }

    return '<p class="wptg-editor-note">'
        . esc_html(sprintf(__('Taxonomy Grid (hanya terlihat oleh editor): %s', 'wp-taxonomy-grid'), $message))
        . '</p>';
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
        'show_header_lines'  => 'true',
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

    // The downstream helpers have strict string parameters.
    $atts['post_type'] = (string) $atts['post_type'];
    $atts['taxonomy']  = (string) $atts['taxonomy'];

    // Empty State (4.4.0): a wrong or unfinished setup no longer stops with an
    // error text that every visitor can read. We note WHY in $problem (shown
    // only to editors), keep going with zero terms, and the layout is drawn
    // with skeletons.
    $problem       = '';
    $post_type_obj = $atts['post_type'] !== '' ? get_post_type_object($atts['post_type']) : null;
    $taxonomy_obj  = null;
    if ($atts['taxonomy'] !== '' && taxonomy_exists($atts['taxonomy'])) {
        $taxonomy_obj = get_taxonomy($atts['taxonomy']) ?: null;
    }

    if ($atts['post_type'] === '') {
        $problem = __('pilih Post Type di tab Content > Source.', 'wp-taxonomy-grid');
    } elseif (!$post_type_obj) {
        $problem = sprintf(__('post type "%s" tidak ditemukan.', 'wp-taxonomy-grid'), $atts['post_type']);
    } elseif ($atts['taxonomy'] === '') {
        $problem = __('pilih Taxonomy di tab Content > Source.', 'wp-taxonomy-grid');
    } elseif (!$taxonomy_obj) {
        $problem = sprintf(__('taxonomy "%s" tidak ditemukan.', 'wp-taxonomy-grid'), $atts['taxonomy']);
    } elseif (!in_array($atts['post_type'], $taxonomy_obj->object_type, true)) {
        $problem = sprintf(
            __('taxonomy "%1$s" tidak terdaftar untuk post type "%2$s".', 'wp-taxonomy-grid'),
            $atts['taxonomy'],
            $atts['post_type']
        );
    }

    // Terms scoped to THIS post type only (see wptg_get_terms_for_post_type()).
    $terms = [];
    if ($problem === '') {
        $terms = wptg_get_terms_for_post_type($atts['taxonomy'], $atts['post_type']);

        if (empty($terms)) {
            $problem = __('belum ada post yang terbit dan punya kategori di taxonomy ini.', 'wp-taxonomy-grid');
        }
    }

    $instance_id = $forced_instance_id ?? ('wptg-' . wp_generate_uuid4());
    $mode        = $atts['mode'] === 'grid' ? 'grid' : 'detail';

    $markup = $mode === 'grid'
        ? wptg_render_grid_mode($atts, $terms, $taxonomy_obj, $instance_id)
        : wptg_render_detail_mode($atts, $terms, $post_type_obj, $instance_id);

    // The note sits OUTSIDE the wrapper so it never disturbs the layout.
    return $markup . wptg_render_editor_note($problem);
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
 * Resolve the header/breadcrumb texts for the layouts that have one fixed
 * title: an empty setting falls back to the given default. Breadcrumb
 * middle/current in turn default to the header subtitle/title.
 *
 * @param array<string,mixed> $atts             Needs header_subtitle, header_title, breadcrumb_middle, breadcrumb_current.
 * @param string              $default_subtitle Used when header_subtitle is empty.
 * @param string              $default_title    Used when header_title is empty.
 * @return array{subtitle:string,title:string,middle:string,current:string}
 */
function wptg_resolve_top_texts(array $atts, string $default_subtitle, string $default_title): array
{
    $subtitle = $atts['header_subtitle'] !== '' ? $atts['header_subtitle'] : $default_subtitle;
    $title    = $atts['header_title'] !== '' ? $atts['header_title'] : $default_title;

    return [
        'subtitle' => $subtitle,
        'title'    => $title,
        'middle'   => $atts['breadcrumb_middle'] !== '' ? $atts['breadcrumb_middle'] : $subtitle,
        'current'  => $atts['breadcrumb_current'] !== '' ? $atts['breadcrumb_current'] : $title,
    ];
}

/**
 * Header banner (the "Top" section). Shared by every layout (Single detail,
 * Single grid, Sections) so the header looks and is styled identically
 * everywhere — Style > Header applies to all of them.
 *
 * The subtitle sits ABOVE the title; the two decorative lines flank only
 * the title row (not the subtitle), matching the reference design.
 *
 * @param string $subtitle   Small text above the title (omitted when empty).
 * @param string $title      Big title (omitted when empty).
 * @param bool   $show_lines Whether to render the decorative lines left/right of the title.
 */
function wptg_render_header_block(string $subtitle, string $title, bool $show_lines = true): string
{
    ob_start();
    ?>
    <div class="wptg-section-top">
        <header class="wptg-header">
            <div class="wptg-header-flex">
                <?php if ($subtitle !== ''): ?>
                    <span class="wptg-header-subtitle"><?php echo esc_html($subtitle); ?></span>
                <?php endif; ?>
                <div class="wptg-header-title-row">
                    <?php if ($show_lines): ?>
                        <span class="wptg-header-line wptg-header-line-start" aria-hidden="true"></span>
                    <?php endif; ?>
                    <div class="wptg-header-inner">
                        <?php if ($title !== ''): ?>
                            <h1 class="wptg-header-title"><?php echo esc_html($title); ?></h1>
                        <?php endif; ?>
                    </div>
                    <?php if ($show_lines): ?>
                        <span class="wptg-header-line wptg-header-line-end" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
            </div>
        </header>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * Breadcrumb trail ("Home > Middle > Current"). Shared by every layout.
 *
 * @param string $home_label     First crumb, links to the site home.
 * @param string $middle         Second crumb (omitted when empty).
 * @param string $current        Last crumb (omitted when empty).
 * @param string $separator_html Trusted separator markup from wptg_render_icon().
 */
function wptg_render_breadcrumb_block(string $home_label, string $middle, string $current, string $separator_html): string
{
    ob_start();
    ?>
    <nav class="wptg-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'wp-taxonomy-grid'); ?>">
        <ol>
            <li class="wptg-breadcrumb-home"><a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html($home_label); ?></a></li>
            <?php if ($middle !== ''): ?>
                <li class="wptg-breadcrumb-sep" aria-hidden="true"><?php echo $separator_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></li>
                <li class="wptg-breadcrumb-middle"><?php echo esc_html($middle); ?></li>
            <?php endif; ?>
            <?php if ($current !== ''): ?>
                <li class="wptg-breadcrumb-sep" aria-hidden="true"><?php echo $separator_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></li>
                <li class="wptg-breadcrumb-current" aria-current="page"><?php echo esc_html($current); ?></li>
            <?php endif; ?>
        </ol>
    </nav>
    <?php
    return (string) ob_get_clean();
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
 * @param \WP_Post_Type|null     $post_type_obj Post type object (null while the widget is not set up yet: skeleton layout).
 * @param string                 $instance_id   Unique instance id for this shortcode render.
 */
function wptg_render_detail_mode(array $atts, array $terms, ?\WP_Post_Type $post_type_obj, string $instance_id): string
{
    $show_header     = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
    $show_breadcrumb = filter_var($atts['show_breadcrumb'], FILTER_VALIDATE_BOOLEAN);
    $show_search     = filter_var($atts['show_search'], FILTER_VALIDATE_BOOLEAN);
    $active_first    = filter_var($atts['active_first'], FILTER_VALIDATE_BOOLEAN);

    // Sensible defaults: subtitle = post type label ("Layanan"),
    // title = the current page/post title (the page the shortcode sits on).
    $top = wptg_resolve_top_texts($atts, $post_type_obj?->labels->name ?? '', get_the_title());

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
    // post_type can be '' while the widget is not set up yet; is_singular('') would match any post.
    $current_post_id   = ($atts['post_type'] !== '' && is_singular($atts['post_type']) && get_queried_object_id())
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
            <?php echo wptg_render_header_block($top['subtitle'], $top['title'], filter_var($atts['show_header_lines'], FILTER_VALIDATE_BOOLEAN)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
        <?php endif; ?>

        <div class="wptg-section-body">
            <?php if ($show_breadcrumb): ?>
                <?php echo wptg_render_breadcrumb_block($atts['breadcrumb_home'], $top['middle'], $top['current'], $breadcrumb_sep_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
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
                        <?php if ($groups === []): ?>
                            <?php echo wptg_render_skeleton('sidebar_terms', 4); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
                        <?php endif; ?>
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
                        <?php if ($groups !== []): ?>
                            <?php // Shown by JS when the sidebar search matches nothing: a skeleton instead of a "not found" text. ?>
                            <div class="wptg-no-results" style="display:none;"><?php echo wptg_render_skeleton('sidebar_terms', 3); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?></div>
                        <?php endif; ?>
                    </div>
                </aside>

                <main class="wptg-detail-container">
                    <?php if ($default_active_id > 0): ?>
                        <?php echo wptg_render_single_post_detail($default_active_id); ?>
                    <?php elseif ($groups === []): ?>
                        <?php echo wptg_render_skeleton('detail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
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
 * @param \WP_Taxonomy|null    $taxonomy_obj Taxonomy object (null while the widget is not set up yet: skeleton layout).
 * @param string               $instance_id  Unique instance id.
 */
function wptg_render_grid_mode(array $atts, array $terms, ?\WP_Taxonomy $taxonomy_obj, string $instance_id): string
{
    $active_first  = filter_var($atts['active_first'], FILTER_VALIDATE_BOOLEAN);
    $first_term_id = (int) ($terms[0]->term_id ?? 0);

    $title = $atts['title'] !== '' ? $atts['title'] : ($taxonomy_obj?->labels->name ?? '');

    // Header + breadcrumb are required in every layout, same defaults as
    // detail mode: subtitle = post type label, title = current page title.
    $show_header         = filter_var($atts['show_header'], FILTER_VALIDATE_BOOLEAN);
    $show_breadcrumb     = filter_var($atts['show_breadcrumb'], FILTER_VALIDATE_BOOLEAN);
    $post_type_obj       = get_post_type_object($atts['post_type']);
    $top                 = wptg_resolve_top_texts($atts, $post_type_obj ? $post_type_obj->labels->name : '', get_the_title());
    $breadcrumb_sep_html = wptg_render_icon($atts['breadcrumb_icon'] ?? null, '&rarr;');

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-grid <?php echo esc_attr($atts['class']); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($atts['taxonomy']); ?>"
         data-mode="grid">

        <?php if ($show_header): ?>
            <?php echo wptg_render_header_block($top['subtitle'], $top['title'], filter_var($atts['show_header_lines'], FILTER_VALIDATE_BOOLEAN)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
        <?php endif; ?>

        <div class="wptg-section-body">
            <?php if ($show_breadcrumb): ?>
                <?php echo wptg_render_breadcrumb_block($atts['breadcrumb_home'], $top['middle'], $top['current'], $breadcrumb_sep_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
            <?php endif; ?>

            <div class="wptg-body wptg-grid-body">
        <div class="wptg-categories">
            <?php if ($title !== ''): ?>
                <h3 class="wptg-title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <?php if ($terms === []): ?>
                <?php echo wptg_render_skeleton('sidebar_terms', 4); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
            <?php endif; ?>
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
            <?php elseif ($terms === []): ?>
                <?php echo wptg_render_skeleton('grid', 6); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
            <?php else: ?>
                <div class="wptg-placeholder">
                    <p><?php esc_html_e('Select a category to view items.', 'wp-taxonomy-grid'); ?></p>
                </div>
            <?php endif; ?>
        </div>
            </div><?php // .wptg-body ?>
        </div><?php // .wptg-section-body ?>
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
        return wptg_render_skeleton('grid', 6);
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
 * Validate an "Item Click Behavior" value (from the widget settings or from
 * the browser's AJAX request): 'inline_detail' | 'modal' | 'media_popup' |
 * 'document_preview' (since 4.5.0). Anything unknown becomes 'inline_detail'.
 *
 * The old value 'redirect_or_popup_player' (before 4.3.0: redirect to a
 * URL, or a small video popup) is still accepted so widgets saved earlier
 * keep working, but it now means 'media_popup': a click never redirects
 * or opens a new tab anymore, the video plays in the popup.
 *
 * @param mixed $value Raw behavior value (a tampered request may send an array, which becomes '').
 */
function wptg_normalize_click_behavior(mixed $value): string
{
    $value = is_string($value) ? sanitize_key($value) : '';

    if ($value === 'redirect_or_popup_player') {
        return 'media_popup';
    }

    return in_array($value, ['inline_detail', 'modal', 'media_popup', 'document_preview'], true) ? $value : 'inline_detail';
}

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
    // "document_list" (4.5.0) is the one style that works in BOTH section types.
    $content_style = sanitize_key($row['section_content_style'] ?? '');
    if ($type === 'flat_list') {
        $content_style = $content_style === 'document_list' ? 'document_list' : 'numbered_feed';
    } elseif (!in_array($content_style, ['grid_thumbnail', 'list_thumbnail', 'single_detail', 'document_list'], true)) {
        $content_style = 'grid_thumbnail';
    }

    $click_behavior = wptg_normalize_click_behavior($row['section_click_behavior'] ?? 'inline_detail');

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
 * Fallback for wptg_resolve_default_active() when NO section has data: make
 * the first valid section the active one in its empty state. Without this the
 * header title, breadcrumb and content title would be blank; with it they
 * show that section's label and the panel shows a skeleton. Pure — no I/O.
 *
 * term_id 0 on an accordion section means "empty state" (see
 * wptg_render_section_content_panel()), not "all posts".
 *
 * @param array<int,array<string,mixed>> $sections Normalized + annotated sections.
 * @return array{section_index:int,term_id:int,post_id:int}|null Null only when there is no valid section at all.
 */
function wptg_resolve_empty_active(array $sections): ?array
{
    $first = reset($sections);

    return $first === false
        ? null
        : ['section_index' => (int) $first['index'], 'term_id' => 0, 'post_id' => 0];
}

/**
 * Read one deep-link query param (?wptg_section=... / ?wptg_term=...) as a
 * plain string. Returns '' when it is missing or not a string (e.g.
 * `?wptg_term[]=x`), so callers never have to deal with arrays.
 *
 * The caller still sanitizes the value (sanitize_key / sanitize_title).
 *
 * @param string $name Query param name.
 */
function wptg_read_deep_link_param(string $name): string
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param, it only picks which section is shown and never changes data.
    $value = $_GET[$name] ?? '';

    return is_string($value) ? wp_unslash($value) : '';
}

/**
 * Find a term inside a section's term list by SLUG (not ID, so URLs stay
 * readable). Only terms that already passed wptg_get_terms_for_post_type()
 * are in the list, so a match is guaranteed to exist in that section's
 * taxonomy AND to have at least one published post of its post type.
 *
 * @param array<string,mixed> $section   Normalized + annotated section.
 * @param string              $term_slug Term slug (already sanitized).
 */
function wptg_find_section_term_by_slug(array $section, string $term_slug): ?object
{
    foreach ($section['terms'] ?? [] as $term) {
        if ($term->slug === $term_slug) {
            return $term;
        }
    }

    return null;
}

/**
 * Build the "default active" entry for ONE section that a deep link points
 * to. Same shape as wptg_resolve_default_active()'s return value.
 *
 * - flat_list: no terms, only needs published items.
 * - taxonomy_accordion: uses $term_id, or the section's first term when
 *   $term_id is 0.
 * - single_detail: also needs the first post of that term (the content
 *   panel shows one full post). This is the only case that does a query,
 *   and it reuses the cached posts of the first term when it can.
 *
 * Returns null when the section has nothing to show.
 *
 * @param array<string,mixed> $section Normalized + annotated section.
 * @param int                 $term_id Term ID, or 0 for "first term".
 * @return array{section_index:int,term_id:int,post_id:int}|null
 */
function wptg_build_deep_link_active(array $section, int $term_id): ?array
{
    if ($section['type'] === 'flat_list') {
        return ($section['has_items'] ?? false)
            ? ['section_index' => $section['index'], 'term_id' => 0, 'post_id' => 0]
            : null;
    }

    if (empty($section['terms'])) {
        return null;
    }

    if ($term_id === 0) {
        $term_id = (int) $section['terms'][0]->term_id;
    }

    if ($section['content_style'] !== 'single_detail') {
        return ['section_index' => $section['index'], 'term_id' => $term_id, 'post_id' => 0];
    }

    $posts   = $section['term_posts_cache'][$term_id]
        ?? wptg_get_posts_for_term($term_id, $section['post_type'], $section['taxonomy']);
    $post_id = (int) ($posts[0]->ID ?? 0);

    return $post_id > 0
        ? ['section_index' => $section['index'], 'term_id' => $term_id, 'post_id' => $post_id]
        : null;
}

/**
 * Deep link: pick the section (and term) named in the page URL, e.g.
 * `/berita/?wptg_section=videos&wptg_term=profil-kantor-imigrasi`.
 *
 * Rules (anything else returns null, and the caller falls back to
 * wptg_resolve_default_active(), so a bad link never breaks the page):
 *  - Only sections whose post type equals $section_slug are considered.
 *  - With a term: the first matching section that really has that term
 *    wins. This lets one page hold a Flat List AND an Accordion for the
 *    same post type (the term then opens the accordion).
 *  - Otherwise (no term, or the term is unknown/empty): the first
 *    matching section that has something to show opens, on its first term.
 *    A Flat List never takes a term, so its term is simply ignored.
 *  - Since 4.4.0, if the matching sections have NO data at all, the first
 *    one still opens, in its empty state (skeleton), instead of being skipped.
 *    A section that matches no configured post type still returns null.
 *
 * @param array<int,array<string,mixed>> $sections     Normalized + annotated sections.
 * @param string                         $section_slug Post type slug (sanitize_key'd).
 * @param string                         $term_slug    Term slug (sanitize_title'd), or ''.
 * @return array{section_index:int,term_id:int,post_id:int}|null
 */
function wptg_resolve_deep_link(array $sections, string $section_slug, string $term_slug): ?array
{
    if ($section_slug === '') {
        return null;
    }

    $matching = [];
    foreach ($sections as $section) {
        if ($section['post_type'] === $section_slug) {
            $matching[] = $section;
        }
    }

    if ($term_slug !== '') {
        foreach ($matching as $section) {
            $term = wptg_find_section_term_by_slug($section, $term_slug);
            if ($term === null) {
                continue;
            }
            $active = wptg_build_deep_link_active($section, (int) $term->term_id);
            if ($active !== null) {
                return $active;
            }
        }
    }

    foreach ($matching as $section) {
        $active = wptg_build_deep_link_active($section, 0);
        if ($active !== null) {
            return $active;
        }
    }

    // The section exists but has no data: open it in its empty state.
    return wptg_resolve_empty_active($matching);
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
 * Validate the "Feed" settings that change how a numbered_feed item looks.
 *
 * Shared by the first page load (values come from Elementor settings) and
 * the AJAX handler (values come from the browser), so a tampered request
 * can never inject an unknown layout, a huge excerpt, or raw HTML.
 *
 * @param string $layout             'featured_compact'|'all_featured'|'all_compact' (anything else = featured_compact).
 * @param bool   $show_number        Show the 01/02/03 number.
 * @param int    $excerpt_length     Excerpt words for featured cards, 0 hides it (clamped 0-100).
 * @param string $readmore_text      Read-more label; empty = "Baca Selengkapnya".
 * @param string $readmore_icon_html Trusted icon markup from wptg_render_icon(). The AJAX handler never passes this.
 * @return array{layout:string,show_number:bool,excerpt_length:int,readmore_text:string,readmore_icon_html:string}
 */
function wptg_normalize_feed_options(string $layout, bool $show_number, int $excerpt_length, string $readmore_text, string $readmore_icon_html = ''): array
{
    $layout        = sanitize_key($layout);
    $readmore_text = trim(sanitize_text_field($readmore_text));

    return [
        'layout'             => in_array($layout, ['featured_compact', 'all_featured', 'all_compact'], true) ? $layout : 'featured_compact',
        'show_number'        => $show_number,
        'excerpt_length'     => min(100, max(0, $excerpt_length)),
        'readmore_text'      => $readmore_text !== '' ? $readmore_text : __('Baca Selengkapnya', 'wp-taxonomy-grid'),
        'readmore_icon_html' => $readmore_icon_html,
    ];
}

/**
 * Build feed options from Elementor widget settings (Content > Feed & Load More).
 *
 * @param array<string,mixed> $settings Elementor get_settings_for_display() output.
 * @return array{layout:string,show_number:bool,excerpt_length:int,readmore_text:string,readmore_icon_html:string}
 */
function wptg_feed_options_from_settings(array $settings): array
{
    return wptg_normalize_feed_options(
        (string) ($settings['wptg_feed_layout'] ?? 'featured_compact'),
        ($settings['wptg_feed_show_number'] ?? 'yes') === 'yes',
        (int) ($settings['wptg_feed_excerpt_length'] ?? 40),
        (string) ($settings['wptg_feed_readmore_text'] ?? ''),
        // No fallback glyph: clearing the icon control should really remove the icon.
        wptg_render_icon($settings['wptg_feed_readmore_icon'] ?? null, '')
    );
}

/**
 * Search pill (input + icon). Used for the sidebar search AND the content
 * search in Sections mode, with the same .wptg-search* classes so
 * Style > Search Box styles both. taxonomy-grid.js keeps all inputs in sync.
 *
 * @param string $placeholder    Placeholder and aria-label text.
 * @param string $icon_html      Trusted icon markup from wptg_render_icon().
 * @param string $modifier_class Extra class, e.g. 'wptg-search--content'.
 */
function wptg_render_search_box(string $placeholder, string $icon_html, string $modifier_class = ''): string
{
    ob_start();
    ?>
    <div class="wptg-search<?php echo $modifier_class !== '' ? ' ' . esc_attr($modifier_class) : ''; ?>">
        <input type="search" class="wptg-search-input"
               placeholder="<?php echo esc_attr($placeholder); ?>"
               aria-label="<?php echo esc_attr($placeholder); ?>">
        <span class="wptg-search-icon" aria-hidden="true"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></span>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * The "Lihat Lebih Banyak" button. The label comes from the widget setting;
 * taxonomy-grid.js re-reads it from the wrapper's data-load-more-text when
 * it rebuilds the button after a section switch or search.
 *
 * @param string $text Button label.
 */
function wptg_render_load_more_button(string $text): string
{
    return '<button type="button" class="wptg-load-more">' . esc_html($text) . '</button>';
}

/**
 * Maps a content_style to its list wrapper CSS class — single source of
 * truth, mirrored client-side by wptgListClassFor() in taxonomy-grid.js.
 *
 * @param string $content_style One of 'grid_thumbnail'|'list_thumbnail'|'document_list'|'numbered_feed'.
 */
function wptg_section_list_class(string $content_style): string
{
    return match ($content_style) {
        'grid_thumbnail' => 'wptg-posts-grid', // reuses the EXISTING grid CSS, no new grid layout rules needed.
        'list_thumbnail' => 'wptg-list-thumbnail',
        'document_list'  => 'wptg-doc-list',
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
 * 'media_popup' and 'document_preview' items also get
 * `data-wptg-media="<post_id>"`. This is the ONLY hook
 * assets/js/media-popup.js listens to (document preview is the same popup,
 * only the answer of the server differs), and it is added here (not in each
 * renderer) so every item style behaves the same.
 *
 * @param int                   $post_id        Post ID.
 * @param string                $post_type      Post type slug.
 * @param string                $click_behavior 'inline_detail'|'modal'|'media_popup'|'document_preview'.
 * @param array<string,string>  $extra          Additional raw `name="value"` pairs, already escaped by the caller.
 * @param bool                  $popup_hint     Add aria-haspopup="dialog" next to data-wptg-media. The document card passes false:
 *                                              its <article> is not focusable, the "Lihat" <button> inside carries the hint.
 */
function wptg_build_item_attrs(int $post_id, string $post_type, string $click_behavior, array $extra = [], bool $popup_hint = true): string
{
    $attrs = sprintf(
        ' data-post-id="%d" data-post-type="%s" data-click-behavior="%s"',
        $post_id,
        esc_attr($post_type),
        esc_attr($click_behavior)
    );

    if (in_array($click_behavior, ['media_popup', 'document_preview'], true)) {
        $attrs .= sprintf(' data-wptg-media="%d"%s', $post_id, $popup_hint ? ' aria-haspopup="dialog"' : '');
    }

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
 * Two visual variants: "featured" (big image, excerpt, vertical text) and
 * "compact" (small thumbnail, no excerpt). Which one each item gets comes
 * from $feed_options['layout'] (see wptg_normalize_feed_options()).
 *
 * @param \WP_Post             $post              Post to render.
 * @param int                  $display_number    Sequential display position (not a post field).
 * @param bool                 $is_first_overall  Whether this is the first item on page 1 (the one "Featured first" makes big).
 * @param string               $click_behavior    'inline_detail'|'modal'|'media_popup'.
 * @param array<string,mixed>  $feed_options      Output of wptg_normalize_feed_options(); empty = defaults.
 */
function wptg_render_item_numbered_feed(\WP_Post $post, int $display_number, bool $is_first_overall, string $click_behavior, array $feed_options = []): string
{
    if ($feed_options === []) {
        $feed_options = wptg_normalize_feed_options('', true, 40, '');
    }

    $is_featured = match ($feed_options['layout']) {
        'all_featured' => true,
        'all_compact'  => false,
        default        => $is_first_overall,
    };

    $extra = [];
    if ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    // Only the featured card shows an excerpt; the compact card stays short.
    // "..." is appended by wp_trim_words() only when the text was cut.
    $excerpt = '';
    if ($is_featured && $feed_options['excerpt_length'] > 0) {
        $excerpt_source = has_excerpt($post->ID) ? $post->post_excerpt : $post->post_content;
        $excerpt        = wp_trim_words(strip_shortcodes($excerpt_source), $feed_options['excerpt_length'], '...');
    }

    ob_start();
    ?>
    <article class="wptg-feed-item <?php echo $is_featured ? 'wptg-feed-item--featured' : 'wptg-feed-item--compact'; ?>" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-feed-thumb">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, $is_featured ? 'large' : 'medium'); ?>
            <?php endif; ?>
        </div>
        <div class="wptg-feed-body">
            <?php if ($feed_options['show_number']): ?>
                <span class="wptg-feed-badge"><?php echo esc_html(sprintf('%02d', $display_number)); ?></span>
            <?php endif; ?>
            <div class="wptg-feed-text">
                <span class="wptg-feed-date"><?php echo esc_html(get_the_date('', $post->ID)); ?></span>
                <h3 class="wptg-feed-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
                <?php if ($excerpt !== ''): ?>
                    <p class="wptg-feed-excerpt"><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
                <?php // The icon span must stay empty-looking (no whitespace) when there is no icon yet: taxonomy-grid.js fills `:empty` ones for items that arrive via AJAX. ?>
                <span class="wptg-feed-readmore"><span class="wptg-feed-readmore-text"><?php echo esc_html($feed_options['readmore_text']); ?></span><span class="wptg-feed-readmore-icon" aria-hidden="true"><?php echo $feed_options['readmore_icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></span></span>
            </div>
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
 * @param string   $click_behavior 'inline_detail'|'modal'|'media_popup'.
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
 * (content_style = list_thumbnail).
 *
 * With click_behavior = media_popup the item only carries
 * data-wptg-media (added by wptg_build_item_attrs()). What plays is decided
 * when it is clicked, see wptg_build_media_modal_payload().
 *
 * @param \WP_Post $post           Post to render.
 * @param string   $click_behavior 'inline_detail'|'modal'|'media_popup'.
 */
function wptg_render_item_list_thumbnail(\WP_Post $post, string $click_behavior): string
{
    $extra = [];

    if ($click_behavior === 'modal') {
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
 * Validate the "Document Card" settings. Same idea as
 * wptg_normalize_feed_options(): shared by the first page load (Elementor
 * settings) and the AJAX handler (JSON from the browser), so a tampered
 * request can only ever pick between these few safe values.
 *
 * Missing keys get the default, which is also the default of the Elementor
 * control. Unknown keys are ignored.
 *
 * @param array<string,mixed> $raw       Plain values: booleans (or "1"/"0") and short texts.
 * @param string              $icon_html Trusted markup of the Card Icon from wptg_render_icon(); '' = built-in icon.
 *                                       Never read from $raw, so the browser can not send HTML.
 * @return array{show_icon:bool,custom_icon:bool,icon_html:string,show_date:bool,date_label:string,show_cover:bool,show_count:bool,view_text:string,show_view_icon:bool,show_card_download:bool,download_text:string,show_download_icon:bool,show_download:bool}
 */
function wptg_normalize_document_options(array $raw, string $icon_html = ''): array
{
    $flag = static fn (string $key, bool $default): bool => array_key_exists($key, $raw)
        ? filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN)
        : $default;

    // Short text, tags stripped, empty = the default text.
    $text = static function (string $key, string $default) use ($raw): string {
        $value = isset($raw[$key]) && is_string($raw[$key]) ? trim(sanitize_text_field($raw[$key])) : '';

        return $value !== '' ? mb_substr($value, 0, 60) : $default;
    };

    return [
        'show_icon'          => $flag('show_icon', true),
        // The slot of a custom icon stays empty in AJAX answers; taxonomy-grid.js copies the icon in from a <template>.
        'custom_icon'        => $icon_html !== '' || $flag('custom_icon', false),
        'icon_html'          => $icon_html,
        'show_date'          => $flag('show_date', true),
        'date_label'         => $text('date_label', __('Diunggah pada:', 'wp-taxonomy-grid')),
        'show_cover'         => $flag('show_cover', true),
        'show_count'         => $flag('show_count', false),
        'view_text'          => $text('view_text', __('Lihat', 'wp-taxonomy-grid')),
        'show_view_icon'     => $flag('show_view_icon', true),
        'show_card_download' => $flag('show_card_download', true),
        'download_text'      => $text('download_text', __('Unduh', 'wp-taxonomy-grid')),
        'show_download_icon' => $flag('show_download_icon', true),
        // Popup setting (not the card button): also show Unduh under the viewer inside the popup.
        'show_download'      => $flag('show_download', false),
    ];
}

/**
 * Document options from Elementor widget settings (Content > Document Card).
 *
 * @param array<string,mixed> $settings Elementor get_settings_for_display() output.
 * @return array<string,mixed> Output of wptg_normalize_document_options().
 */
function wptg_document_options_from_settings(array $settings): array
{
    // A switcher is 'yes' when on and '' when off; a missing key means the control never saved, so use its default.
    $switch = static fn (string $key, bool $default): bool => ($settings[$key] ?? ($default ? 'yes' : '')) === 'yes';

    return wptg_normalize_document_options(
        [
            'show_icon'          => $switch('wptg_doc_show_icon', true),
            'show_date'          => $switch('wptg_doc_show_date', true),
            'date_label'         => $settings['wptg_doc_date_label'] ?? '',
            'show_cover'         => $switch('wptg_doc_show_cover', true),
            'show_count'         => $switch('wptg_doc_show_count', false),
            'view_text'          => $settings['wptg_doc_button_text'] ?? '',
            'show_view_icon'     => $switch('wptg_doc_show_view_icon', true),
            'show_card_download' => $switch('wptg_doc_show_card_download', true),
            'download_text'      => $settings['wptg_doc_download_text'] ?? '',
            'show_download_icon' => $switch('wptg_doc_show_download_icon', true),
            'show_download'      => $switch('wptg_doc_show_download', false),
        ],
        // No fallback glyph: an empty control means "use the built-in icon", handled by the card renderer.
        wptg_render_icon($settings['wptg_doc_icon'] ?? null, '')
    );
}

/**
 * The small inline SVGs of the document card. Drawn here (no icon pack), line
 * only, with currentColor, so the colors of Style > Document Card paint them.
 *
 *   doc       the big card icon: a page with a folded corner, 4 text lines and
 *             a pencil on its right side (used until an editor picks a Card Icon)
 *   eye       "Lihat" button
 *   download  "Unduh" button: arrow down to a line
 *
 * @param string $name 'doc'|'eye'|'download' (unknown = 'doc').
 */
function wptg_render_document_svg(string $name): string
{
    $icons = [
        'doc'      => [
            'class'    => 'wptg-doc-icon-svg',
            'view_box' => '7 5 48 46',
            'stroke'   => '1.6',
            'shape'    => '<path d="M8 6h20l8 8v34H8z"/><path d="M28 6v8h8"/>'
                . '<path d="M14 22h16M14 29h16M14 36h16M14 43h9"/>'
                . '<path d="M35 49l2.5-7.5 11-11 5 5-11 11z"/><path d="M37.5 41.5l5 5M45.5 33.5l5 5"/>',
        ],
        'eye'      => [
            'class'    => 'wptg-doc-btn-svg',
            'view_box' => '0 0 24 24',
            'stroke'   => '2.2',
            'shape'    => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        ],
        'download' => [
            'class'    => 'wptg-doc-btn-svg',
            'view_box' => '0 0 24 24',
            'stroke'   => '2.2',
            'shape'    => '<path d="M12 4v11"/><path d="M7 11l5 5 5-5"/><path d="M5 20h14"/>',
        ],
    ];

    $icon = $icons[$name] ?? $icons['doc'];

    return sprintf(
        '<svg class="%s" viewBox="%s" fill="none" stroke="currentColor" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round" focusable="false" aria-hidden="true">%s</svg>',
        $icon['class'],
        $icon['view_box'],
        $icon['stroke'],
        $icon['shape']
    );
}

/**
 * HTML of a PDF's first-page preview, or '' when WordPress made none.
 *
 * WordPress only makes it when the server can render PDFs (Imagick). Without
 * it, wp_get_attachment_image() does not return '': it returns an <img> whose
 * src is the .pdf file itself, which would show a broken image. So the
 * returned src is checked first.
 *
 * @param int                  $attachment_id PDF attachment id.
 * @param array<string,string> $image_attrs   Attributes for the <img>.
 */
function wptg_get_pdf_preview_image(int $attachment_id, array $image_attrs): string
{
    $source = wp_get_attachment_image_src($attachment_id, 'large');
    if (!is_array($source) || !is_string($source[0] ?? null)) {
        return '';
    }

    $extension = strtolower(pathinfo((string) wp_parse_url($source[0], PHP_URL_PATH), PATHINFO_EXTENSION));
    if ($extension === 'pdf') {
        return '';
    }

    return wp_get_attachment_image($attachment_id, 'large', false, $image_attrs);
}

/**
 * The cover image of a document card, first match wins:
 *   1. the featured image of the post,
 *   2. the first image file in "Document Files",
 *   3. the preview WordPress generated for the first PDF file,
 *   4. nothing ('' = the card has no cover block).
 *
 * The <img> is decorative (alt=""): the title is right above it.
 *
 * @param \WP_Post                         $post  Post of the card.
 * @param array<int,array<string,mixed>>   $files Output of wptg_get_document_files().
 */
function wptg_get_document_cover_html(\WP_Post $post, array $files): string
{
    $image_attrs = ['class' => 'wptg-doc-cover-img', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async'];

    if (has_post_thumbnail($post->ID)) {
        $html = get_the_post_thumbnail($post->ID, 'large', $image_attrs);
        if ($html !== '') {
            return $html;
        }
    }

    foreach ($files as $file) {
        if ($file['kind'] === 'image') {
            $html = wp_get_attachment_image((int) $file['id'], 'large', false, $image_attrs);
            if ($html !== '') {
                return $html;
            }
        }
    }

    foreach ($files as $file) {
        if ($file['kind'] === 'pdf') {
            // Only the FIRST PDF: if it has no preview, the next PDF is not a better "first page".
            return wptg_get_pdf_preview_image((int) $file['id'], $image_attrs);
        }
    }

    return '';
}

/**
 * The "Lihat" and "Unduh" pills of a card. '' when the post has no valid file.
 *
 * - Lihat: <button> that opens the popup (it is the keyboard-focusable way in).
 * - Unduh, ONE file: a plain <a download> to that file, no popup.
 * - Unduh, MANY files: a <button> that opens the popup with the download
 *   buttons visible (data-wptg-show-download), so the visitor picks a file.
 *   Only for the popup click behaviors; with other behaviors there is no popup.
 *
 * @param \WP_Post                       $post     Post of the card.
 * @param array<int,array<string,mixed>> $files    Output of wptg_get_document_files().
 * @param array<string,mixed>            $options  Output of wptg_normalize_document_options().
 * @param bool                           $is_popup Whether the click behavior opens the popup.
 */
function wptg_render_document_actions(\WP_Post $post, array $files, array $options, bool $is_popup): string
{
    if ($files === []) {
        return '';
    }

    $title      = get_the_title($post->ID);
    $popup_attr = $is_popup ? sprintf(' data-wptg-media="%d" aria-haspopup="dialog"', $post->ID) : '';

    // aria-label repeats the card title, because many cards show the same "Lihat".
    $build = static function (string $tag, string $modifier, string $attrs, string $text, string $icon_svg) use ($title): string {
        return sprintf(
            '<%1$s class="wptg-doc-btn wptg-doc-btn--%2$s"%3$s aria-label="%4$s">%5$s<span class="wptg-doc-btn-text">%6$s</span></%1$s>',
            $tag,
            $modifier,
            $attrs,
            esc_attr($text . ': ' . $title),
            $icon_svg,
            esc_html($text)
        );
    };

    $buttons = $build(
        'button',
        'view',
        ' type="button"' . $popup_attr,
        $options['view_text'],
        $options['show_view_icon'] ? wptg_render_document_svg('eye') : ''
    );

    if ($options['show_card_download']) {
        $download_svg = $options['show_download_icon'] ? wptg_render_document_svg('download') : '';

        if (count($files) === 1) {
            $buttons .= $build(
                'a',
                'download',
                sprintf(' href="%s" download rel="noopener"', esc_url((string) $files[0]['url'])),
                $options['download_text'],
                $download_svg
            );
        } elseif ($is_popup) {
            $buttons .= $build(
                'button',
                'download',
                ' type="button"' . $popup_attr . ' data-wptg-show-download="1"',
                $options['download_text'],
                $download_svg
            );
        }
    }

    return '<div class="wptg-doc-card-actions">' . $buttons . '</div>';
}

/**
 * Document card (content_style = document_list), two rows:
 *
 *   head   [icon]  [title / "Diunggah pada: 1 Oktober 2026" / "3 file"]
 *   cover  wide image BELOW the head, "Lihat" + "Unduh" pills on its top-right corner
 *
 * Without a cover the two pills become their own row under the head, on the right.
 * A post without a valid file has no pills at all.
 *
 * Clicking: the <article> has no role and no tabindex, because it holds real
 * buttons/links (a focusable article around them would be nested interactive
 * content). A mouse click anywhere on the card still opens the popup through
 * its data-wptg-media (media-popup.js, the same single listener as every card).
 * Keyboard users reach the popup through the "Lihat" <button>.
 *
 * @param \WP_Post            $post             Post to render.
 * @param string              $click_behavior   'inline_detail'|'modal'|'media_popup'|'document_preview'.
 * @param array<string,mixed> $document_options Output of wptg_normalize_document_options(); empty = defaults.
 */
function wptg_render_item_document_list(\WP_Post $post, string $click_behavior, array $document_options = []): string
{
    if ($document_options === []) {
        $document_options = wptg_normalize_document_options([]);
    }

    $files      = wptg_get_document_files($post->ID);
    $file_count = count($files);
    $is_popup   = in_array($click_behavior, ['media_popup', 'document_preview'], true);

    $cover_html   = $document_options['show_cover'] ? wptg_get_document_cover_html($post, $files) : '';
    $actions_html = wptg_render_document_actions($post, $files, $document_options, $is_popup);
    // Built-in drawing, or the Card Icon (empty here in AJAX answers: taxonomy-grid.js fills it in).
    $icon_html = $document_options['custom_icon'] ? $document_options['icon_html'] : wptg_render_document_svg('doc');

    $extra = [];
    if ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }
    // media-popup.js reads this from the clicked element (or its card) to show Unduh inside the popup.
    if ($document_options['show_download']) {
        $extra['data-wptg-show-download'] = '1';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra, false);

    ob_start();
    ?>
    <article class="wptg-doc-card"<?php echo $attrs; ?>>
        <div class="wptg-doc-head">
            <?php if ($document_options['show_icon']): ?>
                <?php // Keep the icon span on ONE line: it must be :empty for a custom icon that JS fills in. ?>
                <span class="wptg-doc-icon" aria-hidden="true"><?php echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_document_svg() / wptg_render_icon(). ?></span>
            <?php endif; ?>
            <div class="wptg-doc-body">
                <h3 class="wptg-doc-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
                <?php if ($document_options['show_date']): ?>
                    <span class="wptg-doc-date"><?php echo esc_html($document_options['date_label'] . ' ' . get_the_date('j F Y', $post)); ?></span>
                <?php endif; ?>
                <?php if ($document_options['show_count'] && $file_count > 0): ?>
                    <?php /* translators: %d: number of files in the document. */ ?>
                    <span class="wptg-doc-count"><?php echo esc_html(sprintf(_n('%d file', '%d file', $file_count, 'wp-taxonomy-grid'), $file_count)); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($cover_html !== ''): ?>
            <div class="wptg-doc-cover"><?php echo $cover_html . $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress image markup + escaped inside wptg_render_document_actions(). ?></div>
        <?php elseif ($actions_html !== ''): ?>
            <?php echo $actions_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside wptg_render_document_actions(). ?>
        <?php endif; ?>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Dispatch to the right per-item renderer for one post.
 *
 * @param \WP_Post $post              Post to render.
 * @param string   $content_style     'numbered_feed'|'grid_thumbnail'|'list_thumbnail'|'document_list'.
 * @param int      $display_number    Sequential display position (numbered_feed only).
 * @param bool     $is_first_overall  Whether this is the first item overall (numbered_feed only).
 * @param string   $click_behavior    'inline_detail'|'modal'|'media_popup'|'document_preview'.
 * @param array<string,mixed> $feed_options     Output of wptg_normalize_feed_options() (numbered_feed only).
 * @param array<string,mixed> $document_options Output of wptg_normalize_document_options() (document_list only).
 */
function wptg_render_section_item(\WP_Post $post, string $content_style, int $display_number, bool $is_first_overall, string $click_behavior, array $feed_options = [], array $document_options = []): string
{
    return match ($content_style) {
        'grid_thumbnail' => wptg_render_item_grid_thumbnail($post, $click_behavior),
        'list_thumbnail' => wptg_render_item_list_thumbnail($post, $click_behavior),
        'document_list'  => wptg_render_item_document_list($post, $click_behavior, $document_options),
        default          => wptg_render_item_numbered_feed($post, $display_number, $is_first_overall, $click_behavior, $feed_options),
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
    $section_terms      = $section['terms'] ?? [];

    ob_start();
    ?>
    <div class="wptg-section-group wptg-section-group--<?php echo esc_attr($section['type'] === 'flat_list' ? 'flat' : 'accordion'); ?><?php echo $is_accordion_open ? ' open' : ''; ?>"
         data-section-index="<?php echo esc_attr($section['index']); ?>"
         data-section-type="<?php echo esc_attr($section['type']); ?>"
         data-post-type="<?php echo esc_attr($section['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($section['taxonomy']); ?>"
         data-content-style="<?php echo esc_attr($section['content_style']); ?>"
         data-click-behavior="<?php echo esc_attr($section['click_behavior']); ?>"
         data-posts-per-page="<?php echo esc_attr($section['posts_per_page']); ?>"
         data-search-enabled="<?php echo $section['enable_search'] ? '1' : '0'; ?>"
         data-section-label="<?php echo esc_attr($section['label']); ?>">

        <?php if ($section['type'] === 'flat_list'): ?>
            <button type="button" class="wptg-section-nav-trigger <?php echo $is_section_active ? 'active' : ''; ?>"
                    aria-pressed="<?php echo $is_section_active ? 'true' : 'false'; ?>">
                <?php // Same "dash + label" look as a merged row in Single mode; styled
                      // by Style > Category Divider (Merged). Child of the button so
                      // "Position: Absolute" anchors to the button itself. ?>
                <span class="wptg-term-divider wptg-term-divider-merged" aria-hidden="true"></span>
                <span class="wptg-term-name"><?php echo esc_html($section['label']); ?></span>
            </button>
        <?php else: ?>
            <button type="button" class="wptg-section-toggle" aria-expanded="<?php echo $is_accordion_open ? 'true' : 'false'; ?>">
                <?php // Same structure as a Single-mode accordion header, so Style >
                      // Category Divider (Accordion) and Category Name (Accordion) apply. ?>
                <span class="wptg-term-head-left">
                    <span class="wptg-term-divider wptg-term-divider-accordion" aria-hidden="true"></span>
                    <span class="wptg-term-name"><?php echo esc_html($section['label']); ?></span>
                </span>
                <span class="wptg-term-chevron" aria-hidden="true"></span>
            </button>
            <?php if ($section_terms === []): ?>
                <?php // No terms yet: a skeleton list instead of an empty <ul>. A <div>, because a <div> inside a <ul> is invalid HTML; it keeps the open/close behavior of .wptg-section-terms. ?>
                <div class="wptg-section-terms wptg-section-terms--empty"><?php echo wptg_render_skeleton('sidebar_terms', 3); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?></div>
            <?php else: ?>
            <ul class="wptg-section-terms">
                <?php foreach ($section_terms as $term): ?>
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
            <?php // Shown by JS when the sidebar search matches nothing: a skeleton instead of a "not found" text. ?>
            <div class="wptg-section-no-results" style="display:none;"><?php echo wptg_render_skeleton('sidebar_terms', 3); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?></div>
            <?php endif; ?>
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
 * @param array<string,mixed>                                   $section        Normalized + annotated section.
 * @param array{section_index:int,term_id:int,post_id:int}      $active         The resolved default-active entry for THIS section.
 * @param array<string,mixed>                                   $feed_options   Output of wptg_normalize_feed_options().
 * @param string                                                $load_more_text Label of the "load more" button.
 * @param array<string,mixed>                                   $document_options Output of wptg_normalize_document_options() (document_list only).
 */
function wptg_render_section_content_panel(array $section, array $active, array $feed_options, string $load_more_text, array $document_options = []): string
{
    if ($section['content_style'] === 'single_detail') {
        return $active['post_id'] > 0
            ? wptg_render_single_post_detail($active['post_id'], true)
            : wptg_render_skeleton_for_style($section['content_style']);
    }

    // An accordion section with no term picked is the empty state. Querying
    // with term 0 would skip the tax_query and list EVERY post of the post type.
    if ($section['type'] !== 'flat_list' && $active['term_id'] === 0) {
        return wptg_render_skeleton_for_style($section['content_style']);
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
        $items_html .= wptg_render_section_item($post, $section['content_style'], $i + 1, $i === 0, $section['click_behavior'], $feed_options, $document_options);
        $i++;
    }
    wp_reset_postdata();

    // No items: skeleton only, no items wrapper and no Load More button.
    if ($items_html === '') {
        return wptg_render_skeleton_for_style($section['content_style']);
    }

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
            <?php echo wptg_render_load_more_button($load_more_text); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
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
    $rows     = is_array($rows) ? $rows : [];
    $sections = [];

    foreach ($rows as $i => $row) {
        if (!is_array($row)) {
            continue;
        }

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

    // Empty State: no valid section at all is NOT a reason to stop. The
    // layout is still drawn (sidebar + panel as skeletons); $sections stays
    // [] and every step below copes with that.

    // Deep link (?wptg_section=...&wptg_term=...) wins over the normal
    // default. It is rendered on the server, so the right section/term is
    // open on first paint (no flicker, works without JS). An invalid or
    // unknown value returns null and silently falls back to the default.
    $deep_link_active = wptg_resolve_deep_link(
        $sections,
        sanitize_key(wptg_read_deep_link_param('wptg_section')),
        sanitize_title(wptg_read_deep_link_param('wptg_term'))
    );

    // Priority: deep link > first section WITH data > first valid section in
    // its empty state (so the title/breadcrumb still show a label).
    $default_with_data = wptg_resolve_default_active($sections);
    $default_active    = $deep_link_active ?? $default_with_data ?? wptg_resolve_empty_active($sections);

    // Reason for the editor-only note (empty string = nothing to explain).
    $editor_problem = '';
    if ($sections === []) {
        $editor_problem = __('belum ada section valid. Tambahkan section di Content > Sections, atau cek Post Type / Taxonomy-nya.', 'wp-taxonomy-grid');
    } elseif ($default_with_data === null) {
        $editor_problem = __('semua section belum punya konten (belum ada post terbit atau kategori).', 'wp-taxonomy-grid');
    }

    // ONE search box for the whole widget (not one per section). It reuses
    // the legacy .wptg-search* classes, so Style > Search Box applies to it.
    // taxonomy-grid.js applies the typed query to whichever section is open.
    $show_search        = ($settings['wptg_sections_show_search'] ?? 'yes') === 'yes';
    $search_placeholder = trim((string) ($settings['wptg_sections_search_placeholder'] ?? ''));
    $search_placeholder = $search_placeholder !== '' ? $search_placeholder : __('Cari...', 'wp-taxonomy-grid');
    $search_icon_html   = wptg_render_icon($settings['wptg_sections_search_icon'] ?? null, '&#128269;');

    // "Search Position": the sidebar box, the full-width box above the list,
    // or both. Both boxes are just inputs; taxonomy-grid.js keeps them in
    // sync and runs ONE search for the widget.
    $search_position     = (string) ($settings['wptg_sections_search_position'] ?? 'both');
    $search_position     = in_array($search_position, ['sidebar', 'content', 'both'], true) ? $search_position : 'both';
    $show_sidebar_search = $show_search && in_array($search_position, ['sidebar', 'both'], true);
    $show_content_search = $show_search && in_array($search_position, ['content', 'both'], true);

    // Feed look (layout, number, excerpt, read-more) + load more label. Also
    // exposed as data-* on the wrapper so AJAX-loaded items look the same.
    $feed_options     = wptg_feed_options_from_settings($settings);
    $document_options = wptg_document_options_from_settings($settings);
    $load_more_text   = trim((string) ($settings['wptg_load_more_text'] ?? ''));
    $load_more_text = $load_more_text !== '' ? $load_more_text : __('Lihat Lebih Banyak', 'wp-taxonomy-grid');

    // Look the active section up by its repeater index. $sections is a list
    // that skips invalid rows, so its keys don't always equal that index.
    $sections_by_index = [];
    foreach ($sections as $section) {
        $sections_by_index[$section['index']] = $section;
    }
    $active_section = $default_active !== null ? ($sections_by_index[$default_active['section_index']] ?? null) : null;
    $active_label   = $active_section !== null ? (string) $active_section['label'] : '';

    // Header + breadcrumb are required in every layout. In Sections mode an
    // empty Title / Current Label FOLLOWS the open section (taxonomy-grid.js
    // updates them on every switch); a filled one stays fixed. Subtitle
    // defaults to the title of the page the widget sits on, e.g. "Berita".
    $show_header     = ($settings['wptg_show_header'] ?? 'yes') === 'yes';
    $show_breadcrumb = ($settings['wptg_show_breadcrumb'] ?? 'yes') === 'yes';

    $fixed_subtitle = trim((string) ($settings['wptg_header_subtitle'] ?? ''));
    $fixed_title    = trim((string) ($settings['wptg_header_title'] ?? ''));
    $fixed_middle   = trim((string) ($settings['wptg_breadcrumb_middle'] ?? ''));
    $fixed_current  = trim((string) ($settings['wptg_breadcrumb_current'] ?? ''));
    $home_label     = trim((string) ($settings['wptg_breadcrumb_home'] ?? ''));

    $header_subtitle = $fixed_subtitle !== '' ? $fixed_subtitle : get_the_title();
    $header_title    = $fixed_title !== '' ? $fixed_title : $active_label;
    $crumb_middle    = $fixed_middle !== '' ? $fixed_middle : $header_subtitle;
    $crumb_current   = $fixed_current !== '' ? $fixed_current : $active_label;
    $home_label      = $home_label !== '' ? $home_label : __('Beranda', 'wp-taxonomy-grid');

    $breadcrumb_sep_html = wptg_render_icon($settings['wptg_breadcrumb_icon'] ?? null, '&rarr;');
    $show_header_lines   = ($settings['wptg_show_header_lines'] ?? 'yes') === 'yes';

    // Content title (h2 above the list). Empty manual text = follow the open
    // section's label, same idea as the header title above.
    $show_content_title  = ($settings['wptg_sections_show_content_title'] ?? 'yes') === 'yes';
    $fixed_content_title = trim((string) ($settings['wptg_sections_content_title'] ?? ''));
    $content_title       = $fixed_content_title !== '' ? $fixed_content_title : $active_label;

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-sections <?php echo esc_attr($settings['wptg_sections_class'] ?? ''); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-mode="sections"
         data-deep-link="<?php echo $deep_link_active !== null ? '1' : '0'; ?>"
         data-dynamic-title="<?php echo $fixed_title === '' ? '1' : '0'; ?>"
         data-dynamic-crumb="<?php echo $fixed_current === '' ? '1' : '0'; ?>"
         data-dynamic-content-title="<?php echo $fixed_content_title === '' ? '1' : '0'; ?>"
         data-feed-layout="<?php echo esc_attr($feed_options['layout']); ?>"
         data-show-number="<?php echo $feed_options['show_number'] ? '1' : '0'; ?>"
         data-excerpt-length="<?php echo esc_attr($feed_options['excerpt_length']); ?>"
         data-readmore-text="<?php echo esc_attr($feed_options['readmore_text']); ?>"
         data-doc-options="<?php echo esc_attr((string) wp_json_encode(array_diff_key($document_options, ['icon_html' => true]))); ?>"
         data-load-more-text="<?php echo esc_attr($load_more_text); ?>">

        <?php if ($feed_options['readmore_icon_html'] !== ''): ?>
            <?php // Icon template for items that arrive via AJAX (the server does not render the icon for them). ?>
            <template class="wptg-readmore-icon-template"><?php echo $feed_options['readmore_icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></template>
        <?php endif; ?>

        <?php if ($document_options['icon_html'] !== ''): ?>
            <?php // Same idea for the Card Icon of document cards (icon_html itself is never put in data-doc-options). ?>
            <template class="wptg-doc-icon-template"><?php echo $document_options['icon_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wptg_render_icon(). ?></template>
        <?php endif; ?>

        <?php if ($show_header): ?>
            <?php echo wptg_render_header_block($header_subtitle, $header_title, $show_header_lines); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
        <?php endif; ?>

        <div class="wptg-section-body">
        <?php if ($show_breadcrumb): ?>
            <?php echo wptg_render_breadcrumb_block($home_label, $crumb_middle, $crumb_current, $breadcrumb_sep_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
        <?php endif; ?>

        <div class="wptg-body wptg-sections-body">
            <aside class="wptg-sidebar wptg-sections-sidebar">
                <?php if ($show_sidebar_search): ?>
                    <?php echo wptg_render_search_box($search_placeholder, $search_icon_html); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
                <?php endif; ?>

                <?php if ($sections === []): ?>
                    <?php echo wptg_render_skeleton('sidebar_terms', 4); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
                <?php endif; ?>

                <?php foreach ($sections as $section): ?>
                    <?php echo wptg_render_section_sidebar_group($section, $default_active); ?>
                <?php endforeach; ?>
            </aside>

            <?php // data-active-section: taxonomy-grid.js needs to know the open section even when the panel is an empty-state skeleton (it has no items wrapper to read it from). ?>
            <main class="wptg-sections-content"
                  data-section-loaded="<?php echo $default_active !== null ? 'true' : 'false'; ?>"
                  data-active-section="<?php echo $default_active !== null ? esc_attr((string) $default_active['section_index']) : ''; ?>">
                <?php if ($show_content_title && $content_title !== ''): ?>
                    <h2 class="wptg-content-title"><?php echo esc_html($content_title); ?></h2>
                <?php endif; ?>

                <?php if ($show_content_search): ?>
                    <?php echo wptg_render_search_box($search_placeholder, $search_icon_html, 'wptg-search--content'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
                <?php endif; ?>

                <?php // Only this panel is replaced by JS, so the title and the search box above survive every section switch / search. ?>
                <div class="wptg-sections-panel">
                    <?php if ($active_section !== null && $default_active !== null): ?>
                        <?php echo wptg_render_section_content_panel($active_section, $default_active, $feed_options, $load_more_text, $document_options); ?>
                    <?php else: ?>
                        <?php echo wptg_render_skeleton('feed', 3); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built by the helper. ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
        </div><?php // .wptg-section-body ?>
    </div>
    <?php
    // The note sits OUTSIDE the wrapper so it never disturbs the layout.
    return (string) ob_get_clean() . wptg_render_editor_note($editor_problem);
}

/**
 * AJAX handler: query one page of section items (switch section/term,
 * "Lihat Lebih Banyak" pagination, or a flat_list section's search-box
 * re-query). single_detail never calls this action for real content — it
 * has no pagination/search-re-query UI and instead reuses
 * wptg_get_single_post directly, same as legacy detail mode. The only
 * exception is its empty state (an accordion header without terms), which
 * gets the detail skeleton from here.
 *
 * The answer carries `empty: true` when `html` is a skeleton (no items).
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
    $click_behavior = wptg_normalize_click_behavior(isset($_POST['click_behavior']) ? wp_unslash($_POST['click_behavior']) : 'inline_detail');
    $page           = max(1, isset($_POST['page']) ? absint($_POST['page']) : 1);
    $posts_per_page = min(48, max(1, isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 6));
    $search         = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    // Feed look sent by taxonomy-grid.js from the wrapper's data-* attributes.
    // Re-validated here; the read-more ICON is deliberately not accepted
    // (JS copies it from the page's own <template>).
    $feed_options = wptg_normalize_feed_options(
        isset($_POST['feed_layout']) ? sanitize_key(wp_unslash($_POST['feed_layout'])) : '',
        !isset($_POST['show_number']) || $_POST['show_number'] === '1',
        isset($_POST['excerpt_length']) ? absint($_POST['excerpt_length']) : 40,
        isset($_POST['readmore_text']) ? sanitize_text_field(wp_unslash($_POST['readmore_text'])) : ''
    );

    // Document Card look: one JSON string that taxonomy-grid.js copies from the wrapper's data-doc-options.
    // Decoded and re-validated here; the Card Icon HTML is deliberately not accepted (JS copies it from the page's <template>).
    // Not sanitized here on purpose: it is JSON, and every decoded value is sanitized one by one in wptg_normalize_document_options().
    $document_json    = isset($_POST['doc_options']) && is_string($_POST['doc_options']) ? (string) wp_unslash($_POST['doc_options']) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $document_raw     = strlen($document_json) <= 2000 ? json_decode($document_json, true) : null;
    $document_options = wptg_normalize_document_options(is_array($document_raw) ? $document_raw : []);

    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->public) {
        wp_send_json_error(['message' => 'Invalid post type.']);
    }

    // single_detail is accepted ONLY to answer the empty state below (an
    // accordion header without terms). A real single_detail post is still
    // loaded through wptg_get_single_post, never through this action.
    if (!in_array($content_style, ['numbered_feed', 'grid_thumbnail', 'list_thumbnail', 'document_list', 'single_detail'], true)) {
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

    // Empty state without a query: single_detail (see above) or an accordion
    // section with no term (term 0 would skip the tax_query and list every post).
    if ($content_style === 'single_detail' || ($taxonomy !== '' && $term_id === 0)) {
        wp_send_json_success([
            'html'     => wptg_render_skeleton_for_style($content_style),
            'has_more' => false,
            'page'     => 1,
            'empty'    => true,
        ]);
    }

    $query = wptg_query_section_items($post_type, $taxonomy, $term_id, $content_style, $page, $posts_per_page, $search);

    $display_offset = ($page - 1) * $posts_per_page;
    $html           = '';
    $i              = 0;
    foreach ($query->posts as $post) {
        // Search results also start with a "first" card, so item 01 looks the
        // same with or without a search term.
        $is_first_overall = ($page === 1 && $i === 0);
        $html             .= wptg_render_section_item($post, $content_style, $display_offset + $i + 1, $is_first_overall, $click_behavior, $feed_options, $document_options);
        $i++;
    }
    wp_reset_postdata();

    // Nothing on page 1 (search without hits, a term without items): answer
    // with the same skeleton the server prints on first load. Nothing on a
    // later page only means "no more", so the list stays as it is.
    $is_empty = ($html === '' && $page === 1);
    if ($is_empty) {
        $html = wptg_render_skeleton_for_style($content_style);
    }

    wp_send_json_success([
        'html'     => $html,
        'has_more' => $is_empty ? false : wptg_has_more_pages($page, $posts_per_page, $query->found_posts),
        'page'     => $page,
        'empty'    => $is_empty,
    ]);
}
add_action('wp_ajax_wptg_query_items', 'wptg_ajax_query_section_items');
add_action('wp_ajax_nopriv_wptg_query_items', 'wptg_ajax_query_section_items');
