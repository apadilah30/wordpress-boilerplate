# Audit: widget Taxonomy Grid (Sections mode) vs desain halaman "Berita"

Tanggal: 2026-10-27. Basis: kode plugin `plugins-custom/wp-taxonomy-grid` (v4.1.0 di header, readme masih 4.0.2) + `.agents/tasks/content-navigator/*`.
Nama label di kolom "Control" diambil dari `includes/class-elementor-widget.php`, bukan dari asumsi.

Legenda: OK = ada & cocok, KURANG = ada tapi kurang, BELUM = belum ada.

## 1. Tabel gap

| # | Bagian desain | Status | Temuan di kode | Control sebenarnya (label di UI) |
|---|---|---|---|---|
| 1 | Hero: subtitle kecil di ATAS judul | OK | `wptg_render_header_block()` render subtitle lalu `h1`. Warna default putih / emas. | Content > Header: `Show Header`, `Subtitle`, `Title`. Style > Header: Subtitle/Title typography, color, margin, alignment |
| 2 | Hero: garis dekoratif kiri & kanan JUDUL | KURANG | Garis ada (`.wptg-header-line-start/end`) tapi mengapit blok subtitle+judul sekaligus (sejajar tengah dua baris), bukan judul saja. Tidak ada switch tampil/sembunyi. Jarak garis ke judul hardcode `gap: 1.5rem`. Warna/length/thickness sudah ada. | Style > Header > `Decorative Lines`: `Start Line (Left)`, `End Line (Right)`, `Color`, `Length`, `Thickness`. Belum ada switch dan gap |
| 3 | Breadcrumb `Beranda -> Berita -> Berita Terbaru` | OK | 3 item, separator = ICONS control (default chevron kanan, warna aksen emas), item terakhir `font-weight: 600` + typography sendiri. | Content > Breadcrumb: `Show Breadcrumb`, `Home Label`, `Middle Label`, `Current Label`, `Separator Icon`. Style > Breadcrumb: `Link Text`, `Current Item`, `Icon Color`, `Icon Size`, `Icon Margin` |
| 4 | Sidebar: search pill (ikon di kanan) | OK | `.wptg-search` + `.wptg-search-input` radius 20px, ikon absolute kanan. Warna bg default abu muda (krem tinggal diganti di Style). | Content > Sections: `Show Search Box`, `Search Placeholder`, `Search Icon`. Style > Search Box |
| 5 | Sidebar: flat 'Berita Terbaru' pill emas active, margin kiri negatif, teks putih bold | KURANG | Margin negatif sudah jalan (tombol `align-self: stretch; width: auto`, Style > Sections Nav > Active > Margin). Tab Hover/Active TIDAK punya Text Color sendiri; `Label Color` global menimpa warna `.active` bawaan (putih) karena specificity Elementor lebih tinggi. | Style > `Sections Nav (Flat Entries)`: `Label Typography/Color`, tab Normal/Hover/Active: Margin, Padding, Background, Border Radius |
| 6 | Sidebar: flat 'Pengumuman' + divider emas horizontal di kiri | OK | Flat entry punya `.wptg-term-divider-merged` (child tombol). | Style > `Category Divider (Merged)` |
| 7 | Sidebar: accordion 'Galeri Foto/Video' + divider emas + panah segitiga emas | KURANG | Divider dan panah (segitiga CSS, emas, flip saat tutup) ada. Tapi warna/size/jarak panah hanya di-wire ke `.wptg-term-group` (Single mode), jadi TIDAK bisa diedit di Sections mode. Jarak antar entri sidebar hardcode `1.5rem`. | Style > `Category Divider (Accordion)`, `Category Name (Accordion)`. Panah: hanya di `Sidebar / Accordion` (kondisi Single) |
| 8 | Isi term kecil di dalam accordion | BELUM (style) | Term tampil (`.wptg-section-term-trigger`) dengan CSS hardcode. Tidak ada satu pun control Style (typography, warna Normal/Hover/Active, padding, margin, indent `padding-left: 1.4rem`, gap antar term `margin-bottom: .2rem`). | Tidak ada |
| 9 | Judul h2 konten ('Berita Terbaru') di atas panel | BELUM | Tidak ada elemen/judul di panel konten Sections. Panel `.wptg-sections-content` seluruh isinya diganti oleh JS. | Tidak ada |
| 10 | Search di konten, lebar penuh | BELUM | Hanya satu search di sidebar. Posisi tidak bisa dipilih. JS cuma mengikat `.wptg-search-input` pertama. | Tidak ada `Search Position` |
| 11 | Feed: nomor urut 2 digit | KURANG | Ada `.wptg-feed-badge` format `%02d` dan lanjut lintas Load More (offset `(page-1)*ppp`), reset ke 01 saat search/ganti section. Tapi posisi di atas gambar (badge kecil navy), bukan angka besar emas di kolom teks; tidak ada switch tampil; hanya bg/padding/margin/radius, tanpa typography/color. | Style > `Numbered Feed` > `Badge Number` |
| 12 | Feed: tanggal, judul | OK | `.wptg-feed-date`, `.wptg-feed-title` + typography/color. | Style > Numbered Feed > `Title`, `Date` |
| 13 | Feed: excerpt 4-5 baris diakhiri '...' | BELUM | Tidak ada excerpt di `wptg_render_item_numbered_feed()`. | Tidak ada |
| 14 | Feed: 'Baca Selengkapnya ->' + ikon, kanan bawah | KURANG | Teks hardcode (`esc_html_e`), tanpa ikon, tidak di kanan bawah; ada typography + alignment. | Style > Numbered Feed > `"Baca Selengkapnya" Affordance` |
| 15 | Feed: item 1 featured vs item 2..6 compact | KURANG | Item pertama (page 1, tanpa search) dapat `--featured` (thumb 220px, tinggi stretch). Sisanya satu varian 140px + badge. Tidak ada varian compact ±75px, tidak ada pilihan layout, tidak ada kartu krem/radius/shadow/gap/image controls. Featured juga hilang saat search. | Style > Numbered Feed: `Padding` saja untuk item |
| 16 | Load more: teks & style, hilang bila habis, loading state | KURANG | Tombol hilang bila habis (OK). Teks hardcode di PHP dan di JS (dua tempat). Style: text, margin, padding, bg, border. Tanpa radius/hover/alignment. Loading = seluruh list dikasih opacity+spinner, tombol tidak disable (bisa dobel klik). | Style > `Load More Button` |
| 17 | Click behavior | OK | Per-section `Item Click Behavior`: inline_detail / modal / redirect_or_popup_player. | Content > Sections (repeater) > `Item Click Behavior` |
| 18 | Responsif | KURANG | Satu breakpoint 768px. Sidebar jadi atas konten di <=768, tapi di 769-1024 tetap berdampingan. Semua kartu feed jadi kolom vertikal di mobile (compact ikut vertikal). Bug: control `Sidebar Width` menulis `flex: 0 0 280px` via selector Elementor (specificity lebih tinggi dari media query plugin), jadi saat body kolom nilai itu jadi tinggi 280px. | Style > Container > `Sidebar Width` (responsive tapi tanpa default tablet/mobile) |

## 2. Catatan arsitektur yang mempengaruhi implementasi

- `.wptg-sections-content` ditimpa penuh oleh JS (`content.innerHTML = ...`, `loadSinglePost`). Judul dan search konten harus berada DI LUAR area yang ditimpa. Solusi: tambah `.wptg-sections-panel` di dalam `<main>`, JS menimpa panel saja.
- AJAX `wptg_query_items` merender item tanpa tahu pengaturan widget. Pengaturan feed (layout, nomor, panjang excerpt, teks read-more) dikirim dari data-attribute wrapper, divalidasi whitelist di server. Ikon read-more (HTML dari Elementor ICONS) TIDAK dikirim lewat AJAX; SSR merender langsung, item hasil AJAX diisi JS dari `<template>`.
- `is_first_overall` sekarang `page === 1 && search === '' && i === 0`. Akan jadi `page === 1 && i === 0` supaya hasil search juga mulai dengan kartu 01 yang konsisten.
- Search input di Sections dipasang hanya ke satu elemen (`querySelector`). Perlu diubah ke semua `.wptg-search-input` di wrapper dengan satu state `searchQuery`.

## 3. Rencana implementasi (urut kerja)

1. PHP inti (`wp-taxonomy-grid.php`): header lines (show + struktur), helper search box, opsi feed ternormalisasi, render item feed (featured/compact, nomor, excerpt, read-more + ikon), load-more helper, markup `<main>` baru (judul + search konten + panel), handler AJAX terima opsi feed.
2. Widget (`class-elementor-widget.php`): control Content baru (Show Decorative Lines, Search Position, Show Content Title, Content Title, panel `Feed & Load More`), Style baru (Content Title, Accordion Terms, feed card/image/number/excerpt/read-more, load more tambahan, header gap), perbaikan Sidebar Width tablet/mobile.
3. CSS: header title row, kartu featured/compact, judul konten, responsif 1024/767.
4. JS: panel target, multi-input search tersinkron, judul konten dinamis, opsi feed ke AJAX, ikon read-more, loading state tombol.
5. Verifikasi, readme 4.1.0, daily.

## 4. Hasil implementasi (v4.1.0)

| # tabel | Gap | Status akhir |
|---|---|---|
| 2 | Garis dekoratif mengapit judul saja, switch tampil, gap | Selesai: markup baru `.wptg-header-title-row`; `Show Decorative Lines` (Content > Header); `Gap Between Lines & Title` (Style > Header) |
| 5 | Text Color Hover/Active flat nav | Selesai: `Text Color` di tab Hover dan Active (Style > Sections Nav) |
| 7 | Panah accordion + jarak entri sidebar di Sections | Selesai: `Accordion Arrow` (Arrow Color/Size/Spacing) + `Space Between Sidebar Entries` di Style > Accordion Terms (Sections) |
| 8 | Style term di accordion | Selesai: Style > Accordion Terms (Sections) |
| 9 | h2 judul konten | Selesai: `Show Content Title`, `Content Title`, Style > Content Title (Sections); teks ikut section aktif lewat JS |
| 10 | Search di konten + Search Position | Selesai: `Search Position` Sidebar/Content/Both (default Both), satu state `searchQuery` di JS |
| 11, 13, 14, 15 | Kartu feed: nomor, excerpt, read-more + ikon, featured vs compact | Selesai: `Feed Layout`, `Show Number`, `Excerpt Length (words)`, `Read More Text`, `Read More Icon` + Style > Feed Cards (Featured / Compact) |
| 16 | Load more | Selesai: `Load More Text`, radius/alignment/hover, state loading (disabled + spinner) |
| 18 | Responsif | Selesai (belum diuji browser): blok `@media (max-width: 1024px)` untuk Sections, nomor mengecil di 768, sidebar full width dengan `!important` |
| 1, 3, 4, 6, 12, 17 | Sudah cocok | Tidak diubah |

Tidak dikerjakan: lihat ringkasan akhir (default `Sidebar Width` tetap 280px, bukan 140px; tidak ada `Show Excerpt` terpisah dari `Excerpt Length`).

## 5. Verifikasi (jujur)

- `node --check` untuk `taxonomy-grid.js` dan `modal.js`: lulus.
- CSS: jumlah `{` = jumlah `}` (179 = 179, komentar dibuang).
- PHP: TIDAK di-lint (php tidak ada di host). Pengganti: `check-php-brackets.js` (kurung `{ ( [` seimbang di 3 file PHP) dan `check-control-ids.js` (tidak ada ID control ganda; 3 "duplikat" yang muncul adalah false positive dari kondisi `'name' => 'wptg_source_mode'` dan string `'wptg_sterm_' . $state`). Semua PHP yang diubah dibaca ulang baris per baris.
- BOM: `wp-taxonomy-grid.php` ternyata punya BOM (dari percobaan sebelumnya, byte 239,187,191). Sudah dibuang. Tidak ada BOM lagi di semua file plugin. File LF saja.
- Belum diuji di browser/WordPress/Elementor editor. Belum ada tes otomatis.
