// Tiru logika PHP deep link (bukan pengganti tes di WordPress; PHP tidak ada di host).
// Pakai: node test-logic.js
// Sumber yang ditiru:
//   - wp-taxonomy-grid.php: wptg_find_section_term_by_slug / wptg_build_deep_link_active / wptg_resolve_deep_link
//   - wp-content-grid.php: wpcg_normalize_link_options / wpcg_build_item_url / wpcg_build_item_link_attrs
const assert = require('assert');

/* ---------- Taxonomy Grid ---------- */
function findTerm(section, slug) {
    return (section.terms || []).find((t) => t.slug === slug) || null;
}

// postsByTerm menggantikan wptg_get_posts_for_term() untuk single_detail.
function buildActive(section, termId, postsByTerm) {
    if (section.type === 'flat_list') {
        return section.has_items ? { section_index: section.index, term_id: 0, post_id: 0 } : null;
    }
    if (!section.terms || section.terms.length === 0) return null;
    if (termId === 0) termId = section.terms[0].term_id;
    if (section.content_style !== 'single_detail') {
        return { section_index: section.index, term_id: termId, post_id: 0 };
    }
    const posts = postsByTerm[termId] || [];
    const postId = posts.length ? posts[0] : 0;
    return postId > 0 ? { section_index: section.index, term_id: termId, post_id: postId } : null;
}

function resolveDeepLink(sections, sectionSlug, termSlug, postsByTerm) {
    if (sectionSlug === '') return null;
    const matching = sections.filter((s) => s.post_type === sectionSlug);
    if (termSlug !== '') {
        for (const s of matching) {
            const t = findTerm(s, termSlug);
            if (!t) continue;
            const a = buildActive(s, t.term_id, postsByTerm);
            if (a) return a;
        }
    }
    for (const s of matching) {
        const a = buildActive(s, 0, postsByTerm);
        if (a) return a;
    }
    return null;
}

const sections = [
    { index: 0, type: 'flat_list', post_type: 'news', has_items: true },
    { index: 1, type: 'flat_list', post_type: 'announcements', has_items: false },
    { index: 2, type: 'taxonomy_accordion', post_type: 'gallery', content_style: 'grid_thumbnail',
      terms: [{ term_id: 10, slug: 'kegiatan' }, { term_id: 11, slug: 'upacara' }] },
    { index: 3, type: 'taxonomy_accordion', post_type: 'videos', content_style: 'list_thumbnail',
      terms: [{ term_id: 20, slug: 'profil-kantor-imigrasi' }, { term_id: 21, slug: 'layanan' }] },
    { index: 4, type: 'taxonomy_accordion', post_type: 'news', content_style: 'single_detail',
      terms: [{ term_id: 30, slug: 'umum' }, { term_id: 31, slug: 'khusus' }] },
    { index: 5, type: 'taxonomy_accordion', post_type: 'about', content_style: 'grid_thumbnail', terms: [] },
];
const posts = { 30: [301], 31: [] };

// term valid -> section + term itu
assert.deepStrictEqual(resolveDeepLink(sections, 'videos', 'profil-kantor-imigrasi', posts),
    { section_index: 3, term_id: 20, post_id: 0 });
assert.deepStrictEqual(resolveDeepLink(sections, 'gallery', 'upacara', posts),
    { section_index: 2, term_id: 11, post_id: 0 });
// tanpa term -> term pertama
assert.deepStrictEqual(resolveDeepLink(sections, 'videos', '', posts),
    { section_index: 3, term_id: 20, post_id: 0 });
// term tidak dikenal -> section tetap buka, term pertama
assert.deepStrictEqual(resolveDeepLink(sections, 'videos', 'ngawur', posts),
    { section_index: 3, term_id: 20, post_id: 0 });
// flat list: term diabaikan
assert.deepStrictEqual(resolveDeepLink(sections, 'announcements', 'x', posts), null); // kosong -> fallback default
// news: ada flat (baris 0) dan accordion (baris 4). Tanpa term -> baris pertama (flat).
assert.deepStrictEqual(resolveDeepLink(sections, 'news', '', posts),
    { section_index: 0, term_id: 0, post_id: 0 });
// news + term yang ada di accordion -> accordion menang (single_detail ikut isi post_id)
assert.deepStrictEqual(resolveDeepLink(sections, 'news', 'umum', posts),
    { section_index: 4, term_id: 30, post_id: 301 });
// news + term tanpa post (single_detail) -> turun ke baris pertama yang bisa tampil (flat)
assert.deepStrictEqual(resolveDeepLink(sections, 'news', 'khusus', posts),
    { section_index: 0, term_id: 0, post_id: 0 });
// section tidak ada di repeater / kosong / section tanpa term -> null (fallback default)
assert.strictEqual(resolveDeepLink(sections, 'unknown', 'kegiatan', posts), null);
assert.strictEqual(resolveDeepLink(sections, '', 'kegiatan', posts), null);
assert.strictEqual(resolveDeepLink(sections, 'about', 'x', posts), null);
// term milik section lain tidak boleh dipakai
assert.deepStrictEqual(resolveDeepLink(sections, 'gallery', 'layanan', posts),
    { section_index: 2, term_id: 10, post_id: 0 });

/* ---------- Content Grid ---------- */
function normalizeLink(atts, resolvedTaxonomy) {
    const targetUrl = String(atts.target_url || '').trim();
    let mode = atts.link_mode === 'navigator' ? 'navigator' : 'permalink';
    if (targetUrl === '') mode = 'permalink';
    return { mode, target_url: targetUrl, taxonomy: mode === 'navigator' ? resolvedTaxonomy : '',
        external: !!atts.target_external, nofollow: !!atts.target_nofollow };
}

// add_query_arg: tambah param ke URL relatif/absolut, urlencode nilai.
function addQueryArg(args, uri) {
    const [base, frag] = uri.split('#');
    const [path, qs = ''] = base.split('?');
    const params = new URLSearchParams(qs);
    Object.entries(args).forEach(([k, v]) => params.set(k, v));
    return path + '?' + params.toString() + (frag ? '#' + frag : '');
}

function buildUrl(post, link, termSlug) {
    if (link.mode !== 'navigator') return post.permalink;
    const args = { wptg_section: post.type };
    if (link.taxonomy !== '' && termSlug !== '') args.wptg_term = termSlug;
    return addQueryArg(args, link.target_url);
}

const post = { type: 'videos', permalink: 'https://x.test/videos/a/' };
// default = permalink (perilaku lama)
assert.strictEqual(buildUrl(post, normalizeLink({}, 'videos_category'), 'p'), post.permalink);
// navigator tapi target kosong -> permalink
assert.strictEqual(buildUrl(post, normalizeLink({ link_mode: 'navigator', target_url: '  ' }, 'videos_category'), 'p'), post.permalink);
// navigator normal
assert.strictEqual(buildUrl(post, normalizeLink({ link_mode: 'navigator', target_url: '/news/' }, 'videos_category'), 'profil-kantor-imigrasi'),
    '/news/?wptg_section=videos&wptg_term=profil-kantor-imigrasi');
// tanpa taxonomy -> hanya section
assert.strictEqual(buildUrl(post, normalizeLink({ link_mode: 'navigator', target_url: '/news/' }, ''), 'x'),
    '/news/?wptg_section=videos');
// tanpa term -> hanya section
assert.strictEqual(buildUrl(post, normalizeLink({ link_mode: 'navigator', target_url: '/news/' }, 'videos_category'), ''),
    '/news/?wptg_section=videos');
// URL sudah punya query + fragment
assert.strictEqual(buildUrl(post, normalizeLink({ link_mode: 'navigator', target_url: 'https://x.test/berita/?a=1#top' }, 'c'), 'k'),
    'https://x.test/berita/?a=1&wptg_section=videos&wptg_term=k#top');

console.log('Semua tes logika lulus.');
