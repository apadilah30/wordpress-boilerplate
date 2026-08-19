# WordPress Boilerplate — Bedrock + FrankenPHP + Docker

Boilerplate WordPress produksi yang **ramah pemula tapi scalable**. Fokus: keamanan, usability, deploy mudah.

- **Runtime**: [FrankenPHP](https://frankenphp.dev/) (PHP 8.3, Caddy 2, HTTP/3, auto-HTTPS)
- **WP framework**: [Roots Bedrock](https://roots.io/bedrock/) — WordPress managed via Composer
- **Multi-stage env**: `development` / `staging` / `production`
- **Auto backup**: MariaDB dump harian + rotasi 30d/8w/12m + uploads weekly snapshot
- **CI/CD**: GitHub Actions → GHCR → SSH deploy ke VPS, dengan pre-deploy backup & auto-rollback
- **Plugin update safety**: automated weekly PR + smoke test + version pinning
- **Security hardening**: berlapis (app, web, container, DB)

---

## Daftar Isi

1. [Prasyarat](#prasyarat)
2. [Setup dev cepat](#setup-dev-cepat)
3. [Struktur repo](#struktur-repo)
4. [Multi-stage environment](#multi-stage-environment)
5. [Menambah plugin](#menambah-plugin)
6. [Deploy ke staging & production](#deploy-ke-staging--production)
7. [Backup & restore](#backup--restore)
8. [Migrasi antar server](#migrasi-antar-server)
9. [Security hardening](#security-hardening)
10. [Upgrade path](#upgrade-path)
11. [Troubleshooting](#troubleshooting)
12. [Workflow rules](#workflow-rules)

---

## Prasyarat

**Local dev:**
- Docker Desktop (Windows/Mac) atau Docker Engine + Compose plugin (Linux)
- Git

**Production VPS:**
- Ubuntu 22.04+ / Debian 12+ (atau distro Linux modern lainnya)
- Docker Engine + Compose v2
- Port 80 & 443 terbuka
- Domain di-point ke IP server (untuk auto-HTTPS)

---

## Setup dev cepat

```bash
git clone https://github.com/OWNER/wordpress-boilerplate.git
cd wordpress-boilerplate

# 1. Copy env & generate salts baru dari https://roots.io/salts.html
cp .env.example .env
# Edit .env — minimal isi: DB_PASSWORD, DB_ROOT_PASSWORD, semua *_KEY dan *_SALT

# 2. Build & start
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build

# 3. Install WordPress (first boot)
docker compose exec -u www-data app wp core install \
    --path=/app/web/wp \
    --url=http://localhost \
    --title="My Site" \
    --admin_user=owner \
    --admin_password=changeme \
    --admin_email=you@example.com \
    --skip-email

# 4. Aktifkan starter theme + plugin
docker compose exec -u www-data app wp theme activate starter --path=/app/web/wp
docker compose exec -u www-data app wp plugin activate elementor --path=/app/web/wp
```

Buka http://localhost — WordPress siap.

Login admin: http://localhost/wp/wp-login.php (atau slug custom kalau lu setup WPS Hide Login).

---

## Struktur repo

```
.
├── .docker/                    Docker build assets
│   ├── frankenphp/             Dockerfile multi-stage, Caddyfile, php.ini, entrypoint
│   ├── mariadb/conf.d/         MariaDB hardening config
│   └── backup/                 Backup service (Alpine + cron + mysqldump)
├── .github/workflows/          CI, deploy, plugin-update
├── config/                     Bedrock config (application.php + environments/)
├── web/                        Webroot
│   ├── app/                    Bedrock "wp-content" replacement
│   │   ├── mu-plugins/         security.php (hardening) + register-theme-dirs.php
│   │   ├── themes/starter/     Minimal starter theme
│   │   └── plugins/            (managed by composer — gitignored)
│   ├── wp/                     WP core (managed by composer — gitignored)
│   ├── index.php, wp-config.php
├── plugins-custom/             Plugin custom (non-WPackagist), di-commit
│   └── wp-boilerplate-announcement/   Reference plugin
├── scripts/                    Helper scripts
│   ├── lib/common.sh           Shared bash functions (DRY)
│   ├── deploy.sh               Deploy handler (dijalankan di server)
│   ├── rollback.sh             Rollback ke tag sebelumnya
│   ├── migrate.sh              Export/import site
│   ├── restore-backup.sh       Restore DB dari backup file
│   ├── add-custom-plugin.sh    Helper add plugin custom
│   └── smoke-test.sh           Boot image + curl healthz (CI)
├── daily/                      Rekap progress harian (GITIGNORED, kecuali TEMPLATE)
├── docker-compose.yml          Base compose
├── docker-compose.dev.yml      Dev overrides
├── docker-compose.staging.yml  Staging overrides
├── docker-compose.prod.yml     Production overrides (hardened)
├── composer.json               WordPress core + plugin dependencies
├── AGENTS.md                   Aturan workflow (baca!)
└── README.md
```

---

## Multi-stage environment

Boilerplate ini punya 3 environment dengan konfigurasi berbeda:

| Env         | `WP_ENV`      | `WP_DEBUG` | `DISALLOW_FILE_MODS` | Compose file                     |
|-------------|---------------|------------|----------------------|----------------------------------|
| Development | `development` | true       | false                | `docker-compose.dev.yml`         |
| Staging     | `staging`     | true (log) | true                 | `docker-compose.staging.yml`     |
| Production  | `production`  | false      | true                 | `docker-compose.prod.yml`        |

Semua share `docker-compose.yml` sebagai base.

```bash
# Dev
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Staging (di server staging)
docker compose --env-file .env.staging \
    -f docker-compose.yml -f docker-compose.staging.yml up -d

# Production (di server prod)
docker compose --env-file .env.production \
    -f docker-compose.yml -f docker-compose.prod.yml up -d
```

---

## Menambah plugin

### Plugin dari WordPress.org (WPackagist)

```bash
composer require wpackagist-plugin/PLUGIN-SLUG:VERSION
docker compose exec -u www-data app wp plugin activate PLUGIN-SLUG --path=/app/web/wp

# git status → git add composer.json composer.lock → git commit → git push
```

Selalu **pin exact version** (`8.1.2`, bukan `^8.1`) untuk reproducibility.

### Plugin custom / premium (non-WPackagist)

Contoh: Elementor Pro, plugin internal, plugin dari client.

**Opsi 1 — pakai helper:**

```bash
./scripts/add-custom-plugin.sh ~/Downloads/elementor-pro.zip --vendor=premium --version=3.23.0
```

**Opsi 2 — manual:**

```bash
# Copy plugin ke plugins-custom/
mkdir -p plugins-custom/elementor-pro
cp -r ~/Downloads/elementor-pro/* plugins-custom/elementor-pro/

# Buat composer.json minimal
cat > plugins-custom/elementor-pro/composer.json <<'EOF'
{
    "name": "premium/elementor-pro",
    "type": "wordpress-plugin",
    "version": "3.23.0",
    "require": { "composer/installers": "^2.0" }
}
EOF

# Register di composer.json root
composer require premium/elementor-pro:*

# Commit
git add plugins-custom/elementor-pro composer.json composer.lock
git commit -m "add: Elementor Pro 3.23.0"
git push  # auto-deploy via CI
```

**Kenapa TIDAK upload manual via WP admin di prod:**
- Di prod `DISALLOW_FILE_MODS=true` (upload diblok — attack vector major)
- Kalaupun bisa, upload manual bakal hilang saat next deploy (image rebuild)
- Composer path repo = reproducible, version-controlled, rollback-able

### Reference plugin

Boilerplate include reference plugin `wp-boilerplate-announcement` (folder `plugins-custom/wp-boilerplate-announcement/`) sebagai template siap-copy. Non-active by default.

```bash
# Aktifkan (di dev):
docker compose exec -u www-data app wp plugin activate wp-boilerplate-announcement --path=/app/web/wp

# Config di: WP admin → Settings → Announcement Banner
```

---

## Deploy ke staging & production

### First-time server setup

```bash
# Di VPS:
ssh youruser@your-server
mkdir -p /srv/wordpress-boilerplate
cd /srv/wordpress-boilerplate
git clone https://github.com/OWNER/wordpress-boilerplate.git .

# Copy env & set values (jangan pakai .env dev!)
cp .env.example .env.production
nano .env.production
chmod 600 .env.production   # WAJIB — file ini punya semua secret

# Set SERVER_NAME ke domain lu (untuk auto-HTTPS Caddy)
# SERVER_NAME=example.com

# Login GHCR (butuh Personal Access Token dengan scope 'read:packages')
echo "$GHCR_TOKEN" | docker login ghcr.io -u USERNAME --password-stdin

# First deploy (pull tag latest)
DEPLOY_IMAGE=ghcr.io/OWNER/REPO DEPLOY_TAG=latest ./scripts/deploy.sh
```

### GitHub Actions setup (auto-deploy tiap push)

Di GitHub repo → Settings → Secrets and variables → Actions, tambah:

| Secret            | Isi                                                          |
|-------------------|--------------------------------------------------------------|
| `DEPLOY_SSH_KEY`  | Private SSH key (bukan yang ada di GitHub — bikin baru)      |
| `DEPLOY_HOST`     | IP atau hostname VPS lu                                       |
| `DEPLOY_USER`     | User SSH (biasanya `deploy` atau `ubuntu`)                    |
| `DEPLOY_PATH`     | Path repo di server (mis. `/srv/wordpress-boilerplate`)       |

Di Settings → Environments, buat 2 environment:

- `staging` — no protection rules (deploy otomatis dari push ke `main`)
- `production` — set required reviewers (deploy manual approval, trigger dari tag `v*`)

Setelah itu:

```bash
git push origin main            # → auto deploy ke staging
git tag v1.0.0 && git push --tags   # → deploy ke production (setelah approval)
```

### Rollback

```bash
# Di server:
cd /srv/wordpress-boilerplate

# Cek tag sebelumnya (deploy.sh otomatis simpan)
cat .last-deployed-tag.previous

# Rollback
./scripts/rollback.sh sha-abc123
```

---

## Backup & restore

### Auto backup (jalan otomatis)

Backup service jalan sebagai container di `docker-compose.yml`. Default:
- **DB dump harian** jam 02:00 UTC (via `BACKUP_CRON` env)
- **Retensi**: 30 daily + 8 weekly + 12 monthly
- **Uploads snapshot**: mingguan
- **Health marker**: `backups/last_success` di-update tiap sukses

### Cek status backup

```bash
docker compose exec backup ls -lh /backups/db/daily
docker compose exec backup cat /backups/last_success
```

### Restore manual

```bash
# List semua backup
./scripts/restore-backup.sh --list

# Restore latest daily backup
./scripts/restore-backup.sh --latest

# Restore file spesifik
./scripts/restore-backup.sh /backups/db/daily/db-2026-08-18_020001.sql.gz
```

Script otomatis:
1. Take pre-restore snapshot (safety net)
2. Konfirmasi user (skip dengan `--yes`)
3. Restore DB via `mysql` pipe
4. Flush WP cache + rewrite rules

---

## Migrasi antar server

Boilerplate portable — pindah VPS = git clone + import archive.

```bash
# --- DI SERVER LAMA ---
./scripts/migrate.sh export migrasi-2026-08-19.tar.gz
scp migrasi-2026-08-19.tar.gz newserver:/tmp/

# --- DI SERVER BARU ---
git clone https://github.com/OWNER/wordpress-boilerplate.git /srv/wp
cd /srv/wp
cp .env.example .env.production   # isi nilai baru
chmod 600 .env.production
docker compose --env-file .env.production \
    -f docker-compose.yml -f docker-compose.prod.yml up -d

# Import + auto search-replace URL
./scripts/migrate.sh import /tmp/migrasi-2026-08-19.tar.gz \
    --from-url=https://old-domain.com \
    --to-url=https://new-domain.com
```

---

## Security hardening

Berlapis, defense in depth:

### 1. App layer — `web/app/mu-plugins/security.php`

- XML-RPC disabled (`xmlrpc_enabled` filter + method blocking)
- User enumeration blocked (`/?author=N` redirect + `/wp-json/wp/v2/users` blocked untuk non-logged in)
- Generator meta hidden (versi WP tidak leak)
- Login errors generic (tidak leak "user tidak ada" vs "password salah")
- Illegal usernames: `admin`, `administrator`, `root`, `test`
- PHP MIME upload blocked
- Force SSL admin di non-dev
- Trusted proxy handling (`HTTP_X_FORWARDED_PROTO`)

### 2. Web layer — `.docker/frankenphp/Caddyfile`

- Deny akses `.env`, `.git`, `composer.*`, `wp-config.php`, `xmlrpc.php`, `readme.html`
- PHP execution di `/uploads` diblok (403)
- Security headers: HSTS (1y), X-Frame-Options SAMEORIGIN, X-Content-Type-Options nosniff, Referrer-Policy, Permissions-Policy
- Header `Server` & `X-Powered-By` dihilangkan
- Auto HTTPS via Let's Encrypt (kalau `SERVER_NAME` bukan `localhost`)

### 3. Container layer — `docker-compose.prod.yml`

- `no-new-privileges: true`
- `cap_drop: [ALL]` — cuma capability minimal untuk bind port 80/443
- Resource limits (CPU + memory)
- Log rotation (max 20MB × 5 files)

### 4. DB layer — `.docker/mariadb/conf.d/hardening.cnf`

- `skip-name-resolve` (perf + no DNS attack)
- `local-infile=0` (cegah data exfiltration)
- Di prod: network `internal: true` — DB tidak reachable dari luar host

### 5. Plugin layer

- **Wordfence** — WAF, malware scan, brute-force protection, 2FA
- **WPS Hide Login** — ubah URL `/wp-login.php` jadi custom (via env `WP_LOGIN_SLUG`)

### 6. Secret management

- `.env.production` file di server dengan `chmod 600` (owner-read-only)
- NEVER di-commit ke git (`.gitignore`-ed)
- NEVER di-bake ke image (di-inject via `--env-file` saat runtime)

---

## Upgrade path

Boilerplate ini KISS by default. Kalau lu butuh lebih, ini upgrade path-nya:

### FrankenPHP worker mode (perf boost 2-4x)

Aktifkan di `.env`:
```
FRANKENPHP_WORKER_MODE=1
```

Restart container. Test kompatibilitas plugin dulu — beberapa plugin lama tidak support long-running PHP.

### Multi-arch build (ARM64 support)

Edit `.github/workflows/deploy.yml`:
```yaml
platforms: linux/amd64,linux/arm64
```

### Backup off-site (S3, B2, R2, dll)

Edit `.docker/backup/backup.sh`, tambahkan di akhir:
```bash
# Contoh S3 pakai aws-cli:
# aws s3 sync /backups s3://your-bucket/wp-backups/ --exclude "*.tmp"

# Contoh rclone:
# rclone sync /backups remote:wp-backups/
```

Rebuild backup image + update env vars.

### Docker Secrets / Vault (multi-host)

Ganti `--env-file` dengan Docker Secrets di `docker-compose.prod.yml`. Butuh Swarm mode atau K8s.

### Rate limiting di Caddy

Butuh custom FrankenPHP build dengan `mholt/caddy-ratelimit` module via `xcaddy`. Sementara ini kita rely on Wordfence untuk login rate limit.

---

## Troubleshooting

**Container app tidak boot / restart terus**

```bash
docker compose logs app --tail 50
# Common: DB creds salah, salts kosong, permission /uploads
```

**Composer install lambat di dev**

Ini normal saat first boot — plugin di-download semua. Berikutnya cepat karena vendor persist di image dev.

**"Error establishing a database connection"**

```bash
docker compose ps    # cek db healthy?
docker compose logs db --tail 20
# Cek .env: DB_HOST=db, DB_USER, DB_PASSWORD match yang di docker-compose
```

**Caddy tidak dapat cert Let's Encrypt di prod**

- Pastikan `SERVER_NAME` di `.env.production` = domain valid (bukan `localhost`)
- Pastikan port 80 & 443 terbuka & DNS point ke IP server
- Cek log: `docker compose logs app | grep -i caddy`

**Plugin update workflow tidak jalan**

- Cek GitHub Actions tab → Plugin Update workflow
- Cron pakai UTC. Default `0 3 * * 1` = Senin 03:00 UTC.

**wp-cli command "site not installed"**

Normal saat first boot. Jalankan `wp core install` (lihat [Setup dev cepat](#setup-dev-cepat)).

---

## Workflow rules

Baca [`AGENTS.md`](AGENTS.md) sebelum kontribusi. Ringkas:

1. **Jangan commit otomatis** — semua git ops manual
2. **Rekap harian** di `daily/YYYY-MM-DD.md` (gitignored)
3. **Ramah pemula** — kompleksitas advanced ada di upgrade path, bukan default
4. **Jangan overkill** — skip stack yang belum jelas butuh
5. **DRY / KISS / Clean Code** — semua script share `scripts/lib/common.sh`

---

## Lisensi

MIT untuk boilerplate ini. WordPress + plugin dependencies ikut lisensi masing-masing (mayoritas GPL v2+).
