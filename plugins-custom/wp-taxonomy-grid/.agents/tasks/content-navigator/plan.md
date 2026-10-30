# Implementation Plan — "Content Navigator" (Taxonomy Grid v4.0.0)

Source of truth for every code block referenced below: `design.md` in this
same folder (sections "The Sections repeater schema",
"`wptg_render_sections_widget()`", "Client-side `initSectionsMode()`
architecture", "The reusable modal/lightbox component", "The reusable
generic AJAX...", "`videos` external-URL meta field"). This plan sequences
*where* each piece goes and calls out the corrections from the **"Open
design-review findings"** section at the end of `design.md`, which the
coder MUST apply — they are fixes on top of the code blocks shown earlier
in that same document, not optional nitpicks.

**Non-negotiable constraints for every step below:**
- Never run `git add` / `git commit` / `git push` (human commits manually per `AGENTS.md`).
- Never edit `c:/projects/imigrasi-wp/web/app/mu-plugins/custom-taxonomies.php`.
- The legacy `services`-style path (`wptg_post_type` + `wptg_taxonomy` + `wptg_mode`,
  `wptg_resolve_and_render()`) is never removed, renamed, or made conditional on
  anything new — it stays the default (`wptg_source_mode = single`) and every
  existing control keeps working unconditionally for that mode.
- Control-naming convention actually used in this codebase (verified by reading
  `includes/class-elementor-widget.php`): **section** ids use `wptg_section_...`
  (e.g. `wptg_section_source`, `wptg_section_style_container`); individual
  **controls** use a plain `wptg_...` prefix without `_section_` (e.g.
  `wptg_post_type`, `wptg_mode`). New IDs below follow this exact split, not a
  blanket `wptg_section_...` prefix on every control.
- `declare(strict_types=1)` already governs both PHP files; keep typed
  signatures, PHPDoc, and `esc_html()`/`esc_attr()`/`esc_url()` on all new
  dynamic output, no `@` suppression (NFR-3).

---

- [ ] 1. Create the video external-URL meta box as a new, self-contained file, and wire it in.
      Add `includes/class-video-meta-box.php` (plain functions, no class, despite the
      `class-` filename — matches `design.md`'s "File organization" rationale) with
      `wptg_register_video_meta_box()` (hooked on `add_meta_boxes`, registers a `side`
      meta box titled "External Video URL" on the `videos` post type only),
      `wptg_render_video_meta_box(\WP_Post $post)` (renders the `_video_external_url`
      field with `wp_nonce_field('wptg_video_meta_save', 'wptg_video_meta_nonce')`), and
      `wptg_save_video_meta_box(int $post_id)` (hooked on `save_post_videos`; guards with
      `wp_is_post_autosave()`/`wp_is_post_revision()`, nonce check, `current_user_can('edit_post', $post_id)`,
      then `esc_url_raw()` before `update_post_meta()` — exact bodies are in `design.md`
      under "`videos` external-URL meta field"). Add one line near the top of
      `wp-taxonomy-grid.php` (after the `WPTG_PATH`/`WPTG_URL` defines) that
      unconditionally `require_once WPTG_PATH . 'includes/class-video-meta-box.php';`
      — this file has no Elementor dependency, so it must NOT be gated behind the
      `elementor/widgets/register` hook the way `class-elementor-widget.php` is.
      Files: `includes/class-video-meta-box.php` (new), `wp-taxonomy-grid.php`.
      Verify: `php -l includes/class-video-meta-box.php` and `php -l wp-taxonomy-grid.php`
      both print "No syntax errors detected" (run from
      `plugins-custom/wp-taxonomy-grid/`).

- [ ] 2. Create the reusable modal/lightbox JS component.
      Add `assets/js/modal.js`: a single IIFE exposing `window.WptgModal = { open(type, payload), close() }`.
      Lazily builds and appends the modal DOM
      (`.wptg-modal > .wptg-modal-backdrop` + `.wptg-modal-panel[role=dialog] > .wptg-modal-close` + `.wptg-modal-body`)
      to `document.body` once on first `open()` call. `open()` switches on
      `type` (`'image'` / `'post'` / `'video'`) exactly as specified in
      `design.md` under "The reusable modal/lightbox component" — build
      image/video elements via `document.createElement`/`setAttribute`/`textContent`
      (never string concatenation into `innerHTML`, except for the `'post'` type,
      which assigns the already-escaped, trusted AJAX HTML via `innerHTML`,
      exactly like the existing `loadSinglePost()` already does). `close()` hides
      the modal, clears `.wptg-modal-body` (so a stray `<video>` doesn't keep
      playing off-screen), and removes its Escape/backdrop-click listeners.
      Escape key and a direct click on `.wptg-modal-backdrop` (not the panel)
      both call `close()`.
      Files: `assets/js/modal.js` (new).
      Verify: `node --check assets/js/modal.js` reports no syntax errors.

- [ ] 3. Wire `modal.js` into the enqueue, and bump both enqueued asset versions to 4.0.0.
      In `wp-taxonomy-grid.php`'s `wp_enqueue_scripts` callback, add a new
      `wp_enqueue_script('wptg-modal', WPTG_URL . 'assets/js/modal.js', [], '4.0.0', true)`
      call registered before the existing `wptg-scripts` registration, then change
      `wptg-scripts`'s deps array from `[]` to `['wptg-modal']` and bump both its
      and `wptg-styles`'s version argument from `'3.9.1'` to `'4.0.0'` (exact diff is
      in `design.md` under "Enqueue wiring"). Do not touch `wp_localize_script()`.
      Files: `wp-taxonomy-grid.php`.
      Verify: `php -l wp-taxonomy-grid.php` passes; load any front-end page with the
      widget in a browser (or view source) and confirm `wptg-modal.js` now appears
      in the `<script>` tags before `taxonomy-grid.js`.

- [ ] 4. Add the Sections rendering engine to `wp-taxonomy-grid.php` (pure helpers + I/O renderers + the new AJAX action).
      Append, after the existing `wptg_ajax_get_posts()` function (end of file),
      all of the following new functions exactly as specified in `design.md`'s
      `wptg_render_sections_widget()` section, **with the 3 corrections below
      applied** (these come from `design.md`'s own "Open design-review findings"
      list and are not optional):
      - `wptg_normalize_section_row(array $row, int $index): ?array` — **correction
        for Finding 6**: do not return `null` when `section_post_type` is empty; the
        widget's control-level default (Step 6 below) already guarantees Elementor
        always saves a non-empty value for new rows, so an empty value here only
        happens for a pre-existing row saved before that default existed — treat it
        the same as an unresolvable post type (`get_post_type_object()` returns
        falsy) and keep returning `null` for THAT case (row is skipped, as designed),
        but add a code comment explaining why the control default (not this
        function) is what prevents new rows from vanishing silently.
      - `wptg_count_published_posts(string $post_type): int`
      - `wptg_resolve_default_active(array $sections): ?array` (pure, no `WP_Query`)
      - `wptg_query_section_items(...): WP_Query`
      - `wptg_has_more_pages(int $page, int $posts_per_page, int $found_posts): bool` (pure)
      - `wptg_section_list_class(string $content_style): string`
      - `wptg_build_item_attrs(int $post_id, string $post_type, string $click_behavior, array $extra = []): string`
      - `wptg_render_item_numbered_feed()`, `wptg_render_item_grid_thumbnail()`,
        `wptg_render_item_list_thumbnail()`, dispatched by `wptg_render_section_item()`
      - `wptg_render_sections_widget(array $settings, string $instance_id): string`
      - `wptg_render_section_sidebar_group(array $section, ?array $default_active): string`
        — **correction for Finding 1 (HIGH)**: put the `open` class on the ANCESTOR
        element (`.wptg-section-group` for a flat/accordion group's own
        `<ul class="wptg-section-terms">`, and `.wptg-section-term-li` for a
        `single_detail` term's own `<ul class="wptg-term-posts">`), not on the `<ul>`
        itself — i.e. the `<div class="wptg-section-group ... <?= $is_section_active ? 'open' : '' ?>">`
        wrapper and `<li class="wptg-section-term-li ... <?= $is_term_active ? 'open' : '' ?>">`
        carry the `open` class, and the inner `<ul>` elements carry NO class toggle
        at all (CSS below reads the ancestor's `.open` state, matching the legacy
        `.wptg-term-group:not(.open) .wptg-term-posts` convention). **Correction for
        Finding 4**: add `data-term-name="<?php echo esc_attr(strtolower($term->name)); ?>"`
        to every `<li class="wptg-section-term-li">` (mirrors the legacy sidebar's own
        `data-term-name` on `.wptg-term-group`), so `filterTermGroup()` (Step 9) can
        match by category name. **Correction for Finding 7 (NIT)**: only render the
        `<p class="wptg-section-no-results">` element when
        `$section['enable_search'] && $section['type'] !== 'flat_list'` (flat_list's
        search is AJAX-only and never toggles this element — rendering it
        unconditionally for flat_list is dead markup).
      - `wptg_render_section_content_panel(array $section, array $active): string`
      - `wptg_ajax_query_section_items(): void`, hooked on both
        `wp_ajax_wptg_query_items` and `wp_ajax_nopriv_wptg_query_items` (NFR-1) —
        implement the nonce check (`check_ajax_referer('wptg_nonce', 'nonce')`) and
        every validation branch exactly as shown in `design.md` (post type
        existence/`public`, `content_style` whitelist excluding `single_detail`,
        taxonomy-registered-for-post-type check, term-belongs-to-taxonomy check via
        `get_term($term_id, $taxonomy)`) — this is the fix for Finding 3 (MEDIUM):
        do NOT assume `wptg_ajax_get_posts()`'s existing validation pattern already
        covers this; it doesn't (confirmed by reading it — it only checks
        `get_post_type_object()`/`taxonomy_exists()`, not taxonomy-registered-for-
        post-type or term-belongs-to-taxonomy), so every one of these checks must be
        written fresh in `wptg_ajax_query_section_items()`.
      Also modify the existing `wptg_render_single_post_detail(int $post_id): string`
      to `wptg_render_single_post_detail(int $post_id, bool $include_meta = false): string`
      (default preserves today's output byte-for-byte; `$include_meta` adds the hero
      image + date/author meta block per `design.md`), and modify the existing
      `wptg_ajax_get_single_post()` to read `$_POST['include_meta']`
      (`$include_meta = isset($_POST['include_meta']) && $_POST['include_meta'] === '1';`)
      and pass it through to `wptg_render_single_post_detail()` — this is Finding 2's
      (HIGH) missing piece; the legacy JS call site never sends this field, so it
      stays `false` and the AJAX response is byte-identical to today for legacy mode.
      Files: `wp-taxonomy-grid.php`.
      Verify: `php -l wp-taxonomy-grid.php` prints "No syntax errors detected".

- [ ] 5. Add the "Source Mode" backward-compatibility switch and condition every existing Content-tab control on it.
      In `includes/class-elementor-widget.php`'s `register_source_controls()`, add the
      new `wptg_source_mode` SELECT control (`single` default / `sections`) exactly as
      shown in `design.md` under "Backward compatibility mechanism", and add
      `'condition' => ['wptg_source_mode' => 'single']` to the 4 existing controls in
      that method (`wptg_post_type`, `wptg_taxonomy`, `wptg_mode`, `wptg_active_first`).
      Then add `'wptg_source_mode' => 'single'` into the existing `condition` array (or
      create one if absent) on these 4 other Content-tab methods' `start_controls_section()`
      calls: `register_header_content_controls()`, `register_breadcrumb_content_controls()`,
      `register_search_content_controls()` (each currently `['wptg_mode' => 'detail']` →
      `['wptg_mode' => 'detail', 'wptg_source_mode' => 'single']`), and
      `register_grid_controls()` (`['wptg_mode' => 'grid']` →
      `['wptg_mode' => 'grid', 'wptg_source_mode' => 'single']`). No control is removed,
      renamed, or has its default changed.
      Files: `includes/class-elementor-widget.php`.
      Verify: `php -l includes/class-elementor-widget.php` passes; open an existing
      page using this widget in the Elementor editor and confirm the Content tab looks
      identical to before (Source Mode defaults to "Single", every existing field
      still visible).

- [ ] 6. Add the `Sections` repeater control (new Content-tab section).
      In `includes/class-elementor-widget.php`, add a new private method
      `register_sections_controls()` (called from `register_controls()` right after
      `register_source_controls()`) that builds the `\Elementor\Repeater` with the 8
      fields from `design.md`'s "The Sections repeater schema"
      (`section_label`, `section_type`, `section_post_type`, `section_taxonomy`,
      `section_content_style`, `section_click_behavior`, `section_enable_search`,
      `section_posts_per_page`), then registers it as `$this->add_control('wptg_sections', [...])`
      with `'condition' => ['wptg_source_mode' => 'sections']`. **Correction for
      Finding 6 (MEDIUM)**: give `section_post_type` a non-empty `'default' => 'post'`
      (mirrors the existing top-level `wptg_post_type` control's own default) instead
      of leaving it unset — this is the chosen fix from `design.md`'s two offered
      options, picked because it requires no renderer-side "not configured" UI and
      guarantees every row added through the Elementor UI from now on normalizes
      successfully; document this choice in `readme.txt` (Step 11) under the Sections
      usage section. Keep `section_content_style`'s `description` text exactly as
      specified in `design.md` (documents the fallback behavior in-editor, per
      requirements.md AC14).
      Files: `includes/class-elementor-widget.php`.
      Verify: `php -l includes/class-elementor-widget.php` passes; in the Elementor
      editor, switch Source Mode to "Sections" and confirm the "Sections" repeater
      control appears with all 8 fields, and that `section_taxonomy` only shows when
      `section_type = taxonomy_accordion`.

- [ ] 7. Add the `render()` branch that dispatches to the new Sections renderer.
      In `includes/class-elementor-widget.php`'s `render()` method, read
      `$source_mode = $settings['wptg_source_mode'] ?? 'single';` right after
      `$settings = $this->get_settings_for_display();`, and if
      `$source_mode === 'sections'`, `echo wptg_render_sections_widget($settings, $instance_id); return;`
      before any of the existing `$raw_atts` building code — the existing code below
      that point is otherwise untouched, byte-for-byte (exact snippet in `design.md`
      under "Backward compatibility mechanism" → "In `render()`").
      Files: `includes/class-elementor-widget.php`.
      Verify: `php -l includes/class-elementor-widget.php` passes; open the existing
      services page in the Elementor editor, confirm it still has no `wptg_source_mode`
      value stored (Elementor only persists non-default values) and the rendered
      front-end HTML is unchanged (diff page source before/after this step using the
      browser's "View Source").

- [ ] 8. Add `'wptg_source_mode' => 'single'` condition to the 9 existing Style-tab sections that need it, leaving Container unconditional.
      In `includes/class-elementor-widget.php`, add `'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single']`
      (replacing the existing `'condition' => ['wptg_mode' => 'detail']`) to
      `register_style_header_controls()` and `register_style_breadcrumb_controls()`
      and `register_style_search_controls()`; and add a brand-new
      `'condition' => ['wptg_source_mode' => 'single']` (these 5 currently have none)
      to `register_style_sidebar_controls()`, `register_style_divider_accordion_controls()`,
      `register_style_divider_merged_controls()`, `register_style_category_name_accordion_controls()`,
      `register_style_category_name_merged_controls()`, `register_style_content_controls()`.
      Do **not** touch `register_style_container_controls()` — its selectors
      (`.wptg-wrapper`, `.wptg-sidebar`, `.wptg-body`, `.wptg-section-top`/`.wptg-section-body`)
      are deliberately reused by the new Sections markup (Step 4's
      `wptg_render_sections_widget()` reuses these exact class names), so it must
      stay visible/active in both modes.
      Files: `includes/class-elementor-widget.php`.
      Verify: `php -l includes/class-elementor-widget.php` passes; in the Elementor
      editor with Source Mode = "Sections", confirm these 9 Style sections disappear
      from the Style tab, while "Container" stays visible.

- [ ] 9. Add the 6 new Style-tab sections for Sections-mode markup.
      Add 6 new private methods to `includes/class-elementor-widget.php`
      (`register_style_sections_nav_controls()`, `register_style_numbered_feed_controls()`,
      `register_style_grid_thumbnail_controls()`, `register_style_list_thumbnail_controls()`,
      `register_style_load_more_controls()`, `register_style_modal_controls()`), each
      wrapped in `start_controls_section('wptg_section_style_sections_nav', [...])` /
      `..._numbered_feed` / `..._grid_thumbnail` / `..._list_thumbnail` / `..._load_more`
      / `..._modal` with `'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['wptg_source_mode' => 'sections']`,
      and call all 6 from `register_controls()` after the existing Style-tab method
      calls. Reuse this class's own helpers (`add_text_style()`, `add_badge_box_controls()`,
      `add_spacing_controls()`, `add_box_control()`, `add_bg_control()`,
      `add_alignment_control()`, `Group_Control_Border`) with the exact selectors listed
      in `design.md`'s "Style-tab control reuse" table (e.g.
      `add_text_style('wptg_feed_title', ..., '{{WRAPPER}} .wptg-feed-title')`,
      `add_bg_control('wptg_modal_backdrop_bg', ..., '{{WRAPPER}} .wptg-modal-backdrop')`)
      — do not invent new selector names not already used by the Step 4 PHP renderers
      or Step 2's `modal.js`.
      Files: `includes/class-elementor-widget.php`.
      Verify: `php -l includes/class-elementor-widget.php` passes; with Source Mode =
      "Sections", confirm all 6 new Style sections appear and each control's
      live-preview CSS updates the corresponding element in the Elementor editor
      preview iframe.

- [ ] 10. Add `initSectionsMode()` and its helpers to `assets/js/taxonomy-grid.js`, plus the 3 behavior-preserving refactors of existing code.
      In `assets/js/taxonomy-grid.js`:
      - Extract the inline 2-line accordion toggle in `initDetailMode()` into a shared
        `bindAccordionToggle(toggleEl, groupEl)` function (toggles `.open` + flips
        `aria-expanded`), and call it from both `initDetailMode()`'s existing
        `.wptg-term-toggle` listener and the new Sections code below.
      - Extract `filterSidebar()`'s inner per-group matching loop into a new
        `filterTermGroup(groupEl, query)` function; `filterSidebar()` keeps its exact
        current signature/behavior, now delegating to `filterTermGroup()` per group
        (see `design.md`'s "`filterWithinSectionGroup()`" section for the exact
        before/after).
      - Generalize `loadSinglePost(wrapper, postId, postType)` to
        `loadSinglePost(wrapper, postId, postType, includeMeta)` (4th param, falsy by
        default so the existing 3-arg call site in `initDetailMode()` is unaffected
        and keeps omitting `include_meta` from the request body) — **this is Finding
        2's (HIGH) required body**, not just the description already in `design.md`'s
        prose. Resolve its target container via
        `wrapper.querySelector('.wptg-detail-container, .wptg-sections-content')`
        instead of only `.wptg-detail-container`.
      - Add `markActiveNav(sidebar, trigger)`, and `setActiveItem(wrapper, item)`
        (Finding 2's other missing body — sets the clicked item's active state the
        same way `initDetailMode()`'s own post-selection handler already does: clears
        `active`/`aria-pressed` from sibling items in the same list, then marks the
        clicked one).
      - Add a **per-input** debounce, not one shared debounce for the whole sidebar
        (**correction for Finding 5, MEDIUM**): implement `debounce(fn, wait)` as a
        plain reusable factory, but store each input element's own debounced handler
        in a `WeakMap` keyed by the input element (e.g.
        `const debouncedHandlers = new WeakMap();` populated lazily the first time a
        given search input fires, reusing the cached handler on subsequent events for
        that same input) so two sections' search boxes never share/reset each other's
        timer.
      - Add `wptgListClassFor(contentStyle)` and `wptgHasMorePages(page, postsPerPage, foundPosts)`
        (mirrors `wptg_section_list_class()`/`wptg_has_more_pages()` exactly).
      - Add `fetchSectionItems(wrapper, group, sectionState, append)` with the full
        append/replace/error-rollback body from `design.md`.
      - Add `handleItemClick(wrapper, item)` and `loadSinglePostIntoModal(postId, postType)`
        exactly as in `design.md`.
      - Add `filterWithinSectionGroup(group, query)` (calls `filterTermGroup()` per
        `.wptg-section-term-li`, matching `data-term-name` now emitted by Step 4).
      - Add `initSectionsMode(wrapper)` wiring sidebar clicks (flat nav triggers,
        accordion toggles via `bindAccordionToggle()`, single_detail post triggers via
        `handleItemClick()`), content-panel clicks (load-more, item clicks), and the
        per-input-debounced search listener — full body in `design.md`.
      - In the `DOMContentLoaded` dispatcher at the top of the file, add a branch:
        `else if (mode === 'sections') { initSectionsMode(wrapper); }` alongside the
        existing `detail`/`grid` branches (currently an `if/else` with only those two;
        extend to an `if/else if/else` so `sections` gets its own path instead of
        falling into `initGridMode()`).
      Files: `assets/js/taxonomy-grid.js`.
      Verify: `node --check assets/js/taxonomy-grid.js` reports no syntax errors; open
      the existing services page and confirm category expand/collapse and the detail
      search box still behave exactly as before (manual smoke check, since this file
      has no automated test runner).

- [ ] 11. Add the `SECTIONS MODE` and `MODAL` CSS blocks, including the accordion ancestor-class fix.
      Append two new top-level blocks to `assets/css/taxonomy-grid.css`, after the
      existing `GRID MODE (legacy)` block and before `SHARED — loading state &
      animation`, following the file's existing banner-comment convention:
      - `SECTIONS MODE`: rules for `.wptg-sections-sidebar`, `.wptg-section-group`
        (`--flat`/`--accordion` modifiers), `.wptg-section-nav-trigger`,
        `.wptg-section-toggle`, `.wptg-section-terms`, `.wptg-section-term-li`,
        `.wptg-term-toggle`/`.wptg-term-posts` (reused from legacy, already styled),
        `.wptg-feed-item`/`.wptg-feed-thumb`/`.wptg-feed-badge`/`.wptg-feed-body`/
        `.wptg-feed-date`/`.wptg-feed-title`/`.wptg-feed-readmore` (numbered feed),
        `.wptg-post-item--photo`/`.wptg-photo-thumb`/`.wptg-photo-caption`/
        `.wptg-photo-title`/`.wptg-photo-date` (grid thumbnail — reuses the existing
        `.wptg-post-item` box rules, only adds the `--photo` modifier's own caption
        layout), `.wptg-list-thumbnail-item`/`.wptg-list-thumbnail-media`/
        `.wptg-list-thumbnail-play`/`.wptg-list-thumbnail-body`/
        `.wptg-list-thumbnail-title`/`.wptg-list-thumbnail-meta` (video list), and
        `.wptg-load-more`, `.wptg-section-search`, `.wptg-section-no-results`.
        **Correction for Finding 1 (HIGH)**: write the open/closed rules to match
        Step 4's ancestor-class fix —
        `.wptg-section-group:not(.open) .wptg-section-terms { max-height: 0; }` and
        `.wptg-section-term-li:not(.open) .wptg-term-posts { max-height: 0; }`
        (mirroring the legacy `.wptg-term-group:not(.open) .wptg-term-posts` rule
        already in the file), NOT a rule targeting the `<ul>`'s own class.
      - `MODAL (shared lightbox)`: `.wptg-modal` (`[hidden]` state),
        `.wptg-modal-backdrop`, `.wptg-modal-panel`, `.wptg-modal-close`,
        `.wptg-modal-body` (including `img`/`video` sizing inside it).
      Add responsive overrides for both new blocks inside the file's existing
      `@media (max-width: 768px)` block (stack to single column, full-width modal
      panel on small screens), following the same pattern as the existing Detail/Grid
      responsive rules in that block.
      Files: `assets/css/taxonomy-grid.css`.
      Verify: no CSS build/lint tool is configured for this plugin (confirmed: no
      `.stylelintrc`/build step in this repo) — verify by opening a page with Source
      Mode = "Sections" configured (Step 12 provides a quick way to set this up) in a
      browser and confirming the sidebar, feed, grid, list, and modal all render with
      the new rules applied (no unstyled/broken layout), plus confirm accordion
      expand/collapse now actually works now that the class target matches.

- [ ] 12. Bump the plugin version to 4.0.0 everywhere else, and update `readme.txt`.
      - `wp-taxonomy-grid.php`: change the docblock `Version:` header from `3.9.1` to `4.0.0`.
      - `composer.json` (this plugin's, not the repo root): change `"version": "3.9.1"` to `"4.0.0"`.
      - `readme.txt`: change `Stable tag: 3.9.1` to `Stable tag: 4.0.0`; add a new
        `= 4.0.0 =` changelog entry (dated with today's actual date, verified via the
        shell, not a placeholder) describing: the Source Mode toggle, the Sections
        repeater and its 4 configured-section example, the AJAX re-query search
        decision for flat_list sections, the reusable modal, the generic load-more
        handler, the `_video_external_url` meta key and its admin meta box, and the
        `section_post_type` non-empty-default choice from Step 6. Add a new `==
        Sections / Content Navigator ==` usage section (after the existing `== Usage
        ==` section) documenting: how to switch Source Mode, each of the 8 repeater
        fields and what they do, the 4 required section configurations
        (Berita Terbaru / Pengumuman / Galeri Foto / Galeri Video) as a worked
        example table (post type, taxonomy, content style, click behavior — mirrors
        `design.md`'s own "Why the 4 required sections map onto this schema" table),
        and how to set `_video_external_url` on a `videos` post for the redirect
        behavior.
      Files: `wp-taxonomy-grid.php`, `plugins-custom/wp-taxonomy-grid/composer.json`, `readme.txt`.
      Verify: `grep -c "4.0.0" wp-taxonomy-grid.php composer.json readme.txt` (or open
      each file) shows the new version string in all three; `php -l wp-taxonomy-grid.php`
      still passes.

- [ ] 13. Full syntax/lint/static-analysis pass across both PHP files.
      Run, from the repository root (`c:/projects/imigrasi-wp`):
      `php -l plugins-custom/wp-taxonomy-grid/wp-taxonomy-grid.php`,
      `php -l plugins-custom/wp-taxonomy-grid/includes/class-elementor-widget.php`,
      `php -l plugins-custom/wp-taxonomy-grid/includes/class-video-meta-box.php`
      (each must print "No syntax errors detected"). Then, since `vendor/` does not
      currently exist in this repo, run `composer install` once from the repo root,
      then `composer lint` (phpcs, using the repo's `phpcs.xml.dist`, which already
      covers `plugins-custom`) and `composer analyse` (phpstan level 5, using
      `phpstan.neon.dist`, which already covers `plugins-custom`). Per
      `requirements.md` AC12, a clean run is the goal but is **not** a hard blocker on
      completion if `composer install` cannot complete in this environment (e.g. no
      network access) — if so, state that explicitly in the final summary instead of
      silently skipping it.
      Files: none (verification only).
      Verify: `composer lint` and `composer analyse` report no NEW errors in
      `plugins-custom/wp-taxonomy-grid/` compared to a run on the pre-change code
      (pre-existing errors elsewhere in the repo, if any, are not this task's
      responsibility).

---

## Manual / acceptance-criteria checks (not automatable by this agent)

These map to `requirements.md`'s Acceptance Criteria 1–10, 13–14 and
`design.md`'s "Testability" → "Manual/E2E only" list. Record results in the
final summary; they don't block the steps above, but should be spot-checked
once Step 13 passes:

1. Services page (legacy `single` mode): unchanged HTML and still works.
2. Sections mode with the 4 required rows: sidebar shows 2 flat entries + 2
   accordions in order.
3. Berita Terbaru item click swaps the content panel in place, no navigation.
4. "Lihat Lebih Banyak" appends without reloading existing items.
5. Berita Terbaru search finds an item not yet paginated in.
6. Pengumuman item click opens the modal; Escape/backdrop-click closes it.
7. Galeri Foto accordion expand/collapse now works (Step 11's fix).
8. Galeri Foto thumbnail click opens the modal with the image template.
9. Galeri Video: external URL → new-tab redirect; self-hosted → video modal;
   neither → post-detail modal fallback.
10. Every new `wp_ajax_wptg_*` action is reachable logged-out and rejects a
    missing/invalid nonce or a mismatched post/term/taxonomy.
