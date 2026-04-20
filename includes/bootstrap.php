<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('[Fortelescopes bootstrap] ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo APP_DEBUG
        ? "Application startup error.\nDetails: " . $e->getMessage() . "\n"
        : "Application startup error.\n";
    exit(1);
}
