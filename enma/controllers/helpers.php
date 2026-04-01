<?php

declare(strict_types=1);

/**
 * ENMA Controller Helper Functions
 * 
 * This file contains helper functions for the ENMA admin panel controllers.
 */

/**
 * Handle image upload for products
 */
function enma_handle_image_upload(string $fieldName, array &$errors): ?string
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
        $errors[] = 'Image upload failed.';
        return null;
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 4 * 1024 * 1024) {
        $errors[] = 'Image must be between 1 byte and 4MB.';
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

    $ext = $map[$mime];
    $uploadDir = __DIR__ . '/../../assets/uploads/products';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Could not create upload directory.';
        return null;
    }

    $name = 'p_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        $errors[] = 'Could not move uploaded image.';
        return null;
    }

    return absolute_url('/assets/uploads/products/' . $name);
}

/**
 * Validate and process login attempt
 */
function enma_process_login(array &$errors, string $user, string $pass, int &$attempts, int &$lockedUntil, int $maxAttempts = 5, int $lockSeconds = 600): bool
{
    if ($lockedUntil > time()) {
        $errors[] = 'Too many login attempts. Try again in a few minutes.';
        return false;
    }

    if ($user === '' || $pass === '') {
        $errors[] = 'Invalid credentials.';
        return false;
    }

    if (hash_equals(ADMIN_USER, $user) && hash_equals(ADMIN_PASS, $pass)) {
        session_regenerate_id(true);
        $_SESSION['admin_ok'] = true;
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_locked_until'] = 0;
        return true;
    }

    $attempts++;
    if ($attempts >= $maxAttempts) {
        $_SESSION['login_locked_until'] = time() + $lockSeconds;
        $_SESSION['login_attempts'] = 0;
        $errors[] = 'Too many login attempts. Try again in 10 minutes.';
    } else {
        $errors[] = 'Invalid credentials.';
    }

    return false;
}

/**
 * Run advanced maintenance task
 */
function enma_run_advanced_task(string $task, string $advancedKey, string $confirmText, array &$errors, array &$log): ?string
{
    if (!ENMA_ADVANCED_KEY !== '' && !hash_equals(ENMA_ADVANCED_KEY, $advancedKey)) {
        $errors[] = 'Advanced key is invalid.';
        return null;
    }

    $expectedConfirm = 'RUN ' . strtoupper($task);
    if (strtoupper($confirmText) !== $expectedConfirm) {
        $errors[] = 'Invalid confirmation text. Use exactly: ' . $expectedConfirm;
        return null;
    }

    $taskMap = [
        'refresh_sync_cli' => __DIR__ . '/../../scripts/cron_refresh.php',
        'reseed_real_catalog' => __DIR__ . '/../../scripts/seed_real_catalog.php',
        'seed_more_products' => __DIR__ . '/../../scripts/seed_more_products.php',
    ];

    if (!isset($taskMap[$task])) {
        $errors[] = 'Unknown advanced task.';
        return null;
    }

    if ($task === 'seed_more_products' && DB_DRIVER !== 'sqlite') {
        $errors[] = 'seed_more_products supports sqlite only in current script version.';
        return null;
    }

    if (!defined('ENMA_ALLOW_WEB_RUN')) {
        define('ENMA_ALLOW_WEB_RUN', true);
    }

    $scriptPath = realpath($taskMap[$task] ?? '');
    $scriptsRoot = realpath(__DIR__ . '/../../scripts');

    if ($scriptPath === false || $scriptsRoot === false || strpos($scriptPath, $scriptsRoot) !== 0) {
        $errors[] = 'Invalid script path.';
        return null;
    }

    ob_start();
    try {
        require $scriptPath;
        $output = trim((string) ob_get_clean());
        if ($output !== '') {
            foreach (preg_split('/\r\n|\r|\n/', $output) as $line) {
                if (trim((string) $line) !== '') {
                    $log[] = (string) $line;
                }
            }
        }
        return 'Advanced task completed: ' . $task;
    } catch (Throwable $e) {
        ob_end_clean();
        $errors[] = 'Advanced task failed: ' . $e->getMessage();
        return null;
    }
}
