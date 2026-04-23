<?php

declare(strict_types=1);

/**
 * Products handler for ENMA admin panel
 * Handles CRUD operations for products
 */

if (!$authenticated) {
    return;
}

if (!function_exists('enma_products_extract_array_expression')) {
    function enma_products_extract_array_expression(string $input): string
    {
        $raw = trim($input);
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/^\s*```(?:php)?\s*/i', '', $raw) ?? $raw;
        $raw = preg_replace('/\s*```\s*$/', '', $raw) ?? $raw;
        $raw = trim(str_replace('<?php', '', $raw));

        if (preg_match('/\$products\s*=\s*(\[.*\]);/is', $raw, $m) === 1) {
            return trim((string) $m[1]);
        }

        if (preg_match('/(\[.*\])\s*$/is', $raw, $m) === 1) {
            return trim((string) $m[1]);
        }

        return '';
    }
}

if (!function_exists('enma_products_parse_ai_payload')) {
    function enma_products_parse_ai_payload(string $payload): array
    {
        $expr = enma_products_extract_array_expression($payload);
        if ($expr === '') {
            throw new RuntimeException('Could not find a valid $products array in payload.');
        }

        $parsed = @eval('return ' . $expr . ';');
        if (!is_array($parsed)) {
            throw new RuntimeException('Payload did not evaluate to a PHP array.');
        }

        return $parsed;
    }
}

if (!function_exists('enma_products_normalize_ai_category')) {
    function enma_products_normalize_ai_category(string $raw): string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return 'accessories';
        }

        if (
            str_contains($value, 'telescope')
            || str_contains($value, 'telescopio')
            || str_contains($value, 'scope')
        ) {
            return 'telescopes';
        }

        if (str_contains($value, 'accessor')) {
            return 'accessories';
        }

        return in_array($value, ['telescopes', 'telescope'], true) ? 'telescopes' : 'accessories';
    }
}

if (!function_exists('enma_products_normalize_ai_item')) {
    function enma_products_normalize_ai_item(array $raw): ?array
    {
        $asin = strtoupper(trim((string) ($raw['asin'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return null;
        }

        $title = trim((string) ($raw['title'] ?? $raw['name'] ?? $raw['nombre'] ?? ''));
        if ($title === '') {
            return null;
        }

        $categorySlug = enma_products_normalize_ai_category((string) ($raw['category'] ?? $raw['categoria'] ?? $raw['type'] ?? ''));
        $categoryName = $categorySlug === 'telescopes' ? 'Telescopes' : 'Accessories';

        $description = trim((string) ($raw['description'] ?? $raw['descripcion'] ?? $raw['focus'] ?? ''));
        if ($description === '') {
            $description = 'Curated catalog product suggested by AI assistant.';
        }

        $imageUrl = trim((string) ($raw['image'] ?? $raw['imagen'] ?? $raw['image_url'] ?? $raw['img'] ?? ''));
        if ($imageUrl !== '' && filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
            $imageUrl = '';
        }

        $affiliateUrl = trim((string) ($raw['url'] ?? $raw['affiliate_url'] ?? ''));
        if ($affiliateUrl === '') {
            $affiliateUrl = 'https://www.amazon.com/dp/' . $asin;
        }
        if (filter_var($affiliateUrl, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        $affiliateUrl = amazon_affiliate_url($affiliateUrl);

        return [
            'asin' => $asin,
            'title' => $title,
            'description' => mb_substr($description, 0, 500),
            'category_slug' => $categorySlug,
            'category_name' => $categoryName,
            'image_url' => $imageUrl,
            'affiliate_url' => $affiliateUrl,
        ];
    }
}

$productsAiImportForm = ['payload' => ''];
$productsAiImportResult = null;

// Import NEW products from pasted AI array (append-only, no archive/delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'import_products_ai') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $productsAiImportForm['payload'] = trim((string) ($_POST['products_ai_payload'] ?? ''));
    if ($productsAiImportForm['payload'] === '') {
        $errors[] = 'Paste a PHP $products array first.';
    }

    if ($errors === []) {
        try {
            $rows = enma_products_parse_ai_payload($productsAiImportForm['payload']);
            $seenAsins = [];
            $normalizedRows = [];
            $skippedInvalid = 0;
            $skippedDuplicatePayload = 0;

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    $skippedInvalid++;
                    continue;
                }
                $normalized = enma_products_normalize_ai_item($row);
                if ($normalized === null) {
                    $skippedInvalid++;
                    continue;
                }
                $asin = $normalized['asin'];
                if (isset($seenAsins[$asin])) {
                    $skippedDuplicatePayload++;
                    continue;
                }
                $seenAsins[$asin] = true;
                $normalizedRows[] = $normalized;
            }

            if ($normalizedRows === []) {
                throw new RuntimeException('No valid products found in pasted payload.');
            }

            $selectStmt = $pdo->prepare('SELECT id FROM products WHERE asin = :asin LIMIT 1');
            $insertStmt = $pdo->prepare(
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

            $now = now_iso();
            $inserted = 0;
            $skippedExisting = 0;

            foreach ($normalizedRows as $item) {
                $selectStmt->execute([':asin' => $item['asin']]);
                $existingId = (int) $selectStmt->fetchColumn();
                if ($existingId > 0) {
                    $skippedExisting++;
                    continue;
                }

                $insertStmt->execute([
                    ':asin' => $item['asin'],
                    ':slug' => unique_slug($pdo, $item['title']),
                    ':title' => $item['title'],
                    ':description' => $item['description'],
                    ':category_slug' => $item['category_slug'],
                    ':category_name' => $item['category_name'],
                    ':image_url' => $item['image_url'],
                    ':affiliate_url' => $item['affiliate_url'],
                    ':status' => 'published',
                    ':last_synced_at' => $now,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                $newProductId = (int) $pdo->lastInsertId();
                enma_record_activity($pdo, 'product.create.ai_import', 'product', $newProductId, [
                    'asin' => $item['asin'],
                    'title' => $item['title'],
                    'category_name' => $item['category_name'],
                ]);

                $inserted++;
            }

            $productsAiImportResult = [
                'ok' => true,
                'inserted' => $inserted,
                'skipped_existing' => $skippedExisting,
                'skipped_invalid' => $skippedInvalid,
                'skipped_duplicate_payload' => $skippedDuplicatePayload,
            ];
            $flash = 'AI import completed. Inserted: ' . $inserted . ' | Existing skipped: ' . $skippedExisting;
            $productsAiImportForm['payload'] = '';
        } catch (Throwable $e) {
            $productsAiImportResult = [
                'ok' => false,
                'message' => $e->getMessage(),
            ];
            $errors[] = 'AI import failed: ' . $e->getMessage();
        }
    }
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
