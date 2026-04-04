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

        <div class="price-line" style="margin: 14px 0 16px;">
            <span class="price"><?= e(money($product['price_amount'] !== null ? (float) $product['price_amount'] : null, $product['price_currency'])) ?></span>
            <span class="hint">Check availability on Amazon</span>
        </div>
        <div class="trust-strip" style="margin-bottom: 12px;">
            <div class="trust-box">Price and availability can change anytime</div>
            <div class="trust-box">Secure checkout handled by Amazon</div>
            <div class="trust-box">Checking now helps avoid stale price assumptions</div>
        </div>
        <p style="margin: 0 0 12px;">
            <a class="btn amazon-btn" href="<?= e(outbound_url((string) $product['affiliate_url'], (int) ($product['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">Check Price on Amazon</a>
        </p>
        <p class="muted" style="font-size: 13px; margin: 0;">ASIN: <?= e($product['asin']) ?> | External merchant checkout applies.</p>
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

<a class="mobile-sticky-cta amazon-btn" href="<?= e(outbound_url((string) $product['affiliate_url'], (int) ($product['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">Check Price Now - <?= e(money($product['price_amount'] !== null ? (float) $product['price_amount'] : null, $product['price_currency'])) ?> - <?= e($relativeUpdate) ?></a>

