<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$exportPath = __DIR__ . '/../products_export.sql';
$generatedAt = gmdate('Y-m-d H:i:s');

$rows = $pdo->query(
    'SELECT
        asin, slug, title, description, category_slug, category_name,
        price_amount, price_currency, image_url, affiliate_url, status,
        last_synced_at, created_at, updated_at
     FROM products
     ORDER BY id ASC'
)->fetchAll();

$sql = "-- Fortelescopes Products Export\n";
$sql .= "-- Generated on {$generatedAt} UTC\n\n";
$sql .= "SET NAMES utf8mb4;\n\n";

if ($rows === []) {
    $sql .= "-- No product rows found.\n";
} else {
    $columns = [
        'asin', 'slug', 'title', 'description', 'category_slug', 'category_name',
        'price_amount', 'price_currency', 'image_url', 'affiliate_url', 'status',
        'last_synced_at', 'created_at', 'updated_at',
    ];

    $sql .= "INSERT INTO `products` (`" . implode('`, `', $columns) . "`) VALUES\n";
    $valueLines = [];

    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            if ($value === null || $value === '') {
                $values[] = 'NULL';
                continue;
            }

            if ($column === 'price_amount' && is_numeric((string) $value)) {
                $values[] = number_format((float) $value, 2, '.', '');
                continue;
            }

            $values[] = $pdo->quote((string) $value);
        }
        $valueLines[] = '    (' . implode(', ', $values) . ')';
    }

    $sql .= implode(",\n", $valueLines) . "\n";
    $sql .= "ON DUPLICATE KEY UPDATE\n";
    $sql .= "    `title` = VALUES(`title`),\n";
    $sql .= "    `description` = VALUES(`description`),\n";
    $sql .= "    `category_slug` = VALUES(`category_slug`),\n";
    $sql .= "    `category_name` = VALUES(`category_name`),\n";
    $sql .= "    `price_amount` = VALUES(`price_amount`),\n";
    $sql .= "    `price_currency` = VALUES(`price_currency`),\n";
    $sql .= "    `image_url` = VALUES(`image_url`),\n";
    $sql .= "    `affiliate_url` = VALUES(`affiliate_url`),\n";
    $sql .= "    `status` = VALUES(`status`),\n";
    $sql .= "    `last_synced_at` = VALUES(`last_synced_at`),\n";
    $sql .= "    `created_at` = VALUES(`created_at`),\n";
    $sql .= "    `updated_at` = VALUES(`updated_at`);\n";
}

$bytesWritten = file_put_contents($exportPath, $sql);

if ($bytesWritten === false) {
    throw new RuntimeException('Could not write products_export.sql');
}

$lines = [
    'Products exported: ' . count($rows),
    'Location: ' . realpath($exportPath),
];
maintenance_prune_files('logs', 'export-products-sql_*.log', 30);
$logPath = maintenance_append_log('export-products-sql', $lines);
$lines[] = 'Log: ' . $logPath;
echo implode(PHP_EOL, $lines) . PHP_EOL;
