<?php
$products = $data['products'] ?? [];
$telescopes = $data['telescopes'] ?? [];
$accessories = $data['accessories'] ?? [];
?>
<section class="hero">
    <span class="hero-kicker">Astronomy Affiliate Guide</span>
    <h1>Find the Right Telescope Gear for Better Stargazing Nights</h1>
    <p>Explore beginner telescopes, practical accessories, and clear buying guides built for real night-sky sessions.</p>
    <div class="trust-row">
        <span class="chip">Beginner-friendly recommendations</span>
        <span class="chip">Clean category navigation</span>
        <span class="chip">Direct links to Amazon products</span>
    </div>
</section>

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Best beginner telescopes</h2>
    <p class="muted">Start here if you are buying your first telescope. These picks balance ease of use, practical setup, and value.</p>
    <div class="grid">
        <?php foreach ($telescopes as $item): ?>
            <article class="card">
                <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
                    <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
                </a>
                <div class="body">
                    <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span>
                    <span class="badge"><?= e($item['category_name']) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <p class="card-copy"><?= e($item['description']) ?></p>
                    <div class="price-line">
                        <span class="price"><?= e(money($item['price_amount'] !== null ? (float)$item['price_amount'] : null, $item['price_currency'])) ?></span>
                        <span class="hint">Check availability on Amazon</span>
                    </div>
                    <a class="card-cta" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">Check price on Amazon</a>
                    <p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">See details</a></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p style="margin-top:14px;"><a class="btn" href="<?= e(url('/telescopes')) ?>">Browse all telescopes</a></p>
</section>

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Popular accessories</h2>
    <p class="muted">Adapters, eyepieces, finders, filters, and storage gear that improve your observing workflow.</p>
    <div class="grid">
        <?php foreach ($accessories as $item): ?>
            <article class="card">
                <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
                    <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
                </a>
                <div class="body">
                    <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span>
                    <span class="badge"><?= e($item['category_name']) ?></span>
                    <h3><?= e($item['title']) ?></h3>
                    <p class="card-copy"><?= e($item['description']) ?></p>
                    <div class="price-line">
                        <span class="price"><?= e(money($item['price_amount'] !== null ? (float)$item['price_amount'] : null, $item['price_currency'])) ?></span>
                        <span class="hint">Check availability on Amazon</span>
                    </div>
                    <a class="card-cta" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
                    <p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">See details</a></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p style="margin-top:14px;"><a class="btn" href="<?= e(url('/accessories')) ?>">Browse all accessories</a></p>
</section>

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Featured guides</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Guide</div>
            <div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best Beginner Telescopes</a> - practical first purchases for stargazing.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guide</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best Telescope Accessories</a> - high-impact upgrades for better sessions.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guide</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best Telescopes Under $500</a> - value-focused shortlist.</div>
        </div>
    </div>
    <p class="muted" style="margin-top: 10px; font-size: 13px;"><a href="<?= e(url('/guides')) ?>">Browse full guides hub</a></p>
</section>
