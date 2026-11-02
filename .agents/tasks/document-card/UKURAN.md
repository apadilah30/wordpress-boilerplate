# Ukuran kartu dokumen: desain vs nilai yang diterapkan (wp-taxonomy-grid 4.6.0)

**Baca dulu (jujur):** angka "Desain" di bawah adalah **estimasi dari screenshot** (gambar lebar kira-kira 580 px, kartu kira-kira 528 px), bukan hasil pengukuran otomatis. Tidak ada alat yang membandingkan piksel di sini. "Persis 1:1" **belum terbukti** sampai lu membuka `preview.html` di browser dan membandingkannya dengan desain. Kalau ada yang meleset, ubah nilai di kolom "Default" (CSS plugin) atau lewat control Elementor di kolom terakhir.

Cara membandingkan: buka `.agents/tasks/document-card/preview.html` (kontainer 528 px, plus iframe 360 px dan 320 px). Markup di sana adalah output asli renderer PHP; hanya gambar cover yang diganti gradien putih ke abu.

Semua default ada di `plugins-custom/wp-taxonomy-grid/assets/css/taxonomy-grid.css` dalam bentuk `var(--nama, default)`. Control Elementor di Style > Document Card hanya mengisi variabelnya (kecuali Typography, Border, dan Box Shadow, yang menulis properti biasa). Nilai kolom "Tablet/Mobile" berlaku di `max-width: 1024px`; yang bertanda 767 hanya di `max-width: 767px`; yang bertanda 359 hanya di `max-width: 359px`.

## Kartu

| Elemen | Desain (estimasi) | Variabel CSS | Default desktop | Tablet / mobile | Control Elementor (Style > Document Card) |
|---|---|---|---|---|---|
| Jarak antar kartu | 16-18 px | `--wptg-doc-list-gap` | 17px | sama | Gap Between Cards |
| Background | krem hangat `#F9F5F1` | `--wptg-doc-card-bg` | #f9f5f1 | sama | Normal > Background Color |
| Background hover | tidak ada di desain | `--wptg-doc-card-bg-hover` | #f3ece3 (karangan gue) | sama | Hover > Background Color |
| Radius kartu | 8 px | `--wptg-doc-card-radius` | 8px | sama | Border Radius |
| Shadow kartu | tipis lembut, mis. `0 2px 6px rgba(0,0,0,.08)` | (properti `box-shadow` langsung) | 0 2px 6px rgba(0,0,0,.08) | sama | Normal > Box Shadow |
| Padding | 20-22 px | `--wptg-doc-card-padding` | 20px 22px | 15px | Padding |
| Border | tidak ada | (properti border langsung) | tidak ada | sama | Border |

## Baris 1: ikon + teks

| Elemen | Desain (estimasi) | Variabel CSS | Default desktop | Tablet / mobile | Control Elementor |
|---|---|---|---|---|---|
| Layout head | flex horizontal, rata atas | (tetap) `align-items: flex-start` | sama | tetap horizontal; di bawah 359 px jadi kolom (ikon di atas teks) | tidak ada |
| Jarak ikon ke teks | sekitar 24 px | `--wptg-doc-head-gap` | 24px | 16px (767), 10px (359) | Icon > Gap to Text |
| Ikon: lebar | 52-54 px | `--wptg-doc-icon-size` | 53px | 42px | Icon > Icon Size |
| Ikon: tinggi | 50-58 px | (otomatis dari rasio SVG 48:46) | sekitar 51px | sekitar 40px | ikut Icon Size |
| Ikon: warna | emas `#F0B95A` | `--wptg-doc-icon-color` | #f0b95a | sama | Icon > Icon Color |
| Ikon: margin | tidak ada | (properti `margin` langsung) | 0 | sama | Icon > Icon Margin |
| Ikon: tidak menyusut | ya | `flex: 0 0 auto` | ya | ya | tidak ada |
| Judul: warna | emas `#F0B95A` | `--wptg-doc-title-color` | #f0b95a | sama | Title > Title Color |
| Judul: warna hover | tidak ada di desain | `--wptg-doc-title-color-hover` | sama dengan normal | sama | Title > Title Hover Color |
| Judul: ukuran | 18-20 px | (properti `font-size` langsung) | 19px | 16px | Title > Title Typography |
| Judul: weight | 700 | (properti langsung) | 700 | sama | Title Typography |
| Judul: line-height | sekitar 1.35 | (properti langsung) | 1.35 | sama | Title Typography |
| Jarak judul ke tanggal | 6-8 px | (properti `margin-top` langsung) | 7px | 6px | Date > Date Typography tidak mengatur margin; ubah di CSS |
| Tanggal: warna | navy `#1B365D` | `--wptg-doc-date-color` | #1b365d | sama | Date > Date Color |
| Tanggal: ukuran | 13-14 px | (properti langsung) | 13.5px | 12px | Date > Date Typography |
| Tanggal: format | `1 Oktober 2026` (tanpa nol) | (PHP) `get_the_date('j F Y')` | sama | sama | label: Content > Date Label |
| Jumlah file ("3 file") | tidak ada di desain | (properti langsung) | 12.5px, abu `#6b7280`, default **mati** | sama | Content > Show File Count; File Count > Typography/Color |

## Baris 2: cover dan tombol

| Elemen | Desain (estimasi) | Variabel CSS | Default desktop | Tablet / mobile | Control Elementor |
|---|---|---|---|---|---|
| Posisi cover | di bawah baris 1, lebar penuh isi kartu | (tetap) | sama | sama | tidak ada |
| Jarak head ke cover | 12-14 px | `--wptg-doc-cover-gap` | 13px | 12px | Cover > Space Above Cover |
| Tinggi cover | 116 px (tinggi tetap) | `--wptg-doc-cover-height` | 116px | 96px | Cover > Cover Height |
| Radius cover | 6 px | `--wptg-doc-cover-radius` | 6px | sama | Cover > Cover Border Radius |
| Shadow cover | lembut, mis. `0 3px 8px rgba(0,0,0,.18)` | (properti `box-shadow` langsung) | 0 3px 8px rgba(0,0,0,.18) | sama | Cover > Box Shadow |
| Object fit | cover | (properti langsung) | cover | sama | Cover > Cover Object Fit |
| Object position | center top | (properti langsung) | center top | sama | Cover > Cover Object Position |
| Posisi tombol (overlay) | pojok kanan atas cover, top 9 px, right 9 px | `--wptg-doc-actions-top`, `--wptg-doc-actions-right` | 9px, 9px | sama | Buttons > Overlay Offset Top / Right |
| Jarak antar tombol | 6 px | `--wptg-doc-actions-gap` | 6px | sama | Buttons > Gap Between Buttons |
| Urutan tombol | Lihat lalu Unduh (kiri ke kanan) | (urutan markup) | sama | sama | tidak ada |
| Tombol: lebar | sekitar 52 px | `--wptg-doc-btn-min-width` | 52px (min-width; teks panjang melebar) | 48px | Buttons > Button Min Width |
| Tombol: tinggi | sekitar 22 px | `--wptg-doc-btn-height` | 22px (min-height) | 20px | Buttons > Button Min Height |
| Tombol: padding | tidak diketahui | `--wptg-doc-btn-padding` | 0 7px | sama | Buttons > Button Padding |
| Tombol: radius | pill penuh | `--wptg-doc-btn-radius` | 999px | sama | Buttons > Button Border Radius |
| Tombol: font | 11 px, weight 500, putih | (properti langsung) | 11px / 500 | 10.5px | Buttons > Button Typography |
| Tombol: ikon | sekitar 12 px, di kiri teks | `--wptg-doc-btn-icon-size` | 12px | sama | Buttons > Button Icon Size |
| Lihat: background | navy `#17305B` | `--wptg-doc-view-bg` | #17305b | sama | View Button > Normal > Background Color |
| Lihat: teks | putih | `--wptg-doc-view-color` | #ffffff | sama | View Button > Normal > Text Color |
| Lihat: hover | tidak ada di desain | `--wptg-doc-view-bg-hover`, `--wptg-doc-view-color-hover` | #0f2347 (karangan gue), teks ikut normal | sama | View Button > Hover |
| Lihat: ikon | mata (eye) | (SVG bawaan) | ya | sama | Content > Show View Icon |
| Unduh: background | emas `#EDB75B` | `--wptg-doc-download-bg` | #edb75b | sama | Download Button > Normal > Background Color |
| Unduh: teks | putih | `--wptg-doc-download-color` | #ffffff | sama | Download Button > Normal > Text Color |
| Unduh: hover | tidak ada di desain | `--wptg-doc-download-bg-hover`, `--wptg-doc-download-color-hover` | #e0a444 (karangan gue), teks ikut normal | sama | Download Button > Hover |
| Unduh: ikon | panah ke bawah menuju garis | (SVG bawaan) | ya | sama | Content > Show Download Icon |
| Tanpa cover | tombol jadi baris di bawah head, rata kanan, ukuran/warna sama | (aturan `.wptg-doc-card > .wptg-doc-card-actions`) | sama | sama | tidak ada |

## Hal yang gue ketahui belum pasti

- **Kontras:** teks putih di atas emas `#EDB75B` kira-kira 1,8 : 1, dan judul emas `#F0B95A` di atas krem `#F9F5F1` juga rendah (sekitar 1,7 : 1), jauh di bawah batas WCAG (4,5 : 1). Itu angka dari desain; ubah warnanya kalau aksesibilitas lebih penting daripada 1:1.
- **Tombol kecil:** 22 px (20 px di mobile) jauh di bawah target sentuh yang disarankan (sekitar 44 px). Sesuai desain; area klik kartu (mouse/sentuh) tetap seluruh kartu.
- **Ikon:** gambar sendiri (dokumen dengan sudut terlipat, 4 garis teks, pensil di sisi kanan bawah). Bentuk persisnya tidak dari desain; ikon bawaan hanya mirip. Ganti lewat Content > Card Icon bila perlu.
- **Typography:** font keluarga mengikuti tema (`font-family: inherit`/stack plugin). Kalau desain memakai font tertentu, set di Title / Date / Button Typography.
- **Hover** kartu, judul, dan kedua tombol tidak ada di desain; nilainya gue pilih sendiri.
- Titik pindah responsif memakai viewport (`max-width: 1024px` / `767px` / `359px`), bukan lebar kolom. Kartu 360 px di kolom sempit pada layar desktop tetap memakai ukuran desktop; itu sebabnya `preview.html` memakai iframe untuk versi 360 / 320.
