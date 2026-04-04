<?php

declare(strict_types=1);

// Autoloader simple para MVC
spl_autoload_register(function ($class) {
    $prefix = 'Enma\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

session_start();

// Router simple: si viene ?action=analytics, usar el controlador MVC
$action = $_GET['action'] ?? 'login';

if ($action === 'analytics') {
    $controller = new \Enma\Controllers\AnalyticsController();
    $controller->index();
    exit;
}

// Si no es una acción MVC, continuar con el legacy
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$flash = null;
$maxLoginAttempts = 5;
$lockSeconds = 600;
$_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0);
$_SESSION['login_locked_until'] = (int) ($_SESSION['login_locked_until'] ?? 0);
$isLocked = ($_SESSION['login_locked_until'] > time());

// Include helpers first (provides enma_handle_image_upload and enma_normalize_editor_html)
require_once __DIR__ . '/helpers.php';

// Include handlers
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/posts_handler.php';
require_once __DIR__ . '/products_handler.php';
require_once __DIR__ . '/maintenance.php';

$authenticated = !empty($_SESSION['admin_ok']);
$maintenanceLog = [];
$advancedEnabled = ENMA_ADVANCED_KEY !== '';
$editingPost = null;
$editingProduct = null;

$activeTab = $authenticated ? (string) ($_GET['tab'] ?? 'overview') : 'overview';
if (!in_array($activeTab, ['overview', 'products', 'posts', 'views', 'maintenance'], true)) {
    $activeTab = 'overview';
}
$viewDays = $authenticated ? max(7, min(180, (int) ($_GET['days'] ?? 30))) : 30;
$viewsDashboard = ($authenticated && $activeTab === 'views') ? get_views_dashboard($pdo, $viewDays) : [];

$productQuery = $authenticated ? trim((string) ($_GET['q'] ?? '')) : '';
$allProducts = [];
if ($authenticated && $activeTab === 'products') {
    if ($productQuery !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, asin, title, category_name, price_amount, last_synced_at, affiliate_url
             FROM products
             WHERE asin LIKE :q OR title LIKE :q OR category_name LIKE :q
             ORDER BY id DESC
             LIMIT 500'
        );
        $stmt->execute([':q' => '%' . $productQuery . '%']);
        $allProducts = $stmt->fetchAll();
    } else {
        $allProducts = $pdo->query(
            'SELECT id, asin, title, category_name, price_amount, last_synced_at, affiliate_url
             FROM products
             ORDER BY id DESC
             LIMIT 500'
        )->fetchAll();
    }
}

$overviewStats = [];
if ($authenticated && $activeTab === 'overview') {
$views30dSql = "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)";
$overviewStats = [
        'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'categories' => (int) $pdo->query('SELECT COUNT(DISTINCT category_slug) FROM products')->fetchColumn(),
        'missing_tags' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE affiliate_url NOT LIKE '%tag=%'")->fetchColumn(),
        'missing_images' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE image_url IS NULL OR image_url = ''")->fetchColumn(),
        'views_30d' => (int) $pdo->query($views30dSql)->fetchColumn(),
        'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    ];
}

$dbTables = [];
if ($authenticated && $activeTab === 'maintenance') {
    $tableNames = ['products', 'page_views', 'page_view_hits', 'outbound_clicks', 'posts'];
    foreach ($tableNames as $tableName) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $tableName)->fetchColumn();
        } catch (Throwable $e) {
            $count = -1;
        }
        $dbTables[] = ['name' => $tableName, 'rows' => $count];
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
      $(document).ready(function() {
        var $addContent = $('#add_post_content_html');
        if ($addContent.length) {
          $addContent.summernote({
          placeholder: 'Write your content here...',
          tabsize: 2,
          height: 400,
          toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture', 'video']],
            ['view', ['fullscreen', 'codeview', 'help']]
          ]
          });
        }

        // Force plain textarea in Edit Post, even if Summernote was initialized by cache/old script.
        var $editContent = $('#edit_post_content_html');
        if ($editContent.length) {
          if ($editContent.next('.note-editor').length && typeof $editContent.summernote === 'function') {
            $editContent.summernote('destroy');
          }
          $editContent.show();
        }

        // Keep hidden textarea in sync with Summernote before submit.
        $('form').on('submit', function () {
          var $form = $(this);
          var action = ($form.find('input[name="action"]').val() || '').toLowerCase();
          if (action !== 'add_post' && action !== 'update_post') {
            return;
          }

          var $content = $form.find('textarea[name="content_html"]');
          if ($content.length === 0) {
            return;
          }

          var html = '';
          if (typeof $content.summernote === 'function' && $content.next('.note-editor').length) {
            html = $content.summernote('code') || '';
          } else {
            html = $content.val() || '';
          }
          $content.val(html);
        });
      });
    </script>
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f2f5f8; }
        .wrap { max-width: 980px; margin: 20px auto; padding: 0 14px 28px; }
        .box { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 16px; margin-bottom: 16px; }
        input, textarea, select { width: 100%; box-sizing: border-box; margin: 6px 0 12px; padding: 10px; }
        .btn { background: #0b1f3a; color: #fff; border: 0; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e8edf3; padding: 10px 8px; font-size: 14px; }
        .error { background: #ffe5e5; color: #8a1f1f; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .ok { background: #e4f8ea; color: #165f2b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .toplink { display: inline-block; margin-bottom: 12px; }
        .tabs { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
        .tab { display:inline-block; text-decoration:none; padding:8px 12px; border-radius:999px; border:1px solid #d5deea; background:#fff; color:#1d3556; font-weight:700; font-size:13px; }
        .tab.active { background:#0b1f3a; color:#fff; border-color:#0b1f3a; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
        .stat { background:#f6f9fc; border:1px solid #e2e8f0; border-radius:10px; padding:10px; }
        .stat-k { font-size:12px; color:#4a5b73; margin-bottom:4px; }
        .stat-v { font-size:24px; font-weight:800; color:#0b1f3a; }
        .muted { color:#55647a; font-size:13px; }
        .toolbar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar .field { max-width:280px; }
        .empty { padding:14px; border:1px dashed #d8e2ee; border-radius:8px; color:#5d6f86; background:#f9fbfe; }
        .note-editor { margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <a class="toplink" href="<?= e(url('/')) ?>">Back to site</a>

    <?php foreach ($errors as $error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($flash !== null): ?>
        <div class="ok"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if (!$authenticated): ?>
        <section class="box">
            <h2>Admin Login</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>User</label>
                <input type="text" name="user" required>
                <label>Password</label>
                <input type="password" name="pass" required>
                <button class="btn" type="submit">Login</button>
            </form>
            <p>Default local credentials: <strong>admin</strong> / <strong>change-this-now</strong></p>
        </section>
    <?php else: ?>
        <div class="tabs">
            <a class="tab <?= $activeTab === 'overview' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=overview')) ?>">Overview</a>
            <a class="tab <?= $activeTab === 'products' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=products')) ?>">Products</a>
            <a class="tab <?= $activeTab === 'posts' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=posts')) ?>">Posts</a>
            <a class="tab <?= $activeTab === 'views' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=views&days=' . $viewDays)) ?>">Views</a>
            <a class="tab" href="<?= e(url('/enma/?action=analytics')) ?>">Analytics & Seguridad</a>
            <a class="tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=maintenance')) ?>">Maintenance</a>
        </div>

        <?php if ($activeTab === 'overview'): ?>
        <section class="box">
            <h2>Admin Overview</h2>
            <div class="stats">
                <div class="stat"><div class="stat-k">Products</div><div class="stat-v"><?= number_format((int) ($overviewStats['products'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Categories</div><div class="stat-v"><?= number_format((int) ($overviewStats['categories'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Views (30d)</div><div class="stat-v"><?= number_format((int) ($overviewStats['views_30d'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Missing Tags</div><div class="stat-v"><?= number_format((int) ($overviewStats['missing_tags'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Missing Images</div><div class="stat-v"><?= number_format((int) ($overviewStats['missing_images'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Posts</div><div class="stat-v"><?= number_format((int) ($overviewStats['posts'] ?? 0)) ?></div></div>
            </div>
            <p class="muted">Use tabs for product management, traffic analytics, and DB/scripts maintenance.</p>
        </section>
        <?php elseif ($activeTab === 'products'): ?>
        <?php if ($editingProduct): ?>
        <section class="box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h2 style="margin:0;">Edit Product: <?= e($editingProduct['title']) ?></h2>
                <a href="<?= e(url('/enma/?tab=products')) ?>" style="font-size:13px;">&larr; Cancel Edit</a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="id" value="<?= (int)$editingProduct['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>ASIN</label>
                        <input type="text" name="asin" required value="<?= e($editingProduct['asin']) ?>">
                    </div>
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required value="<?= e($editingProduct['title']) ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Category Name</label>
                        <input type="text" name="category_name" required value="<?= e($editingProduct['category_name']) ?>">
                    </div>
                    <div>
                        <label>Price (USD)</label>
                        <input type="number" name="price_amount" step="0.01" min="0" value="<?= e((string)$editingProduct['price_amount']) ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Image URL</label>
                        <input type="url" name="image_url" value="<?= e($editingProduct['image_url'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Upload New Image</label>
                        <input type="file" name="image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <label>Affiliate URL</label>
                <input type="url" name="affiliate_url" required value="<?= e($editingProduct['affiliate_url']) ?>">
                <label>Description</label>
                <textarea name="description" rows="4"><?= e($editingProduct['description'] ?? '') ?></textarea>
                <button class="btn" type="submit">Update Product</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="box">
            <h2>Add Product</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>ASIN</label>
                        <input type="text" name="asin" required>
                    </div>
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Category Name</label>
                        <input type="text" name="category_name" required>
                    </div>
                    <div>
                        <label>Price (USD)</label>
                        <input type="number" name="price_amount" step="0.01" min="0">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Image URL</label>
                        <input type="url" name="image_url" placeholder="https://...">
                    </div>
                    <div>
                        <label>Upload Image</label>
                        <input type="file" name="image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <label>Affiliate URL</label>
                <input type="url" name="affiliate_url" required placeholder="https://www.amazon.com/dp/...?...">
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
                <button class="btn" type="submit">Save Product</button>
            </form>
        </section>

        <section class="box">
            <h2>Products</h2>
            <form method="get" class="toolbar">
                <input type="hidden" name="tab" value="products">
                <div class="field">
                    <label>Search</label>
                    <input type="text" name="q" value="<?= e($productQuery) ?>" placeholder="ASIN, title, category">
                </div>
                <button class="btn" type="submit">Filter</button>
            </form>
            <?php if ($allProducts === []): ?>
                <div class="empty">No products found for this filter.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ASIN</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Tag</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allProducts as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= e($item['asin']) ?></td>
                        <td><?= e($item['title']) ?></td>
                        <td><?= e($item['category_name']) ?></td>
                        <td><?= e(money($item['price_amount'] !== null ? (float) $item['price_amount'] : null, 'USD')) ?></td>
                        <td><?= amazon_tag_present((string) ($item['affiliate_url'] ?? '')) ? 'OK' : 'Missing' ?></td>
                        <td>
                            <a href="<?= e(url('/enma/?tab=products&edit_product=' . $item['id'])) ?>" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:13px;">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <?php elseif ($activeTab === 'posts'): ?>
        <?php if ($editingPost): ?>
        <section class="box">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h2 style="margin:0;">Edit Post: <?= e($editingPost['title']) ?></h2>
                <a href="<?= e(url('/enma/?tab=posts')) ?>" style="font-size:13px;">&larr; Cancel Edit</a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_post">
                <input type="hidden" name="id" value="<?= (int)$editingPost['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required value="<?= e($editingPost['title']) ?>">
                    </div>
                    <div>
                        <label>Post Type</label>
                        <select name="post_type">
                            <option value="post" <?= $editingPost['post_type'] === 'post' ? 'selected' : '' ?>>Standard Post</option>
                            <option value="guide" <?= $editingPost['post_type'] === 'guide' ? 'selected' : '' ?>>Guide (Structured)</option>
                        </select>
                    </div>
                </div>
                <label>Excerpt (Short summary)</label>
                <textarea name="excerpt" rows="2" required><?= e($editingPost['excerpt'] ?? '') ?></textarea>
                <label>Content (HTML allowed)</label>
                <textarea id="edit_post_content_html" name="content_html" rows="16" style="font-family: Consolas, 'Courier New', monospace; line-height: 1.45;"><?= e($editingPost['content_html'] ?? '') ?></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Featured Image URL</label>
                        <input type="text" name="featured_image" value="<?= e($editingPost['featured_image'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Upload New Image (Replaces current)</label>
                        <input type="file" name="featured_image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <?php if (!empty($editingPost['featured_image'])): ?>
                    <div style="margin-bottom:12px;">
                        <span class="muted">Current Image:</span><br>
                        <img src="<?= e(url($editingPost['featured_image'])) ?>" alt="Preview" style="max-width:100px;max-height:100px;border-radius:6px;margin-top:5px;border:1px solid #ddd;">
                    </div>
                <?php endif; ?>
                <button class="btn" type="submit">Update Post</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="box">
            <h2>Add New Post</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required placeholder="e.g. How to Clean Your Eyepieces">
                    </div>
                    <div>
                        <label>Post Type</label>
                        <select name="post_type">
                            <option value="post">Standard Post</option>
                            <option value="guide">Guide (Structured)</option>
                        </select>
                    </div>
                </div>
                <label>Excerpt (Short summary)</label>
                <textarea name="excerpt" rows="2" required placeholder="A brief summary for listings..."></textarea>
                <label>Content (HTML allowed)</label>
                <textarea id="add_post_content_html" name="content_html" rows="10" placeholder="<p>Full article content here...</p>"></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Featured Image URL (Optional)</label>
                        <input type="text" name="featured_image" placeholder="https://...">
                    </div>
                    <div>
                        <label>Upload Image (Optional)</label>
                        <input type="file" name="featured_image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <button class="btn" type="submit">Publish Post</button>
            </form>
        </section>

        <section class="box">
            <h2>Existing Posts</h2>
            <?php
            $stmt = $pdo->query('SELECT id, title, slug, post_type, status, published_at FROM posts ORDER BY published_at DESC');
            $allPosts = $stmt->fetchAll();
            ?>
            <?php if ($allPosts === []): ?>
                <div class="empty">No posts or guides found in database.</div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allPosts as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['title']) ?></strong><br>
                                <span class="muted"><?= e($p['slug']) ?></span>
                            </td>
                            <td><span class="badge" style="background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:11px;"><?= e(strtoupper($p['post_type'])) ?></span></td>
                            <td><?= e($p['status']) ?></td>
                            <td><?= e(substr((string)$p['published_at'], 0, 10)) ?></td>
                            <td>
                                <a href="<?= e(url('/enma/?tab=posts&edit_post=' . $p['id'])) ?>" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:13px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
        <?php elseif ($activeTab === 'views'): ?>
        <section class="box">
            <h2>Views Dashboard</h2>
            <p style="margin: 0 0 10px; font-size: 14px; color: #334155;">Tracking window: last <?= (int) ($viewsDashboard['days'] ?? $viewDays) ?> days (from <?= e((string) ($viewsDashboard['from_date'] ?? '')) ?> UTC)</p>
            <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
                <input type="hidden" name="tab" value="views">
                <div style="max-width:160px;">
                    <label>Days</label>
                    <input type="number" name="days" min="7" max="180" value="<?= (int) $viewDays ?>">
                </div>
                <button class="btn" type="submit">Refresh</button>
            </form>

            <div class="stats">
                <div class="stat">
                    <div class="stat-k">Total Views</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['total_views'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Tracked Paths</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['unique_paths'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Aggregated Rows</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['rows_count'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Outbound Clicks</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['clicks']['total_clicks'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">CTR</div>
                    <div class="stat-v"><?= number_format((float) (($viewsDashboard['clicks']['ctr_percent'] ?? 0.0)), 2) ?>%</div>
                </div>
            </div>
            <p style="margin: 0; font-size: 12px; color: #5b6678;">Country is best-effort from server/CDN geo headers (fallback: Accept-Language).</p>
        </section>

        <section class="box">
            <h2>Top Pages</h2>
            <table>
                <thead>
                <tr>
                    <th>Path</th>
                    <th>Type</th>
                    <th>Views</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['top_pages'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['path'] ?? '')) ?></td>
                        <td><?= e((string) ($row['page_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['total_views'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Top Product Pages</h2>
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Slug</th>
                    <th>Views</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['top_products'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['title'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['total_views'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Top Clicked Products</h2>
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Slug</th>
                    <th>Clicks</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['clicks']['top_products'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['title'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['clicks'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Traffic Source Breakdown</h2>
            <table>
                <thead>
                <tr>
                    <th>Source</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['source_breakdown'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['source_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Top Countries</h2>
            <table>
                <thead>
                <tr>
                    <th>Country</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['top_countries'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['country_code'] ?? 'UNK')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Top Referrers</h2>
            <table>
                <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Type</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['top_referrers'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['referrer_host'] ?? 'direct')) ?></td>
                        <td><?= e((string) ($row['source_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php else: ?>
        <section class="box">
            <h2>Maintenance Tools</h2>
            <p class="muted" style="margin: 0 0 12px;">Run safe tasks behind login. All schema tasks are idempotent.</p>

            <form method="post" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="task" value="update_db_schema">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Update DB Schema (safe)</button>
            </form>

            <form method="post" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="task" value="migrate_guides_to_db">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Migrate Guides to DB</button>
            </form>

            <form method="post" style="margin-bottom: 10px;">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="task" value="normalize_affiliate_urls">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Normalize Amazon Affiliate URLs</button>
            </form>

            <form method="post" style="margin-bottom: 12px;">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="task" value="refresh_sync_labels">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Refresh Sync Labels (23h)</button>
            </form>

            <form method="post" style="margin-bottom: 12px;">
                <input type="hidden" name="action" value="maintenance_run">
                <input type="hidden" name="task" value="fix_product_images">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Fix Product Images (ASIN-based)</button>
            </form>

            <?php if ($maintenanceLog !== []): ?>
                <div style="background:#f6f9fc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;">
                    <?php foreach ($maintenanceLog as $line): ?>
                        <div style="font-family:monospace;font-size:13px;"><?= e($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="box">
            <h2>Database Snapshot</h2>
            <table>
                <thead>
                    <tr><th>Table</th><th>Rows</th></tr>
                </thead>
                <tbody>
                <?php foreach ($dbTables as $t): ?>
                    <tr>
                        <td><?= e((string) $t['name']) ?></td>
                        <td><?= $t['rows'] >= 0 ? number_format((int) $t['rows']) : 'n/a' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($advancedEnabled): ?>
        <section class="box">
            <h2>Advanced Mode</h2>
            <p style="margin: 0 0 12px; font-size: 14px; color: #8a1f1f;">
                High-impact tasks. Use only if you understand the effect on catalog data.
            </p>
            <form method="post">
                <input type="hidden" name="action" value="maintenance_advanced_run">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <label>Task</label>
                <select name="task" required>
                    <option value="refresh_sync_cli">Refresh Sync Labels (script)</option>
                    <option value="reseed_real_catalog">Reseed Real Catalog (can overwrite/rehydrate catalog)</option>
                    <option value="seed_more_products">Seed More Products</option>
                </select>

                <label>Advanced Key</label>
                <input type="password" name="advanced_key" required>

                <label>Confirmation Text</label>
                <input type="text" name="confirm_text" required placeholder="RUN TASK_NAME">

                <button class="btn" type="submit">Run Advanced Task</button>
            </form>
            <p style="margin: 10px 0 0; font-size: 12px; color: #555;">
                Confirmation must match: <code>RUN &lt;task_value&gt;</code> in uppercase.
            </p>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <section class="box">
            <form method="post">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Logout</button>
            </form>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
