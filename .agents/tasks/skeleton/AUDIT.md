# AUDIT jalur "kosong" wp-taxonomy-grid (v4.3.0 -> 4.4.0)

Permintaan user: "jika tidak ada data tetap render saja layout nya walaupun kosong, namun hanya skeleton nya saja."

Audit ini ditulis SEBELUM kode diubah. Semua nomor baris dari v4.3.0.
Kolom "Aksi" = keputusan untuk 4.4.0.

## A. Sections mode (`wptg_render_sections_widget` dan turunannya)

| # | Lokasi | Perilaku sekarang | Aksi |
|---|--------|-------------------|------|
| A1 | `wp-taxonomy-grid.php` ~1786, `empty($sections)` | Return `<p class="wptg-empty">No valid sections configured.</p>`. Seluruh widget (hero, breadcrumb, search, sidebar) hilang. | Hapus return. Layout tetap dirender: sidebar = skeleton `sidebar_terms` (4 baris), panel = skeleton `feed`. Catatan kecil untuk editor saja. |
| A2 | ~1747, baris repeater tidak valid (`wptg_normalize_section_row` return null) | Baris di-skip diam-diam. | Tetap di-skip (post type/taxonomy-nya memang sudah tidak ada, tidak bisa dirender). Kalau SEMUA baris tidak valid, jatuh ke A1. Tambah guard `is_array($row)` supaya settings aneh tidak TypeError (strict_types). |
| A3 | ~1790, `wptg_resolve_default_active()` | Melewati section tanpa item/term. Return null kalau tidak ada section berdata. | Fungsi TIDAK diubah (tetap memilih section berdata). Tambah fungsi baru `wptg_resolve_empty_active()`: kalau null, section valid pertama jadi aktif (state kosong) supaya judul hero/breadcrumb/content title terisi label section. |
| A4 | ~1910, `$active_section === null` | `.wptg-placeholder` "Belum ada konten." | Ganti skeleton `feed`. Praktis hanya terjadi bila tidak ada section sama sekali. |
| A5 | `wptg_render_section_sidebar_group` (~1585) | Section TIDAK di-skip di sidebar (temuan: rancangan mengira di-skip, ternyata tidak). Tapi accordion tanpa term menghasilkan `<ul>` kosong (header + panah saja, ruang kosong). Flat list tetap tombol label. | Accordion tanpa term: isi diganti skeleton `sidebar_terms` (pakai `<div>` bukan `<ul>`, karena `<div>` di dalam `<ul>` tidak valid HTML). |
| A6 | `<p class="wptg-section-no-results">Tidak ditemukan.</p>` (~1670) | Teks "Tidak ditemukan." muncul saat filter sidebar single_detail tidak cocok apa pun (toggle JS lewat `style.display`). | Ganti jadi `<div class="wptg-section-no-results">` berisi skeleton `sidebar_terms`. JS tidak perlu diubah (toggle `style.display` sama). |
| A7 | `wptg_render_section_content_panel` single_detail tanpa post (~1695) | `.wptg-placeholder` "Pilih salah satu item di samping." | Ganti skeleton `detail`. |
| A8 | `wptg_render_section_content_panel`, query kosong | `<div class="wptg-section-items-wrap">` dengan list kosong (area kosong putih). Flat list tanpa post = list kosong. | Skeleton sesuai content_style, tanpa wrapper items dan tanpa tombol Load More. |
| A9 | Panel accordion dengan `term_id = 0` | Query TANPA tax_query, jadi menampilkan SEMUA post dari post type (taxonomy diabaikan). Saat ini tidak pernah terjadi karena term awal selalu > 0, tapi state kosong baru akan memicunya. | Term 0 pada section accordion = state kosong (skeleton), tidak ada query. Berlaku di SSR dan AJAX. |
| A10 | `wptg_resolve_deep_link` + `wptg_build_deep_link_active` | Section valid tapi kosong -> return null -> di-skip, jatuh ke default. | Setelah semua pass lama gagal, section pertama yang cocok post type-nya dibuka dalam state kosong. Urutan lama (term cocok -> section berdata) tidak berubah. Param tidak valid tetap fallback ke default. |
| A11 | `wptg_ajax_query_section_items`, hasil 0 (search tanpa hasil, term kosong) | `html` kosong, JS membungkus dengan list kosong. | Hasil 0 di halaman 1: `html` = skeleton (render server pakai helper yang sama), `empty: true`. Halaman > 1 tanpa hasil: `html` kosong + `has_more: false` (tombol Load More dihapus; skeleton di bawah item yang sudah ada tidak masuk akal). |
| A12 | `wptg_ajax_query_section_items`, whitelist `content_style` tanpa `single_detail` | Klik header accordion kosong single_detail tidak bisa dilayani. | `single_detail` ditambah ke whitelist, hanya untuk state kosong (langsung return skeleton `detail`, tidak query). |
| A13 | JS `fetchSectionItems` (replace) | Membungkus html dengan `.wptg-section-items-wrap` + tombol Load More bila `has_more`. | Bila `empty: true`, isi panel langsung `html` (skeleton), tanpa wrapper/tombol. |
| A14 | JS `initSectionsMode`, `activeSectionIndex` | Dicari dari `.wptg-section-items-wrap` atau `.wptg-post-li.active`. Di state kosong dua-duanya tidak ada -> `null` -> search tidak jalan. | Server menulis `data-active-section` di `<main class="wptg-sections-content">`, JS jadikan fallback. |
| A15 | JS toggle `.wptg-section-toggle` | Hanya buka/tutup accordion. Section tanpa term tidak punya tombol term, jadi panel tidak bisa pindah ke section itu. | Header accordion yang tidak punya `.wptg-section-term-li` juga membuka panel state kosong (lewat AJAX yang sama, jadi satu sumber skeleton). |
| A16 | Teks `Tidak ditemukan.` / `Belum ada konten.` / `No valid sections configured.` | Teks biasa. | Dihapus dari tampilan publik. Satu-satunya teks baru: `Belum ada konten` sebagai `.wptg-sr-only` di dalam skeleton konten (hanya pembaca layar). |

## B. Single mode (`wptg_resolve_and_render`, detail + grid)

| # | Lokasi | Perilaku sekarang | Aksi |
|---|--------|-------------------|------|
| B1 | ~255-272, post_type kosong / post type tidak ada / taxonomy kosong / taxonomy tidak ada / taxonomy bukan untuk post type | 5 kali `return '<p class="wptg-error">Error: ...</p>'`. Widget baru (taxonomy default kosong) menampilkan error ke pengunjung. | Tidak ada early return. Alasan dihitung jadi `$problem`, layout tetap dirender dengan `$terms = []`. Pesan hanya dirender untuk `current_user_can('edit_posts')`. `.wptg-error` tidak dipakai lagi (CSS dibiarkan, tidak merusak). |
| B2 | ~277, `empty($terms)` -> `<p class="wptg-empty">No items found.</p>` | Widget hilang. | Layout dirender dengan skeleton. Catatan editor "belum ada post". |
| B3 | `wptg_render_detail_mode`, `$groups` kosong | Sidebar kosong, panel kanan `.wptg-placeholder` "Pilih salah satu item di samping...". | `$groups` kosong: sidebar skeleton `sidebar_terms`, panel skeleton `detail`. |
| B4 | `wptg_render_detail_mode`, `$groups` ada tapi `active_first` mati | `.wptg-placeholder` "Pilih salah satu item di samping...". | DIBIARKAN (disengaja). Data ada, user memang belum memilih. Bukan state kosong. |
| B5 | `<p class="wptg-no-results">Tidak ditemukan.</p>` di sidebar detail | Teks saat filter sidebar tidak cocok. | `<div class="wptg-no-results">` + skeleton `sidebar_terms` (toggle JS tetap `style.display`). Tidak dirender bila `$groups` kosong (sudah skeleton). |
| B6 | `wptg_render_detail_mode(…, \WP_Post_Type $post_type_obj, …)` | Parameter non-null. | Jadi `?\WP_Post_Type`. Subtitle default kosong bila null. |
| B7 | `wptg_render_grid_mode`, `$terms` kosong | List kategori kosong; panel `.wptg-placeholder` "Select a category to view items.". | List kategori diganti skeleton `sidebar_terms`, panel skeleton `grid`. Parameter `\WP_Taxonomy` jadi nullable, judul sidebar h3 hanya dirender bila ada teks. |
| B8 | `wptg_render_grid_mode`, `active_first` mati, terms ada | `.wptg-placeholder` "Select a category to view items.". | DIBIARKAN (disengaja), alasan sama dengan B4. |
| B9 | `wptg_get_posts_html`, `!have_posts()` -> `.wptg-empty` "No items in this category." | Teks. Dipakai SSR grid dan AJAX `wptg_get_posts`. | Ganti skeleton `grid` (server, jadi AJAX ikut). |
| B10 | `is_singular($atts['post_type'])` dengan post_type kosong | `is_singular('')` = true di halaman singular, tidak berbahaya tapi menyesatkan. | Tambah guard post_type tidak kosong. |
| B11 | `wptg_ajax_get_single_post`, `wptg_ajax_get_posts` -> `wp_send_json_error` | Parameter salah (post tidak ada, dll.). | DIBIARKAN. Itu error request/manipulasi, bukan data kosong. JS menampilkan pesan error (lihat C3). |

## C. JS (`taxonomy-grid.js`)

| # | Lokasi | Perilaku sekarang | Aksi |
|---|--------|-------------------|------|
| C1 | `filterSidebar` / `filterWithinSectionGroup` | Toggle `style.display` pada elemen "tidak ditemukan". | Tidak diubah. Elemennya sekarang berisi skeleton (A6, B5). |
| C2 | `fetchSectionItems` | Lihat A13. | Diubah. |
| C3 | `.wptg-empty` "Error loading content/posts/items." (catch + `!data.success`) | Pesan error jaringan/server. | DIBIARKAN (disengaja). Bukan "data kosong"; menyembunyikan error jaringan akan menyulitkan debugging. |
| C4 | Teks "no results" hardcoded di JS | Tidak ada. Teks "Tidak ditemukan." hanya di PHP. | Tidak ada yang dihapus di JS. |
| C5 | `modal.js` / `media-popup.js` | Pesan error/loading popup. | Di luar cakupan (popup, bukan layout). Tidak diubah. |

## D. Temuan lain (tidak diubah, hanya dicatat)

- D1. `markActiveNav()` menambah class `active` ke tombol `.wptg-section-term-trigger`, sedangkan CSS dan kontrol Style "Active" memakai `.wptg-section-term-li.active .wptg-section-term-trigger`. Class `active` di `<li>` hanya dari render server, jadi setelah klik term lain, highlight bisa tertinggal di term awal. Sudah ada sebelum 4.4.0 dan bukan bagian permintaan skeleton, jadi TIDAK diubah. Perlu diuji user; kalau terbukti, jadi tugas terpisah.
- D2. Breakpoint CSS plugin yang sudah ada adalah `1024px` dan `768px` (bukan 767). Skeleton mengikuti breakpoint itu.
- D3. Elementor menaruh variabel layout (tinggi gambar featured/compact, gap kartu) di `.wptg-numbered-feed`. Karena itu skeleton feed memakai kelas asli (`wptg-numbered-feed`, `wptg-feed-item`, `wptg-feed-thumb`, dst.) ditambah kelas `wptg-skeleton-*`, supaya ukuran/gap/radius otomatis sama dengan layout asli dan ikut kontrol Style yang sudah ada.
