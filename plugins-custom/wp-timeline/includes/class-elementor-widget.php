<?php
/**
 * Elementor widget: Timeline.
 *
 * Thin wrapper around wptl_render_timeline() — all markup and layout logic
 * lives in wp-timeline.php. This class only declares the controls and maps
 * the saved settings into that function's input.
 *
 * @package WpTimeline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

class WPTL_Elementor_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'wptl_timeline';
    }

    public function get_title(): string
    {
        return __('Timeline', 'wp-timeline');
    }

    public function get_icon(): string
    {
        return 'eicon-time-line';
    }

    public function get_categories(): array
    {
        return ['wptl-category'];
    }

    public function get_keywords(): array
    {
        return ['timeline', 'history', 'sejarah', 'year', 'tahun', 'milestone'];
    }

    protected function register_controls(): void
    {
        $this->register_content_items_controls();
        $this->register_content_layout_controls();

        $this->register_style_container_controls();
        $this->register_style_line_controls();
        $this->register_style_dot_controls();
        $this->register_style_spacing_controls();
        $this->register_style_card_controls();
        $this->register_style_year_controls();
        $this->register_style_description_controls();
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    /** Typography group + matching Color control for one text element. */
    private function add_text_style(string $prefix, string $label, string $selector): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => $prefix . '_typography',
            'label'    => sprintf(__('%s Typography', 'wp-timeline'), $label),
            'selector' => $selector,
        ]);

        $this->add_control($prefix . '_color', [
            'label'     => sprintf(__('%s Color', 'wp-timeline'), $label),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);
    }

    /** Responsive Margin/Padding/Border-radius style DIMENSIONS control. */
    private function add_dimensions(string $name, string $label, string $selector, string $property): void
    {
        $this->add_responsive_control($name, [
            'label'      => $label,
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [$selector => $property . ': {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);
    }

    /**
     * Responsive px slider that writes a CSS variable (default target: the
     * list, where the stylesheet reads these variables).
     */
    private function add_css_var_slider(
        string $name,
        string $label,
        string $css_var,
        int $min,
        int $max,
        int $default,
        string $selector = '{{WRAPPER}} .wptl-list',
        string $description = ''
    ): void {
        $args = [
            'label'      => $label,
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => $min, 'max' => $max]],
            'default'    => ['unit' => 'px', 'size' => $default],
            'selectors'  => [$selector => $css_var . ': {{SIZE}}{{UNIT}};'],
        ];

        if ($description !== '') {
            $args['description'] = $description;
        }

        $this->add_responsive_control($name, $args);
    }

    /** Responsive px slider that writes one plain CSS property. */
    private function add_property_slider(
        string $name,
        string $label,
        string $selector,
        string $property,
        int $min,
        int $max,
        ?int $default = null
    ): void {
        $args = [
            'label'      => $label,
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => $min, 'max' => $max]],
            'selectors'  => [$selector => $property . ': {{SIZE}}{{UNIT}};'],
        ];

        if ($default !== null) {
            $args['default'] = ['unit' => 'px', 'size' => $default];
        }

        $this->add_responsive_control($name, $args);
    }

    /* ---------------------------------------------------------------------
     * CONTENT TAB
     * ------------------------------------------------------------------- */

    private function register_content_items_controls(): void
    {
        $this->start_controls_section('wptl_section_items', [
            'label' => __('Timeline Items', 'wp-timeline'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('wptl_year', [
            'label'       => __('Year / Label', 'wp-timeline'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'default'     => '',
            'placeholder' => '2024',
        ]);

        $repeater->add_control('wptl_description', [
            'label'   => __('Description', 'wp-timeline'),
            'type'    => Controls_Manager::WYSIWYG,
            'default' => '',
        ]);

        $repeater->add_control('wptl_side', [
            'label'   => __('Side', 'wp-timeline'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'auto'  => __('Auto (alternate)', 'wp-timeline'),
                'left'  => __('Left', 'wp-timeline'),
                'right' => __('Right', 'wp-timeline'),
            ],
            'default' => 'auto',
        ]);

        $this->add_control('wptl_items', [
            'label'       => __('Items', 'wp-timeline'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ wptl_year }}}',
            'default'     => [
                [
                    'wptl_year'        => '1913',
                    'wptl_description' => '<p>Pemerintah kolonial Hindia Belanda membentuk Kantor Sekretaris Komisi Imigrasi di Batavia untuk mengatur masuknya warga asing seiring meningkatnya perdagangan komoditas perkebunan di Indonesia.</p>',
                    'wptl_side'        => 'auto',
                ],
                [
                    'wptl_year'        => '1921',
                    'wptl_description' => '<p>Lembaga ini berkembang menjadi Immigratie Dienst (Dinas Imigrasi) di bawah Direktur Yustisi, yang bertugas mengawasi gerbang pelabuhan pesisir utara Jakarta dengan kebijakan pintu terbuka.</p>',
                    'wptl_side'        => 'auto',
                ],
                [
                    'wptl_year'        => '1950',
                    'wptl_description' => '<p>Seluruh wewenang keimigrasian resmi dialihkan kepada Pemerintah Indonesia pada tanggal 26 Januari, yang kini diperingati sebagai Hari Bhakti Imigrasi, dengan menerapkan kebijakan selektif demi keamanan nasional.</p>',
                    'wptl_side'        => 'auto',
                ],
                [
                    'wptl_year'        => '2003',
                    'wptl_description' => '<p>Kantor Imigrasi Jakarta Utara mulai menjalankan operasional pelayanan publik dan pengawasan wilayah pesisir secara mandiri, namun harus menyewa gedung operasional selama 21 tahun karena belum memiliki fasilitas permanen.</p>',
                    'wptl_side'        => 'auto',
                ],
                [
                    'wptl_year'        => '2024',
                    'wptl_description' => '<p>Instansi ini akhirnya menempati gedung permanen sendiri di Papanggo, Tanjung Priok, menggunakan aset lahan sitaan KPK, serta bertransisi ke bawah naungan Kementerian Imigrasi dan Pemasyarakatan.</p>',
                    'wptl_side'        => 'auto',
                ],
            ],
        ]);

        $this->end_controls_section();
    }

    private function register_content_layout_controls(): void
    {
        $this->start_controls_section('wptl_section_layout', [
            'label' => __('Layout', 'wp-timeline'),
        ]);

        $this->add_control('wptl_first_side', [
            'label'       => __('First Item Side', 'wp-timeline'),
            'type'        => Controls_Manager::SELECT,
            'options'     => [
                'right' => __('Right', 'wp-timeline'),
                'left'  => __('Left', 'wp-timeline'),
            ],
            'default'     => 'right',
            'description' => __('Applies to items whose Side is "Auto". They alternate from here. On mobile every item stacks on the right of the line.', 'wp-timeline'),
        ]);

        $this->add_control('wptl_show_year_line', [
            'label'   => __('Show Line Beside Year', 'wp-timeline'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * STYLE TAB
     * ------------------------------------------------------------------- */

    private function register_style_container_controls(): void
    {
        $this->start_controls_section('wptl_section_style_container', [
            'label' => __('Container', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('wptl_max_width', [
            'label'      => __('Max Width', 'wp-timeline'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 300, 'max' => 1600],
                '%'  => ['min' => 10, 'max' => 100],
            ],
            'selectors'  => [
                '{{WRAPPER}} .wptl-wrapper' => 'max-width: {{SIZE}}{{UNIT}}; margin-left: auto; margin-right: auto;',
            ],
        ]);

        $this->add_dimensions('wptl_container_padding', __('Padding', 'wp-timeline'), '{{WRAPPER}} .wptl-wrapper', 'padding');

        $this->add_control('wptl_container_bg', [
            'label'     => __('Background Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptl-wrapper' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function register_style_line_controls(): void
    {
        $this->start_controls_section('wptl_section_style_line', [
            'label' => __('Center Line', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('wptl_line_color', [
            'label'     => __('Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptl-list' => '--wptl-line-color: {{VALUE}};'],
        ]);

        $this->add_control('wptl_line_style', [
            'label'     => __('Line Style', 'wp-timeline'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                'solid'  => __('Solid', 'wp-timeline'),
                'dashed' => __('Dashed', 'wp-timeline'),
            ],
            'default'   => 'solid',
            'selectors' => ['{{WRAPPER}} .wptl-list' => '--wptl-line-style: {{VALUE}};'],
        ]);

        $this->add_css_var_slider('wptl_line_width', __('Thickness', 'wp-timeline'), '--wptl-line-width', 1, 20, 3);

        $this->end_controls_section();
    }

    private function register_style_dot_controls(): void
    {
        $this->start_controls_section('wptl_section_style_dot', [
            'label' => __('Dots', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('wptl_dot_color', [
            'label'     => __('Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptl-list' => '--wptl-dot-color: {{VALUE}};'],
        ]);

        $this->add_css_var_slider('wptl_dot_size', __('Size', 'wp-timeline'), '--wptl-dot-size', 6, 60, 22);

        $this->add_control('wptl_dot_ring_color', [
            'label'     => __('Ring Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wptl-list' => '--wptl-dot-ring-color: {{VALUE}};'],
        ]);

        $this->add_css_var_slider('wptl_dot_ring_width', __('Ring Width', 'wp-timeline'), '--wptl-dot-ring-width', 0, 20, 6);

        $this->add_css_var_slider(
            'wptl_dot_offset',
            __('Vertical Position', 'wp-timeline'),
            '--wptl-dot-offset',
            0,
            200,
            34,
            '{{WRAPPER}} .wptl-list',
            __('Distance from the top of a card to the center of its dot. Increase it if you enlarge the year text so the dot stays level with the year.', 'wp-timeline')
        );

        $this->end_controls_section();
    }

    private function register_style_spacing_controls(): void
    {
        $this->start_controls_section('wptl_section_style_spacing', [
            'label' => __('Spacing', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_css_var_slider(
            'wptl_center_width',
            __('Space Around Center Line', 'wp-timeline'),
            '--wptl-center-width',
            20,
            300,
            80,
            '{{WRAPPER}} .wptl-list',
            __('Width of the gap between the left and right cards (desktop/tablet).', 'wp-timeline')
        );

        $this->add_css_var_slider('wptl_row_gap', __('Vertical Gap Between Items', 'wp-timeline'), '--wptl-row-gap', 0, 120, 24);

        $this->add_css_var_slider(
            'wptl_mobile_col',
            __('Line Column Width (Mobile)', 'wp-timeline'),
            '--wptl-mobile-col',
            16,
            120,
            40,
            '{{WRAPPER}} .wptl-list',
            __('On mobile the line moves to the left; this is the space it and the dots occupy.', 'wp-timeline')
        );

        $this->end_controls_section();
    }

    private function register_style_card_controls(): void
    {
        $this->start_controls_section('wptl_section_style_card', [
            'label' => __('Cards', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .wptl-card';

        $this->add_dimensions('wptl_card_padding', __('Padding', 'wp-timeline'), $selector, 'padding');
        $this->add_dimensions('wptl_card_radius', __('Border Radius', 'wp-timeline'), $selector, 'border-radius');

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'wptl_card_border',
            'selector' => $selector,
        ]);

        $this->start_controls_tabs('wptl_card_state_tabs');

        $this->start_controls_tab('wptl_card_tab_normal', ['label' => __('Normal', 'wp-timeline')]);
        $this->add_control('wptl_card_bg', [
            'label'     => __('Background', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'background-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'wptl_card_shadow',
            'selector' => $selector,
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('wptl_card_tab_hover', ['label' => __('Hover', 'wp-timeline')]);
        $this->add_control('wptl_card_bg_hover', [
            'label'     => __('Background', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector . ':hover' => 'background-color: {{VALUE}};'],
        ]);
        $this->add_control('wptl_card_border_color_hover', [
            'label'     => __('Border Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['wptl_card_border_border!' => ''],
            'selectors' => [$selector . ':hover' => 'border-color: {{VALUE}};'],
        ]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'wptl_card_shadow_hover',
            'selector' => $selector . ':hover',
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function register_style_year_controls(): void
    {
        $this->start_controls_section('wptl_section_style_year', [
            'label' => __('Year', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_text_style('wptl_year', __('Year', 'wp-timeline'), '{{WRAPPER}} .wptl-year');

        $this->add_property_slider('wptl_year_space_below', __('Space Below Year', 'wp-timeline'), '{{WRAPPER}} .wptl-card-head', 'margin-bottom', 0, 60, 10);

        $this->add_control('wptl_year_line_heading', [
            'label'     => __('Line Beside Year', 'wp-timeline'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['wptl_show_year_line' => 'yes'],
        ]);

        $this->add_control('wptl_year_line_color', [
            'label'     => __('Color', 'wp-timeline'),
            'type'      => Controls_Manager::COLOR,
            'condition' => ['wptl_show_year_line' => 'yes'],
            'selectors' => ['{{WRAPPER}} .wptl-year-line' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_property_slider('wptl_year_line_length', __('Length', 'wp-timeline'), '{{WRAPPER}} .wptl-year-line', 'width', 0, 300, 80);
        $this->add_property_slider('wptl_year_line_thickness', __('Thickness', 'wp-timeline'), '{{WRAPPER}} .wptl-year-line', 'height', 0, 10, 2);
        $this->add_property_slider('wptl_year_line_gap', __('Gap Between Year and Line', 'wp-timeline'), '{{WRAPPER}} .wptl-card-head', 'gap', 0, 80, 16);

        $this->end_controls_section();
    }

    private function register_style_description_controls(): void
    {
        $this->start_controls_section('wptl_section_style_description', [
            'label' => __('Description', 'wp-timeline'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $selector = '{{WRAPPER}} .wptl-desc';

        $this->add_text_style('wptl_desc', __('Description', 'wp-timeline'), $selector);

        $this->add_responsive_control('wptl_desc_align', [
            'label'     => __('Alignment', 'wp-timeline'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'left'    => ['title' => __('Left', 'wp-timeline'), 'icon' => 'eicon-text-align-left'],
                'center'  => ['title' => __('Center', 'wp-timeline'), 'icon' => 'eicon-text-align-center'],
                'right'   => ['title' => __('Right', 'wp-timeline'), 'icon' => 'eicon-text-align-right'],
                'justify' => ['title' => __('Justify', 'wp-timeline'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => [$selector => 'text-align: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * RENDER
     * ------------------------------------------------------------------- */

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $items = [];
        foreach ((array) ($settings['wptl_items'] ?? []) as $row) {
            $items[] = [
                'year'             => (string) ($row['wptl_year'] ?? ''),
                'description_html' => $this->parse_text_editor((string) ($row['wptl_description'] ?? '')),
                'side'             => (string) ($row['wptl_side'] ?? 'auto'),
            ];
        }

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside wptl_render_timeline().
        echo wptl_render_timeline([
            'items'          => $items,
            'first_side'     => (string) ($settings['wptl_first_side'] ?? 'right'),
            'show_year_line' => ($settings['wptl_show_year_line'] ?? 'yes') === 'yes',
        ]);
    }
}
