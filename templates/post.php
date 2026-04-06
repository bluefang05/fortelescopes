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
}

?>
<section class="hero">
    <span class="hero-kicker">Astronomy Article</span>
    <h1><?= e($post['title']) ?></h1>
    <p><?= e($postSummary) ?></p>
    <?php
    $heroImage = (string) ($post['featured_image'] ?? '');
    $heroTitle = (string) ($post['title'] ?? '');
    require __DIR__ . '/partials/hero-media.php';
    ?>
</section>

<?php if ($postSummary !== ''): ?>
<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Quick answer</h2>
    <p class="muted"><?= e($postSummary) ?></p>
</section>
<?php endif; ?>

<?php if ($postHtml !== ''): ?>
<section class="panel article-content" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Full article</h2>
    <article class="article-prose">
        <?= $postHtml ?>
    </article>
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

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Related pages</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Blog</div>
            <div class="compare-value"><a href="<?= e(url('/blog')) ?>">Back to all articles</a></div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Guides Hub</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Browse astronomy buying guides</a> for telescope picks and accessories.</div>
        </div>
    </div>
</section>
