<?php

declare(strict_types=1);

if (!$authenticated) {
    return;
}

if (!function_exists('enma_operator_init_table')) {
    function enma_operator_init_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS enma_operator_reminders (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(180) NOT NULL,
                details VARCHAR(500) NOT NULL DEFAULT "",
                priority VARCHAR(12) NOT NULL DEFAULT "medium",
                status VARCHAR(12) NOT NULL DEFAULT "open",
                due_date DATE NULL,
                last_done_at VARCHAR(40) NULL DEFAULT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                INDEX idx_status_priority (status, priority),
                INDEX idx_due_date (due_date),
                INDEX idx_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('enma_operator_seed_defaults')) {
    function enma_operator_seed_defaults(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM enma_operator_reminders')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $defaults = [
            ['Revisar indexacion de URLs nuevas', 'Confirmar estado de URLs publicadas en los ultimos 7 dias.', 'high', null],
            ['Actualizar 1 post con bajo CTR', 'Tomar una URL con trafico y bajo click-out para mejorar CTA/tablas.', 'high', null],
            ['Publicar 1 post money intent', 'Priorizar comparativas y best-for queries.', 'high', null],
            ['Revisar productos sin imagen/tag', 'Corregir missing images y affiliate tags.', 'medium', null],
            ['Generar y revisar sitemap', 'Verificar que el sitemap incluya URLs publicas nuevas.', 'medium', null],
        ];

        $stmt = $pdo->prepare(
            'INSERT INTO enma_operator_reminders (
                title, details, priority, status, due_date, created_at, updated_at
             ) VALUES (
                :title, :details, :priority, "open", :due_date, :created_at, :updated_at
             )'
        );
        $now = now_iso();
        foreach ($defaults as $row) {
            $stmt->execute([
                ':title' => (string) $row[0],
                ':details' => (string) $row[1],
                ':priority' => (string) $row[2],
                ':due_date' => $row[3],
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
        }
    }
}

if (!function_exists('enma_operator_priority_rank')) {
    function enma_operator_priority_rank(string $priority): int
    {
        $priority = strtolower(trim($priority));
        if ($priority === 'high') {
            return 3;
        }
        if ($priority === 'low') {
            return 1;
        }
        return 2;
    }
}

try {
    enma_operator_init_table($pdo);
    enma_operator_seed_defaults($pdo);
    if (function_exists('enma_indexation_init_table')) {
        enma_indexation_init_table($pdo);
    }
} catch (Throwable $e) {
    $errors[] = 'Operator workspace DB init failed: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'operator_add_reminder') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $title = trim((string) ($_POST['title'] ?? ''));
        $details = trim((string) ($_POST['details'] ?? ''));
        $priority = strtolower(trim((string) ($_POST['priority'] ?? 'medium')));
        $dueDate = trim((string) ($_POST['due_date'] ?? ''));
        if (!in_array($priority, ['high', 'medium', 'low'], true)) {
            $priority = 'medium';
        }
        if ($title === '') {
            $errors[] = 'Reminder title is required.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO enma_operator_reminders (title, details, priority, status, due_date, created_at, updated_at)
                     VALUES (:title, :details, :priority, "open", :due_date, :created_at, :updated_at)'
                );
                $stmt->execute([
                    ':title' => mb_substr($title, 0, 180),
                    ':details' => mb_substr($details, 0, 500),
                    ':priority' => $priority,
                    ':due_date' => $dueDate !== '' ? $dueDate : null,
                    ':created_at' => now_iso(),
                    ':updated_at' => now_iso(),
                ]);
                $flash = 'Reminder added.';
            } catch (Throwable $e) {
                $errors[] = 'Could not add reminder: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'operator_toggle_reminder') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid reminder id.';
        } else {
            try {
                $rowStmt = $pdo->prepare('SELECT status FROM enma_operator_reminders WHERE id = :id LIMIT 1');
                $rowStmt->execute([':id' => $id]);
                $row = $rowStmt->fetch();
                if (!is_array($row)) {
                    $errors[] = 'Reminder not found.';
                } else {
                    $isDone = strtolower((string) ($row['status'] ?? 'open')) === 'done';
                    $nextStatus = $isDone ? 'open' : 'done';
                    $stmt = $pdo->prepare(
                        'UPDATE enma_operator_reminders
                         SET status = :status,
                             last_done_at = :last_done_at,
                             updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':status' => $nextStatus,
                        ':last_done_at' => $nextStatus === 'done' ? now_iso() : null,
                        ':updated_at' => now_iso(),
                        ':id' => $id,
                    ]);
                    $flash = $nextStatus === 'done' ? 'Reminder completed.' : 'Reminder reopened.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Could not update reminder: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'operator_delete_reminder') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid reminder id.';
        } else {
            try {
                $stmt = $pdo->prepare('DELETE FROM enma_operator_reminders WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $flash = 'Reminder deleted.';
            } catch (Throwable $e) {
                $errors[] = 'Could not delete reminder: ' . $e->getMessage();
            }
        }
    }
}

$operatorRemindersOpen = [];
$operatorRemindersDone = [];
$operatorReminderStats = [
    'open' => 0,
    'done' => 0,
    'high_open' => 0,
];
$operatorMoneyStats = [
    'posts_published' => 0,
    'posts_draft' => 0,
    'products_missing_tags' => 0,
    'products_missing_images' => 0,
    'index_pending' => 0,
    'index_not_indexed' => 0,
];

if ($activeTab === 'control' || $activeTab === 'prompts') {
    try {
        $operatorReminderStats = [
            'open' => (int) $pdo->query('SELECT COUNT(*) FROM enma_operator_reminders WHERE status = "open"')->fetchColumn(),
            'done' => (int) $pdo->query('SELECT COUNT(*) FROM enma_operator_reminders WHERE status = "done"')->fetchColumn(),
            'high_open' => (int) $pdo->query('SELECT COUNT(*) FROM enma_operator_reminders WHERE status = "open" AND priority = "high"')->fetchColumn(),
        ];

        $openRows = $pdo->query(
            'SELECT id, title, details, priority, status, due_date, last_done_at, updated_at
             FROM enma_operator_reminders
             WHERE status = "open"
             ORDER BY
               CASE priority WHEN "high" THEN 3 WHEN "medium" THEN 2 ELSE 1 END DESC,
               (due_date IS NULL) ASC,
               due_date ASC,
               id DESC
             LIMIT 50'
        )->fetchAll();
        $doneRows = $pdo->query(
            'SELECT id, title, details, priority, status, due_date, last_done_at, updated_at
             FROM enma_operator_reminders
             WHERE status = "done"
             ORDER BY updated_at DESC, id DESC
             LIMIT 20'
        )->fetchAll();
        $operatorRemindersOpen = is_array($openRows) ? $openRows : [];
        $operatorRemindersDone = is_array($doneRows) ? $doneRows : [];

        $operatorMoneyStats = [
            'posts_published' => (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE status = "published"')->fetchColumn(),
            'posts_draft' => (int) $pdo->query('SELECT COUNT(*) FROM posts WHERE status = "draft"')->fetchColumn(),
            'products_missing_tags' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE affiliate_url NOT LIKE '%tag=%'")->fetchColumn(),
            'products_missing_images' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE image_url IS NULL OR image_url = ''")->fetchColumn(),
            'index_pending' => (int) $pdo->query('SELECT COUNT(*) FROM post_indexation_tracker WHERE index_state = "pending"')->fetchColumn(),
            'index_not_indexed' => (int) $pdo->query('SELECT COUNT(*) FROM post_indexation_tracker WHERE index_state = "not_indexed"')->fetchColumn(),
        ];
    } catch (Throwable $e) {
        $errors[] = 'Operator workspace metrics unavailable: ' . $e->getMessage();
    }
}
