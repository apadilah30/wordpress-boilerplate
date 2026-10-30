<?php
/**
 * migrate-category.php — pindahkan term dan relasi post dari taxonomy bawaan
 * "category" ke taxonomy khusus sebuah post type (konvensi {post_type}_category).
 *
 * Jangan dijalankan langsung. Dipanggil oleh scripts/migrate-category.sh:
 *   wp eval-file <file> <post_type> <new_taxonomy> [apply]
 *
 * Tanpa argumen "apply" skrip hanya menampilkan rencana (dry run) dan tidak
 * mengubah data. Skrip aman dijalankan ulang: term yang sudah ada di taxonomy
 * baru dipakai lagi, tidak dibuat dobel.
 *
 * Yang dipindahkan: term "category" yang terpasang di post milik post type
 * tsb (hierarki parent/child ikut), lalu relasi lamanya dilepas dari post itu.
 * Term lama di "category" TIDAK dihapus (bisa jadi masih dipakai post biasa).
 * Term kategori default (Uncategorized) tidak disalin, hanya dilepas.
 *
 * @package Boilerplate
 */

declare(strict_types=1);

if (!defined('ABSPATH') || !class_exists('WP_CLI')) {
    exit;
}

$post_type = (string) ($args[0] ?? '');
$new_tax   = (string) ($args[1] ?? '');
$apply     = ($args[2] ?? '') === 'apply';
$old_tax   = 'category';

if ($post_type === '' || !post_type_exists($post_type)) {
    WP_CLI::error("Post type '{$post_type}' tidak ditemukan.");
}
if ($new_tax === '' || !taxonomy_exists($new_tax)) {
    WP_CLI::error("Taxonomy '{$new_tax}' tidak ditemukan. Sudah didaftarkan di mu-plugin custom-taxonomies.php?");
}
if (!is_object_in_taxonomy($post_type, $new_tax)) {
    WP_CLI::error("Taxonomy '{$new_tax}' tidak terpasang ke post type '{$post_type}'.");
}

$default_term_id = (int) get_option('default_category');
$term_map        = [];

// Pastikan padanan sebuah term lama ada di taxonomy baru (parent dibuat lebih dulu).
$ensure_term = function (WP_Term $old_term) use (&$ensure_term, &$term_map, $old_tax, $new_tax, $apply): int {
    if (isset($term_map[$old_term->term_id])) {
        return $term_map[$old_term->term_id];
    }

    $new_parent_id = 0;
    if ($old_term->parent > 0) {
        $old_parent = get_term($old_term->parent, $old_tax);
        if ($old_parent instanceof WP_Term) {
            $new_parent_id = $ensure_term($old_parent);
        }
    }

    $existing = term_exists($old_term->slug, $new_tax, $new_parent_id);
    if (is_array($existing)) {
        $term_map[$old_term->term_id] = (int) $existing['term_id'];
        return $term_map[$old_term->term_id];
    }

    WP_CLI::log(sprintf('  %s term: %s (slug: %s)', $apply ? 'buat' : 'akan dibuat', $old_term->name, $old_term->slug));

    if (!$apply) {
        $term_map[$old_term->term_id] = 0;
        return 0;
    }

    $created = wp_insert_term($old_term->name, $new_tax, [
        'slug'        => $old_term->slug,
        'parent'      => $new_parent_id,
        'description' => $old_term->description,
    ]);

    if (is_wp_error($created)) {
        WP_CLI::warning(sprintf("  gagal membuat term '%s': %s", $old_term->name, $created->get_error_message()));
        return 0;
    }

    $term_map[$old_term->term_id] = (int) $created['term_id'];
    return $term_map[$old_term->term_id];
};

$post_ids = get_posts([
    'post_type'     => $post_type,
    'post_status'   => 'any',
    'numberposts'   => -1,
    'fields'        => 'ids',
    'no_found_rows' => true,
]);

WP_CLI::log(sprintf(
    '%s: %s dari "%s" ke "%s" (%d post dicek)',
    $apply ? 'MIGRASI' : 'DRY RUN',
    $post_type,
    $old_tax,
    $new_tax,
    count($post_ids)
));

$processed = 0;
foreach ($post_ids as $post_id) {
    $old_terms = wp_get_object_terms($post_id, $old_tax);
    if (is_wp_error($old_terms) || empty($old_terms)) {
        continue;
    }

    $new_ids   = [];
    $removable = [];
    foreach ($old_terms as $old_term) {
        if ((int) $old_term->term_id === $default_term_id) {
            $removable[] = (int) $old_term->term_id;
            continue;
        }

        $new_id = $ensure_term($old_term);
        if ($new_id > 0) {
            $new_ids[]   = $new_id;
            // Relasi lama hanya dilepas kalau padanan barunya benar-benar ada.
            $removable[] = (int) $old_term->term_id;
        }
    }

    WP_CLI::log(sprintf('post #%d "%s": %s', $post_id, get_the_title($post_id), implode(', ', wp_list_pluck($old_terms, 'name'))));

    if ($apply) {
        if ($new_ids !== []) {
            wp_set_object_terms($post_id, $new_ids, $new_tax, true);
        }
        if ($removable !== []) {
            wp_remove_object_terms($post_id, $removable, $old_tax);
        }
    }

    $processed++;
}

if ($apply) {
    WP_CLI::success(sprintf('Selesai: %d post dipindahkan, %d term dipetakan.', $processed, count($term_map)));
} else {
    WP_CLI::success(sprintf('Dry run selesai, tidak ada data yang diubah (%d post punya kategori). Tambahkan --apply untuk menjalankan.', $processed));
}
