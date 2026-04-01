<?php

declare(strict_types=1);

/**
 * ENMA Views Handler
 * 
 * Handles all page views-related operations
 */

/**
 * Get views dashboard data
 */
function enma_get_views_data(int $days): array
{
    global $pdo;
    return get_views_dashboard($pdo, $days);
}

/**
 * Get overview statistics
 */
function enma_get_overview_stats(): array
{
    global $pdo;
    
    $views30dSql = DB_DRIVER === 'mysql'
        ? "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)"
        : "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= date('now','-29 day')";
    
    return [
        'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'categories' => (int) $pdo->query('SELECT COUNT(DISTINCT category_slug) FROM products')->fetchColumn(),
        'missing_tags' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE affiliate_url NOT LIKE '%tag=%'")->fetchColumn(),
        'missing_images' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE image_url IS NULL OR image_url = ''")->fetchColumn(),
        'views_30d' => (int) $pdo->query($views30dSql)->fetchColumn(),
        'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    ];
}
