<?php
/**
 * Plugin Name:       WP Boilerplate Announcement
 * Plugin URI:        https://example.com/plugins/wp-boilerplate-announcement
 * Description:       Reference plugin bawaan boilerplate. Contoh integrasi WordPress: CPT + meta box + dynamic shortcode + i18n. Aman untuk di-copy sebagai template plugin custom lain.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            Apadilah
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-boilerplate-announcement
 * Domain Path:       /languages
 *
 * @package WpBoilerplateAnnouncement
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const WPBA_VERSION    = '2.0.0';
const WPBA_POST_TYPE  = 'wpba_announcement';
const WPBA_META_COLOR = '_wpba_bg_color';
const WPBA_META_LINK  = '_wpba_cta_url';
const WPBA_OPTION_KEY = 'wpba_settings';
const WPBA_TEXT_DOMAIN = 'wp-boilerplate-announcement';

define( 'WPBA_PLUGIN_FILE', __FILE__ );
define( 'WPBA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WPBA_PLUGIN_DIR . 'includes/cpt.php';
require_once WPBA_PLUGIN_DIR . 'includes/shortcode.php';
require_once WPBA_PLUGIN_DIR . 'includes/settings.php';

/**
 * Load translation files from /languages folder.
 * WordPress core auto-loads from wp-content/languages/plugins/ (community
 * translations) — plugin-local /languages/ is a fallback for bundled .mo files.
 */
add_action(
	'init',
	static function (): void {
		load_plugin_textdomain(
			WPBA_TEXT_DOMAIN,
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}
);

register_activation_hook(
	__FILE__,
	static function (): void {
		wpba_register_post_type();
		flush_rewrite_rules();

		if ( false === get_option( WPBA_OPTION_KEY ) ) {
			add_option(
				WPBA_OPTION_KEY,
				array(
					'legacy_enabled' => false,
					'legacy_message' => __( 'Welcome to our site!', 'wp-boilerplate-announcement' ),
					'legacy_color'   => '#0073aa',
				)
			);
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		flush_rewrite_rules();
	}
);
