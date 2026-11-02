/**
 * Extra static checks for the Empty State (skeleton) change:
 *   node .agents/tasks/skeleton/check-skeleton.js
 * - PHP alternative syntax balance (if/endif, foreach/endforeach)
 * - leftover old "empty" texts
 * - version 4.4.0 everywhere
 * Complements media-popup/check-files.js. Does NOT replace `php -l`.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const base = path.resolve(__dirname, '..', '..', '..', 'plugins-custom', 'wp-taxonomy-grid') + path.sep;
let problems = 0;

for (const file of ['wp-taxonomy-grid.php', 'includes/class-elementor-widget.php']) {
    const text = fs.readFileSync(base + file, 'utf8');
    const count = (re) => (text.match(re) || []).length;
    const ifs = count(/(?<!else)\bif\s*\([^\n]*\)\s*:\s*(\?>|\r?\n)/g);
    const endifs = count(/\bendif;/g);
    const fors = count(/\bforeach\s*\([^\n]*\)\s*:\s*(\?>|\r?\n)/g);
    const endfors = count(/\bendforeach;/g);
    console.log(file, 'if/endif', ifs, endifs, 'foreach/endforeach', fors, endfors, 'CRLF lines', count(/\r\n/g));
    if (ifs !== endifs || fors !== endfors) {
        problems += 1;
        console.log('PROBLEM: alternative syntax unbalanced in ' + file);
    }
}

const oldTexts = ['No valid sections', 'Belum ada konten', 'wptg-placeholder', 'no results', 'Tidak ditemukan', 'No items', 'Error: ', 'wptg-empty', 'wptg-error'];
for (const file of ['wp-taxonomy-grid.php', 'includes/class-elementor-widget.php', 'assets/js/taxonomy-grid.js', 'assets/js/modal.js', 'assets/js/media-popup.js', 'includes/media-popup.php', 'assets/css/taxonomy-grid.css']) {
    fs.readFileSync(base + file, 'utf8').split('\n').forEach((line, i) => {
        oldTexts.forEach((needle) => {
            if (line.includes(needle)) console.log('LEFTOVER ' + file + ':' + (i + 1) + ' [' + needle + '] ' + line.trim().slice(0, 100));
        });
    });
}

const versions = {
    'wp-taxonomy-grid.php': [/Version:\s+4\.4\.0/, /WPTG_VERSION', '4\.4\.0'/],
    'composer.json': [/"version": "4\.4\.0"/],
    'readme.txt': [/Stable tag: 4\.4\.0/, /= 4\.4\.0 =/],
};
for (const file of Object.keys(versions)) {
    const text = fs.readFileSync(base + file, 'utf8');
    versions[file].forEach((re) => {
        if (!re.test(text)) {
            problems += 1;
            console.log('PROBLEM: version 4.4.0 missing in ' + file + ' (' + re + ')');
        }
    });
}

// New control ids must exist exactly once.
const widget = fs.readFileSync(base + 'includes/class-elementor-widget.php', 'utf8');
for (const id of ['wptg_section_style_skeleton', 'wptg_skeleton_color', 'wptg_skeleton_shine', 'wptg_skeleton_radius']) {
    const n = (widget.match(new RegExp("'" + id + "'", 'g')) || []).length;
    if (n !== 1) {
        problems += 1;
        console.log('PROBLEM: control id ' + id + ' appears ' + n + ' times');
    }
}

console.log(problems === 0 ? 'NO PROBLEMS FOUND' : problems + ' PROBLEM(S)');
