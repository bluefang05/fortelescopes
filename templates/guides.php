<?php
$guides = $data['guides'] ?? [];
?>
<section class="hero">
    <span class="hero-kicker">Guides Hub</span>
    <h1>Astronomy Buying Guides</h1>
    <p>Actionable telescope and accessory guides built for first-time stargazers and practical buyers.</p>
    <div class="trust-row">
        <span class="chip">Beginner-first explanations</span>
        <span class="chip">Real product examples</span>
        <span class="chip">Conversion-focused CTAs</span>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Featured guides</h2>
    <p class="muted">Start with the guide that matches your current purchase intent, then compare categories and product pages before checkout.</p>
    <div class="grid">
        <?php foreach ($guides as $guide): ?>
            <?php
            $guideImage = $guide['featured_image'] ?: match ($guide['slug'] ?? '') {
                'best-beginner-telescopes' => '/assets/img/optimized_1.webp',
                'best-telescope-accessories' => '/assets/img/optimized_2.webp',
                'best-telescopes-under-500' => '/assets/img/optimized_3.webp',
                default => '/assets/img/product-placeholder.svg',
            };
            ?>
            <article class="card">
                <img src="<?= e(url($guideImage)) ?>" alt="<?= e($guide['title']) ?>" loading="lazy">
                <div class="body">
                    <span class="badge">Guide</span>
                    <h3><?= e($guide['title']) ?></h3>
                    <p class="card-copy"><?= e($guide['excerpt'] ?? '') ?></p>
                    <a class="card-cta" href="<?= e(url('/' . $guide['slug'])) ?>">Open guide</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
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
