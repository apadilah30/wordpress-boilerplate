=== Timeline ===
Contributors: apadilah
Tags: elementor, widget, timeline, history
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.1.0
Requires PHP: 8.0
Requires Plugins: elementor
License: GPLv2 or later

Vertical zig-zag timeline widget for Elementor. Every part is editable.

== Description ==

Adds a **"Timeline"** widget (category: *Timeline*) to the Elementor panel.
Cards alternate left and right of a center line, each with a dot on the
line, a year with a short decorative line beside it, and a description.
Cards on opposite sides overlap vertically (staggered) while the dots stay
evenly attached to the line. Pure CSS, no JavaScript.

On screens up to 767px wide the timeline becomes a single column with the
line on the left and every card on its right.

== Usage ==

1. Drag **Timeline** onto the canvas. It comes with five sample items.
2. **Content > Timeline Items:** edit, add, remove or reorder items. Each
   has a Year / Label, a rich-text Description and a Side
   (Auto / Left / Right).
3. **Content > Layout:** pick which side the first Auto item starts on, and
   whether to show the short line beside each year.
4. **Style tab** (all values are responsive where it makes sense):
   * Container: max width, padding, background.
   * Center Line: style (solid or dashed), color, thickness.
   * Dots: color, size, ring color/width, vertical position.
   * Spacing: space around the line, vertical gap between items, line
     column width on mobile.
   * Cards: padding, border radius, border, and Normal/Hover background and
     shadow.
   * Year: typography, color, space below, and the line beside it (color,
     length, thickness, gap).
   * Description: typography, color, alignment.

== How "Side" works ==

* **Auto** items alternate. The first Auto item uses *First Item Side*; each
  following Auto item takes the opposite side of the item before it.
* **Left / Right** forces that side. Two items forced to the same side are
  stacked one after the other so they never overlap.

== Tips ==

* If you enlarge the year text, raise **Dots > Vertical Position** so the dot
  stays level with the year.
* The distance between dots follows the card heights, as in the reference
  design, so longer descriptions create more space automatically.

== Requirements ==

* Elementor (required)

== Changelog ==

= 1.1.0 =
* Center Line: added a Line Style option (Solid or Dashed). The line is now
  drawn with a border instead of a background color so it can be dashed.

= 1.0.0 =
* Initial release.
