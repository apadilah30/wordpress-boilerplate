<?php
/**
 * Plugin Name:  Register Theme Directory
 * Plugin URI:   https://roots.io/bedrock/
 * Description:  Registers wp/wp-content/themes as an additional theme location so
 *               WordPress core themes (Twenty-*) are still available as fallback.
 * Version:      1.0.0
 * License:      MIT
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

register_theme_directory(ABSPATH . 'wp-content/themes');
