// Helper: php -l semua file PHP yang diubah/baru + cek BOM + node --check JS + kurung CSS + ID control ganda.
// Pakai: node .agents/tasks/documents/lint-all.js  -> hasil di lint-all.out
// php diambil dari Laragon (ada di host ini). Kalau tidak ada, php -l dilewati dan dicatat.
'use strict';

const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const repo = path.resolve(__dirname, '..', '..', '..');
const plugin = path.join(repo, 'plugins-custom', 'wp-taxonomy-grid');
const out = [];

const phpCandidates = [
    'C:\\laragon\\bin\\php\\php-8.3.1-nts-Win32-vs16-x64\\php.exe',
    'C:\\laragon\\bin\\php\\php-8.1.10-Win32-vs16-x64\\php.exe',
];
const php = phpCandidates.find((p) => fs.existsSync(p));

const phpFiles = [
    path.join(repo, 'web', 'app', 'mu-plugins', 'custom-post-types.php'),
    path.join(plugin, 'wp-taxonomy-grid.php'),
    path.join(plugin, 'includes', 'class-document-meta-box.php'),
    path.join(plugin, 'includes', 'class-elementor-widget.php'),
    path.join(plugin, 'includes', 'class-video-meta-box.php'),
    path.join(plugin, 'includes', 'media-popup.php'),
];

const jsFiles = ['modal.js', 'media-popup.js', 'taxonomy-grid.js', 'document-viewer.js', 'admin-documents.js']
    .map((f) => path.join(plugin, 'assets', 'js', f));
const cssFiles = ['taxonomy-grid.css', 'admin-documents.css'].map((f) => path.join(plugin, 'assets', 'css', f));

let problems = 0;
function note(ok, text) {
    out.push((ok ? 'ok   ' : 'FAIL ') + text);
    if (!ok) problems++;
}

for (const file of phpFiles) {
    const buf = fs.readFileSync(file);
    const bom = buf[0] === 0xef && buf[1] === 0xbb && buf[2] === 0xbf;
    const src = buf.toString('utf8');
    note(!bom, 'no BOM ' + path.basename(file));
    note(src.startsWith('<?php'), 'starts with <?php ' + path.basename(file));
    note(/declare\(strict_types=1\);/.test(src), 'strict_types ' + path.basename(file));
    if (php) {
        try {
            const res = cp.execFileSync(php, ['-l', file], { encoding: 'utf8' });
            note(/No syntax errors/.test(res), 'php -l ' + path.basename(file) + ' :: ' + res.trim());
        } catch (e) {
            note(false, 'php -l ' + path.basename(file) + ' :: ' + String(e.stdout || e.message).trim());
        }
    }
}
if (!php) out.push('SKIP php -l (php tidak ditemukan)');

for (const file of jsFiles) {
    try {
        cp.execFileSync(process.execPath, ['--check', file], { stdio: 'pipe' });
        note(true, 'node --check ' + path.basename(file));
    } catch (e) {
        note(false, 'node --check ' + path.basename(file) + ' :: ' + String(e.stderr || e.message));
    }
}

for (const file of cssFiles) {
    const css = fs.readFileSync(file, 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
    const open = css.split('{').length - 1;
    const close = css.split('}').length - 1;
    note(open === close, 'css braces ' + path.basename(file) + ' ' + open + '/' + close);
}

// Control id ganda (pola sama dengan halaman-berita/check-control-ids.js).
const widget = fs.readFileSync(path.join(plugin, 'includes', 'class-elementor-widget.php'), 'utf8');
const ids = [];
let m;
const direct = /(?:add_control|add_responsive_control|add_box_control|add_alignment_control|add_bg_control|start_controls_section|start_controls_tab|start_controls_tabs)\(\s*'([a-z0-9_]+)'/g;
while ((m = direct.exec(widget))) ids.push(m[1]);
// Group controls only. 'name' => ... inside a conditions array is followed by 'operator'.
const group = /'name'\s*=>\s*'([a-z0-9_]+)'\s*,(?!\s*'operator')/g;
while ((m = group.exec(widget))) ids.push(m[1]);
const textStyle = /add_text_style\(\s*'([a-z0-9_]+)'/g;
while ((m = textStyle.exec(widget))) ids.push(m[1] + '_typography', m[1] + '_color');
const badge = /add_badge_box_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = badge.exec(widget))) ids.push(m[1] + '_margin', m[1] + '_padding', m[1] + '_bg', m[1] + '_radius');
const spacing = /add_spacing_controls\(\s*'([a-z0-9_]+)'/g;
while ((m = spacing.exec(widget))) ids.push(m[1] + '_margin', m[1] + '_padding');
const seen = {};
ids.forEach((id) => { seen[id] = (seen[id] || 0) + 1; });
// Ids ending in "_" are loop prefixes (e.g. 'wptg_sterm_' . $state), not real ids.
const dups = Object.keys(seen).filter((id) => seen[id] > 1 && !id.endsWith('_'));
note(dups.length === 0, 'control ids unique (' + ids.length + ' ids) ' + dups.join(', '));

// Versi 4.5.0 di semua tempat.
const main = fs.readFileSync(path.join(plugin, 'wp-taxonomy-grid.php'), 'utf8');
note(/Version:\s+4\.5\.0/.test(main), 'docblock Version 4.5.0');
note(/define\('WPTG_VERSION', '4\.5\.0'\)/.test(main), 'WPTG_VERSION 4.5.0');
note(/"version": "4\.5\.0"/.test(fs.readFileSync(path.join(plugin, 'composer.json'), 'utf8')), 'composer.json 4.5.0');
note(/Stable tag: 4\.5\.0/.test(fs.readFileSync(path.join(plugin, 'readme.txt'), 'utf8')), 'readme Stable tag 4.5.0');
note(/= 4\.5\.0 =/.test(fs.readFileSync(path.join(plugin, 'readme.txt'), 'utf8')), 'readme changelog 4.5.0');

out.push(problems === 0 ? 'NO PROBLEMS FOUND' : problems + ' PROBLEM(S) FOUND');
fs.writeFileSync(path.join(__dirname, 'lint-all.out'), out.join('\n') + '\n');
