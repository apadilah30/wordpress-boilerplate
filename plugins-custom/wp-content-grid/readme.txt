=== Content Grid ===
Contributors: apadilah
Tags: elementor, widget, news, video, grid
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
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
3. **Content > Section Header**: optional title + "View All" link.
4. **Style tab**: colors/typography for header, cards, badge, play icon,
   grid columns/gap — all responsive.

== Requirements ==

* Elementor (required)
* A public post type with a featured image set on its posts

== Changelog ==

= 1.0.0 =
* Initial release: News and Video layouts.
