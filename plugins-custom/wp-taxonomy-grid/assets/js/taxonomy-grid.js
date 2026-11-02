/**
 * Taxonomy Grid - JavaScript
 *
 * Handles three display modes:
 *   - "detail":   sidebar accordion + search + AJAX single-post swap (legacy)
 *   - "grid":     category click + AJAX posts-grid swap (legacy)
 *   - "sections": N independent sidebar sections (flat list or taxonomy
 *                 accordion) sharing ONE content panel — the Content
 *                 Navigator. See initSectionsMode(). A deep link
 *                 (?wptg_section=...&wptg_term=...) is resolved by PHP; the
 *                 JS only scrolls to the widget (data-deep-link="1").
 */

(function () {
    'use strict';

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
        // In Sections mode only the inner panel is swapped, so the content
        // title and the content search box stay on screen.
        const container = wrapper.querySelector('.wptg-detail-container, .wptg-sections-panel');
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
        if (contentStyle === 'document_list') return 'wptg-doc-list';
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
        clearActiveNav(sidebar);
        trigger.classList.add('active');
        trigger.setAttribute('aria-pressed', 'true');
    }

    /**
     * Clears the active state of every nav trigger. Also used on its own when
     * an empty section (no trigger to mark) becomes the open one.
     */
    function clearActiveNav(sidebar) {
        sidebar.querySelectorAll('.wptg-section-nav-trigger, .wptg-section-term-trigger').forEach(function (el) {
            el.classList.remove('active');
            el.setAttribute('aria-pressed', 'false');
        });
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
        const panel = wrapper.querySelector('.wptg-sections-panel');
        const itemsWrap = append ? panel.querySelector('.wptg-section-items-wrap') : null;
        const loadMoreBtn = itemsWrap ? itemsWrap.querySelector('.wptg-load-more') : null;

        // "Load more" only marks its own button as busy (and disables it, so a
        // double click cannot request the same page twice). A section switch or
        // search dims the whole panel instead.
        const busyEl = loadMoreBtn || panel;
        const errorHost = itemsWrap || panel;
        setBusy(busyEl, true);

        // The feed look (layout, number, excerpt, read-more text) lives on the
        // wrapper, so items from AJAX look the same as the server-rendered ones.
        const feed = wrapper.dataset;

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
                feed_layout: feed.feedLayout || '',
                show_number: feed.showNumber || '1',
                excerpt_length: feed.excerptLength || '40',
                readmore_text: feed.readmoreText || '',
                doc_options: feed.docOptions || '',
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                setBusy(busyEl, false);

                if (!data.success) {
                    if (append) {
                        sectionState.page -= 1; // roll back: this page was never actually appended.
                    }
                    errorHost.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>' + (data.data && data.data.message ? data.data.message : 'Error loading items.') + '</p></div>');
                    return;
                }

                if (append) {
                    const list = itemsWrap.querySelector(':scope > div');
                    list.insertAdjacentHTML('beforeend', data.data.html);
                    itemsWrap.dataset.page = String(sectionState.page);
                    if (loadMoreBtn && !data.data.has_more) {
                        loadMoreBtn.remove();
                    }
                    fillIconSlots(wrapper);
                    return;
                }

                // Empty state (search without hits, section/term without items):
                // the server already built the skeleton. Show it as is, with no
                // items wrapper and no Load More button.
                if (data.data.empty) {
                    panel.innerHTML = data.data.html;
                    return;
                }

                // Replace: rebuild the exact SSR shape so a later "load more" works identically.
                const listClass = wptgListClassFor(group.dataset.contentStyle);
                const loadMoreHtml = data.data.has_more
                    ? '<button type="button" class="wptg-load-more"></button>'
                    : '';
                panel.innerHTML = '<div class="wptg-section-items-wrap" data-section-index="' + group.dataset.sectionIndex + '" data-term-id="' + (sectionState.termId || 0) + '" data-page="1">'
                    + '<div class="' + listClass + '">' + data.data.html + '</div>'
                    + loadMoreHtml
                    + '</div>';

                // textContent (not innerHTML) so the editor-typed label can never inject markup.
                const newLoadMoreBtn = panel.querySelector('.wptg-load-more');
                if (newLoadMoreBtn) {
                    newLoadMoreBtn.textContent = feed.loadMoreText || 'Lihat Lebih Banyak';
                }
                fillIconSlots(wrapper);
            })
            .catch((error) => {
                setBusy(busyEl, false);
                if (append) {
                    sectionState.page -= 1; // network/parse failure: also roll back.
                }
                errorHost.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>Error loading items.</p></div>');
                console.error('Taxonomy Grid AJAX Error:', error);
            });
    }

    /**
     * Loading state. The "load more" button gets .is-loading + disabled; any
     * other element (the content panel) gets the shared .wptg-loading class.
     */
    function setBusy(el, isBusy) {
        if (el.classList.contains('wptg-load-more')) {
            el.classList.toggle('is-loading', isBusy);
            el.disabled = isBusy;
            el.setAttribute('aria-busy', isBusy ? 'true' : 'false');
            return;
        }
        el.classList.toggle('wptg-loading', isBusy);
    }

    /**
     * Items that arrive via AJAX have an empty icon slot (the server never
     * receives icon markup): the read-more icon of feed cards and the Card Icon
     * of document cards. Copy the icon from the <template> the server rendered
     * once inside the wrapper. The skeleton's grey icon block is also an empty
     * .wptg-doc-icon, so it is skipped.
     */
    function fillIconSlots(wrapper) {
        [
            ['.wptg-readmore-icon-template', '.wptg-feed-readmore-icon:empty'],
            ['.wptg-doc-icon-template', '.wptg-doc-icon:empty:not(.wptg-skeleton-block)'],
        ].forEach(function (pair) {
            const template = wrapper.querySelector(pair[0]);

            if (!template) {
                return;
            }
            wrapper.querySelectorAll(pair[1]).forEach(function (slot) {
                slot.innerHTML = template.innerHTML;
            });
        });
    }

    /**
     * Fetches a post's full content and shows it inside the shared modal
     * (click_behavior = modal on a post-style item such as Pengumuman).
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

        // behavior === 'media_popup' and 'document_preview' are deliberately NOT
        // handled here: those items carry data-wptg-media and media-popup.js (one
        // listener on document) opens the popup. Handling it twice would open it twice.
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

        // The widget has ONE search box (top of the sidebar). `searchQuery`
        // is what was typed; `activeSectionIndex` is the section currently
        // open. The query applies to the open section and is re-applied
        // whenever another section is opened.
        let searchQuery = '';
        let activeSectionIndex = null;

        const getGroup = function (index) {
            return sidebar.querySelector('.wptg-section-group[data-section-index="' + index + '"]');
        };

        // Header title + last breadcrumb item follow the open section, unless
        // the editor typed a fixed Title / Current Label (the server sets
        // data-dynamic-title / data-dynamic-crumb to "1" only when empty).
        const headerTitle = wrapper.querySelector('.wptg-header-title');
        const crumbCurrent = wrapper.querySelector('.wptg-breadcrumb-current');
        const contentTitle = wrapper.querySelector('.wptg-content-title');
        const syncLabels = function (group) {
            const label = group ? group.dataset.sectionLabel : '';
            if (!label) return;

            if (wrapper.dataset.dynamicTitle === '1' && headerTitle) {
                headerTitle.textContent = label;
            }
            if (wrapper.dataset.dynamicCrumb === '1' && crumbCurrent) {
                crumbCurrent.textContent = label;
            }
            if (wrapper.dataset.dynamicContentTitle === '1' && contentTitle) {
                contentTitle.textContent = label;
            }
        };

        if (itemsWrap) {
            activeSectionIndex = itemsWrap.dataset.sectionIndex;
        } else {
            const activePost = sidebar.querySelector('.wptg-post-li.active');
            const activeGroup = activePost ? activePost.closest('.wptg-section-group') : null;
            // Empty state: the panel is a skeleton (no items wrapper, no active
            // post), so the server tells us the open section.
            activeSectionIndex = activeGroup ? activeGroup.dataset.sectionIndex : (content.dataset.activeSection || null);
        }

        // single_detail sections have no paginated feed to re-query, so the
        // query filters their sidebar tree instead (DOM filter, same as the
        // legacy sidebar search). Only the open section is filtered; every
        // other one is reset.
        const filterDetailGroups = function () {
            const active = activeSectionIndex !== null ? getGroup(activeSectionIndex) : null;

            sidebar.querySelectorAll('.wptg-section-group[data-content-style="single_detail"]').forEach(function (group) {
                const isSearched = group === active && group.dataset.searchEnabled === '1';
                filterWithinSectionGroup(group, isSearched ? searchQuery.toLowerCase() : '');
            });
        };

        // Apply the current query to the open section: filter the sidebar for
        // single_detail, otherwise re-query its items from page 1 (so older,
        // not-yet-loaded items are searched too).
        const applySearch = function () {
            filterDetailGroups();

            const active = activeSectionIndex !== null ? getGroup(activeSectionIndex) : null;
            if (!active || active.dataset.contentStyle === 'single_detail' || active.dataset.searchEnabled !== '1') {
                return;
            }

            const current = state[activeSectionIndex] || {};
            state[activeSectionIndex] = { page: 1, termId: current.termId || 0, search: searchQuery };
            fetchSectionItems(wrapper, active, state[activeSectionIndex], /* append */ false);
        };

        // Accordion toggles (section-level and, for single_detail, nested
        // term-level) reuse the SAME shared helper as legacy detail mode's
        // own accordion.
        sidebar.querySelectorAll('.wptg-section-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const group = toggle.closest('.wptg-section-group');
                bindAccordionToggle(toggle, group);

                // A section without terms has no term button to click, so its
                // header also opens it. The server answers with the skeleton for
                // its content style (same source as the first page load).
                if (!group.querySelector('.wptg-section-term-li') && activeSectionIndex !== group.dataset.sectionIndex) {
                    const sectionIndex = group.dataset.sectionIndex;
                    clearActiveNav(sidebar);
                    activeSectionIndex = sectionIndex;
                    syncLabels(group);
                    filterDetailGroups();

                    const search = group.dataset.searchEnabled === '1' ? searchQuery : '';
                    state[sectionIndex] = { page: 1, termId: 0, search: search };
                    fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
                }
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
            activeSectionIndex = sectionIndex;
            syncLabels(group);
            filterDetailGroups();

            // The one search box keeps applying after switching sections.
            const search = group.dataset.searchEnabled === '1' ? searchQuery : '';
            state[sectionIndex] = { page: 1, termId: termId, search: search };
            fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
        });

        // single_detail's nested post triggers reuse the normal item-click path.
        sidebar.addEventListener('click', function (e) {
            const postTrigger = e.target.closest('.wptg-post-trigger[data-click-behavior]');
            if (!postTrigger) return;

            const postGroup = postTrigger.closest('.wptg-section-group');
            if (postGroup) {
                activeSectionIndex = postGroup.dataset.sectionIndex;
                syncLabels(postGroup);
                filterDetailGroups();
            }
            handleItemClick(wrapper, postTrigger);
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

            // The "Unduh" link of a document card only downloads its file; it is not a click on the item.
            if (e.target.closest('a[download]')) return;

            const item = e.target.closest('[data-click-behavior]');
            if (item) handleItemClick(wrapper, item);
        });

        // Search. There can be two inputs (sidebar + content, "Search Position:
        // Both"), but only ONE state (`searchQuery`) and one debounced search:
        // typing in either input copies the text into the other one.
        const searchInputs = wrapper.querySelectorAll('.wptg-search-input');
        const runSearch = debounce(applySearch, 300);
        searchInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                searchQuery = input.value.trim();
                searchInputs.forEach(function (other) {
                    if (other !== input) {
                        other.value = input.value;
                    }
                });
                runSearch();
            });
        });

        fillIconSlots(wrapper);

        // Deep link (?wptg_section=...): the server already opened the right
        // section/term, so the visitor only needs to land on the widget. Wait
        // for "load" so images above it cannot push the widget out of view
        // afterwards. Runs once per page load; later clicks never scroll.
        if (wrapper.dataset.deepLink === '1') {
            const scrollToWidget = function () {
                wrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
            };
            if (document.readyState === 'complete') {
                scrollToWidget();
            } else {
                window.addEventListener('load', scrollToWidget, { once: true });
            }
        }
    }
})();
