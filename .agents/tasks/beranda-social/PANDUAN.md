# Panduan: feed sosial media otomatis di widget "Social Media Showcase" (v1.1.0)

Panduan step by step buat nampilin feed Instagram / Facebook / X / YouTube / TikTok yang update sendiri di halaman Beranda. Cara kerjanya hybrid (opsi A):

- **Widget kita** (`wp-social-showcase`) tetap pegang tab ikon + tombol "Open Account".
- **Plugin feed pihak ketiga** (Smash Balloon / Feed Them Social) yang konek ke akun dan narik postingan. Isi panel tiap platform = shortcode dari plugin itu.
- Nggak ada integrasi API langsung di kode kita. Token/OAuth diurus plugin feed.

> **Status jujur:** kode widget v1.1.0 belum pernah dites di browser / Elementor, dan PHP-nya belum di-lint (host nggak punya `php`). UI plugin feed bisa berubah, jadi langkah di bagian 3 gue tandai **(cek manual)** kalau cuma garis besar. Versi plugin diambil dari API wordpress.org; ketersediaan di WPackagist belum bisa gue cek dari sini, jadi kalau `composer update` bilang versi nggak ketemu, lihat catatan di bagian 2.

Cara pakai: ganti `- [ ]` jadi `- [x]` tiap selesai satu langkah.

---

## 0. Putuskan platform dulu

Platform yang dipakai belum dikonfirmasi. Pilih dulu, jangan pasang semua plugin kalau nggak perlu (tiap plugin nambah beban halaman).

- [ ] Instagram?
- [ ] Facebook (Page)?
- [ ] X (Twitter)?
- [ ] YouTube?
- [ ] TikTok?

Cuma platform yang dicentang yang perlu plugin feed. Sisanya boleh tetap **Manual Gallery** (upload gambar sendiri), mode lama tetap jalan.

---

## 1. Platform -> plugin yang disarankan -> batasan versi gratis

Versi di tabel = "Stable tag" terbaru dari API wordpress.org (dicek saat riset; cek lagi sebelum pasang).

| Platform | Plugin (slug WordPress.org / WPackagist) | Versi stabil | Shortcode | Batasan versi gratis | Kebutuhan akun |
|---|---|---|---|---|---|
| Instagram | Smash Balloon Social Photo Feed: `instagram-feed` | 6.15.0 | `[instagram-feed feed=1]` | Hashtag feed, tagged feed, moderasi, lightbox video dll. ada di Pro. Cuma bisa nampilin akun yang lo miliki / punya akses admin. | Akun **Business atau Creator**. Mode "Business Basic" nggak butuh Facebook Page; "Business Advanced" (hashtag/mention, multi akun) butuh Facebook Page yang ditautkan. Akun Personal sudah nggak bisa konek sejak Meta mematikan Basic Display API (Des 2024). Sumber Smash Balloon agak beda-beda soal ini -> **(cek manual)** di layar connect plugin. |
| Facebook | Smash Balloon Social Post Feed: `custom-facebook-feed` | 4.15.0 | `[custom-facebook-feed feed=1]` | Deskripsi wordpress.org: versi gratis cuma teks + link postingan; foto, video, komentar, like dll. butuh Pro. **(cek manual)** apakah batasan ini masih sama di versi terbaru. | **Facebook Page** (atau Group) yang lo kelola. Profil pribadi hampir nggak bisa ditampilkan (batasan Meta). |
| X (Twitter) | Custom Twitter Feeds: `custom-twitter-feeds` | 2.11.0 | `[custom-twitter-feeds feed=1]` | Gratis: 1 user timeline. Multi user, foto/video/GIF di popup, hashtag/search ada di Pro. Karena harga API X, Pro membatasi jumlah refresh per hari. **(cek manual)** cara connect-nya sekarang (FAQ lama menyebut 4 token dari app X developer). | Akun X; bisa butuh app/kredensial developer X **(cek manual)**. |
| YouTube | Feeds for YouTube: `feeds-for-youtube` | 2.11.0 | `[youtube-feed feed=1]` | Layout bawaan terbatas; live stream API, search feed, fitur lanjutan ada di Pro. | Login Google (tombol connect plugin) atau API key sendiri dari Google Cloud (opsional). |
| TikTok | Smash Balloon Feeds for TikTok: `feeds-for-tiktok` | 1.9.0 | `[sbtt-tiktok feed=1]` | Gratis: grid video akun yang konek + popup lightbox. Multi akun, layout lain, filter ada di Pro. | Akun TikTok milik lo (authorize lewat connect.smashballoon.com). |
| Semua-dalam-satu (alternatif) | Feed Them Social: `feed-them-social` | 4.4.3 | salin dari editor feed plugin (bentuk persisnya **(cek manual)**) | Deskripsi menyebut Instagram, Facebook, TikTok, YouTube. **X tidak disebut.** Ekstensi premium untuk fitur tambahan (hashtag IG, TikTok, combined streams, dll.); **(cek manual)** apakah TikTok bisa di versi gratis. | Tergantung platform (token via tombol di plugin). |

Saran gue: **Smash Balloon per platform** (satu plugin kecil per platform, shortcode seragam `feed=ID`, tiap feed punya ID). Pakai **Feed Them Social** kalau mau satu plugin saja dan platformnya cuma Instagram/Facebook/YouTube.

Sumber:
- [wordpress.org Instagram Feed](https://wordpress.org/plugins/instagram-feed/), [Facebook](https://wordpress.org/plugins/custom-facebook-feed/), [X](https://wordpress.org/plugins/custom-twitter-feeds/), [YouTube](https://wordpress.org/plugins/feeds-for-youtube/), [TikTok](https://wordpress.org/plugins/feeds-for-tiktok/), [Feed Them Social](https://wordpress.org/plugins/feed-them-social/) (versi dari `api.wordpress.org/plugins/info/1.0/<slug>.json`)
- [Ringkasan produk + bentuk shortcode Smash Balloon](https://smashballoon.com/llms.txt)
- [Soal Instagram Basic Display API dan tipe akun](https://smashballoon.com/instagram-is-shutting-down-basic-display-api-continue-displaying-instagram-feeds-on-your-site/)
- [Setup TikTok gratis](https://smashballoon.com/doc/how-to-create-a-free-tiktok-feed/)

---

## 2. Pasang plugin feed lewat composer

Project ini pakai Bedrock: plugin dipasang lewat composer, bukan klik "Add New" di wp-admin.

### Catatan teknis (WAJIB baca, biar nggak bingung error)

Di dev, `composer.json` di-bind-mount **satu file, read-only** ke container (`./composer.json:/app/composer.json:ro` di `docker-compose.dev.yml`). Akibatnya:

1. `docker compose exec app composer require ...` **gagal** karena file-nya read-only.
2. Edit `composer.json` di host **nggak langsung kebaca** container. Mount satu file nempel ke inode file lama, jadi setelah file diganti di host, container masih lihat versi lama sampai container dibuat ulang: `docker compose up -d --force-recreate app`.

Makanya alurnya begini (semua di terminal WSL, folder repo):

```bash
cd /mnt/c/projects/imigrasi-wp
```

- [ ] **2.0 Sync widget v1.1.0 ke container** (plugin custom di-copy, bukan symlink):

```bash
./scripts/sync-plugin.sh wp-social-showcase
```

- [ ] **2.1 Edit `composer.json` di host** (editor biasa). Tambah baris di blok `"require"` untuk plugin yang lo pilih saja. Versi harus **exact** (aturan README: pin exact, bukan `^`):

```json
"wpackagist-plugin/instagram-feed": "6.15.0",
"wpackagist-plugin/custom-facebook-feed": "4.15.0",
"wpackagist-plugin/custom-twitter-feeds": "2.11.0",
"wpackagist-plugin/feeds-for-youtube": "2.11.0",
"wpackagist-plugin/feeds-for-tiktok": "1.9.0",
"wpackagist-plugin/feed-them-social": "4.4.3"
```

Pakai yang perlu saja, jangan lupa koma antar baris dan jaga urutan alfabet (`sort-packages: true`).

- [ ] **2.2 Buat ulang container** supaya `composer.json` baru kebaca:

```bash
docker compose up -d --force-recreate app
```

- [ ] **2.3 Install** (ganti `<slug>`; boleh beberapa slug sekaligus):

```bash
docker compose exec app composer update wpackagist-plugin/<slug> --no-interaction
# contoh: docker compose exec app composer update wpackagist-plugin/instagram-feed wpackagist-plugin/feeds-for-youtube --no-interaction
```

Kalau error "could not be found" untuk versi tertentu: berarti versi itu belum ada di mirror WPackagist (atau baru rilis). Cek daftar versi di wordpress.org, turunkan ke versi stabil sebelumnya yang ada, ulangi dari 2.1.

- [ ] **2.4 Aktifkan plugin:**

```bash
docker compose exec -u www-data app wp plugin activate <slug> --path=/app/web/wp
```

- [ ] **2.5 Salin `composer.lock` dari container ke host** (lock dibuat di dalam container, bukan di host):

```bash
docker compose cp app:/app/composer.lock ./composer.lock
```

- [ ] **2.6 Cek `git status`**: yang berubah harus `composer.json` + `composer.lock`. Commit dikerjakan manual oleh lo, saran pesan: `add: feed plugins untuk social showcase`.

---

## 3. Sambung akun & ambil shortcode (garis besar, UI bisa berubah)

Aturan umum semua plugin: **bikin feed dulu di menu plugin -> copy shortcode dari tombol Embed / Shortcode**. Jangan ngarang atribut sendiri; di Smash Balloon versi baru semua pengaturan tampilan ada di feed-nya dan shortcode cuma bawa ID (`feed=1`).

### Instagram (`instagram-feed`)
- [ ] Menu **Instagram Feed** > Add New / All Feeds (nama menu **(cek manual)**).
- [ ] Add Source -> pilih tipe akun. Akun Business/Creator tanpa Facebook: **Business Basic**. Butuh hashtag/multi akun: **Business Advanced** (butuh Facebook Page).
- [ ] Authorize di Instagram, konfirmasi domain, lalu atur layout (Grid) dan jumlah postingan.
- [ ] Klik **Embed** -> copy shortcode, contoh `[instagram-feed feed=1]`.

### Facebook (`custom-facebook-feed`)
- [ ] Menu **Facebook Feed** > Add New (**cek manual**).
- [ ] Add Source -> login Facebook, pilih Page yang lo kelola, beri izin.
- [ ] Atur feed (ingat: versi gratis cuma teks + link, **cek manual**).
- [ ] Embed -> copy shortcode, contoh `[custom-facebook-feed feed=1]`.

### X / Twitter (`custom-twitter-feeds`)
- [ ] Menu **Twitter Feeds** (**cek manual**) -> buat feed user timeline akun lo.
- [ ] Ikuti layar connect plugin (bisa butuh kredensial app developer X, **cek manual**).
- [ ] Copy shortcode, contoh `[custom-twitter-feeds feed=1]`.

### YouTube (`feeds-for-youtube`)
- [ ] Menu **YouTube Feed** (**cek manual**) -> klik tombol login YouTube (atau masukkan API key sendiri kalau mau).
- [ ] Buat feed channel lewat customizer.
- [ ] Copy shortcode, contoh `[youtube-feed feed=1]`.

### TikTok (`feeds-for-tiktok`)
- [ ] Menu **TikTok Feeds** > All Feeds > Add new.
- [ ] Add Source -> **Connect with TikTok** -> Authorize -> konfirmasi domain -> pilih akun.
- [ ] Pilih template default, atur feed, klik **Embed**.
- [ ] Copy shortcode, contoh `[sbtt-tiktok feed=1]`.

### Feed Them Social (`feed-them-social`), kalau dipakai
- [ ] Buat feed di menu plugin, copy shortcode dari kanan atas editor feed. Bentuk persisnya **(cek manual)**.

Catat hasilnya:

| Platform | Shortcode | Sudah dites di halaman biasa? |
|---|---|---|
| Instagram | | - [ ] |
| Facebook | | - [ ] |
| X | | - [ ] |
| YouTube | | - [ ] |
| TikTok | | - [ ] |

Tips: tes dulu tiap shortcode di halaman draft biasa. Kalau di situ feed-nya sudah muncul, baru masukin ke widget.

---

## 4. Isi widget di Elementor

- [ ] Buka halaman Beranda di Elementor, tambah/pilih widget **Social Media Showcase** (kategori "Social Showcase").
- [ ] **Content > Platforms**, per platform (klik item di repeater):
  - [ ] **Icon** dan **Label** (mis. Instagram).
  - [ ] **Account URL** = alamat akun asli (dipakai tombol "Open Account"), mis. `https://instagram.com/akunlo`.
  - [ ] **Feed Source** = **Shortcode (from a feed plugin)**.
  - [ ] **Feed Shortcode** = tempel shortcode dari bagian 3, mis. `[instagram-feed feed=1]`. Harus **hanya shortcode** (diawali `[`, diakhiri `]`, tanpa teks/HTML lain), dan plugin-nya harus aktif.
- [ ] Platform yang nggak pakai plugin feed: biarkan **Manual Gallery** dan upload gambarnya.
- [ ] Urutan tab = urutan item di repeater (item pertama = tab aktif default). Drag item buat ubah urutan. Taruh platform yang paling penting / feed paling ringan di urutan pertama.
- [ ] Hapus item bawaan yang nggak dipakai (default ada Instagram, X, Facebook, TikTok).
- [ ] **General > "Open Account" Button Text** (default "Buka").

Catatan:
- Pindah ke Shortcode **tidak menghapus** gambar galeri lama; cuma disembunyikan. Balik ke Manual Gallery, gambar muncul lagi.
- Widget lama yang belum pernah disentuh tetap tampil sebagai galeri manual persis seperti sebelumnya.
- Di editor Elementor, feed kadang tampil berantakan / nggak interaktif karena JS plugin feed belum jalan. Nilai hasil akhir di preview atau halaman publik.
- Kalau shortcode nggak valid atau feed kosong, user yang login dan bisa edit akan lihat catatan kecil ("Shortcode tidak valid" / "Feed kosong: cek koneksi akun di plugin feed"). Pengunjung nggak lihat apa-apa.

---

## 5. Styling: apa di widget, apa di plugin feed

| Yang mau diubah | Dimana |
|---|---|
| Ikon tab (ukuran, warna normal/aktif, background, gap) | Widget: **Style > Tabs** |
| Tombol Open Account (font, warna, padding, radius) | Widget: **Style > Open Account Button** |
| Kotak pembungkus feed: padding, background, border radius, min height | Widget: **Style > Feed Container** |
| Kolom, jumlah postingan, kartu, warna teks, tombol load more, header, ukuran gambar feed | **Plugin feed** (customizer feed-nya), bukan di widget |
| Gallery Grid (kolom, gap, radius gambar) | Widget: **Style > Gallery Grid**, hanya buat mode **Manual Gallery** |

Widget **tidak** men-style isi plugin feed. Itu disengaja: kalau dipaksa pakai CSS dari luar, gampang rusak tiap plugin update. Kalau mentok perlu CSS khusus, pakai fitur Custom CSS di plugin feed-nya.

Style > Feed Container selalu tampil di panel Style (Elementor nggak bisa menyembunyikannya per item repeater), tapi efeknya cuma ke platform yang Feed Source-nya Shortcode.

---

## 6. Tes & troubleshooting

### Checklist tes
- [ ] Halaman publik (bukan editor): tab pertama tampil lengkap.
- [ ] Klik tiap tab: panel ganti, tombol "Open Account" ikut ganti URL-nya.
- [ ] **Pindah tab lalu cek layout feed**: kolom/masonry/carousel di tab yang tadinya tersembunyi harus rapi (bukan menumpuk satu kolom, bukan lebar 0). Widget sudah memicu event `resize` tiap pindah tab supaya plugin feed menghitung ulang.
- [ ] Mobile (DevTools mode responsif lebar ~375 px): ikon tab nggak overflow, feed nggak keluar dari layar.
- [ ] Login sebagai admin vs buka di jendela incognito: catatan error kecil cuma muncul untuk admin.
- [ ] Cek kecepatan halaman (lihat bagian 7 tentang beban).

### Troubleshooting

| Gejala | Kemungkinan penyebab & solusi |
|---|---|
| "Shortcode tidak valid" | Ada teks/HTML lain di kolom, tanda kurung kurang, atau plugin feed belum aktif (shortcode belum terdaftar). Pastikan cuma `[...]` dan plugin aktif (`wp plugin list`). |
| "Feed kosong: cek koneksi akun di plugin feed" | Akun belum tersambung, token kadaluarsa, feed ID salah, atau akun belum punya postingan. Buka menu plugin feed, cek status sumber/akun (biasanya ada indikator error/Reconnect). |
| Feed hilang / berhenti update | Token kadaluarsa atau izin dicabut (mis. ganti password / ubah tipe akun IG). Reconnect source di plugin. |
| Feed tidak update walau sudah post baru | Cache plugin feed (default sekitar 1 jam, bisa diatur) dan/atau cache halaman (WP Super Cache). Clear cache plugin feed + hapus cache halaman. |
| Layout feed rusak hanya di tab yang tersembunyi | Pindah tab sekali lagi; kalau tetap rusak, plugin feed itu mungkin nggak mendengarkan `resize`. Taruh platform itu di tab pertama, atau laporkan supaya dicari workaround. |
| Feed Instagram kosong padahal sudah konek | Akun masih Personal. Ubah ke Business/Creator di app Instagram lalu reconnect. |
| Facebook cuma menampilkan teks | Batasan versi gratis (lihat tabel bagian 1). |
| Muncul tulisan shortcode mentah di halaman | Plugin feed nonaktif, atau shortcode salah ketik. |
| `composer update` gagal "read-only" / baris baru nggak kebaca | Ulangi dari catatan teknis bagian 2: edit di host, lalu `--force-recreate app`. |

---

## 7. Keamanan & privasi (singkat)

- **Token jangan ditaruh di repo atau di `.env` yang ke-commit.** Plugin feed menyimpan token di database WordPress. Di repo cukup `composer.json`/`composer.lock` (nama + versi plugin), bukan kredensial.
- Kalau dipakai API key sendiri (mis. YouTube), simpan di pengaturan plugin, dan batasi key di Google Cloud ke domain situs.
- Dump database (`scripts/db-dump.sh`) berisi token tersimpan; file `*.sql` sudah di-`.gitignore`, jangan dikirim sembarangan.
- **Privasi / GDPR:** feed memuat gambar/video dari Meta, Google, X, TikTok yang bisa memasang cookie / mengirim IP pengunjung. Kalau pengunjung dari wilayah yang diatur GDPR, pasang cookie notice dan cek opsi GDPR mode di plugin feed (Smash Balloon menyediakan opsi yang menyajikan media dari server sendiri; **cek manual** namanya di versi lo).
- **Performa:** semua panel dirender sekaligus saat halaman dimuat (tab non-aktif hanya disembunyikan). Makin banyak platform, makin banyak request. Pakai platform seperlunya.
- Setelah `composer update`, rutin cek update plugin feed (security patch). Pin exact berarti update harus manual.

---

## 8. Ide masa depan (BELUM dibangun)

- Feed YouTube tanpa API key lewat RSS channel (`https://www.youtube.com/feeds/videos.xml?channel_id=...`). Cuma ide: kalau nanti YouTube saja yang dibutuhkan dan lo mau lepas dari plugin feed, bisa dibuat widget kecil. Sekarang **tidak** dikerjakan.

---

## Ringkasan file yang berubah (buat commit nanti)

- `plugins-custom/wp-social-showcase/` (kode widget v1.1.0, readme, composer.json)
- `README.md` (deskripsi plugin)
- `.agents/tasks/beranda-social/PANDUAN.md` (file ini)
- Nanti, setelah lo pilih plugin: `composer.json` + `composer.lock` di root

Label control baru di editor: **Feed Source**, **Feed Shortcode**, section Style **Feed Container** (Padding, Background Color, Border Radius, Min Height).
