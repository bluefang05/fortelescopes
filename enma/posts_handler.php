<?php

declare(strict_types=1);

/**
 * Posts handler for ENMA admin panel
 * Handles CRUD operations for posts
 */

if (!$authenticated) {
    return;
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

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO posts (
                    slug, title, excerpt, content_html, featured_image, post_type, status, meta_title, meta_description,
                    created_at, updated_at, published_at
                 ) VALUES (
                    :slug, :title, :excerpt, :content_html, :featured_image, :post_type, :status, :meta_title, :meta_description,
                    :created_at, :updated_at, :published_at
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
                ':created_at' => $now,
                ':updated_at' => $now,
                ':published_at' => $now,
            ]);

            $newPostId = (int) $pdo->lastInsertId();
            enma_record_activity($pdo, 'post.create', 'post', $newPostId, [
                'title' => $title,
                'slug' => $slug,
                'post_type' => $postType,
                'meta_title' => $metaTitle,
            ]);
            $postPath = $postType === 'guide' ? '/' . $slug : '/blog/' . $slug;
            $indexNowResult = indexnow_submit_urls([absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')]);
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
                ':updated_at' => $now,
                ':id' => $id
            ]);

            enma_record_activity($pdo, 'post.update', 'post', $id, [
                'title' => $title,
                'post_type' => $postType,
                'meta_title' => $metaTitle,
            ]);
            $slugStmt = $pdo->prepare('SELECT slug, post_type FROM posts WHERE id = :id LIMIT 1');
            $slugStmt->execute([':id' => $id]);
            $savedPost = $slugStmt->fetch();
            $savedSlug = trim((string) ($savedPost['slug'] ?? ''));
            $savedType = trim((string) ($savedPost['post_type'] ?? $postType));
            if ($savedSlug !== '') {
                $postPath = $savedType === 'guide' ? '/' . $savedSlug : '/blog/' . $savedSlug;
                $indexNowResult = indexnow_submit_urls([absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')]);
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
            $titleStmt = $pdo->prepare('SELECT title, slug, post_type FROM posts WHERE id = :id LIMIT 1');
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
            if ($deletedSlug !== '') {
                $postPath = $deletedType === 'guide' ? '/' . $deletedSlug : '/blog/' . $deletedSlug;
                $indexNowResult = indexnow_submit_urls([absolute_url($postPath), absolute_url('/blog'), absolute_url('/guides')]);
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
