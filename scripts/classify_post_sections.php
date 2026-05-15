<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && !defined('ENMA_ALLOW_WEB_RUN')) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$rows = $pdo->query(
    'SELECT id, slug, title, excerpt, post_type, extra_data
     FROM posts
     WHERE post_type = "post"'
)->fetchAll();

$updated = 0;
$checked = 0;
$now = now_iso();

$updateStmt = $pdo->prepare(
    'UPDATE posts
     SET extra_data = :extra_data, updated_at = :updated_at
     WHERE id = :id'
);

foreach ($rows as $row) {
    $checked++;
    $extra = json_decode((string) ($row['extra_data'] ?? ''), true);
    if (!is_array($extra)) {
        $extra = [];
    }

    $existing = strtolower(trim((string) ($extra['section'] ?? '')));
    if ($existing === 'review') {
        $existing = 'reviews';
    }

    $section = in_array($existing, ['blog', 'reviews', 'learn'], true)
        ? $existing
        : post_section(format_post_row($row));

    if (($extra['section'] ?? null) === $section) {
        continue;
    }

    $extra['section'] = $section;
    $extraJson = json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($extraJson) || $extraJson === '') {
        continue;
    }

    $updateStmt->execute([
        ':extra_data' => $extraJson,
        ':updated_at' => $now,
        ':id' => (int) ($row['id'] ?? 0),
    ]);
    $updated++;
}

$output = [
    'Post section classification done.',
    'Checked: ' . $checked,
    'Updated: ' . $updated,
];

maintenance_prune_files('logs', 'classify-post-sections_*.log', 30);
$logPath = maintenance_append_log('classify-post-sections', $output);
$output[] = 'Log: ' . $logPath;

echo implode(PHP_EOL, $output) . PHP_EOL;

