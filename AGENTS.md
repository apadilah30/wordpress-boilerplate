# Agent & Contributor Workflow Rules

Aturan ini berlaku untuk **agent AI** (Cursor, Claude, Copilot, dll.) maupun **manusia** yang bekerja di repo ini. Baca sebelum mulai.

---

## 1. Jangan commit otomatis

- Agent **TIDAK BOLEH** menjalankan `git add`, `git commit`, atau `git push` sendiri.
- Agent boleh:
  - Menjelaskan file mana saja yang berubah.
  - Menyarankan pesan commit (format `type: subject` — mis. `add: elementor-pro plugin` atau `fix: caddy rate limit rule`).
- User yang inisiasi commit secara manual.

## 2. Rekap progress harian ke `daily/YYYY-MM-DD.md`

- Setiap sesi kerja, agent bikin/append file `daily/YYYY-MM-DD.md` (tanggal lokal) sesuai [`daily/TEMPLATE.md`](daily/TEMPLATE.md).
- Format singkat: apa yang dikerjakan, keputusan penting, blocker, next step.
- Folder `daily/` di-`.gitignore` (personal notes, bukan artifact produk). Yang tetap tracked: `daily/TEMPLATE.md` dan `daily/.gitkeep`.

## 3. Ramah pemula

- Hindari pattern advanced tanpa penjelasan.
- Kalau pakai konsep advanced (mis. FrankenPHP worker mode, Docker healthcheck exit codes), sediakan mode simple sebagai default + dokumentasi lengkap cara upgrade.
- Semua script/config penting harus punya komentar penjelasan singkat di header.

## 4. Jangan overkill — tapi tetap scalable

Skip stack yang belum jelas dibutuhkan:

- Prometheus, Grafana, Loki
- Fail2ban (Wordfence + Caddy rate-limit sudah cover)
- Kubernetes, Helm
- Traefik / Nginx reverse proxy di depan FrankenPHP (Caddy built-in cukup)

Pilih tool yang bisa "grow with the project":

- Compose bisa migrasi ke Swarm/K8s nanti (`kompose convert`)
- Image portable, deploy dimana saja
- Backup local bisa tambah handler S3/rclone belakangan

## 5. DRY / KISS / Clean Code

### DRY (Don't Repeat Yourself)

- Bash scripts share fungsi umum di [`scripts/lib/common.sh`](scripts/lib/common.sh) (logging, error handler, wait-for-service).
- Docker Compose pakai `x-*` extension fields + YAML anchors untuk service definition umum.
- Dockerfile pakai multi-stage untuk reuse layer.
- Env vars di-interpolate, tidak di-hardcode.

### KISS (Keep It Simple, Stupid)

- 1 file = 1 tanggung jawab.
- Tidak ada abstraksi yang belum terbukti dipakai minimal 3x.
- Folder structure flat sebisa mungkin.
- Prefer solusi bawaan Docker/PHP/WP dibanding tool eksternal.

### Clean Code

- **PHP**: PSR-12, `declare(strict_types=1);`, no `@` error suppression.
- **Bash**: `set -euo pipefail`, shellcheck-clean, quoted variables (`"$var"` bukan `$var`).
- **Naming**: self-documenting. `$backup_retention_days` bukan `$d`. `wpba_render_settings_page()` bukan `page()`.
- **Komentar**: cuma untuk "kenapa" bukan "apa". Kode yang jelas tidak butuh komentar.
- **Formatting**: konsisten. Tab vs space, quote style, line length — ikuti file yang sudah ada.

---

## Alur kerja standar tiap sesi

1. Baca task dari user.
2. Buka [`daily/YYYY-MM-DD.md`](daily/) (buat kalau belum ada, copy dari `TEMPLATE.md`).
3. Kerjakan task.
4. Update `daily/YYYY-MM-DD.md` dengan yang barusan dikerjakan.
5. Kasih summary ke user + list file yang berubah + saran pesan commit.
6. **STOP** — user yang commit.
