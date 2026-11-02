# Panduan: bikin halaman "Berita" pakai widget Taxonomy Grid (mode Sections)

Panduan step by step buat nyusun halaman Berita di Elementor Pro. Semua nama control, opsi dropdown, dan nilai default di bawah gue ambil dari kode plugin v4.1.0 (`includes/class-elementor-widget.php`, `wp-taxonomy-grid.php`, `assets/css/taxonomy-grid.css`). Kalau ada yang gue nggak yakin, gue tandai **(cek di editor)**.

> **Status jujur:** kode plugin belum pernah diuji di browser / WordPress / editor Elementor, dan PHP-nya belum di-lint (host nggak punya `php`). Jadi ikuti panduan ini sambil ngetes. Bagian 7 isinya checklist tes + perintah lint.

Cara pakai: centang `- [ ]` jadi `- [x]` setiap selesai satu langkah.

---

## 0. Ringkasan

**Yang dibangun:** satu halaman WordPress "Berita" yang isinya satu widget **Taxonomy Grid** dengan **Source Mode = Sections**. Satu widget itu sudah menghasilkan:

| Bagian desain | Datang dari |
|---|---|
| Hero navy (subtitle putih "Berita", judul emas "Berita Terbaru", garis emas kiri/kanan judul) | Content > Header + Style > Header |
| Breadcrumb `Beranda > Berita > Berita Terbaru` | Content > Breadcrumb + Style > Breadcrumb |
| Sidebar: search pill, entri flat (Berita Terbaru, Pengumuman), accordion (Galeri Foto, Galeri Video) | Content > Sections + Style (Sections Nav, Accordion Terms, dll.) |
| Konten: h2 judul, search lebar penuh, list bernomor, tombol "Lihat Lebih Banyak" | Content > Sections, Feed & Load More + Style (Content Title, Feed Cards, Load More) |

**Estimasi:** 8 langkah kerja (bagian 1 sampai 8; bagian 0 ringkasan dan bagian 9 keterbatasan cuma dibaca), sekitar 1,5 sampai 2 jam termasuk bikin konten contoh dan styling.

**BUKAN bagian halaman ini:** header situs (logo + menu) dan footer situs. Itu sudah dihandle Elementor Theme Builder. Jangan bikin ulang, jangan dimasukin ke halaman.

**Palet perkiraan** (ambil hex presisi dari Figma / color picker, jangan percaya angka ini mentah-mentah):

| Nama | Hex perkiraan | Dipakai untuk |
|---|---|---|
| Navy | `#1B365D` | latar hero, teks judul/label |
| Emas | `#E8B04B` | judul hero, garis, pill Active, panah, nomor, tombol Load More |
| Krem | `#F8F3EE` | latar search pill, latar kartu feed |
| Abu teks | `#6B7280` | tanggal, excerpt, teks breadcrumb |
| Putih | `#FFFFFF` | subtitle hero, teks pill Active |

Default plugin sudah dekat: primary `#1b3a5c`, accent `#e3a73c`, kartu feed `#fbf6e9`. Nanti kita timpa lewat Style.

---

## 1. Persiapan teknis

Semua perintah dijalankan di terminal **WSL**, di folder repo:

```bash
cd /mnt/c/projects/imigrasi-wp
```

- [ ] **1.1 Sync plugin ke container** (wajib tiap habis edit kode plugin; restart container NGGAK cukup):

```bash
./scripts/sync-plugin.sh wp-taxonomy-grid
```

Di output, baris `Version:` harus menampilkan **4.1.0**. Kalau masih 4.0.x, sync belum ke-copy: ulangi perintahnya dan baca error di atasnya.

- [ ] **1.2 Aktifkan plugin** (kalau di akhir sync bilang belum aktif):

```bash
docker compose exec -u www-data app wp plugin activate wp-taxonomy-grid --path=/app/web/wp
```

- [ ] **1.3 Flush rewrite rules:**

```bash
docker compose exec -u www-data app wp rewrite flush --path=/app/web/wp
```

- [ ] **1.4 Verifikasi plugin aktif.** Harus ada baris `wp-taxonomy-grid`, status `active`, versi `4.1.0`. `elementor` dan `elementor-pro` juga harus `active`:

```bash
docker compose exec -u www-data app wp plugin list --path=/app/web/wp
```

- [ ] **1.5 Regenerate CSS Elementor:** WP Admin > **Elementor > Tools > General** tab > **Regenerate CSS & Data** > klik tombolnya. Habis itu hard reload editor (`Ctrl+Shift+R`).

> Catatan: folder `.agents` di plugin ikut ke-copy ke `web/app/plugins/wp-taxonomy-grid/`. Aman, abaikan.

---

## 2. Kesiapan CPT & taxonomy

Plugin butuh 4 CPT. CPT-nya dibuat lewat **CPT UI**. Taxonomy-nya di-register lewat kode di `web/app/mu-plugins/custom-taxonomies.php` (jangan edit taxonomy di CPT UI, bakal ditimpa kode).

| CPT (slug) | Taxonomy | Dipakai untuk | `supports` yang dibutuhkan |
|---|---|---|---|
| `news` | `news_category` (opsional untuk section ini) | Berita Terbaru | `title`, `editor`, `excerpt`, `thumbnail` |
| `announcements` | tidak ada | Pengumuman | `title`, `editor`; `excerpt` + `thumbnail` disarankan (gambar kartu + ringkasan) |
| `gallery` | `gallery_category` | Galeri Foto | `title`, `thumbnail` (`editor` opsional) |
| `videos` | `videos_category` | Galeri Video | `title`, `thumbnail` (`editor` opsional) |

Penjelasan: section **Flat List** (Berita Terbaru, Pengumuman) nggak query pakai taxonomy. Jadi `news_category` nggak wajib diisi supaya berita muncul. Section **Taxonomy Accordion** (Galeri Foto, Galeri Video) wajib punya term dan post yang sudah dikategorikan.

- [ ] **2.1 Cek CPT & taxonomy terdaftar:**

```bash
docker compose exec -u www-data app wp post-type list --fields=name,label,public --path=/app/web/wp
docker compose exec -u www-data app wp taxonomy list --fields=name,object_type --path=/app/web/wp
```

Yang harus ada: post type `news`, `announcements`, `gallery`, `videos` (kolom `public` = `1`). Taxonomy `news_category` (object_type `news`), `gallery_category` (`gallery`), `videos_category` (`videos`).

- [ ] **2.2 Cek `supports` tiap CPT** lewat WP-CLI:

```bash
for pt in news announcements gallery videos; do
  echo -n "$pt: "
  docker compose exec -T -u www-data app wp eval "echo implode(', ', array_keys(get_all_post_type_supports('$pt')));" --path=/app/web/wp
  echo
done
```

Bandingkan sama tabel di atas. Atau cek lewat UI: **CPT UI > Add/Edit Post Types > Edit Post Types**, pilih CPT, buka bagian **Supports**, centang `Title`, `Editor`, `Excerpt`, `Featured Image` sesuai tabel, klik **Save Post Type**. Pastikan juga `Public` = True.

- [ ] **2.3 Migrasi kategori lama** (HANYA kalau sudah ada berita/konten lama yang masih pakai kategori bawaan `category`). Backup database dulu. **Dry run dulu**, baru `--apply`:

```bash
./scripts/migrate-category.sh news news_category            # dry run, cuma tampil rencana
./scripts/migrate-category.sh news news_category --apply    # jalankan beneran
```

Hal yang sama berlaku untuk CPT lain bila perlu (`gallery gallery_category`, `videos videos_category`). Term lama di `category` nggak dihapus.

---

## 3. Bikin konten contoh (sesuai desain)

Tanpa konten, section kosong dan widget cuma nampilin placeholder. Bikin dulu semua ini.

**Aturan penting:**
- Semua post harus berstatus **Published**.
- Tiap post **wajib punya Featured Image** (gambar kartu, thumbnail galeri, foto di popup galeri; di popup video gambar ini tidak ditampilkan).
- Urutan tampil = **tanggal terbaru dulu**. Post yang paling baru di-publish jadi item 01.
- Item 01 (featured) menampilkan excerpt. Excerpt diambil dari field **Excerpt**; kalau kosong, diambil dari isi (content) post. Supaya ada "..." di ujung, teksnya harus lebih panjang dari **Excerpt Length** (default 40 kata).

### 3.1 Berita (CPT `news`), minimal 7

Minimal 7 supaya tombol "Lihat Lebih Banyak" muncul dan bisa dites lanjut ke nomor 07 (default 6 item per halaman).

- [ ] Bikin 7+ post lewat **News > Add New**. Judul dari desain:
  1. Perkuat Pengawasan Orang Asing, Imigrasi Jakarta Utara Gelar Operasi Gabungan Bersama Timpora
  2. Imigrasi Jakarta Utara Tingkatkan Layanan Prioritas
  3. Kemudahan Visa untuk Wisatawan, Ini Syarat & Ketentuannya
  4. Pekerja Migran Indonesia kini Memiliki Lounge Khusus
  5. Imigrasi Jakarta Utara Perketat Pengawasan Haji 2026
  6. Imigrasi Jakarta Utara Optimalkan Pemeriksaan Kepulangan Perdana 391 Jemaah Haji Tuntas dalam 40 Menit
  7. (bebas, mis. "Berita Contoh 07")
- [ ] Judul no. 1 harus jadi post **paling baru** (publish terakhir, atau atur **Publish date** paling baru), karena dia yang tampil sebagai item 01.
- [ ] Item 01: isi **Excerpt** (panjang, 45+ kata biar ada "...") dan **Featured Image**.
- [ ] Item 02 sampai 07: isi **Featured Image** (excerpt nggak tampil di kartu compact).
- [ ] (Opsional) kategorikan ke `news_category`. Nggak wajib untuk section ini.

### 3.2 Pengumuman (CPT `announcements`)

- [ ] Bikin 2 sampai 3 post lewat **Announcements** (menu CPT `announcements`, bukan menu "Announcements" milik plugin banner) > Add New. Isi judul, isi, Excerpt, Featured Image. Klik item ini nanti membuka modal.

### 3.3 Galeri Foto (CPT `gallery` + `gallery_category`)

- [ ] Bikin 4 term di **Gallery > Categories** (taxonomy `gallery_category`): `Outing 2026`, `Hari Bhakti Imigrasi 2026`, `Sosialisasi Keimigrasian`, `Fasilitas Ramah HAM`.
- [ ] Bikin minimal 1 sampai 3 post per term lewat **Gallery > Add New**: judul + **Featured Image** (wajib, ini yang tampil di popup foto) + centang term-nya. Term tanpa post published **nggak muncul** di sidebar.
- Klik foto membuka **popup** (foto besar + judul + tanggal). Detail lengkap: [`.agents/tasks/media-popup/MEDIA-POPUP.md`](../media-popup/MEDIA-POPUP.md).

### 3.4 Galeri Video (CPT `videos` + `videos_category`)

- [ ] Bikin 4 term di **Videos > Categories** (`videos_category`): `Profil Kantor Imigrasi`, `Tutorial Aplikasi M-Paspor`, `Alur Permohonan Izin Tinggal`, `Iklan Layanan Masyarakat`.
- [ ] Bikin minimal 1 post per term lewat **Videos > Add New**: judul + **Featured Image** (jadi thumbnail di daftar; **tidak** ditampilkan di popup video) + centang term.
- [ ] Siapkan sumber video. Sejak wp-taxonomy-grid 4.3.0 klik video **tidak lagi redirect / buka tab baru**: selalu muncul **popup**, videonya langsung diputar. Sumber video dicari berurutan, yang pertama ketemu dipakai:
  1. **External Video URL** terisi (meta box di kolom samping layar edit post `videos`; nama field `wptg_video_external_url`, tersimpan di meta `_video_external_url`). Contoh: `https://youtube.com/watch?v=...`. YouTube / Vimeo / link file `.mp4` diputar di popup. Link lain (TikTok, Instagram) cuma menampilkan tombol **Buka video** di popup (tab baru), karena nggak bisa di-embed.
  2. **Elemen video di isi post**: blok Video / Embed (YouTube, Vimeo), tag `<video>`, atau `<iframe>` YouTube / Vimeo. Hanya elemen pertama yang dipakai.
  3. **File video yang ter-attach ke post** (upload video dari dalam layar edit post itu).
  4. Ketiganya kosong → popup menampilkan **foto** kalau ada Featured Image, kalau nggak ada → tampilan post biasa (judul + isi).

### 3.5 (Opsional) Bikin dummy banyak sekaligus lewat WP-CLI

Cuma buat ngisi cepat. Boleh dilewat dan bikin manual. Jalankan di WSL, folder repo. Berita dibikin dari yang paling lama ke paling baru, jadi **judul terakhir di daftar akan jadi item 01**. Makanya judul panjang "Perkuat Pengawasan..." ditaruh paling bawah:

```bash
body='Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer nec odio praesent libero. Sed cursus ante dapibus diam. Sed nisi nulla quis sem at nibh elementum imperdiet. Duis sagittis ipsum praesent mauris. Fusce nec tellus sed augue semper porta. Mauris massa vestibulum lacinia arcu eget nulla class aptent taciti sociosqu ad litora torquent per conubia nostra.'

titles=(
  'Berita Contoh 07'
  'Imigrasi Jakarta Utara Optimalkan Pemeriksaan Kepulangan Perdana 391 Jemaah Haji Tuntas dalam 40 Menit'
  'Imigrasi Jakarta Utara Perketat Pengawasan Haji 2026'
  'Pekerja Migran Indonesia kini Memiliki Lounge Khusus'
  'Kemudahan Visa untuk Wisatawan, Ini Syarat & Ketentuannya'
  'Imigrasi Jakarta Utara Tingkatkan Layanan Prioritas'
  'Perkuat Pengawasan Orang Asing, Imigrasi Jakarta Utara Gelar Operasi Gabungan Bersama Timpora'
)

for t in "${titles[@]}"; do
  docker compose exec -T -u www-data app wp post create \
    --post_type=news --post_status=publish \
    --post_title="$t" --post_excerpt="$body" --post_content="$body" \
    --path=/app/web/wp --porcelain
  sleep 1   # biar tanggal publish beda 1 detik dan urutannya pasti
done
```

Output-nya ID post. Featured image tetap harus ditambah. Cara termudah: lewat editor post. Lewat CLI (bisa gagal kalau container nggak punya internet atau URL tanpa ekstensi `.jpg`/`.png`):

```bash
docker compose exec -T -u www-data app wp media import "https://example.com/gambar.jpg" --post_id=<ID> --featured_image --path=/app/web/wp
```

Dummy term galeri/video:

```bash
docker compose exec -T -u www-data app wp term create gallery_category "Outing 2026" --path=/app/web/wp
docker compose exec -T -u www-data app wp post create --post_type=gallery --post_status=publish --post_title="Foto Outing 1" --path=/app/web/wp --porcelain
docker compose exec -T -u www-data app wp post term set <ID_POST> gallery_category "Outing 2026" --path=/app/web/wp
```

---

## 4. Bikin halaman "Berita"

- [ ] **4.1** WP Admin > **Pages > Add New**. Judul: `Berita` (judul halaman ini otomatis jadi default Subtitle hero kalau field Subtitle dikosongin). Slug: `berita`.
- [ ] **4.2** Di panel Page Settings / **Page Attributes > Template**, pilih **Elementor Full Width**.
  - *Elementor Full Width* = konten full lebar layar, **tetap memuat** header dan footer dari Theme Builder. Ini yang kita mau.
  - *Elementor Canvas* = halaman kosong tanpa header dan footer. Jangan dipakai.
  - *Default* = pakai template theme; biasanya juga kena Theme Builder, tapi **(cek di editor)** hasilnya.
- [ ] **4.3** Klik **Edit with Elementor**. Tambah satu **Container** (flexbox).
- [ ] **4.4** Atur lebar. Hero navy di desain kemungkinan full lebar layar, sedangkan isi (sidebar + konten) sekitar 1100 sampai 1200 px. Karena hero ada DI DALAM widget, pilih salah satu:
  - **Opsi A (disarankan, hero full-bleed):** Container > **Content Width = Full Width**. Lalu di widget pakai **Style > Container > Body Section** untuk padding kiri/kanan isi (lihat bagian 6). Mis. `Padding` kiri/kanan `12%` di desktop supaya isi sekitar 1200 px di layar 1920 **(cek di editor)**.
  - **Opsi B (simpel):** Container > **Content Width = Boxed**, **Width** `1200` px. Konsekuensinya hero navy ikut 1200 px, bukan full layar.
- [ ] **4.5** Di panel widget (kiri), cari **Taxonomy Grid** (nama dari `get_title()`; ada di kategori panel bernama "Taxonomy Grid"). Drag ke dalam container. Kalau nggak ketemu, lihat bagian 8.

---

## 5. Tab Content (isian widget)

Klik widget, buka tab **Content**. Urutan section di panel: Source, Sections, Feed & Load More, Header, Breadcrumb. (Section "Search Box" dan "Grid Options" cuma muncul untuk Source Mode = Single, abaikan.)

### 5.1 Source

- [ ] **Source Mode** = `Sections — multiple sidebar sections (Content Navigator)`.

### 5.2 Sections (repeater + pengaturan umum)

Isian di luar repeater:

| Field | Isi |
|---|---|
| Show Search Box | Yes (default) |
| Search Placeholder | `Cari...` (default) atau sesuai desain |
| Search Icon | default `fas fa-search` (kaca pembesar) |
| Search Position | `Both` (default). Pilihan: `Sidebar`, `Content (full width above the list)`, `Both`. Dengan Both, kotak sidebar dan kotak konten selalu sinkron. |
| Show Content Title | Yes (default) |
| Content Title | **Kosongkan** supaya h2 ikut nama section yang terbuka ("Berita Terbaru", "Pengumuman", dst.). Isi teks kalau mau tetap/fixed. |

Repeater **Sections**: bikin **4 baris persis urutan ini**. Urutan penting: widget otomatis membuka **section pertama yang punya konten** (nggak ada field "default active" terpisah), jadi Berita Terbaru harus di baris paling atas dan punya minimal 1 berita published.

Dropdown **Post Type** formatnya `Label (slug)`, pilih berdasarkan slug di dalam kurung.

| Field (label di editor) | Baris 1 | Baris 2 | Baris 3 | Baris 4 |
|---|---|---|---|---|
| **Label** | `Berita Terbaru` | `Pengumuman` | `Galeri Foto` | `Galeri Video` |
| **Section Type** | Flat List (no categories) | Flat List (no categories) | Taxonomy Accordion (categories) | Taxonomy Accordion (categories) |
| **Post Type** | `(news)` | `(announcements)` | `(gallery)` | `(videos)` |
| **Taxonomy** | tidak muncul | tidak muncul | `(gallery_category)` | `(videos_category)` |
| **Content Style** | Numbered Feed (Flat List only) | Numbered Feed (Flat List only) | Grid of Thumbnails (Taxonomy Accordion only) | Vertical List of Thumbnails (Taxonomy Accordion only) |
| **Item Click Behavior** | Swap Content Panel (inline detail) | Open Floating Modal | Media Popup (video / photo) | Media Popup (video / photo) |
| **Include in Search** | Yes | Yes | No | No |
| **Items Per Page** | 6 | 6 | 6 | 6 |

Catatan:
- **Media Popup (video / photo)** (baris 3 dan 4) butuh wp-taxonomy-grid **4.3.0**. Baris yang dulu `Video: Redirect to URL, or Popup Player` tampil sebagai `Old: Video redirect / popup (now same as Media Popup)` dan perilakunya sudah sama dengan Media Popup, tapi lebih rapi ganti ke Media Popup. Panduan lengkap: [`.agents/tasks/media-popup/MEDIA-POPUP.md`](../media-popup/MEDIA-POPUP.md).
- Content Style yang nggak cocok sama Section Type nggak error, tapi diam-diam jatuh ke fallback (Flat List ke Numbered Feed, Accordion ke Grid of Thumbnails). Jadi pilih sesuai tabel.
- **Include in Search** = No artinya kotak search mengabaikan section itu. Nilai Yes/No di atas ngikutin spesifikasi awal (`requirements.md`); ubah kalau lu mau galeri ikut dicari.
- **Items Per Page** nggak muncul untuk Content Style `Single Post Full Detail` (nggak dipakai di halaman ini).

### 5.3 Feed & Load More

| Field | Isi |
|---|---|
| **Feed Layout** | `Featured first + compact rest` (default). Ini yang meniru desain: item 01 besar, item 02 dst. kecil. Pilihan lain: `All featured`, `All compact`. |
| **Show Number** | Yes (default) |
| **Excerpt Length (words)** | `40` (default). Cuma kartu featured. `0` = excerpt disembunyikan. |
| **Read More Text** | `Baca Selengkapnya` (kolom kosong pun otomatis pakai ini) |
| **Read More Icon** | default `fas fa-arrow-right`. Ganti kalau panah di Figma beda. |
| **Load More Text** | `Lihat Lebih Banyak` (kolom kosong pun otomatis pakai ini) |

### 5.4 Header

| Field | Isi |
|---|---|
| **Show Header** | Yes |
| **Show Decorative Lines** | Yes (garis emas kiri/kanan judul) |
| **Subtitle** | `Berita` (tampil kecil di atas judul) |
| **Title** | **Kosongkan** supaya ikut section aktif ("Berita Terbaru", lalu "Pengumuman" saat pindah). Isi teks kalau mau tetap. |

### 5.5 Breadcrumb

| Field | Isi |
|---|---|
| **Show Breadcrumb** | Yes |
| **Home Label** | `Beranda` (default) |
| **Middle Label** | `Berita` (kosong = ikut Subtitle) |
| **Current Label** | **Kosongkan** supaya ikut section aktif. |
| **Separator Icon** | default `fas fa-chevron-right`. Desain pakai panah; kalau mau panah, pilih `fas fa-arrow-right` atau ikon dari Figma. |

---

## 6. Tab Style

Buka tab **Style**. Untuk setiap section Style di bawah, cara bukanya: klik nama section-nya di panel. Nilai di kolom "Disarankan" adalah **perkiraan dari desain**, cocokkan dengan Figma. Hex di sini = palet bagian 0.

Section Style yang **tidak** muncul di Sections mode (abaikan): Sidebar / Accordion, Category Name (Merged), Content Panel.

### 6.1 Style > Container

| Control | Disarankan |
|---|---|
| Max Width | Kosong (Opsi A) atau `1200` px kalau mau hero ikut boxed |
| Margin / Padding / Background Color (wrapper) | Background `#FFFFFF` |
| **Body Section (Breadcrumb + Sidebar + Content Wrap)** > Padding | Opsi A: kiri/kanan `12%` desktop, `5%` tablet, `16px` mobile **(cek di editor)** |
| Body Section > Background Color | `#FFFFFF` |
| **Sidebar Width** | Default plugin **280 px**. Set ke **140 px** kalau sidebar di desain sesempit itu. Slider rentang 100 sampai 500 px. Cek teks "Hari Bhakti Imigrasi 2026" masih enak kebaca (bakal wrap ke 2 baris). Ini responsif: biarkan tablet/mobile kosong (di layar kecil sidebar otomatis 100%). |
| **Gap Between Sidebar & Content** | `40` px (default 36) |
| **Quick Theme Colors > Primary Color** | `#1B365D` (default `#1b3a5c`) |
| **Quick Theme Colors > Accent Color** | `#E8B04B` (default `#e3a73c`) |

Primary dan Accent ini murah tapi ngubah banyak hal sekaligus (latar hero, panah, warna pill Active, garis, dll.). Set ini dulu.

### 6.2 Style > Header

| Control | Disarankan |
|---|---|
| Padding | `56px 24px 56px 24px` (atas, kanan, bawah, kiri) |
| Background Color | `#1B365D` |
| **Subtitle** > Subtitle Typography | 16 px, weight 400 |
| Subtitle Color | `#FFFFFF` |
| Subtitle > Margin | bawah `8` px |
| Subtitle > Alignment | Center |
| **Title** > Title Typography | 40 px desktop (tablet 32, mobile 28), weight 700 |
| Title Color | `#E8B04B` |
| Title > Alignment | Center |
| **Decorative Lines** > Start Line (Left) / End Line (Right) | Biarkan **Position = Default (follows layout)**. Jangan diubah ke Absolute kecuali mau posisi bebas. |
| **Both Lines — Appearance** > Color | `#E8B04B` |
| Length | `120` px (default). Mobile kecilin ke `40` px. |
| Thickness | `2` px (default) |
| **Gap Between Lines & Title** | `24` px |

### 6.3 Style > Breadcrumb

| Control | Disarankan |
|---|---|
| Padding | `16px 0` |
| Background Color | `#FFFFFF` |
| Alignment | Left |
| Border (set Bottom width untuk garis pemisah) | Kosong (default none) |
| Vertical Alignment | Middle (default) |
| **Link Text** > Typography / Color | 14 px, weight 400 / `#6B7280` |
| **Current Item** > Typography / Color | 14 px, weight 600 / `#1B365D` |
| **Separator Icon** > Icon Color | `#E8B04B` |
| Icon Size | `12` px |
| Icon Margin | `0 8px 0 8px` |

### 6.4 Style > Search Box

Berlaku buat KEDUA kotak search (sidebar dan konten), karena classnya sama.

| Control | Disarankan |
|---|---|
| Alignment | Left |
| Width | `100` % (default) |
| **Input** > Padding | `12px 48px 12px 20px` (kanan minimal ±44 px biar teks nggak nabrak ikon) |
| Input Text Typography / Color | 14 px / `#1B365D` |
| Background | `#F8F3EE` |
| Border Color | `#F8F3EE` (samain dengan background biar nggak ada garis), atau warna garis di Figma |
| Border Radius | `30` px (slider maks 50). Itu sudah bentuk pill. |
| **Icon** > Icon Color | `#1B365D` **(cek Figma)** |
| Size | `16` px |

### 6.5 Style > Sections Nav (Flat Entries)

Ini yang bikin pill "Berita Terbaru" emas, Active, menjorok kiri.

| Control | Disarankan |
|---|---|
| Label Typography | 16 px, weight 600 |
| Label Color | `#1B365D` |
| **Tab Normal** > Padding | `12px 16px 12px 16px` |
| Normal > Margin | `0` semua |
| Normal > Background Color | kosong |
| Normal > Border Radius | `30` px semua sisi |
| **Tab Hover** > Text Color | `#1B365D` |
| Hover > Background Color | `#F8F3EE` |
| **Tab Active** > Text Color | `#FFFFFF` (wajib diisi eksplisit, kalau nggak, "Label Color" di atas bisa menimpa putihnya) |
| Active > Background Color | `#E8B04B` |
| Active > Border Radius | `30` px semua sisi |
| **Active > Margin (kiri minus)** | `Top 0, Right 0, Bottom 0, Left -20` px. Tombol melebar ke kiri sebesar 20 px dan tepi kanannya tetap. Pakai nilai yang sama dengan jarak sidebar ke tepi container (mis. sama dengan Body Section padding kiri, atau ukuran gutter di Figma) supaya pill pas nyentuh tepi. |
| Active > Padding | `Top 12, Right 16, Bottom 12, Left 36` px. Left = padding Normal (16) + 20 supaya teks nggak ikut geser ke kiri. |

Kalau Body Section padding kiri lu pakai persen (`12%`), ukuran minusnya ikut dihitung dalam px sesuai selera; boleh pakai unit `%` atau `em` di control Margin juga **(cek di editor)**.

### 6.6 Style > Category Divider (Merged)

Garis emas kecil di kiri entri flat ("Pengumuman" dll.). Anak dari tombol, jadi ikut tombol.

| Control | Disarankan |
|---|---|
| Color | `#E8B04B` |
| Length | `14` px (default) atau sesuai Figma |
| Thickness | `2` px (default) |
| Position | Default |

Catatan: di state Active (pill emas) dash emas di atas latar emas nggak kelihatan. Kalau di Figma dash tetap kelihatan di Active, ubah Color divider atau Background Active **(cek di editor)**.

### 6.7 Style > Category Divider (Accordion)

Garis emas di samping header "Galeri Foto" / "Galeri Video".

| Control | Disarankan |
|---|---|
| Color | `#E8B04B` |
| Length / Thickness | `14` px / `2` px (default) |

### 6.8 Style > Category Name (Accordion)

Teks header "Galeri Foto" / "Galeri Video".

| Control | Disarankan |
|---|---|
| Typography | 16 px, weight 600 |
| Alignment | Left |
| Tab Normal > Text Color | `#1B365D` |
| Tab Hover > Text Color | `#E8B04B` atau `#1B365D` **(cek Figma)** |
| Margin / Padding / Background Color / Border Radius (tiap tab) | Biarkan kosong |

### 6.9 Style > Accordion Terms (Sections)

Daftar term kecil di dalam accordion + panah segitiga emas.

| Control | Disarankan |
|---|---|
| **Space Between Sidebar Entries** | `16` px |
| **Term List** > Indent | `20` px (default kode sekitar 22 px / 1.4rem, tapi nilainya hardcode jadi isi biar jelas) |
| Gap Between Terms | `6` px |
| **Term Button** > Typography | 14 px, weight 400 |
| Alignment | Left |
| Padding | `4px 0 4px 0` |
| Border Radius | `0` |
| **Tab Normal** > Text Color | `#6B7280` |
| Normal > Background Color | kosong |
| **Tab Hover** > Text Color | `#1B365D` |
| **Tab Active** > Text Color | `#1B365D` |
| Active > Background Color | kosong atau `#F8F3EE` **(cek Figma)** |
| **Accordion Arrow** > Arrow Color | `#E8B04B` |
| Arrow Size | `6` px (rentang 3 sampai 12) |
| Arrow Spacing From Text | `10` px |

### 6.10 Style > Content Title (Sections)

h2 "Berita Terbaru" di atas konten.

| Control | Disarankan |
|---|---|
| Title Typography | 28 px (tablet 24, mobile 22), weight 700 |
| Title Color | `#1B365D` |
| Margin | `0 0 16px 0` |
| Alignment | Left |

### 6.11 Style > Numbered Feed

| Control | Disarankan |
|---|---|
| **Badge Number** > Margin / Padding / Background Color / Border Radius | Kosongkan Background Color (nomor di desain angka emas tanpa kotak). Kalau masih ada kotak latar, cek di sini **(cek di editor)** |
| **Item** > Padding | `12px 12px 12px 12px` |
| **Title** > Title Typography | 18 px, weight 700 |
| Title Color | `#1B365D` |
| **Date** > Date Typography | 12 px, weight 400 |
| Date Color | `#6B7280` |
| **"Baca Selengkapnya" Affordance** > "Baca Selengkapnya" Typography | 14 px, weight 600 |
| "Baca Selengkapnya" Color | `#1B365D` **(cek Figma)** |
| Alignment | Right (kartu featured sudah rata kanan bawah secara default) |

Catatan: Title Typography ini berlaku ke featured DAN compact. Plugin sudah memperkecil judul compact (±15 px, maks 2 baris), tapi setting Elementor bisa menimpanya. Kalau judul compact jadi terlalu besar, turunkan ukurannya ke kompromi 16 px **(cek di editor)**.

### 6.12 Style > Feed Cards (Featured / Compact)

Kartu krem, nomor emas, gambar.

| Control | Disarankan |
|---|---|
| **Card** > Gap Between Cards | `16` px |
| Border Radius | `12` px semua sisi (default CSS 12 px) |
| Tab Normal > Background Color | `#F8F3EE` (default plugin `#fbf6e9`) |
| Normal > Box Shadow | kosong |
| Tab Hover > Background Color | sedikit lebih gelap, mis. `#F1E9DF` **(cek Figma)** |
| **Image** > Featured Image Width | `45` % (default CSS 50%) |
| Featured Image Height | `250` px (default CSS) atau sesuai Figma |
| Compact Image Width | `90` px (default CSS) |
| Compact Image Height | `75` px |
| Image Border Radius | `8` px |
| Image Object Fit | Cover |
| **Number** > Number (Featured) Typography | 42 px, weight 700 |
| Number (Featured) Color | `#E8B04B` |
| Number (Compact) Typography | 24 px, weight 700 |
| Number (Compact) Color | `#E8B04B` |
| Space Between Number & Text | `16` px |
| **Text** > Gap Between Date, Title & Excerpt | `8` px |
| Excerpt Typography | 14 px, weight 400 |
| Excerpt Color | `#6B7280` |
| **Excerpt Max Lines** | `5` (desain: 4 sampai 5 baris diakhiri "...") |
| **Read More Icon** > Icon Color | `#1B365D` |
| Icon Size | `14` px |
| Space Between Text & Icon | `8` px |

### 6.13 Style > Load More Button

Default plugin: pill abu muda dengan teks navy. Ubah jadi emas.

| Control | Disarankan |
|---|---|
| Text Typography | 14 px, weight 600 |
| Text Color | `#FFFFFF` **(cek Figma, bisa juga navy `#1B365D`)** |
| Margin | `24px 0 0 0` |
| Padding | `12px 32px 12px 32px` |
| Background Color | `#E8B04B` |
| Border | None |
| Border Radius | `30` px semua sisi |
| Alignment | Center |
| **Hover** > Hover Text Color | sama dengan Text Color |
| Hover Background Color | emas lebih gelap, mis. `#D49A3A` **(cek Figma)** |

### 6.14 Opsional: section Style yang cuma berpengaruh ke galeri/video

Tidak ada di desain Berita yang lu kasih, tapi bisa disetel kalau perlu:

| Section | Isi |
|---|---|
| Grid Thumbnail (Photo Caption) | Caption foto: Margin/Padding, Title Typography/Color, Date Typography/Color |
| List Thumbnail (Video) | Title, Meta Line, Play Icon (Color, Size) |
| Modal / Lightbox | Backdrop Color, Panel (Background Color, Margin, Padding), Close Button (Color, Size) untuk modal lama (Pengumuman). Sub-bagian **Media Popup (video / photo)**: Popup Width, Popup Border Radius, Popup Background, Close Button Background / Icon Color, Popup Title dan Popup Date (typography + color). Default-nya sudah sesuai desain popup. Popup ini global ke halaman: kalau ada beberapa widget, yang terakhir menang. |

---

## 7. Responsif & tes

Simpan dulu (**Update**), lalu buka halaman di tab baru (bukan cuma preview editor).

### 7.1 Responsif

Breakpoint plugin: 1024 px, 768 px, 480 px. Cek lewat tombol responsive mode Elementor dan juga di browser asli.

- [ ] **Desktop (>1024):** sidebar di kiri, konten di kanan. Item 01 featured (gambar kiri besar + teks kanan), item 02 dst. compact.
- [ ] **Tablet (≤1024):** sidebar pindah ke ATAS konten dan lebarnya penuh. Kartu featured jadi vertikal (gambar di atas).
- [ ] **Mobile (≤768):** semua kartu feed vertikal / tersusun rapi; nomor mengecil; hero padding lebih kecil; garis dekoratif pendek.
- [ ] **Mobile (≤480):** nggak ada scroll horizontal; teks hero nggak kepotong.
- [ ] Pill Active dengan margin kiri minus **tidak keluar layar / bikin scroll horizontal** di tablet dan mobile. Kalau iya, set Margin Active di tab tablet/mobile ke `0`.
- [ ] Sidebar Width yang lu set 140 px **tidak** bikin sidebar jadi pendek-vertikal di mobile (harusnya otomatis penuh lebar karena plugin pakai `!important`).

### 7.2 Fungsional

- [ ] Awalnya: section **Berita Terbaru** terbuka, pill emas Active, h2 "Berita Terbaru", hero judul "Berita Terbaru", breadcrumb `Beranda > Berita > Berita Terbaru`.
- [ ] Klik **Pengumuman**: h2, judul hero, dan crumb terakhir berubah jadi "Pengumuman"; list ganti.
- [ ] Klik **Galeri Foto**: accordion terbuka (panah berputar), term tampil; klik term, grid foto tampil dan h2 berubah.
- [ ] Klik **Galeri Video**: sama, list video dengan ikon play.
- [ ] Ketik di search **sidebar**: kotak search **konten** ikut terisi (dan sebaliknya); hasil tersaring di section yang terbuka. Kosongkan search: hasil balik normal.
- [ ] Numbering: item 01 sampai 06, klik **Lihat Lebih Banyak** → muncul **07** dst. (nomor lanjut). Setelah search atau pindah section, nomor mulai lagi dari 01.
- [ ] Tombol Load More hilang saat sudah nggak ada data lagi, dan sempat disabled + spinner saat loading (nggak bisa diklik dua kali).
- [ ] Klik **berita** (Berita Terbaru): panel diganti detail post. Klik **pengumuman**: muncul modal. Klik **foto**: popup foto (foto besar + judul + tanggal). Klik **video**: popup, video langsung diputar, featured image tidak tampil (sumber video: urutan di bagian 3.4).
- [ ] Modal / popup bisa ditutup (tombol X, klik backdrop, tombol Esc **(cek di editor)**). Setelah popup video ditutup, suara berhenti.
- [ ] DevTools > Console: nggak ada error JS merah. DevTools > Network (filter `admin-ajax`): request `wptg_query_items` status **200**, bukan 403 atau `-1`.

### 7.3 Lint PHP & analisis (belum pernah dijalankan)

Kode PHP plugin belum di-lint. Jalankan di WSL, folder repo:

```bash
docker compose exec app php -l plugins-custom/wp-taxonomy-grid/wp-taxonomy-grid.php
docker compose exec app php -l plugins-custom/wp-taxonomy-grid/includes/class-elementor-widget.php
docker compose exec app php -l plugins-custom/wp-taxonomy-grid/includes/class-video-meta-box.php
docker compose exec app php -l plugins-custom/wp-taxonomy-grid/includes/media-popup.php
```

Setiap file harus balas `No syntax errors detected`. Lalu analisis statis (`composer analyse` gagal kalau nggak dikasih path, pakai perintah ini):

```bash
docker compose exec app vendor/bin/phpstan analyse plugins-custom/wp-taxonomy-grid --level=5 --memory-limit=1G
```

Alternatif style check (`composer lint` juga butuh path):

```bash
docker compose exec app vendor/bin/phpcs plugins-custom/wp-taxonomy-grid
```

Kalau phpstan ngeluh soal class Elementor / fungsi WordPress "not found", itu wajar kalau stub-nya belum diset; yang penting lihat error syntax dan undefined variable sungguhan.

---

## 8. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| Widget **Taxonomy Grid** nggak muncul di panel | Plugin belum aktif (`wp plugin list`), atau Elementor belum aktif. Cari lewat kolom search panel, atau di kategori panel "Taxonomy Grid". Habis aktifkan: hard reload editor (`Ctrl+Shift+R`). Kalau masih kosong: cek `wp-content/debug.log` buat fatal error. |
| Tampilan masih lama / Style baru nggak nongol | **Elementor > Tools > Regenerate CSS & Data**, lalu hard reload. Cek juga plugin cache/CDN. Versi plugin harus 4.1.0 (`wp plugin list`). |
| Perubahan kode plugin nggak kebaca | Plugin di-copy (bukan di-symlink) ke `web/app/plugins`. Harus `./scripts/sync-plugin.sh wp-taxonomy-grid`, restart container nggak ngaruh. |
| Klik section / load more / search nggak jalan, Network `admin-ajax` 403 atau `-1` | Nonce kadaluarsa karena halaman di-cache oleh page cache. Kecualikan halaman `/berita` dari page cache, atau kosongkan cache. |
| Gambar nggak muncul di kartu / galeri | Post belum punya **Featured Image**. Semua gambar diambil dari featured image. |
| Section kosong / term nggak muncul di accordion | Term hanya tampil kalau punya minimal 1 post **Published** dari post type itu. Cek post sudah publish dan sudah dicentang term-nya. Urutan term di sidebar alfabetis. |
| Seluruh widget kosong / "Belum ada konten" | Section pertama yang punya konten dipilih otomatis. Berarti belum ada satu pun post published di semua section. |
| "No valid sections configured." | Ada baris repeater tanpa Post Type valid, atau Taxonomy kosong di baris Accordion. Cek 4 baris sesuai tabel 5.2. |
| Pill Active nggak putih / nggak emas | Isi `Text Color` di tab **Active** (Style > Sections Nav). Isi juga Primary / Accent di Style > Container > Quick Theme Colors. |
| Excerpt nggak ada "..." | Teks excerpt/content lebih pendek dari **Excerpt Length**. Panjangin teks atau kecilin angkanya. |
| Hero nggak full lebar | Container pakai Boxed. Pakai Opsi A di langkah 4.4. |
| Header/footer situs hilang | Template halaman salah (Canvas). Ganti ke **Elementor Full Width**. |
| Folder `.agents` ikut ke-copy ke `web/app/plugins/wp-taxonomy-grid/` | Aman, diabaikan. |

---

## 9. Keterbatasan yang diketahui

Diambil dari `AUDIT.md` ("Tidak dikerjakan" dan hasil implementasi v4.1.0) plus temuan saat nulis panduan ini:

- **Default Sidebar Width 280 px**, bukan 140 px seperti di desain. Set manual di **Style > Container > Sidebar Width** (bagian 6.1).
- **Nggak ada switch "Show Excerpt"** terpisah. Excerpt dimatikan lewat **Excerpt Length (words) = 0**.
- Excerpt cuma tampil di kartu **featured** (item 01). Kartu compact selalu tanpa excerpt.
- Nggak ada field "default active" per baris. Yang dibuka pertama = section pertama yang punya konten.
- Urutan term di accordion **alfabetis** (nama A ke Z), bukan urutan desain ("Outing 2026" dulu). Mengikuti desain persis perlu ubah kode atau nama term. Belum ada urutan manual.
- Style `Title` dan `Date` kartu feed berlaku ke featured dan compact sekaligus.
- Kartu featured selalu item pertama halaman 1 (termasuk hasil search); tidak bisa dipilih item lain jadi featured.
- Style Modal / Lightbox bersifat global untuk halaman (kalau ada banyak widget, yang terakhir menang).
- Section Style "Search Box" mengatur kedua kotak search sekaligus, belum bisa dibedakan sidebar vs konten.
- **Belum diuji di browser / WordPress / Elementor editor.** PHP belum di-lint (host tanpa `php`, cek bagian 7.3). Verifikasi sejauh ini cuma `node --check` JS, kurung CSS/PHP seimbang, dan cek ID control ganda.
- Nilai hex dan ukuran di panduan ini perkiraan dari gambar desain, bukan hasil ukur Figma.

---

## 10. Halaman Informasi (dokumen)

Halaman "Informasi" (Standar Pelayanan, Maklumat Pelayanan, dan entri lain) disusun dengan widget yang sama, tapi isinya dokumen (PDF, Word, gambar) yang dibuka di popup. Dua CPT baru (`service-standards`, `service-pledges`) dibuat lewat CPT UI.

Langkah lengkap (CPT, upload banyak file, isian widget, tes, troubleshooting) ada di **`.agents/tasks/documents/DOKUMEN.md`**.
