# Dokumen: Standar Pelayanan & Maklumat Pelayanan (halaman Informasi)

Panduan step by step buat dua hal baru di plugin **wp-taxonomy-grid v4.6.0** (fitur dokumen datang di 4.5.0; **4.6.0 mendesain ulang kartu dokumen**, lihat bagian 4.6, 6, 7, dan `.agents/tasks/document-card/`):

1. Dua CPT baru lewat kode: **Standar Pelayanan** (`service-standards`) dan **Maklumat Pelayanan** (`service-pledges`). Tiap post bisa punya BANYAK file (PDF, Word, gambar).
2. Klik kartu dokumen membuka **popup** yang menampilkan file itu (PDF lewat PDF.js, gambar, Word lewat viewer Microsoft).

Cara pakai: centang `- [ ]` jadi `- [x]` tiap selesai satu langkah. Perintah dijalankan di terminal **WSL**, di folder repo:

```bash
cd /mnt/c/projects/imigrasi-wp
```

> **Status jujur:** kode PHP sudah di-`php -l` di host (PHP 8.3.1 dari Laragon) dan lulus; fungsi murni (jenis file, deteksi host publik, URL embed Word) sudah dites. **Belum pernah diuji di browser / WordPress / Elementor**, dan PDF.js **belum dijalankan di browser** (cuma dites di Node: import + baca PDF kecil). Jadi ikuti panduan ini sambil ngetes; bagian 8 isinya checklist tes.

---

## Update ke 4.6.0 (desain ulang kartu): 3 langkah

1. `./scripts/sync-plugin.sh wp-taxonomy-grid` (output harus menampilkan `Version: 4.6.0`).
2. WP Admin > **Elementor > Tools > General > Regenerate CSS & Data**. Wajib: Style > Document Card sekarang menulis variabel CSS baru, dan kartu lama bergantung CSS lama.
3. Hard refresh browser (`Ctrl+Shift+R`) di halaman dan di editor Elementor. Tanpa ini `taxonomy-grid.js` / `media-popup.js` lama tertinggal di cache dan kartu dari **Lihat Lebih Banyak** serta tombol **Unduh** tidak jalan.

Widget yang sudah tersimpan tetap terbaca: ID control lama yang masih bermakna dipertahankan (Show Cover, Show File Count, tombol Lihat, Show Download Button popup, Gap, Padding, Radius, Border, Background, Shadow, Icon Color/Size, Title, Cover Height/Radius/Fit). Nilai yang tersimpan di control yang dihapus (Cover Width, jarak antar elemen, warna pill tunggal) diabaikan. **Show File Count** sekarang default OFF; widget yang menyimpan ON tetap ON.

---

## 0. Apa yang berubah

| File | Baru / ubah | Isi |
|---|---|---|
| ~~`web/app/mu-plugins/custom-post-types.php`~~ | dihapus | Dulu mendaftarkan 2 CPT lewat kode. Sekarang kedua CPT dibuat lewat **CPT UI** (lihat bagian 1A) |
| `plugins-custom/wp-taxonomy-grid/includes/class-document-meta-box.php` | baru | Meta box **Document Files** + `wptg_get_document_files()` (satu-satunya pembaca meta) |
| `.../assets/js/admin-documents.js`, `.../assets/css/admin-documents.css` | baru | UI meta box (media modal, drag urutan, label, hapus). Hanya dimuat di layar edit CPT dokumen |
| `.../assets/js/document-viewer.js` | baru | Isi popup: chip file, gambar, PDF.js, Word |
| `.../assets/vendor/pdfjs/` | baru | PDF.js **6.4.299** (Apache-2.0, build legacy) + worker, wasm, font, LICENSE, `VERSION.txt` |
| `.../includes/media-popup.php` | ubah | Jenis popup baru `document`, `wptg_is_public_url()`, filter `wptg_office_preview_enabled` |
| `.../wp-taxonomy-grid.php` | ubah | Content Style `document_list`, Click Behavior `document_preview`, skeleton `document`, enqueue |
| `.../includes/class-elementor-widget.php` | ubah | Control baru (daftar di bagian 6 dan 7) |
| `.../assets/js/modal.js`, `media-popup.js`, `taxonomy-grid.js` | ubah | Varian popup `document`, `WptgModal.onClose()`, mirror list class + opsi dokumen di AJAX (4.6.0: satu field `doc_options`, `fillIconSlots()`, flag `data-wptg-show-download`, klik `a[download]` diabaikan) |
| `.../assets/css/taxonomy-grid.css` | ubah | Kartu dokumen (4.6.0: ikon + judul di atas, cover di bawah, variabel `--wptg-doc-*`), skeleton, popup dokumen |
| `.../readme.txt`, `composer.json` | ubah | Versi 4.6.0 + bagian "Document Files" + changelog |
| `.gitignore` | ubah | Whitelist `plugins-custom/*/assets/vendor/` (tanpa ini folder PDF.js TIDAK ikut ke-commit) |

Popup foto dan video yang sudah ada **tidak berubah**. Urutan jenis popup sekarang: video > dokumen > foto > post biasa.

---

## 1A. Buat kedua CPT di CPT UI

CPT dikelola lewat **CPT UI** (bukan kode). Datanya disimpan di database, jadi **post yang sudah dibuat sebelumnya tidak hilang**: setelah CPT dibuat ulang dengan slug yang sama, post-nya muncul lagi.

> **Slug harus persis** `service-standards` dan `service-pledges`. Taxonomy Grid dan kotak Document Files mencarinya dengan nama itu.

WP Admin > **CPT UI > Add/Edit Post Types**. Buat dua kali, nilainya sama kecuali baris yang diberi tanda `*`:

| Field di CPT UI | Isi |
|---|---|
| Post Type Slug * | `service-standards` / `service-pledges` |
| Plural Label * | `Standar Pelayanan` / `Maklumat Pelayanan` |
| Singular Label * | `Standar Pelayanan` / `Maklumat Pelayanan` |
| Public | True |
| Publicly Queryable | True |
| Show UI | True |
| Show in Menu | True |
| Menu Icon | `dashicons-media-document` |
| Show in REST API | True |
| Has Archive | False |
| Rewrite | True |
| Custom Rewrite Slug * | `standar-pelayanan` / `maklumat-pelayanan` |
| Supports | Title, Editor, Featured Image, Excerpt |
| Taxonomies | **kosongkan semua** (jangan centang Categories atau Tags) |

Klik **Add Post Type**. Lalu flush rewrite rules:

```bash
docker compose exec -u www-data app wp rewrite flush --path=/app/web/wp
docker compose exec -u www-data app wp post-type list --fields=name,label,public --path=/app/web/wp | grep service
```

Staging dan production: CPT UI tidak ikut deploy karena datanya di database. Di sana buat lagi dengan cara yang sama, atau pindahkan lewat **CPT UI > Tools > Import/Export** (export JSON di dev, import di server).

---

## 1. Deploy ke container (dev)

- [ ] **1.1 Sync plugin** (wajib tiap habis edit kode plugin):

```bash
./scripts/sync-plugin.sh wp-taxonomy-grid
```

Baris `Version:` di output harus **4.6.0**.

- [ ] **1.2 Cek folder PDF.js ikut ke-copy** (folder bernama `vendor` kadang kena aturan ignore; harus ada isinya):

```bash
docker compose exec app ls web/app/plugins/wp-taxonomy-grid/assets/vendor/pdfjs
```

Harus ada `pdf.min.mjs`, `pdf.worker.min.mjs`, `wasm`, `standard_fonts`, `iccs`, `LICENSE`, `VERSION.txt`.

- [ ] **1.3 Buat kedua CPT di CPT UI dulu** (bagian 1A di atas). Tanpa itu kotak **Document Files** dan menu admin tidak muncul.

- [ ] **1.4 Flush rewrite rules** (WAJIB sekali, kalau tidak URL single `/standar-pelayanan/...` 404):

```bash
docker compose exec -u www-data app wp rewrite flush --path=/app/web/wp
```

- [ ] **1.5 Cek CPT terdaftar.** Harus ada `service-standards` dan `service-pledges`:

```bash
docker compose exec -u www-data app wp post-type list --path=/app/web/wp
```

- [ ] **1.6 Lint PHP di container** (di host sudah lulus, ini cek versi PHP container-nya):

```bash
docker compose exec app sh -c 'for f in web/app/plugins/wp-taxonomy-grid/wp-taxonomy-grid.php web/app/plugins/wp-taxonomy-grid/includes/*.php; do php -l "$f"; done'
```

Semua baris harus `No syntax errors detected`.

- [ ] **1.7 Regenerate CSS Elementor:** WP Admin > **Elementor > Tools > General** > **Regenerate CSS & Data**, lalu hard reload editor (`Ctrl+Shift+R`).

- [ ] **1.8 Cek `.mjs` disajikan sebagai JavaScript** (PDF.js dimuat sebagai module; header `X-Content-Type-Options: nosniff` aktif, jadi MIME salah = PDF gagal tampil):

```bash
curl -I http://localhost:6300/app/plugins/wp-taxonomy-grid/assets/vendor/pdfjs/pdf.min.mjs
```

Baris `Content-Type:` harus mengandung `javascript` (`text/javascript` atau `application/javascript`). Ulangi untuk `pdf.worker.min.mjs`, dan cek `wasm/jbig2.wasm` (harus `application/wasm`). Di prod ganti host dan port-nya.

Kalau MIME-nya salah (mis. `application/octet-stream`), tambahkan ini di blok server `.docker/frankenphp/Caddyfile` (belum gue terapin, karena header produksi lu yang pegang; juga belum dites):

```
@mjs path *.mjs
header @mjs Content-Type "text/javascript; charset=utf-8"
```

Kalau dibiarkan salah, PDF tampil pesan "Dokumen ini tidak bisa ditampilkan di sini" + link **Buka file**: halaman tidak rusak, cuma tanpa preview.

---

## 2. Polylang (kalau situs multibahasa)

- [ ] WP Admin > **Languages > Settings** > bagian **Custom post types and Taxonomies**: centang **Standar Pelayanan** dan **Maklumat Pelayanan**. Tanpa ini post-nya tidak punya bahasa dan bisa tidak muncul di daftar per bahasa.
- [ ] Taxonomy Grid membaca post lewat `WP_Query`, jadi Polylang menyaring per bahasa halaman. Pastikan tiap post punya bahasa.

---

## 3. Isi konten: upload banyak file di satu post

- [ ] **3.1** WP Admin > **Standar Pelayanan > Tambah Baru** (atau **Maklumat Pelayanan**). Isi **judul** (ini yang tampil di kartu dan di bawah popup).
- [ ] **3.2 Cover (opsional):** set **Featured image** untuk mengatur sendiri gambar di bawah judul kartu. Kalau kosong, kartu mencari cover dari sumber ini, urut: (1) Featured image, (2) file **gambar** pertama di Document Files, (3) preview halaman pertama **PDF** pertama bila WordPress berhasil membuatnya (butuh Imagick di server; tanpa itu dilewati), (4) tidak ada cover, tombol Lihat/Unduh jadi baris sendiri di bawah judul. Tidak pernah error.
- [ ] **3.3** Scroll ke kotak **Document Files**, klik **Tambah file**. Media modal terbuka: pilih **beberapa file sekaligus** (Ctrl/Shift+klik) atau upload baru, lalu **Tambah file**.
  - Yang bisa dipilih: PDF, `.doc`, `.docx`, JPG, PNG, WebP. Tipe lain tidak ditampilkan di modal, dan kalau lolos pun ditolak saat simpan.
  - Maksimal 50 file per post. File yang sama tidak masuk dua kali.
- [ ] **3.4 Urutan:** tarik ikon garis tiga (di kiri baris) untuk mengubah urutan. Urutan di sini = urutan chip di popup. File pertama yang dibuka duluan.
- [ ] **3.5 Label (opsional):** isi kolom **Label** kalau mau nama chip beda dari judul file. Kosong = pakai judul attachment (kolom menampilkan judul sebagai placeholder abu-abu).
- [ ] **3.6** Hapus file dari post dengan **Hapus** di barisnya (file tetap ada di Media Library).
- [ ] **3.7** Klik **Publish / Update**. Daftar file tersimpan sebagai meta `_wptg_document_files`.

Catatan:
- Kalau attachment dihapus dari Media Library, file itu otomatis hilang dari post (tidak error). Barisnya hilang dari kotak saat post dibuka lagi; simpan ulang untuk membersihkan datanya.
- Post tanpa file valid tidak jadi popup dokumen: popup jatuh ke foto (featured image) lalu ke tampilan post biasa.
- Limit upload server: `upload_max_filesize` 64M (lihat `.docker/frankenphp/php.ini`).

### Format yang disarankan

| Format | Kata gue |
|---|---|
| **PDF** | Pilihan utama untuk produksi. Dirender langsung di popup, tidak butuh layanan luar. Pakai PDF yang tidak diberi password. |
| **JPG / PNG / WebP** | Cocok untuk sertifikat atau scan satu halaman. Kompres dulu kalau besar. |
| **DOC / DOCX** | Preview lewat viewer online Microsoft, jadi **hanya jalan di situs yang bisa diakses publik** (lihat bagian 8.5). Di localhost yang muncul pesan + link **Buka file**. Kalau dokumen penting, sediakan PDF-nya. |

---

## 4. Susun halaman "Informasi"

- [ ] **4.1** Bikin halaman WordPress **Informasi** (slug mis. `informasi`), edit pakai Elementor, tambahkan widget **Taxonomy Grid**.
- [ ] **4.2 Content > Source:** **Source Mode = Sections**.
- [ ] **4.3 Content > Header:** **Subtitle** = `Informasi` (tetap). **Title** = **kosong** (otomatis mengikuti entri sidebar yang terbuka, mis. "Standar Pelayanan"). **Current Label** (Breadcrumb) juga dikosongkan supaya ikut.
- [ ] **4.4 Content > Sections:** isi baris repeater. Dua baris dokumen:

| Field | Baris 1: Standar Pelayanan | Baris 2: Maklumat Pelayanan |
|---|---|---|
| Label | `Standar Pelayanan` | `Maklumat Pelayanan` |
| Section Type | Flat List (no categories) | Flat List (no categories) |
| Post Type | Standar Pelayanan (`service-standards`) | Maklumat Pelayanan (`service-pledges`) |
| Content Style | **Document List (Flat List or Taxonomy Accordion)** | **Document List (...)** |
| Item Click Behavior | **Document Preview (PDF / Word / image)** | **Document Preview (PDF / Word / image)** |
| Include in Search | sesuai selera | sesuai selera |
| Items Per Page | 6 (kalau lebih banyak, muncul tombol Lihat Lebih Banyak) | 6 |

Kartu dokumen juga jalan di Taxonomy Accordion (Section Type = Taxonomy Accordion + Taxonomy), tapi dua CPT ini sengaja tanpa taxonomy.

- [ ] **4.5 Baris tambahan untuk entri lain di sidebar.** Entri lain di halaman Informasi memakai CPT **`information`** yang sudah ada (tidak ada CPT baru). Label baris = nama menu di desain; gue nggak tahu daftar lengkapnya, isi sesuai desain. Dua pola:

| Kebutuhan | Setelan |
|---|---|
| Daftar post biasa (tanpa kategori), klik tampil isi di panel kanan | Section Type = **Flat List**, Post Type = post type `information` (di dropdown tertulis sesuai label di CPT UI), Content Style = **Numbered Feed** (satu-satunya selain Document List untuk Flat List), Item Click Behavior = **Swap Content Panel (inline detail)** |
| Kategori `information_category` di sidebar, tiap kategori berisi post | Section Type = **Taxonomy Accordion**, Taxonomy = **Informasi Categories (`information_category`)**, Content Style = **Single Post Full Detail** (sidebar menampilkan post per kategori, isi penuh di panel) atau **Grid of Thumbnails** / **Vertical List of Thumbnails** kalau mau kartu, Item Click Behavior sesuai kebutuhan |

Satu baris repeater = satu entri di sidebar. Pola pertama menampilkan SEMUA post `information` dalam satu entri (belum bisa difilter per kategori); pola kedua memecahnya per kategori `information_category`. Pilih yang paling dekat dengan desain; keduanya sudah ada sebelum 4.5.0.

Kalau post `information` tertentu juga perlu file PDF/Word: tambahkan slug-nya ke filter (misal di `functions.php` tema atau mu-plugin), maka kotak Document Files muncul juga di sana:

```php
add_filter('wptg_document_post_types', static fn (array $types): array => [...$types, 'information']);
```

- [ ] **4.6 Content > Document Card** (muncul di panel setelah "Feed & Load More"; dulu bernama "Document List"). Default sudah sesuai desain, jadi biasanya tidak perlu diubah:

| Control | Default | Fungsi |
|---|---|---|
| Show Icon | ON | Ikon dokumen besar di kiri judul |
| Card Icon | kosong | Pilih ikon dari library Elementor atau upload SVG sendiri. Kosong = ikon bawaan plugin (dokumen + pensil, garis emas). Warna dan ukuran diatur di Style > Document Card > Icon |
| Show Date | ON | Teks tanggal di bawah judul (tanggal publish post, format `1 Oktober 2026`) |
| Date Label | kosong = `Diunggah pada:` | Teks sebelum tanggal |
| Show Cover | ON | Gambar di bawah judul (sumber: lihat 3.2) |
| Show File Count | **OFF** | Teks kecil `3 file` di bawah tanggal |
| View Button Text | kosong = `Lihat` | Teks tombol Lihat |
| Show View Icon | ON | Ikon mata di tombol Lihat |
| Show Download Button (card) | ON | Tombol **Unduh** di kartu. 1 file = langsung mengunduh file itu. Banyak file = membuka popup dengan tombol unduh terlihat (per file yang sedang dibuka) |
| Download Button Text | kosong = `Unduh` | Teks tombol Unduh |
| Show Download Icon | ON | Ikon panah unduh di tombol Unduh |
| Show Download Button (popup) | **OFF** | Beda dengan tombol kartu: ON = tombol Unduh selalu muncul di bawah viewer popup untuk SEMUA kartu |

Post tanpa file valid: tombol Lihat dan Unduh tidak dirender (kartu tampil tanpa tombol).

- [ ] **4.7** Content > Sections: **Show Search Box**, **Search Position**, **Show Content Title** sesuai desain. Content Title kosong = ikut label entri.
- [ ] **4.8** Simpan (**Update**), buka halaman di tab baru (bukan cuma preview editor; di editor Elementor klik kartu memang tidak membuka popup).

---

## 5. Perilaku popup (supaya tahu apa yang diharapkan)

- Klik kartu dengan mouse (judul, ikon, cover, atau tombol **Lihat**) membuka popup besar. Dari keyboard: `Tab` ke tombol **Lihat**, lalu Enter / Spasi (kartunya sendiri tidak bisa difokus, supaya tombol di dalamnya tidak "bersarang"). Popup: lebar `min(96vw, 960px)`, area viewer tinggi sekitar 72% layar dan **scroll di dalam popup**.
- **Lebih dari 1 file:** deretan chip (tombol) di atas viewer. Klik chip = ganti file. Chip aktif berwarna emas. Chip bisa digeser ke samping kalau banyak.
- **Gambar:** ditampilkan utuh.
- **PDF:** semua halaman dirender sebagai canvas berurutan, lebar mengikuti popup. Halaman digambar lazy (hanya yang dekat layar), dan paling banyak 8 canvas disimpan di memori, jadi PDF besar tidak membekukan HP. Tidak ada text layer (teks tidak bisa diseleksi/dicari). PDF.js baru diunduh saat PDF pertama dibuka.
- **Word:** iframe viewer Microsoft kalau situs publik; kalau tidak, pesan "Pratinjau Word hanya tersedia di situs yang bisa diakses publik." + link **Buka file**.
- **PDF berpassword / rusak / gagal dimuat:** pesan sopan + link **Buka file** (tab baru).
- **Tutup:** tombol X bulat emas, Esc, atau klik backdrop. Fokus kembali ke tombol yang diklik (untuk klik mouse di sembarang tempat kartu: tombol **Lihat** kartu itu). Saat ditutup, rendering PDF dihentikan dan dokumen dilepas dari memori.
- Judul post (dan tanggal) tampil di bawah viewer, sama seperti popup foto/video.

---

## 6. Control baru di Elementor (label persis di UI)

**Content tab**

| Lokasi | Label | Catatan |
|---|---|---|
| Sections > baris > Content Style | **Document List (Flat List or Taxonomy Accordion)** | opsi baru |
| Sections > baris > Item Click Behavior | **Document Preview (PDF / Word / image)** | opsi baru |
| Section **Document Card** (dulu "Document List") | **Show Icon**, **Card Icon**, **Show Date**, **Date Label**, **Show Cover**, **Show File Count**, **View Button Text**, **Show View Icon**, **Show Download Button (card)**, **Download Button Text**, **Show Download Icon**; heading **Popup**: **Show Download Button (popup)** | section baru (hanya Source Mode = Sections); tabel isi dan default di 4.6 |

**Style tab** (dua section baru, hanya Source Mode = Sections)

Di **Document Card**, tiap control cuma mengisi satu variabel CSS (`--wptg-doc-*`). Nilai default desain ada di CSS plugin, jadi control yang tidak disentuh tidak mengubah apa-apa. Deskripsi tiap slider menyebut default-nya. Daftar variabel, default, dan padanan angka desain: `.agents/tasks/document-card/UKURAN.md`.

| Section | Control |
|---|---|
| **Document Card** | Gap Between Cards; Padding; Border Radius; Border; tab **Normal** / **Hover** (Background Color, Box Shadow); heading **Icon**: Icon Color, Icon Size, Gap to Text, Icon Margin; heading **Title**: Title Typography, Title Color, Title Hover Color; heading **Date**: Date Typography, Date Color; heading **File Count**: File Count Typography, File Count Color; heading **Cover**: Cover Height, Space Above Cover, Cover Border Radius, Box Shadow, Cover Object Fit, Cover Object Position; heading **Buttons**: Overlay Offset Top, Overlay Offset Right, Gap Between Buttons, Button Typography, Button Padding, Button Border Radius, Button Min Height, Button Min Width, Button Icon Size; heading **View Button** dan heading **Download Button**, masing-masing tab **Normal** / **Hover** (Text Color, Background Color) |
| **Document Popup** | Panel Width; Viewer Height; Viewer Background; heading **File Chips**: tab **Normal** (Text Color, Background Color) dan **Active** (Text Color, Background Color); heading **PDF Pages**: Page Background, Space Between Pages, Page Min Height (while loading) |

Warna panel popup, tombol tutup bulat, judul, dan tanggal tetap diatur di **Style > Modal / Lightbox > Media Popup (video / photo)** (sama untuk semua popup). Section **Document Popup** memakai selector tanpa `{{WRAPPER}}` karena popup ada di `<body>`; efeknya sama seperti Media Popup (lihat catatan di `register_style_modal_controls()`).

---

## 7. Tabel Style yang disarankan

**Document Card: tidak perlu diisi.** Sejak 4.6.0 default plugin sudah mengikuti angka desain (kartu krem `#F9F5F1`, judul dan ikon emas `#F0B95A`, tanggal navy `#1B365D`, tombol Lihat `#17305B` dan Unduh `#EDB75B`, cover 116 px). Angkanya diestimasi dari screenshot, bukan diukur otomatis. Kalau di browser terlihat meleset, koreksi lewat control Style > Document Card, atau ubah nilai default-nya (tabel di `.agents/tasks/document-card/UKURAN.md`).

Nilai **Document Popup** (perkiraan dari palet halaman Berita, **cek ke Figma**):

| Section > Control | Disarankan |
|---|---|
| Document Popup > Panel Width | 960 px |
| Document Popup > Viewer Height | 72 vh |
| Document Popup > Viewer Background | `#EBE7E1` |
| Document Popup > File Chips > Normal | teks `#1B365D`, latar `#FFFFFF` |
| Document Popup > File Chips > Active | teks `#FFFFFF`, latar `#E8B04B` |
| Document Popup > PDF Pages > Page Background | `#FFFFFF` |
| Document Popup > PDF Pages > Space Between Pages | 12 px |
| Modal / Lightbox > Media Popup > Close Button Background | `#E8B04B` (default plugin `#f1a945`) |

Skeleton (kalau belum ada post) memakai **Style > Empty State (Skeleton)** yang sudah ada.

---

## 8. Tes manual

Sesudah bagian 1 sampai 4 beres, siapkan data: satu Standar Pelayanan dengan **PDF multi-halaman** (10+ halaman), satu dengan **PDF + 1 gambar + 1 DOCX** (3 file), satu **tanpa file**.

- [ ] **8.1 Kartu (desain 1:1):** buka `.agents/tasks/document-card/preview.html` di browser dan bandingkan dengan desain dulu (itu markup asli renderer, tanpa WordPress). Lalu cek di halaman nyata: baris atas = ikon emas besar di kiri + judul emas + `Diunggah pada: 1 Oktober 2026` (navy); **cover di BAWAH judul**, selebar kartu, tinggi sekitar 116 px; di pojok kanan atas cover ada pill **Lihat** (navy, ikon mata) dan **Unduh** (emas, ikon unduh). Hover mengubah latar kartu. `Tab` memfokuskan tombol **Lihat** (kartunya tidak), Enter/Spasi membuka popup; klik di mana saja pada kartu dengan mouse juga membuka popup.
- [ ] **8.1b Tombol Unduh:** post dengan 1 file: klik **Unduh** langsung mengunduh file itu, popup TIDAK terbuka. Post dengan banyak file: klik **Unduh** membuka popup dan tombol **Unduh** di bawah viewer muncul (mengunduh file yang sedang dibuka; ganti chip untuk file lain). Post tanpa file: tidak ada tombol sama sekali.
- [ ] **8.1c Cover:** post dengan featured image memakai itu; tanpa featured image tapi ada file gambar memakai gambar pertama; tanpa keduanya tapi PDF dengan preview (server ber-Imagick) memakai preview PDF; kalau semuanya tidak ada, tidak ada kotak cover dan tombol jadi baris kanan di bawah judul.
- [ ] **8.1d Ikon bisa diubah:** Content > Document Card > **Card Icon**: pilih ikon library atau upload SVG. Ikon di kartu berganti, warna/ukurannya ikut Style > Icon. Klik **Lihat Lebih Banyak**: kartu baru dari AJAX juga memakai ikon itu.
- [ ] **8.2 PDF multi-halaman (desktop):** popup terbuka, ada spinner "Memuat dokumen...", lalu halaman 1 muncul. Scroll di dalam popup: halaman berikutnya muncul. Scroll sampai ujung viewer tidak menggeser halaman di belakang popup. Teks terbaca tajam.
- [ ] **8.3 PDF di HP / emulator HP:** lebar halaman pas layar, scroll mulus, tidak freeze, scroll cepat sampai halaman akhir lalu balik ke atas tetap normal (halaman jauh dibuang dari memori dan digambar lagi saat didekati).
- [ ] **8.4 Ganti file lewat chip:** post dengan 3 file: klik chip kedua (gambar), lalu chip ketiga (DOCX), lalu balik ke PDF. Chip aktif berganti warna, viewer kembali ke atas tiap ganti. Tidak ada PDF yang "nyangkut" di belakang.
- [ ] **8.5 DOCX:** di server publik (staging/prod) viewer Microsoft tampil di dalam popup. Di `localhost` (dev) muncul pesan "Pratinjau Word hanya tersedia di situs yang bisa diakses publik." + **Buka file**. Itu perilaku yang benar, bukan bug.
- [ ] **8.6 Tutup:** tombol X, Esc, klik backdrop. Fokus balik ke tombol Lihat kartu. Buka DevTools > Network: setelah ditutup tidak ada request halaman PDF baru; buka PDF lagi berjalan normal.
- [ ] **8.7 Popup lama tidak berubah:** klik item Galeri Foto dan Galeri Video (Media Popup) di halaman lain: foto dan video tetap seperti sebelumnya.
- [ ] **8.8 Post tanpa file:** kartu tampil tanpa tombol Lihat/Unduh dan tanpa "n file"; klik pada kartu membuka popup foto (kalau ada featured image) atau tampilan post biasa.
- [ ] **8.9 Load More dan search:** isi lebih dari 6 post, tombol **Lihat Lebih Banyak** menambah kartu dokumen dengan tampilan sama. Search mengubah daftar; hasil kosong = skeleton kartu dokumen.
- [ ] **8.10 Skeleton:** kosongkan semua post (atau pakai section yang belum ada post): panel menampilkan skeleton kartu dokumen (blok ikon di kiri, dua garis, blok cover lebar di bawah dengan dua pill kecil di pojok kanan atas).
- [ ] **8.11 Show Download Button (popup) = ON:** tombol **Unduh** muncul di bawah viewer untuk semua kartu (juga bila klik datang dari tombol **Lihat**) dan mengunduh file yang sedang aktif. OFF = tombol itu hanya muncul bila popup dibuka lewat tombol **Unduh** kartu dengan banyak file.
- [ ] **8.13 Responsif:** lebar 1024 / 767: padding kartu 15 px, ikon 42 px, judul 16 px, tanggal 12 px, cover 96 px, tombol 48 x 20. Lebar < 360 px: ikon pindah ke atas teks. Cover tetap di bawah judul. (Cek juga iframe 360 / 320 px di `preview.html`.)
- [ ] **8.12 Console:** tidak ada error merah saat buka dan tutup popup (peringatan `.map` hilang abaikan).

---

## 9. Server dan keamanan

### 9.1 Header (belum gue ubah)

- `.docker/frankenphp/Caddyfile`: `X-Frame-Options SAMEORIGIN` hanya membatasi halaman lu di-iframe pihak lain; **tidak** mempengaruhi iframe viewer Microsoft di dalam halaman lu. Tidak ada `Content-Security-Policy` default (komentar di file menyebut prod boleh menambah).
- `web/app/mu-plugins/security.php`: filter `upload_mimes` hanya membuang tipe PHP. `pdf`, `doc`, `docx`, `jpg`, `png`, `webp` tetap lolos (bawaan WordPress). Caddy juga menolak eksekusi PHP di uploads.

### 9.2 Kalau prod nanti memasang CSP

Direktif **minimum** yang dibutuhkan fitur ini (gabungkan dengan yang sudah dibutuhkan Elementor/tema; Elementor sendiri biasanya masih butuh `'unsafe-inline'`):

```
frame-src https://www.youtube-nocookie.com https://player.vimeo.com https://view.officeapps.live.com;
worker-src 'self' blob:;
script-src 'self' 'wasm-unsafe-eval';
img-src 'self' data: blob:;
connect-src 'self';
```

| Direktif | Kenapa |
|---|---|
| `frame-src ... view.officeapps.live.com` | iframe viewer Word (YouTube/Vimeo untuk popup video yang sudah ada) |
| `worker-src 'self' blob:` | worker PDF.js (modul dari folder plugin) |
| `script-src 'self'` | PDF.js dimuat sebagai module dari domain sendiri |
| `'wasm-unsafe-eval'` | decoder gambar PDF (JBIG2 / JPEG2000) berbentuk WebAssembly. Tanpa ini PDF biasa tetap tampil, kemungkinan hanya PDF scan jenis itu yang gambarnya kosong |
| `img-src data: blob:` | hasil canvas dan gambar inline |
| `connect-src 'self'` | PDF.js mengambil file PDF, wasm, dan font lewat fetch |

Kalau file upload dilayani dari domain lain (CDN), domain itu juga harus mengizinkan CORS ke domain situs (PDF.js memakai fetch), dan masuk ke `connect-src`/`img-src`.

### 9.3 Pemakaian layanan luar

Viewer Word mengirim URL file ke `view.officeapps.live.com` (Microsoft yang mengunduh file itu). Jangan pakai untuk file yang tidak boleh publik. Matikan lewat filter kalau perlu:

```php
add_filter('wptg_office_preview_enabled', '__return_false');
```

Filter ini menerima `($enabled, $file_url)`, jadi bisa juga dipakai untuk menyalakan di host tertentu.

---

## 10. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| Menu **Standar Pelayanan** / **Maklumat Pelayanan** tidak muncul di admin | CPT belum dibuat di CPT UI, atau slug-nya salah. Cek CPT UI > Edit Post Types, dan `wp post-type list`. CPT UI menyimpan datanya di database, jadi di staging/prod CPT harus dibuat lagi (atau Import/Export CPT UI, bagian 1A) |
| URL single (`/standar-pelayanan/...`) 404 | belum `wp rewrite flush` (bagian 1.4) |
| Kotak **Document Files** tidak ada di edit post | post type tidak ada di filter `wptg_document_post_types`, atau plugin belum di-sync (bagian 1.1) |
| Tombol **Tambah file** tidak membuka media modal | cek Console: JS admin error. Pastikan plugin versi 4.5.0 (`wp plugin list`) dan hard reload |
| File tidak muncul di kartu / popup | attachment sudah dihapus, tipe file bukan PDF/DOC/DOCX/JPG/PNG/WebP, atau post belum di-**Update** setelah menambah file |
| Kartu muncul tapi klik tidak membuka popup | Item Click Behavior bukan **Document Preview** atau **Media Popup**; atau sedang di editor Elementor (popup sengaja dimatikan di editor, tes di tab biasa) |
| Tombol **Lihat** / **Unduh** tidak ada di kartu | post tidak punya file valid (lihat baris "File tidak muncul"). **Unduh** juga hilang bila Show Download Button (card) OFF, atau post punya banyak file tetapi Item Click Behavior bukan Document Preview / Media Popup (tidak ada popup untuk dibuka) |
| Cover kosong padahal ada file PDF | WordPress tidak membuat preview PDF (server tanpa Imagick). Set **Featured image** atau tambahkan file gambar di Document Files |
| Ikon custom tidak muncul di kartu yang dimuat lewat Lihat Lebih Banyak | cek Console; ikon disalin dari `<template class="wptg-doc-icon-template">` di dalam `.wptg-wrapper`. Hard reload setelah sync (`taxonomy-grid.js` lama tidak punya `fillIconSlots`) |
| Warna tombol Unduh sulit dibaca | teks putih di atas emas `#EDB75B` kontrasnya rendah (sesuai desain). Ubah di Style > Document Card > Download Button |
| Popup terbuka tapi isinya post biasa / foto, bukan dokumen | post tidak punya file valid (lihat baris "File tidak muncul"), atau ada video di isi post (video menang atas dokumen) |
| PDF tidak tampil: pesan "Dokumen ini tidak bisa ditampilkan di sini" | buka Console. (1) **MIME `.mjs` salah** atau 404: ulangi bagian 1.8. (2) **CSP** memblokir worker/script: lihat bagian 9.2. (3) Folder `assets/vendor/pdfjs` tidak ikut ke-copy: bagian 1.2. (4) URL file PDF beda domain tanpa CORS |
| PDF tampil tapi gambar scan kosong | CSP tanpa `'wasm-unsafe-eval'`, atau `wasm/` tidak ter-copy |
| PDF tampil tapi huruf aneh | PDF memakai font standar yang tidak di-embed; folder `standard_fonts/` harus ikut ter-copy. PDF dengan huruf CJK butuh `cmaps/` (sengaja tidak ikut) |
| "PDF dilindungi password" | PDF.js tidak bisa membuka PDF berpassword di sini. Pakai PDF tanpa password, atau pakai link **Buka file** |
| DOCX hanya pesan + link di localhost | normal: viewer Microsoft tidak bisa mengambil file dari `localhost`/`*.local`/`*.test`/IP privat/port dev. Tes di situs publik |
| DOCX tetap pesan di situs publik | URL file ternyata dianggap non-publik (port selain 80/443, host tanpa titik) atau filter `wptg_office_preview_enabled` mengembalikan false |
| Skeleton (kotak abu) muncul terus di panel | belum ada post terbit di post type itu (untuk kartu dokumen: post harus **Published**). Skeleton bukan error |
| Kartu muncul tapi tanpa "n file" | normal: Show File Count default **OFF** sejak 4.6.0. Nyalakan di Content > Document Card. Kalau sudah ON dan tetap tidak ada: post belum punya file valid |
| Style Document Popup tidak berpengaruh | popup ada di `<body>`; Regenerate CSS Elementor dan hard reload. Style hanya berlaku di halaman yang memuat widget ini |

---

## 11. Keputusan desain dan yang tidak dikerjakan

**Keputusan**

- `kind` popup dipilih dari **data** (post punya file valid), bukan dari post type. Post type dokumen tanpa file tidak menghasilkan viewer kosong, tapi jatuh ke foto / post biasa.
- Flat List sekarang boleh memakai `document_list` (sebelumnya Flat List dipaksa Numbered Feed). Style lain tidak berubah.
- Satu dispatcher klik: kartu dokumen memakai `data-wptg-media` yang sama dengan Media Popup. Atribut itu ada di `<article>` (klik mouse di mana saja) dan di tombol **Lihat** / **Unduh** (banyak file) yang berupa `<button>`. Flag `data-wptg-show-download="1"` (di kartu untuk setting popup, di tombol Unduh untuk klik itu saja) dibaca lewat `closest()`; menimpa setting widget hanya ke arah "tampilkan".
- A11y kartu (4.6.0): `<article>` tidak punya `role="button"` / `tabindex` karena memuat tombol dan link sungguhan (menghindari interactive bersarang). Jalur keyboard = tombol Lihat. Satu file: **Unduh** = `<a download>`, klik darinya diabaikan listener popup (`closest('a[download]')`). Fokus setelah popup ditutup kembali ke tombol Lihat milik kartu.
- Cover tidak pernah `<img src=".pdf">`: untuk PDF, `wptg_get_pdf_preview_image()` memeriksa dulu bahwa WordPress benar-benar membuat gambar (fungsi `wp_get_attachment_image()` untuk lampiran non-gambar tanpa thumbnail mengembalikan URL file aslinya, bukan string kosong).
- Opsi kartu dikirim ke AJAX sebagai satu JSON (`data-doc-options` -> field `doc_options`), divalidasi ulang di server oleh `wptg_normalize_document_options()`; HTML ikon kustom tidak pernah diterima dari browser.
- Server **tidak** mengirim HTML preview; hanya daftar file (`label, url, kind, mime, ext, size, download_url, office_preview, embed_url`). `office_preview` dihitung per file di server.
- File JS baru `document-viewer.js` (bukan di dalam `media-popup.js`) supaya satu file satu tanggung jawab; `media-popup.js` tetap pemegang listener.
- Build **legacy** PDF.js (jalan di HP lama; sekitar 60 KB lebih besar per file). Untuk ganti ke build modern: timpa `pdf.min.mjs` dan `pdf.worker.min.mjs` dengan versi `build/` dari paket yang sama.
- Ikut di-vendor: `wasm/` (JBIG2, JPEG2000, qcms), `standard_fonts/`, `iccs/`. Tidak ikut: source map, `cmaps/`, web viewer, sandbox, types. Total sekitar 3,0 MiB di disk (3.139.246 byte); browser hanya mengunduh `pdf.min.mjs` (524 KB) + worker (1,3 MB) saat PDF pertama dibuka, dan wasm/font bila PDF membutuhkannya.
- Kartu mengurutkan post **terbaru dulu** (sama seperti section lain). Urutan manual antar post belum ada.

**Tidak dikerjakan (YAGNI / di luar permintaan)**

- Text layer PDF (seleksi dan cari teks), zoom, tombol halaman berikutnya, cetak.
- Render ulang saat ukuran popup berubah (rotasi HP): canvas menyesuaikan lewat CSS, ketajaman tidak dihitung ulang.
- Taxonomy untuk dua CPT baru, halaman archive, template single.
- Mengubah header Caddy / CSP produksi (hanya didokumentasikan).
- Editor Elementor untuk dua CPT ini (tidak perlu; isi lewat judul, editor biasa, dan meta box).
- Upload otomatis dari URL, atau konversi Word ke PDF di server.

**Risiko yang harus lu uji** (belum pernah jalan di browser): render PDF.js di browser asli (terutama Safari iOS), media modal di meta box (`library.type` array), simpan meta lewat Block Editor, MIME `.mjs`, viewer Word di situs publik, dan tampilan kartu terhadap desain Figma.

---

## 12. Memperbarui PDF.js nanti

Isi `assets/vendor/pdfjs/VERSION.txt` mencatat versi, sumber, dan tanggal. Untuk update, dari folder repo (butuh Node dan internet):

```bash
node .agents/tasks/documents/fetch-pdfjs.js   # npm pack pdfjs-dist ke .agents/tasks/documents/tmp
node .agents/tasks/documents/copy-pdfjs.js    # salin file yang dibutuhkan + tulis VERSION.txt
```

Lalu sync plugin, tes ulang PDF multi-halaman (bagian 8.2 sampai 8.4), dan hapus folder `.agents/tasks/documents/tmp`.

---

## 13. Verifikasi yang sudah dijalankan

- `php -l` semua file PHP yang diubah/baru (PHP 8.3.1 di host): lulus. Yang belum: PHP versi container.
- `node --check` semua JS non-vendor, BOM, kurung CSS, ID control Elementor tidak ganda: bersih (`.agents/tasks/documents/lint-all.js`, `.agents/tasks/check-plugin.js`).
- `.agents/tasks/documents/test-logic.js` (port Node) dan `test-logic.php` (kode PHP asli dengan stub WordPress) memakai tabel yang sama (`test-cases.json`): jenis file dari mime/ekstensi, deteksi host non-publik untuk viewer Office, URL embed (rawurlencode), penjaga ukuran file. Semua lulus.
- `smoke-pdfjs.mjs`: PDF.js di vendor bisa di-import di Node dan membaca PDF dua halaman (jumlah halaman dan ukuran viewport benar). Rendering canvas **tidak** dites.
- Belum: browser, WordPress (simpan meta, AJAX), Elementor editor.

**Kartu dokumen 4.6.0** (`.agents/tasks/document-card/`):

- `lint-card.js` (php -l semua PHP plugin, BOM, strict_types, `node --check` semua JS, kurung CSS, ID control unik, versi 4.6.0 di semua tempat, sisa kode lama), `check-plugin.js`: bersih.
- `render-preview.php` menjalankan renderer ASLI (`wptg_render_item_document_list()` dkk) dengan stub WordPress dan menulis `preview.html`, `preview-frame.html`, `markup-sample.html`. `check-markup.js` memeriksa markup itu (cover di bawah judul, tombol di dalam cover, `<a download>` untuk 1 file, flag untuk banyak file, tanpa tombol bila tanpa file, PDF tanpa preview tidak jadi `<img src=.pdf>`, `<article>` tanpa role/tabindex): lulus. `test-options.php` (normalizer opsi, termasuk JSON yang dirusak): lulus.
- **Belum terbukti:** kesesuaian visual 1:1. Angka desain berasal dari estimasi screenshot (`UKURAN.md`); tidak ada pengukuran otomatis. Buka `preview.html` dan bandingkan dengan desain. Belum diuji di browser/WordPress/Elementor: klik, popup, AJAX Load More, ikon kustom.
