// Helper: ambil rilis stabil terbaru pdfjs-dist dari npm, ekstrak hanya yang dibutuhkan, tulis log.
// Pakai: node .agents/tasks/documents/fetch-pdfjs.js
// Hasil log: .agents/tasks/documents/fetch-pdfjs.out (shell Windows kadang tidak menampilkan stdout).
// Folder sementara: .agents/tasks/documents/tmp (di-ignore oleh git? cek; boleh dihapus setelah selesai).
'use strict';

const fs = require('fs');
const path = require('path');
const cp = require('child_process');

const here = __dirname;
const tmp = path.join(here, 'tmp');
const logFile = path.join(here, 'fetch-pdfjs.out');
const log = [];

function say(text) {
    log.push(text);
    fs.writeFileSync(logFile, log.join('\n') + '\n');
}

function run(command, cwd) {
    say('$ ' + command);
    try {
        const out = cp.execSync(command, { cwd, stdio: 'pipe', encoding: 'utf8', timeout: 240000, shell: true });
        say(out.trim());
        return out;
    } catch (error) {
        say('FAILED: ' + (error.stderr || error.message));
        return null;
    }
}

fs.mkdirSync(tmp, { recursive: true });

const meta = run('npm view pdfjs-dist version license dist-tags engines --json', tmp);
if (meta === null) {
    say('RESULT: npm/internet tidak tersedia');
    process.exit(1);
}

const packed = run('npm pack pdfjs-dist --silent', tmp);
if (packed === null) {
    say('RESULT: npm pack gagal');
    process.exit(1);
}

const tarball = packed.trim().split(/\r?\n/).pop();
say('tarball: ' + tarball);
run('tar -xf "' + tarball + '"', tmp);
say('RESULT: ok, ekstrak ada di ' + path.join(tmp, 'package'));
