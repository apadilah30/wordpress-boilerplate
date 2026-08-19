<?php
/**
 * Development environment.
 * Prioritas: developer experience. Debug on, cache off.
 */

declare(strict_types=1);

use Roots\WPConfig\Config;

Config::define('WP_ENVIRONMENT_TYPE', 'development');
Config::define('SAVEQUERIES', true);
Config::define('WP_DEBUG', true);
Config::define('WP_DEBUG_DISPLAY', true);
Config::define('WP_DEBUG_LOG', true);
Config::define('SCRIPT_DEBUG', true);
Config::define('WP_DISABLE_FATAL_ERROR_HANDLER', true);

// Cache & filesystem modifications allowed in dev so bisa experiment.
Config::define('DISALLOW_FILE_MODS', false);
Config::define('WP_CACHE', false);

ini_set('display_errors', '1');
