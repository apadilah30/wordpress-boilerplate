/**
 * Taxonomy Grid - Media Popup.
 *
 * One click listener on `document` for every element that has a
 * `data-wptg-media="<post_id>"` attribute. The attribute is written by:
 *   - Taxonomy Grid (Sections) when Item Click Behavior = "Media Popup" or
 *     "Document Preview" (since 4.5.0, same listener, no second dispatcher).
 *     A document card puts it on the <article> (mouse click anywhere on the
 *     card) AND on its "Lihat" / "Unduh" buttons (keyboard). The "Unduh" of a
 *     one-file card is an <a download> and is skipped by the click handler.
 *   - Content Grid when Link Mode = "Media Popup".
 *
 * Because the listener lives on `document`, cards that are added later
 * (load more, search, change of term) work without any extra binding. The
 * `WptgMediaPopupReady` flag stops a second copy of this file from binding
 * twice.
 *
 * Flow: open the shared modal with a loading text -> ask the
 * `wptg_get_media_modal` AJAX action what to show -> render it (video,
 * photo, document files or the old post view) in the same modal (modal.js,
 * WptgModal). Document files are drawn by document-viewer.js.
 *
 * Without JavaScript, or when something is not ready, the card's own
 * `href` (the post permalink) is followed, so the link never dies.
 */
(function () {
    'use strict';

    if (window.WptgMediaPopupReady) {
        return;
    }
    window.WptgMediaPopupReady = true;

    var config = window.wptg_media || {};
    var i18n = config.i18n || {};

    // Counts requests so a slow answer for an old click cannot replace a newer popup.
    var latestRequest = 0;

    /**
     * Elementor's editor preview: clicks there are for selecting widgets,
     * a popup would only get in the way.
     */
    function isElementorEditor() {
        return document.body.classList.contains('elementor-editor-active');
    }

    function showMessage(text) {
        window.WptgModal.open('message', { text: text || i18n.error || 'Error' });
    }

    /**
     * Document popup: the server only sends the file list, document-viewer.js
     * draws it inside the frame. The viewer's cleanup (stop PDF.js, free the
     * document) is handed to the modal, which runs it on close or when
     * another post is opened. Returns false when the viewer script is missing.
     */
    function mountDocument(body, payload, options) {
        var frame = body.querySelector('.wptg-media-frame');
        if (!frame || !window.WptgDocumentViewer) {
            return false;
        }

        var viewer = window.WptgDocumentViewer.mount(frame, payload, { showDownload: !!options.showDownload });
        window.WptgModal.onClose(function () {
            viewer.destroy();
        });
        return true;
    }

    function render(payload, options) {
        // Neither video nor image: show the normal post view, as "Open Floating Modal" does.
        if (payload.kind === 'post') {
            window.WptgModal.open('post', { html: payload.html });
            return;
        }

        var body = window.WptgModal.open('media', payload);

        if (payload.kind === 'document') {
            if (!mountDocument(body, payload, options)) {
                showMessage('');
            }
            return;
        }

        // Start playing a self-hosted <video>. YouTube / Vimeo start through their own
        // autoplay parameter. A browser may refuse autoplay: the rejection is ignored so
        // the visitor can still press play on the controls.
        var video = body.querySelector('video');
        if (video) {
            var playing = video.play();
            if (playing && typeof playing.catch === 'function') {
                playing.catch(function () {});
            }
        }
    }

    /**
     * Where focus goes back when the popup closes. A mouse click on a document
     * card's cover or title lands on the <article>, which is not focusable (it
     * holds real buttons), so use the card's own "Lihat" button instead.
     */
    function focusTargetFor(trigger) {
        if (!trigger || typeof trigger.matches !== 'function' || trigger.matches('a[href], button, [tabindex]')) {
            return trigger;
        }
        return trigger.querySelector('button[data-wptg-media]') || trigger;
    }

    /**
     * Returns false when the popup cannot be used, so the caller keeps the
     * normal link behavior instead of swallowing the click.
     */
    function openPopup(postId, opener) {
        if (!window.WptgModal || !config.ajax_url) {
            return false;
        }

        latestRequest += 1;
        var requestId = latestRequest;

        // Only Taxonomy Grid document cards set data-wptg-show-download="1": on the card
        // (setting Show Download Button (popup)) or on the clicked "Unduh" button of a
        // card with many files. closest() covers both; other triggers never have it.
        var renderOptions = {
            showDownload: !!opener && typeof opener.closest === 'function' && !!opener.closest('[data-wptg-show-download="1"]'),
        };

        window.WptgModal.open('loading', { text: i18n.loading || 'Loading...' }, { opener: focusTargetFor(opener) });

        fetch(config.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wptg_get_media_modal',
                nonce: config.nonce || '',
                post_id: String(postId),
            }),
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                // Closed in the meantime, or another card was clicked: drop this answer.
                if (requestId !== latestRequest || !window.WptgModal.isOpen()) {
                    return;
                }

                if (!data || !data.success || !data.data) {
                    // A server message is neutral text, shown with textContent only.
                    showMessage(data && data.data && data.data.message ? data.data.message : '');
                    return;
                }

                render(data.data, renderOptions);
            })
            .catch(function (error) {
                if (requestId !== latestRequest || !window.WptgModal.isOpen()) {
                    return;
                }
                showMessage('');
                console.error('Taxonomy Grid Media Popup Error:', error);
            });

        return true;
    }

    document.addEventListener('click', function (event) {
        // Leave ctrl/cmd/shift/middle clicks alone: "open in new tab" must keep working.
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        // The "Unduh" pill of a document card with ONE file is a real <a download>
        // inside the clickable card: let the browser download, do not open the popup.
        if (event.target.closest && event.target.closest('a[download]')) {
            return;
        }

        var trigger = event.target.closest ? event.target.closest('[data-wptg-media]') : null;
        if (!trigger || isElementorEditor()) {
            return;
        }

        var postId = parseInt(trigger.getAttribute('data-wptg-media'), 10);
        if (!postId) {
            return;
        }

        if (openPopup(postId, trigger)) {
            event.preventDefault();
        }
    });

    // Most Sections cards are <article role="button" tabindex="0">, not real buttons,
    // so Enter and Space must be handled here. (The document card is not one of them:
    // its "Lihat" is a real <button>, which clicks by itself.) Real links (<a>) already click on Enter.
    document.addEventListener('keydown', function (event) {
        if ((event.key !== 'Enter' && event.key !== ' ') || event.repeat) {
            return;
        }

        var trigger = event.target;
        if (!trigger || !trigger.hasAttribute || !trigger.hasAttribute('data-wptg-media') || trigger.getAttribute('role') !== 'button') {
            return;
        }

        var postId = parseInt(trigger.getAttribute('data-wptg-media'), 10);
        if (!postId || isElementorEditor()) {
            return;
        }

        if (openPopup(postId, trigger)) {
            event.preventDefault();
        }
    });
})();
