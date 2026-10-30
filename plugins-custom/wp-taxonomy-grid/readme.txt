=== Taxonomy Grid ===
Contributors: apadilah
Tags: cpt, taxonomy, elementor, widget, ajax, dynamic
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 4.0.0
Requires PHP: 8.0
Requires Plugins: elementor
License: GPLv2 or later

Native Elementor widget — master-detail layout for ANY CPT with ANY taxonomy.

== Description ==

Adds a **"Taxonomy Grid"** widget to the Elementor panel (category: *Taxonomy
Grid*). Drag it onto any page — no shortcode needed. All settings are
configured from the Elementor Content/Style tabs, with live preview in the
editor.

Two layout modes:

**"Detail" (default)** — government-service style layout:
Header banner + breadcrumb + search box + sidebar accordion (category > list
of posts) + right panel showing the FULL content of the active post.

**"Grid"** — simpler layout:
Sidebar category list + right panel showing a grid of post excerpts for the
active category.

Works with ANY post type and ANY taxonomy — including taxonomies shared
with regular blog posts (e.g. the default "category").

== Installation ==

1. Make sure Elementor is active.
2. Activate this plugin.
3. Edit any page with Elementor, search for **"Taxonomy Grid"** in the
   widgets panel, and drag it onto the canvas.

== Taxonomy Naming Convention ==

Don't share the built-in "category" taxonomy across multiple custom post
types — it causes category lists/archives to mix unrelated content types
together. Instead, create a DEDICATED taxonomy per post type via CPT UI,
named `{post_type}_category` (hierarchical, attached to that post type
only):

* `services` CPT -> taxonomy `services_category`
* `news` CPT     -> taxonomy `news_category`

The widget's Taxonomy dropdown shows every registered taxonomy — once
you create one following this convention, just pick it from the list.

== Usage ==

1. In the **Content > Source** section, pick the **Post Type** and
   **Taxonomy** (both are dropdowns populated from your site's registered
   post types/taxonomies — see "Taxonomy Naming Convention" above for
   what to create first if you don't have a dedicated taxonomy yet).
2. Pick **Layout Mode**: Detail or Grid.
3. For Detail mode, configure the **Header**, **Breadcrumb**, and
   **Search Box** sections (all optional — toggle off what you don't need).
4. For Grid mode, configure **Grid Options** (posts per category, columns).
5. In **Content > Breadcrumb**, pick a custom **Separator Icon** (any
   Font Awesome icon or your own uploaded SVG). Same for **Content >
   Search Box > Search Icon**.
6. Switch to the **Style** tab — every visual piece is independently
   customizable, organized into sections:
   - **Layout** — sidebar width, sidebar/content gap, content padding,
     quick Primary/Accent colors (affects the whole widget at once).
   - **Header** — padding, Subtitle typography+color, Title typography+color,
     decorative line color/length/thickness.
   - **Breadcrumb** — padding, link text typography+color, current item
     typography+color, separator icon color+size.
   - **Search Box** — margin, input padding, input typography+colors+
     border radius, icon color+size.
   - **Sidebar / Accordion** — space between categories, divider
     color/length/thickness, category name typography+color, accordion
     arrow color+size, post item padding+typography, hover/active state
     colors (separate tabs).
   - **Content Panel** — padding, post title typography+color+spacing,
     post body typography+color.

   Every Typography control includes font family, size, weight, style,
   letter-spacing, line-height, and text-transform — not just color/size.

== Sections / Content Navigator ==

Starting in 4.0.0, the widget's **Content > Source** section has a **Source
Mode** control: **Single** (the original one post type + one taxonomy
layout described above, unchanged) or **Sections** ("Content Navigator" —
multiple independent sidebar sections sharing one content panel, for pages
like "Imigrasi Jakarta Utara" with Berita Terbaru / Pengumuman / Galeri
Foto / Galeri Video side by side).

**Migration note:** existing widget instances (e.g. the "services" page)
were saved before this control existed, so Elementor treats them as
Source Mode = Single (the control's own default) automatically — no
action is needed, nothing on those pages changes. Source Mode = Sections
is purely additive and only applies to a widget instance where you
explicitly switch it.

**Switching Source Mode:** Content tab > Source > Source Mode > pick
"Sections — multiple sidebar sections (Content Navigator)". This reveals
a new **Sections** repeater control and hides every Single-mode-only
Content/Style control (they stay saved, just hidden, so switching back to
Single restores them).

**The Sections repeater — one row per sidebar entry:**

* **Label** — sidebar display name, e.g. "Berita Terbaru".
* **Section Type** — `Flat List` (no categories, a plain nav button) or
  `Taxonomy Accordion` (categories, an expandable accordion).
* **Post Type** — the CPT this section browses. Defaults to `post` so a
  freshly-added row always has a valid value (see "Content style
  fallback" below for why this matters).
* **Taxonomy** — only shown for `Taxonomy Accordion` rows; must be
  registered for the Post Type above.
* **Content Style** — `Numbered Feed` (Flat List only), `Grid of
  Thumbnails`, `Vertical List of Thumbnails`, or `Single Post Full Detail`
  (the last three are Taxonomy Accordion only).
* **Item Click Behavior** — `Swap Content Panel (inline detail)`, `Open
  Floating Modal`, or `Video: Redirect to URL, or Popup Player` (the last
  one is only meaningful for a video-style section).
* **Enable Search** — shows a search box scoped to this section only.
* **Items Per Page** — initial load size and "Lihat Lebih Banyak" AJAX
  page size (hidden for `Single Post Full Detail`, which shows one post
  at a time, not a paginated list).

**Content style fallback:** picking a Content Style that doesn't match a
row's Section Type (e.g. `Single Post Full Detail` on a `Flat List` row)
is not an error — it silently falls back to `Numbered Feed` (Flat List)
or `Grid of Thumbnails` (Taxonomy Accordion) when the page renders. The
same explanation is shown directly under the Content Style field in the
editor.

**The 4 reference section configurations:**

| Sidebar label | Section Type | Post Type | Taxonomy | Content Style | Click Behavior | Search |
|---|---|---|---|---|---|---|
| Berita Terbaru | Flat List | news | — | Numbered Feed | Swap Content Panel | Yes |
| Pengumuman | Flat List | announcements | — | Numbered Feed | Open Floating Modal | Yes |
| Galeri Foto | Taxonomy Accordion | gallery | gallery_category | Grid of Thumbnails | Open Floating Modal | No |
| Galeri Video | Taxonomy Accordion | videos | videos_category | Vertical List of Thumbnails | Video: Redirect/Popup | No |

**Search on Berita Terbaru / Pengumuman (Flat List sections):** typing in
the box triggers a fresh AJAX query (debounced 300ms), not a filter over
already-loaded items — because these lists are paginated, a DOM-only
filter could only ever search items already paged in, which would wrongly
show "no results" for an older item still on page 2+. A Taxonomy
Accordion section's own search box (Galeri Foto/Video, if enabled) stays
a DOM filter, same as the legacy sidebar search, since its whole term/post
tree is always rendered up front, never paginated.

**Galeri Video — external URL vs. self-hosted:** edit a `videos` post and
use the **"External Video URL"** box in the editor sidebar. If filled,
clicking that video in the Galeri Video section opens the URL in a new
tab. If left empty, attach a video file to the post instead (self-hosted)
— clicking then opens the shared modal with an HTML5 player. If neither
is set, clicking falls back to opening the modal with the normal
post-detail view instead of doing nothing. The URL is stored in post meta
key `_video_external_url`.

**The reusable modal:** used by Pengumuman, Galeri Foto, and Galeri
Video's self-hosted/fallback cases. Styled from **Style > Modal /
Lightbox**. Closes on Escape or a click on the dark backdrop (not the
panel).

== Detail Mode Defaults ==

If left empty:
- **Subtitle** defaults to the post type's label (e.g. "Layanan").
- **Title** defaults to the title of the page the widget is placed on.
- **Breadcrumb** becomes "Beranda > {Subtitle} > {Title}".

== Responsive Behavior ==

- Desktop: sidebar + content side by side (sidebar width adjustable in Style tab).
- Mobile (≤768px): sidebar stacks above content, full width; header
  decorative lines hidden; font sizes scale down.

== Notes on Shared Taxonomies ==

If the taxonomy (e.g. `category`, `post_tag`) is also used by regular blog
posts or other CPTs, the term list is automatically scoped to only terms
that have at least one published item of the selected Post Type — unrelated
categories won't appear, and clicking won't show empty results.

== Requirements ==

* Elementor (required — this plugin only ships an Elementor widget)
* Custom Post Type must exist
* Taxonomy must be registered for the CPT

== Changelog ==

= 4.0.0 =
* "Content Navigator": added a **Source Mode** control (Content > Source)
  — **Single** (the original one post type + one taxonomy layout, now the
  default and fully unchanged — existing widget instances, including the
  "services" page, keep rendering and behaving identically) or **Sections**
  (new) — multiple independent sidebar sections (flat list or taxonomy
  accordion) sharing ONE content panel, for pages with several unrelated
  content types side by side (e.g. Berita Terbaru / Pengumuman / Galeri
  Foto / Galeri Video).
* Added the **Sections** repeater control (8 fields per row: Label,
  Section Type, Post Type, Taxonomy, Content Style, Item Click Behavior,
  Enable Search, Items Per Page) — see the new "Sections / Content
  Navigator" section below for the full field reference and the 4
  reference configurations this feature was built for.
* `section_post_type` defaults to `post` (non-empty) so a freshly-added
  repeater row always normalizes successfully instead of silently
  vanishing from the sidebar.
* Berita Terbaru / Pengumuman search boxes re-query via AJAX (debounced
  300ms) instead of filtering already-loaded DOM nodes, so searching
  finds items not yet paged in. Taxonomy-accordion sections' own search
  (if enabled) stays a DOM filter, same as the legacy sidebar search.
* Added one reusable modal/lightbox component (`assets/js/modal.js`,
  `window.WptgModal`) supporting 3 content types (post detail, single
  image, video player) via one documented switch — used by Pengumuman,
  Galeri Foto, and Galeri Video's self-hosted/fallback cases. Closes on
  Escape or backdrop click.
* Added one generic AJAX "load more"/query handler
  (`wp_ajax_wptg_query_items`, with its `_nopriv_` counterpart) reused by
  every paginated section (flat list or grid/list thumbnail) instead of
  one handler per section.
* Galeri Video: clicking an item redirects to that post's
  `_video_external_url` meta value (new native meta box, "External Video
  URL", shown on the `videos` post type editor) if set; otherwise opens
  the shared modal with a self-hosted video player if a video is
  attached; otherwise falls back to the modal's normal post-detail view.
* Added 6 new Style-tab sections for Sections mode (Sections Nav,
  Numbered Feed, Grid Thumbnail, List Thumbnail, Load More Button, Modal /
  Lightbox), reusing this widget's existing Style-tab helper methods.
* Every new AJAX handler is registered for guests
  (`wp_ajax_nopriv_wptg_query_items`), checks a nonce, and re-validates
  post type/taxonomy/term ownership server-side from client-supplied IDs.

= 3.9.1 =
* Taxonomy control no longer defaults to "category" (there's no single
  universal default anymore under the new per-post-type convention).
  Added guidance in the Content tab description and this readme on
  naming dedicated taxonomies as "{post_type}_category" instead of
  sharing the built-in "category" across multiple post types.

= 3.9.0 =
* Split "Category Divider" into two fully independent Style sections:
  **"Category Divider (Accordion)"** and **"Category Divider (Merged)"**
  — separate color/length/thickness/margin/padding/position controls,
  separate CSS variables, no shared state.
* The divider is now a CHILD of its button (not a flex sibling), so
  "Position: Absolute" correctly anchors to the button itself. Default
  stays "Static" (same as before — a dash beside the text); switch to
  Absolute + Horizontal/Vertical Alignment + Z-Index for a floating,
  custom-positioned look (e.g. recreating the earlier "centered over
  text" effect, now fully configurable instead of hardcoded).
* Fixed: the Position "Center" alignment math for both the category
  dividers AND the header decorative lines now reads the actual
  configured Length/Thickness value (via CSS variable) instead of
  silently falling back to 0 — centering is now pixel-accurate.

= 3.8.3 =
* Merged row's divider now floats (`position: absolute`), centered over
  the category name, with `z-index: 1` so it renders visually on top of
  the button/text — instead of sitting beside it like a normal category
  row. Added `pointer-events: none` on the floating divider so it never
  intercepts clicks; clicking anywhere in that area still correctly
  opens the matching-slug post (verified: the divider never had its own
  JS click handler to begin with).

= 3.8.2 =
* Merged row's divider now stacks ABOVE the button (vertical layout)
  instead of beside it like a normal category row. Scoped to
  `.wptg-term-merged .wptg-term-head` only — normal accordion category
  rows keep their original side-by-side divider layout, unaffected.

= 3.8.1 =
* Reverted 3.7.1: merged rows show the category divider dash again,
  same as a normal category row (no more special no-divider
  compensation CSS). Button positioning (e.g. pushing it flush left on
  Active) remains fully controlled via Style > Category Name (Merged).

= 3.8.0 =
* "Category Name (Merged)" Style controls (Typography, Alignment, and
  the Normal/Hover/Active Margin/Padding/Background/Border Radius) now
  target the clickable BUTTON itself (`.wptg-term-merged-trigger`),
  instead of only the text span inside it. This fixes the mismatch where
  setting margin/padding only moved the label text while the button's
  own background/border stayed in place.
* Removed the hardcoded "auto full-width pill" CSS for the Active state
  (fixed padding + border-radius). Positioning/sizing on Active is now
  fully controlled via Style > Category Name (Merged) > Active tab —
  e.g. set a negative Margin Left there to push the button flush against
  the sidebar's left edge.

= 3.7.1 =
* Merged rows no longer render the category divider dash at all (not
  even in Normal/Hover state — previously it was only hidden while
  Active). Text indent is compensated using the same divider-width
  variable so it stays visually aligned with normal category rows.

= 3.7.0 =
* Split the old combined "Category Name" Style section into two fully
  independent sections: **"Category Name (Accordion)"** (Normal/Hover —
  for a normal expandable category heading) and **"Category Name
  (Merged)"** (Normal/Hover/Active — for the special merged row). Each
  uses an ancestor-scoped selector (`.wptg-term-toggle .wptg-term-name`
  vs `.wptg-term-merged-trigger .wptg-term-name`) so styling one never
  affects the other, even though both share the same `.wptg-term-name`
  class on the element itself.

= 3.6.0 =
* Merged row now expands to a full-width, flush-left / rounded-right
  "selected tab" pill when it becomes the active item — removes the
  reserved divider indent and hides its own divider dash while active,
  matching the government-service reference design.

= 3.5.1 =
* Fixed: merged row's text appeared shifted/padded compared to a normal
  category header. The merged trigger reused `.wptg-post-trigger`'s
  padding (meant for indented items inside the post list), now overridden
  to match the category header's own padding so it sits flush/aligned.
  Note: the new Style > Category Name > Active (Merged) > Padding control
  can be used to add breathing room back intentionally if desired.

= 3.5.0 =
* Category Name (Style > Sidebar/Accordion) Normal/Hover/Active(Merged)
  tabs now each include Margin, Padding, Background Color, and Border
  Radius — on top of the existing Text Color — so the category label can
  be styled as a badge/button per state, not just colored text.

= 3.4.1 =
* Fixed: opening a post's own single page (where this widget's post_type
  matches the current singular query) now correctly marks THAT post as
  active in the sidebar, instead of always defaulting to the
  alphabetically-first post regardless of which page is actually open.
* Added dedicated Normal/Hover/Active(Merged) Text Color tabs for
  "Category Name" under Style > Sidebar/Accordion, on top of the existing
  shared Typography + Alignment controls.

= 3.4.0 =
* Sidebar: when a category contains exactly one post, and that post's
  name matches the category's own name (e.g. category "Perubahan Data
  Paspor" with a single post "Perubahan Data Paspor"), the sidebar now
  merges them into one row instead of showing a category heading with a
  redundant single child. The merged row acts directly as the clickable
  post trigger (no chevron, no expand) and uses the same Active/Hover
  styling as a normal post item.

= 3.3.1 =
* Fixed: Hover and Active states on sidebar Post Item were being silently
  overridden by the Normal state (CSS specificity bug introduced in 3.2.0
  when Normal/Hover/Active tabs were added — Normal used a higher-
  specificity direct selector while Hover/Active used lower-specificity
  CSS variables). All three states now use consistent, correctly
  escalating specificity (Normal < Hover < Active) so state overrides
  always apply correctly regardless of stylesheet load order.

= 3.3.0 =
* Header decorative lines (Start/End) can now each be switched to
  `position: absolute` independently, with their own Horizontal
  Alignment (Left/Center/Right), Vertical Alignment (Top/Middle/Bottom),
  and Z-Index. Centering uses a margin-based trick (no `transform`) so it
  never conflicts with the existing Margin/Padding controls, which still
  work for fine offset adjustments once Absolute is active.

= 3.2.2 =
* Removed the default divider line between Breadcrumb and the Sidebar/
  Content area below it. Added an optional Border control under
  Style > Breadcrumb if a divider is wanted back later.

= 3.2.1 =
* Post Item Box Shadow moved into the Normal/Hover/Active tabs — each
  state now has its own independent shadow, instead of one shared shadow
  for all states.

= 3.2.0 =
* Breadcrumb: added Vertical Alignment control (Top/Middle/Bottom).
* Fixed Breadcrumb/Search icon color & size controls not applying when
  Elementor renders the icon as SVG (now targets the icon element's own
  `fill`/`color` directly, matching Elementor core widget conventions).
* Post Item (sidebar): added Border Radius, Border (width/style/color),
  Box Shadow, Transition Duration, and a full Normal/Hover/Active tab
  group (background + text color + border color per state) — matches
  both Detail mode's post buttons and Grid mode's category buttons.

= 3.1.0 =
* Restructured markup: Header now wrapped in its own "Top Section"
  container; Breadcrumb + Sidebar + Content now wrapped together in a
  "Body Section" container — both independently stylable (margin,
  padding, background color) from Style > Container.
* Decorative header lines and the sidebar category divider are now real
  elements (not CSS pseudo-elements), so their position can be fine-tuned
  with Margin/Padding controls, not just length/thickness/color.
* Added Margin + Alignment controls to every text element (header
  subtitle/title, breadcrumb text/current, category name, post item,
  post title/content) — on top of the existing Typography + Color.
* Added overall widget Max Width + centering, and a dedicated
  Sidebar/Content row spacing section.
* All new spacing/alignment controls are responsive (separate values for
  desktop/tablet/mobile).

= 3.0.0 =
* BREAKING: Converted from a `[taxonomy_grid]` shortcode into a native
  Elementor widget. Existing shortcode placements must be replaced by
  dragging the "Taxonomy Grid" widget instead.
* Added Elementor Style-tab controls: sidebar width, column gap, primary
  color, accent color — adjustable with live preview, no code/CSS needed.
* Fixed font rendering: explicit sans-serif font stack so the widget no
  longer inherits a theme's default serif fallback.

= 2.0.0 =
* Added "detail" mode: header banner, breadcrumb, search box, sidebar
  accordion (category > posts), single active post full content panel.
* CSS custom properties for easy color overrides.
* Kept "grid" mode for backward compatibility.

= 1.0.0 =
* Initial release (shortcode-based).
* Scoped term counting per post type (fix for shared taxonomies).
* Fixed AJAX for guest/non-logged-in visitors.
