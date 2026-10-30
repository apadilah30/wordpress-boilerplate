# Design — "Content Navigator" (Taxonomy Grid v4.0.0)

> **Revision pass 2.** This document now also responds to the second
> `design-review.json` (verdict: `CHANGES_REQUESTED`, 2 HIGH / 4 MEDIUM / 4
> NIT), which reviewed round 1's revision below. The round-2 gaps were: the
> top-level sections/items still referenced `item.dataset.postType` and a
> mis-placed `data-post-id` that no renderer actually emitted (Finding 1),
> the 3 per-item renderer function **bodies** were still missing even
> though they were named everywhere (Finding 2), several client-side
> helpers (`markActiveNav`, `debounce`, `fetchSectionItems`'s body, the JS
> mirror of `wptg_section_list_class()`) were called but never defined, and
> `filterWithinSectionGroup()` mischaracterized a genuinely new function as
> "reuse" (Finding 3), `fetchSectionItems()` had no error handling or
> page-counter-rollback spec (Finding 4), the `custom-taxonomies.php`
> non-edit justification stated `news` has no taxonomy when it actually
> has `news_category` registered (Finding 5), plus 4 NITs (an undocumented
> third video-click branch, prose-only enqueue wiring, a missing autosave
> guard, and an unjustified `sanitize_url()` choice). All are now resolved
> below; see **"Response to review findings — round 2"** at the end for the
> finding-by-finding mapping. Round 1's own response table is kept below it
> for history.

## DECISION POINT check (required by the task brief)

No concrete technical blocker was found that would force splitting this into
a second plugin. The existing plugin already centralizes exactly the pieces
the new sections need (term-scoped queries, single-post AJAX detail render,
Style-tab control helpers, the enqueue/nonce wiring), and Elementor's
`REPEATER` control plus per-row `condition` is sufficient to express the
Sections schema. **Conclusion: stays ONE generalized widget, inside the same
`wp-taxonomy-grid` plugin.** No new plugin is created.

## Stack (locked)

PHP 8.2+, WordPress 6.x, Elementor/Elementor Pro (free Elementor APIs only —
`Widget_Base`, `Controls_Manager`, `REPEATER`, the existing `Group_Control`
helpers; no Pro-only APIs are introduced, so the feature works even if Pro
is later deactivated, matching the current plugin's dependency footprint).
Vanilla JS (no jQuery, no external modal/lightbox library — matches
`taxonomy-grid.js`'s existing vanilla `fetch()`-based style). Plain CSS with
custom properties (matches `taxonomy-grid.css`). No build step is introduced
(the plugin currently ships hand-written, unminified JS/CSS — kept as-is).

## File organization

| File | Change |
|---|---|
| `wp-taxonomy-grid.php` | Adds new shared `wptg_`-prefixed functions: `wptg_normalize_section_row()`, `wptg_count_published_posts()`, `wptg_resolve_default_active()` (pure), `wptg_render_sections_widget()`, `wptg_render_section_sidebar_group()`, `wptg_render_section_content_panel()`, `wptg_query_section_items()`, `wptg_render_section_item()` + its 3 per-style renderers, `wptg_has_more_pages()` (pure), `wptg_ajax_query_section_items()`. **Modifies one existing function**: `wptg_render_single_post_detail()` gains a new `bool $include_meta = false` parameter (default preserves today's exact output — see "Backward compatibility" below for why this is still a non-breaking change). No other existing function body changes. |
| `includes/class-elementor-widget.php` | Adds `Source Mode` control, the `Sections` repeater + its Content-tab conditionals, and 6 new Style-tab sections. **Modifies 5 existing Content-tab registration methods** and **9 of the 10 existing Style-tab registration methods** by adding a `condition` key to their controls/sections — see the two dedicated tables under "Backward compatibility mechanism" below. This is a correction from an earlier draft of this document, which incorrectly claimed these methods were "untouched." |
| `includes/class-video-meta-box.php` **(new)** | A small, self-contained admin-only concern — the `_video_external_url` meta box for the `videos` post type. Not "shared render logic" and not "Elementor widget controls," so it doesn't belong in either existing file. Implemented as plain functions (not a class) despite the `class-` filename prefix matching this plugin's include-naming convention, since there's no instance state to hold. |
| `assets/js/modal.js` **(new)** | The reusable lightbox component (`WptgModal`). Split out because it is genuinely reused 3+ times (Pengumuman, Galeri Foto, Galeri Video) and has zero dependency on the grid/accordion logic. Enqueued as a dependency of `wptg-scripts` — see the explicit `wp_enqueue_script()` diff below (resolves Finding 7). |
| `assets/js/taxonomy-grid.js` | Adds `initSectionsMode()` alongside the existing `initDetailMode()`/`initGridMode()`, plus new helpers `markActiveNav()`, `debounce()`, `wptgListClassFor()`, `wptgHasMorePages()`, `fetchSectionItems()`, `handleItemClick()`, `loadSinglePostIntoModal()`, `filterWithinSectionGroup()` (see "Client-side architecture" below for all of their bodies). **Modifies three existing pieces**, all behavior-preserving: (a) the per-term accordion toggle in `initDetailMode()` is refactored to call a new shared `bindAccordionToggle(toggleEl, groupEl)` helper instead of inlining the same 2 lines; (b) `loadSinglePost()` is generalized to look up its target container via `wrapper.querySelector('.wptg-detail-container, .wptg-sections-content')` instead of only `.wptg-detail-container`, and to optionally send `include_meta: '1'` — legacy detail mode's own call site is **not changed**, so it keeps finding only `.wptg-detail-container` and keeps omitting `include_meta`, meaning its AJAX request body and the resulting HTML are byte-identical to today; (c) `filterSidebar()`'s inner per-group matching logic is extracted into a new shared `filterTermGroup(groupEl, query)` helper that both it and the new `filterWithinSectionGroup()` call — `filterSidebar()`'s own observable behavior (and its callers) is unchanged, only its implementation is split (see "`filterWithinSectionGroup()`" below for why this is a real extraction, not a same-function-reuse claim). |
| `assets/css/taxonomy-grid.css` | Appends two new top-level blocks, `SECTIONS MODE` and `MODAL (shared lightbox)`, following the file's existing `DETAIL MODE` / `GRID MODE` block convention. No existing rule is edited. |
| `readme.txt` | New "Sections / Content Navigator" usage section + changelog entry. |
| `composer.json` (plugin) | `version` bump. |

No file is deleted or renamed. `web/app/mu-plugins/custom-taxonomies.php` is
**not** edited. Verified against its actual content: it registers
`gallery_category` on `gallery` and `videos_category` on `videos`, which is
exactly what sections 3 and 4 need. **Correction from an earlier draft of
this document:** that draft claimed "`news` and `announcements`
intentionally have no taxonomy" — this is only half true. The file *does*
register a `news_category` taxonomy on `news` (alongside `services_category`
and the two above); only `announcements` has no taxonomy registered at all.
This doesn't change the conclusion, because the Berita Terbaru section uses
`section_type = flat_list`, which never queries by taxonomy regardless of
whether one is registered — `news_category` simply goes unused by this
widget, same as it would for any CPT a `flat_list` section pointed at. The
file is still correct as-is and is not edited.

## Enqueue wiring (resolves Finding 7)

The existing `wp_enqueue_scripts` callback in `wp-taxonomy-grid.php` today
registers `wptg-scripts` with an empty deps array (`[]`). It gains one new
`wp_enqueue_script()` call for `modal.js`, registered *before* `wptg-scripts`
so it can be declared as that script's dependency (WordPress loads deps
before the dependent script regardless of registration order, but
registering the dependency first keeps the diff readable top-to-bottom):

```diff
+    wp_enqueue_script(
+        'wptg-modal',
+        WPTG_URL . 'assets/js/modal.js',
+        [],
+        '4.0.0',
+        true
+    );

     wp_enqueue_script(
         'wptg-scripts',
         WPTG_URL . 'assets/js/taxonomy-grid.js',
-        [],
+        ['wptg-modal'],
-        '3.9.1',
+        '4.0.0',
         true
     );
```

`WptgModal` (the global `window.WptgModal` object `modal.js` exposes) is
therefore guaranteed to exist by the time `taxonomy-grid.js` runs and calls
`WptgModal.open(...)` from `handleItemClick()`.

## Backward compatibility mechanism

A new top-level Content-tab control in the existing "Source" section:

```php
$this->add_control('wptg_source_mode', [
    'label'   => __('Source Mode', 'wp-taxonomy-grid'),
    'type'    => Controls_Manager::SELECT,
    'options' => [
        'single'   => __('Single — one Post Type + Taxonomy (legacy)', 'wp-taxonomy-grid'),
        'sections' => __('Sections — multiple sidebar sections (Content Navigator)', 'wp-taxonomy-grid'),
    ],
    'default' => 'single',
]);
```

Because `$settings['wptg_source_mode'] ?? 'single'` defaults to `'single'`,
a widget instance saved **before** this control existed (where
`get_settings_for_display()` fills in `'single'` as the control's own
registered default, since Elementor only persists non-default values)
takes the exact same branch it always has.

### Exactly which existing methods are touched, and how (corrects Findings 4 & 5)

An earlier draft of this document claimed "existing method bodies are
untouched" in the file-organization table while simultaneously requiring
`condition` keys to be added to existing controls elsewhere — a direct
contradiction. The real, accurate scope is:

**Content-tab methods (5 edited) — each existing `add_control()`/
`add_responsive_control()` call keeps its current `condition` (if any) and
gains `'wptg_source_mode' => 'single'` as an additional key (Elementor ANDs
multiple keys in one `condition` array — all must match):**

| Method | Edit |
|---|---|
| `register_source_controls()` | Add the new `wptg_source_mode` control (shown above). Add `'condition' => ['wptg_source_mode' => 'single']` to `wptg_post_type`, `wptg_taxonomy`, `wptg_mode`, `wptg_active_first`. |
| `register_header_content_controls()` | Section's `condition` changes from `['wptg_mode' => 'detail']` to `['wptg_mode' => 'detail', 'wptg_source_mode' => 'single']`. |
| `register_breadcrumb_content_controls()` | Same pattern as above. |
| `register_search_content_controls()` | Same pattern as above. |
| `register_grid_controls()` | Section's `condition` changes from `['wptg_mode' => 'grid']` to `['wptg_mode' => 'grid', 'wptg_source_mode' => 'single']`. |

**Style-tab methods (9 of 10 edited) — each existing section's
`start_controls_section()` call gains a `condition`:**

| Method | Edit |
|---|---|
| `register_style_container_controls()` | **No change.** Its selectors (`.wptg-wrapper`, `.wptg-sidebar`, `.wptg-body`, `.wptg-section-top`/`.wptg-section-body` aside) are deliberately reused by the new Sections markup (see "The Sections repeater schema" → sidebar/content markup below), so Max Width, Sidebar Width, Gap, and the quick Primary/Accent colors keep working identically in both modes. This is intentional DRY, not an oversight — see Finding 7's resolution below for the full reasoning. |
| `register_style_header_controls()` | `condition` changes from `['wptg_mode' => 'detail']` to `['wptg_mode' => 'detail', 'wptg_source_mode' => 'single']`. |
| `register_style_breadcrumb_controls()` | Same pattern. |
| `register_style_search_controls()` | Same pattern. |
| `register_style_sidebar_controls()` | Currently has **no** `condition` — add `'condition' => ['wptg_source_mode' => 'single']`. |
| `register_style_divider_accordion_controls()` | Same (add the condition fresh). |
| `register_style_divider_merged_controls()` | Same. |
| `register_style_category_name_accordion_controls()` | Same. |
| `register_style_category_name_merged_controls()` | Same. |
| `register_style_content_controls()` | Same. |

This is behavior-preserving (a `condition` only affects which controls the
*editor UI* shows; it never changes a control's saved value or the
rendered CSS output for a control that's already been set), but it is a
real edit to 14 existing method bodies total, not a no-op — stated
precisely here instead of the earlier "untouched" claim.

In `render()`:

```php
protected function render(): void
{
    $settings    = $this->get_settings_for_display();
    $source_mode = $settings['wptg_source_mode'] ?? 'single';
    $instance_id = 'wptg-' . $this->get_id();

    if ($source_mode === 'sections') {
        echo wptg_render_sections_widget($settings, $instance_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        return;
    }

    // --- existing code, byte-for-byte unchanged below this line ---
    $raw_atts = [ /* ... */ ];
    echo wptg_resolve_and_render($raw_atts, $instance_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
```

**Testable:** open the existing services page in the editor; confirm no
`wptg_source_mode` value is stored for it (Elementor only persists
non-default values); confirm the rendered HTML is byte-identical
before/after this change (diff the page source).

## The Sections repeater schema

```php
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
    // Resolves Finding 8: the fallback below is now documented IN the
    // editor, not only in readme.txt.
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
```

`section_content_style`'s options are not dynamically filtered by Elementor
itself (repeater fields can't swap option lists per-row without custom JS);
`wptg_normalize_section_row()` (below) is the single place that enforces
the fallback described in the control's own `description` text above.

### Why the 4 required sections map onto this schema

| Sidebar label | section_type | post_type | taxonomy | content_style | click_behavior | enable_search |
|---|---|---|---|---|---|---|
| Berita Terbaru | flat_list | news | — | numbered_feed | inline_detail | yes |
| Pengumuman | flat_list | announcements | — | numbered_feed | modal | yes |
| Galeri Foto | taxonomy_accordion | gallery | gallery_category | grid_thumbnail | modal | no (reuses existing accordion DOM filter if ever turned on, see below) |
| Galeri Video | taxonomy_accordion | videos | videos_category | list_thumbnail | redirect_or_popup_player | no |

`single_detail` is not used by any of the 4 required rows (none of them is
"services"-shaped), but it is fully implemented (not a stub) precisely so a
site editor could configure a 5th section exactly like "services" using the
new Sections UI instead of the legacy Single mode — see its full render
path below.

## `wptg_render_sections_widget()` — top-level renderer (resolves Finding 1)

### Step 1: normalize + validate each repeater row (pure-ish, one small query per row)

```php
/**
 * Normalize one "Sections" repeater row into a validated struct, or null
 * if its post_type/taxonomy no longer exist (e.g. deleted after the
 * widget was configured) — invalid rows are skipped entirely rather than
 * rendered broken.
 *
 * @param array<string,mixed> $row   Raw repeater row from Elementor settings.
 * @param int                 $index Row's position in the repeater (stable per save).
 * @return array<string,mixed>|null
 */
function wptg_normalize_section_row(array $row, int $index): ?array
{
    $post_type     = sanitize_key($row['section_post_type'] ?? '');
    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->public) {
        return null;
    }

    $type = ($row['section_type'] ?? 'flat_list') === 'taxonomy_accordion' ? 'taxonomy_accordion' : 'flat_list';

    $taxonomy = '';
    if ($type === 'taxonomy_accordion') {
        $taxonomy     = sanitize_key($row['section_taxonomy'] ?? '');
        $taxonomy_obj = taxonomy_exists($taxonomy) ? get_taxonomy($taxonomy) : null;
        if (!$taxonomy_obj || !in_array($post_type, $taxonomy_obj->object_type, true)) {
            return null;
        }
    }

    // Enforce the fallback documented in section_content_style's own
    // 'description' text above — this is the ONLY place that does so.
    $content_style = sanitize_key($row['section_content_style'] ?? '');
    if ($type === 'flat_list') {
        $content_style = 'numbered_feed';
    } elseif (!in_array($content_style, ['grid_thumbnail', 'list_thumbnail', 'single_detail'], true)) {
        $content_style = 'grid_thumbnail';
    }

    $click_behavior = sanitize_key($row['section_click_behavior'] ?? 'inline_detail');
    if (!in_array($click_behavior, ['inline_detail', 'modal', 'redirect_or_popup_player'], true)) {
        $click_behavior = 'inline_detail';
    }

    return [
        'index'          => $index,
        'label'          => sanitize_text_field($row['section_label'] ?? ''),
        'type'           => $type,
        'post_type'      => $post_type,
        'taxonomy'       => $taxonomy,
        'content_style'  => $content_style,
        'click_behavior' => $click_behavior,
        'enable_search'  => ($row['section_enable_search'] ?? 'yes') === 'yes',
        'posts_per_page' => min(48, max(1, absint($row['section_posts_per_page'] ?? 6))),
    ];
}

/**
 * Count of 'publish' posts for a post type — just enough to know whether a
 * flat_list section has anything to show, without fetching the posts
 * themselves (that happens later via wptg_query_section_items()).
 */
function wptg_count_published_posts(string $post_type): int
{
    $counts = (array) wp_count_posts($post_type);
    return (int) ($counts['publish'] ?? 0);
}
```

### Step 2: the "first section with ≥1 item" lookahead — extracted as a pure function

This generalizes the legacy `$default_active_id` priority logic in
`wptg_render_detail_mode()` (today: first post of the first term) across N
heterogeneous sections. Extracted as a **pure** function (no `WP_Query`
calls of its own — it only reads fields the caller already queried) so it
is unit-testable with plain PHP arrays, no WordPress bootstrap required:

```php
/**
 * Given sections already annotated with 'has_items' (flat_list) or
 * 'terms'/'first_post_id' (taxonomy_accordion), pick the first one with
 * real content to show by default. Pure — no I/O.
 *
 * @param array<int,array<string,mixed>> $sections
 * @return array{section_index:int,term_id:int,post_id:int}|null
 */
function wptg_resolve_default_active(array $sections): ?array
{
    foreach ($sections as $section) {
        if ($section['type'] === 'flat_list') {
            if ($section['has_items']) {
                return ['section_index' => $section['index'], 'term_id' => 0, 'post_id' => 0];
            }
            continue;
        }

        if (empty($section['terms'])) {
            continue;
        }
        $first_term_id = (int) $section['terms'][0]->term_id;

        if ($section['content_style'] === 'single_detail') {
            if (($section['first_post_id'] ?? 0) > 0) {
                return ['section_index' => $section['index'], 'term_id' => $first_term_id, 'post_id' => (int) $section['first_post_id']];
            }
            continue; // this term has no posts; try the next section.
        }

        return ['section_index' => $section['index'], 'term_id' => $first_term_id, 'post_id' => 0];
    }

    return null;
}
```

### Step 3: the main renderer — I/O + markup, calling the two pure/small helpers above

```php
/**
 * Render the full "Sections" (Content Navigator) markup: a sidebar with N
 * sections (flat nav buttons or taxonomy accordions) + ONE shared content
 * panel showing whichever section/term/post is active.
 *
 * SSR vs AJAX-lazy-load: only the DEFAULT ACTIVE section's content panel
 * is rendered on the initial page load (mirrors the legacy detail mode's
 * `active_first` behavior — one section gets real content immediately for
 * perceived performance and a working no-JS fallback). Every other
 * section's items are fetched on first click via `wptg_query_items`
 * (flat_list/grid_thumbnail/list_thumbnail) or `wptg_get_single_post`
 * (single_detail) — see "Client-side initSectionsMode() architecture".
 *
 * @param array<string,mixed> $settings    Elementor get_settings_for_display() output.
 * @param string              $instance_id Stable instance id (same convention as wptg_resolve_and_render()).
 */
function wptg_render_sections_widget(array $settings, string $instance_id): string
{
    $rows     = $settings['wptg_sections'] ?? [];
    $sections = [];

    foreach ($rows as $i => $row) {
        $section = wptg_normalize_section_row($row, (int) $i);
        if ($section === null) {
            continue;
        }

        if ($section['type'] === 'flat_list') {
            $section['has_items'] = wptg_count_published_posts($section['post_type']) > 0;
        } else {
            $section['terms'] = wptg_get_terms_for_post_type($section['taxonomy'], $section['post_type']);

            if ($section['content_style'] === 'single_detail' && !empty($section['terms'])) {
                $first_term_id              = (int) $section['terms'][0]->term_id;
                $first_term_posts           = wptg_get_posts_for_term($first_term_id, $section['post_type'], $section['taxonomy']);
                $section['first_post_id']   = (int) ($first_term_posts[0]->ID ?? 0);
                // Cached so wptg_render_section_sidebar_group() doesn't re-query
                // the same term's posts a second time just below.
                $section['term_posts_cache'] = [$first_term_id => $first_term_posts];
            }
        }

        $sections[] = $section;
    }

    if (empty($sections)) {
        return '<p class="wptg-empty">' . esc_html__('No valid sections configured.', 'wp-taxonomy-grid') . '</p>';
    }

    $default_active = wptg_resolve_default_active($sections);

    ob_start();
    ?>
    <div class="wptg-wrapper wptg-mode-sections <?php echo esc_attr($settings['wptg_sections_class'] ?? ''); ?>"
         data-instance-id="<?php echo esc_attr($instance_id); ?>"
         data-mode="sections">
        <div class="wptg-body wptg-sections-body">
            <aside class="wptg-sidebar wptg-sections-sidebar">
                <?php foreach ($sections as $section): ?>
                    <?php echo wptg_render_section_sidebar_group($section, $default_active); ?>
                <?php endforeach; ?>
            </aside>

            <main class="wptg-sections-content"
                  data-section-loaded="<?php echo $default_active !== null ? 'true' : 'false'; ?>">
                <?php if ($default_active !== null): ?>
                    <?php echo wptg_render_section_content_panel($sections[$default_active['section_index']], $default_active); ?>
                <?php else: ?>
                    <div class="wptg-placeholder">
                        <p><?php esc_html_e('Belum ada konten.', 'wp-taxonomy-grid'); ?></p>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
```

Note the deliberate reuse of `.wptg-wrapper`, `.wptg-body`, `.wptg-sidebar`
class names from the legacy markup (not new section-specific class names)
for the two outermost structural elements — this is exactly what lets
`register_style_container_controls()` stay unconditional (see the table
above) instead of needing a 10th `condition` edit.

### Sidebar group renderer — flat nav button vs. taxonomy accordion (incl. `single_detail`'s nested posts)

```php
/**
 * Render one sidebar entry: either a flat nav button (flat_list) or a
 * taxonomy accordion (taxonomy_accordion). For content_style =
 * single_detail, the accordion nests individual POSTS per term — the same
 * shape as the legacy detail-mode sidebar — instead of just term buttons.
 *
 * @param array<string,mixed>                      $section        Normalized section (see wptg_normalize_section_row()).
 * @param array{section_index:int,term_id:int,post_id:int}|null $default_active Output of wptg_resolve_default_active().
 */
function wptg_render_section_sidebar_group(array $section, ?array $default_active): string
{
    $is_section_active = $default_active !== null && $default_active['section_index'] === $section['index'];

    ob_start();
    ?>
    <div class="wptg-section-group wptg-section-group--<?php echo esc_attr($section['type'] === 'flat_list' ? 'flat' : 'accordion'); ?>"
         data-section-index="<?php echo esc_attr($section['index']); ?>"
         data-section-type="<?php echo esc_attr($section['type']); ?>"
         data-post-type="<?php echo esc_attr($section['post_type']); ?>"
         data-taxonomy="<?php echo esc_attr($section['taxonomy']); ?>"
         data-content-style="<?php echo esc_attr($section['content_style']); ?>"
         data-click-behavior="<?php echo esc_attr($section['click_behavior']); ?>"
         data-posts-per-page="<?php echo esc_attr($section['posts_per_page']); ?>">

        <?php if ($section['enable_search']): ?>
            <div class="wptg-section-search">
                <input type="search" class="wptg-section-search-input"
                       placeholder="<?php esc_attr_e('Cari...', 'wp-taxonomy-grid'); ?>"
                       aria-label="<?php esc_attr_e('Cari...', 'wp-taxonomy-grid'); ?>">
            </div>
            <?php // Mirrors the legacy sidebar's .wptg-no-results — only ever shown by
                  // filterWithinSectionGroup() for a taxonomy_accordion section's own
                  // DOM-filtered search (flat_list sections re-query via AJAX instead
                  // and never need this element — see "Search on paginated flat_list
                  // sections" below). ?>
            <p class="wptg-section-no-results" style="display:none;"><?php esc_html_e('Tidak ditemukan.', 'wp-taxonomy-grid'); ?></p>
        <?php endif; ?>

        <?php if ($section['type'] === 'flat_list'): ?>
            <button type="button" class="wptg-section-nav-trigger <?php echo $is_section_active ? 'active' : ''; ?>"
                    aria-pressed="<?php echo $is_section_active ? 'true' : 'false'; ?>">
                <?php echo esc_html($section['label']); ?>
            </button>
        <?php else: ?>
            <button type="button" class="wptg-section-toggle" aria-expanded="<?php echo $is_section_active ? 'true' : 'false'; ?>">
                <span class="wptg-term-name"><?php echo esc_html($section['label']); ?></span>
                <span class="wptg-term-chevron" aria-hidden="true"></span>
            </button>
            <ul class="wptg-section-terms <?php echo $is_section_active ? 'open' : ''; ?>">
                <?php foreach ($section['terms'] as $term): ?>
                    <?php $is_term_active = $is_section_active && $default_active['term_id'] === (int) $term->term_id; ?>
                    <li class="wptg-section-term-li <?php echo $is_term_active ? 'active' : ''; ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>">
                        <?php if ($section['content_style'] === 'single_detail'): ?>
                            <?php
                            // Nests individual POSTS under each term — same
                            // shape as legacy detail mode. Reuses the cached
                            // query for the active term (built in
                            // wptg_render_sections_widget()) to avoid a
                            // double query; queries fresh for any other term.
                            $posts = $section['term_posts_cache'][(int) $term->term_id]
                                ?? wptg_get_posts_for_term((int) $term->term_id, $section['post_type'], $section['taxonomy']);
                            ?>
                            <button type="button" class="wptg-term-toggle" aria-expanded="<?php echo $is_term_active ? 'true' : 'false'; ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                            <ul class="wptg-term-posts <?php echo $is_term_active ? 'open' : ''; ?>">
                                <?php foreach ($posts as $post): ?>
                                    <?php $is_post_active = $is_term_active && $default_active['post_id'] === (int) $post->ID; ?>
                                    <li class="wptg-post-li <?php echo $is_post_active ? 'active' : ''; ?>">
                                        <button type="button" class="wptg-post-trigger"
                                                data-post-id="<?php echo esc_attr($post->ID); ?>"
                                                data-post-type="<?php echo esc_attr($section['post_type']); ?>"
                                                data-click-behavior="inline_detail"
                                                aria-pressed="<?php echo $is_post_active ? 'true' : 'false'; ?>">
                                            <?php echo esc_html($post->title); ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <button type="button" class="wptg-section-term-trigger" aria-pressed="<?php echo $is_term_active ? 'true' : 'false'; ?>">
                                <?php echo esc_html($term->name); ?>
                            </button>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
```

### Content panel renderer — SSR path, shared by the AJAX path's shape

```php
/**
 * Render the active section's content panel for the INITIAL page load.
 * The HTML shape produced here (the `.wptg-section-items-wrap` +
 * `.wptg-load-more` button) is exactly what the client reconstructs
 * around `wptg_ajax_query_section_items()`'s JSON `html` payload on a
 * later AJAX switch/search — one shape, two call sites (SSR here,
 * AJAX in `wptg_ajax_query_section_items()` below), not two shapes.
 */
function wptg_render_section_content_panel(array $section, array $active): string
{
    if ($section['content_style'] === 'single_detail') {
        return $active['post_id'] > 0
            ? wptg_render_single_post_detail($active['post_id'], true)
            : '<div class="wptg-placeholder"><p>' . esc_html__('Pilih salah satu item di samping.', 'wp-taxonomy-grid') . '</p></div>';
    }

    $query = wptg_query_section_items(
        $section['post_type'],
        $section['taxonomy'],
        $active['term_id'],
        $section['content_style'],
        1,
        $section['posts_per_page'],
        ''
    );

    $items_html = '';
    $i          = 0;
    foreach ($query->posts as $post) {
        $items_html .= wptg_render_section_item($post, $section['content_style'], $i + 1, $i === 0, $section['click_behavior']);
        $i++;
    }
    wp_reset_postdata();

    $has_more   = wptg_has_more_pages(1, $section['posts_per_page'], $query->found_posts);
    $list_class = wptg_section_list_class($section['content_style']);

    ob_start();
    ?>
    <div class="wptg-section-items-wrap"
         data-section-index="<?php echo esc_attr($section['index']); ?>"
         data-term-id="<?php echo esc_attr($active['term_id']); ?>"
         data-page="1">
        <div class="<?php echo esc_attr($list_class); ?>"><?php echo $items_html; ?></div>
        <?php if ($has_more): ?>
            <button type="button" class="wptg-load-more"><?php esc_html_e('Lihat Lebih Banyak', 'wp-taxonomy-grid'); ?></button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Maps a content_style to its list wrapper CSS class — single source of truth, mirrored client-side (see JS architecture below). */
function wptg_section_list_class(string $content_style): string
{
    return match ($content_style) {
        'grid_thumbnail' => 'wptg-posts-grid', // reuses the EXISTING grid CSS, no new grid layout rules needed.
        'list_thumbnail' => 'wptg-list-thumbnail',
        default           => 'wptg-numbered-feed',
    };
}
```

### Data-\* attribute catalog emitted by the renderers above

**Section-level** (one per sidebar group — describes the section itself, used to build the next AJAX request):

| Attribute | Element | Purpose |
|---|---|---|
| `data-section-index` | `.wptg-section-group`, `.wptg-section-items-wrap` | Correlates a sidebar group with its content-panel state entry |
| `data-section-type` | `.wptg-section-group` | `flat_list` \| `taxonomy_accordion` — picks DOM-filter vs. AJAX-re-query search path client-side |
| `data-post-type` / `data-taxonomy` | `.wptg-section-group` | Echoed back as AJAX query params when fetching a page of items for this section |
| `data-content-style` | `.wptg-section-group` | Picks the list CSS class client-side (mirrors `wptg_section_list_class()` — see its JS twin `wptgListClassFor()` below) |
| `data-posts-per-page` | `.wptg-section-group` | AJAX page size |
| `data-term-id` | `.wptg-section-term-li`, `.wptg-section-items-wrap` | Active-term tracking |
| `data-page` | `.wptg-section-items-wrap` | Seeds client-side pagination state on load |

**Item-level** (one per rendered post — emitted by `wptg_build_item_attrs()`, called from all 3 per-item renderers in "Per-item renderers" below, AND by the corrected `single_detail` sidebar `<button>` markup above). **Rule: every element matching `[data-click-behavior]` always carries `data-post-id` and `data-post-type` directly on itself** — this is what `handleItemClick()`'s `item.dataset.postId`/`item.dataset.postType` reads, and it is never left to be inherited from an ancestor `.wptg-section-group`, because a clicked item can be deeply nested (e.g. inside `.wptg-section-items-wrap`) while the section group describes the section in general, not the one clicked post:

| Attribute | Element | Purpose |
|---|---|---|
| `data-post-id` | every `[data-click-behavior]` item | The post this item represents — read by `handleItemClick()` for both `inline_detail` and `modal`('post') |
| `data-post-type` | every `[data-click-behavior]` item | Sent back to `wptg_get_single_post`/`wptg_query_items` for server-side ownership re-validation |
| `data-click-behavior` | every item (section default copied verbatim per row — see repeater schema) | `inline_detail` \| `modal` \| `redirect_or_popup_player` — read directly off the clicked element, never off an ancestor |
| `data-modal-type` | only items where `click_behavior = modal` or the `redirect_or_popup_player` fallback branches land on a modal | `post` \| `image` \| `video` — see "Modal type mapping" table |
| `data-image-src` / `data-item-title` / `data-item-date` | `grid_thumbnail` items with `data-modal-type="image"` | Modal's `'image'` payload |
| `data-external-url` | `list_thumbnail` items with a non-empty `_video_external_url` | `window.open()` target, no modal |
| `data-video-src` / `data-poster` | `list_thumbnail` items with `data-modal-type="video"` | Modal's `'video'` payload |

## Client-side `initSectionsMode()` architecture (resolves Finding 2)

### State shape

One plain object per widget instance, keyed by section index, built lazily
as sections are visited — **no cross-switch cache**: switching away from a
section and back always re-fetches page 1 fresh. This is a deliberate
simplicity choice (same as the legacy grid mode, which also always
re-fetches on category switch, never caches) rather than an oversight —
caching scroll/page position across section switches was not requested and
would add real state-invalidation complexity (e.g., stale "load more"
counts after a new post is published) for no asked-for benefit.

```js
// const state = { [sectionIndex]: { page: number, termId: number, search: string } };
```

### Append-vs-replace rule (single rule, no exceptions)

- **"Lihat Lebih Banyak" click** → **append** the response's item HTML to
  the existing `.wptg-section-items-wrap`'s list node; increment `page`.
- **Any other trigger** (switching to a different flat/term nav item, or a
  debounced search keystroke) → **replace** the entire
  `.wptg-sections-content` panel with a freshly built wrapper around the
  response; reset `page` to `1`.

### Click-decision logic (one function, reads `data-click-behavior`)

```js
function handleItemClick(wrapper, item) {
    const behavior = item.dataset.clickBehavior;

    if (behavior === 'inline_detail') {
        setActiveItem(wrapper, item);
        loadSinglePost(wrapper, item.dataset.postId, item.dataset.postType, /* includeMeta */ true);
        return;
    }

    if (behavior === 'modal') {
        if (item.dataset.modalType === 'image') {
            WptgModal.open('image', {
                src: item.dataset.imageSrc,
                title: item.dataset.itemTitle,
                date: item.dataset.itemDate,
            });
        } else {
            // modalType === 'post' (Pengumuman)
            loadSinglePostIntoModal(item.dataset.postId, item.dataset.postType);
        }
        return;
    }

    if (behavior === 'redirect_or_popup_player') {
        if (item.dataset.externalUrl) {
            window.open(item.dataset.externalUrl, '_blank', 'noopener');
        } else if (item.dataset.videoSrc) {
            WptgModal.open('video', { src: item.dataset.videoSrc, poster: item.dataset.poster || '' });
        } else {
            // Graceful fallback for a mis-configured video post (see FR-6):
            // show the normal post-detail modal instead of doing nothing.
            loadSinglePostIntoModal(item.dataset.postId, item.dataset.postType);
        }
    }
}

function loadSinglePostIntoModal(postId, postType) {
    fetch(wptg_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'wptg_get_single_post',
            nonce: wptg_ajax.nonce,
            post_id: postId,
            post_type: postType,
            include_meta: '1',
        }),
    })
        .then((r) => r.json())
        .then((data) => { if (data.success) WptgModal.open('post', { html: data.data.html }); });
}
```

### Sidebar navigation / term switch / load-more / search wiring

```js
function initSectionsMode(wrapper) {
    const sidebar = wrapper.querySelector('.wptg-sections-sidebar');
    const content = wrapper.querySelector('.wptg-sections-content');
    const state = {};

    // Seed state for whatever was server-rendered on initial load.
    const itemsWrap = content.querySelector('.wptg-section-items-wrap');
    if (itemsWrap) {
        state[itemsWrap.dataset.sectionIndex] = {
            page: parseInt(itemsWrap.dataset.page, 10) || 1,
            termId: parseInt(itemsWrap.dataset.termId, 10) || 0,
            search: '',
        };
    }

    // Accordion toggles (section-level and, for single_detail, nested term-level)
    // reuse the SAME shared helper as legacy detail mode's own accordion.
    sidebar.querySelectorAll('.wptg-section-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => bindAccordionToggle(toggle, toggle.closest('.wptg-section-group')));
    });
    sidebar.querySelectorAll('.wptg-term-toggle').forEach((toggle) => {
        toggle.addEventListener('click', () => bindAccordionToggle(toggle, toggle.closest('.wptg-section-term-li')));
    });

    // Flat nav buttons + accordion term buttons (grid_thumbnail/list_thumbnail only).
    sidebar.addEventListener('click', function (e) {
        const trigger = e.target.closest('.wptg-section-nav-trigger, .wptg-section-term-trigger');
        if (!trigger) return;

        const group = trigger.closest('.wptg-section-group');
        const termLi = trigger.closest('[data-term-id]');
        const sectionIndex = group.dataset.sectionIndex;
        const termId = termLi ? parseInt(termLi.dataset.termId, 10) : 0;

        markActiveNav(sidebar, trigger);
        state[sectionIndex] = { page: 1, termId: termId, search: '' };
        fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
    });

    // single_detail's nested post triggers reuse the normal item-click path.
    sidebar.addEventListener('click', function (e) {
        const postTrigger = e.target.closest('.wptg-post-trigger[data-click-behavior]');
        if (postTrigger) handleItemClick(wrapper, postTrigger);
    });

    content.addEventListener('click', function (e) {
        const loadMoreBtn = e.target.closest('.wptg-load-more');
        if (loadMoreBtn) {
            const wrap = loadMoreBtn.closest('.wptg-section-items-wrap');
            const sectionIndex = wrap.dataset.sectionIndex;
            state[sectionIndex].page += 1;
            const group = sidebar.querySelector('.wptg-section-group[data-section-index="' + sectionIndex + '"]');
            fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ true);
            return;
        }

        const item = e.target.closest('[data-click-behavior]');
        if (item) handleItemClick(wrapper, item);
    });

    // Per-section search: taxonomy_accordion sections reuse the EXISTING
    // DOM-filter pattern (scoped to just that section's own group — same
    // mechanism as the legacy sidebar search, unchanged); flat_list
    // sections trigger a fresh AJAX query (see "Search decision" below).
    sidebar.addEventListener('input', debounce(function (e) {
        const input = e.target.closest('.wptg-section-search-input');
        if (!input) return;

        const group = input.closest('.wptg-section-group');
        const query = input.value.trim().toLowerCase();

        if (group.dataset.sectionType === 'taxonomy_accordion') {
            filterWithinSectionGroup(group, query);
            return;
        }

        const sectionIndex = group.dataset.sectionIndex;
        state[sectionIndex] = { page: 1, termId: 0, search: input.value.trim() };
        fetchSectionItems(wrapper, group, state[sectionIndex], /* append */ false);
    }, 300));
}

function bindAccordionToggle(toggleEl, groupEl) {
    const isOpen = groupEl.classList.toggle('open');
    toggleEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}
```

### Helper functions referenced above (resolves Finding 3 — each was named but not defined)

**`markActiveNav(sidebar, trigger)`** — the Sections-mode equivalent of the
existing grid-mode click handler's `active`-class loop
(`categoriesList.querySelectorAll('.wptg-category-item').forEach(...)` in
today's `initGridMode()`). Clears `active`/`aria-pressed` from every nav
trigger in the sidebar, then marks the clicked one:

```js
function markActiveNav(sidebar, trigger) {
    sidebar.querySelectorAll('.wptg-section-nav-trigger, .wptg-section-term-trigger').forEach((el) => {
        el.classList.remove('active');
        el.setAttribute('aria-pressed', 'false');
    });
    trigger.classList.add('active');
    trigger.setAttribute('aria-pressed', 'true');
}
```

**`debounce(fn, wait)`** — a standard trailing-edge debounce, new utility
(the current file has no timing helper to reuse — verified by reading
`taxonomy-grid.js`, confirmed in the review). Declared once near the top of
the IIFE, available to both the new search wiring and (optionally, not
required) the legacy `filterSidebar()` input listener:

```js
function debounce(fn, wait) {
    let timeoutId = null;
    return function (...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => fn.apply(this, args), wait);
    };
}
```

**`wptgListClassFor(contentStyle)`** — the client-side mirror of the PHP
`wptg_section_list_class()`. Needed because a "load more"/switch/search
response's `data.html` (the AJAX JSON payload) only contains the `<article>`
items, not the wrapping `<div class="wptg-posts-grid">`/etc. — that wrapper
is built once client-side when a section is first switched to, so the
mapping must exist on both sides. Kept as a literal, manually-synced copy
of the PHP `match` arms (not generated), same as how `wptg_has_more_pages()`
below is mirrored — both are tiny enough that duplication is simpler and
less error-prone than a build step to share one source of truth, which
would be the only way to truly avoid the duplication given this plugin
ships no JS build/bundle step (see "Stack," which keeps it that way
deliberately):

```js
function wptgListClassFor(contentStyle) {
    if (contentStyle === 'grid_thumbnail') return 'wptg-posts-grid';
    if (contentStyle === 'list_thumbnail') return 'wptg-list-thumbnail';
    return 'wptg-numbered-feed';
}

// Mirrors wptg_has_more_pages() exactly — same formula, same meaning.
function wptgHasMorePages(page, postsPerPage, foundPosts) {
    return (page * postsPerPage) < foundPosts;
}
```

**`fetchSectionItems(wrapper, group, sectionState, append)`** — the one
generic request function backing section/term switch, "load more," and
flat-section search-re-query. Follows the **same** `fetch()`/`.catch()`
error-handling shape as the existing `loadSinglePost()`/`loadPostsGrid()`
(a visible `.wptg-empty` message on failure, no silent dead end), and
explicitly rolls back the pre-incremented page counter on a failed "load
more" request so a retry doesn't skip a page (resolves Finding 4):

```js
function fetchSectionItems(wrapper, group, sectionState, append) {
    const content = wrapper.querySelector('.wptg-sections-content');
    const target = append ? content.querySelector('.wptg-section-items-wrap') : content;

    target.classList.add('wptg-loading');

    fetch(wptg_ajax.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'wptg_query_items',
            nonce: wptg_ajax.nonce,
            post_type: group.dataset.postType,
            taxonomy: group.dataset.taxonomy || '',
            term_id: String(sectionState.termId || 0),
            content_style: group.dataset.contentStyle,
            click_behavior: group.dataset.clickBehavior,
            page: String(sectionState.page),
            posts_per_page: group.dataset.postsPerPage,
            search: sectionState.search || '',
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            target.classList.remove('wptg-loading');

            if (!data.success) {
                if (append) {
                    sectionState.page -= 1; // roll back: this page was never actually appended.
                }
                target.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>' + (data.data && data.data.message ? data.data.message : 'Error loading items.') + '</p></div>');
                return;
            }

            if (append) {
                const list = target.querySelector(':scope > div');
                list.insertAdjacentHTML('beforeend', data.data.html);
                target.dataset.page = String(sectionState.page);
                const loadMoreBtn = target.querySelector('.wptg-load-more');
                if (loadMoreBtn && !data.data.has_more) {
                    loadMoreBtn.remove();
                }
                return;
            }

            // Replace: rebuild the exact SSR shape so a later "load more" works identically.
            const listClass = wptgListClassFor(group.dataset.contentStyle);
            const loadMoreHtml = data.data.has_more
                ? '<button type="button" class="wptg-load-more">Lihat Lebih Banyak</button>'
                : '';
            content.innerHTML = '<div class="wptg-section-items-wrap" data-section-index="' + group.dataset.sectionIndex + '" data-term-id="' + (sectionState.termId || 0) + '" data-page="1">'
                + '<div class="' + listClass + '">' + data.data.html + '</div>'
                + loadMoreHtml
                + '</div>';
        })
        .catch((error) => {
            target.classList.remove('wptg-loading');
            if (append) {
                sectionState.page -= 1; // network/parse failure: also roll back.
            }
            target.insertAdjacentHTML('beforeend', '<div class="wptg-empty"><p>Error loading items.</p></div>');
            console.error('Taxonomy Grid AJAX Error:', error);
        });
}
```

`loadSinglePost()` is the **existing** function, generalized (see "File
organization" above) to target `.wptg-detail-container, .wptg-sections-content`
and to optionally include `include_meta`.

### `filterWithinSectionGroup()` — refactor, not reuse-as-is (corrects Finding 3's mischaracterization)

The existing `filterSidebar(wrapper, query)` operates over the **whole
wrapper's** `.wptg-term-group` elements and toggles one single, global
`.wptg-no-results` message — it has no per-group scoping and no notion of
`single_detail`'s two-level (term → post) nesting that a Sections-mode
search box needs. Calling the new function "reuse" of `filterSidebar()`
as-is would be inaccurate (it genuinely needs a different scope), so
instead `filterSidebar()`'s inner matching logic is **extracted** into a
new shared helper, `filterTermGroup(groupEl, query, noResultsEl)`, that
both the legacy function and the new one call — this is the actual
DRY/NFR-4-compliant fix, not a same-function reuse claim:

```js
// Shared core: given ONE .wptg-term-group-shaped element (works for both
// the legacy sidebar's groups and a single section's own term list),
// show/hide its posts by text match and return whether anything matched.
function filterTermGroup(groupEl, query) {
    const termName = groupEl.dataset.termName || '';
    let groupHasMatch = query === '' || termName.includes(query);

    groupEl.querySelectorAll('.wptg-post-li').forEach((li) => {
        const text = li.textContent.trim().toLowerCase();
        const match = query === '' || text.includes(query) || termName.includes(query);
        li.style.display = match ? '' : 'none';
        if (match) groupHasMatch = true;
    });

    if (query !== '' && groupHasMatch) {
        groupEl.classList.add('open');
    }
    groupEl.style.display = groupHasMatch ? '' : 'none';
    return groupHasMatch;
}

// Legacy detail-mode search — same observable behavior as before, now
// delegating its per-group work to the shared helper above.
function filterSidebar(wrapper, query) {
    let anyVisible = false;
    wrapper.querySelectorAll('.wptg-term-group').forEach((group) => {
        if (filterTermGroup(group, query)) anyVisible = true;
    });
    const noResults = wrapper.querySelector('.wptg-no-results');
    if (noResults) {
        noResults.style.display = (query !== '' && !anyVisible) ? '' : 'none';
    }
}

// New: scoped to ONE section's own group. Handles both shapes a
// taxonomy_accordion section can have: plain term buttons (Galeri
// Foto/Video — filterTermGroup's .wptg-post-li query simply finds none,
// which is harmless, groupHasMatch still falls back to the term-name
// check) and single_detail's nested term->post tree (reuses
// filterTermGroup's existing .wptg-post-li handling directly, since that
// markup shape is identical to the legacy sidebar's).
function filterWithinSectionGroup(group, query) {
    const termLis = group.querySelectorAll('.wptg-section-term-li');
    let anyVisible = query === '';

    termLis.forEach((li) => {
        if (filterTermGroup(li, query)) anyVisible = true;
    });

    const noResults = group.querySelector('.wptg-section-no-results');
    if (noResults) {
        noResults.style.display = (query !== '' && !anyVisible) ? '' : 'none';
    }
}
```

This is listed as a **modification** to `initDetailMode()`'s existing
`filterSidebar()` in the "File organization" table above (the extraction),
alongside the already-documented `bindAccordionToggle()` extraction —
both are small, behavior-preserving refactors that the new Sections code
then reuses, not silent duplicates.

## Search on paginated `flat_list` sections — AJAX re-query, not DOM filter

**Decision: search triggers a fresh AJAX query (page reset to 1), not a
client-side filter over already-loaded DOM nodes.**

Reasoning: `posts_per_page` caps the initial/appended DOM to N items; a
DOM-only filter can only ever search what has already been paged in, so a
search for a news article on page 3 (not yet loaded) would silently return
nothing — a correctness bug, not just a UX nitpick (this directly fails
acceptance criterion 5). Re-querying via the already-required generic AJAX
endpoint costs nothing extra in code, since `search` is just one more
optional `WP_Query` arg (`'s' => $search`) on the same query builder.
Debounced at 300ms client-side to avoid firing a request per keystroke.

This is the opposite of the sidebar accordion's own existing search box
(unchanged), which stays a DOM filter, because that list is never
paginated — all terms/posts are rendered up front server-side once, so
filtering what's already in the DOM is correct there. The same DOM-filter
approach is reused (generalized, not duplicated) for any `taxonomy_accordion`
section's own search box, including a hypothetical `single_detail` section
with search enabled, for the same reason: its term/post sidebar tree is
also rendered up front, never paginated.

## Modal type mapping (resolves Finding 6)

| section_type | content_style | click_behavior | `data-modal-type` |
|---|---|---|---|
| flat_list | numbered_feed | modal | `post` |
| taxonomy_accordion | grid_thumbnail | modal | `image` |
| taxonomy_accordion | list_thumbnail | redirect_or_popup_player, non-empty `_video_external_url` | n/a — `window.open`, no modal |
| taxonomy_accordion | list_thumbnail | redirect_or_popup_player, empty URL + attached video file | `video` |
| taxonomy_accordion | list_thumbnail | redirect_or_popup_player, empty URL + no attached video | `post` (graceful fallback) |
| any | any | inline_detail | n/a — no modal, inline swap |

Only `modal`-behavior items ever carry `data-modal-type`; it is set by the
per-item renderer at render time (see below), never computed client-side.

## The reusable modal/lightbox component (`assets/js/modal.js`)

A single IIFE exposing `window.WptgModal = { open(type, payload), close() }`.
Lazily builds its DOM on first `open()` call and appends it to
`document.body` once (so it isn't duplicated per widget instance on a page
with multiple Content Navigator widgets):

```html
<div class="wptg-modal" hidden>
  <div class="wptg-modal-backdrop"></div>
  <div class="wptg-modal-panel" role="dialog" aria-modal="true">
    <button class="wptg-modal-close" aria-label="Close">&times;</button>
    <div class="wptg-modal-body"></div>
  </div>
</div>
```

`open(type, payload)` switches on `type` to populate `.wptg-modal-body`,
building elements via `document.createElement` (never raw string
concatenation) so attacker-controlled text (titles, dates) can never break
out of an attribute/tag even if it contains quotes or angle brackets:

- `'image'` — `payload = { src, title, date }`. Creates an `<img>` with
  `src` set via `setAttribute`, and a caption built from `textContent`
  assignments (never `innerHTML`) for `title`/`date`.
- `'post'` — `payload = { html }`. `html` is the already-escaped, trusted
  output of `wptg_render_single_post_detail($post_id, true)` fetched via
  the **existing, minimally-generalized** `wptg_get_single_post` AJAX
  action (same trust boundary `loadSinglePost()` already relies on for the
  inline-swap case) — assigned via `innerHTML`, exactly like
  `loadSinglePost()` already does.
- `'video'` — `payload = { src, poster }`. Creates a `<video controls
  playsinline>` element with `src`/`poster` set via `setAttribute`. No
  other branch exists today (an embeddable-URL/`iframe` case is explicitly
  out of scope per `requirements.md` and this repo's own KISS rule — add it
  later only if a real requirement for it appears, rather than stubbing a
  dead comment now).

`close()` hides the modal (`hidden = true`), clears `.wptg-modal-body`
(so a stray `<video>` doesn't keep playing audio off-screen), and removes
the `keydown`/backdrop listeners added on open. Escape key and a click
directly on `.wptg-modal-backdrop` (not the panel) both call `close()`.

## The reusable generic AJAX "load more" / query handler

One new action, `wptg_query_items`, backs: (a) switching to a different
sidebar section/term, (b) "Lihat Lebih Banyak" pagination, and (c) a flat
section's search-box re-query — all three are "give me page N of post type
X [optionally scoped to taxonomy/term Y], optionally filtered by search
term Z, rendered as content-style W." `single_detail` never calls this
action — it has no pagination/search-re-query UI (see repeater schema) and
instead reuses `wptg_get_single_post` directly, same as legacy detail mode.

```php
function wptg_query_section_items(
    string $post_type,
    string $taxonomy,
    int $term_id,
    string $content_style,
    int $page,
    int $posts_per_page,
    string $search
): WP_Query {
    $args = [
        'post_type'      => $post_type,
        'post_status'    => 'publish',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ($search !== '') {
        $args['s'] = $search;
    }
    if ($taxonomy !== '' && $term_id > 0) {
        $args['tax_query'] = [[
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => $term_id,
        ]];
    }
    return new WP_Query($args);
}

/** Pure — no I/O. Shared by the SSR path above and the AJAX handler below. */
function wptg_has_more_pages(int $page, int $posts_per_page, int $found_posts): bool
{
    return ($page * $posts_per_page) < $found_posts;
}

function wptg_render_section_item(\WP_Post $post, string $content_style, int $display_number, bool $is_first_overall, string $click_behavior): string
{
    return match ($content_style) {
        'grid_thumbnail' => wptg_render_item_grid_thumbnail($post, $click_behavior),
        'list_thumbnail' => wptg_render_item_list_thumbnail($post, $click_behavior),
        default          => wptg_render_item_numbered_feed($post, $display_number, $is_first_overall, $click_behavior),
    };
}

/**
 * Consistent attribute rule for every per-item renderer below (resolves
 * Finding 1): the single clickable element for one item ALWAYS carries
 * data-post-id, data-post-type, and data-click-behavior directly on
 * itself — never left for the browser to infer from an ancestor. When
 * click_behavior is 'modal', the same element also carries
 * data-modal-type plus that type's payload attributes (see the Modal
 * type mapping table above). This is the exact rule
 * wptg_render_section_sidebar_group()'s single_detail branch above was
 * corrected to follow too (data-post-id/data-post-type moved from the
 * <li> onto the <button>).
 *
 * Build the shared data-* attribute string for one clickable item.
 *
 * @param array<string,string> $extra Additional raw `name="value"` pairs, already escaped by the caller.
 */
function wptg_build_item_attrs(int $post_id, string $post_type, string $click_behavior, array $extra = []): string
{
    $attrs = sprintf(
        ' data-post-id="%d" data-post-type="%s" data-click-behavior="%s"',
        $post_id,
        esc_attr($post_type),
        esc_attr($click_behavior)
    );

    foreach ($extra as $name => $value) {
        $attrs .= sprintf(' %s="%s"', $name, $value);
    }

    return $attrs;
}

/**
 * Numbered feed item — Berita Terbaru / Pengumuman (content_style = numbered_feed).
 * Thumbnail (left) + sequential badge number + date + clamped title +
 * "Baca Selengkapnya" affordance. The whole <article> is the single
 * clickable element (click_behavior lives on it directly, per the rule
 * above) — there is no separate inner link, so a screen reader / keyboard
 * user has one unambiguous target, matching how `.wptg-post-trigger`
 * already behaves as a single full-row button in the legacy sidebar.
 */
function wptg_render_item_numbered_feed(\WP_Post $post, int $display_number, bool $is_first_overall, string $click_behavior): string
{
    $extra = [];
    if ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-feed-item <?php echo $is_first_overall ? 'wptg-feed-item--featured' : ''; ?>" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-feed-thumb">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
            <?php endif; ?>
            <span class="wptg-feed-badge"><?php echo esc_html(sprintf('%02d', $display_number)); ?></span>
        </div>
        <div class="wptg-feed-body">
            <span class="wptg-feed-date"><?php echo esc_html(get_the_date('', $post->ID)); ?></span>
            <h3 class="wptg-feed-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
            <span class="wptg-feed-readmore"><?php esc_html_e('Baca Selengkapnya', 'wp-taxonomy-grid'); ?></span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Grid thumbnail item — Galeri Foto (content_style = grid_thumbnail).
 * Reuses the EXISTING `.wptg-post-item` box class from grid mode (border/
 * radius/hover transform already defined in taxonomy-grid.css) with a
 * `--photo` modifier for the caption layout, instead of a new unrelated
 * box class.
 */
function wptg_render_item_grid_thumbnail(\WP_Post $post, string $click_behavior): string
{
    $extra = [];
    if ($click_behavior === 'modal') {
        $image_src = wp_get_attachment_image_url(get_post_thumbnail_id($post->ID), 'large') ?: '';
        $extra['data-modal-type'] = 'image';
        $extra['data-image-src']  = esc_url($image_src);
        $extra['data-item-title'] = esc_attr(get_the_title($post->ID));
        $extra['data-item-date']  = esc_attr(get_the_date('', $post->ID));
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-post-item wptg-post-item--photo" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-photo-thumb">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium_large'); ?>
            <?php endif; ?>
        </div>
        <div class="wptg-photo-caption">
            <span class="wptg-photo-title"><?php echo esc_html(get_the_title($post->ID)); ?></span>
            <span class="wptg-photo-date"><?php echo esc_html(get_the_date('', $post->ID)); ?></span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * Vertical list item with play-icon overlay — Galeri Video
 * (content_style = list_thumbnail). This is the ONLY renderer that
 * branches on click_behavior = redirect_or_popup_player's 3-way logic
 * (external URL -> attached video -> graceful post-detail fallback), per
 * the Modal type mapping table above and FR-6/AC9 in requirements.md.
 */
function wptg_render_item_list_thumbnail(\WP_Post $post, string $click_behavior): string
{
    $extra = [];

    if ($click_behavior === 'redirect_or_popup_player') {
        $external_url = get_post_meta($post->ID, '_video_external_url', true);

        if (is_string($external_url) && $external_url !== '') {
            $extra['data-external-url'] = esc_url($external_url);
        } else {
            $video_attachments = get_attached_media('video', $post->ID);
            $video_attachment  = !empty($video_attachments) ? reset($video_attachments) : null;

            if ($video_attachment instanceof \WP_Post) {
                $extra['data-modal-type'] = 'video';
                $extra['data-video-src']  = esc_url(wp_get_attachment_url($video_attachment->ID));
                $poster_url               = get_the_post_thumbnail_url($post->ID, 'large');
                if ($poster_url) {
                    $extra['data-poster'] = esc_url($poster_url);
                }
            } else {
                // Graceful fallback (FR-6 point 4 / AC9): neither an
                // external URL nor a self-hosted attachment is set —
                // show the normal post-detail modal instead of a dead click.
                $extra['data-modal-type'] = 'post';
            }
        }
    } elseif ($click_behavior === 'modal') {
        $extra['data-modal-type'] = 'post';
    }

    $attrs = wptg_build_item_attrs($post->ID, $post->post_type, $click_behavior, $extra);

    ob_start();
    ?>
    <article class="wptg-list-thumbnail-item" tabindex="0" role="button" <?php echo $attrs; ?>>
        <div class="wptg-list-thumbnail-media">
            <?php if (has_post_thumbnail($post->ID)): ?>
                <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
            <?php endif; ?>
            <span class="wptg-list-thumbnail-play" aria-hidden="true"></span>
        </div>
        <div class="wptg-list-thumbnail-body">
            <h3 class="wptg-list-thumbnail-title"><?php echo esc_html(get_the_title($post->ID)); ?></h3>
            <span class="wptg-list-thumbnail-meta">
                <?php echo esc_html(get_the_date('', $post->ID)); ?>
                &middot;
                <?php echo esc_html(get_the_author_meta('display_name', (int) $post->post_author)); ?>
            </span>
        </div>
    </article>
    <?php
    return ob_get_clean();
}

function wptg_ajax_query_section_items(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');

    $post_type      = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    $taxonomy       = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
    $term_id        = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $content_style  = isset($_POST['content_style']) ? sanitize_key(wp_unslash($_POST['content_style'])) : 'numbered_feed';
    $click_behavior = isset($_POST['click_behavior']) ? sanitize_key(wp_unslash($_POST['click_behavior'])) : 'inline_detail';
    $page           = max(1, isset($_POST['page']) ? absint($_POST['page']) : 1);
    $posts_per_page = min(48, max(1, isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 6));
    $search         = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

    $post_type_obj = get_post_type_object($post_type);
    if (!$post_type_obj || !$post_type_obj->public) {
        wp_send_json_error(['message' => 'Invalid post type.']);
    }

    // single_detail is intentionally NOT in this whitelist — it never
    // reaches this action (see repeater schema + client architecture).
    if (!in_array($content_style, ['numbered_feed', 'grid_thumbnail', 'list_thumbnail'], true)) {
        wp_send_json_error(['message' => 'Invalid content style.']);
    }

    if ($taxonomy !== '') {
        if (!taxonomy_exists($taxonomy)) {
            wp_send_json_error(['message' => 'Invalid taxonomy.']);
        }
        $taxonomy_obj = get_taxonomy($taxonomy);
        if (!in_array($post_type, $taxonomy_obj->object_type, true)) {
            wp_send_json_error(['message' => 'Taxonomy not registered for this post type.']);
        }
        if ($term_id > 0 && !(get_term($term_id, $taxonomy) instanceof WP_Term)) {
            wp_send_json_error(['message' => 'Term not found for this taxonomy.']);
        }
    } else {
        $term_id = 0; // flat_list sections never take a term.
    }

    $query = wptg_query_section_items($post_type, $taxonomy, $term_id, $content_style, $page, $posts_per_page, $search);

    $display_offset = ($page - 1) * $posts_per_page;
    $html = '';
    $i = 0;
    foreach ($query->posts as $post) {
        $is_first_overall = ($page === 1 && $search === '' && $i === 0);
        $html .= wptg_render_section_item($post, $content_style, $display_offset + $i + 1, $is_first_overall, $click_behavior);
        $i++;
    }
    wp_reset_postdata();

    wp_send_json_success([
        'html'     => $html,
        'has_more' => wptg_has_more_pages($page, $posts_per_page, $query->found_posts),
        'page'     => $page,
    ]);
}
add_action('wp_ajax_wptg_query_items', 'wptg_ajax_query_section_items');
add_action('wp_ajax_nopriv_wptg_query_items', 'wptg_ajax_query_section_items');
```

This mirrors the existing `wptg_ajax_get_posts()`'s validation style
(`get_post_type_object()` + `taxonomy_exists()` + "is this taxonomy
actually registered for this post type" — the same check already used in
`wptg_resolve_and_render()`), so it is consistent with, not a stricter
reinvention of, this plugin's existing trust model: only `publish`-status,
`public` post types are ever queryable, exactly like today.

`wptg_ajax_get_single_post` (existing) is reused for both the
`inline_detail` swap (Berita Terbaru, `single_detail` nested posts) and the
`modal` `'post'`-type fetch (Pengumuman, video graceful-fallback) — see
"`wptg_render_single_post_detail()` modification" below for its one new
parameter.

## `wptg_render_single_post_detail()` modification (resolves Finding 4)

FR-3.4 (Berita Terbaru inline detail) and FR-4.3 (Pengumuman modal) both
require "hero image, title, date, author[, full content]" — more fields
than the legacy function renders today (title + content only). Rather than
writing a second, near-duplicate renderer (which NFR-4 forbids), the
existing function gains one new parameter, defaulting to today's exact
output so legacy Single-mode detail view is untouched:

```php
function wptg_render_single_post_detail(int $post_id, bool $include_meta = false): string
{
    $elementor_content = '';
    if (class_exists('\Elementor\Plugin')) {
        $elementor_content = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($post_id);
    }

    ob_start();
    ?>
    <article class="wptg-detail-post" data-post-id="<?php echo esc_attr($post_id); ?>">
        <?php if ($include_meta && has_post_thumbnail($post_id)): ?>
            <div class="wptg-detail-hero"><?php echo get_the_post_thumbnail($post_id, 'large'); ?></div>
        <?php endif; ?>

        <h2 class="wptg-detail-title"><?php echo esc_html(get_the_title($post_id)); ?></h2>

        <?php if ($include_meta): ?>
            <div class="wptg-detail-meta">
                <span class="wptg-detail-date"><?php echo esc_html(get_the_date('', $post_id)); ?></span>
                <span class="wptg-detail-author"><?php echo esc_html(get_the_author_meta('display_name', (int) get_post_field('post_author', $post_id))); ?></span>
            </div>
        <?php endif; ?>

        <div class="wptg-detail-content">
            <?php if (!empty($elementor_content)): ?>
                <?php echo $elementor_content; ?>
            <?php else: ?>
                <?php echo apply_filters('the_content', get_post_field('post_content', $post_id)); ?>
            <?php endif; ?>
        </div>
    </article>
    <?php
    return ob_get_clean();
}
```

Callers:

| Caller | `$include_meta` | Why |
|---|---|---|
| `wptg_render_detail_mode()` (legacy, unchanged call site) | `false` (omitted — default) | Preserves today's exact output for FR-1 backward compatibility. |
| `wptg_render_section_content_panel()` for `single_detail` | `true` | FR-2's `single_detail` style explicitly needs the richer fields. |
| `wptg_ajax_get_single_post()` | reads a new optional `$_POST['include_meta']` flag, `true` only if `'1'` | Legacy JS call site never sends this field → defaults `false` → byte-identical AJAX response for the legacy inline swap. New Sections-mode JS call sites (`loadSinglePost()`'s generalized version, `loadSinglePostIntoModal()`) always send `include_meta: '1'`. |

`wptg_ajax_get_single_post()`'s existing ownership check
(`$post->post_type === $post_type && $post->post_status === 'publish'`) is
unchanged — `include_meta` is a display toggle, not a security boundary
(it can only add fields already implied by the post the client was already
authorized to fetch in full).

## Per-item renderers and their Style-tab control reuse

`wptg_render_item_numbered_feed()`, `wptg_render_item_grid_thumbnail()`,
`wptg_render_item_list_thumbnail()` live in `wp-taxonomy-grid.php` next to
`wptg_render_single_post_detail()`, following the same pattern (build via
`ob_start()`/`ob_get_clean()`, `esc_html()`/`esc_attr()`/`esc_url()`
everywhere). Each emits `data-post-id`, `data-click-behavior` (copied
verbatim from the section's `click_behavior`), and — per the Modal type
mapping table above — `data-modal-type` plus the type-specific payload
attributes (`data-image-src`/`data-item-title`/`data-item-date` for
`image`; nothing extra for `post`, since the modal fetches via AJAX).

For `redirect_or_popup_player` (Galeri Video only — `wptg_render_item_list_thumbnail()`
is the only renderer that checks this), the item reads
`get_post_meta($post->ID, '_video_external_url', true)`. If non-empty, it
`esc_url()`s it into `data-external-url`. If empty, it looks up
`get_attached_media('video', $post->ID)` and, if a video attachment
exists, puts its `wp_get_attachment_url()` into `data-video-src` (plus the
post's featured image URL, if any, into `data-poster`) and sets
`data-modal-type="video"`. If neither exists, it sets
`data-modal-type="post"` instead (the graceful-fallback row in the mapping
table) so the click handler's existing `'post'` branch takes over with zero
extra client-side logic.

### Style-tab control reuse (new sections, all inside `includes/class-elementor-widget.php`)

Now that "Per-item renderers" above pins down the exact markup, every
helper call below names its real `$selector` argument — none of these are
placeholders:

| New Style section | Helpers reused (with selectors) |
|---|---|
| `wptg_section_style_sections_nav` (flat entry: label text + Normal/Hover/Active) | `add_text_style('wptg_nav_label', ..., '{{WRAPPER}} .wptg-section-nav-trigger')`; `add_badge_box_controls('wptg_nav_label_normal', '{{WRAPPER}} .wptg-section-nav-trigger')` / `..._hover` (`:hover`) / `..._active` (`.wptg-section-nav-trigger.active`) — identical pattern to the existing "Category Name (Accordion)" section |
| `wptg_section_style_numbered_feed` (badge number, title, date, "Baca Selengkapnya") | `add_badge_box_controls('wptg_feed_badge', '{{WRAPPER}} .wptg-feed-badge')` for the number badge; `add_text_style('wptg_feed_title', ..., '{{WRAPPER}} .wptg-feed-title')`, `add_text_style('wptg_feed_date', ..., '{{WRAPPER}} .wptg-feed-date')`, `add_text_style('wptg_feed_readmore', ..., '{{WRAPPER}} .wptg-feed-readmore')`; `add_box_control('wptg_feed_item_padding', ..., '{{WRAPPER}} .wptg-feed-item', 'padding')`; `add_alignment_control('wptg_feed_readmore_align', '{{WRAPPER}} .wptg-feed-readmore')` |
| `wptg_section_style_grid_thumbnail` (caption title/date) | `add_text_style('wptg_photo_title', ..., '{{WRAPPER}} .wptg-photo-title')`, `add_text_style('wptg_photo_date', ..., '{{WRAPPER}} .wptg-photo-date')`; `add_spacing_controls('wptg_photo_caption', '{{WRAPPER}} .wptg-photo-caption')` — grid layout itself reuses the **existing** `.wptg-posts-grid` CSS (see `wptg_section_list_class()`), no new grid CSS needed, only `.wptg-post-item--photo`'s own caption area is new |
| `wptg_section_style_list_thumbnail` (title, meta line, play-icon color/size) | `add_text_style('wptg_vid_title', ..., '{{WRAPPER}} .wptg-list-thumbnail-title')`, `add_text_style('wptg_vid_meta', ..., '{{WRAPPER}} .wptg-list-thumbnail-meta')`; plain `add_control('wptg_vid_play_color', [COLOR, selectors => ['{{WRAPPER}} .wptg-list-thumbnail-play' => '--wptg-play-color: {{VALUE}};']])` + `add_responsive_control('wptg_vid_play_size', [SLIDER, selectors => ['{{WRAPPER}} .wptg-list-thumbnail-play' => '--wptg-play-size: {{SIZE}}{{UNIT}};']])` (not `add_position_controls()` — the play icon is always dead-center over `.wptg-list-thumbnail-media` by design via plain centered-absolute CSS; exposing free positioning here would be unused complexity per this repo's KISS convention) |
| `wptg_section_style_load_more` (button) | `add_text_style('wptg_load_more', ..., '{{WRAPPER}} .wptg-load-more')`, `add_box_control('wptg_load_more_margin', ..., '{{WRAPPER}} .wptg-load-more', 'margin')`, `add_box_control('wptg_load_more_padding', ..., '{{WRAPPER}} .wptg-load-more', 'padding')`, `add_bg_control('wptg_load_more_bg', ..., '{{WRAPPER}} .wptg-load-more')`, `Group_Control_Border` on `'{{WRAPPER}} .wptg-load-more'` (already imported) |
| `wptg_section_style_modal` (backdrop, panel, close button) | `add_bg_control('wptg_modal_backdrop_bg', ..., '{{WRAPPER}} .wptg-modal-backdrop')`, `add_bg_control('wptg_modal_panel_bg', ..., '{{WRAPPER}} .wptg-modal-panel')`; `add_spacing_controls('wptg_modal_panel', '{{WRAPPER}} .wptg-modal-panel')`; plain `COLOR`/`SLIDER` controls targeting `'{{WRAPPER}} .wptg-modal-close'` for the close button (no existing helper fits a button glyph exactly, so two plain controls are added rather than forcing an ill-fitting helper) — **note:** `WptgModal`'s DOM is appended once to `document.body`, outside any widget's `{{WRAPPER}}`, so this section's selectors target `{{WRAPPER}} .wptg-modal-*` only for the sake of consistency with every other Style section's selector convention; Elementor's generated CSS rule still applies globally to `.wptg-modal-backdrop`/etc. regardless of nesting, since the modal markup is never actually inside any particular widget's wrapper element at the time styles are applied — if multiple Content Navigator widget instances exist on one page with different modal color choices, the LAST one registered wins (acceptable: this project's pages use exactly one per page, matching the reference design) |

All six new sections get `'tab' => Controls_Manager::TAB_STYLE,
'condition' => ['wptg_source_mode' => 'sections']` so they stay invisible
(and inert) for any widget instance still on legacy Single mode — the
mirror image of the 9 existing sections getting
`'wptg_source_mode' => 'single'` (see the table under "Backward
compatibility mechanism" above).

## `videos` external-URL meta field

**Meta key: `_video_external_url`** (leading underscore — a "protected",
non-quick-edit-visible custom field, matching how WordPress core itself
hides implementation-detail meta from the generic Custom Fields UI).

Editor UX: a plain native meta box (`add_meta_box()`), not an ACF field
group — chosen over ACF (even though ACF is active site-wide) because (a)
it needs zero admin configuration/import step for the human operator (no
ACF field group to create/export/sync across environments), and (b) this
plugin is meant to be portable/self-contained like its other features — it
already doesn't depend on ACF anywhere.

```php
add_action('add_meta_boxes', 'wptg_register_video_meta_box');
function wptg_register_video_meta_box(): void
{
    add_meta_box(
        'wptg_video_external_url',
        __('External Video URL', 'wp-taxonomy-grid'),
        'wptg_render_video_meta_box',
        'videos',
        'side'
    );
}

function wptg_render_video_meta_box(\WP_Post $post): void
{
    wp_nonce_field('wptg_video_meta_save', 'wptg_video_meta_nonce');
    $value = get_post_meta($post->ID, '_video_external_url', true);
    ?>
    <label for="wptg_video_external_url" class="screen-reader-text"><?php esc_html_e('External Video URL', 'wp-taxonomy-grid'); ?></label>
    <input type="url" id="wptg_video_external_url" name="wptg_video_external_url"
           value="<?php echo esc_attr($value); ?>" class="widefat"
           placeholder="https://youtube.com/watch?v=...">
    <p class="description"><?php esc_html_e('Leave empty for self-hosted videos (attach the video file to this post instead). If filled, clicking this video in the "Galeri Video" section redirects here in a new tab.', 'wp-taxonomy-grid'); ?></p>
    <?php
}

add_action('save_post_videos', 'wptg_save_video_meta_box');
function wptg_save_video_meta_box(int $post_id): void
{
    // Skip autosaves/revisions (WP core convention for save_post handlers,
    // resolves Finding 8) — an autosave fires this hook too but has no
    // corresponding meta-box form submitted for it, so there is nothing
    // useful to save; checking the nonce alone isn't enough to catch this
    // because WordPress's autosave request does NOT include our nonce
    // field at all, meaning the existing !isset(...) check below already
    // happens to return early for a plain autosave — this guard instead
    // protects the AUTOSAVE-WITH-STALE-FORM-DATA case (e.g. Heartbeat API
    // re-submitting cached field values), where skipping early avoids a
    // redundant identical meta write, not a security issue either way.
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!isset($_POST['wptg_video_meta_nonce']) || !wp_verify_nonce(wp_unslash($_POST['wptg_video_meta_nonce']), 'wptg_video_meta_save')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw_url = isset($_POST['wptg_video_external_url']) ? wp_unslash($_POST['wptg_video_external_url']) : '';
    // esc_url_raw() over sanitize_url() (resolves Finding 9): this is the
    // WordPress core / WPCS convention specifically for "sanitize a URL
    // immediately before persisting it to the database" (e.g. used
    // throughout wp-includes for option/meta values); sanitize_url() is
    // the older alias historically used for OUTPUT contexts (it's what
    // esc_url() used to be called), not storage — using esc_url_raw() here
    // matches the convention reviewers/linters expect for this exact use case.
    $clean_url = $raw_url !== '' ? esc_url_raw($raw_url) : '';
    update_post_meta($post_id, '_video_external_url', $clean_url);
}
```

Lives in `includes/class-video-meta-box.php` (plain functions, no class
state — see "File organization" above).

## AJAX endpoint summary (security)

| Action | Guest access | Nonce | Server-side re-validation |
|---|---|---|---|
| `wptg_get_single_post` (existing, parameter added — see above) | `wp_ajax_nopriv_` already present | `wptg_nonce` (existing) | `post->post_type === $post_type` and `post_status === 'publish'` (existing, unchanged); `include_meta` is read as an optional display flag, not used for any authorization decision |
| `wptg_get_posts` (existing, untouched) | `wp_ajax_nopriv_` already present | `wptg_nonce` (existing) | `get_post_type_object()` + `taxonomy_exists()` (existing, unchanged) |
| `wptg_query_items` (new) | `wp_ajax_nopriv_wptg_query_items` registered alongside `wp_ajax_wptg_query_items` | `wptg_nonce` via `check_ajax_referer()` | post type must exist and be `public`; `content_style` whitelisted to `numbered_feed`/`grid_thumbnail`/`list_thumbnail` (`single_detail` never reaches this action); if a taxonomy is supplied it must exist and be registered for that post type; if a term is supplied it must resolve via `get_term($term_id, $taxonomy)` (fails if the term belongs to a different taxonomy); query always forces `post_status => publish` — client input never reaches raw SQL (all through `WP_Query`/`get_term()`/`get_taxonomy()`) |
| Video meta save (`save_post_videos`) | n/a (admin-only) | `wptg_video_meta_save` nonce | `current_user_can('edit_post', $post_id)` |

No handler is registered only on `wp_ajax_*` without its `wp_ajax_nopriv_*`
counterpart — the exact bug class called out in the brief.

## Versioning

New version: **4.0.0**.

The best precedent in this plugin's own changelog is `2.0.0` ("Added
'detail' mode... Kept 'grid' mode for backward compatibility") — this
change is the same shape (additive, backward-compatible, a second whole
mode/source-type alongside the existing one), not `3.0.0` (which was
tagged `BREAKING` — shortcode removal, a different and stronger
justification than applies here). An earlier draft of this document
mis-cited `3.0.0`; `2.0.0` is the accurate precedent, and a MAJOR bump is
still warranted on its own terms regardless of precedent, since the
Content tab's shape genuinely grows (new top-level mode, 4 new AJAX
surfaces, a new meta box).

- `wp-taxonomy-grid.php` docblock: `Version: 4.0.0`
- Both `wp_enqueue_style()`/`wp_enqueue_script()` version strings: `'4.0.0'`
- `plugins-custom/wp-taxonomy-grid/composer.json`: `"version": "4.0.0"`
- `readme.txt`: `Stable tag: 4.0.0` + a new `= 4.0.0 =` changelog entry
  dated **2026-10-30** — this is the actual current date in this
  environment (verified via the shell's system clock at design time, not a
  placeholder; an earlier draft of this document was flagged for this date
  looking like a future placeholder, but it is today's real date here) —
  describing: the Source Mode toggle, the Sections repeater, the 4 required
  section configurations, the AJAX re-query search decision, the reusable
  modal, the generic load-more handler, and the `_video_external_url` meta
  key.

## Testability

- **Unit-testable (pure functions, no WP globals):**
  `wptg_normalize_section_row()`'s fallback branches given plain array
  fixtures; `wptg_resolve_default_active()` given pre-annotated section
  arrays (no `WP_Query` involved — this is the "first section/term/post
  with ≥1 item" algorithm, now directly testable); `wptg_has_more_pages()`;
  `wptg_section_list_class()`; the per-item renderers
  (`wptg_render_item_numbered_feed()` / `_grid_thumbnail()` /
  `_list_thumbnail()`) given a `WP_Post` fixture, asserting the correct
  `data-modal-type`/`data-external-url`/`data-video-src` per the mapping
  table.
- **Integration-testable (needs WP + a test DB):**
  `wptg_query_section_items()` against seeded posts/terms;
  `wptg_render_sections_widget()`'s end-to-end I/O (row normalization +
  term/post queries + default-active wiring) given seeded CPTs/taxonomies;
  the full `wptg_ajax_query_section_items()` handler's nonce/validation
  branches (valid nonce+invalid term, missing nonce, mismatched
  taxonomy/post-type, search with no matches); `wptg_render_single_post_detail()`'s
  `$include_meta` branches against a post with/without a featured image;
  the video meta box's save/sanitize round-trip.
- **Manual/E2E only (no headless browser available to this agent):**
  accordion expand/collapse visuals, the modal's Escape/backdrop-click
  close behavior, the "first item is bigger" CSS, drag-and-drop widget
  configuration in the Elementor editor itself, the append-vs-replace DOM
  behavior of `fetchSectionItems()`. These are covered by the manual
  verification steps in the final summary instead.

## Response to review findings — round 2 (current `design-review.json`)

| # | Severity | Resolution |
|---|---|---|
| 1 | HIGH | Addressed. Added `wptg_build_item_attrs()` plus an explicit stated rule — "every `[data-click-behavior]` element carries `data-post-id`/`data-post-type` directly on itself" — enforced by all 3 per-item renderers (Finding 2) and by the corrected `single_detail` sidebar markup (`data-post-id`/`data-post-type` moved from the `<li>` onto the `<button>`, which is the element the sidebar's `postTrigger` click listener actually passes to `handleItemClick()`). The data-\* attribute catalog table was split into "section-level" and "item-level" subsections to make this unambiguous. |
| 2 | HIGH | Addressed. Wrote full bodies for `wptg_render_item_numbered_feed()`, `wptg_render_item_grid_thumbnail()`, `wptg_render_item_list_thumbnail()` (with concrete CSS classes: `.wptg-feed-item`/`.wptg-feed-thumb`/`.wptg-feed-badge`/etc. for numbered feed; `.wptg-post-item--photo`/`.wptg-photo-*` for grid thumbnail, reusing the existing `.wptg-post-item` box; `.wptg-list-thumbnail-*` for the video list). The Style-tab reuse table was then updated to name every helper call's real `$selector` argument instead of leaving them as placeholders. |
| 3 | MEDIUM | Addressed. Defined `markActiveNav()`, `debounce()`, and the JS mirror `wptgListClassFor()` (plus its sibling `wptgHasMorePages()`, mirroring `wptg_has_more_pages()`) with full bodies. For `filterWithinSectionGroup()`, took the review's suggested option (a): extracted `filterSidebar()`'s inner per-group logic into a new shared `filterTermGroup(groupEl, query)` helper that both the legacy function (unchanged observable behavior) and the new, differently-scoped `filterWithinSectionGroup()` call — documented explicitly as a refactor-and-reuse, not a same-function "it already does this" claim. |
| 4 | MEDIUM | Addressed. `fetchSectionItems()` now has a full body: a `.catch()` and a `{success:false}` branch both show a `.wptg-empty` message (same visible-error pattern as the existing `loadSinglePost()`/`loadPostsGrid()`), and both explicitly decrement `sectionState.page` back on a failed "load more" append (`append === true`) before returning, so a retried click re-requests the same page instead of silently skipping one. |
| 5 | MEDIUM | Addressed. Corrected the non-edit justification for `custom-taxonomies.php`: it now states plainly that `news_category` **is** registered on `news` (only `announcements` has no taxonomy), and that the reason the Berita Terbaru section still doesn't need it is that `flat_list` sections never query by taxonomy regardless of whether one exists — not because none was registered. |
| 6 | NIT | Addressed. The 3-way `redirect_or_popup_player` fallback (external URL → attached video → graceful `post`-modal fallback when neither is set) is now spelled out as a bullet in `requirements.md` FR-6 point 4 and in AC9, matching what the "Modal type mapping" table and `wptg_render_item_list_thumbnail()` already implement. |
| 7 | NIT | Addressed. Added the "Enqueue wiring" section with the literal `diff` for the `wp_enqueue_scripts` callback: a new `wp_enqueue_script('wptg-modal', ...)` call plus `wptg-scripts`'s deps array changing from `[]` to `['wptg-modal']` and its version bumping to `4.0.0`. |
| 8 | NIT | Addressed. `wptg_save_video_meta_box()` now opens with `if (wp_is_post_autosave($post_id) \|\| wp_is_post_revision($post_id)) { return; }`, with an inline comment explaining it's a consistency/no-redundant-write guard, not a security boundary (the nonce check already covers that). |
| 9 | NIT | Addressed. Switched `sanitize_url()` to `esc_url_raw()` with an inline comment: `esc_url_raw()` is the WP core/WPCS convention for sanitizing a URL immediately before persisting it, whereas `sanitize_url()` (the modern alias for the older `clean_url()`) is conventionally used for output/display contexts. |

## Response to review findings — round 1 (prior `design-review.json`, for history)

| # | Severity | Resolution |
|---|---|---|
| 1 | HIGH | Addressed. Added the full `wptg_render_sections_widget()` section: step-by-step with `wptg_normalize_section_row()`, `wptg_resolve_default_active()` (pure), the main renderer, the sidebar-group renderer, the content-panel renderer, and the data-\* attribute catalog. SSR-vs-lazy, the lookahead algorithm, and the exact attributes are all now specified. |
| 2 | HIGH | Addressed. Added "Client-side `initSectionsMode()` architecture": the state shape, the single append-vs-replace rule (with the explicit "no cross-switch cache" decision and its rationale), the full click-decision function, and the sidebar/content event-wiring code. |
| 3 | HIGH | Addressed by implementing `single_detail` for real (option (a) from the review's suggested fix), not removing it. It reuses `wptg_render_single_post_detail()` directly (bypassing the AJAX "query items" whitelist entirely, by design — documented explicitly in both the AJAX handler section and the client architecture section) and nests individual posts per term in the sidebar, mirroring legacy detail mode. `requirements.md` was updated to state this explicitly and add 2 acceptance criteria. |
| 4 | HIGH | Addressed. `wptg_render_single_post_detail()` is now explicitly documented as modified, with a new `bool $include_meta = false` parameter — default preserves legacy output exactly; new callers (Sections mode) pass `true`. The file-organization table's "no existing function is modified" claim was corrected to name this one exception. |
| 5 | HIGH | Addressed. Added the "Exactly which existing methods are touched, and how" subsection with a precise table of the 5 Content-tab methods and their edits; the file-organization table's wording was corrected to match. |
| 6 | MEDIUM | Addressed. Added the "Modal type mapping" table covering all 4 required rows plus the video graceful-degradation case and the generic `inline_detail`/non-empty-URL rows. |
| 7 | MEDIUM | Addressed. Added the full 10-row Style-tab table (1 unconditioned + 9 conditioned) with reasoning for why Container stays unconditional (deliberate class-name reuse between legacy and Sections markup) rather than leaving it as a silent gap. |
| 8 | MEDIUM | Addressed. `section_content_style`'s control definition now carries a `description` string spelling out the valid combinations and the exact fallback, so it's visible in-editor, not just in `readme.txt`. `requirements.md` gained a matching acceptance criterion. |
| 9 | NIT | Addressed. "Versioning" now cites `2.0.0` as the precedent and explains why `3.0.0` was the wrong citation. |
| 10 | NIT | Investigated, not changed: the shell's system clock in this environment reads `2026-10-30` (confirmed via `echo %date%` at design time), so the changelog date is today's actual date, not a placeholder. Documented this explicitly in the "Versioning" section so a future reader doesn't re-flag it without that context. |
| 11 | NIT | Addressed. Removed the stubbed `iframe` branch/comment from the modal component's `'video'` type description; it now states plainly that no other branch exists today and why (out of scope per `requirements.md` and this repo's KISS rule). |

---

## Open design-review findings (design-loop ended after 3 iterations)

The design-loop was ended by the product owner with verdict CHANGES_REQUESTED
(round 3: 2 HIGH, 4 MEDIUM, 1 NIT). The architecture is accepted; the items
below are implementation details that MUST be resolved while building. Full
reasoning and fixes: `design-review.md` / `design-review.json` in this folder.

HIGH (runtime-breaking if skipped):
1. Accordion `open` class target mismatch. In Sections mode the PHP templates
   put `open` on the inner `<ul>`, but `bindAccordionToggle()` and the CSS
   convention (`.wptg-term-group:not(.open) .wptg-term-posts`) work on the
   ANCESTOR. Pick one rule and apply it everywhere: put `open` on
   `.wptg-section-group` / `.wptg-section-term-li` and add mirrored
   `:not(.open)` CSS rules for `.wptg-section-terms` and `.wptg-term-posts`.
2. Write the missing bodies: `setActiveItem(wrapper, item)`, the 4-parameter
   `loadSinglePost(wrapper, postId, postType, includeMeta)` (falsy default so
   existing 3-arg call sites behave identically), and the modified
   `wptg_ajax_get_single_post()` reading `$_POST['include_meta']`.

MEDIUM:
3. `wptg_ajax_get_posts()` does NOT validate that the taxonomy is registered
   for the post type or that the term belongs to the taxonomy; only
   `wptg_resolve_and_render()` does. Do not claim otherwise, and make the new
   `wptg_ajax_query_section_items()` perform those checks itself.
4. Add `data-term-name="<?php echo esc_attr(strtolower($term->name)); ?>"` to
   `<li class="wptg-section-term-li">` so `filterTermGroup()` can match by
   category name.
5. Debounce per input element (e.g. a WeakMap of input -> debounced handler),
   not one shared debounce for the whole sidebar.
6. A repeater row with no post type must not silently vanish: give
   `section_post_type` a non-empty default or render a visible "not
   configured" entry, and document the choice in the acceptance criteria.

NIT:
7. Only render `.wptg-section-no-results` for non-`flat_list` sections
   (flat_list search is AJAX, never toggles it).

Related repo change made after the design was written: the mu-plugin
`web/app/mu-plugins/custom-taxonomies.php` now also registers `news_category`,
`information_category` and `about_category`, and detaches the built-in
`category` from every post type it manages. This does not affect the widget
(flat_list sections never query by taxonomy).
