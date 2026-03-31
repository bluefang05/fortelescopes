<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

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

echo 'Checked: ' . $checked . PHP_EOL;
echo 'Updated: ' . $updated . PHP_EOL;
echo 'Tag: ' . AMAZON_ASSOCIATE_TAG . PHP_EOL;
