// Helper: salin file PDF.js yang dibutuhkan dari tmp/package ke vendor plugin, tulis VERSION.txt.
// Pakai: node .agents/tasks/documents/copy-pdfjs.js  -> log di copy-pdfjs.out
// Dipilih: build LEGACY (jalan di browser HP lama), versi .min saja (tanpa source map),
// + wasm (gambar JBIG2/JPEG2000), standard_fonts (font PDF yang tidak di-embed), iccs.
// Tidak dipakai: cmaps (hanya untuk PDF huruf CJK), web viewer, types, sandbox.
'use strict';

const fs = require('fs');
const path = require('path');

const pkg = path.join(__dirname, 'tmp', 'package');
const dest = path.resolve(__dirname, '..', '..', '..', 'plugins-custom', 'wp-taxonomy-grid', 'assets', 'vendor', 'pdfjs');
const log = [];

function copy(from, to) {
    fs.mkdirSync(path.dirname(to), { recursive: true });
    fs.copyFileSync(from, to);
    log.push('copied ' + path.relative(pkg, from) + ' -> ' + path.relative(dest, to) + ' (' + fs.statSync(to).size + ' bytes)');
}

function copyDir(fromDir, toDir, filter) {
    for (const name of fs.readdirSync(fromDir)) {
        if (filter && !filter(name)) continue;
        copy(path.join(fromDir, name), path.join(toDir, name));
    }
}

const version = JSON.parse(fs.readFileSync(path.join(pkg, 'package.json'), 'utf8')).version;

copy(path.join(pkg, 'legacy', 'build', 'pdf.min.mjs'), path.join(dest, 'pdf.min.mjs'));
copy(path.join(pkg, 'legacy', 'build', 'pdf.worker.min.mjs'), path.join(dest, 'pdf.worker.min.mjs'));
copy(path.join(pkg, 'LICENSE'), path.join(dest, 'LICENSE'));
copyDir(path.join(pkg, 'wasm'), path.join(dest, 'wasm'), (n) => (n.endsWith('.wasm') && !n.startsWith('quickjs')) || n.startsWith('LICENSE'));
copyDir(path.join(pkg, 'standard_fonts'), path.join(dest, 'standard_fonts'));
copyDir(path.join(pkg, 'iccs'), path.join(dest, 'iccs'));

const versionText = [
    'pdfjs-dist ' + version,
    'License: Apache-2.0 (see LICENSE in this folder)',
    'Source: https://www.npmjs.com/package/pdfjs-dist (npm pack pdfjs-dist@' + version + ')',
    'Fetched: ' + new Date().toISOString().slice(0, 10),
    '',
    'Build: LEGACY (legacy/build/*.min.mjs) so older phones work. Swap to build/*.min.mjs for the modern build.',
    'Files here: pdf.min.mjs, pdf.worker.min.mjs, wasm/ (JBIG2, JPEG2000, qcms), standard_fonts/, iccs/.',
    'Left out on purpose: source maps, cmaps (only for CJK PDFs), web viewer, types, sandbox.',
    '',
    'Update: run .agents/tasks/documents/fetch-pdfjs.js then copy-pdfjs.js, and re-test a multi-page PDF.',
    '',
].join('\n');
fs.writeFileSync(path.join(dest, 'VERSION.txt'), versionText);
log.push('wrote VERSION.txt');

fs.writeFileSync(path.join(__dirname, 'copy-pdfjs.out'), log.join('\n') + '\n');
