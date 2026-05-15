<?php
$contentSection = (string) ($data['content_section'] ?? 'blog');
$expectedPostType = $contentSection === 'reviews' ? 'review' : 'post';
$posts = array_values(array_filter(
    $data['posts'] ?? [],
    static fn(array $post): bool => (($post['post_type'] ?? 'post') === $expectedPostType)
));
$pagination = $data['blog_pagination'] ?? [
    'page' => 1,
    'total_pages' => 1,
    'total_items' => count($posts),
    'has_prev' => false,
    'has_next' => false,
    'prev_page' => 1,
    'next_page' => 1,
];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$totalItems = max(0, (int) ($pagination['total_items'] ?? 0));
$isAdminPreview = !empty($data['blog_admin_preview']);
$sectionTitle = $contentSection === 'reviews'
    ? 'Telescope Reviews & Comparisons'
    : 'Astronomy Blog Articles';
$sectionKicker = $contentSection === 'reviews'
    ? 'Reviews Hub'
    : 'Blog Hub';
$sectionIntro = $contentSection === 'reviews'
    ? 'Product-focused comparisons and review-driven breakdowns for telescope buyers.'
    : 'Practical telescope, stargazing, and astrophotography articles written for beginner and intermediate hobbyists who are still researching before they buy.';
$buildBlogPageUrl = static function (int $page) use ($contentSection): string {
    $safePage = max(1, $page);
    $base = '/blog';
    if ($contentSection === 'reviews') {
        $base = '/reviews';
    }
    return $safePage === 1 ? url($base) : url($base . '?page=' . $safePage);
};
$pageWindow = pagination_window($currentPage, $totalPages, 2);

$isPlaceholderValue = static function (?string $value): bool {
    $normalized = strtolower(trim((string) $value));
    return $normalized === '' || in_array($normalized, ['post type', 'standard post'], true);
};

$pickPostText = static function (array $post, array $keys, string $fallback) use ($isPlaceholderValue): string {
    foreach ($keys as $key) {
        $value = trim((string) ($post[$key] ?? ''));
        if (!$isPlaceholderValue($value)) {
            return $value;
        }
    }

    return $fallback;
};
?>
<section class="hero">
    <span class="hero-kicker"><?= e($sectionKicker) ?></span>
    <h1><?= e($sectionTitle) ?></h1>
    <p><?= e($sectionIntro) ?></p>
    <div class="content-switch" aria-label="Content type switcher">
        <?php if ($contentSection === 'guides'): ?>
            <span class="is-active" aria-current="page">Guides</span>
        <?php else: ?>
            <a href="<?= e(url('/guides')) ?>">Guides</a>
        <?php endif; ?>

        <?php if ($contentSection === 'reviews'): ?>
            <span class="is-active" aria-current="page">Reviews</span>
        <?php else: ?>
            <a href="<?= e(url('/reviews')) ?>">Reviews</a>
        <?php endif; ?>

        <?php if ($contentSection === 'blog'): ?>
            <span class="is-active" aria-current="page">Posts</span>
        <?php else: ?>
            <a href="<?= e(url('/blog')) ?>">Posts</a>
        <?php endif; ?>
    </div>
    <div class="trust-row">
        <span class="chip">Clear beginner guidance</span>
        <span class="chip">Actionable checklists</span>
        <span class="chip">Top-of-funnel search topics</span>
    </div>
</section>

<section class="panel home-section">
    <h2 class="section-title u-mt-0">Popular beginner research paths</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">First telescope</div>
            <div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Compare beginner telescope options</a> after reading the foundational articles.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Budget research</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">See telescopes under $500</a> if you already have a spending ceiling.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Upgrade research</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">See practical accessories</a> if you already own a telescope and want better sessions.</div>
        </div>
    </div>
</section>

<section class="panel home-section">
    <h2 class="section-title u-mt-0"><?= $currentPage === 1 ? 'Featured posts' : 'Blog posts' ?></h2>
    <p class="muted">Start with the latest articles, then use guides and category pages to compare products before buying.</p>
    <?php if ($posts === []): ?>
        <p class="muted">No posts found. Check back soon for new astronomy content.</p>
    <?php else: ?>
        <div class="grid blog-grid">
            <?php foreach ($posts as $idx => $post): ?>
                <?php
                $slug = trim((string) ($post['slug'] ?? ''));
                $isDraft = (($post['status'] ?? 'published') !== 'published');
                $title = $pickPostText($post, ['title', 'seo_title', 'headline'], $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : 'Astronomy article');
                $excerpt = $pickPostText(
                    $post,
                    ['excerpt', 'meta_description', 'description', 'summary'],
                    'Read this practical astronomy article and apply the tips to your next observing session.'
                );
                $postImageRaw = trim((string) ($post['featured_image'] ?? ''));
                $postImage = $postImageRaw !== '' ? content_asset_path($postImageRaw) : '/assets/img/product-placeholder.svg';
                ?>
                <article class="card">
                    <img src="<?= e(content_asset_path($postImage)) ?>" alt="<?= e($title) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $idx === 0 ? 'high' : 'auto' ?>" width="800" height="600">
                    <div class="body">
                        <span class="badge"><?= $isDraft ? 'Draft' : 'Article' ?></span>
                        <h3><?= e($title) ?></h3>
                        <p class="card-copy"><?= e($excerpt) ?></p>
                        <?php if ($isDraft && $isAdminPreview): ?>
                            <p class="muted u-mt-8 u-mb-0 u-fs-12 u-color-warm">Borrador visible solo para tu sesión admin.</p>
                        <?php endif; ?>
                        <a class="card-cta" href="<?= e(url(post_url_path($post))) ?>">Read article</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($totalPages > 1): ?>
            <div class="pagination" aria-label="Blog pagination">
                <div class="pagination-info">
                    Page <?= (int) $currentPage ?> of <?= (int) $totalPages ?> · <?= number_format($totalItems) ?> posts
                </div>
                <div class="pagination-nav">
                    <?php if (!empty($pagination['has_prev'])): ?>
                        <a class="pagination-link" href="<?= e($buildBlogPageUrl((int) $pagination['prev_page'])) ?>">Prev</a>
                    <?php endif; ?>
                    <?php for ($page = $pageWindow['start']; $page <= $pageWindow['end']; $page++): ?>
                        <a class="pagination-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= e($buildBlogPageUrl($page)) ?>"><?= (int) $page ?></a>
                    <?php endfor; ?>
                    <?php if (!empty($pagination['has_next'])): ?>
                        <a class="pagination-link" href="<?= e($buildBlogPageUrl((int) $pagination['next_page'])) ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="panel home-section">
    <h2 class="section-title u-mt-0">Category paths</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Guides</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Browse astronomy buying guides</a> for conversion-focused product picks.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Telescopes</div>
            <div class="compare-value"><a href="<?= e(url('/telescopes')) ?>">See telescope recommendations</a> from beginner to premium tiers.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Accessories</div>
            <div class="compare-value"><a href="<?= e(url('/accessories')) ?>">Compare practical accessories</a> for better observing sessions.</div>
        </div>
    </div>
</section>

<section class="panel faq-panel home-section">
    <h2 class="section-title u-mt-0">Frequently asked questions</h2>
    <details>
        <summary>Are blog articles useful before comparing products?</summary>
        <p class="muted">Yes. Informational articles answer the questions people usually search first, then point to deeper guides and category pages once the buying intent becomes clearer.</p>
    </details>
    <details>
        <summary>Where should I go after reading a blog post?</summary>
        <p class="muted">Move into the <a href="<?= e(url('/guides')) ?>">guides hub</a> for more structured buying advice, or straight to <a href="<?= e(url('/telescopes')) ?>">telescopes</a> and <a href="<?= e(url('/accessories')) ?>">accessories</a> if you already know the category you want.</p>
    </details>
</section>
