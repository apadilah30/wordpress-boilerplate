<?php
/**
 * Elementor widget: Social Media Showcase.
 *
 * Thin wrapper around wpss_render_showcase() — markup lives in
 * wp-social-showcase.php.
 *
 * @package WpSocialShowcase
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;

class WPSS_Elementor_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'social_showcase';
    }

    public function get_title(): string
    {
        return __('Social Media Showcase', 'wp-social-showcase');
    }

    public function get_icon(): string
    {
        return 'eicon-share';
    }

    public function get_categories(): array
    {
        return ['wpss-category'];
    }

    public function get_keywords(): array
    {
        return ['social', 'instagram', 'facebook', 'twitter', 'tiktok', 'gallery', 'tabs'];
    }

    protected function register_controls(): void
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /* ---------------------------------------------------------------------
     * CONTENT TAB
     * ------------------------------------------------------------------- */

    private function register_content_controls(): void
    {
        $this->start_controls_section('wpss_section_general', [
            'label' => __('General', 'wp-social-showcase'),
        ]);

        $this->add_control('wpss_title', [
            'label'       => __('Title', 'wp-social-showcase'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('e.g. "Ikuti Perjalanan Kami"', 'wp-social-showcase'),
        ]);

        $this->add_control('wpss_open_text', [
            'label'   => __('"Open Account" Button Text', 'wp-social-showcase'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Buka', 'wp-social-showcase'),
        ]);

        $this->end_controls_section();

        $this->start_controls_section('wpss_section_platforms', [
            'label' => __('Platforms', 'wp-social-showcase'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('icon', [
            'label'   => __('Icon', 'wp-social-showcase'),
            'type'    => Controls_Manager::ICONS,
            'default' => ['value' => 'fab fa-instagram', 'library' => 'fa-brands'],
        ]);

        $repeater->add_control('label', [
            'label'   => __('Label', 'wp-social-showcase'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('Instagram', 'wp-social-showcase'),
        ]);

        $repeater->add_control('url', [
            'label'       => __('Account URL', 'wp-social-showcase'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('https://instagram.com/youraccount', 'wp-social-showcase'),
        ]);

        $repeater->add_control('gallery', [
            'label' => __('Gallery (up to 16 images, shown as a 4x4 grid)', 'wp-social-showcase'),
            'type'  => Controls_Manager::GALLERY,
            'default' => [],
        ]);

        $this->add_control('wpss_platforms', [
            'label'       => __('Platforms', 'wp-social-showcase'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'default'     => [
                [
                    'label' => __('Instagram', 'wp-social-showcase'),
                    'icon'  => ['value' => 'fab fa-instagram', 'library' => 'fa-brands'],
                ],
                [
                    'label' => __('X (Twitter)', 'wp-social-showcase'),
                    'icon'  => ['value' => 'fab fa-x-twitter', 'library' => 'fa-brands'],
                ],
                [
                    'label' => __('Facebook', 'wp-social-showcase'),
                    'icon'  => ['value' => 'fab fa-facebook', 'library' => 'fa-brands'],
                ],
                [
                    'label' => __('TikTok', 'wp-social-showcase'),
                    'icon'  => ['value' => 'fab fa-tiktok', 'library' => 'fa-brands'],
                ],
            ],
            'title_field' => '{{{ label }}}',
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * STYLE TAB
     * ------------------------------------------------------------------- */

    private function register_style_controls(): void
    {
        $this->start_controls_section('wpss_section_style_tabs', [
            'label' => __('Tabs', 'wp-social-showcase'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('wpss_tab_icon_size', [
            'label'      => __('Icon Size', 'wp-social-showcase'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 12, 'max' => 48]],
            'default'    => ['unit' => 'px', 'size' => 20],
            'selectors'  => ['{{WRAPPER}} .wpss-tab i, {{WRAPPER}} .wpss-tab svg' => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->start_controls_tabs('wpss_tab_state_tabs');

        $this->start_controls_tab('wpss_tab_tab_normal', ['label' => __('Normal', 'wp-social-showcase')]);
        $this->add_control('wpss_tab_normal_color', [
            'label'     => __('Icon Color', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wpss-tab i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .wpss-tab svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('wpss_tab_normal_bg', [
            'label'     => __('Background', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpss-tab' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->start_controls_tab('wpss_tab_tab_active', ['label' => __('Active', 'wp-social-showcase')]);
        $this->add_control('wpss_tab_active_color', [
            'label'     => __('Icon Color', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .wpss-tab.active i'   => 'color: {{VALUE}};',
                '{{WRAPPER}} .wpss-tab.active svg' => 'fill: {{VALUE}};',
            ],
        ]);
        $this->add_control('wpss_tab_active_bg', [
            'label'     => __('Background', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpss-tab.active' => 'background-color: {{VALUE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_responsive_control('wpss_tab_gap', [
            'label'      => __('Gap Between Tabs', 'wp-social-showcase'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'default'    => ['unit' => 'px', 'size' => 10],
            'selectors'  => ['{{WRAPPER}} .wpss-tabs' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('wpss_section_style_button', [
            'label' => __('Open Account Button', 'wp-social-showcase'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'wpss_open_btn_typography',
            'selector' => '{{WRAPPER}} .wpss-open-btn',
        ]);

        $this->add_control('wpss_open_btn_color', [
            'label'     => __('Text Color', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpss-open-btn' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('wpss_open_btn_bg', [
            'label'     => __('Background', 'wp-social-showcase'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpss-open-btn' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wpss_open_btn_radius', [
            'label'      => __('Border Radius', 'wp-social-showcase'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .wpss-open-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('wpss_open_btn_padding', [
            'label'      => __('Padding', 'wp-social-showcase'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .wpss-open-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->end_controls_section();

        $this->start_controls_section('wpss_section_style_gallery', [
            'label' => __('Gallery Grid', 'wp-social-showcase'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('wpss_gallery_columns', [
            'label'   => __('Columns', 'wp-social-showcase'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['2' => '2', '3' => '3', '4' => '4'],
            'default' => '4',
            'selectors' => ['{{WRAPPER}} .wpss-gallery' => '--wpss-gallery-columns: {{VALUE}};'],
        ]);

        $this->add_responsive_control('wpss_gallery_gap', [
            'label'      => __('Gap', 'wp-social-showcase'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'default'    => ['unit' => 'px', 'size' => 8],
            'selectors'  => ['{{WRAPPER}} .wpss-gallery' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('wpss_gallery_radius', [
            'label'      => __('Image Border Radius', 'wp-social-showcase'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .wpss-gallery-item' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $platforms = [];
        foreach ($settings['wpss_platforms'] ?? [] as $item) {
            $gallery = [];
            foreach ($item['gallery'] ?? [] as $image) {
                if (!empty($image['url'])) {
                    $gallery[] = ['id' => (int) ($image['id'] ?? 0), 'url' => $image['url']];
                }
            }

            $platforms[] = [
                'icon'    => $item['icon'] ?? null,
                'label'   => $item['label'] ?? '',
                'url'     => $item['url'] ?? '',
                'gallery' => $gallery,
            ];
        }

        $atts = [
            'title'     => $settings['wpss_title'] ?? '',
            'open_text' => $settings['wpss_open_text'] ?? __('Buka', 'wp-social-showcase'),
            'platforms' => $platforms,
            'class'     => '',
        ];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is built with esc_* calls internally.
        echo wpss_render_showcase($atts);
    }
}
