<?php
/**
 * Menjalankan renderer ASLI plugin (wptg_render_item_document_list() dkk) dengan
 * sedikit fungsi WordPress yang di-stub, lalu menulis pratinjau statis:
 *
 *   php .agents/tasks/document-card/render-preview.php
 *     -> preview.html        (kontainer 528px + iframe 360px dan 320px)
 *     -> preview-frame.html  (isi iframe; boleh dibuka langsung)
 *     -> markup-sample.html  (markup mentah satu kartu, buat dibaca)
 *
 * Bukan bagian plugin. Yang BEDA dari WordPress asli hanya: gambar cover
 * (src diganti gradien putih->abu; WordPress asli juga menambah srcset/sizes),
 * dan tanggal/judul/file yang berasal dari data contoh di bawah.
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

$plugin_dir = dirname(__DIR__, 3) . '/plugins-custom/wp-taxonomy-grid/';

// ---------------------------------------------------------------------------
// Data contoh. Kunci = id post / id attachment.
// ---------------------------------------------------------------------------
$GLOBALS['demo'] = [
    'posts' => [
        101 => ['title' => 'Keputusan Menteri Hukum dan Hak Asasi Manusia tentang Standar Pelayanan Keimigrasian Tahun 2026', 'date' => '2026-10-01', 'thumb' => true, 'files' => [11, 12, 13]],
        102 => ['title' => 'Standar Pelayanan Penerbitan Paspor Republik Indonesia', 'date' => '2026-08-09', 'thumb' => false, 'files' => [21]],
        103 => ['title' => 'Maklumat Pelayanan Kantor Imigrasi', 'date' => '2026-10-15', 'thumb' => false, 'files' => [31]],
        104 => ['title' => 'Standar Pelayanan Tanpa Lampiran', 'date' => '2026-09-20', 'thumb' => true, 'files' => []],
    ],
    'attachments' => [
        11 => ['title' => 'Salinan SK', 'mime' => 'application/pdf', 'url' => 'http://example.test/uploads/sk.pdf', 'sizes' => false],
        12 => ['title' => 'Lampiran gambar', 'mime' => 'image/jpeg', 'url' => 'http://example.test/uploads/lampiran.jpg', 'sizes' => false],
        13 => ['title' => 'Lampiran Word', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'url' => 'http://example.test/uploads/lampiran.docx', 'sizes' => false],
        21 => ['title' => 'Paspor', 'mime' => 'application/pdf', 'url' => 'http://example.test/uploads/paspor.pdf', 'sizes' => true],
        31 => ['title' => 'Maklumat', 'mime' => 'application/pdf', 'url' => 'http://example.test/uploads/maklumat.pdf', 'sizes' => false],
    ],
];

$months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// ---------------------------------------------------------------------------
// Stub WordPress. Hanya yang dipanggil oleh renderer kartu dokumen.
// ---------------------------------------------------------------------------
class WP_Post
{
    public int $ID;
    public string $post_type = 'service-standards';
    public function __construct(int $id)
    {
        $this->ID = $id;
    }
}

function plugin_dir_path(string $file): string { return dirname($file) . '/'; }
function plugin_dir_url(string $file): string { return 'http://example.test/wp-content/plugins/wp-taxonomy-grid/'; }
function add_action(string $hook, $callback, int $priority = 10, int $args = 1): void {}
function apply_filters(string $tag, $value, ...$args) { return $value; }
function __(string $text, string $domain = ''): string { return $text; }
function esc_html__(string $text, string $domain = ''): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8', false); }
function _n(string $single, string $plural, int $number, string $domain = ''): string { return $number === 1 ? $single : $plural; }
function esc_attr($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false); }
function esc_html($text): string { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false); }
function esc_url(string $url): string { return htmlspecialchars($url, ENT_QUOTES, 'UTF-8', false); }
function sanitize_text_field(string $text): string { return trim(strip_tags($text)); }
function wp_json_encode($data): string|false { return json_encode($data); }
function absint($value): int { return abs((int) $value); }
function wp_parse_url(string $url, int $component = -1) { return $component === -1 ? parse_url($url) : parse_url($url, $component); }
function wp_strip_all_tags(string $text): string { return strip_tags($text); }
function wp_basename(string $path): string { return basename($path); }
function size_format($bytes, int $decimals = 0): string { return $bytes . ' B'; }
function wp_filesize(string $path): int { return 0; }
function get_attached_file(int $id): string { return ''; }
function wp_get_attachment_metadata(int $id) { return []; }

function get_post_meta(int $id, string $key, bool $single = false)
{
    $files = $GLOBALS['demo']['posts'][$id]['files'] ?? [];
    return array_map(static fn (int $file_id): array => ['id' => $file_id, 'label' => ''], $files);
}
function get_post_type(int $id): string { return isset($GLOBALS['demo']['attachments'][$id]) ? 'attachment' : 'service-standards'; }
function get_post_mime_type(int $id): string { return $GLOBALS['demo']['attachments'][$id]['mime'] ?? ''; }
function wp_get_attachment_url(int $id) { return $GLOBALS['demo']['attachments'][$id]['url'] ?? false; }
function get_the_title(int $id): string
{
    return $GLOBALS['demo']['posts'][$id]['title'] ?? ($GLOBALS['demo']['attachments'][$id]['title'] ?? '');
}
function get_the_date(string $format, $post): string
{
    global $months;
    $time = strtotime($GLOBALS['demo']['posts'][$post->ID]['date']);
    // Hanya format 'j F Y' yang dipakai renderer; bulan diterjemahkan seperti date_i18n() pada locale id_ID.
    return (string) ((int) date('j', $time)) . ' ' . $months[(int) date('n', $time)] . ' ' . date('Y', $time);
}
function has_post_thumbnail(int $id): bool { return (bool) ($GLOBALS['demo']['posts'][$id]['thumb'] ?? false); }

/** Gambar pengganti: gradien putih -> abu, 600x800 (seperti halaman dokumen). */
function demo_image(array $attrs): string
{
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='800' viewBox='0 0 600 800'>"
        . "<defs><linearGradient id='g' x1='0' y1='0' x2='0' y2='1'><stop offset='0' stop-color='#ffffff'/><stop offset='1' stop-color='#b8b8b8'/></linearGradient></defs>"
        . "<rect width='600' height='800' fill='url(#g)'/></svg>";
    $out = '<img width="600" height="800" src="data:image/svg+xml,' . rawurlencode($svg) . '"';
    foreach ($attrs as $name => $value) {
        $out .= ' ' . $name . '="' . esc_attr($value) . '"';
    }
    return $out . '>';
}
function get_the_post_thumbnail(int $id, string $size, array $attrs = []): string { return has_post_thumbnail($id) ? demo_image($attrs) : ''; }
function wp_get_attachment_image(int $id, string $size, bool $icon = false, array $attrs = []): string { return demo_image($attrs); }
function wp_get_attachment_image_src(int $id, string $size)
{
    // Seperti WordPress asli: PDF TANPA preview mengembalikan URL file .pdf itu sendiri.
    $attachment = $GLOBALS['demo']['attachments'][$id] ?? null;
    if ($attachment === null) {
        return false;
    }
    $url = $attachment['sizes'] ? 'http://example.test/uploads/paspor-pdf-large.jpg' : $attachment['url'];
    return [$url, 600, 800, true];
}

// ---------------------------------------------------------------------------
// Muat plugin ASLI (file utama memuat includes/*).
// ---------------------------------------------------------------------------
require $plugin_dir . 'wp-taxonomy-grid.php';

$options = wptg_normalize_document_options([]);

$cards = '';
foreach (array_keys($GLOBALS['demo']['posts']) as $post_id) {
    $cards .= wptg_render_item_document_list(new WP_Post($post_id), 'document_preview', $options);
}
$skeleton_cards = wptg_render_skeleton('document', 2);

$css_href = '../../../plugins-custom/wp-taxonomy-grid/assets/css/taxonomy-grid.css';

/** Satu panel konten persis seperti struktur halaman: judul, search, daftar kartu. */
$panel = static function (string $list_html): string {
    return '<div class="wptg-wrapper wptg-mode-sections"><main class="wptg-sections-content">'
        . '<h2 class="wptg-content-title">Standar Pelayanan</h2>'
        . wptg_render_search_box('Cari...', '&#128269;', 'wptg-search--content')
        . '<div class="wptg-sections-panel"><div class="wptg-section-items-wrap" data-section-index="0" data-term-id="0" data-page="1">'
        . '<div class="wptg-doc-list">' . $list_html . '</div></div></div>'
        . '</main></div>';
};

$head = static fn (string $title, string $extra_css): string => '<!doctype html><html lang="id"><head><meta charset="utf-8">'
    . '<meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>' . $title . '</title>'
    . '<link rel="stylesheet" href="' . $css_href . '">'
    . '<style>' . $extra_css . '</style></head><body>';

$page_css = 'body{margin:24px;font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;color:#222;background:#fff}'
    . '.note{max-width:720px;font-size:13px;line-height:1.5;background:#fff8e6;border:1px solid #edb75b;border-radius:6px;padding:10px 14px;margin:0 0 24px}'
    . 'h1{font-size:18px;margin:0 0 12px}h2.cap{font-size:14px;margin:28px 0 8px;color:#555}'
    . '.stage{width:528px}'
    . 'iframe{display:block;border:1px dashed #bbb;height:1250px;max-width:100%}';

$html = $head('Pratinjau kartu dokumen 4.6.0', $page_css)
    . '<h1>Pratinjau kartu dokumen (wp-taxonomy-grid 4.6.0)</h1>'
    . '<p class="note">Markup kartu = output asli <code>wptg_render_item_document_list()</code> (dijalankan lewat PHP CLI dengan stub WordPress, lihat <code>render-preview.php</code>). '
    . 'Beda dari WordPress asli: <b>gambar cover diganti gradien putih&rarr;abu</b> (WordPress asli juga menambah srcset/sizes). '
    . 'CSS dimuat dari plugin (path relatif). Kartu 1: 3 file + cover dari Featured Image (Unduh membuka popup). Kartu 2: 1 file PDF, cover dari preview PDF (Unduh langsung mengunduh). '
    . 'Kartu 3: 1 file tanpa cover, tombol jadi baris kanan. Kartu 4: tanpa file, tanpa tombol. '
    . 'Ukuran desain ada di <code>UKURAN.md</code>. Ini <b>estimasi dari screenshot</b>, bukan hasil ukur otomatis: bandingkan dengan desain di browser.</p>'
    . '<h2 class="cap">Kontainer 528px (lebar kartu desain)</h2><div class="stage">' . $panel($cards) . '</div>'
    . '<h2 class="cap">Skeleton (varian document)</h2><div class="stage">' . $panel($skeleton_cards) . '</div>'
    . '<h2 class="cap">Viewport 360px (iframe, aturan tablet/mobile ikut aktif)</h2><iframe src="preview-frame.html" width="360" title="360px"></iframe>'
    . '<h2 class="cap">Viewport 320px (&lt;360px: ikon di atas teks)</h2><iframe src="preview-frame.html" width="320" title="320px"></iframe>'
    . '</body></html>';

$frame = $head('Pratinjau kartu dokumen (frame)', 'body{margin:0;padding:12px 0 12px;font-family:-apple-system,"Segoe UI",Roboto,Arial,sans-serif;background:#fff}')
    . $panel($cards) . '</body></html>';

// Markup mentah satu kartu (kartu 1) supaya gampang dibaca dan dibandingkan.
$sample = "<!-- Output asli wptg_render_item_document_list(), kartu 1 (3 file + cover). -->\n" . trim(preg_replace('/\n\s*\n+/', "\n", wptg_render_item_document_list(new WP_Post(101), 'document_preview', $options))) . "\n";

$dir = __DIR__ . '/';
file_put_contents($dir . 'preview.html', $html);
file_put_contents($dir . 'preview-frame.html', $frame);
file_put_contents($dir . 'markup-sample.html', $sample);
file_put_contents($dir . 'render-preview.out', "OK\n" . strlen($html) . " bytes preview.html\n");
