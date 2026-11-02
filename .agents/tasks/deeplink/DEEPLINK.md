# Deep link: klik item di beranda -> halaman Berita dengan sidebar langsung terbuka

Status: kode sudah ditulis, **PHP belum di-lint** (`php` tidak ada di host) dan **belum diuji di browser/Elementor**. Ikuti langkah 2 dulu, baru tes.

## 1. Apa yang berubah

Klik kartu di widget **Content Grid** (beranda) sekarang bisa diarahkan ke halaman yang berisi widget **Taxonomy Grid** (mode Sections), dan halaman itu langsung membuka section + kategori dari item yang diklik.

Dua plugin ini **tidak saling memanggil kode**. Mereka cuma sepakat soal dua nama parameter URL:

```
/news/?wptg_section=<post_type>&wptg_term=<term_slug>
```

| Param | Isi | Wajib |
|---|---|---|
| `wptg_section` | slug post type, mis. `gallery`, `videos`, `news` | ya |
| `wptg_term` | **slug** term (bukan ID) dari taxonomy section itu | tidak |

Kenapa slug, bukan ID: URL bisa dibaca orang dan tidak berubah kalau DB di-restore/dipindah.

| Plugin | Versi | Perubahan |
|---|---|---|
| `wp-content-grid` | 1.0.0 -> **1.1.0** | Section baru `Item Link`: Link Mode, Target Page URL, Taxonomy. Default tetap permalink, jadi widget lama tidak berubah |
| `wp-taxonomy-grid` | 4.1.0 -> **4.2.0** | Mode Sections membaca `wptg_section` / `wptg_term` dan merender section + term itu langsung dari server. Tidak ada control baru |

## 2. Langkah sync (di terminal WSL, folder repo)

```bash
./scripts/sync-plugin.sh wp-taxonomy-grid
./scripts/sync-plugin.sh wp-content-grid
```

Lalu:

1. Cek versi di WP Admin > Plugins: Taxonomy Grid **4.2.0**, Content Grid **1.1.0**.
2. Elementor > Tools > **Regenerate CSS & Data**, lalu hard refresh browser (Ctrl+F5). Ini supaya JS/CSS versi baru terambil.
3. (Disarankan) cek sintaks PHP di container:
   ```bash
   docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/wp-taxonomy-grid.php
   docker compose exec app php -l web/app/plugins/wp-content-grid/wp-content-grid.php
   docker compose exec app php -l web/app/plugins/wp-content-grid/includes/class-elementor-widget.php
   ```

## 3. Atur widget Content Grid di beranda

Edit beranda dengan Elementor, klik widget Content Grid, tab **Content** > section **Item Link** (baru, ada di bawah Source).

| Control | Isi |
|---|---|
| **Link Mode** | pilih `Content Navigator page (deep link)`. Default `Post permalink` = perilaku lama |
| **Target Page URL** | halaman tujuan, mis. `/news/` (atau `/berita/`). Bisa tempel URL penuh. Ada ikon gerigi untuk "Open in new window" dan "Add nofollow". Kosong = link tetap ke permalink post |
| **Taxonomy** | slug taxonomy kategori. Boleh kosong: otomatis dicoba `{post_type}_category` (mis. `videos_category`), lalu taxonomy hierarchical publik pertama milik post type itu. Kalau post type tidak punya taxonomy, link hanya membawa section |

Control Target Page URL dan Taxonomy baru muncul setelah Link Mode = navigator.

Ulangi untuk tiap widget (satu widget galeri, satu widget video), masing-masing dengan **Post Type** yang benar di section Source (`gallery` / `videos`).

Update halaman.

## 4. Syarat di halaman tujuan (mis. /news/)

Halaman itu harus punya widget **Taxonomy Grid** dengan **Source Mode = Sections**, dan repeater-nya punya baris yang cocok:

| Item yang diklik | Baris repeater di halaman tujuan |
|---|---|
| Galeri (`gallery`) | Post Type = `gallery`, Section Type = Taxonomy Accordion, Taxonomy = `gallery_category` |
| Video (`videos`) | Post Type = `videos`, Section Type = Taxonomy Accordion, Taxonomy = `videos_category` |
| Berita (`news`) | Post Type = `news`. Kalau mau kategori ikut terbuka: Taxonomy Accordion + `news_category` |

Aturan penting:

- **Taxonomy di baris repeater harus sama** dengan taxonomy yang dipakai Content Grid (isian `Taxonomy` atau hasil tebakan).
- **Term harus punya minimal satu post yang published** untuk post type itu. Term kosong tidak muncul di sidebar, jadi tidak bisa dibuka.
- Kalau ada dua baris dengan post type yang sama, baris pertama yang dipakai. Pengecualian: kalau URL membawa term dan hanya baris kedua yang punya term itu, baris kedua yang dipakai (berguna kalau ada "Berita Terbaru" Flat List dan accordion kategori untuk `news` di halaman yang sama).

## 5. Contoh URL dan cara tes manual

Contoh URL yang dihasilkan Content Grid (Target Page URL = `/news/`):

```
/news/?wptg_section=videos&wptg_term=profil-kantor-imigrasi
/news/?wptg_section=gallery&wptg_term=kegiatan-pimpinan
/news/?wptg_section=news
```

Tes tanpa menyentuh beranda: buka langsung di browser

```
https://<domain-lu>/berita/?wptg_section=videos&wptg_term=profil-kantor-imigrasi
```

Yang harus terlihat:

1. Halaman scroll ke widget (sekali, setelah load).
2. Grup sidebar **Galeri Video** terbuka, panahnya berputar.
3. Term `Profil Kantor Imigrasi` ditandai aktif.
4. Panel kanan langsung berisi item term itu (jumlah sesuai Items Per Page, ada tombol Lihat Lebih Banyak kalau lebih banyak).
5. Judul hero, breadcrumb, dan judul konten memakai **label section** (mis. "Galeri Video"). Sama seperti kalau term diklik manual.
6. Klik term lain, search, Lihat Lebih Banyak, atau pindah section: jalan normal.
7. Matikan JavaScript (DevTools) lalu reload: section dan isi term tetap sudah terbuka, karena dirender dari server.

Tes lanjutan: dari beranda klik kartu galeri dan kartu video, pastikan masing-masing mendarat di section dan kategori yang benar. Cek juga lewat "View Page Source": `href` kartu harus berisi `wptg_section=` (dan `wptg_term=` kalau item punya kategori).

## 6. Perilaku

- **Flat List** (mis. Berita Terbaru, Pengumuman): tidak punya kategori, jadi `wptg_term` **diabaikan**, section-nya saja yang dibuka.
- **Param tidak valid diam-diam diabaikan**, tanpa error atau layar putih:
  - `wptg_section` tidak ada di repeater, atau section-nya kosong: halaman jatuh ke section default (yang pertama punya konten).
  - `wptg_term` tidak dikenal, salah taxonomy, atau tidak punya post: section tetap dibuka pada term pertamanya.
  - param berupa array (`wptg_term[]=x`): dianggap kosong.
- Input disanitasi: `sanitize_key()` untuk section, `sanitize_title()` untuk term. Section dan term divalidasi terhadap isi repeater dan taxonomy-nya di server.
- Param hanya dibaca (tidak mengubah data), jadi tanpa nonce.
- Section bertipe **Single Post Full Detail**: term dibuka dan post pertama term itu ditampilkan.
- Yang **tidak** dilakukan: membuka satu item/post tertentu di dalam term. Yang dibuka section dan kategorinya, bukan item yang diklik.
- Cache: plugin page-cache (WP Super Cache) menyimpan satu salinan per query string, jadi tiap URL deep link punya cache sendiri. Tidak perlu setting tambahan.
- Item tanpa kategori: link hanya membawa `wptg_section`. Term "Uncategorized" bawaan WP dilewati kalau item punya term lain.

## 7. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| Klik kartu tetap ke halaman post | Link Mode masih `Post permalink`, atau **Target Page URL kosong** (sengaja fallback ke permalink). Isi URL, update, hard refresh. Kalau pakai cache, purge cache |
| Control `Item Link` tidak ada | Plugin Content Grid belum 1.1.0. Jalankan sync lagi dan cek versi di Plugins |
| Halaman tujuan terbuka tapi section default yang aktif | `wptg_section` tidak cocok dengan Post Type di baris repeater mana pun, atau section itu kosong. Cek ejaan post type di URL dan di repeater |
| Section benar, tapi term pertama yang aktif, bukan term item | Slug term salah/tidak ada di taxonomy baris itu, **taxonomy Content Grid beda dengan taxonomy repeater**, atau term itu belum punya post published. Cek di Posts > kategori dan isi `Taxonomy` di Content Grid secara eksplisit |
| URL tidak punya `wptg_term` | Item tidak punya kategori, atau post type tidak punya taxonomy yang cocok. Isi `Taxonomy` manual |
| Slug salah ketik | Slug harus persis seperti di WP Admin > kategori (kolom Slug). Huruf besar otomatis jadi kecil |
| Perubahan JS/CSS tidak terlihat | Regenerate CSS Elementor, purge cache plugin, hard refresh |
| Halaman tujuan lama muncul padahal param beda | Cache yang mengabaikan query string. Atur plugin cache agar mengenali query string, atau exclude halaman tujuan |
| Tidak scroll ke widget | Hanya scroll kalau deep link valid dan JS aktif. Cek console browser |
| Halaman error / layar putih setelah update | Cek `wp-content/debug.log` dan jalankan `php -l` (langkah 2). Laporkan pesan error-nya |

## File yang berubah

- `plugins-custom/wp-taxonomy-grid/wp-taxonomy-grid.php` (versi, 4 fungsi baru, pembacaan param di `wptg_render_sections_widget()`, `data-deep-link`)
- `plugins-custom/wp-taxonomy-grid/assets/js/taxonomy-grid.js` (scroll sekali ke widget)
- `plugins-custom/wp-taxonomy-grid/composer.json`, `readme.txt`
- `plugins-custom/wp-content-grid/wp-content-grid.php` (versi, 4 helper link, dipakai semua kartu)
- `plugins-custom/wp-content-grid/includes/class-elementor-widget.php` (section `Item Link`)
- `plugins-custom/wp-content-grid/composer.json`, `readme.txt`
- Catatan kerja: `.agents/tasks/deeplink/` (dokumen ini + `test-logic.js`, tidak ikut plugin)
