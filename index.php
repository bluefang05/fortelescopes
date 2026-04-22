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
    $canonicalPath = '/category/' . $categorySlug;

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
            $meta['next_url'] = absolute_url('/category/' . $categorySlug . '?page=' . ($categoryCurrentPage + 1));
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
} elseif (
    (count($segments) === 2 && $segments[0] === 'guides') ||
    (count($segments) === 1 && ($guide = find_post_by_slug($pdo, slugify($segments[0]), $canPreviewDrafts)) && $guide['post_type'] === 'guide')
) {
    $guideSlug = count($segments) === 2 ? slugify($segments[1]) : slugify($segments[0]);
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
        $meta['image'] = !empty($guide['featured_image']) ? absolute_url($guide['featured_image']) : absolute_url('/assets/logo/1024.png');
        $canonicalPath = '/' . $guideSlug;
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
} elseif (count($segments) === 2 && $segments[0] === 'blog') {
    $postSlug = slugify($segments[1]);
    $post = find_post_by_slug($pdo, $postSlug, $canPreviewDrafts);

    if ($post === null || ($post['post_type'] ?? 'post') !== 'post') {
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
        $meta['image'] = $post['featured_image'] ?: absolute_url('/assets/logo/1024.png');
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
    $breadcrumbs[] = ['name' => 'Contact', 'url' => absolute_url($canonicalPath)];
} else {
    http_response_code(404);
    $viewPageType = 'not_found';
    $viewPageSlug = trim((string) $path);
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
try {
    $trackingPath = $viewPageType === 'not_found' ? $requestPath : $canonicalPath;
    track_page_view($pdo, $trackingPath, $viewPageType, $viewPageSlug, $viewProductId);
} catch (Throwable $e) {
    // Do not break frontend if analytics write fails.
}

require __DIR__ . '/templates/layout.php';
