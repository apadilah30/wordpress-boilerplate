// Helper: lihat isi paket pdfjs-dist hasil fetch-pdfjs.js (ukuran file + browserslist + header versi).
// Pakai: node .agents/tasks/documents/inspect-pdfjs.js  -> hasil di inspect-pdfjs.out
'use strict';

const fs = require('fs');
const path = require('path');

const pkg = path.join(__dirname, 'tmp', 'package');
const out = [];

function list(dir, depth) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (depth < 2) list(full, depth + 1);
            continue;
        }
        out.push(path.relative(pkg, full) + '  ' + fs.statSync(full).size);
    }
}

list(pkg, 0);

const json = JSON.parse(fs.readFileSync(path.join(pkg, 'package.json'), 'utf8'));
out.push('---- package.json');
out.push(JSON.stringify({ version: json.version, license: json.license, browserslist: json.browserslist, main: json.main, module: json.module, exports: json.exports }, null, 1));

fs.writeFileSync(path.join(__dirname, 'inspect-pdfjs.out'), out.join('\n') + '\n');
