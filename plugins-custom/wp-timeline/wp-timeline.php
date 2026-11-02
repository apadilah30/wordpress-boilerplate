<?php
/**
 * Plugin Name: Timeline
 * Description: Elementor widget — vertical zig-zag timeline (year + description cards around a center line), every part editable.
 * Version:     1.1.0
 * Author:      Apadilah
 * Text Domain: wp-timeline
 * Requires Plugins: elementor
 *
 * Drag "Timeline" from the Elementor widgets panel. Add items (year,
 * description, optional side override) in the Content tab; every visual part
 * (line, dots, cards, year, year line, description, spacing) is adjustable
 * from the Style tab, with separate values per breakpoint where it matters.
 *
 * Layout is pure CSS grid (no JavaScript): left card | center line | right
 * card. Cards of opposite sides overlap vertically (staggered, like the
 * reference design) while the dots stay on the center line. Below 768px the
 * timeline collapses to a single column with the line on the left.
 *
 * @package WpTimeline
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('WPTL_PATH', plugin_dir_path(__FILE__));
define('WPTL_URL', plugin_dir_url(__FILE__));

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'wptl-styles',
        WPTL_URL . 'assets/css/timeline.css',
        [],
        '1.1.0'
    );
});

add_action('elementor/elements/categories_registered', static function ($elements_manager): void {
    $elements_manager->add_category('wptl-category', [
        'title' => __('Timeline', 'wp-timeline'),
        'icon'  => 'fa fa-stream',
    ]);
});

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    require_once WPTL_PATH . 'includes/class-elementor-widget.php';
    $widgets_manager->register(new WPTL_Elementor_Widget());
});

/**
 * Decide which side each item sits on and which grid row it starts at.
 *
 * Side: an explicit "left"/"right" wins. Otherwise the item takes the
 * opposite side of the previous item (the first auto item uses
 * $first_side), so the zig-zag continues naturally after a manual override.
 *
 * Row: a card spans 2 grid rows. It starts one row below the previous item
 * (that is what staggers opposite-side cards), but never above the end of
 * the previous card on its OWN side, so two cards on the same side can't
 * overlap each other.
 *
 * @param array<int,array{year:string,description_html:string,side:string}> $items
 * @return array<int,array{year:string,description_html:string,side:string,row:int,index:int}>
 */
function wptl_resolve_item_layout(array $items, string $first_side): array
{
    $first_side = $first_side === 'left' ? 'left' : 'right';
    $next_free  = ['left' => 1, 'right' => 1];
    $prev_start = 0;
    $prev_side  = '';
    $resolved   = [];

    foreach (array_values($items) as $index => $item) {
        $requested = $item['side'];

        if ($requested === 'left' || $requested === 'right') {
            $side = $requested;
        } elseif ($prev_side === '') {
            $side = $first_side;
        } else {
            $side = $prev_side === 'left' ? 'right' : 'left';
        }

        $start = max($prev_start + 1, $next_free[$side]);

        $next_free[$side] = $start + 2;
        $prev_start       = $start;
        $prev_side        = $side;

        $resolved[] = [
            'year'             => $item['year'],
            'description_html' => $item['description_html'],
            'side'             => $side,
            'row'              => $start,
            'index'            => $index + 1,
        ];
    }

    return $resolved;
}

/**
 * Render the whole timeline.
 *
 * @param array<string,mixed> $atts {
 *     @type array<int,array<string,string>> $items          year, description_html, side
 *     @type string                          $first_side     "left" or "right"
 *     @type bool                            $show_year_line Show the short line next to each year
 * }
 */
function wptl_render_timeline(array $atts): string
{
    $items = [];
    foreach ((array) ($atts['items'] ?? []) as $row) {
        $year        = trim((string) ($row['year'] ?? ''));
        $description = trim((string) ($row['description_html'] ?? ''));

        if ($year === '' && $description === '') {
            continue;
        }

        $items[] = [
            'year'             => $year,
            'description_html' => $description,
            'side'             => (string) ($row['side'] ?? 'auto'),
        ];
    }

    if ($items === []) {
        return '<p class="wptl-empty">' . esc_html__('Add at least one timeline item in the widget settings.', 'wp-timeline') . '</p>';
    }

    $layout         = wptl_resolve_item_layout($items, (string) ($atts['first_side'] ?? 'right'));
    $count          = count($layout);
    $last_row       = $layout[$count - 1]['row'];
    $show_year_line = !empty($atts['show_year_line']);
    $list_class     = $count > 1 ? 'wptl-list wptl-has-line' : 'wptl-list';
    $list_style     = sprintf('--wptl-last-row: %d; --wptl-count: %d;', $last_row, $count);

    ob_start();
    ?>
    <div class="wptl-wrapper">
        <ol class="<?php echo esc_attr($list_class); ?>" style="<?php echo esc_attr($list_style); ?>">
            <?php foreach ($layout as $item): ?>
                <?php
                $item_style = sprintf('--wptl-row: %d; --wptl-i: %d;', $item['row'], $item['index']);
                $line_html  = $show_year_line ? '<span class="wptl-year-line" aria-hidden="true"></span>' : '';
                ?>
                <li class="wptl-item wptl-side-<?php echo esc_attr($item['side']); ?>"
                    style="<?php echo esc_attr($item_style); ?>">
                    <span class="wptl-dot" aria-hidden="true"></span>
                    <div class="wptl-card">
                        <?php if ($item['year'] !== ''): ?>
                            <div class="wptl-card-head">
                                <?php
                                // Left cards read "line — year" (right-aligned), right cards
                                // read "year — line" (left-aligned), like the reference design.
                                if ($item['side'] === 'left') {
                                    echo $line_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built above.
                                }
                                ?>
                                <span class="wptl-year"><?php echo esc_html($item['year']); ?></span>
                                <?php
                                if ($item['side'] !== 'left') {
                                    echo $line_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup built above.
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($item['description_html'] !== ''): ?>
                            <div class="wptl-desc"><?php echo wp_kses_post($item['description_html']); ?></div>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>
    <?php
    return (string) ob_get_clean();
}
