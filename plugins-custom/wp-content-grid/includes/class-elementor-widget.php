<?php
/**
 * Elementor widget: Content Grid.
 *
 * Thin wrapper around wpcg_render_grid() — all markup/query/link logic
 * lives in wp-content-grid.php.
 *
 * @package WpContentGrid
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class WPCG_Elementor_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'content_grid';
    }

    public function get_title(): string
    {
        return __('Content Grid', 'wp-content-grid');
    }

    public function get_icon(): string
    {
        return 'eicon-posts-grid';
    }

    public function get_categories(): array
    {
        return ['wpcg-category'];
    }

    public function get_keywords(): array
    {
        return ['news', 'video', 'berita', 'grid', 'posts', 'featured'];
    }

    /**
     * Media Popup script and styles live in the Taxonomy Grid plugin. They
     * are requested only when that plugin registered them, so this widget
     * still works (as plain links) when Taxonomy Grid is not active.
     *
     * @return string[]
     */
    public function get_script_depends(): array
    {
        return wp_script_is('wptg-media-popup', 'registered') ? ['wptg-media-popup'] : [];
    }

    /**
     * @return string[]
     */
    public function get_style_depends(): array
    {
        return wp_style_is('wptg-styles', 'registered') ? ['wptg-styles'] : [];
    }

    protected function register_controls(): void
    {
        $this->register_source_controls();
        $this->register_link_controls();
        $this->register_header_controls();
        $this->register_style_header_controls();
        $this->register_style_news_controls();
        $this->register_style_video_controls();
    }

    /* ---------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------- */

    private function add_text_style(string $prefix, string $label, string $selector): void
    {
        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => $prefix . '_typography',
            'label'    => sprintf(__('%s Typography', 'wp-content-grid'), $label),
            'selector' => $selector,
        ]);

        $this->add_control($prefix . '_color', [
            'label'     => sprintf(__('%s Color', 'wp-content-grid'), $label),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$selector => 'color: {{VALUE}};'],
        ]);
    }

    /* ---------------------------------------------------------------------
     * CONTENT TAB
     * ------------------------------------------------------------------- */

    private function register_source_controls(): void
    {
        $this->start_controls_section('wpcg_section_source', [
            'label' => __('Source', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_layout', [
            'label'   => __('Layout Style', 'wp-content-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'news'  => __('Featured News Grid (2 large + small, numbered)', 'wp-content-grid'),
                'video' => __('Video Grid (uniform + play icon)', 'wp-content-grid'),
            ],
            'default' => 'news',
        ]);

        $this->add_control('wpcg_post_type', [
            'label'       => __('Post Type', 'wp-content-grid'),
            'type'        => Controls_Manager::SELECT,
            'options'     => wpcg_get_post_type_options(),
            'default'     => 'post',
            'description' => __('E.g. "news" or "videos".', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_count', [
            'label'   => __('Number of Items', 'wp-content-grid'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 6,
            'min'     => 1,
            'max'     => 24,
        ]);

        $this->add_control('wpcg_featured_count', [
            'label'       => __('Featured Items (large cards)', 'wp-content-grid'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 2,
            'min'         => 0,
            'max'         => 6,
            'condition'   => ['wpcg_layout' => 'news'],
            'description' => __('How many of the first items show as large cards. The rest show as small cards.', 'wp-content-grid'),
        ]);

        $this->end_controls_section();
    }

    /**
     * Where a click on a card goes. Default = the post's own page (old
     * behavior). "Content Navigator page" sends the visitor to a page with a
     * Taxonomy Grid (Sections) widget, with the clicked item's section and
     * category already open (see wpcg_build_item_url()). "Media Popup" keeps
     * the permalink as href and adds data-wptg-media (see wpcg_build_item_link_attrs()).
     */
    private function register_link_controls(): void
    {
        $this->start_controls_section('wpcg_section_link', [
            'label' => __('Item Link', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_link_mode', [
            'label'   => __('Link Mode', 'wp-content-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => [
                'permalink'   => __('Post permalink', 'wp-content-grid'),
                'navigator'   => __('Content Navigator page (deep link)', 'wp-content-grid'),
                'media_popup' => __('Media Popup (video / photo)', 'wp-content-grid'),
            ],
            'default'     => 'permalink',
            'description' => __('Media Popup: a click opens a popup (video plays, photo shows) instead of leaving the page. Butuh plugin Taxonomy Grid aktif.', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_target_url', [
            'label'       => __('Target Page URL', 'wp-content-grid'),
            'type'        => Controls_Manager::URL,
            'placeholder' => '/news/',
            'label_block' => true,
            'default'     => ['url' => '', 'is_external' => false, 'nofollow' => false],
            'condition'   => ['wpcg_link_mode' => 'navigator'],
            'description' => __('The page that contains a Taxonomy Grid widget in Sections mode. Empty = links go to the post page instead.', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_link_taxonomy', [
            'label'       => __('Taxonomy', 'wp-content-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => 'news_category',
            'condition'   => ['wpcg_link_mode' => 'navigator'],
            'description' => __('Taxonomy slug that holds the category. Leave empty to guess "{post_type}_category". It must be the same Taxonomy as the matching row of the Taxonomy Grid widget on the target page.', 'wp-content-grid'),
        ]);

        $this->end_controls_section();
    }

    private function register_header_controls(): void
    {
        $this->start_controls_section('wpcg_section_header', [
            'label' => __('Section Header', 'wp-content-grid'),
        ]);

        $this->add_control('wpcg_show_header', [
            'label'   => __('Show Header', 'wp-content-grid'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('wpcg_title', [
            'label'       => __('Title', 'wp-content-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('e.g. "Berita Terbaru"', 'wp-content-grid'),
            'condition'   => ['wpcg_show_header' => 'yes'],
        ]);

        $this->add_control('wpcg_view_all_text', [
            'label'       => __('"View All" Link Text', 'wp-content-grid'),
            'type'        => Controls_Manager::TEXT,
            'placeholder' => __('e.g. "Lihat Berita Lebih Banyak"', 'wp-content-grid'),
            'condition'   => ['wpcg_show_header' => 'yes'],
        ]);

        $this->add_control('wpcg_view_all_url', [
            'label'       => __('"View All" Link URL', 'wp-content-grid'),
            'type'        => Controls_Manager::URL,
            'placeholder' => __('https://your-site.com/news', 'wp-content-grid'),
            'condition'   => ['wpcg_show_header' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* ---------------------------------------------------------------------
     * STYLE TAB
     * ------------------------------------------------------------------- */

    private function register_style_header_controls(): void
    {
        $this->start_controls_section('wpcg_section_style_header', [
            'label' => __('Section Header', 'wp-content-grid'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_text_style('wpcg_title_style', __('Title', 'wp-content-grid'), '{{WRAPPER}} .wpcg-title');
        $this->add_text_style('wpcg_view_all_style', __('View All Link', 'wp-content-grid'), '{{WRAPPER}} .wpcg-view-all');

        $this->end_controls_section();
    }

    private function register_style_news_controls(): void
    {
        $this->start_controls_section('wpcg_section_style_news', [
            'label'     => __('News Cards', 'wp-content-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wpcg_layout' => 'news'],
        ]);

        $this->add_responsive_control('wpcg_news_columns', [
            'label'   => __('Regular Card Columns', 'wp-content-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['2' => '2', '3' => '3', '4' => '4'],
            'default' => '4',
            'selectors' => [
                '{{WRAPPER}} .wpcg-news-grid' => '--wpcg-news-columns: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('wpcg_news_gap', [
            'label'      => __('Grid Gap', 'wp-content-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 24],
            'selectors'  => ['{{WRAPPER}} .wpcg-news-grid' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wpcg_news_card_bg', [
            'label'     => __('Card Background', 'wp-content-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpcg-news-card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('wpcg_news_badge_color', [
            'label'     => __('Badge Number Color', 'wp-content-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpcg-news-badge' => 'color: {{VALUE}};'],
        ]);

        $this->add_text_style('wpcg_news_date_style', __('Date', 'wp-content-grid'), '{{WRAPPER}} .wpcg-news-date');
        $this->add_text_style('wpcg_news_title_style', __('Card Title', 'wp-content-grid'), '{{WRAPPER}} .wpcg-news-card-title');
        $this->add_text_style('wpcg_news_readmore_style', __('Read More', 'wp-content-grid'), '{{WRAPPER}} .wpcg-news-read-more');

        $this->end_controls_section();
    }

    private function register_style_video_controls(): void
    {
        $this->start_controls_section('wpcg_section_style_video', [
            'label'     => __('Video Cards', 'wp-content-grid'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['wpcg_layout' => 'video'],
        ]);

        $this->add_responsive_control('wpcg_video_columns', [
            'label'   => __('Columns', 'wp-content-grid'),
            'type'    => Controls_Manager::SELECT,
            'options' => ['1' => '1', '2' => '2', '3' => '3', '4' => '4'],
            'default' => '2',
            'selectors' => [
                '{{WRAPPER}} .wpcg-video-grid' => '--wpcg-video-columns: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('wpcg_video_gap', [
            'label'      => __('Grid Gap', 'wp-content-grid'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['unit' => 'px', 'size' => 24],
            'selectors'  => ['{{WRAPPER}} .wpcg-video-grid' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wpcg_video_play_color', [
            'label'     => __('Play Icon Color', 'wp-content-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpcg-video-play' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('wpcg_video_play_bg', [
            'label'     => __('Play Icon Background', 'wp-content-grid'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .wpcg-video-play' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_text_style('wpcg_video_title_style', __('Card Title', 'wp-content-grid'), '{{WRAPPER}} .wpcg-video-card-title');
        $this->add_text_style('wpcg_video_meta_style', __('Meta Text', 'wp-content-grid'), '{{WRAPPER}} .wpcg-video-meta');

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();

        $view_all_url = '';
        if (!empty($settings['wpcg_view_all_url']['url'])) {
            $view_all_url = $settings['wpcg_view_all_url']['url'];
        }

        $atts = [
            'layout'          => $settings['wpcg_layout'] ?? 'news',
            'post_type'       => $settings['wpcg_post_type'] ?? '',
            'count'           => $settings['wpcg_count'] ?? 6,
            'featured_count'  => $settings['wpcg_featured_count'] ?? 2,
            'show_header'     => ($settings['wpcg_show_header'] ?? 'yes') === 'yes' ? 'true' : 'false',
            'title'           => $settings['wpcg_title'] ?? '',
            'view_all_text'   => $settings['wpcg_view_all_text'] ?? '',
            'view_all_url'    => $view_all_url,
            'class'           => '',
            'link_mode'       => $settings['wpcg_link_mode'] ?? 'permalink',
            'target_url'      => $settings['wpcg_target_url']['url'] ?? '',
            'target_external' => !empty($settings['wpcg_target_url']['is_external']),
            'target_nofollow' => !empty($settings['wpcg_target_url']['nofollow']),
            'link_taxonomy'   => $settings['wpcg_link_taxonomy'] ?? '',
        ];

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is built with esc_* calls internally.
        echo wpcg_render_grid($atts);
    }
}
