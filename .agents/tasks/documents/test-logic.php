<?php
/**
 * Runs the REAL plugin functions (not a port) against test-cases.json with a few
 * WordPress functions stubbed. Not part of the plugin.
 *
 *   php .agents/tasks/documents/test-logic.php   -> writes test-logic-php.out
 *
 * Covers: wptg_document_kind(), wptg_is_public_url(), the Word embed URL built
 * by wptg_build_document_payload_files(), and the guard of wptg_format_file_size().
 * Does NOT cover anything that needs a database or WordPress (meta box save,
 * wptg_get_document_files()).
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('WPTG_URL', 'http://example.test/');
define('WPTG_VERSION', 'test');

function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void {}
function apply_filters(string $tag, $value, ...$args) { return $value; }
function wp_parse_url(string $url, int $component = -1) { return $component === -1 ? parse_url($url) : parse_url($url, $component); }
function esc_url_raw(string $url, $protocols = null): string { return $url; }
function size_format($bytes, int $decimals = 0) { return 'SIZE(' . $bytes . ',' . $decimals . ')'; }

$plugin = dirname(__DIR__, 3) . '/plugins-custom/wp-taxonomy-grid/includes/';
require $plugin . 'class-document-meta-box.php';
require $plugin . 'media-popup.php';

$cases    = json_decode((string) file_get_contents(__DIR__ . '/test-cases.json'), true);
$lines    = [];
$failures = 0;

$check = static function (string $label, $actual, $expected) use (&$lines, &$failures): void {
    $ok = $actual === $expected;
    $failures += $ok ? 0 : 1;
    $lines[] = ($ok ? 'ok   ' : 'FAIL ') . $label . ($ok ? '' : ' :: got ' . var_export($actual, true) . ' expected ' . var_export($expected, true));
};

foreach ($cases['kind'] as [$mime, $ext, $expected]) {
    $check('kind(' . $mime . ', ' . $ext . ')', wptg_document_kind($mime, $ext), $expected);
}

foreach ($cases['public_url'] as [$url, $expected]) {
    $check('public(' . $url . ')', wptg_is_public_url($url), $expected);
}

foreach ($cases['embed'] as [$url, $expected]) {
    $files = wptg_build_document_payload_files([[
        'label' => 'x', 'url' => $url, 'kind' => 'office', 'mime' => 'application/msword', 'ext' => 'doc', 'size' => '',
    ]]);
    // The stubbed filter keeps the default, so a public URL must give an embed URL and a private one none.
    $expected_embed = wptg_is_public_url($url) ? $expected : '';
    $check('embed(' . $url . ')', $files[0]['embed_url'], $expected_embed);
    $check('office_preview(' . $url . ')', $files[0]['office_preview'], $expected_embed !== '');
}

$check('size(0) is empty', wptg_format_file_size(0), '');
$check('size(-5) is empty', wptg_format_file_size(-5), '');
$check('size(1536) goes to size_format with 1 decimal', wptg_format_file_size(1536), 'SIZE(1536,1)');

$lines[] = $failures === 0 ? 'ALL PASSED (' . count($lines) . ' checks)' : $failures . ' FAILED';
file_put_contents(__DIR__ . '/test-logic-php.out', implode("\n", $lines) . "\n");
