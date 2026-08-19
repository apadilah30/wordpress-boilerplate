<?php
/**
 * Staging environment.
 * Mirror production, tapi debug log tetap on untuk troubleshoot pre-release.
 */

declare(strict_types=1);

use Roots\WPConfig\Config;

Config::define('WP_ENVIRONMENT_TYPE', 'staging');
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', false);
Config::define('WP_DEBUG_LOG', true);
Config::define('SCRIPT_DEBUG', false);

// Sama seperti prod: no filesystem mods dari WP admin.
Config::define('DISALLOW_FILE_MODS', true);
Config::define('WP_CACHE', true);
Config::define('FORCE_SSL_ADMIN', true);

ini_set('display_errors', '0');
