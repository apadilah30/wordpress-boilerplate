<?php
/**
 * Media Popup (since 4.3.0): the ONE popup used for videos and photos.
 *
 * Both this plugin (Item Click Behavior = "Media Popup") and the Content
 * Grid plugin (Link Mode = "Media Popup") put `data-wptg-media="<post_id>"`
 * on a card. assets/js/media-popup.js catches the click and asks the
 * `wptg_get_media_modal` AJAX action below for what to show.
 *
 * The popup kind is decided from the post's DATA, never from its post type:
 *   1. "video" when the post has a video source, first match wins:
 *        a. post meta `_video_external_url` (set on purpose by an editor),
 *        b. the first video/embed inside the post content,
 *        c. a video file attached to the post.
 *   2. "document" (since 4.5.0) when there is no video but the post has files
 *      in its "Document Files" meta (PDF / Word / images, see
 *      class-document-meta-box.php). The server sends only the file list; the
 *      JS (assets/js/document-viewer.js) draws the preview.
 *   3. "photo" when there is no video and no files but there is an image
 *      (featured image, else the first image in the content).
 *   4. "post" otherwise: the title + content popup that already existed.
 *
 * Plain functions only (no class), same style as class-video-meta-box.php.
 * No outgoing HTTP request is ever made (no oEmbed): YouTube/Vimeo ids are
 * read from the URL text, because a dev container may have no internet.
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/** File extensions the browser's own <video> tag can play. */
const WPTG_VIDEO_FILE_EXTENSIONS = ['mp4', 'webm', 'ogv', 'm4v', 'mov'];

/** Host suffixes that never exist on the public internet (see wptg_is_public_url()). */
const WPTG_NON_PUBLIC_HOST_SUFFIXES = ['local', 'localhost', 'test', 'example', 'invalid', 'internal', 'lan'];

/** Microsoft's viewer for Word files; the file URL is appended, URL-encoded. */
const WPTG_OFFICE_EMBED_URL = 'https://view.officeapps.live.com/op/embed.aspx?src=';

/**
 * Small constructor so every source has the same keys.
 *
 * @param string $type 'youtube'|'vimeo'|'file'|'link'.
 * @param string $id   YouTube / Vimeo id.
 * @param string $hash Vimeo privacy hash (unlisted videos), or ''.
 * @param string $url  URL for 'file' and 'link'.
 * @return array{type:string,id:string,hash:string,url:string}
 */
function wptg_make_video_source(string $type, string $id = '', string $hash = '', string $url = ''): array
{
    return ['type' => $type, 'id' => $id, 'hash' => $hash, 'url' => $url];
}

/**
 * Work out what a video URL is. Only the URL text is looked at.
 *
 *  - 'youtube' / 'vimeo': host is on the allowlist (exact match, so
 *    "youtube.com.evil.com" does not pass) and an id was found.
 *  - 'file': direct link to a video file (.mp4 .webm .ogv .m4v .mov).
 *  - 'link': any other http(s) URL (TikTok, Instagram, a YouTube channel...).
 *    It cannot be embedded, the popup shows an "open video" button for it.
 *  - null: not a usable http(s) URL at all.
 *
 * @param string $url URL taken from post meta, a tag attribute or the content.
 * @return array{type:string,id:string,hash:string,url:string}|null
 */
function wptg_parse_video_url(string $url): ?array
{
    $url = trim($url);

    // Older editor content has "//host/x" or "/wp-content/x"; make it absolute first.
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    } elseif (str_starts_with($url, '/')) {
        $url = home_url($url);
    }

    if (!preg_match('~^https?://~i', $url)) {
        return null;
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = strtolower((string) $parts['host']);
    $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    $path = (string) ($parts['path'] ?? '');

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);

    if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
        $video_id = '';
        if ($host === 'youtu.be') {
            $video_id = preg_match('~^/([^/]+)~', $path, $matches) ? $matches[1] : '';
        } elseif (isset($query['v']) && is_string($query['v'])) {
            $video_id = $query['v'];
        } elseif (preg_match('~^/(?:embed|shorts|live|v)/([^/]+)~', $path, $matches)) {
            $video_id = $matches[1];
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $video_id)) {
            return wptg_make_video_source('youtube', $video_id);
        }
    }

    if (
        in_array($host, ['vimeo.com', 'player.vimeo.com'], true)
        && preg_match('~^/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d+)(?:/([A-Za-z0-9]+))?/?$~', $path, $matches)
    ) {
        // An unlisted Vimeo video needs its hash: "vimeo.com/123/abc123def4" or "?h=abc123def4".
        $hash = isset($query['h']) && is_string($query['h']) ? $query['h'] : ($matches[2] ?? '');
        $hash = preg_match('/^[A-Za-z0-9]{6,20}$/', $hash) ? $hash : '';

        return wptg_make_video_source('vimeo', $matches[1], $hash);
    }

    $clean_url = esc_url_raw($url, ['http', 'https']);
    if ($clean_url === '') {
        return null;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    return in_array($extension, WPTG_VIDEO_FILE_EXTENSIONS, true)
        ? wptg_make_video_source('file', '', '', $clean_url)
        : wptg_make_video_source('link', '', '', $clean_url);
}

/**
 * Turn ONE token found in the post content into a video source, or null.
 *
 * A token is a <video>/<source>/<iframe> tag, a [video] shortcode or a bare
 * URL (see wptg_find_video_in_content()). Rules:
 *  - <iframe>: only YouTube / Vimeo. Any other host is dropped.
 *  - <video>: an explicit video tag, so its src counts even without a known extension.
 *  - <source>: needs a video extension or type="video/...".
 *  - bare URL: only YouTube / Vimeo / direct video files.
 *
 * @param string $token Raw text, e.g. '<iframe src="...">'.
 * @return array{type:string,id:string,hash:string,url:string}|null
 */
function wptg_video_source_from_token(string $token): ?array
{
    if ($token[0] === '<') {
        $tag = new WP_HTML_Tag_Processor($token);
        if (!$tag->next_tag()) {
            return null;
        }

        $src = $tag->get_attribute('src');
        if (!is_string($src) || $src === '') {
            return null;
        }

        $source = wptg_parse_video_url($src);
        if ($source === null) {
            return null;
        }

        $is_embed = in_array($source['type'], ['youtube', 'vimeo'], true);
        $tag_name = $tag->get_tag();

        if ($tag_name === 'IFRAME') {
            return $is_embed ? $source : null;
        }

        if ($tag_name === 'VIDEO') {
            return ($is_embed || $source['type'] === 'file')
                ? $source
                : wptg_make_video_source('file', '', '', $source['url']);
        }

        // <source>
        $type_attr = $tag->get_attribute('type');
        $says_video = is_string($type_attr) && str_starts_with(strtolower($type_attr), 'video/');

        if ($source['type'] === 'file') {
            return $source;
        }

        return ($says_video && $source['type'] === 'link')
            ? wptg_make_video_source('file', '', '', $source['url'])
            : null;
    }

    if ($token[0] === '[') {
        $atts = shortcode_parse_atts(trim($token, '[]'));
        if (!is_array($atts)) {
            return null;
        }

        foreach (['src', 'mp4', 'm4v', 'webm', 'ogv', 'mov'] as $key) {
            if (empty($atts[$key]) || !is_string($atts[$key])) {
                continue;
            }
            $source = wptg_parse_video_url($atts[$key]);
            if ($source !== null) {
                // [video] is explicit, so a link without a known extension still counts as a file.
                return $source['type'] === 'link'
                    ? wptg_make_video_source('file', '', '', $source['url'])
                    : $source;
            }
        }

        return null;
    }

    // Bare URL. Block comments store "&" as \u0026, text stores it as &amp;.
    $url    = str_replace('\u0026', '&', rtrim($token, '.,;:!?)'));
    $url    = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $source = wptg_parse_video_url($url);

    return ($source !== null && $source['type'] !== 'link') ? $source : null;
}

/**
 * First video inside the post content, in document order.
 *
 * Works on the RAW post_content on purpose: the rendered content would call
 * oEmbed (an outgoing HTTP request) for Embed blocks. Raw content already
 * holds what we need: <video> tags, the plain URL inside an Embed block,
 * [video] / [embed] shortcodes and <iframe> tags.
 *
 * Posts built only with Elementor keep their content in post meta, not in
 * post_content, so nothing is found there. Use External Video URL for those.
 *
 * @param string $content Raw post content.
 * @return array{type:string,id:string,hash:string,url:string}|null
 */
function wptg_find_video_in_content(string $content): ?array
{
    if ($content === '') {
        return null;
    }

    $pattern = '~<video\b[^>]*>|<source\b[^>]*>|<iframe\b[^>]*>|\[video\b[^\]]*\]|https?://[^\s"\'<>\[\]]+~i';
    if (!preg_match_all($pattern, $content, $matches)) {
        return null;
    }

    foreach ($matches[0] as $token) {
        $source = wptg_video_source_from_token($token);
        if ($source !== null) {
            return $source;
        }
    }

    return null;
}

/**
 * Find the video of a post. Priority (first hit wins):
 *   1. post meta `_video_external_url` (explicit choice of the editor),
 *   2. first video/embed inside the content,
 *   3. a video file attached to the post.
 *
 * @return array{type:string,id:string,hash:string,url:string}|null null = the post has no video.
 */
function wptg_resolve_video_source(\WP_Post $post): ?array
{
    $external_url = get_post_meta($post->ID, '_video_external_url', true);
    if (is_string($external_url) && trim($external_url) !== '') {
        $source = wptg_parse_video_url($external_url);
        if ($source !== null) {
            return $source;
        }
    }

    $source = wptg_find_video_in_content((string) $post->post_content);
    if ($source !== null) {
        return $source;
    }

    $attachments = get_attached_media('video', $post->ID);
    $attachment  = reset($attachments);
    if ($attachment instanceof \WP_Post) {
        $attachment_url = wp_get_attachment_url($attachment->ID);
        if (is_string($attachment_url) && $attachment_url !== '') {
            return wptg_make_video_source('file', '', '', $attachment_url);
        }
    }

    return null;
}

/**
 * <iframe> for a YouTube / Vimeo player. $src is built by this plugin from
 * a validated id, never copied from the post.
 */
function wptg_render_embed_iframe(string $src, string $title): string
{
    return sprintf(
        '<iframe src="%s" title="%s" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" frameborder="0"></iframe>',
        esc_url($src),
        esc_attr($title)
    );
}

/**
 * Player markup for one video source. A direct file gets NO poster
 * attribute: the featured image must never show up in a video popup.
 *
 * @param array{type:string,id:string,hash:string,url:string} $source Output of wptg_parse_video_url().
 * @param string                                              $title  Post title (iframe title for screen readers).
 */
function wptg_render_video_source_html(array $source, string $title): string
{
    if ($source['type'] === 'youtube') {
        return wptg_render_embed_iframe(
            'https://www.youtube-nocookie.com/embed/' . $source['id'] . '?autoplay=1&rel=0&playsinline=1',
            $title
        );
    }

    if ($source['type'] === 'vimeo') {
        $hash_param = $source['hash'] !== '' ? '&h=' . $source['hash'] : '';
        return wptg_render_embed_iframe('https://player.vimeo.com/video/' . $source['id'] . '?autoplay=1' . $hash_param, $title);
    }

    if ($source['type'] === 'file') {
        return sprintf('<video controls playsinline preload="metadata" src="%s"></video>', esc_url($source['url']));
    }

    // 'link': honest fallback, the site cannot embed this. No automatic redirect.
    return sprintf(
        '<div class="wptg-media-fallback"><p>%s</p><a class="wptg-media-fallback-link" href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>',
        esc_html__('Video ini tidak bisa diputar di sini.', 'wp-taxonomy-grid'),
        esc_url($source['url']),
        esc_html__('Buka video', 'wp-taxonomy-grid')
    );
}

/**
 * Strict allowlist for the popup media markup (video, photo, fallback).
 * Everything the plugin builds goes through wp_kses() with this list as a
 * second safety net. Not used for the "post" kind, which has the same
 * trust level as the existing wptg_get_single_post response.
 *
 * @return array<string,array<string,bool>>
 */
function wptg_media_allowed_html(): array
{
    return [
        'video'  => ['src' => true, 'controls' => true, 'playsinline' => true, 'preload' => true, 'width' => true, 'height' => true],
        'source' => ['src' => true, 'type' => true],
        'iframe' => ['src' => true, 'allow' => true, 'allowfullscreen' => true, 'title' => true, 'referrerpolicy' => true, 'frameborder' => true, 'width' => true, 'height' => true],
        'img'    => ['src' => true, 'srcset' => true, 'sizes' => true, 'alt' => true, 'width' => true, 'height' => true, 'class' => true, 'loading' => true, 'decoding' => true],
        'div'    => ['class' => true],
        'p'      => ['class' => true],
        'a'      => ['class' => true, 'href' => true, 'target' => true, 'rel' => true],
    ];
}

/**
 * <img> markup for the photo popup: the featured image, else the first
 * image in the content. Empty string when the post has no image.
 */
function wptg_find_image_html(\WP_Post $post, string $title): string
{
    $image_args = ['alt' => $title, 'class' => 'wptg-media-img', 'loading' => 'eager'];

    $thumbnail_id = (int) get_post_thumbnail_id($post);
    if ($thumbnail_id > 0) {
        $html = wp_get_attachment_image($thumbnail_id, 'large', false, $image_args);
        if ($html !== '') {
            return $html;
        }
    }

    if (!preg_match('~<img\b[^>]*>~i', (string) $post->post_content, $matches)) {
        return '';
    }

    $tag = new WP_HTML_Tag_Processor($matches[0]);
    if (!$tag->next_tag('img')) {
        return '';
    }

    // Images from the editor carry their attachment id in the class (wp-image-123).
    $class_attr = $tag->get_attribute('class');
    if (is_string($class_attr) && preg_match('/\bwp-image-(\d+)\b/', $class_attr, $id_match)) {
        $html = wp_get_attachment_image((int) $id_match[1], 'large', false, $image_args);
        if ($html !== '') {
            return $html;
        }
    }

    $src = $tag->get_attribute('src');
    if (!is_string($src) || $src === '') {
        return '';
    }

    return sprintf(
        '<img class="wptg-media-img" src="%s" alt="%s" loading="eager" decoding="async">',
        esc_url($src),
        esc_attr($title)
    );
}

/**
 * Is this URL reachable from the public internet? Decided from the URL text
 * only, no request is made.
 *
 * "No" for: a non-http(s) scheme, a port other than 80/443 (dev servers), an
 * IP address in a private or reserved range (127.0.0.1, 192.168.x.x, ::1 ...),
 * a host without a dot ("localhost", a Docker service name), and the
 * reserved suffixes .local .localhost .test .example .invalid .internal .lan.
 *
 * Why it matters: Microsoft's Office viewer fetches the file itself, so it
 * can only show a file that the internet can reach.
 *
 * @param string $url Absolute URL of the file.
 */
function wptg_is_public_url(string $url): bool
{
    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return false;
    }

    if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return false;
    }

    $port = (int) ($parts['port'] ?? 0);
    if ($port !== 0 && !in_array($port, [80, 443], true)) {
        return false;
    }

    $host = strtolower(trim((string) $parts['host'], '[]'));

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    $last_dot = strrpos($host, '.');
    if ($last_dot === false) {
        return false;
    }

    return !in_array(substr($host, $last_dot + 1), WPTG_NON_PUBLIC_HOST_SUFFIXES, true);
}

/**
 * The files of a document popup, in the shape the JS expects.
 *
 * No preview HTML is built here: the JS renders each file by its `kind`.
 * The Word preview is decided here, per file, and can be overridden with the
 * filter `wptg_office_preview_enabled` (bool $enabled, string $file_url),
 * e.g. to force it on behind a proxy or to switch it off for good.
 *
 * @param array<int,array<string,mixed>> $files Output of wptg_get_document_files().
 * @return array<int,array{label:string,url:string,kind:string,mime:string,ext:string,size:string,download_url:string,office_preview:bool,embed_url:string}>
 */
function wptg_build_document_payload_files(array $files): array
{
    $payload = [];

    foreach ($files as $file) {
        $url       = esc_url_raw((string) $file['url']);
        $embed_url = '';

        if ($file['kind'] === 'office' && $url !== '') {
            $enabled   = (bool) apply_filters('wptg_office_preview_enabled', wptg_is_public_url($url), $url);
            $embed_url = $enabled ? WPTG_OFFICE_EMBED_URL . rawurlencode($url) : '';
        }

        $payload[] = [
            'label'          => (string) $file['label'],
            'url'            => $url,
            'kind'           => (string) $file['kind'],
            'mime'           => (string) $file['mime'],
            'ext'            => (string) $file['ext'],
            'size'           => (string) $file['size'],
            'download_url'   => $url,
            'office_preview' => $embed_url !== '',
            'embed_url'      => $embed_url,
        ];
    }

    return $payload;
}

/**
 * Everything the popup needs for one post.
 *
 * Kind order: video > document > photo > post. "document" is chosen from the
 * DATA (the post has validated files in its "Document Files" meta), like the
 * other kinds, not from the post type. A document post type without files
 * therefore falls through to photo / post instead of an empty viewer.
 *
 * @return array{kind:string,title:string,date:string,html:string,files?:array<int,array<string,mixed>>}
 */
function wptg_build_media_modal_payload(\WP_Post $post): array
{
    // The JS shows title/date with textContent, so HTML entities (&#8211;) must be decoded here.
    $title = html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $date  = get_the_date('d F Y', $post);
    $date  = is_string($date) ? $date : '';

    $source = wptg_resolve_video_source($post);
    if ($source !== null) {
        return [
            'kind'  => 'video',
            'title' => $title,
            'date'  => $date,
            'html'  => wp_kses(wptg_render_video_source_html($source, $title), wptg_media_allowed_html()),
        ];
    }

    $document_files = wptg_get_document_files($post->ID);
    if ($document_files !== []) {
        return [
            'kind'  => 'document',
            'title' => $title,
            'date'  => $date,
            'html'  => '',
            'files' => wptg_build_document_payload_files($document_files),
        ];
    }

    $image_html = wptg_find_image_html($post, $title);
    if ($image_html !== '') {
        return [
            'kind'  => 'photo',
            'title' => $title,
            'date'  => $date,
            'html'  => wp_kses($image_html, wptg_media_allowed_html()),
        ];
    }

    // No video, no image: same title + content popup as behavior "Open Floating Modal".
    return [
        'kind'  => 'post',
        'title' => $title,
        'date'  => $date,
        'html'  => wptg_render_single_post_detail($post->ID, true),
    ];
}

/**
 * AJAX: popup data for one post. Read-only, so guests may call it, but the
 * post must be public: published, public post type, no password.
 */
function wptg_ajax_get_media_modal(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post    = $post_id > 0 ? get_post($post_id) : null;

    $post_type_obj = $post instanceof \WP_Post ? get_post_type_object($post->post_type) : null;

    if (
        !$post instanceof \WP_Post
        || $post->post_status !== 'publish'
        || !$post_type_obj
        || !$post_type_obj->public
        || post_password_required($post)
    ) {
        // One neutral message for every reason, so the answer does not reveal what exists.
        wp_send_json_error(['message' => __('Konten tidak tersedia.', 'wp-taxonomy-grid')]);
    }

    wp_send_json_success(wptg_build_media_modal_payload($post));
}
add_action('wp_ajax_wptg_get_media_modal', 'wptg_ajax_get_media_modal');
add_action('wp_ajax_nopriv_wptg_get_media_modal', 'wptg_ajax_get_media_modal');
