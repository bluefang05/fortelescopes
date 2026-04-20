<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=UTF-8');

$checks = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'json' => extension_loaded('json'),
    'env_file' => is_file(__DIR__ . DIRECTORY_SEPARATOR . '.env'),
];

$dbOk = false;
try {
    $pdo = db();
    $pdo->query('SELECT 1');
    $dbOk = true;
} catch (Throwable $e) {
    error_log('[Fortelescopes healthcheck] ' . $e->getMessage());
}

$allOk = $dbOk && !in_array(false, $checks, true);
http_response_code($allOk ? 200 : 503);

echo "status: " . ($allOk ? 'ok' : 'fail') . "\n";
echo "app_env: " . APP_ENV . "\n";
echo "db: " . ($dbOk ? 'ok' : 'fail') . "\n";
foreach ($checks as $key => $value) {
    echo $key . ': ' . ($value ? 'ok' : 'fail') . "\n";
}
