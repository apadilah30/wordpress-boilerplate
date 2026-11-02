/**
 * Logic test for the Media Popup (wp-taxonomy-grid 4.3.0), run with:
 *
 *   node .agents/tasks/media-popup/test-logic.js
 *
 * There is no php on the host, so this is a literal JavaScript copy of the
 * pure parts of plugins-custom/wp-taxonomy-grid/includes/media-popup.php:
 *   wptg_parse_video_url(), wptg_video_source_from_token(),
 *   wptg_find_video_in_content(), wptg_resolve_video_source() (priority).
 * It proves the RULES (ids, host allowlist, extensions, ordering), not the
 * PHP syntax and not WordPress functions (WP_HTML_Tag_Processor is replaced by
 * a small regex attribute reader here). If you change the PHP rules, change
 * this copy too. Lives here, not in the plugin folder, so it is not shipped.
 */
'use strict';

const assert = require('assert');

const HOME_URL = 'https://site.test';
const VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogv', 'm4v', 'mov'];

const make = (type, id = '', hash = '', url = '') => ({ type, id, hash, url });

/* ---- wptg_parse_video_url() ---- */
function parseVideoUrl(input) {
    let url = input.trim();

    if (url.startsWith('//')) {
        url = 'https:' + url;
    } else if (url.startsWith('/')) {
        url = HOME_URL + url;
    }

    if (!/^https?:\/\//i.test(url)) {
        return null;
    }

    let parts;
    try {
        parts = new URL(url);
    } catch (e) {
        return null;
    }

    let host = parts.hostname.toLowerCase();
    host = host.startsWith('www.') ? host.slice(4) : host;
    const path = parts.pathname === '/' && !/^https?:\/\/[^/]+\//i.test(url) ? '' : parts.pathname;
    const query = parts.searchParams;

    if (['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com', 'youtu.be'].includes(host)) {
        let videoId = '';
        let m;
        if (host === 'youtu.be') {
            m = path.match(/^\/([^/]+)/);
            videoId = m ? m[1] : '';
        } else if (query.has('v')) {
            videoId = query.get('v');
        } else if ((m = path.match(/^\/(?:embed|shorts|live|v)\/([^/]+)/))) {
            videoId = m[1];
        }

        if (/^[A-Za-z0-9_-]{11}$/.test(videoId)) {
            return make('youtube', videoId);
        }
    }

    if (['vimeo.com', 'player.vimeo.com'].includes(host)) {
        const m = path.match(/^\/(?:video\/|channels\/[^/]+\/|groups\/[^/]+\/videos\/)?(\d+)(?:\/([A-Za-z0-9]+))?\/?$/);
        if (m) {
            let hash = query.has('h') ? query.get('h') : (m[2] || '');
            hash = /^[A-Za-z0-9]{6,20}$/.test(hash) ? hash : '';
            return make('vimeo', m[1], hash);
        }
    }

    const dot = path.lastIndexOf('.');
    const slash = path.lastIndexOf('/');
    const extension = dot > slash ? path.slice(dot + 1).toLowerCase() : '';

    return VIDEO_EXTENSIONS.includes(extension) ? make('file', '', '', url) : make('link', '', '', url);
}

/* ---- tiny stand-in for WP_HTML_Tag_Processor::get_attribute() ---- */
function getAttr(tag, name) {
    const re = new RegExp('(?<![\\w-])' + name + '\\s*=\\s*(?:"([^"]*)"|\'([^\']*)\'|([^\\s>]+))', 'i');
    const m = tag.match(re);
    if (!m) return null;
    const raw = m[1] !== undefined ? m[1] : (m[2] !== undefined ? m[2] : m[3]);
    return raw.replace(/&amp;/g, '&').replace(/&#0?38;/g, '&');
}

/* ---- wptg_video_source_from_token() ---- */
function sourceFromToken(token) {
    if (token[0] === '<') {
        const src = getAttr(token, 'src');
        if (typeof src !== 'string' || src === '') return null;

        const source = parseVideoUrl(src);
        if (source === null) return null;

        const isEmbed = ['youtube', 'vimeo'].includes(source.type);
        const tagName = token.match(/^<(\w+)/)[1].toUpperCase();

        if (tagName === 'IFRAME') return isEmbed ? source : null;

        if (tagName === 'VIDEO') {
            return (isEmbed || source.type === 'file') ? source : make('file', '', '', source.url);
        }

        const typeAttr = getAttr(token, 'type');
        const saysVideo = typeof typeAttr === 'string' && typeAttr.toLowerCase().startsWith('video/');
        if (source.type === 'file') return source;
        return (saysVideo && source.type === 'link') ? make('file', '', '', source.url) : null;
    }

    if (token[0] === '[') {
        const attrs = {};
        token.slice(1, -1).replace(/(\w+)\s*=\s*"([^"]*)"/g, (all, k, v) => { attrs[k] = v; return all; });
        for (const key of ['src', 'mp4', 'm4v', 'webm', 'ogv', 'mov']) {
            if (!attrs[key]) continue;
            const source = parseVideoUrl(attrs[key]);
            if (source !== null) {
                return source.type === 'link' ? make('file', '', '', source.url) : source;
            }
        }
        return null;
    }

    let url = token.replace(/[.,;:!?)]+$/, '').replace(/\\u0026/g, '&');
    url = url.replace(/&amp;/g, '&').replace(/&#0?38;/g, '&');
    const source = parseVideoUrl(url);
    return (source !== null && source.type !== 'link') ? source : null;
}

/* ---- wptg_find_video_in_content() ---- */
function findVideoInContent(content) {
    if (content === '') return null;
    const pattern = /<video\b[^>]*>|<source\b[^>]*>|<iframe\b[^>]*>|\[video\b[^\]]*\]|https?:\/\/[^\s"'<>\[\]]+/gi;
    const tokens = content.match(pattern);
    if (!tokens) return null;
    for (const token of tokens) {
        const source = sourceFromToken(token);
        if (source !== null) return source;
    }
    return null;
}

/* ---- wptg_resolve_video_source() priority ---- */
function resolveVideoSource(post) {
    if (post.externalUrl && post.externalUrl.trim() !== '') {
        const source = parseVideoUrl(post.externalUrl);
        if (source !== null) return source;
    }
    const fromContent = findVideoInContent(post.content || '');
    if (fromContent !== null) return fromContent;
    if (post.attachmentUrl) return make('file', '', '', post.attachmentUrl);
    return null;
}

/* ------------------------------------------------------------------ */

let passed = 0;
function check(name, actual, expected) {
    try {
        assert.deepStrictEqual(actual, expected);
        passed += 1;
    } catch (e) {
        console.error('FAIL: ' + name + '\n  actual:   ' + JSON.stringify(actual) + '\n  expected: ' + JSON.stringify(expected));
        process.exitCode = 1;
    }
}

const YT = 'dQw4w9WgXcQ';

// YouTube forms
check('yt watch', parseVideoUrl('https://www.youtube.com/watch?v=' + YT), make('youtube', YT));
check('yt watch + extra params', parseVideoUrl('https://youtube.com/watch?feature=share&v=' + YT + '&t=42s'), make('youtube', YT));
check('yt http', parseVideoUrl('http://www.youtube.com/watch?v=' + YT), make('youtube', YT));
check('yt youtu.be', parseVideoUrl('https://youtu.be/' + YT), make('youtube', YT));
check('yt youtu.be + si', parseVideoUrl('https://youtu.be/' + YT + '?si=abcDEF'), make('youtube', YT));
check('yt shorts', parseVideoUrl('https://www.youtube.com/shorts/' + YT), make('youtube', YT));
check('yt embed', parseVideoUrl('https://www.youtube.com/embed/' + YT), make('youtube', YT));
check('yt nocookie embed', parseVideoUrl('https://www.youtube-nocookie.com/embed/' + YT + '?rel=0'), make('youtube', YT));
check('yt live', parseVideoUrl('https://www.youtube.com/live/' + YT), make('youtube', YT));
check('yt mobile', parseVideoUrl('https://m.youtube.com/watch?v=' + YT), make('youtube', YT));
check('yt music', parseVideoUrl('https://music.youtube.com/watch?v=' + YT), make('youtube', YT));
check('yt id with - and _', parseVideoUrl('https://youtu.be/a-b_c-d_e-f'), make('youtube', 'a-b_c-d_e-f'));
check('yt id too short -> link', parseVideoUrl('https://youtu.be/short').type, 'link');
check('yt id too long -> link', parseVideoUrl('https://www.youtube.com/watch?v=' + YT + 'X').type, 'link');
check('yt channel -> link', parseVideoUrl('https://www.youtube.com/@imigrasi').type, 'link');
check('yt playlist without v -> link', parseVideoUrl('https://www.youtube.com/playlist?list=PL123').type, 'link');

// Host allowlist: exact match only
check('evil suffix host', parseVideoUrl('https://youtube.com.evil.com/watch?v=' + YT).type, 'link');
check('evil prefix host', parseVideoUrl('https://notyoutube.com/watch?v=' + YT).type, 'link');
check('evil subdomain of vimeo', parseVideoUrl('https://vimeo.com.evil.io/123456789').type, 'link');
check('evil user@host', parseVideoUrl('https://youtube.com@evil.com/watch?v=' + YT).type, 'link');
check('other site with v param', parseVideoUrl('https://example.com/watch?v=' + YT).type, 'link');

// Schemes
check('javascript scheme', parseVideoUrl('javascript:alert(1)'), null);
check('data scheme', parseVideoUrl('data:text/html;base64,AAAA'), null);
check('ftp scheme', parseVideoUrl('ftp://x.test/a.mp4'), null);
check('empty', parseVideoUrl('   '), null);
check('protocol-relative', parseVideoUrl('//player.vimeo.com/video/123456789'), make('vimeo', '123456789'));
check('root-relative file', parseVideoUrl('/app/uploads/a.mp4'), make('file', '', '', 'https://site.test/app/uploads/a.mp4'));

// Vimeo forms
check('vimeo plain', parseVideoUrl('https://vimeo.com/123456789'), make('vimeo', '123456789'));
check('vimeo www', parseVideoUrl('https://www.vimeo.com/123456789'), make('vimeo', '123456789'));
check('vimeo player', parseVideoUrl('https://player.vimeo.com/video/123456789'), make('vimeo', '123456789'));
check('vimeo player ?h=', parseVideoUrl('https://player.vimeo.com/video/123456789?h=abcdef1234'), make('vimeo', '123456789', 'abcdef1234'));
check('vimeo unlisted path hash', parseVideoUrl('https://vimeo.com/123456789/abcdef1234'), make('vimeo', '123456789', 'abcdef1234'));
check('vimeo bad hash ignored', parseVideoUrl('https://vimeo.com/123456789/ab'), make('vimeo', '123456789'));
check('vimeo channels', parseVideoUrl('https://vimeo.com/channels/staffpicks/123456789'), make('vimeo', '123456789'));
check('vimeo groups', parseVideoUrl('https://vimeo.com/groups/abc/videos/123456789'), make('vimeo', '123456789'));
check('vimeo trailing slash', parseVideoUrl('https://vimeo.com/123456789/'), make('vimeo', '123456789'));
check('vimeo profile -> link', parseVideoUrl('https://vimeo.com/imigrasi').type, 'link');

// Direct files
for (const ext of VIDEO_EXTENSIONS) {
    check('file .' + ext, parseVideoUrl('https://cdn.test/a/b.' + ext).type, 'file');
}
check('file uppercase', parseVideoUrl('https://cdn.test/B.MP4').type, 'file');
check('file with query', parseVideoUrl('https://cdn.test/b.mp4?x=1&y=2').type, 'file');
check('file mp3 -> link', parseVideoUrl('https://cdn.test/b.mp3').type, 'link');
check('file double ext -> link', parseVideoUrl('https://cdn.test/b.mp4.exe').type, 'link');
check('dot only in host -> link', parseVideoUrl('https://cdn.test').type, 'link');
check('tiktok -> link', parseVideoUrl('https://www.tiktok.com/@imigrasi/video/7000000000000000000').type, 'link');
check('instagram -> link', parseVideoUrl('https://www.instagram.com/reel/Cabc123/').type, 'link');

// Scanning the content
check('empty content', findVideoInContent(''), null);
check('plain text', findVideoInContent('<p>Halo dunia, tidak ada video.</p>'), null);
check('image url ignored', findVideoInContent('<img src="https://cdn.test/a.jpg">'), null);
check('iframe youtube', findVideoInContent('<p>x</p><iframe src="https://www.youtube.com/embed/' + YT + '" width="560"></iframe>'), make('youtube', YT));
check('iframe protocol-relative', findVideoInContent('<iframe src="//player.vimeo.com/video/123456789"></iframe>'), make('vimeo', '123456789'));
check('iframe other host dropped', findVideoInContent('<iframe src="https://www.google.com/maps/embed?pb=1"></iframe>'), null);
check('iframe other host skipped, later youtube wins', findVideoInContent('<iframe src="https://maps.test/x"></iframe><p>https://youtu.be/' + YT + '</p>'), make('youtube', YT));
check('iframe with youtube in query of other host dropped', findVideoInContent('<iframe src="https://evil.test/?u=https://youtu.be/' + YT + '"></iframe>'), null);
check('video tag src', findVideoInContent('<figure class="wp-block-video"><video controls src="https://cdn.test/v.mp4"></video></figure>'), make('file', '', '', 'https://cdn.test/v.mp4'));
check('video tag src without extension', findVideoInContent('<video controls src="https://cdn.test/stream?id=9"></video>'), make('file', '', '', 'https://cdn.test/stream?id=9'));
check('video with source child', findVideoInContent('<video controls><source src="https://cdn.test/v.webm" type="video/webm"></video>'), make('file', '', '', 'https://cdn.test/v.webm'));
check('source with video type, no extension', findVideoInContent('<video><source src="https://cdn.test/s?id=1" type="video/mp4"></video>'), make('file', '', '', 'https://cdn.test/s?id=1'));
check('audio source ignored', findVideoInContent('<audio><source src="https://cdn.test/a.mp3" type="audio/mpeg"></audio>'), null);
check('picture source ignored', findVideoInContent('<picture><source srcset="a.webp" type="image/webp"></picture>'), null);
check('embed block url', findVideoInContent('<!-- wp:embed {"url":"https://www.youtube.com/watch?v=' + YT + '","type":"video"} -->\n<figure class="wp-block-embed"><div class="wp-block-embed__wrapper">\nhttps://www.youtube.com/watch?v=' + YT + '\n</div></figure>\n<!-- /wp:embed -->'), make('youtube', YT));
check('embed with \\u0026 in comment json', findVideoInContent('<!-- wp:embed {"url":"https://www.youtube.com/watch?t=3\\u0026v=' + YT + '"} /-->'), make('youtube', YT));
check('bare url in link href with &amp;', findVideoInContent('<a href="https://www.youtube.com/watch?t=3&amp;v=' + YT + '">tonton</a>'), make('youtube', YT));
check('bare url trailing dot', findVideoInContent('Tonton di https://youtu.be/' + YT + '.'), make('youtube', YT));
check('[embed] shortcode', findVideoInContent('[embed]https://vimeo.com/123456789[/embed]'), make('vimeo', '123456789'));
check('[video src]', findVideoInContent('[video src="https://cdn.test/v.mp4" width="640"]'), make('file', '', '', 'https://cdn.test/v.mp4'));
check('[video mp4=]', findVideoInContent('[video mp4="https://cdn.test/v.mp4" poster="https://cdn.test/p.jpg"]'), make('file', '', '', 'https://cdn.test/v.mp4'));
check('[video] no source', findVideoInContent('[video]'), null);
check('link to a channel page ignored', findVideoInContent('<a href="https://www.youtube.com/@imigrasi">channel</a>'), null);
check('link to tiktok in content ignored', findVideoInContent('<a href="https://www.tiktok.com/@x/video/1">tiktok</a>'), null);
check('first in document order wins (iframe before video)', findVideoInContent('<iframe src="https://www.youtube.com/embed/' + YT + '"></iframe><video src="https://cdn.test/v.mp4"></video>'), make('youtube', YT));
check('first in document order wins (video before iframe)', findVideoInContent('<video src="https://cdn.test/v.mp4"></video><iframe src="https://www.youtube.com/embed/' + YT + '"></iframe>'), make('file', '', '', 'https://cdn.test/v.mp4'));
check('data-src is not src', findVideoInContent('<video data-src="https://cdn.test/v.mp4"></video>'), null);
check('video poster url is not a video', findVideoInContent('<video controls poster="https://cdn.test/p.jpg"></video>'), null);

// Priority: meta > content > attachment
const inContent = '<iframe src="https://www.youtube.com/embed/' + YT + '"></iframe>';
check('priority: meta wins over content', resolveVideoSource({ externalUrl: 'https://vimeo.com/123456789', content: inContent, attachmentUrl: 'https://cdn.test/a.mp4' }), make('vimeo', '123456789'));
check('priority: content wins over attachment', resolveVideoSource({ externalUrl: '', content: inContent, attachmentUrl: 'https://cdn.test/a.mp4' }), make('youtube', YT));
check('priority: attachment last', resolveVideoSource({ externalUrl: '', content: '<p>x</p>', attachmentUrl: 'https://cdn.test/a.mp4' }), make('file', '', '', 'https://cdn.test/a.mp4'));
check('priority: no video at all', resolveVideoSource({ externalUrl: '', content: '<p>x</p>', attachmentUrl: '' }), null);
check('priority: tiktok meta stays a link (fallback button), beats content', resolveVideoSource({ externalUrl: 'https://www.tiktok.com/@x/video/1', content: inContent }).type, 'link');
check('priority: junk meta falls through to content', resolveVideoSource({ externalUrl: 'not a url', content: inContent }), make('youtube', YT));

console.log(process.exitCode ? 'SOME TESTS FAILED' : 'ALL ' + passed + ' CHECKS PASSED');
