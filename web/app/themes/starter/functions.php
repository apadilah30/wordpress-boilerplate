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
