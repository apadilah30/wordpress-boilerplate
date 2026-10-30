/**
 * Taxonomy Grid - JavaScript
 *
 * Handles three display modes:
 *   - "detail":   sidebar accordion + search + AJAX single-post swap (legacy)
 *   - "grid":     category click + AJAX posts-grid swap (legacy)
 *   - "sections": N independent sidebar sections (flat list or taxonomy
 *                 accordion) sharing ONE content panel — the Content
 *                 Navigator. See initSectionsMode().
 */

(function () {
    'use strict';

    // Keyed by search <input> element — lazily populated the first time a
    // given input fires, so each section's own search box gets its OWN
    // debounce timer instead of sharing/resetting one global timer.
    const debouncedHandlers = new WeakMap();

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wptg-wrapper').forEach(function (wrapper) {
            const mode = wrapper.dataset.mode || 'grid';

            if (mode === 'detail') {
                initDetailMode(wrapper);
            } else if (mode === 'sections') {
                initSectionsMode(wrapper);
            } else {
                initGridMode(wrapper);
            }
        });
    });

    /* ---------------------------------------------------------------------
     * SHARED HELPERS (used by 2+ modes)
     * ------------------------------------------------------------------- */

    /**
     * Toggle one accordion group open/closed and flip its trigger's
     * aria-expanded. Shared by legacy detail mode's category accordion and
     * Sections mode's section/term accordions.
     */
    function bindAccordionToggle(toggleEl, groupEl) {
        const isOpen = groupEl.classList.toggle('open');
        toggleEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    /**
     * Show/hide one .wptg-term-group-shaped element's posts by text match
     * against `query`, and return whether anything in it matched. Shared
     * core extracted from the legacy filterSidebar() so both it and the
     * new, differently-scoped filterWithinSectionGroup() (Sections mode)
     * can reuse the same matching logic instead of duplicating it.
     */
    function filterTermGroup(groupEl, query) {
        const termName = groupEl.dataset.termName || '';
        let groupHasMatch = query === '' || termName.includes(query);

        groupEl.querySelectorAll('.wptg-post-li').forEach(function (li) {
            const text = li.textContent.trim().toLowerCase();
            const match = query === '' || text.includes(query) || termName.includes(query);
            li.style.display = match ? '' : 'none';
            if (match) {
                groupHasMatch = true;
            }
        });

        if (query !== '' && groupHasMatch) {
            groupEl.classList.add('open');
        }
        groupEl.style.display = groupHasMatch ? '' : 'none';
        return groupHasMatch;
    }

    /**
     * Generic AJAX fetch of one post's full content, used by:
     *   - legacy detail mode's inline-swap post click (3-arg call, includeMeta
     *     omitted/falsy -> request body and HTML stay byte-identical to before).
     *   - Sections mode's inline_detail click (always passes includeMeta = true).
     *
     * Resolves its target container via BOTH the legacy and Sections-mode
     * selector so one function serves both modes.
     */
    function loadSinglePost(wrapper, postId, postType, includeMeta) {
        const container = wrapper.querySelector('.wptg-detail-container, .wptg-sections-content');
        if (!container) {
            return;
        }

        container.classList.add('wptg-loading');

        const body = {
            action: 'wptg_get_single_post',
            nonce: wptg_ajax.nonce,
            post_id: postId,
            post_type: postType,
        };
        if (includeMeta) {
            body.include_meta = '1';
        }

        fetch(wptg_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(body),
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                container.classList.remove('wptg-loading');

                if (data.success) {
                    container.innerHTML = data.data.html;
                    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    container.innerHTML = '<div class="wptg-empty"><p>' + (data.data.message || 'Error loading content.') + '</p></div>';
                }
            })
            .catch(function (error) {
                container.classList.remove('wptg-loading');
                container.innerHTML = '<div class="wptg-empty"><p>Error loading content.</p></div>';
                console.error('Taxonomy Grid AJAX Error:', error);
            });
    }

    /**
     * Standard trailing-edge debounce factory.
     */
    function debounce(fn, wait) {
        let timeoutId = null;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    /* ---------------------------------------------------------------------
     * DETAIL MODE (legacy)
     * ------------------------------------------------------------------- */

    function initDetailMode(wrapper) {
        const postType = wrapper.dataset.postType;

        // Accordion toggle (category expand/collapse).
        wrapper.querySelectorAll('.wptg-term-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                bindAccordionToggle(toggle, toggle.closest('.wptg-term-group'));
            });
        });

        // Post selection -> load full content on the right.
        // Uses the closest [data-post-id] ancestor so it works for both a
        // normal "<li class="wptg-post-li">" row AND a merged category+post
        // row ("<div class="wptg-term-group wptg-term-merged">") that has
        // no <li> wrapper at all.
        wrapper.querySelectorAll('.wptg-post-trigger').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const holder = btn.closest('[data-post-id]');
                if (!holder) {
                    return;
                }
                const postId = holder.dataset.postId;

                wrapper.querySelectorAll('[data-post-id]').forEach(function (el) {
                    el.classList.remove('active');
                    const elBtn = el.querySelector('.wptg-post-trigger');
                    if (elBtn) {
                        elBtn.setAttribute('aria-pressed', 'false');
                    }
                });

                holder.classList.add('active');
                btn.setAttribute('aria-pressed', 'true');

                loadSinglePost(wrapper, postId, postType);
            });
        });

        // Live search filter over the sidebar.
        const searchInput = wrapper.querySelector('.wptg-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function () {
                filterSidebar(wrapper, searchInput.value.trim().toLowerCase());
            });
        }
    }

    function filterSidebar(wrapper, query) {
        const groups = wrapper.querySelectorAll('.wptg-term-group');
        let anyVisible = false;

        groups.forEach(function (group) {
            if (filterTermGroup(group, query)) {
                anyVisible = true;
            }
        });

        const noResults = wrapper.querySelector('.wptg-no-results');
        if (noResults) {
            noResults.style.display = (query !== '' && !anyVisible) ? '' : 'none';
        }
    }

    /* ---------------------------------------------------------------------
     * GRID MODE (legacy)
     * ------------------------------------------------------------------- */

    function initGridMode(wrapper) {
        const categoriesList = wrapper.querySelector('.wptg-categories-list');
        const postsContainer = wrapper.querySelector('.wptg-posts-container');

        if (!categoriesList || !postsContainer) {
            return;
        }

        const postType = wrapper.dataset.postType;
        const taxonomy = wrapper.dataset.taxonomy;

        categoriesList.addEventListener('click', function (e) {
            const btn = e.target.closest('.wptg-category-btn');
            if (!btn) {
                return;
            }

            const item = btn.closest('.wptg-category-item');
            const termId = item.dataset.termId;

            categoriesList.querySelectorAll('.wptg-category-item').forEach(function (el) {
                el.classList.remove('active');
                el.querySelector('.wptg-category-btn').setAttribute('aria-pressed', 'false');
            });

            item.classList.add('active');
            btn.setAttribute('aria-pressed', 'true');

            loadPostsGrid(postsContainer, termId, postType, taxonomy);
        });
    }

    function loadPostsGrid(postsContainer, termId, postType, taxonomy) {
        postsContainer.classList.add('wptg-loading');

        fetch(wptg_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wptg_get_posts',
                nonce: wptg_ajax.nonce,
                term_id: termId,
                post_type: postType,
                taxonomy: taxonomy,
            }),
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                postsContainer.classList.remove('wptg-loading');

                if (data.success) {
                    postsContainer.innerHTML = data.data.html;
                } else {
                    postsContainer.innerHTML = '<div class="wptg-empty"><p>' + (data.data.message || 'Error loading posts.') + '</p></div>';
                }
            })
            .catch(function (error) {
                postsContainer.classList.remove('wptg-loading');
                postsContainer.innerHTML = '<div class="wptg-empty"><p>Error loading posts.</p></div>';
                console.error('Taxonomy Grid AJAX Error:', error);
            });
    }

    /* ---------------------------------------------------------------------
     * SECTIONS MODE — Content Navigator
     * ------------------------------------------------------------------- */

    /**
     * Client-side mirror of the PHP wptg_section_list_class() — needed
     * because an AJAX response's `html` only contains the <article> items,
     * not the wrapping list <div>, so this mapping must exist on both
     * sides. Kept as a literal, manually-synced copy (this plugin ships no
     * JS build/bundle step to share one source of truth).
     */
    function wptgListClassFor(contentStyle) {
        if (contentStyle === 'grid_thumbnail') return 'wptg-posts-grid';
        if (contentStyle === 'list_thumbnail') return 'wptg-list-thumbnail';
        return 'wptg-numbered-feed';
    }

    /** Client-side mirror of the PHP wptg_has_more_pages() — same formula, same meaning. */
    function wptgHasMorePages(page, postsPerPage, foundPosts) {
        return (page * postsPerPage) < foundPosts;
    }

    /**
     * Sections-mode equivalent of the existing grid-mode click handler's
     * active-class loop. Clears active/aria-pressed from every nav trigger
     * in the sidebar, then marks the clicked one.
     */
    function markActiveNav(sidebar, trigger) {
        sidebar.querySelectorAll('.wptg-section-nav-trigger, .wptg-section-term-trigger').forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-pressed', 'false');
        });
        trigger.classList.add('active');
        trigger.setAttribute('aria-pressed', 'true');
    }

    /**
     * Sets the clicked item's active state the same way legacy detail
     * mode's own post-selection handler already does: clears active/
     * aria-pressed from sibling items in the same list, then marks the
     * clicked one. Scoped to the nearest items wrapper (content panel's
     * .wptg-section-items-wrap, or a single_detail term's own
     * .wptg-term-posts) so items in a DIFFERENT section/term are untouched.
     */
    function setActiveItem(wrapper, item) {
        const scope = item.closest('.wptg-section-items-wrap, .wptg-term-posts') || wrapper;
        scope.querySelectorAll('[data-click-behavior]').forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-pressed', 'false');
        });
        item.classList.add('active');
        item.setAttribute('aria-pressed', 'true');
    }

    /**
     * The one generic request function backing section/term switch,
     * "Lihat Lebih Banyak", and a flat section's search-re-query. Follows
     * the SAME fetch()/.catch() visible-error shape as loadSinglePost()/
     * loadPostsGrid() (a visible .wptg-empty message on failure, no silent
     * dead end), and explicitly rolls back the pre-incremented page
     * counter on a failed "load more" request so a retry doesn't skip a
     * page.
     */
    function fetchSectionItems(wrapper, group, sectionState, append) {
        const content = wrapper.querySelector('.wptg-sections-content');
        const target = append ? content.querySelector('.wptg-section-items-wrap') : content;

        target.classList.add('wptg-loading');

        fetch(wptg_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wptg_query_items',
                nonce: wptg_ajax.nonce,
                post_type: group.dataset.postType,
                taxonomy: group.dataset.taxonomy || '',
                term_id: String(sectionState.termId || 0),
                content_style: group.dataset.contentStyle,
                click_behavior: group.dataset.clickBehavior,
                page: String(sectionState.page),
                posts_per_page: group.dataset.postsPerPage,
                search: sectionState.search || '',
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                target.classList.remove('wptg-loading');

                if (!data.success) {
                    if (append) {
                        sectionState.page -= 1; // roll back: this page was never actually appended.
                    }
                    target.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>' + (data.data && data.data.message ? data.data.message : 'Error loading items.') + '</p></div>');
                    return;
                }

                if (append) {
                    const list = target.querySelector(':scope > div');
                    list.insertAdjacentHTML('beforeend', data.data.html);
                    target.dataset.page = String(sectionState.page);
                    const loadMoreBtn = target.querySelector('.wptg-load-more');
                    if (loadMoreBtn && !data.data.has_more) {
                        loadMoreBtn.remove();
                    }
                    return;
                }

                // Replace: rebuild the exact SSR shape so a later "load more" works identically.
                const listClass = wptgListClassFor(group.dataset.contentStyle);
                const loadMoreHtml = data.data.has_more
                    ? '<button type="button" class="wptg-load-more">Lihat Lebih Banyak</button>'
                    : '';
                content.innerHTML = '<div class="wptg-section-items-wrap" data-section-index="' + group.dataset.sectionIndex + '" data-term-id="' + (sectionState.termId || 0) + '" data-page="1">'
                    + '<div class="' + listClass + '">' + data.data.html + '</div>'
                    + loadMoreHtml
                    + '</div>';
            })
            .catch((error) => {
                target.classList.remove('wptg-loading');
                if (append) {
                    sectionState.page -= 1; // network/parse failure: also roll back.
                }
                target.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>Error loading items.</p></div>');
                console.error('Taxonomy Grid AJAX Error:', error);
            });
    }

    /**
     * Fetches a post's full content and shows it inside the shared modal
     * (Pengumuman's click_behavior = modal, and the video graceful
     * fallback when neither an external URL nor an attachment is set).
     */
    function loadSinglePostIntoModal(postId, postType) {
        fetch(wptg_ajax.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'wptg_get_single_post',
                nonce: wptg_ajax.nonce,
                post_id: postId,
                post_type: postType,
                include_meta: '1',
            }),
        })
            .then((r) => r.json())
            .then((data) => { if (data.success) WptgModal.open('post', { html: data.data.html }); });
    }

    /**
     * One function, reads data-click-behavior off the clicked element
     * directly (never off an ancestor — see wptg_build_item_attrs()'s own
     * doc comment in wp-taxonomy-grid.php for why).
     */
    function handleItemClick(wrapper, item) {
        const behavior = item.dataset.clickBehavior;

        if (behavior === 'inline_detail') {
            setActiveItem(wrapper, item);
            loadSinglePost(wrapper, item.dataset.postId, item.dataset.postType, /* includeMeta */ true);
            return;
        }

        if (behavior === 'modal') {
            if (item.dataset.modalType === 'image') {
                WptgModal.open('image', {
                    src: item.dataset.imageSrc,
                    title: item.dataset.itemTitle,
                    date: item.dataset.itemDate,
                });
            } else {
                // modalType === 'post' (Pengumuman)
                loadSinglePostIntoModal(item.dataset.postId, item.dataset.postType);
            }
            return;
        }

        if (behavior === 'redirect_or_popup_player') {
            if (item.dataset.externalUrl) {
                window.open(item.dataset.externalUrl, '_blank', 'noopener');
            } else if (item.dataset.videoSrc) {
                WptgModal.open('video', { src: item.dataset.videoSrc, poster: item.dataset.poster || '' });
            } else {
                // Graceful fallback for a mis-configured video post (FR-6):
                // show the normal post-detail modal instead of doing nothing.
                loadSinglePostIntoModal(item.dataset.postId, item.dataset.postType);
            }
        }
    }

    /**
     * Scoped to ONE section's own group. Handles both shapes a
     * taxonomy_accordion section can have: plain term buttons (Galeri
     * Foto/Video — filterTermGroup's .wptg-post-li query simply finds
     * none, harmless, groupHasMatch still falls back to the term-name
     * check) and single_detail's nested term->post tree (reuses
     * filterTermGroup's existing .wptg-post-li handling directly, since
     * that markup shape is identical to the legacy sidebar's).
     */
    function filterWithinSectionGroup(group, query) {
        const termLis = group.querySelectorAll('.wptg-section-term-li');
        let anyVisible = query === '';

        termLis.forEach(function (li) {
            if (filterTermGroup(li, query)) {
                anyVisible = true;
            }
        });

        const noResults = group.querySelector('.wptg-section-no-results');
        if (noResults) {
            noResults.style.display = (query !== '' && !anyVisible) ? '' : 'none';
        }
    }

    function initSectionsMode(wrapper) {
        const sidebar = wrapper.querySelector('.wptg-sections-sidebar');
        const content = wrapper.querySelector('.wptg-sections-content');
        const state = {};

        if (!sidebar || !content) {
            return;
        }

        // Seed state for whatever was server-rendered on initial load.
        const itemsWrap = content.querySelector('.wptg-section-items-wrap');
        if (itemsWrap) {
            state[itemsWrap.dataset.sectionIndex] = {
                page: parseInt(itemsWrap.dataset.page, 10) || 1,
                termId: parseInt(itemsWrap.dataset.termId, 10) || 0,
                search: '',
            };
        }

        // Accordion toggles (section-level and, for single_detail, nested
        // term-level) reuse the SAME shared helper as legacy detail mode's
        // own accordion.
        sidebar.querySelectorAll('.wptg-section-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                bindAccordionToggle(toggle, toggle.closest('.wptg-section-group'));
            });
        });
        sidebar.querySelectorAll('.wptg-term-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                bindAccordionToggle(toggle, toggle.closest('.wptg-section-term-li'));
            });
        });

        // Flat nav buttons + accordion term buttons (grid_thumbnail/list_thumbnail only).
        sidebar.addEventListener('click', function (e) {
            const trigger = e.target.closest('.wptg-section-nav-trigger, .wptg-section-term-trigger');
            if (!trigger) return;

            const group = trigger.closest('.wptg-section-group');
            const termLi = trigger.closest('[data-term-id]');
            const sectionIndex = group.dataset.sectionIndex;
            const termId = termLi ? parseInt(termLi.dataset.termId, 10) : 0;

            markActiveNav(sidebar, trigger);
            state[sectionIndex] = { page: 1, termId: termId, search: '' };
            fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
        });

        // single_detail's nested post triggers reuse the normal item-click path.
        sidebar.addEventListener('click', function (e) {
            const postTrigger = e.target.closest('.wptg-post-trigger[data-click-behavior]');
            if (postTrigger) handleItemClick(wrapper, postTrigger);
        });

        content.addEventListener('click', function (e) {
            const loadMoreBtn = e.target.closest('.wptg-load-more');
            if (loadMoreBtn) {
                const wrap = loadMoreBtn.closest('.wptg-section-items-wrap');
                const sectionIndex = wrap.dataset.sectionIndex;
                state[sectionIndex].page += 1;
                const group = sidebar.querySelector('.wptg-section-group[data-section-index="' + sectionIndex + '"]');
                fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ true);
                return;
            }

            const item = e.target.closest('[data-click-behavior]');
            if (item) handleItemClick(wrapper, item);
        });

        // Per-section search: taxonomy_accordion sections reuse the
        // EXISTING DOM-filter pattern (scoped to just that section's own
        // group — same mechanism as the legacy sidebar search, unchanged);
        // flat_list sections trigger a fresh AJAX query. Debounced PER
        // INPUT ELEMENT (via the module-level WeakMap), not with one
        // shared debounce for the whole sidebar, so two sections' search
        // boxes never share/reset each other's timer.
        sidebar.addEventListener('input', function (e) {
            const input = e.target.closest('.wptg-section-search-input');
            if (!input) return;

            if (!debouncedHandlers.has(input)) {
                debouncedHandlers.set(input, debounce(function () {
                    const group = input.closest('.wptg-section-group');
                    const query = input.value.trim().toLowerCase();

                    if (group.dataset.sectionType === 'taxonomy_accordion') {
                        filterWithinSectionGroup(group, query);
                        return;
                    }

                    const sectionIndex = group.dataset.sectionIndex;
                    state[sectionIndex] = { page: 1, termId: 0, search: input.value.trim() };
                    fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
                }, 300));
            }

            debouncedHandlers.get(input)();
        });
    }
})();
