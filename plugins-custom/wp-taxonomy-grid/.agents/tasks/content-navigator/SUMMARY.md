# Content Navigator — Final Summary (Taxonomy Grid v4.0.0)

Status: **APPROVED** (`review.json` verdict `APPROVED`, 0 findings). No commit has been made — see "Commit" at the end.

## What was built

A new "Source Mode" on the existing `wp-taxonomy-grid` Elementor widget: `Single` (today's behavior, default) or `Sections` (the new Content Navigator). In `Sections` mode, the widget renders a sidebar with N configurable sections plus one shared content panel, backed by:

- **Sections repeater** (Content tab): one row per sidebar entry, with 8 fields — label, section type (`flat_list` vs `taxonomy_accordion`), post type, taxonomy (accordion only), content style, click behavior, enable-search toggle, and items-per-page.
- **4 section behaviors**, each a combination of the schema above:
  - *Berita Terbaru* — `flat_list` / `news` / numbered feed / click swaps the inline detail panel / search on.
  - *Pengumuman* — same as above but `announcements`, and clicking opens the floating modal instead of swapping inline.
  - *Galeri Foto* — `taxonomy_accordion` / `gallery` + `gallery_category` / grid of thumbnails / click opens the modal with the image.
  - *Galeri Video* — `taxonomy_accordion` / `videos` + `videos_category` / vertical list with a play-icon overlay / click redirects to an external URL, opens a video modal, or falls back to a post-detail modal.
  - A 5th style, `single_detail` (full post detail, same shape as the legacy "services" page), is implemented but not required by any of the 4 rows above — it lets an editor rebuild a "services"-like section through the new UI if ever needed.
- **Modal component** (`assets/js/modal.js`, new) — a single reusable lightbox (`window.WptgModal`), lazily built on first use, with `open('image'|'post'|'video', payload)` / `close()`. Escape, backdrop click, and the close button all close it; closing clears the body so a playing `<video>` doesn't keep running off-screen.
- **Generic AJAX load-more / re-query handler** — one new action, `wptg_query_items` (registered for both logged-in and guest requests), backs every flat-list and taxonomy-accordion section's pagination, term switch, and search re-query. It is not duplicated per section.
- **Backward-compat path** — the legacy single post-type + taxonomy path is untouched; see next section.

## Backward-compatibility mechanism

A new Content-tab control, `wptg_source_mode` (`single` default, `sections`), gates everything. In `render()`, `includes/class-elementor-widget.php` reads `$settings['wptg_source_mode'] ?? 'single'` and only branches into the new `wptg_render_sections_widget()` when it equals `'sections'`; otherwise execution falls through to the existing `$raw_atts` build and `wptg_resolve_and_render()` call, unchanged.

Because Elementor only persists non-default control values, every widget instance saved before this feature existed has no stored `wptg_source_mode` value, so `get_settings_for_display()` fills in the control's own default (`'single'`) and takes the exact same branch it always has.

Confirmed unchanged for the legacy `services` page:
- `wptg_post_type`, `wptg_taxonomy`, `wptg_mode`, `wptg_active_first`, and all Content/Style-tab sections that needed a `condition` now also require `wptg_source_mode = single` — this only affects which fields the *editor UI* shows, never a stored value or the rendered front-end HTML.
- `register_style_container_controls()` has no added condition on purpose — the Sections markup deliberately reuses the same `.wptg-wrapper` / `.wptg-body` / `.wptg-sidebar` classes, so Container styling stays active in both modes.
- `wptg_render_single_post_detail()` gained a `bool $include_meta = false` parameter; the legacy call site never passes it, so its output is byte-identical to before. `wptg_ajax_get_single_post()` only turns `include_meta` on when the client explicitly sends `include_meta: '1'`, which the legacy JS call site never does.
- The two enqueued script/style versions moved from `3.9.1` to `4.0.0` and `wptg-scripts` gained `wptg-modal` as a dependency — the only observable difference for Single mode is the version query string on the asset URLs.

## Assumptions from ambiguous points in the original brief

Documented in `design.md` and carried through plan/review unchanged:

- **Search on paginated flat-list sections (Berita Terbaru / Pengumuman):** resolved as a fresh AJAX re-query (page reset to 1), not a client-side filter over already-loaded DOM nodes. A DOM filter can only search items already paged in, so a match on page 3 before it's loaded would silently return nothing. The taxonomy-accordion sidebar's own search (Galeri Foto/Video, when enabled) stays a DOM filter instead, because that list is rendered up front and never paginated — same approach the legacy sidebar already uses.
- **Video external-URL meta key:** `_video_external_url`, a protected (leading-underscore) post meta field on the `videos` post type, set through a new native WordPress meta box (`includes/class-video-meta-box.php`), not ACF — avoids needing to import/configure an ACF field group per environment. `wptg_render_item_list_thumbnail()` checks it first; if empty, it falls back to an attached video file, then to a plain post-detail modal if neither exists.
- **Modal content-type switch:** one shared `WptgModal.open(type, payload)` with `type` ∈ `image` / `post` / `video`, picked per item via a `data-modal-type` attribute set server-side at render time (never computed client-side). `image`/`video` payloads are built through `createElement`/`setAttribute`/`textContent`; only the `post` payload uses `innerHTML`, because it's already-escaped trusted HTML from the same nonce-protected AJAX endpoint the legacy detail view already trusts the same way.
- **File organization:** the video meta box went into its own new file (`includes/class-video-meta-box.php`) because it's an admin-only concern unrelated to either shared render logic or Elementor widget controls — it didn't fit cleanly into either of the two existing files. The modal component went into its own new file (`assets/js/modal.js`) because it's reused across 3 sections and has no dependency on the grid/accordion logic.

## Files changed

| File | Change |
|---|---|
| `wp-taxonomy-grid.php` | New Sections rendering engine (`wptg_render_sections_widget()` and its helpers), the new `wptg_ajax_query_section_items()` AJAX handler, `wptg_render_single_post_detail()` gained `$include_meta`, enqueue block updated (new `wptg-modal` script + version bumps), version header bumped to `4.0.0`. |
| `includes/class-elementor-widget.php` | New `wptg_source_mode` control, new `Sections` repeater + `register_sections_controls()`, new `render()` dispatch branch, `condition` keys added to 5 existing Content-tab methods and 9 of 10 existing Style-tab methods, 6 new Style-tab sections for Sections-mode styling. |
| `includes/class-video-meta-box.php` **(new)** | `_video_external_url` meta box (register/render/save) for the `videos` post type. |
| `assets/js/modal.js` **(new)** | `WptgModal` lightbox component. |
| `assets/js/taxonomy-grid.js` | New `initSectionsMode()` and its helpers (`handleItemClick`, `fetchSectionItems`, `loadSinglePostIntoModal`, per-input-debounced search, etc.); 3 behavior-preserving refactors (`bindAccordionToggle()`, `filterTermGroup()` extraction, generalized 4-arg `loadSinglePost()`). |
| `assets/css/taxonomy-grid.css` | New `SECTIONS MODE` and `MODAL (shared lightbox)` blocks, plus matching responsive rules. |
| `readme.txt` | New "Sections / Content Navigator" usage section + `= 4.0.0 =` changelog entry; `Stable tag: 4.0.0`. |
| `composer.json` (plugin) | `"version": "4.0.0"`. |

`web/app/mu-plugins/custom-taxonomies.php` was **not** touched (confirmed by content match and file timestamp predating every other changed file).

## Version

**4.0.0**, bumped in: the plugin docblock `Version:` header, the `wptg-styles` enqueue version string, the new `wptg-modal` enqueue version string, the `wptg-scripts` enqueue version string, the plugin's `composer.json`, and `readme.txt`'s `Stable tag` plus its new `= 4.0.0 =` changelog entry.

## Manual verification steps (human operator)

1. **Sync the plugin first**, in your WSL terminal:
   ```
   ./scripts/sync-plugin.sh wp-taxonomy-grid
   ```

2. **Legacy services page (Source Mode = Single):** open the existing services page. Confirm the category accordion in the sidebar still expands/collapses, and clicking a post still swaps the detail panel via AJAX exactly as before. *Success = no visual or behavioral change from before this work.*

3. **Berita Terbaru:** confirm the numbered feed of `news` posts loads with no category sidebar. Type a search term — including a word that only matches a post deep in the list, not yet paginated in — and confirm it's found. Click "Lihat Lebih Banyak" and confirm more items append without a page reload. Click an item and confirm it swaps into the inline detail view in the same panel (no modal, no navigation). *Success = feed loads, search finds unpaginated matches, load-more appends, click swaps inline.*

4. **Pengumuman:** same list/search/load-more behavior as Berita Terbaru. Click an item and confirm the floating modal opens instead, showing the announcement's image, title, date, and content. Confirm Escape, clicking the backdrop, and clicking the X all close it. *Success = modal opens with full content and all 3 close methods work.*

5. **Galeri Foto:** confirm the sidebar accordion expands by `gallery_category`. Confirm a grid of thumbnails loads for the selected category, and "Lihat Lebih Banyak" appends more. Click a photo and confirm the same modal opens showing that image. *Success = accordion expands, grid loads/paginates, modal shows the clicked photo.*

6. **Galeri Video:** confirm the sidebar accordion expands by `videos_category`. Confirm a single-column list with a play-icon overlay loads, and load-more appends. Click a video that has `_video_external_url` set and confirm it opens that URL in a new tab. Click a video without that meta set and confirm the modal opens instead with an HTML5 video player. *Success = accordion expands, list loads/paginates, external-URL videos open a new tab, others play in the modal.*

## Nothing was committed

Per this workspace's `AGENTS.md`, no `git add` / `git commit` / `git push` was run. Please review the diff (the entire `plugins-custom/wp-taxonomy-grid/` directory is currently untracked) and commit manually once the manual checks above pass.

Suggested commit message: `add: content navigator multi-section widget to wp-taxonomy-grid`
