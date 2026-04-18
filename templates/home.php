<?php
$products = $data['products'] ?? [];
$telescopes = $data['telescopes'] ?? [];
$accessories = $data['accessories'] ?? [];
?>
<section class="hero">
    <span class="hero-kicker">Astronomy Affiliate Guide</span>
    <h1>Find the Best Beginner Telescope and Astronomy Gear for Real Stargazing Nights</h1>
    <p>Compare beginner telescopes, practical accessories, and plain-English buying guides built to help new observers choose gear they will actually use.</p>
    <div class="trust-row">
        <span class="chip">Beginner-friendly recommendations</span>
        <span class="chip">Buying guides matched to search intent</span>
        <span class="chip">Direct links to product detail pages</span>
    </div>
</section>

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Start with the question you are actually asking</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">First telescope</div>
            <div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best beginner telescopes</a> for easy setup, stable viewing, and fewer expensive mistakes.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Budget cap</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best telescopes under $500</a> if you need a realistic shortlist before spending more.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Upgrades</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best telescope accessories</a> when you already own a scope and want better observing sessions.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Learning path</div>
            <div class="compare-value"><a href="<?= e(url('/blog')) ?>">Astronomy blog articles</a> for stargazing tips, setup advice, and beginner research questions.</div>
        </div>
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
                    <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
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
                    <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
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

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">How this site helps with organic search intent</h2>
    <p class="muted">Most telescope buyers do not start on a product page. They start with searches like "best beginner telescope", "what telescope should I buy first", or "best telescope accessories". This site is structured to answer those early questions first, then send you to more specific comparisons and product pages when you are ready.</p>
    <p class="muted" style="margin-top:10px;">If you are completely new, begin with the buying guides. If you already know the category you want, use the telescope and accessory hubs to compare current options faster.</p>
</section>

<section class="panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Frequently asked beginner questions</h2>
    <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">What is the best telescope for a beginner?</summary>
        <p class="muted" style="margin: 8px 0 0;">The best beginner telescope is usually one that is easy to set up, stable enough to use comfortably, and realistic for your observing habits. Start with the <a href="<?= e(url('/best-beginner-telescopes')) ?>">beginner telescope guide</a> if you want a filtered shortlist instead of a raw catalog.</p>
    </details>
    <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">How much should I spend on a first telescope?</summary>
        <p class="muted" style="margin: 8px 0 0;">A reasonable first budget depends on how often you expect to observe and how much setup friction you can tolerate. If budget is your main constraint, go straight to <a href="<?= e(url('/best-telescopes-under-500')) ?>">telescopes under $500</a>.</p>
    </details>
    <details style="border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
        <summary style="font-weight: 700; cursor: pointer;">Which accessories help most after buying a telescope?</summary>
        <p class="muted" style="margin: 8px 0 0;">The best accessories are the ones that solve a real problem in your sessions, such as poor comfort, weak magnification choices, or difficult phone alignment. The <a href="<?= e(url('/best-telescope-accessories')) ?>">accessories guide</a> focuses on those high-impact upgrades.</p>
    </details>
</section>
