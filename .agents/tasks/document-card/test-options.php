<?php
/**
 * Tes wptg_normalize_document_options() (kode ASLI plugin, stub WordPress minimal):
 * default, nilai dari JSON browser yang dirusak, dan icon_html yang tidak boleh
 * dipercaya dari browser.
 *
 *   php .agents/tasks/document-card/test-options.php   -> test-options.out
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'http://example.test/'; }
function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void {}
function apply_filters(string $tag, $value, ...$args) { return $value; }
function __(string $text, string $domain = ''): string { return $text; }
function sanitize_text_field(string $text): string { return trim(preg_replace('/\s+/', ' ', strip_tags($text))); }

require dirname(__DIR__, 3) . '/plugins-custom/wp-taxonomy-grid/wp-taxonomy-grid.php';

$lines    = [];
$failures = 0;
$check    = static function (string $label, $actual, $expected) use (&$lines, &$failures): void {
    $ok        = $actual === $expected;
    $failures += $ok ? 0 : 1;
    $lines[]   = ($ok ? 'ok   ' : 'FAIL ') . $label . ($ok ? '' : ' :: got ' . var_export($actual, true) . ' expected ' . var_export($expected, true));
};

// 1. Tanpa data = default desain.
$d = wptg_normalize_document_options([]);
$check('default show_icon', $d['show_icon'], true);
$check('default custom_icon', $d['custom_icon'], false);
$check('default show_date', $d['show_date'], true);
$check('default date_label', $d['date_label'], 'Diunggah pada:');
$check('default show_cover', $d['show_cover'], true);
$check('default show_count (NO)', $d['show_count'], false);
$check('default view_text', $d['view_text'], 'Lihat');
$check('default show_view_icon', $d['show_view_icon'], true);
$check('default show_card_download (YES)', $d['show_card_download'], true);
$check('default download_text', $d['download_text'], 'Unduh');
$check('default show_download_icon', $d['show_download_icon'], true);
$check('default show_download popup (NO)', $d['show_download'], false);

// 2. JSON dari browser (bool asli) dan string "0"/"1".
$d = wptg_normalize_document_options(['show_cover' => false, 'show_count' => '1', 'show_date' => '0', 'show_card_download' => 'false']);
$check('bool false dipatuhi', $d['show_cover'], false);
$check('"1" jadi true', $d['show_count'], true);
$check('"0" jadi false', $d['show_date'], false);
$check('"false" jadi false', $d['show_card_download'], false);

// 3. Teks: tag dibuang, kosong = default, dipotong 60 karakter, bukan string diabaikan.
$d = wptg_normalize_document_options(['date_label' => '<b>Dibuat</b> <script>x</script>', 'view_text' => '   ', 'download_text' => str_repeat('a', 100)]);
$check('tag dibuang dari label', $d['date_label'], 'Dibuat x');
$check('teks kosong = default', $d['view_text'], 'Lihat');
$check('teks dipotong 60', strlen($d['download_text']), 60);
$d = wptg_normalize_document_options(['date_label' => ['x'], 'view_text' => 123]);
$check('label bukan string = default', $d['date_label'], 'Diunggah pada:');
$check('teks angka = default', $d['view_text'], 'Lihat');

// 4. icon_html TIDAK dipercaya dari browser; hanya dari argumen server.
$d = wptg_normalize_document_options(['icon_html' => '<script>alert(1)</script>', 'custom_icon' => true]);
$check('icon_html dari raw diabaikan', $d['icon_html'], '');
$check('custom_icon boleh dari browser (cuma flag)', $d['custom_icon'], true);
$d = wptg_normalize_document_options([], '<i class="fas fa-file"></i>');
$check('icon_html dari server dipakai', $d['icon_html'], '<i class="fas fa-file"></i>');
$check('custom_icon otomatis true bila ada icon_html', $d['custom_icon'], true);

// 5. Yang dikirim ke browser tidak membawa icon_html.
$json = json_encode(array_diff_key($d, ['icon_html' => true]));
$check('JSON ke browser tanpa icon_html', str_contains((string) $json, 'icon_html'), false);

$lines[] = $failures === 0 ? 'ALL PASSED (' . count($lines) . ' checks)' : $failures . ' FAILED';
file_put_contents(__DIR__ . '/test-options.out', implode("\n", $lines) . "\n");
