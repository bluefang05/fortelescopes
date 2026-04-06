<?php

declare(strict_types=1);

/**
 * Helper functions for ENMA admin panel
 */

/**
 * Handle image upload for posts and products
 */
function enma_handle_image_upload(string $fieldName, array &$errors, string $subDir = 'products'): ?string
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return null;
    }

    $file = $_FILES[$fieldName];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        $msg = match($error) {
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.'
        };
        $errors[] = 'Image upload failed: ' . $msg;
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 10 * 1024 * 1024) {
        $errors[] = 'Image must be between 1 byte and 10MB.';
        return null;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $errors[] = 'Invalid uploaded image.';
        return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? (string) finfo_file($finfo, $tmp) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($map[$mime])) {
        $errors[] = 'Only JPG, PNG, WEBP, or GIF are allowed.';
        return null;
    }

    $uploadDir = __DIR__ . '/../assets/uploads/' . $subDir;
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Could not create upload directory.';
        return null;
    }

    $prefix = $subDir === 'posts' ? 'post_' : 'p_';
    $baseName = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    $webpRelative = '/assets/uploads/' . $subDir . '/' . $baseName . '.webp';
    $webpTarget = $uploadDir . '/' . $baseName . '.webp';

    if (enma_convert_uploaded_image_to_webp($tmp, $mime, $webpTarget)) {
        return absolute_url($webpRelative);
    }

    $ext = $map[$mime];
    $name = $baseName . '.' . $ext;
    $target = $uploadDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        $errors[] = 'Could not move uploaded image.';
        return null;
    }

    return absolute_url('/assets/uploads/' . $subDir . '/' . $name);
}

function enma_convert_uploaded_image_to_webp(string $tmpPath, string $mime, string $targetPath): bool
{
    if (class_exists('Imagick')) {
        try {
            $imagick = new Imagick($tmpPath);
            if ($imagick->getNumberImages() > 1) {
                $imagick = $imagick->coalesceImages();
            }
            $imagick->setImageFormat('webp');
            $imagick->setImageCompressionQuality(82);
            $result = $imagick->writeImages($targetPath, true);
            $imagick->clear();
            $imagick->destroy();
            return (bool) $result;
        } catch (Throwable $e) {
            return false;
        }
    }

    if (!extension_loaded('gd') || !function_exists('imagewebp')) {
        return false;
    }

    $image = match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($tmpPath) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($tmpPath) : false,
        'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($tmpPath) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false,
        default => false,
    };

    if (!$image) {
        return false;
    }

    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $result = @imagewebp($image, $targetPath, 82);
    imagedestroy($image);

    return (bool) $result;
}

/**
 * Normalize HTML content from editor
 */
function enma_normalize_editor_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    // Some editors/paste flows can double-encode HTML. Decode a few passes
    // until tags become real markup or the content stops changing.
    $decoded = $html;
    for ($i = 0; $i < 3; $i++) {
        if (strpos($decoded, '&lt;') === false && strpos($decoded, '&gt;') === false) {
            break;
        }
        $next = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }

    if ($decoded !== '' && strpos($decoded, '<') !== false) {
        return trim($decoded);
    }

    return $html;
}

function enma_admin_init_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            username VARCHAR(100) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            display_name VARCHAR(100) NULL,
            avatar_url TEXT NULL,
            role VARCHAR(20) NOT NULL DEFAULT \'user\',
            status VARCHAR(20) NOT NULL DEFAULT \'active\',
            last_login_at VARCHAR(40) NULL,
            created_at VARCHAR(40) NOT NULL,
            updated_at VARCHAR(40) NOT NULL,
            UNIQUE KEY uq_users_email (email),
            UNIQUE KEY uq_users_username (username),
            KEY idx_users_email (email),
            KEY idx_users_username (username),
            KEY idx_users_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admin_activity_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            admin_user_id INT UNSIGNED NULL,
            admin_username VARCHAR(100) NOT NULL DEFAULT \'\',
            action_key VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NOT NULL DEFAULT \'\',
            entity_id BIGINT NULL,
            details_json LONGTEXT NULL,
            ip_address VARCHAR(64) NOT NULL DEFAULT \'\',
            user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
            created_at VARCHAR(40) NOT NULL,
            KEY idx_admin_activity_user (admin_user_id),
            KEY idx_admin_activity_action (action_key),
            KEY idx_admin_activity_entity (entity_type, entity_id),
            KEY idx_admin_activity_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function enma_activity_actor(): array
{
    return [
        'id' => isset($_SESSION['admin_user_id']) ? (int) $_SESSION['admin_user_id'] : null,
        'username' => trim((string) ($_SESSION['admin_username'] ?? 'system')),
    ];
}

function enma_record_activity(PDO $pdo, string $actionKey, string $entityType = '', ?int $entityId = null, array $details = []): void
{
    try {
        $actor = enma_activity_actor();
        $stmt = $pdo->prepare(
            'INSERT INTO admin_activity_log (
                admin_user_id, admin_username, action_key, entity_type, entity_id,
                details_json, ip_address, user_agent, created_at
            ) VALUES (
                :admin_user_id, :admin_username, :action_key, :entity_type, :entity_id,
                :details_json, :ip_address, :user_agent, :created_at
            )'
        );
        $stmt->execute([
            ':admin_user_id' => $actor['id'],
            ':admin_username' => $actor['username'] !== '' ? $actor['username'] : 'system',
            ':action_key' => $actionKey,
            ':entity_type' => $entityType,
            ':entity_id' => $entityId,
            ':details_json' => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ':ip_address' => substr(trim((string) ($_SERVER['REMOTE_ADDR'] ?? '')), 0, 64),
            ':user_agent' => substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255),
            ':created_at' => now_iso(),
        ]);
    } catch (Throwable $e) {
        // Activity logging must never block admin operations.
    }
}

function enma_count_active_admins(PDO $pdo, ?int $excludeUserId = null): int
{
    $sql = 'SELECT COUNT(*) FROM users WHERE role = :role AND status = :status';
    $params = [':role' => 'admin', ':status' => 'active'];
    if ($excludeUserId !== null && $excludeUserId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeUserId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}
