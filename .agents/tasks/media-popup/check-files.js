/**
 * Static checks for the Media Popup change, run with:
 *
 *   node .agents/tasks/media-popup/check-files.js
 *
 * There is no php on the host, so this only catches gross mistakes:
 *   - a BOM or a missing `declare(strict_types=1);` in PHP files,
 *   - unbalanced { } ( ) [ ] in PHP / JS (strings and comments are skipped),
 *   - unbalanced { } in CSS,
 *   - duplicate Elementor control ids inside one widget file.
 * It does NOT replace `php -l`. Lives in .agents/tasks, not in the plugin.
 */
'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..', '..', 'plugins-custom');
const files = {
    php: [
        'wp-taxonomy-grid/wp-taxonomy-grid.php',
        'wp-taxonomy-grid/includes/media-popup.php',
        'wp-taxonomy-grid/includes/class-elementor-widget.php',
        'wp-taxonomy-grid/includes/class-video-meta-box.php',
        'wp-content-grid/wp-content-grid.php',
        'wp-content-grid/includes/class-elementor-widget.php',
    ],
    js: [
        'wp-taxonomy-grid/assets/js/modal.js',
        'wp-taxonomy-grid/assets/js/taxonomy-grid.js',
        'wp-taxonomy-grid/assets/js/media-popup.js',
    ],
    css: [
        'wp-taxonomy-grid/assets/css/taxonomy-grid.css',
        'wp-content-grid/assets/css/content-grid.css',
    ],
    other: [
        'wp-taxonomy-grid/readme.txt',
        'wp-taxonomy-grid/composer.json',
        'wp-content-grid/readme.txt',
        'wp-content-grid/composer.json',
    ],
};

let problems = 0;
function fail(message) {
    problems += 1;
    console.error('PROBLEM: ' + message);
}

/** Remove comments and string contents so brackets inside them are not counted. */
function stripCode(source, kind) {
    let out = '';
    let i = 0;
    const n = source.length;
    while (i < n) {
        const c = source[i];
        const next = source[i + 1];

        if (c === '/' && next === '*') {
            const end = source.indexOf('*/', i + 2);
            i = end === -1 ? n : end + 2;
            continue;
        }
        if (c === '/' && next === '/' && !(kind === 'js' && source[i - 1] === ':')) {
            const end = source.indexOf('\n', i);
            i = end === -1 ? n : end;
            continue;
        }
        if (kind === 'php' && c === '#') {
            const end = source.indexOf('\n', i);
            i = end === -1 ? n : end;
            continue;
        }
        if (c === '"' || c === "'" || (kind === 'js' && c === '`')) {
            let j = i + 1;
            while (j < n && source[j] !== c) {
                j += source[j] === '\\' ? 2 : 1;
            }
            i = j + 1;
            continue;
        }
        out += c;
        i += 1;
    }
    return out;
}

function balance(text, pairs) {
    const result = {};
    for (const [open, close] of pairs) {
        const opens = text.split(open).length - 1;
        const closes = text.split(close).length - 1;
        result[open + close] = [opens, closes];
    }
    return result;
}

function checkBalance(label, text, pairs) {
    const counts = balance(text, pairs);
    for (const key of Object.keys(counts)) {
        const [opens, closes] = counts[key];
        if (opens !== closes) {
            fail(label + ' has unbalanced ' + key + ': ' + opens + ' open vs ' + closes + ' close');
        }
    }
}

for (const group of Object.keys(files)) {
    for (const relative of files[group]) {
        const full = path.join(root, relative);
        if (!fs.existsSync(full)) {
            fail('missing file ' + relative);
            continue;
        }

        const buffer = fs.readFileSync(full);
        const hasBom = buffer[0] === 0xef && buffer[1] === 0xbb && buffer[2] === 0xbf;
        if (hasBom) fail(relative + ' starts with a BOM');

        const text = buffer.toString('utf8');

        if (group === 'php') {
            if (!text.startsWith('<?php')) fail(relative + ' does not start with <?php');
            if (!/declare\(strict_types=1\);/.test(text)) fail(relative + ' has no declare(strict_types=1);');
            checkBalance(relative, stripCode(text, 'php'), [['{', '}'], ['(', ')'], ['[', ']']]);
        } else if (group === 'js') {
            checkBalance(relative, stripCode(text, 'js'), [['{', '}'], ['(', ')'], ['[', ']']]);
        } else if (group === 'css') {
            checkBalance(relative, text.replace(/\/\*[\s\S]*?\*\//g, ''), [['{', '}'], ['(', ')']]);
        }

        console.log((hasBom ? 'BOM  ' : 'ok   ') + relative + ' (' + buffer.length + ' bytes)');
    }
}

// Elementor control ids must be unique per widget class.
for (const relative of ['wp-taxonomy-grid/includes/class-elementor-widget.php', 'wp-content-grid/includes/class-elementor-widget.php']) {
    const text = fs.readFileSync(path.join(root, relative), 'utf8');
    const seen = new Map();
    const pattern = /(?:add_control|add_responsive_control|start_controls_section)\(\s*'([a-z0-9_]+)'/g;
    let match;
    while ((match = pattern.exec(text)) !== null) {
        seen.set(match[1], (seen.get(match[1]) || 0) + 1);
    }
    // Group controls and helper-built ids use a prefix, so only direct ids are checked here.
    for (const [id, count] of seen) {
        if (count > 1) fail(relative + ' registers control id "' + id + '" ' + count + ' times');
    }
}

console.log(problems === 0 ? 'NO PROBLEMS FOUND' : problems + ' PROBLEM(S) FOUND');
process.exitCode = problems === 0 ? 0 : 1;
