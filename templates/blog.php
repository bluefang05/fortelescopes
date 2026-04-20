<?php
$posts = array_values(array_filter(
    $data['posts'] ?? [],
    static fn(array $post): bool => (($post['post_type'] ?? 'post') === 'post')
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
$buildBlogPageUrl = static function (int $page): string {
    $safePage = max(1, $page);
    return $safePage === 1 ? url('/blog') : url('/blog?page=' . $safePage);
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
    <span class="hero-kicker">Blog Hub</span>
    <h1>Astronomy Blog Articles</h1>
    <p>Practical telescope, stargazing, and astrophotography articles written for beginner and intermediate hobbyists who are still researching before they buy.</p>
    <div class="trust-row">
        <span class="chip">Clear beginner guidance</span>
        <span class="chip">Actionable checklists</span>
        <span class="chip">Top-of-funnel search topics</span>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Popular beginner research paths</h2>
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

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;"><?= $currentPage === 1 ? 'Featured posts' : 'Blog posts' ?></h2>
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
                $postImage = trim((string) ($post['featured_image'] ?? '')) !== '' ? (string) $post['featured_image'] : '/assets/img/product-placeholder.svg';
                ?>
                <article class="card">
                    <img src="<?= e(url($postImage)) ?>" alt="<?= e($title) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $idx === 0 ? 'high' : 'auto' ?>" width="800" height="600">
                    <div class="body">
                        <span class="badge"><?= $isDraft ? 'Draft' : 'Article' ?></span>
                        <h3><?= e($title) ?></h3>
                        <p class="card-copy"><?= e($excerpt) ?></p>
                        <?php if ($isDraft && $isAdminPreview): ?>
                            <p class="muted" style="margin: 8px 0 0; font-size: 12px; color: #7d2d00;">Borrador visible solo para tu sesión admin.</p>
                        <?php endif; ?>
                        <a class="card-cta" href="<?= e(url('/blog/' . $slug)) ?>">Read article</a>
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

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Category paths</h2>
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

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Frequently asked questions</h2>
    <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Are blog articles useful before comparing products?</summary>
        <p class="muted" style="margin: 8px 0 0;">Yes. Informational articles answer the questions people usually search first, then point to deeper guides and category pages once the buying intent becomes clearer.</p>
    </details>
    <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Where should I go after reading a blog post?</summary>
        <p class="muted" style="margin: 8px 0 0;">Move into the <a href="<?= e(url('/guides')) ?>">guides hub</a> for more structured buying advice, or straight to <a href="<?= e(url('/telescopes')) ?>">telescopes</a> and <a href="<?= e(url('/accessories')) ?>">accessories</a> if you already know the category you want.</p>
    </details>
</section>
