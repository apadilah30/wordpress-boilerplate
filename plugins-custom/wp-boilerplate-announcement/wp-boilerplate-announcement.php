<?php
/**
 * Plugin Name:  WP Boilerplate Announcement
 * Plugin URI:   https://example.com/plugins/wp-boilerplate-announcement
 * Description:  Reference plugin bawaan boilerplate. Menampilkan banner pengumuman
 *               site-wide yang bisa di-config dari admin. Aman untuk di-copy sebagai
 *               template untuk plugin custom lain.
 * Version:      1.0.0
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * Author:       Apadilah
 * License:      GPL-2.0-or-later
 * Text Domain:  wp-boilerplate-announcement
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

const WPBA_OPTION_KEY = 'wpba_settings';
const WPBA_VERSION    = '1.0.0';

// --------------------------------------------------------------------
// Activation: set default options
// --------------------------------------------------------------------
register_activation_hook(__FILE__, static function (): void {
    add_option(WPBA_OPTION_KEY, [
        'enabled'  => false,
        'message'  => __('Welcome to our site!', 'wp-boilerplate-announcement'),
        'bg_color' => '#0073aa',
    ]);
});

// --------------------------------------------------------------------
// Admin: settings page
// --------------------------------------------------------------------
add_action('admin_menu', static function (): void {
    add_options_page(
        __('Announcement Banner', 'wp-boilerplate-announcement'),
        __('Announcement Banner', 'wp-boilerplate-announcement'),
        'manage_options',
        'wpba-settings',
        'wpba_render_settings_page'
    );
});

add_action('admin_init', static function (): void {
    register_setting('wpba_settings_group', WPBA_OPTION_KEY, [
        'type'              => 'array',
        'sanitize_callback' => 'wpba_sanitize_settings',
        'default'           => [],
    ]);
});

/**
 * @param mixed $input
 * @return array{enabled: bool, message: string, bg_color: string}
 */
function wpba_sanitize_settings($input): array
{
    $input = is_array($input) ? $input : [];
    return [
        'enabled'  => !empty($input['enabled']),
        'message'  => isset($input['message']) ? sanitize_text_field((string) $input['message']) : '',
        'bg_color' => sanitize_hex_color((string) ($input['bg_color'] ?? '')) ?: '#0073aa',
    ];
}

function wpba_render_settings_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }
    $defaults = ['enabled' => false, 'message' => '', 'bg_color' => '#0073aa'];
    $opts     = wp_parse_args((array) get_option(WPBA_OPTION_KEY, []), $defaults);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Announcement Banner', 'wp-boilerplate-announcement'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('wpba_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="wpba-enabled"><?php esc_html_e('Enable banner', 'wp-boilerplate-announcement'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="wpba-enabled" name="wpba_settings[enabled]" value="1" <?php checked($opts['enabled']); ?>>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpba-message"><?php esc_html_e('Message', 'wp-boilerplate-announcement'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wpba-message" class="regular-text"
                               name="wpba_settings[message]"
                               value="<?php echo esc_attr($opts['message']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpba-bg"><?php esc_html_e('Background color', 'wp-boilerplate-announcement'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="wpba-bg"
                               name="wpba_settings[bg_color]"
                               value="<?php echo esc_attr($opts['bg_color']); ?>"
                               placeholder="#0073aa">
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// --------------------------------------------------------------------
// Frontend: render banner
// --------------------------------------------------------------------
add_action('wp_body_open', static function (): void {
    $opts = (array) get_option(WPBA_OPTION_KEY, []);
    if (empty($opts['enabled']) || empty($opts['message'])) {
        return;
    }
    $bg      = sanitize_hex_color((string) ($opts['bg_color'] ?? '#0073aa')) ?: '#0073aa';
    $message = (string) $opts['message'];
    printf(
        '<div class="wpba-banner" style="background:%s;color:#fff;padding:12px;text-align:center;font-family:sans-serif;">%s</div>',
        esc_attr($bg),
        esc_html($message)
    );
});
