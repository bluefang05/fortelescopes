<?php

declare(strict_types=1);

/**
 * Media handler for ENMA admin panel
 * Handles media library schema + upload/delete actions.
 */

if (!$authenticated) {
    return;
}

if (!function_exists('enma_media_init_table')) {
    function enma_media_init_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS media_library (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                media_type VARCHAR(20) NOT NULL DEFAULT "image",
                mime_type VARCHAR(120) NOT NULL DEFAULT "",
                file_ext VARCHAR(12) NOT NULL DEFAULT "",
                original_name VARCHAR(255) NOT NULL DEFAULT "",
                stored_name VARCHAR(255) NOT NULL DEFAULT "",
                file_url TEXT NOT NULL,
                file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
                title VARCHAR(255) NOT NULL DEFAULT "",
                alt_text VARCHAR(255) NOT NULL DEFAULT "",
                notes TEXT NULL,
                status VARCHAR(20) NOT NULL DEFAULT "active",
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                INDEX idx_media_type (media_type),
                INDEX idx_media_status (status),
                INDEX idx_media_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('enma_media_table_exists')) {
    function enma_media_table_exists(PDO $pdo): bool
    {
        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = :schema
               AND table_name = "media_library"
             LIMIT 1'
        );
        $stmt->execute([':schema' => DB_NAME]);
        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('enma_media_allowed_mimes')) {
    function enma_media_allowed_mimes(): array
    {
        return [
            'image/jpeg' => ['ext' => 'jpg', 'type' => 'image'],
            'image/png' => ['ext' => 'png', 'type' => 'image'],
            'image/webp' => ['ext' => 'webp', 'type' => 'image'],
            'image/gif' => ['ext' => 'gif', 'type' => 'image'],
            'image/svg+xml' => ['ext' => 'svg', 'type' => 'image'],
            'video/mp4' => ['ext' => 'mp4', 'type' => 'video'],
            'video/webm' => ['ext' => 'webm', 'type' => 'video'],
            'video/quicktime' => ['ext' => 'mov', 'type' => 'video'],
            'application/pdf' => ['ext' => 'pdf', 'type' => 'document'],
            'text/plain' => ['ext' => 'txt', 'type' => 'document'],
            'application/zip' => ['ext' => 'zip', 'type' => 'document'],
        ];
    }
}

if (!function_exists('enma_media_save_upload')) {
    function enma_media_save_upload(string $fieldName, array &$errors): ?array
    {
        if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
            $errors[] = 'Select a file first.';
            return null;
        }

        $file = $_FILES[$fieldName];
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'No file uploaded.';
            return null;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Upload failed with error code: ' . $error;
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > (25 * 1024 * 1024)) {
            $errors[] = 'File must be between 1 byte and 25MB.';
            return null;
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            $errors[] = 'Invalid uploaded file.';
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpPath) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = enma_media_allowed_mimes();
        if (!isset($allowed[$mime])) {
            $errors[] = 'Unsupported file type: ' . ($mime !== '' ? $mime : 'unknown');
            return null;
        }

        $meta = $allowed[$mime];
        $ext = (string) ($meta['ext'] ?? 'bin');
        $mediaType = (string) ($meta['type'] ?? 'document');

        $uploadDir = __DIR__ . '/../assets/uploads/media';
        if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $errors[] = 'Could not create media upload directory.';
            return null;
        }

        $storedName = 'm_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $uploadDir . '/' . $storedName;
        if (!move_uploaded_file($tmpPath, $target)) {
            $errors[] = 'Could not save uploaded file.';
            return null;
        }

        return [
            'media_type' => $mediaType,
            'mime_type' => $mime,
            'file_ext' => $ext,
            'original_name' => trim((string) ($file['name'] ?? '')),
            'stored_name' => $storedName,
            'file_url' => absolute_url('/assets/uploads/media/' . $storedName),
            'file_size' => $size,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'media_upload') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        try {
            enma_media_init_table($pdo);
        } catch (Throwable $e) {
            $errors[] = 'Media table is unavailable: ' . $e->getMessage();
        }
    }

    if ($errors === []) {
        $saved = enma_media_save_upload('media_file', $errors);
        if ($saved !== null) {
            $title = trim((string) ($_POST['media_title'] ?? ''));
            $altText = trim((string) ($_POST['media_alt_text'] ?? ''));
            $notes = trim((string) ($_POST['media_notes'] ?? ''));
            $now = now_iso();

            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO media_library (
                        media_type, mime_type, file_ext, original_name, stored_name, file_url, file_size,
                        title, alt_text, notes, status, created_at, updated_at
                     ) VALUES (
                        :media_type, :mime_type, :file_ext, :original_name, :stored_name, :file_url, :file_size,
                        :title, :alt_text, :notes, :status, :created_at, :updated_at
                     )'
                );
                $stmt->execute([
                    ':media_type' => (string) ($saved['media_type'] ?? 'document'),
                    ':mime_type' => (string) ($saved['mime_type'] ?? ''),
                    ':file_ext' => (string) ($saved['file_ext'] ?? ''),
                    ':original_name' => (string) ($saved['original_name'] ?? ''),
                    ':stored_name' => (string) ($saved['stored_name'] ?? ''),
                    ':file_url' => (string) ($saved['file_url'] ?? ''),
                    ':file_size' => (int) ($saved['file_size'] ?? 0),
                    ':title' => $title,
                    ':alt_text' => $altText,
                    ':notes' => $notes === '' ? null : $notes,
                    ':status' => 'active',
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);

                $mediaId = (int) $pdo->lastInsertId();
                enma_record_activity($pdo, 'media.upload', 'media', $mediaId, [
                    'original_name' => (string) ($saved['original_name'] ?? ''),
                    'file_url' => (string) ($saved['file_url'] ?? ''),
                    'media_type' => (string) ($saved['media_type'] ?? ''),
                ]);
                $flash = 'Media uploaded successfully.';
            } catch (Throwable $e) {
                $errors[] = 'Media insert failed: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'media_delete') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $errors[] = 'Invalid media id.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT file_url, stored_name FROM media_library WHERE id = :id LIMIT 1');
                $stmt->execute([':id' => $id]);
                $row = $stmt->fetch();

                $deleteStmt = $pdo->prepare('DELETE FROM media_library WHERE id = :id');
                $deleteStmt->execute([':id' => $id]);

                if (is_array($row)) {
                    $storedName = trim((string) ($row['stored_name'] ?? ''));
                    if ($storedName !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $storedName) === 1) {
                        $path = __DIR__ . '/../assets/uploads/media/' . $storedName;
                        if (is_file($path)) {
                            @unlink($path);
                        }
                    }
                }

                enma_record_activity($pdo, 'media.delete', 'media', $id, []);
                $flash = 'Media item deleted.';
            } catch (Throwable $e) {
                $errors[] = 'Media delete failed: ' . $e->getMessage();
            }
        }
    }
}
