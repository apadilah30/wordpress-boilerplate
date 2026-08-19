<?php
/**
 * Plugin Name:  Boilerplate Security Hardening
 * Description:  Runtime hardening WordPress (XML-RPC off, user enumeration blok,
 *               header cleanup, dsb). Berjalan di semua env.
 * Version:      1.0.0
 * License:      MIT
 *
 * KENAPA mu-plugin: mu-plugin auto-load sebelum plugin biasa & tidak bisa di-
 * deactivate dari WP admin — cocok untuk hardening yang wajib selalu aktif.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

// ----------------------------------------------------------------------------
// 1. Kill XML-RPC (attack vector klasik: pingback amplification & brute force)
// ----------------------------------------------------------------------------
add_filter('xmlrpc_enabled', '__return_false');
add_filter('wp_headers', static function (array $headers): array {
    unset($headers['X-Pingback']);
    return $headers;
});
add_filter('pings_open', '__return_false');

// ----------------------------------------------------------------------------
// 2. Hilangkan generator meta (versi WP tidak leak di HTML/RSS)
// ----------------------------------------------------------------------------
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

// ----------------------------------------------------------------------------
// 3. Hapus header WLW / RSD (jarang dipakai, cuma bikin surface)
// ----------------------------------------------------------------------------
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

// ----------------------------------------------------------------------------
// 4. Block user enumeration via /?author=1 dan /wp-json/wp/v2/users
// ----------------------------------------------------------------------------
add_action('template_redirect', static function (): void {
    if (isset($_GET['author']) && !is_admin()) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 1);

add_filter('rest_endpoints', static function (array $endpoints): array {
    if (!is_user_logged_in()) {
        unset(
            $endpoints['/wp/v2/users'],
            $endpoints['/wp/v2/users/(?P<id>[\d]+)']
        );
    }
    return $endpoints;
});

// ----------------------------------------------------------------------------
// 5. Sembunyikan error login yang informatif ("wrong password" vs "no user")
// ----------------------------------------------------------------------------
add_filter('login_errors', static function (): string {
    return __('Login tidak valid.', 'boilerplate-security');
});

// ----------------------------------------------------------------------------
// 6. Disable pingback & trackback
// ----------------------------------------------------------------------------
add_filter('xmlrpc_methods', static function (array $methods): array {
    unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
    return $methods;
});

// ----------------------------------------------------------------------------
// 7. Force strong app passwords: minimal length 20 (WP built-in generator sudah)
//    dan disable auto-generated username 'admin'.
// ----------------------------------------------------------------------------
add_filter('illegal_user_logins', static function (array $names): array {
    return array_unique(array_merge($names, ['admin', 'administrator', 'root', 'test']));
});

// ----------------------------------------------------------------------------
// 8. Trusted proxy handling — kalau nanti ada CDN/LB di depan Caddy, respect
//    X-Forwarded-Proto supaya is_ssl() akurat.
// ----------------------------------------------------------------------------
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) {
    $_SERVER['HTTPS'] = 'on';
}

// ----------------------------------------------------------------------------
// 9. Nonaktifkan file upload PHP via media library (double-check di server layer)
// ----------------------------------------------------------------------------
add_filter('upload_mimes', static function (array $mimes): array {
    unset($mimes['php'], $mimes['phtml'], $mimes['php3'], $mimes['php4'], $mimes['php5'], $mimes['php7'], $mimes['phar']);
    return $mimes;
});

// ----------------------------------------------------------------------------
// 10. Force SSL admin di non-dev
// ----------------------------------------------------------------------------
if (defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE !== 'development') {
    if (!defined('FORCE_SSL_ADMIN')) {
        define('FORCE_SSL_ADMIN', true);
    }
}
