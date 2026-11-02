// Helper: cek kartu dokumen 4.6.0 (php -l, BOM, strict_types, node --check, kurung CSS,
// ID control ganda, versi 4.6.0 di semua tempat, sisa kode lama).
// Pakai: node .agents/tasks/document-card/lint-card.js   -> hasil di lint-card.out
// (shell Windows kadang tidak menampilkan stdout, jadi hasil ditulis ke file.)
'use strict';

const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const repo = path.resolve(__dirname, '..', '..', '..');
const plugin = path.join(repo, 'plugins-custom', 'wp-taxonomy-grid');
const out = [];
let problems = 0;

function note(ok, text) {
    out.push((ok ? 'ok   ' : 'FAIL ') + text);
    if (!ok) problems++;
}

const php = [
    'C:\\laragon\\bin\\php\\php-8.3.1-nts-Win32-vs16-x64\\php.exe',
    'C:\\laragon\\bin\\php\\php-8.2.14-nts-Win32-vs16-x64\\php.exe',
].find((p) => fs.existsSync(p));

const phpFiles = ['wp-taxonomy-grid.php', 'includes/class-elementor-widget.php', 'includes/class-document-meta-box.php', 'includes/media-popup.php', 'includes/class-video-meta-box.php']
    .map((f) => path.join(plugin, f));

for (const file of phpFiles) {
    const buf = fs.readFileSync(file);
    const src = buf.toString('utf8');
    const name = path.basename(file);
    note(!(buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf), 'no BOM ' + name);
    note(src.startsWith('<?php'), 'starts with <?php ' + name);
    note(/declare\(strict_types=1\);/.test(src), 'strict_types ' + name);
    if (php) {
        try {
            const res = cp.execFileSync(php, ['-l', file], { encoding: 'utf8' });
            note(/No syntax errors/.test(res), 'php -l ' + name + ' :: ' + res.trim());
        } catch (e) {
            note(false, 'php -l ' + name + ' :: ' + String(e.stdout || e.message).trim());
        }
    }
}
if (!php) out.push('SKIP php -l (php tidak ditemukan)');

const jsFiles = ['modal.js', 'media-popup.js', 'taxonomy-grid.js', 'document-viewer.js', 'admin-documents.js'].map((f) => path.join(plugin, 'assets', 'js', f));
for (const file of jsFiles) {
    const buf = fs.readFileSync(file);
    note(!(buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf), 'no BOM ' + path.basename(file));
    try {
        cp.execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
        note(true, 'node --check ' + path.basename(file));
    } catch (e) {
        note(false, 'node --check ' + path.basename(file) + ' :: ' + String(e.stderr || e.message));
    }
}

const cssFile = path.join(plugin, 'assets', 'css', 'taxonomy-grid.css');
const css = fs.readFileSync(cssFile, 'utf8');
const cssBuf = fs.readFileSync(cssFile);
note(!(cssBuf[0] === 0xef && cssBuf[1] === 0xbb && cssBuf[2] === 0xbf), 'no BOM taxonomy-grid.css');
const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '');
const open = stripped.split('{').length - 1;
const close = stripped.split('}').length - 1;
note(open === close, 'css braces ' + open + '/' + close);
const parenOpen = stripped.split('(').length - 1;
const parenClose = stripped.split(')').length - 1;
note(parenOpen === parenClose, 'css parentheses ' + parenOpen + '/' + parenClose);

// Control ids: direct calls + group 'name' + helper-built ids. Ids built in a loop ('wptg_doc_' . $kind) are expanded by hand.
const widget = fs.readFileSync(path.join(plugin, 'includes', 'class-elementor-widget.php'), 'utf8');
const ids = [];
let m;
const direct = /(?:add_control|add_responsive_control|add_box_control|add_alignment_control|add_bg_control|add_var_slider|add_var_color|start_controls_section|start_controls_tab|start_controls_tabs)\(\s*'([a-z0-9_]+)'/g;
while ((m = direct.exec(widget))) ids.push(m[1]);
const group = /'name'\s*=>\s*'([a-z0-9_]+)'\s*,(?!\s*'operator')/g;
while ((m = group.exec(widget))) ids.push(m[1]);
const textStyle = /add_text_style\(\s*'([a-z0-9_]+)'/g;
while ((m = textStyle.exec(widget))) ids.push(m[1] + '_typography', m[1] + '_color');
const badge = /add_badge_box_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = badge.exec(widget))) ids.push(m[1] + '_margin', m[1] + '_padding', m[1] + '_bg', m[1] + '_radius');
const spacing = /add_spacing_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = spacing.exec(widget))) ids.push(m[1] + '_margin', m[1] + '_padding');
for (const kind of ['view', 'download']) {
    ids.push('wptg_doc_' + kind + '_heading', 'wptg_doc_' + kind + '_state_tabs', 'wptg_doc_' + kind + '_tab_normal', 'wptg_doc_' + kind + '_tab_hover');
    ids.push('wptg_doc_' + kind + '_color', 'wptg_doc_' + kind + '_bg', 'wptg_doc_' + kind + '_hover_color', 'wptg_doc_' + kind + '_hover_bg');
}
const seen = {};
ids.forEach((id) => { seen[id] = (seen[id] || 0) + 1; });
const dups = Object.keys(seen).filter((id) => seen[id] > 1 && !id.endsWith('_'));
note(dups.length === 0, 'control ids unique (' + ids.length + ' ids) ' + dups.join(', '));

// Versi 4.6.0 di semua tempat.
const main = fs.readFileSync(path.join(plugin, 'wp-taxonomy-grid.php'), 'utf8');
const readme = fs.readFileSync(path.join(plugin, 'readme.txt'), 'utf8');
note(/Version:\s+4\.6\.0/.test(main), 'docblock Version 4.6.0');
note(/define\('WPTG_VERSION', '4\.6\.0'\)/.test(main), 'WPTG_VERSION 4.6.0');
note(/"version": "4\.6\.0"/.test(fs.readFileSync(path.join(plugin, 'composer.json'), 'utf8')), 'composer.json 4.6.0');
note(/Stable tag: 4\.6\.0/.test(readme), 'readme Stable tag 4.6.0');
note(/= 4\.6\.0 =/.test(readme), 'readme changelog 4.6.0');

// Sisa kode lama yang tidak boleh ada lagi (di luar .agents dan readme changelog yang menyebut nama lama).
const mediaPopup = fs.readFileSync(path.join(plugin, 'assets', 'js', 'media-popup.js'), 'utf8');
const taxGrid = fs.readFileSync(path.join(plugin, 'assets', 'js', 'taxonomy-grid.js'), 'utf8');
const leftovers = [
    ['wptg-doc-button', [main, css, widget]],
    ['wptg_render_document_icon', [main]],
    ['data-wptg-download', [main, mediaPopup]],
    ['fillReadmoreIcons', [taxGrid]],
    ["$_POST['doc_show_cover']", [main]],
    ['doc_show_cover:', [taxGrid]],
    ['data-doc-show-cover', [main, taxGrid]],
    ['wptg-doc-icon--', [main, css]],
    ['wptg-doc-card" tabindex', [main]],
];
for (const [needle, sources] of leftovers) {
    note(!sources.some((s) => s.includes(needle)), 'no leftover "' + needle + '"');
}

out.push(problems === 0 ? 'NO PROBLEMS FOUND' : problems + ' PROBLEM(S) FOUND');
fs.writeFileSync(path.join(__dirname, 'lint-card.out'), out.join('\n') + '\n');
