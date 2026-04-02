<?php
/**
 * Blog Listing Template
 * Displays all published blog posts
 */

if (!isset($posts)) {
    $posts = [];
}
if (!isset($pageTitle)) {
    $pageTitle = 'Blog | ' . APP_NAME;
}
?>

<div class="blog-listing" style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">
    <header class="blog-header" style="text-align: center; margin-bottom: 50px;">
        <h1 style="font-size: 2.5rem; color: #1a1a2e; margin-bottom: 15px;">Our Blog</h1>
        <p style="font-size: 1.2rem; color: #6c757d; max-width: 700px; margin: 0 auto;">
            Tips, guides, and insights about telescopes, astronomy, and stargazing for beginners and enthusiasts.
        </p>
    </header>

    <?php if ($posts === []): ?>
        <div style="text-align: center; padding: 60px 20px; color: #6c757d;">
            <p style="font-size: 1.1rem;">No blog posts yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="blog-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 30px;">
            <?php foreach ($posts as $post): ?>
                <article class="blog-card" style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08); transition: transform 0.2s, box-shadow 0.2s;">
                    <?php if (!empty($post['featured_image'])): ?>
                        <a href="<?= e(url('/blog/' . $post['slug'])) ?>" style="display: block; height: 220px; overflow: hidden;">
                            <img src="<?= e($post['featured_image']) ?>" 
                                 alt="<?= e($post['title']) ?>" 
                                 style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s;"
                                 onerror="this.style.display='none'">
                        </a>
                    <?php endif; ?>
                    
                    <div class="blog-card-content" style="padding: 25px;">
                        <h2 style="font-size: 1.4rem; margin: 0 0 12px; line-height: 1.3;">
                            <a href="<?= e(url('/blog/' . $post['slug'])) ?>" 
                               style="color: #1a1a2e; text-decoration: none;">
                                <?= e($post['title']) ?>
                            </a>
                        </h2>
                        
                        <?php if (!empty($post['excerpt'])): ?>
                            <p style="color: #495057; line-height: 1.6; margin-bottom: 20px; font-size: 0.95rem;">
                                <?= e(mb_substr($post['excerpt'], 0, 150)) ?><?= mb_strlen($post['excerpt']) > 150 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #e9ecef;">
                            <span style="font-size: 0.85rem; color: #6c757d;">
                                <?= e(date('F j, Y', strtotime($post['created_at']))) ?>
                            </span>
                            <a href="<?= e(url('/blog/' . $post['slug'])) ?>" 
                               class="read-more" 
                               style="color: #007bff; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                Read More →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .blog-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .blog-card:hover img {
        transform: scale(1.05);
    }
    .blog-card h2 a:hover {
        color: #007bff !important;
    }
    .read-more:hover {
        color: #0056b3 !important;
    }
</style>
