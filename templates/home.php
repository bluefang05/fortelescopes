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
    'overlay_link_label' => trim((string) ($data['home_promo_tile_1_overlay_link_label'] ?? '')),
    'overlay_link_url' => trim((string) ($data['home_promo_tile_1_overlay_link_url'] ?? '')),
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
    'overlay_link_label' => trim((string) ($data['home_promo_tile_2_overlay_link_label'] ?? '')),
    'overlay_link_url' => trim((string) ($data['home_promo_tile_2_overlay_link_url'] ?? '')),
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
$heroTitleText = $homeHeroTitle !== '' ? $homeHeroTitle : 'Find Your First Telescope';
$heroSubtitleText = $homeHeroSubtitle !== '' ? $homeHeroSubtitle : 'Clear beginner picks, practical accessories, and plain-English guides so you can buy once and start observing faster.';
$heroCtaText = $homeHeroCtaLabel !== '' ? $homeHeroCtaLabel : 'Explore Telescopes';
$heroSocialProofText = 'Trusted by 50,000+ beginner readers.';
$homeH1Text = trim((string) ($data['home_h1_text'] ?? ''));
$homeH1Text = $homeH1Text !== '' ? $homeH1Text : $heroTitleText;
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
    $imageSrc = content_asset_path($image);
    $eyebrow = trim((string) ($block['eyebrow'] ?? ''));
    $title = trim((string) ($block['title'] ?? ''));
    $subtitle = trim((string) ($block['subtitle'] ?? ''));
    $ctaLabel = trim((string) ($block['cta_label'] ?? ''));
    $ctaUrl = trim((string) ($block['cta_url'] ?? ''));
    $overlayLinkLabel = trim((string) ($block['overlay_link_label'] ?? ''));
    $overlayLinkUrl = trim((string) ($block['overlay_link_url'] ?? ''));
    if ($overlayLinkLabel === '' && $overlayLinkUrl !== '') {
        $overlayLinkLabel = 'Learn more';
    }
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

    $tileTargetUrl = $overlayLinkUrl !== '' ? $overlayLinkUrl : $ctaUrl;
    ob_start(); ?>
    <article class="<?= e($classes) ?>">
        <img
            src="<?= e($imageSrc) ?>"
            <?php if ($srcset2x !== ''): ?>srcset="<?= e($imageSrc) ?> 1x, <?= e(content_asset_path($srcset2x)) ?> 2x"<?php endif; ?>
            alt="<?= e($alt) ?>"
            loading="<?= e($loading) ?>"
            decoding="<?= e($decoding) ?>"
            fetchpriority="<?= e($fetchpriority) ?>">
        <?php if ($linkWholeTile && $tileTargetUrl !== ''): ?>
            <a class="visual-block-link" href="<?= e(url($tileTargetUrl)) ?>" aria-label="<?= e($tileLinkLabel) ?>"></a>
        <?php endif; ?>
        <div class="visual-overlay-content">
            <?php if ($eyebrow !== '' || $title !== '' || $subtitle !== ''): ?>
                <?php if ($overlayLinkUrl !== ''): ?>
                    <a class="visual-text-block visual-text-block-link" href="<?= e(url($overlayLinkUrl)) ?>" aria-label="<?= e($overlayLinkLabel) ?>">
                <?php else: ?>
                    <div class="visual-text-block">
                <?php endif; ?>
                    <?php if ($eyebrow !== ''): ?><span class="visual-eyebrow"><?= e($eyebrow) ?></span><?php endif; ?>
                    <?php if ($title !== ''): ?><h3><?= e($title) ?></h3><?php endif; ?>
                    <?php if ($subtitle !== ''): ?><p><?= e($subtitle) ?></p><?php endif; ?>
                    <?php if ($overlayLinkUrl !== ''): ?><span class="visual-inline-link"><?= e($overlayLinkLabel) ?></span><?php endif; ?>
                <?php if ($overlayLinkUrl !== ''): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($ctaLabel !== '' && $ctaUrl !== ''): ?><a class="btn promo-cta <?= e($ctaClass) ?>" href="<?= e(url($ctaUrl)) ?>"><?= e($ctaLabel) ?></a><?php endif; ?>
            <?= $extraHtml ?>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
};

$renderProductCard = static function (array $item, string $ctaLabel = 'View on Amazon', ?callable $badgeCallback = null): string {
    $outbound = outbound_url((string) ($item['affiliate_url'] ?? ''), (int) ($item['id'] ?? 0));
    $detailUrl = url('/product/' . (string) ($item['slug'] ?? ''));
    $badges = $badgeCallback !== null ? $badgeCallback($item) : [];
    $ratingRaw = null;
    foreach (['rating', 'amazon_rating', 'stars', 'review_rating'] as $key) {
        if (isset($item[$key]) && is_numeric((string) $item[$key])) {
            $ratingRaw = (float) $item[$key];
            break;
        }
    }
    $reviewsRaw = null;
    foreach (['review_count', 'reviews_count', 'amazon_reviews', 'rating_count'] as $key) {
        if (isset($item[$key]) && is_numeric((string) $item[$key])) {
            $reviewsRaw = (int) $item[$key];
            break;
        }
    }
    $hasExternalRating = $ratingRaw !== null && $ratingRaw > 0;
    $displayRating = $hasExternalRating ? number_format(min(5, max(0, $ratingRaw)), 1) : editorial_stars($item);
    $reviewsLabel = $reviewsRaw !== null && $reviewsRaw > 0 ? number_format($reviewsRaw) . ' reviews' : 'Editorial score';
    ob_start(); ?>
    <article class="card product-card">
        <a class="product-card-media" href="<?= e($outbound) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
            <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
        </a>
        <div class="body product-card-body">
            <?php if ($badges !== []): ?>
                <div class="card-meta">
                    <?php foreach ($badges as $badge): ?><span class="mini-trust"><?= e($badge) ?></span><?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-meta">
                    <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span>
                    <span class="badge"><?= e($item['category_name']) ?></span>
                </div>
            <?php endif; ?>
            <h3><?= e($item['title']) ?></h3>
            <p class="rating-line"><span class="stars"><?= e($displayRating) ?> ★</span> · <?= e($reviewsLabel) ?></p>
            <p class="card-copy"><?= e($item['description']) ?></p>
            <div class="card-actions">
                <a class="card-cta amazon-btn" href="<?= e($outbound) ?>" target="_blank" rel="nofollow sponsored noopener"><?= e($ctaLabel) ?></a>
                <a class="detail-link" href="<?= e($detailUrl) ?>">Details</a>
            </div>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
};

$budgetTelescopes = array_values(array_filter($telescopes, static function (array $item): bool {
    return isset($item['price_amount']) && is_numeric((string) $item['price_amount']) && (float) $item['price_amount'] > 0 && (float) $item['price_amount'] <= 200;
}));
$budgetScopedTelescopes = $budgetTelescopes !== [];
$telescopesForHome = $budgetTelescopes;
$budgetSectionKicker = 'Budget picks';
$budgetSectionTitle = 'Best beginner telescopes under $200';
$budgetSectionCopy = 'Price-capped shortlist for first-time buyers who want simple setup without stretching budget.';

if (!$budgetScopedTelescopes) {
    $pricedTelescopes = array_values(array_filter($telescopes, static function (array $item): bool {
        return isset($item['price_amount']) && is_numeric((string) $item['price_amount']) && (float) $item['price_amount'] > 0;
    }));
    usort($pricedTelescopes, static function (array $a, array $b): int {
        return ((float) ($a['price_amount'] ?? 0)) <=> ((float) ($b['price_amount'] ?? 0));
    });
    $telescopesForHome = array_slice($pricedTelescopes !== [] ? $pricedTelescopes : $telescopes, 0, 6);
    $budgetSectionKicker = 'Budget-first picks';
    $budgetSectionTitle = 'Budget beginner telescopes (closest to $200)';
    $budgetSectionCopy = 'No current options are synced under $200, so these are the closest beginner-friendly budget picks available now.';
}
?>
<?php if ($hasHomeHeroImage): ?>
    <?php
    $heroTrustHtml =
        '<div class="trust-row">'
        . '<span class="chip">Beginner-focused gear shortlists</span>'
        . '<span class="chip">Comparison-first buying guides</span>'
        . '<span class="chip">Clear paths for telescopes, accessories, and reviews</span>'
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
        <h1 class="sr-only"><?= e($homeH1Text) ?></h1>
        <?= $renderVisualBlock($heroBlock, [
            'extra_class' => 'hero-visual-block',
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'srcset_2x' => $homeHeroImage2x !== '' ? $homeHeroImage2x : $homeHeroImage,
            'extra_html' => $heroTrustHtml,
            'cta_class' => 'hero-main-cta',
        ]) ?>
        <p class="hero-social-proof"><?= e($heroSocialProofText) ?></p>
    </section>
<?php else: ?>
    <section class="hero">
        <div class="hero-content">
            <span class="hero-kicker"><?= e($homeHeroEyebrow !== '' ? $homeHeroEyebrow : 'Astronomy Affiliate Guide') ?></span>
            <h1><?= e($homeH1Text) ?></h1>
            <p><?= e($heroSubtitleText) ?></p>
            <a class="btn hero-main-cta" href="<?= e(url($homeHeroCtaUrl)) ?>"><?= e($heroCtaText) ?></a>
            <p class="hero-social-proof"><?= e($heroSocialProofText) ?></p>
            <div class="trust-row">
                <span class="chip">Beginner-focused gear shortlists</span>
                <span class="chip">Comparison-first buying guides</span>
                <span class="chip">Clear paths for telescopes, accessories, and reviews</span>
            </div>
        </div>
    </section>
<?php endif; ?>
<nav class="home-jump-nav" aria-label="Jump to homepage sections">
    <span class="home-jump-nav-label">Jump to:</span>
    <a href="#section-editors-picks">Editor picks</a>
    <a href="#section-telescopes">Telescopes</a>
    <a href="#section-accessories">Accessories</a>
    <a href="#section-guides">Guides</a>
    <a href="#section-faq">FAQ</a>
</nav>
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
<section class="panel home-section home-featured-section" id="section-editors-picks">
    <div class="section-heading">
        <span class="section-kicker">Mixed shortlist</span>
        <h2 class="section-title">Top beginner picks this month</h2>
        <p class="muted">Cross-category editor picks so you can compare scopes and upgrades in one quick pass.</p>
    </div>
    <div class="grid product-grid">
        <?php foreach ($homeFeaturedProducts as $item): ?>
            <?= $renderProductCard($item, 'Shop', $homeTrustBadges) ?>
        <?php endforeach; ?>
    </div>
    <p class="mobile-only-section-cta"><a class="btn secondary-btn" href="<?= e(url('/telescopes')) ?>">View all beginner telescopes</a></p>
</section>
<?php endif; ?>

<section class="panel home-section decision-section" id="section-start-here">
    <div class="section-heading section-heading-split">
        <div>
            <span class="section-kicker">Choose the route</span>
            <h2 class="section-title">Start with the question you are actually asking</h2>
        </div>
        <a class="btn secondary-btn" href="<?= e(url('/guides')) ?>">Browse guides</a>
    </div>
    <div class="compare-table">
        <div class="compare-row"><div class="compare-label">First telescope</div><div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best beginner telescopes</a> for easy setup, stable viewing, and fewer expensive mistakes.</div></div>
        <div class="compare-row"><div class="compare-label">Budget cap</div><div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best telescopes under $500</a> if you need a realistic shortlist before spending more.</div></div>
        <div class="compare-row"><div class="compare-label">Upgrades</div><div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best telescope accessories</a> when you already own a scope and want better observing sessions.</div></div>
        <div class="compare-row"><div class="compare-label">Learning path</div><div class="compare-value"><a href="<?= e(url('/learn')) ?>">Astronomy learning articles</a> for stargazing tips, setup advice, and beginner research questions.</div></div>
    </div>
    <div class="mobile-intent-actions" aria-label="Quick intent actions">
        <a href="<?= e(url('/best-beginner-telescopes')) ?>">See the Moon</a>
        <a href="<?= e(url('/best-beginner-telescopes')) ?>">View Planets</a>
        <a href="<?= e(url('/best-beginner-telescopes')) ?>">Beginner Setup</a>
        <a href="<?= e(url('/best-telescope-accessories')) ?>">Accessories</a>
    </div>
</section>

<section class="panel home-section" id="section-telescopes">
    <div class="section-heading section-heading-split">
        <div>
            <span class="section-kicker"><?= e($budgetSectionKicker) ?></span>
            <h2 class="section-title"><?= e($budgetSectionTitle) ?></h2>
            <p class="muted"><?= e($budgetSectionCopy) ?></p>
        </div>
        <a class="btn secondary-btn" href="<?= e(url('/telescopes')) ?>">Browse telescopes</a>
    </div>
    <div class="grid product-grid"><?php foreach ($telescopesForHome as $item): ?><?= $renderProductCard($item) ?><?php endforeach; ?></div>
    <p class="mobile-only-section-cta"><a class="btn secondary-btn" href="<?= e(url('/telescopes')) ?>">View all budget picks</a></p>
</section>

<?php foreach ($homeBanners as $banner): ?>
    <?php $bannerHtml = $renderVisualBlock($banner); if ($bannerHtml !== ''): ?>
        <section class="promo-strip banner-strip"><?= $bannerHtml ?></section>
    <?php endif; ?>
<?php endforeach; ?>

<section class="panel home-section" id="section-accessories">
    <div class="section-heading section-heading-split">
        <div>
            <span class="section-kicker">High-impact upgrades</span>
            <h2 class="section-title">Popular accessories</h2>
            <p class="muted">Adapters, eyepieces, finders, filters, and storage gear that improve your observing workflow.</p>
        </div>
        <a class="btn secondary-btn" href="<?= e(url('/accessories')) ?>">Browse accessories</a>
    </div>
    <div class="grid product-grid"><?php foreach ($accessories as $item): ?><?= $renderProductCard($item) ?><?php endforeach; ?></div>
    <p class="mobile-only-section-cta"><a class="btn secondary-btn" href="<?= e(url('/accessories')) ?>">View all accessories</a></p>
</section>

<section class="panel home-section guide-panel" id="section-guides"><div class="section-heading section-heading-split"><div><span class="section-kicker">Editorial paths</span><h2 class="section-title">Featured guides</h2></div><a class="btn secondary-btn" href="<?= e(url('/guides')) ?>">Browse guide hub</a></div><div class="compare-table"><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>">Best Beginner Telescopes</a> - practical first purchases for stargazing.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>">Best Telescope Accessories</a> - high-impact upgrades for better sessions.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>">Best Telescopes Under $500</a> - value-focused shortlist.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/blog/best-smart-computerized-telescopes-for-beginners')) ?>">Smart telescope guide</a> - compare beginner-friendly smart scope options.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/blog/best-kids-telescopes-for-beginners')) ?>">Kids telescope guide</a> - family-friendly beginner picks.</div></div><div class="compare-row"><div class="compare-label">Guide</div><div class="compare-value"><a href="<?= e(url('/best-telescope-eyepiece-upgrades-for-beginners-2026')) ?>">Eyepiece upgrades</a> and <a href="<?= e(url('/best-telescope-collimation-tools-for-beginners')) ?>">collimation tools</a> for targeted upgrades.</div></div></div></section>

<section class="panel home-section intent-panel"><span class="section-kicker">Why this works</span><h2 class="section-title">How this site helps with organic search intent</h2><p class="muted">Most telescope buyers do not start on a product page. They start with searches like "best beginner telescope", "what telescope should I buy first", or "best telescope accessories". This site is structured to answer those early questions first, then send you to more specific comparisons and product pages when you are ready.</p><p class="muted">If you are completely new, begin with the buying guides. If you already know the category you want, use the telescope and accessory hubs to compare current options faster.</p></section>
<?php if (!$hasHomeHeroImage): ?>
<a class="mobile-context-cta" id="mobile-context-cta" href="<?= e(url('/best-beginner-telescopes')) ?>">Start with Beginner Telescopes</a>
<?php endif; ?>

<section class="panel faq-panel home-section" id="section-faq">
    <span class="section-kicker">Quick answers</span>
    <h2 class="section-title">Frequently asked beginner questions</h2>
    <?php foreach ($homeFaqs as $faq): ?>
        <?php if ($faq['question'] === '' || $faq['answer'] === '') { continue; } ?>
        <details>
            <summary><?= e($faq['question']) ?></summary>
            <p class="muted"><?= nl2br(e($faq['answer'])) ?></p>
        </details>
    <?php endforeach; ?>
</section>
</div>
