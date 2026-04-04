<?php
$post = $data['post'];
$postHtmlRaw = trim((string) ($post['content_html'] ?? ''));
$postHtml = $postHtmlRaw;
if ($postHtmlRaw !== '') {
    if ((strpos($postHtmlRaw, '&lt;') !== false || strpos($postHtmlRaw, '&gt;') !== false) && strpos($postHtmlRaw, '<') === false) {
        $decoded = html_entity_decode($postHtmlRaw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($decoded) !== '') {
            $postHtml = trim($decoded);
        }
    }
    $postHtml = preg_replace('/<\s*(script|style|object|embed)\b[^>]*>[\s\S]*?<\s*\/\s*\1\s*>/i', '', $postHtml) ?? $postHtml;
    $postHtml = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $postHtml) ?? $postHtml;
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

// Generate dynamic schema for this post
$jsonLd = generate_dynamic_schema($post, base_url());
?>
<section class="hero">
    <span class="hero-kicker">Astronomy Article</span>
    <h1><?= e($post['title']) ?></h1>
    <p><?= e($post['excerpt']) ?></p>
</section>

<?php if ($post['featured_image']): ?>
<section class="panel" style="margin-bottom: 18px; padding: 0; overflow: hidden; border-radius: 12px; aspect-ratio: 16 / 9; max-height: 500px; position: relative; background: #0b1f3a; display: flex; align-items: center; justify-content: center; box-shadow: var(--card-shadow); border: 1px solid #2d3e50;">
    <!-- Blurred background echo -->
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('<?= e(url($post['featured_image'])) ?>'); background-size: cover; background-position: center; filter: blur(30px) brightness(0.5); transform: scale(1.1);"></div>
    <!-- Main image -->
    <img src="<?= e(url($post['featured_image'])) ?>" alt="<?= e($post['title']) ?>" style="position: relative; z-index: 1; max-width: 100%; height: 100%; object-fit: contain; display: block;">
</section>
<?php endif; ?>

<section class="panel article-content" style="margin-bottom: 18px;">
    <div class="muted" style="line-height: 1.6;">
        <?= $postHtml ?>
    </div>
</section>

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
                    <img src="<?= e(url($guideImage)) ?>" alt="<?= e($otherGuide['title']) ?>" loading="lazy" style="aspect-ratio: 2 / 3; object-fit: cover; width: 100%; height: auto;">
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
