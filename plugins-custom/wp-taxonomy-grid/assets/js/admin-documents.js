/**
 * Taxonomy Grid - "Document Files" meta box (admin only).
 *
 * Loaded only on the edit screen of a document post type (see
 * wptg_enqueue_document_admin_assets() in includes/class-document-meta-box.php).
 *
 * What it does:
 *   - "Tambah file" opens the WordPress media modal (several files at once,
 *     only PDF / Word / JPG / PNG / WebP).
 *   - Each picked file becomes a row. Rows can be dragged to change the order
 *     (jquery-ui-sortable, bundled with WordPress), labelled, or removed.
 *   - After every change the list is written as JSON into the hidden field
 *     `wptg_document_files`. PHP validates that JSON again when saving, so
 *     nothing here is trusted.
 *
 * New rows are clones of the <template> that PHP prints, so the row markup
 * lives in PHP only (wptg_render_document_admin_row()). Texts are set with
 * textContent, never innerHTML.
 */
(function ($) {
    'use strict';

    var config = window.wptgDocuments || {};
    var i18n = config.i18n || {};
    var allowedMimes = config.mimes || [];
    var kinds = config.kinds || {};

    var box = document.querySelector('.wptg-doc-box');
    if (!box || !window.wp || !window.wp.media) {
        return;
    }

    var list = box.querySelector('.wptg-doc-rows');
    var template = box.querySelector('.wptg-doc-row-template');
    var emptyNote = box.querySelector('.wptg-doc-empty');
    var addButton = box.querySelector('.wptg-doc-add');
    var dataField = box.querySelector('.wptg-doc-data');
    var maxFiles = parseInt(box.getAttribute('data-max'), 10) || 50;
    var frame = null;

    /** Write the list (order, ids, labels) into the hidden field. */
    function sync() {
        var rows = list.querySelectorAll('.wptg-doc-row');
        var files = [];

        rows.forEach(function (row) {
            files.push({
                id: parseInt(row.getAttribute('data-id'), 10) || 0,
                label: row.querySelector('.wptg-doc-label').value.trim(),
            });
        });

        dataField.value = JSON.stringify(files);
        emptyNote.hidden = rows.length > 0;
    }

    function hasFile(id) {
        return !!list.querySelector('.wptg-doc-row[data-id="' + id + '"]');
    }

    /** Make a row for one attachment object from the media modal. */
    function addAttachment(attachment) {
        if (allowedMimes.indexOf(attachment.mime) === -1 || hasFile(attachment.id)) {
            return;
        }

        if (list.children.length >= maxFiles) {
            window.alert(i18n.tooMany || 'Too many files.');
            return;
        }

        var row = template.content.firstElementChild.cloneNode(true);
        var title = attachment.title || attachment.filename || '';
        var extension = String(attachment.filename || '').split('.').pop().toUpperCase();

        row.setAttribute('data-id', String(attachment.id));

        var badge = row.querySelector('.wptg-doc-ext');
        badge.className = 'wptg-doc-ext wptg-doc-ext--' + (kinds[attachment.mime] || 'other');
        badge.textContent = extension;

        row.querySelector('.wptg-doc-title').textContent = title;
        row.querySelector('.wptg-doc-size').textContent = attachment.filesizeHumanReadable || '';
        row.querySelector('.wptg-doc-label').setAttribute('placeholder', title);

        list.appendChild(row);
    }

    function openMediaModal() {
        // One frame for the page: it keeps its own state and is cheap to reopen.
        if (!frame) {
            frame = window.wp.media({
                title: i18n.modalTitle || 'Select files',
                button: { text: i18n.modalButton || 'Add files' },
                multiple: true,
                library: { type: allowedMimes },
            });

            frame.on('select', function () {
                frame.state().get('selection').each(function (attachment) {
                    addAttachment(attachment.toJSON());
                });
                sync();
            });
        }

        frame.open();
    }

    addButton.addEventListener('click', openMediaModal);

    list.addEventListener('input', function (event) {
        if (event.target.classList.contains('wptg-doc-label')) {
            sync();
        }
    });

    list.addEventListener('click', function (event) {
        var remove = event.target.closest('.wptg-doc-remove');
        if (!remove) {
            return;
        }
        remove.closest('.wptg-doc-row').remove();
        sync();
    });

    $(list).sortable({
        handle: '.wptg-doc-handle',
        axis: 'y',
        forcePlaceholderSize: true,
        placeholder: 'wptg-doc-row wptg-doc-placeholder',
        update: sync,
    });

    sync();
})(window.jQuery);
