=== Social Media Showcase ===
Contributors: apadilah
Tags: elementor, widget, social media, gallery, tabs
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 8.0
Requires Plugins: elementor
License: GPLv2 or later

Social platform icon tabs + a manually curated 4x4 gallery grid + an
"Open Account" button, per platform.

== Description ==

Adds a **"Social Media Showcase"** widget to the Elementor panel.
Each social platform (Instagram, X, Facebook, TikTok, or any other —
fully customizable) becomes a clickable icon tab. Clicking a tab shows
that platform's gallery (up to 16 images, as a 4x4 grid) and updates the
"Open Account" button to link to that platform's URL.

**This widget does NOT connect to any social media API.** Images are
manually picked from the WordPress Media Library per platform. If a
LIVE, auto-syncing feed is ever needed instead, use a dedicated plugin
for that (e.g. the already-installed Feed Them Social) — API
integration (OAuth, token refresh, rate limits) is a significant,
ongoing maintenance burden better left to an actively maintained tool.

== Usage ==

1. Drag **"Social Media Showcase"** onto the canvas.
2. **Content > Platforms**: add/edit entries in the repeater — each has
   an Icon, Label, Account URL, and a Gallery (pick up to 16 images).
3. **Style tab**: tab icon size/colors (Normal/Active), gap, Open button
   styling, gallery columns/gap/radius — all responsive.

== Requirements ==

* Elementor (required)

== Changelog ==

= 1.0.0 =
* Initial release.
