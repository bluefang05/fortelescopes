<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $pdo = db();
} catch (Throwable $e) {
    error_log('[Fortelescopes database.php] ' . $e->getMessage());
    http_response_code(500);
    echo APP_DEBUG
        ? '<h3>Database connection error</h3><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<h3>Database connection error</h3>';
    exit;
}
