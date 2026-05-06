<?php
$products = $data['products'] ?? [];
$telescopes = $data['telescopes'] ?? [];
$accessories = $data['accessories'] ?? [];
$homeHeroImage = trim((string) ($data['home_hero_image'] ?? ''));
$homeHeroImage2x = trim((string) ($data['home_hero_image_2x'] ?? ''));
$homeHeroAlt = trim((string) ($data['home_hero_alt'] ?? ''));
$homeHeroTitle = trim((string) ($data['home_hero_title'] ?? ''));
$homeHeroSubtitle = trim((string) ($data['home_hero_subtitle'] ?? ''));
$homeHeroEyebrow = trim((string) ($data['home_hero_eyebrow'] ?? 'Astronomy Affiliate Guide'));
$homeHeroCtaLabel = trim((string) ($data['home_hero_cta_label'] ?? ''));
$homeHeroCtaUrl = trim((string) ($data['home_hero_cta_url'] ?? '/telescopes#telescopes-grid'));
if ($homeHeroCtaUrl === '') {
    $homeHeroCtaUrl = '/telescopes#telescopes-grid';
}
$homeHeroOverlay = (int) ($data['home_hero_overlay'] ?? 55);
$homeHeroOverlay = max(15, min(85, $homeHeroOverlay));
$homeHeroTextPosition = trim((string) ($data['home_hero_text_position'] ?? 'center'));
$homeHeroOverlayStrength = trim((string) ($data['home_hero_overlay_strength'] ?? 'dark'));
$homeHeroLayoutSize = trim((string) ($data['home_hero_layout_size'] ?? 'full'));
$promoTile1 = [
    'image' => trim((string) ($data['home_promo_tile_1_image'] ?? '')),
    'eyebrow' => trim((string) ($data['home_promo_tile_1_eyebrow'] ?? '')),
    'title' => trim((string) ($data['home_promo_tile_1_title'] ?? '')),
    'subtitle' => trim((string) ($data['home_promo_tile_1_subtitle'] ?? '')),
    'cta_label' => trim((string) ($data['home_promo_tile_1_cta_label'] ?? '')),
    'cta_url' => trim((string) ($data['home_promo_tile_1_cta_url'] ?? '')),
    'text_position' => trim((string) ($data['home_promo_tile_1_text_position'] ?? 'bottom-left')),
    'overlay_strength' => trim((string) ($data['home_promo_tile_1_overlay_strength'] ?? 'medium')),
    'layout_size' => trim((string) ($data['home_promo_tile_1_layout_size'] ?? 'half')),
];
$promoTile2 = [
    'image' => trim((string) ($data['home_promo_tile_2_image'] ?? '')),
    'eyebrow' => trim((string) ($data['home_promo_tile_2_eyebrow'] ?? '')),
    'title' => trim((string) ($data['home_promo_tile_2_title'] ?? '')),
    'subtitle' => trim((string) ($data['home_promo_tile_2_subtitle'] ?? '')),
    'cta_label' => trim((string) ($data['home_promo_tile_2_cta_label'] ?? '')),
    'cta_url' => trim((string) ($data['home_promo_tile_2_cta_url'] ?? '')),
    'text_position' => trim((string) ($data['home_promo_tile_2_text_position'] ?? 'bottom-left')),
    'overlay_strength' => trim((string) ($data['home_promo_tile_2_overlay_strength'] ?? 'medium')),
    'layout_size' => trim((string) ($data['home_promo_tile_2_layout_size'] ?? 'half')),
];
$homeBanners = [];
for ($i = 1; $i <= 2; $i++) {
    $homeBanners[] = [
        'image' => trim((string) ($data['home_banner_' . $i . '_image'] ?? '')),
        'eyebrow' => trim((string) ($data['home_banner_' . $i . '_eyebrow'] ?? '')),
        'title' => trim((string) ($data['home_banner_' . $i . '_title'] ?? '')),
        'subtitle' => trim((string) ($data['home_banner_' . $i . '_subtitle'] ?? '')),
        'cta_label' => trim((string) ($data['home_banner_' . $i . '_cta_label'] ?? '')),
        'cta_url' => trim((string) ($data['home_banner_' . $i . '_cta_url'] ?? '')),
        'text_position' => trim((string) ($data['home_banner_' . $i . '_text_position'] ?? 'left')),
        'overlay_strength' => trim((string) ($data['home_banner_' . $i . '_overlay_strength'] ?? 'medium')),
        'layout_size' => trim((string) ($data['home_banner_' . $i . '_layout_size'] ?? 'full')),
    ];
}
$shopGoals = [];
for ($i = 1; $i <= 4; $i++) {
    $shopGoals[] = [
        'label' => trim((string) ($data['home_goal_' . $i . '_label'] ?? '')),
        'url' => trim((string) ($data['home_goal_' . $i . '_url'] ?? '')),
    ];
}
$homeFaqs = [
    [
        'question' => trim((string) ($data['home_faq_1_question'] ?? 'What is the best telescope for a beginner?')),
        'answer' => trim((string) ($data['home_faq_1_answer'] ?? 'The best beginner telescope is usually one that is easy to set up, stable enough to use comfortably, and realistic for your observing habits. Start with the beginner telescope guide if you want a filtered shortlist instead of a raw catalog.')),
    ],
    [
        'question' => trim((string) ($data['home_faq_2_question'] ?? 'How much should I spend on a first telescope?')),
        'answer' => trim((string) ($data['home_faq_2_answer'] ?? 'A reasonable first budget depends on how often you expect to observe and how much setup friction you can tolerate. If budget is your main constraint, go straight to telescopes under $500.')),
    ],
    [
        'question' => trim((string) ($data['home_faq_3_question'] ?? 'Which accessories help most after buying a telescope?')),
        'answer' => trim((string) ($data['home_faq_3_answer'] ?? 'The best accessories are the ones that solve a real problem in your sessions, such as poor comfort, weak magnification choices, or difficult phone alignment. The accessories guide focuses on those high-impact upgrades.')),
    ],
];
$homeFeaturedProducts = $data['home_featured_products'] ?? [];
$hasHomeHeroImage = $homeHeroImage !== '';
$heroTitleText = $homeHeroTitle !== '' ? $homeHeroTitle : 'Find the Best Beginner Telescope and Astronomy Gear for Real Stargazing Nights';
$heroSubtitleText = $homeHeroSubtitle !== '' ? $homeHeroSubtitle : 'Compare beginner telescopes, practical accessories, and plain-English buying guides built to help new observers choose gear they will actually use.';
$heroCtaText = $homeHeroCtaLabel !== '' ? $homeHeroCtaLabel : 'Explore Telescopes';
$tile1TitleText = $promoTile1['title'];
$tile2TitleText = $promoTile2['title'];
if ($tile1TitleText === '' || strtolower($tile1TitleText) === 'eye it yourself') {
    $tile1TitleText = 'Start Stargazing Tonight';
}
if ($tile2TitleText === '') {
    $tile2TitleText = 'Beginner Telescope Picks';
}

$homeTrustBadges = static function (array $item): array {
    $title = strtolower(trim((string) ($item['title'] ?? '')));
    $category = strtolower(trim((string) ($item['category_slug'] ?? '')));
    $badges = [];
    if (str_contains($title, 'beginner') || str_contains($title, 'easy') || str_contains($title, 'starter')) {
        $badges[] = 'Beginner Pick';
    }
    if (str_contains($title, 'portable') || str_contains($title, 'compact') || str_contains($title, 'travel')) {
        $badges[] = 'Portable';
    }
    if (str_contains($title, 'under $500') || str_contains($title, 'budget') || str_contains($title, 'affordable')) {
        $badges[] = 'Budget Friendly';
    }
    if ($category === 'accessories') {
        $badges[] = 'High-Impact Upgrade';
    }
    if ($badges === []) {
        $badges[] = 'Top Rated Choice';
    }

    return array_slice(array_values(array_unique($badges)), 0, 2);
};

$renderVisualBlock = static function (array $block, array $options = []): string {
    $image = trim((string) ($block['image'] ?? ''));
    if ($image === '') {
        return '';
    }
    $eyebrow = trim((string) ($block['eyebrow'] ?? ''));
    $title = trim((string) ($block['title'] ?? ''));
    $subtitle = trim((string) ($block['subtitle'] ?? ''));
    $ctaLabel = trim((string) ($block['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($block['cta_url'] ?? ''));
    $textPos = trim((string) ($block['text_position'] ?? 'center'));
    $overlay = trim((string) ($block['overlay_strength'] ?? 'medium'));
    $size = trim((string) ($block['layout_size'] ?? 'full'));
    $alt = trim((string) ($block['alt'] ?? ($title !== '' ? $title : 'Visual block image')));
    $extraClass = trim((string) ($options['extra_class'] ?? ''));
    $classes = 'visual-block ' . $extraClass . ' visual-pos-' . preg_replace('/[^a-z\-]/', '', strtolower($textPos))
        . ' visual-overlay-' . preg_replace('/[^a-z]/', '', strtolower($overlay))
        . ' visual-size-' . preg_replace('/[^a-z\-]/', '', strtolower($size));
    $loading = trim((string) ($options['loading'] ?? 'lazy'));
    $decoding = trim((string) ($options['decoding'] ?? 'async'));
    $fetchpriority = trim((string) ($options['fetchpriority'] ?? 'auto'));
    $srcset2x = trim((string) ($options['srcset_2x'] ?? ''));
    $extraHtml = (string) ($options['extra_html'] ?? '');
    $ctaClass = trim((string) ($options['cta_class'] ?? ''));
    $linkWholeTile = (bool) ($options['link_whole_tile'] ?? false);
    $tileLinkLabel = trim((string) ($options['tile_link_label'] ?? ''));
    if ($tileLinkLabel === '') {
        $tileLinkLabel = $title !== '' ? $title : ($ctaLabel !== '' ? $ctaLabel : 'Open content');
    }

    ob_start(); ?>
    <article class="<?= e($classes) ?>">
        <img
            src="<?= e(url($image)) ?>"
            <?php if ($srcset2x !== ''): ?>srcset="<?= e(url($image)) ?> 1x, <?= e(url($srcset2x)) ?> 2x"<?php endif; ?>
            alt="<?= e($alt) ?>"
            loading="<?= e($loading) ?>"
            decoding="<?= e($decoding) ?>"
            fetchpriority="<?= e($fetchpriority) ?>">
        <?php if ($linkWholeTile && $ctaUrl !== ''): ?>
            <a class="visual-block-link" href="<?= e(url($ctaUrl)) ?>" aria-label="<?= e($tileLinkLabel) ?>"></a>
        <?php endif; ?>
        <div class="visual-overlay-content">
            <?php if ($eyebrow !== '' || $title !== '' || $subtitle !== ''): ?>
                <div class="visual-text-block">
                    <?php if ($eyebrow !== ''): ?><span class="visual-eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
                    <?php if ($title !== ''): ?><h3><?= e($title) ?></h3><?php endif; ?>
                    <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if ($ctaLabel !== '' && $ctaUrl !== ''): ?><a class="btn promo-cta <?= e($ctaClass) ?>" href="<?= e(url($ctaUrl)) ?>"><?= e($ctaLabel) ?></a><?php endif; ?>
            <?= $extraHtml ?>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
};
?>
<?php if ($hasHomeHeroImage): ?>
    <?php
    $heroTrustHtml =
        '<div class="trust-row">'
        . '<span class="chip">Beginner-friendly recommendations</span>'
        . '<span class="chip">Buying guides matched to search intent</span>'
        . '<span class="chip">Direct links to product detail pages</span>'
        . '</div>';
    $heroBlock = [
        'image' => $homeHeroImage,
        'eyebrow' => $homeHeroEyebrow !== '' ? $homeHeroEyebrow : 'Astronomy Affiliate Guide',
        'title' => $heroTitleText,
        'subtitle' => $heroSubtitleText,
        'cta_label' => $heroCtaText,
        'cta_url' => $homeHeroCtaUrl,
        'text_position' => $homeHeroTextPosition,
        'overlay_strength' => $homeHeroOverlayStrength,
        'layout_size' => $homeHeroLayoutSize,
        'alt' => $homeHeroAlt !== '' ? $homeHeroAlt : 'Home hero image',
    ];
    ?>
    <section class="hero hero--with-image" style="--hero-overlay-opacity: <?= e((string) ($homeHeroOverlay / 100)) ?>;">
        <?= $renderVisualBlock($heroBlock, [
            'extra_class' => 'hero-visual-block',
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'srcset_2x' => $homeHeroImage2x !== '' ? $homeHeroImage2x : $homeHeroImage,
            'extra_html' => $heroTrustHtml,
            'cta_class' => 'hero-main-cta',
        ]) ?>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="hero-content">
            <span class="hero-kicker"><?= e($homeHeroEyebrow !== '' ? $homeHeroEyebrow : 'Astronomy Affiliate Guide') ?></span>
            <h1><?= e($heroTitleText) ?></h1>
            <p><?= e($heroSubtitleText) ?></p>
            <div class="trust-row">
                <span class="chip">Beginner-friendly recommendations</span>
                <span class="chip">Buying guides matched to search intent</span>
                <span class="chip">Direct links to product detail pages</span>
            </div>
        </div>
    </section>
<?php endif; ?>
<?php if ($promoTile1['image'] !== '' || $promoTile2['image'] !== ''): ?>
<section class="promo-strip">
    <?php
    $promoTile1['title'] = $tile1TitleText;
    $promoTile2['title'] = $tile2TitleText;
    echo $renderVisualBlock($promoTile1, ['link_whole_tile' => true]);
    echo $renderVisualBlock($promoTile2, ['link_whole_tile' => true]);
    ?>
    <?php if ($shopGoals !== []): ?>
        <div class="promo-goal-links">
            <?php foreach ($shopGoals as $idx => $goal): ?>
                <?php if ($goal['label'] === '' || $goal['url'] === '') { continue; } ?>
                <a class="<?= $idx === 0 ? 'is-primary' : '' ?>" href="<?= e(url($goal['url'])) ?>"><?= e($goal['label']) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<div class="content-constrained">
<?php if ($homeFeaturedProducts !== []): ?>
<section class="panel" id="most-loved" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Most-Loved Gear</h2>
    <p class="muted">Hand-picked products to start faster with fewer mistakes.</p>
    <div class="grid">
        <?php foreach ($homeFeaturedProducts as $item): ?>
            <article class="card">
                <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon"><img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';"></a>
                <div class="body">
                    <?php foreach ($homeTrustBadges($item) as $badge): ?><span class="mini-trust"><?= e($badge) ?></span><?php endforeach; ?>
                    <h3><?= e($item['title']) ?></h3>
                    <p class="card-copy"><?= e($item['description']) ?></p>
                    <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">Shop</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel" id="section-telescopes" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Start with the question you are actually asking</h2>
    <div class="compare-table">
        <div class="compare-row"><div class="compare-label">First telescope</div><div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best beginner telescopes</a> for easy setup, stable viewing, and fewer expensive mistakes.</div></div>
        <div class="compare-row"><div class="compare-label">Budget cap</div><div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best telescopes under $500</a> if you need a realistic shortlist before spending more.</div></div>
        <div class="compare-row"><div class="compare-label">Upgrades</div><div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best telescope accessories</a> when you already own a scope and want better observing sessions.</div></div>
        <div class="compare-row"><div class="compare-label">Learning path</div><div class="compare-value"><a href="<?= e(url('/blog')) ?>">Astronomy blog articles</a> for stargazing tips, setup advice, and beginner research questions.</div></div>
    </div>
</section>

<section class="panel" id="section-accessories" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Best beginner telescopes</h2>
    <p class="muted">Start here if you are buying your first telescope. These picks balance ease of use, practical setup, and value.</p>
    <div class="grid"><?php foreach ($telescopes as $item): ?><article class="card"><a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon"><img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';"></a><div class="body"><span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span><span class="badge"><?= e($item['category_name']) ?></span><h3><?= e($item['title']) ?></h3><p class="card-copy"><?= e($item['description']) ?></p><a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a><p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">See details</a></p></div></article><?php endforeach; ?></div>
    <p style="margin-top:14px;"><a class="btn" href="<?= e(url('/telescopes')) ?>">Browse all telescopes</a></p>
</section>

<?php foreach ($homeBanners as $banner): ?>
    <?php $bannerHtml = $renderVisualBlock($banner); if ($bannerHtml !== ''): ?>
        <section class="promo-strip banner-strip"><?= $bannerHtml ?></section>
    <?php endif; ?>
<?php endforeach; ?>

<section class="panel" id="section-guides" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Popular accessories</h2>
    <p class="muted">Adapters, eyepieces, finders, filters, and storage gear that improve your observing workflow.</p>
    <div class="grid"><?php foreach ($accessories as $item): ?><article class="card"><a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon"><img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';"></a><div class="body"><span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span><span class="badge"><?= e($item['category_name']) ?></span><h3><?= e($item['title']) ?></h3><p class="card-copy"><?= e($item['description']) ?></p><a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a><p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">See details</a></p></div></article><?php endforeach; ?></div>
    <p style="margin-top:14px;"><a class="btn" href="<?= e(url('/accessories')) ?>">Browse all accessories</a></p>
</section>

<section class="panel" style="margin-bottom:18px;"><h2 class="section-title" style="margin-top:0;">Featured guides</h2><div class="compare-table"><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best Beginner Telescopes</a> - practical first purchases for stargazing.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best Telescope Accessories</a> - high-impact upgrades for better sessions.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best Telescopes Under $500</a> - value-focused shortlist.</div></div></div><p class="muted" style="margin-top: 10px; font-size: 13px;"><a href="<?= e(url('/guides')) ?>">Browse full guides hub</a></p></section>

<section class="panel" style="margin-bottom:18px;"><h2 class="section-title" style="margin-top:0;">How this site helps with organic search intent</h2><p class="muted">Most telescope buyers do not start on a product page. They start with searches like "best beginner telescope", "what telescope should I buy first", or "best telescope accessories". This site is structured to answer those early questions first, then send you to more specific comparisons and product pages when you are ready.</p><p class="muted" style="margin-top:10px;">If you are completely new, begin with the buying guides. If you already know the category you want, use the telescope and accessory hubs to compare current options faster.</p></section>
<?php if (!$hasHomeHeroImage): ?>
<a class="mobile-context-cta" id="mobile-context-cta" href="<?= e(url('/best-beginner-telescopes')) ?>">Start with Beginner Telescopes</a>
<?php endif; ?>

<section class="panel faq-panel" style="margin-bottom:18px;">
    <h2 class="section-title" style="margin-top:0;">Frequently asked beginner questions</h2>
    <?php foreach ($homeFaqs as $faq): ?>
        <?php if ($faq['question'] === '' || $faq['answer'] === '') { continue; } ?>
        <details>
            <summary><?= e($faq['question']) ?></summary>
            <p class="muted"><?= nl2br(e($faq['answer'])) ?></p>
        </details>
    <?php endforeach; ?>
</section>
</div>
