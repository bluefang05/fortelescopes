<?php
$product = $data['product'];
$pros = product_pros($product);
$cons = product_cons($product);
$freshnessClass = sync_freshness_class($product['last_synced_at'] ?? null);
$relativeUpdate = relative_time_label($product['last_synced_at'] ?? null);
?>
<section class="panel" style="max-width: 980px; margin: 0 auto;">
    <a href="<?= e(outbound_url((string) $product['affiliate_url'], (int) ($product['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($product['title']) ?> on Amazon">
        <img src="<?= e(product_image_url($product)) ?>" alt="<?= e($product['title']) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
    </a>
    <div class="body" style="padding: 16px 4px 4px;">
        <span class="badge"><?= e($product['category_name']) ?></span>
        <h1 class="section-title" style="margin-top: 10px;"><?= e($product['title']) ?></h1>
        <p class="muted" style="margin-bottom: 10px;"><?= e($product['description']) ?></p>
        <p style="margin: 0 0 12px;">
            <span class="update-pill <?= e($freshnessClass) ?>"><?= e($relativeUpdate) ?></span>
        </p>

        <div class="compare-table" style="margin-top: 12px; margin-bottom: 14px;">
            <div class="compare-row">
                <div class="compare-label">Best For</div>
                <div class="compare-value"><?= e(product_best_for($product)) ?></div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Pros</div>
                <div class="compare-value">
                    <?php foreach ($pros as $idx => $pro): ?>
                        <span class="pill ok">Pro <?= (int) ($idx + 1) ?></span> <?= e($pro) ?><br>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Cons</div>
                <div class="compare-value">
                    <?php foreach ($cons as $idx => $con): ?>
                        <span class="pill warn">Con <?= (int) ($idx + 1) ?></span> <?= e($con) ?><br>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="trust-strip" style="margin-bottom: 12px;">
            <div class="trust-box">Price and availability can change anytime</div>
            <div class="trust-box">Secure checkout handled by Amazon</div>
            <div class="trust-box">Checking now helps avoid stale assumptions</div>
        </div>
        <p style="margin: 0 0 12px;">
            <a class="btn amazon-btn" href="<?= e(outbound_url((string) $product['affiliate_url'], (int) ($product['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
        </p>
        <p class="muted" style="font-size: 13px; margin: 0;">ASIN: <?= e($product['asin']) ?> | External merchant checkout applies.</p>
    </div>
</section>

<section class="panel" style="max-width: 980px; margin: 18px auto 0;">
    <h2 class="section-title" style="margin-top:0;">Should you buy this?</h2>
    <p class="muted" style="margin-bottom: 10px;">Use this page as a fit check, not just a click-through. The goal is to help you decide whether this product matches your experience level, observing goals, and current telescope setup before you leave for Amazon.</p>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Good fit</div>
            <div class="compare-value"><?= e(product_best_for($product)) ?></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Watch for</div>
            <div class="compare-value"><?= e($cons[0] ?? 'Check compatibility and real usage before buying.') ?></div>
        </div>
    </div>
</section>

<section class="panel" style="max-width: 980px; margin: 18px auto 0;">
    <h2 class="section-title" style="margin-top:0;">Related paths</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Category</div>
            <div class="compare-value"><a href="<?= e(url('/' . $product['category_slug'])) ?>">Browse more in <?= e($product['category_name']) ?></a></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guide</div>
            <div class="compare-value">
                <?php if (($product['category_slug'] ?? '') === 'telescopes'): ?>
                    <a href="<?= e(url('/best-beginner-telescopes')) ?>">Read the beginner telescope guide</a> and compare buying paths.
                <?php else: ?>
                    <a href="<?= e(url('/best-telescope-accessories')) ?>">Read the telescope accessories guide</a> before upgrading.
                <?php endif; ?>
            </div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guides hub</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Open all buying guides</a></div>
        </div>
    </div>
</section>

<section class="panel" style="max-width: 980px; margin: 18px auto 0;">
    <h2 class="section-title" style="margin-top:0;">Questions buyers often ask</h2>
    <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Who is this product best for?</summary>
        <p class="muted" style="margin: 8px 0 0;"><?= e(product_best_for($product)) ?>.</p>
    </details>
    <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;"><?= ($product['category_slug'] ?? '') === 'accessories' ? 'How do I check compatibility before buying?' : 'Is this a good beginner option?' ?></summary>
        <p class="muted" style="margin: 8px 0 0;">
            <?= ($product['category_slug'] ?? '') === 'accessories'
                ? 'Check the accessory size, mounting standard, and whether it solves a real problem in your current setup before buying.'
                : 'It can be a good beginner option if the setup, size, and price match how often you expect to observe and what you want to see first.' ?>
        </p>
    </details>
</section>

<a class="mobile-sticky-cta amazon-btn" href="<?= e(outbound_url((string) $product['affiliate_url'], (int) ($product['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon - <?= e($relativeUpdate) ?></a>

