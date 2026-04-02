<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

init_schema($pdo);

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$stmt = $pdo->prepare(
    'SELECT table_name
     FROM information_schema.tables
     WHERE table_schema = :schema
       AND table_name IN (\'products\', \'page_views\', \'page_view_hits\', \'posts\')
     ORDER BY table_name'
);

$stmt->execute([':schema' => DB_NAME]);

$tables = array_map(static function (array $row): string {
    return (string) $row['table_name'];
}, $stmt->fetchAll());

echo 'DB_DRIVER: ' . DB_DRIVER . PHP_EOL;
echo 'Schema update: OK' . PHP_EOL;
echo 'Tables: ' . implode(', ', $tables) . PHP_EOL;