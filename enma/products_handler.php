<?php

declare(strict_types=1);

/**
 * Products handler for ENMA admin panel
 * Handles CRUD operations for products
 */

if (!$authenticated) {
    return;
}

// Add new product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $asin = strtoupper(trim((string) ($_POST['asin'] ?? '')));
    $title = trim((string) ($_POST['title'] ?? ''));
    $categoryName = trim((string) ($_POST['category_name'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $affiliateUrl = trim((string) ($_POST['affiliate_url'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $uploaded = enma_handle_image_upload('image_file', $errors, 'products');
    if ($uploaded !== null) {
        $imageUrl = $uploaded;
    }

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
                    image_url, affiliate_url, status,
                    last_synced_at, created_at, updated_at
                 ) VALUES (
                    :asin, :slug, :title, :description, :category_slug, :category_name,
                    :image_url, :affiliate_url, :status,
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
                ':image_url' => $imageUrl,
                ':affiliate_url' => $affiliateUrl,
                ':status' => 'published',
                ':last_synced_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $newProductId = (int) $pdo->lastInsertId();
            enma_record_activity($pdo, 'product.create', 'product', $newProductId, [
                'title' => $title,
                'asin' => $asin,
                'category_name' => $categoryName,
            ]);
            $indexNowResult = indexnow_submit_urls([
                absolute_url('/product/' . $slug),
                absolute_url('/category/' . $categorySlug),
                absolute_url('/' . $categorySlug),
            ]);
            if (!empty($indexNowResult['message'])) {
                $maintenanceLog[] = (string) $indexNowResult['message'];
            }
            $flash = 'Product created successfully.';
        } catch (Throwable $e) {
            $errors[] = 'Insert failed. Verify ASIN uniqueness and URL fields.';
        }
    }
}

// Update existing product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $id = (int)($_POST['id'] ?? 0);
    $asin = strtoupper(trim((string) ($_POST['asin'] ?? '')));
    $title = trim((string) ($_POST['title'] ?? ''));
    $categoryName = trim((string) ($_POST['category_name'] ?? ''));
    $imageUrl = trim((string) ($_POST['image_url'] ?? ''));
    $affiliateUrl = trim((string) ($_POST['affiliate_url'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $uploaded = enma_handle_image_upload('image_file', $errors, 'products');
    if ($uploaded !== null) {
        $imageUrl = $uploaded;
    }

    if ($id <= 0 || $asin === '' || $title === '' || $categoryName === '' || $affiliateUrl === '') {
        $errors[] = 'ID, ASIN, title, category and affiliate URL are required.';
    }

    if ($errors === []) {
        $now = now_iso();
        $categorySlug = slugify($categoryName);

        try {
            $stmt = $pdo->prepare(
                'UPDATE products SET 
                    asin = :asin, 
                    title = :title, 
                    description = :description, 
                    category_slug = :category_slug, 
                    category_name = :category_name, 
                    image_url = :image_url, 
                    affiliate_url = :affiliate_url, 
                    updated_at = :updated_at 
                WHERE id = :id'
            );

            $stmt->execute([
                ':asin' => $asin,
                ':title' => $title,
                ':description' => $description,
                ':category_slug' => $categorySlug,
                ':category_name' => $categoryName,
                ':image_url' => $imageUrl,
                ':affiliate_url' => $affiliateUrl,
                ':updated_at' => $now,
                ':id' => $id
            ]);

            enma_record_activity($pdo, 'product.update', 'product', $id, [
                'title' => $title,
                'asin' => $asin,
                'category_name' => $categoryName,
            ]);
            $slugStmt = $pdo->prepare('SELECT slug, category_slug FROM products WHERE id = :id LIMIT 1');
            $slugStmt->execute([':id' => $id]);
            $savedProduct = $slugStmt->fetch();
            $savedSlug = trim((string) ($savedProduct['slug'] ?? ''));
            $savedCategorySlug = trim((string) ($savedProduct['category_slug'] ?? $categorySlug));
            if ($savedSlug !== '') {
                $indexNowResult = indexnow_submit_urls([
                    absolute_url('/product/' . $savedSlug),
                    absolute_url('/category/' . $savedCategorySlug),
                    absolute_url('/' . $savedCategorySlug),
                ]);
                if (!empty($indexNowResult['message'])) {
                    $maintenanceLog[] = (string) $indexNowResult['message'];
                }
            }
            $flash = 'Product updated successfully.';
            $editingProduct = null;
        } catch (Throwable $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Delete product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_product') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $productStmt = $pdo->prepare('SELECT title, asin, category_name, slug, category_slug FROM products WHERE id = :id LIMIT 1');
            $productStmt->execute([':id' => $id]);
            $productRow = $productStmt->fetch();
            $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
            $stmt->execute([':id' => $id]);
            enma_record_activity($pdo, 'product.delete', 'product', $id, [
                'title' => (string) ($productRow['title'] ?? ''),
                'asin' => (string) ($productRow['asin'] ?? ''),
                'category_name' => (string) ($productRow['category_name'] ?? ''),
            ]);
            $productSlug = trim((string) ($productRow['slug'] ?? ''));
            $categorySlug = trim((string) ($productRow['category_slug'] ?? ''));
            if ($productSlug !== '') {
                $indexNowResult = indexnow_submit_urls([
                    absolute_url('/product/' . $productSlug),
                    absolute_url('/category/' . $categorySlug),
                    absolute_url('/' . $categorySlug),
                ]);
                if (!empty($indexNowResult['message'])) {
                    $maintenanceLog[] = (string) $indexNowResult['message'];
                }
            }
            $flash = 'Product deleted successfully.';
        }
    }
}

// Load product for editing
if ($_GET['edit_product'] ?? '') {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['edit_product']]);
    $editingProduct = $stmt->fetch();
}
