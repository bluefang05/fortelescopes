<?php
$guide = $data['guide'];
$items = $data['guideProducts'] ?? [];
$top = $items[0] ?? null;
$budget = $items[count($items) - 1] ?? null;
$premium = $items[1] ?? ($items[0] ?? null);
$guideSlug = (string) ($guide['slug'] ?? '');
$isBeginnerGuide = $guideSlug === 'best-beginner-telescopes';
$isAccessoriesGuide = $guideSlug === 'best-telescope-accessories';
$isUnder500Guide = $guideSlug === 'best-telescopes-under-500';
$showComparison = $isBeginnerGuide || $isAccessoriesGuide || $isUnder500Guide;
$bestForMap = is_array($guide['best_for_map'] ?? null) ? $guide['best_for_map'] : [];
$framework = is_array($guide['framework'] ?? null) ? $guide['framework'] : [];
$mistakes = is_array($guide['mistakes'] ?? null) ? $guide['mistakes'] : [];
$fullGuideHtmlRaw = trim((string) ($guide['content_html'] ?? ''));
$fullGuideHtml = '';
if ($fullGuideHtmlRaw !== '') {
    $candidate = $fullGuideHtmlRaw;
    for ($i = 0; $i < 3; $i++) {
        if (strpos($candidate, '&lt;') === false && strpos($candidate, '&gt;') === false) {
            break;
        }
        $next = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $candidate) {
            break;
        }
        $candidate = $next;
    }
    // Keep <style> blocks from trusted guide content so scoped guide CSS can render.
    $candidate = preg_replace('/<\s*(script|object|embed)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/i', '', $candidate) ?? $candidate;
    $candidate = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $candidate) ?? $candidate;
    $candidate = preg_replace('/\s(href|src)\s*=\s*([\"\'])\s*javascript:[^\"\']*\2/i', ' $1="#"', $candidate) ?? $candidate;
    $candidate = preg_replace_callback('/<iframe\b[^>]*\bsrc=(["\'])([^"\']+)\1[^>]*>\s*<\/iframe>/i', static function ($m) {
        $src = trim((string) ($m[2] ?? ''));
        if (!preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/#i', $src)) {
            return '';
        }
        return '<iframe src="' . e($src) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    }, $candidate) ?? $candidate;
    $candidate = preg_replace('/<iframe\b(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/iframe>/i', '', $candidate) ?? $candidate;
    $candidate = preg_replace('/<iframe\b[^>]*\bsrc=(["\'])(?!https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com)\/embed\/)[^"\']+\1[^>]*>\s*<\/iframe>/i', '', $candidate) ?? $candidate;
    $candidate = lazy_load_youtube_embeds($candidate);
    $fullGuideHtml = trim($candidate);
}
?>
<section class="hero">
    <span class="hero-kicker">Buying Guide</span>
    <?php if (!empty($isDraftPreview)): ?>
        <span class="hero-kicker" style="background:#7d2d00;margin-left:8px;">Draft Preview</span>
    <?php endif; ?>
    <h1><?= e($guide['title']) ?></h1>
    <p><?= e($guide['description']) ?></p>
    <?php
    $heroImage = (string) ($guide['featured_image'] ?? '');
    $heroTitle = (string) ($guide['title'] ?? '');
    require __DIR__ . '/partials/hero-media.php';
    ?>
    <div class="trust-row">
        <span class="chip">Updated product shortlist</span>
        <span class="chip">Decision-first framework</span>
        <span class="chip">Beginner-friendly picks</span>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Quick answer</h2>
    <p class="muted"><?= e($guide['intro'] ?? '') ?></p>

    <?php if (!empty($guide['article_intro']) && is_array($guide['article_intro'])): ?>
        <?php foreach ($guide['article_intro'] as $introParagraph): ?>
            <p class="muted" style="margin-bottom: 10px;"><?= e((string) $introParagraph) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2 class="section-title" style="margin-top: 18px;"><?= $isBeginnerGuide ? 'How to choose your first telescope without wasting money' : ($isAccessoriesGuide ? 'How to choose upgrades that actually help' : ($isUnder500Guide ? 'How to choose a telescope under $500 without regret' : 'How to choose without wasting money')) ?></h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Step 1</div>
            <div class="compare-value"><?= e($framework[0] ?? 'Start with stability and compatibility first.') ?></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Step 2</div>
            <div class="compare-value"><?= e($framework[1] ?? 'Choose products with immediate real-world usage.') ?></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Step 3</div>
            <div class="compare-value"><?= e($framework[2] ?? 'Upgrade incrementally based on repeated bottlenecks.') ?></div>
        </div>
    </div>
</section>

<?php if ($fullGuideHtml !== ''): ?>
<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Full guide</h2>
    <div class="guide-prose"><?= $fullGuideHtml ?></div>
</section>
<?php endif; ?>

<?php if (!empty($guide['key_factors']) && is_array($guide['key_factors'])): ?>
<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Key factors to consider</h2>
    <?php foreach ($guide['key_factors'] as $factor): ?>
        <h3 style="margin: 0 0 8px; font-size: 20px;"><?= e((string) ($factor['title'] ?? '')) ?></h3>
        <?php if (!empty($factor['points']) && is_array($factor['points'])): ?>
            <ul style="margin: 0 0 14px 18px; color: #334155; line-height: 1.5;">
                <?php foreach ($factor['points'] as $point): ?>
                    <li style="margin-bottom: 6px;"><?= e((string) $point) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($top !== null): ?>
<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top: 0;"><?= $isBeginnerGuide ? 'Top recommended beginner telescopes' : 'Top recommendations for this guide' ?></h2>
    <div class="tier-grid">
        <article class="tier-card">
            <span class="tier-tag tier-top">Top Pick</span>
            <h4><?= e($top['title']) ?></h4>
            <p><?= e($bestForMap[$top['asin']] ?? product_best_for($top)) ?></p>
        </article>
        <?php if ($budget !== null): ?>
        <article class="tier-card">
            <span class="tier-tag tier-budget">Budget Pick</span>
            <h4><?= e($budget['title']) ?></h4>
            <p><?= e($bestForMap[$budget['asin']] ?? product_best_for($budget)) ?></p>
        </article>
        <?php endif; ?>
        <?php if ($premium !== null): ?>
        <article class="tier-card">
            <span class="tier-tag tier-premium">Premium Pick</span>
            <h4><?= e($premium['title']) ?></h4>
            <p><?= e($bestForMap[$premium['asin']] ?? product_best_for($premium)) ?></p>
        </article>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<h2 class="section-title">Recommended shortlist</h2>
<p class="muted">
    <?= $isBeginnerGuide ? 'These real picks are suitable for first-time stargazers. Check details and current availability on Amazon before buying.' : ($isAccessoriesGuide ? 'These accessories are selected for practical field impact. Start with one high-impact upgrade and scale gradually.' : ($isUnder500Guide ? 'These real models are commonly considered in the under-$500 category. Verify current availability before purchase.' : 'These are selected for beginner usability and conversion intent. Open detail pages for use-case fit before checkout.')) ?>
</p>

<div class="grid">
    <?php foreach ($items as $idx => $item): ?>
        <article class="card">
            <a href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener" aria-label="<?= e($item['title']) ?> on Amazon">
                <img src="<?= e(product_image_url($item)) ?>" alt="<?= e($item['title']) ?>" loading="lazy" decoding="async" width="800" height="600" onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';">
            </a>
            <div class="body">
                <span class="update-pill <?= e(sync_freshness_class($item['last_synced_at'] ?? null)) ?>"><?= e(relative_time_label($item['last_synced_at'] ?? null)) ?></span>
                <span class="badge"><?= e($item['category_name']) ?></span>
                <h3><?= e($item['title']) ?></h3>
                <p class="card-copy"><?= e($item['description']) ?></p>
                <?php if ($isBeginnerGuide || $isAccessoriesGuide || $isUnder500Guide): ?>
                    <p class="muted" style="margin: 8px 0 0; font-size: 13px;"><?= e($bestForMap[$item['asin']] ?? product_best_for($item)) ?></p>
                <?php endif; ?>
                <a class="card-cta amazon-btn" href="<?= e(outbound_url((string) $item['affiliate_url'], (int) ($item['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener">View on Amazon</a>
                <p class="muted" style="margin:8px 0 0;font-size:12px;"><a href="<?= e(url('/product/' . $item['slug'])) ?>">Open product page</a></p>
            </div>
        </article>
    <?php endforeach; ?>
</div>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;"><?= $isBeginnerGuide ? 'Common mistakes beginners make' : ($isAccessoriesGuide ? 'Accessory mistakes to avoid' : ($isUnder500Guide ? 'Common under-$500 buying mistakes' : 'Common beginner mistakes')) ?></h2>
    <?php if ($mistakes !== []): ?>
        <ul style="margin: 0 0 0 18px; color: #334155; line-height: 1.55;">
            <?php foreach ($mistakes as $mistake): ?>
                <li style="margin-bottom: 8px;"><?= e((string) $mistake) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="compare-table">
            <div class="compare-row">
                <div class="compare-label">Mistake</div>
                <div class="compare-value">Buying accessories by hype instead of telescope compatibility.</div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Mistake</div>
                <div class="compare-value">Spending on niche upgrades before solving mount/alignment basics.</div>
            </div>
            <div class="compare-row">
                <div class="compare-label">Fix</div>
                <div class="compare-value">Start with one high-impact upgrade, test across 3-5 sessions, then scale.</div>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if ($showComparison && $items !== []): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Quick comparison</h2>
    <div class="compare-table">
        <?php if (($isAccessoriesGuide || $isUnder500Guide) && !empty($guide['comparisons']) && is_array($guide['comparisons'])): ?>
            <?php foreach ($guide['comparisons'] as $entry): ?>
                <div class="compare-row">
                    <div class="compare-label"><?= e((string) ($entry['label'] ?? '')) ?></div>
                    <div class="compare-value"><?= e((string) ($entry['value'] ?? '')) ?></div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach (array_slice($items, 0, 5) as $item): ?>
                <div class="compare-row">
                    <div class="compare-label"><?= e($item['title']) ?></div>
                    <div class="compare-value"><?= e($bestForMap[$item['asin']] ?? product_best_for($item)) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<?php if (($isAccessoriesGuide || $isUnder500Guide) && !empty($guide['upgrade_timing']) && is_array($guide['upgrade_timing'])): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">When upgrades make sense</h2>
    <ul style="margin: 0 0 0 18px; color: #334155; line-height: 1.55;">
        <?php foreach ($guide['upgrade_timing'] as $row): ?>
            <li style="margin-bottom: 8px;"><?= e((string) $row) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (($isAccessoriesGuide || $isUnder500Guide) && !empty($guide['avoid_list']) && is_array($guide['avoid_list'])): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Low-value upgrades to skip</h2>
    <ul style="margin: 0 0 0 18px; color: #334155; line-height: 1.55;">
        <?php foreach ($guide['avoid_list'] as $row): ?>
            <li style="margin-bottom: 8px;"><?= e((string) $row) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<?php if (($isAccessoriesGuide || $isUnder500Guide) && !empty($guide['budget_notes']) && is_array($guide['budget_notes'])): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Budget vs performance</h2>
    <ul style="margin: 0 0 0 18px; color: #334155; line-height: 1.55;">
        <?php foreach ($guide['budget_notes'] as $row): ?>
            <li style="margin-bottom: 8px;"><?= e((string) $row) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Final recommendation</h2>
    <p class="muted" style="margin-bottom: 10px;">
        <?= e((string) ($guide['final_recommendation'] ?? 'For most first-time stargazers, start with a simple and stable model that you can use consistently from week one. If you are still comparing options, open the top pick first and verify availability on Amazon.')) ?>
    </p>
    <?php if ($top !== null): ?>
        <a class="btn amazon-btn" href="<?= e(outbound_url((string) $top['affiliate_url'], (int) ($top['id'] ?? 0))) ?>" target="_blank" rel="nofollow sponsored noopener"><?= e((string) ($guide['cta_text'] ?? 'View on Amazon')) ?></a>
        <?php if (!empty($guide['cta_note'])): ?>
            <p class="muted" style="margin-top: 8px; font-size: 13px;"><?= e((string) $guide['cta_note']) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Related pages</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Guides Hub</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Browse all astronomy buying guides</a> for telescope picks, accessories, and budget paths.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Telescopes</div>
            <div class="compare-value"><a href="<?= e(url('/telescopes')) ?>">See telescope category recommendations</a> with current availability links.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Accessories</div>
            <div class="compare-value"><a href="<?= e(url('/accessories')) ?>">See accessory category recommendations</a> for practical upgrades.</div>
        </div>
    </div>
</section>

<?php if (!empty($guide['faq'])): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">FAQ</h2>
    <?php foreach ($guide['faq'] as $faq): ?>
        <details style="margin-bottom: 10px; border: 1px solid #e8edf3; border-radius: 10px; padding: 10px 12px; background: #fff;">
            <summary style="font-weight: 700; cursor: pointer;"><?= e($faq['q']) ?></summary>
            <p class="muted" style="margin: 8px 0 0;"><?= e($faq['a']) ?></p>
        </details>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if (!empty($data['otherGuides'])): ?>
<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">More astronomy buying guides</h2>
    <div class="grid">
        <?php foreach ($data['otherGuides'] as $otherGuide): ?>
            <?php
            $guideImage = !empty($otherGuide['featured_image']) ? $otherGuide['featured_image'] : match ($otherGuide['slug'] ?? '') {
                'best-beginner-telescopes' => '/assets/img/optimized_1.webp',
                'best-telescope-accessories' => '/assets/img/optimized_2.webp',
                'best-telescopes-under-500' => '/assets/img/optimized_3.webp',
                default => '/assets/img/product-placeholder.svg',
            };
            ?>
            <article class="card">
                <?php if ($guideImage): ?>
                    <img src="<?= e(url($guideImage)) ?>" alt="<?= e($otherGuide['title']) ?>" loading="lazy">
                <?php endif; ?>
                <div class="body">
                    <span class="badge">Guide</span>
                    <h3><?= e($otherGuide['title']) ?></h3>
                    <a class="card-cta" href="<?= e(url('/' . $otherGuide['slug'])) ?>">Open guide</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
