/**
 * Taxonomy Grid - Document viewer (since 4.5.0).
 *
 * Draws the files of a "document" popup inside the Media Popup. Exposes
 * window.WptgDocumentViewer = { mount(container, payload, options) }, which
 * media-popup.js calls when the server answers with kind = 'document'.
 *
 * payload.files = [{ label, url, kind, mime, ext, download_url, office_preview, embed_url }]
 * options       = { showDownload: bool }
 *
 * What is shown, by file kind:
 *   - image  : a plain <img>.
 *   - pdf    : PDF.js (assets/vendor/pdfjs/). It is NOT loaded with the page:
 *              the first PDF that is opened downloads it (dynamic import).
 *              Every page becomes a <canvas>, drawn only when it comes near
 *              the visible area (IntersectionObserver), and only a few canvases
 *              are kept, so a 200-page PDF does not freeze or crash a phone.
 *   - office : Microsoft's viewer in an <iframe>, only when the server said
 *              `office_preview` (the file is on a public site). Otherwise a
 *              short message plus an "open file" link.
 *   - other  : the same message + link.
 *
 * With more than one file a row of buttons (chips) switches between them.
 * mount() returns { destroy() }: it cancels page rendering, frees the PDF and
 * empties the DOM. media-popup.js registers it with WptgModal.onClose(), so it
 * runs when the popup closes or shows another post.
 *
 * All text from the server is set with textContent. Links and sources are
 * used only when they start with http:// or https://.
 */
(function () {
    'use strict';

    if (window.WptgDocumentViewer) {
        return;
    }

    // At most this many page canvases stay in memory; the oldest invisible one is dropped.
    var MAX_RENDERED_PAGES = 8;
    // A phone with a 3x screen would need 9x the pixels for a barely visible gain.
    var MAX_PIXEL_RATIO = 2;
    // The only iframe address the Word preview may use (the server builds it, this is a second check).
    var OFFICE_EMBED_PREFIX = 'https://view.officeapps.live.com/op/embed.aspx?src=';

    var pdfjsPromise = null;

    function getConfig() {
        return window.wptg_media || {};
    }

    function text(key, fallback) {
        var i18n = getConfig().i18n || {};
        return i18n[key] || fallback;
    }

    function noop() {}

    /** Run something that may throw or return a rejected promise during cleanup. */
    function safely(callback) {
        try {
            var result = callback();
            if (result && typeof result.catch === 'function') {
                result.catch(noop);
            }
        } catch (error) {
            // Cleanup must never block closing the popup.
        }
    }

    function el(tag, className, content) {
        var node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (content) {
            node.textContent = content;
        }
        return node;
    }

    function isHttpUrl(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url);
    }

    function actionLink(label, href, asDownload) {
        var link = el('a', 'wptg-doc-action', label);
        link.href = href;
        if (asDownload) {
            link.setAttribute('download', '');
        } else {
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
        }
        return link;
    }

    function loadingBlock() {
        var block = el('div', 'wptg-doc-loading');
        block.setAttribute('role', 'status');
        block.appendChild(el('span', 'wptg-doc-spinner'));
        block.appendChild(el('span', 'wptg-doc-loading-text', text('pdfLoading', 'Memuat dokumen...')));
        return block;
    }

    /** Polite message with an "open file" link, used for every failure and for unsupported files. */
    function showFallback(stage, message, file) {
        stage.textContent = '';

        var box = el('div', 'wptg-doc-fallback');
        box.setAttribute('role', 'status');
        box.appendChild(el('p', 'wptg-doc-fallback-text', message));
        if (isHttpUrl(file.url)) {
            box.appendChild(actionLink(text('openFile', 'Buka file'), file.url, false));
        }
        stage.appendChild(box);
    }

    /* ---------------------------------------------------------------------
     * Image
     * ------------------------------------------------------------------- */

    function mountImage(stage, file) {
        stage.textContent = '';

        if (!isHttpUrl(file.url)) {
            showFallback(stage, text('otherFile', 'File ini tidak bisa dipratinjau di sini.'), file);
            return { destroy: noop };
        }

        var image = el('img', 'wptg-doc-image');
        image.alt = file.label || '';
        image.decoding = 'async';
        image.addEventListener('error', function () {
            showFallback(stage, text('otherFile', 'File ini tidak bisa dipratinjau di sini.'), file);
        });
        image.src = file.url;
        stage.appendChild(image);

        return {
            destroy: function () {
                image.removeAttribute('src');
                stage.textContent = '';
            },
        };
    }

    /* ---------------------------------------------------------------------
     * Word (doc / docx)
     * ------------------------------------------------------------------- */

    function mountOffice(stage, file) {
        stage.textContent = '';

        var embedUrl = typeof file.embed_url === 'string' ? file.embed_url : '';
        if (!file.office_preview || embedUrl.indexOf(OFFICE_EMBED_PREFIX) !== 0) {
            showFallback(stage, text('officeUnavailable', 'Pratinjau Word hanya tersedia di situs yang bisa diakses publik.'), file);
            return { destroy: noop };
        }

        var loading = loadingBlock();
        var frame = el('iframe', 'wptg-doc-office');
        frame.title = file.label || '';
        frame.setAttribute('referrerpolicy', 'no-referrer');
        frame.addEventListener('load', function () {
            loading.remove();
        });
        frame.src = embedUrl;

        stage.appendChild(loading);
        stage.appendChild(frame);

        return {
            destroy: function () {
                frame.removeAttribute('src');
                stage.textContent = '';
            },
        };
    }

    /* ---------------------------------------------------------------------
     * PDF (PDF.js)
     * ------------------------------------------------------------------- */

    /**
     * Download PDF.js once and reuse it. A failure (blocked by CSP, wrong
     * MIME type for .mjs, 404) is not cached, so a later click can retry.
     */
    function loadPdfJs() {
        if (pdfjsPromise) {
            return pdfjsPromise;
        }

        var settings = getConfig().pdfjs || {};
        if (!settings.lib || !settings.worker) {
            return Promise.reject(new Error('PDF.js is not configured.'));
        }

        pdfjsPromise = import(settings.lib)
            .then(function (library) {
                library.GlobalWorkerOptions.workerSrc = settings.worker;
                return library;
            })
            .catch(function (error) {
                pdfjsPromise = null;
                throw error;
            });

        return pdfjsPromise;
    }

    function pdfErrorMessage(error) {
        var name = error && error.name ? error.name : '';

        if (name === 'PasswordException') {
            return text('pdfPassword', 'Dokumen ini dilindungi password, jadi tidak bisa ditampilkan di sini.');
        }
        if (name === 'InvalidPDFException') {
            return text('pdfCorrupt', 'File dokumen ini rusak atau bukan PDF yang valid.');
        }
        return text('pdfError', 'Dokumen ini tidak bisa ditampilkan di sini.');
    }

    /** Throw away the oldest page canvas that is not on screen. */
    function evictOldPages(state) {
        while (state.renderedOrder.length > MAX_RENDERED_PAGES) {
            var evictIndex = -1;
            for (var i = 0; i < state.renderedOrder.length; i++) {
                if (!state.visible[state.renderedOrder[i]]) {
                    evictIndex = i;
                    break;
                }
            }
            if (evictIndex === -1) {
                return; // everything left is on screen
            }

            var pageNumber = state.renderedOrder.splice(evictIndex, 1)[0];
            var container = state.pageEls[pageNumber - 1];
            container.querySelectorAll('canvas').forEach(function (canvas) {
                canvas.width = 0; // releases the canvas memory right away
                canvas.height = 0;
            });
            container.textContent = '';
            container.classList.remove('is-rendered');
            state.done[pageNumber] = false;
        }
    }

    function renderPdfPage(state, stage, pageNumber) {
        // Scrolled past while waiting in the queue: it is queued again if it comes back.
        if (state.destroyed || state.done[pageNumber] || !state.visible[pageNumber]) {
            state.queued[pageNumber] = false;
            return Promise.resolve();
        }

        return state.pdf.getPage(pageNumber).then(function (page) {
            if (state.destroyed) {
                return null;
            }

            var container = state.pageEls[pageNumber - 1];
            var natural = page.getViewport({ scale: 1 });
            var width = container.clientWidth || stage.clientWidth || natural.width;
            var pixelRatio = Math.min(window.devicePixelRatio || 1, MAX_PIXEL_RATIO);
            var viewport = page.getViewport({ scale: (width / natural.width) * pixelRatio });

            var canvas = document.createElement('canvas');
            canvas.className = 'wptg-doc-canvas';
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', text('page', 'Halaman %d').replace('%d', String(pageNumber)));

            var task = page.render({ canvas: canvas, viewport: viewport });
            state.tasks.push(task);

            return task.promise.then(function () {
                state.tasks.splice(state.tasks.indexOf(task), 1);
                if (state.destroyed) {
                    return;
                }

                // Real size of this page (pages may differ from page 1).
                container.style.aspectRatio = natural.width + ' / ' + natural.height;
                container.textContent = '';
                container.appendChild(canvas);
                container.classList.add('is-rendered');

                state.done[pageNumber] = true;
                state.renderedOrder.push(pageNumber);
                evictOldPages(state);
                page.cleanup();
            });
        }).catch(function (error) {
            // Closing the popup cancels the running page on purpose; that is not an error.
            if (!state.destroyed && !(error && error.name === 'RenderingCancelledException')) {
                console.error('Taxonomy Grid PDF page error:', error);
            }
        }).then(function () {
            state.queued[pageNumber] = false;
        });
    }

    /** Pages are drawn one after another, so the browser stays responsive. */
    function queuePdfPage(state, stage, pageNumber) {
        if (state.destroyed || state.done[pageNumber] || state.queued[pageNumber]) {
            return;
        }
        state.queued[pageNumber] = true;
        state.queue = state.queue.then(function () {
            return renderPdfPage(state, stage, pageNumber);
        });
    }

    function buildPdfPages(state, stage) {
        return state.pdf.getPage(1).then(function (firstPage) {
            if (state.destroyed) {
                return;
            }

            var first = firstPage.getViewport({ scale: 1 });
            var list = el('div', 'wptg-doc-pages');

            stage.textContent = '';
            stage.appendChild(list);

            var observer = null;
            if (window.IntersectionObserver) {
                // Pages within 800px of the visible area get drawn before the visitor reaches them.
                observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var pageNumber = parseInt(entry.target.getAttribute('data-page'), 10);
                        state.visible[pageNumber] = entry.isIntersecting;
                        if (entry.isIntersecting) {
                            queuePdfPage(state, stage, pageNumber);
                        }
                    });
                }, { root: stage, rootMargin: '800px 0px' });
                state.observer = observer;
            }

            for (var pageNumber = 1; pageNumber <= state.pdf.numPages; pageNumber++) {
                var pageEl = el('div', 'wptg-doc-page');
                pageEl.setAttribute('data-page', String(pageNumber));
                // The first page's shape reserves the space until the real page is drawn.
                pageEl.style.aspectRatio = first.width + ' / ' + first.height;
                state.pageEls.push(pageEl);
                list.appendChild(pageEl);

                if (observer) {
                    observer.observe(pageEl);
                } else {
                    // Very old browser: no lazy drawing, but the queue still keeps it responsive.
                    state.visible[pageNumber] = true;
                    queuePdfPage(state, stage, pageNumber);
                }
            }
        });
    }

    function destroyPdf(state, stage) {
        state.destroyed = true;

        if (state.observer) {
            state.observer.disconnect();
            state.observer = null;
        }

        state.tasks.forEach(function (task) {
            safely(function () { task.cancel(); });
        });
        state.tasks = [];

        stage.querySelectorAll('canvas').forEach(function (canvas) {
            canvas.width = 0;
            canvas.height = 0;
        });

        // In PDF.js the loading task owns the document and its worker: destroying
        // it frees both (the PDFDocumentProxy itself has no destroy()). It also
        // works while the PDF is still downloading.
        if (state.loadingTask) {
            safely(function () { return state.loadingTask.destroy(); });
        }
        state.pdf = null;
        state.loadingTask = null;

        stage.textContent = '';
    }

    function mountPdf(stage, file) {
        var state = {
            destroyed: false,
            loadingTask: null,
            pdf: null,
            observer: null,
            tasks: [],
            queue: Promise.resolve(),
            queued: {},
            done: {},
            visible: {},
            pageEls: [],
            renderedOrder: [],
        };

        stage.textContent = '';
        stage.appendChild(loadingBlock());

        if (!isHttpUrl(file.url)) {
            showFallback(stage, text('pdfError', 'Dokumen ini tidak bisa ditampilkan di sini.'), file);
            return { destroy: noop };
        }

        loadPdfJs()
            .then(function (library) {
                if (state.destroyed) {
                    return null;
                }

                var settings = getConfig().pdfjs || {};
                state.loadingTask = library.getDocument({
                    url: file.url,
                    wasmUrl: settings.wasmUrl,
                    standardFontDataUrl: settings.fontsUrl,
                    iccUrl: settings.iccUrl,
                });
                return state.loadingTask.promise;
            })
            .then(function (pdf) {
                if (!pdf) {
                    return null;
                }
                if (state.destroyed) {
                    return null; // destroyPdf() already destroyed the loading task
                }

                state.pdf = pdf;
                return buildPdfPages(state, stage);
            })
            .catch(function (error) {
                if (state.destroyed) {
                    return;
                }
                console.error('Taxonomy Grid PDF error:', error);
                showFallback(stage, pdfErrorMessage(error), file);
            });

        return {
            destroy: function () {
                destroyPdf(state, stage);
            },
        };
    }

    /* ---------------------------------------------------------------------
     * Viewer shell: file chips + stage + optional download button
     * ------------------------------------------------------------------- */

    function mountFile(stage, file) {
        if (file.kind === 'image') {
            return mountImage(stage, file);
        }
        if (file.kind === 'pdf') {
            return mountPdf(stage, file);
        }
        if (file.kind === 'office') {
            return mountOffice(stage, file);
        }

        showFallback(stage, text('otherFile', 'File ini tidak bisa dipratinjau di sini.'), file);
        return { destroy: noop };
    }

    function mount(container, payload, options) {
        var files = payload && Array.isArray(payload.files) ? payload.files : [];
        var showDownload = !!(options && options.showDownload);

        container.textContent = '';

        var root = el('div', 'wptg-doc-viewer');
        var stage = el('div', 'wptg-doc-stage');
        stage.tabIndex = 0; // a scrollable area must be reachable with the keyboard
        stage.setAttribute('role', 'region');

        var actions = el('div', 'wptg-doc-actions');
        var chips = [];
        var current = null;

        if (files.length > 1) {
            var tabs = el('div', 'wptg-doc-tabs');
            tabs.setAttribute('role', 'group');
            tabs.setAttribute('aria-label', text('files', 'Daftar file'));

            files.forEach(function (file, index) {
                var chip = el('button', 'wptg-doc-chip', file.label || '');
                chip.type = 'button';
                chip.title = file.label || '';
                chip.addEventListener('click', function () {
                    select(index);
                });
                chips.push(chip);
                tabs.appendChild(chip);
            });

            root.appendChild(tabs);
        }

        root.appendChild(stage);
        root.appendChild(actions);
        container.appendChild(root);

        function select(index) {
            if (current) {
                current.destroy();
                current = null;
            }

            var file = files[index];

            chips.forEach(function (chip, chipIndex) {
                var isActive = chipIndex === index;
                chip.classList.toggle('is-active', isActive);
                chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });

            stage.setAttribute('aria-label', file.label || '');
            stage.scrollTop = 0;

            actions.textContent = '';
            if (showDownload && isHttpUrl(file.download_url)) {
                actions.appendChild(actionLink(text('download', 'Unduh'), file.download_url, true));
            }

            current = mountFile(stage, file);
        }

        if (files.length === 0) {
            showFallback(stage, text('otherFile', 'File ini tidak bisa dipratinjau di sini.'), { url: '' });
        } else {
            select(0);
        }

        return {
            destroy: function () {
                if (current) {
                    current.destroy();
                    current = null;
                }
                root.remove();
            },
        };
    }

    window.WptgDocumentViewer = {
        mount: mount,
    };
})();
