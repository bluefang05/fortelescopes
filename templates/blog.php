<?php
$posts = array_values(array_filter(
    $data['posts'] ?? [],
    static fn(array $post): bool => (($post['post_type'] ?? 'post') === 'post')
));

$isPlaceholderValue = static function (?string $value): bool {
    $normalized = strtolower(trim((string) $value));
    return $normalized === '' || in_array($normalized, ['post type', 'standard post'], true);
};

$pickPostText = static function (array $post, array $keys, string $fallback) use ($isPlaceholderValue): string {
    foreach ($keys as $key) {
        $value = trim((string) ($post[$key] ?? ''));
        if (!$isPlaceholderValue($value)) {
            return $value;
        }
    }

    return $fallback;
};
?>
<section class="hero">
    <span class="hero-kicker">Blog Hub</span>
    <h1>Astronomy Blog Articles</h1>
    <p>Practical telescope, stargazing, and astrophotography articles written for beginner and intermediate hobbyists.</p>
    <div class="trust-row">
        <span class="chip">Clear beginner guidance</span>
        <span class="chip">Actionable checklists</span>
        <span class="chip">Real-world observing tips</span>
    </div>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Featured posts</h2>
    <p class="muted">Start with the latest articles, then use guides and category pages to compare products before buying.</p>
    <?php if ($posts === []): ?>
        <p class="muted">No posts found. Check back soon for new astronomy content.</p>
    <?php else: ?>
        <div class="grid blog-grid">
            <?php foreach ($posts as $post): ?>
                <?php
                $slug = trim((string) ($post['slug'] ?? ''));
                $title = $pickPostText($post, ['title', 'seo_title', 'headline'], $slug !== '' ? ucwords(str_replace('-', ' ', $slug)) : 'Astronomy article');
                $excerpt = $pickPostText(
                    $post,
                    ['excerpt', 'meta_description', 'description', 'summary'],
                    'Read this practical astronomy article and apply the tips to your next observing session.'
                );
                $postImage = trim((string) ($post['featured_image'] ?? '')) !== '' ? (string) $post['featured_image'] : '/assets/img/product-placeholder.svg';
                ?>
                <article class="card">
                    <img src="<?= e(url($postImage)) ?>" alt="<?= e($title) ?>" loading="lazy">
                    <div class="body">
                        <span class="badge">Article</span>
                        <h3><?= e($title) ?></h3>
                        <p class="card-copy"><?= e($excerpt) ?></p>
                        <a class="card-cta" href="<?= e(url('/blog/' . $slug)) ?>">Read article</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <h2 class="section-title" style="margin-top:0;">Category paths</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Guides</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Browse astronomy buying guides</a> for conversion-focused product picks.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Telescopes</div>
            <div class="compare-value"><a href="<?= e(url('/telescopes')) ?>">See telescope recommendations</a> from beginner to premium tiers.</div>
        </div>
        <div class="compare-row">
            <div class="compare-label">Accessories</div>
            <div class="compare-value"><a href="<?= e(url('/accessories')) ?>">Compare practical accessories</a> for better observing sessions.</div>
        </div>
    </div>
</section>
