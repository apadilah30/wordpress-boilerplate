// Helper: cek markup hasil render-preview.php (output renderer asli) terhadap spesifikasi kartu.
// Pakai: node .agents/tasks/document-card/check-markup.js  -> hasil di check-markup.out
'use strict';

const fs = require('fs');
const path = require('path');

const html = fs.readFileSync(path.join(__dirname, 'preview-frame.html'), 'utf8');
const out = [];
let problems = 0;
const check = (ok, text) => {
    out.push((ok ? 'ok   ' : 'FAIL ') + text);
    if (!ok) problems++;
};

// Potong per kartu (artikel), buang data URI gambar supaya mudah dibaca.
const cards = html.split('<article ').slice(1).map((chunk) => '<article ' + chunk.split('</article>')[0] + '</article>');
check(cards.length === 4, 'ada 4 kartu contoh (' + cards.length + ')');
const [c1, c2, c3, c4] = cards.map((c) => c.replace(/src="data:[^"]*"/g, 'src="DATA"'));

for (const [i, c] of [c1, c2, c3, c4].entries()) {
    const open = c.match(/^<article [^>]*>/)[0];
    check(!/\srole=/.test(open) && !/tabindex/.test(open), 'kartu ' + (i + 1) + ': <article> tanpa role/tabindex (tidak ada interactive bersarang)');
    check(/data-wptg-media="\d+"/.test(open) && !/aria-haspopup/.test(open), 'kartu ' + (i + 1) + ': article punya data-wptg-media, tanpa aria-haspopup');
    check(c.indexOf('wptg-doc-head') < c.indexOf('wptg-doc-title'), 'kartu ' + (i + 1) + ': head berisi judul');
    check(/<span class="wptg-doc-date">Diunggah pada: \d{1,2} [A-Za-z]+ 2026<\/span>/.test(c), 'kartu ' + (i + 1) + ': tanggal "Diunggah pada: j F Y"');
}
check(/Diunggah pada: 1 Oktober 2026/.test(c1) && /Diunggah pada: 9 Agustus 2026/.test(c2), 'tanggal tanpa nol di depan (1 Oktober, 9 Agustus)');

// Kartu 1: 3 file + cover: cover SETELAH head, dua tombol di dalam cover, Unduh = button dengan flag.
check(c1.indexOf('<div class="wptg-doc-cover">') > c1.indexOf('wptg-doc-title'), 'kartu 1: cover setelah judul (di bawah, bukan di samping)');
const cover1 = c1.slice(c1.indexOf('<div class="wptg-doc-cover">'));
check(/wptg-doc-cover-img/.test(cover1) && cover1.indexOf('wptg-doc-card-actions') > cover1.indexOf('<img'), 'kartu 1: tombol ada DI DALAM cover setelah <img> (overlay)');
check(cover1.indexOf('wptg-doc-btn--view') < cover1.indexOf('wptg-doc-btn--download'), 'kartu 1: urutan Lihat lalu Unduh');
check(/<button[^>]*wptg-doc-btn--view[^>]*type="button"/.test(c1), 'kartu 1: Lihat = <button type="button">');
check(/<button[^>]*wptg-doc-btn--download[^>]*data-wptg-show-download="1"/.test(c1), 'kartu 1 (banyak file): Unduh = <button> dengan data-wptg-show-download="1"');
check(!/<a /.test(c1), 'kartu 1 (banyak file): tidak ada <a>, hanya <button>');

// Kartu 2: 1 file PDF dengan preview: Unduh = <a download>.
check(/<a class="wptg-doc-btn wptg-doc-btn--download" href="http:\/\/example\.test\/uploads\/paspor\.pdf" download rel="noopener"/.test(c2), 'kartu 2 (1 file): Unduh = <a href download rel=noopener>');
check(!/data-wptg-show-download/.test(c2.replace(/<article [^>]*>/, '')), 'kartu 2: Unduh tidak membawa flag popup');
check(/wptg-doc-cover-img/.test(c2), 'kartu 2: cover dari preview PDF');

// Kartu 3: PDF tanpa preview -> TIDAK ada cover (guard src .pdf), tombol jadi baris sendiri di bawah head.
check(!/wptg-doc-cover/.test(c3), 'kartu 3: tidak ada blok cover (PDF tanpa preview tidak jadi <img src=.pdf>)');
check(/<\/div>\s*<div class="wptg-doc-card-actions">/.test(c3), 'kartu 3: tombol = anak langsung kartu, setelah head');

// Kartu 4: tanpa file -> tanpa tombol, tetap ada cover dari Featured Image.
check(!/wptg-doc-btn/.test(c4) && !/wptg-doc-card-actions/.test(c4), 'kartu 4 (tanpa file): tidak ada tombol');
check(/wptg-doc-cover-img/.test(c4), 'kartu 4: cover dari Featured Image tetap tampil');

// Ikon: satu baris tanpa spasi (harus :empty bila ikon kustom), SVG bawaan ada.
check(/<span class="wptg-doc-icon" aria-hidden="true"><svg class="wptg-doc-icon-svg"/.test(c1), 'ikon bawaan: span satu baris berisi SVG');
check(/<svg class="wptg-doc-btn-svg"/.test(c1), 'ikon tombol (eye/download) ada');

out.push(problems === 0 ? 'NO PROBLEMS FOUND' : problems + ' PROBLEM(S) FOUND');
fs.writeFileSync(path.join(__dirname, 'check-markup.out'), out.join('\n') + '\n');
