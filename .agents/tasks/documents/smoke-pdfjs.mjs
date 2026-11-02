// Smoke test: apakah file PDF.js di vendor plugin bisa di-import dan membaca PDF kecil (di Node, tanpa browser)?
// Hanya menguji nama API yang dipakai document-viewer.js (GlobalWorkerOptions, getDocument, numPages, getPage,
// getViewport, destroy). TIDAK menguji render canvas (butuh browser).
// Pakai: node .agents/tasks/documents/smoke-pdfjs.mjs  -> hasil di smoke-pdfjs.out
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const vendor = path.resolve(here, '..', '..', '..', 'plugins-custom', 'wp-taxonomy-grid', 'assets', 'vendor', 'pdfjs');
const out = [];

try {
    const lib = await import(pathToFileURL(path.join(vendor, 'pdf.min.mjs')).href);
    out.push('import ok; exports: getDocument=' + typeof lib.getDocument + ' GlobalWorkerOptions=' + typeof lib.GlobalWorkerOptions);

    lib.GlobalWorkerOptions.workerSrc = pathToFileURL(path.join(vendor, 'pdf.worker.min.mjs')).href;

    const body = [
        '%PDF-1.1',
        '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
        '2 0 obj<</Type/Pages/Kids[3 0 R 4 0 R]/Count 2>>endobj',
        '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 300]>>endobj',
        '4 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 400 300]>>endobj',
        'trailer<</Root 1 0 R/Size 5>>',
        '%%EOF',
    ].join('\n');

    const task = lib.getDocument({ data: new Uint8Array(Buffer.from(body, 'latin1')) });
    const pdf = await task.promise;
    out.push('numPages=' + pdf.numPages);

    const page = await pdf.getPage(2);
    const viewport = page.getViewport({ scale: 2 });
    out.push('page2 viewport=' + viewport.width + 'x' + viewport.height + ' (expected 800x600)');

    out.push('pdf.destroy is ' + typeof pdf.destroy + ' (the viewer must not rely on it)');
    await task.destroy();
    out.push('loadingTask.destroy ok');
    out.push('RESULT: ok');
} catch (error) {
    out.push('RESULT: FAILED ' + (error && error.stack ? error.stack : error));
}

fs.writeFileSync(path.join(here, 'smoke-pdfjs.out'), out.join('\n') + '\n');
process.exit(0);
