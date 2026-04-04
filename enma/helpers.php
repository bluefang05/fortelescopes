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

    $ext = $map[$mime];
    $uploadDir = __DIR__ . '/../assets/uploads/' . $subDir;
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        $errors[] = 'Could not create upload directory.';
        return null;
    }

    $prefix = $subDir === 'posts' ? 'post_' : 'p_';
    $name = $prefix . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $uploadDir . '/' . $name;
    if (!move_uploaded_file($tmp, $target)) {
        $errors[] = 'Could not move uploaded image.';
        return null;
    }

    return absolute_url('/assets/uploads/' . $subDir . '/' . $name);
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

    if ((strpos($html, '&lt;') !== false || strpos($html, '&gt;') !== false) && strpos($html, '<') === false) {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($decoded) !== '') {
            return trim($decoded);
        }
    }

    return $html;
}
