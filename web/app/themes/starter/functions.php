<?php
/**
 * Boilerplate Starter theme bootstrap.
 *
 * Sengaja minim: mayoritas layout diserahkan ke Elementor.
 * Tambahkan feature theme di sini seperlunya (menus, image sizes, dll.).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('automatic-feed-links');

    register_nav_menus([
        'primary' => __('Primary Menu', 'boilerplate-starter'),
        'footer'  => __('Footer Menu', 'boilerplate-starter'),
    ]);
});

/**
 * Enable Elementor for all public CPT with show_in_rest.
 *
 * Automatically detects CPT from CPT UI and enables Elementor support.
 * No need to manually add each CPT slug.
 */
add_filter('elementor/utils/get_capable_post_types', static function (array $post_types): array {
    $public_types = get_post_types([
        'public'       => true,
        'show_in_rest' => true,
    ], 'names');

    foreach ($public_types as $type) {
        if (!in_array($type, $post_types, true)) {
            $post_types[] = $type;
        }
    }

    return $post_types;
});
