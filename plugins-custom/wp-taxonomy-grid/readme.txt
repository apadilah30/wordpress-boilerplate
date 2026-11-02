=== Taxonomy Grid ===
Contributors: apadilah
Tags: cpt, taxonomy, elementor, widget, ajax, dynamic
Requires at least: 6.2
Tested up to: 6.5
Stable tag: 4.6.0
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
  Floating Modal`, or `Media Popup (video / photo)` (see "Media Popup"
  below). The old value `Video: Redirect to URL, or Popup Player` is still
  accepted for widgets saved before 4.3.0, shown as `Old: Video redirect /
  popup (now same as Media Popup)`, and behaves exactly like Media Popup: a
  click never redirects or opens a new tab anymore.
* **Include in Search** — the widget has ONE search box (see "Search"
  below). Turn this off to make that box ignore this section.
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

| Sidebar label | Section Type | Post Type | Taxonomy | Content Style | Click Behavior | Include in Search |
|---|---|---|---|---|---|---|
| Berita Terbaru | Flat List | news | — | Numbered Feed | Swap Content Panel | Yes |
| Pengumuman | Flat List | announcements | — | Numbered Feed | Open Floating Modal | Yes |
| Galeri Foto | Taxonomy Accordion | gallery | gallery_category | Grid of Thumbnails | Media Popup (video / photo) | No |
| Galeri Video | Taxonomy Accordion | videos | videos_category | Vertical List of Thumbnails | Media Popup (video / photo) | No |

**Search:** there is ONE search per widget (toggle, placeholder, icon and
**Search Position** under **Content > Sections**; styled by **Style > Search
Box**). The box can sit in the sidebar, above the list, or both (two inputs
that stay in sync). It searches inside whichever section is open, and keeps
applying when you switch to another section:

* Sections shown as a feed, grid or list (Berita Terbaru, Pengumuman, Galeri
  Foto, Galeri Video): typing triggers a fresh AJAX query (debounced
  300ms) from page 1, not a filter over already-loaded items. These lists
  are paginated, so a DOM-only filter would wrongly show "no results" for
  an older item still on page 2+.
* `Single Post Full Detail` sections: the sidebar tree is filtered in the
  browser, same as the legacy sidebar search (its whole term/post tree is
  always rendered up front).
* A section with **Include in Search** turned off is left untouched.

**The reusable modal:** used by Pengumuman (Open Floating Modal) and by the
Media Popup. Styled from **Style > Modal / Lightbox**. Closes on Escape,
the close button or a click on the dark backdrop (not the panel). Focus
goes to the close button when it opens and back to the clicked item when
it closes.

== Media Popup (video / photo) ==

Since 4.3.0 one popup serves every video and photo. A clicked item shows a
dim backdrop and a centered panel: the media on top, then the title and the
date ("02 Oktober 2026"), and a round gold close button on the panel's
top-right corner.

**Where it is used**

* Taxonomy Grid, Sections repeater: **Item Click Behavior = Media Popup
  (video / photo)**. The item then only carries `data-wptg-media="<post id>"`
  (plus its usual `data-post-id`, `data-post-type`, `data-click-behavior`).
  It works for items that arrive by AJAX too (load more, search, other term).
* Content Grid plugin 1.2.0+: **Link Mode = Media Popup (video / photo)**. The
  card stays a normal `<a href="permalink">` and gets the same
  `data-wptg-media` attribute.
* Any other markup: add `data-wptg-media="<post id>"` to an element. One
  listener on `document` (assets/js/media-popup.js) handles it.

**What the popup shows** is decided from the post's data, not from its
post type (`wptg_build_media_modal_payload()`):

1. **Video**, when the post has a video source. The first one found wins:
   1. post meta `_video_external_url` (the "External Video URL" box of the
      `videos` post type),
   2. the first video in the post content: a `<video>` tag or Video block, a
      YouTube / Vimeo link or Embed block, a `[video]` shortcode, or a
      YouTube / Vimeo `<iframe>`. Iframes of any other host are ignored.
   3. a video file attached to the post (uploaded from the post's edit screen).
2. **Photo**, when there is no video: the featured image (size `large`),
   else the first image in the content.
3. **Post**, when there is neither: the title + content popup of "Open
   Floating Modal".

**How a video is played**

* YouTube (`watch?v=`, `youtu.be/`, `shorts/`, `embed/`) opens in a
  `youtube-nocookie.com` iframe, Vimeo (`vimeo.com/<id>`,
  `player.vimeo.com/video/<id>`) in a `player.vimeo.com` iframe. Both autoplay.
* A direct file (`.mp4 .webm .ogv .m4v .mov`) or an attached video plays in
  a `<video controls>` that is started by the script.
* Any other external URL (TikTok, Instagram...) cannot be embedded: the popup
  shows a short message and an "Buka video" button (new tab). Nothing redirects
  automatically.
* The featured image is never shown in a video popup (no poster either).
* Closing the popup removes the player, so sound stops.

The ids are read from the URL text. WordPress oEmbed is not used, so no
outgoing request is made. The content is scanned as raw `post_content`: a post
built only with Elementor keeps its content elsewhere, so use External Video
URL for such posts.

**Security:** the AJAX action `wptg_get_media_modal` checks the `wptg_nonce`
nonce, serves only published posts of public post types, and refuses posts
that need a password. The player/image markup passes through `wp_kses()` with
a strict allowlist.

**Style:** the defaults match the design and live in CSS variables on
`.wptg-media-modal` (`--wptg-modal-panel-width`, `--wptg-modal-panel-bg`,
`--wptg-modal-radius`, `--wptg-modal-close-bg`, `--wptg-modal-close-color`,
`--wptg-modal-title-color`, ...), so the popup also looks right on pages
without a Taxonomy Grid widget. **Style > Modal / Lightbox > Media Popup**
(Sections mode) changes width, radius, background, close button colors, and
title/date typography and color. Those controls apply site-wide on the page
(the popup lives in `<body>`, outside the widget).

== Deep Link (Sections mode) ==

Since 4.2.0 a Sections page can be opened with a section and a category
already selected, by adding two query params to the page URL:

`/berita/?wptg_section=videos&wptg_term=profil-kantor-imigrasi`

* `wptg_section` — the **post type slug** of a Sections repeater row
  (e.g. `gallery`, `videos`, `news`).
* `wptg_term` — the **slug** (not the ID) of a term of that row's taxonomy.
  Optional.

The Content Grid plugin (1.1.0+) builds these links when its "Link Mode" is
"Content Navigator page (deep link)". The two plugins share nothing but
these two param names.

What happens:

* The server renders the result (no flicker, works without JavaScript): the
  matching section is open, the matching term is marked active, and the
  content panel already lists that term's items, same as after a manual
  click. The header title, breadcrumb and content title show the SECTION
  label, exactly as they do after a manual click on a term.
* The first repeater row whose Post Type matches is used. If a term is
  given, the first matching row that really contains that term wins (so a
  page can have a Flat List "Berita Terbaru" AND an accordion for the same
  post type).
* A Flat List section has no categories: `wptg_term` is ignored and only
  the section opens.
* The term must have at least one published post of that post type
  (that is how the sidebar lists terms). A section that matches but a term
  that is unknown or empty: the section opens on its first term.
* Anything invalid (unknown section, no such row, empty section, wrong
  type of value) is ignored silently and the normal default section opens.
  The page never errors.
* Values are sanitized with `sanitize_key()` (section) and
  `sanitize_title()` (term). The params are read-only and change no data,
  so no nonce is used.
* After a deep link, search, "Lihat Lebih Banyak" and switching sections
  behave as usual.
* The page scrolls to the widget once after load (JS, only when the deep
  link was valid).
* It does not open one specific post inside the term.

Caching: a page-cache plugin such as WP Super Cache stores one copy per
query string, so each deep link URL gets its own cached page. No extra
code or settings needed.

== Detail Mode Defaults ==

If left empty:
- **Subtitle** defaults to the post type's label (e.g. "Layanan").
- **Title** defaults to the title of the page the widget is placed on.
- **Breadcrumb** becomes "Beranda > {Subtitle} > {Title}".

== Empty State (Skeleton) ==

Since 4.4.0 the widget never disappears or prints an "empty" / error text
when there is no data. It always draws its full layout (header, breadcrumb,
search box, sidebar, content panel, following the switches you set) and the
parts that would hold data show grey skeleton blocks shaped like the real
cards. This applies to all layouts: Sections, Single + Detail, Single + Grid.

**What you see**

* Sections: no valid section at all = sidebar with 4 skeleton lines and a
  feed-shaped skeleton in the panel. A section with a label but no data stays
  in the sidebar (a Flat List is still a button; an Accordion keeps its header
  and arrow and shows skeleton lines instead of terms). If no section has
  data, the first valid section is the open one, so the header, breadcrumb
  and content title show its label.
* The panel skeleton follows the section's Content Style: Numbered Feed =
  one big card + compact cards, Grid of Thumbnails = image + 2 lines, Vertical
  List = small thumbnail + lines, Single Post Full Detail = title + text block.
* Search without results, a term with no items, or clicking a section without
  data swaps the panel for the same skeleton. There is no Load More button in
  an empty state.
* Single mode: a missing / wrong Post Type or Taxonomy, or no published posts,
  gives the two-column frame with a skeleton sidebar and a skeleton detail
  (or grid) panel.
* Deep link (`?wptg_section=...`) to a section that exists but has no data
  opens it in this empty state. A bad value still falls back to the default.

**Accessibility:** every skeleton shape is `aria-hidden`. The content skeleton
has one visually hidden text, "Belum ada konten", inside a `role="status"`
wrapper. Nothing else is printed.

**Editor note:** users who can edit posts (and the Elementor editor) also see
one small dashed line under the widget, e.g. "Taxonomy Grid (hanya terlihat
oleh editor): pilih Taxonomy di tab Content > Source." Visitors never see it.

**Look:** Style > Empty State (Skeleton): Skeleton Color, Shine Color, Border
Radius. The shine animation is switched off for visitors with
`prefers-reduced-motion: reduce`. Sizes, gaps and card radius come from the
existing layout controls (Feed Cards, Grid, ...).

**Left as text on purpose:** a request / network error while loading
("Error loading ..."), and the hint "Pilih salah satu item di samping..." in
Single mode when data exists but "Auto-select First Item" is off.

== Document Files (PDF / Word / image preview) ==

Since 4.5.0 a post can hold MANY files that visitors preview in a popup. Built
for the "Informasi" page (Standar Pelayanan, Maklumat Pelayanan), but it works
for any post type you add to the `wptg_document_post_types` filter.

**Document Files meta box** (class-document-meta-box.php)

* Shown on the edit screen of the post types returned by
  `apply_filters('wptg_document_post_types', ['service-standards', 'service-pledges'])`.
  Those two post types are created in CPT UI (slugs exactly `service-standards`
  and `service-pledges`), not by this plugin.
* "Tambah file" opens the WordPress media modal (several files at once; only
  PDF, .doc/.docx, JPG, PNG, WebP). Drag the rows to reorder. "Label" is
  optional: empty = the attachment title is shown.
* Saved as post meta `_wptg_document_files` = list of `['id' => attachment id,
  'label' => text]`. Nonce + `edit_post` capability are checked, autosaves and
  revisions are skipped, every id must be an existing attachment whose mime type
  is on the allowlist, at most 50 files.
* `wptg_get_document_files($post_id)` is the only function that reads the meta.
  It returns `id, label, url, mime, kind (pdf|image|office|other), ext, size,
  title` and skips deleted or not-allowed files.
* The script and style of the box load only on those edit screens.

**Content Style: Document List** (Sections repeater; works for Flat List and
Taxonomy Accordion). One card per post, two rows (layout since 4.6.0):

* Row 1: a big line icon on the left (document + pencil, or your own "Card
  Icon") and, to its right, the title in gold and "Diunggah pada: 1 Oktober
  2026" (publish date, `j F Y`) in navy. Optional "3 file" under the date.
* Row 2: the cover image BELOW row 1, full width, fixed height, top of the
  document visible. The "Lihat" and "Unduh" pills sit on its top-right corner.
  Cover source, first match wins: Featured Image, first image file in Document
  Files, WordPress's generated preview of the first PDF (only when the server
  can make it), else no cover; then the pills become their own row under row 1,
  on the right. A post without valid files shows no pills.
* "Lihat" is a real `<button>` and opens the popup (the way in for keyboard
  users). A mouse click anywhere on the card opens the same popup. The card
  itself has no `role` / `tabindex`, so buttons are not nested in a button.
* "Unduh": one file = a plain `<a download>` to that file, no popup; several
  files = opens the popup with the download buttons visible (flag
  `data-wptg-show-download="1"`, only for Media Popup / Document Preview).

Texts and switches: Content > Document Card (Show Icon, Card Icon, Show Date,
Date Label, Show Cover, Show File Count, View Button Text, Show View Icon, Show
Download Button (card), Download Button Text, Show Download Icon, Show Download
Button (popup)). Look: Style > Document Card (card, icon, title, date, file
count, cover, buttons). Every size/gap/color default is a CSS variable with a
fallback in `assets/css/taxonomy-grid.css` (`--wptg-doc-*`), so the design looks
the same with no Elementor setting at all. Pagination, Load More, search and the
empty-state skeleton work like the other content styles.

**Item Click Behavior: Document Preview (PDF / Word / image)** opens the same
popup as Media Popup (same `data-wptg-media` attribute, same listener). The
answer kind order is video > document > photo > post, decided from the data: a
post with validated files becomes a "document" popup. The server sends only
the file list (`files`: label, url, kind, mime, ext, download_url,
office_preview, embed_url); the browser draws the preview
(`assets/js/document-viewer.js`).

* Several files: a row of file chips switches between them.
* Image: shown as is.
* PDF: PDF.js (bundled in `assets/vendor/pdfjs/`, Apache-2.0, see VERSION.txt
  there). It is loaded only when the first PDF is opened. Every page is a
  canvas that is drawn when it comes near the visible area; only 8 canvases stay
  in memory. Password-protected or broken PDFs show a polite message and an
  "Buka file" link. No text layer (no text selection or in-document search).
* Word (.doc / .docx): Microsoft's online viewer in an iframe, only when the file
  URL is publicly reachable. The server decides (`wptg_is_public_url()`:
  localhost, *.local, *.test, *.localhost, private IPs and dev ports are not
  public) and the filter `wptg_office_preview_enabled` ($enabled, $file_url) can
  override it. Otherwise a message and an "Buka file" link.
* The popup closes with Esc, the backdrop or the round button, and focus goes
  back to the card. Closing stops PDF rendering and frees the document.
* Style > Document Popup: panel width, viewer height, viewer background, chip
  colors (normal / active), PDF page background, space between pages.

**Server requirements**

* `.mjs` files must be served as JavaScript (`Content-Type: text/javascript`).
  Check: `curl -I https://your-site/app/plugins/wp-taxonomy-grid/assets/vendor/pdfjs/pdf.min.mjs`.
* If a Content-Security-Policy is added: `frame-src` must allow
  https://view.officeapps.live.com (and the YouTube / Vimeo hosts), `worker-src`
  must allow 'self' blob:, `script-src` 'self', `img-src` data: blob:.
* With Polylang, enable the two post types in Languages > Settings.

== Responsive Behavior ==

- Desktop: sidebar + content side by side (sidebar width adjustable in Style tab).
- Mobile (≤768px): sidebar stacks above content, full width; header
  decorative lines hidden; font sizes scale down.
- Sections mode: the sidebar also stacks above the content on tablet
  (≤1024px) and the featured feed card becomes vertical; on mobile the
  numbers shrink and compact cards keep a small thumbnail on the left.

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

= 4.6.0 =
* Document card redesigned: head row (changeable icon, gold title, "Diunggah pada:
  <date>") and the cover BELOW it with "Lihat" / "Unduh" pills on its top-right
  corner. New Content section "Document Card" (renamed from "Document List"):
  Show Icon, Card Icon, Show Date, Date Label, Show Cover, Show File Count (now
  off by default), View Button Text, Show View Icon, Show Download Button
  (card), Download Button Text, Show Download Icon, Show Download Button (popup).
* Cover sources: Featured Image, first image file, generated preview of the
  first PDF (checked so a missing preview never shows a broken image).
* "Unduh": one file downloads directly, several files open the popup with the
  download buttons shown. New per-click flag `data-wptg-show-download="1"`
  (replaces the card attribute `data-wptg-download`).
* The card is no longer `role="button"` / `tabindex="0"` (it holds real
  buttons). `media-popup.js` skips clicks that come from an `a[download]`.
* Style > Document Card rebuilt around CSS variables (`--wptg-doc-*`) with the
  design values as fallbacks; new Date, Cover (height, space, radius, shadow,
  fit, position) and Buttons (overlay offset, gap, typography, padding, radius,
  size, View / Download colors Normal and Hover) controls. Removed: Cover Width,
  Space Between Cover/Icon/Text/Button, the single-pill Button colors.
* The AJAX request now sends ONE field `doc_options` (JSON from the wrapper's
  `data-doc-options`), validated by `wptg_normalize_document_options()`. The
  Card Icon is copied from a `<template>` like the read-more icon
  (`fillIconSlots()` in taxonomy-grid.js).
* Skeleton variant `document` mirrors the new layout.
* New PHP helpers: `wptg_render_document_svg()`, `wptg_get_document_cover_html()`,
  `wptg_get_pdf_preview_image()`, `wptg_render_document_actions()`.
  `wptg_build_item_attrs()` got an optional `$popup_hint` argument.

= 4.5.0 =
* New "Document Files" meta box (`includes/class-document-meta-box.php`) for the
  post types in the `wptg_document_post_types` filter (default
  `service-standards`, `service-pledges`): pick many PDF / Word / image files,
  drag to reorder, optional label per file. Meta `_wptg_document_files`; the
  single reader is `wptg_get_document_files()`.
* New Content Style **Document List** (Flat List and Taxonomy Accordion) with
  its own skeleton (`wptg_render_skeleton('document')`) and Load More / search
  support. New Content section "Document List" (Show Cover, Show File Count,
  Button Text, Show Download Button) and Style sections "Document Card" and
  "Document Popup".
* New Item Click Behavior **Document Preview (PDF / Word / image)**. It reuses
  `data-wptg-media` and the existing click listener. The popup payload has a new
  kind `document` (order: video > document > photo > post) with a `files` list.
* New `assets/js/document-viewer.js` draws the files: image, PDF through the
  bundled PDF.js 6.4.299 (legacy build, lazy loaded, lazy page rendering,
  cleanup on close), Word through Microsoft's viewer on public sites (server
  flag `office_preview`, filter `wptg_office_preview_enabled`).
* `modal.js`: new `document` popup variant and `WptgModal.onClose()`.
* `.gitignore` of the project now keeps `assets/vendor/` of custom plugins.
* Needs `.mjs` served as JavaScript and, with a CSP, the directives listed in
  "Document Files" above.

= 4.4.0 =
* New Empty State: with no data the widget still renders its whole layout and
  shows grey skeleton blocks instead of text. Covers Sections, Single + Detail
  and Single + Grid. See "Empty State (Skeleton)" above.
* New PHP helpers `wptg_render_skeleton()` (variants feed, grid, list, detail,
  sidebar_terms), `wptg_render_skeleton_for_style()`, `wptg_render_editor_note()`
  and `wptg_resolve_empty_active()`. New CSS classes `.wptg-skeleton*`,
  `.wptg-sr-only`, `.wptg-editor-note`.
* Removed the public texts "No valid sections configured.", "No items found.",
  "No items in this category.", "Belum ada konten." (now only visually hidden),
  "Tidak ditemukan." and the `Error: ...` lines of Single mode. Editors see one
  small note with the reason instead. The unused `.wptg-error` CSS is gone.
* Sections: a section without data stays in the sidebar. A deep link to such a
  section opens its empty state. An accordion section with no term picked no
  longer lists every post of the post type.
* AJAX `wptg_query_items` answers an empty first page with the skeleton in
  `html` plus `empty: true`; an empty later page returns no html and
  `has_more: false`. It also accepts `content_style=single_detail` for the
  empty state only.
* JS: an accordion header without terms opens that section's empty panel.
  `.wptg-sections-content` has a new `data-active-section` attribute.
* New Style section "Empty State (Skeleton)": Skeleton Color, Shine Color,
  Border Radius.

= 4.3.0 =
* New Media Popup (video / photo): one popup for every video and photo.
  Sections repeater > Item Click Behavior has a new option **Media Popup
  (video / photo)**. Items only output `data-wptg-media="<post id>"` and
  `assets/js/media-popup.js` (one listener on `document`) opens the popup.
  See "Media Popup" above.
* The popup kind comes from the post data: video (External Video URL meta,
  then the first video/embed in the content, then an attached video file),
  else photo (featured image, else first content image), else the old post
  view. YouTube and Vimeo ids are parsed from the URL text, no oEmbed call.
* Video popups never show the featured image (no poster) and start playing at
  once. Closing the popup removes the player, so sound stops.
* Behavior change: the old `redirect_or_popup_player` value (open the URL in a
  new tab, or a small video popup) is still accepted but now behaves exactly
  like Media Popup. Nothing redirects anymore. Its label in the editor is now
  "Old: Video redirect / popup (now same as Media Popup)".
* The External Video URL box description is updated. A URL that is not
  YouTube / Vimeo / a video file (TikTok, Instagram...) shows an "Buka video"
  button in the popup.
* New AJAX action `wptg_get_media_modal` (read-only, nonce, published posts of
  public post types only, password-protected posts refused).
* New PHP file `includes/media-popup.php`: `wptg_build_media_modal_payload()`
  and helpers. New JS file `assets/js/media-popup.js`. `modal.js` now also
  handles the types `media`, `loading` and `message`, gives focus to the close
  button and back to the clicked item, and no longer has a `video` type.
* `wptg-media-popup` (script) and `wptg-styles` are registered and loaded on
  every page, so the Content Grid plugin can use the popup on pages without a
  Taxonomy Grid widget. The script data comes as `wptg_media` (ajax_url, nonce,
  translatable texts).
* New CSS block "MEDIA POPUP" with CSS variables. New Style controls under
  Modal / Lightbox > Media Popup: Popup Width, Border Radius, Background, Close
  Button Background / Icon Color, Title and Date typography + color.
* Requires WordPress 6.2+ (uses `WP_HTML_Tag_Processor`).

= 4.2.0 =
* Deep link for Sections mode: `?wptg_section=<post_type>&wptg_term=<term_slug>`
  opens that section with that category already active (server-side render,
  no flicker, no JS needed). Invalid values are ignored and the normal default
  section opens. Flat List sections ignore the term. See "Deep Link" above.
* New PHP helpers: `wptg_read_deep_link_param()`, `wptg_find_section_term_by_slug()`,
  `wptg_build_deep_link_active()`, `wptg_resolve_deep_link()`.
* The Sections wrapper has `data-deep-link="1"` when a valid deep link was
  applied; the JS scrolls to the widget once after load.
* No new Elementor controls. Without the params everything works as in 4.1.0.

= 4.1.0 =
Sections mode, for the "Berita" page design:
* Content title: an h2 above the list (Content > Sections > Show Content Title,
  default on). Empty "Content Title" follows the open section's label and is
  updated by JS on every switch; type a text to keep it fixed. Style > Content
  Title (Sections): typography, color, margin, alignment.
* Search Position (Content > Sections): Sidebar / Content / Both (default
  Both). With Both there are two inputs that stay in sync and run ONE search.
  The content search is full width above the list. Both use the .wptg-search*
  classes, so Style > Search Box styles both.
* Numbered feed redesign: cream cards, big gold number (01, 02, ...), date,
  title, excerpt, and a "read more" with text + icon. New Content > Feed &
  Load More: Feed Layout (Featured first + compact rest / All featured / All
  compact), Show Number, Excerpt Length (words), Read More Text, Read More
  Icon, Load More Text. The number continues across Load More (07 on page 2)
  and restarts at 01 after a search or a section switch. The first result of a
  search is also the "featured" card now.
* New Style > Feed Cards (Featured / Compact): gap between cards, card radius,
  background and shadow (Normal/Hover), featured/compact image width and
  height, image radius and object-fit, number typography (featured and compact
  separately), number spacing, text gap, excerpt typography and max lines,
  read-more icon color/size/spacing.
* Load More: text is editable, extra Style controls (radius, alignment, hover
  colors), a loading state (button disabled + spinner, no double requests).
  The old fixed "Lihat Lebih Banyak" strings are gone from PHP and JS.
* New Style > Accordion Terms (Sections): typography, text/background color
  (Normal/Hover/Active), padding, margin, radius, indent and gap of the term
  buttons inside an accordion section, plus the accordion arrow
  (color/size/spacing) and the space between sidebar entries.
* Style > Sections Nav: Hover and Active tabs now have their own Text Color.
* Header: new "Show Decorative Lines" switch (Content > Header) and "Gap
  Between Lines & Title" (Style > Header). The lines now flank only the title,
  with the subtitle above it (all layouts).
* Responsive: at <=1024px the Sections sidebar goes above the content and the
  featured card becomes vertical; at <=767px numbers shrink and the compact
  card keeps its small thumbnail on the left. Sidebar Width now accepts down to
  100px and up to 100%.
* Fixed: on mobile, Style > Container > Sidebar Width could make the stacked
  sidebar 280px tall (an Elementor selector beat the stylesheet's media
  query). The stylesheet now forces full width there. The stacked content
  column also stretches to full width now.
* Heads-up for existing Sections widgets: the feed cards look different
  (cream cards, number beside the text instead of a badge on the image, compact
  rest), a content title and a second search box appear by default. Switch them
  off in Content if not wanted. Regenerate Elementor CSS (Elementor > Tools)
  after updating.
* AJAX still uses the `wptg_nonce` nonce. Feed options sent by the browser are
  whitelisted server-side; the read-more icon markup is never sent by the
  browser (JS copies it from a <template> rendered by the server).

= 4.0.2 =
* Header banner and breadcrumb now render in EVERY layout: Single (Detail),
  Single (Grid) and Sections. Previously only Single + Detail had them.
  Content > Header / Breadcrumb and Style > Header / Breadcrumb now show in
  all modes (the Show Header / Show Breadcrumb switches stay, default on).
  Heads-up: an existing Single + Grid widget now gains a header and
  breadcrumb; switch them off in Content if that page should not have them.
* Sections mode: an empty header Title / breadcrumb Current Label FOLLOWS the
  open section (e.g. "Berita Terbaru" -> "Pengumuman"), updated on every
  switch without reloading. Type a Title / Current Label to keep it fixed.
  Subtitle defaults to the title of the page the widget sits on.
* Header and breadcrumb markup is now one shared implementation used by all
  three layouts (Single detail output is unchanged).
* Grid mode: the sidebar + posts row now sits in an inner `.wptg-body`
  (same as detail mode) so it shares its spacing/responsive rules.
* Fixed: Sections mode picked the initial section by list position instead
  of its repeater index, which could show the wrong section's content if an
  earlier repeater row was invalid and skipped.

= 4.0.1 =
* Sections mode: ONE search box at the top of the sidebar instead of one per
  section. It searches inside the open section and keeps applying when
  switching sections. New Content > Sections controls: Show Search Box,
  Search Placeholder, Search Icon. The per-row "Enable Search" switch is now
  "Include in Search". Style > Search Box now also shows in Sections mode.
* Sections mode: the category divider dash is back. Flat entries (Berita
  Terbaru, Pengumuman) use the "Merged" divider, accordion headers use the
  "Accordion" divider, so Style > Category Divider (Merged/Accordion) and
  Category Name (Accordion) now show and apply in Sections mode too.
* Sections mode: flat entries now stretch to the sidebar width instead of a
  fixed `width: 100%`, so a negative Margin Left on Style > Sections Nav
  (e.g. Active state) grows the button to the left and keeps its right
  edge, instead of sliding the whole button left.
* Sections mode: accordion header arrow now flips when collapsed.

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
