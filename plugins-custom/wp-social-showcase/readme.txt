=== Social Media Showcase ===
Contributors: apadilah
Tags: elementor, widget, social media, gallery, tabs, feed
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.1.0
Requires PHP: 8.0
Requires Plugins: elementor
License: GPLv2 or later

Social platform icon tabs + per-platform panel (manual 4x4 gallery or a
feed-plugin shortcode) + an "Open Account" button.

== Description ==

Adds a **"Social Media Showcase"** widget to the Elementor panel.
Each social platform (Instagram, X, Facebook, TikTok, YouTube, or any
other - fully customizable) becomes a clickable icon tab. Clicking a tab
shows that platform's panel and updates the "Open Account" button to link
to that platform's URL.

Each platform panel has a **Feed Source**:

* **Manual Gallery** (default): up to 16 images picked from the
  WordPress Media Library, shown as a 4x4 grid.
* **Shortcode (from a feed plugin)**: paste the shortcode of a feed
  plugin (e.g. Smash Balloon, Feed Them Social) and the panel prints its
  output, so the feed updates itself.

**This widget does NOT connect to any social media API.** Connecting
accounts (OAuth, token refresh, rate limits) is done by the feed plugin.
This widget only provides the icon tabs, the panel switching and the
"Open Account" button. The look of the feed itself (cards, columns,
colors) is configured in the feed plugin, not here.

== Usage ==

1. Drag **"Social Media Showcase"** onto the canvas.
2. **Content > Platforms**: add/edit entries in the repeater - each has
   an Icon, Label, Account URL and a Feed Source.
3. Feed Source = **Manual Gallery**: pick up to 16 images.
   Feed Source = **Shortcode (from a feed plugin)**: paste the shortcode
   into **Feed Shortcode**, e.g. `[instagram-feed feed=1]`.
4. **Style tab**: tab icon size/colors (Normal/Active), gap, Open button
   styling, gallery columns/gap/radius, and **Feed Container** (padding,
   background, border radius, min height around the feed) - all responsive.

Notes:

* Switching to Shortcode mode only hides the gallery images; they are
  kept if you switch back to Manual Gallery.
* Only a value made of shortcode(s) only is accepted. Logged-in users who
  can edit posts see a short hint when the shortcode is invalid or the
  feed is empty; visitors see nothing.
* Switching tabs fires a window `resize` event so feed plugins can
  re-calculate their layout once the panel is visible.

== Requirements ==

* Elementor (required)
* A feed plugin, only if you use Shortcode mode

== Changelog ==

= 1.1.0 =
* New: Feed Source per platform (Manual Gallery / Shortcode from a feed plugin).
* New: Feed Shortcode field, rendered with do_shortcode().
* New: Style section "Feed Container" (padding, background, border radius, min height).
* Tab switching now fires a window resize event so hidden feeds lay out correctly.
* Existing widgets keep working as a manual gallery (no migration needed).

= 1.0.0 =
* Initial release.
