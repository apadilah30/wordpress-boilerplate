# Empty State (Skeleton) - wp-taxonomy-grid v4.4.0

Permintaan: "jika tidak ada data tetap render saja layout nya walaupun kosong, namun hanya skeleton nya saja."

Audit lengkap (sebelum kode diubah): [AUDIT.md](AUDIT.md).

## 1. Yang berubah

- Widget TIDAK pernah hilang atau menulis teks kosong/error lagi. Header, breadcrumb, search, sidebar, dan panel konten selalu dirender sesuai switch. Bagian yang seharusnya berisi data jadi blok abu-abu (skeleton).
- Berlaku di semua mode: Sections, Single + Detail, Single + Grid.
- Satu komponen: `wptg_render_skeleton($variant, $count)` di `wp-taxonomy-grid.php`. Varian: `feed`, `grid`, `list`, `detail`, `sidebar_terms`. Dipilih otomatis dari Content Style section (`numbered_feed`=feed, `grid_thumbnail`=grid, `list_thumbnail`=list, `single_detail`=detail).
- Skeleton memakai kelas layout ASLI (`wptg-numbered-feed`, `wptg-feed-item`, `wptg-posts-grid`, dst.) ditambah `wptg-skeleton-*`. Jadi tinggi gambar, gap, dan radius kartu ikut kontrol Style yang sudah ada, dan responsif (<=1024, <=768) tanpa aturan baru.
- Teks publik yang dihapus: "No valid sections configured.", "No items found.", "No items in this category.", "Belum ada konten." (sekarang hanya `.wptg-sr-only`), "Tidak ditemukan.", dan baris `Error: ...` di Single mode.
- Pembaca layar: tiap skeleton konten punya `role="status"` + satu teks tersembunyi "Belum ada konten". Skeleton sidebar murni dekorasi (`aria-hidden`).
- Animasi shimmer mati di `prefers-reduced-motion: reduce`.
- Sections:
  - Section yang sudah dikonfigurasi tapi kosong TETAP muncul di sidebar. Flat = tombol label. Accordion = header + panah, isi term diganti skeleton.
  - Kalau tidak ada section berdata, section valid pertama jadi aktif (state kosong), jadi judul hero/breadcrumb/content title tetap terisi label.
  - Tidak ada section valid sama sekali: sidebar 4 baris skeleton, panel skeleton feed.
  - Klik section/term kosong: panel jadi skeleton (lewat AJAX yang sama, tanpa error).
  - Deep link ke section valid yang kosong: dibuka dalam state kosong. Param tidak valid tetap fallback ke default.
- AJAX `wptg_query_items`: halaman 1 tanpa hasil mengembalikan skeleton di `html` + `empty: true`. Halaman > 1 tanpa hasil: `html` kosong + `has_more: false` (tombol Load More dihapus). Skeleton dirender server dengan fungsi yang sama dengan load pertama, jadi tidak ada `<template>` tambahan.
- Catatan editor: user yang bisa `edit_posts` (dan editor Elementor) melihat satu baris kecil putus-putus di bawah widget yang menjelaskan kenapa kosong. Pengunjung tidak melihatnya.

## 2. Kontrol Style baru

Tab Style > **Empty State (Skeleton)** (muncul di semua mode):

| Label | Fungsi |
|-------|--------|
| Skeleton Color | warna dasar blok abu-abu (default `#e9eaee`) |
| Shine Color | warna pita cahaya yang bergeser (default `#f6f6f8`) |
| Border Radius | radius garis/blok teks (gambar kartu mengikuti radius kartu aslinya sampai nilai ini diisi) |

Bisa juga lewat Additional CSS: `.wptg-wrapper { --wptg-skeleton-color: #e5e7eb; --wptg-skeleton-shine: #f9fafb; --wptg-skeleton-radius: 6px; }`

## 3. Deploy

```bash
./scripts/sync-plugin.sh wp-taxonomy-grid     # versi 4.4.0
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/wp-taxonomy-grid.php
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/includes/class-elementor-widget.php
```

Lalu di WP Admin: Elementor > Tools > **Regenerate CSS & Data**, dan **hard refresh** browser (Ctrl+Shift+R) supaya CSS/JS baru terambil.

## 4. Cara tes state kosong

| # | Skenario | Yang diharapkan |
|---|----------|-----------------|
| 1 | Halaman dengan widget Single, CPT belum punya post terbit | Header, breadcrumb, search tampil. Sidebar 4 baris skeleton, panel skeleton detail/grid. Tidak ada teks "No items found". |
| 2 | Tarik widget baru (Single, Taxonomy belum diisi) | Layout skeleton, bukan error. Login editor: catatan "pilih Taxonomy di tab Content > Source." |
| 3 | Widget Sections, repeater kosong | Sidebar 4 baris skeleton, panel skeleton feed. Catatan editor "belum ada section valid". |
| 4 | Sections, satu section accordion tanpa term | Header + panah tetap ada, isi diganti skeleton. Klik header: panel jadi skeleton sesuai Content Style, label hero ikut berubah. |
| 5 | Sections, semua section tanpa data | Section pertama aktif, judul hero/breadcrumb/content title = labelnya, panel skeleton. |
| 6 | Deep link `?wptg_section=<post_type>` ke section kosong | Section itu terbuka dalam state kosong. |
| 7 | Deep link `?wptg_section=ngawur` atau term tidak ada | Fallback ke default seperti sebelumnya. |
| 8 | Search kata ngawur di section berdata | Panel jadi skeleton, tombol Load More hilang. Hapus kata: item kembali. |
| 9 | Klik term berdata setelah state kosong | Item muncul normal, Load More muncul bila ada halaman berikutnya. |
| 10 | Single detail, ketik kata ngawur di search sidebar | Daftar jadi skeleton pendek (bukan teks "Tidak ditemukan."). |
| 11 | OS dengan "reduce motion" aktif | Blok abu-abu diam, tanpa shimmer. |
| 12 | Logout, buka halaman kosong | Tidak ada catatan editor, hanya skeleton. |
| 13 | Popup media (klik video/foto) di widget berdata | Tetap jalan seperti 4.3.0. |

## 5. Catatan untuk editor

- Baris kecil "Taxonomy Grid (hanya terlihat oleh editor): ..." muncul di bawah widget untuk user yang login dan bisa edit post, juga di editor Elementor. Itu cuma alat bantu debug. Kalau mau lihat tampilan publik: buka halaman di jendela incognito.
- Layout skeleton feed selalu "1 kartu besar + kartu kecil" walau Feed Layout di widget diset All featured / All compact.
- Skeleton Single + Grid memakai kolom otomatis, tidak mengikuti setting Columns.

## 6. Troubleshooting

| Gejala | Cek |
|--------|-----|
| Skeleton tidak abu-abu / tidak ada bentuk | CSS lama ter-cache: sync plugin, Regenerate CSS, hard refresh. |
| Skeleton tidak bergerak | Normal bila OS memakai "reduce motion". Cek juga Shine Color tidak sama dengan Skeleton Color. |
| Teks lama "Tidak ditemukan." masih muncul | Plugin belum ter-sync ke `web/app/plugins` (symlink false, perlu `sync-plugin.sh`). |
| Layout kosong padahal seharusnya ada data | Baca catatan editor. Biasanya Post Type/Taxonomy tidak cocok, atau belum ada post terbit yang punya kategori (term tanpa post tidak ditampilkan). |
| Klik section kosong tidak mengubah panel | Buka Network tab, cek request `admin-ajax.php?action=wptg_query_items`. Harus 200 dengan `empty: true`. |
| Term tertentu tidak highlight setelah klik | Temuan lama (AUDIT D1): class `active` ditaruh di tombol, CSS membaca `li.active`. Bukan bagian perubahan ini. |
