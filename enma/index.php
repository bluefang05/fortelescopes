<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$flash = null;
$maxLoginAttempts = 5;
$lockSeconds = 600;
$_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0);
$_SESSION['login_locked_until'] = (int) ($_SESSION['login_locked_until'] ?? 0);
$isLocked = ($_SESSION['login_locked_until'] > time());

function enma_handle_image_upload(string $fieldName, array &$errors): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = 'Image upload failed.';
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        $errors[] = 'Image must be between 1 byte and 4MB.';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'Invalid uploaded image.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        $errors[] = 'Only JPG, PNG, WEBP, or GIF are allowed.';
        return null;
    }

    $ext = $map[$mime];
    $uploadDir = __DIR__ . '/../assets/uploads/products';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Could not create upload directory.';
        return null;
    }

    $name = 'p_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        $errors[] = 'Could not move uploaded image.';
        return null;
    }

    return absolute_url('/assets/uploads/products/' . $name);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('/enma/'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    if ($isLocked) {
        $errors[] = 'Too many login attempts. Try again in a few minutes.';
    }

    $user = trim((string) ($_POST['user'] ?? ''));
    $pass = (string) ($_POST['pass'] ?? '');

    if ($errors === [] && hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = 0;
        header('Location: ' . url('/enma/'));
        exit;
    }

    if ($errors === []) {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= $maxLoginAttempts) {
            $_SESSION['login_locked_until'] = time() + $lockSeconds;
            $_SESSION['login_attempts'] = 0;
            $errors[] = 'Too many login attempts. Try again in 10 minutes.';
        } else {
            $errors[] = 'Invalid credentials.';
        }
    }
}

$authenticated = !empty($_SESSION['admin_ok']);
$maintenanceLog = [];
$advancedEnabled = ENMA_ADVANCED_KEY !== '';

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_advanced_run') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } elseif (!$advancedEnabled) {
        $errors[] = 'Advanced mode is disabled. Set ENMA_ADVANCED_KEY in .env first.';
    } else {
        $task = trim((string) ($_POST['task'] ?? ''));
        $advancedKey = (string) ($_POST['advanced_key'] ?? '');
        $confirmText = strtoupper(trim((string) ($_POST['confirm_text'] ?? '')));
        $expectedConfirm = 'RUN ' . strtoupper($task);

        if (!hash_equals(ENMA_ADVANCED_KEY, $advancedKey)) {
            $errors[] = 'Advanced key is invalid.';
        }
        if ($confirmText !== $expectedConfirm) {
            $errors[] = 'Invalid confirmation text. Use exactly: ' . $expectedConfirm;
        }

        $taskMap = [
            'refresh_sync_cli' => __DIR__ . '/../scripts/cron_refresh.php',
            'reseed_real_catalog' => __DIR__ . '/../scripts/seed_real_catalog.php',
            'seed_more_products' => __DIR__ . '/../scripts/seed_more_products.php',
        ];

        if (!isset($taskMap[$task])) {
            $errors[] = 'Unknown advanced task.';
        }
        if ($task === 'seed_more_products' && DB_DRIVER !== 'sqlite') {
            $errors[] = 'seed_more_products supports sqlite only in current script version.';
        }

        if ($errors === []) {
            if (!defined('ENMA_ALLOW_WEB_RUN')) {
                define('ENMA_ALLOW_WEB_RUN', true);
            }

            $scriptPath = realpath($taskMap[$task] ?? '');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Advanced task completed: ' . $task;
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Advanced task failed: ' . $e->getMessage();
                }
            }
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'maintenance_run') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $task = trim((string) ($_POST['task'] ?? ''));

        if ($task === 'normalize_affiliate_urls') {
            $stmt = $pdo->query('SELECT id, affiliate_url FROM products');
            $rows = $stmt->fetchAll();
            $updated = 0;
            $checked = 0;

            $updateStmt = $pdo->prepare(
                'UPDATE products
                 SET affiliate_url = :affiliate_url, updated_at = :updated_at
                 WHERE id = :id'
            );

            foreach ($rows as $row) {
                $checked++;
                $id = (int) ($row['id'] ?? 0);
                $current = (string) ($row['affiliate_url'] ?? '');
                $normalized = amazon_affiliate_url($current);

                if ($id <= 0 || $normalized === '' || $normalized === $current) {
                    continue;
                }

                $updateStmt->execute([
                    ':affiliate_url' => $normalized,
                    ':updated_at' => now_iso(),
                    ':id' => $id,
                ]);
                $updated++;
            }

            $flash = "Affiliate normalization done. Checked: {$checked} | Updated: {$updated}";
            $maintenanceLog[] = 'Task: normalize_affiliate_urls';
            $maintenanceLog[] = 'Tag: ' . AMAZON_ASSOCIATE_TAG;
        } elseif ($task === 'update_db_schema') {
            $scriptPath = realpath(__DIR__ . '/../scripts/update_db_schema.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'DB schema updater completed.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: update_db_schema';
                    $maintenanceLog[] = 'Mode: idempotent (CREATE IF NOT EXISTS)';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'DB schema updater failed: ' . $e->getMessage();
                }
            }
        } elseif ($task === 'refresh_sync_labels') {
            $threshold = gmdate('c', time() - (23 * 3600));
            $now = now_iso();
            $stmt = $pdo->prepare(
                'UPDATE products
                 SET last_synced_at = :now, updated_at = :now
                 WHERE status = "published"
                   AND (last_synced_at IS NULL OR last_synced_at < :threshold)'
            );
            $stmt->execute([
                ':now' => $now,
                ':threshold' => $threshold,
            ]);
            $affected = $stmt->rowCount();
            $flash = "Sync labels refreshed. Products updated: {$affected}";
            $maintenanceLog[] = 'Task: refresh_sync_labels';
            $maintenanceLog[] = 'Threshold: 23h';
        } elseif ($task === 'fix_product_images') {
            $scriptPath = realpath(__DIR__ . '/../scripts/fix_product_images.php');
            $scriptsRoot = realpath(__DIR__ . '/../scripts');

            if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
                $errors[] = 'Invalid script path.';
            } else {
                ob_start();
                try {
                    require $scriptPath;
                    $output = trim((string) ob_get_clean());
                    $flash = 'Image fix completed.';
                    if ($output !== '') {
                        foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                            if (trim((string) $line) !== '') {
                                $maintenanceLog[] = (string) $line;
                            }
                        }
                    }
                    $maintenanceLog[] = 'Task: fix_product_images';
                    $maintenanceLog[] = 'Source: Amazon product page scrape + safe placeholder';
                } catch (Throwable $e) {
                    ob_end_clean();
                    $errors[] = 'Image fix failed: ' . $e->getMessage();
                }
            }
        } else {
            $errors[] = 'Unknown maintenance task.';
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $asin = strtoupper(trim((string) ($_POST['asin'] ?? '')));
    $title = trim((string) ($_POST['title'] ?? ''));
    $categoryName = trim((string) ($_POST['category_name'] ?? ''));
    $price = trim((string) ($_POST['price_amount'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $affiliateUrl = trim((string) ($_POST['affiliate_url'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($asin === '' || $title === '' || $categoryName === '' || $affiliateUrl === '') {
        $errors[] = 'ASIN, title, category and affiliate URL are required.';
    }
    if ($imageUrl !== '' && filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Image URL is not valid.';
    }
    if (filter_var($affiliateUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Affiliate URL is not valid.';
    }
    if ($errors === []) {
        $affiliateUrl = amazon_affiliate_url($affiliateUrl);
    }

    if ($errors === []) {
        $slug = unique_slug($pdo, $title);
        $categorySlug = slugify($categoryName);
        $now = now_iso();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (
                    asin, slug, title, description, category_slug, category_name,
                    price_amount, price_currency, image_url, affiliate_url, status,
                    last_synced_at, created_at, updated_at
                 ) VALUES (
                    :asin, :slug, :title, :description, :category_slug, :category_name,
                    :price_amount, :price_currency, :image_url, :affiliate_url, :status,
                    :last_synced_at, :created_at, :updated_at
                 )'
            );

            $stmt->execute([
                ':asin' => $asin,
                ':slug' => $slug,
                ':title' => $title,
                ':description' => $description,
                ':category_slug' => $categorySlug,
                ':category_name' => $categoryName,
                ':price_amount' => is_numeric($price) ? (float) $price : null,
                ':price_currency' => 'USD',
                ':image_url' => $imageUrl,
                ':affiliate_url' => $affiliateUrl,
                ':status' => 'published',
                ':last_synced_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $flash = 'Product created successfully.';
        } catch (Throwable $e) {
            $errors[] = 'Insert failed. Verify ASIN uniqueness and URL fields.';
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_guides_overrides') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $slugs = ['best-beginner-telescopes', 'best-telescope-accessories', 'best-telescopes-under-500'];
        $fields = ['title', 'description', 'intro', 'final_recommendation', 'cta_text', 'cta_note'];
        $payload = [];

        foreach ($slugs as $slug) {
            $payload[$slug] = [];
            foreach ($fields as $field) {
                $key = $slug . '__' . $field;
                $value = trim((string) ($_POST[$key] ?? ''));
                if ($value !== '') {
                    $payload[$slug][$field] = $value;
                }
            }
        }

        if (!save_guides_overrides($payload)) {
            $errors[] = 'Could not save guide overrides file.';
        } else {
            $flash = 'Guide text overrides saved.';
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        $asin = strtoupper(trim((string) ($_POST['asin'] ?? '')));
        $title = trim((string) ($_POST['title'] ?? ''));
        $categoryName = trim((string) ($_POST['category_name'] ?? ''));
        $price = trim((string) ($_POST['price_amount'] ?? ''));
        $affiliateUrl = trim((string) ($_POST['affiliate_url'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $keepImage = (string) ($_POST['keep_image'] ?? '');
        
        $uploadedImageUrl = enma_handle_image_upload('product_image', $errors);
        
        if ($id <= 0 || $asin === '' || $title === '' || $categoryName === '' || $affiliateUrl === '') {
            $errors[] = 'ID, ASIN, title, category and affiliate URL are required.';
        }
        if ($affiliateUrl !== '' && filter_var($affiliateUrl, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'Affiliate URL is not valid.';
        }
        if ($errors === []) {
            $affiliateUrl = amazon_affiliate_url($affiliateUrl);
        }
        
        if ($errors === []) {
            $now = now_iso();
            $imageClause = '';
            $params = [
                ':asin' => $asin,
                ':title' => $title,
                ':description' => $description,
                ':category_slug' => slugify($categoryName),
                ':category_name' => $categoryName,
                ':price_amount' => is_numeric($price) ? (float) $price : null,
                ':affiliate_url' => $affiliateUrl,
                ':updated_at' => $now,
                ':id' => $id,
            ];
            
            if ($uploadedImageUrl !== null) {
                $imageClause = ', image_url = :image_url';
                $params[':image_url'] = $uploadedImageUrl;
            } elseif ($keepImage === '0' || $keepImage === '') {
                $imageClause = ', image_url = NULL';
            }
            
            try {
                $stmt = $pdo->prepare(
                    'UPDATE products SET
                        asin = :asin,
                        title = :title,
                        description = :description,
                        category_slug = :category_slug,
                        category_name = :category_name,
                        price_amount = :price_amount,
                        affiliate_url = :affiliate_url,
                        updated_at = :updated_at' . $imageClause . '
                     WHERE id = :id'
                );
                $stmt->execute($params);
                $flash = 'Product updated successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Update failed: ' . $e->getMessage();
            }
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid product ID.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $flash = 'Product deleted successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

$activeTab = $authenticated ? (string) ($_GET['tab'] ?? 'overview') : 'overview';
if (!in_array($activeTab, ['overview', 'products', 'guides', 'views', 'maintenance'], true)) {
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
    $views30dSql = DB_DRIVER === 'mysql'
        ? "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)"
        : "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= date('now','-29 day')";
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

$guideOverrides = ($authenticated && $activeTab === 'guides') ? load_guides_overrides() : [];

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
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
            <a class="tab <?= $activeTab === 'guides' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=guides')) ?>">Guides</a>
            <a class="tab <?= $activeTab === 'views' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=views&days=' . $viewDays)) ?>">Views</a>
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
        <section class="box">
            <h2>Add Product</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>ASIN</label>
                <input type="text" name="asin" required>
                <label>Title</label>
                <input type="text" name="title" required>
                <label>Category Name</label>
                <input type="text" name="category_name" required>
                <label>Price (USD)</label>
                <input type="number" name="price_amount" step="0.01" min="0">
                <label>Image URL</label>
                <input type="url" name="image_url" placeholder="https://...">
                <label>Affiliate URL</label>
                <input type="url" name="affiliate_url" required placeholder="https://www.amazon.com/dp/...?...">
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
                <button class="btn" type="submit">Save</button>
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
                        <th>Image</th>
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
                    <?php
                    $editData = null;
                    if (isset($_GET['edit']) && (int)$_GET['edit'] === (int)$item['id']) {
                        $editData = $item;
                        try {
                            $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id');
                            $stmt->execute([':id' => $item['id']]);
                            $editData = $stmt->fetch();
                        } catch (Throwable $e) {}
                    }
                    ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td>
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="" style="max-width:60px;max-height:60px;object-fit:contain;border-radius:4px;">
                            <?php else: ?>
                                <span class="muted">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($item['asin']) ?></td>
                        <td><?= e($item['title']) ?></td>
                        <td><?= e($item['category_name']) ?></td>
                        <td><?= e(money($item['price_amount'] !== null ? (float) $item['price_amount'] : null, 'USD')) ?></td>
                        <td><?= amazon_tag_present((string) ($item['affiliate_url'] ?? '')) ? 'OK' : 'Missing' ?></td>
                        <td>
                            <?php if ($editData === null): ?>
                                <a href="<?= e(url('/enma/?tab=products&edit=' . $item['id'])) ?>" class="btn" style="padding:6px 10px;font-size:12px;">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" class="btn" style="padding:6px 10px;font-size:12px;background:#c62828;">Delete</button>
                                </form>
                            <?php else: ?>
                                <span class="muted" style="font-size:12px;">Editing...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($editData !== null): ?>
                    <tr>
                        <td colspan="8" style="background:#f9fbfe;padding:16px;">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="edit_product">
                                <input type="hidden" name="id" value="<?= (int) $editData['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                
                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                    <div>
                                        <label>ASIN</label>
                                        <input type="text" name="asin" value="<?= e($editData['asin']) ?>" required>
                                        
                                        <label>Title</label>
                                        <input type="text" name="title" value="<?= e($editData['title']) ?>" required>
                                        
                                        <label>Category Name</label>
                                        <input type="text" name="category_name" value="<?= e($editData['category_name']) ?>" required>
                                        
                                        <label>Price (USD)</label>
                                        <input type="number" name="price_amount" step="0.01" min="0" value="<?= e($editData['price_amount']) ?>">
                                    </div>
                                    <div>
                                        <label>Current Image</label>
                                        <?php if (!empty($editData['image_url'])): ?>
                                            <div style="margin-bottom:8px;">
                                                <img src="<?= e($editData['image_url']) ?>" alt="" style="max-width:150px;max-height:150px;object-fit:contain;border-radius:4px;">
                                            </div>
                                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                                                <input type="checkbox" name="keep_image" value="1" checked> Keep current image
                                            </label>
                                        <?php else: ?>
                                            <p class="muted" style="font-size:13px;margin:6px 0;">No image currently set.</p>
                                        <?php endif; ?>
                                        
                                        <label style="margin-top:10px;">Upload New Image</label>
                                        <input type="file" name="product_image" accept="image/*">
                                        
                                        <label>Affiliate URL</label>
                                        <input type="url" name="affiliate_url" value="<?= e($editData['affiliate_url']) ?>" required>
                                    </div>
                                </div>
                                
                                <label>Description</label>
                                <textarea name="description" rows="4"><?= e($editData['description']) ?></textarea>
                                
                                <div style="margin-top:12px;display:flex;gap:8px;">
                                    <button class="btn" type="submit">Save Changes</button>
                                    <a href="<?= e(url('/enma/?tab=products')) ?>" class="btn" style="background:#6c757d;">Cancel</a>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <?php elseif ($activeTab === 'guides'): ?>
        <section class="box">
            <h2>Guides Text Manager</h2>
            <p class="muted">Edit key conversion copy for the three main guides without touching code.</p>
            <form method="post">
                <input type="hidden" name="action" value="save_guides_overrides">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <?php
                $guideMeta = [
                    'best-beginner-telescopes' => 'Best Beginner Telescopes',
                    'best-telescope-accessories' => 'Best Telescope Accessories',
                    'best-telescopes-under-500' => 'Best Telescopes Under $500',
                ];
                $fields = [
                    'title' => 'Title',
                    'description' => 'Meta Description / Intro Description',
                    'intro' => 'Quick Answer Intro',
                    'final_recommendation' => 'Final Recommendation',
                    'cta_text' => 'CTA Button Text',
                    'cta_note' => 'CTA Note',
                ];
                ?>
                <?php foreach ($guideMeta as $slug => $label): ?>
                    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:12px;margin-bottom:12px;background:#f9fbfe;">
                        <h3 style="margin:0 0 10px;"><?= e($label) ?> <span class="muted">(<?= e($slug) ?>)</span></h3>
                        <?php foreach ($fields as $fieldKey => $fieldLabel): ?>
                            <label><?= e($fieldLabel) ?></label>
                            <?php
                            $name = $slug . '__' . $fieldKey;
                            $val = (string) ($guideOverrides[$slug][$fieldKey] ?? '');
                            ?>
                            <?php if (in_array($fieldKey, ['description', 'intro', 'final_recommendation', 'cta_note'], true)): ?>
                                <textarea name="<?= e($name) ?>" rows="3" placeholder="Leave empty to keep default"><?= e($val) ?></textarea>
                            <?php else: ?>
                                <input type="text" name="<?= e($name) ?>" value="<?= e($val) ?>" placeholder="Leave empty to keep default">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <button class="btn" type="submit">Save Guide Text Overrides</button>
            </form>
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
                    <option value="seed_more_products">Seed More Products (sqlite only)</option>
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
