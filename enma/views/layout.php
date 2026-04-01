<?php

declare(strict_types=1);

/**
 * ENMA View: Layout (Header, Footer, Navigation)
 */

if (!function_exists('enma_render_header')) {
    function enma_render_header(): void
    {
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background: #f2f5f8; }
        .wrap { max-width: 980px; margin: 20px auto; padding: 0 14px 28px; }
        .box { background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.08); padding: 16px; margin-bottom: 16px; }
        input, textarea, select { width: 100%; box-sizing: border-box; margin: 6px 0 12px; padding: 10px; }
        .btn { background: #0b1f3a; color: #fff; border: 0; padding: 10px 14px; border-radius: 6px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e8edf3; padding: 10px 8px; font-size: 14px; }
        .error { background: #ffe5e5; color: #8a1f1f; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .ok { background: #e4f8ea; color: #165f2b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .toplink { display: inline-block; margin-bottom: 12px; }
        .tabs { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
        .tab { display:inline-block; text-decoration:none; padding:8px 12px; border-radius:999px; border:1px solid #d5deea; background:#fff; color:#1d3556; font-weight:700; font-size:13px; }
        .tab.active { background:#0b1f3a; color:#fff; border-color:#0b1f3a; }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:10px; margin-bottom:14px; }
        .stat-k { font-size:12px; color:#4a5b73; margin-bottom:4px; }
        .stat-v { font-size:24px; font-weight:800; color:#0b1f3a; }
        .muted { color:#55647a; font-size:13px; }
        .toolbar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar .field { max-width:280px; }
        .empty { padding:14px; border:1px dashed #d8e2ee; border-radius:8px; color:#5d6f86; background:#f9fbfe; }
    </style>
</head>
<body>
<div class="wrap">
    <a class="toplink" href="<?= e(url('/')) ?>">Back to site</a>
        <?php
    }
}

if (!function_exists('enma_render_footer')) {
    function enma_render_footer(): void
    {
        ?>
</div>
</body>
</html>
        <?php
    }
}

if (!function_exists('enma_render_errors')) {
    function enma_render_errors(array $errors): void
    {
        foreach ($errors as $error):
            ?>
            <div class="error"><?= e($error) ?></div>
            <?php
        endforeach;
    }
}

if (!function_exists('enma_render_flash')) {
    function enma_render_flash(?string $flash): void
    {
        if ($flash !== null):
            ?>
            <div class="ok"><?= e($flash) ?></div>
            <?php
        endif;
    }
}

if (!function_exists('enma_render_tabs')) {
    function enma_render_tabs(string $activeTab, bool $authenticated): void
    {
        $tabs = [
            'overview' => 'Overview',
            'products' => 'Products',
            'guides' => 'Guides',
            'views' => 'Views',
            'maintenance' => 'Maintenance',
        ];
        
        if (!$authenticated) {
            return;
        }
        ?>
        <nav class="tabs">
            <?php foreach ($tabs as $tab => $label): ?>
                <a class="tab <?= $tab === $activeTab ? 'active' : '' ?>" href="?tab=<?= $tab ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
            <form method="post" style="display:inline;margin-left:auto;">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="tab" type="submit" style="background:#dc3545;color:#fff;border-color:#dc3545;">Logout</button>
            </form>
        </nav>
        <?php
    }
}
