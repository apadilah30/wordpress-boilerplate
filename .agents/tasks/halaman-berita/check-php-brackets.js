// Kasar: cek keseimbangan { } ( ) [ ] di file PHP (abaikan string, komentar, dan HTML di luar <?php ... ?>).
// Bukan pengganti `php -l` (php tidak ada di host). Pakai: node check-php-brackets.js <file.php> ...
const fs = require('fs');

function check(path) {
    const s = fs.readFileSync(path, 'utf8');
    const open = { '{': 0, '(': 0, '[': 0 };
    const closeToOpen = { '}': '{', ')': '(', ']': '[' };
    const errors = [];
    let i = 0;
    let inPhp = false;
    const lineNo = () => s.slice(0, i).split('\n').length;

    while (i < s.length) {
        if (!inPhp) {
            const next = s.indexOf('<?php', i);
            if (next < 0) break;
            i = next + 5;
            inPhp = true;
            continue;
        }
        const c = s[i];
        const n = s[i + 1];
        if (c === '?' && n === '>') { inPhp = false; i += 2; continue; }
        if ((c === '/' && n === '/') || c === '#') {
            while (i < s.length && s[i] !== '\n' && !(s[i] === '?' && s[i + 1] === '>')) i++;
            continue;
        }
        if (c === '/' && n === '*') { i = s.indexOf('*/', i + 2) + 2; continue; }
        if (c === "'" || c === '"') {
            i++;
            while (i < s.length && s[i] !== c) { if (s[i] === '\\') i++; i++; }
            i++;
            continue;
        }
        if (open[c] !== undefined) open[c]++;
        if (closeToOpen[c]) {
            open[closeToOpen[c]]--;
            if (open[closeToOpen[c]] < 0) { errors.push('kelebihan ' + c + ' di baris ' + lineNo()); open[closeToOpen[c]] = 0; }
        }
        i++;
    }
    const bom = fs.readFileSync(path).slice(0, 3).equals(Buffer.from([0xef, 0xbb, 0xbf]));
    console.log(path.split(/[\\/]/).pop(), JSON.stringify(open), 'BOM=' + bom, errors.join('; '));
}

process.argv.slice(2).forEach(check);
