<?php

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_prefix(): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if ($dir === '' || $dir === '.' || $dir === '/') {
        return '';
    }

    if (substr($dir, -5) === '/enma') {
        $dir = substr($dir, 0, -5);
    } elseif (substr($dir, -6) === '/admin') {
        $dir = substr($dir, 0, -6);
    }

    return $dir === '/' ? '' : $dir;
}

function url(string $path = '/'): string
{
    $prefix = app_prefix();
    $path = '/' . ltrim($path, '/');

    if ($prefix === '') {
        return $path;
    }

    return rtrim($prefix, '/') . ($path === '/' ? '/' : $path);
}

function base_url(): string
{
    return rtrim(BASE_URL, '/');
}

function absolute_url(string $path = '/'): string
{
    return base_url() . url($path);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item';
}

function unique_slug(PDO $pdo, string $base): string
{
    $base = slugify($base);
    $slug = $base;
    $n = 2;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM products WHERE slug = :slug');
    while (true) {
        $stmt->execute([':slug' => $slug]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $n;
        $n++;
    }
}

function now_iso(): string
{
    return gmdate('c');
}

function get_categories(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT category_slug, category_name, COUNT(*) AS total
         FROM products
         WHERE status = "published"
         GROUP BY category_slug, category_name
         ORDER BY category_name ASC'
    );

    return $stmt->fetchAll();
}

function get_recent_products(PDO $pdo, int $limit = 12): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM products
         WHERE status = "published"
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    usort($rows, 'compare_products_for_conversion');
    return $rows;
}

function get_products_by_category(PDO $pdo, string $slug, int $limit = 12): array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM products
         WHERE status = "published" AND category_slug = :slug
         ORDER BY id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll();
    usort($rows, 'compare_products_for_conversion');
    return $rows;
}

function find_product_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare(
        'SELECT * FROM products
         WHERE status = "published" AND slug = :slug
         LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();

    return $row ?: null;
}

function money(?float $amount, string $currency = 'USD'): string
{
    if ($amount === null) {
        return 'Check availability on Amazon';
    }

    return $currency . ' ' . number_format($amount, 2);
}

function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_is_valid(?string $token): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (!is_string($token) || $token === '') {
        return false;
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '') {
        return false;
    }

    return hash_equals($sessionToken, $token);
}

function site_meta_defaults(): array
{
    return [
        'description' => 'Affiliate product recommendations for telescope accessories.',
        'type' => 'website',
        'image' => 'https://images.unsplash.com/photo-1446776653964-20c1d3a81b06?auto=format&fit=crop&w=1200&q=80',
    ];
}

function product_price_value(array $product): float
{
    if (!isset($product['price_amount']) || $product['price_amount'] === null || !is_numeric((string) $product['price_amount'])) {
        return 0.0;
    }

    return (float) $product['price_amount'];
}

function pick_tier_products(array $products): array
{
    if ($products === []) {
        return [];
    }

    $rankable = array_values(array_filter($products, static function (array $item): bool {
        return isset($item['price_amount']) && $item['price_amount'] !== null && is_numeric((string) $item['price_amount']);
    }));

    if ($rankable === []) {
        return ['top' => $products[0], 'budget' => $products[0], 'premium' => $products[0]];
    }

    usort($rankable, static function (array $a, array $b): int {
        return product_price_value($a) <=> product_price_value($b);
    });

    $budget = $rankable[0];
    $premium = $rankable[count($rankable) - 1];
    $top = $rankable[(int) floor((count($rankable) - 1) / 2)];

    return ['top' => $top, 'budget' => $budget, 'premium' => $premium];
}

function product_best_for(array $product): string
{
    $price = product_price_value($product);
    if ($price > 0 && $price < 40) {
        return 'Best for first-time buyers and tight budgets';
    }
    if ($price >= 40 && $price < 90) {
        return 'Best for regular backyard observation sessions';
    }

    return 'Best for enthusiasts who need stronger performance';
}

function product_pros(array $product): array
{
    return [
        'Clear product-market fit for telescope users',
        'Practical setup for quick observation nights',
        'Direct Amazon checkout with updated listing',
    ];
}

function product_cons(array $product): array
{
    $price = product_price_value($product);
    if ($price > 0 && $price >= 90) {
        return [
            'Higher price compared with entry-level alternatives',
            'May be overkill for occasional use',
        ];
    }

    return [
        'Limited advanced features versus premium models',
        'May require upgrade as skill level grows',
    ];
}

function parse_iso_time(?string $value): ?int
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $ts = strtotime($value);
    return $ts === false ? null : $ts;
}

function relative_time_label(?string $isoValue): string
{
    $ts = parse_iso_time($isoValue);
    if ($ts === null) {
        return 'Update pending';
    }

    $delta = max(0, time() - $ts);
    if ($delta < 60) {
        return 'Updated just now';
    }
    if ($delta < 3600) {
        return 'Updated ' . (int) floor($delta / 60) . 'm ago';
    }
    if ($delta < 86400) {
        return 'Updated ' . (int) floor($delta / 3600) . 'h ago';
    }

    return 'Updated ' . (int) floor($delta / 86400) . 'd ago';
}

function sync_freshness_class(?string $isoValue): string
{
    $ts = parse_iso_time($isoValue);
    if ($ts === null) {
        return 'stale';
    }

    $delta = max(0, time() - $ts);
    if ($delta < 6 * 3600) {
        return 'fresh';
    }
    if ($delta < 24 * 3600) {
        return 'aging';
    }

    return 'stale';
}

function freshness_weight(?string $isoValue): int
{
    $class = sync_freshness_class($isoValue);
    if ($class === 'fresh') {
        return 3;
    }
    if ($class === 'aging') {
        return 2;
    }

    return 1;
}

function price_intent_weight(?float $amount): int
{
    if ($amount === null || $amount <= 0) {
        return 1;
    }

    if ($amount >= 35 && $amount <= 120) {
        return 3;
    }
    if (($amount >= 20 && $amount < 35) || ($amount > 120 && $amount <= 220)) {
        return 2;
    }

    return 1;
}

function conversion_score(array $product): int
{
    $price = isset($product['price_amount']) && is_numeric((string) $product['price_amount'])
        ? (float) $product['price_amount']
        : null;

    return (freshness_weight($product['last_synced_at'] ?? null) * 4) + (price_intent_weight($price) * 3);
}

function compare_products_for_conversion(array $a, array $b): int
{
    $scoreA = conversion_score($a);
    $scoreB = conversion_score($b);

    if ($scoreA !== $scoreB) {
        return $scoreB <=> $scoreA;
    }

    $idA = (int) ($a['id'] ?? 0);
    $idB = (int) ($b['id'] ?? 0);
    return $idB <=> $idA;
}

function parse_accept_language_country(?string $acceptLanguage): string
{
    $acceptLanguage = trim((string) $acceptLanguage);
    if ($acceptLanguage === '') {
        return 'UNK';
    }
    if (preg_match('/\b[a-z]{2}-([A-Z]{2})\b/', $acceptLanguage, $m)) {
        return strtoupper((string) $m[1]);
    }
    return 'UNK';
}

function detect_country_code(): string
{
    $keys = [
        'HTTP_CF_IPCOUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_COUNTRY_CODE',
        'GEOIP_COUNTRY_CODE',
        'HTTP_X_APPENGINE_COUNTRY',
    ];
    foreach ($keys as $key) {
        $value = strtoupper(trim((string) ($_SERVER[$key] ?? '')));
        if ($value !== '' && preg_match('/^[A-Z]{2,3}$/', $value)) {
            return $value;
        }
    }
    return parse_accept_language_country($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
}

function detect_referrer_host(): string
{
    $ref = trim((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if ($ref === '' || filter_var($ref, FILTER_VALIDATE_URL) === false) {
        return 'direct';
    }
    $host = strtolower((string) (parse_url($ref, PHP_URL_HOST) ?? ''));
    return $host !== '' ? substr($host, 0, 255) : 'direct';
}

function detect_source_type(string $referrerHost): string
{
    if ($referrerHost === '' || $referrerHost === 'direct') {
        return 'direct';
    }
    $searchHosts = ['google.', 'bing.', 'duckduckgo.', 'yahoo.', 'yandex.', 'baidu.', 'ecosia.', 'startpage.'];
    foreach ($searchHosts as $needle) {
        if (strpos($referrerHost, $needle) !== false) {
            return 'search';
        }
    }
    $socialHosts = ['facebook.', 'instagram.', 't.co', 'twitter.', 'x.com', 'reddit.', 'pinterest.', 'linkedin.', 'youtube.', 'tiktok.'];
    foreach ($socialHosts as $needle) {
        if (strpos($referrerHost, $needle) !== false) {
            return 'social';
        }
    }
    return 'other';
}

function anonymized_ip_hash(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($ip === '') {
        return '';
    }
    return hash('sha256', SITE_DOMAIN . '|' . $ip);
}

function track_page_view(PDO $pdo, string $path, string $pageType, string $pageSlug = '', int $productId = 0): void
{
    $path = '/' . ltrim(trim($path), '/');
    $path = substr($path, 0, 255) ?: '/';
    $pageType = substr(trim($pageType), 0, 40) ?: 'page';
    $pageSlug = substr(trim($pageSlug), 0, 255);
    $productId = max(0, $productId);
    $now = now_iso();
    $viewDate = gmdate('Y-m-d');
    $countryCode = substr(detect_country_code(), 0, 8) ?: 'UNK';
    $referrerHost = detect_referrer_host();
    $sourceType = detect_source_type($referrerHost);
    $ipHash = anonymized_ip_hash();
    $userAgent = substr(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 255);

    if (DB_DRIVER === 'mysql') {
        $aggStmt = $pdo->prepare(
            'INSERT INTO page_views (
                view_date, path, page_type, page_slug, product_id, views, last_viewed_at, created_at, updated_at
             ) VALUES (
                :view_date, :path, :page_type, :page_slug, :product_id, 1, :last_viewed_at, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                views = views + 1,
                last_viewed_at = VALUES(last_viewed_at),
                updated_at = VALUES(updated_at)'
        );
    } else {
        $aggStmt = $pdo->prepare(
            'INSERT INTO page_views (
                view_date, path, page_type, page_slug, product_id, views, last_viewed_at, created_at, updated_at
             ) VALUES (
                :view_date, :path, :page_type, :page_slug, :product_id, 1, :last_viewed_at, :created_at, :updated_at
             )
             ON CONFLICT(view_date, path, page_type, page_slug, product_id) DO UPDATE SET
                views = views + 1,
                last_viewed_at = excluded.last_viewed_at,
                updated_at = excluded.updated_at'
        );
    }

    $aggStmt->execute([
        ':view_date' => $viewDate,
        ':path' => $path,
        ':page_type' => $pageType,
        ':page_slug' => $pageSlug,
        ':product_id' => $productId,
        ':last_viewed_at' => $now,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    $hitStmt = $pdo->prepare(
        'INSERT INTO page_view_hits (
            viewed_at, view_date, path, page_type, page_slug, product_id, country_code, referrer_host, source_type, ip_hash, user_agent
         ) VALUES (
            :viewed_at, :view_date, :path, :page_type, :page_slug, :product_id, :country_code, :referrer_host, :source_type, :ip_hash, :user_agent
         )'
    );
    $hitStmt->execute([
        ':viewed_at' => $now,
        ':view_date' => $viewDate,
        ':path' => $path,
        ':page_type' => $pageType,
        ':page_slug' => $pageSlug,
        ':product_id' => $productId,
        ':country_code' => $countryCode,
        ':referrer_host' => $referrerHost,
        ':source_type' => $sourceType,
        ':ip_hash' => $ipHash,
        ':user_agent' => $userAgent,
    ]);
}

function get_views_dashboard(PDO $pdo, int $days = 30): array
{
    $days = max(1, min(365, $days));
    $fromDate = gmdate('Y-m-d', time() - (($days - 1) * 86400));

    $totalsStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(views), 0) AS total_views,
            COUNT(*) AS rows_count,
            COUNT(DISTINCT path) AS unique_paths
         FROM page_views
         WHERE view_date >= :from_date'
    );
    $totalsStmt->execute([':from_date' => $fromDate]);
    $totals = $totalsStmt->fetch() ?: ['total_views' => 0, 'rows_count' => 0, 'unique_paths' => 0];

    $topPagesStmt = $pdo->prepare(
        'SELECT path, page_type, SUM(views) AS total_views
         FROM page_views
         WHERE view_date >= :from_date
         GROUP BY path, page_type
         ORDER BY total_views DESC
         LIMIT 30'
    );
    $topPagesStmt->execute([':from_date' => $fromDate]);
    $topPages = $topPagesStmt->fetchAll();

    $topProductsStmt = $pdo->prepare(
        'SELECT pv.product_id, p.title, p.slug, SUM(pv.views) AS total_views
         FROM page_views pv
         JOIN products p ON p.id = pv.product_id
         WHERE pv.view_date >= :from_date
           AND pv.product_id > 0
         GROUP BY pv.product_id, p.title, p.slug
         ORDER BY total_views DESC
         LIMIT 30'
    );
    $topProductsStmt->execute([':from_date' => $fromDate]);
    $topProducts = $topProductsStmt->fetchAll();

    $topCountriesStmt = $pdo->prepare(
        'SELECT country_code, COUNT(*) AS hits
         FROM page_view_hits
         WHERE view_date >= :from_date
         GROUP BY country_code
         ORDER BY hits DESC
         LIMIT 20'
    );
    $topCountriesStmt->execute([':from_date' => $fromDate]);
    $topCountries = $topCountriesStmt->fetchAll();

    $topReferrersStmt = $pdo->prepare(
        'SELECT referrer_host, source_type, COUNT(*) AS hits
         FROM page_view_hits
         WHERE view_date >= :from_date
         GROUP BY referrer_host, source_type
         ORDER BY hits DESC
         LIMIT 30'
    );
    $topReferrersStmt->execute([':from_date' => $fromDate]);
    $topReferrers = $topReferrersStmt->fetchAll();

    $sourceBreakdownStmt = $pdo->prepare(
        'SELECT source_type, COUNT(*) AS hits
         FROM page_view_hits
         WHERE view_date >= :from_date
         GROUP BY source_type
         ORDER BY hits DESC'
    );
    $sourceBreakdownStmt->execute([':from_date' => $fromDate]);
    $sourceBreakdown = $sourceBreakdownStmt->fetchAll();

    $clickTotalsStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_clicks
         FROM outbound_clicks
         WHERE click_date >= :from_date'
    );
    $clickTotalsStmt->execute([':from_date' => $fromDate]);
    $clickTotals = $clickTotalsStmt->fetch() ?: ['total_clicks' => 0];

    $topClickedProductsStmt = $pdo->prepare(
        'SELECT oc.product_id, p.title, p.slug, COUNT(*) AS clicks
         FROM outbound_clicks oc
         JOIN products p ON p.id = oc.product_id
         WHERE oc.click_date >= :from_date
           AND oc.product_id > 0
         GROUP BY oc.product_id, p.title, p.slug
         ORDER BY clicks DESC
         LIMIT 30'
    );
    $topClickedProductsStmt->execute([':from_date' => $fromDate]);
    $topClickedProducts = $topClickedProductsStmt->fetchAll();

    $ctr = 0.0;
    $totalViews = (int) ($totals['total_views'] ?? 0);
    $totalClicks = (int) ($clickTotals['total_clicks'] ?? 0);
    if ($totalViews > 0) {
        $ctr = ($totalClicks / $totalViews) * 100;
    }

    return [
        'days' => $days,
        'from_date' => $fromDate,
        'totals' => $totals,
        'top_pages' => $topPages,
        'top_products' => $topProducts,
        'top_countries' => $topCountries,
        'top_referrers' => $topReferrers,
        'source_breakdown' => $sourceBreakdown,
        'clicks' => [
            'total_clicks' => $totalClicks,
            'ctr_percent' => $ctr,
            'top_products' => $topClickedProducts,
        ],
    ];
}

function product_image_url(array $product): string
{
    $raw = trim((string) ($product['image_url'] ?? ''));
    if (is_usable_product_image_url($raw)) {
        return $raw;
    }

    return product_image_fallback_url();
}

function is_usable_product_image_url(string $url): bool
{
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || is_logo_placeholder_url($url)) {
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return false;
    }

    $host = strtolower((string) $parts['host']);
    $path = strtolower((string) ($parts['path'] ?? ''));

    if (strpos($path, '.jpg') !== false || strpos($path, '.jpeg') !== false || strpos($path, '.png') !== false || strpos($path, '.webp') !== false || strpos($path, '.avif') !== false || strpos($path, '.gif') !== false || strpos($path, '.svg') !== false) {
        return true;
    }

    // Block amazon product/detail URLs used by mistake as image URLs.
    if (is_amazon_host($host) && (strpos($path, '/dp/') !== false || strpos($path, '/gp/') !== false || strpos($path, '/s?') !== false || strpos($path, '/product') !== false)) {
        return false;
    }

    if (strpos($host, 'm.media-amazon.com') !== false || strpos($host, 'images-na.ssl-images-amazon.com') !== false) {
        return true;
    }

    // Conservative default: if no clear image signal, treat as unusable.
    return false;
}

function normalize_asin(string $asin): string
{
    $asin = strtoupper(trim($asin));
    return preg_match('/^[A-Z0-9]{10}$/', $asin) ? $asin : '';
}

function is_logo_placeholder_url(string $url): bool
{
    $url = strtolower(trim($url));
    if ($url === '') {
        return false;
    }

    return strpos($url, '/assets/logo/') !== false
        || strpos($url, '\\assets\\logo\\') !== false
        || strpos($url, 'fortelescopes/logo/') !== false;
}

function amazon_asin_image_url(string $asin, int $size = 600): string
{
    $asin = normalize_asin($asin);
    if ($asin === '') {
        return '';
    }

    $size = max(150, min(1200, $size));
    $tag = trim((string) AMAZON_ASSOCIATE_TAG);
    $query = [
        '_encoding' => 'UTF8',
        'ASIN' => $asin,
        'Format' => '_SL' . $size . '_',
        'ID' => 'AsinImage',
        'MarketPlace' => 'US',
        'ServiceVersion' => '20070822',
        'WS' => '1',
    ];
    if ($tag !== '') {
        $query['tag'] = $tag;
    }

    return 'https://ws-na.amazon-adsystem.com/widgets/q?' . http_build_query($query);
}

function product_image_fallback_url(): string
{
    return url('/assets/img/product-placeholder.svg');
}

function is_amazon_host(string $host): bool
{
    $host = strtolower(trim($host));
    if ($host === '') {
        return false;
    }

    return (bool) preg_match('/(^|\.)amazon\.[a-z.]+$/', $host);
}

function add_or_replace_query_param(string $url, string $key, string $value): string
{
    $parts = parse_url($url);
    if ($parts === false) {
        return $url;
    }

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $query[$key] = $value;
    $parts['query'] = http_build_query($query);

    $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
    $user = $parts['user'] ?? '';
    $pass = isset($parts['pass']) ? ':' . $parts['pass'] : '';
    $auth = $user !== '' ? $user . $pass . '@' : '';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '';
    $queryPart = $parts['query'] !== '' ? '?' . $parts['query'] : '';
    $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    return $scheme . $auth . $host . $port . $path . $queryPart . $fragment;
}

function amazon_affiliate_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return $url;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['host']) || !is_amazon_host((string) $parts['host'])) {
        return $url;
    }

    $tag = trim((string) AMAZON_ASSOCIATE_TAG);
    if ($tag === '') {
        return $url;
    }

    return add_or_replace_query_param($url, 'tag', $tag);
}

function amazon_tag_present(string $url): bool
{
    $url = trim($url);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false || empty($parts['query'])) {
        return false;
    }

    parse_str((string) $parts['query'], $query);
    return isset($query['tag']) && (string) $query['tag'] === (string) AMAZON_ASSOCIATE_TAG;
}

function current_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = rtrim($scriptDir, '/');

    if ($scriptDir !== '' && $scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
        $path = substr($path, strlen($scriptDir));
        $path = $path === '' ? '/' : $path;
    }

    $path = '/' . ltrim((string) $path, '/');
    return $path === '' ? '/' : substr($path, 0, 255);
}

function outbound_url(string $externalUrl, int $productId = 0, ?string $fromPath = null): string
{
    $target = amazon_affiliate_url($externalUrl);
    if ($target === '' || filter_var($target, FILTER_VALIDATE_URL) === false) {
        return $target;
    }

    $host = strtolower((string) (parse_url($target, PHP_URL_HOST) ?? ''));
    if (!is_amazon_host($host)) {
        return $target;
    }

    $params = ['u' => $target];
    if ($productId > 0) {
        $params['pid'] = (string) $productId;
    }

    $from = $fromPath !== null ? trim($fromPath) : current_request_path();
    if ($from !== '') {
        $from = '/' . ltrim($from, '/');
        $params['from'] = substr($from, 0, 255);
    }

    return url('/go?' . http_build_query($params));
}

function track_outbound_click(PDO $pdo, string $targetUrl, int $productId = 0, string $fromPath = '/'): void
{
    $targetUrl = trim($targetUrl);
    if ($targetUrl === '' || filter_var($targetUrl, FILTER_VALIDATE_URL) === false) {
        return;
    }
    $targetHost = strtolower((string) (parse_url($targetUrl, PHP_URL_HOST) ?? ''));
    if (!is_amazon_host($targetHost)) {
        return;
    }

    $now = now_iso();
    $clickDate = gmdate('Y-m-d');
    $fromPath = '/' . ltrim(trim($fromPath), '/');
    $fromPath = substr($fromPath, 0, 255) ?: '/';
    $countryCode = substr(detect_country_code(), 0, 8) ?: 'UNK';
    $referrerHost = detect_referrer_host();
    $sourceType = detect_source_type($referrerHost);

    $stmt = $pdo->prepare(
        'INSERT INTO outbound_clicks (
            clicked_at, click_date, from_path, product_id, target_host, target_url, country_code, source_type, referrer_host
         ) VALUES (
            :clicked_at, :click_date, :from_path, :product_id, :target_host, :target_url, :country_code, :source_type, :referrer_host
         )'
    );
    $stmt->execute([
        ':clicked_at' => $now,
        ':click_date' => $clickDate,
        ':from_path' => $fromPath,
        ':product_id' => max(0, $productId),
        ':target_host' => substr($targetHost, 0, 255),
        ':target_url' => $targetUrl,
        ':country_code' => $countryCode,
        ':source_type' => $sourceType,
        ':referrer_host' => substr($referrerHost, 0, 255),
    ]);
}

function guides_overrides_path(): string
{
    return __DIR__ . '/../data/guides_overrides.json';
}

function load_guides_overrides(): array
{
    $path = guides_overrides_path();
    if (!is_file($path)) {
        return [];
    }
    $json = @file_get_contents($path);
    if (!is_string($json) || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function save_guides_overrides(array $overrides): bool
{
    $path = guides_overrides_path();
    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return false;
    }
    $json = json_encode($overrides, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return false;
    }
    return @file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function apply_guides_overrides(array $guides): array
{
    $overrides = load_guides_overrides();
    if ($overrides === []) {
        return $guides;
    }

    $allowed = ['title', 'description', 'intro', 'final_recommendation', 'cta_text', 'cta_note'];
    foreach ($guides as $slug => $guide) {
        if (!isset($overrides[$slug]) || !is_array($overrides[$slug])) {
            continue;
        }
        foreach ($allowed as $key) {
            if (isset($overrides[$slug][$key]) && is_string($overrides[$slug][$key])) {
                $value = trim((string) $overrides[$slug][$key]);
                if ($value !== '') {
                    $guides[$slug][$key] = $value;
                }
            }
        }
    }

    return $guides;
}

function editorial_stars(array $product): string
{
    $price = isset($product['price_amount']) && is_numeric((string) $product['price_amount'])
        ? (float) $product['price_amount']
        : 0.0;

    if ($price >= 30 && $price <= 150) {
        return '4.8';
    }
    if ($price > 0) {
        return '4.6';
    }

    return '4.5';
}

function json_ld_for_itemlist(array $products, string $listName): array
{
    $items = [];
    $position = 1;
    foreach ($products as $product) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'url' => absolute_url('/product/' . $product['slug']),
            'name' => $product['title'],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => $listName,
        'itemListElement' => $items,
    ];
}

function json_ld_for_product(array $product): array
{
    $price = isset($product['price_amount']) && is_numeric((string) $product['price_amount'])
        ? (float) $product['price_amount']
        : null;

    $imageUrl = product_image_url($product);
    if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
        $imageUrl = absolute_url($imageUrl);
    }

    $out = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['title'],
        'description' => $product['description'],
        'image' => [$imageUrl],
        'sku' => $product['asin'],
        'brand' => ['@type' => 'Brand', 'name' => APP_NAME],
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => $product['price_currency'] ?: 'USD',
            'url' => amazon_affiliate_url((string) $product['affiliate_url']),
        ],
    ];

    if ($price !== null && $price > 0) {
        $out['offers']['price'] = number_format($price, 2, '.', '');
    }

    return $out;
}

function json_ld_for_faq(array $faqItems): array
{
    $entities = [];
    foreach ($faqItems as $faq) {
        if (!isset($faq['q'], $faq['a'])) {
            continue;
        }
        $entities[] = [
            '@type' => 'Question',
            'name' => (string) $faq['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => (string) $faq['a'],
            ],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $entities,
    ];
}

function json_ld_for_article(string $headline, string $description, string $url, string $dateModified): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $headline,
        'description' => $description,
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $url,
        ],
        'author' => [
            '@type' => 'Organization',
            'name' => APP_NAME,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => APP_NAME,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => absolute_url('/assets/logo/512.png'),
            ],
        ],
        'dateModified' => $dateModified,
    ];
}

function json_ld_for_breadcrumb(array $items): array
{
    $list = [];
    $position = 1;

    foreach ($items as $item) {
        if (!isset($item['name'], $item['url'])) {
            continue;
        }
        $list[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => (string) $item['name'],
            'item' => (string) $item['url'],
        ];
    }

    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ];
}

function json_ld_for_organization(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => APP_NAME,
        'url' => absolute_url('/'),
        'logo' => absolute_url('/assets/logo/512.png'),
    ];
}

function json_ld_for_website(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => APP_NAME,
        'url' => absolute_url('/'),
    ];
}
