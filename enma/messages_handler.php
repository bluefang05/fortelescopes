<?php

declare(strict_types=1);

if (!function_exists('enma_messages_init_table')) {
    function enma_messages_init_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS contact_messages (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                subject VARCHAR(190) NOT NULL,
                message_text TEXT NOT NULL,
                status ENUM("new","read","archived") NOT NULL DEFAULT "new",
                source_path VARCHAR(255) NOT NULL DEFAULT "/contact",
                ip_address VARCHAR(64) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                admin_notes TEXT DEFAULT NULL,
                read_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_contact_messages_status_created (status, created_at),
                KEY idx_contact_messages_email_created (email, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

enma_messages_init_table($pdo);

$messagesPage = $authenticated ? enma_page_value('messages_page') : 1;
$messagesPerPage = 25;
$messagesTotal = 0;
$messagesTotalPages = 1;
$messagesRows = [];
$messageStatusFilter = $authenticated ? strtolower(trim((string) ($_GET['msg_status'] ?? 'all'))) : 'all';
$messageSearch = $authenticated ? trim((string) ($_GET['msg_q'] ?? '')) : '';
$messageStatusOptions = ['all', 'new', 'read', 'archived'];
if (!in_array($messageStatusFilter, $messageStatusOptions, true)) {
    $messageStatusFilter = 'all';
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'messages_update_status') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token for message update.';
    } else {
        $messageId = (int) ($_POST['message_id'] ?? 0);
        $newStatus = strtolower(trim((string) ($_POST['message_status'] ?? '')));
        if ($messageId <= 0 || !in_array($newStatus, ['new', 'read', 'archived'], true)) {
            $errors[] = 'Invalid message update payload.';
        } else {
            $now = gmdate('Y-m-d H:i:s');
            $readAt = $newStatus === 'read' ? $now : null;
            $stmt = $pdo->prepare(
                'UPDATE contact_messages
                 SET status = :status,
                     read_at = CASE WHEN :read_at IS NOT NULL THEN :read_at ELSE read_at END,
                     updated_at = :updated_at
                 WHERE id = :id
                 LIMIT 1'
            );
            $stmt->execute([
                ':status' => $newStatus,
                ':read_at' => $readAt,
                ':updated_at' => $now,
                ':id' => $messageId,
            ]);
            $_SESSION['flash'] = 'Message status updated.';
            header('Location: ' . url('/enma/?tab=messages'));
            exit;
        }
    }
}

if ($authenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'messages_delete') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid CSRF token for message delete.';
    } else {
        $messageId = (int) ($_POST['message_id'] ?? 0);
        if ($messageId <= 0) {
            $errors[] = 'Invalid message id for delete.';
        } else {
            $stmt = $pdo->prepare('DELETE FROM contact_messages WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $messageId]);
            $_SESSION['flash'] = 'Message deleted.';
            header('Location: ' . url('/enma/?tab=messages'));
            exit;
        }
    }
}

if ($authenticated && $activeTab === 'messages') {
    $where = [];
    $params = [];
    if ($messageStatusFilter !== 'all') {
        $where[] = 'status = :status';
        $params[':status'] = $messageStatusFilter;
    }
    if ($messageSearch !== '') {
        $where[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message_text LIKE :q)';
        $params[':q'] = '%' . $messageSearch . '%';
    }
    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages' . $whereSql);
    $countStmt->execute($params);
    $messagesTotal = (int) $countStmt->fetchColumn();
    $messagesTotalPages = enma_total_pages($messagesTotal, $messagesPerPage);
    $messagesPage = min($messagesPage, $messagesTotalPages);

    $sql = 'SELECT id, name, email, subject, message_text, status, source_path, created_at, read_at
            FROM contact_messages'
        . $whereSql
        . ' ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $messagesPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($messagesPage - 1) * $messagesPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $messagesRows = $stmt->fetchAll() ?: [];
}
