<?php
/**
 * Document files (since 4.5.0): the "Document Files" meta box and the ONE
 * helper that reads what it saved.
 *
 * Used by the document post types of the "Informasi" page (Standar Pelayanan,
 * Maklumat Pelayanan). One post holds MANY files (PDF, Word, images). The
 * editor picks them in the WordPress media modal, drags them into order and
 * can type an optional label per file.
 *
 * Saved as ONE post meta `_wptg_document_files`:
 *   [ ['id' => 12, 'label' => 'Lampiran 1'], ['id' => 15, 'label' => ''], ... ]
 * `id` is an attachment id; an empty label means "use the attachment title".
 *
 * wptg_get_document_files() is the ONLY place that reads this meta. The
 * document card (wp-taxonomy-grid.php) and the popup (media-popup.php) both
 * call it, so a deleted attachment or a forbidden file type is dropped in one
 * place.
 *
 * Which post types get the box comes from the filter
 * `wptg_document_post_types`; add your own slug there to reuse it.
 *
 * Plain functions, no class, same style as class-video-meta-box.php.
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mime types an editor may attach, and the kind the popup uses to preview
 * each one. This is also the security allowlist: any other mime type is
 * dropped when saving AND when reading.
 */
const WPTG_DOCUMENT_MIME_KINDS = [
    'application/pdf'                                                           => 'pdf',
    'application/msword'                                                       => 'office',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'office',
    'image/jpeg'                                                               => 'image',
    'image/png'                                                                => 'image',
    'image/webp'                                                               => 'image',
];

/** Post meta key of the file list. */
const WPTG_DOCUMENT_META_KEY = '_wptg_document_files';

/** Upper limit of files per post, so a tampered request cannot store a huge list. */
const WPTG_DOCUMENT_MAX_FILES = 50;

/**
 * Post types that have the "Document Files" meta box.
 *
 * @return array<int,string>
 */
function wptg_document_post_types(): array
{
    $post_types = apply_filters('wptg_document_post_types', ['service-standards', 'service-pledges']);
    $post_types = is_array($post_types) ? array_filter($post_types, 'is_string') : [];

    return array_values(array_unique($post_types));
}

/**
 * Kind of a file: 'pdf' | 'image' | 'office' | 'other'.
 * The mime type decides; the extension is only the fallback.
 *
 * @param string $mime      Mime type, e.g. 'application/pdf'.
 * @param string $extension File extension without the dot, e.g. 'docx'.
 */
function wptg_document_kind(string $mime, string $extension): string
{
    $mime = strtolower($mime);
    if (isset(WPTG_DOCUMENT_MIME_KINDS[$mime])) {
        return WPTG_DOCUMENT_MIME_KINDS[$mime];
    }

    return match (strtolower($extension)) {
        'pdf'                          => 'pdf',
        'doc', 'docx'                  => 'office',
        'jpg', 'jpeg', 'png', 'webp'   => 'image',
        default                        => 'other',
    };
}

/**
 * Human readable file size ("1.5 MB"), or '' when the size is unknown.
 *
 * @param int $bytes Size in bytes.
 */
function wptg_format_file_size(int $bytes): string
{
    return $bytes > 0 ? (string) size_format($bytes, 1) : '';
}

/**
 * The mime type of an attachment when it is allowed here, otherwise ''.
 * '' also covers "this id is not an attachment (anymore)".
 *
 * @param int $attachment_id Attachment post id.
 */
function wptg_allowed_attachment_mime(int $attachment_id): string
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return '';
    }

    $mime = (string) get_post_mime_type($attachment_id);

    return isset(WPTG_DOCUMENT_MIME_KINDS[$mime]) ? $mime : '';
}

/**
 * The validated file list of a post, in the order the editor chose.
 *
 * Entries whose attachment was deleted or whose type is not allowed are
 * skipped, so the callers never have to check again.
 *
 * @param int $post_id Post id.
 * @return array<int,array{id:int,label:string,url:string,mime:string,kind:string,ext:string,size:string,title:string}>
 */
function wptg_get_document_files(int $post_id): array
{
    $saved = get_post_meta($post_id, WPTG_DOCUMENT_META_KEY, true);
    if (!is_array($saved)) {
        return [];
    }

    $files = [];
    foreach ($saved as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $attachment_id = absint($entry['id'] ?? 0);
        $mime          = wptg_allowed_attachment_mime($attachment_id);
        $url           = $mime !== '' ? wp_get_attachment_url($attachment_id) : false;
        if (!is_string($url) || $url === '') {
            continue;
        }

        $path      = (string) wp_parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // The JS shows these with textContent, so entities (&#8211;) are decoded here.
        $title = html_entity_decode(wp_strip_all_tags(get_the_title($attachment_id)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = $title !== '' ? $title : wp_basename($path);
        $label = isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : '';

        // WordPress 6.0+ stores the size in the attachment metadata; read the disk only when it is missing.
        $metadata = wp_get_attachment_metadata($attachment_id);
        $bytes    = is_array($metadata) ? (int) ($metadata['filesize'] ?? 0) : 0;
        if ($bytes <= 0) {
            $disk_path = get_attached_file($attachment_id);
            $bytes     = is_string($disk_path) ? (int) wp_filesize($disk_path) : 0;
        }

        $files[] = [
            'id'    => $attachment_id,
            'label' => $label !== '' ? $label : $title,
            'url'   => $url,
            'mime'  => $mime,
            'kind'  => wptg_document_kind($mime, $extension),
            'ext'   => $extension,
            'size'  => wptg_format_file_size($bytes),
            'title' => $title,
        ];
    }

    return $files;
}

/**
 * Clean the list that arrives from the browser before it is saved.
 * Every id must be an existing attachment with an allowed mime type.
 *
 * @param mixed $raw Decoded JSON from the hidden field.
 * @return array<int,array{id:int,label:string}>
 */
function wptg_sanitize_document_files(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $clean = [];
    $seen  = [];
    foreach ($raw as $entry) {
        if (!is_array($entry) || !isset($entry['id']) || !is_numeric($entry['id'])) {
            continue;
        }

        $attachment_id = absint($entry['id']);
        if (isset($seen[$attachment_id]) || wptg_allowed_attachment_mime($attachment_id) === '') {
            continue;
        }
        $seen[$attachment_id] = true;

        $label   = isset($entry['label']) && is_string($entry['label']) ? sanitize_text_field($entry['label']) : '';
        $clean[] = ['id' => $attachment_id, 'label' => mb_substr($label, 0, 200)];

        if (count($clean) >= WPTG_DOCUMENT_MAX_FILES) {
            break;
        }
    }

    return $clean;
}

/**
 * Register the meta box on every document post type.
 */
function wptg_register_document_meta_box(): void
{
    add_meta_box(
        'wptg_document_files',
        __('Document Files', 'wp-taxonomy-grid'),
        'wptg_render_document_meta_box',
        wptg_document_post_types(),
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'wptg_register_document_meta_box');

/**
 * One row of the file list. Called for every saved file, and once with null
 * to make the blank row inside the <template> that the JS clones for each
 * newly picked file, so the row markup exists in this one place only.
 *
 * @param array<string,mixed>|null $file An item of wptg_get_document_files(), or null for the blank row.
 */
function wptg_render_document_admin_row(?array $file): string
{
    $custom_label = ($file !== null && $file['label'] !== $file['title']) ? $file['label'] : '';

    ob_start();
    ?>
    <li class="wptg-doc-row" data-id="<?php echo esc_attr((string) ($file['id'] ?? 0)); ?>">
        <span class="wptg-doc-handle dashicons dashicons-menu" aria-hidden="true" title="<?php esc_attr_e('Drag to reorder', 'wp-taxonomy-grid'); ?>"></span>
        <span class="wptg-doc-ext wptg-doc-ext--<?php echo esc_attr($file['kind'] ?? 'other'); ?>"><?php echo esc_html(strtoupper($file['ext'] ?? '')); ?></span>
        <span class="wptg-doc-name">
            <span class="wptg-doc-title"><?php echo esc_html($file['title'] ?? ''); ?></span>
            <span class="wptg-doc-size"><?php echo esc_html($file['size'] ?? ''); ?></span>
        </span>
        <input type="text" class="wptg-doc-label"
               value="<?php echo esc_attr($custom_label); ?>"
               placeholder="<?php echo esc_attr($file['title'] ?? ''); ?>"
               maxlength="200"
               aria-label="<?php esc_attr_e('Label', 'wp-taxonomy-grid'); ?>">
        <button type="button" class="button-link wptg-doc-remove"><?php esc_html_e('Hapus', 'wp-taxonomy-grid'); ?></button>
    </li>
    <?php
    return (string) ob_get_clean();
}

/**
 * Render the meta box.
 *
 * @param \WP_Post $post Current post being edited.
 */
function wptg_render_document_meta_box(\WP_Post $post): void
{
    wp_nonce_field('wptg_document_files_save', 'wptg_document_files_nonce');

    $files = wptg_get_document_files($post->ID);

    // The JS keeps this hidden field in sync with the list and it is what gets saved.
    $state = [];
    foreach ($files as $file) {
        $state[] = ['id' => $file['id'], 'label' => $file['label'] !== $file['title'] ? $file['label'] : ''];
    }
    ?>
    <div class="wptg-doc-box" data-max="<?php echo esc_attr((string) WPTG_DOCUMENT_MAX_FILES); ?>">
        <p class="description">
            <?php esc_html_e('PDF, Word (.doc/.docx) and images (JPG, PNG, WebP). Drag the rows to change the order. "Label" is optional: when empty, the file title is shown to visitors.', 'wp-taxonomy-grid'); ?>
        </p>

        <ul class="wptg-doc-rows">
            <?php foreach ($files as $file): ?>
                <?php echo wptg_render_document_admin_row($file); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?>
            <?php endforeach; ?>
        </ul>

        <p class="wptg-doc-empty"<?php echo $files !== [] ? ' hidden' : ''; ?>><?php esc_html_e('Belum ada file.', 'wp-taxonomy-grid'); ?></p>

        <template class="wptg-doc-row-template"><?php echo wptg_render_document_admin_row(null); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside the helper. ?></template>

        <button type="button" class="button button-primary wptg-doc-add"><?php esc_html_e('Tambah file', 'wp-taxonomy-grid'); ?></button>

        <input type="hidden" name="wptg_document_files" class="wptg-doc-data" value="<?php echo esc_attr((string) wp_json_encode($state)); ?>">
    </div>
    <?php
}

/**
 * Save the file list.
 *
 * @param int $post_id Post ID being saved.
 */
function wptg_save_document_meta_box(int $post_id): void
{
    // Same guards as class-video-meta-box.php.
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    $nonce = isset($_POST['wptg_document_files_nonce']) ? wp_unslash($_POST['wptg_document_files_nonce']) : '';
    if (!is_string($nonce) || !wp_verify_nonce($nonce, 'wptg_document_files_save')) {
        return;
    }

    if (!in_array(get_post_type($post_id), wptg_document_post_types(), true) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw     = isset($_POST['wptg_document_files']) ? wp_unslash($_POST['wptg_document_files']) : '';
    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    // Broken JSON must never wipe a good list: leave the saved value alone.
    if (!is_array($decoded)) {
        return;
    }

    $files = wptg_sanitize_document_files($decoded);
    if ($files === []) {
        delete_post_meta($post_id, WPTG_DOCUMENT_META_KEY);
        return;
    }

    // wp_slash(): update_post_meta() strips slashes, so a label with a backslash would lose it.
    update_post_meta($post_id, WPTG_DOCUMENT_META_KEY, wp_slash($files));
}
add_action('save_post', 'wptg_save_document_meta_box');

/**
 * Load the media modal, the sortable list and the box script, only on the
 * edit screen of a document post type.
 *
 * @param string $hook_suffix Admin page hook, e.g. 'post.php'.
 */
function wptg_enqueue_document_admin_assets(string $hook_suffix): void
{
    if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, wptg_document_post_types(), true)) {
        return;
    }

    $post = get_post();
    wp_enqueue_media($post instanceof \WP_Post ? ['post' => $post->ID] : []);

    wp_enqueue_style('wptg-admin-documents', WPTG_URL . 'assets/css/admin-documents.css', [], WPTG_VERSION);

    wp_enqueue_script(
        'wptg-admin-documents',
        WPTG_URL . 'assets/js/admin-documents.js',
        ['jquery', 'jquery-ui-sortable', 'media-views'],
        WPTG_VERSION,
        true
    );

    wp_localize_script('wptg-admin-documents', 'wptgDocuments', [
        'mimes' => array_keys(WPTG_DOCUMENT_MIME_KINDS),
        'kinds' => WPTG_DOCUMENT_MIME_KINDS,
        'max'   => WPTG_DOCUMENT_MAX_FILES,
        'i18n'  => [
            'modalTitle'  => __('Pilih file dokumen', 'wp-taxonomy-grid'),
            'modalButton' => __('Tambah file', 'wp-taxonomy-grid'),
            'tooMany'     => __('Jumlah file sudah mencapai batas.', 'wp-taxonomy-grid'),
        ],
    ]);
}
add_action('admin_enqueue_scripts', 'wptg_enqueue_document_admin_assets');
