#!/usr/bin/env bash
# ==============================================================================
# backup-ftp.sh — backup seluruh aplikasi jadi 2 file siap deploy ke hosting FTP
#                 (tanpa Docker dan tanpa Composer di server)
#
# Output (tepat 2 file, default di folder backups/):
#   imigrasi-wp.zip         WP core, vendor, semua plugin, theme, uploads, config,
#                           .env produksi (template), .htaccess, panduan DEPLOY-FTP.txt
#   backup_YYYY-MM-DD.sql   dump database
#
# Kenapa lewat container: WP core, vendor/, plugin, dan uploads hidup di volume
# Docker, bukan di folder project. Script ini mengambilnya dari container 'app'
# dan 'db' (keduanya harus sedang jalan). Container tidak diubah sama sekali.
#
# Usage (jalankan dari WSL / Linux):
#   bash scripts/backup-ftp.sh                          # backup apa adanya
#   bash scripts/backup-ftp.sh https://imigrasi.go.id   # + ganti URL lokal ke URL produksi
#
# Kalau URL produksi diberikan:
#   - URL lokal (mis. http://localhost:6300) di SQL dan file CSS Elementor diganti
#   - WP_HOME di .env dalam zip terisi otomatis
# Tanpa URL produksi, SQL masih berisi URL lokal (script memberi tahu jumlahnya).
#
# Opsional (environment variable):
#   OUTPUT_DIR=/path/lain bash scripts/backup-ftp.sh    # default: backups/
#   TMPDIR=$HOME bash scripts/backup-ftp.sh             # kalau /tmp (tmpfs di WSL) kepenuhan karena uploads besar
# ==============================================================================

set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=lib/common.sh
source "${SCRIPT_DIR}/lib/common.sh"

# Root dihitung dari lokasi script, bukan `git rev-parse`: di WSL git bisa belum
# terpasang atau menolak repo di /mnt/c ("dubious ownership").
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
OUTPUT_DIR="${OUTPUT_DIR:-backups}" # relatif ke root project, atau path absolut
ZIP_NAME="imigrasi-wp.zip"
SQL_NAME="backup_$(date +%Y-%m-%d).sql"
TARGET_URL="${1:-}" # opsional, mis. https://imigrasi.go.id

# Diisi saat script jalan.
WORK_DIR=""    # folder sementara (dihapus otomatis saat script selesai)
SITE_DIR=""    # isi zip sebelum dikompres
DB_PREFIX=""   # prefix tabel yang dipakai WordPress di container
OLD_HOST=""    # host URL lokal, mis. localhost:6300
URL_PATTERN="" # regex URL lokal (http/https, biasa atau JSON-escaped)
NEW_SCHEME=""  # skema URL produksi (http/https)
NEW_HOST=""    # host URL produksi

compose() { docker compose "$@"; }

cleanup() {
    [[ -n "${WORK_DIR}" ]] || return 0
    chmod -R u+rwX "${WORK_DIR}" 2>/dev/null || true # sebagian folder plugin read-only
    rm -rf "${WORK_DIR}"
}

# Baca environment variable dari container app (nilai yang benar-benar dipakai WordPress).
app_env() {
    compose exec -T app printenv "$1" | tr -d '\r' || die "Variabel $1 tidak ada di container app"
}

# Escape semua karakter non-alfanumerik (kecuali _ : -) supaya host aman dipakai di regex.
escape_regex() {
    printf '%s' "$1" | sed 's/[^A-Za-z0-9_:-]/\\&/g'
}

# 64 karakter acak [A-Za-z0-9] untuk salt WordPress.
generate_secret() {
    head -c 96 /dev/urandom | base64 | LC_ALL=C tr -dc 'A-Za-z0-9' | cut -c1-64
}

# ------------------------------------------------------------------------------
# Persiapan
# ------------------------------------------------------------------------------
check_prerequisites() {
    require_cmd docker zip unzip tar

    local service
    for service in app db; do
        [[ -n "$(compose ps --status running -q "${service}")" ]] \
            || die "Container '${service}' belum jalan. Nyalakan dulu: docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d"
    done

    if [[ -n "${TARGET_URL}" ]]; then
        [[ "${TARGET_URL}" =~ ^(https?)://([A-Za-z0-9.-]+(:[0-9]+)?)/?$ ]] \
            || die "URL produksi tidak valid: '${TARGET_URL}'. Pakai domain tanpa path, contoh: https://imigrasi.go.id"
        NEW_SCHEME="${BASH_REMATCH[1]}"
        NEW_HOST="${BASH_REMATCH[2]}"
        TARGET_URL="${NEW_SCHEME}://${NEW_HOST}"
    fi
}

read_runtime_settings() {
    local old_home
    DB_PREFIX="$(app_env DB_PREFIX)"
    old_home="$(app_env WP_HOME)"
    OLD_HOST="${old_home%/}"
    OLD_HOST="${OLD_HOST#*://}"
    # '\\?' = backslash opsional, jadi cocok dengan "://" biasa maupun ":\/\/" (JSON, dipakai Elementor).
    URL_PATTERN='https?(:\\?/\\?/)'"$(escape_regex "${OLD_HOST}")"
}

# ------------------------------------------------------------------------------
# Database
# ------------------------------------------------------------------------------
# Dump apa adanya. Image MariaDB 11.4 tidak punya `mysqldump`, hanya `mariadb-dump`.
# Kredensial dibaca di dalam container sehingga password tidak lewat command line host.
dump_database_as_is() {
    local output_file="$1"
    # shellcheck disable=SC2016  # sengaja: variabel diurai oleh shell di dalam container
    compose exec -T db sh -c '
        MYSQL_PWD="$MARIADB_PASSWORD" mariadb-dump \
            --user="$MARIADB_USER" \
            --single-transaction --quick --skip-add-locks \
            --routines --triggers \
            --default-character-set=utf8mb4 \
            "$MARIADB_DATABASE"
    ' > "${output_file}"
}

# Dump + ganti URL lokal ke URL produksi lewat `wp search-replace --export`.
# Kenapa bukan sed: data WordPress/Elementor banyak yang serialized (panjang string
# ikut tersimpan) dan JSON-escaped (http:\/\/...). WP-CLI menangani keduanya.
# --export hanya menulis SQL ke stdout, database yang sedang jalan TIDAK diubah.
# Kolom guid ikut diganti (biasanya dilewati): ini deploy pertama ke domain baru,
# jadi tidak ada feed/subscriber lama yang bergantung pada guid, dan tidak ada
# sisa URL lokal di tabel posts.
# WP_ENV=production mematikan tampilan notice PHP yang bisa menyelip ke SQL.
dump_database_with_new_url() {
    local output_file="$1"
    local log_file="${WORK_DIR}/search-replace.log"
    local replacement="${NEW_SCHEME}\${1}${NEW_HOST}"

    # Header standar mysqldump yang tidak ditulis WP-CLI: charset utf8mb4 dan SQL mode
    # longgar supaya default '0000-00-00' milik WordPress tidak ditolak MySQL 8.
    printf '%s\n' \
        '/*!40101 SET NAMES utf8mb4 */;' \
        "/*!40101 SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" \
        '/*!40014 SET FOREIGN_KEY_CHECKS=0 */;' > "${output_file}"

    compose exec -T -u www-data -e WP_ENV=production app \
        php -d display_errors=0 /usr/local/bin/wp search-replace \
        "${URL_PATTERN}" "${replacement}" \
        --regex --regex-delimiter='#' --all-tables \
        --skip-plugins --skip-themes --export --path=/app/web/wp \
        >> "${output_file}" 2> "${log_file}" \
        || { cat "${log_file}" >&2; die "wp search-replace gagal"; }

    printf '%s\n' \
        '/*!40014 SET FOREIGN_KEY_CHECKS=1 */;' \
        "-- Dump completed on $(date '+%Y-%m-%d %H:%M:%S')" >> "${output_file}"

    disable_elementor_element_cache "${output_file}"
    assert_only_sql_lines "${output_file}"
}

# Element Cache Elementor menyimpan data widget sebagai base64 di dalam HTML cache
# ([elementor-element data="..."]), jadi URL lama di dalamnya tidak terjangkau
# search-replace dan muncul lagi saat halaman dirender (mis. logo menu mobile).
# Nilai cache dikosongkan, artinya sama dengan "belum ada cache": Elementor membuatnya
# lagi saat halaman dibuka. Setiap baris WP-CLI export berisi satu row.
disable_elementor_element_cache() {
    sed -i "s/^\(('[0-9]*', '[0-9]*', '_elementor_element_cache', \).*\()[,;]\)$/\1''\2/" "$1"
}

# Jaga-jaga: kalau PHP menyelipkan notice/warning ke stdout, file SQL jadi rusak.
assert_only_sql_lines() {
    local stray
    stray="$(grep -vnE '^(DROP |CREATE |INSERT |\(|\)|  |/\*|--|$)' "$1" | head -n 3 || true)"
    [[ -z "${stray}" ]] || die "Ada baris non-SQL di hasil export (kemungkinan notice PHP): ${stray}"
}

verify_sql_dump() {
    local sql_file="$1"
    [[ -s "${sql_file}" ]] || die "File SQL kosong."
    [[ "$(tail -n 1 "${sql_file}")" == "-- Dump completed"* ]] \
        || die "Dump SQL tidak lengkap (baris penutup tidak ada)."
    grep -q "^CREATE TABLE \`${DB_PREFIX}options\`" "${sql_file}" \
        || die "Tabel ${DB_PREFIX}options tidak ditemukan di dump. Cek DB_PREFIX."
}

create_sql_dump() {
    local sql_file="${WORK_DIR}/${SQL_NAME}"
    if [[ -n "${TARGET_URL}" ]]; then
        dump_database_with_new_url "${sql_file}"
    else
        dump_database_as_is "${sql_file}"
    fi
    verify_sql_dump "${sql_file}"
}

# ------------------------------------------------------------------------------
# File aplikasi
# ------------------------------------------------------------------------------
# Daftar putih: hanya yang dibutuhkan saat runtime. /app/public (phpinfo bawaan image)
# dan plugins-custom/ (source + catatan dev, hasil build-nya sudah ada di web/app/plugins)
# sengaja tidak ikut. Cache, log, dan folder .agents juga dibuang.
export_app_files() {
    mkdir -p "${SITE_DIR}"
    compose exec -T app tar -C /app -cf - --warning=no-file-changed --anchored \
        --exclude='web/app/cache' \
        --exclude='web/app/upgrade' \
        --exclude='web/app/upgrade-temp-backup' \
        --exclude='web/app/debug.log' \
        --exclude='web/app/plugins/*/.agents' \
        composer.json composer.lock config vendor web \
        | tar -xf - -C "${SITE_DIR}"

    local required
    for required in web/index.php web/wp-config.php web/wp/wp-load.php vendor/autoload.php config/application.php; do
        [[ -f "${SITE_DIR}/${required}" ]] || die "File wajib tidak ada di hasil export: ${required}"
    done

    # WordPress/plugin cache butuh folder ini ada (isinya sengaja kosong).
    mkdir -p "${SITE_DIR}/web/app/uploads" "${SITE_DIR}/web/app/cache"
}

# CSS/JSON buatan Elementor (uploads/elementor) menyimpan URL absolut, ganti juga.
replace_url_in_elementor_files() {
    local elementor_dir="${SITE_DIR}/web/app/uploads/elementor"
    [[ -d "${elementor_dir}" ]] || return 0
    { grep -rlZE --include='*.css' --include='*.json' -e "${URL_PATTERN}" "${elementor_dir}" || true; } \
        | xargs -0 -r sed -i -E "s#${URL_PATTERN}#${NEW_SCHEME}\\1${NEW_HOST}#g"
}

# ------------------------------------------------------------------------------
# File khusus produksi (hanya ada di zip, tidak mengubah isi repo)
# ------------------------------------------------------------------------------
write_env_file() {
    local wp_home="${TARGET_URL:-https://GANTI-DENGAN-DOMAIN-ANDA}"
    local key
    {
        cat <<EOF
# ==============================================================================
# .env produksi — dibuat otomatis oleh scripts/backup-ftp.sh
# Edit nilai yang diawali GANTI sebelum website dibuka.
# Password yang mengandung spasi, # atau tanda dollar harus dibungkus kutip tunggal.
# ==============================================================================

# production = HTTPS wajib di wp-admin, dan install/update plugin dari dashboard dikunci
# (lihat config/environments/production.php). Pastikan SSL sudah aktif di hosting.
WP_ENV=production
WP_HOME=${wp_home}
WP_SITEURL=\${WP_HOME}/wp

# Data database dari panel hosting (DB_HOST biasanya localhost).
DB_HOST=localhost
DB_NAME=GANTI_NAMA_DATABASE
DB_USER=GANTI_USER_DATABASE
DB_PASSWORD=GANTI_PASSWORD_DATABASE
DB_PREFIX=${DB_PREFIX}

# Salt acak baru, dibuat saat backup ini.
EOF
        for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
            printf "%s='%s'\n" "${key}" "$(generate_secret)"
        done
    } > "${SITE_DIR}/.env"
}

# Pengganti aturan Caddyfile (Docker) untuk hosting Apache/LiteSpeed.
# Dipakai kalau document root domain diarahkan ke folder web/ (cara yang disarankan).
write_web_htaccess() {
    cat > "${SITE_DIR}/web/.htaccess" <<'EOF'
# ==============================================================================
# web/.htaccess — dibuat otomatis oleh scripts/backup-ftp.sh
# Pengganti aturan Caddyfile (Docker) untuk hosting Apache/LiteSpeed.
# ==============================================================================

<IfModule mod_rewrite.c>
RewriteEngine On

# Validasi SSL (Let's Encrypt / AutoSSL) harus tetap bisa diakses.
RewriteRule ^\.well-known/ - [L]

# Blokir dotfile (.env, .git, dll) dan file sensitif.
RewriteRule (^|/)\. - [R=404,L]
RewriteRule ^(composer\.(json|lock)|README\.md|readme\.html|wp-config(-sample)?\.php)$ - [R=404,L]

# Blokir eksekusi PHP di folder uploads.
RewriteRule ^app/uploads/.*\.(php[0-9]?|phtml|pht|phar)$ - [F,L]
</IfModule>

<IfModule mod_headers.c>
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "SAMEORIGIN"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress
EOF
}

# FALLBACK untuk hosting yang document root-nya tidak bisa diarahkan ke web/.
# Kalau document root sudah di web/, file ini ada di luar root dan tidak terpakai.
write_root_htaccess() {
    cat > "${SITE_DIR}/.htaccess" <<'EOF'
# ==============================================================================
# .htaccess (root) — dibuat otomatis oleh scripts/backup-ftp.sh
# FALLBACK untuk hosting yang document root-nya TIDAK bisa diarahkan ke folder web/.
# Semua request dialihkan ke web/, sedangkan config/, vendor/, .env, dan file project
# lainnya tidak bisa diakses dari browser.
# Kalau document root sudah diarahkan ke web/, file ini tidak terpakai (aman dibiarkan).
# ==============================================================================

<IfModule mod_rewrite.c>
RewriteEngine On

# Validasi SSL (Let's Encrypt / AutoSSL) harus tetap bisa diakses.
RewriteRule ^\.well-known/ - [L]

# Request yang sudah menuju web/ tidak diproses lagi (aturan web/.htaccess berlaku).
RewriteRule ^web/ - [L]

# Blokir dotfile (.env, .git, dll), folder internal, dan file project.
RewriteRule (^|/)\. - [R=404,L]
RewriteRule ^(config|vendor)(/|$) - [R=404,L]
RewriteRule ^(composer\.(json|lock)|DEPLOY-FTP\.txt)$ - [R=404,L]

# Semua request lain dilayani dari web/.
RewriteRule ^(.*)$ web/$1 [L]
</IfModule>
EOF
}

# Dipakai dua kali: ditulis ke DEPLOY-FTP.txt di dalam zip, dan dicetak di akhir script.
deploy_instructions() {
    cat <<EOF
LANGKAH DEPLOY KE HOSTING FTP (tanpa Docker)

1. Di panel hosting, buat database MySQL/MariaDB beserta user-nya.
   Catat nama database, user, dan password.
2. phpMyAdmin > pilih database itu > Import > pilih ${SQL_NAME}.
3. Upload ${ZIP_NAME} lewat FTP/File Manager, lalu Extract.
   - Cara yang disarankan: arahkan document root domain ke folder "web" hasil extract.
   - Kalau document root tidak bisa diubah: extract langsung di folder document root
     (biasanya public_html). File .htaccess di root sudah mengarahkan semua request
     ke web/ dan memblokir akses ke config/, vendor/, dan .env.
4. Edit file .env (di File Manager aktifkan "Show Hidden Files"):
   isi DB_NAME, DB_USER, DB_PASSWORD, dan cek WP_HOME = alamat website (pakai https://).
5. Buka website. Kalau muncul "Error establishing a database connection", cek lagi isi .env.

Syarat hosting: PHP 8.2 atau lebih baru (aplikasi dibuild di PHP 8.3), mod_rewrite aktif,
memory_limit sebaiknya 256M ke atas, SSL aktif.
Hosting tanpa fitur Extract: extract zip di komputer, lalu upload isi foldernya lewat FTP client.
EOF
}

write_production_files() {
    write_env_file
    write_web_htaccess
    write_root_htaccess
    deploy_instructions > "${SITE_DIR}/DEPLOY-FTP.txt"
}

# ------------------------------------------------------------------------------
# Zip + hasil akhir
# ------------------------------------------------------------------------------
build_zip() {
    local zip_file="${WORK_DIR}/${ZIP_NAME}"
    (cd "${SITE_DIR}" && zip -r -q -X "${zip_file}" .)
    unzip -tq "${zip_file}" > /dev/null || die "Zip rusak, backup dibatalkan."
}

# File baru dipindah ke OUTPUT_DIR hanya setelah semuanya lolos pengecekan,
# jadi tidak pernah ada output setengah jadi.
publish_outputs() {
    mkdir -p "${OUTPUT_DIR}"
    mv -f "${WORK_DIR}/${ZIP_NAME}" "${OUTPUT_DIR}/${ZIP_NAME}"
    mv -f "${WORK_DIR}/${SQL_NAME}" "${OUTPUT_DIR}/${SQL_NAME}"
}

count_occurrences() {
    local count
    count="$(grep -oF -- "$1" "$2" | wc -l || true)"
    printf '%s' "${count}"
}

print_summary() {
    local output_abs sql_file table_count leftover
    output_abs="$(cd "${OUTPUT_DIR}" && pwd)"
    sql_file="${OUTPUT_DIR}/${SQL_NAME}"
    table_count="$(grep -c '^CREATE TABLE' "${sql_file}" || true)"
    leftover="$(count_occurrences "${OLD_HOST}" "${sql_file}")"

    log_ok "Selesai. 2 file siap di-upload:"
    printf '  %s  (%s)\n' "${ZIP_NAME}" "$(du -h "${OUTPUT_DIR}/${ZIP_NAME}" | cut -f1)"
    printf '  %s  (%s, %s tabel)\n' "${SQL_NAME}" "$(du -h "${sql_file}" | cut -f1)" "${table_count}"
    printf 'Lokasi: %s\n' "${output_abs}"
    if command -v wslpath > /dev/null 2>&1; then
        printf 'Windows: %s\n' "$(wslpath -w "${output_abs}")"
    fi

    if [[ -n "${TARGET_URL}" ]]; then
        log_info "URL lokal diganti ke ${TARGET_URL}. Sisa '${OLD_HOST}' di SQL: ${leftover} (hanya log dev Elementor dan data akun plugin, aman)."
    else
        log_warn "SQL masih berisi ${leftover}x '${OLD_HOST}' (URL lokal) sehingga gambar/link dari database akan rusak di server."
        log_warn "Kalau domain produksi sudah ada, jalankan ulang: bash scripts/backup-ftp.sh https://domain-anda"
    fi

    printf '\n'
    deploy_instructions
}

main() {
    cd "${REPO_ROOT}"
    check_prerequisites
    read_runtime_settings

    WORK_DIR="$(mktemp -d)"
    SITE_DIR="${WORK_DIR}/site"
    trap cleanup EXIT

    log_info "[1/4] Dump database ..."
    create_sql_dump

    log_info "[2/4] Ambil file aplikasi dari container (WP core, vendor, plugin, uploads) ..."
    export_app_files
    [[ -z "${TARGET_URL}" ]] || replace_url_in_elementor_files

    log_info "[3/4] Siapkan file produksi (.env, .htaccess, panduan) ..."
    write_production_files

    log_info "[4/4] Bikin ${ZIP_NAME} ..."
    build_zip
    publish_outputs
    print_summary
}

trap 'log_error "Gagal di baris ${LINENO}. Backup dibatalkan, tidak ada file output yang dibuat."' ERR
main
