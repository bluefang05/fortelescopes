<?php
$guides = $data['guides'] ?? [];
$pagination = $data['guides_pagination'] ?? [
    'page' => 1,
    'total_pages' => 1,
    'total_items' => count($guides),
    'has_prev' => false,
    'has_next' => false,
    'prev_page' => 1,
    'next_page' => 1,
];
$currentPage = max(1, (int) ($pagination['page'] ?? 1));
$totalPages = max(1, (int) ($pagination['total_pages'] ?? 1));
$totalItems = max(0, (int) ($pagination['total_items'] ?? 0));
$pageWindow = pagination_window($currentPage, $totalPages, 2);
$buildGuidesPageUrl = static function (int $page): string {
    $safePage = max(1, $page);
    return $safePage === 1 ? url('/guides') : url('/guides?page=' . $safePage);
};
?>
<section class="hero">
    <span class="hero-kicker">Guides Hub</span>
    <h1>Astronomy Buying Guides</h1>
    <p>Actionable telescope and accessory guides built for first-time stargazers, budget-conscious buyers, and people narrowing down their next upgrade.</p>
    <div class="trust-row">
        <span class="chip">Beginner-first explanations</span>
        <span class="chip">Real product examples</span>
        <span class="chip">Decision-first topic clusters</span>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Choose the guide that matches your search</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">"best beginner telescope"</div>
            <div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Open the beginner telescope guide</a> for first-time purchase decisions.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">"best telescope under 500"</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Open the under-$500 guide</a> for budget-bound comparisons.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">"best telescope accessories"</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Open the accessories guide</a> for high-impact upgrades.</div>
        </div>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Buying guides</h2>
    <p class="muted">Start with the guide that matches your current purchase intent, then compare categories and product pages before checkout.</p>
    <div class="grid">
        <?php foreach ($guides as $idx => $guide): ?>
            <?php
            $guideImage = !empty($guide['featured_image']) ? $guide['featured_image'] : match ($guide['slug'] ?? '') {
                'best-beginner-telescopes' => '/assets/img/optimized_1.webp',
                'best-telescope-accessories' => '/assets/img/optimized_2.webp',
                'best-telescopes-under-500' => '/assets/img/optimized_3.webp',
                default => '/assets/img/product-placeholder.svg',
            };
            ?>
            <article class="card">
                <?php if ($guideImage): ?>
                    <img src="<?= e(url($guideImage)) ?>" alt="<?= e($guide['title']) ?>" loading="<?= $idx === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $idx === 0 ? 'high' : 'auto' ?>" width="800" height="600">
                <?php else: ?>
                    <div style="height: 200px; background: #0f1c30; display: flex; align-items: center; justify-content: center; color: #fff;">No Image</div>
                <?php endif; ?>
                <div class="body">
                    <span class="badge">Guide</span>
                    <h3><?= e($guide['title']) ?></h3>
                    <p class="card-copy"><?= e($guide['excerpt'] ?? '') ?></p>
                    <a class="card-cta" href="<?= e(url('/' . $guide['slug'])) ?>">Open guide</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
        <div class="pagination" aria-label="Guides pagination">
            <div class="pagination-info">
                Page <?= (int) $currentPage ?> of <?= (int) $totalPages ?> · <?= number_format($totalItems) ?> guides
            </div>
            <div class="pagination-nav">
                <?php if (!empty($pagination['has_prev'])): ?>
                    <a class="pagination-link" href="<?= e($buildGuidesPageUrl((int) $pagination['prev_page'])) ?>">Prev</a>
                <?php endif; ?>
                <?php for ($page = $pageWindow['start']; $page <= $pageWindow['end']; $page++): ?>
                    <a class="pagination-link <?= $page === $currentPage ? 'active' : '' ?>" href="<?= e($buildGuidesPageUrl($page)) ?>"><?= (int) $page ?></a>
                <?php endfor; ?>
                <?php if (!empty($pagination['has_next'])): ?>
                    <a class="pagination-link" href="<?= e($buildGuidesPageUrl((int) $pagination['next_page'])) ?>">Next</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Category paths</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Telescopes</div>
            <div class="compare-value"><a href="<?= e(url('/telescopes')) ?>">Browse telescope recommendations</a> and compare beginner to premium options.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Accessories</div>
            <div class="compare-value"><a href="<?= e(url('/accessories')) ?>">Browse accessory recommendations</a> for eyepieces, filters, adapters, and practical upgrades.</div>
        </div>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Frequently asked questions</h2>
    <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Which astronomy buying guide should I start with?</summary>
        <p class="muted" style="margin: 8px 0 0;">Start with the guide that matches your immediate decision: first telescope, budget limit, or accessories. That gets you to the right internal pages faster than browsing products at random.</p>
    </details>
    <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Do these guides replace product research?</summary>
        <p class="muted" style="margin: 8px 0 0;">No. They narrow the field, explain tradeoffs, and then link you into product and category pages so you can validate fit before buying.</p>
    </details>
</section>
