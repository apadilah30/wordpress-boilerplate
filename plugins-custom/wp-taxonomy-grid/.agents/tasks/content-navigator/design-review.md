# Design Review — "Content Navigator" (Taxonomy Grid v4.0.0)

Reviewed fresh against `requirements.md`, `design.md`, and the actual plugin
source (`wp-taxonomy-grid.php`, `includes/class-elementor-widget.php`,
`assets/js/taxonomy-grid.js`, `assets/css/taxonomy-grid.css`,
`composer.json`, `readme.txt`) and `web/app/mu-plugins/custom-taxonomies.php`.

## Findings

### 1. [HIGH] Sections-mode accordion's "open" class is applied to the wrong element — breaks expand/collapse (AC7) and contradicts the existing CSS convention

**Where:** `wptg_render_section_sidebar_group()`'s `taxonomy_accordion` branch
(both the top-level term list and the nested `single_detail` post list), and
`bindAccordionToggle()`'s two call sites inside `initSectionsMode()`.

**Problem:** The legacy accordion (verified in `taxonomy-grid.css` line 334)
uses the rule `.wptg-term-group:not(.open) .wptg-term-posts { max-height: 0; ... }`
— i.e. the `open` class lives on the **outer** `.wptg-term-group` ancestor,
and CSS hides the **inner** `<ul>` based on that ancestor's class. The
existing JS confirms this: `initDetailMode()`'s toggle does
`group.classList.toggle('open')` where `group = toggle.closest('.wptg-term-group')`
— the same element the markup puts `open` on
(`<div class="wptg-term-group open" ...>`).

The new Sections markup breaks this pairing in two places:

```php
<ul class="wptg-section-terms <?php echo $is_section_active ? 'open' : ''; ?>">
```
```php
<ul class="wptg-term-posts <?php echo $is_term_active ? 'open' : ''; ?>">
```

Here `open` is rendered on the **inner `<ul>` itself**, not on an ancestor.
But the corresponding click handlers do the opposite — they toggle `open` on
the **ancestor**:

```js
sidebar.querySelectorAll('.wptg-section-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => bindAccordionToggle(toggle, toggle.closest('.wptg-section-group')));
});
sidebar.querySelectorAll('.wptg-term-toggle').forEach((toggle) => {
    toggle.addEventListener('click', () => bindAccordionToggle(toggle, toggle.closest('.wptg-section-term-li')));
});
```
`bindAccordionToggle()` does `groupEl.classList.toggle('open')` on whatever
it's given — here `.wptg-section-group` / `.wptg-section-term-li` — which is
never the element the SSR markup put `open` on, and is not the element any
documented CSS rule in this design targets either (the design never writes
the "SECTIONS MODE" CSS block's actual selectors, just says it "follows the
file's existing block convention"). Net effect: on first load the list is
already marked `open` on itself (useless unless CSS selects `.wptg-section-terms.open`
directly, which the design never specifies), and clicking the toggle adds
`open` to a *different* element that no SSR-rendered state or stated CSS
rule ever reads. This directly fails Acceptance Criterion 7 ("matching the
existing services accordion expand/collapse interaction exactly") and the
analogous `single_detail` nested-post toggle.

**Fix (pick one, state it explicitly):**
- Option A (matches legacy convention exactly): move the `open` class in the
  PHP templates from the `<ul>` onto the ancestor (`.wptg-section-group` /
  `.wptg-section-term-li`), and add the mirrored CSS rules
  `.wptg-section-group:not(.open) .wptg-section-terms { max-height: 0; overflow: hidden; }`
  and `.wptg-section-term-li:not(.open) .wptg-term-posts { max-height: 0; overflow: hidden; }`
  to the new "SECTIONS MODE" CSS block.
- Option B: keep `open` on the `<ul>`, but change `bindAccordionToggle()`'s
  call sites to pass the actual list element
  (`toggle.nextElementSibling` or `toggle.closest('.wptg-section-group').querySelector(':scope > .wptg-section-terms')`)
  instead of the ancestor, and write the CSS against `.wptg-section-terms.open`/`.wptg-term-posts.open` directly.

Either is fine; the document must pick one and make the PHP markup, the JS
toggle target, and the (currently unwritten) CSS selectors agree.

### 2. [HIGH] Three functions are called/relied upon but their bodies are never written — the exact defect class flagged (and supposedly fixed) in rounds 1 and 2

**Where:** `handleItemClick()`, the "File organization" table's description
of `loadSinglePost()`, and the "`wptg_render_single_post_detail()`
modification" section's callers table.

- `setActiveItem(wrapper, item)` is called inside `handleItemClick()`'s
  `inline_detail` branch:
  ```js
  if (behavior === 'inline_detail') {
      setActiveItem(wrapper, item);
      loadSinglePost(wrapper, item.dataset.postId, item.dataset.postType, /* includeMeta */ true);
      return;
  }
  ```
  `setActiveItem` is never defined anywhere in `design.md` (confirmed by
  searching the whole document). There's no indication of what it marks
  active or how (compare to the sidebar's `active`/`aria-pressed` pattern
  used elsewhere) or whether it needs to clear the previous active item
  across sections.

- `loadSinglePost()`'s **new, 4-parameter signature** is only described in
  prose ("is generalized to look up its target container via
  `wrapper.querySelector('.wptg-detail-container, .wptg-sections-content')`
  ... and to optionally send `include_meta: '1'`"). The actual existing
  function (verified in `assets/js/taxonomy-grid.js`) has signature
  `loadSinglePost(wrapper, postId, postType)` — 3 parameters, hardcoded to
  `.wptg-detail-container`, no `include_meta` in its request body. The call
  site above passes a 4th argument (`true`) that the existing function
  doesn't accept. No updated function body is given anywhere.

- `wptg_ajax_get_single_post()`'s modification is likewise prose-only: "reads
  a new optional `$_POST['include_meta']` flag, `true` only if `'1'`". The
  actual existing PHP function (verified in `wp-taxonomy-grid.php`,
  `wptg_ajax_get_single_post()`) has no such read today, and no updated
  function body is given in `design.md`.

**Fix:** Write out the full bodies for all three:
```js
function setActiveItem(wrapper, item) {
    wrapper.querySelectorAll('[data-click-behavior].active').forEach((el) => el.classList.remove('active'));
    item.classList.add('active');
}

function loadSinglePost(wrapper, postId, postType, includeMeta) {
    const container = wrapper.querySelector('.wptg-detail-container, .wptg-sections-content');
    if (!container) return;
    // ...same fetch()/then()/catch() shape as today, plus:
    // body: new URLSearchParams({ action: 'wptg_get_single_post', nonce: wptg_ajax.nonce,
    //   post_id: postId, post_type: postType, ...(includeMeta ? { include_meta: '1' } : {}) })
}
```
```php
function wptg_ajax_get_single_post(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');
    $post_id      = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $post_type    = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
    $include_meta = isset($_POST['include_meta']) && $_POST['include_meta'] === '1';
    // ...unchanged ownership check...
    $html = wptg_render_single_post_detail($post_id, $include_meta);
    wp_send_json_success(['html' => $html]);
}
```
Note `loadSinglePost(wrapper, postId, postType)`'s two **existing** call
sites in `initDetailMode()` must keep calling it with 3 args (the 4th
parameter needs a default of `false`/`undefined` so the legacy call site's
request body is still byte-identical, per FR-1).

### 3. [MEDIUM] Misattributed "mirrors existing validation" claim — the cited existing function doesn't do what's claimed

**Where:** "The reusable generic AJAX 'load more' / query handler" section,
the paragraph right after `wptg_ajax_query_section_items()`'s code block.

**Problem:** The design states the new handler's validation "mirrors the
existing `wptg_ajax_get_posts()`'s validation style (`get_post_type_object()`
+ `taxonomy_exists()` + 'is this taxonomy actually registered for this post
type' — the same check already used in `wptg_resolve_and_render()`)".
Reading the actual `wptg_ajax_get_posts()` (verified, `wp-taxonomy-grid.php`
lines 738-759):
```php
function wptg_ajax_get_posts(): void
{
    check_ajax_referer('wptg_nonce', 'nonce');
    $term_id   = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
    $post_type = isset($_POST['post_type']) ? sanitize_text_field(wp_unslash($_POST['post_type'])) : '';
    $taxonomy  = isset($_POST['taxonomy']) ? sanitize_text_field(wp_unslash($_POST['taxonomy'])) : '';
    ...
    if (!get_post_type_object($post_type) || !taxonomy_exists($taxonomy)) {
        wp_send_json_error(['message' => 'Invalid post type or taxonomy.']);
    }
    $html = wptg_get_posts_html($term_id, $post_type, $taxonomy, $limit);
    ...
}
```
It does **not** verify the taxonomy is actually registered for that post
type, and it does not verify the term belongs to that taxonomy at all — it
passes `$term_id` straight into the query. Only `wptg_resolve_and_render()`
(the page-render path, not this AJAX handler) performs the
"taxonomy-registered-for-post-type" check. The sentence conflates the two
and overstates what the existing AJAX code already guarantees.

This doesn't weaken the **new** `wptg_query_items` handler, which does
implement the stronger check correctly — but the review brief specifically
calls for verifying "the existing helper handles this" claims against
source, and this one is wrong, which could mislead whoever reads this
section into believing `wptg_ajax_get_posts()` is already this rigorous (it
is not, and is explicitly out of scope to fix here).

**Fix:** Reword to: "mirrors the stronger validation style used in
`wptg_resolve_and_render()` (not currently present in `wptg_ajax_get_posts()`,
which is left as-is per NFR-4/out-of-scope)."

### 4. [MEDIUM] `.wptg-section-term-li` never gets a `data-term-name` attribute — breaks the reused `filterTermGroup()` helper's category-name matching

**Where:** `wptg_render_section_sidebar_group()`'s per-term `<li>` markup,
vs. `filterTermGroup(groupEl, query)`'s body.

**Problem:** `filterTermGroup()` (the extracted helper `filterWithinSectionGroup()`
calls once per `.wptg-section-term-li`) reads:
```js
const termName = groupEl.dataset.termName || '';
```
and uses it both to decide whether the term/category heading itself matches
the search query and as a fallback match for every post inside it. The
legacy `.wptg-term-group` element this was extracted from does carry
`data-term-name` (verified: `data-term-name="<?php echo $term_slug; ?>"` in
`wptg_render_detail_mode()`). The new `.wptg-section-term-li` markup never
emits this attribute:
```php
<li class="wptg-section-term-li <?php echo $is_term_active ? 'active' : ''; ?>" data-term-id="<?php echo esc_attr($term->term_id); ?>">
```
So `termName` is always `''` for every Sections-mode term, meaning a search
can never match on the category/term name itself — only on child post
titles — for any `taxonomy_accordion` section that turns search on (the 4
required rows disable search here, but the design explicitly describes this
code path as reusable for "a hypothetical `single_detail` section with
search enabled").

**Fix:** Add `data-term-name="<?php echo esc_attr(strtolower($term->name)); ?>"`
to the `<li class="wptg-section-term-li ...">` tag, matching the legacy
`.wptg-term-group` convention exactly.

### 5. [MEDIUM] One shared `debounce()` instance for the whole sidebar can silently drop a pending search from a different section's input

**Where:** `initSectionsMode()`'s search wiring:
```js
sidebar.addEventListener('input', debounce(function (e) { ... }, 300));
```

**Problem:** `debounce()` wraps the entire delegated `input` listener in a
single closure with one shared `timeoutId`. With up to 4 independent search
boxes in one widget (Berita Terbaru, Pengumuman, and optionally any
`taxonomy_accordion`/`single_detail` section with search enabled), typing in
section A's box and then, within the 300ms window, typing in section B's
box cancels A's pending debounced call entirely — A's search never fires
(not delayed, just dropped), until the user types in A again. This directly
undermines AC5's "ends up ... showing only matching news items" guarantee
whenever two search boxes are used in quick succession.

**Fix:** Debounce per input element, not once for the whole delegated
listener, e.g.:
```js
const debouncedHandlers = new WeakMap();
sidebar.addEventListener('input', function (e) {
    const input = e.target.closest('.wptg-section-search-input');
    if (!input) return;
    if (!debouncedHandlers.has(input)) {
        debouncedHandlers.set(input, debounce(() => runSectionSearch(input), 300));
    }
    debouncedHandlers.get(input)();
});
```

### 6. [MEDIUM] No validation or default for a repeater row with no `section_post_type` selected — the section silently vanishes with zero feedback

**Where:** `section_post_type`'s control definition (no `default`) and
`wptg_normalize_section_row()`'s first check:
```php
$post_type     = sanitize_key($row['section_post_type'] ?? '');
$post_type_obj = get_post_type_object($post_type);
if (!$post_type_obj || !$post_type_obj->public) {
    return null;
}
```

**Problem:** A site editor adding a new "Sections" repeater row in the
Elementor editor sees it default to an empty Post Type dropdown. Until they
explicitly pick one, `wptg_normalize_section_row()` returns `null` for that
row and `wptg_render_sections_widget()` drops it from the sidebar entirely
— no error, no placeholder, no note in the editor. This is distinct from
the fully-empty case (`empty($sections)` → "No valid sections configured."
message), which *is* handled. A half-configured repeater (e.g. the editor
just added a 5th row and hasn't picked a post type yet, or picked a post
type but it later got deleted) is indistinguishable from "this row
shouldn't exist" and produces no diagnostic anywhere, violating this
repo's "ramah pemula" (beginner-friendly) convention and the review
checklist's "missing input validation rules" criterion. Neither
`requirements.md` nor `design.md` states what should happen here.

**Fix:** Either (a) give `section_post_type` a non-empty default matching
the top-level `wptg_post_type` control's `'default' => 'post'` pattern so a
freshly-added row is never literally empty, or (b) have
`wptg_normalize_section_row()` keep the row with a `'post_type' => ''`
marker, and have `wptg_render_section_sidebar_group()` render a visibly
disabled entry ("Belum dikonfigurasi — pilih Post Type") instead of
disappearing silently. State the choice and document it in
`requirements.md`'s acceptance criteria.

### 7. [NIT] Dead `.wptg-section-no-results` markup rendered (but never toggled) for `flat_list` sections

**Where:** `wptg_render_section_sidebar_group()`:
```php
<?php if ($section['enable_search']): ?>
    <div class="wptg-section-search">...</div>
    <p class="wptg-section-no-results" style="display:none;">...</p>
<?php endif; ?>
```
The design's own comment states this element is "only ever shown by
`filterWithinSectionGroup()`'s ... DOM-filtered search" and that "`flat_list`
sections re-query via AJAX instead and never need this element." Yet the
markup renders it unconditionally for every section with `enable_search`
true, including `flat_list` ones (Berita Terbaru, Pengumuman), where it is
permanently dead weight.

**Fix:** Gate the `<p class="wptg-section-no-results">` on
`$section['type'] !== 'flat_list'` as well, or give `flat_list`'s AJAX
no-results case its own message element instead of leaving an always-hidden,
never-shown paragraph in the DOM.

## Verified Assumptions

- `web/app/mu-plugins/custom-taxonomies.php` registers `{post_type}_category`
  taxonomies for `services`, `news`, `gallery`, and `videos` — **including
  `news_category` on `news`** — and does *not* register one for
  `announcements`. This matches the design's (corrected) claim exactly.
- Advanced Custom Fields (`advanced-custom-fields`) is installed and part of
  the project's default-active plugin set (confirmed in root `composer.json`
  and `scripts/dev-init.sh`), supporting the design's "even though ACF is
  active site-wide" framing for why a plain meta box was chosen instead.
- All seven Style-tab helper methods the design calls out by name —
  `add_text_style()`, `add_spacing_controls()`, `add_box_control()`,
  `add_alignment_control()`, `add_bg_control()`, `add_badge_box_controls()`,
  `add_position_controls()` — exist in `includes/class-elementor-widget.php`
  with exactly the signatures/parameter order the design's call sites use.
- `Group_Control_Border` is already `use`'d at the top of
  `class-elementor-widget.php`, so no new import is needed for the Load More
  button's border controls, as the design claims.
- The "exactly which existing methods are touched" tables are accurate: all
  5 named Content-tab methods and all 10 named Style-tab methods exist with
  the names given, and the claimed current `condition` state of each
  (`['wptg_mode' => 'detail']`, `['wptg_mode' => 'grid']`, or none) matches
  the actual source exactly, including that `register_style_container_controls()`,
  `register_style_divider_accordion_controls()`,
  `register_style_divider_merged_controls()`,
  `register_style_category_name_accordion_controls()`,
  `register_style_category_name_merged_controls()`, and
  `register_style_content_controls()` currently have **no** `condition` key.
- The plugin's current version is `3.9.1` in all three places the design
  says it is (`wp-taxonomy-grid.php` docblock, both enqueue version
  strings, `composer.json`), consistent with bumping all three to `4.0.0`.
- The shell's system date really is `2026-10-30`, confirmed independently
  via `date /t`, so the changelog date in the design is accurate, not a
  stale/placeholder value.
- `wptg_render_single_post_detail(int $post_id): string` (no `$include_meta`
  parameter today), `loadSinglePost(wrapper, postId, postType)` (3
  parameters, hardcoded to `.wptg-detail-container`), and the inline
  (non-extracted) 3-line accordion toggle in `initDetailMode()` all match
  exactly the "before" state the design describes modifying.
- The root `composer.json` depends on `boilerplate/wp-taxonomy-grid` with an
  unpinned `"*"` version constraint, so no root-level version bump is
  needed beyond the plugin's own `composer.json`.

## Unverified / Wrong Assumptions

- **Wrong:** the design's claim that the new `wptg_query_items` handler's
  validation "mirrors the existing `wptg_ajax_get_posts()`'s validation
  style ... (`get_post_type_object()` + `taxonomy_exists()` + 'is this
  taxonomy actually registered for this post type')" — the real
  `wptg_ajax_get_posts()` does not perform the taxonomy-registered-for-post-type
  check at all (see Finding 3).
- **Unverified / implicit:** the design assumes the new "SECTIONS MODE" CSS
  block will "follow the file's existing block convention," but never
  states the concrete selectors, and the markup it does specify (the `open`
  class placed on the inner `<ul>`) is incompatible with the actual existing
  convention in `taxonomy-grid.css` (`.wptg-term-group:not(.open) .wptg-term-posts`),
  which keys off an ancestor class (see Finding 1).

## Verdict

2 HIGH, 4 MEDIUM, 1 NIT → **CHANGES_REQUESTED**
