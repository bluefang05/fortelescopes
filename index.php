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
    $nowIso = gmdate('c');
    $urls = [
        ['loc' => absolute_url('/'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/telescopes'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/accessories'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-beginner-telescopes'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-telescope-accessories'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/best-telescopes-under-500'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/guides'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/about'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/affiliate-disclosure'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/contact'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/privacy-policy'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/terms-of-use'), 'lastmod' => $nowIso],
    ];
    $cats = get_categories($pdo);
    foreach ($cats as $cat) {
        $urls[] = [
            'loc' => absolute_url('/category/' . $cat['category_slug']),
            'lastmod' => $nowIso,
        ];
    }
    foreach (get_recent_products($pdo, 5000) as $product) {
        $urls[] = [
            'loc' => absolute_url('/product/' . $product['slug']),
            'lastmod' => (string) ($product['updated_at'] ?? $product['last_synced_at'] ?? $nowIso),
        ];
    }

    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $seen = [];
    foreach ($urls as $entry) {
        $loc = (string) ($entry['loc'] ?? '');
        if ($loc === '' || isset($seen[$loc])) {
            continue;
        }
        $seen[$loc] = true;
        $lastmod = (string) ($entry['lastmod'] ?? $nowIso);
        $lastmodTs = strtotime($lastmod);
        if ($lastmodTs !== false) {
            $lastmod = gmdate('c', $lastmodTs);
        } else {
            $lastmod = $nowIso;
        }
        echo "  <url>\n";
        echo "    <loc>" . e($loc) . "</loc>\n";
        echo "    <lastmod>" . e($lastmod) . "</lastmod>\n";
        echo "  </url>\n";
    }
    echo "</urlset>";
    exit;
}

if ($segments === []) {
    $data['products'] = get_recent_products($pdo, 18);
    $data['telescopes'] = get_products_by_category($pdo, 'telescopes', 6);
    $data['accessories'] = get_products_by_category($pdo, 'accessories', 6);
    $pageTitle = APP_NAME . ' | Telescopes and Astronomy Gear';
    $meta['description'] = 'Explore beginner telescopes, popular astronomy accessories, and practical stargazing guides.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $jsonLd[] = json_ld_for_itemlist($data['products'], 'Featured Telescope and Astronomy Products');
} elseif (count($segments) === 2 && $segments[0] === 'category') {
    $categorySlug = slugify($segments[1]);
    $products = get_products_by_category($pdo, $categorySlug, 12);
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
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Browse ' . $products[0]['category_name'] . ' recommendations and compare options before buying.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name'] . ' recommendations');
        $breadcrumbs[] = ['name' => 'Categories', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (count($segments) === 1 && in_array($segments[0], ['telescopes', 'accessories'], true)) {
    $categorySlug = $segments[0];
    $products = get_products_by_category($pdo, $categorySlug, 16);
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
        $viewPageType = 'category';
        $viewPageSlug = $categorySlug;
        $pageTitle = $products[0]['category_name'] . ' | ' . APP_NAME;
        $template = __DIR__ . '/templates/category.php';
        $meta['description'] = 'Compare ' . strtolower($products[0]['category_name']) . ' for astronomy and stargazing sessions.';
        $meta['image'] = absolute_url('/assets/logo/1024.png');
        $jsonLd[] = json_ld_for_itemlist($products, $products[0]['category_name']);
        $breadcrumbs[] = ['name' => $products[0]['category_name'], 'url' => absolute_url($canonicalPath)];
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
        $breadcrumbs[] = ['name' => 'Products', 'url' => absolute_url('/telescopes')];
        $breadcrumbs[] = ['name' => $product['title'], 'url' => absolute_url($canonicalPath)];
    }
} elseif (
    (count($segments) === 2 && $segments[0] === 'guides') ||
    (count($segments) === 1 && ($guide = find_post_by_slug($pdo, slugify($segments[0]))) && $guide['post_type'] === 'guide')
) {
    $guideSlug = count($segments) === 2 ? slugify($segments[1]) : slugify($segments[0]);
    if (!isset($guide)) {
        $guide = find_post_by_slug($pdo, $guideSlug);
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
        $data['guide'] = $guide;
        $data['guideProducts'] = $guideProducts;
        $data['otherGuides'] = array_filter(get_posts($pdo, 'guide', 4), function($g) use ($guideSlug) {
            return $g['slug'] !== $guideSlug;
        });
        $template = __DIR__ . '/templates/guide.php';
        $pageTitle = $guide['title'] . ' | ' . APP_NAME;
        $meta['description'] = $guide['description'] ?? site_meta_defaults()['description'];
        $meta['image'] = !empty($guide['featured_image']) ? absolute_url($guide['featured_image']) : absolute_url('/assets/logo/1024.png');
        $canonicalPath = '/' . $guideSlug;
        $jsonLd[] = json_ld_for_itemlist($guideProducts, $guide['title']);
        $jsonLd[] = json_ld_for_article($guide['title'], $guide['description'], absolute_url($canonicalPath), gmdate('c'));
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
    $data['guides'] = get_posts($pdo, 'guide', 10);
    $pageTitle = 'Astronomy Buying Guides | ' . APP_NAME;
    $meta['description'] = 'Browse telescope and astronomy buying guides covering beginner picks, accessories, and budget-friendly models.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/guides';
    $breadcrumbs[] = ['name' => 'Guides', 'url' => absolute_url('/guides')];
} elseif (count($segments) === 1 && $segments[0] === 'blog') {
    $viewPageType = 'blog';
    $viewPageSlug = 'blog-index';
    $template = __DIR__ . '/templates/blog.php';
    $data['posts'] = get_posts($pdo, 'post', 10);
    $pageTitle = 'Astronomy Blog | ' . APP_NAME;
    $meta['description'] = 'Read the latest astronomy articles, news, and stargazing tips.';
    $meta['image'] = absolute_url('/assets/logo/1024.png');
    $canonicalPath = '/blog';
    $breadcrumbs[] = ['name' => 'Blog', 'url' => absolute_url('/blog')];
} elseif (count($segments) === 2 && $segments[0] === 'blog') {
    $postSlug = slugify($segments[1]);
    $post = find_post_by_slug($pdo, $postSlug);

    if ($post === null || ($post['post_type'] ?? 'post') !== 'post') {
        http_response_code(404);
        $template = __DIR__ . '/templates/not-found.php';
        $pageTitle = 'Post Not Found | ' . APP_NAME;
        $meta['robots'] = 'noindex,follow';
    } else {
        $viewPageType = 'post';
        $viewPageSlug = $postSlug;
        $data['post'] = $post;
        $data['otherGuides'] = get_posts($pdo, 'guide', 3);
        $template = __DIR__ . '/templates/post.php';
        $pageTitle = $post['title'] . ' | ' . APP_NAME;
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
