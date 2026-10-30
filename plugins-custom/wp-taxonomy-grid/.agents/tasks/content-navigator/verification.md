# Verification — "Content Navigator" (Taxonomy Grid v4.0.0)

Date: 2026-10-30

This records the exact commands run locally and a manual code trace, per
the task instructions (no live Docker/WP/Elementor environment is
reachable from this agent).

## 1. PHP syntax (`php -l`)

Environment: `PHP 8.3.1 (cli) (Zend Engine v4.3.1, with Xdebug v3.3.2)`.
Run from `plugins-custom/wp-taxonomy-grid/`:

```
> php -l wp-taxonomy-grid.php
No syntax errors detected in wp-taxonomy-grid.php

> php -l includes\class-elementor-widget.php
No syntax errors detected in includes\class-elementor-widget.php

> php -l includes\class-video-meta-box.php
No syntax errors detected in includes\class-video-meta-box.php
```

Result: **PASS** — all 3 PHP files (plan.md steps 1, 4, 5, 6, 7, 8, 9, 12).

## 2. JS syntax (`node --check`)

Environment: `node v22.14.0`. Run from `plugins-custom/wp-taxonomy-grid/`:

```
> node --check assets\js\modal.js
(no output — exit code 0)

> node --check assets\js\taxonomy-grid.js
(no output — exit code 0)
```

`node --check` prints nothing on success (only on a syntax error), so the
absence of output together with a successful chained `&&` continuation
(verified by appending `&& echo MODAL_OK` / `&& echo GRID_OK`, both of
which printed) confirms both files parse cleanly.

Result: **PASS** — both JS files (plan.md steps 2, 10).

## 3. Composer lint/analyse (`composer lint` / `composer analyse`)

Per `requirements.md` AC12 and plan.md Step 13, this is **not** a hard
blocker if it cannot be run in this environment, provided that is stated
explicitly — stating it here:

`composer` is present on `PATH` (`where composer` resolves to
`C:\ProgramData\ComposerSetup\bin\composer(.bat)` and a second Laragon
copy), but invoking it in this shell (`composer --version`, and the
full-path `.bat` form) produced no output and no usable exit status in
this environment/tool combination — it could not be made to run
successfully here. The repository root `vendor/` directory does not
exist, so `composer install` was never reached either. **`composer
lint` (phpcs) and `composer analyse` (phpstan) could not be run in this
environment.** This does not block completion per AC12/Step 13's explicit
carve-out.

## 4. Manual code trace (no live WP/Elementor environment available)

Performed by reading the actual committed source, not by re-deriving from
design.md's prose. Confirmed:

### 4a. Legacy `services` page path (Source Mode = Single) — unaffected
- `wp-taxonomy-grid.php` `render()` entry point (`includes/class-elementor-widget.php`
  line ~1627): reads `$source_mode = $settings['wptg_source_mode'] ?? 'single'`;
  only when `=== 'sections'` does it call `wptg_render_sections_widget()` and
  `return`. Everything below that branch (`$raw_atts` build +
  `wptg_resolve_and_render($raw_atts, $instance_id)`) is reached unchanged
  for Single mode, byte-for-byte identical to the pre-existing code.
- `wptg_post_type`, `wptg_taxonomy`, `wptg_mode`, `wptg_active_first` and
  the Header/Breadcrumb/Search/Grid Content-tab sections all carry the new
  `'wptg_source_mode' => 'single'` condition key alongside their pre-existing
  condition (confirmed by `grep` across `class-elementor-widget.php`) — this
  only affects which controls the editor UI shows, never a stored value or
  rendered output.
- `register_style_container_controls()` has no added condition (confirmed by
  reading), so Container stays visible/active in both modes as planned; all
  9 other Style-tab sections that needed a condition have one.
- Enqueue: `wptg-styles`/`wptg-scripts` both bumped to version `4.0.0`;
  `wptg-scripts` now depends on `wptg-modal` (`['wptg-modal']`); `wp_localize_script`
  call is untouched.

### 4b. Berita Terbaru (flat_list, news, numbered_feed, inline_detail, search, AJAX load-more)
- `wptg_render_sections_widget()` normalizes the row via
  `wptg_normalize_section_row()`, counts published `news` posts via
  `wptg_count_published_posts()`, and (if it's the default-active section)
  renders page 1 server-side via `wptg_render_section_content_panel()` →
  `wptg_query_section_items()` → `wptg_render_item_numbered_feed()` per post.
- Click (`inline_detail`): `handleItemClick()` in `taxonomy-grid.js` calls
  `setActiveItem()` + `loadSinglePost(wrapper, postId, postType, true)` (4-arg
  form, `includeMeta = true`), which posts to the existing `wptg_get_single_post`
  AJAX action with `include_meta: '1'` — server passes this through to
  `wptg_render_single_post_detail($post_id, true)`, confirmed in
  `wptg_ajax_get_single_post()`.
- Load more: `.wptg-load-more` click → `fetchSectionItems(..., append=true)` →
  POSTs `action=wptg_query_items` → `wptg_ajax_query_section_items()` (both
  `wp_ajax_wptg_query_items` and `wp_ajax_nopriv_wptg_query_items` registered,
  confirmed by grep) → appends `data.data.html`, removes the load-more button
  once `has_more` is false.
- Search: per-input-debounced (`WeakMap`-based) listener resets `page=1`,
  `termId=0`, sets `search`, and calls `fetchSectionItems(..., append=false)` —
  i.e. a fresh AJAX re-query including not-yet-paginated items, matching the
  documented FR-9 decision and AC5.

### 4c. Pengumuman (flat_list, announcements, numbered_feed, modal, search, AJAX load-more)
- Same renderer/AJAX/search/load-more path as 4b, with `click_behavior = modal`.
  `wptg_render_item_numbered_feed()` sets `data-modal-type="post"` when
  `click_behavior === 'modal'`. Client `handleItemClick()`'s `modal` branch with
  `modalType !== 'image'` calls `loadSinglePostIntoModal(postId, postType)`,
  which POSTs `wptg_get_single_post` with `include_meta: '1'` and, on success,
  calls `WptgModal.open('post', { html: data.data.html })`.
- `modal.js`'s `'post'` branch assigns `payload.html` via `innerHTML` (the
  same trust boundary `loadSinglePost()` already relies on). Escape key and a
  click on `.wptg-modal-backdrop` (not the panel) both call `close()`, which
  hides the modal and clears `.wptg-modal-body`.

### 4d. Galeri Foto (taxonomy_accordion, gallery/gallery_category, grid_thumbnail, modal on each photo, AJAX load-more)
- `wptg_render_section_sidebar_group()` renders a `.wptg-section-toggle` +
  `.wptg-section-terms` accordion using `wptg_get_terms_for_post_type('gallery_category', 'gallery')`
  (confirmed reused, not duplicated). The `open` class is placed on
  `.wptg-section-group` (section-level) and `.wptg-section-term-li`
  (term-level) ancestors, never on the inner `<ul>` — confirmed in the
  rendered markup, matching the CSS rules
  `.wptg-section-group:not(.open) .wptg-section-terms` /
  `.wptg-section-term-li:not(.open) .wptg-term-posts` in `taxonomy-grid.css`.
  `bindAccordionToggle()` toggles exactly this ancestor class and flips
  `aria-expanded`, so expand/collapse works.
- Content: `grid_thumbnail` → `wptg_section_list_class()` returns
  `wptg-posts-grid` (reuses the existing grid CSS, no new layout rules).
  `wptg_render_item_grid_thumbnail()` sets `data-modal-type="image"`,
  `data-image-src`, `data-item-title`, `data-item-date` when
  `click_behavior === 'modal'`. Client's `handleItemClick()` `modal` branch
  with `modalType === 'image'` calls `WptgModal.open('image', { src, title, date })`,
  which builds an `<img>` + caption via `createElement`/`setAttribute`/`textContent`.
- Load more: identical generic `fetchSectionItems()`/`wptg_ajax_query_section_items()`
  path as 4b/4c, parameterized by this section's `post_type`/`taxonomy`/`term_id`.

### 4e. Galeri Video (taxonomy_accordion, videos/videos_category, list_thumbnail, redirect_or_popup_player, AJAX load-more)
- Same accordion sidebar shape as 4d, parameterized by `videos`/`videos_category`.
- Content: `list_thumbnail` → `wptg_section_list_class()` returns
  `wptg-list-thumbnail`. `wptg_render_item_list_thumbnail()` implements the
  3-way branch for `click_behavior === 'redirect_or_popup_player'`:
  1. `get_post_meta($post->ID, '_video_external_url', true)` non-empty →
     `data-external-url` set (esc_url'd) → client `handleItemClick()`'s
     `redirect_or_popup_player` branch does `window.open(url, '_blank', 'noopener')`.
  2. Empty URL + `get_attached_media('video', $post->ID)` returns a video →
     `data-modal-type="video"` + `data-video-src` (+ optional `data-poster`
     from the featured image) → client opens `WptgModal.open('video', { src, poster })`,
     which builds a `<video controls playsinline>` via `setAttribute`.
  3. Neither → `data-modal-type="post"` (graceful fallback) →
     `loadSinglePostIntoModal()` → `WptgModal.open('post', { html })`.
  Confirmed this exact 3-way priority order in
  `wptg_render_item_list_thumbnail()`'s source.
- `_video_external_url` is managed via `includes/class-video-meta-box.php`'s
  "External Video URL" meta box, registered unconditionally (no Elementor
  dependency) on `add_meta_boxes` for the `videos` post type only; save
  handler guards autosave/revision, verifies the
  `wptg_video_meta_save`/`wptg_video_meta_nonce` nonce, checks
  `current_user_can('edit_post', $post_id)`, then `esc_url_raw()`s before
  `update_post_meta()`.

### 4f. AJAX security (NFR-1 / NFR-2 / AC10)
All 3 actions (`wptg_get_single_post`, `wptg_get_posts`, `wptg_query_items`)
are registered on both `wp_ajax_{action}` and `wp_ajax_nopriv_{action}`
(confirmed by grep — guest-reachable). `wptg_ajax_query_section_items()`
(backing the 4 new sections) calls `check_ajax_referer('wptg_nonce', 'nonce')`
first, then re-validates: `get_post_type_object($post_type)` existence +
`->public`; `content_style` whitelist (excludes `single_detail`, which never
calls this action); when a taxonomy is supplied, `taxonomy_exists()` +
`in_array($post_type, $taxonomy_obj->object_type, true)` (taxonomy actually
registered for this post type) + `get_term($term_id, $taxonomy) instanceof WP_Term`
(term genuinely belongs to that taxonomy) — every check written fresh in this
function rather than assumed from `wptg_ajax_get_posts()`.

### 4g. Versioning consistency (AC11)
`wp-taxonomy-grid.php` docblock `Version: 4.0.0`; `wp_enqueue_style('wptg-styles', ..., '4.0.0')`;
`wp_enqueue_script('wptg-modal', ..., '4.0.0', true)`; `wp_enqueue_script('wptg-scripts', ..., '4.0.0', true)`;
plugin `composer.json` `"version": "4.0.0"`; `readme.txt` `Stable tag: 4.0.0`
plus a `= 4.0.0 =` changelog entry — all consistent (confirmed by reading
each file directly).

### 4h. readme.txt documentation (AC per task brief)
Confirmed present: the Source Mode switch, all 8 Sections repeater fields,
a worked table mapping Berita Terbaru / Pengumuman / Galeri Foto / Galeri
Video to their `section_type`/`post_type`/`taxonomy`/`content_style`/
`click_behavior`/search settings, the AJAX-re-query-vs-DOM-filter search
decision for flat_list sections, the `_video_external_url` meta key and
its admin meta box, and a migration note confirming existing single-source
widget instances are unaffected.

## 5. Not independently re-run (out of scope / unavailable here)

- No live WP/Elementor browser session — the Elementor-editor-side manual
  checks in plan.md's "Manual / acceptance-criteria checks" (items 1–10,
  e.g. visually confirming the Content tab, live-preview CSS, actual
  accordion click in a browser) could not be executed; they are covered
  above only via static code trace, not an actual rendered/interactive
  session.
- `composer install`/`composer lint`/`composer analyse` — see Section 3.

## Summary

| Check | Status |
|---|---|
| `php -l` × 3 files | PASS |
| `node --check` × 2 files | PASS |
| `composer lint`/`composer analyse` | Could not run in this environment (stated per AC12; not a blocker) |
| Manual trace: legacy Single mode unaffected | PASS (code trace) |
| Manual trace: Berita Terbaru (flat_list/news) | PASS (code trace) |
| Manual trace: Pengumuman (flat_list/announcements/modal) | PASS (code trace) |
| Manual trace: Galeri Foto (taxonomy_accordion/gallery/modal-image) | PASS (code trace) |
| Manual trace: Galeri Video (taxonomy_accordion/videos/redirect-or-popup) | PASS (code trace) |
| Manual trace: AJAX nonce + guest + ownership re-validation | PASS (code trace) |
| Versioning consistency | PASS |
