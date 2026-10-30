/**
 * Taxonomy Grid - Reusable modal/lightbox component.
 *
 * Exposes window.WptgModal = { open(type, payload), close() }.
 *
 * Supports 3 content types via a documented switch in open():
 *   - 'image' — payload = { src, title, date }. Builds an <img> + caption
 *               via document.createElement/setAttribute/textContent (never
 *               innerHTML), so attacker-controlled text can never break out
 *               of an attribute/tag.
 *   - 'post'  — payload = { html }. `html` is the already-escaped, trusted
 *               output of wptg_render_single_post_detail() fetched via the
 *               existing wptg_get_single_post AJAX action (same trust
 *               boundary taxonomy-grid.js's loadSinglePost() already relies
 *               on) — assigned via innerHTML, exactly like loadSinglePost().
 *   - 'video' — payload = { src, poster }. Builds a <video controls
 *               playsinline> element via setAttribute. No other branch
 *               exists today (an embeddable-URL/iframe case is out of scope).
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
    var keydownHandler = null;
    var backdropClickHandler = null;

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
        closeEl.setAttribute('aria-label', 'Close');
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

        if (type === 'video') {
            var video = document.createElement('video');
            video.setAttribute('controls', '');
            video.setAttribute('playsinline', '');
            video.setAttribute('src', payload.src || '');
            if (payload.poster) {
                video.setAttribute('poster', payload.poster);
            }
            bodyEl.appendChild(video);
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

    function open(type, payload) {
        buildModal();
        populateBody(type, payload || {});

        modalEl.removeAttribute('hidden');

        keydownHandler = onKeydown;
        backdropClickHandler = onBackdropClick;
        document.addEventListener('keydown', keydownHandler);
        backdropEl.addEventListener('click', backdropClickHandler);
    }

    function close() {
        if (!modalEl) {
            return;
        }

        modalEl.setAttribute('hidden', '');
        // Clear the body so a stray <video> doesn't keep playing off-screen.
        bodyEl.innerHTML = '';

        if (keydownHandler) {
            document.removeEventListener('keydown', keydownHandler);
            keydownHandler = null;
        }
        if (backdropClickHandler) {
            backdropEl.removeEventListener('click', backdropClickHandler);
            backdropClickHandler = null;
        }
    }

    window.WptgModal = {
        open: open,
        close: close,
    };
})();
