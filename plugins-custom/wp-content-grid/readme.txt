=== Content Grid ===
Contributors: apadilah
Tags: elementor, widget, news, video, grid
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.2.0
Requires PHP: 8.0
Requires Plugins: elementor
License: GPLv2 or later

Native Elementor widget for two common "latest posts grid" layouts:
Featured News Grid and Video Grid.

== Description ==

Adds a **"Content Grid"** widget (category: *Content Grid*) to the
Elementor panel. Drag it onto any page, pick a Post Type and a Layout
Style — no shortcode, no taxonomy setup required. Queries the latest
published posts by date only.

**Layout: Featured News Grid**
First N items shown as large cards (image + content side by side),
the rest as small cards — each with a numbered badge (01, 02, ...)
based purely on display order.

**Layout: Video Grid**
Uniform grid, each card with a centered play icon overlay on the
featured image, title, and date/author meta below.

== Usage ==

1. Drag **"Content Grid"** onto the canvas.
2. **Content > Source**: pick Layout Style, Post Type (e.g. `news` or
   `videos`), Number of Items, and (for News) how many are "Featured".
3. **Content > Item Link**: where a click goes (see "Item Link" below).
4. **Content > Section Header**: optional title + "View All" link.
5. **Style tab**: colors/typography for header, cards, badge, play icon,
   grid columns/gap — all responsive.

== Item Link ==

**Content > Item Link** controls where a click on a card goes:

* **Link Mode**
  * `Post permalink` (default): the post's own page, same as before 1.1.0.
  * `Content Navigator page (deep link)`: a page that has a Taxonomy Grid
    widget in Sections mode, opened on the clicked item's section and
    category.
  * `Media Popup (video / photo)` (since 1.2.0): a click opens a popup on the
    same page instead of leaving it. See "Media Popup" below.
* **Target Page URL** (navigator mode only): e.g. `/news/`. The standard
  Elementor link box, so "Open in new window" and "Add nofollow" work too.
  Empty = cards link to the post page, so no link is ever broken.
* **Taxonomy** (navigator mode only): the taxonomy slug that holds the
  category. Empty = guess `{post_type}_category`; if that is not registered,
  the first public hierarchical taxonomy of the post type; if there is none,
  only the section is sent. It must be the same taxonomy as the matching row
  of the Taxonomy Grid widget on the target page.

The generated link is:

`{Target Page URL}?wptg_section={post_type}&wptg_term={term_slug}`

* `wptg_section` is the post type slug of the clicked item (`gallery`,
  `videos`, `news`, ...).
* `wptg_term` is the slug of the item's first term in that taxonomy. The
  default "Uncategorized" term is skipped when the item has another term.
  Left out when the item has no term.

The Taxonomy Grid plugin (4.2.0+) reads these two params and opens the
matching section and category. This plugin and Taxonomy Grid share only the
param names, no code. Video Grid cards behave the same way: in navigator
mode the whole card links to the target page.

== Media Popup ==

With **Content > Item Link > Link Mode = Media Popup (video / photo)** a card
stays a normal link (`<a href="post permalink">`, so it still works without
JavaScript, for search engines and for "open in new tab") and gets one extra
attribute: `data-wptg-media="<post id>"`. The Taxonomy Grid plugin (4.3.0+)
listens for that attribute and opens its popup: a post with a video plays the
video, a post without video shows its photo (see the Taxonomy Grid readme,
"Media Popup", for the order in which the video source is found).

* Soft dependency: this plugin never calls Taxonomy Grid code. It only checks
  that the function `wptg_build_media_modal_payload()` exists. When Taxonomy
  Grid is not active, the cards link to the post as usual (no error), and in
  the Elementor editor a small note tells editors why.
* The widget asks for the script `wptg-media-popup` and the style
  `wptg-styles` (both registered by Taxonomy Grid) through
  `get_script_depends()` / `get_style_depends()`, only when they are
  registered. The render also enqueues them as a safety net.
* The default Link Mode stays "Post permalink", so existing widgets do not
  change.
* The same helper, `wpcg_build_item_url()` + `wpcg_build_item_link_attrs()`,
  serves every card (featured news, small news, video).

== Requirements ==

* Elementor (required)
* A public post type with a featured image set on its posts
* For deep links: Taxonomy Grid 4.2.0+ in Sections mode on the target page
* For Media Popup: Taxonomy Grid 4.3.0+ active

== Changelog ==

= 1.2.0 =
* New Link Mode option **Media Popup (video / photo)** (value `media_popup`).
  Cards stay `<a href="permalink">` and get `data-wptg-media="<post id>"`; the
  Taxonomy Grid plugin (4.3.0+) opens the popup. Default stays "Post
  permalink", so existing widgets are unchanged.
* Soft dependency on Taxonomy Grid: when it is not active the card links to
  the post, no fatal error, and editors see a small note in the Elementor
  editor.
* `wpcg_build_item_link_attrs()` now takes the post id (it adds the
  `data-wptg-media` attribute); `wpcg_normalize_link_options()` returns an
  extra key `popup_missing`. The widget lists `wptg-media-popup` and
  `wptg-styles` in `get_script_depends()` / `get_style_depends()` when they are
  registered. New `WPCG_VERSION` constant used for the stylesheet version.

= 1.1.0 =
* New Content > Item Link section: Link Mode (Post permalink / Content
  Navigator page (deep link)), Target Page URL, Taxonomy. Cards in both
  layouts can now link to `{target}?wptg_section=<post_type>&wptg_term=<term_slug>`.
* Default stays "Post permalink", so existing widgets are unchanged.
* Navigator mode with an empty Target Page URL falls back to the permalink.
* One helper, `wpcg_build_item_url()`, builds the link for every card
  (featured, small and video cards).

= 1.0.0 =
* Initial release: News and Video layouts.
