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

if (!function_exists('enma_site_settings_init_table')) {
    function enma_site_settings_init_table(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS enma_site_settings (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL,
                setting_value TEXT NULL,
                updated_at VARCHAR(40) NOT NULL,
                UNIQUE KEY uq_setting_key (setting_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

if (!function_exists('enma_set_site_setting')) {
    function enma_set_site_setting(PDO $pdo, string $key, string $value): void
    {
        $now = now_iso();
        $stmt = $pdo->prepare(
            'INSERT INTO enma_site_settings (setting_key, setting_value, updated_at)
             VALUES (:setting_key, :setting_value, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
            ':updated_at' => $now,
        ]);
    }
}

if (!function_exists('enma_is_valid_hero_url')) {
    function enma_is_valid_hero_url(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        if ($url[0] === '/') {
            return true;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('enma_is_valid_internal_or_external_url')) {
    function enma_is_valid_internal_or_external_url(string $url): bool
    {
        if ($url === '') {
            return true;
        }
        if ($url[0] === '/') {
            return true;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('enma_apply_home_visual_assignment')) {
    function enma_apply_home_visual_assignment(PDO $pdo, string $target, string $mediaUrl, string $mediaTitle = '', bool $publish = true): void
    {
        $prefix = $publish ? '' : 'draft_';
        if ($target === 'hero') {
            enma_set_site_setting($pdo, $prefix . 'home_hero_image', $mediaUrl);
            if (site_setting_get($pdo, $prefix . 'home_hero_image_2x', '') === '') {
                enma_set_site_setting($pdo, $prefix . 'home_hero_image_2x', $mediaUrl);
            }
            if (site_setting_get($pdo, $prefix . 'home_hero_alt', '') === '' && $mediaTitle !== '') {
                enma_set_site_setting($pdo, $prefix . 'home_hero_alt', $mediaTitle);
            }
        } elseif ($target === 'tile1') {
            enma_set_site_setting($pdo, $prefix . 'home_promo_tile_1_image', $mediaUrl);
            if (site_setting_get($pdo, $prefix . 'home_promo_tile_1_title', '') === '' && $mediaTitle !== '') {
                enma_set_site_setting($pdo, $prefix . 'home_promo_tile_1_title', $mediaTitle);
            }
        } elseif ($target === 'tile2') {
            enma_set_site_setting($pdo, $prefix . 'home_promo_tile_2_image', $mediaUrl);
            if (site_setting_get($pdo, $prefix . 'home_promo_tile_2_title', '') === '' && $mediaTitle !== '') {
                enma_set_site_setting($pdo, $prefix . 'home_promo_tile_2_title', $mediaTitle);
            }
        }
    }
}

if (!function_exists('enma_media_save_upload')) {
    function enma_media_convert_to_webp(string $tmpPath, string $mimeType, string $targetPath, int $quality = 82): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $resource = null;
        if ($mimeType === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $resource = @imagecreatefromjpeg($tmpPath);
        } elseif ($mimeType === 'image/png' && function_exists('imagecreatefrompng')) {
            $resource = @imagecreatefrompng($tmpPath);
            if ($resource !== false && $resource !== null) {
                imagepalettetotruecolor($resource);
                imagealphablending($resource, true);
                imagesavealpha($resource, true);
            }
        } elseif ($mimeType === 'image/gif' && function_exists('imagecreatefromgif')) {
            $resource = @imagecreatefromgif($tmpPath);
        }

        if ($resource === false || $resource === null) {
            return false;
        }

        $ok = @imagewebp($resource, $targetPath, $quality);
        imagedestroy($resource);

        return $ok && is_file($targetPath);
    }

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

        $baseName = 'm_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $storedExt = $ext;
        $storedMime = $mime;

        // Normalize raster uploads to WebP for better front-end performance.
        if ($mediaType === 'image' && in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            $storedExt = 'webp';
            $storedMime = 'image/webp';
            $storedName = $baseName . '.webp';
            $target = $uploadDir . '/' . $storedName;
            if (!enma_media_convert_to_webp($tmpPath, $mime, $target)) {
                $errors[] = 'Could not convert image to WebP. Verify PHP GD/WebP support.';
                return null;
            }
        } else {
            $storedName = $baseName . '.' . $storedExt;
            $target = $uploadDir . '/' . $storedName;
            if (!move_uploaded_file($tmpPath, $target)) {
                $errors[] = 'Could not save uploaded file.';
                return null;
            }
        }

        return [
            'media_type' => $mediaType,
            'mime_type' => $storedMime,
            'file_ext' => $storedExt,
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
            $quickAssignTarget = trim((string) ($_POST['quick_assign_target'] ?? ''));
            $quickAssignAutosave = trim((string) ($_POST['quick_assign_autosave'] ?? '1')) === '1';
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
                if (in_array($quickAssignTarget, ['hero', 'tile1', 'tile2'], true)) {
                    enma_site_settings_init_table($pdo);
                    enma_apply_home_visual_assignment(
                        $pdo,
                        $quickAssignTarget,
                        (string) ($saved['file_url'] ?? ''),
                        $title !== '' ? $title : (string) ($saved['original_name'] ?? ''),
                        $quickAssignAutosave
                    );
                    $flash = 'Media uploaded and assigned to ' . strtoupper($quickAssignTarget) . '.';
                } else {
                    $flash = 'Media uploaded successfully.';
                }
            } catch (Throwable $e) {
                $errors[] = 'Media insert failed: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'home_media_assign') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $assignTarget = trim((string) ($_POST['assign_target'] ?? ''));
        $assignUrl = trim((string) ($_POST['assign_url'] ?? ''));
        $assignTitle = trim((string) ($_POST['assign_title'] ?? ''));
        $assignMode = trim((string) ($_POST['assign_mode'] ?? 'publish'));
        $publish = $assignMode !== 'draft';
        if (!in_array($assignTarget, ['hero', 'tile1', 'tile2'], true) || $assignUrl === '') {
            $errors[] = 'Invalid assignment payload.';
        } else {
            try {
                enma_site_settings_init_table($pdo);
                enma_apply_home_visual_assignment($pdo, $assignTarget, $assignUrl, $assignTitle, $publish);
                $flash = 'Assigned image to ' . strtoupper($assignTarget) . ' (' . ($publish ? 'published' : 'draft') . ').';
            } catch (Throwable $e) {
                $errors[] = 'Could not assign media: ' . $e->getMessage();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'home_publish_draft') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        try {
            enma_site_settings_init_table($pdo);
            $keys = [
                'home_hero_image',
                'home_hero_image_2x',
                'home_hero_alt',
                'home_hero_title',
                'home_hero_subtitle',
                'home_hero_cta_label',
                'home_hero_cta_url',
                'home_hero_overlay',
                'home_promo_tile_1_image',
                'home_promo_tile_1_title',
                'home_promo_tile_1_cta_label',
                'home_promo_tile_1_cta_url',
                'home_promo_tile_2_image',
                'home_promo_tile_2_title',
                'home_promo_tile_2_cta_label',
                'home_promo_tile_2_cta_url',
                'home_featured_product_ids',
                'home_hero_eyebrow',
                'home_hero_text_position',
                'home_hero_overlay_strength',
                'home_hero_layout_size',
                'home_promo_tile_1_eyebrow',
                'home_promo_tile_1_subtitle',
                'home_promo_tile_1_text_position',
                'home_promo_tile_1_overlay_strength',
                'home_promo_tile_1_layout_size',
                'home_promo_tile_2_eyebrow',
                'home_promo_tile_2_subtitle',
                'home_promo_tile_2_text_position',
                'home_promo_tile_2_overlay_strength',
                'home_promo_tile_2_layout_size',
                'home_banner_1_image','home_banner_1_eyebrow','home_banner_1_title','home_banner_1_subtitle','home_banner_1_cta_label','home_banner_1_cta_url','home_banner_1_text_position','home_banner_1_overlay_strength','home_banner_1_layout_size',
                'home_banner_2_image','home_banner_2_eyebrow','home_banner_2_title','home_banner_2_subtitle','home_banner_2_cta_label','home_banner_2_cta_url','home_banner_2_text_position','home_banner_2_overlay_strength','home_banner_2_layout_size',
                'home_goal_1_label','home_goal_1_url','home_goal_2_label','home_goal_2_url','home_goal_3_label','home_goal_3_url','home_goal_4_label','home_goal_4_url',
                'home_faq_1_question','home_faq_1_answer','home_faq_2_question','home_faq_2_answer','home_faq_3_question','home_faq_3_answer',
            ];
            foreach ($keys as $key) {
                $draftValue = site_setting_get($pdo, 'draft_' . $key, site_setting_get($pdo, $key, ''));
                enma_set_site_setting($pdo, $key, $draftValue);
            }
            $flash = 'Draft home visuals published to live.';
        } catch (Throwable $e) {
            $errors[] = 'Could not publish draft: ' . $e->getMessage();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_home_hero_settings') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $settingsMode = trim((string) ($_POST['home_settings_mode'] ?? 'publish'));
        $settingsPrefix = $settingsMode === 'draft' ? 'draft_' : '';
        $heroImage = trim((string) ($_POST['home_hero_image'] ?? ''));
        $heroImage2x = trim((string) ($_POST['home_hero_image_2x'] ?? ''));
        $heroAlt = trim((string) ($_POST['home_hero_alt'] ?? ''));
        $heroTitle = trim((string) ($_POST['home_hero_title'] ?? ''));
        $heroSubtitle = trim((string) ($_POST['home_hero_subtitle'] ?? ''));
        $heroCtaLabel = trim((string) ($_POST['home_hero_cta_label'] ?? ''));
        $heroCtaUrl = trim((string) ($_POST['home_hero_cta_url'] ?? ''));
        $heroOverlay = max(15, min(85, (int) ($_POST['home_hero_overlay'] ?? 55)));
        $heroEyebrow = trim((string) ($_POST['home_hero_eyebrow'] ?? ''));
        $heroTextPosition = trim((string) ($_POST['home_hero_text_position'] ?? 'center'));
        $heroOverlayStrength = trim((string) ($_POST['home_hero_overlay_strength'] ?? 'dark'));
        $heroLayoutSize = trim((string) ($_POST['home_hero_layout_size'] ?? 'full'));
        $tile1Image = trim((string) ($_POST['home_promo_tile_1_image'] ?? ''));
        $tile1Title = trim((string) ($_POST['home_promo_tile_1_title'] ?? ''));
        $tile1Eyebrow = trim((string) ($_POST['home_promo_tile_1_eyebrow'] ?? ''));
        $tile1Subtitle = trim((string) ($_POST['home_promo_tile_1_subtitle'] ?? ''));
        $tile1CtaLabel = trim((string) ($_POST['home_promo_tile_1_cta_label'] ?? ''));
        $tile1CtaUrl = trim((string) ($_POST['home_promo_tile_1_cta_url'] ?? ''));
        $tile1TextPosition = trim((string) ($_POST['home_promo_tile_1_text_position'] ?? 'bottom-left'));
        $tile1OverlayStrength = trim((string) ($_POST['home_promo_tile_1_overlay_strength'] ?? 'medium'));
        $tile1LayoutSize = trim((string) ($_POST['home_promo_tile_1_layout_size'] ?? 'half'));
        $tile2Image = trim((string) ($_POST['home_promo_tile_2_image'] ?? ''));
        $tile2Title = trim((string) ($_POST['home_promo_tile_2_title'] ?? ''));
        $tile2Eyebrow = trim((string) ($_POST['home_promo_tile_2_eyebrow'] ?? ''));
        $tile2Subtitle = trim((string) ($_POST['home_promo_tile_2_subtitle'] ?? ''));
        $tile2CtaLabel = trim((string) ($_POST['home_promo_tile_2_cta_label'] ?? ''));
        $tile2CtaUrl = trim((string) ($_POST['home_promo_tile_2_cta_url'] ?? ''));
        $tile2TextPosition = trim((string) ($_POST['home_promo_tile_2_text_position'] ?? 'bottom-left'));
        $tile2OverlayStrength = trim((string) ($_POST['home_promo_tile_2_overlay_strength'] ?? 'medium'));
        $tile2LayoutSize = trim((string) ($_POST['home_promo_tile_2_layout_size'] ?? 'half'));
        $banner1Image = trim((string) ($_POST['home_banner_1_image'] ?? ''));
        $banner1Eyebrow = trim((string) ($_POST['home_banner_1_eyebrow'] ?? ''));
        $banner1Title = trim((string) ($_POST['home_banner_1_title'] ?? ''));
        $banner1Subtitle = trim((string) ($_POST['home_banner_1_subtitle'] ?? ''));
        $banner1CtaLabel = trim((string) ($_POST['home_banner_1_cta_label'] ?? ''));
        $banner1CtaUrl = trim((string) ($_POST['home_banner_1_cta_url'] ?? ''));
        $banner1TextPosition = trim((string) ($_POST['home_banner_1_text_position'] ?? 'left'));
        $banner1OverlayStrength = trim((string) ($_POST['home_banner_1_overlay_strength'] ?? 'medium'));
        $banner1LayoutSize = trim((string) ($_POST['home_banner_1_layout_size'] ?? 'full'));
        $banner2Image = trim((string) ($_POST['home_banner_2_image'] ?? ''));
        $banner2Eyebrow = trim((string) ($_POST['home_banner_2_eyebrow'] ?? ''));
        $banner2Title = trim((string) ($_POST['home_banner_2_title'] ?? ''));
        $banner2Subtitle = trim((string) ($_POST['home_banner_2_subtitle'] ?? ''));
        $banner2CtaLabel = trim((string) ($_POST['home_banner_2_cta_label'] ?? ''));
        $banner2CtaUrl = trim((string) ($_POST['home_banner_2_cta_url'] ?? ''));
        $banner2TextPosition = trim((string) ($_POST['home_banner_2_text_position'] ?? 'left'));
        $banner2OverlayStrength = trim((string) ($_POST['home_banner_2_overlay_strength'] ?? 'medium'));
        $banner2LayoutSize = trim((string) ($_POST['home_banner_2_layout_size'] ?? 'full'));
        $goal1Label = trim((string) ($_POST['home_goal_1_label'] ?? ''));
        $goal1Url = trim((string) ($_POST['home_goal_1_url'] ?? ''));
        $goal2Label = trim((string) ($_POST['home_goal_2_label'] ?? ''));
        $goal2Url = trim((string) ($_POST['home_goal_2_url'] ?? ''));
        $goal3Label = trim((string) ($_POST['home_goal_3_label'] ?? ''));
        $goal3Url = trim((string) ($_POST['home_goal_3_url'] ?? ''));
        $goal4Label = trim((string) ($_POST['home_goal_4_label'] ?? ''));
        $goal4Url = trim((string) ($_POST['home_goal_4_url'] ?? ''));
        $faq1Question = trim((string) ($_POST['home_faq_1_question'] ?? ''));
        $faq1Answer = trim((string) ($_POST['home_faq_1_answer'] ?? ''));
        $faq2Question = trim((string) ($_POST['home_faq_2_question'] ?? ''));
        $faq2Answer = trim((string) ($_POST['home_faq_2_answer'] ?? ''));
        $faq3Question = trim((string) ($_POST['home_faq_3_question'] ?? ''));
        $faq3Answer = trim((string) ($_POST['home_faq_3_answer'] ?? ''));
        $featuredProductIds = trim((string) ($_POST['home_featured_product_ids'] ?? ''));

        if (
            !enma_is_valid_hero_url($heroImage)
            || !enma_is_valid_hero_url($heroImage2x)
            || !enma_is_valid_hero_url($tile1Image)
            || !enma_is_valid_hero_url($tile2Image)
            || !enma_is_valid_hero_url($banner1Image)
            || !enma_is_valid_hero_url($banner2Image)
        ) {
            $errors[] = 'Image URLs must be absolute URLs or paths starting with "/".';
        } elseif (
            !enma_is_valid_internal_or_external_url($heroCtaUrl)
            || !enma_is_valid_internal_or_external_url($tile1CtaUrl)
            || !enma_is_valid_internal_or_external_url($tile2CtaUrl)
            || !enma_is_valid_internal_or_external_url($banner1CtaUrl)
            || !enma_is_valid_internal_or_external_url($banner2CtaUrl)
            || !enma_is_valid_internal_or_external_url($goal1Url)
            || !enma_is_valid_internal_or_external_url($goal2Url)
            || !enma_is_valid_internal_or_external_url($goal3Url)
            || !enma_is_valid_internal_or_external_url($goal4Url)
        ) {
            $errors[] = 'CTA URLs must be absolute URLs or paths starting with "/".';
        } else {
            try {
                enma_site_settings_init_table($pdo);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_image', $heroImage);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_image_2x', $heroImage2x);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_alt', $heroAlt);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_title', $heroTitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_subtitle', $heroSubtitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_cta_label', $heroCtaLabel);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_cta_url', $heroCtaUrl);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_overlay', (string) $heroOverlay);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_eyebrow', $heroEyebrow);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_text_position', $heroTextPosition);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_overlay_strength', $heroOverlayStrength);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_hero_layout_size', $heroLayoutSize);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_image', $tile1Image);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_title', $tile1Title);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_eyebrow', $tile1Eyebrow);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_subtitle', $tile1Subtitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_cta_label', $tile1CtaLabel);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_cta_url', $tile1CtaUrl);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_text_position', $tile1TextPosition);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_overlay_strength', $tile1OverlayStrength);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_1_layout_size', $tile1LayoutSize);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_image', $tile2Image);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_title', $tile2Title);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_eyebrow', $tile2Eyebrow);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_subtitle', $tile2Subtitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_cta_label', $tile2CtaLabel);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_cta_url', $tile2CtaUrl);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_text_position', $tile2TextPosition);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_overlay_strength', $tile2OverlayStrength);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_promo_tile_2_layout_size', $tile2LayoutSize);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_image', $banner1Image);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_eyebrow', $banner1Eyebrow);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_title', $banner1Title);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_subtitle', $banner1Subtitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_cta_label', $banner1CtaLabel);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_cta_url', $banner1CtaUrl);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_text_position', $banner1TextPosition);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_overlay_strength', $banner1OverlayStrength);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_1_layout_size', $banner1LayoutSize);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_image', $banner2Image);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_eyebrow', $banner2Eyebrow);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_title', $banner2Title);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_subtitle', $banner2Subtitle);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_cta_label', $banner2CtaLabel);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_cta_url', $banner2CtaUrl);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_text_position', $banner2TextPosition);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_overlay_strength', $banner2OverlayStrength);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_banner_2_layout_size', $banner2LayoutSize);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_1_label', $goal1Label);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_1_url', $goal1Url);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_2_label', $goal2Label);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_2_url', $goal2Url);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_3_label', $goal3Label);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_3_url', $goal3Url);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_4_label', $goal4Label);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_goal_4_url', $goal4Url);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_1_question', $faq1Question);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_1_answer', $faq1Answer);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_2_question', $faq2Question);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_2_answer', $faq2Answer);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_3_question', $faq3Question);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_faq_3_answer', $faq3Answer);
                enma_set_site_setting($pdo, $settingsPrefix . 'home_featured_product_ids', $featuredProductIds);
                enma_record_activity($pdo, 'settings.home_hero.save', 'setting', 0, [
                    'home_hero_image' => $heroImage,
                    'home_hero_image_2x' => $heroImage2x,
                ]);
                $flash = $settingsMode === 'draft' ? 'Home visual draft saved.' : 'Home visuals published.';
            } catch (Throwable $e) {
                $errors[] = 'Could not save hero settings: ' . $e->getMessage();
            }
        }
    }
}
