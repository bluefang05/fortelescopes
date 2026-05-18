<?php
$post = $data['post'];
$postSummary = trim((string) ($post['excerpt'] ?? ''));
if ($postSummary === '') {
    $postSummary = trim((string) ($post['meta_description'] ?? ''));
}
$postHtmlRaw = trim((string) ($post['content_html'] ?? ''));
$postHtml = $postHtmlRaw;
if ($postHtmlRaw !== '') {
    $decodedHtml = $postHtmlRaw;
    for ($i = 0; $i < 3; $i++) {
        if (strpos($decodedHtml, '&lt;') === false && strpos($decodedHtml, '&gt;') === false) {
            break;
        }
        $next = html_entity_decode($decodedHtml, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $decodedHtml) {
            break;
        }
        $decodedHtml = $next;
    }
    if (trim($decodedHtml) !== '' && strpos($decodedHtml, '<') !== false) {
        $postHtml = trim($decodedHtml);
    }
    // Force guide-like visual consistency: drop custom style/script blocks from post body.
    $postHtml = preg_replace('/<\s*(script|style|object|embed)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/i', '', $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/\sstyle\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/\s(href|src)\s*=\s*([\"\'])\s*javascript:[^\"\']*\2/i', ' $1="#"', $postHtml) ?? $postHtml;
    $postHtml = preg_replace_callback('/<iframe\b[^>]*\bsrc=(["\'])([^"\']+)\1[^>]*>\s*<\/iframe>/i', static function ($m) {
        $src = trim((string) ($m[2] ?? ''));
        if (!preg_match('#^https://(www\.)?(youtube\.com|youtube-nocookie\.com)/embed/#i', $src)) {
            return '';
        }
        return '<iframe src="' . e($src) . '" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
    }, $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/<iframe\b(?![^>]*\bsrc=)[^>]*>[\s\S]*?<\/iframe>/i', '', $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/<iframe\b[^>]*\bsrc=(["\'])(?!https:\/\/(www\.)?(youtube\.com|youtube-nocookie\.com)\/embed\/)[^"\']+\1[^>]*>\s*<\/iframe>/i', '', $postHtml) ?? $postHtml;
    
    // Apply YouTube lazy loading transformation
    $postHtml = lazy_load_youtube_embeds($postHtml);

    // Add hover help text to button-like links (especially affiliate CTAs).
    $postHtml = preg_replace_callback('/<a\b([^>]*)>([\s\S]*?)<\/a>/i', static function (array $m): string {
        $attrs = (string) ($m[1] ?? '');
        $inner = (string) ($m[2] ?? '');

        if (preg_match('/\btitle\s*=\s*("|\').*?\1/i', $attrs)) {
            return (string) $m[0];
        }

        $href = '';
        if (preg_match('/\bhref\s*=\s*("|\')(.*?)\1/i', $attrs, $hrefMatch)) {
            $href = trim((string) ($hrefMatch[2] ?? ''));
        }
        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($inner)));
        $textLower = strtolower($text);
        $attrsLower = strtolower($attrs);

        $isButtonLike = (
            strpos($attrsLower, 'btn') !== false
            || strpos($attrsLower, 'button') !== false
            || strpos($attrsLower, 'cta') !== false
            || (strpos($attrsLower, 'style=') !== false
                && strpos($attrsLower, 'background') !== false
                && strpos($attrsLower, 'padding') !== false)
        );

        if (!$isButtonLike) {
            return (string) $m[0];
        }

        $title = '';
        if (strpos(strtolower($href), 'amazon.') !== false || strpos($textLower, 'amazon') !== false || strpos($textLower, 'check price') !== false || strpos($textLower, 'buy') !== false) {
            $title = 'Opens Amazon in a new tab (affiliate link).';
        } elseif (strpos($textLower, 'guide') !== false || strpos($textLower, 'read') !== false || strpos($textLower, 'article') !== false) {
            $title = 'Opens related content in a new tab.';
        } else {
            $title = 'Opens this action link.';
        }

        $newAttrs = trim($attrs . ' title="' . e($title) . '"');
        return '<a ' . $newAttrs . '>' . $inner . '</a>';
    }, $postHtml) ?? $postHtml;
}
$postSection = post_section($post);
$postHubPath = $postSection === 'reviews' ? '/reviews' : ($postSection === 'learn' ? '/learn' : '/blog');
$postHubLabel = $postSection === 'reviews' ? 'Reviews' : ($postSection === 'learn' ? 'Learn' : 'Blog');
$postType = strtolower(trim((string) ($post['post_type'] ?? 'post')));
$postSlug = strtolower(trim((string) ($post['slug'] ?? '')));
$renderIntroBlocks = !empty($post['render_intro_blocks']);
if ($postSlug === 'why-is-my-telescope-blurry') {
    $renderIntroBlocks = false;
}

?>
<section class="hero">
    <span class="hero-kicker">Astronomy Article</span>
    <?php if (!empty($isDraftPreview)): ?>
        <span class="hero-kicker hero-kicker-draft">Draft Preview</span>
    <?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p><?= e($postSummary) ?></p>
    <div class="content-switch" aria-label="Content type switcher">
        <?php if ($postType === 'guide'): ?>
            <span class="is-active" aria-current="page">Guides</span>
        <?php else: ?>
            <a href="<?= e(url('/guides')) ?>">Guides</a>
        <?php endif; ?>

        <?php if ($postType === 'review' || $postSection === 'reviews'): ?>
            <span class="is-active" aria-current="page">Reviews</span>
        <?php else: ?>
            <a href="<?= e(url('/reviews')) ?>">Reviews</a>
        <?php endif; ?>

        <?php if ($postType === 'post'): ?>
            <span class="is-active" aria-current="page">Posts</span>
        <?php else: ?>
            <a href="<?= e(url('/blog')) ?>">Posts</a>
        <?php endif; ?>
    </div>
    <?php
    $heroImage = content_asset_path((string) ($post['featured_image'] ?? ''));
    $heroTitle = (string) ($post['title'] ?? '');
    require __DIR__ . '/partials/hero-media.php';
    ?>
</section>

<?php if ($renderIntroBlocks && $postSummary !== ''): ?>
<section class="panel u-mb-18">
    <h2 class="section-title u-mt-0">Quick answer</h2>
    <p class="muted"><?= e($postSummary) ?></p>
</section>
<?php endif; ?>

<?php if ($renderIntroBlocks): ?>
<section class="panel u-mb-18">
    <h2 class="section-title u-mt-0">Affiliate and editorial note</h2>
    <p class="muted">Some links on this page may be affiliate links. As an Amazon Associate, this site may earn from qualifying purchases. Product recommendations are based on beginner usability, setup friction, and value.</p>
</section>

<section class="panel u-mb-18">
    <h2 class="section-title u-mt-0">What to read next</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Need a first scope?</div>
            <div class="compare-value"><a href="<?= e(url('/best-beginner-telescopes')) ?>" title="Opens the beginner buying guide.">Read the beginner telescope guide</a> for product shortlists and buying tradeoffs.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Need a budget cap?</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescopes-under-500')) ?>" title="Opens the under-$500 comparison guide.">Compare telescopes under $500</a> if price is your main constraint.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Need upgrades?</div>
            <div class="compare-value"><a href="<?= e(url('/best-telescope-accessories')) ?>" title="Opens accessory recommendations.">See practical accessory picks</a> if you already own a telescope.</div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($postHtml !== ''): ?>
<section class="panel article-content u-mb-18">
    <?php if ($renderIntroBlocks): ?>
        <h2 class="section-title u-mt-0">Full article</h2>
    <?php endif; ?>
    <article class="article-prose">
        <?= $postHtml ?>
    </article>
</section>
<?php endif; ?>

<?php if (!empty($data['otherGuides'])): ?>
<section class="panel u-mt-18">
    <h2 class="section-title u-mt-0">More astronomy buying guides</h2>
    <div class="grid">
        <?php foreach ($data['otherGuides'] as $otherGuide): ?>
            <?php
            $guideImage = !empty($otherGuide['featured_image']) ? content_asset_path((string) $otherGuide['featured_image']) : match ($otherGuide['slug'] ?? '') {
                'best-beginner-telescopes' => '/assets/img/optimized_1.webp',
                'best-telescope-accessories' => '/assets/img/optimized_2.webp',
                'best-telescopes-under-500' => '/assets/img/optimized_3.webp',
                default => '/assets/img/product-placeholder.svg',
            };
            ?>
            <article class="card">
                <?php if ($guideImage): ?>
                    <img src="<?= e(content_asset_path($guideImage)) ?>" alt="<?= e($otherGuide['title']) ?>" loading="lazy">
                <?php endif; ?>
                <div class="body">
                    <span class="badge">Guide</span>
                    <h3><?= e($otherGuide['title']) ?></h3>
                    <a class="card-cta" href="<?= e(url('/' . $otherGuide['slug'])) ?>" title="Open this guide.">Open guide</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel u-mt-18">
    <h2 class="section-title u-mt-0">Related pages</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label"><?= e($postHubLabel) ?></div>
            <div class="compare-value"><a href="<?= e(url($postHubPath)) ?>" title="Go back to all articles in this section.">Back to all articles</a></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guides Hub</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>" title="Open the full guides hub.">Browse astronomy buying guides</a> for telescope picks and accessories.</div>
        </div>
    </div>
</section>
