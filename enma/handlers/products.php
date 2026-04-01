<?php

declare(strict_types=1);

/**
 * ENMA Product Handler
 * 
 * Handles all product-related operations (add, edit, delete)
 */

require_once __DIR__ . '/helpers.php';

/**
 * Handle add product form submission
 */
function enma_handle_add_product(array &$errors, array $postData): ?string
{
    global $pdo;
    
    if (!csrf_is_valid($postData['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        return null;
    }

    $asin = strtoupper(trim((string) ($postData['asin'] ?? '')));
    $title = trim((string) ($postData['title'] ?? ''));
    $categoryName = trim((string) ($postData['category_name'] ?? ''));
    $price = trim((string) ($postData['price_amount'] ?? ''));
    $imageUrl = trim((string) ($postData['image_url'] ?? ''));
    $affiliateUrl = trim((string) ($postData['affiliate_url'] ?? ''));
    $description = trim((string) ($postData['description'] ?? ''));

    if ($asin === '' || $title === '' || $categoryName === '' || $affiliateUrl === '') {
        $errors[] = 'ASIN, title, category and affiliate URL are required.';
        return null;
    }
    if ($imageUrl !== '' && filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Image URL is not valid.';
        return null;
    }
    if (filter_var($affiliateUrl, FILTER_VALIDATE_URL) === false) {
        $errors[] = 'Affiliate URL is not valid.';
        return null;
    }

    $affiliateUrl = amazon_affiliate_url($affiliateUrl);

    if ($errors !== []) {
        return null;
    }

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

        return 'Product created successfully.';
    } catch (Throwable $e) {
        $errors[] = 'Insert failed. Verify ASIN uniqueness and URL fields.';
        return null;
    }
}

/**
 * Handle edit product form submission
 */
function enma_handle_edit_product(array &$errors, array $postData): ?string
{
    global $pdo;
    
    if (!csrf_is_valid($postData['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        return null;
    }

    $id = (int) ($postData['id'] ?? 0);
    $asin = strtoupper(trim((string) ($postData['asin'] ?? '')));
    $title = trim((string) ($postData['title'] ?? ''));
    $categoryName = trim((string) ($postData['category_name'] ?? ''));
    $price = trim((string) ($postData['price_amount'] ?? ''));
    $affiliateUrl = trim((string) ($postData['affiliate_url'] ?? ''));
    $description = trim((string) ($postData['description'] ?? ''));
    $keepImage = (string) ($postData['keep_image'] ?? '');

    if ($id <= 0 || $asin === '' || $title === '' || $categoryName === '' || $affiliateUrl === '') {
        $errors[] = 'ID, ASIN, title, category and affiliate URL are required.';
        return null;
    }

    $uploadedImageUrl = enma_handle_image_upload('product_image', $errors);
    $finalImageUrl = $uploadedImageUrl ?? ($keepImage === '1' ? null : '');

    $affiliateUrl = amazon_affiliate_url($affiliateUrl);

    if ($errors !== []) {
        return null;
    }

    try {
        $updateFields = [
            ':asin' => $asin,
            ':title' => $title,
            ':category_name' => $categoryName,
            ':category_slug' => slugify($categoryName),
            ':price_amount' => is_numeric($price) ? (float) $price : null,
            ':affiliate_url' => $affiliateUrl,
            ':description' => $description,
            ':updated_at' => now_iso(),
            ':id' => $id,
        ];

        if ($finalImageUrl !== null) {
            $updateFields[':image_url'] = $finalImageUrl;
        }

        $sql = 'UPDATE products SET
            asin = :asin,
            title = :title,
            category_name = :category_name,
            category_slug = :category_slug,
            price_amount = :price_amount,
            affiliate_url = :affiliate_url,
            description = :description,
            updated_at = :updated_at';

        if ($finalImageUrl !== null) {
            $sql .= ', image_url = :image_url';
        }

        $sql .= ' WHERE id = :id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateFields);

        return 'Product updated successfully.';
    } catch (Throwable $e) {
        $errors[] = 'Update failed: ' . $e->getMessage();
        return null;
    }
}

/**
 * Handle delete product form submission
 */
function enma_handle_delete_product(array &$errors, array $postData): ?string
{
    global $pdo;
    
    if (!csrf_is_valid($postData['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
        return null;
    }

    $id = (int) ($postData['id'] ?? 0);
    if ($id <= 0) {
        $errors[] = 'Invalid product ID.';
        return null;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return 'Product deleted successfully.';
    } catch (Throwable $e) {
        $errors[] = 'Delete failed: ' . $e->getMessage();
        return null;
    }
}

/**
 * Fetch products with optional search query
 */
function enma_fetch_products(string $query = ''): array
{
    global $pdo;
    
    if ($query !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, asin, title, category_name, price_amount, last_synced_at, affiliate_url
             FROM products
             WHERE asin LIKE :q OR title LIKE :q OR category_name LIKE :q
             ORDER BY id DESC
             LIMIT 500'
        );
        $stmt->execute([':q' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    return $pdo->query(
        'SELECT id, asin, title, category_name, price_amount, last_synced_at, affiliate_url
         FROM products
         ORDER BY id DESC
         LIMIT 500'
    )->fetchAll();
}
