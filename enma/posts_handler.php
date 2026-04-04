<?php

declare(strict_types=1);

/**
 * Posts handler for ENMA admin panel
 * Handles CRUD operations for posts
 */

if (!$authenticated) {
    return;
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
                    slug, title, excerpt, content_html, featured_image, post_type, status,
                    created_at, updated_at, published_at
                 ) VALUES (
                    :slug, :title, :excerpt, :content_html, :featured_image, :post_type, :status,
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
                ':created_at' => $now,
                ':updated_at' => $now,
                ':published_at' => $now,
            ]);

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
                    updated_at = :updated_at 
                WHERE id = :id'
            );

            $stmt->execute([
                ':title' => $title,
                ':excerpt' => $excerpt,
                ':content_html' => $content,
                ':featured_image' => $featuredImage,
                ':post_type' => $postType,
                ':updated_at' => $now,
                ':id' => $id
            ]);

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
            $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
            $stmt->execute([':id' => $id]);
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
