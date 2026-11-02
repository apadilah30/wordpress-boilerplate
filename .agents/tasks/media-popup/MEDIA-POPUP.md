# Media Popup: klik video / foto = popup (wp-taxonomy-grid 4.3.0 + wp-content-grid 1.2.0)

Status jujur: kode sudah ditulis, tapi **PHP belum di-lint** (host nggak punya `php`) dan **belum diuji di browser / Elementor**. Yang sudah dicek: `node --check` semua JS lulus, kurung PHP/CSS seimbang, nggak ada BOM, `test-logic.js` (aturan parse YouTube/Vimeo/file, whitelist host, urutan prioritas) lulus 83 cek. Ikuti langkah 2 dulu, baru tes.

Cara pakai: ganti `- [ ]` jadi `- [x]` tiap selesai satu langkah.

---

## 1. Apa yang berubah

Setiap video dan foto yang diklik sekarang membuka **satu popup yang sama**. Nggak ada lagi redirect atau tab baru untuk video.

**Tampilan popup** (sesuai screenshot):

- Backdrop gelap transparan, halaman di belakang terlihat redup.
- Panel di tengah, lebar `min(92vw, 800px)`, background krem `#FBF7F4`, radius 8px, padding 14px.
- Isi panel dari atas ke bawah: **media** (foto besar atau player 16:9), **judul** (bold, navy `#1B365D`, 14px), **tanggal** (kecil, format `02 Oktober 2026`).
- Tombol tutup: lingkaran emas `#F1A945` dengan X putih, diameter 28px, nempel di pojok kanan atas dan sebagian keluar dari tepi panel.
- Mobile (<= 767px): panel 92% lebar layar.

**Popup video** punya panel dan tombol tutup yang sama, tapi media-nya player 16:9 yang **langsung diputar**. Featured image **tidak ditampilkan sama sekali** (bukan gambar, bukan poster `<video>`).

**Jenis popup ditentukan dari isi post, bukan dari jenis CPT:**

| Kondisi post | Popup |
|---|---|
| Punya sumber video (lihat urutan di bagian 5) | **Video** |
| Nggak ada video, tapi ada gambar (Featured Image, atau gambar pertama di isi) | **Foto** |
| Nggak ada video dan nggak ada gambar | Tampilan post biasa (judul + isi), sama seperti "Open Floating Modal" |

**Kode:**

| Plugin | Versi | Perubahan |
|---|---|---|
| `wp-taxonomy-grid` | 4.2.0 -> **4.3.0** | Pilihan baru `Media Popup (video / photo)` di **Item Click Behavior**; file baru `includes/media-popup.php` (data popup + AJAX `wptg_get_media_modal`) dan `assets/js/media-popup.js`; `modal.js` dan CSS dilengkapi; control Style baru di Modal / Lightbox |
| `wp-content-grid` | 1.1.0 -> **1.2.0** | Pilihan baru `Media Popup (video / photo)` di **Item Link > Link Mode** |

Cara kerjanya: kartu yang boleh membuka popup punya atribut `data-wptg-media="<ID post>"`. Satu listener di `document` (`media-popup.js`) menangkap klik, minta data ke server, lalu menampilkannya. Karena listener-nya di `document`, kartu yang baru muncul lewat **Lihat Lebih Banyak**, search, atau ganti kategori otomatis ikut jalan.

Hal-hal yang sengaja **nggak berubah**:

- Behavior `Swap Content Panel (inline detail)` dan `Open Floating Modal` (Pengumuman) sama persis seperti sebelumnya.
- Fitur deep link Content Grid (`Content Navigator page (deep link)`, lihat `.agents/tasks/deeplink/DEEPLINK.md`) tetap ada dan jalan. Satu widget Content Grid cuma bisa pakai **satu** Link Mode: deep link **atau** Media Popup.
- Default Link Mode Content Grid tetap `Post permalink`, jadi widget lama nggak berubah.

---

## 2. Sync ke container

Di terminal WSL, folder repo:

- [ ] Sync kedua plugin:

```bash
./scripts/sync-plugin.sh wp-taxonomy-grid
./scripts/sync-plugin.sh wp-content-grid
```

- [ ] Cek versi di WP Admin > Plugins: Taxonomy Grid **4.3.0**, Content Grid **1.2.0**.
- [ ] Elementor > Tools > **Regenerate CSS & Data**, lalu hard refresh browser (Ctrl+F5). Tanpa ini JS/CSS lama bisa masih ke-cache dan popup nggak muncul.
- [ ] (Disarankan) cek sintaks PHP:

```bash
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/wp-taxonomy-grid.php
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/includes/media-popup.php
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/includes/class-elementor-widget.php
docker compose exec app php -l web/app/plugins/wp-taxonomy-grid/includes/class-video-meta-box.php
docker compose exec app php -l web/app/plugins/wp-content-grid/wp-content-grid.php
docker compose exec app php -l web/app/plugins/wp-content-grid/includes/class-elementor-widget.php
```

Syarat: WordPress **6.2 atau lebih baru** (kode pakai `WP_HTML_Tag_Processor`). Di proyek ini WordPress 6.9, jadi aman.

---

## 3. Widget Taxonomy Grid (halaman Berita)

Edit halaman Berita dengan Elementor, klik widget Taxonomy Grid (Source Mode = Sections), tab **Content** > **Sections**.

- [ ] Buka baris **Galeri Foto**: set **Item Click Behavior** = `Media Popup (video / photo)`.
- [ ] Buka baris **Galeri Video**: set **Item Click Behavior** = `Media Popup (video / photo)`.
- [ ] Baris **Berita Terbaru** (`Swap Content Panel`) dan **Pengumuman** (`Open Floating Modal`) jangan diubah.
- [ ] **Update** halaman.

Catatan:

- Kalau baris lu masih bertuliskan `Old: Video redirect / popup (now same as Media Popup)`, itu nilai lama (`Video: Redirect to URL, or Popup Player`). Sekarang perilakunya **sudah sama** dengan Media Popup (nggak redirect lagi), tapi ganti ke `Media Popup (video / photo)` biar rapi.
- Popup bisa diatur di tab **Style** > **Modal / Lightbox**, bagian **Media Popup (video / photo)**:

| Control | Fungsi | Default |
|---|---|---|
| Popup Width | Lebar panel (px / vw). Di HP tetap 92% layar | 800px |
| Popup Border Radius | Radius panel | 8px |
| Popup Background | Warna panel | `#FBF7F4` |
| Close Button Background | Warna lingkaran tutup | `#F1A945` |
| Close Button Icon Color | Warna X | putih |
| Popup Title (Typography + Color) | Judul | 14px bold, `#1B365D` |
| Popup Date (Typography + Color) | Tanggal | 12px, abu gelap |

Control ini berlaku ke popup di halaman itu. Popup ditempel ke `<body>` (di luar widget), jadi kalau ada beberapa widget Taxonomy Grid di satu halaman, yang terakhir menang. Kalau nggak diubah, tampilan sudah sesuai desain.

---

## 4. Widget Content Grid (beranda)

Edit beranda dengan Elementor, klik widget Content Grid, tab **Content** > **Item Link**.

- [ ] Set **Link Mode** = `Media Popup (video / photo)` (deskripsi di bawahnya: "Butuh plugin Taxonomy Grid aktif").
- [ ] Ulangi untuk tiap widget Content Grid yang mau popup (widget galeri dan widget video, masing-masing dengan **Post Type** yang benar di section Source).
- [ ] **Update** beranda.

Yang terjadi:

- Kartu tetap `<a href="link post">`, ditambah `data-wptg-media="<ID post>"`. Klik biasa membuka popup di beranda (halaman nggak pindah). Ctrl/Cmd + klik, klik tengah, atau JavaScript mati: tetap ke halaman post.
- Field **Target Page URL** dan **Taxonomy** disembunyikan di mode ini (cuma dipakai oleh deep link).
- Kalau plugin **Taxonomy Grid nggak aktif**: kartu jatuh ke link post biasa (nggak error). Di editor Elementor muncul catatan kecil "Media Popup butuh plugin Taxonomy Grid aktif" (cuma kelihatan oleh user yang bisa edit, nggak kelihatan pengunjung).

---

## 5. Menyiapkan konten

### 5.1 Video

Sumber video dicari **berurutan, yang pertama ketemu dipakai**:

| Urutan | Sumber | Catatan |
|---|---|---|
| 1 | Meta box **External Video URL** (ada di kolom samping layar edit post `videos`) | Pilihan eksplisit editor. Menang atas yang lain |
| 2 | **Elemen video pertama di isi post** | Blok Video, blok Embed (YouTube / Vimeo), tag `<video>`, shortcode `[video]` / `[embed]`, atau `<iframe>` YouTube / Vimeo. Cuma elemen pertama; sisa isi post nggak ditampilkan. `<iframe>` dari host lain (mis. Google Maps) diabaikan |
| 3 | **File video yang ter-attach** ke post | Upload video dari dalam layar edit post itu |

Cara pilih salah satu:

- [ ] **Paling gampang:** isi **External Video URL** dengan link YouTube atau Vimeo (`https://youtube.com/watch?v=...`, `https://youtu.be/...`, `.../shorts/...`, `https://vimeo.com/123456789`).
- [ ] **Atau** taruh blok **Video** / **Embed** di editor post (Gutenberg / classic).
- [ ] **Atau** upload file video dari layar edit post (jadi attachment post itu).

Cara video diputar:

| Jenis | Hasil |
|---|---|
| YouTube | Iframe `youtube-nocookie.com`, autoplay |
| Vimeo | Iframe `player.vimeo.com`, autoplay |
| File langsung (`.mp4`, `.webm`, `.ogv`, `.m4v`, `.mov`) atau file ter-attach | Pemutar HTML5 dengan tombol kontrol, diputar otomatis |
| Link lain (TikTok, Instagram, channel YouTube, dst.) | Nggak bisa di-embed. Popup menampilkan pesan singkat dan tombol **Buka video** (tab baru). Nggak ada redirect otomatis |

Catatan teknis:

- ID YouTube/Vimeo dibaca dari teks URL, **tanpa panggilan keluar** (nggak pakai oEmbed), jadi aman di container tanpa internet dan nggak lambat.
- Post yang dibangun **hanya dengan Elementor** menyimpan isinya di tempat lain, bukan di isi post, jadi video di dalam widget Elementor **tidak terdeteksi**. Untuk post seperti itu pakai External Video URL.
- Video wajib `https` supaya nggak diblok browser di situs https.

### 5.2 Foto

- [ ] Isi **Featured Image** (wajib). Itu yang tampil di popup foto (ukuran `large`, alt = judul).
- Kalau Featured Image kosong, dipakai gambar pertama di isi post.

### 5.3 Kalau nggak ada video dan nggak ada gambar

Popup menampilkan tampilan post biasa (judul, tanggal, penulis, isi), sama seperti `Open Floating Modal`. Jadi klik nggak pernah "mati".

### 5.4 Tanggal

Tanggal tampil `d F Y`, mis. `02 Oktober 2026`. Nama bulan "Oktober" muncul kalau bahasa situs = **Bahasa Indonesia** (Settings > General > Site Language). Kalau masih English, tampil "October".

---

## 6. Tes manual

Buka halaman di tab baru (bukan preview editor; di editor Elementor popup sengaja dimatikan supaya klik nggak mengganggu pemilihan widget).

**Popup video** (Galeri Video di halaman Berita, dan kartu video di beranda):

- [ ] Klik kartu video: popup muncul (sempat tulisan "Memuat..."), lalu video **langsung diputar**.
- [ ] **Featured image nggak muncul** di popup (cek juga lewat DevTools: nggak ada `<img>` dan nggak ada atribut `poster` di popup).
- [ ] Judul dan tanggal ada di bawah player.
- [ ] Tutup popup (tombol X, Esc, atau klik backdrop): video berhenti dan **suara hilang**.
- [ ] Buka lagi video yang sama atau video lain: jalan normal.
- [ ] Post dengan External Video URL TikTok/Instagram: popup menampilkan pesan + tombol **Buka video**, nggak ada redirect.

**Popup foto** (Galeri Foto):

- [ ] Klik foto: popup muncul dengan foto besar penuh lebar panel, di bawahnya judul (bold navy) dan tanggal kecil.
- [ ] Foto tinggi (portrait) tinggi maksimalnya 70% layar, nggak bikin panel keluar layar.

**Umum:**

- [ ] Tombol tutup: lingkaran emas, X putih, nempel di pojok kanan atas dan sebagian keluar dari panel.
- [ ] Esc menutup. Klik backdrop menutup. Klik di dalam panel **nggak** menutup.
- [ ] Setelah ditutup, fokus keyboard kembali ke kartu yang tadi diklik (tekan Tab untuk memastikan).
- [ ] Keyboard: Tab ke kartu, Enter atau Spasi membuka popup (kartu Taxonomy Grid).
- [ ] Mobile (DevTools responsive 375px): panel sekitar 92% lebar layar, tombol tutup tetap kelihatan penuh, nggak ada scroll horizontal.
- [ ] **Lihat Lebih Banyak**: kartu baru yang muncul juga membuka popup.
- [ ] **Search** dan **ganti kategori**: kartu hasilnya juga membuka popup.
- [ ] Pengumuman (Open Floating Modal) dan Berita Terbaru (Swap Content Panel) **masih seperti dulu**.
- [ ] Beranda (Content Grid): Ctrl+klik kartu membuka halaman post di tab baru.
- [ ] Matikan JavaScript (DevTools > Command menu > Disable JavaScript) lalu klik kartu beranda: pindah ke halaman post.
- [ ] DevTools > Console: nggak ada error merah. DevTools > Network (filter `admin-ajax`): request `wptg_get_media_modal` status **200** dengan `success: true`.

---

## 7. Troubleshooting

| Gejala | Penyebab / solusi |
|---|---|
| Klik kartu nggak memunculkan popup (halaman pindah ke post / nggak terjadi apa-apa) | Belum sync atau CSS/JS ke-cache. Ulangi bagian 2 (sync, Regenerate CSS, hard refresh, purge plugin cache). Cek juga di "View Page Source": kartu harus punya `data-wptg-media="..."`. Kalau atribut itu nggak ada, Item Click Behavior / Link Mode belum diset ke Media Popup |
| Popup muncul tapi tulisan "Konten tidak bisa dimuat" | Nonce kedaluwarsa karena halaman lama di-cache (nonce berlaku sekitar 12-24 jam): purge cache halaman. Atau cek Network: respons `-1` / 403 = nonce salah, respons `Konten tidak tersedia` = post belum Published / bukan post type publik / dilindungi password |
| Content Grid: kartu tetap ke halaman post | Plugin Taxonomy Grid belum aktif atau belum 4.3.0 (muncul catatan di editor), atau Link Mode belum `Media Popup`, atau halaman masih ke-cache |
| Video nggak langsung main (cuma muncul player) | Kebijakan autoplay browser (Safari / iOS paling ketat). Itu bukan error: klik tombol play. Iframe YouTube/Vimeo punya autoplay sendiri, file video diputar lewat skrip |
| Video YouTube error / "video unavailable" di popup | Pemilik video mematikan embed, atau header `Referrer-Policy: no-referrer` di server memblok embed YouTube. Cek link di tab biasa |
| URL TikTok / Instagram cuma menampilkan tombol **Buka video** | Memang begitu: dua layanan itu nggak bisa di-embed tanpa panggilan keluar. Itu fallback yang disengaja |
| Popup foto muncul padahal post punya video | Video nggak terdeteksi. Cek urutan di bagian 5.1: video di dalam widget Elementor nggak terbaca, `<iframe>` host lain diabaikan, URL harus YouTube/Vimeo/file `.mp4` dst. Cara paling pasti: isi External Video URL |
| Popup video muncul di berita biasa | Post itu punya video/embed di isinya, dan jenis popup ditentukan dari isi post. Kalau nggak mau, jangan pakai Media Popup di baris/widget itu |
| Tanggal tampil "October" | Site Language belum Bahasa Indonesia |
| Gaya popup nggak berubah setelah ubah Style | Regenerate CSS Elementor + hard refresh |
| Tombol tutup kepotong | Ada CSS tema yang memberi `overflow: hidden` ke `.wptg-modal`/`.wptg-modal-panel`. Kabari lewat Console/Elements |

---

## 8. Hal yang perlu lu tahu

- Endpoint `wptg_get_media_modal` bisa dipanggil pengunjung (tanpa login), cuma-baca, dengan nonce. Hanya post **Published** dari post type publik yang dilayani, post berpassword ditolak, markup player/foto melewati `wp_kses` dengan daftar atribut yang ketat.
- Jenis popup (video / foto / post) dihitung dari isi saat diklik, jadi mengubah External Video URL langsung berlaku tanpa regenerate apa pun (kecuali halaman di-cache).
- Control Style lama di **Modal / Lightbox** (Backdrop, Panel, Close Button) tetap untuk modal lama dan sebenarnya jarang berpengaruh, karena modal ditempel ke `<body>` di luar widget (keterbatasan lama, nggak diubah).
- `composer.lock` di root repo masih mencatat versi lama kedua plugin (itu sudah begitu sebelum perubahan ini, `content-hash` juga basi). Refresh di container kalau perlu: `composer update --lock`.

---

## File yang berubah

- `plugins-custom/wp-taxonomy-grid/wp-taxonomy-grid.php` (versi + konstanta `WPTG_VERSION`, enqueue/localize `wptg-media-popup`, `wptg_normalize_click_behavior()`, `data-wptg-media` di `wptg_build_item_attrs()`, renderer list thumbnail disederhanakan)
- `plugins-custom/wp-taxonomy-grid/includes/media-popup.php` (baru)
- `plugins-custom/wp-taxonomy-grid/includes/class-elementor-widget.php` (opsi `Media Popup (video / photo)`, control Style popup)
- `plugins-custom/wp-taxonomy-grid/includes/class-video-meta-box.php` (deskripsi meta box)
- `plugins-custom/wp-taxonomy-grid/assets/js/modal.js`, `assets/js/media-popup.js` (baru), `assets/js/taxonomy-grid.js`
- `plugins-custom/wp-taxonomy-grid/assets/css/taxonomy-grid.css`
- `plugins-custom/wp-taxonomy-grid/composer.json`, `readme.txt`
- `plugins-custom/wp-content-grid/wp-content-grid.php`, `includes/class-elementor-widget.php`, `assets/css/content-grid.css`, `composer.json`, `readme.txt`
- Catatan kerja: `.agents/tasks/media-popup/` (dokumen ini, `test-logic.js`, `check-files.js`) dan `.agents/tasks/halaman-berita/PANDUAN.md`
