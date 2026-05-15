<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptDir = rtrim($scriptDir, '/');

if ($scriptDir !== '' && $scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
    $path = substr($path, strlen($scriptDir));
    $path = $path === '' ? '/' : $path;
}

$path = trim($path, '/');
$segments = $path === '' ? [] : explode('/', $path);
$requestPath = '/' . ltrim($path, '/');
if ($requestPath === '') {
    $requestPath = '/';
}
$guideDetailCanonicalMode = 'root';

if (count($segments) === 1 && $segments[0] === 'enma') {
    require __DIR__ . '/enma/index.php';
    exit;
}

$buildRedirectUrl = static function (string $targetPath): string {
    $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));
    return url($targetPath . ($query !== '' ? '?' . $query : ''));
};

if (count($segments) === 2 && $segments[0] === 'category') {
    $legacyCategoryPath = '/category/' . slugify((string) $segments[1]);
    $canonicalCategoryPath = canonical_public_path($pdo, $legacyCategoryPath, ['guide_detail_mode' => $guideDetailCanonicalMode]);
    if ($canonicalCategoryPath !== $legacyCategoryPath) {
        header('Location: ' . $buildRedirectUrl($canonicalCategoryPath), true, 301);
        exit;
    }
}

$suspiciousReason = suspicious_path_reason($requestPath);

$guideCanonicalAliases = [
    'best-beginner-telescopes-for-first-time-stargazers-in-2026' => 'best-beginner-telescopes',
    'best-beginner-telescopes-2026' => 'best-beginner-telescopes',
    'best-beginner-telescopes-for-stargazing-in-2026-your-first-steps-to-the-cosmos' => 'best-beginner-telescopes',
    'best-beginner-telescopes-for-exploring-the-night-sky' => 'best-beginner-telescopes',
    'best-celestron-telescopes-for-beginners-in-2026-every-budget-covered' => 'best-beginner-telescopes',
    'telescopes-for-viewing-planets-and-the-moon-under-300' => 'best-telescopes-under-500',
];

if (count($segments) === 1) {
    $requestedSlug = slugify($segments[0]);
    if (isset($guideCanonicalAliases[$requestedSlug])) {
        header('Location: ' . url('/' . $guideCanonicalAliases[$requestedSlug]), true, 301);
        exit;
    }
}

if (count($segments) === 2 && $segments[0] === 'blog') {
    $requestedSlug = slugify($segments[1]);
    if (isset($guideCanonicalAliases[$requestedSlug])) {
        header('Location: ' . url('/' . $guideCanonicalAliases[$requestedSlug]), true, 301);
        exit;
    }
    $blogCanonicalAliases = [
        'seestar-s50-vs-dwarf-ii-3-best-smart-telescope-for-beginners-2' => 'seestar-s50-vs-dwarf-ii-3-best-smart-telescope-for-beginners',
        'best-telescopes-for-planets-and-moon-2024-buyer-s-guide' => 'best-telescopes-for-planets-and-moon-2026-buyer-s-guide',
        'best-beginner-telescopes-for-stargazing-in-2026-your-first-steps-to-the-cosmos' => '../best-beginner-telescopes',
        'best-beginner-telescopes-for-exploring-the-night-sky' => '../best-beginner-telescopes',
        'dobsonian-vs-refractor-which-beginner-telescope-should-you-buy-in-2026' => '../best-beginner-telescopes',
    ];
    if (isset($blogCanonicalAliases[$requestedSlug])) {
        $target = (string) $blogCanonicalAliases[$requestedSlug];
        if (str_starts_with($target, '../')) {
            header('Location: ' . url('/' . ltrim(substr($target, 3), '/')), true, 301);
        } else {
            header('Location: ' . url('/blog/' . $target), true, 301);
        }
        exit;
    }
}

$pageTitle = APP_NAME;
$template = __DIR__ . '/templates/home.php';
$data = [];
$meta = site_meta_defaults();
$meta['robots'] = 'index,follow';
$canonicalPath = '/';
$jsonLd = [];
$breadcrumbs = [
    ['name' => 'Home', 'url' => absolute_url('/')],
];
$viewPageType = 'home';
$viewPageSlug = '';
$viewProductId = 0;
$canPreviewDrafts = frontend_admin_preview_enabled();
$isDraftPreview = false;
$draftPreviewNotice = '';
$jsonLd[] = json_ld_for_organization();
$jsonLd[] = json_ld_for_website();

if (count($segments) === 1 && $segments[0] === 'go') {
    $target = trim((string) ($_GET['u'] ?? ''));
    $productId = (int) ($_GET['pid'] ?? 0);
    $fromPath = trim((string) ($_GET['from'] ?? '/'));
    $target = amazon_affiliate_url($target);

    if ($target === '' || filter_var($target, FILTER_VALIDATE_URL) === false) {
        header('Location: ' . url('/'), true, 302);
        exit;
    }

    $host = (string) (parse_url($target, PHP_URL_HOST) ?? '');
    if (!is_amazon_host($host)) {
        header('Location: ' . url('/'), true, 302);
        exit;
    }

    try {
        track_outbound_click($pdo, $target, $productId, $fromPath);
    } catch (Throwable $e) {
        // Do not block redirect if click tracking fails.
    }

    header('Location: ' . $target, true, 302);
    exit;
}

if (count($segments) === 1 && $segments[0] === 'review') {
    header('Location: ' . url('/reviews'), true, 301);
    exit;
}

if (count($segments) === 2 && $segments[0] === 'review') {
    header('Location: ' . url('/reviews/' . slugify($segments[1])), true, 301);
    exit;
}

if (count($segments) === 1 && $segments[0] === 'learn') {
    header('Location: ' . url('/blog'), true, 301);
    exit;
}

if (count($segments) === 2 && $segments[0] === 'learn') {
    header('Location: ' . url('/blog/' . slugify($segments[1])), true, 301);
    exit;
}

if (count($segments) === 1 && $segments[0] === 'robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\n";
    echo "Allow: /\n";
    echo "Disallow: /admin/\n";
    echo "Disallow: /enma/\n";
    echo "Disallow: /dev/\n\n";
    echo 'Sitemap: ' . absolute_url('/sitemap.xml') . "\n";
    exit;
}

if (count($segments) === 1 && $segments[0] === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    echo render_sitemap_xml(get_sitemap_entries($pdo));
    exit;
}

if ($segments === []) {
    $data['products'] = get_recent_products($pdo, 18);
    $data['telescopes'] = get_products_by_category($pdo, 'telescopes', 6);
    $data['accessories'] = get_products_by_category($pdo, 'accessories', 6);
    $data['home_hero_image'] = site_setting_get($pdo, 'home_hero_image', '');
    $data['home_hero_image_2x'] = site_setting_get($pdo, 'home_hero_image_2x', '');
    $data['home_hero_alt'] = site_setting_get($pdo, 'home_hero_alt', '');
    $data['home_hero_title'] = site_setting_get($pdo, 'home_hero_title', '');
    $data['home_hero_subtitle'] = site_setting_get($pdo, 'home_hero_subtitle', '');
    $data['home_hero_eyebrow'] = site_setting_get($pdo, 'home_hero_eyebrow', 'Astronomy Affiliate Guide');
    $data['home_hero_cta_label'] = site_setting_get($pdo, 'home_hero_cta_label', '');
    $data['home_hero_cta_url'] = site_setting_get($pdo, 'home_hero_cta_url', '');
    $data['home_hero_overlay'] = site_setting_get($pdo, 'home_hero_overlay', '55');
    $data['home_hero_text_position'] = site_setting_get($pdo, 'home_hero_text_position', 'center');
    $data['home_hero_overlay_strength'] = site_setting_get($pdo, 'home_hero_overlay_strength', 'dark');
    $data['home_hero_layout_size'] = site_setting_get($pdo, 'home_hero_layout_size', 'full');
    $data['home_promo_tile_1_image'] = site_setting_get($pdo, 'home_promo_tile_1_image', '');
    $data['home_promo_tile_1_title'] = site_setting_get($pdo, 'home_promo_tile_1_title', '');
    $data['home_promo_tile_1_eyebrow'] = site_setting_get($pdo, 'home_promo_tile_1_eyebrow', '');
    $data['home_promo_tile_1_subtitle'] = site_setting_get($pdo, 'home_promo_tile_1_subtitle', '');
    $data['home_promo_tile_1_cta_label'] = site_setting_get($pdo, 'home_promo_tile_1_cta_label', '');
    $data['home_promo_tile_1_cta_url'] = site_setting_get($pdo, 'home_promo_tile_1_cta_url', '');
    $data['home_promo_tile_1_text_position'] = site_setting_get($pdo, 'home_promo_tile_1_text_position', 'bottom-left');
    $data['home_promo_tile_1_overlay_strength'] = site_setting_get($pdo, 'home_promo_tile_1_overlay_strength', 'medium');
    $data['home_promo_tile_1_layout_size'] = site_setting_get($pdo, 'home_promo_tile_1_layout_size', 'half');
    $data['home_promo_tile_2_image'] = site_setting_get($pdo, 'home_promo_tile_2_image', '');
    $data['home_promo_tile_2_title'] = site_setting_get($pdo, 'home_promo_tile_2_title', '');
    $data['home_promo_tile_2_eyebrow'] = site_setting_get($pdo, 'home_promo_tile_2_eyebrow', '');
    $data['home_promo_tile_2_subtitle'] = site_setting_get($pdo, 'home_promo_tile_2_subtitle', '');
    $data['home_promo_tile_2_cta_label'] = site_setting_get($pdo, 'home_promo_tile_2_cta_label', '');
    $data['home_promo_tile_2_cta_url'] = site_setting_get($pdo, 'home_promo_tile_2_cta_url', '');
    $data['home_promo_tile_2_text_position'] = site_setting_get($pdo, 'home_promo_tile_2_text_position', 'bottom-left');
    $data['home_promo_tile_2_overlay_strength'] = site_setting_get($pdo, 'home_promo_tile_2_overlay_strength', 'medium');
    $data['home_promo_tile_2_layout_size'] = site_setting_get($pdo, 'home_promo_tile_2_layout_size', 'half');

    $data['home_banner_1_image'] = site_setting_get($pdo, 'home_banner_1_image', '');
    $data['home_banner_1_eyebrow'] = site_setting_get($pdo, 'home_banner_1_eyebrow', '');
    $data['home_banner_1_title'] = site_setting_get($pdo, 'home_banner_1_title', '');
    $data['home_banner_1_subtitle'] = site_setting_get($pdo, 'home_banner_1_subtitle', '');
    $data['home_banner_1_cta_label'] = site_setting_get($pdo, 'home_banner_1_cta_label', '');
    $data['home_banner_1_cta_url'] = site_setting_get($pdo, 'home_banner_1_cta_url', '');
    $data['home_banner_1_text_position'] = site_setting_get($pdo, 'home_banner_1_text_position', 'left');
    $data['home_banner_1_overlay_strength'] = site_setting_get($pdo, 'home_banner_1_overlay_strength', 'medium');
    $data['home_banner_1_layout_size'] = site_setting_get($pdo, 'home_banner_1_layout_size', 'full');

    $data['home_banner_2_image'] = site_setting_get($pdo, 'home_banner_2_image', '');
    $data['home_banner_2_eyebrow'] = site_setting_get($pdo, 'home_banner_2_eyebrow', '');
    $data['home_banner_2_title'] = site_setting_get($pdo, 'home_banner_2_title', '');
    $data['home_banner_2_subtitle'] = site_setting_get($pdo, 'home_banner_2_subtitle', '');
    $data['home_banner_2_cta_label'] = site_setting_get($pdo, 'home_banner_2_cta_label', '');
    $data['home_banner_2_cta_url'] = site_setting_get($pdo, 'home_banner_2_cta_url', '');
    $data['home_banner_2_text_position'] = site_setting_get($pdo, 'home_banner_2_text_position', 'left');
    $data['home_banner_2_overlay_strength'] = site_setting_get($pdo, 'home_banner_2_overlay_strength', 'medium');
    $data['home_banner_2_layout_size'] = site_setting_get($pdo, 'home_banner_2_layout_size', 'full');

    $data['home_goal_1_label'] = site_setting_get($pdo, 'home_goal_1_label', 'First Telescope');
    $data['home_goal_1_url'] = site_setting_get($pdo, 'home_goal_1_url', '/best-beginner-telescopes');
    $data['home_goal_2_label'] = site_setting_get($pdo, 'home_goal_2_label', 'Budget Under $500');
    $data['home_goal_2_url'] = site_setting_get($pdo, 'home_goal_2_url', '/best-telescopes-under-500');
    $data['home_goal_3_label'] = site_setting_get($pdo, 'home_goal_3_label', 'Upgrade Accessories');
    $data['home_goal_3_url'] = site_setting_get($pdo, 'home_goal_3_url', '/best-telescope-accessories');
    $data['home_goal_4_label'] = site_setting_get($pdo, 'home_goal_4_label', 'Astrophotography Path');
    $data['home_goal_4_url'] = site_setting_get($pdo, 'home_goal_4_url', '/guides');
    $data['home_h1_text'] = site_setting_get($pdo, 'home_h1_text', '');

    $featuredIdsRaw = site_setting_get($pdo, 'home_featured_product_ids', '');
    $featuredIds = [];
    foreach (preg_split('/[\s,]+/', $featuredIdsRaw) ?: [] as $token) {
        $id = (int) trim((string) $token);
        if ($id > 0) {
            $featuredIds[] = $id;
        }
    }
    $featuredIds = array_values(array_unique($featuredIds));
    $featuredProducts = [];
    if ($featuredIds !== []) {
        $placeholders = implode(',', array_fill(0, count($featuredIds), '?'));
        $stmt = $pdo->prepare('SELECT * FROM products WHERE status = "published" AND id IN (' . $placeholders . ')');
        $stmt->execute($featuredIds);
        $rows = $stmt->fetchAll();
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) ($row['id'] ?? 0)] = $row;
        }
        foreach ($featuredIds as $id) {
            if (isset($byId[$id])) {
                $featuredProducts[] = $byId[$id];
            }
            if (count($featuredProducts) >= 4) {
                break;
            }
        }
    }
    if ($featuredProducts === []) {
        $featuredProducts = array_slice($data['products'], 0, 4);
    }
    $data['home_featured_products'] = $featuredProducts;

    $pageTitle = 'Best Beginner Telescopes, Astronomy Gear Reviews & Stargazing Guides | ' . APP_NAME;
    $meta['description'] = 'Compare beginner telescopes, telescope accessories, and practical stargazing guides built to help new observers choose the right gear.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $jsonLd[] = json_ld_for_itemlist($data['products'], 'Featured Telescope and Astronomy Products');
    $jsonLd[] = json_ld_for_faq(seo_faq_for_page('home'));
} elseif (count($segments) === 2 && $segments[0] === 'category') {
    $categorySlug = slugify($segments[1]);
    $categoryPerPage = 12;
    $requestedCategoryPage = max(1, (int) ($_GET['page'] ?? 1));
    $categoryTotalItems = get_products_count($pdo, $categorySlug);
    $categoryTotalPages = max(1, (int) ceil($categoryTotalItems / $categoryPerPage));
    $categoryCurrentPage = min($requestedCategoryPage, $categoryTotalPages);
    $products = get_products_by_category_paginated($pdo, $categorySlug, $categoryCurrentPage, $categoryPerPage);
    $categoryBasePath = canonical_public_path($pdo, '/category/' . $categorySlug, ['guide_detail_mode' => $guideDetailCanonicalMode]);
    $canonicalPath = $categoryBasePath;

    if ($products === []) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Category Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested category does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['products'] = $products;
        $data['categoryName'] = $products[0]['category_name'];
        $data['categorySlug'] = $categorySlug;
        $data['category_pagination'] = [
            'page' => $categoryCurrentPage,
            'per_page' => $categoryPerPage,
            'total_items' => $categoryTotalItems,
            'total_pages' => $categoryTotalPages,
            'has_prev' => $categoryCurrentPage > 1,
            'has_next' => $categoryCurrentPage < $categoryTotalPages,
            'prev_page' => max(1, $categoryCurrentPage - 1),
            'next_page' => min($categoryTotalPages, $categoryCurrentPage + 1),
        ];
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        if ($categoryCurrentPage > 1) {
            $pageTitle = $products[0]['category_name'] . ' - Page ' . $categoryCurrentPage . ' | ' . APP_NAME;
        }
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Browse ' . $products[0]['category_name'] . ' recommendations, buying tips, and practical picks for astronomy sessions.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name'] . ' recommendations');
        $jsonLd[] = json_ld_for_faq(seo_faq_for_page('category', [
            'slug' => $categorySlug,
            'name' => $products[0]['category_name'],
        ]));
        $breadcrumbs[] = ['name' => 'Categories', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
        if ($categoryCurrentPage > 1) {
            $meta['prev_url'] = absolute_url($canonicalPath . ($categoryCurrentPage === 2 ? '' : '?page=' . ($categoryCurrentPage - 1)));
            $canonicalPath .= '?page=' . $categoryCurrentPage;
        }
        if ($categoryCurrentPage < $categoryTotalPages) {
            $meta['next_url'] = absolute_url($categoryBasePath . '?page=' . ($categoryCurrentPage + 1));
        }
    }
} elseif (count($segments) === 1 && in_array($segments[0], ['telescopes', 'accessories'], true)) {
    $categorySlug = $segments[0];
    $categoryPerPage = 12;
    $requestedCategoryPage = max(1, (int) ($_GET['page'] ?? 1));
    $categoryTotalItems = get_products_count($pdo, $categorySlug);
    $categoryTotalPages = max(1, (int) ceil($categoryTotalItems / $categoryPerPage));
    $categoryCurrentPage = min($requestedCategoryPage, $categoryTotalPages);
    $products = get_products_by_category_paginated($pdo, $categorySlug, $categoryCurrentPage, $categoryPerPage);
    $canonicalPath = '/' . $categorySlug;

    if ($products === []) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Category Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested category does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['products'] = $products;
        $data['categoryName'] = $products[0]['category_name'];
        $data['categorySlug'] = $categorySlug;
        $data['category_pagination'] = [
            'page' => $categoryCurrentPage,
            'per_page' => $categoryPerPage,
            'total_items' => $categoryTotalItems,
            'total_pages' => $categoryTotalPages,
            'has_prev' => $categoryCurrentPage > 1,
            'has_next' => $categoryCurrentPage < $categoryTotalPages,
            'prev_page' => max(1, $categoryCurrentPage - 1),
            'next_page' => min($categoryTotalPages, $categoryCurrentPage + 1),
        ];
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        if ($categoryCurrentPage > 1) {
            $pageTitle = $products[0]['category_name'] . ' - Page ' . $categoryCurrentPage . ' | ' . APP_NAME;
        }
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Compare ' . strtolower($products[0]['category_name']) . ' with practical buying advice, use-case notes, and beginner-friendly recommendations.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name']);
        $jsonLd[] = json_ld_for_faq(seo_faq_for_page('category', [
            'slug' => $categorySlug,
            'name' => $products[0]['category_name'],
        ]));
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
        if ($categoryCurrentPage > 1) {
            $meta['prev_url'] = absolute_url($canonicalPath . ($categoryCurrentPage === 2 ? '' : '?page=' . ($categoryCurrentPage - 1)));
            $canonicalPath .= '?page=' . $categoryCurrentPage;
        }
        if ($categoryCurrentPage < $categoryTotalPages) {
            $meta['next_url'] = absolute_url('/' . $categorySlug . '?page=' . ($categoryCurrentPage + 1));
        }
    }
} elseif (count($segments) === 2 && $segments[0] === 'product') {
    $productSlug = slugify($segments[1]);
    $product = find_product_by_slug($pdo, $productSlug);
    $canonicalPath = '/product/' . $productSlug;

    if ($product === null) {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Product Not Found | ' . APP_NAME;
        $meta['description'] = 'Requested product does not exist.';
        $meta['robots'] = 'noindex,follow';
    } else {
        $data['product'] = $product;
        $viewPageType = 'product';
        $viewPageSlug = $productSlug;
        $viewProductId = (int) ($product['id'] ?? 0);
        $template = __DIR__ . '/templates/product.php';
        $pageTitle = $product['title'] . ' | ' . APP_NAME;
        $meta['description'] = substr($product['description'], 0, 150);
        if ($meta['description'] === '') {
            $meta['description'] = 'Detailed recommendation for ' . $product['title'] . '.';
        }
        if ($product['image_url'] !== '') {
            $meta['image'] = $product['image_url'];
        } else {
            $meta['image'] = absolute_url('/assets/logo/1024.png');
        }
        $meta['type'] = 'product';
        $jsonLd[] = json_ld_for_product($product);
        $jsonLd[] = json_ld_for_faq(seo_faq_for_page('product', $product));
        $breadcrumbs[] = ['name' => 'Products', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $product['title'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 1 && ($legacyGuide = find_post_by_slug($pdo, slugify($segments[0]), $canPreviewDrafts)) && ($legacyGuide['post_type'] ?? '') === 'guide') {
    $guideSlug = slugify($segments[0]);
    $guide = $legacyGuide;
    $viewPageType = 'guide';
    $viewPageSlug = $guideSlug;
    $guideProducts = get_products_by_category($pdo, $guide['focus'] ?? 'telescopes', 6);
    if ($guideProducts === []) {
        $guideProducts = get_recent_products($pdo, 6);
    }
    $isDraftPreview = $canPreviewDrafts && (($guide['status'] ?? 'published') !== 'published');
    if ($isDraftPreview) {
        $draftPreviewNotice = 'Preview privado: esta guía está en BORRADOR. Solo es visible para tu sesión admin.';
        $meta['robots'] = 'noindex,nofollow';
    }
    $data['guide'] = $guide;
    $data['guideProducts'] = $guideProducts;
    $data['otherGuides'] = array_values(array_filter(get_posts($pdo, 'guide', 4, $canPreviewDrafts), static function (array $item) use ($guideSlug): bool {
        return ($item['slug'] ?? '') !== $guideSlug;
    }));
    $template = __DIR__ . '/templates/guide.php';
    $pageTitle = $guide['title'] . ' | ' . APP_NAME;
    $meta['description'] = trim((string) ($guide['description'] ?? '')) !== ''
        ? (string) $guide['description']
        : (trim((string) ($guide['excerpt'] ?? '')) !== '' ? (string) $guide['excerpt'] : site_meta_defaults()['description']);
    $meta['image'] = !empty($guide['featured_image']) ? absolute_url(content_asset_path((string) $guide['featured_image'])) : absolute_url('/assets/logo/1024.png');
    $canonicalPath = canonical_public_path($pdo, '/guides/' . $guideSlug, ['guide_detail_mode' => $guideDetailCanonicalMode]);
    $jsonLd[] = json_ld_for_itemlist($guideProducts, $guide['title']);
    $jsonLd[] = json_ld_for_article(
        $guide['title'],
        (string) $meta['description'],
        absolute_url($canonicalPath),
        (string) ($guide['updated_at'] ?? $guide['published_at'] ?? gmdate('c'))
    );
    $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/guides')];
    $breadcrumbs[] = ['name' => $guide['title'], 'url' => absolute_url($canonicalPath)];
    if (!empty($guide['faq'])) {
        $jsonLd[] = json_ld_for_faq($guide['faq']);
    }
} elseif (
    (count($segments) === 2 && $segments[0] === 'guides')
) {
    if ($guideDetailCanonicalMode === 'root') {
        header('Location: ' . $buildRedirectUrl('/' . slugify((string) $segments[1])), true, 301);
        exit;
    }
    $guideSlug = slugify($segments[1]);
    if (!isset($guide)) {
        $guide = find_post_by_slug($pdo, $guideSlug, $canPreviewDrafts);
    }

    if ($guide === null || $guide['post_type'] !== 'guide') {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Guide Not Found | ' . APP_NAME;
        $meta['robots'] = 'noindex,follow';
    } else {
        $viewPageType = 'guide';
        $viewPageSlug = $guideSlug;
        $guideProducts = get_products_by_category($pdo, $guide['focus'] ?? 'telescopes', 6);
        if ($guideProducts === []) {
            $guideProducts = get_recent_products($pdo, 6);
        }
        $isDraftPreview = $canPreviewDrafts && (($guide['status'] ?? 'published') !== 'published');
        if ($isDraftPreview) {
            $draftPreviewNotice = 'Preview privado: esta guía está en BORRADOR. Solo es visible para tu sesión admin.';
            $meta['robots'] = 'noindex,nofollow';
        }
        $data['guide'] = $guide;
        $data['guideProducts'] = $guideProducts;
        $data['otherGuides'] = array_values(array_filter(get_posts($pdo, 'guide', 4, $canPreviewDrafts), static function (array $item) use ($guideSlug): bool {
            return ($item['slug'] ?? '') !== $guideSlug;
        }));
        $template = __DIR__ . '/templates/guide.php';
        $pageTitle = $guide['title'] . ' | ' . APP_NAME;
        $meta['description'] = trim((string) ($guide['description'] ?? '')) !== ''
            ? (string) $guide['description']
            : (trim((string) ($guide['excerpt'] ?? '')) !== '' ? (string) $guide['excerpt'] : site_meta_defaults()['description']);
        $meta['image'] = !empty($guide['featured_image']) ? absolute_url(content_asset_path((string) $guide['featured_image'])) : absolute_url('/assets/logo/1024.png');
        $canonicalPath = canonical_public_path($pdo, '/guides/' . $guideSlug, ['guide_detail_mode' => $guideDetailCanonicalMode]);
        $jsonLd[] = json_ld_for_itemlist($guideProducts, $guide['title']);
        $jsonLd[] = json_ld_for_article(
            $guide['title'],
            (string) $meta['description'],
            absolute_url($canonicalPath),
            (string) ($guide['updated_at'] ?? $guide['published_at'] ?? gmdate('c'))
        );
        $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/guides')];
        $breadcrumbs[] = ['name' => $guide['title'], 'url' => absolute_url($canonicalPath)];
        if (!empty($guide['faq'])) {
            $jsonLd[] = json_ld_for_faq($guide['faq']);
        }
    }
} elseif (count($segments) === 1 && $segments[0] === 'guides') {
    $viewPageType = 'guides';
    $viewPageSlug = 'guides-hub';
    $template = __DIR__ . '/templates/guides.php';
    $guidesPerPage = 9;
    $requestedGuidesPage = max(1, (int) ($_GET['page'] ?? 1));
    $guidesTotalItems = get_posts_count($pdo, 'guide', $canPreviewDrafts);
    $guidesTotalPages = max(1, (int) ceil($guidesTotalItems / $guidesPerPage));
    $guidesCurrentPage = min($requestedGuidesPage, $guidesTotalPages);
    $data['guides'] = get_posts_paginated($pdo, 'guide', $guidesCurrentPage, $guidesPerPage, $canPreviewDrafts);
    $data['guides_pagination'] = [
        'page' => $guidesCurrentPage,
        'per_page' => $guidesPerPage,
        'total_items' => $guidesTotalItems,
        'total_pages' => $guidesTotalPages,
        'has_prev' => $guidesCurrentPage > 1,
        'has_next' => $guidesCurrentPage < $guidesTotalPages,
        'prev_page' => max(1, $guidesCurrentPage - 1),
        'next_page' => min($guidesTotalPages, $guidesCurrentPage + 1),
    ];
    $pageTitle = 'Astronomy Buying Guides | ' . APP_NAME;
    if ($guidesCurrentPage > 1) {
        $pageTitle = 'Astronomy Buying Guides - Page ' . $guidesCurrentPage . ' | ' . APP_NAME;
    }
    $meta['description'] = 'Browse telescope buying guides, accessory recommendations, and budget-friendly astronomy advice for beginners.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/guides';
    if ($guidesCurrentPage > 1) {
        $meta['prev_url'] = absolute_url('/guides' . ($guidesCurrentPage === 2 ? '' : '?page=' . ($guidesCurrentPage - 1)));
        $canonicalPath .= '?page=' . $guidesCurrentPage;
    }
    if ($guidesCurrentPage < $guidesTotalPages) {
        $meta['next_url'] = absolute_url('/guides?page=' . ($guidesCurrentPage + 1));
    }
    if ($canPreviewDrafts) {
        $draftPreviewNotice = 'Preview privado activo: la lista incluye borradores visibles solo para tu sesión admin.';
    }
    $jsonLd[] = json_ld_for_faq(seo_faq_for_page('guides'));
    $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/guides')];
} elseif (count($segments) === 1 && $segments[0] === 'reviews') {
    $section = $segments[0];
    $sectionPosts = get_posts($pdo, 'review', 5000, $canPreviewDrafts);
    $perPage = 9;
    $requestedPage = max(1, (int) ($_GET['page'] ?? 1));
    $totalItems = count($sectionPosts);
    $totalPages = max(1, (int) ceil($totalItems / $perPage));
    $currentPage = min($requestedPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;
    $data['posts'] = array_slice($sectionPosts, $offset, $perPage);
    $data['blog_pagination'] = [
        'page' => $currentPage,
        'per_page' => $perPage,
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'has_prev' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'prev_page' => max(1, $currentPage - 1),
        'next_page' => min($totalPages, $currentPage + 1),
    ];
    $data['blog_admin_preview'] = $canPreviewDrafts;
    $data['content_section'] = $section;
    $viewPageType = $section;
    $viewPageSlug = $section . '-index';
    $template = __DIR__ . '/templates/blog.php';
    $pageTitle = 'Telescope Reviews & Comparisons | ' . APP_NAME;
    if ($currentPage > 1) {
        $pageTitle = 'Telescope Reviews - Page ' . $currentPage . ' | ' . APP_NAME;
    }
    $meta['description'] = 'Read telescope and accessory reviews, comparisons, and buyer-intent breakdowns.';
    $metaOgImage = site_setting_get($pdo, 'site_og_image', '');
    $meta['image'] = $metaOgImage !== '' ? absolute_url($metaOgImage) : absolute_url('/assets/logo/512.png');
    $canonicalPath = '/' . $section;
    if ($currentPage > 1) {
        $meta['prev_url'] = absolute_url('/' . $section . ($currentPage === 2 ? '' : '?page=' . ($currentPage - 1)));
        $canonicalPath .= '?page=' . $currentPage;
    }
    if ($currentPage < $totalPages) {
        $meta['next_url'] = absolute_url('/' . $section . '?page=' . ($currentPage + 1));
    }
    $breadcrumbs[] = ['name' => ucfirst($section), 'url' => absolute_url('/' . $section)];
} elseif (count($segments) === 1 && $segments[0] === 'blog') {
    $viewPageType = 'blog';
    $viewPageSlug = 'blog-index';
    $template = __DIR__ . '/templates/blog.php';
    $blogPerPage = 9;
    $requestedBlogPage = max(1, (int) ($_GET['page'] ?? 1));
    $blogTotalPosts = get_posts_count($pdo, 'post', $canPreviewDrafts);
    $blogTotalPages = max(1, (int) ceil($blogTotalPosts / $blogPerPage));
    $blogCurrentPage = min($requestedBlogPage, $blogTotalPages);
    $data['posts'] = get_posts_paginated($pdo, 'post', $blogCurrentPage, $blogPerPage, $canPreviewDrafts);
    $data['blog_pagination'] = [
        'page' => $blogCurrentPage,
        'per_page' => $blogPerPage,
        'total_items' => $blogTotalPosts,
        'total_pages' => $blogTotalPages,
        'has_prev' => $blogCurrentPage > 1,
        'has_next' => $blogCurrentPage < $blogTotalPages,
        'prev_page' => max(1, $blogCurrentPage - 1),
        'next_page' => min($blogTotalPages, $blogCurrentPage + 1),
    ];
    $data['blog_admin_preview'] = $canPreviewDrafts;
    $pageTitle = 'Astronomy Blog, Stargazing Tips & Telescope Advice | ' . APP_NAME;
    if ($blogCurrentPage > 1) {
        $pageTitle = 'Astronomy Blog - Page ' . $blogCurrentPage . ' | ' . APP_NAME;
    }
    $meta['description'] = 'Read astronomy articles, stargazing tips, telescope setup advice, and beginner-friendly observing content.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    if ($blogCurrentPage > 1) {
        $meta['prev_url'] = absolute_url('/blog' . ($blogCurrentPage === 2 ? '' : '?page=' . ($blogCurrentPage - 1)));
    }
    if ($blogCurrentPage < $blogTotalPages) {
        $meta['next_url'] = absolute_url('/blog?page=' . ($blogCurrentPage + 1));
    }
    if ($canPreviewDrafts) {
        $draftPreviewNotice = 'Preview privado activo: la lista incluye borradores visibles solo para tu sesión admin.';
    }
    $canonicalPath = '/blog';
    if ($blogCurrentPage > 1) {
        $canonicalPath .= '?page=' . $blogCurrentPage;
    }
    $jsonLd[] = json_ld_for_faq(seo_faq_for_page('blog'));
    $breadcrumbs[] = ['name' => 'Blog', 'url' => absolute_url('/blog')];
} elseif (count($segments) === 2 && $segments[0] === 'reviews') {
    $section = $segments[0];
    $postSlug = slugify($segments[1]);
    $post = find_post_by_slug($pdo, $postSlug, $canPreviewDrafts);

    if ($post === null || ($post['post_type'] ?? 'post') !== 'review') {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Post Not Found | ' . APP_NAME;
        $meta['robots'] = 'noindex,follow';
    } else {
        $isDraftPreview = $canPreviewDrafts && (($post['status'] ?? 'published') !== 'published');
        if ($isDraftPreview) {
            $draftPreviewNotice = 'Preview privado: este artículo está en BORRADOR. Solo es visible para tu sesión admin.';
            $meta['robots'] = 'noindex,nofollow';
        }
        $viewPageType = 'post';
        $viewPageSlug = $postSlug;
        $data['post'] = $post;
        $data['otherGuides'] = get_posts($pdo, 'guide', 3, $canPreviewDrafts);
        $template = __DIR__ . '/templates/post.php';
        $pageTitle = (($post['meta_title'] ?? '') !== '' ? $post['meta_title'] : $post['title']) . ' | ' . APP_NAME;
        $meta['description'] = $post['meta_description'] ?: $post['excerpt'];
        $meta['image'] = $post['featured_image'] !== '' ? absolute_url(content_asset_path((string) $post['featured_image'])) : absolute_url('/assets/logo/1024.png');
        $canonicalPath = '/' . $section . '/' . $postSlug;
        $dynamicSchemas = generate_dynamic_schema($post, base_url());
        foreach ($dynamicSchemas as $schemaObj) {
            $jsonLd[] = $schemaObj;
        }
        $breadcrumbs[] = ['name' => ucfirst($section), 'url' => absolute_url('/' . $section)];
        $breadcrumbs[] = ['name' => $post['title'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 2 && $segments[0] === 'blog') {
    $postSlug = slugify($segments[1]);
    $post = find_post_by_slug($pdo, $postSlug, $canPreviewDrafts);

    if ($post === null || ($post['post_type'] ?? 'post') !== 'post') {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Post Not Found | ' . APP_NAME;
        $meta['robots'] = 'noindex,follow';
    } else {
        $postType = strtolower(trim((string) ($post['post_type'] ?? 'post')));
        if ($postType === 'review') {
            header('Location: ' . url('/reviews/' . $postSlug), true, 301);
            exit;
        }
        if ($postType === 'guide') {
            header('Location: ' . url('/' . $postSlug), true, 301);
            exit;
        }
        $isDraftPreview = $canPreviewDrafts && (($post['status'] ?? 'published') !== 'published');
        if ($isDraftPreview) {
            $draftPreviewNotice = 'Preview privado: este artículo está en BORRADOR. Solo es visible para tu sesión admin.';
            $meta['robots'] = 'noindex,nofollow';
        }
        $viewPageType = 'post';
        $viewPageSlug = $postSlug;
        $data['post'] = $post;
        $data['otherGuides'] = get_posts($pdo, 'guide', 3, $canPreviewDrafts);
        $template = __DIR__ . '/templates/post.php';
        $pageTitle = (($post['meta_title'] ?? '') !== '' ? $post['meta_title'] : $post['title']) . ' | ' . APP_NAME;
        $meta['description'] = $post['meta_description'] ?: $post['excerpt'];
        $meta['image'] = $post['featured_image'] !== '' ? absolute_url(content_asset_path((string) $post['featured_image'])) : absolute_url('/assets/logo/1024.png');
        $canonicalPath = '/blog/' . $postSlug;
        $dynamicSchemas = generate_dynamic_schema($post, base_url());
        foreach ($dynamicSchemas as $schemaObj) {
            $jsonLd[] = $schemaObj;
        }
        $breadcrumbs[] = ['name' => 'Blog', 'url' => absolute_url('/blog')];
        $breadcrumbs[] = ['name' => $post['title'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 1 && $segments[0] === 'about') {
    $viewPageType = 'page';
    $viewPageSlug = 'about';
    $template = __DIR__ . '/templates/about.php';
    $pageTitle = 'About | ' . APP_NAME;
    $meta['description'] = 'About Fortelescopes and our mission to help beginners choose astronomy gear.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/about';
    $breadcrumbs[] = ['name' => 'About', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'affiliate-disclosure') {
    $viewPageType = 'page';
    $viewPageSlug = 'affiliate-disclosure';
    $template = __DIR__ . '/templates/legal-affiliate.php';
    $pageTitle = 'Affiliate Disclosure | ' . APP_NAME;
    $meta['description'] = 'Affiliate disclosure and monetization transparency for Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/affiliate-disclosure';
    $breadcrumbs[] = ['name' => 'Affiliate Disclosure', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'privacy-policy') {
    $viewPageType = 'page';
    $viewPageSlug = 'privacy-policy';
    $template = __DIR__ . '/templates/legal-privacy.php';
    $pageTitle = 'Privacy Policy | ' . APP_NAME;
    $meta['description'] = 'Privacy policy for visitors of Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/privacy-policy';
    $breadcrumbs[] = ['name' => 'Privacy Policy', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'terms-of-use') {
    $viewPageType = 'page';
    $viewPageSlug = 'terms-of-use';
    $template = __DIR__ . '/templates/legal-terms.php';
    $pageTitle = 'Terms of Use | ' . APP_NAME;
    $meta['description'] = 'Terms and conditions for using Fortelescopes.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/terms-of-use';
    $breadcrumbs[] = ['name' => 'Terms of Use', 'url' => absolute_url($canonicalPath)];
} elseif (count($segments) === 1 && $segments[0] === 'contact') {
    $viewPageType = 'page';
    $viewPageSlug = 'contact';
    $template = __DIR__ . '/templates/contact.php';
    $pageTitle = 'Contact | ' . APP_NAME;
    $meta['description'] = 'Contact Fortelescopes for partnerships, corrections, or feedback.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/contact';
    $data['contact_form'] = [
        'name' => '',
        'email' => '',
        'subject' => '',
        'message' => '',
    ];
    $data['contact_errors'] = [];
    $data['contact_flash'] = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_contact_message') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $website = trim((string) ($_POST['website'] ?? ''));
        $errorsForm = [];

        if ($website !== '') {
            $errorsForm[] = 'Invalid form submission.';
        }
        if ($name === '' || mb_strlen($name) > 120) {
            $errorsForm[] = 'Please enter your name (max 120 chars).';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errorsForm[] = 'Please enter a valid email.';
        }
        if ($subject === '' || mb_strlen($subject) > 190) {
            $errorsForm[] = 'Please enter a subject (max 190 chars).';
        }
        if ($message === '' || mb_strlen($message) < 15) {
            $errorsForm[] = 'Please write a message of at least 15 characters.';
        }
        if (mb_strlen($message) > 5000) {
            $errorsForm[] = 'Message is too long (max 5000 chars).';
        }

        $data['contact_form'] = [
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'message' => $message,
        ];

        if ($errorsForm === []) {
            try {
                $now = gmdate('Y-m-d H:i:s');
                $stmt = $pdo->prepare(
                    'INSERT INTO contact_messages (
                        name, email, subject, message_text, status, source_path, ip_address, user_agent, created_at, updated_at
                    ) VALUES (
                        :name, :email, :subject, :message_text, "new", :source_path, :ip_address, :user_agent, :created_at, :updated_at
                    )'
                );
                $stmt->execute([
                    ':name' => mb_substr($name, 0, 120),
                    ':email' => mb_substr($email, 0, 190),
                    ':subject' => mb_substr($subject, 0, 190),
                    ':message_text' => $message,
                    ':source_path' => '/contact',
                    ':ip_address' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
                    ':user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
                $data['contact_flash'] = 'Message sent. We will review it and respond soon.';
                $data['contact_form'] = ['name' => '', 'email' => '', 'subject' => '', 'message' => ''];
            } catch (Throwable $e) {
                $errorsForm[] = 'Could not store your message right now. Please try again.';
            }
        }

        $data['contact_errors'] = $errorsForm;
    }
    $breadcrumbs[] = ['name' => 'Contact', 'url' => absolute_url($canonicalPath)];
} else {
    http_response_code(404);
    $viewPageType = $suspiciousReason !== '' ? 'security' : 'not_found';
    $viewPageSlug = $suspiciousReason !== '' ? $suspiciousReason : trim((string) $path);
    $template = __DIR__ . '/templates/not-found.php';
    $pageTitle = 'Not Found | ' . APP_NAME;
    $meta['description'] = 'Requested page does not exist.';
    $meta['robots'] = 'noindex,follow';
}

if (count($breadcrumbs) > 1) {
    $jsonLd[] = json_ld_for_breadcrumb($breadcrumbs);
}

$categories = get_categories($pdo);
$canonicalUrl = absolute_url($canonicalPath);
if (!headers_sent()) {
    header_remove('Expires');
    header_remove('Pragma');
    header_remove('Cache-Control');
    $enableSecurityHeaders = site_setting_get($pdo, 'site_enable_security_headers', '1') !== '0';
    if ($enableSecurityHeaders) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    $isCacheableGet = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && !$isDraftPreview && !frontend_admin_preview_enabled();
    $enablePublicCache = site_setting_get($pdo, 'site_enable_public_cache', '1') !== '0';
    if ($enablePublicCache && $isCacheableGet) {
        $maxAge = max(60, min(3600, (int) site_setting_get($pdo, 'site_public_cache_max_age', '300')));
        $sMaxAge = max($maxAge, min(86400, (int) site_setting_get($pdo, 'site_public_smaxage', '600')));
        header('Cache-Control: public, max-age=' . $maxAge . ', s-maxage=' . $sMaxAge . ', stale-while-revalidate=60');
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }
}
try {
    $trackingPath = $viewPageType === 'not_found'
        ? normalize_public_path($requestPath)
        : canonical_public_path($pdo, $canonicalPath, ['guide_detail_mode' => $guideDetailCanonicalMode]);
    $shouldTrack = !($viewPageType === 'not_found' && is_encoded_external_path($requestPath));
    if ($shouldTrack) {
        track_page_view($pdo, $trackingPath, $viewPageType, $viewPageSlug, $viewProductId);
    }
} catch (Throwable $e) {
    // Do not break frontend if analytics write fails.
}

require __DIR__ . '/templates/layout.php';
