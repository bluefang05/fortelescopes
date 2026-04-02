<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/index.php'; // Import upload handlers

$errors = [];
$flash = null;

// Check authentication
if (empty($_SESSION['admin_ok'])) {
    header('Location: ' . url('/enma/'));
    exit;
}

// Handle post actions
$action = $_POST['action'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid request token.';
    } else {
        if ($action === 'save_post') {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $slug = trim((string) ($_POST['slug'] ?? ''));
            $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
            $contentHtml = $_POST['content_html'] ?? ''; // HTML from WYSIWYG
            
            // Handle featured image upload or URL
            $featuredImage = trim((string) ($_POST['featured_image_url'] ?? ''));
            if (!empty($_FILES['featured_image']['name'])) {
                $uploadedImage = enma_handle_blog_image_upload('featured_image', $errors);
                if ($uploadedImage) {
                    $featuredImage = $uploadedImage;
                }
            }
            
            $status = $_POST['status'] ?? 'draft';
            $metaTitle = trim((string) ($_POST['meta_title'] ?? ''));
            $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
            
            if ($title === '') {
                $errors[] = 'Title is required.';
            }
            if ($slug === '') {
                $slug = slugify($title);
            }
            if (!in_array($status, ['draft', 'published'], true)) {
                $status = 'draft';
            }
            
            if ($errors === []) {
                $now = now_iso();
                
                if ($id > 0) {
                    // Update existing post
                    $stmt = $pdo->prepare(
                        'UPDATE posts SET
                            title = :title,
                            slug = :slug,
                            excerpt = :excerpt,
                            content_html = :content_html,
                            featured_image = :featured_image,
                            status = :status,
                            meta_title = :meta_title,
                            meta_description = :meta_description,
                            updated_at = :updated_at
                         WHERE id = :id'
                    );
                    $stmt->execute([
                        ':title' => $title,
                        ':slug' => $slug,
                        ':excerpt' => $excerpt,
                        ':content_html' => $contentHtml,
                        ':featured_image' => $featuredImage ?: null,
                        ':status' => $status,
                        ':meta_title' => $metaTitle ?: null,
                        ':meta_description' => $metaDescription ?: null,
                        ':updated_at' => $now,
                        ':id' => $id,
                    ]);
                    
                    if ($status === 'published') {
                        $publishStmt = $pdo->prepare(
                            'UPDATE posts SET published_at = COALESCE(published_at, :published_at) WHERE id = :id'
                        );
                        $publishStmt->execute([
                            ':published_at' => $now,
                            ':id' => $id,
                        ]);
                    }
                    
                    $flash = 'Blog post implemented successfully.';
                } else {
                    // Create new post
                    $stmt = $pdo->prepare(
                        'INSERT INTO posts (
                            slug, title, excerpt, content_html, featured_image,
                            status, meta_title, meta_description,
                            created_at, updated_at, published_at
                         ) VALUES (
                            :slug, :title, :excerpt, :content_html, :featured_image,
                            :status, :meta_title, :meta_description,
                            :created_at, :updated_at, :published_at
                         )'
                    );
                    
                    $publishedAt = ($status === 'published') ? $now : null;
                    
                    $stmt->execute([
                        ':slug' => $slug,
                        ':title' => $title,
                        ':excerpt' => $excerpt,
                        ':content_html' => $contentHtml,
                        ':featured_image' => $featuredImage ?: null,
                        ':status' => $status,
                        ':meta_title' => $metaTitle ?: null,
                        ':meta_description' => $metaDescription ?: null,
                        ':created_at' => $now,
                        ':updated_at' => $now,
                        ':published_at' => $publishedAt,
                    ]);
                    
                    $flash = 'Blog post implemented successfully.';
                }
                
                header('Location: ' . url('/enma/blog.php?tab=posts'));
                exit;
            }
        } elseif ($action === 'delete_post') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('DELETE FROM posts WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $flash = 'Post deleted successfully.';
                header('Location: ' . url('/enma/blog.php?tab=posts'));
                exit;
            }
        }
    }
}

// Get current tab
$activeTab = $_GET['tab'] ?? 'posts';
if (!in_array($activeTab, ['posts', 'new', 'edit'], true)) {
    $activeTab = 'posts';
}

// Fetch posts list
$posts = [];
if ($activeTab === 'posts') {
    $posts = $pdo->query('SELECT * FROM posts ORDER BY created_at DESC')->fetchAll();
}

// Fetch single post for edit
$post = null;
if ($activeTab === 'edit') {
    $postId = (int) ($_GET['id'] ?? 0);
    if ($postId > 0) {
        $stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id');
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();
        if (!$post) {
            $errors[] = 'Post not found.';
            $activeTab = 'posts';
        }
    } else {
        $activeTab = 'posts';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Management | Enma Admin</title>
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 { margin: 0; color: #1a1a2e; }
        .back-link {
            display: inline-block;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .back-link:hover { background: #5a6268; }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 10px;
        }
        .tab {
            padding: 10px 20px;
            background: #e9ecef;
            border: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            color: #495057;
        }
        .tab.active {
            background: #007bff;
            color: white;
        }
        .tab:hover:not(.active) { background: #dee2e6; }
        .box {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .flash {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        th { background: #f8f9fa; font-weight: 600; }
        tr:hover { background: #f8f9fa; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
        }
        .btn:hover { background: #0056b3; }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover { background: #c82333; }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        input[type="text"],
        input[type="url"],
        textarea,
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }
        textarea { resize: vertical; min-height: 100px; }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        .help-text {
            font-size: 13px;
            color: #6c757d;
            margin-top: 4px;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-published {
            background: #d4edda;
            color: #155724;
        }
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📝 Blog Management</h1>
        <a href="<?= e(url('/enma/')) ?>" class="back-link">← Back to Dashboard</a>
    </div>

    <?php if ($flash): ?>
        <div class="flash"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if ($errors !== []): ?>
        <?php foreach ($errors as $error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="tabs">
        <a href="<?= e(url('/enma/blog.php?tab=posts')) ?>" class="tab <?= $activeTab === 'posts' ? 'active' : '' ?>">All Posts</a>
        <a href="<?= e(url('/enma/blog.php?tab=new')) ?>" class="tab <?= $activeTab === 'new' ? 'active' : '' ?>">New Post</a>
    </div>

    <?php if ($activeTab === 'posts'): ?>
        <div class="box">
            <h2>All Blog Posts</h2>
            <?php if ($posts === []): ?>
                <p style="color: #6c757d;">No posts yet. <a href="<?= e(url('/enma/blog.php?tab=new')) ?>">Create your first post</a>.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $p): ?>
                            <tr>
                                <td><?= e($p['title']) ?></td>
                                <td><code><?= e($p['slug']) ?></code></td>
                                <td>
                                    <span class="status-badge status-<?= e($p['status']) ?>">
                                        <?= e(ucfirst($p['status'])) ?>
                                    </span>
                                </td>
                                <td><?= e(date('Y-m-d', strtotime($p['created_at']))) ?></td>
                                <td class="actions">
                                    <a href="<?= e(url('/enma/blog.php?tab=edit&id=' . $p['id'])) ?>" class="btn btn-sm">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($activeTab === 'new' || $activeTab === 'edit'): ?>
        <div class="box">
            <h2><?= $activeTab === 'new' ? 'Create New Post' : 'Edit Post' ?></h2>
            <form method="post">
                <input type="hidden" name="action" value="save_post">
                <input type="hidden" name="id" value="<?= $post ? (int) $post['id'] : 0 ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                <div class="form-group">
                    <label for="title">Title *</label>
                    <input type="text" id="title" name="title" required 
                           value="<?= e($post['title'] ?? '') ?>" 
                           placeholder="Enter post title">
                </div>

                <div class="form-group">
                    <label for="slug">Slug</label>
                    <input type="text" id="slug" name="slug" 
                           value="<?= e($post['slug'] ?? '') ?>" 
                           placeholder="auto-generated-from-title">
                    <div class="help-text">Leave empty to auto-generate from title. Use lowercase letters, numbers, and hyphens.</div>
                </div>

                <div class="form-group">
                    <label for="excerpt">Excerpt</label>
                    <textarea id="excerpt" name="excerpt" rows="3" 
                              placeholder="Brief summary of the post (optional)"><?= e($post['excerpt'] ?? '') ?></textarea>
                    <div class="help-text">Short description shown in post listings.</div>
                </div>

                <div class="form-group">
                    <label for="content_html">Content</label>
                    <textarea id="content_html" name="content_html" rows="20"><?= e($post['content_html'] ?? '') ?></textarea>
                    <div class="help-text">Use the editor above to format your content.</div>
                </div>

                <div class="form-group">
                    <label for="featured_image_upload">Featured Image</label>
                    <?php if (!empty($post['featured_image'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?= e($post['featured_image']) ?>" alt="Current image" style="max-width: 300px; max-height: 200px; border-radius: 8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="featured_image_upload" name="featured_image" accept="image/*">
                    <div class="help-text">Upload a new image or provide a URL below.</div>
                    
                    <label for="featured_image_url" style="margin-top: 15px;">Or Image URL</label>
                    <input type="url" id="featured_image_url" name="featured_image_url" 
                           value="<?= !empty($_FILES['featured_image']['name']) ? '' : e($post['featured_image'] ?? '') ?>" 
                           placeholder="https://example.com/image.jpg">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?= ($post['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    </select>
                </div>

                <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">

                <h3>SEO Settings</h3>

                <div class="form-group">
                    <label for="meta_title">Meta Title</label>
                    <input type="text" id="meta_title" name="meta_title" 
                           value="<?= e($post['meta_title'] ?? '') ?>" 
                           placeholder="SEO title (defaults to post title)">
                </div>

                <div class="form-group">
                    <label for="meta_description">Meta Description</label>
                    <textarea id="meta_description" name="meta_description" rows="2" 
                              placeholder="SEO description for search engines"><?= e($post['meta_description'] ?? '') ?></textarea>
                </div>

                <div class="actions">
                    <button type="submit" class="btn"><?= $activeTab === 'new' ? 'Create Post' : 'Update Post' ?></button>
                    <a href="<?= e(url('/enma/blog.php?tab=posts')) ?>" class="btn" style="background:#6c757d;">Cancel</a>
                </div>
            </form>
        </div>

        <script>
            tinymce.init({
                selector: '#content_html',
                height: 500,
                menubar: true,
                plugins: [
                    'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
                    'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
                    'insertdatetime', 'media', 'table', 'help', 'wordcount'
                ],
                toolbar: 'undo redo | blocks | ' +
                    'bold italic backcolor | alignleft aligncenter ' +
                    'alignright alignjustify | bullist numlist outdent indent | ' +
                    'removeformat | image link media table | code | help',
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.6; }',
                branding: false,
                promotion: false
            });
        </script>
    <?php endif; ?>
</div>
</body>
</html>
