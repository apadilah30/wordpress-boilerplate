<?php
/**
 * Boilerplate Starter theme bootstrap.
 *
 * Sengaja minim: mayoritas layout diserahkan ke Elementor.
 * Tambahkan feature theme di sini seperlunya (menus, image sizes, dll.).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('automatic-feed-links');

    register_nav_menus([
        'primary' => __('Primary Menu', 'boilerplate-starter'),
        'footer'  => __('Footer Menu', 'boilerplate-starter'),
    ]);
});

/**
 * Enqueue scripts and styles.
 */
add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'boilerplate-starter-style',
        get_stylesheet_uri(),
        [],
        '1.0.0'
    );

    wp_enqueue_script(
        'boilerplate-starter-navigation',
        get_template_directory_uri() . '/assets/js/navigation.js',
        [],
        '1.0.0',
        true
    );
});

/**
 * Enable Elementor for all public CPT with show_in_rest.
 *
 * Automatically detects CPT from CPT UI and enables Elementor support.
 * No need to manually add each CPT slug.
 */
add_filter('elementor/utils/get_capable_post_types', static function (array $post_types): array {
    $public_types = get_post_types([
        'public'       => true,
        'show_in_rest' => true,
    ], 'names');

    foreach ($public_types as $type) {
        if (!in_array($type, $post_types, true)) {
            $post_types[] = $type;
        }
    }

    return $post_types;
});

/**
 * Custom Walker Nav Menu for accessible dropdown menus.
 *
 * Adds CSS classes and ARIA attributes for dropdown submenus.
 */
class Boilerplate_Walker_Nav_Menu extends Walker_Nav_Menu
{
    /**
     * Start level - adds classes for dropdown submenu.
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void
    {
        $indent = str_repeat("\t", $depth);
        $classes = ['sub-menu', 'dropdown-menu'];
        
        if ($depth > 0) {
            $classes[] = 'sub-menu-level-' . $depth;
        }
        
        $output .= "\n$indent<ul class=\"" . esc_attr(implode(' ', $classes)) . "\">\n";
    }

    /**
     * Start element - adds ARIA and CSS classes.
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0): void
    {
        $indent = str_repeat("\t", $depth);
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $classes[] = 'menu-item-' . $item->ID;

        // Add dropdown class for items with children.
        if (in_array('menu-item-has-children', $classes, true)) {
            $classes[] = 'dropdown';
        }

        $class_names = implode(' ', array_filter($classes));
        $class_names = $class_names ? ' class="' . esc_attr($class_names) . '"' : '';

        $output .= $indent . '<li' . $class_names . '>';

        $atts = [
            'title'  => $item->attr_title ?: '',
            'target' => $item->target ?: '',
            'rel'    => $item->xfn ?: '',
            'href'   => $item->url ?: '',
        ];

        // ARIA current page.
        if ($item->current) {
            $atts['aria-current'] = 'page';
        }

        // ARIA expanded for dropdown toggles.
        if (in_array('menu-item-has-children', $classes, true)) {
            $atts['aria-expanded'] = 'false';
            $atts['aria-haspopup'] = 'true';
        }

        $attributes = '';
        foreach ($atts as $attr => $value) {
            if (!empty($value)) {
                $attributes .= ' ' . $attr . '="' . esc_attr($value) . '"';
            }
        }

        $title = apply_filters('the_title', $item->title, $item->ID);
        $title = apply_filters('nav_menu_item_title', $title, $item, $args, $depth);

        $item_output = $args->before ?? '';
        $item_output .= '<a' . $attributes . '>';
        $item_output .= ($args->link_before ?? '') . $title . ($args->link_after ?? '');
        $item_output .= '</a>';

        // Add dropdown toggle button for items with children.
        if (in_array('menu-item-has-children', $classes, true) && $depth === 0) {
            $item_output .= '<button class="dropdown-toggle" aria-expanded="false" aria-label="' . 
                esc_attr__('Toggle submenu', 'boilerplate-starter') . '">';
            $item_output .= '<span class="dropdown-icon">▼</span>';
            $item_output .= '</button>';
        }

        $item_output .= $args->after ?? '';

        $output .= apply_filters('walker_nav_menu_start_el', $item_output, $item, $depth, $args);
    }
}
