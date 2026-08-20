=== WP Boilerplate Announcement ===
Contributors: apadilah
Tags: announcement, banner, cpt, shortcode, elementor, i18n
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: wp-boilerplate-announcement
Domain Path: /languages

Reference plugin bawaan boilerplate — contoh integrasi WordPress: CPT + meta box + dynamic shortcode + i18n.

== Description ==

Plugin ini adalah **reference implementation** yang di-bundle dengan wordpress-boilerplate. Tujuannya bukan untuk production sebagai plugin utama, melainkan sebagai **template starting-point** untuk plugin custom lu sendiri.

Yang dicontohkan:

* Register custom post type (`wpba_announcement`) tanpa tergantung plugin CPT UI.
* Meta box native dengan nonce validation, sanitize input, escape output.
* `register_post_meta` untuk expose meta ke REST API + Gutenberg.
* Dynamic shortcode `[wpba_announcement]` dengan attribute-driven output.
* i18n lengkap: `load_plugin_textdomain`, `.pot` template, escaping i18n functions (`esc_html__`, `esc_attr__`, `_x`, etc.).
* Backward compatibility fallback (legacy option-based banner).
* Uninstall handler yang bersih (hapus option + hapus semua CPT posts).

== Shortcode ==

`[wpba_announcement]` — tampilkan 1 pengumuman terbaru
`[wpba_announcement latest="3"]` — 3 pengumuman terbaru
`[wpba_announcement id="42"]` — pengumuman ID 42 spesifik
`[wpba_announcement id="42" class="promo"]` — dengan CSS class tambahan

== Integrasi Elementor ==

Karena shortcode standar, bisa langsung di-drag Elementor's "Shortcode" widget ke halaman.

== Changelog ==

= 2.0.0 =
* NEW: Custom Post Type `wpba_announcement`.
* NEW: Shortcode `[wpba_announcement]` dinamis.
* NEW: i18n lengkap dengan `.pot` template.
* CHANGE: Legacy banner sekarang jadi fallback (tampil hanya kalau CPT kosong).

= 1.0.0 =
* Initial release: option-based banner sederhana.
