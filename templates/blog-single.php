<?php
/**
 * Blog Single Post Template
 * Displays a single blog post with full content
 */

if (!isset($post)) {
    $post = [];
}
?>

<article class="blog-post" style="max-width: 800px; margin: 0 auto; padding: 40px 20px;">
    <?php if (!empty($post['featured_image'])): ?>
        <div class="blog-post-featured" style="margin-bottom: 40px;">
            <img src="<?= e($post['featured_image']) ?>" 
                 alt="<?= e($post['title']) ?>" 
                 style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 12px;"
                 onerror="this.style.display='none'">
        </div>
    <?php endif; ?>

    <header class="blog-post-header" style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 2.5rem; color: #1a1a2e; margin-bottom: 15px; line-height: 1.3;">
            <?= e($post['title']) ?>
        </h1>
        
        <?php if (!empty($post['published_at'])): ?>
            <p style="color: #6c757d; font-size: 0.95rem;">
                Published on <?= e(date('F j, Y', strtotime($post['published_at'] ?: $post['created_at']))) ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if (!empty($post['excerpt'])): ?>
        <div class="blog-post-excerpt" style="background: #f8f9fa; padding: 20px 25px; border-left: 4px solid #007bff; border-radius: 0 8px 8px 0; margin-bottom: 35px; font-style: italic; color: #495057; line-height: 1.7;">
            <?= nl2br(e($post['excerpt'])) ?>
        </div>
    <?php endif; ?>

    <div class="blog-post-content" style="font-size: 1.1rem; line-height: 1.8; color: #333;">
        <?= $post['content_html'] ?>
    </div>

    <footer class="blog-post-footer" style="margin-top: 50px; padding-top: 30px; border-top: 1px solid #e9ecef;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <a href="<?= e(url('/blog')) ?>" style="color: #007bff; text-decoration: none; font-weight: 600;">
                ← Back to Blog
            </a>
            
            <div class="blog-share" style="display: flex; gap: 10px; align-items: center;">
                <span style="color: #6c757d; font-size: 0.9rem;">Share:</span>
                <?php
                $shareUrl = urlencode(absolute_url('/blog/' . ($post['slug'] ?? '')));
                $shareTitle = urlencode($post['title'] ?? '');
                ?>
                <a href="https://twitter.com/intent/tweet?url=<?= $shareUrl ?>&text=<?= $shareTitle ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   style="display: inline-block; padding: 8px 16px; background: #1da1f2; color: white; text-decoration: none; border-radius: 4px; font-size: 0.85rem;">
                    Twitter
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" 
                   target="_blank" 
                   rel="noopener noreferrer"
                   style="display: inline-block; padding: 8px 16px; background: #4267B2; color: white; text-decoration: none; border-radius: 4px; font-size: 0.85rem;">
                    Facebook
                </a>
            </div>
        </div>
    </footer>

    <?php
    // Show related products if this is a product-related post
    if (!empty($relatedProducts) && count($relatedProducts) > 0):
    ?>
        <section class="related-products" style="margin-top: 60px; padding-top: 40px; border-top: 2px solid #e9ecef;">
            <h2 style="font-size: 1.8rem; color: #1a1a2e; margin-bottom: 30px; text-align: center;">
                Related Products
            </h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 25px;">
                <?php foreach ($relatedProducts as $product): ?>
                    <div style="background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
                        <?php if (!empty($product['image_url'])): ?>
                            <a href="<?= e(url('/product/' . $product['slug'])) ?>" style="display: block; height: 200px; overflow: hidden;">
                                <img src="<?= e($product['image_url']) ?>" 
                                     alt="<?= e($product['title']) ?>" 
                                     style="width: 100%; height: 100%; object-fit: contain; padding: 20px; background: #f8f9fa;"
                                     onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22 viewBox=%220 0 200 200%22%3E%3Crect fill=%22%23e9ecef%22 width=%22200%22 height=%22200%22/%3E%3Ctext fill=%22%23adb5bd%22 x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22%3ENo Image%3C/text%3E%3C/svg%3E'">
                            </a>
                        <?php endif; ?>
                        <div style="padding: 20px;">
                            <h3 style="font-size: 1rem; margin: 0 0 10px; line-height: 1.4;">
                                <a href="<?= e(url('/product/' . $product['slug'])) ?>" 
                                   style="color: #1a1a2e; text-decoration: none;">
                                    <?= e($product['title']) ?>
                                </a>
                            </h3>
                            <?php if (!empty($product['price_amount'])): ?>
                                <p style="color: #28a745; font-weight: 700; font-size: 1.1rem; margin: 10px 0 0;">
                                    $<?= number_format($product['price_amount'], 2) ?>
                                </p>
                            <?php endif; ?>
                            <a href="<?= e(url('/go')) ?>?u=<?= urlencode($product['affiliate_url']) ?>&from=<?= urlencode('/blog/' . ($post['slug'] ?? '')) ?>&pid=<?= (int) $product['id'] ?>" 
                               class="btn-view-product"
                               style="display: block; text-align: center; padding: 10px; background: #007bff; color: white; text-decoration: none; border-radius: 6px; font-weight: 600; margin-top: 15px;">
                                View on Amazon
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>

<style>
    .blog-post-content h1,
    .blog-post-content h2,
    .blog-post-content h3 {
        color: #1a1a2e;
        margin-top: 2em;
        margin-bottom: 0.75em;
    }
    
    .blog-post-content p {
        margin-bottom: 1.5em;
    }
    
    .blog-post-content img {
        max-width: 100%;
        height: auto;
        border-radius: 8px;
        margin: 1.5em 0;
    }
    
    .blog-post-content a {
        color: #007bff;
        text-decoration: underline;
    }
    
    .blog-post-content ul,
    .blog-post-content ol {
        margin-bottom: 1.5em;
        padding-left: 2em;
    }
    
    .blog-post-content li {
        margin-bottom: 0.5em;
    }
    
    .blog-post-content blockquote {
        border-left: 4px solid #007bff;
        padding-left: 1.5em;
        margin: 1.5em 0;
        font-style: italic;
        color: #495057;
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 0 8px 8px 0;
    }
    
    .blog-post-content pre {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        overflow-x: auto;
        font-family: 'Courier New', monospace;
        font-size: 0.9rem;
    }
    
    .btn-view-product:hover {
        background: #0056b3 !important;
    }
</style>
