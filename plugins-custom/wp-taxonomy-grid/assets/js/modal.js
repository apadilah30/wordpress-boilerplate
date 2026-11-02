/**
 * Taxonomy Grid - Reusable modal/lightbox component.
 *
 * Exposes window.WptgModal = { open(type, payload, options), close(), isOpen() }.
 *
 * Supports these content types via a documented switch in populateBody():
 *   - 'image'   — payload = { src, title, date }. Builds an <img> + caption
 *                 via document.createElement/setAttribute/textContent (never
 *                 innerHTML), so attacker-controlled text can never break out
 *                 of an attribute/tag. Used by Item Click Behavior = "Open
 *                 Floating Modal" on a photo section.
 *   - 'post'    — payload = { html }. `html` is the already-escaped, trusted
 *                 output of wptg_render_single_post_detail() fetched via the
 *                 existing wptg_get_single_post AJAX action (same trust
 *                 boundary taxonomy-grid.js's loadSinglePost() already relies
 *                 on) — assigned via innerHTML, exactly like loadSinglePost().
 *   - 'media'   — payload = { kind: 'video'|'photo'|'document', title, date, html }.
 *                 The Media Popup (see media-popup.js). `html` is the player
 *                 or <img> markup, already run through wp_kses() on the
 *                 server. Title and date are set with textContent. For
 *                 kind 'document' `html` is empty: media-popup.js mounts the
 *                 file viewer (document-viewer.js) into .wptg-media-frame
 *                 and registers its cleanup with WptgModal.onClose().
 *   - 'loading' — payload = { text }. The same panel as 'media' with a
 *                 spinner, shown while media-popup.js waits for the server.
 *   - 'message' — payload = { text }. The same panel with a plain text (an
 *                 error), set with textContent.
 *
 * options (3rd argument of open): { opener } — the element to give focus back
 * to when the modal closes. Defaults to document.activeElement.
 *
 * The modal DOM is built lazily on first open() and appended to
 * document.body ONCE, so it is not duplicated per widget instance on a page
 * with multiple Content Navigator widgets.
 */
(function () {
    'use strict';

    var modalEl = null;
    var backdropEl = null;
    var panelEl = null;
    var closeEl = null;
    var bodyEl = null;
    var openerEl = null;
    // Cleanup of something mounted inside the body by another script (the document viewer).
    var closeHandler = null;

    function getCloseLabel() {
        var config = window.wptg_media;
        return config && config.i18n && config.i18n.close ? config.i18n.close : 'Close';
    }

    function buildModal() {
        if (modalEl) {
            return;
        }

        modalEl = document.createElement('div');
        modalEl.className = 'wptg-modal';
        modalEl.setAttribute('hidden', '');

        backdropEl = document.createElement('div');
        backdropEl.className = 'wptg-modal-backdrop';

        panelEl = document.createElement('div');
        panelEl.className = 'wptg-modal-panel';
        panelEl.setAttribute('role', 'dialog');
        panelEl.setAttribute('aria-modal', 'true');

        closeEl = document.createElement('button');
        closeEl.type = 'button';
        closeEl.className = 'wptg-modal-close';
        closeEl.innerHTML = '&times;';

        bodyEl = document.createElement('div');
        bodyEl.className = 'wptg-modal-body';

        panelEl.appendChild(closeEl);
        panelEl.appendChild(bodyEl);
        modalEl.appendChild(backdropEl);
        modalEl.appendChild(panelEl);
        document.body.appendChild(modalEl);

        closeEl.addEventListener('click', close);
    }

    function isOpen() {
        return !!modalEl && !modalEl.hasAttribute('hidden');
    }

    /**
     * The Media Popup look (gold close circle, cream panel) is switched on
     * by the .wptg-media-modal class. Every open() starts from a clean class
     * list, so a later 'post' or 'image' modal never keeps the media look.
     */
    function setVariant(variant) {
        modalEl.className = 'wptg-modal' + (variant ? ' wptg-media-modal wptg-media-modal--' + variant : '');
    }

    /**
     * Stop playback before the markup is thrown away. Removing an <iframe>
     * from the page stops it; a <video> is paused and its source released.
     */
    function stopMedia() {
        // Runs once: the document viewer stops PDF.js rendering and frees the document here.
        if (closeHandler) {
            var handler = closeHandler;
            closeHandler = null;
            try {
                handler();
            } catch (error) {
                console.error('Taxonomy Grid Modal cleanup error:', error);
            }
        }

        if (!bodyEl) {
            return;
        }

        bodyEl.querySelectorAll('video').forEach(function (video) {
            video.pause();
            video.removeAttribute('src');
            video.load();
        });
    }

    function buildMediaBody(payload) {
        var wrapper = document.createElement('div');
        wrapper.className = 'wptg-media';

        var frame = document.createElement('div');
        frame.className = 'wptg-media-frame';
        // Server-side wp_kses() output (see wptg_build_media_modal_payload()).
        frame.innerHTML = payload.html || '';

        var caption = document.createElement('div');
        caption.className = 'wptg-media-caption';

        var title = document.createElement('h3');
        title.className = 'wptg-media-title';
        title.textContent = payload.title || '';

        var date = document.createElement('p');
        date.className = 'wptg-media-date';
        date.textContent = payload.date || '';

        caption.appendChild(title);
        if (payload.date) {
            caption.appendChild(date);
        }

        wrapper.appendChild(frame);
        wrapper.appendChild(caption);
        return wrapper;
    }

    function populateBody(type, payload) {
        bodyEl.innerHTML = '';

        if (type === 'image') {
            var img = document.createElement('img');
            img.setAttribute('src', payload.src || '');
            img.setAttribute('alt', payload.title || '');

            var caption = document.createElement('div');
            caption.className = 'wptg-modal-caption';

            var title = document.createElement('span');
            title.className = 'wptg-modal-caption-title';
            title.textContent = payload.title || '';

            var date = document.createElement('span');
            date.className = 'wptg-modal-caption-date';
            date.textContent = payload.date || '';

            caption.appendChild(title);
            caption.appendChild(date);

            bodyEl.appendChild(img);
            bodyEl.appendChild(caption);
            return;
        }

        if (type === 'loading' || type === 'message') {
            var status = document.createElement('div');
            status.className = type === 'loading' ? 'wptg-media-loading' : 'wptg-media-message';
            status.setAttribute('role', type === 'loading' ? 'status' : 'alert');
            status.textContent = payload.text || '';
            bodyEl.appendChild(status);
            return;
        }

        if (type === 'media') {
            bodyEl.appendChild(buildMediaBody(payload));
            return;
        }

        // type === 'post' — payload.html is already-escaped, trusted markup
        // from wptg_render_single_post_detail() via wptg_get_single_post.
        bodyEl.innerHTML = payload.html || '';
    }

    function onKeydown(e) {
        if (e.key === 'Escape') {
            close();
        }
    }

    function onBackdropClick(e) {
        if (e.target === backdropEl) {
            close();
        }
    }

    /**
     * Opens the modal, or swaps its content when it is already open (media
     * popup: 'loading' first, then 'media'). Returns the body element so the
     * caller can reach what was inserted (media-popup.js starts the <video>).
     */
    function open(type, payload, options) {
        buildModal();
        payload = payload || {};

        var wasOpen = isOpen();
        if (!wasOpen) {
            openerEl = (options && options.opener) || document.activeElement;
        }

        stopMedia();

        if (type === 'loading' || type === 'message') {
            setVariant(type);
        } else if (type === 'media') {
            setVariant(payload.kind === 'video' || payload.kind === 'document' ? payload.kind : 'photo');
        } else {
            setVariant('');
        }

        populateBody(type, payload);

        closeEl.setAttribute('aria-label', getCloseLabel());
        panelEl.setAttribute('aria-label', payload.title || payload.text || '');

        modalEl.removeAttribute('hidden');

        // Adding the same function twice is ignored by the browser, so a
        // second open() while already open cannot double-bind.
        document.addEventListener('keydown', onKeydown);
        backdropEl.addEventListener('click', onBackdropClick);

        closeEl.focus();
        return bodyEl;
    }

    function close() {
        if (!isOpen()) {
            return;
        }

        // Stop sound first, then clear the body so no <video>/<iframe> keeps playing off-screen.
        stopMedia();
        bodyEl.innerHTML = '';
        modalEl.setAttribute('hidden', '');

        document.removeEventListener('keydown', onKeydown);
        backdropEl.removeEventListener('click', onBackdropClick);

        var opener = openerEl;
        openerEl = null;
        if (opener && document.contains(opener) && typeof opener.focus === 'function') {
            opener.focus();
        }
    }

    /** Register ONE function to run when the current content is replaced or the modal closes. */
    function onClose(handler) {
        closeHandler = typeof handler === 'function' ? handler : null;
    }

    window.WptgModal = {
        open: open,
        close: close,
        isOpen: isOpen,
        onClose: onClose,
    };
})();
