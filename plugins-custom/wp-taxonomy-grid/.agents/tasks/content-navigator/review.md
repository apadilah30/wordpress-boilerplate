# Review — "Content Navigator" (Taxonomy Grid v4.0.0)

Reviewed against `plan.md` (13 steps) and `design.md` (incl. its "Open
design-review findings" corrections 1–7 and round-2 findings 1–9). No git
history exists for this plugin or for `web/app/mu-plugins/custom-taxonomies.php`
(both are untracked in the repo — `git log --oneline -- <path>` returns
nothing for either), so there is no commit diff to read; the review reads
the actual current source files directly. Verdict is also cross-checked
against `verification.md`'s recorded `php -l` / `node --check` runs and
manual code trace (no live WP/Elementor session was available to the coder
either, consistent with this review's own constraints).

## 1. Legacy `services` page path (Source Mode = Single) — backward compatibility

- `includes/class-elementor-widget.php::render()` (line ~1625): reads
  `$source_mode = $settings['wptg_source_mode'] ?? 'single'`; only branches
  into `wptg_render_sections_widget()` when `=== 'sections'`, then
  `return`s. Everything below (the `$raw_atts` array build +
  `wptg_resolve_and_render($raw_atts, $instance_id)`) is reached unchanged
  for Single mode and is textually identical to the pre-existing code shown
  in `design.md`'s "In `render()`" snippet.
- `wptg_post_type`, `wptg_taxonomy`, `wptg_mode`, `wptg_active_first` and
  all 9 other Content/Style-tab sections that needed a condition
  (`register_header_content_controls()`, `register_breadcrumb_content_controls()`,
  `register_search_content_controls()`, `register_grid_controls()`, and the
  5 previously-unconditioned Style sections) all carry
  `'wptg_source_mode' => 'single'` alongside their pre-existing condition
  (confirmed by reading each method). `register_style_container_controls()`
  has no added condition, confirmed, so it stays active in both modes as
  designed (the Sections markup deliberately reuses `.wptg-wrapper`/
  `.wptg-body`/`.wptg-sidebar`).
- `wptg_render_single_post_detail(int $post_id, bool $include_meta = false)`:
  default `false` preserves the exact pre-existing markup
  (`wptg_render_detail_mode()`'s call site is unchanged — still a bare
  `wptg_render_single_post_detail($default_active_id)`).
  `wptg_ajax_get_single_post()` only sets `include_meta = true` when
  `$_POST['include_meta'] === '1'`; the legacy JS call site
  (`initDetailMode()`'s 3-arg `loadSinglePost(wrapper, postId, postType)`)
  never sends that field, so the AJAX response stays byte-identical.
- Enqueue: `wptg-styles` and `wptg-scripts` both bumped to `'4.0.0'`;
  `wptg-scripts` now depends on `['wptg-modal']`; `wp_localize_script()`
  call is untouched. This changes nothing observable for Single mode other
  than the version query string.
- Conclusion: the legacy single post_type+taxonomy+mode path is unchanged
  byte-for-byte, matching plan Step 7's explicit requirement.

## 2. AJAX endpoints

| Action | nopriv registered | Nonce | Server-side re-validation |
|---|---|---|---|
| `wptg_get_single_post` | yes (`wp_ajax_nopriv_wptg_get_single_post`) | `check_ajax_referer('wptg_nonce', 'nonce')` | `get_post($post_id)` then `$post->post_type === $post_type && $post->post_status === 'publish'` — rejects a mismatched post/type |
| `wptg_get_posts` | yes | same | `get_post_type_object($post_type)` + `taxonomy_exists($taxonomy)` (pre-existing, unchanged) |
| `wptg_query_items` (new) | yes (`wp_ajax_nopriv_wptg_query_items`) | same | `get_post_type_object()->public`; `content_style` whitelist (`numbered_feed`/`grid_thumbnail`/`list_thumbnail`, excludes `single_detail`); when `taxonomy` is non-empty: `taxonomy_exists()` + `in_array($post_type, $taxonomy_obj->object_type, true)` (taxonomy actually attached to that post type) + (`term_id > 0` only) `get_term($term_id, $taxonomy) instanceof WP_Term` (term genuinely belongs to that taxonomy) |
| Video meta save (`save_post_videos`, not AJAX but same class of risk) | n/a (admin screen only) | `wptg_video_meta_save`/`wptg_video_meta_nonce` via `wp_verify_nonce()` | `wp_is_post_autosave()`/`wp_is_post_revision()` guard, `current_user_can('edit_post', $post_id)`, `esc_url_raw()` before `update_post_meta()` |

All client-supplied `post_type`/`taxonomy`/`term_id`/`post_id` values are
re-validated server-side rather than trusted; none of the new or modified
handlers skips the `nopriv` registration. This satisfies the task's
guest-access + nonce + ownership-revalidation requirement in full,
including the two MEDIUM findings design.md flagged against an earlier
draft (Finding 3: `wptg_ajax_query_section_items()` does NOT rely on
`wptg_ajax_get_posts()`'s weaker validation — it performs the
taxonomy-registered-for-post-type and term-belongs-to-taxonomy checks
itself).

## 3. Duplicate-code check

- **Single-post detail render**: genuinely shared — `wptg_render_single_post_detail()`
  is the one function used by legacy detail mode (`include_meta = false`,
  unchanged call site), the new `single_detail` content-style panel
  (`include_meta = true`), and both AJAX call sites. Not duplicated.
- **Generic AJAX query/paginate**: one new handler, `wptg_ajax_query_section_items()`,
  backs every flat_list/grid_thumbnail/list_thumbnail section (switch,
  load-more, and flat search-requery) — not one handler per section.
- **Search filter JS**: `filterTermGroup(groupEl, query)` was extracted
  from the legacy `filterSidebar()`'s inner loop and is now called by both
  the unchanged `filterSidebar()` and the new `filterWithinSectionGroup()`.
  Confirmed in `assets/js/taxonomy-grid.js`: `filterSidebar()`'s own
  signature/behavior is unchanged, now delegating per-group work to the
  shared helper.
- **Accordion toggle JS**: `bindAccordionToggle(toggleEl, groupEl)` is a new
  shared helper, called by both `initDetailMode()`'s existing
  `.wptg-term-toggle` listener and `initSectionsMode()`'s two accordion
  listeners (section-level `.wptg-section-toggle` and single_detail's
  nested `.wptg-term-toggle`). Not duplicated.
- **Accordion sidebar PHP render** (`wptg_render_detail_mode()`'s
  non-merged term-group branch vs. `wptg_render_section_sidebar_group()`'s
  `single_detail` branch): these two blocks are structurally similar
  (term accordion → nested post list) but are NOT byte-level duplicates —
  different wrapper elements (`<div class="wptg-term-group">` vs.
  `<li class="wptg-section-term-li">`), different decorative markup
  (legacy has `.wptg-term-head-left`/`.wptg-term-divider-accordion` spans
  that Sections mode omits), and different active-state bookkeeping (N
  sections vs. one legacy sidebar). `design.md` presents both as separate
  code blocks and never proposes extracting a shared PHP renderer for this
  piece — only the client-side toggle behavior and the single-post-detail
  render are called out as shared in the design. Since this is the
  approved design's own trade-off (not a deviation introduced by the
  coder), it is not raised as a blocking finding here, per the instruction
  to honor approved behavior/trade-offs.
- **Grid-style item rendering**: legacy `wptg_get_posts_html()` (full
  Elementor-content-or-excerpt card with a "View Details" link) and new
  `wptg_render_item_grid_thumbnail()` (photo-focused card, modal click)
  render different content for different purposes; they share only the
  `.wptg-post-item` CSS box class, which is the explicitly-designed reuse
  (not a missed extraction opportunity).

## 4. Escaping / types / naming conventions

- All 3 PHP files (`wp-taxonomy-grid.php`, `includes/class-elementor-widget.php`,
  `includes/class-video-meta-box.php`) declare `strict_types=1` and use
  typed method/function signatures throughout the new code.
- Every new global function is `wptg_`-prefixed (`grep -E '^function (?!wptg_)'`
  across the plugin returns no matches).
- Dynamic output is escaped consistently: `esc_html()`/`esc_attr()`/`esc_url()`
  on every interpolated value in the new renderers
  (`wptg_render_section_sidebar_group()`, `wptg_render_section_content_panel()`,
  `wptg_render_item_*()`, `wptg_build_item_attrs()`); `wptg_build_item_attrs()`'s
  `$extra` array is documented and used as pre-escaped-by-caller only, and
  every caller does escape before passing it in (`esc_url()` for
  `data-image-src`/`data-external-url`/`data-video-src`/`data-poster`,
  `esc_attr()` for `data-item-title`/`data-item-date`). No `@` suppression
  anywhere in the new code.
- `modal.js` builds `'image'`/`'video'` DOM via `createElement`/`setAttribute`/
  `textContent` only; `innerHTML` is used only for the `'post'` payload,
  which is the already-escaped trusted HTML from
  `wptg_render_single_post_detail()` via the nonce-protected
  `wptg_get_single_post` AJAX action — the same trust boundary the
  pre-existing `loadSinglePost()` already relies on.
- New CSS classes are all `wptg-`-prefixed (`.wptg-sections-sidebar`,
  `.wptg-section-group`, `.wptg-feed-*`, `.wptg-photo-*`,
  `.wptg-list-thumbnail-*`, `.wptg-load-more`, `.wptg-modal*`, etc.).
- The 6 new Style-tab sections (`register_style_sections_nav_controls()`
  through `register_style_modal_controls()`) all reuse this class's
  existing control-building helpers (`add_text_style()`,
  `add_badge_box_controls()`, `add_box_control()`, `add_spacing_controls()`,
  `add_bg_control()`, `add_alignment_control()`, `Group_Control_Border`)
  with the exact selectors `design.md`'s reuse table specifies — no
  control-building code is duplicated; the two plain `COLOR`/`SLIDER`
  controls for the modal close button and the play-icon CSS-variable
  controls are the only ad-hoc controls, and `design.md` explicitly says no
  existing helper fits those exactly.

## 5. Versioning and documentation

- `wp-taxonomy-grid.php` docblock: `Version: 4.0.0`.
- All 3 enqueued asset version strings (`wptg-styles`, `wptg-modal`,
  `wptg-scripts`) are `'4.0.0'`.
- `composer.json` (plugin-local): `"version": "4.0.0"`.
- `readme.txt`: `Stable tag: 4.0.0`, plus a `= 4.0.0 =` changelog entry.
- `readme.txt` documents, under a new "== Sections / Content Navigator ==”
  section: the Source Mode switch, all 8 repeater fields, a worked table of
  the 4 required section configurations (section_type/post_type/taxonomy/
  content_style/click_behavior/search), the `section_content_style`
  fallback behavior, the AJAX-requery-vs-DOM-filter search decision, the
  `_video_external_url` meta key and its admin meta box, the reusable
  modal, and an explicit migration note that existing widget instances are
  unaffected (Elementor's own default-value persistence behavior).

## 6. `custom-taxonomies.php`

- File content read directly: registers `services_category`, `news_category`,
  `gallery_category`, `videos_category`, `information_category`,
  `about_category` — matches `design.md`'s claim exactly, including that
  `announcements` has no taxonomy registered. This is consistent with
  `design.md`'s corrected (round-2) non-edit justification.
- No git history exists for this file (untracked), so a commit-diff check
  is not possible; filesystem timestamp evidence was used instead: this
  file's last-write time (2026-10-30 15:52) predates every other file
  touched by this task (15:52 vs. 16:06–16:17 for the plugin's own files),
  consistent with it not having been touched during this work session.
  Combined with the file's content matching `design.md`'s description
  verbatim, there is no evidence of an edit to this file.

## 7. Verification evidence

`verification.md` records `php -l` passing on all 3 PHP files and
`node --check` passing on both JS files, plus a detailed manual code trace
covering the legacy path, all 4 reference sections, AJAX security, and
versioning. `composer lint`/`composer analyse` could not be run in that
environment and this is stated explicitly, which is an accepted carve-out
per `plan.md` Step 13 (AC12) when `composer install` cannot complete (no
`vendor/` present, composer invocation produced no usable output). No live
WP/Elementor browser session was available to either the coder or this
review, so the plan's "Manual / acceptance-criteria checks" items 1–10
remain visual/E2E-only per `design.md`'s own "Testability" section; this is
consistent with the task's constraints, not a gap introduced by the coder.

## Conclusion

Every plan step (1–13) and every design correction (round-2 findings 1–9,
the "Open design-review findings" HIGH/MEDIUM/NIT 1–7) is implemented as
specified, read directly in the source. The legacy Single-mode path is
preserved byte-for-byte. All AJAX endpoints are guest-reachable, nonce
checked, and re-validate ownership server-side. No problematic duplicate
logic was found; the one structurally-similar-but-not-identical block
(accordion sidebar PHP markup) is the design's own accepted trade-off, not
a coder deviation. Escaping, strict types, and naming conventions are
consistent. Versioning and `readme.txt` documentation are complete and
consistent. `custom-taxonomies.php` was not modified.

No blockers found.
