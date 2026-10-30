<?php
/**
 * Plugin Name:  Custom Taxonomies — Dedicated Category per CPT
 * Description:  Registers a hierarchical "{post_type}_category" taxonomy for
 *               each CPT below, attached to ONLY that post type — instead of
 *               sharing the built-in "category" taxonomy across multiple CPTs
 *               (which mixes unrelated content together in category lists).
 * Version:      1.0.0
 * License:      MIT
 *
 * Registered here via CODE (not CPT UI) so it's version-controlled and can't
 * drift through the admin screen. Hooked at a LATE 'init' priority (20) so
 * it OVERRIDES/REPLACES any taxonomy CPT UI may have already registered
 * with the same slug — register_taxonomy() simply overwrites the entry in
 * WordPress's internal registry, last call wins.
 *
 * IMPORTANT: if CPT UI still shows one of these taxonomies in its own
 * admin list, don't edit it there anymore — this file is now the single
 * source of truth for these taxonomies' settings. Editing in CPT UI
 * would be silently overridden on the next page load anyway.
 *
 * @package Boilerplate
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'boilerplate_register_dedicated_categories', 20);

function boilerplate_register_dedicated_categories(): void
{
    boilerplate_register_dedicated_category('services', __('Layanan', 'boilerplate'));
    boilerplate_register_dedicated_category('news', __('Berita', 'boilerplate'));
    boilerplate_register_dedicated_category('gallery', __('Galeri Foto', 'boilerplate'));
    boilerplate_register_dedicated_category('videos', __('Galeri Video', 'boilerplate'));
    boilerplate_register_dedicated_category('information', __('Informasi', 'boilerplate'));
    boilerplate_register_dedicated_category('about', __('Tentang Kami', 'boilerplate'));
}

/**
 * Register (or replace) a "{post_type}_category" taxonomy for one post
 * type only.
 *
 * @param string $post_type Post type slug (must already be registered,
 *                           e.g. via CPT UI — this only defines the
 *                           taxonomy, not the post type itself).
 * @param string $label     Human label used to build the taxonomy's labels
 *                           (e.g. "Layanan" -> "Layanan Categories").
 */
function boilerplate_register_dedicated_category(string $post_type, string $label): void
{
    $taxonomy = $post_type . '_category';

    register_taxonomy($taxonomy, [$post_type], [
        'labels' => [
            'name'          => sprintf(__('%s Categories', 'boilerplate'), $label),
            'singular_name' => sprintf(__('%s Category', 'boilerplate'), $label),
            'menu_name'     => __('Categories', 'boilerplate'),
            'search_items'  => __('Search Categories', 'boilerplate'),
            'all_items'     => __('All Categories', 'boilerplate'),
            'edit_item'     => __('Edit Category', 'boilerplate'),
            'update_item'   => __('Update Category', 'boilerplate'),
            'add_new_item'  => __('Add New Category', 'boilerplate'),
            'new_item_name' => __('New Category Name', 'boilerplate'),
        ],
        'hierarchical'      => true,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => false,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => ['slug' => $taxonomy],
    ]);

    // CPT UI may still attach the built-in "category" to this post type
    // (its "Taxonomies" checkboxes). Detach it here so editors only see the
    // dedicated one and posts can't be tagged into the shared pool by mistake.
    // This only changes the attachment; existing term relationships in the
    // database are untouched (move them with scripts/migrate-category.sh).
    unregister_taxonomy_for_object_type('category', $post_type);
}
