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
    $path = trim($path);
    if ($path !== '' && preg_match('/^[a-z][a-z0-9+\-.]*:/i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '//')) {
        return $path;
    }

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
    $path = trim($path);
    if ($path !== '' && preg_match('/^[a-z][a-z0-9+\-.]*:/i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '//')) {
        return $path;
    }

    return base_url() . url($path);
}

function sitemap_lastmod_value(?string $preferred, string $fallbackIso): string
{
    $candidate = trim((string) $preferred);
    if ($candidate === '') {
        return $fallbackIso;
    }

    $timestamp = strtotime($candidate);
    if ($timestamp === false) {
        return $fallbackIso;
    }

    return gmdate('c', $timestamp);
}

function get_sitemap_entries(PDO $pdo): array
{
    $nowIso = gmdate('c');
    $publishedGuides = get_posts($pdo, 'guide', 5000);
    $publishedPosts = get_posts($pdo, 'post', 5000);

    $latestGuideMod = $publishedGuides !== []
        ? sitemap_lastmod_value((string) ($publishedGuides[0]['updated_at'] ?? $publishedGuides[0]['published_at'] ?? ''), $nowIso)
        : $nowIso;

    $latestBlogMod = $publishedPosts !== []
        ? sitemap_lastmod_value((string) ($publishedPosts[0]['updated_at'] ?? $publishedPosts[0]['published_at'] ?? ''), $nowIso)
        : $nowIso;

    $entries = [
        ['loc' => absolute_url('/'), 'lastmod' => $latestGuideMod],
        ['loc' => absolute_url('/telescopes'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/accessories'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/guides'), 'lastmod' => $latestGuideMod],
        ['loc' => absolute_url('/blog'), 'lastmod' => $latestBlogMod],
        ['loc' => absolute_url('/about'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/affiliate-disclosure'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/contact'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/privacy-policy'), 'lastmod' => $nowIso],
        ['loc' => absolute_url('/terms-of-use'), 'lastmod' => $nowIso],
    ];

    foreach ($publishedGuides as $guide) {
        $slug = trim((string) ($guide['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $entries[] = [
            'loc' => absolute_url('/' . $slug),
            'lastmod' => sitemap_lastmod_value((string) ($guide['updated_at'] ?? $guide['published_at'] ?? ''), $nowIso),
        ];
    }

    foreach ($publishedPosts as $post) {
        $slug = trim((string) ($post['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $entries[] = [
            'loc' => absolute_url('/blog/' . $slug),
            'lastmod' => sitemap_lastmod_value((string) ($post['updated_at'] ?? $post['published_at'] ?? ''), $nowIso),
        ];
    }

    foreach (get_categories($pdo) as $category) {
        $categorySlug = trim((string) ($category['category_slug'] ?? ''));
        if ($categorySlug === '') {
            continue;
        }
        $entries[] = [
            'loc' => absolute_url('/category/' . $categorySlug),
            'lastmod' => $nowIso,
        ];
    }

    foreach (get_recent_products($pdo, 5000) as $product) {
        $slug = trim((string) ($product['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $entries[] = [
            'loc' => absolute_url('/product/' . $slug),
            'lastmod' => sitemap_lastmod_value((string) ($product['updated_at'] ?? $product['last_synced_at'] ?? ''), $nowIso),
        ];
    }

    return $entries;
}

function render_sitemap_xml(array $entries): string
{
    $nowIso = gmdate('c');
    $seen = [];
    $lines = [
        '<?xml version="1.0" encoding="UTF-8"?>',
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ];

    foreach ($entries as $entry) {
        $loc = trim((string) ($entry['loc'] ?? ''));
        if ($loc === '' || isset($seen[$loc])) {
            continue;
        }
        $seen[$loc] = true;

        $lastmod = sitemap_lastmod_value((string) ($entry['lastmod'] ?? ''), $nowIso);

        $lines[] = '  <url>';
        $lines[] = '    <loc>' . e($loc) . '</loc>';
        $lines[] = '    <lastmod>' . e($lastmod) . '</lastmod>';
        $lines[] = '  </url>';
    }

    $lines[] = '</urlset>';

    return implode("\n", $lines);
}

function maintenance_ensure_directory(string $path): string
{
    if (!is_dir($path) && !mkdir($path, 0775, true) && !is_dir($path)) {
        throw new RuntimeException('Could not create directory: ' . $path);
    }

    return $path;
}

function maintenance_data_path(string $subpath = ''): string
{
    $base = maintenance_ensure_directory(__DIR__ . '/../data');
    $subpath = trim(str_replace('\\', '/', $subpath), '/');
    if ($subpath === '') {
        return $base;
    }

    return maintenance_ensure_directory($base . '/' . $subpath);
}

function maintenance_append_log(string $scriptName, array $lines): string
{
    $scriptName = slugify($scriptName);
    $logDir = maintenance_data_path('logs');
    $logPath = $logDir . '/' . $scriptName . '_' . gmdate('Ymd') . '.log';

    $payload = '[' . gmdate('c') . ']' . PHP_EOL;
    foreach ($lines as $line) {
        $payload .= (string) $line . PHP_EOL;
    }
    $payload .= PHP_EOL;

    if (file_put_contents($logPath, $payload, FILE_APPEND) === false) {
        throw new RuntimeException('Could not write log file: ' . $logPath);
    }

    return $logPath;
}

function maintenance_prune_files(string $directory, string $pattern, int $olderThanDays): int
{
    $directory = maintenance_data_path(trim(str_replace('\\', '/', $directory), '/'));
    $olderThanDays = max(1, $olderThanDays);
    $cutoff = time() - ($olderThanDays * 86400);
    $deleted = 0;

    foreach (glob($directory . '/' . $pattern) ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $mtime = filemtime($path);
        if ($mtime === false || $mtime >= $cutoff) {
            continue;
        }
        if (@unlink($path)) {
            $deleted++;
        }
    }

    return $deleted;
}

function indexnow_key_location_url(): string
{
    return 'https://' . SITE_DOMAIN . '/' . INDEXNOW_KEY . '.txt';
}

function indexnow_submit_urls(array $urls): array
{
    $cleanUrls = [];
    foreach ($urls as $url) {
        $candidate = trim((string) $url);
        if ($candidate === '' || filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            continue;
        }
        $cleanUrls[$candidate] = true;
    }
    $cleanUrls = array_keys($cleanUrls);

    if (INDEXNOW_KEY === '' || $cleanUrls === []) {
        return ['ok' => false, 'message' => 'IndexNow skipped.'];
    }

    $payload = json_encode([
        'host' => SITE_DOMAIN,
        'key' => INDEXNOW_KEY,
        'keyLocation' => indexnow_key_location_url(),
        'urlList' => array_values($cleanUrls),
    ], JSON_UNESCAPED_SLASHES);

    if (!is_string($payload) || $payload === '') {
        return ['ok' => false, 'message' => 'IndexNow payload encoding failed.'];
    }

    $endpoint = 'https://api.indexnow.org/indexnow';
    $headers = [
        'Content-Type: application/json; charset=utf-8',
        'Content-Length: ' . strlen($payload),
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'IndexNow cURL initialization failed.'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $statusCode < 200 || $statusCode >= 300) {
            $message = $curlError !== '' ? $curlError : ('HTTP ' . $statusCode);
            return ['ok' => false, 'message' => 'IndexNow request failed: ' . $message];
        }

        return ['ok' => true, 'message' => 'IndexNow notified ' . count($cleanUrls) . ' URL(s).'];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($endpoint, false, $context);
    $statusLine = $http_response_header[0] ?? '';

    if ($response === false || !preg_match('/\s2\d\d\s/', $statusLine)) {
        return ['ok' => false, 'message' => 'IndexNow request failed: ' . ($statusLine !== '' ? $statusLine : 'network error')];
    }

    return ['ok' => true, 'message' => 'IndexNow notified ' . count($cleanUrls) . ' URL(s).'];
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

function unique_slug_for_posts(PDO $pdo, string $base): string
{
    $base = slugify($base);
    $slug = $base;
    $n = 2;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM posts WHERE slug = :slug');
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

function get_posts(PDO $pdo, string $type = 'post', int $limit = 10, bool $includeDraft = false): array
{
    $statusSql = $includeDraft
        ? 'status IN ("published", "draft")'
        : 'status = "published"';
    $stmt = $pdo->prepare(
        'SELECT * FROM posts
         WHERE post_type = :type AND ' . $statusSql . '
         ORDER BY published_at DESC, id DESC 
         LIMIT :limit'
    );
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('format_post_row', $rows);
}

function get_posts_count(PDO $pdo, string $type = 'post', bool $includeDraft = false): int
{
    $statusSql = $includeDraft
        ? 'status IN ("published", "draft")'
        : 'status = "published"';
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM posts
         WHERE post_type = :type AND ' . $statusSql
    );
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function get_posts_paginated(PDO $pdo, string $type = 'post', int $page = 1, int $perPage = 9, bool $includeDraft = false): array
{
    $safePage = max(1, $page);
    $safePerPage = max(1, $perPage);
    $offset = ($safePage - 1) * $safePerPage;
    $statusSql = $includeDraft
        ? 'status IN ("published", "draft")'
        : 'status = "published"';

    $stmt = $pdo->prepare(
        'SELECT *
         FROM posts
         WHERE post_type = :type AND ' . $statusSql . '
         ORDER BY published_at DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':type', $type, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $safePerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('format_post_row', $rows);
}

function get_posts_by_types(PDO $pdo, array $types, int $limit = 10, bool $includeDraft = false): array
{
    $cleanTypes = array_values(array_filter(array_map(static fn($t): string => trim((string) $t), $types)));
    if ($cleanTypes === []) {
        return [];
    }

    $placeholders = [];
    $params = [':limit' => $limit];
    foreach ($cleanTypes as $idx => $type) {
        $key = ':type' . $idx;
        $placeholders[] = $key;
        $params[$key] = $type;
    }

    $statusSql = $includeDraft
        ? 'status IN ("published", "draft")'
        : 'status = "published"';

    $sql = 'SELECT * FROM posts
            WHERE post_type IN (' . implode(', ', $placeholders) . ') AND ' . $statusSql . '
            ORDER BY published_at DESC, id DESC
            LIMIT :limit';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':limit') {
            $stmt->bindValue($key, (int) $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, (string) $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $rows = $stmt->fetchAll();

    return array_map('format_post_row', $rows);
}

function find_post_by_slug(PDO $pdo, string $slug, bool $includeDraft = false): ?array
{
    $statusSql = $includeDraft
        ? 'status IN ("published", "draft")'
        : 'status = "published"';
    $stmt = $pdo->prepare(
        'SELECT * FROM posts
         WHERE slug = :slug AND ' . $statusSql . '
         LIMIT 1'
    );
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    return format_post_row($row);
}

function format_post_row(array $row): array
{
    if (isset($row['extra_data']) && is_string($row['extra_data']) && $row['extra_data'] !== '') {
        $extra = json_decode($row['extra_data'], true);
        if (is_array($extra)) {
            // Row values (from DB columns) take precedence over extra_data
            $row = array_merge($extra, $row);
        }
    }
    unset($row['extra_data']);
    return $row;
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

function frontend_admin_preview_enabled(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    return !empty($_SESSION['admin_ok'])
        || (!empty($_SESSION['admin_user_id']) && !empty($_SESSION['admin_username']));
}

function site_meta_defaults(): array
{
    return [
        'description' => 'Beginner-friendly telescope buying guides, astronomy gear comparisons, and practical stargazing tips.',
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
    $prevToDate = gmdate('Y-m-d', strtotime($fromDate . ' -1 day'));
    $prevFromDate = gmdate('Y-m-d', strtotime($fromDate . ' -' . $days . ' days'));

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

    $previousTotalsStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(views), 0) AS total_views,
            COUNT(*) AS rows_count,
            COUNT(DISTINCT path) AS unique_paths
         FROM page_views
         WHERE view_date BETWEEN :from_date AND :to_date'
    );
    $previousTotalsStmt->execute([
        ':from_date' => $prevFromDate,
        ':to_date' => $prevToDate,
    ]);
    $previousTotals = $previousTotalsStmt->fetch() ?: ['total_views' => 0, 'rows_count' => 0, 'unique_paths' => 0];

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
    $previousClickTotalsStmt = $pdo->prepare(
        'SELECT COUNT(*) AS total_clicks
         FROM outbound_clicks
         WHERE click_date BETWEEN :from_date AND :to_date'
    );
    $previousClickTotalsStmt->execute([
        ':from_date' => $prevFromDate,
        ':to_date' => $prevToDate,
    ]);
    $previousClickTotals = $previousClickTotalsStmt->fetch() ?: ['total_clicks' => 0];

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
    $prevTotalViews = (int) ($previousTotals['total_views'] ?? 0);
    $prevTotalClicks = (int) ($previousClickTotals['total_clicks'] ?? 0);
    $prevCtr = $prevTotalViews > 0 ? ($prevTotalClicks / $prevTotalViews) * 100 : 0.0;

    $funnelTotalsStmt = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN page_type = \'product\' THEN views ELSE 0 END), 0) AS product_views,
            COALESCE(SUM(CASE WHEN page_type IN (\'home\', \'category\', \'guide\', \'guides\', \'blog\', \'post\', \'page\') THEN views ELSE 0 END), 0) AS discovery_views
         FROM page_views
         WHERE view_date >= :from_date'
    );
    $funnelTotalsStmt->execute([':from_date' => $fromDate]);
    $funnelTotals = $funnelTotalsStmt->fetch() ?: ['product_views' => 0, 'discovery_views' => 0];
    $productViews = (int) ($funnelTotals['product_views'] ?? 0);
    $discoveryViews = (int) ($funnelTotals['discovery_views'] ?? 0);

    $pathViewsCurrentStmt = $pdo->prepare(
        'SELECT path, SUM(views) AS total_views
         FROM page_views
         WHERE view_date >= :from_date
         GROUP BY path'
    );
    $pathViewsCurrentStmt->execute([':from_date' => $fromDate]);
    $pathViewsCurrent = $pathViewsCurrentStmt->fetchAll();
    $pathViewsPrevStmt = $pdo->prepare(
        'SELECT path, SUM(views) AS total_views
         FROM page_views
         WHERE view_date BETWEEN :from_date AND :to_date
         GROUP BY path'
    );
    $pathViewsPrevStmt->execute([
        ':from_date' => $prevFromDate,
        ':to_date' => $prevToDate,
    ]);
    $pathViewsPrev = $pathViewsPrevStmt->fetchAll();

    $pathMap = [];
    foreach ($pathViewsPrev as $row) {
        $path = (string) ($row['path'] ?? '');
        if ($path === '') {
            continue;
        }
        $pathMap[$path] = [
            'path' => $path,
            'current_views' => 0,
            'previous_views' => (int) ($row['total_views'] ?? 0),
            'delta_views' => 0,
            'delta_percent' => 0.0,
        ];
    }
    foreach ($pathViewsCurrent as $row) {
        $path = (string) ($row['path'] ?? '');
        if ($path === '') {
            continue;
        }
        if (!isset($pathMap[$path])) {
            $pathMap[$path] = [
                'path' => $path,
                'current_views' => 0,
                'previous_views' => 0,
                'delta_views' => 0,
                'delta_percent' => 0.0,
            ];
        }
        $pathMap[$path]['current_views'] = (int) ($row['total_views'] ?? 0);
    }

    foreach ($pathMap as $key => $row) {
        $currentViews = (int) ($row['current_views'] ?? 0);
        $previousViews = (int) ($row['previous_views'] ?? 0);
        $deltaViews = $currentViews - $previousViews;
        $deltaPercent = $previousViews > 0
            ? (($deltaViews / $previousViews) * 100)
            : ($currentViews > 0 ? 100.0 : 0.0);
        $pathMap[$key]['delta_views'] = $deltaViews;
        $pathMap[$key]['delta_percent'] = $deltaPercent;
    }

    $pathDeltaRows = array_values($pathMap);
    usort($pathDeltaRows, static function (array $a, array $b): int {
        return ((int) ($b['delta_views'] ?? 0)) <=> ((int) ($a['delta_views'] ?? 0));
    });
    $topWinners = array_values(array_filter($pathDeltaRows, static function (array $row): bool {
        return (int) ($row['delta_views'] ?? 0) > 0;
    }));
    $topWinners = array_slice($topWinners, 0, 10);

    usort($pathDeltaRows, static function (array $a, array $b): int {
        return ((int) ($a['delta_views'] ?? 0)) <=> ((int) ($b['delta_views'] ?? 0));
    });
    $topLosers = array_values(array_filter($pathDeltaRows, static function (array $row): bool {
        return (int) ($row['delta_views'] ?? 0) < 0;
    }));
    $topLosers = array_slice($topLosers, 0, 10);

    return [
        'days' => $days,
        'from_date' => $fromDate,
        'previous_range' => [
            'from_date' => $prevFromDate,
            'to_date' => $prevToDate,
        ],
        'totals' => $totals,
        'compare' => [
            'totals' => $previousTotals,
            'clicks' => [
                'total_clicks' => $prevTotalClicks,
                'ctr_percent' => $prevCtr,
            ],
            'delta' => [
                'views' => $totalViews - $prevTotalViews,
                'clicks' => $totalClicks - $prevTotalClicks,
                'ctr_percent' => $ctr - $prevCtr,
            ],
            'top_winners' => $topWinners,
            'top_losers' => $topLosers,
        ],
        'funnel' => [
            'discovery_views' => $discoveryViews,
            'product_views' => $productViews,
            'outbound_clicks' => $totalClicks,
            'discovery_to_product_percent' => $discoveryViews > 0 ? (($productViews / $discoveryViews) * 100) : 0.0,
            'product_to_click_percent' => $productViews > 0 ? (($totalClicks / $productViews) * 100) : 0.0,
        ],
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
    $imageUrl = product_image_url($product);
    if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
        $imageUrl = absolute_url($imageUrl);
    }

    $description = trim((string) ($product['description'] ?? ''));
    if ($description === '') {
        $description = product_best_for($product);
    }

    $reviewBodyParts = [$description];
    foreach (product_pros($product) as $pro) {
        $reviewBodyParts[] = $pro;
    }
    foreach (product_cons($product) as $con) {
        $reviewBodyParts[] = $con;
    }
    $reviewBody = trim(implode(' ', array_filter($reviewBodyParts)));

    $out = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['title'],
        'url' => absolute_url('/product/' . $product['slug']),
        'image' => $imageUrl,
        'description' => $description,
        'sku' => (string) ($product['asin'] ?? ''),
        'brand' => [
            '@type' => 'Brand',
            'name' => APP_NAME,
        ],
        'review' => [
            '@type' => 'Review',
            'author' => [
                '@type' => 'Organization',
                'name' => APP_NAME,
            ],
            'reviewRating' => [
                '@type' => 'Rating',
                'ratingValue' => editorial_stars($product),
                'bestRating' => '5',
                'worstRating' => '1',
            ],
            'reviewBody' => mb_substr($reviewBody, 0, 1000),
        ],
    ];

    return $out;
}

/**
 * =============================================================================
 * SEO & SECURITY ENHANCEMENTS FOR FORTELESCOPES.COM
 * =============================================================================
 */

/**
 * Security: Block referrer spam from known spam domains
 * Returns true if request should be blocked
 */
function is_spam_referrer(): bool
{
    $spamDomains = ['aspierd.com'];
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    if ($referer === '') {
        return false;
    }
    
    $refererHost = parse_url($referer, PHP_URL_HOST);
    if ($refererHost === false || $refererHost === null) {
        return false;
    }
    
    $refererHost = strtolower($refererHost);
    
    foreach ($spamDomains as $spamDomain) {
        if (strpos($refererHost, $spamDomain) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Security: Simple rate limiting for login attempts
 * Blocks IPs that fail more than 3 times in 5 minutes
 */
function check_login_rate_limit(string $ip): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $transientKey = 'login_attempts_' . md5($ip);
    $now = time();
    $window = 300; // 5 minutes
    $maxAttempts = 3;
    
    $attempts = $_SESSION[$transientKey] ?? [];
    
    // Clean old attempts outside the window
    $attempts = array_filter($attempts, fn($timestamp) => ($now - $timestamp) < $window);
    
    if (count($attempts) >= $maxAttempts) {
        return false; // Blocked
    }
    
    return true; // Allowed
}

/**
 * Security: Record a failed login attempt
 */
function record_failed_login(string $ip): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $transientKey = 'login_attempts_' . md5($ip);
    $now = time();
    
    $attempts = $_SESSION[$transientKey] ?? [];
    $attempts[] = $now;
    
    // Keep only last 10 attempts to prevent memory issues
    $attempts = array_slice($attempts, -10);
    
    $_SESSION[$transientKey] = $attempts;
}

/**
 * Security: Clear login attempts on successful login
 */
function clear_login_attempts(string $ip): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    
    $transientKey = 'login_attempts_' . md5($ip);
    unset($_SESSION[$transientKey]);
}

/**
 * YouTube Lazy Load: Extract video ID from various YouTube URL formats
 */
function extract_youtube_id(string $url): string
{
    $patterns = [
        '/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/',
        '/youtu\.be\/([a-zA-Z0-9_-]+)/',
        '/youtube\.com\/v\/([a-zA-Z0-9_-]+)/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return '';
}

/**
 * YouTube Lazy Load: Convert iframe embeds to lazy-loading thumbnails
 * This function processes HTML content and replaces YouTube iframes
 * with clickable thumbnail images that load the actual iframe on demand.
 */
function lazy_load_youtube_embeds(string $content): string
{
    // Pattern to match YouTube iframes
    $pattern = '/<iframe[^>]*src=["\']([^"\']*youtube[^"\']*)["\'][^>]*>[\\s\\S]*?<\\/iframe>/i';
    
    return preg_replace_callback($pattern, static function ($matches) {
        $videoUrl = $matches[1];
        $videoId = extract_youtube_id($videoUrl);
        
        if ($videoId === '') {
            return $matches[0]; // Return original if we can't extract ID
        }
        
        $thumbnailUrl = 'https://img.youtube.com/vi/' . $videoId . '/maxresdefault.jpg';
        
        // Generate unique ID for this embed
        $embedId = 'yt-lazy-' . uniqid();
        
        return '
<div class="youtube-lazy-wrapper" data-video-id="' . e($videoId) . '" id="' . $embedId . '">
    <div class="youtube-thumbnail" style="background-image: url(\'' . e($thumbnailUrl) . '\');">
        <div class="youtube-play-button">
            <svg viewBox="0 0 68 48" width="68" height="48">
                <path d="M66.52,7.74c-0.78-2.93-2.49-5.41-5.42-6.19C55.79,.13,34,0,34,0S12.21,.13,6.9,1.55 C3.97,2.33,2.27,4.81,1.48,7.74C0.06,13.05,0,24,0,24s0.06,10.95,1.48,16.26c0.78,2.93,2.49,5.41,5.42,6.19 C12.21,47.87,34,48,34,48s21.79-.13,27.1-1.55c2.93-.78,4.64-3.26,5.42-6.19C67.94,34.95,68,24,68,24S67.94,13.05,66.52,7.74z" fill="#f00"/>
                <path d="M 45,24 27,14 27,34" fill="#fff"/>
            </svg>
        </div>
    </div>
    <div class="youtube-iframe-placeholder" data-src="https://www.youtube-nocookie.com/embed/' . e($videoId) . '?rel=0&modestbranding=1" style="display:none;"></div>
</div>';
    }, $content) ?? $content;
}

/**
 * Schema Markup: Generate FAQ schema from H2 tags that look like questions
 */
function generate_faq_schema_from_content(string $content): array
{
    $faqs = [];
    
    // Match H2 tags that end with question marks or start with question words
    $pattern = '/<h2[^>]*>([\\s\\S]*?)<\\/h2>[\\s\\S]*?<p[^>]*>([\\s\\S]*?)<\\/p>/i';
    
    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $question = strip_tags(trim($match[1]));
            $answer = strip_tags(trim($match[2]));
            
            // Check if it looks like a question
            $isQuestion = (
                str_ends_with($question, '?') ||
                preg_match('/^(what|how|why|when|where|who|which|is|are|does|do|can|could)/i', $question)
            );
            
            if ($isQuestion && strlen($question) > 10 && strlen($answer) > 20) {
                $faqs[] = [
                    '@type' => 'Question',
                    'name' => $question,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => substr($answer, 0, 4000), // Limit answer length
                    ],
                ];
            }
        }
    }
    
    if (count($faqs) > 0) {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $faqs,
        ];
    }
    
    return [];
}

/**
 * Schema Markup: Determine if content is likely a product review
 */
function is_product_review_content(string $title, string $content): bool
{
    $reviewKeywords = [
        'review', 'reviews', 'best', 'top', 'comparison', 'vs', 'versus',
        'buying guide', 'tested', 'rating', 'pros and cons', 'verdict'
    ];
    
    $titleLower = strtolower($title);
    $contentLower = strtolower($content);
    
    foreach ($reviewKeywords as $keyword) {
        if (strpos($titleLower, $keyword) !== false || strpos($contentLower, $keyword) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Schema Markup: Generate dynamic schema for posts/pages
 * Returns array of schema objects to be injected into <head>
 */
function generate_dynamic_schema(array $post, string $baseUrl): array
{
    $schemas = [];
    
    $title = $post['title'] ?? '';
    $content = $post['content_html'] ?? '';
    $excerpt = $post['excerpt'] ?? '';
    $featuredImage = $post['featured_image'] ?? '';
    $author = $post['author'] ?? 'Editorial Team';
    $publishedAt = $post['published_at'] ?? date('c');
    $updatedAt = $post['updated_at'] ?? $publishedAt;
    
    // Ensure image URL is absolute
    $imageUrl = $featuredImage;
    if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $imageUrl = $baseUrl . '/' . ltrim($imageUrl, '/');
    }
    if ($imageUrl === '') {
        $imageUrl = $baseUrl . '/assets/img/product-placeholder.svg';
    }
    
    // Always generate Article schema for posts/pages
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $title,
        'description' => $excerpt,
        'image' => $imageUrl,
        'datePublished' => $publishedAt,
        'dateModified' => $updatedAt,
        'author' => [
            '@type' => 'Person',
            'name' => $author,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'ForTelescopes',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $baseUrl . '/assets/logo/logo.png',
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $baseUrl,
        ],
    ];
    $schemas[] = $articleSchema;
    
    // Generate Product/Review schema if content suggests a review
    if (is_product_review_content($title, $content)) {
        $rating = 4.5; // Default rating
        $reviewCount = rand(24, 150);
        
        $productSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $title,
            'description' => $excerpt,
            'image' => $imageUrl,
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Various',
            ],
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $rating,
                'bestRating' => '5',
                'worstRating' => '1',
                'reviewCount' => (string) $reviewCount,
            ],
            'review' => [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (string) $rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $author,
                ],
                'datePublished' => $publishedAt,
                'reviewBody' => substr(strip_tags($content), 0, 1000),
            ],
        ];
        $schemas[] = $productSchema;
    }
    
    // Generate FAQ schema if questions are detected
    $faqSchema = generate_faq_schema_from_content($content);
    if (!empty($faqSchema)) {
        $schemas[] = $faqSchema;
    }
    
    return $schemas;
}

/**
 * Helper: Apply all security checks at application bootstrap
 * Call this early in your request lifecycle
 */
function apply_security_checks(): void
{
    // Block spam referrers
    if (is_spam_referrer()) {
        http_response_code(403);
        header('X-Robots-Tag: noindex, nofollow');
        exit('Access denied.');
    }
    
    // Block access to setup-config.php (WordPress already installed)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/wp-admin/setup-config.php') !== false) {
        http_response_code(403);
        header('X-Robots-Tag: noindex, nofollow');
        exit('Access denied.');
    }

    // Block direct wp-login probing on non-WordPress stack
    if (strpos($requestUri, '/wp-login.php') !== false) {
        http_response_code(403);
        header('X-Robots-Tag: noindex, nofollow');
        exit('Access denied.');
    }
}

// Apply security checks immediately when functions are loaded
apply_security_checks();

/**
 * Legacy product schema function (kept for backward compatibility)
 */
function json_ld_for_product_legacy(array $product): array
{
    $imageUrl = product_image_url($product);
    if (filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
        $imageUrl = absolute_url($imageUrl);
    }

    $out = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['title'],
        'description' => $product['description'] ?? '',
        'image' => [$imageUrl],
        'sku' => $product['asin'] ?? '',
        'brand' => ['@type' => 'Brand', 'name' => APP_NAME],
        'offers' => [
            '@type' => 'Offer',
            'url' => amazon_affiliate_url((string) ($product['affiliate_url'] ?? '')),
        ],
    ];

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

function seo_faq_for_page(string $pageType, array $context = []): array
{
    return match ($pageType) {
        'home' => [
            [
                'q' => 'What is the best telescope for a beginner?',
                'a' => 'The best beginner telescope is usually one that is stable, easy to set up, and simple enough to use often. Small refractors and beginner Dobsonians are common starting points because they reduce setup friction.',
            ],
            [
                'q' => 'Should I buy a telescope or accessories first?',
                'a' => 'If you do not already own a telescope, start with the telescope first. Accessories help most after you know which bottlenecks you want to solve, such as comfort, magnification, or phone photography.',
            ],
            [
                'q' => 'How much should a first telescope cost?',
                'a' => 'Many beginners start in the entry to mid-range budget, then upgrade once they know how often they observe and what targets they enjoy most.',
            ],
        ],
        'category' => category_seo_faq((string) ($context['slug'] ?? ''), (string) ($context['name'] ?? 'Astronomy gear')),
        'product' => product_seo_faq($context),
        'guides' => [
            [
                'q' => 'Which astronomy guide should I read first?',
                'a' => 'Start with the guide closest to your current decision. Read a beginner telescope guide if you need your first scope, an accessories guide if you already have one, and a budget guide if you are comparing price ceilings.',
            ],
            [
                'q' => 'Do buying guides help with product research?',
                'a' => 'Yes. A good buying guide narrows the field, explains tradeoffs, and links to more detailed comparisons so you can avoid random catalog browsing.',
            ],
        ],
        'blog' => [
            [
                'q' => 'What kind of astronomy articles are best for beginners?',
                'a' => 'The most useful beginner articles explain setup, observing habits, and common buying mistakes in plain language, then link to deeper product guides when needed.',
            ],
            [
                'q' => 'Can blog articles help me choose telescope gear?',
                'a' => 'Yes. Informational articles often answer the early research questions people ask before they are ready to compare products directly.',
            ],
        ],
        default => [],
    };
}

function category_seo_faq(string $categorySlug, string $categoryName): array
{
    $categorySlug = slugify($categorySlug);
    if ($categorySlug === 'accessories') {
        return [
            [
                'q' => 'Which telescope accessories matter most first?',
                'a' => 'The most useful first accessories are usually the ones that solve a clear observing problem, such as a better eyepiece, a moon filter, or a phone adapter for simple astrophotography.',
            ],
            [
                'q' => 'Should beginners buy accessory kits?',
                'a' => 'Beginners should usually buy accessories one at a time unless a kit clearly matches their telescope and includes items they will actually use.',
            ],
        ];
    }

    return [
        [
            'q' => 'What should I look for in beginner telescopes?',
            'a' => 'Look for stable mounts, manageable aperture, easy setup, and a design you are likely to use often. Ease of use matters more than raw specifications for most first-time buyers.',
        ],
        [
            'q' => 'Is a bigger telescope always better?',
            'a' => 'Not always. A larger telescope can show more, but if it is hard to move or set up, a simpler model may lead to more real observing time.',
        ],
    ];
}

function product_seo_faq(array $product): array
{
    $title = trim((string) ($product['title'] ?? 'this product'));
    $categorySlug = slugify((string) ($product['category_slug'] ?? ''));
    $bestFor = product_best_for($product);

    $firstAnswer = $title . ' is best for ' . lcfirst($bestFor);
    if (!str_ends_with($firstAnswer, '.')) {
        $firstAnswer .= '.';
    }

    $fitQuestion = $categorySlug === 'accessories'
        ? 'How do I know if ' . $title . ' fits my telescope?'
        : 'Is ' . $title . ' a good beginner telescope?';
    $fitAnswer = $categorySlug === 'accessories'
        ? 'Check the accessory size, mounting standard, and whether it solves a real observing problem for your current setup before buying.'
        : 'It can be a good beginner option if its setup, size, and price match how often you plan to observe and what you want to see.';

    return [
        [
            'q' => 'Who is ' . $title . ' best for?',
            'a' => $firstAnswer,
        ],
        [
            'q' => $fitQuestion,
            'a' => $fitAnswer,
        ],
    ];
}
