<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

try {
    $pdo = db();
    init_schema($pdo);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Application startup error.\n";
    echo "Details: " . $e->getMessage() . "\n";
    exit(1);
}
