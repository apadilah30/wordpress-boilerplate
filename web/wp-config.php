<?php
/**
 * WordPress config bridge.
 *
 * All real configuration is in /config/application.php (Bedrock).
 * This file exists because WordPress core still looks for it in the webroot.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Load Bedrock configuration.
 */
require_once dirname(__DIR__) . '/config/application.php';

require_once ABSPATH . 'wp-settings.php';
