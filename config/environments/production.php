<?php
/**
 * Production environment.
 * Hardened: no debug output, no filesystem mods, force SSL.
 */

declare(strict_types=1);

use Roots\WPConfig\Config;

Config::define('WP_ENVIRONMENT_TYPE', 'production');
Config::define('WP_DEBUG', false);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', false);
Config::define('SCRIPT_DEBUG', false);

// Lock down: plugin/theme install & auto-update dari WP admin diblok.
// Semua perubahan harus lewat composer + git.
Config::define('DISALLOW_FILE_MODS', true);
Config::define('WP_CACHE', true);
Config::define('FORCE_SSL_ADMIN', true);

// Concatenate scripts di admin off (kompatibilitas + security).
Config::define('CONCATENATE_SCRIPTS', false);

ini_set('display_errors', '0');
