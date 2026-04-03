<?php
$posts = $data['posts'] ?? [];
?>
<section class="hero">
    <span class="hero-kicker">Astronomy Blog</span>
    <h1>Stargazing Tips & News</h1>
    <p>In-depth articles about telescopes, astrophotography, and how to get the most out of your night sky sessions.</p>
</section>

<section class="panel" style="margin-bottom: 18px;">
    <?php if ($posts === []): ?>
        <p class="muted">No posts found. Check back soon for new astronomy content.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach ($posts as $post): ?>
                <article class="card">
                    <?php if ($post['featured_image']): ?>
                        <img src="<?= e(url($post['featured_image'])) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
                    <?php else: ?>
                        <img src="<?= e(url('/assets/img/product-placeholder.svg')) ?>" alt="<?= e($post['title']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="body">
                        <span class="badge">Article</span>
                        <h3><?= e($post['title']) ?></h3>
                        <p class="card-copy"><?= e($post['excerpt']) ?></p>
                        <a class="card-cta" href="<?= e(url('/blog/' . $post['slug'])) ?>">Read article</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="panel" style="margin-top: 18px;">
    <h2 class="section-title" style="margin-top:0;">Looking for buying advice?</h2>
    <div class="compare-table">
        <div class="compare-row">
            <div class="compare-label">Guides Hub</div>
            <div class="compare-value"><a href="<?= e(url('/guides')) ?>">Browse our buying guides</a> for curated telescope and accessory picks.</div>
        </div>
    </div>
</section>
