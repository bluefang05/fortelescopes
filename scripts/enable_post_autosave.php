<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (DB_DRIVER !== 'mysql') {
    throw new RuntimeException('This script only supports mysql.');
}

$existsStmt = $pdo->prepare(
    'SELECT 1
     FROM information_schema.tables
     WHERE table_schema = :schema
       AND table_name = :table_name
     LIMIT 1'
);
$existsStmt->execute([
    ':schema' => DB_NAME,
    ':table_name' => 'post_autosaves',
]);
$alreadyExists = (bool) $existsStmt->fetchColumn();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS post_autosaves (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        post_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        editor_user_id INT UNSIGNED NOT NULL DEFAULT 0,
        draft_key VARCHAR(64) NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT \'\',
        excerpt TEXT NULL,
        meta_title VARCHAR(255) NULL,
        meta_description TEXT NULL,
        content_html MEDIUMTEXT NULL,
        saved_at VARCHAR(40) NOT NULL,
        UNIQUE KEY uq_post_autosave_slot (post_id, editor_user_id, draft_key),
        KEY idx_post_autosave_saved (saved_at),
        KEY idx_post_autosave_post (post_id, editor_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

echo $alreadyExists
    ? 'Post autosave schema already present.'
    : 'Post autosave schema created (table: post_autosaves).';
