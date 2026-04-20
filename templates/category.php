<?php
$products = $data['products'] ?? [];
$tiers = pick_tier_products($products);
$pagination = $data['category_pagination'] ?? [
    'page' => 1,
    'total_pages' => 1,
    'total_items' => count($products),
    'has_prev' => false,
    'has_next' => false,
    'prev_page' => 1,
    'next_page' => 1,
];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$totalItems = max(0, (int) ($pagination['total_items'] ?? 0));
$pageWindow = pagination_window($currentPage, $totalPages, 2);
$categorySlug = trim((string) ($data['categorySlug'] ?? slugify((string) ($data['categoryName'] ?? ''))));
$categoryPath = in_array($categorySlug, ['telescopes', 'accessories'], true) ? '/' . $categorySlug : '/category/' . $categorySlug;
$buildCategoryPageUrl = static function (int $page) use ($categoryPath): string {
    $safePage = max(1, $page);
    return $safePage === 1 ? url($categoryPath) : url($categoryPath . '?page=' . $safePage);
};
?>
<section class="hero">
    <span class="hero-kicker">Category Focus</span>
    <h1><?= e($data['categoryName'] ?? 'Category Picks') ?></h1>
    <p>Curated options with practical use cases, buying context, and internal links that help you compare faster than a raw catalog page.</p>
</section>

<section class="trust-strip">
    <div class="trust-box">Practical shortlist for faster decisions</div>
    <div class="trust-box">Recent sync labels on every product card</div>
    <div class="trust-box">No checkout form on-site, redirect to Amazon</div>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">How to use this <?= e(strtolower($data['categoryName'] ?? 'category')) ?> shortlist</h2>
    <?php if (($data['categoryName'] ?? '') === 'Telescopes'): ?>
        <p class="muted">If you are choosing your first telescope, prioritize ease of setup, mount stability, and how likely you are to use it regularly. Bigger specifications do not always lead to a better beginner experience.</p>
        <p class="muted" style="margin-top:10px;">Use this page to narrow the field, then open the <a href="<?= e(url('/best-beginner-telescopes')) ?>">beginner telescope guide</a> if you want more explanation before buying.</p>
    <?php else: ?>
        <p class="muted">Accessories work best when they solve a specific observing problem. Start with the upgrade that improves comfort, compatibility, or image quality the most for your current setup.</p>
        <p class="muted" style="margin-top:10px;">If you want a decision-first breakdown instead of a plain shortlist, open the <a href="<?= e(url('/best-telescope-accessories')) ?>">telescope accessories guide</a>.</p>
    <?php endif; ?>
</section>

<?php if ($tiers !== []): ?>
    <h2 class="section-title">Best Picks in <?= e($data['categoryName'] ?? 'This Category') ?></h2>
    <div class="tier-grid">
        <article class="tier-card">
            <span class="tier-tag tier-top">Top Pick</span>
            <h4><?= e($tiers['top']['title']) ?></h4>
            <p><?= e(product_best_for($tiers['top'])) ?></p>
        </article>
        <article class="tier-card">
            <span class="tier-tag tier-budget">Budget Pick</span>
            <h4><?= e($tiers['budget']['title']) ?></h4>
            <p><?= e(product_best_for($tiers['budget'])) ?></p>
        </article>
        <article class="tier-card">
            <span class="tier-tag tier-premium">Premium Pick</span>
            <h4><?= e($tiers['premium']['title']) ?></h4>
            <p><?= e(product_best_for($tiers['premium'])) ?></p>
        </article>
    </div>
<?php endif; ?>

<h2 class="section-title"><?= e($data['categoryName'] ?? 'Category') ?></h2>
<p class="muted">Products currently published in this category. Page <?= (int) $currentPage ?> of <?= (int) $totalPages ?>.</p>

<div class="grid">
    <?php foreach ($products as $idx => $item): ?>
        <article class="card">
            <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
                <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $idx === 0 ? 'high' : 'auto' ?>" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
            </a>
            <div class="body">
                <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>">
                    <?= e(relative_time_label($item['last_synced_at'] ?? null)) ?>
                </span>
                <span class="badge"><?= e($item['category_name']) ?></span>
                <h3><?= e($item['title']) ?></h3>
                <p class="card-copy"><?= e($item['description']) ?></p>
                <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
                <p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">Open product page</a></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination" aria-label="<?= e((string) ($data['categoryName'] ?? 'Category')) ?> pagination">
        <div class="pagination-info">
            Page <?= (int) $currentPage ?> of <?= (int) $totalPages ?> · <?= number_format($totalItems) ?> products
        </div>
        <div class="pagination-nav">
            <?php if (!empty($pagination['has_prev'])): ?>
                <a class="pagination-link" href="<?= e($buildCategoryPageUrl((int) $pagination['prev_page'])) ?>">Prev</a>
            <?php endif; ?>
            <?php for ($page = $pageWindow['start']; $page <= $pageWindow['end']; $page++): ?>
                <a class="pagination-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= e($buildCategoryPageUrl($page)) ?>"><?= (int) $page ?></a>
            <?php endfor; ?>
            <?php if (!empty($pagination['has_next'])): ?>
                <a class="pagination-link" href="<?= e($buildCategoryPageUrl((int) $pagination['next_page'])) ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($tiers !== []): ?>
    <section class="panel" style="margin-top: 18px;">
        <h2 class="section-title" style="margin-top: 0;">Quick Comparison</h2>
        <div class="compare-table">
            <div class="compare-row">
                <div class="compare-label">Top</div>
                <div class="compare-value"><span class="pill ok">Top</span> <?= e($tiers['top']['title']) ?> - <?= e(product_best_for($tiers['top'])) ?></div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Budget</div>
                <div class="compare-value"><span class="pill ok">Budget</span> <?= e($tiers['budget']['title']) ?> - <?= e(product_best_for($tiers['budget'])) ?></div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Premium</div>
                <div class="compare-value"><span class="pill warn">Premium</span> <?= e($tiers['premium']['title']) ?> - <?= e(product_best_for($tiers['premium'])) ?></div>
            </div>
        </div>
    </section>
<?php endif; ?>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;"><?= e($data['categoryName'] ?? 'Category') ?> buying guide</h2>
    <p class="muted">Use this shortlist to compare quickly, then move to detail pages for fit and use-case checks. This category is updated through the same micro-CMS workflow, so freshness labels and pricing context stay visible.</p>
    <p><a class="btn" href="<?= e(url(($data['categoryName'] ?? '') === 'Telescopes' ? '/best-beginner-telescopes' : '/best-telescope-accessories')) ?>">Open Related Guide</a></p>
    <p class="muted" style="margin-top: 10px; font-size: 13px;"><a href="<?= e(url('/guides')) ?>">See all astronomy buying guides</a></p>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Frequently asked questions</h2>
    <?php if (($data['categoryName'] ?? '') === 'Telescopes'): ?>
        <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
            <summary style="font-weight: 700; cursor: pointer;">What should I look for in a beginner telescope?</summary>
            <p class="muted" style="margin: 8px 0 0;">Look for a telescope that is easy to set up, stable enough to use comfortably, and realistic for your space and schedule. The <a href="<?= e(url('/best-beginner-telescopes')) ?>">beginner guide</a> explains those tradeoffs in more detail.</p>
        </details>
        <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
            <summary style="font-weight: 700; cursor: pointer;">Is a bigger telescope always better?</summary>
            <p class="muted" style="margin: 8px 0 0;">No. A bigger telescope can show more, but a simpler model often wins if it is easier to move, store, and use often enough to build a real observing habit.</p>
        </details>
    <?php else: ?>
        <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
            <summary style="font-weight: 700; cursor: pointer;">Which telescope accessory should I buy first?</summary>
            <p class="muted" style="margin: 8px 0 0;">Buy the accessory that solves the clearest problem in your sessions, such as comfort, alignment, or magnification flexibility. The <a href="<?= e(url('/best-telescope-accessories')) ?>">accessories guide</a> is the best starting point if you are unsure.</p>
        </details>
        <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
            <summary style="font-weight: 700; cursor: pointer;">Should beginners buy accessory kits?</summary>
            <p class="muted" style="margin: 8px 0 0;">Only if the kit clearly matches your telescope and includes items you will actually use. In many cases, one targeted upgrade is more useful than a bundle.</p>
        </details>
    <?php endif; ?>
</section>
