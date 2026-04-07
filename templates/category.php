<?php
$products = $data['products'] ?? [];
$tiers = pick_tier_products($products);
?>
<section class="hero">
    <span class="hero-kicker">Category Focus</span>
    <h1><?= e($data['categoryName'] ?? 'Category Picks') ?></h1>
    <p>Curated options with practical use cases, not random catalog dumps. Compare fast, then jump to the final merchant page when you are ready.</p>
</section>

<section class="trust-strip">
    <div class="trust-box">Practical shortlist for faster decisions</div>
    <div class="trust-box">Recent sync labels on every product card</div>
    <div class="trust-box">No checkout form on-site, redirect to Amazon</div>
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
<p class="muted">Products currently published in this category.</p>

<div class="grid">
    <?php foreach ($products as $idx => $item): ?>
        <article class="card">
            <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
                <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
            </a>
            <div class="body">
                <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>">
                    <?= e(relative_time_label($item['last_synced_at'] ?? null)) ?>
                </span>
                <span class="badge"><?= e($item['category_name']) ?></span>
                <h3><?= e($item['title']) ?></h3>
                <p class="card-copy"><?= e($item['description']) ?></p>
                <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">
                    <?= $idx === 0 ? 'View on Amazon' : 'View on Amazon' ?>
                </a>
                <p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">Open product page</a></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>

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

