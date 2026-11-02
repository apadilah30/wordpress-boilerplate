<?php
/**
 * Plugin Name: Social Media Showcase
 * Description: Elementor widget — social platform tabs (icon switcher) + per-platform panel (manual 4x4 gallery or a feed-plugin shortcode) + an "Open Account" button.
 * Version:     1.1.0
 * Author:      Apadilah
 * Text Domain: wp-social-showcase
 * Requires Plugins: elementor
 *
 * No social media API integration here on purpose — managing OAuth/token
 * refresh/rate limits for Instagram/Facebook/X/TikTok is a significant,
 * ongoing maintenance burden better left to a dedicated, actively
 * maintained feed plugin (e.g. Smash Balloon, Feed Them Social).
 *
 * Each platform panel has two modes:
 *   - Manual Gallery (default): an admin picks up to 16 images per
 *     platform (via the Media Library) and an account URL.
 *   - Shortcode: the panel prints the output of a feed plugin's shortcode,
 *     so the feed updates itself. This widget only keeps the icon tabs and
 *     the "Open Account" button.
 * Clicking a platform icon switches the panel + the "Open" button target —
 * all client-side, no AJAX needed since every panel is already in the page.
 *
 * @package WpSocialShowcase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPSS_PATH', plugin_dir_path(__FILE__));
define('WPSS_URL', plugin_dir_url(__FILE__));
define('WPSS_VERSION', '1.1.0');

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wpss-styles',
        WPSS_URL . 'assets/css/social-showcase.css',
        [],
        WPSS_VERSION
    );

    wp_enqueue_script(
        'wpss-scripts',
        WPSS_URL . 'assets/js/social-showcase.js',
        [],
        WPSS_VERSION,
        true
    );
});

add_action('elementor/elements/categories_registered', static function ($elements_manager): void {
    $elements_manager->add_category('wpss-category', [
        'title' => __('Social Showcase', 'wp-social-showcase'),
        'icon'  => 'fa fa-share-alt',
    ]);
});

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    require_once WPSS_PATH . 'includes/class-elementor-widget.php';
    $widgets_manager->register(new WPSS_Elementor_Widget());
});

/**
 * Render the whole widget: tab bar (one tab per platform) + one panel per
 * platform (first one visible by default) + the "Open" button.
 *
 * @param array<string,mixed> $atts
 *   'title'        => string
 *   'open_text'    => string
 *   'platforms'    => array<int,array{icon:mixed,label:string,url:string,gallery:array<int,array{id:int,url:string}>,feed_source?:string,shortcode?:string}>
 *                     feed_source 'manual' (default) shows the gallery,
 *                     'shortcode' prints the feed plugin shortcode instead.
 *   'class'        => string
 */
function wpss_render_showcase(array $atts): string
{
    $platforms = $atts['platforms'] ?? [];

    if (empty($platforms)) {
        return '<p class="wpss-empty">' . esc_html__('Add at least one platform in the widget settings.', 'wp-social-showcase') . '</p>';
    }

    $instance_id = 'wpss-' . wp_generate_uuid4();

    ob_start();
    ?>
    <div class="wpss-wrapper <?php echo esc_attr($atts['class']); ?>" data-instance-id="<?php echo esc_attr($instance_id); ?>">
        <?php if ($atts['title'] !== ''): ?>
            <h2 class="wpss-title"><?php echo esc_html($atts['title']); ?></h2>
        <?php endif; ?>

        <div class="wpss-tabs" role="tablist">
            <?php foreach ($platforms as $index => $platform): ?>
                <button type="button"
                        class="wpss-tab <?php echo $index === 0 ? 'active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>"
                        data-panel-index="<?php echo esc_attr($index); ?>"
                        data-account-url="<?php echo esc_url($platform['url']); ?>"
                        title="<?php echo esc_attr($platform['label']); ?>">
                    <?php echo wpss_render_icon($platform['icon'] ?? null, '&#9679;'); ?>
                    <span class="wpss-tab-label"><?php echo esc_html($platform['label']); ?></span>
                </button>
            <?php endforeach; ?>

            <a class="wpss-open-btn"
               href="<?php echo esc_url($platforms[0]['url'] ?? ''); ?>"
               target="_blank" rel="noopener noreferrer">
                <?php echo esc_html($atts['open_text']); ?>
            </a>
        </div>

        <div class="wpss-panels">
            <?php foreach ($platforms as $index => $platform): ?>
                <div class="wpss-panel <?php echo $index === 0 ? 'active' : ''; ?>" data-panel-index="<?php echo esc_attr($index); ?>">
                    <?php if (($platform['feed_source'] ?? 'manual') === 'shortcode'): ?>
                        <div class="wpss-feed">
                            <?php
                            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML dari plugin feed, tanggung jawab plugin tsb.
                            echo wpss_render_feed_shortcode((string) ($platform['shortcode'] ?? ''));
                            ?>
                        </div>
                    <?php else: ?>
                        <div class="wpss-gallery">
                            <?php foreach (array_slice($platform['gallery'], 0, 16) as $image): ?>
                                <a class="wpss-gallery-item" href="<?php echo esc_url($platform['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <img src="<?php echo esc_url($image['url']); ?>" alt="" loading="lazy">
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Return the shortcode string only if it is nothing but registered
 * shortcode(s), otherwise ''.
 *
 * Kenapa dicek: nilai ini diketik di editor lalu dieksekusi do_shortcode().
 * Kita tidak mau teks/HTML lain ikut tercetak lewat jalur ini, jadi sisa
 * string setelah semua shortcode terdaftar dibuang harus kosong. Efek
 * samping: shortcode dari plugin yang tidak aktif juga dianggap tidak valid.
 */
function wpss_clean_shortcode(string $raw): string
{
    $value = trim($raw);

    if ($value === '' || !str_starts_with($value, '[') || !str_ends_with($value, ']')) {
        return '';
    }

    $leftover = preg_replace('/' . get_shortcode_regex() . '/', '', $value);
    if ($leftover === null || trim($leftover) !== '') {
        return '';
    }

    return $value;
}

/**
 * Small notice shown only to users who can edit content, so visitors never
 * see setup errors.
 */
function wpss_render_editor_note(string $message): string
{
    if (!current_user_can('edit_posts')) {
        return '';
    }

    return '<p class="wpss-note">' . esc_html($message) . '</p>';
}

/**
 * Run a feed plugin shortcode and return its HTML as-is (escaping it is
 * the feed plugin's job). Editors get a hint when the shortcode is
 * invalid or the feed came back empty; visitors get nothing.
 */
function wpss_render_feed_shortcode(string $raw_shortcode): string
{
    $shortcode = wpss_clean_shortcode($raw_shortcode);

    if ($shortcode === '') {
        return wpss_render_editor_note(__('Shortcode tidak valid', 'wp-social-showcase'));
    }

    $output = do_shortcode($shortcode);

    if (trim($output) === '') {
        return wpss_render_editor_note(__('Feed kosong: cek koneksi akun di plugin feed', 'wp-social-showcase'));
    }

    return $output;
}

/**
 * Render an Elementor icon control value, falling back to raw HTML if no
 * icon was picked (or Elementor's icon renderer isn't available). Mirrors
 * Taxonomy Grid's own helper conceptually, but named with this plugin's
 * own "wpss_" prefix (not "wptg_") so the two plugins never collide if
 * both are active at the same time — PHP does not allow two different
 * files to declare a global function with the exact same name.
 *
 * @param mixed $icon_value
 */
function wpss_render_icon($icon_value, string $fallback_html = ''): string
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
