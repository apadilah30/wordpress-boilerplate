# Requirements — "Content Navigator" (Taxonomy Grid v4)

## Summary

Generalize the existing `wp-taxonomy-grid` Elementor widget (currently single
post_type + single taxonomy + `detail`/`grid` mode) so ONE widget instance can
render a government-service-style page with **4 independent sidebar
sections**, each with its own post type, optional taxonomy, list/grid
behavior, and click behavior — while the **existing "services" page**, which
uses the old single-source Content-tab controls, keeps rendering and
behaving exactly as it does today, unchanged.

This plugin stays the single implementation (no new plugin is created) —
see `design.md` for the explicit confirmation that no technical blocker was
found that would require one.

## Functional Requirements

### FR-1 — Backward compatibility (legacy single-source mode)
- A new top-level control, **Source Mode**: `Single` (legacy) | `Sections`
  (new), defaults to `Single`.
- When `Single` (or on any pre-existing widget instance that predates this
  control, since Elementor fills unset controls with their default), the
  widget renders exactly via today's code path (`wptg_resolve_and_render()`
  with the existing `post_type`/`taxonomy`/`mode` controls) — byte-identical
  output, markup, CSS classes, and AJAX actions.
- No existing control is removed or renamed.

### FR-2 — Sections repeater (new "Sections" source mode)
When Source Mode = `Sections`, the Content tab exposes a Repeater control
"Sections" (0..N rows). Each row has:

| Field | Control type | Notes |
|---|---|---|
| `label` | TEXT | Sidebar display name, e.g. "Berita Terbaru" |
| `section_type` | SELECT | `flat_list` \| `taxonomy_accordion` |
| `post_type` | SELECT | Populated from `wptg_get_post_type_options()` |
| `taxonomy` | SELECT | Only shown/required when `section_type = taxonomy_accordion`; populated from `wptg_get_taxonomy_options()` |
| `content_style` | SELECT | Options depend on `section_type`: `flat_list` → `numbered_feed` (only option today, kept as SELECT for future growth); `taxonomy_accordion` → `grid_thumbnail` \| `list_thumbnail` \| `single_detail` |
| `item_click_behavior` | SELECT | `inline_detail` \| `modal` \| `redirect_or_popup_player` (last one only meaningful for video-style sections) |
| `enable_search` | SWITCHER | Shows a search box scoped to this section |
| `posts_per_page` | NUMBER | Initial load size and AJAX "load more" page size, for `flat_list` and `grid_thumbnail`/`list_thumbnail` (hidden/not applicable for `single_detail`, which shows one post at a time, not a paginated list) |

`single_detail` is a fully implemented option, not a placeholder: it is
the mechanism by which a `taxonomy_accordion` section reuses the EXISTING
detail-mode single-post-full-content renderer (`wptg_render_single_post_detail()`)
instead of a grid/list of excerpts — this is exactly the behavior the
"services" page needs, now expressed as one selectable per-section style
instead of a whole-widget mode. Its sidebar nests individual posts under
each term (same shape as today's legacy detail-mode sidebar, reusing
`wptg_get_terms_for_post_type()`/`wptg_get_posts_for_term()`), and clicking
a post swaps the content panel via the existing `wptg_get_single_post` AJAX
action — see `design.md` for the full render/AJAX flow. A `content_style`
value that doesn't apply to a row's `section_type` (e.g. `single_detail` on
a `flat_list` row) falls back to a documented default; see `design.md` for
the fallback table and the in-editor description text that documents it.

The first section whose data resolves to at least one item is active by
default on initial page load (mirrors existing `active_first` behavior).
See `design.md`'s `wptg_render_sections_widget()` section for the exact
lookahead algorithm across N sections.

### FR-3 — "Berita Terbaru" behavior (flat_list, post_type `news`)
1. Content panel default/active view: single-column numbered feed. Each row:
   thumbnail (left), badge number (01, 02, … — sequential display position,
   not a post field), publish date, clamped title, "Baca Selengkapnya"
   affordance.
2. First item is visually larger than the rest; all rows are full width
   (single column, not a grid).
3. "Lihat Lebih Banyak" button below the list triggers AJAX pagination that
   appends the next page (does not reload/navigate).
4. Clicking an item (`item_click_behavior = inline_detail`) swaps the whole
   content panel to the full single-post detail view (hero image, title,
   date, author, full content) using the existing single-post AJAX/render
   mechanism.
5. Has its own search box (`enable_search = true`).

### FR-4 — "Pengumuman" behavior (flat_list, post_type `announcements`)
1. Same numbered-feed layout and AJAX load-more as Berita Terbaru.
2. Has its own search box.
3. `item_click_behavior = modal`: clicking an item opens a floating
   modal/lightbox (dark backdrop, centered panel, close "X", closes on
   backdrop click or Escape) showing that announcement's image + title +
   date + author/content, instead of swapping the inline panel.

### FR-5 — "Galeri Foto" behavior (taxonomy_accordion, post_type `gallery`, taxonomy `gallery_category`)
1. Sidebar accordion identical in structure/behavior to the existing
   services accordion, parameterized by this section's post type/taxonomy.
2. Content panel for the active category: `content_style = grid_thumbnail`
   — grid of photo thumbnails with title + date caption under each.
3. "Lihat Lebih Banyak" AJAX load-more below the grid (same generic handler
   as Berita Terbaru).
4. `item_click_behavior = modal`: clicking a thumbnail opens the same
   reusable modal/lightbox, rendering a single larger image + title + date.

### FR-6 — "Galeri Video" behavior (taxonomy_accordion, post_type `videos`, taxonomy `videos_category`)
1. Sidebar accordion identical in structure to Galeri Foto, parameterized by
   videos + videos_category.
2. Content panel for the active category: `content_style = list_thumbnail`
   — single-column vertical list, each row: thumbnail with centered
   circular play-icon overlay, title below, meta line (date + author/
   location) below the title.
3. "Lihat Lebih Banyak" AJAX load-more, same generic handler.
4. `item_click_behavior = redirect_or_popup_player`:
   - If the video post has a non-empty external URL (post meta — exact key
     decided in `design.md`), clicking navigates the browser to that URL in
     a new tab.
   - If empty and the post has a self-hosted video attached, clicking opens
     the same reusable modal, this time rendering a video player instead of
     an image.
   - If neither an external URL nor a self-hosted video attachment is
     present (a misconfigured video post), clicking falls back to opening
     the modal with the normal post-detail content type, instead of doing
     nothing — see `design.md`'s "Modal type mapping" table for the exact
     3-way branch.

### FR-7 — Reusable modal/lightbox component
One JS component renders 3 content types — image, post/announcement detail,
video player — via a documented switch, not three separate implementations.
Used by Pengumuman, Galeri Foto, and Galeri Video (self-hosted case).

### FR-8 — Reusable AJAX "load more" handler
One generic server-side AJAX handler (plus one JS helper) serves pagination
for `flat_list` (Berita Terbaru, Pengumuman) and
`grid_thumbnail`/`list_thumbnail` (Galeri Foto, Galeri Video) — not
duplicated per section.

### FR-9 — Search behavior for paginated flat_list sections
Berita Terbaru and Pengumuman each have a search box. Because their lists
are paginated via AJAX, search must behave consistently and be documented
in `readme.txt` — the exact mechanism (fresh AJAX query vs. DOM-only filter)
is chosen and justified in `design.md`.

## Non-Functional Requirements

### NFR-1 — Guest access
Every new AJAX handler is registered on both `wp_ajax_{action}` and
`wp_ajax_nopriv_{action}` — no feature silently breaks for logged-out
visitors (this class of bug has recurred on this project before).

### NFR-2 — Defense in depth on AJAX input
Every AJAX handler verifies a nonce (`check_ajax_referer()`), and
re-validates on the server that any client-supplied post type / taxonomy /
term / post ID actually exists and that the resolved post genuinely belongs
to the claimed post type/taxonomy/term — client input is never trusted
blindly for ownership.

### NFR-3 — Code conventions
`declare(strict_types=1);`, typed signatures, `esc_html()`/`esc_attr()`/
`esc_url()` on all dynamic output, PHPDoc on new functions, `wptg_` prefix
for new global functions, `wptg-` CSS class prefix for new markup, Elementor
control IDs following the existing `wptg_...` pattern, no `@` suppression.

### NFR-4 — No code duplication
Any new code path that overlaps an existing mechanism (sidebar accordion,
single-post detail AJAX swap, grid-of-thumbnails rendering, search-filter
JS pattern, Style-tab helper methods) reuses/refactors the existing
function/method rather than duplicating it for "legacy" vs "new" paths.

### NFR-5 — Versioning
Plugin version is bumped (docblock `Version:` header, enqueued asset
version strings, `composer.json` `version`, dated `readme.txt` changelog
entry), consistent with every prior change in this plugin's history.

## Acceptance Criteria

1. An existing widget instance saved before this change (Source Mode absent
   from its saved settings) renders identical HTML/CSS classes/AJAX calls to
   today's output, in both `detail` and `grid` legacy modes.
2. Setting Source Mode = `Sections` and adding 4 rows matching the "Berita
   Terbaru / Pengumuman / Galeri Foto / Galeri Video" spec renders a sidebar
   with: 2 flat entries (no sub-items) + 2 accordions (with term children),
   in that order, matching FR-3 through FR-6.
3. Clicking a Berita Terbaru item swaps the content panel in place (no page
   navigation) to a full single-post view; the URL does not reload.
4. Clicking "Lihat Lebih Banyak" under Berita Terbaru appends the next page
   of news items below the existing ones without removing/reloading what's
   already shown.
5. Typing in the Berita Terbaru search box ends up (per the documented
   mechanism from FR-9) showing only matching news items, including items
   not yet loaded via pagination.
6. Clicking a Pengumuman item opens a modal overlay (not an inline swap);
   pressing Escape or clicking the backdrop closes it; no page navigation
   occurs.
7. Clicking a Galeri Foto category in the sidebar accordion expands it and
   shows its child items, matching the existing services accordion
   expand/collapse interaction exactly.
8. Clicking a Galeri Foto thumbnail opens the same modal component as
   Pengumuman, but rendering the image-content-type template.
9. Clicking a Galeri Video item with a non-empty external URL meta value
   opens that URL in a new tab; clicking one with an empty value but a
   self-hosted video attachment opens the modal with a video-player
   content-type template instead; clicking one with neither a URL nor a
   self-hosted attachment falls back to opening the modal with the normal
   post-detail content-type template, instead of producing no reaction.
10. Every new `wp_ajax_wptg_*` handler is reachable by a logged-out request
    (no `is_user_logged_in()` gate blocking it) and rejects a request with a
    missing/invalid nonce, and rejects a `post_id`/`term_id` that doesn't
    actually belong to the claimed `post_type`/`taxonomy`.
11. `composer.json` (plugin), the plugin header `Version:`, both enqueued
    asset version strings, and `readme.txt`'s changelog all show the same
    new version number.
12. `composer lint` (phpcs) and `composer analyse` (phpstan), if runnable,
    report no new errors introduced by this change (verification step —
    does not block completion per workflow constraints; see final summary
    for the exact command).
13. A `taxonomy_accordion` section with `content_style = single_detail`
    renders a nested term → post sidebar (reusing
    `wptg_get_terms_for_post_type()`/`wptg_get_posts_for_term()`) and
    clicking a post swaps the content panel via the existing
    `wptg_get_single_post` AJAX action — no new AJAX endpoint is introduced
    for this style, and no pagination/search-re-query UI is shown for it.
14. Picking a `content_style` value that doesn't apply to a row's
    `section_type` (e.g. `single_detail` on a `flat_list` row) falls back
    to a documented default at render time, and the Elementor control's
    own `description` text explains the valid combinations and the
    fallback behavior, so the mismatch is visible in the editor UI itself,
    not only in `readme.txt`.

## Out of Scope

- Visual pixel-matching against the reference screenshots (not available to
  this agent) — behavior/structure is matched from the written description,
  not a design-fidelity pass.
- Editing `web/app/mu-plugins/custom-taxonomies.php` (already done, out of
  scope unless a genuine defect blocking this feature is found and flagged).
- Server-side (re-query) search for the sidebar accordion search box on
  Galeri Foto/Video — that already exists today as a DOM filter and is not
  changed by this task; only the two NEW flat_list search boxes need a
  decision.
- Building a full video embed/oEmbed abstraction layer; only "has external
  URL" vs "self-hosted" is distinguished, per FR-6.
- Any changes to the live services page's actual Elementor widget settings
  in the database (this is a code change only; the human operator does not
  need to touch the services page at all for it to keep working).
- Creating a WP-CLI command or admin UI for bulk-migrating old widget
  instances — not needed since the default (`Single`) already preserves
  them unmodified.
