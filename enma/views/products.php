<?php

declare(strict_types=1);

/**
 * ENMA View: Products List and Forms
 */

if (!function_exists('enma_render_products')) {
    function enma_render_products(array $products, string $query): void
    {
        ?>
        <section class="box">
            <h2>Add Product</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="toolbar">
                    <div class="field">
                        <label>ASIN</label>
                        <input type="text" name="asin" required>
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label>Category Name</label>
                        <input type="text" name="category_name" required>
                    </div>
                    <div class="field">
                        <label>Price (USD)</label>
                        <input type="text" name="price_amount">
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Image URL</label>
                        <input type="url" name="image_url">
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Affiliate URL</label>
                        <input type="url" name="affiliate_url" required>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Description</label>
                        <textarea name="description" rows="3"></textarea>
                    </div>
                </div>
                <button class="btn" type="submit">Add Product</button>
            </form>
        </section>

        <section class="box">
            <h2>Products</h2>
            <form method="get" class="toolbar">
                <input type="hidden" name="tab" value="products">
                <div class="field">
                    <label>Search</label>
                    <input type="text" name="q" value="<?= e($query) ?>" placeholder="ASIN, title, category...">
                </div>
                <button class="btn" type="submit">Filter</button>
            </form>

            <?php if ($products === []): ?>
                <div class="empty">No products found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ASIN</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Last Sync</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?= (int) $p['id'] ?></td>
                                <td><code><?= e($p['asin']) ?></code></td>
                                <td><?= e($p['title']) ?></td>
                                <td><?= e($p['category_name']) ?></td>
                                <td><?= $p['price_amount'] ? '$' . number_format((float) $p['price_amount'], 2) : '-' ?></td>
                                <td class="muted"><?= $p['last_synced_at'] ? e(substr($p['last_synced_at'], 0, 16)) : '-' ?></td>
                                <td>
                                    <a href="?tab=products&action=edit&id=<?= (int) $p['id'] ?>" class="btn" style="padding:4px 8px;font-size:12px;">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php
    }
}

if (!function_exists('enma_render_product_edit')) {
    function enma_render_product_edit(array $product): void
    {
        ?>
        <section class="box">
            <h2>Edit Product #<?= (int) $product['id'] ?></h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="keep_image" value="1">
                
                <div class="toolbar">
                    <div class="field">
                        <label>ASIN</label>
                        <input type="text" name="asin" value="<?= e($product['asin']) ?>" required>
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Title</label>
                        <input type="text" name="title" value="<?= e($product['title']) ?>" required>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label>Category Name</label>
                        <input type="text" name="category_name" value="<?= e($product['category_name']) ?>" required>
                    </div>
                    <div class="field">
                        <label>Price (USD)</label>
                        <input type="text" name="price_amount" value="<?= $product['price_amount'] ? e($product['price_amount']) : '' ?>">
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Affiliate URL</label>
                        <input type="url" name="affiliate_url" value="<?= e($product['affiliate_url']) ?>" required>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field" style="flex:1;">
                        <label>Description</label>
                        <textarea name="description" rows="3"><?= e($product['description']) ?></textarea>
                    </div>
                </div>
                <div class="toolbar">
                    <div class="field">
                        <label>Upload New Image</label>
                        <input type="file" name="product_image" accept="image/*">
                    </div>
                </div>
                <div class="toolbar">
                    <a href="?tab=products" class="btn" style="background:#6c757d;">Cancel</a>
                    <button class="btn" type="submit">Save Changes</button>
                </div>
            </form>
        </section>
        <?php
    }
}
