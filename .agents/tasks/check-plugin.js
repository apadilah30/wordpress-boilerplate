// Helper: cek BOM + sintaks JS untuk satu atau lebih folder plugin.
// Pakai: node .agents/tasks/check-plugin.js wp-taxonomy-grid [wp-content-grid ...]
// Hasil ditulis ke .agents/tasks/check-plugin.out (shell Windows kadang tidak menampilkan stdout).
const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const root = path.resolve(__dirname, '..', '..', 'plugins-custom');
const lines = [];
let problems = 0;
let files = 0;

function walk(dir) {
    for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
        const full = path.join(dir, entry.name);
        if (entry.isDirectory()) {
            if (entry.name !== '.agents') walk(full);
            continue;
        }
        files++;
        const b = fs.readFileSync(full);
        if (b[0] === 0xef && b[1] === 0xbb && b[2] === 0xbf) {
            lines.push('BOM  ' + full);
            problems++;
        }
        if (/\.(js|mjs)$/.test(full) && !full.includes(path.sep + 'vendor' + path.sep)) {
            try {
                cp.execSync('node --check "' + full + '"', { stdio: 'pipe' });
            } catch (e) {
                lines.push('JS FAIL  ' + full);
                problems++;
            }
        }
    }
}

for (const slug of process.argv.slice(2)) {
    walk(path.join(root, slug));
}
lines.push('RESULT files=' + files + ' problems=' + problems);
fs.writeFileSync(path.join(__dirname, 'check-plugin.out'), lines.join('\n') + '\n');
