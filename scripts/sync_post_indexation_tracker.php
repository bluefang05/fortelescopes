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

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS post_indexation_tracker (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT UNSIGNED NOT NULL,
        slug VARCHAR(191) NOT NULL,
        post_type VARCHAR(20) NOT NULL DEFAULT "post",
        post_status VARCHAR(20) NOT NULL DEFAULT "draft",
        canonical_url VARCHAR(255) NOT NULL DEFAULT "",
        index_state VARCHAR(20) NOT NULL DEFAULT "pending",
        is_indexed TINYINT(1) NOT NULL DEFAULT 0,
        check_priority TINYINT UNSIGNED NOT NULL DEFAULT 1,
        notes VARCHAR(500) NOT NULL DEFAULT "",
        last_checked_at VARCHAR(40) NULL DEFAULT NULL,
        next_check_at VARCHAR(40) NULL DEFAULT NULL,
        created_at VARCHAR(40) NOT NULL,
        updated_at VARCHAR(40) NOT NULL,
        UNIQUE KEY uniq_post_id (post_id),
        INDEX idx_state (index_state),
        INDEX idx_is_indexed (is_indexed),
        INDEX idx_post_type (post_type),
        INDEX idx_post_status (post_status),
        INDEX idx_next_check (next_check_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$postRows = $pdo->query(
    'SELECT id, slug, post_type, status
     FROM posts
     WHERE TRIM(COALESCE(slug, "")) <> ""
       AND post_type IN ("post", "guide")'
)->fetchAll();

$existingIds = $pdo->query('SELECT post_id FROM post_indexation_tracker')->fetchAll(PDO::FETCH_COLUMN);
$existingMap = [];
foreach ($existingIds as $existingId) {
    $existingMap[(int) $existingId] = true;
}

$upsert = $pdo->prepare(
    'INSERT INTO post_indexation_tracker (
        post_id, slug, post_type, post_status, canonical_url,
        index_state, is_indexed, check_priority, notes, created_at, updated_at
     ) VALUES (
        :post_id, :slug, :post_type, :post_status, :canonical_url,
        :index_state, :is_indexed, 1, "", :created_at, :updated_at
     )
     ON DUPLICATE KEY UPDATE
        slug = VALUES(slug),
        post_type = VALUES(post_type),
        post_status = VALUES(post_status),
        canonical_url = VALUES(canonical_url),
        index_state = CASE
            WHEN post_indexation_tracker.is_indexed = 1 THEN "indexed"
            WHEN VALUES(post_status) <> "published" THEN "excluded"
            WHEN post_indexation_tracker.index_state = "excluded" THEN "pending"
            ELSE post_indexation_tracker.index_state
        END,
        is_indexed = CASE
            WHEN post_indexation_tracker.index_state = "indexed" OR post_indexation_tracker.is_indexed = 1 THEN 1
            WHEN VALUES(post_status) <> "published" THEN 0
            ELSE post_indexation_tracker.is_indexed
        END,
        updated_at = VALUES(updated_at)'
);

$processed = 0;
$added = 0;
$seenIds = [];
$now = now_iso();
foreach ($postRows as $row) {
    $postId = (int) ($row['id'] ?? 0);
    if ($postId <= 0) {
        continue;
    }
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        continue;
    }
    $postType = trim((string) ($row['post_type'] ?? 'post'));
    $postStatus = trim((string) ($row['status'] ?? 'draft'));
    $path = $postType === 'guide' ? '/' . ltrim($slug, '/') : '/blog/' . ltrim($slug, '/');
    $canonicalUrl = absolute_url($path);
    $defaultState = $postStatus === 'published' ? 'pending' : 'excluded';
    $defaultIndexed = $postStatus === 'published' ? 0 : 0;

    $upsert->execute([
        ':post_id' => $postId,
        ':slug' => $slug,
        ':post_type' => $postType,
        ':post_status' => $postStatus,
        ':canonical_url' => $canonicalUrl,
        ':index_state' => $defaultState,
        ':is_indexed' => $defaultIndexed,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $processed++;
    if (!isset($existingMap[$postId])) {
        $added++;
    }
    $seenIds[$postId] = true;
}

$staleIds = [];
foreach ($existingMap as $postId => $_) {
    if (!isset($seenIds[$postId])) {
        $staleIds[] = (int) $postId;
    }
}

$removed = 0;
if ($staleIds !== []) {
    $chunks = array_chunk($staleIds, 200);
    foreach ($chunks as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $stmt = $pdo->prepare('DELETE FROM post_indexation_tracker WHERE post_id IN (' . $placeholders . ')');
        $stmt->execute($chunk);
        $removed += (int) $stmt->rowCount();
    }
}

$outputLines = [
    'Tracker ready: 1',
    'Posts processed: ' . $processed,
    'Rows added: ' . $added,
    'Rows updated: ' . max(0, $processed - $added),
    'Rows removed: ' . $removed,
];

maintenance_prune_files('logs', 'sync-post-indexation-tracker_*.log', 30);
$logPath = maintenance_append_log('sync-post-indexation-tracker', $outputLines);
$outputLines[] = 'Log: ' . $logPath;

echo implode(PHP_EOL, $outputLines) . PHP_EOL;
