<?php
$post = $data['post'];
?>
<section class="hero">
    <span class="hero-kicker">Astronomy Article</span>
    <h1><?= e($post['title']) ?></h1>
    <p><?= e($post['excerpt']) ?></p>
</section>

<?php if ($post['featured_image']): ?>
<section class="panel" style="margin-bottom: 18px; padding: 0; overflow: hidden; border-radius: 12px;">
    <img src="<?= e(url($post['featured_image'])) ?>" alt="<?= e($post['title']) ?>" style="width: 100%; height: auto; display: block;">
</section>
<?php endif; ?>

<section class="panel article-content" style="margin-bottom: 18px;">
    <div class="muted" style="line-height: 1.6;">
        <?= $post['content_html'] ?>
    </div>
</section>

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
