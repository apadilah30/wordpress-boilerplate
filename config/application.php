<?php
/**
 * Bedrock main WordPress configuration.
 *
 * Env-specific overrides live in config/environments/{WP_ENV}.php.
 * All secrets & host-specific values come from environment variables.
 */

declare(strict_types=1);

use Roots\WPConfig\Config;

use function Env\env;

/**
 * Directory containing all site files.
 */
$root_dir = dirname(__DIR__);

/**
 * Document root (webroot).
 */
$webroot_dir = $root_dir . '/web';

/**
 * Load .env from repo root when present. In containers we usually pass env
 * vars via docker-compose, but this covers local runs without Docker too.
 */
if (file_exists($root_dir . '/.env')) {
    $dotenv = Dotenv\Dotenv::createUnsafeImmutable($root_dir);
    $dotenv->load();

    $dotenv->required([
        'WP_HOME',
        'WP_SITEURL',
        'DB_NAME',
        'DB_USER',
        'DB_PASSWORD',
    ])->notEmpty();
}

/**
 * Environment (development | staging | production).
 */
Config::define('WP_ENV', env('WP_ENV') ?: 'production');

/**
 * URLs.
 */
Config::define('WP_HOME', env('WP_HOME'));
Config::define('WP_SITEURL', env('WP_SITEURL'));

/**
 * Custom content directory (Bedrock's `app` instead of `wp-content`).
 */
Config::define('CONTENT_DIR', '/app');
Config::define('WP_CONTENT_DIR', $webroot_dir . Config::get('CONTENT_DIR'));
Config::define('WP_CONTENT_URL', Config::get('WP_HOME') . Config::get('CONTENT_DIR'));

/**
 * Database.
 */
Config::define('DB_NAME', env('DB_NAME'));
Config::define('DB_USER', env('DB_USER'));
Config::define('DB_PASSWORD', env('DB_PASSWORD'));
Config::define('DB_HOST', env('DB_HOST') ?: 'localhost');
Config::define('DB_CHARSET', 'utf8mb4');
Config::define('DB_COLLATE', '');
$table_prefix = env('DB_PREFIX') ?: 'wp_';

/**
 * Authentication unique keys and salts.
 * Regenerate: https://roots.io/salts.html
 */
Config::define('AUTH_KEY',         env('AUTH_KEY'));
Config::define('SECURE_AUTH_KEY',  env('SECURE_AUTH_KEY'));
Config::define('LOGGED_IN_KEY',    env('LOGGED_IN_KEY'));
Config::define('NONCE_KEY',        env('NONCE_KEY'));
Config::define('AUTH_SALT',        env('AUTH_SALT'));
Config::define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT'));
Config::define('LOGGED_IN_SALT',   env('LOGGED_IN_SALT'));
Config::define('NONCE_SALT',       env('NONCE_SALT'));

/**
 * Baseline hardening — always on regardless of env.
 * File editor is disabled everywhere; per-env constants (WP_DEBUG, DISALLOW_FILE_MODS, etc.)
 * live in config/environments/*.php.
 */
Config::define('DISALLOW_FILE_EDIT', true);
Config::define('AUTOMATIC_UPDATER_DISABLED', true);
Config::define('WP_AUTO_UPDATE_CORE', false);
Config::define('WP_POST_REVISIONS', 20);
Config::define('EMPTY_TRASH_DAYS', 30);
Config::define('WPLANG', '');

/**
 * Redis object cache (via 'redis' plugin, added when needed).
 */
Config::define('WP_REDIS_HOST', env('REDIS_HOST') ?: 'redis');
Config::define('WP_REDIS_PORT', (int) (env('REDIS_PORT') ?: 6379));
Config::define('WP_CACHE_KEY_SALT', env('WP_HOME'));

/**
 * Load env-specific config overrides.
 */
$env_config = __DIR__ . '/environments/' . Config::get('WP_ENV') . '.php';
if (file_exists($env_config)) {
    require_once $env_config;
}

Config::apply();

/**
 * Bootstrap WordPress.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $webroot_dir . '/wp/');
}
