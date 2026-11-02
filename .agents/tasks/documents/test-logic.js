// Test logika murni untuk fitur Document Preview (bukan bagian plugin).
// Pakai: node .agents/tasks/documents/test-logic.js   -> hasil di test-logic.out
//
// Isinya PORT ke JavaScript dari fungsi PHP di plugin, diuji dengan tabel yang sama
// (test-cases.json) yang dipakai test-logic.php terhadap kode PHP asli:
//   - wptg_document_kind()                  klasifikasi jenis file (mime dulu, ekstensi cadangan)
//   - wptg_is_public_url()                  deteksi host non-publik untuk viewer Office
//   - rawurlencode + embed URL Office       (encodeURIComponent beda dari rawurlencode di ! ' ( ) *)
//   - wptg_format_file_size()               penjaga ukuran (<= 0 = ''); size_format() sendiri milik WordPress
// Port ini TIDAK menjalankan PHP. Kesamaan perilaku dijaga karena kedua test memakai tabel yang sama.
'use strict';

const fs = require('fs');
const path = require('path');

const cases = JSON.parse(fs.readFileSync(path.join(__dirname, 'test-cases.json'), 'utf8'));
const lines = [];
let failures = 0;

function check(label, actual, expected) {
    const ok = actual === expected;
    if (!ok) failures++;
    lines.push((ok ? 'ok   ' : 'FAIL ') + label + (ok ? '' : ' :: got ' + JSON.stringify(actual) + ' expected ' + JSON.stringify(expected)));
}

/* --- port: wptg_document_kind() ------------------------------------------ */
const MIME_KINDS = {
    'application/pdf': 'pdf',
    'application/msword': 'office',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'office',
    'image/jpeg': 'image',
    'image/png': 'image',
    'image/webp': 'image',
};

function documentKind(mime, extension) {
    mime = mime.toLowerCase();
    if (Object.prototype.hasOwnProperty.call(MIME_KINDS, mime)) return MIME_KINDS[mime];

    switch (extension.toLowerCase()) {
        case 'pdf': return 'pdf';
        case 'doc':
        case 'docx': return 'office';
        case 'jpg':
        case 'jpeg':
        case 'png':
        case 'webp': return 'image';
        default: return 'other';
    }
}

/* --- port: wptg_is_public_url() ------------------------------------------ */
const NON_PUBLIC_SUFFIXES = ['local', 'localhost', 'test', 'example', 'invalid', 'internal', 'lan'];

// Same ranges as PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE for IPv4,
// plus the usual IPv6 loopback / link-local / unique-local blocks.
function isNonPublicIp(host) {
    const v4 = host.match(/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/);
    if (v4) {
        const [a, b] = [Number(v4[1]), Number(v4[2])];
        return a === 0 || a === 10 || a === 127 || a >= 240
            || (a === 169 && b === 254)
            || (a === 172 && b >= 16 && b <= 31)
            || (a === 192 && b === 168);
    }
    const lower = host.toLowerCase();
    return lower === '::1' || lower === '::' || /^fe[89ab]/.test(lower) || /^f[cd]/.test(lower);
}

function isIp(host) {
    return /^(\d{1,3}\.){3}\d{1,3}$/.test(host) || host.includes(':');
}

function isPublicUrl(url) {
    let parsed;
    try {
        parsed = new URL(url);
    } catch (error) {
        return false;
    }

    if (parsed.hostname === '' || !['http:', 'https:'].includes(parsed.protocol)) return false;

    // URL drops the default port of the scheme; PHP sees 80/443 on any scheme as allowed.
    const port = parsed.port === '' ? 0 : Number(parsed.port);
    if (port !== 0 && ![80, 443].includes(port)) return false;

    const host = parsed.hostname.replace(/^\[|\]$/g, '').toLowerCase();

    if (isIp(host)) return !isNonPublicIp(host);

    const lastDot = host.lastIndexOf('.');
    if (lastDot === -1) return false;

    return !NON_PUBLIC_SUFFIXES.includes(host.slice(lastDot + 1));
}

/* --- port: rawurlencode + embed URL --------------------------------------- */
function rawurlencode(value) {
    return encodeURIComponent(value).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase());
}

const OFFICE_EMBED_URL = 'https://view.officeapps.live.com/op/embed.aspx?src=';

function embedUrl(url) {
    return isPublicUrl(url) ? OFFICE_EMBED_URL + rawurlencode(url) : '';
}

/* --- port: wptg_format_file_size() guard + a MODEL of WordPress size_format() --- */
function sizeFormatModel(bytes, decimals) {
    const units = [['TB', 1024 ** 4], ['GB', 1024 ** 3], ['MB', 1024 ** 2], ['KB', 1024], ['B', 1]];
    for (const [name, size] of units) {
        if (bytes >= size) return (bytes / size).toFixed(decimals) + ' ' + name;
    }
    return '0.0 B';
}

function formatFileSize(bytes) {
    return bytes > 0 ? sizeFormatModel(bytes, 1) : '';
}

/* --- run ------------------------------------------------------------------ */
cases.kind.forEach(([mime, ext, expected]) => check('kind(' + mime + ', ' + ext + ')', documentKind(mime, ext), expected));
cases.public_url.forEach(([url, expected]) => check('public(' + url + ')', isPublicUrl(url), expected));

cases.embed.forEach(([url, expected]) => {
    const wanted = isPublicUrl(url) ? expected : '';
    check('embed(' + url + ')', embedUrl(url), wanted);
});

check('size(0) is empty', formatFileSize(0), '');
check('size(-5) is empty', formatFileSize(-5), '');
check('size(500)', formatFileSize(500), '500.0 B');
check('size(1536)', formatFileSize(1536), '1.5 KB');
check('size(1048576)', formatFileSize(1048576), '1.0 MB');
check('size(5 * 1024^3)', formatFileSize(5 * 1024 ** 3), '5.0 GB');

lines.push(failures === 0 ? 'ALL PASSED (' + lines.length + ' checks)' : failures + ' FAILED');
fs.writeFileSync(path.join(__dirname, 'test-logic.out'), lines.join('\n') + '\n');
