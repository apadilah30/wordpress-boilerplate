<?php
/**
 * Elementor widget: Taxonomy Grid.
 *
 * Thin wrapper around wptg_resolve_and_render() — all actual markup/logic
 * lives in wp-taxonomy-grid.php so the render output is identical whether
 * it came from the widget or (in the future) any other entry point.
 *
 * Style tab is organized so every visual piece of the design is fully
 * customizable from Elementor: icons, typography (family/size/weight/
 * color/etc.), spacing (margin + padding), alignment, and divider/line
 * accents — each with sensible defaults matching the reference design.
 *
 * @package WpTaxonomyGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class WPTG_Elementor_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'taxonomy_grid';
    }

    public function get_title(): string
    {
        return __('Taxonomy Grid', 'wp-taxonomy-grid');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-grid';
    }

    public function get_categories(): array
    {
        return ['wptg-category'];
    }

    public function get_keywords(): array
    {
        return ['cpt', 'taxonomy', 'category', 'post', 'grid', 'accordion', 'layanan', 'services'];
    }

    protected function register_controls(): void
    {
        // Content tab
        $this->register_source_controls();
        $this->register_sections_controls();
        $this->register_header_content_controls();
        $this->register_breadcrumb_content_controls();
        $this->register_search_content_controls();
        $this->register_grid_controls();

        // Style tab
        $this->register_style_container_controls();
        $this->register_style_header_controls();
        $this->register_style_breadcrumb_controls();
        $this->register_style_search_controls();
        $this->register_style_sidebar_controls();
        $this->register_style_divider_accordion_controls();
        $this->register_style_divider_merged_controls();
        $this->register_style_category_name_accordion_controls();
        $this->register_style_category_name_merged_controls();
        $this->register_style_content_controls();
        $this->register_style_sections_nav_controls();
        $this->register_style_numbered_feed_controls();
        $this->register_style_grid_thumbnail_controls();
        $this->register_style_list_thumbnail_controls();
        $this->register_style_load_more_controls();
        $this->register_style_modal_controls();
    }

    /* ---------------------------------------------------------------------
     * Helpers (DRY — reused across many Style sections)
     * ------------------------------------------------------------------- */

    /** Typography group + matching Color control, same selector. */
    private function add_text_style(string $prefix, string $label, string $selector, ?string $default_color = null): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => $prefix . '_typography',
            'label'    => sprintf(__('%s Typography', 'wp-taxonomy-grid'), $label),
            'selector' => $selector,
        ]);

        $args = [
            'label'     => sprintf(__('%s Color', 'wp-taxonomy-grid'), $label),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ];
        if ($default_color !== null) {
            $args['default'] = $default_color;
        }
        $this->add_control($prefix . '_color', $args);
    }

    /** Responsive Margin + Padding DIMENSIONS pair for one selector. */
    private function add_spacing_controls(string $prefix, string $selector): void
    {
        $this->add_responsive_control($prefix . '_margin', [
            'label'      => __('Margin', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control($prefix . '_padding', [
            'label'      => __('Padding', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
    }

    /** Single responsive Margin OR Padding DIMENSIONS control. */
    private function add_box_control(string $name, string $label, string $selector, string $property): void
    {
        $this->add_responsive_control($name, [
            'label'      => $label,
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
    }

    /** Left/Center/Right alignment control (text-align or justify-content). */
    private function add_alignment_control(string $name, string $selector, string $property = 'text-align', ?string $default = null): void
    {
        $args = [
            'label'   => __('Alignment', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::CHOOSE,
            'options' => [
                'left'    => ['title' => __('Left', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-left'],
                'center'  => ['title' => __('Center', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-center'],
                'right'   => ['title' => __('Right', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors' => [$selector => $property . ': {{VALUE}};'],
        ];
        if ($default !== null) {
            $args['default'] = $default;
        }
        $this->add_responsive_control($name, $args);
    }

    /** Background color control. */
    private function add_bg_control(string $name, string $label, string $selector): void
    {
        $this->add_control($name, [
            'label'     => $label,
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);
    }

    /**
     * Margin + Padding + Background + Border Radius for one state of a
     * "badge/button-like" text element (e.g. Category Name per Normal/
     * Hover/Active). Text Color is added separately by the caller since
     * each state already has its own differently-named color control.
     */
    private function add_badge_box_controls(string $prefix, string $selector): void
    {
        $this->add_responsive_control($prefix . '_margin', [
            'label'      => __('Margin', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control($prefix . '_padding', [
            'label'      => __('Padding', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control($prefix . '_bg', [
            'label'     => __('Background Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control($prefix . '_radius', [
            'label'      => __('Border Radius', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
    }

    /**
     * Full "Position" control group for one element: Static/Absolute toggle,
     * horizontal + vertical orientation (with offset), and z-index — the
     * same idea as Elementor's built-in widget Position feature, scoped to
     * a specific inner element (e.g. one decorative line) instead of the
     * whole widget.
     *
     * Centering uses a negative margin trick (half of $center_offset_var)
     * instead of `transform`, so horizontal and vertical centering never
     * fight over the same `transform` property when both are active.
     */
    private function add_position_controls(
        string $prefix,
        string $selector,
        string $center_width_var = '',
        string $center_height_var = ''
    ): void {
        $this->add_control($prefix . '_position', [
            'label'   => __('Position', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'static'   => __('Default (follows layout)', 'wp-taxonomy-grid'),
                'absolute' => __('Absolute (free positioning)', 'wp-taxonomy-grid'),
            ],
            'default' => 'static',
            'selectors' => [$selector => 'position: {{VALUE}};'],
        ]);

        $condition = [$prefix . '_position' => 'absolute'];

        $center_x_margin = $center_width_var !== '' ? "calc(-0.5 * var({$center_width_var}, 0px))" : '0';
        $center_y_margin = $center_height_var !== '' ? "calc(-0.5 * var({$center_height_var}, 0px))" : '0';

        $this->add_responsive_control($prefix . '_h_orientation', [
            'label'                => __('Horizontal Alignment', 'wp-taxonomy-grid'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'left'   => ['title' => __('Left', 'wp-taxonomy-grid'), 'icon' => 'eicon-h-align-left'],
                'center' => ['title' => __('Center', 'wp-taxonomy-grid'), 'icon' => 'eicon-h-align-center'],
                'right'  => ['title' => __('Right', 'wp-taxonomy-grid'), 'icon' => 'eicon-h-align-right'],
            ],
            'default'              => 'left',
            'condition'            => $condition,
            'selectors_dictionary' => [
                'left'   => 'left: 0; right: auto; margin-left: 0;',
                'center' => "left: 50%; right: auto; margin-left: {$center_x_margin};",
                'right'  => 'left: auto; right: 0; margin-left: 0;',
            ],
            'selectors'            => [$selector => '{{VALUE}}'],
            'description'          => __('Use the Margin control below to fine-tune the exact offset from this anchor point.', 'wp-taxonomy-grid'),
        ]);

        $this->add_responsive_control($prefix . '_v_orientation', [
            'label'                => __('Vertical Alignment', 'wp-taxonomy-grid'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'top'    => ['title' => __('Top', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-top'],
                'center' => ['title' => __('Middle', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-middle'],
                'bottom' => ['title' => __('Bottom', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-bottom'],
            ],
            'default'              => 'center',
            'condition'            => $condition,
            'selectors_dictionary' => [
                'top'    => 'top: 0; bottom: auto; margin-top: 0;',
                'center' => "top: 50%; bottom: auto; margin-top: {$center_y_margin};",
                'bottom' => 'top: auto; bottom: 0; margin-top: 0;',
            ],
            'selectors'            => [$selector => '{{VALUE}}'],
        ]);

        $this->add_control($prefix . '_zindex', [
            'label'     => __('Z-Index', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::NUMBER,
            'condition' => $condition,
            'selectors' => [$selector => 'z-index: {{VALUE}};'],
        ]);
    }

    /* ---------------------------------------------------------------------
     * CONTENT TAB
     * ------------------------------------------------------------------- */

    private function register_source_controls(): void
    {
        $this->start_controls_section('wptg_section_source', [
            'label' => __('Source', 'wp-taxonomy-grid'),
        ]);

        $this->add_control('wptg_source_mode', [
            'label'   => __('Source Mode', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'single'   => __('Single — one Post Type + Taxonomy (legacy)', 'wp-taxonomy-grid'),
                'sections' => __('Sections — multiple sidebar sections (Content Navigator)', 'wp-taxonomy-grid'),
            ],
            'default' => 'single',
        ]);

        $this->add_control('wptg_post_type', [
            'label'       => __('Post Type', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::SELECT,
            'options'     => wptg_get_post_type_options(),
            'default'     => 'post',
            'description' => __('The custom post type to browse (e.g. "services").', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_taxonomy', [
            'label'   => __('Taxonomy', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => wptg_get_taxonomy_options(),
            'default' => '',
            // No single universal default anymore: this project's convention
            // is a DEDICATED taxonomy per post type, named "{post_type}_category"
            // (e.g. "services_category", "news_category") instead of sharing
            // the built-in "category" across every CPT. Must be registered
            // for the Post Type selected above (create it via CPT UI first).
            'description' => __('Must be registered for the Post Type above. Convention: "{post_type}_category", e.g. "services_category".', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_mode', [
            'label'   => __('Layout Mode', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'detail' => __('Detail — header, breadcrumb, search, single post view', 'wp-taxonomy-grid'),
                'grid'   => __('Grid — category list + posts grid', 'wp-taxonomy-grid'),
            ],
            'default'   => 'detail',
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_active_first', [
            'label'     => __('Auto-select First Item', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $this->end_controls_section();
    }

    /**
     * "Sections" repeater — the Content Navigator source mode. Each row
     * is one independent sidebar section (flat list or taxonomy
     * accordion), with its own post type, optional taxonomy, content
     * style, click behavior, search toggle, and page size.
     */
    private function register_sections_controls(): void
    {
        $this->start_controls_section('wptg_section_sections', [
            'label'     => __('Sections', 'wp-taxonomy-grid'),
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $repeater = new \Elementor\Repeater();

        $repeater->add_control('section_label', [
            'label'   => __('Label', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Section', 'wp-taxonomy-grid'),
        ]);

        $repeater->add_control('section_type', [
            'label'   => __('Section Type', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'flat_list'          => __('Flat List (no categories)', 'wp-taxonomy-grid'),
                'taxonomy_accordion' => __('Taxonomy Accordion (categories)', 'wp-taxonomy-grid'),
            ],
            'default' => 'flat_list',
        ]);

        $repeater->add_control('section_post_type', [
            'label'   => __('Post Type', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => wptg_get_post_type_options(),
            // Non-empty default (mirrors the top-level wptg_post_type
            // control's own default) so every row added through the
            // Elementor UI from now on always normalizes successfully —
            // see wptg_normalize_section_row()'s own doc comment for why
            // an empty value is still handled gracefully for rows saved
            // before this default existed.
            'default' => 'post',
        ]);

        $repeater->add_control('section_taxonomy', [
            'label'     => __('Taxonomy', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::SELECT,
            'options'   => wptg_get_taxonomy_options(),
            'condition' => ['section_type' => 'taxonomy_accordion'],
        ]);

        $repeater->add_control('section_content_style', [
            'label'       => __('Content Style', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::SELECT,
            'options'     => [
                'numbered_feed'  => __('Numbered Feed (Flat List only)', 'wp-taxonomy-grid'),
                'grid_thumbnail' => __('Grid of Thumbnails (Taxonomy Accordion only)', 'wp-taxonomy-grid'),
                'list_thumbnail' => __('Vertical List of Thumbnails (Taxonomy Accordion only)', 'wp-taxonomy-grid'),
                'single_detail'  => __('Single Post Full Detail (Taxonomy Accordion only)', 'wp-taxonomy-grid'),
            ],
            'default'     => 'numbered_feed',
            // Documents the fallback enforced by wptg_normalize_section_row()
            // directly in the editor UI (requirements.md AC14), not only in readme.txt.
            'description' => __('Flat List sections only support "Numbered Feed". Taxonomy Accordion sections only support "Grid of Thumbnails", "Vertical List of Thumbnails", or "Single Post Full Detail". Picking a style that does not match this row\'s Section Type is not an error — it silently falls back to "Numbered Feed" (Flat List) or "Grid of Thumbnails" (Taxonomy Accordion) when the page renders.', 'wp-taxonomy-grid'),
        ]);

        $repeater->add_control('section_click_behavior', [
            'label'   => __('Item Click Behavior', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'inline_detail'            => __('Swap Content Panel (inline detail)', 'wp-taxonomy-grid'),
                'modal'                    => __('Open Floating Modal', 'wp-taxonomy-grid'),
                'redirect_or_popup_player' => __('Video: Redirect to URL, or Popup Player', 'wp-taxonomy-grid'),
            ],
            'default' => 'inline_detail',
        ]);

        $repeater->add_control('section_enable_search', [
            'label'   => __('Enable Search', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $repeater->add_control('section_posts_per_page', [
            'label'     => __('Items Per Page', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 6,
            'min'       => 1,
            'max'       => 48,
            'condition' => ['section_content_style!' => 'single_detail'],
        ]);

        $this->add_control('wptg_sections', [
            'label'       => __('Sections', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ section_label }}}',
            'condition'   => ['wptg_source_mode' => 'sections'],
        ]);

        $this->end_controls_section();
    }

    private function register_header_content_controls(): void
    {
        $this->start_controls_section('wptg_section_header', [
            'label'     => __('Header', 'wp-taxonomy-grid'),
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_show_header', [
            'label'   => __('Show Header', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('wptg_header_subtitle', [
            'label'       => __('Subtitle', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Default: post type label, e.g. "Layanan"', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_show_header' => 'yes'],
        ]);

        $this->add_control('wptg_header_title', [
            'label'       => __('Title', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Default: current page title', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_show_header' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function register_breadcrumb_content_controls(): void
    {
        $this->start_controls_section('wptg_section_breadcrumb', [
            'label'     => __('Breadcrumb', 'wp-taxonomy-grid'),
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_show_breadcrumb', [
            'label'   => __('Show Breadcrumb', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('wptg_breadcrumb_home', [
            'label'     => __('Home Label', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Beranda', 'wp-taxonomy-grid'),
            'condition' => ['wptg_show_breadcrumb' => 'yes'],
        ]);

        $this->add_control('wptg_breadcrumb_middle', [
            'label'       => __('Middle Label', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Default: header subtitle', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_show_breadcrumb' => 'yes'],
        ]);

        $this->add_control('wptg_breadcrumb_current', [
            'label'       => __('Current Label', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Default: header title', 'wp-taxonomy-grid'),
            'condition'   => ['wptg_show_breadcrumb' => 'yes'],
        ]);

        $this->add_control('wptg_breadcrumb_icon', [
            'label'     => __('Separator Icon', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-chevron-right', 'library' => 'fa-solid'],
            'condition' => ['wptg_show_breadcrumb' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function register_search_content_controls(): void
    {
        $this->start_controls_section('wptg_section_search', [
            'label'     => __('Search Box', 'wp-taxonomy-grid'),
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_show_search', [
            'label'   => __('Show Search', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('wptg_search_placeholder', [
            'label'     => __('Placeholder', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('Cari...', 'wp-taxonomy-grid'),
            'condition' => ['wptg_show_search' => 'yes'],
        ]);

        $this->add_control('wptg_search_icon', [
            'label'     => __('Search Icon', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => 'fas fa-search', 'library' => 'fa-solid'],
            'condition' => ['wptg_show_search' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function register_grid_controls(): void
    {
        $this->start_controls_section('wptg_section_grid', [
            'label'     => __('Grid Options', 'wp-taxonomy-grid'),
            'condition' => ['wptg_mode' => 'grid', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_sidebar_title', [
            'label'       => __('Sidebar Title', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('Default: taxonomy name', 'wp-taxonomy-grid'),
        ]);

        $this->add_control('wptg_show_posts', [
            'label'       => __('Posts Per Category', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => -1,
            'description' => __('-1 shows all posts.', 'wp-taxonomy-grid'),
        ]);

        $this->add_control('wptg_columns', [
            'label'   => __('Grid Columns', 'wp-taxonomy-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'auto' => __('Auto (responsive)', 'wp-taxonomy-grid'),
                '1'    => '1',
                '2'    => '2',
                '3'    => '3',
                '4'    => '4',
            ],
            'default' => 'auto',
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * STYLE TAB
     * ------------------------------------------------------------------- */

    private function register_style_container_controls(): void
    {
        $this->start_controls_section('wptg_section_style_container', [
            'label' => __('Container', 'wp-taxonomy-grid'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('wptg_max_width', [
            'label'      => __('Max Width', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 400, 'max' => 1600],
                '%'  => ['min' => 10, 'max' => 100],
            ],
            'selectors'  => [
                '{{WRAPPER}} .wptg-wrapper' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;',
            ],
        ]);

        $this->add_spacing_controls('wptg_wrapper', '{{WRAPPER}} .wptg-wrapper');
        $this->add_bg_control('wptg_wrapper_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-wrapper');

        $this->add_control('wptg_top_section_heading', [
            'label'     => __('Top Section (Header Wrap)', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_spacing_controls('wptg_top_section', '{{WRAPPER}} .wptg-section-top');
        $this->add_bg_control('wptg_top_section_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-section-top');

        $this->add_control('wptg_body_section_heading', [
            'label'     => __('Body Section (Breadcrumb + Sidebar + Content Wrap)', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_spacing_controls('wptg_body_section', '{{WRAPPER}} .wptg-section-body');
        $this->add_bg_control('wptg_body_section_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-section-body');

        $this->add_control('wptg_inner_layout_heading', [
            'label'     => __('Sidebar / Content Row', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('wptg_sidebar_width', [
            'label'      => __('Sidebar Width', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 180, 'max' => 500],
                '%'  => ['min' => 15, 'max' => 50],
            ],
            'default'    => ['unit' => 'px', 'size' => 280],
            'selectors'  => [
                '{{WRAPPER}} .wptg-sidebar, {{WRAPPER}} .wptg-categories' => 'flex: 0 0 {{SIZE}}{{UNIT}}; min-width: 0;',
            ],
        ]);

        $this->add_responsive_control('wptg_body_gap', [
            'label'      => __('Gap Between Sidebar & Content', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'default'    => ['unit' => 'px', 'size' => 36],
            'selectors'  => [
                '{{WRAPPER}} .wptg-body'     => 'gap: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}}.wptg-mode-grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_spacing_controls('wptg_body_row', '{{WRAPPER}} .wptg-body, {{WRAPPER}}.wptg-mode-grid');

        $this->add_control('wptg_quick_colors_heading', [
            'label'     => __('Quick Theme Colors', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_color_primary', [
            'label'       => __('Primary Color', 'wp-taxonomy-grid'),
            'type'        => Controls_Manager::COLOR,
            'default'     => '#1b3a5c',
            'selectors'   => ['{{WRAPPER}} .wptg-wrapper' => '--wptg-color-primary: {{VALUE}};'],
            'description' => __('Affects the whole widget at once. Every section below also has its own independent overrides.', 'wp-taxonomy-grid'),
        ]);

        $this->add_control('wptg_color_accent', [
            'label'     => __('Accent Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#e3a73c',
            'selectors' => ['{{WRAPPER}} .wptg-wrapper' => '--wptg-color-accent: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function register_style_header_controls(): void
    {
        $this->start_controls_section('wptg_section_style_header', [
            'label'     => __('Header', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_spacing_controls('wptg_header', '{{WRAPPER}} .wptg-header');
        $this->add_bg_control('wptg_header_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header');

        $this->add_control('wptg_header_subtitle_heading', [
            'label'     => __('Subtitle', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_header_subtitle', __('Subtitle', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-subtitle');
        $this->add_box_control('wptg_header_subtitle_margin', __('Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-subtitle', 'margin');
        $this->add_alignment_control('wptg_header_subtitle_align', '{{WRAPPER}} .wptg-header-subtitle');

        $this->add_control('wptg_header_title_heading', [
            'label'     => __('Title', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_header_title', __('Title', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-title');
        $this->add_box_control('wptg_header_title_margin', __('Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-title', 'margin');
        $this->add_alignment_control('wptg_header_title_align', '{{WRAPPER}} .wptg-header-title');

        $this->add_control('wptg_header_divider_heading', [
            'label'     => __('Decorative Lines', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_header_line_start_heading', [
            'label' => __('Start Line (Left)', 'wp-taxonomy-grid'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_position_controls(
            'wptg_line_start',
            '{{WRAPPER}} .wptg-header-line-start',
            '--wptg-header-divider-width',
            '--wptg-header-divider-thickness'
        );

        $this->add_control('wptg_header_line_end_heading', [
            'label'     => __('End Line (Right)', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_position_controls(
            'wptg_line_end',
            '{{WRAPPER}} .wptg-header-line-end',
            '--wptg-header-divider-width',
            '--wptg-header-divider-thickness'
        );

        $this->add_control('wptg_header_divider_style_heading', [
            'label'     => __('Both Lines — Appearance', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_header_divider_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-header-line' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_header_divider_width', [
            'label'      => __('Length', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 300]],
            'default'    => ['unit' => 'px', 'size' => 120],
            // Also set the CSS var so Position > Horizontal "Center" (above)
            // can compute a correct half-width offset, not just a fallback.
            'selectors'  => ['{{WRAPPER}} .wptg-header-line' => 'width: {{SIZE}}{{UNIT}}; --wptg-header-divider-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('wptg_header_divider_thickness', [
            'label'      => __('Thickness', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 10]],
            'default'    => ['unit' => 'px', 'size' => 2],
            'selectors'  => ['{{WRAPPER}} .wptg-header-line' => 'height: {{SIZE}}{{UNIT}}; --wptg-header-divider-thickness: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_box_control('wptg_header_divider_margin', __('Margin (position offset)', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-line', 'margin');
        $this->add_box_control('wptg_header_divider_padding', __('Padding', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-header-line', 'padding');

        $this->end_controls_section();
    }

    private function register_style_breadcrumb_controls(): void
    {
        $this->start_controls_section('wptg_section_style_breadcrumb', [
            'label'     => __('Breadcrumb', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_spacing_controls('wptg_breadcrumb', '{{WRAPPER}} .wptg-breadcrumb');
        $this->add_bg_control('wptg_breadcrumb_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-breadcrumb');
        $this->add_alignment_control('wptg_breadcrumb_align', '{{WRAPPER}} .wptg-breadcrumb ol', 'justify-content');

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'wptg_breadcrumb_border',
            'label'    => __('Border (none by default — set Bottom width to add a divider)', 'wp-taxonomy-grid'),
            'selector' => '{{WRAPPER}} .wptg-breadcrumb',
        ]);

        $this->add_responsive_control('wptg_breadcrumb_valign', [
            'label'     => __('Vertical Alignment', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => __('Top', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-top'],
                'center'     => ['title' => __('Middle', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-middle'],
                'flex-end'   => ['title' => __('Bottom', 'wp-taxonomy-grid'), 'icon' => 'eicon-v-align-bottom'],
            ],
            'default'   => 'center',
            'selectors' => ['{{WRAPPER}} .wptg-breadcrumb ol' => 'align-items: {{VALUE}};'],
        ]);

        $this->add_control('wptg_breadcrumb_text_heading', [
            'label'     => __('Link Text', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_breadcrumb_text', __('Link Text', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-breadcrumb a, {{WRAPPER}} .wptg-breadcrumb-middle');

        $this->add_control('wptg_breadcrumb_current_heading', [
            'label'     => __('Current Item', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_breadcrumb_current', __('Current Item', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-breadcrumb-current');

        $this->add_control('wptg_breadcrumb_icon_heading', [
            'label'     => __('Separator Icon', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_breadcrumb_icon_color', [
            'label'     => __('Icon Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-breadcrumb-sep i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .wptg-breadcrumb-sep svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('wptg_breadcrumb_icon_size', [
            'label'      => __('Icon Size', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 6, 'max' => 30]],
            'selectors'  => [
                '{{WRAPPER}} .wptg-breadcrumb-sep i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .wptg-breadcrumb-sep svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_box_control('wptg_breadcrumb_icon_margin', __('Icon Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-breadcrumb-sep', 'margin');

        $this->end_controls_section();
    }

    private function register_style_search_controls(): void
    {
        $this->start_controls_section('wptg_section_style_search', [
            'label'     => __('Search Box', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_mode' => 'detail', 'wptg_source_mode' => 'single'],
        ]);

        $this->add_spacing_controls('wptg_search_box', '{{WRAPPER}} .wptg-search');

        $this->add_responsive_control('wptg_search_align', [
            'label'                => __('Alignment', 'wp-taxonomy-grid'),
            'type'                 => Controls_Manager::CHOOSE,
            'options'              => [
                'left'   => ['title' => __('Left', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-left'],
                'center' => ['title' => __('Center', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-center'],
                'right'  => ['title' => __('Right', 'wp-taxonomy-grid'), 'icon' => 'eicon-text-align-right'],
            ],
            'selectors_dictionary' => [
                'left'   => 'margin-left: 0; margin-right: auto;',
                'center' => 'margin-left: auto; margin-right: auto;',
                'right'  => 'margin-left: auto; margin-right: 0;',
            ],
            'selectors'            => ['{{WRAPPER}} .wptg-search' => '{{VALUE}}'],
        ]);

        $this->add_responsive_control('wptg_search_width', [
            'label'      => __('Width', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 100, 'max' => 600],
                '%'  => ['min' => 10, 'max' => 100],
            ],
            'default'    => ['unit' => '%', 'size' => 100],
            'selectors'  => ['{{WRAPPER}} .wptg-search' => 'max-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wptg_search_input_heading', [
            'label'     => __('Input', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_box_control('wptg_search_input_padding', __('Padding', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-search-input', 'padding');
        $this->add_text_style('wptg_search_input', __('Input Text', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-search-input');

        $this->add_bg_control('wptg_search_input_bg', __('Background', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-search-input');

        $this->add_control('wptg_search_input_border', [
            'label'     => __('Border Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-search-input' => 'border-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_search_input_radius', [
            'label'      => __('Border Radius', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 50]],
            'selectors'  => ['{{WRAPPER}} .wptg-search-input' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wptg_search_icon_heading', [
            'label'     => __('Icon', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_search_icon_color', [
            'label'     => __('Icon Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-search-icon i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .wptg-search-icon svg' => 'fill: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('wptg_search_icon_size', [
            'label'      => __('Size', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 32]],
            'selectors'  => [
                '{{WRAPPER}} .wptg-search-icon i'   => 'font-size: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .wptg-search-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_style_sidebar_controls(): void
    {
        $this->start_controls_section('wptg_section_style_sidebar', [
            'label'     => __('Sidebar / Accordion', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_box_control('wptg_term_group_margin', __('Space Between Categories (Margin)', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-term-group', 'margin');


        $this->add_control('wptg_chevron_heading', [
            'label'     => __('Accordion Arrow', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_chevron_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-term-group' => '--wptg-chevron-color: {{VALUE}};'],
        ]);

        $this->add_control('wptg_chevron_size', [
            'label'      => __('Size', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 3, 'max' => 12]],
            'default'    => ['unit' => 'px', 'size' => 5],
            'selectors'  => ['{{WRAPPER}} .wptg-term-group' => '--wptg-chevron-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wptg_chevron_margin', [
            'label'      => __('Spacing From Text', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .wptg-term-group' => '--wptg-chevron-margin: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wptg_post_item_heading', [
            'label'     => __('Post Item', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_box_control('wptg_post_item_margin', __('Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-post-li, {{WRAPPER}} .wptg-category-item', 'margin');
        $this->add_box_control('wptg_post_item_padding', __('Padding', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn', 'padding');

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'wptg_post_item_typography',
            'label'    => __('Typography', 'wp-taxonomy-grid'),
            'selector' => '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-name',
        ]);

        $this->add_alignment_control('wptg_post_item_align', '{{WRAPPER}} .wptg-post-trigger', 'text-align');

        $this->add_responsive_control('wptg_post_item_radius', [
            'label'      => __('Border Radius', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn' =>
                    'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'wptg_post_item_border',
            'label'    => __('Border', 'wp-taxonomy-grid'),
            'selector' => '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn',
        ]);

        $this->add_control('wptg_post_item_transition', [
            'label'      => __('Transition Duration (s)', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'range'      => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'default'    => ['size' => 0.15],
            'selectors'  => [
                '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn' => 'transition-duration: {{SIZE}}s;',
            ],
        ]);

        $this->start_controls_tabs('wptg_post_item_state_tabs');

        $this->start_controls_tab('wptg_post_item_tab_normal', ['label' => __('Normal', 'wp-taxonomy-grid')]);
        $this->add_control('wptg_post_normal_bg', [
            'label'     => __('Background', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('wptg_post_normal_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-name' => 'color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'wptg_post_normal_shadow',
            'selector' => '{{WRAPPER}} .wptg-post-trigger, {{WRAPPER}} .wptg-category-btn',
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_post_item_tab_hover', ['label' => __('Hover', 'wp-taxonomy-grid')]);
        $this->add_control('wptg_post_hover_bg', [
            'label'     => __('Background', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-post-trigger:hover, {{WRAPPER}} .wptg-category-btn:hover' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('wptg_post_hover_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-post-trigger:hover, {{WRAPPER}} .wptg-category-btn:hover' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('wptg_post_hover_border_color', [
            'label'     => __('Border Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['wptg_post_item_border_border!' => ''],
            'selectors' => ['{{WRAPPER}} .wptg-post-trigger:hover, {{WRAPPER}} .wptg-category-btn:hover' => 'border-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'wptg_post_hover_shadow',
            'selector' => '{{WRAPPER}} .wptg-post-trigger:hover, {{WRAPPER}} .wptg-category-btn:hover',
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_post_item_tab_active', ['label' => __('Active', 'wp-taxonomy-grid')]);
        $this->add_control('wptg_post_active_bg', [
            'label'     => __('Background', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-post-li.active .wptg-post-trigger, {{WRAPPER}} .wptg-category-item.active .wptg-category-btn, {{WRAPPER}} .wptg-term-merged.active .wptg-post-trigger' => 'background-color: {{VALUE}};',
            ],
        ]);
        $this->add_control('wptg_post_active_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wptg-post-li.active .wptg-post-trigger, {{WRAPPER}} .wptg-category-item.active .wptg-category-btn, {{WRAPPER}} .wptg-term-merged.active .wptg-post-trigger' => 'color: {{VALUE}};',
            ],
        ]);
        $this->add_control('wptg_post_active_border_color', [
            'label'     => __('Border Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['wptg_post_item_border_border!' => ''],
            'selectors' => ['{{WRAPPER}} .wptg-post-li.active .wptg-post-trigger, {{WRAPPER}} .wptg-category-item.active .wptg-category-btn, {{WRAPPER}} .wptg-term-merged.active .wptg-post-trigger' => 'border-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'wptg_post_active_shadow',
            'selector' => '{{WRAPPER}} .wptg-post-li.active .wptg-post-trigger, {{WRAPPER}} .wptg-category-item.active .wptg-category-btn, {{WRAPPER}} .wptg-term-merged.active .wptg-post-trigger',
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * "Category Divider (Accordion)" — the dash beside a normal category
     * heading. Fully independent from the merged row's divider, even
     * though both reuse the same ".wptg-term-divider" base class, because
     * each has its own modifier class ("-accordion" vs "-merged") AND its
     * own CSS variables for width/thickness/color.
     *
     * The divider is now a CHILD of its button (.wptg-term-toggle), so
     * "Position: Absolute" anchors to the button itself (CSS absolute
     * positioning always anchors to the nearest POSITIONED ancestor).
     */
    private function register_style_divider_accordion_controls(): void
    {
        $this->start_controls_section('wptg_section_style_divider_accordion', [
            'label'     => __('Category Divider (Accordion)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $selector = '{{WRAPPER}} .wptg-term-divider-accordion';

        $this->add_control('wptg_divider_acc_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_divider_acc_width', [
            'label'      => __('Length', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 14],
            // Also set the CSS var so Position > Horizontal "Center" below
            // can compute a correct half-width offset, not just a fallback.
            'selectors'  => [$selector => 'width: {{SIZE}}{{UNIT}}; --wptg-divider-acc-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('wptg_divider_acc_thickness', [
            'label'      => __('Thickness', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 10]],
            'default'    => ['unit' => 'px', 'size' => 2],
            'selectors'  => [$selector => 'height: {{SIZE}}{{UNIT}}; --wptg-divider-acc-thickness: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_box_control('wptg_divider_acc_margin', __('Margin', 'wp-taxonomy-grid'), $selector, 'margin');
        $this->add_box_control('wptg_divider_acc_padding', __('Padding', 'wp-taxonomy-grid'), $selector, 'padding');

        $this->add_control('wptg_divider_acc_position_heading', [
            'label'     => __('Position', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_position_controls('wptg_divider_acc', $selector, '--wptg-divider-acc-width', '--wptg-divider-acc-thickness');

        $this->end_controls_section();
    }

    /**
     * "Category Divider (Merged)" — same idea as the accordion one above,
     * fully independent, targeting ".wptg-term-divider-merged" inside
     * ".wptg-term-merged-trigger" (the merged button).
     */
    private function register_style_divider_merged_controls(): void
    {
        $this->start_controls_section('wptg_section_style_divider_merged', [
            'label'     => __('Category Divider (Merged)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $selector = '{{WRAPPER}} .wptg-term-divider-merged';

        $this->add_control('wptg_divider_merged_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_divider_merged_width', [
            'label'      => __('Length', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 14],
            'selectors'  => [$selector => 'width: {{SIZE}}{{UNIT}}; --wptg-divider-merged-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('wptg_divider_merged_thickness', [
            'label'      => __('Thickness', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 10]],
            'default'    => ['unit' => 'px', 'size' => 2],
            'selectors'  => [$selector => 'height: {{SIZE}}{{UNIT}}; --wptg-divider-merged-thickness: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_box_control('wptg_divider_merged_margin', __('Margin', 'wp-taxonomy-grid'), $selector, 'margin');
        $this->add_box_control('wptg_divider_merged_padding', __('Padding', 'wp-taxonomy-grid'), $selector, 'padding');

        $this->add_control('wptg_divider_merged_position_heading', [
            'label'     => __('Position', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_position_controls('wptg_divider_merged', $selector, '--wptg-divider-merged-width', '--wptg-divider-merged-thickness');

        $this->end_controls_section();
    }

    /**
     * "Category Name (Accordion)" — styling for a NORMAL category heading
     * (the one with the expand/collapse chevron). Scoped via the
     * ".wptg-term-toggle" ancestor so it never touches the merged row's
     * text, even though both share the ".wptg-term-name" class.
     */
    private function register_style_category_name_accordion_controls(): void
    {
        $this->start_controls_section('wptg_section_style_category_name_accordion', [
            'label'     => __('Category Name (Accordion)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $accordion_selector = '{{WRAPPER}} .wptg-term-toggle .wptg-term-name, {{WRAPPER}} .wptg-category-btn .wptg-category-name';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'wptg_cat_name_acc_typography',
            'label'    => __('Typography', 'wp-taxonomy-grid'),
            'selector' => $accordion_selector,
        ]);

        $this->add_alignment_control('wptg_cat_name_acc_align', '{{WRAPPER}} .wptg-term-toggle', 'text-align');

        $this->start_controls_tabs('wptg_cat_name_acc_state_tabs');

        $this->start_controls_tab('wptg_cat_name_acc_tab_normal', ['label' => __('Normal', 'wp-taxonomy-grid')]);
        $this->add_control('wptg_cat_name_acc_normal_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$accordion_selector => 'color: {{VALUE}};'],
        ]);
        $this->add_badge_box_controls('wptg_cat_name_acc_normal', $accordion_selector);
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_cat_name_acc_tab_hover', ['label' => __('Hover', 'wp-taxonomy-grid')]);
        $hover_selector = '{{WRAPPER}} .wptg-term-toggle:hover .wptg-term-name, {{WRAPPER}} .wptg-category-btn:hover .wptg-category-name';
        $this->add_control('wptg_cat_name_acc_hover_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$hover_selector => 'color: {{VALUE}};'],
        ]);
        $this->add_badge_box_controls('wptg_cat_name_acc_hover', $hover_selector);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * "Category Name (Merged)" — styling for the special row where a
     * category + its single matching-name post are shown as one combined
     * row (see wptg_term_post_names_match()). Scoped via the
     * ".wptg-term-merged-trigger" ancestor so it never touches a normal
     * accordion category heading.
     */
    private function register_style_category_name_merged_controls(): void
    {
        $this->start_controls_section('wptg_section_style_category_name_merged', [
            'label'     => __('Category Name (Merged)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_control('wptg_cat_name_merged_note', [
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __('Applies only to a category that has exactly one post sharing its name — shown as a single combined row instead of an expandable category.', 'wp-taxonomy-grid'),
            'content_classes' => 'elementor-descriptor',
        ]);

        // Target the BUTTON itself (.wptg-term-merged-trigger), not just the
        // text span inside it — margin/padding/background/radius need to
        // move and resize the whole clickable row (so it can be pushed
        // flush to the left edge on Active/Hover), not just the label text.
        $merged_selector = '{{WRAPPER}} .wptg-term-merged-trigger';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'wptg_cat_name_merged_typography',
            'label'    => __('Typography', 'wp-taxonomy-grid'),
            'selector' => $merged_selector,
        ]);

        $this->add_alignment_control('wptg_cat_name_merged_align', $merged_selector, 'text-align');

        $this->start_controls_tabs('wptg_cat_name_merged_state_tabs');

        $this->start_controls_tab('wptg_cat_name_merged_tab_normal', ['label' => __('Normal', 'wp-taxonomy-grid')]);
        $this->add_control('wptg_cat_name_merged_normal_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$merged_selector => 'color: {{VALUE}};'],
        ]);
        $this->add_badge_box_controls('wptg_cat_name_merged_normal', $merged_selector);
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_cat_name_merged_tab_hover', ['label' => __('Hover', 'wp-taxonomy-grid')]);
        $hover_selector = '{{WRAPPER}} .wptg-term-merged-trigger:hover';
        $this->add_control('wptg_cat_name_merged_hover_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$hover_selector => 'color: {{VALUE}};'],
        ]);
        $this->add_badge_box_controls('wptg_cat_name_merged_hover', $hover_selector);
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_cat_name_merged_tab_active', ['label' => __('Active', 'wp-taxonomy-grid')]);
        $active_selector = '{{WRAPPER}} .wptg-term-merged.active .wptg-term-merged-trigger';
        $this->add_control('wptg_cat_name_merged_active_color', [
            'label'     => __('Text Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$active_selector => 'color: {{VALUE}};'],
        ]);
        $this->add_badge_box_controls('wptg_cat_name_merged_active', $active_selector);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function register_style_content_controls(): void
    {
        $this->start_controls_section('wptg_section_style_content', [
            'label'     => __('Content Panel', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'single'],
        ]);

        $this->add_spacing_controls('wptg_content_panel', '{{WRAPPER}} .wptg-detail-container, {{WRAPPER}} .wptg-posts-container');
        $this->add_bg_control('wptg_content_panel_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-detail-container, {{WRAPPER}} .wptg-posts-container');

        $this->add_control('wptg_detail_title_heading', [
            'label'     => __('Post Title', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_detail_title', __('Post Title', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-detail-title, {{WRAPPER}} .wptg-post-title');
        $this->add_box_control('wptg_detail_title_margin', __('Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-detail-title', 'margin');
        $this->add_alignment_control('wptg_detail_title_align', '{{WRAPPER}} .wptg-detail-title, {{WRAPPER}} .wptg-post-title');

        $this->add_control('wptg_detail_body_heading', [
            'label'     => __('Post Content', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_detail_body', __('Post Content', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-detail-content, {{WRAPPER}} .wptg-post-excerpt');
        $this->add_alignment_control('wptg_detail_body_align', '{{WRAPPER}} .wptg-detail-content, {{WRAPPER}} .wptg-post-excerpt');

        $this->end_controls_section();
    }

    /**
     * "Sections Nav" — flat nav trigger (e.g. "Berita Terbaru",
     * "Pengumuman") label styling, Normal/Hover/Active. Same pattern as
     * the existing "Category Name (Accordion)" section.
     */
    private function register_style_sections_nav_controls(): void
    {
        $this->start_controls_section('wptg_section_style_sections_nav', [
            'label'     => __('Sections Nav (Flat Entries)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_text_style('wptg_nav_label', __('Label', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-section-nav-trigger');

        $this->start_controls_tabs('wptg_nav_label_state_tabs');

        $this->start_controls_tab('wptg_nav_label_tab_normal', ['label' => __('Normal', 'wp-taxonomy-grid')]);
        $this->add_badge_box_controls('wptg_nav_label_normal', '{{WRAPPER}} .wptg-section-nav-trigger');
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_nav_label_tab_hover', ['label' => __('Hover', 'wp-taxonomy-grid')]);
        $this->add_badge_box_controls('wptg_nav_label_hover', '{{WRAPPER}} .wptg-section-nav-trigger:hover');
        $this->end_controls_tab();

        $this->start_controls_tab('wptg_nav_label_tab_active', ['label' => __('Active', 'wp-taxonomy-grid')]);
        $this->add_badge_box_controls('wptg_nav_label_active', '{{WRAPPER}} .wptg-section-nav-trigger.active');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * "Numbered Feed" — Berita Terbaru / Pengumuman item styling: badge
     * number, title, date, "Baca Selengkapnya" affordance.
     */
    private function register_style_numbered_feed_controls(): void
    {
        $this->start_controls_section('wptg_section_style_numbered_feed', [
            'label'     => __('Numbered Feed', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_control('wptg_feed_badge_heading', [
            'label' => __('Badge Number', 'wp-taxonomy-grid'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_badge_box_controls('wptg_feed_badge', '{{WRAPPER}} .wptg-feed-badge');

        $this->add_control('wptg_feed_item_heading', [
            'label'     => __('Item', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_box_control('wptg_feed_item_padding', __('Padding', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-feed-item', 'padding');

        $this->add_control('wptg_feed_title_heading', [
            'label'     => __('Title', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_feed_title', __('Title', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-feed-title');

        $this->add_control('wptg_feed_date_heading', [
            'label'     => __('Date', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_feed_date', __('Date', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-feed-date');

        $this->add_control('wptg_feed_readmore_heading', [
            'label'     => __('"Baca Selengkapnya" Affordance', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_feed_readmore', __('"Baca Selengkapnya"', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-feed-readmore');
        $this->add_alignment_control('wptg_feed_readmore_align', '{{WRAPPER}} .wptg-feed-readmore');

        $this->end_controls_section();
    }

    /**
     * "Grid Thumbnail" — Galeri Foto caption styling. Grid layout itself
     * reuses the EXISTING .wptg-posts-grid CSS (no new grid rules); only
     * .wptg-post-item--photo's own caption area is new.
     */
    private function register_style_grid_thumbnail_controls(): void
    {
        $this->start_controls_section('wptg_section_style_grid_thumbnail', [
            'label'     => __('Grid Thumbnail (Photo Caption)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_spacing_controls('wptg_photo_caption', '{{WRAPPER}} .wptg-photo-caption');

        $this->add_control('wptg_photo_title_heading', [
            'label' => __('Title', 'wp-taxonomy-grid'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_text_style('wptg_photo_title', __('Title', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-photo-title');

        $this->add_control('wptg_photo_date_heading', [
            'label'     => __('Date', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_photo_date', __('Date', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-photo-date');

        $this->end_controls_section();
    }

    /**
     * "List Thumbnail" — Galeri Video item styling: title, meta line, and
     * the centered play-icon overlay's color/size. Position is always
     * dead-center by design (plain centered-absolute CSS via the two CSS
     * variables these controls set) — add_position_controls() is
     * deliberately NOT used here, since free positioning would be unused
     * complexity for an element that's always centered.
     */
    private function register_style_list_thumbnail_controls(): void
    {
        $this->start_controls_section('wptg_section_style_list_thumbnail', [
            'label'     => __('List Thumbnail (Video)', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_control('wptg_vid_title_heading', [
            'label' => __('Title', 'wp-taxonomy-grid'),
            'type'  => Controls_Manager::HEADING,
        ]);
        $this->add_text_style('wptg_vid_title', __('Title', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-list-thumbnail-title');

        $this->add_control('wptg_vid_meta_heading', [
            'label'     => __('Meta Line', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_text_style('wptg_vid_meta', __('Meta Line', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-list-thumbnail-meta');

        $this->add_control('wptg_vid_play_heading', [
            'label'     => __('Play Icon', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_vid_play_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-list-thumbnail-play' => '--wptg-play-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_vid_play_size', [
            'label'      => __('Size', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 20, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .wptg-list-thumbnail-play' => '--wptg-play-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /**
     * "Load More" — the paginated-list "Lihat Lebih Banyak" button, shared
     * by every flat_list/grid_thumbnail/list_thumbnail section.
     */
    private function register_style_load_more_controls(): void
    {
        $this->start_controls_section('wptg_section_style_load_more', [
            'label'     => __('Load More Button', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_text_style('wptg_load_more', __('Text', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-load-more');
        $this->add_box_control('wptg_load_more_margin', __('Margin', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-load-more', 'margin');
        $this->add_box_control('wptg_load_more_padding', __('Padding', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-load-more', 'padding');
        $this->add_bg_control('wptg_load_more_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-load-more');

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'wptg_load_more_border',
            'label'    => __('Border', 'wp-taxonomy-grid'),
            'selector' => '{{WRAPPER}} .wptg-load-more',
        ]);

        $this->end_controls_section();
    }

    /**
     * "Modal" — the shared lightbox's backdrop/panel/close button.
     *
     * Note: WptgModal's DOM is appended once to document.body, outside
     * any widget's {{WRAPPER}} — these selectors target
     * {{WRAPPER}} .wptg-modal-* purely for consistency with every other
     * Style section's selector convention; the generated CSS rule still
     * applies globally to .wptg-modal-backdrop/etc. regardless of
     * nesting. If multiple Content Navigator widgets exist on one page
     * with different modal color choices, the LAST one registered wins
     * (acceptable: this project's pages use exactly one per page).
     */
    private function register_style_modal_controls(): void
    {
        $this->start_controls_section('wptg_section_style_modal', [
            'label'     => __('Modal / Lightbox', 'wp-taxonomy-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wptg_source_mode' => 'sections'],
        ]);

        $this->add_bg_control('wptg_modal_backdrop_bg', __('Backdrop Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-modal-backdrop');

        $this->add_control('wptg_modal_panel_heading', [
            'label'     => __('Panel', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);
        $this->add_bg_control('wptg_modal_panel_bg', __('Background Color', 'wp-taxonomy-grid'), '{{WRAPPER}} .wptg-modal-panel');
        $this->add_spacing_controls('wptg_modal_panel', '{{WRAPPER}} .wptg-modal-panel');

        $this->add_control('wptg_modal_close_heading', [
            'label'     => __('Close Button', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('wptg_modal_close_color', [
            'label'     => __('Color', 'wp-taxonomy-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptg-modal-close' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wptg_modal_close_size', [
            'label'      => __('Size', 'wp-taxonomy-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 12, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .wptg-modal-close' => 'font-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings    = $this->get_settings_for_display();
        $source_mode = $settings['wptg_source_mode'] ?? 'single';
        $instance_id = 'wptg-' . $this->get_id();

        if ($source_mode === 'sections') {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is built with esc_* calls internally.
            echo wptg_render_sections_widget($settings, $instance_id);
            return;
        }

        $yes = static fn (string $key): string => ($settings[$key] ?? 'yes') === 'yes' ? 'true' : 'false';

        $raw_atts = [
            'post_type'          => $settings['wptg_post_type'] ?? '',
            'taxonomy'           => $settings['wptg_taxonomy'] ?? '',
            'mode'               => $settings['wptg_mode'] ?? 'detail',
            'active_first'       => $yes('wptg_active_first'),
            'class'              => '',
            'show_posts'         => $settings['wptg_show_posts'] ?? -1,
            'title'              => $settings['wptg_sidebar_title'] ?? '',
            'columns'            => $settings['wptg_columns'] ?? 'auto',
            'show_header'        => $yes('wptg_show_header'),
            'header_subtitle'    => $settings['wptg_header_subtitle'] ?? '',
            'header_title'       => $settings['wptg_header_title'] ?? '',
            'show_breadcrumb'    => $yes('wptg_show_breadcrumb'),
            'breadcrumb_home'    => $settings['wptg_breadcrumb_home'] ?? __('Beranda', 'wp-taxonomy-grid'),
            'breadcrumb_middle'  => $settings['wptg_breadcrumb_middle'] ?? '',
            'breadcrumb_current' => $settings['wptg_breadcrumb_current'] ?? '',
            'breadcrumb_icon'    => $settings['wptg_breadcrumb_icon'] ?? null,
            'show_search'        => $yes('wptg_show_search'),
            'search_placeholder' => $settings['wptg_search_placeholder'] ?? __('Cari...', 'wp-taxonomy-grid'),
            'search_icon'        => $settings['wptg_search_icon'] ?? null,
        ];

        // --- existing code, byte-for-byte unchanged below this line ---
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is built with esc_* calls internally.
        echo wptg_resolve_and_render($raw_atts, $instance_id);
    }
}
