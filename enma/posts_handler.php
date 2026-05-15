<?php

declare(strict_types=1);

/**
 * Posts handler for ENMA admin panel
 * Handles CRUD operations for posts
 */

if (!$authenticated) {
    return;
}

if (!function_exists('enma_post_theme_fix_html')) {
    function enma_post_theme_fix_html(string $html): string
    {
        $normalized = enma_normalize_editor_html($html);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/\s+$/m', '', $normalized) ?? $normalized;
        return trim($normalized);
    }
}

if (!function_exists('enma_post_theme_dark_html')) {
    function enma_post_theme_dark_html(string $html): string
    {
        $darkReady = enma_post_theme_fix_html($html);
        if ($darkReady === '') {
            return '';
        }

        // Remove presentation attributes that commonly force light blocks in post bodies.
        $darkReady = preg_replace('/\s(style|bgcolor|color)\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/<\s*style\b[^>]*>[\s\S]*?<\s*\/\s*style\s*>/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/<\s*script\b[^>]*type\s*=\s*("|\')application\/ld\+json\1[^>]*>[\s\S]*?<\s*\/\s*script\s*>/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/<\s*script\b[^>]*>[\s\S]*?<\s*\/\s*script\s*>/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/<\s*article\b[^>]*>/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/<\s*\/\s*article\s*>/i', '', $darkReady) ?? $darkReady;
        $darkReady = preg_replace('/\s{2,}/', ' ', $darkReady) ?? $darkReady;
        return trim($darkReady);
    }
}

if (!function_exists('enma_posts_table_exists')) {
    function enma_posts_table_exists(PDO $pdo, string $tableName): bool
    {
        $tableName = trim($tableName);
        if ($tableName === '') {
            return false;
        }

        $stmt = $pdo->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = :schema
               AND table_name = :table_name
             LIMIT 1'
        );
        $stmt->execute([
            ':schema' => DB_NAME,
            ':table_name' => $tableName,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('enma_normalize_post_section')) {
    function enma_normalize_post_section(string $section, string $title = '', string $excerpt = '', string $slug = ''): string
    {
        $candidate = strtolower(trim($section));
        if ($candidate === 'review') {
            $candidate = 'reviews';
        }
        if (in_array($candidate, ['blog', 'reviews'], true)) {
            return $candidate;
        }

        $mock = [
            'section' => $candidate,
            'title' => $title,
            'excerpt' => $excerpt,
            'slug' => $slug,
        ];
        return post_section($mock);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_fix_posts_theme') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        try {
            $rows = $pdo->query('SELECT id, content_html FROM posts')->fetchAll();
            $updated = 0;
            $stmt = $pdo->prepare('UPDATE posts SET content_html = :content_html, updated_at = :updated_at WHERE id = :id');
            $now = now_iso();
            foreach ($rows as $row) {
                $original = (string) ($row['content_html'] ?? '');
                $fixed = enma_post_theme_fix_html($original);
                if ($fixed === $original) {
                    continue;
                }
                $stmt->execute([
                    ':content_html' => $fixed,
                    ':updated_at' => $now,
                    ':id' => (int) ($row['id'] ?? 0),
                ]);
                $updated++;
            }
            enma_record_activity($pdo, 'post.bulk_fix_theme', 'post', null, ['updated' => $updated]);
            $flash = 'Bulk post fix complete. Updated ' . $updated . ' post(s).';
        } catch (Throwable $e) {
            $errors[] = 'Bulk fix failed: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_dark_posts_theme') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        try {
            $rows = $pdo->query('SELECT id, content_html FROM posts')->fetchAll();
            $updated = 0;
            $stmt = $pdo->prepare('UPDATE posts SET content_html = :content_html, updated_at = :updated_at WHERE id = :id');
            $now = now_iso();
            foreach ($rows as $row) {
                $original = (string) ($row['content_html'] ?? '');
                $darkReady = enma_post_theme_dark_html($original);
                if ($darkReady === $original) {
                    continue;
                }
                $stmt->execute([
                    ':content_html' => $darkReady,
                    ':updated_at' => $now,
                    ':id' => (int) ($row['id'] ?? 0),
                ]);
                $updated++;
            }
            enma_record_activity($pdo, 'post.bulk_dark_theme', 'post', null, ['updated' => $updated]);
            $flash = 'Bulk dark-theme conversion complete. Updated ' . $updated . ' post(s).';
        } catch (Throwable $e) {
            $errors[] = 'Bulk dark-theme conversion failed: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'bulk_reclassify_post_sections') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        try {
            $rows = $pdo->query('SELECT id, slug, title, excerpt, post_type, extra_data FROM posts WHERE post_type = "post"')->fetchAll();
            $updated = 0;
            $now = now_iso();
            $stmt = $pdo->prepare('UPDATE posts SET extra_data = :extra_data, updated_at = :updated_at WHERE id = :id');

            foreach ($rows as $row) {
                $extra = json_decode((string) ($row['extra_data'] ?? ''), true);
                if (!is_array($extra)) {
                    $extra = [];
                }

                $section = enma_normalize_post_section(
                    (string) ($extra['section'] ?? ''),
                    (string) ($row['title'] ?? ''),
                    (string) ($row['excerpt'] ?? ''),
                    (string) ($row['slug'] ?? '')
                );
                if (($extra['section'] ?? null) === $section) {
                    continue;
                }

                $extra['section'] = $section;
                $payload = json_encode($extra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (!is_string($payload) || $payload === '') {
                    continue;
                }

                $stmt->execute([
                    ':extra_data' => $payload,
                    ':updated_at' => $now,
                    ':id' => (int) ($row['id'] ?? 0),
                ]);
                $updated++;
            }

            enma_record_activity($pdo, 'post.bulk_reclassify_sections', 'post', null, ['updated' => $updated]);
            $flash = 'Post section reclassification complete. Updated ' . $updated . ' post(s).';
        } catch (Throwable $e) {
            $errors[] = 'Bulk reclassify failed: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_post_autosave') {
    header('Content-Type: application/json; charset=UTF-8');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Invalid request token.']);
        exit;
    }

    if (!enma_posts_table_exists($pdo, 'post_autosaves')) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'message' => 'Autosave DB schema is not enabled yet.']);
        exit;
    }

    $postId = max(0, (int) ($_POST['post_id'] ?? 0));
    $draftKey = substr(trim((string) ($_POST['draft_key'] ?? '')), 0, 64);
    if ($draftKey === '') {
        $draftKey = 'draft-' . substr(sha1((string) microtime(true) . '-' . (string) random_int(1000, 9999)), 0, 24);
    }

    $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
    $excerpt = mb_substr(trim((string) ($_POST['excerpt'] ?? '')), 0, 5000);
    $metaTitle = mb_substr(trim((string) ($_POST['meta_title'] ?? '')), 0, 255);
    $metaDescription = mb_substr(trim((string) ($_POST['meta_description'] ?? '')), 0, 5000);
    $content = mb_substr(enma_normalize_editor_html((string) ($_POST['content_html'] ?? '')), 0, 65000);
    $savedAt = now_iso();
    $editorUserId = (int) ($_SESSION['admin_user_id'] ?? 0);

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO post_autosaves (
                post_id, editor_user_id, draft_key, title, excerpt, meta_title, meta_description, content_html, saved_at
             ) VALUES (
                :post_id, :editor_user_id, :draft_key, :title, :excerpt, :meta_title, :meta_description, :content_html, :saved_at
             )
             ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                excerpt = VALUES(excerpt),
                meta_title = VALUES(meta_title),
                meta_description = VALUES(meta_description),
                content_html = VALUES(content_html),
                saved_at = VALUES(saved_at)'
        );
        $stmt->execute([
            ':post_id' => $postId,
            ':editor_user_id' => $editorUserId,
            ':draft_key' => $draftKey,
            ':title' => $title,
            ':excerpt' => $excerpt,
            ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
            ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
            ':content_html' => $content !== '' ? $content : null,
            ':saved_at' => $savedAt,
        ]);

        echo json_encode([
            'ok' => true,
            'saved_at' => $savedAt,
            'draft_key' => $draftKey,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'message' => 'Autosave failed.',
        ]);
    }
    exit;
}

// Add new post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_post') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = enma_normalize_editor_html((string) ($_POST['content_html'] ?? ''));
    $postType = trim((string) ($_POST['post_type'] ?? 'post'));
    $featuredImage = trim((string) ($_POST['featured_image'] ?? ''));
    $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
    $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
    $sectionInput = trim((string) ($_POST['section'] ?? ''));
    $postType = strtolower($postType);
    if ($postType === 'review') {
        $sectionInput = 'reviews';
    } elseif ($postType === 'post') {
        $sectionInput = 'blog';
    } elseif ($postType === 'guide') {
        $sectionInput = '';
    } else {
        $postType = 'post';
        $sectionInput = 'blog';
    }

    $uploaded = enma_handle_image_upload('featured_image_file', $errors, 'posts');
    if ($uploaded !== null) {
        $featuredImage = $uploaded;
    }

    if ($title === '' || $excerpt === '' || $content === '') {
        $errors[] = 'Title, excerpt and content are required.';
    }

    if ($errors === []) {
        $slug = unique_slug_for_posts($pdo, $title);
        $now = now_iso();
        $section = enma_normalize_post_section($sectionInput, $title, $excerpt, $slug);
        $extraData = json_encode(['section' => $section], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            $createdByUserId = (int) ($_SESSION['admin_user_id'] ?? 0);
            $stmt = $pdo->prepare(
                'INSERT INTO posts (
                    slug, title, excerpt, content_html, featured_image, post_type, status, meta_title, meta_description,
                    extra_data, created_by_user_id, created_at, updated_at, published_at
                 ) VALUES (
                    :slug, :title, :excerpt, :content_html, :featured_image, :post_type, :status, :meta_title, :meta_description,
                    :extra_data, :created_by_user_id, :created_at, :updated_at, :published_at
                 )'
            );

            $stmt->execute([
                ':slug' => $slug,
                ':title' => $title,
                ':excerpt' => $excerpt,
                ':content_html' => $content,
                ':featured_image' => $featuredImage,
                ':post_type' => $postType,
                ':status' => 'published',
                ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ':extra_data' => $postType === 'post' ? $extraData : null,
                ':created_by_user_id' => $createdByUserId > 0 ? $createdByUserId : null,
                ':created_at' => $now,
                ':updated_at' => $now,
                ':published_at' => $now,
            ]);

            $newPostId = (int) $pdo->lastInsertId();
            enma_record_activity($pdo, 'post.create', 'post', $newPostId, [
                'title' => $title,
                'slug' => $slug,
                'post_type' => $postType,
                'section' => $section,
                'meta_title' => $metaTitle,
            ]);
            $postPath = $postType === 'guide'
                ? '/' . $slug
                : post_url_path(['slug' => $slug, 'section' => $section, 'post_type' => 'post']);
            $indexNowTargets = [absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')];
            if ($postType === 'post') {
                $indexNowTargets[] = absolute_url('/' . $section);
            }
            $indexNowResult = indexnow_submit_urls($indexNowTargets);
            if (!empty($indexNowResult['message'])) {
                $maintenanceLog[] = (string) $indexNowResult['message'];
            }
            $flash = ucfirst($postType) . ' created successfully.';
        } catch (Throwable $e) {
            $errors[] = 'Insert failed: ' . $e->getMessage();
        }
    }
}

// Update existing post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_post') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    }

    $id = (int)($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = enma_normalize_editor_html((string) ($_POST['content_html'] ?? ''));
    $postType = trim((string) ($_POST['post_type'] ?? 'post'));
    $featuredImage = trim((string) ($_POST['featured_image'] ?? ''));
    $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
    $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
    $sectionInput = trim((string) ($_POST['section'] ?? ''));
    $postType = strtolower($postType);
    if ($postType === 'review') {
        $sectionInput = 'reviews';
    } elseif ($postType === 'post') {
        $sectionInput = 'blog';
    } elseif ($postType === 'guide') {
        $sectionInput = '';
    } else {
        $postType = 'post';
        $sectionInput = 'blog';
    }

    $uploaded = enma_handle_image_upload('featured_image_file', $errors, 'posts');
    if ($uploaded !== null) {
        $featuredImage = $uploaded;
    }

    if ($id <= 0 || $title === '' || $excerpt === '') {
        $errors[] = 'Valid ID, title and excerpt are required.';
    }

    if ($errors === [] && $content === '') {
        $stmt = $pdo->prepare('SELECT content_html FROM posts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $existingContent = (string) ($stmt->fetchColumn() ?: '');
        if ($existingContent !== '') {
            $content = $existingContent;
        } else {
            $errors[] = 'Content is required.';
        }
    }

    if ($errors === []) {
        $now = now_iso();
        $slugLookupStmt = $pdo->prepare('SELECT slug, post_type, extra_data FROM posts WHERE id = :id LIMIT 1');
        $slugLookupStmt->execute([':id' => $id]);
        $existingPostRow = $slugLookupStmt->fetch() ?: [];
        $existingSlug = trim((string) ($existingPostRow['slug'] ?? ''));
        $existingExtra = json_decode((string) ($existingPostRow['extra_data'] ?? ''), true);
        if (!is_array($existingExtra)) {
            $existingExtra = [];
        }
        $resolvedSection = enma_normalize_post_section(
            $sectionInput !== '' ? $sectionInput : (string) ($existingExtra['section'] ?? ''),
            $title,
            $excerpt,
            $existingSlug
        );
        if ($postType === 'post') {
            $existingExtra['section'] = $resolvedSection;
        } else {
            unset($existingExtra['section']);
        }
        $updatedExtraData = $existingExtra !== []
            ? json_encode($existingExtra, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        try {
            $stmt = $pdo->prepare(
                'UPDATE posts SET 
                    title = :title, 
                    excerpt = :excerpt, 
                    content_html = :content_html, 
                    featured_image = :featured_image, 
                    post_type = :post_type, 
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    extra_data = :extra_data,
                    updated_at = :updated_at 
                WHERE id = :id'
            );

            $stmt->execute([
                ':title' => $title,
                ':excerpt' => $excerpt,
                ':content_html' => $content,
                ':featured_image' => $featuredImage,
                ':post_type' => $postType,
                ':meta_title' => $metaTitle !== '' ? $metaTitle : null,
                ':meta_description' => $metaDescription !== '' ? $metaDescription : null,
                ':extra_data' => $updatedExtraData,
                ':updated_at' => $now,
                ':id' => $id
            ]);

            enma_record_activity($pdo, 'post.update', 'post', $id, [
                'title' => $title,
                'post_type' => $postType,
                'section' => $resolvedSection,
                'meta_title' => $metaTitle,
            ]);
            $slugStmt = $pdo->prepare('SELECT slug, post_type FROM posts WHERE id = :id LIMIT 1');
            $slugStmt->execute([':id' => $id]);
            $savedPost = $slugStmt->fetch();
            $savedSlug = trim((string) ($savedPost['slug'] ?? ''));
            $savedType = trim((string) ($savedPost['post_type'] ?? $postType));
            if ($savedSlug !== '') {
                $postPath = $savedType === 'guide'
                    ? '/' . $savedSlug
                    : post_url_path(['slug' => $savedSlug, 'section' => $resolvedSection, 'post_type' => 'post']);
                $indexNowTargets = [absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')];
                if ($savedType === 'post') {
                    $indexNowTargets[] = absolute_url('/' . $resolvedSection);
                }
                $indexNowResult = indexnow_submit_urls($indexNowTargets);
                if (!empty($indexNowResult['message'])) {
                    $maintenanceLog[] = (string) $indexNowResult['message'];
                }
            }
            $flash = ucfirst($postType) . ' updated successfully.';
            $editingPost = null; // Clear editing state after success
        } catch (Throwable $e) {
            $errors[] = 'Update failed: ' . $e->getMessage();
        }
    }
}

// Delete post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_post') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $titleStmt = $pdo->prepare('SELECT title, slug, post_type, extra_data FROM posts WHERE id = :id LIMIT 1');
            $titleStmt->execute([':id' => $id]);
            $postRow = $titleStmt->fetch();
            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute([':id' => $id]);
            enma_record_activity($pdo, 'post.delete', 'post', $id, [
                'title' => (string) ($postRow['title'] ?? ''),
                'slug' => (string) ($postRow['slug'] ?? ''),
                'post_type' => (string) ($postRow['post_type'] ?? ''),
            ]);
            $deletedSlug = trim((string) ($postRow['slug'] ?? ''));
            $deletedType = trim((string) ($postRow['post_type'] ?? 'post'));
            $deletedExtra = json_decode((string) ($postRow['extra_data'] ?? ''), true);
            if (!is_array($deletedExtra)) {
                $deletedExtra = [];
            }
            $deletedSection = enma_normalize_post_section((string) ($deletedExtra['section'] ?? ''), (string) ($postRow['title'] ?? ''), '', $deletedSlug);
            if ($deletedSlug !== '') {
                $postPath = $deletedType === 'guide'
                    ? '/' . $deletedSlug
                    : post_url_path(['slug' => $deletedSlug, 'section' => $deletedSection, 'post_type' => 'post']);
                $indexNowTargets = [absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')];
                if ($deletedType === 'post') {
                    $indexNowTargets[] = absolute_url('/' . $deletedSection);
                }
                $indexNowResult = indexnow_submit_urls($indexNowTargets);
                if (!empty($indexNowResult['message'])) {
                    $maintenanceLog[] = (string) $indexNowResult['message'];
                }
            }
            $flash = 'Post deleted successfully.';
        }
    }
}

// Load post for editing
if ($_GET['edit_post'] ?? '') {
    $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$_GET['edit_post']]);
    $editingPost = $stmt->fetch();
    if ($editingPost) {
        $editingPost = format_post_row($editingPost);
    }
}
