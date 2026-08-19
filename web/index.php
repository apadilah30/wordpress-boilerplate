<?php
/**
 * WordPress front controller.
 *
 * Bedrock convention: keep WP core in /web/wp and load its front controller.
 */

declare(strict_types=1);

define('WP_USE_THEMES', true);

require __DIR__ . '/wp/wp-blog-header.php';
