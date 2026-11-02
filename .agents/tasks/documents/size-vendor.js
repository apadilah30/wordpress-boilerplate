// Helper: total ukuran folder vendor/pdfjs per sub-folder. Hasil di size-vendor.out
'use strict';
const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..', '..', '..', 'plugins-custom', 'wp-taxonomy-grid', 'assets', 'vendor', 'pdfjs');
const out = [];
let total = 0;

function walk(dir, bucket) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            walk(full, entry.name);
            continue;
        }
        const size = fs.statSync(full).size;
        total += size;
        out.push(path.relative(root, full) + '  ' + size);
        bucket = bucket || '';
    }
}

walk(root, '');
out.push('TOTAL ' + total + ' bytes (' + (total / 1048576).toFixed(2) + ' MiB)');
fs.writeFileSync(path.join(__dirname, 'size-vendor.out'), out.join('\n') + '\n');
