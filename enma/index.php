<?php

declare(strict_types=1);

// Autoloader simple para MVC
spl_autoload_register(function ($class) {
    $prefix = 'Enma\\';
    $base_dir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $relative_path = str_replace('\\', '/', $relative_class);

    // ENMA keeps top-level MVC folders in lowercase on disk.
    // On Linux, class namespace segment case must be mapped explicitly.
    $parts = explode('/', $relative_path);
    if (!empty($parts[0])) {
        $parts[0] = strtolower($parts[0]);
    }

    $file = $base_dir . implode('/', $parts) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

session_start();

// Router simple: si viene ?action=analytics u otras acciones MVC, usar el controlador correspondiente
$action = $_GET['action'] ?? '';

// Handle MVC actions
if ($action === 'analytics') {
    // Keep a single ENMA shell/theme: analytics is rendered as a normal tab.
    header('Location: ?tab=analytics');
    exit;
}

// Si no es una accion MVC, continuar con el legacy
require_once __DIR__ . '/../includes/bootstrap.php';

$errors = [];
$flash = null;
$maxLoginAttempts = 5;
$lockSeconds = 600;
$_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0);
$_SESSION['login_locked_until'] = (int) ($_SESSION['login_locked_until'] ?? 0);
$isLocked = ($_SESSION['login_locked_until'] > time());

// Include helpers first (provides enma_handle_image_upload and enma_normalize_editor_html)
require_once __DIR__ . '/helpers.php';

// Runtime state used/updated by handlers
$authenticated = !empty($_SESSION['admin_ok']);
$maintenanceLog = [];
$advancedEnabled = ENMA_ADVANCED_KEY !== '';
$editingPost = null;
$editingProduct = null;
$editingUser = null;

// Include handlers
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/posts_handler.php';
require_once __DIR__ . '/products_handler.php';
require_once __DIR__ . '/media_handler.php';
require_once __DIR__ . '/users_handler.php';
require_once __DIR__ . '/maintenance.php';

// Refresh auth state in case auth.php changed the session
$authenticated = !empty($_SESSION['admin_ok']);

$activeTab = $authenticated ? (string) ($_GET['tab'] ?? 'control') : 'overview';
if (!in_array($activeTab, ['control', 'overview', 'products', 'media', 'posts', 'indexation', 'prompts', 'users', 'views', 'analytics', 'maintenance'], true)) {
    $activeTab = 'control';
}
$viewDays = $authenticated ? max(1, min(180, (int) ($_GET['days'] ?? 30))) : 30;
$viewsDashboard = ($authenticated && $activeTab === 'views') ? get_views_dashboard($pdo, $viewDays) : [];
$postAutosaveEnabled = !empty($postAutosaveEnabled);

if (!function_exists('enma_page_value')) {
    function enma_page_value(string $key): int
    {
        return max(1, (int) ($_GET[$key] ?? 1));
    }
}

if (!function_exists('enma_total_pages')) {
    function enma_total_pages(int $totalRows, int $perPage): int
    {
        return max(1, (int) ceil(max(0, $totalRows) / max(1, $perPage)));
    }
}

if (!function_exists('enma_render_pagination')) {
    function enma_render_pagination(string $tab, string $pageParam, int $currentPage, int $totalPages, array $extra = []): string
    {
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:12px;">';
        $buildUrl = static function (int $page) use ($tab, $pageParam, $extra): string {
            $params = array_merge(['tab' => $tab, $pageParam => $page], $extra);
            return url('/enma/?' . http_build_query($params));
        };

        if ($currentPage > 1) {
            $html .= '<a class="tab" href="' . e($buildUrl($currentPage - 1)) . '">Prev</a>';
        }

        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        for ($page = $start; $page <= $end; $page++) {
            $class = $page === $currentPage ? 'tab active' : 'tab';
            $html .= '<a class="' . $class . '" href="' . e($buildUrl($page)) . '">' . $page . '</a>';
        }

        if ($currentPage < $totalPages) {
            $html .= '<a class="tab" href="' . e($buildUrl($currentPage + 1)) . '">Next</a>';
        }

        $html .= '<span class="muted">Page ' . $currentPage . ' of ' . $totalPages . '</span>';
        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('enma_post_public_path')) {
    function enma_post_public_path(array $post): string
    {
        $slug = trim((string) ($post['slug'] ?? ''));
        if ($slug === '') {
            return '';
        }

        $postType = trim((string) ($post['post_type'] ?? 'post'));
        return $postType === 'guide' ? '/' . $slug : '/blog/' . $slug;
    }
}
require_once __DIR__ . '/indexation_handler.php';
require_once __DIR__ . '/operator_handler.php';

$productQuery = $authenticated ? trim((string) ($_GET['q'] ?? '')) : '';
$allProducts = [];
$productsPage = $authenticated ? enma_page_value('products_page') : 1;
$productsPerPage = 25;
$productsTotal = 0;
$productsTotalPages = 1;
if ($authenticated && $activeTab === 'products') {
    $productLinkChecksAvailable = false;
    try {
        $productLinkChecksAvailable = (bool) $pdo->query(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = ' . $pdo->quote(DB_NAME) . '
               AND table_name = "product_link_checks"
             LIMIT 1'
        )->fetchColumn();
    } catch (Throwable $e) {
        $productLinkChecksAvailable = false;
    }

    $productSelectSql = 'SELECT
            p.id,
            p.asin,
            p.slug,
            p.title,
            p.category_name,
            p.status,
            p.image_url,
            p.last_synced_at,
            p.affiliate_url';
    $productFromSql = ' FROM products p';
    if ($productLinkChecksAvailable) {
        $productSelectSql .= ',
            plc.state AS link_state,
            plc.http_status AS link_http_status';
        $productFromSql .= ' LEFT JOIN product_link_checks plc ON plc.product_id = p.id';
    }

    if ($productQuery !== '') {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM products
             WHERE asin LIKE :q OR title LIKE :q OR category_name LIKE :q'
        );
        $countStmt->execute([':q' => '%' . $productQuery . '%']);
        $productsTotal = (int) $countStmt->fetchColumn();
        $productsTotalPages = enma_total_pages($productsTotal, $productsPerPage);
        $productsPage = min($productsPage, $productsTotalPages);
        $stmt = $pdo->prepare(
            $productSelectSql
            . $productFromSql
            . ' WHERE p.asin LIKE :q OR p.title LIKE :q OR p.category_name LIKE :q
                ORDER BY p.id DESC
                LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':q', '%' . $productQuery . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $productsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($productsPage - 1) * $productsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $allProducts = $stmt->fetchAll();
    } else {
        $productsTotal = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $productsTotalPages = enma_total_pages($productsTotal, $productsPerPage);
        $productsPage = min($productsPage, $productsTotalPages);
        $stmt = $pdo->prepare(
            $productSelectSql
            . $productFromSql
            . ' ORDER BY p.id DESC
                LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $productsPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($productsPage - 1) * $productsPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $allProducts = $stmt->fetchAll();
    }
}

$overviewStats = [];
if ($authenticated && $activeTab === 'overview') {
$views30dSql = "SELECT COALESCE(SUM(views),0) FROM page_views WHERE view_date >= DATE_SUB(UTC_DATE(), INTERVAL 29 DAY)";
$overviewStats = [
        'products' => (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'categories' => (int) $pdo->query('SELECT COUNT(DISTINCT category_slug) FROM products')->fetchColumn(),
        'missing_tags' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE affiliate_url NOT LIKE '%tag=%'")->fetchColumn(),
        'missing_images' => (int) $pdo->query("SELECT COUNT(*) FROM products WHERE image_url IS NULL OR image_url = ''")->fetchColumn(),
        'views_30d' => (int) $pdo->query($views30dSql)->fetchColumn(),
        'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
        'users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    ];
}

$allUsers = [];
$recentAdminActivity = [];
$activityPage = $authenticated ? enma_page_value('activity_page') : 1;
$activityPerPage = 20;
$activityTotal = 0;
$activityTotalPages = 1;
if ($authenticated && ($activeTab === 'users' || $activeTab === 'overview')) {
    $activityTotal = (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_log')->fetchColumn();
    $activityTotalPages = enma_total_pages($activityTotal, $activityPerPage);
    $activityPage = min($activityPage, $activityTotalPages);
    $activityStmt = $pdo->prepare(
        'SELECT id, admin_username, action_key, entity_type, entity_id, details_json, created_at
         FROM admin_activity_log
         ORDER BY id DESC
         LIMIT :limit OFFSET :offset'
    );
    $activityStmt->bindValue(':limit', $activityPerPage, PDO::PARAM_INT);
    $activityStmt->bindValue(':offset', ($activityPage - 1) * $activityPerPage, PDO::PARAM_INT);
    $activityStmt->execute();
    $recentAdminActivity = $activityStmt->fetchAll();
}
$usersPage = $authenticated ? enma_page_value('users_page') : 1;
$usersPerPage = 20;
$usersTotal = 0;
$usersTotalPages = 1;
$usersActiveCount = 0;
$usersInactiveCount = 0;
if ($authenticated && $activeTab === 'users') {
    $usersActiveCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
    $usersInactiveCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'inactive'")->fetchColumn();

    if ($userSearch !== '') {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM users
             WHERE email LIKE :q OR username LIKE :q OR display_name LIKE :q'
        );
        $countStmt->execute([':q' => '%' . $userSearch . '%']);
        $usersTotal = (int) $countStmt->fetchColumn();
        $usersTotalPages = enma_total_pages($usersTotal, $usersPerPage);
        $usersPage = min($usersPage, $usersTotalPages);
        $stmt = $pdo->prepare(
            'SELECT id, email, username, display_name, role, status, last_login_at, created_at, updated_at
             FROM users
             WHERE email LIKE :q OR username LIKE :q OR display_name LIKE :q
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':q', '%' . $userSearch . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $usersPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($usersPage - 1) * $usersPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $allUsers = $stmt->fetchAll();
    } else {
        $usersTotal = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $usersTotalPages = enma_total_pages($usersTotal, $usersPerPage);
        $usersPage = min($usersPage, $usersTotalPages);
        $stmt = $pdo->prepare(
            'SELECT id, email, username, display_name, role, status, last_login_at, created_at, updated_at
             FROM users
             ORDER BY id DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $usersPerPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', ($usersPage - 1) * $usersPerPage, PDO::PARAM_INT);
        $stmt->execute();
        $allUsers = $stmt->fetchAll();
    }
}

$postsPage = $authenticated ? enma_page_value('posts_page') : 1;
$postsPerPage = 20;
$postsTotal = 0;
$postsTotalPages = 1;
$postsDraftCount = 0;
$postsPublishedCount = 0;
$allPosts = [];
$postsStatusFilter = $authenticated ? strtolower(trim((string) ($_GET['posts_status'] ?? 'all'))) : 'all';
if (!in_array($postsStatusFilter, ['all', 'published', 'draft'], true)) {
    $postsStatusFilter = 'all';
}
if ($authenticated && $activeTab === 'posts') {
    $postsDraftCount = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn();
    $postsPublishedCount = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'")->fetchColumn();

    $whereSql = '';
    if ($postsStatusFilter !== 'all') {
        $whereSql = ' WHERE status = :status';
    }

    $countSql = 'SELECT COUNT(*) FROM posts' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    if ($postsStatusFilter !== 'all') {
        $countStmt->bindValue(':status', $postsStatusFilter, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $postsTotal = (int) $countStmt->fetchColumn();

    $postsTotalPages = enma_total_pages($postsTotal, $postsPerPage);
    $postsPage = min($postsPage, $postsTotalPages);
    $stmt = $pdo->prepare(
        'SELECT id, title, slug, post_type, status, published_at
         FROM posts
         ' . $whereSql . '
         ORDER BY (status = "draft") DESC, published_at DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    if ($postsStatusFilter !== 'all') {
        $stmt->bindValue(':status', $postsStatusFilter, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $postsPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', ($postsPage - 1) * $postsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $allPosts = $stmt->fetchAll();
}

$analyticsDashboard = [];
if ($authenticated && $activeTab === 'analytics') {
    try {
        $analytics = new \Enma\Models\Analytics();
        $analyticsDashboard = [
            'stats' => $analytics->getDashboardStats(),
            'chart_data' => $analytics->getTrafficChartData(),
            'top_agents' => $analytics->getTopUserAgents(50),
            'suspicious_ips' => $analytics->getSuspiciousIPs(),
            'recent_logs' => $analytics->getRecentLogs(200),
        ];
    } catch (Throwable $e) {
        $errors[] = 'Analytics load failed: ' . $e->getMessage();
        $analyticsDashboard = [
            'stats' => [],
            'chart_data' => [],
            'top_agents' => [],
            'suspicious_ips' => [],
            'recent_logs' => [],
        ];
    }
}

$dbTables = [];
if ($authenticated && $activeTab === 'maintenance') {
    $tableNames = ['products', 'page_views', 'page_view_hits', 'outbound_clicks', 'posts', 'users', 'admin_activity_log'];
    foreach ($tableNames as $tableName) {
        try {
            $count = (int) $pdo->query('SELECT COUNT(*) FROM ' . $tableName)->fetchColumn();
        } catch (Throwable $e) {
            $count = -1;
        }
        $dbTables[] = ['name' => $tableName, 'rows' => $count];
    }
}

if (!function_exists('enma_human_last_run')) {
    function enma_human_last_run(?string $isoDate): string
    {
        if ($isoDate === null || trim($isoDate) === '') {
            return 'Never';
        }

        $ts = strtotime($isoDate);
        if ($ts === false) {
            return $isoDate;
        }

        $diff = time() - $ts;
        if ($diff < 60) {
            return 'Just now';
        }
        if ($diff < 3600) {
            return floor($diff / 60) . 'm ago';
        }
        if ($diff < 86400) {
            return floor($diff / 3600) . 'h ago';
        }

        return floor($diff / 86400) . 'd ago';
    }
}

if (!function_exists('enma_signed_number')) {
    function enma_signed_number(float $value, int $decimals = 0): string
    {
        $normalized = round($value, $decimals);
        $prefix = $normalized > 0 ? '+' : '';
        return $prefix . number_format($normalized, $decimals);
    }
}

if (!function_exists('enma_export_cell')) {
    function enma_export_cell($value): string
    {
        $text = trim((string) $value);
        return str_replace(["\r", "\n", "\t"], [' ', ' ', ' '], $text);
    }
}

$productsPagination = $authenticated && $activeTab === 'products'
    ? enma_render_pagination('products', 'products_page', $productsPage, $productsTotalPages, $productQuery !== '' ? ['q' => $productQuery] : [])
    : '';
$postsPagination = $authenticated && $activeTab === 'posts'
    ? enma_render_pagination('posts', 'posts_page', $postsPage, $postsTotalPages, $postsStatusFilter !== 'all' ? ['posts_status' => $postsStatusFilter] : [])
    : '';
$indexationPagination = $authenticated && $activeTab === 'indexation'
    ? enma_render_pagination(
        'indexation',
        'indexation_page',
        $indexationPage,
        $indexationTotalPages,
        array_filter([
            'idx_state' => $indexationStateFilter !== 'all' ? $indexationStateFilter : null,
            'idx_type' => $indexationTypeFilter !== 'all' ? $indexationTypeFilter : null,
            'idx_indexed' => $indexationIndexedFilter !== 'all' ? $indexationIndexedFilter : null,
            'idx_sort' => $indexationSort !== 'priority' ? $indexationSort : null,
        ], static fn($value): bool => $value !== null && $value !== '')
    )
    : '';
$usersPagination = $authenticated && $activeTab === 'users'
    ? enma_render_pagination('users', 'users_page', $usersPage, $usersTotalPages, $userSearch !== '' ? ['user_q' => $userSearch] : [])
    : '';
$activityPagination = $authenticated && ($activeTab === 'users' || $activeTab === 'overview')
    ? enma_render_pagination($activeTab === 'overview' ? 'overview' : 'users', 'activity_page', $activityPage, $activityTotalPages, $activeTab === 'users' && $userSearch !== '' ? ['user_q' => $userSearch] : [])
    : '';
$allMedia = [];
$mediaImageOptions = [];
$mediaFeaturedProductOptions = [];
$mediaPage = $authenticated ? enma_page_value('media_page') : 1;
$mediaPerPage = 24;
$mediaTotal = 0;
$mediaTotalPages = 1;
$mediaTableReady = false;
if ($authenticated && $activeTab === 'media') {
    try {
        $mediaTableReady = function_exists('enma_media_table_exists') && enma_media_table_exists($pdo);
        if ($mediaTableReady) {
            $mediaTotal = (int) $pdo->query('SELECT COUNT(*) FROM media_library')->fetchColumn();
            $mediaTotalPages = enma_total_pages($mediaTotal, $mediaPerPage);
            $mediaPage = min($mediaPage, $mediaTotalPages);
            $stmt = $pdo->prepare(
                'SELECT id, media_type, mime_type, file_ext, original_name, stored_name, file_url, file_size, title, alt_text, notes, status, created_at
                 FROM media_library
                 ORDER BY id DESC
                 LIMIT :limit OFFSET :offset'
            );
            $stmt->bindValue(':limit', $mediaPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', ($mediaPage - 1) * $mediaPerPage, PDO::PARAM_INT);
            $stmt->execute();
            $allMedia = $stmt->fetchAll();

            $imageOptionsStmt = $pdo->prepare(
                'SELECT file_url, title, original_name
                 FROM media_library
                 WHERE media_type = "image" AND status = "active"
                 ORDER BY id DESC
                 LIMIT 400'
            );
            $imageOptionsStmt->execute();
            $mediaImageOptions = $imageOptionsStmt->fetchAll();

            $productOptionsStmt = $pdo->prepare(
                'SELECT id, title
                 FROM products
                 WHERE status = "published"
                 ORDER BY id DESC
                 LIMIT 500'
            );
            $productOptionsStmt->execute();
            $mediaFeaturedProductOptions = $productOptionsStmt->fetchAll();
        }
    } catch (Throwable $e) {
        $mediaTableReady = false;
        $errors[] = 'Media library load failed: ' . $e->getMessage();
    }
}
$homeHeroSettings = [
    'image' => '',
    'image_2x' => '',
    'alt' => '',
    'title' => '',
    'subtitle' => '',
    'cta_label' => '',
    'cta_url' => '',
    'overlay' => '55',
    'tile_1_image' => '',
    'tile_1_title' => '',
    'tile_1_cta_label' => '',
    'tile_1_cta_url' => '',
    'tile_2_image' => '',
    'tile_2_title' => '',
    'tile_2_cta_label' => '',
    'tile_2_cta_url' => '',
    'featured_ids' => '',
];
$homeHeroDraftSettings = $homeHeroSettings;
if ($authenticated && $activeTab === 'media') {
    $homeHeroSettings['image'] = site_setting_get($pdo, 'home_hero_image', '');
    $homeHeroSettings['image_2x'] = site_setting_get($pdo, 'home_hero_image_2x', '');
    $homeHeroSettings['alt'] = site_setting_get($pdo, 'home_hero_alt', '');
    $homeHeroSettings['title'] = site_setting_get($pdo, 'home_hero_title', '');
    $homeHeroSettings['subtitle'] = site_setting_get($pdo, 'home_hero_subtitle', '');
    $homeHeroSettings['cta_label'] = site_setting_get($pdo, 'home_hero_cta_label', '');
    $homeHeroSettings['cta_url'] = site_setting_get($pdo, 'home_hero_cta_url', '');
    $homeHeroSettings['overlay'] = site_setting_get($pdo, 'home_hero_overlay', '55');
    $homeHeroSettings['tile_1_image'] = site_setting_get($pdo, 'home_promo_tile_1_image', '');
    $homeHeroSettings['tile_1_title'] = site_setting_get($pdo, 'home_promo_tile_1_title', '');
    $homeHeroSettings['tile_1_cta_label'] = site_setting_get($pdo, 'home_promo_tile_1_cta_label', '');
    $homeHeroSettings['tile_1_cta_url'] = site_setting_get($pdo, 'home_promo_tile_1_cta_url', '');
    $homeHeroSettings['tile_2_image'] = site_setting_get($pdo, 'home_promo_tile_2_image', '');
    $homeHeroSettings['tile_2_title'] = site_setting_get($pdo, 'home_promo_tile_2_title', '');
    $homeHeroSettings['tile_2_cta_label'] = site_setting_get($pdo, 'home_promo_tile_2_cta_label', '');
    $homeHeroSettings['tile_2_cta_url'] = site_setting_get($pdo, 'home_promo_tile_2_cta_url', '');
    $homeHeroSettings['featured_ids'] = site_setting_get($pdo, 'home_featured_product_ids', '');
    $homeHeroDraftSettings['image'] = site_setting_get($pdo, 'draft_home_hero_image', $homeHeroSettings['image']);
    $homeHeroDraftSettings['image_2x'] = site_setting_get($pdo, 'draft_home_hero_image_2x', $homeHeroSettings['image_2x']);
    $homeHeroDraftSettings['alt'] = site_setting_get($pdo, 'draft_home_hero_alt', $homeHeroSettings['alt']);
    $homeHeroDraftSettings['title'] = site_setting_get($pdo, 'draft_home_hero_title', $homeHeroSettings['title']);
    $homeHeroDraftSettings['subtitle'] = site_setting_get($pdo, 'draft_home_hero_subtitle', $homeHeroSettings['subtitle']);
    $homeHeroDraftSettings['cta_label'] = site_setting_get($pdo, 'draft_home_hero_cta_label', $homeHeroSettings['cta_label']);
    $homeHeroDraftSettings['cta_url'] = site_setting_get($pdo, 'draft_home_hero_cta_url', $homeHeroSettings['cta_url']);
    $homeHeroDraftSettings['overlay'] = site_setting_get($pdo, 'draft_home_hero_overlay', $homeHeroSettings['overlay']);
    $homeHeroDraftSettings['tile_1_image'] = site_setting_get($pdo, 'draft_home_promo_tile_1_image', $homeHeroSettings['tile_1_image']);
    $homeHeroDraftSettings['tile_1_title'] = site_setting_get($pdo, 'draft_home_promo_tile_1_title', $homeHeroSettings['tile_1_title']);
    $homeHeroDraftSettings['tile_1_cta_label'] = site_setting_get($pdo, 'draft_home_promo_tile_1_cta_label', $homeHeroSettings['tile_1_cta_label']);
    $homeHeroDraftSettings['tile_1_cta_url'] = site_setting_get($pdo, 'draft_home_promo_tile_1_cta_url', $homeHeroSettings['tile_1_cta_url']);
    $homeHeroDraftSettings['tile_2_image'] = site_setting_get($pdo, 'draft_home_promo_tile_2_image', $homeHeroSettings['tile_2_image']);
    $homeHeroDraftSettings['tile_2_title'] = site_setting_get($pdo, 'draft_home_promo_tile_2_title', $homeHeroSettings['tile_2_title']);
    $homeHeroDraftSettings['tile_2_cta_label'] = site_setting_get($pdo, 'draft_home_promo_tile_2_cta_label', $homeHeroSettings['tile_2_cta_label']);
    $homeHeroDraftSettings['tile_2_cta_url'] = site_setting_get($pdo, 'draft_home_promo_tile_2_cta_url', $homeHeroSettings['tile_2_cta_url']);
    $homeHeroDraftSettings['featured_ids'] = site_setting_get($pdo, 'draft_home_featured_product_ids', $homeHeroSettings['featured_ids']);
}
$homeUsedImageUrls = [];
foreach ([
    $homeHeroSettings['image'] ?? '',
    $homeHeroSettings['tile_1_image'] ?? '',
    $homeHeroSettings['tile_2_image'] ?? '',
    $homeHeroDraftSettings['image'] ?? '',
    $homeHeroDraftSettings['tile_1_image'] ?? '',
    $homeHeroDraftSettings['tile_2_image'] ?? '',
] as $usedUrl) {
    $usedUrl = trim((string) $usedUrl);
    if ($usedUrl !== '') {
        $homeUsedImageUrls[$usedUrl] = true;
    }
}
$homeVisualStatus = [
    'published' => [
        'hero' => trim((string) ($homeHeroSettings['image'] ?? '')) !== '',
        'tile1' => trim((string) ($homeHeroSettings['tile_1_image'] ?? '')) !== '',
        'tile2' => trim((string) ($homeHeroSettings['tile_2_image'] ?? '')) !== '',
    ],
    'draft' => [
        'hero' => trim((string) ($homeHeroDraftSettings['image'] ?? '')) !== '',
        'tile1' => trim((string) ($homeHeroDraftSettings['tile_1_image'] ?? '')) !== '',
        'tile2' => trim((string) ($homeHeroDraftSettings['tile_2_image'] ?? '')) !== '',
    ],
];
$publishedFeaturedIdsArray = array_values(array_filter(array_map('trim', explode(',', (string) ($homeHeroSettings['featured_ids'] ?? ''))), static fn(string $v): bool => $v !== ''));
$notFoundReviewPagination = $authenticated && $activeTab === 'maintenance'
    ? enma_render_pagination('maintenance', 'nf_review_page', (int) ($notFoundReviewPage ?? 1), (int) ($notFoundReviewTotalPages ?? 1))
    : '';
$mediaPagination = $authenticated && $activeTab === 'media'
    ? enma_render_pagination('media', 'media_page', $mediaPage, $mediaTotalPages)
    : '';
$productsCopyText = '';
if ($authenticated && $activeTab === 'products' && $allProducts !== []) {
    $productLines = ['ID' . "\t" . 'ASIN' . "\t" . 'Title' . "\t" . 'Category' . "\t" . 'Affiliate URL'];
    foreach ($allProducts as $item) {
        $productLines[] = implode("\t", [
            enma_export_cell($item['id'] ?? ''),
            enma_export_cell($item['asin'] ?? ''),
            enma_export_cell($item['title'] ?? ''),
            enma_export_cell($item['category_name'] ?? ''),
            enma_export_cell($item['affiliate_url'] ?? ''),
        ]);
    }
    $productsCopyText = implode("\n", $productLines);
}

$postsCopyText = '';
if ($authenticated && $activeTab === 'posts' && $allPosts !== []) {
    $postLines = ['ID' . "\t" . 'Title' . "\t" . 'Slug' . "\t" . 'Type' . "\t" . 'Status' . "\t" . 'Published Date'];
    foreach ($allPosts as $postRow) {
        $postLines[] = implode("\t", [
            enma_export_cell($postRow['id'] ?? ''),
            enma_export_cell($postRow['title'] ?? ''),
            enma_export_cell($postRow['slug'] ?? ''),
            enma_export_cell($postRow['post_type'] ?? ''),
            enma_export_cell($postRow['status'] ?? ''),
            enma_export_cell(substr((string) ($postRow['published_at'] ?? ''), 0, 10)),
        ]);
    }
    $postsCopyText = implode("\n", $postLines);
}

$maintenanceLogCopyText = '';
if ($authenticated && $activeTab === 'maintenance' && $maintenanceLog !== []) {
    $maintenanceLogCopyText = implode("\n", array_map(static fn($line): string => trim((string) $line), $maintenanceLog));
}

$dbSchemaCopyText = '';
if ($authenticated && $activeTab === 'maintenance') {
    $dbSchemaPath = __DIR__ . '/../db_schema.sql';
    if (is_file($dbSchemaPath) && is_readable($dbSchemaPath)) {
        $contents = file_get_contents($dbSchemaPath);
        if (is_string($contents)) {
            $dbSchemaCopyText = $contents;
        }
    }
}

$productsSqlCopyText = '';
$postsJsonCopyText = '';
$sitemapCopyText = '';
$productsBaselineCopyText = '';
$seoPromptTemplate = '';
$blogPostPromptTemplate = '';
$guidePromptTemplate = '';
$productPostPromptTemplate = '';
$productSingleReviewPromptTemplate = '';
$productVersusPromptTemplate = '';
$bestForYPromptTemplate = '';
$updateExistingPostPromptTemplate = '';
$blogPostPromptMissionCopyText = '';
$guidePromptMissionCopyText = '';
$productSingleReviewMissionCopyText = '';
$productVersusMissionCopyText = '';
$bestForYMissionCopyText = '';
$existingPostsBaselineCopyText = '';
$existingPostsWithIndexationCopyText = '';
$promptPlusSitemapCopyText = '';
$catalogPromptTemplate = '';
$catalogPromptMissionCopyText = '';
$llmOperatorPromptCopyText = '';
$postGenerationQaPromptCopyText = '';
$productAcquisitionQaPromptCopyText = '';
$fullRunPackPostsCopyText = '';
$fullRunPackGuidesCopyText = '';
$fullRunPackNewProductsCopyText = '';
$blogCmsReadyPromptCopyText = '';
$legacyBlogPromptWithSitemapCopyText = '';
$productsNewPromptTemplate = '';
if ($authenticated && ($activeTab === 'products' || $activeTab === 'maintenance' || $activeTab === 'prompts')) {
    $baseline = [];
    $baselineAsins = [];
    try {
        $rows = $pdo->query(
            'SELECT asin, title, category_slug
             FROM products
             WHERE status = "published"
             ORDER BY id ASC'
        )->fetchAll();
        foreach ($rows as $row) {
            $asin = strtoupper(trim((string) ($row['asin'] ?? '')));
            if ($asin === '') {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $category = trim((string) ($row['category_slug'] ?? ''));
            $baselineAsins[] = $asin;
            $baseline[] = '- ' . $asin . ' | ' . ($title !== '' ? $title : '(no-title)') . ' | category=' . ($category !== '' ? $category : 'unknown');
        }
    } catch (Throwable $e) {
        $baseline = [];
        $baselineAsins = [];
    }

    $baselineText = $baseline !== [] ? implode("\n", $baseline) : '- No published products in current DB.';
    $asinCsv = $baselineAsins !== [] ? implode(', ', $baselineAsins) : 'none';
    $productsNewPromptTemplate =
        "You are a catalog expansion assistant for fortelescopes.com.\n\n"
        . "Goal:\n"
        . "Propose ONLY NEW products worth adding now (seasonal relevance + buyer intent), excluding everything already in DB baseline.\n\n"
        . "Current date: " . gmdate('F j, Y') . ".\n\n"
        . "Rules:\n"
        . "1) Never return products whose ASIN is already in DB baseline.\n"
        . "2) Prioritize products likely to perform this season (gift cycles, beginner demand, current smart/portable trends).\n"
        . "3) Keep recommendations practical for first-time buyers and affiliate conversion.\n"
        . "4) Verify ASIN + title from accessible sources (Amazon search/pages).\n"
        . "5) Do not invent ASINs or URLs.\n"
        . "6) Return between 6 and 15 NEW items.\n\n"
        . "Output format:\n"
        . "- Return ONLY one PHP code block.\n"
        . "- Use exactly:\n"
        . "\$products = [\n"
        . "  [\n"
        . "    'asin' => 'B000000000',\n"
        . "    'nombre' => 'Exact title',\n"
        . "    'categoria' => 'telescopes',\n"
        . "    'descripcion' => 'Short factual value description (max 180 chars).',\n"
        . "    'imagen' => 'https://...',\n"
        . "    'url' => 'https://www.amazon.com/dp/B000000000?tag=fortelescopes-20',\n"
        . "  ],\n"
        . "];\n\n"
        . "Allowed categories: 'telescopes' or 'accessories'.\n\n"
        . "Amazon source hints:\n"
        . "- https://www.amazon.com/s?k=best+beginner+telescope\n"
        . "- https://www.amazon.com/s?k=smart+telescope\n"
        . "- https://www.amazon.com/s?k=dobsonian+telescope\n"
        . "- https://www.amazon.com/s?k=telescope+accessories\n\n"
        . "DB baseline ASINs (must be excluded):\n"
        . $asinCsv . "\n\n"
        . "DB baseline details:\n"
        . $baselineText . "\n\n"
        . "Return only the PHP code block.\n";
}
if ($authenticated && ($activeTab === 'maintenance' || $activeTab === 'prompts')) {
    $sitemapPath = __DIR__ . '/../sitemap.xml';
    if (is_file($sitemapPath) && is_readable($sitemapPath)) {
        $sitemapContents = file_get_contents($sitemapPath);
        if (is_string($sitemapContents) && trim($sitemapContents) !== '') {
            $sitemapCopyText = $sitemapContents;
        }
    }
    if ($sitemapCopyText === '') {
        $sitemapCopyText = 'sitemap.xml not found or empty. Run "Generate Sitemap" first.';
    }
    try {
        $productBaselineRows = $pdo->query(
            'SELECT id, asin, title, slug, category_slug
             FROM products
             WHERE status = "published"
             ORDER BY id ASC'
        )->fetchAll();
        $productBaselineLines = [];
        foreach ($productBaselineRows as $productRow) {
            $productBaselineLines[] =
                '- id=' . (int) ($productRow['id'] ?? 0)
                . ' | asin=' . strtoupper(trim((string) ($productRow['asin'] ?? '')))
                . ' | title=' . trim((string) ($productRow['title'] ?? ''))
                . ' | slug=' . trim((string) ($productRow['slug'] ?? ''))
                . ' | category=' . trim((string) ($productRow['category_slug'] ?? ''));
        }
        $productsBaselineCopyText = $productBaselineLines !== []
            ? implode("\n", $productBaselineLines)
            : '- No published products in DB baseline.';
    } catch (Throwable $e) {
        $productsBaselineCopyText = '- Unable to load products baseline.';
    }

    $blogPostPromptTemplate = <<<'PROMPT'
ROLE & CONTEXT

Act as a Senior SEO Blog Writer + CRO Specialist for astronomy affiliate content.

Current date: April 30, 2026 UTC.

Write ONE blog post for Fortelescopes designed to rank and convert affiliate clicks with tag fortelescopes-20.

EXECUTION RULES

Output ONLY the HTML that belongs inside <body></body>. Do NOT generate <html>, <head>, or <body> wrappers.
Prefer Amazon search links when ASIN certainty is low.
Do not invent specs or fake availability.
Include relevant media when possible: at least one topic-relevant YouTube embed and practical image suggestions/placements.

MANDATORY:
- Word count: 1400-1900
- At least 2 internal links to fortelescopes URLs
- At least 2 H2 and 3 H3
- Include FAQ section
- Include comparison table (3-5 items)
- Include 3 CTA buttons minimum
- Include at least 1 relevant YouTube embed and at least 2 relevant image recommendations (with alt text ideas).

Start with this exact disclaimer inside a styled <div>:
"As an Amazon Associate, I earn from qualifying purchases."

Output format:
1) Topic rationale (short plain text)
2) Single code block with RAW HTML only
3) Metadata:
   - Title (40-65)
   - Excerpt
   - Meta Title (45-65)
   - Meta Description (120-160)
PROMPT;
    $guidePromptTemplate = <<<'PROMPT'
ROLE:
Act as an astronomy educator + SEO guide writer for Fortelescopes.

OBJECTIVE:
Create one long-form GUIDE (not a generic blog post) that teaches a complete workflow and naturally monetizes with affiliate links.

REQUIREMENTS:
- RAW HTML only (body content)
- 1800-2600 words
- Start with disclaimer: "As an Amazon Associate, I earn from qualifying purchases."
- Structure with clear steps:
  1) Who this guide is for
  2) Tools/gear checklist
  3) Step-by-step setup/use
  4) Common mistakes + fixes
  5) Recommended products by budget
  6) FAQ
  7) Final action plan
- At least 3 internal links to Fortelescopes.
- At least 3 CTA buttons to Amazon (search links if uncertain).
- Include one compact comparison table.
- Include at least 1 relevant YouTube embed and at least 2 relevant image recommendations (with alt text ideas).
- Tone: practical, clear, no fluff.

OUTPUT:
1) Guide angle rationale
2) Single code block with RAW HTML
3) Metadata (Title, Excerpt, Meta Title, Meta Description)
PROMPT;
    $productSingleReviewPromptTemplate = <<<'PROMPT'
ROLE:
You are a conversion-focused affiliate reviewer for telescope buyers.

OBJECTIVE:
Write ONE single-product review post designed to maximize qualified clicks.

MANDATORY STRUCTURE:
- Disclaimer at top: "As an Amazon Associate, I earn from qualifying purchases."
- Decision summary box above the fold:
  - Best for
  - Avoid if
  - Quick verdict
- Specs snapshot table (single product)
- Pros and cons
- Alternatives section (2-3 alternatives with short reasons)
- "Who should buy this?" and "Who should skip this?"
- FAQ (buyer objections)
- Final recommendation + CTA

CONVERSION RULES:
- Minimum 1200 words
- At least 2 internal links
- At least 3 CTA buttons
- Amazon links should include tag=fortelescopes-20
- If ASIN certainty is low, use search URL format:
  https://www.amazon.com/s?k=<query>&tag=fortelescopes-20
- Do not invent specs.
- Include at least 1 relevant YouTube embed and at least 2 relevant image recommendations (with alt text ideas).

OUTPUT:
1) Product post angle rationale
2) Single code block with RAW HTML body only
3) Metadata (Title, Excerpt, Meta Title, Meta Description)
PROMPT;
    $productVersusPromptTemplate = <<<'PROMPT'
ROLE:
You are a conversion-focused affiliate reviewer for telescope buyers.

OBJECTIVE:
Write ONE versus post (A vs B) designed to help buyers decide quickly and click through.

MANDATORY STRUCTURE:
- Disclaimer at top: "As an Amazon Associate, I earn from qualifying purchases."
- Above-the-fold decision box:
  - Winner for beginners
  - Winner for value
  - Winner for portability
  - One-line final verdict
- Side-by-side comparison table:
  - Feature
  - Product A
  - Product B
  - Why it matters
- Round-by-round verdict sections (optics, mount, portability, value)
- Pros/cons for each product
- "Choose A if..." and "Choose B if..."
- FAQ
- Final recommendation + CTA buttons for both options

CONVERSION RULES:
- Minimum 1300 words
- At least 2 internal links
- At least 4 CTA buttons total
- Amazon links must include tag=fortelescopes-20
- If ASIN certainty is low, use search URL format:
  https://www.amazon.com/s?k=<query>&tag=fortelescopes-20
- Do not invent specs.
- Include at least 1 relevant YouTube embed per compared product when possible (max 2 embeds total), and at least 2 relevant image recommendations (with alt text ideas).

OUTPUT:
1) Versus angle rationale
2) Single code block with RAW HTML body only
3) Metadata (Title, Excerpt, Meta Title, Meta Description)
PROMPT;
    $productPostPromptTemplate = $productSingleReviewPromptTemplate;
    $seoPromptTemplate = $blogPostPromptTemplate;
    $promptPlusSitemapCopyText = $blogPostPromptTemplate . "\n\nCURRENT SITEMAP.XML\n\n" . $sitemapCopyText;
    $postsBaselineLines = [];
    try {
        $postRows = $pdo->query(
            'SELECT id, title, slug, post_type, status, published_at, updated_at
             FROM posts
             ORDER BY id DESC'
        )->fetchAll();
        foreach ($postRows as $postRow) {
            $postPath = enma_post_public_path((array) $postRow);
            $postUrl = $postPath !== '' ? absolute_url($postPath) : '';
            $postsBaselineLines[] =
                '- id=' . (int) ($postRow['id'] ?? 0)
                . ' | type=' . trim((string) ($postRow['post_type'] ?? 'post'))
                . ' | status=' . trim((string) ($postRow['status'] ?? 'draft'))
                . ' | title=' . trim((string) ($postRow['title'] ?? ''))
                . ' | slug=' . trim((string) ($postRow['slug'] ?? ''))
                . ' | url=' . ($postUrl !== '' ? $postUrl : 'n/a')
                . ' | published=' . substr((string) ($postRow['published_at'] ?? ''), 0, 10)
                . ' | updated=' . substr((string) ($postRow['updated_at'] ?? ''), 0, 10);
        }
    } catch (Throwable $e) {
        $postsBaselineLines = [];
    }
    $existingPostsBaselineCopyText = $postsBaselineLines !== []
        ? implode("\n", $postsBaselineLines)
        : '- No posts found in DB baseline.';
    $postsWithIndexationLines = [];
    try {
        if (function_exists('enma_indexation_init_table')) {
            enma_indexation_init_table($pdo);
        }
        $postIndexRows = $pdo->query(
            'SELECT
                p.id,
                p.title,
                p.slug,
                p.post_type,
                p.status,
                p.published_at,
                p.updated_at,
                COALESCE(pit.index_state, "pending") AS index_state,
                COALESCE(pit.is_indexed, 0) AS is_indexed,
                COALESCE(pit.last_checked_at, "") AS last_checked_at,
                COALESCE(pit.next_check_at, "") AS next_check_at
             FROM posts p
             LEFT JOIN post_indexation_tracker pit ON pit.post_id = p.id
             ORDER BY p.id DESC'
        )->fetchAll();
        foreach ($postIndexRows as $postRow) {
            $postPath = enma_post_public_path((array) $postRow);
            $postUrl = $postPath !== '' ? absolute_url($postPath) : '';
            $postsWithIndexationLines[] =
                '- id=' . (int) ($postRow['id'] ?? 0)
                . ' | type=' . trim((string) ($postRow['post_type'] ?? 'post'))
                . ' | status=' . trim((string) ($postRow['status'] ?? 'draft'))
                . ' | index_state=' . trim((string) ($postRow['index_state'] ?? 'pending'))
                . ' | is_indexed=' . ((int) ($postRow['is_indexed'] ?? 0) === 1 ? 'yes' : 'no')
                . ' | title=' . trim((string) ($postRow['title'] ?? ''))
                . ' | slug=' . trim((string) ($postRow['slug'] ?? ''))
                . ' | url=' . ($postUrl !== '' ? $postUrl : 'n/a')
                . ' | last_checked=' . trim((string) ($postRow['last_checked_at'] ?? ''))
                . ' | next_check=' . trim((string) ($postRow['next_check_at'] ?? ''))
                . ' | published=' . substr((string) ($postRow['published_at'] ?? ''), 0, 10)
                . ' | updated=' . substr((string) ($postRow['updated_at'] ?? ''), 0, 10);
        }
    } catch (Throwable $e) {
        $postsWithIndexationLines = [];
    }
    $existingPostsWithIndexationCopyText = $postsWithIndexationLines !== []
        ? implode("\n", $postsWithIndexationLines)
        : '- No posts/indexation rows found.';

    $bestForYPromptTemplate = <<<'PROMPT'
ROLE:
You are a commercial SEO writer for Fortelescopes.

OBJECTIVE:
Create one "Best X for Y" post that ranks for buyer intent and maximizes affiliate clicks.

MANDATORY FORMAT:
- RAW HTML only (body content)
- 1500-2200 words
- Start with disclaimer: "As an Amazon Associate, I earn from qualifying purchases."
- Include:
  - Quick picks box (best overall, best budget, best upgrade)
  - Comparison table (3-6 options)
  - Buyer segmentation (for beginner / for city / for portability / for AP)
  - FAQ
  - Final recommendation
- At least 3 Amazon CTA buttons with tag=fortelescopes-20
- At least 2 internal links to Fortelescopes pages
- Use Amazon search links if ASIN certainty is low.
- Include at least 1 relevant YouTube embed and at least 2 relevant image recommendations (with alt text ideas).

RULES:
- Do not invent specs.
- Keep tone practical and conversion-focused.
- Avoid repeating topics already covered in existing posts baseline.

OUTPUT:
1) Topic + intent rationale
2) Single code block with RAW HTML
3) Metadata (Title, Excerpt, Meta Title, Meta Description)
PROMPT;
    $updateExistingPostPromptTemplate =
        "ROLE:\n"
        . "You are a content strategist for Fortelescopes focused on updating existing posts to increase traffic and affiliate clicks.\n\n"
        . "OBJECTIVE:\n"
        . "Using the existing posts baseline below, decide which current posts should be updated now (not new posts).\n\n"
        . "CURRENT DATE: " . gmdate('F j, Y') . "\n\n"
        . "TASK:\n"
        . "1) Identify top 10 posts to update first.\n"
        . "2) For each, output:\n"
        . "   - id\n"
        . "   - title\n"
        . "   - reason to update now\n"
        . "   - update priority (high/medium/low)\n"
        . "   - exact update plan (headline/meta/CTA/table/FAQ/internal links)\n"
        . "3) Also output 5 posts that should NOT be touched now.\n\n"
        . "RULES:\n"
        . "- Prefer commercial-intent URLs.\n"
        . "- Do not propose writing completely new topics in this task.\n"
        . "- If baseline is small, still prioritize what exists.\n"
        . "- Keep output practical for one-person workflow.\n\n"
        . "OUTPUT FORMAT:\n"
        . "- Section A: \"Update Now\" (table)\n"
        . "- Section B: \"Skip for now\" (list)\n"
        . "- Section C: \"This week plan\" (day by day, 7 days)\n\n"
        . "EXISTING POSTS BASELINE:\n"
        . $existingPostsBaselineCopyText . "\n\n"
        . "EXISTING POSTS + INDEXATION BASELINE:\n"
        . $existingPostsWithIndexationCopyText . "\n";
    $missionPreStep =
        "MANDATORY PRE-STEP (STOP IF FAILS)\n"
        . "1) Refresh sitemap from existing script/query.\n"
        . "2) Refresh product list from existing query (ID + ASIN + title + slug).\n"
        . "3) Use only refreshed data from this run as source of truth.\n"
        . "4) If refresh is stale/missing, stop and return an error.\n\n"
        . "DUPLICATE GATE (HARD RULE)\n"
        . "- Never create content or products that collide with existing URL/slug/title/ASIN.\n"
        . "- Treat case, punctuation, spaces, singular/plural, and hyphen variants as duplicates.\n"
        . "- If near-duplicate exists, update/reuse existing entry instead of creating a new one.\n"
        . "- Output a \"Data Refresh\" section and a \"Duplicate Check\" section before final output.\n\n";
    $missionContext =
        "CURRENT SITEMAP.XML\n"
        . $sitemapCopyText . "\n\n"
        . "CURRENT PRODUCTS BASELINE\n"
        . $productsBaselineCopyText . "\n\n"
        . "CURRENT POSTS BASELINE\n"
        . $existingPostsBaselineCopyText . "\n\n";
    $blogPostPromptMissionCopyText = $missionPreStep . $blogPostPromptTemplate . "\n\n" . $missionContext;
    $guidePromptMissionCopyText = $missionPreStep . $guidePromptTemplate . "\n\n" . $missionContext;
    $productSingleReviewMissionCopyText = $missionPreStep . $productSingleReviewPromptTemplate . "\n\n" . $missionContext;
    $productVersusMissionCopyText = $missionPreStep . $productVersusPromptTemplate . "\n\n" . $missionContext;
    $bestForYMissionCopyText = $missionPreStep . $bestForYPromptTemplate . "\n\n" . $missionContext;
    $llmOperatorPromptCopyText =
        "SYSTEM / OPERATOR LAYER FOR CLAUDE OR CHATGPT\n"
        . "You are generating production content for Fortelescopes.\n"
        . "Non-negotiable behavior:\n"
        . "1) Follow the provided task prompt exactly.\n"
        . "2) If required context is missing or stale, stop and ask for refresh.\n"
        . "3) Do not invent specs, ASINs, URLs, pricing, or availability.\n"
        . "4) Prevent duplicates using sitemap + product + posts baseline.\n"
        . "5) If duplicate/near-duplicate found, propose update of existing URL instead of net-new.\n"
        . "6) Keep output deterministic and parseable.\n\n"
        . "Output contract:\n"
        . "- First: Data Refresh section (what was refreshed + timestamp).\n"
        . "- Second: Duplicate Check section (candidate, matched existing item, decision).\n"
        . "- Third: Requested deliverable only.\n"
        . "- No extra commentary outside requested format.\n\n"
        . "Near-duplicate policy:\n"
        . "- Normalize by lowercase + remove punctuation + collapse spaces + singular/plural variants + hyphen/space swaps.\n"
        . "- Consider two candidates duplicate if normalized titles or slugs are materially equivalent.\n\n"
        . "Safety fallback:\n"
        . "- If confidence < 0.85 for factual fields, replace with safe wording or explicit TODO marker.\n";

    $catalogSources = [
        'https://www.amazon.com/s?k=best+beginner+telescope',
        'https://www.amazon.com/s?k=smart+telescope',
        'https://www.amazon.com/s?k=dobsonian+telescope',
        'https://www.amazon.com/s?k=telescope+accessories',
        absolute_url('/'),
        absolute_url('/sitemap.xml'),
        absolute_url('/telescopes'),
        absolute_url('/accessories'),
        absolute_url('/guides'),
        absolute_url('/blog'),
    ];

    $catalogBaselineLines = [];
    try {
        $catalogRows = $pdo->query(
            'SELECT asin, title, category_slug, description, image_url, affiliate_url, slug
             FROM products
             WHERE status = "published"
             ORDER BY id ASC'
        )->fetchAll();
        foreach ($catalogRows as $row) {
            $asin = strtoupper(trim((string) ($row['asin'] ?? '')));
            if ($asin === '') {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $category = trim((string) ($row['category_slug'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $image = trim((string) ($row['image_url'] ?? ''));
            $affiliate = trim((string) ($row['affiliate_url'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));

            $catalogBaselineLines[] =
                '- ' . $asin
                . ' | ' . ($title !== '' ? $title : '(no-title)')
                . ' | category=' . ($category !== '' ? $category : 'unknown')
                . ' | image=' . ($image !== '' ? $image : 'missing')
                . ' | url=' . ($affiliate !== '' ? $affiliate : 'missing')
                . ' | note=' . ($description !== '' ? mb_substr($description, 0, 120) : 'missing');
        }
    } catch (Throwable $e) {
        $catalogBaselineLines = [];
    }

    $catalogSources = array_values(array_unique(array_filter(array_map('trim', $catalogSources), static fn(string $u): bool => $u !== '')));
    $catalogSourceText = implode("\n", array_map(static fn(string $u): string => '- ' . $u, $catalogSources));
    $catalogBaselineText = $catalogBaselineLines !== []
        ? implode("\n", $catalogBaselineLines)
        : '- No published products found in DB baseline.';

    $catalogPromptTemplate =
        "You are a product data extraction assistant.\n\n"
        . "Goal:\n"
        . "Expand and refresh the Fortelescopes catalog using the current DB list as baseline, and return ONLY a PHP array to paste directly into scripts/seed_real_catalog.php.\n\n"
        . "Current date: " . gmdate('F j, Y') . ".\n\n"
        . "Execution rules:\n"
        . "1) Use the source URLs below (already provided). Do NOT ask for more URLs.\n"
        . "2) If fortelescopes.com returns 403/blocked, continue using accessible sources (especially Amazon search URLs).\n"
        . "3) Start from CURRENT DB BASELINE and preserve existing valid products, but you MUST add NEW verified products.\n"
        . "4) Add between 8 and 20 NEW ASINs that are NOT present in baseline (seasonal + buyer intent).\n"
        . "5) Keep existing ASINs unless clearly discontinued/unavailable.\n"
        . "6) Add new products only when ASIN + title are verifiable from accessible sources.\n"
        . "7) Deduplicate strictly by ASIN.\n"
        . "8) Do not invent ASINs, names, URLs, or images.\n"
        . "9) Return a FULL final catalog array (existing + updated + new), not partial deltas.\n"
        . "10) If fewer than 8 new verified items are found, continue broader Amazon search queries until you reach at least 8.\n\n"
        . "Required output format:\n"
        . "- Output ONLY one PHP code block.\n"
        . "- Inside the code block output ONLY:\n"
        . "\$products = [\n"
        . "  [\n"
        . "    'asin' => 'B000000000',\n"
        . "    'nombre' => 'Exact product title',\n"
        . "    'categoria' => 'telescopes',\n"
        . "    'descripcion' => 'Short practical description (max 180 chars).',\n"
        . "    'imagen' => 'https://...',\n"
        . "    'url' => 'https://www.amazon.com/dp/B000000000',\n"
        . "  ],\n"
        . "];\n\n"
        . "Field rules:\n"
        . "- asin: exactly 10 uppercase alphanumeric chars.\n"
        . "- categoria: only 'telescopes' or 'accessories'.\n"
        . "- descripcion: concise, factual, no hype.\n"
        . "- imagen: full https URL.\n"
        . "- url: full https URL.\n\n"
        . "New items requirement:\n"
        . "- At least 8 rows in final output must have ASIN not present in baseline list.\n"
        . "- Prioritize beginner telescopes, smart telescopes, dobsonians, and high-intent accessories.\n\n"
        . "SOURCE URLS (crawl these):\n"
        . $catalogSourceText . "\n\n"
        . "CURRENT DB BASELINE (use this as update reference):\n"
        . $catalogBaselineText . "\n\n"
        . "Return only the PHP code block, no explanations.\n";
    $catalogPromptMissionCopyText =
        "MANDATORY PRE-STEP (PRODUCT ACQUISITION)\n"
        . "1) Refresh sitemap from existing script/query first.\n"
        . "2) Refresh full product baseline from DB first (id, asin, title, slug, category).\n"
        . "3) If either refresh is stale/missing, stop with error.\n\n"
        . "DUPLICATE GATE\n"
        . "- Never return rows that match existing ASIN.\n"
        . "- Also reject near-duplicates by normalized title/slug.\n"
        . "- Reuse/update existing rows instead of proposing duplicates.\n"
        . "- Output \"Data Refresh\" and \"Duplicate Check\" sections before the PHP block.\n\n"
        . $catalogPromptTemplate
        . "\nCURRENT SITEMAP.XML\n"
        . $sitemapCopyText
        . "\n\nCURRENT PRODUCTS BASELINE\n"
        . $productsBaselineCopyText . "\n";
    $postGenerationQaPromptCopyText =
        "POST-GENERATION QA PROMPT (FOR CLAUDE/CHATGPT)\n"
        . "Task: Validate one generated Fortelescopes content draft before publishing.\n\n"
        . "Checks:\n"
        . "1) Structure compliance with requested template.\n"
        . "2) Duplicate collision against sitemap/products/posts baseline.\n"
        . "3) Affiliate compliance: include fortelescopes-20 tag; no misleading claims.\n"
        . "4) Internal links: at least required minimum and valid Fortelescopes paths.\n"
        . "5) Spec integrity: flag any likely invented technical specs.\n"
        . "6) CTA quality: specific, intent-matched, non-spammy.\n"
        . "7) Media quality: YouTube embeds are directly relevant to subject/products and image suggestions are relevant and usable.\n\n"
        . "Output format (strict):\n"
        . "A) PASS/FAIL\n"
        . "B) Blocking issues (numbered)\n"
        . "C) Non-blocking improvements (numbered)\n"
        . "D) Corrected final HTML (single code block only)\n\n"
        . "CONTEXT\n"
        . "CURRENT SITEMAP.XML\n"
        . $sitemapCopyText . "\n\n"
        . "CURRENT PRODUCTS BASELINE\n"
        . $productsBaselineCopyText . "\n\n"
        . "CURRENT POSTS BASELINE\n"
        . $existingPostsBaselineCopyText . "\n";
    $productAcquisitionQaPromptCopyText =
        "PRODUCT ACQUISITION QA PROMPT (FOR CLAUDE/CHATGPT)\n"
        . "Task: Validate a proposed \$products array before DB import.\n\n"
        . "Checks:\n"
        . "1) ASIN format: 10 uppercase alphanumeric chars.\n"
        . "2) Duplicate collisions: ASIN exact + title/slug near-duplicates.\n"
        . "3) Category allowed only: telescopes|accessories.\n"
        . "4) URL and image must be full https URLs.\n"
        . "5) Description quality: factual, concise, no hype.\n"
        . "6) Ensure rows with missing critical fields are rejected.\n\n"
        . "Output format (strict):\n"
        . "A) PASS/FAIL\n"
        . "B) Rejected rows (asin/title/reason)\n"
        . "C) Corrected \$products PHP block only\n\n"
        . "CONTEXT\n"
        . "CURRENT SITEMAP.XML\n"
        . $sitemapCopyText . "\n\n"
        . "CURRENT PRODUCTS BASELINE\n"
        . $productsBaselineCopyText . "\n";
    $fullRunPackPostsCopyText =
        "FULL RUN PACK: POSTS\n\n"
        . "STEP 1) PASTE THIS OPERATOR LAYER AS SYSTEM/INSTRUCTION:\n"
        . $llmOperatorPromptCopyText . "\n\n"
        . "STEP 2) RUN THIS MISSION PROMPT:\n"
        . $blogPostPromptMissionCopyText . "\n\n"
        . "STEP 3) AFTER DRAFT, RUN THIS QA PROMPT (PASTE DRAFT INSIDE IT):\n"
        . $postGenerationQaPromptCopyText . "\n\n"
        . "STEP 4) FINAL OUTPUT EXPECTED:\n"
        . "- PASS status\n"
        . "- Corrected HTML block ready to publish\n";
    $fullRunPackGuidesCopyText =
        "FULL RUN PACK: GUIDES\n\n"
        . "STEP 1) PASTE THIS OPERATOR LAYER AS SYSTEM/INSTRUCTION:\n"
        . $llmOperatorPromptCopyText . "\n\n"
        . "STEP 2) RUN THIS MISSION PROMPT:\n"
        . $guidePromptMissionCopyText . "\n\n"
        . "STEP 3) AFTER DRAFT, RUN THIS QA PROMPT (PASTE DRAFT INSIDE IT):\n"
        . $postGenerationQaPromptCopyText . "\n\n"
        . "STEP 4) FINAL OUTPUT EXPECTED:\n"
        . "- PASS status\n"
        . "- Corrected HTML block ready to publish\n";
    $fullRunPackNewProductsCopyText =
        "FULL RUN PACK: NEW PRODUCTS ACQUISITION\n\n"
        . "STEP 1) PASTE THIS OPERATOR LAYER AS SYSTEM/INSTRUCTION:\n"
        . $llmOperatorPromptCopyText . "\n\n"
        . "STEP 2) RUN THIS ACQUISITION MISSION PROMPT:\n"
        . $catalogPromptMissionCopyText . "\n\n"
        . "STEP 3) AFTER \$products DRAFT, RUN THIS QA PROMPT:\n"
        . $productAcquisitionQaPromptCopyText . "\n\n"
        . "STEP 4) FINAL OUTPUT EXPECTED:\n"
        . "- PASS status\n"
        . "- Corrected \$products PHP array only\n"
        . "- Then paste it in \"Claude Catalog Import\" and click Update Catalog DB\n";
    $blogCmsReadyPromptCopyText =
        "ROLE & CONTEXT\n\n"
        . "Act as a Senior SEO Content Strategist and Conversion Rate Optimization (CRO) Expert for the astronomy niche.\n\n"
        . "Current date: " . gmdate('F j, Y') . ".\n\n"
        . "Your job: Analyze Fortelescopes and create a ready-to-publish affiliate article for the Fortelescopes CMS designed to rank, build trust, and maximize Amazon affiliate clicks using the tag fortelescopes-20.\n\n"
        . "EXECUTION RULES\n\n"
        . "Output ONLY the HTML that belongs inside <body></body>. Do NOT generate <html>, <head>, or <body> wrappers.\n"
        . "If sitemap.xml is inaccessible, fall back to analyzing public site structure: homepage, guides, categories, and visible product pages.\n"
        . "Do NOT guess coverage or invent product specs, ASINs, or availability. If unsure, use clear Amazon search links.\n"
        . "All YouTube embeds must be relevant to the exact product or category discussed.\n"
        . "Add relevant images when possible: suggest at least 2 useful image assets/placements with concise alt text.\n"
        . "Final article must feel human, commercially strong, SEO-aware, and trustworthy.\n\n"
        . "SEO CHECKLIST (MANDATORY)\n\n"
        . "Title length: 40-65 chars\n"
        . "Meta title: 45-65 chars\n"
        . "Meta description: 120-160 chars\n"
        . "At least 2 H2 headings\n"
        . "At least 600 words\n"
        . "At least 2 internal links to relevant Fortelescopes content\n\n"
        . "STEP 1: SITE ANALYSIS\n\n"
        . "Analyze:\n"
        . "https://fortelescopes.com/sitemap.xml\n"
        . "If unavailable: homepage, guides, categories, and public product pages.\n\n"
        . "Deliver:\n"
        . "Main existing content clusters and categories.\n"
        . "One high-intent commercial content gap not already well covered.\n"
        . "Choose ONE topic most likely to convert affiliate clicks.\n\n"
        . "Briefly explain:\n"
        . "The chosen topic\n"
        . "Why it fills a content gap\n"
        . "Why it has buyer intent\n"
        . "Why it fits Fortelescopes\n\n"
        . "STEP 2: WRITE THE ARTICLE (RAW HTML ONLY)\n\n"
        . "Write a complete, high-converting article of at least 1,500 words.\n"
        . "Output: RAW HTML ONLY for the article body content.\n\n"
        . "Critical Requirements:\n\n"
        . "Affiliate Disclaimer\n"
        . "Start with a styled <div> that says exactly:\n"
        . "\"As an Amazon Associate, I earn from qualifying purchases.\"\n\n"
        . "Structure\n"
        . "Use <h2> and <h3> headings\n"
        . "Short <p> paragraphs for mobile readability\n"
        . "Use <ul>, <ol>, <table>, and <strong> where helpful\n"
        . "Simple explanations for technical terms\n"
        . "Conversion-focused but trustworthy tone\n\n"
        . "Internal Links (Minimum 2)\n"
        . "Include at least 2 contextual internal links to relevant Fortelescopes content using descriptive anchor text.\n\n"
        . "Product Coverage\n"
        . "For every product recommendation:\n"
        . "Explain what it is, who it's for, why it stands out, and realistic limitations\n"
        . "Include a short \"Why We Love It\" subsection\n"
        . "Include a Pros and Cons list using <ul>\n"
        . "Include a relevant embedded YouTube video for that product\n"
        . "Include a yellow Amazon CTA button immediately after the pros/cons\n\n"
        . "Amazon Yellow CTA Buttons (SEARCH LINKS ONLY)\n"
        . "Do NOT use /dp/ASIN links unless 100% certain of a current, in-stock ASIN.\n"
        . "DEFAULT TO AMAZON SEARCH LINKS.\n\n"
        . "Amazon SEARCH link format (MANDATORY):\n"
        . "https://www.amazon.com/s?k=[url-encoded-search-terms]&tag=fortelescopes-20\n\n"
        . "Comparison Table\n"
        . "Include a responsive HTML <table> comparing 3-5 top picks with columns: Model, Aperture, Best For, Check Price.\n\n"
        . "Include a helpful FAQ section and a strong conclusion with one final large yellow button.\n\n"
        . "STEP 3: FINAL OUTPUT FORMAT\n\n"
        . "Output in this exact order:\n"
        . "1) Topic Rationale (plain text)\n"
        . "2) A single code block with complete raw HTML article body only\n"
        . "3) Metadata: Title, Excerpt, Meta Title, Meta Description\n"
        . "4) Final line exactly:\n"
        . "ask me to redo this same html with proper relevant youtube videos and to make sure amazon links ensure some sort of comission\n\n"
        . "CURRENT SITEMAP.XML\n\n"
        . $sitemapCopyText;
    $legacyBlogPromptWithSitemapCopyText =
        "ROLE & CONTEXT\n\n"
        . "Act as a Senior SEO Content Strategist and Conversion Rate Optimization (CRO) Expert for the astronomy niche.\n\n"
        . "Current date: April 11, 2026.\n\n"
        . "Your job: Analyze Fortelescopes and create a ready-to-publish affiliate article for the Fortelescopes CMS designed to rank, build trust, and maximize Amazon affiliate clicks using the tag fortelescopes-20.\n\n"
        . "EXECUTION RULES\n\n"
        . "Output ONLY the HTML that belongs inside <body></body>. Do NOT generate <html>, <head>, or <body> wrappers.\n"
        . "If sitemap.xml is inaccessible, fall back to analyzing public site structure: homepage, guides, categories, and visible product pages.\n"
        . "Do NOT guess coverage or invent product specs, ASINs, or availability. If unsure, use clear Amazon search links.\n"
        . "All YouTube embeds must be relevant to the exact product or category discussed.\n"
        . "Final article must feel human, commercially strong, SEO-aware, and trustworthy.\n\n"
        . "SEO CHECKLIST (MANDATORY)\n\n"
        . "Title length: 40-65 chars\n"
        . "Meta title: 45-65 chars\n"
        . "Meta description: 120-160 chars\n"
        . "At least 2 H2 headings\n"
        . "At least 600 words\n"
        . "At least 2 internal links to relevant Fortelescopes content\n\n"
        . "STEP 1: SITE ANALYSIS\n\n"
        . "Analyze:\n"
        . "https://fortelescopes.com/sitemap.xml\n"
        . "If unavailable: homepage, guides, categories, and public product pages.\n\n"
        . "Deliver:\n"
        . "Main existing content clusters and categories.\n"
        . "One high-intent commercial content gap not already well covered.\n"
        . "Choose ONE topic most likely to convert affiliate clicks.\n\n"
        . "Briefly explain:\n"
        . "The chosen topic\n"
        . "Why it fills a content gap\n"
        . "Why it has buyer intent\n"
        . "Why it fits Fortelescopes\n\n"
        . "STEP 2: WRITE THE ARTICLE (RAW HTML ONLY)\n\n"
        . "Write a complete, high-converting article of at least 1,500 words.\n"
        . "Output: RAW HTML ONLY for the article body content.\n\n"
        . "Critical Requirements:\n\n"
        . "Affiliate Disclaimer\n"
        . "Start with a styled <div> that says exactly:\n"
        . "\"As an Amazon Associate, I earn from qualifying purchases.\"\n\n"
        . "Structure\n"
        . "Use <h2> and <h3> headings\n"
        . "Short <p> paragraphs for mobile readability\n"
        . "Use <ul>, <ol>, <table>, and <strong> where helpful\n"
        . "Simple explanations for technical terms\n"
        . "Conversion-focused but trustworthy tone\n\n"
        . "Internal Links (Minimum 2)\n"
        . "Include at least 2 contextual internal links to relevant Fortelescopes content using descriptive anchor text.\n\n"
        . "Product Coverage\n"
        . "For every product recommendation:\n"
        . "Explain what it is, who it's for, why it stands out, and realistic limitations\n"
        . "Include a short \"Why We Love It\" subsection\n"
        . "Include a Pros and Cons list using <ul>\n"
        . "Include a relevant embedded YouTube video for that product\n"
        . "Include a yellow Amazon CTA button immediately after the pros/cons\n\n"
        . "YouTube Embeds\n"
        . "Include 1 relevant YouTube embed per product (2 max for high-priority sections)\n"
        . "Use proper responsive embed HTML\n"
        . "Only embed videos that exist and are directly relevant to the exact telescope/product type discussed\n"
        . "Verify video relevance before embedding; if uncertain, omit rather than guess\n\n"
        . "Amazon Yellow CTA Buttons (SEARCH LINKS ONLY)\n"
        . "Use button-style affiliate links with inline CSS.\n"
        . "Do NOT use /dp/ASIN links unless 100% certain of a current, in-stock ASIN.\n"
        . "DEFAULT TO AMAZON SEARCH LINKS.\n\n"
        . "Amazon SEARCH link format (MANDATORY):\n"
        . "https://www.amazon.com/s?k=[url-encoded-search-terms]&tag=fortelescopes-20\n\n"
        . "Comparison Table\n"
        . "Include a responsive HTML <table> comparing 3-5 top picks.\n\n"
        . "STEP 3: FINAL OUTPUT FORMAT\n\n"
        . "Output in this exact order:\n\n"
        . "Topic Rationale\n"
        . "Plain text only. Short but clear.\n\n"
        . "A single code block\n"
        . "Inside it, provide the complete raw HTML article body only (clean, minified, no wrapper tags).\n\n"
        . "Metadata\n"
        . "Then provide:\n"
        . "Title (40-65 chars)\n"
        . "Excerpt (Short summary)\n"
        . "Meta Title (45-65 chars)\n"
        . "Meta Description (120-160 chars)\n\n"
        . "Final line\n"
        . "After everything is done, write exactly:\n"
        . "ask me to redo this same html with proper relevant youtube videos and to make sure amazon links ensure some sort of comission\n\n"
        . "CURRENT SITEMAP.XML\n"
        . "------------------------------------------------\n"
        . $sitemapCopyText;

    try {
        if (function_exists('enma_maintenance_build_products_export_sql')) {
            $productsSqlExport = enma_maintenance_build_products_export_sql($pdo);
            $productsSqlCopyText = (string) ($productsSqlExport['content'] ?? '');
        }
    } catch (Throwable $e) {
        $productsSqlCopyText = '';
    }

    try {
        if (function_exists('enma_maintenance_build_posts_export_json')) {
            $postsJsonExport = enma_maintenance_build_posts_export_json($pdo);
            $postsJsonCopyText = (string) ($postsJsonExport['content'] ?? '');
        }
    } catch (Throwable $e) {
        $postsJsonCopyText = '';
    }
}

$viewsSectionPerPage = 10;
$viewsTopPagesPage = $authenticated ? enma_page_value('views_top_pages_page') : 1;
$viewsTopProductsPage = $authenticated ? enma_page_value('views_top_products_page') : 1;
$viewsTopClickedPage = $authenticated ? enma_page_value('views_top_clicked_page') : 1;
$viewsReferrersPage = $authenticated ? enma_page_value('views_referrers_page') : 1;

$viewsTopPagesAll = $viewsDashboard['top_pages'] ?? [];
$viewsTopProductsAll = $viewsDashboard['top_products'] ?? [];
$viewsTopClickedAll = $viewsDashboard['clicks']['top_products'] ?? [];
$viewsReferrersAll = $viewsDashboard['top_referrers'] ?? [];
$viewsCompare = $viewsDashboard['compare'] ?? [];
$viewsCompareDelta = $viewsCompare['delta'] ?? ['views' => 0, 'clicks' => 0, 'ctr_percent' => 0];
$viewsTopWinners = $viewsCompare['top_winners'] ?? [];
$viewsTopLosers = $viewsCompare['top_losers'] ?? [];
$viewsFunnel = $viewsDashboard['funnel'] ?? [
    'discovery_views' => 0,
    'product_views' => 0,
    'outbound_clicks' => 0,
    'discovery_to_product_percent' => 0.0,
    'product_to_click_percent' => 0.0,
];

$viewsTopPagesTotalPages = enma_total_pages(count($viewsTopPagesAll), $viewsSectionPerPage);
$viewsTopProductsTotalPages = enma_total_pages(count($viewsTopProductsAll), $viewsSectionPerPage);
$viewsTopClickedTotalPages = enma_total_pages(count($viewsTopClickedAll), $viewsSectionPerPage);
$viewsReferrersTotalPages = enma_total_pages(count($viewsReferrersAll), $viewsSectionPerPage);

$viewsTopPagesPage = min($viewsTopPagesPage, $viewsTopPagesTotalPages);
$viewsTopProductsPage = min($viewsTopProductsPage, $viewsTopProductsTotalPages);
$viewsTopClickedPage = min($viewsTopClickedPage, $viewsTopClickedTotalPages);
$viewsReferrersPage = min($viewsReferrersPage, $viewsReferrersTotalPages);

$viewsTopPagesRows = array_slice($viewsTopPagesAll, ($viewsTopPagesPage - 1) * $viewsSectionPerPage, $viewsSectionPerPage);
$viewsTopProductsRows = array_slice($viewsTopProductsAll, ($viewsTopProductsPage - 1) * $viewsSectionPerPage, $viewsSectionPerPage);
$viewsTopClickedRows = array_slice($viewsTopClickedAll, ($viewsTopClickedPage - 1) * $viewsSectionPerPage, $viewsSectionPerPage);
$viewsReferrersRows = array_slice($viewsReferrersAll, ($viewsReferrersPage - 1) * $viewsSectionPerPage, $viewsSectionPerPage);

$viewsBaseExtra = ['days' => $viewDays];
$viewsTopPagesPagination = $authenticated && $activeTab === 'views'
    ? enma_render_pagination('views', 'views_top_pages_page', $viewsTopPagesPage, $viewsTopPagesTotalPages, $viewsBaseExtra)
    : '';
$viewsTopProductsPagination = $authenticated && $activeTab === 'views'
    ? enma_render_pagination('views', 'views_top_products_page', $viewsTopProductsPage, $viewsTopProductsTotalPages, $viewsBaseExtra)
    : '';
$viewsTopClickedPagination = $authenticated && $activeTab === 'views'
    ? enma_render_pagination('views', 'views_top_clicked_page', $viewsTopClickedPage, $viewsTopClickedTotalPages, $viewsBaseExtra)
    : '';
$viewsReferrersPagination = $authenticated && $activeTab === 'views'
    ? enma_render_pagination('views', 'views_top_referrers_page', $viewsReferrersPage, $viewsReferrersTotalPages, $viewsBaseExtra)
    : '';

$analyticsSectionPerPage = 10;
$analyticsAgentsPage = $authenticated ? enma_page_value('analytics_agents_page') : 1;
$analyticsLogsPage = $authenticated ? enma_page_value('analytics_logs_page') : 1;

$analyticsAgentsAll = $analyticsDashboard['top_agents'] ?? [];
$analyticsLogsAll = $analyticsDashboard['recent_logs'] ?? [];

$analyticsAgentsTotalPages = enma_total_pages(count($analyticsAgentsAll), $analyticsSectionPerPage);
$analyticsLogsTotalPages = enma_total_pages(count($analyticsLogsAll), $analyticsSectionPerPage);
$analyticsAgentsPage = min($analyticsAgentsPage, $analyticsAgentsTotalPages);
$analyticsLogsPage = min($analyticsLogsPage, $analyticsLogsTotalPages);

$analyticsAgentsRows = array_slice($analyticsAgentsAll, ($analyticsAgentsPage - 1) * $analyticsSectionPerPage, $analyticsSectionPerPage);
$analyticsLogsRows = array_slice($analyticsLogsAll, ($analyticsLogsPage - 1) * $analyticsSectionPerPage, $analyticsSectionPerPage);

$analyticsAgentsPagination = $authenticated && $activeTab === 'analytics'
    ? enma_render_pagination('analytics', 'analytics_agents_page', $analyticsAgentsPage, $analyticsAgentsTotalPages)
    : '';
$analyticsLogsPagination = $authenticated && $activeTab === 'analytics'
    ? enma_render_pagination('analytics', 'analytics_logs_page', $analyticsLogsPage, $analyticsLogsTotalPages)
    : '';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | <?= e(APP_NAME) ?></title>
    <meta name="robots" content="noindex,nofollow">
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
      $(document).ready(function() {
        var $addContent = $('#add_post_content_html');
        if ($addContent.length) {
          $addContent.summernote({
            placeholder: 'Write your content here...',
            tabsize: 2,
            height: 400,
            toolbar: [
              ['style', ['style']],
              ['font', ['bold', 'underline', 'clear']],
              ['color', ['color']],
              ['para', ['ul', 'ol', 'paragraph']],
              ['table', ['table']],
              ['insert', ['link', 'picture', 'video']],
              ['view', ['fullscreen', 'codeview', 'help']]
            ]
          });
        }

        var $editContent = $('#edit_post_content_html');
        if ($editContent.length) {
          if ($editContent.next('.note-editor').length && typeof $editContent.summernote === 'function') {
            $editContent.summernote('destroy');
          }
          $editContent.show();
        }

        function stripPreviewHtml(html) {
          var $tmp = $('<div>').html(html || '');
          $tmp.find('script, style').remove();
          return ($tmp.text() || '').replace(/\s+/g, ' ').trim();
        }

        function extractLinks(html) {
          var urls = [];
          var regex = /<a[^>]+href=["']([^"']+)["']/gi;
          var match;
          while ((match = regex.exec(html || '')) !== null) {
            urls.push((match[1] || '').trim());
          }
          return urls;
        }

        function slugifyPreview(value) {
          return (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .replace(/-{2,}/g, '-');
        }

        function getContentHtml($form) {
          var $content = $form.find('textarea[name="content_html"]');
          if ($content.length === 0) {
            return '';
          }
          if (typeof $content.summernote === 'function' && $content.next('.note-editor').length) {
            return $content.summernote('code') || '';
          }
          return $content.val() || '';
        }
        function buildHorizontalImageBrief($form) {
          var title = ($form.find('input[name="title"]').val() || '').trim();
          var postType = ($form.find('select[name="post_type"]').val() || 'post').trim();
          var excerpt = ($form.find('textarea[name="excerpt"]').val() || '').trim();
          var metaTitle = ($form.find('input[name="meta_title"]').val() || '').trim();
          var metaDescription = ($form.find('textarea[name="meta_description"]').val() || '').trim();
          var html = getContentHtml($form);
          var plainBody = stripPreviewHtml(html).replace(/\s+/g, ' ').trim();
          var bodySnippet = plainBody.substring(0, 800);
          var brand = 'Fortelescopes';
          var formatHint = 'Horizontal hero image (16:9, web, clean editorial style).';
          var topicLine = title !== '' ? title : 'Untitled post draft';
          var summary = excerpt || metaDescription || bodySnippet || 'No summary provided yet.';
          var seoLine = metaTitle || title || '';
          return [
            'Create a horizontal hero image based on this post.',
            'Brand: ' + brand,
            'Post type: ' + postType,
            'Format: ' + formatHint,
            'Topic: ' + topicLine,
            'Summary: ' + summary,
            seoLine !== '' ? 'SEO angle: ' + seoLine : '',
            bodySnippet !== '' ? 'Body context: ' + bodySnippet : '',
            'Constraints:',
            '- No text overlays unless essential and minimal.',
            '- Realistic astronomy context; avoid fake telescope hardware details.',
            '- Professional, high-contrast composition that works as blog hero.',
            '- Safe for commercial affiliate content (no logos/trademarks).'
          ].filter(function (line) { return line !== ''; }).join('\n');
        }

        function updatePostPreview($form) {
          if (!$form || $form.length === 0) {
            return;
          }

          var title = ($form.find('input[name="title"]').val() || '').trim();
          var excerpt = ($form.find('textarea[name="excerpt"]').val() || '').trim();
          var metaTitle = ($form.find('input[name="meta_title"]').val() || '').trim();
          var metaDescription = ($form.find('textarea[name="meta_description"]').val() || '').trim();
          var imageUrl = ($form.find('input[name="featured_image"]').val() || '').trim();
          var postType = ($form.find('select[name="post_type"]').val() || 'post').trim();
          var html = getContentHtml($form);
          var plainBody = stripPreviewHtml(html);
          var serpTitle = metaTitle || title || 'Post title preview';
          var serpDescription = metaDescription || excerpt || plainBody || 'Meta description preview';
          var cardTitle = title || 'Post title preview';
          var cardExcerpt = excerpt || metaDescription || plainBody || 'Post excerpt preview';
          var previewSlug = slugifyPreview(title) || 'preview-post';
          var previewPath = postType === 'guide' ? '/' + previewSlug : '/blog/' + previewSlug;

          $form.find('[data-preview="serp-url"]').text(window.location.origin + <?= json_encode(url('/')) ?>.replace(/\/$/, '') + previewPath);
          $form.find('[data-preview="serp-title"]').text(serpTitle);
          $form.find('[data-preview="serp-description"]').text(serpDescription.substring(0, 170));
          $form.find('[data-preview="hero-title"]').text(cardTitle);
          $form.find('[data-preview="hero-copy"]').text(cardExcerpt.substring(0, 180));
          $form.find('[data-preview="article-title"]').text(cardTitle);
          $form.find('[data-preview="article-copy"]').text(cardExcerpt.substring(0, 220));
          $form.find('[data-preview="article-body"]').text((plainBody || 'Article body preview').substring(0, 520));

          var $img = $form.find('[data-preview="hero-image"]');
          if (imageUrl !== '') {
            $img.attr('src', imageUrl).show();
          } else {
            $img.hide().attr('src', '');
          }
        }

        function updateSeoAssistant($form) {
          var title = ($form.find('input[name="title"]').val() || '').trim();
          var metaTitle = ($form.find('input[name="meta_title"]').val() || '').trim();
          var excerpt = ($form.find('textarea[name="excerpt"]').val() || '').trim();
          var metaDescription = ($form.find('textarea[name="meta_description"]').val() || '').trim();
          var imageUrl = ($form.find('input[name="featured_image"]').val() || '').trim();
          var html = getContentHtml($form);
          var body = stripPreviewHtml(html);
          var words = body === '' ? 0 : body.split(/\s+/).length;
          var h2Count = (html.match(/<h2\b/gi) || []).length;
          var linkList = extractLinks(html);
          var internalLinks = linkList.filter(function(link) {
            return link.startsWith('/') || link.indexOf(window.location.origin) === 0;
          }).length;
          var titleLen = title.length;
          var metaTitleLen = metaTitle.length;
          var metaDescLen = (metaDescription || excerpt).length;

          var checks = [
            { key: 'title', ok: titleLen >= 40 && titleLen <= 65, message: 'Title length 40-65 chars' },
            { key: 'meta-title', ok: metaTitleLen === 0 || (metaTitleLen >= 45 && metaTitleLen <= 65), message: 'Meta title 45-65 chars (optional but recommended)' },
            { key: 'meta-desc', ok: metaDescLen >= 120 && metaDescLen <= 160, message: 'Meta description 120-160 chars' },
            { key: 'h2', ok: h2Count >= 2, message: 'At least 2 H2 headings' },
            { key: 'words', ok: words >= 600, message: 'At least 600 words' },
            { key: 'links', ok: internalLinks >= 2, message: 'At least 2 internal links' },
            { key: 'image', ok: imageUrl !== '', message: 'Featured image defined' }
          ];

          var passed = checks.filter(function(item) { return item.ok; }).length;
          var score = Math.round((passed / checks.length) * 100);

          $form.find('[data-seo="score"]').text(score + '/100');
          $form.find('[data-seo="title-len"]').text(titleLen.toString());
          $form.find('[data-seo="meta-title-len"]').text(metaTitleLen.toString());
          $form.find('[data-seo="meta-desc-len"]').text(metaDescLen.toString());
          $form.find('[data-seo="h2-count"]').text(h2Count.toString());
          $form.find('[data-seo="word-count"]').text(words.toString());
          $form.find('[data-seo="internal-links"]').text(internalLinks.toString());

          checks.forEach(function(check) {
            var $node = $form.find('[data-seo-check="' + check.key + '"]');
            $node.removeClass('seo-ok seo-warn').addClass(check.ok ? 'seo-ok' : 'seo-warn');
            $node.find('[data-seo-check-status]').text(check.ok ? 'OK' : 'Needs work');
          });
        }

        function insertContentBlock($form, blockType) {
          var templates = {
            review_intro: '<p><strong>Quick verdict:</strong> This option is best for beginners who want reliable results without overpaying.</p>',
            pros_cons: '<h2>Pros and Cons</h2><h3>Pros</h3><ul><li>Easy to set up and use</li><li>Good value for the price</li></ul><h3>Cons</h3><ul><li>Limited advanced features</li></ul>',
            faq: '<h2>Frequently Asked Questions</h2><h3>Is this good for beginners?</h3><p>Yes, it offers a friendly learning curve and enough performance for early stages.</p>',
            cta: '<h2>Final Recommendation</h2><p>If this matches your budget and use case, check today\'s price and availability before stock changes.</p><p><a href="/telescopes">Compare more telescope options</a>.</p>'
          };
          var snippet = templates[blockType] || '';
          if (snippet === '') {
            return;
          }

          var $content = $form.find('textarea[name="content_html"]');
          if ($content.length === 0) {
            return;
          }
          if (typeof $content.summernote === 'function' && $content.next('.note-editor').length) {
            $content.summernote('pasteHTML', '\n' + snippet + '\n');
          } else {
            var previous = $content.val() || '';
            $content.val(previous + '\n' + snippet + '\n');
          }
          updatePostPreview($form);
          updateSeoAssistant($form);
        }

        function updateAutosaveStatus($form, message, isError) {
          var $status = $form.find('[data-autosave-status]');
          if ($status.length === 0) {
            return;
          }
          $status.text(message).removeClass('seo-warn seo-ok').addClass(isError ? 'seo-warn' : 'seo-ok');
        }

        function setupAutosave($form) {
          var enabled = ($form.attr('data-autosave-enabled') || '0') === '1';
          if (!enabled) {
            updateAutosaveStatus($form, 'Autosave DB schema not enabled yet', true);
            return;
          }

          var lastFingerprint = '';
          var saveInFlight = false;
          var timerMs = 45000;
          updateAutosaveStatus($form, 'Autosave active (every 45s)', false);

          function saveNow() {
            if (saveInFlight) {
              return;
            }

            var payload = {
              action: 'save_post_autosave',
              csrf_token: $form.find('input[name="csrf_token"]').val() || '',
              post_id: $form.find('input[name="post_id"]').val() || $form.find('input[name="id"]').val() || '0',
              draft_key: $form.find('input[name="draft_key"]').val() || '',
              title: $form.find('input[name="title"]').val() || '',
              excerpt: $form.find('textarea[name="excerpt"]').val() || '',
              meta_title: $form.find('input[name="meta_title"]').val() || '',
              meta_description: $form.find('textarea[name="meta_description"]').val() || '',
              content_html: getContentHtml($form)
            };

            var fingerprint = JSON.stringify(payload);
            if (fingerprint === lastFingerprint) {
              return;
            }

            saveInFlight = true;
            var formData = new URLSearchParams(payload);
            fetch(window.location.pathname + window.location.search, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
              body: formData.toString()
            }).then(function(response) {
              return response.json();
            }).then(function(json) {
              if (json && json.ok) {
                lastFingerprint = fingerprint;
                if (json.draft_key) {
                  $form.find('input[name="draft_key"]').val(json.draft_key);
                }
                updateAutosaveStatus($form, 'Saved at ' + (json.saved_at || 'now'), false);
              } else {
                updateAutosaveStatus($form, (json && json.message) ? json.message : 'Autosave failed', true);
              }
            }).catch(function() {
              updateAutosaveStatus($form, 'Autosave failed (network/server)', true);
            }).finally(function() {
              saveInFlight = false;
            });
          }

          setInterval(saveNow, timerMs);
          $form.on('input change', 'input, textarea, select', function() {
            if (lastFingerprint !== '') {
              updateAutosaveStatus($form, 'Unsaved changes...', true);
            }
          });
        }

        function updateCopyStatus(statusId, message, isError) {
          if (!statusId) {
            return;
          }
          var $status = $('#' + statusId);
          if ($status.length === 0) {
            return;
          }
          $status.text(message).css('color', isError ? '#9a2f15' : '#1a6f35');
        }

        function fallbackCopyText(text) {
          var temp = document.createElement('textarea');
          temp.value = text;
          temp.setAttribute('readonly', '');
          temp.style.position = 'absolute';
          temp.style.left = '-9999px';
          document.body.appendChild(temp);
          temp.select();
          var ok = false;
          try {
            ok = document.execCommand('copy');
          } catch (err) {
            ok = false;
          }
          document.body.removeChild(temp);
          return ok;
        }

        $('[data-copy-text]').on('click', function () {
          var $btn = $(this);
          if ($btn.attr('data-copy-open-url')) {
            return;
          }
          var text = ($btn.attr('data-copy-text') || '').toString();
          var statusId = ($btn.attr('data-copy-status') || '').trim();
          if (text.trim() === '') {
            updateCopyStatus(statusId, 'Nothing to copy', true);
            return;
          }
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
              updateCopyStatus(statusId, 'Copied', false);
            }).catch(function () {
              var copied = fallbackCopyText(text);
              updateCopyStatus(statusId, copied ? 'Copied' : 'Copy failed', !copied);
            });
            return;
          }
          var copied = fallbackCopyText(text);
          updateCopyStatus(statusId, copied ? 'Copied' : 'Copy failed', !copied);
        });

        $('[data-copy-open-url]').on('click', function () {
          var $btn = $(this);
          var text = ($btn.attr('data-copy-text') || '').toString();
          var statusId = ($btn.attr('data-copy-status') || '').trim();
          var openUrl = ($btn.attr('data-copy-open-url') || '').trim();

          if (openUrl !== '') {
            window.open(openUrl, '_blank', 'noopener');
          }

          if (text.trim() === '') {
            updateCopyStatus(statusId, 'Opened target. Nothing copied.', true);
            return;
          }

          function notifyCopied(ok) {
            if (ok) {
              updateCopyStatus(statusId, 'Opened + copied URL', false);
            } else {
              updateCopyStatus(statusId, 'Opened target. Copy failed.', true);
            }
          }

          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
              notifyCopied(true);
            }).catch(function () {
              notifyCopied(fallbackCopyText(text));
            });
            return;
          }

          notifyCopied(fallbackCopyText(text));
        });

        $('[data-copy-target]').on('click', function () {
          var $btn = $(this);
          var sourceId = ($btn.attr('data-copy-target') || '').trim();
          var statusId = ($btn.attr('data-copy-status') || '').trim();
          if (sourceId === '') {
            return;
          }

          var source = document.getElementById(sourceId);
          if (!source) {
            updateCopyStatus(statusId, 'Source not found', true);
            return;
          }

          var text = typeof source.value === 'string' ? source.value : (source.textContent || '');
          if (text.trim() === '') {
            updateCopyStatus(statusId, 'Nothing to copy', true);
            return;
          }

          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
              updateCopyStatus(statusId, 'Copied', false);
            }).catch(function () {
              var copied = fallbackCopyText(text);
              updateCopyStatus(statusId, copied ? 'Copied' : 'Copy failed', !copied);
            });
            return;
          }

          var copied = fallbackCopyText(text);
          updateCopyStatus(statusId, copied ? 'Copied' : 'Copy failed', !copied);
        });

        $('[data-media-assign]').on('click', function () {
          var $btn = $(this);
          var assign = ($btn.attr('data-media-assign') || '').toString();
          var mediaUrl = ($btn.attr('data-media-url') || '').toString();
          var mediaTitle = ($btn.attr('data-media-title') || '').toString();
          if (mediaUrl.trim() === '') {
            return;
          }

          function fillIfEmpty(id, value) {
            var el = document.getElementById(id);
            if (!el) return;
            if ((el.value || '').toString().trim() === '') {
              el.value = value;
            }
          }

          if (assign === 'hero') {
            var heroImage = document.getElementById('home_hero_image');
            if (heroImage) {
              heroImage.value = mediaUrl;
            }
            fillIfEmpty('home_hero_cta_label', 'Explore Telescopes');
            fillIfEmpty('home_hero_cta_url', '/best-beginner-telescopes');
          } else if (assign === 'tile1') {
            var tile1Image = document.getElementById('home_promo_tile_1_image');
            if (tile1Image) {
              tile1Image.value = mediaUrl;
            }
            fillIfEmpty('home_promo_tile_1_title', mediaTitle || 'Start Stargazing Now');
            fillIfEmpty('home_promo_tile_1_cta_label', 'Beginner Telescopes');
            fillIfEmpty('home_promo_tile_1_cta_url', '/best-beginner-telescopes');
          } else if (assign === 'tile2') {
            var tile2Image = document.getElementById('home_promo_tile_2_image');
            if (tile2Image) {
              tile2Image.value = mediaUrl;
            }
            fillIfEmpty('home_promo_tile_2_title', mediaTitle || 'Create Your Masterpiece');
            fillIfEmpty('home_promo_tile_2_cta_label', 'Explore Astrophotography');
            fillIfEmpty('home_promo_tile_2_cta_url', '/guides');
          }

          var heroSection = document.getElementById('home-hero-settings');
          if (heroSection && typeof heroSection.scrollIntoView === 'function') {
            heroSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        });

        var publishedSettings = <?= json_encode($homeHeroSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        var draftSettings = <?= json_encode($homeHeroDraftSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        function setInputValue(id, value) {
          var el = document.getElementById(id);
          if (!el) return;
          el.value = (value || '').toString();
          el.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function loadSettings(source) {
          setInputValue('home_hero_image', source.image || '');
          setInputValue('home_hero_image_2x', source.image_2x || '');
          setInputValue('home_hero_alt', source.alt || '');
          setInputValue('home_hero_title', source.title || '');
          setInputValue('home_hero_subtitle', source.subtitle || '');
          setInputValue('home_hero_cta_label', source.cta_label || '');
          setInputValue('home_hero_cta_url', source.cta_url || '');
          setInputValue('home_promo_tile_1_title', source.tile_1_title || '');
          setInputValue('home_promo_tile_1_image', source.tile_1_image || '');
          setInputValue('home_promo_tile_1_cta_label', source.tile_1_cta_label || '');
          setInputValue('home_promo_tile_1_cta_url', source.tile_1_cta_url || '');
          setInputValue('home_promo_tile_2_title', source.tile_2_title || '');
          setInputValue('home_promo_tile_2_image', source.tile_2_image || '');
          setInputValue('home_promo_tile_2_cta_label', source.tile_2_cta_label || '');
          setInputValue('home_promo_tile_2_cta_url', source.tile_2_cta_url || '');
          setInputValue('home_featured_product_ids', source.featured_ids || '');
        }

        $('#load_live_settings').on('click', function () { loadSettings(publishedSettings); });
        $('#load_draft_settings').on('click', function () { loadSettings(draftSettings); });
        $('#save_draft_btn').on('click', function () { $('#home_settings_mode').val('draft'); });
        $('#publish_btn').on('click', function () { $('#home_settings_mode').val('publish'); });
        $('#save_draft_btn_sticky').on('click', function () { $('#home_settings_mode').val('draft'); });
        $('#publish_btn_sticky').on('click', function () { $('#home_settings_mode').val('publish'); });

        function bindPreview(inputId, imgId, qualityId) {
          var $input = $('#' + inputId);
          var $img = $('#' + imgId);
          var $quality = qualityId ? $('#' + qualityId) : $();
          if (!$input.length || !$img.length) return;
          function syncPreview() {
            var val = ($input.val() || '').toString().trim();
            if (val === '') {
              $img.hide();
              $img.attr('src', '');
              if ($quality.length) $quality.hide().text('');
              return;
            }
            $img.attr('src', val);
            $img.show();
            if ($quality.length) {
              var tester = new Image();
              tester.onload = function () {
                if (tester.naturalWidth < 1600) {
                  $quality.text('Low-res warning: image width is ' + tester.naturalWidth + 'px (recommended >= 1600px).').show();
                } else {
                  $quality.hide().text('');
                }
              };
              tester.onerror = function () {
                $quality.text('Could not validate image dimensions from URL.').show();
              };
              tester.src = val;
            }
          }
          $input.on('input change', syncPreview);
          syncPreview();
        }
        bindPreview('home_hero_image', 'home_hero_image_preview', 'home_hero_image_quality');
        bindPreview('home_promo_tile_1_image', 'home_tile_1_image_preview', 'home_tile_1_image_quality');
        bindPreview('home_promo_tile_2_image', 'home_tile_2_image_preview', 'home_tile_2_image_quality');

        function refreshDuplicateAndStatus() {
          var hero = ($('#home_hero_image').val() || '').toString().trim();
          var t1 = ($('#home_promo_tile_1_image').val() || '').toString().trim();
          var t2 = ($('#home_promo_tile_2_image').val() || '').toString().trim();
          var dup = [];
          if (hero !== '' && hero === t1) dup.push('Hero and Tile 1 use the same image');
          if (hero !== '' && hero === t2) dup.push('Hero and Tile 2 use the same image');
          if (t1 !== '' && t1 === t2) dup.push('Tile 1 and Tile 2 use the same image');
          if (dup.length) {
            $('#home-dup-warning').text(dup.join(' | ')).show();
          } else {
            $('#home-dup-warning').hide().text('');
          }

          function mark(id, ok, label) {
            var el = document.getElementById(id);
            if (!el) return;
            el.textContent = label + ' ' + (ok ? 'OK' : 'Missing');
            el.style.color = ok ? '#166534' : '#9a3412';
          }
          mark('status_pub_hero', hero !== '', 'Hero');
          mark('status_pub_tile1', t1 !== '', 'Tile 1');
          mark('status_pub_tile2', t2 !== '', 'Tile 2');
        }
        $('#home_hero_image,#home_promo_tile_1_image,#home_promo_tile_2_image').on('input change', refreshDuplicateAndStatus);
        refreshDuplicateAndStatus();

        var promptTemplates = {
          cinematic: {
            hero: 'Ultra-detailed astrophotography-style hero background for a beginner telescope website, cinematic Milky Way over mountains, a modern telescope in foreground, high contrast, dark blue and orange accents, premium ecommerce look, no logos, no text, 16:9 composition, realistic lighting, web-ready.',
            tile1: 'High-quality lifestyle astronomy image, person setting up a beginner telescope outdoors at dusk, warm natural light, actionable beginner vibe, shallow depth of field, premium brand visual style, no logos, no text, 16:9 crop-safe for tile card.',
            tile2: 'Stunning deep-space nebula scene with rich detail, vivid but natural colors, astrophotography inspiration mood, clean composition with dark areas for text overlay, no logos, no text, premium ecommerce campaign aesthetic, 16:9 crop-safe.'
          },
          product: {
            hero: 'Premium telescope product hero shot outdoors at night, dramatic sky, clean composition with negative space for headline, ecommerce-ready, no logos, no text, realistic materials, 16:9.',
            tile1: 'Close-up of beginner telescope setup process, hands adjusting mount, practical educational vibe, sharp focus, no logos, no text, 16:9.',
            tile2: 'Detailed telescope with camera adapter pointed to nebula sky, product-first composition, no logos, no text, 16:9.'
          },
          lifestyle: {
            hero: 'Family-friendly stargazing night scene with telescope under clear sky, emotional and aspirational, premium web visual, no logos, no text, 16:9.',
            tile1: 'Beginner observer learning sky alignment with telescope in backyard, warm and authentic style, no logos, no text, 16:9.',
            tile2: 'Astrophotography enthusiast capturing night sky with telescope rig, dynamic but clean composition, no logos, no text, 16:9.'
          },
          deepsky: {
            hero: 'Epic deep-space inspired hero visual with Milky Way core and dark mountain silhouette, cinematic contrast, premium astronomy mood, no logos, no text, 16:9.',
            tile1: 'Star cluster themed visual with subtle telescope foreground silhouette, high clarity, no logos, no text, 16:9.',
            tile2: 'Color-rich nebula and galaxy fusion aesthetic for astrophotography promo tile, dramatic but clean, no logos, no text, 16:9.'
          }
        };

        function applyPromptVariant(variant) {
          var tpl = promptTemplates[variant] || promptTemplates.cinematic;
          $('#prompt_home_hero').val(tpl.hero);
          $('#prompt_tile_1').val(tpl.tile1);
          $('#prompt_tile_2').val(tpl.tile2);
        }
        $('#prompt_variant').on('change', function () {
          applyPromptVariant(($(this).val() || 'cinematic').toString());
        });
        applyPromptVariant(($('#prompt_variant').val() || 'cinematic').toString());

        function syncFeaturedIdsFromPicker() {
          var vals = ($('#home_featured_picker').val() || []).slice(0, 4);
          $('#home_featured_product_ids').val(vals.join(','));
        }
        $('#home_featured_picker').on('change', syncFeaturedIdsFromPicker);
        syncFeaturedIdsFromPicker();

        function currentFormPayload() {
          var ids = [
            'home_hero_title','home_hero_subtitle','home_hero_image','home_hero_image_2x','home_hero_alt',
            'home_hero_cta_label','home_hero_cta_url','home_promo_tile_1_title','home_promo_tile_1_image',
            'home_promo_tile_1_cta_label','home_promo_tile_1_cta_url','home_promo_tile_2_title','home_promo_tile_2_image',
            'home_promo_tile_2_cta_label','home_promo_tile_2_cta_url','home_featured_product_ids'
          ];
          var out = {};
          ids.forEach(function (id) { out[id] = ($('#' + id).val() || '').toString(); });
          return out;
        }
        function applyFormPayload(payload) {
          Object.keys(payload || {}).forEach(function (id) { setInputValue(id, payload[id]); });
          var pickerVals = (payload.home_featured_product_ids || '').split(',').map(function (x) { return x.trim(); }).filter(Boolean);
          $('#home_featured_picker').val(pickerVals);
          syncFeaturedIdsFromPicker();
        }
        function listPresetNames() {
          var out = [];
          var prefix = 'home_visual_preset_';
          for (var i = 0; i < localStorage.length; i++) {
            var key = localStorage.key(i) || '';
            if (key.indexOf(prefix) === 0) {
              out.push(key.slice(prefix.length));
            }
          }
          out.sort();
          return out;
        }
        function refreshPresetSelect() {
          var $sel = $('#preset_select');
          if (!$sel.length) return;
          var current = ($sel.val() || '').toString();
          var names = listPresetNames();
          $sel.empty();
          $sel.append($('<option/>').val('').text('Select preset'));
          names.forEach(function (name) {
            $sel.append($('<option/>').val(name).text(name));
          });
          if (current !== '' && names.indexOf(current) !== -1) {
            $sel.val(current);
          }
        }
        $('#preset_refresh_btn').on('click', function () { refreshPresetSelect(); });
        $('#preset_save_btn').on('click', function () {
          var name = window.prompt('Preset name');
          if (!name) return;
          var key = 'home_visual_preset_' + name.trim();
          localStorage.setItem(key, JSON.stringify(currentFormPayload()));
          refreshPresetSelect();
          $('#preset_select').val(name.trim());
          alert('Preset saved: ' + name);
        });
        $('#preset_load_btn').on('click', function () {
          var name = ($('#preset_select').val() || '').toString().trim();
          if (name === '') {
            name = window.prompt('Preset name to load');
          }
          if (!name) return;
          var key = 'home_visual_preset_' + name.trim();
          var raw = localStorage.getItem(key);
          if (!raw) { alert('Preset not found.'); return; }
          try { applyFormPayload(JSON.parse(raw)); } catch (e) { alert('Invalid preset data.'); }
        });
        $('#preset_delete_btn').on('click', function () {
          var name = ($('#preset_select').val() || '').toString().trim();
          if (name === '') {
            name = window.prompt('Preset name to delete');
          }
          if (!name) return;
          localStorage.removeItem('home_visual_preset_' + name.trim());
          refreshPresetSelect();
          alert('Preset deleted: ' + name);
        });
        refreshPresetSelect();

        $(document).on('keydown', function (e) {
          if (!e.ctrlKey || !e.shiftKey) return;
          var key = (e.key || '').toLowerCase();
          if (key === 'd') { e.preventDefault(); $('#home_settings_mode').val('draft'); $('#home-hero-form').trigger('submit'); }
          if (key === 'p') { e.preventDefault(); $('#home_settings_mode').val('publish'); $('#home-hero-form').trigger('submit'); }
          if (key === 'h') { e.preventDefault(); $('#home_hero_image').trigger('focus'); }
        });

        $('[data-media-filter]').on('click', function () {
          var filter = ($(this).attr('data-media-filter') || 'all').toString();
          $('[data-media-row="1"]').each(function () {
            var $row = $(this);
            var show = true;
            if (filter === 'recent') show = $row.attr('data-recent') === '1';
            if (filter === 'webp') show = $row.attr('data-webp') === '1';
            if (filter === 'landscape') show = $row.attr('data-landscape') === '1';
            if (filter === 'used') show = $row.attr('data-used') === '1';
            $row.toggle(show);
          });
        });
        $('[data-copy-post-image-brief]').on('click', function () {
          var $btn = $(this);
          var statusId = ($btn.attr('data-copy-status') || '').trim();
          var $form = $btn.closest('form');
          if ($form.length === 0) {
            updateCopyStatus(statusId, 'Form not found', true);
            return;
          }
          var text = buildHorizontalImageBrief($form);
          if (text.trim() === '') {
            updateCopyStatus(statusId, 'Nothing to copy', true);
            return;
          }
          if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
              updateCopyStatus(statusId, 'Copied image brief', false);
            }).catch(function () {
              var copied = fallbackCopyText(text);
              updateCopyStatus(statusId, copied ? 'Copied image brief' : 'Copy failed', !copied);
            });
            return;
          }
          var copied = fallbackCopyText(text);
          updateCopyStatus(statusId, copied ? 'Copied image brief' : 'Copy failed', !copied);
        });

        $('[data-paste-import-target]').on('click', function () {
          var $btn = $(this);
          var targetId = ($btn.attr('data-paste-import-target') || '').trim();
          var formId = ($btn.attr('data-paste-import-form') || '').trim();
          var statusId = ($btn.attr('data-paste-import-status') || '').trim();

          if (targetId === '' || formId === '') {
            return;
          }

          var target = document.getElementById(targetId);
          var form = document.getElementById(formId);
          if (!target || !form) {
            updateCopyStatus(statusId, 'Target/form not found', true);
            return;
          }

          target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          target.focus();

          if (!(navigator.clipboard && window.isSecureContext && typeof navigator.clipboard.readText === 'function')) {
            updateCopyStatus(statusId, 'Clipboard read unavailable. Paste manually.', true);
            return;
          }

          navigator.clipboard.readText().then(function (text) {
            var value = (text || '').trim();
            if (value === '') {
              updateCopyStatus(statusId, 'Clipboard is empty', true);
              return;
            }

            target.value = text;
            updateCopyStatus(statusId, 'Pasted. Importing...', false);
            if (typeof form.requestSubmit === 'function') {
              form.requestSubmit();
            } else {
              form.submit();
            }
          }).catch(function () {
            updateCopyStatus(statusId, 'Clipboard blocked. Paste manually.', true);
          });
        });

        $('#availability_safe_check_form').on('submit', function () {
          var $button = $('#availability_safe_check_button');
          var $status = $('#availability_safe_check_status');
          if ($button.length) {
            $button.prop('disabled', true).text('Checking...');
          }
          if ($status.length) {
            $status.show().text('Checking... please wait (safe delay 3-6s + request)');
          }
        });

        $('#products_check_all').on('change', function () {
          var checked = $(this).is(':checked');
          $('.products-check').prop('checked', checked);
          $('#products_bulk_status').text(checked ? 'All visible rows selected.' : '');
        });

        $('.products-check').on('change', function () {
          var total = $('.products-check').length;
          var checked = $('.products-check:checked').length;
          $('#products_check_all').prop('checked', total > 0 && total === checked);
          $('#products_bulk_status').text(checked > 0 ? (checked + ' selected') : '');
        });

        $('#products_bulk_form').on('submit', function (event) {
          var ids = $('.products-check:checked').map(function () { return $(this).val(); }).get();
          if (!ids.length) {
            event.preventDefault();
            $('#products_bulk_status').text('Select at least one product.');
            return;
          }

          $('#products_bulk_selected_ids').val(ids.join(','));
          var action = ($(this).find('select[name="bulk_action"]').val() || '').toLowerCase();
          if (action === 'delete') {
            var ok = window.confirm('Delete selected products permanently?');
            if (!ok) {
              event.preventDefault();
            }
          }
        });

        $('form').on('submit', function () {
          var $form = $(this);
          var action = ($form.find('input[name="action"]').val() || '').toLowerCase();
          if (action !== 'add_post' && action !== 'update_post') {
            return;
          }
          var $content = $form.find('textarea[name="content_html"]');
          if ($content.length === 0) {
            return;
          }
          $content.val(getContentHtml($form));
        });

        $('.post-editor-form').each(function () {
          var $form = $(this);
          $form.on('input change', 'input, textarea, select', function () {
            updatePostPreview($form);
            updateSeoAssistant($form);
          });

          var $content = $form.find('textarea[name="content_html"]');
          if ($content.length && typeof $content.on === 'function') {
            $content.on('summernote.change', function () {
              updatePostPreview($form);
              updateSeoAssistant($form);
            });
          }

          $form.find('[data-insert-block]').on('click', function (event) {
            event.preventDefault();
            var blockType = ($form.find('[data-editor-blocks]').val() || '').trim();
            insertContentBlock($form, blockType);
          });

          updatePostPreview($form);
          updateSeoAssistant($form);
          setupAutosave($form);
        });
      });
    </script>
    <style>
        :root {
            --bg: #edf3fb;
            --panel: #ffffff;
            --text: #162235;
            --muted: #5d6b81;
            --line: #d7e0ed;
            --brand: #0e2a57;
            --brand-2: #144488;
            --focus: #2f7ae5;
        }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background:
                radial-gradient(1200px 500px at 90% -10%, #d9e8ff 0%, transparent 60%),
                radial-gradient(700px 350px at -10% -15%, #d6fff0 0%, transparent 55%),
                var(--bg);
        }
        .wrap { max-width: 1260px; margin: 26px auto; padding: 0 14px 28px; }
        .box {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid #e4ebf5;
            box-shadow: 0 10px 30px rgba(8, 29, 66, 0.08);
            padding: 18px;
            margin-bottom: 16px;
            overflow-x: auto;
        }
        .box h2 {
            margin: 0 0 12px;
            font-size: 22px;
            line-height: 1.2;
        }
        input, textarea, select {
            width: 100%;
            box-sizing: border-box;
            margin: 6px 0 12px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 8px;
            background: #fdfefe;
        }
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #6ea1ee;
            box-shadow: 0 0 0 3px rgba(73, 132, 221, 0.15);
        }
        .btn {
            background: linear-gradient(180deg, var(--brand-2), var(--brand));
            color: #fff;
            border: 0;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
        }
        table { width: 100%; min-width: 720px; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e9eef6; padding: 10px 8px; font-size: 14px; }
        th { color: #2c3e57; background: #f6f9fd; position: sticky; top: 0; z-index: 1; }
        tbody tr:nth-child(even) { background: #fbfdff; }
        tbody tr:hover { background: #eef4fd; }
        .error { background: #ffe5e5; color: #8a1f1f; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .ok { background: #e4f8ea; color: #165f2b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .toplink { display: inline-block; margin-bottom: 12px; color: var(--brand); font-weight: 700; text-decoration: none; }
        .tabs {
            display:flex;
            gap:10px;
            margin-bottom:14px;
            flex-wrap:wrap;
            position: sticky;
            top: 10px;
            z-index: 20;
            padding: 8px;
            border-radius: 12px;
            border: 1px solid #dbe6f4;
            background: rgba(248, 251, 255, 0.92);
            backdrop-filter: blur(6px);
        }
        .tab {
            display:inline-block;
            text-decoration:none;
            padding:10px 14px;
            border-radius:999px;
            border:1px solid var(--line);
            background:#fff;
            color:#1c365d;
            font-weight:700;
            font-size:13px;
        }
        .tab:hover {
            border-color:#a7bee0;
            color:#112e56;
        }
        .tab.active {
            background: linear-gradient(180deg, var(--brand-2), var(--brand));
            color:#fff;
            border-color:var(--brand);
        }
        .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; margin-bottom:14px; }
        .stat { background:#f7faff; border:1px solid #dce6f3; border-radius:10px; padding:10px; }
        .stat-k { font-size:12px; color:#4a5b73; margin-bottom:4px; }
        .stat-v { font-size:24px; font-weight:800; color:#0b1f3a; }
        .muted { color: var(--muted); font-size:13px; }
        .toolbar { display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-bottom:12px; }
        .toolbar .field { max-width:280px; }
        .copy-toolbar {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:10px;
            flex-wrap:wrap;
            margin-bottom:8px;
        }
        .copy-toolbar h2,
        .copy-toolbar h3 {
            margin:0;
        }
        .copy-actions {
            display:flex;
            align-items:center;
            gap:8px;
            flex-wrap:wrap;
        }
        .btn-copy {
            padding:8px 12px;
            font-size:12px;
            border-radius:8px;
        }
        .copy-status {
            font-size:12px;
            color:#5d6f86;
            min-height:16px;
        }
        .help-icon {
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:18px;
            height:18px;
            border-radius:999px;
            border:1px solid #9fb7da;
            color:#1f4f8e;
            background:#eef4ff;
            font-size:11px;
            font-weight:800;
            line-height:1;
            cursor:help;
            user-select:none;
            vertical-align:middle;
            margin-left:6px;
        }
        .copy-source {
            position:absolute;
            left:-9999px;
            width:1px;
            height:1px;
            opacity:0;
            pointer-events:none;
        }
        .empty { padding:14px; border:1px dashed #d8e2ee; border-radius:8px; color:#5d6f86; background:#f9fbfe; }
        .note-editor { margin-bottom: 12px; }
        .maintenance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }
        .maintenance-card {
            border: 1px solid #d9e6f7;
            border-radius: 12px;
            background: #f8fbff;
            padding: 12px;
        }
        .maintenance-card h4 {
            margin: 0 0 6px;
            font-size: 15px;
        }
        .maintenance-meta {
            margin: 0 0 8px;
            font-size: 12px;
            color: #395377;
        }
        .maintenance-desc {
            margin: 0 0 10px;
            font-size: 13px;
            color: #395377;
        }
        .maintenance-last {
            margin: 0 0 10px;
            font-size: 12px;
            color: #5d6f86;
        }
        .maintenance-last strong.ok { color: #1e6a31; background: transparent; padding: 0; }
        .maintenance-last strong.fail { color: #9b1c1c; background: transparent; padding: 0; }
        .ops-nav {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 10px;
            border: 1px solid #dbe6f4;
            border-radius: 10px;
            background: #f6faff;
            margin: 0 0 12px;
        }
        .ops-link {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
            border: 1px solid #c9d8ee;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 700;
            color: #1c3b67;
            background: #fff;
        }
        .ops-link:hover {
            border-color: #8fb0dd;
            color: #0f2f59;
        }
        .quick-actions {
            display:flex;
            flex-wrap:wrap;
            gap:8px;
            align-items:center;
        }
        a, button, input, textarea, select {
            transition: box-shadow .15s ease, border-color .15s ease, background-color .15s ease, color .15s ease;
        }
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        textarea:focus-visible,
        select:focus-visible {
            outline: 2px solid var(--focus);
            outline-offset: 2px;
        }
        div[style="display:grid;grid-template-columns:1fr 1fr;gap:15px;"] {
            display:grid !important;
            grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
            gap:15px !important;
        }
        .ops-kpis {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
            gap:10px;
            margin: 0 0 12px;
        }
        .ops-kpi {
            border:1px solid #dbe6f4;
            border-radius:10px;
            background:#f8fbff;
            padding:10px;
        }
        .ops-kpi .k {
            font-size:12px;
            color:#4e6280;
            margin-bottom:4px;
        }
        .ops-kpi .v {
            font-size:20px;
            font-weight:800;
            color:#0d2a53;
        }
        .ops-section-title {
            margin: 0 0 8px;
            font-size: 16px;
        }
        .ops-anchor-offset {
            scroll-margin-top: 88px;
        }
	        .maintenance-badge {
	            display: inline-block;
	            font-size: 11px;
	            padding: 2px 8px;
	            border-radius: 999px;
            border: 1px solid #b8ccec;
            color: #20426f;
            background: #edf4ff;
	            margin-left: 6px;
	            vertical-align: middle;
	        }
	        .post-preview-grid {
	            display:grid;
	            grid-template-columns:1.1fr 1fr;
	            gap:16px;
	            align-items:start;
	        }
	        .post-preview-card {
	            border:1px solid #d8e3f0;
	            border-radius:12px;
	            background:#fbfdff;
	            padding:14px;
	        }
	        .serp-preview-title {
	            color:#1a0dab;
	            font-size:22px;
	            line-height:1.25;
	            margin:0 0 6px;
	        }
	        .serp-preview-url {
	            color:#188038;
	            font-size:14px;
	            margin-bottom:6px;
	        }
	        .serp-preview-desc {
	            color:#4d5156;
	            font-size:14px;
	            line-height:1.45;
	            margin:0;
	        }
	        .post-render-preview {
	            border:1px solid #dfe8f3;
	            border-radius:16px;
	            background:#fff;
	            overflow:hidden;
	        }
	        .post-render-preview .hero-preview {
	            padding:18px;
	            background:linear-gradient(145deg,#fff9ee 0%,#fff2d8 100%);
	            border-bottom:1px solid #ebf0f5;
	        }
	        .post-render-preview .hero-preview h3 {
	            margin:10px 0 8px;
	            font-size:28px;
	            line-height:1.1;
	            font-family:Georgia, serif;
	        }
	        .preview-kicker {
	            display:inline-flex;
	            font-size:11px;
	            font-weight:800;
	            text-transform:uppercase;
	            letter-spacing:.04em;
	            color:#0f294f;
	            background:#eaf2ff;
	            border-radius:999px;
	            padding:5px 8px;
	        }
	        .preview-hero-media {
	            margin:16px auto 0;
	            width:min(100%, 560px);
	            aspect-ratio:16 / 9;
	            border-radius:12px;
	            background:#15284a;
	            display:flex;
	            align-items:center;
	            justify-content:center;
	            overflow:hidden;
	        }
	        .preview-hero-media img {
	            width:100%;
	            height:100%;
	            object-fit:cover;
	            display:block;
	        }
	        .preview-article-body {
	            padding:18px;
	        }
	        .preview-article-body h4 {
	            margin:0 0 10px;
	            font-size:22px;
	            font-family:Georgia, serif;
	        }
	        .preview-muted {
	            color:var(--muted);
	            font-size:14px;
	        }
            .seo-panel {
                border: 1px solid #d8e3f0;
                border-radius: 12px;
                background: #f9fcff;
                padding: 12px;
                margin: 8px 0 14px;
            }
            .seo-panel h3 {
                margin: 0 0 10px;
                font-size: 16px;
            }
            .seo-score {
                font-size: 24px;
                font-weight: 800;
                color: #0e2a57;
            }
            .seo-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
                gap: 8px;
                margin: 10px 0;
            }
            .seo-metric {
                border: 1px solid #e0eaf7;
                border-radius: 8px;
                padding: 8px;
                background: #fff;
                font-size: 12px;
            }
            .seo-checklist {
                margin: 10px 0 0;
                padding: 0;
                list-style: none;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
                gap: 8px;
            }
            .seo-checklist li {
                border: 1px solid #dbe6f4;
                border-radius: 8px;
                padding: 8px;
                font-size: 13px;
                display: flex;
                justify-content: space-between;
                gap: 10px;
                background: #fff;
            }
            .seo-ok {
                color: #1a6f35;
            }
            .seo-warn {
                color: #9a2f15;
            }
            .editor-tools {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                align-items: flex-end;
                margin: 8px 0 10px;
            }
            .editor-tools .field {
                min-width: 220px;
                flex: 1;
            }
	        .btn[disabled] {
	            opacity: 0.55;
	            cursor: not-allowed;
	        }
        @media (max-width: 980px) {
            .tabs {
                position: static;
                backdrop-filter: none;
            }
	            .post-preview-grid {
	                grid-template-columns:1fr;
	            }
                div[style="display:grid;grid-template-columns:1fr 1fr;gap:15px;"] {
                    grid-template-columns:1fr !important;
                }
                .ops-nav {
                    gap: 6px;
                    padding: 8px;
                }
                .ops-link {
                    font-size: 11px;
                    padding: 5px 8px;
                }
	        }
	    </style>
</head>
<body>
<div class="wrap">
    <a class="toplink" href="<?= e(url('/')) ?>">Volver al sitio</a>

    <?php foreach ($errors as $error): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <?php if ($flash !== null): ?>
        <div class="ok"><?= e($flash) ?></div>
    <?php endif; ?>

    <?php if (!$authenticated): ?>
	        <section class="box">
	            <h2>Admin Login</h2>
	            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label>User</label>
                <input type="text" name="user" required>
                <label>Password</label>
	                <input type="password" name="pass" required>
	                <button class="btn" type="submit">Login</button>
	            </form>
	        </section>
    <?php else: ?>
        <div class="tabs">
            <a class="tab <?= $activeTab === 'control' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=control')) ?>">Control</a>
            <a class="tab <?= $activeTab === 'overview' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=overview')) ?>">Resumen</a>
            <a class="tab <?= $activeTab === 'products' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=products')) ?>">Productos</a>
            <a class="tab <?= $activeTab === 'media' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=media')) ?>">Media</a>
            <a class="tab <?= $activeTab === 'posts' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=posts')) ?>">Publicaciones</a>
            <a class="tab <?= $activeTab === 'indexation' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=indexation')) ?>">Indexacion</a>
            <a class="tab <?= $activeTab === 'prompts' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=prompts')) ?>">Prompts</a>
            <a class="tab <?= $activeTab === 'users' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=users')) ?>">Usuarios</a>
            <a class="tab <?= $activeTab === 'views' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=views&days=' . $viewDays)) ?>">Visitas</a>
            <a class="tab <?= $activeTab === 'analytics' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=analytics')) ?>">Analytics & Seguridad</a>
            <a class="tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=maintenance')) ?>">Mantenimiento</a>
        </div>
        <section class="box" style="padding:12px 14px;">
            <div class="quick-actions">
                <span class="muted" style="margin:0;font-size:12px;font-weight:700;">Accesos rapidos:</span>
                <a class="ops-link" href="<?= e(url('/enma/?tab=products#products-list')) ?>">Lista de productos</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=products#products-not-found-actions')) ?>">Limpieza not found</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=indexation')) ?>">Seguimiento indexacion</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Workspace prompts</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-progress')) ?>">Progreso mantenimiento</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-safe-check')) ?>">Chequeo seguro</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-not-found-review')) ?>">Cola de revision</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Importar catalogo</a>
            </div>
        </section>

        <?php if ($activeTab === 'control'): ?>
        <?php require __DIR__ . '/views/tabs/control.php'; ?>
        <?php elseif ($activeTab === 'overview'): ?>
        <section class="box">
            <h2>Admin Overview</h2>
            <div class="stats">
                <div class="stat"><div class="stat-k">Products</div><div class="stat-v"><?= number_format((int) ($overviewStats['products'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Categories</div><div class="stat-v"><?= number_format((int) ($overviewStats['categories'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Views (30d)</div><div class="stat-v"><?= number_format((int) ($overviewStats['views_30d'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Missing Tags</div><div class="stat-v"><?= number_format((int) ($overviewStats['missing_tags'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Missing Images</div><div class="stat-v"><?= number_format((int) ($overviewStats['missing_images'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Posts</div><div class="stat-v"><?= number_format((int) ($overviewStats['posts'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Users</div><div class="stat-v"><?= number_format((int) ($overviewStats['users'] ?? 0)) ?></div></div>
            </div>
            <p class="muted">Use tabs for product management, traffic analytics, and DB/scripts maintenance.</p>
        </section>
        <section class="box">
            <h2>Recent Admin Activity</h2>
            <?php if ($recentAdminActivity === []): ?>
                <div class="empty">No admin activity recorded yet.</div>
            <?php else: ?>
                <p class="muted">Showing <?= number_format(count($recentAdminActivity)) ?> of <?= number_format($activityTotal) ?> records.</p>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Entity</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recentAdminActivity as $activity): ?>
                        <tr>
                            <td><?= e((string) ($activity['created_at'] ?? '')) ?></td>
                            <td><?= e((string) ($activity['admin_username'] ?? '')) ?></td>
                            <td><?= e((string) ($activity['action_key'] ?? '')) ?></td>
                            <td><?= e(trim((string) (($activity['entity_type'] ?? '') . ' #' . ($activity['entity_id'] ?? '')), ' #')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?= $activityPagination ?>
            <?php endif; ?>
        </section>
        <?php elseif ($activeTab === 'products'): ?>
        <section class="box">
            <h2>Products Workspace</h2>
            <p class="muted" style="margin:0 0 10px;">Add/edit catalog entries, filter quickly, and jump to AI-assisted catalog refresh tools.</p>
            <div class="ops-kpis">
                <div class="ops-kpi"><div class="k">Visible Rows</div><div class="v"><?= number_format(count($allProducts)) ?></div></div>
                <div class="ops-kpi"><div class="k">Total Products</div><div class="v"><?= number_format($productsTotal) ?></div></div>
                <div class="ops-kpi"><div class="k">Current Page</div><div class="v"><?= number_format($productsPage) ?>/<?= number_format($productsTotalPages) ?></div></div>
                <div class="ops-kpi"><div class="k">Search Filter</div><div class="v" style="font-size:14px;line-height:1.3;"><?= e($productQuery !== '' ? $productQuery : 'none') ?></div></div>
            </div>
            <div class="ops-nav">
                <a class="ops-link" href="#products-add">Add Product</a>
                <a class="ops-link" href="#products-list">Product List</a>
                <a class="ops-link" href="#products-not-found-actions">Not Found Cleanup</a>
                <a class="ops-link" href="#products-ai-import">AI New Products</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Full Catalog Mode</a>
            </div>
        </section>
        <section id="products-not-found-actions" class="box ops-anchor-offset">
            <?php
            $notFoundFlaggedTotal = 0;
            try {
                $notFoundFlaggedTotal = (int) $pdo->query(
                    'SELECT COUNT(*)
                     FROM product_link_checks
                     WHERE state = "not_found"'
                )->fetchColumn();
            } catch (Throwable $e) {
                $notFoundFlaggedTotal = 0;
            }
            ?>
            <h2>Not Found Cleanup</h2>
            <p class="muted" style="margin:0 0 10px;">
                <strong>Clean Not Found</strong> archives broken products. <strong>Delete Not Found</strong> permanently removes products flagged as not found.
                Current flagged as <code>not_found</code>: <?= number_format($notFoundFlaggedTotal) ?>.
            </p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="maintenance_run">
                    <input type="hidden" name="task" value="clean_not_found_products">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="btn" type="submit">Clean Not Found (Archive)</button>
                </form>
                <form method="post" style="margin:0;" onsubmit="return confirm('Delete permanently all products flagged as not_found? This cannot be undone.');">
                    <input type="hidden" name="action" value="maintenance_run">
                    <input type="hidden" name="task" value="delete_not_found_products">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" style="background:#b91c1c;color:#fff;border:none;border-radius:8px;padding:10px 14px;font-weight:700;cursor:pointer;">Delete Not Found (Permanent)</button>
                </form>
                <a class="tab" href="<?= e(url('/enma/?tab=maintenance#ops-not-found-review')) ?>">Open Not Found Review</a>
            </div>
        </section>
        <section id="products-ai-import" class="box ops-anchor-offset">
            <textarea id="products_new_prompt_copy_source" class="copy-source" readonly><?= e($productsNewPromptTemplate) ?></textarea>
            <div class="copy-toolbar" style="margin-bottom:10px;">
                <h2>AI New Products (Fast Flow)</h2>
                <div class="copy-actions">
                    <button class="btn btn-copy" type="button" data-copy-target="products_new_prompt_copy_source" data-copy-status="products_new_prompt_copy_status">Copy Prompt For Claude</button>
                    <span id="products_new_prompt_copy_status" class="copy-status"></span>
                </div>
            </div>
            <p class="muted" style="margin:0 0 10px;">One-click prompt -> paste in Claude -> copy returned array -> paste below -> import only NEW ASINs (existing products are preserved).</p>
            <?php $productsAiImportForm = is_array($productsAiImportForm ?? null) ? $productsAiImportForm : ['payload' => '']; ?>
            <form id="products_ai_import_form" method="post" style="margin:0;">
                <input type="hidden" name="action" value="import_products_ai">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <label for="products_ai_payload">Paste Claude PHP array (new products only)</label>
                <textarea id="products_ai_payload" name="products_ai_payload" rows="12" placeholder="$products = [ ... ];"><?= e((string) ($productsAiImportForm['payload'] ?? '')) ?></textarea>
                <div style="display:flex;gap:10px;align-items:center;margin-top:10px;">
                    <button class="btn" type="button" data-paste-import-target="products_ai_payload" data-paste-import-form="products_ai_import_form" data-paste-import-status="products_ai_paste_import_status">Paste & Import</button>
                    <button class="btn" type="submit">Import New Products</button>
                    <span id="products_ai_paste_import_status" class="copy-status"></span>
                </div>
            </form>
            <?php if (is_array($productsAiImportResult ?? null)): ?>
                <div style="margin-top:10px;border:1px solid #e2e8f0;border-radius:8px;padding:10px;background:#f8fbff;">
                    <?php if (!empty($productsAiImportResult['ok'])): ?>
                        <p style="margin:0 0 8px;font-size:13px;">Result: <strong class="ok">OK</strong></p>
                        <div style="font-family:monospace;font-size:12px;">Inserted: <?= number_format((int) ($productsAiImportResult['inserted'] ?? 0)) ?></div>
                        <div style="font-family:monospace;font-size:12px;">Skipped existing ASIN: <?= number_format((int) ($productsAiImportResult['skipped_existing'] ?? 0)) ?></div>
                        <div style="font-family:monospace;font-size:12px;">Skipped invalid rows: <?= number_format((int) ($productsAiImportResult['skipped_invalid'] ?? 0)) ?></div>
                        <div style="font-family:monospace;font-size:12px;">Skipped duplicate rows in payload: <?= number_format((int) ($productsAiImportResult['skipped_duplicate_payload'] ?? 0)) ?></div>
                    <?php else: ?>
                        <p style="margin:0 0 8px;font-size:13px;">Result: <strong class="fail">FAIL</strong></p>
                        <div style="font-family:monospace;font-size:12px;"><?= e((string) ($productsAiImportResult['message'] ?? 'Unknown error')) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>
        <?php if ($editingProduct): ?>
        <section id="products-edit" class="box ops-anchor-offset">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h2 style="margin:0;">Edit Product: <?= e($editingProduct['title']) ?></h2>
                <a href="<?= e(url('/enma/?tab=products')) ?>" style="font-size:13px;">&larr; Cancel Edit</a>
            </div>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="id" value="<?= (int)$editingProduct['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>ASIN</label>
                        <input type="text" name="asin" required value="<?= e($editingProduct['asin']) ?>">
                    </div>
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required value="<?= e($editingProduct['title']) ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Category Name</label>
                        <input type="text" name="category_name" required value="<?= e($editingProduct['category_name']) ?>">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Image URL</label>
                        <input type="url" name="image_url" value="<?= e($editingProduct['image_url'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Upload New Image</label>
                        <input type="file" name="image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <label>Affiliate URL</label>
                <input type="url" name="affiliate_url" required value="<?= e($editingProduct['affiliate_url']) ?>">
                <label>Description</label>
                <textarea name="description" rows="4"><?= e($editingProduct['description'] ?? '') ?></textarea>
                <button class="btn" type="submit">Update Product</button>
            </form>
        </section>
        <?php endif; ?>

        <section id="products-add" class="box ops-anchor-offset">
            <h2>Add Product</h2>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_product">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>ASIN</label>
                        <input type="text" name="asin" required>
                    </div>
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Category Name</label>
                        <input type="text" name="category_name" required>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Image URL</label>
                        <input type="url" name="image_url" placeholder="https://...">
                    </div>
                    <div>
                        <label>Upload Image</label>
                        <input type="file" name="image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <label>Affiliate URL</label>
                <input type="url" name="affiliate_url" required placeholder="https://www.amazon.com/dp/...?...">
                <label>Description</label>
                <textarea name="description" rows="4"></textarea>
                <button class="btn" type="submit">Save Product</button>
            </form>
        </section>

        <section id="products-list" class="box ops-anchor-offset">
            <div class="copy-toolbar">
                <h2>Products</h2>
                <div class="copy-actions">
                    <button class="btn btn-copy" type="button" data-copy-target="products_copy_source" data-copy-status="products_copy_status">Copy Product List</button>
                    <span id="products_copy_status" class="copy-status"></span>
                </div>
            </div>
            <form method="get" class="toolbar">
                <input type="hidden" name="tab" value="products">
                <input type="hidden" name="products_page" value="1">
                <div class="field">
                    <label>Search</label>
                    <input type="text" name="q" value="<?= e($productQuery) ?>" placeholder="ASIN, title, category">
                </div>
                <button class="btn" type="submit">Filter</button>
            </form>
            <?php if ($allProducts === []): ?>
                <div class="empty">No products found for this filter.</div>
            <?php else: ?>
            <textarea id="products_copy_source" class="copy-source" readonly><?= e($productsCopyText) ?></textarea>
            <p class="muted">Showing <?= number_format(count($allProducts)) ?> of <?= number_format($productsTotal) ?> products.</p>
            <form id="products_bulk_form" method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin:0 0 10px;">
                <input type="hidden" name="action" value="bulk_products_apply">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="selected_ids" id="products_bulk_selected_ids" value="">
                <div class="field" style="max-width:280px;margin:0;">
                    <label>Bulk action</label>
                    <select name="bulk_action">
                        <option value="archive">Archive selected</option>
                        <option value="delete">Delete selected permanently</option>
                    </select>
                </div>
                <button class="btn" type="submit" data-products-bulk-submit>Apply to selected</button>
                <span id="products_bulk_status" class="muted" style="margin:0 0 10px;"></span>
            </form>
            <table>
                <thead>
                    <tr>
                        <th style="width:38px;">
                            <input type="checkbox" id="products_check_all" aria-label="Select all products">
                        </th>
                        <th>ID</th>
                        <th>ASIN</th>
                        <th>Image</th>
                        <th>Quick Image Fix</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Available</th>
                        <th>Tag</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allProducts as $item): ?>
                    <?php
                    $affiliateUrl = trim((string) ($item['affiliate_url'] ?? ''));
                    $isPublished = strtolower(trim((string) ($item['status'] ?? 'published'))) === 'published';
                    $hasAffiliateUrl = $affiliateUrl !== '' && filter_var($affiliateUrl, FILTER_VALIDATE_URL) !== false;
                    $linkState = strtolower(trim((string) ($item['link_state'] ?? '')));
                    $isLinkNotFound = $linkState === 'not_found' || $linkState === 'warning';
                    $isAvailable = $isPublished && $hasAffiliateUrl && !$isLinkNotFound;
                    $availabilityLabel = $isAvailable ? 'Available' : 'Not available';
                    $availabilityColor = $isAvailable ? '#16a34a' : '#dc2626';
                    $productSlug = trim((string) ($item['slug'] ?? ''));
                    $productViewUrl = $productSlug !== ''
                        ? url('/product/' . $productSlug)
                        : ($hasAffiliateUrl ? outbound_url($affiliateUrl, (int) ($item['id'] ?? 0)) : '');
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="products-check" value="<?= (int) $item['id'] ?>" aria-label="Select product <?= (int) $item['id'] ?>">
                        </td>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= e($item['asin']) ?></td>
                        <td style="width:84px;">
                            <img
                                src="<?= e(product_image_url((array) $item)) ?>"
                                alt="<?= e((string) ($item['title'] ?? 'Product image')) ?>"
                                loading="lazy"
                                decoding="async"
                                style="display:block;width:68px;height:68px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;"
                                onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';"
                            >
                        </td>
                        <td style="min-width:260px;">
                            <form method="post" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="action" value="quick_update_product_image">
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input
                                    type="url"
                                    name="quick_image_url"
                                    value="<?= e((string) ($item['image_url'] ?? '')) ?>"
                                    placeholder="Paste Amazon image URL"
                                    style="margin:0;min-width:170px;font-size:12px;padding:6px 8px;"
                                >
                                <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;">Save image</button>
                            </form>
                        </td>
                        <td><?= e($item['title']) ?></td>
                        <td><?= e($item['category_name']) ?></td>
                        <td>
                            <span style="display:inline-flex;align-items:center;gap:6px;font-weight:700;">
                                <span style="width:9px;height:9px;border-radius:50%;display:inline-block;background:<?= e($availabilityColor) ?>;"></span>
                                <?= e($availabilityLabel) ?>
                            </span>
                        </td>
                        <td>
                            <?= amazon_tag_present($affiliateUrl) ? '<span style="color:#16a34a;font-weight:700;">OK</span>' : '<span style="color:#dc2626;font-weight:700;">Missing</span>' ?>
                        </td>
                        <td>
                            <?php if ($hasAffiliateUrl): ?>
                                <a href="<?= e($affiliateUrl) ?>" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Open Amazon</a>
                            <?php endif; ?>
                            <?php if ($productViewUrl !== ''): ?>
                                <a href="<?= e($productViewUrl) ?>" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">View</a>
                            <?php endif; ?>
                            <a href="<?= e(url('/enma/?tab=products&edit_product=' . $item['id'])) ?>" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Edit</a>
                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:13px;">Delete</button>
                            </form>
                        </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
              </table>
              <?= $productsPagination ?>
              <?php endif; ?>
          </section>
        <?php elseif ($activeTab === 'media'): ?>
        <section class="box">
            <h2>Media Library Workspace</h2>
            <p class="muted" style="margin:0 0 10px;">Upload assets once and reuse their URLs in posts/guides quickly.</p>
            <div class="ops-kpis">
                <div class="ops-kpi"><div class="k">Visible Rows</div><div class="v"><?= number_format(count($allMedia)) ?></div></div>
                <div class="ops-kpi"><div class="k">Total Assets</div><div class="v"><?= number_format($mediaTotal) ?></div></div>
                <div class="ops-kpi"><div class="k">Current Page</div><div class="v"><?= number_format($mediaPage) ?>/<?= number_format($mediaTotalPages) ?></div></div>
                <div class="ops-kpi"><div class="k">Table Status</div><div class="v" style="font-size:14px;line-height:1.3;"><?= $mediaTableReady ? 'Ready' : 'Missing' ?></div></div>
            </div>
            <div class="ops-nav">
                <a class="ops-link" href="#media-upload">Upload Asset</a>
                <a class="ops-link" href="#media-list">Library List</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=maintenance#ops-routines')) ?>">Sync Indexation Tracker</a>
            </div>
        </section>

        <section id="home-hero-settings" class="box ops-anchor-offset">
            <h2>Home Hero Settings</h2>
            <p class="muted" style="margin:0 0 10px;">Control hero content, promo tiles, and featured home products. Use Media Library URLs in WEBP format when possible.</p>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin:0 0 12px;">
                <div style="border:1px solid #d7e0ed;border-radius:10px;padding:10px;background:#fff;">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#1f3558;">Published</div>
                    <div style="margin-top:6px;font-size:13px;">
                        <span id="status_pub_hero" style="display:inline-block;margin-right:8px;color:<?= $homeVisualStatus['published']['hero'] ? '#166534' : '#9a3412' ?>;">Hero <?= $homeVisualStatus['published']['hero'] ? 'OK' : 'Missing' ?></span>
                        <span id="status_pub_tile1" style="display:inline-block;margin-right:8px;color:<?= $homeVisualStatus['published']['tile1'] ? '#166534' : '#9a3412' ?>;">Tile 1 <?= $homeVisualStatus['published']['tile1'] ? 'OK' : 'Missing' ?></span>
                        <span id="status_pub_tile2" style="display:inline-block;color:<?= $homeVisualStatus['published']['tile2'] ? '#166534' : '#9a3412' ?>;">Tile 2 <?= $homeVisualStatus['published']['tile2'] ? 'OK' : 'Missing' ?></span>
                    </div>
                </div>
                <div style="border:1px solid #d7e0ed;border-radius:10px;padding:10px;background:#fff;">
                    <div style="font-size:12px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#1f3558;">Draft</div>
                    <div style="margin-top:6px;font-size:13px;">
                        <span style="display:inline-block;margin-right:8px;color:<?= $homeVisualStatus['draft']['hero'] ? '#166534' : '#9a3412' ?>;">Hero <?= $homeVisualStatus['draft']['hero'] ? 'OK' : 'Missing' ?></span>
                        <span style="display:inline-block;margin-right:8px;color:<?= $homeVisualStatus['draft']['tile1'] ? '#166534' : '#9a3412' ?>;">Tile 1 <?= $homeVisualStatus['draft']['tile1'] ? 'OK' : 'Missing' ?></span>
                        <span style="display:inline-block;color:<?= $homeVisualStatus['draft']['tile2'] ? '#166534' : '#9a3412' ?>;">Tile 2 <?= $homeVisualStatus['draft']['tile2'] ? 'OK' : 'Missing' ?></span>
                    </div>
                </div>
            </div>
            <form method="post" id="home-hero-form">
                <input type="hidden" name="action" value="save_home_hero_settings">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="home_settings_mode" id="home_settings_mode" value="publish">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;">
                    <button class="btn" type="button" id="load_live_settings">Load Published</button>
                    <button class="btn" type="button" id="load_draft_settings">Load Draft</button>
                    <button class="btn" type="submit" id="save_draft_btn">Save Draft</button>
                    <button class="btn" type="submit" id="publish_btn">Publish To Home</button>
                </div>
                <div id="home-dup-warning" style="display:none;margin:0 0 10px;padding:8px 10px;border-radius:8px;background:#fff4e5;border:1px solid #f0c48a;color:#8a3b00;font-size:12px;font-weight:700;"></div>
                <label>Hero title (optional override)</label>
                <input id="home_hero_title" type="text" name="home_hero_title" value="<?= e((string) ($homeHeroSettings['title'] ?? '')) ?>" placeholder="See what's out there">
                <label>Hero eyebrow / small label</label>
                <input type="text" name="home_hero_eyebrow" value="<?= e(site_setting_get($pdo, 'home_hero_eyebrow', 'Astronomy Affiliate Guide')) ?>" placeholder="Astronomy Affiliate Guide">
                <label>Hero subtitle (optional override)</label>
                <input id="home_hero_subtitle" type="text" name="home_hero_subtitle" value="<?= e((string) ($homeHeroSettings['subtitle'] ?? '')) ?>" placeholder="Short value statement">
                <label>Hero image URL (1x)</label>
                <input id="home_hero_image" type="text" name="home_hero_image" list="media-image-urls" value="<?= e((string) ($homeHeroSettings['image'] ?? '')) ?>" placeholder="/assets/img/optimized_1.webp or https://...">
                <label>Hero image URL (2x, optional)</label>
                <input id="home_hero_image_2x" type="text" name="home_hero_image_2x" list="media-image-urls" value="<?= e((string) ($homeHeroSettings['image_2x'] ?? '')) ?>" placeholder="/assets/img/optimized_2.webp or https://...">
                <img id="home_hero_image_preview" src="<?= e((string) ($homeHeroSettings['image'] ?? '')) ?>" alt="" style="max-width:220px;max-height:120px;border-radius:8px;border:1px solid #d7e0ed;display:<?= trim((string) ($homeHeroSettings['image'] ?? '')) !== '' ? 'block' : 'none' ?>;">
                <div id="home_hero_image_quality" style="display:none;font-size:12px;color:#8a3b00;font-weight:700;margin-top:4px;"></div>
                <label>Hero image ALT text</label>
                <input id="home_hero_alt" type="text" name="home_hero_alt" value="<?= e((string) ($homeHeroSettings['alt'] ?? '')) ?>" placeholder="Accessible description of the hero image">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label>Hero CTA label</label>
                        <input id="home_hero_cta_label" type="text" name="home_hero_cta_label" value="<?= e((string) ($homeHeroSettings['cta_label'] ?? '')) ?>" placeholder="Explore Telescopes">
                    </div>
                    <div>
                        <label>Hero CTA URL</label>
                        <input id="home_hero_cta_url" type="text" name="home_hero_cta_url" value="<?= e((string) ($homeHeroSettings['cta_url'] ?? '')) ?>" placeholder="/best-beginner-telescopes">
                    </div>
                </div>
                <label>Hero overlay darkness (%)</label>
                <input type="number" min="15" max="85" name="home_hero_overlay" value="<?= e((string) ($homeHeroSettings['overlay'] ?? '55')) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <div>
                        <label>Hero text position</label>
                        <select name="home_hero_text_position">
                            <?php $heroPos = site_setting_get($pdo, 'home_hero_text_position', 'center'); ?>
                            <?php foreach (['left','center','right','bottom-left','bottom-center','bottom-right'] as $opt): ?>
                                <option value="<?= e($opt) ?>" <?= $heroPos === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Hero overlay strength</label>
                        <?php $heroOverlayStrength = site_setting_get($pdo, 'home_hero_overlay_strength', 'dark'); ?>
                        <select name="home_hero_overlay_strength"><?php foreach (['none','light','medium','dark'] as $opt): ?><option value="<?= e($opt) ?>" <?= $heroOverlayStrength === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select>
                    </div>
                    <div>
                        <label>Hero layout size</label>
                        <?php $heroLayoutSize = site_setting_get($pdo, 'home_hero_layout_size', 'full'); ?>
                        <select name="home_hero_layout_size"><?php foreach (['full','half','third'] as $opt): ?><option value="<?= e($opt) ?>" <?= $heroLayoutSize === $opt ? 'selected' : '' ?>><?= e($opt) ?></option><?php endforeach; ?></select>
                    </div>
                </div>

                <h3 style="margin:14px 0 8px;">Promo Tile 1</h3>
                <input id="home_promo_tile_1_title" type="text" name="home_promo_tile_1_title" value="<?= e((string) ($homeHeroSettings['tile_1_title'] ?? '')) ?>" placeholder="Start Stargazing Now">
                <input type="text" name="home_promo_tile_1_eyebrow" value="<?= e(site_setting_get($pdo, 'home_promo_tile_1_eyebrow', '')) ?>" placeholder="Tile 1 eyebrow">
                <input type="text" name="home_promo_tile_1_subtitle" value="<?= e(site_setting_get($pdo, 'home_promo_tile_1_subtitle', '')) ?>" placeholder="Tile 1 subtitle">
                <input id="home_promo_tile_1_image" type="text" name="home_promo_tile_1_image" list="media-image-urls" value="<?= e((string) ($homeHeroSettings['tile_1_image'] ?? '')) ?>" placeholder="/assets/uploads/media/....webp">
                <img id="home_tile_1_image_preview" src="<?= e((string) ($homeHeroSettings['tile_1_image'] ?? '')) ?>" alt="" style="max-width:220px;max-height:120px;border-radius:8px;border:1px solid #d7e0ed;display:<?= trim((string) ($homeHeroSettings['tile_1_image'] ?? '')) !== '' ? 'block' : 'none' ?>;">
                <div id="home_tile_1_image_quality" style="display:none;font-size:12px;color:#8a3b00;font-weight:700;margin-top:4px;"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input id="home_promo_tile_1_cta_label" type="text" name="home_promo_tile_1_cta_label" value="<?= e((string) ($homeHeroSettings['tile_1_cta_label'] ?? '')) ?>" placeholder="Beginner Telescopes">
                    <input id="home_promo_tile_1_cta_url" type="text" name="home_promo_tile_1_cta_url" value="<?= e((string) ($homeHeroSettings['tile_1_cta_url'] ?? '')) ?>" placeholder="/best-beginner-telescopes">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <?php $t1Pos = site_setting_get($pdo, 'home_promo_tile_1_text_position', 'bottom-left'); ?>
                    <?php $t1Overlay = site_setting_get($pdo, 'home_promo_tile_1_overlay_strength', 'medium'); ?>
                    <?php $t1Size = site_setting_get($pdo, 'home_promo_tile_1_layout_size', 'half'); ?>
                    <select name="home_promo_tile_1_text_position"><?php foreach (['left','center','right','bottom-left','bottom-center','bottom-right'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t1Pos === $opt ? 'selected' : '' ?>><?= e('Tile1 '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_promo_tile_1_overlay_strength"><?php foreach (['none','light','medium','dark'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t1Overlay === $opt ? 'selected' : '' ?>><?= e('Overlay '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_promo_tile_1_layout_size"><?php foreach (['full','half','third'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t1Size === $opt ? 'selected' : '' ?>><?= e('Size '.$opt) ?></option><?php endforeach; ?></select>
                </div>

                <h3 style="margin:14px 0 8px;">Promo Tile 2</h3>
                <input id="home_promo_tile_2_title" type="text" name="home_promo_tile_2_title" value="<?= e((string) ($homeHeroSettings['tile_2_title'] ?? '')) ?>" placeholder="Create Your Masterpiece">
                <input type="text" name="home_promo_tile_2_eyebrow" value="<?= e(site_setting_get($pdo, 'home_promo_tile_2_eyebrow', '')) ?>" placeholder="Tile 2 eyebrow">
                <input type="text" name="home_promo_tile_2_subtitle" value="<?= e(site_setting_get($pdo, 'home_promo_tile_2_subtitle', '')) ?>" placeholder="Tile 2 subtitle">
                <input id="home_promo_tile_2_image" type="text" name="home_promo_tile_2_image" list="media-image-urls" value="<?= e((string) ($homeHeroSettings['tile_2_image'] ?? '')) ?>" placeholder="/assets/uploads/media/....webp">
                <img id="home_tile_2_image_preview" src="<?= e((string) ($homeHeroSettings['tile_2_image'] ?? '')) ?>" alt="" style="max-width:220px;max-height:120px;border-radius:8px;border:1px solid #d7e0ed;display:<?= trim((string) ($homeHeroSettings['tile_2_image'] ?? '')) !== '' ? 'block' : 'none' ?>;">
                <div id="home_tile_2_image_quality" style="display:none;font-size:12px;color:#8a3b00;font-weight:700;margin-top:4px;"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input id="home_promo_tile_2_cta_label" type="text" name="home_promo_tile_2_cta_label" value="<?= e((string) ($homeHeroSettings['tile_2_cta_label'] ?? '')) ?>" placeholder="Explore Astrophotography">
                    <input id="home_promo_tile_2_cta_url" type="text" name="home_promo_tile_2_cta_url" value="<?= e((string) ($homeHeroSettings['tile_2_cta_url'] ?? '')) ?>" placeholder="/guides">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <?php $t2Pos = site_setting_get($pdo, 'home_promo_tile_2_text_position', 'bottom-left'); ?>
                    <?php $t2Overlay = site_setting_get($pdo, 'home_promo_tile_2_overlay_strength', 'medium'); ?>
                    <?php $t2Size = site_setting_get($pdo, 'home_promo_tile_2_layout_size', 'half'); ?>
                    <select name="home_promo_tile_2_text_position"><?php foreach (['left','center','right','bottom-left','bottom-center','bottom-right'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t2Pos === $opt ? 'selected' : '' ?>><?= e('Tile2 '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_promo_tile_2_overlay_strength"><?php foreach (['none','light','medium','dark'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t2Overlay === $opt ? 'selected' : '' ?>><?= e('Overlay '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_promo_tile_2_layout_size"><?php foreach (['full','half','third'] as $opt): ?><option value="<?= e($opt) ?>" <?= $t2Size === $opt ? 'selected' : '' ?>><?= e('Size '.$opt) ?></option><?php endforeach; ?></select>
                </div>

                <h3 style="margin:14px 0 8px;">Reusable Banner 1</h3>
                <input type="text" name="home_banner_1_image" list="media-image-urls" value="<?= e(site_setting_get($pdo, 'home_banner_1_image', '')) ?>" placeholder="/assets/uploads/media/...webp">
                <input type="text" name="home_banner_1_eyebrow" value="<?= e(site_setting_get($pdo, 'home_banner_1_eyebrow', '')) ?>" placeholder="Banner eyebrow">
                <input type="text" name="home_banner_1_title" value="<?= e(site_setting_get($pdo, 'home_banner_1_title', '')) ?>" placeholder="Banner title">
                <input type="text" name="home_banner_1_subtitle" value="<?= e(site_setting_get($pdo, 'home_banner_1_subtitle', '')) ?>" placeholder="Banner subtitle">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input type="text" name="home_banner_1_cta_label" value="<?= e(site_setting_get($pdo, 'home_banner_1_cta_label', '')) ?>" placeholder="Button text">
                    <input type="text" name="home_banner_1_cta_url" value="<?= e(site_setting_get($pdo, 'home_banner_1_cta_url', '')) ?>" placeholder="/guides">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <?php $b1Pos = site_setting_get($pdo, 'home_banner_1_text_position', 'left'); ?>
                    <?php $b1Overlay = site_setting_get($pdo, 'home_banner_1_overlay_strength', 'medium'); ?>
                    <?php $b1Size = site_setting_get($pdo, 'home_banner_1_layout_size', 'full'); ?>
                    <select name="home_banner_1_text_position"><?php foreach (['left','center','right','bottom-left','bottom-center','bottom-right'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b1Pos === $opt ? 'selected' : '' ?>><?= e('Banner1 '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_banner_1_overlay_strength"><?php foreach (['none','light','medium','dark'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b1Overlay === $opt ? 'selected' : '' ?>><?= e('Overlay '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_banner_1_layout_size"><?php foreach (['full','half','third'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b1Size === $opt ? 'selected' : '' ?>><?= e('Size '.$opt) ?></option><?php endforeach; ?></select>
                </div>

                <h3 style="margin:14px 0 8px;">Reusable Banner 2</h3>
                <input type="text" name="home_banner_2_image" list="media-image-urls" value="<?= e(site_setting_get($pdo, 'home_banner_2_image', '')) ?>" placeholder="/assets/uploads/media/...webp">
                <input type="text" name="home_banner_2_eyebrow" value="<?= e(site_setting_get($pdo, 'home_banner_2_eyebrow', '')) ?>" placeholder="Banner eyebrow">
                <input type="text" name="home_banner_2_title" value="<?= e(site_setting_get($pdo, 'home_banner_2_title', '')) ?>" placeholder="Banner title">
                <input type="text" name="home_banner_2_subtitle" value="<?= e(site_setting_get($pdo, 'home_banner_2_subtitle', '')) ?>" placeholder="Banner subtitle">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input type="text" name="home_banner_2_cta_label" value="<?= e(site_setting_get($pdo, 'home_banner_2_cta_label', '')) ?>" placeholder="Button text">
                    <input type="text" name="home_banner_2_cta_url" value="<?= e(site_setting_get($pdo, 'home_banner_2_cta_url', '')) ?>" placeholder="/guides">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                    <?php $b2Pos = site_setting_get($pdo, 'home_banner_2_text_position', 'left'); ?>
                    <?php $b2Overlay = site_setting_get($pdo, 'home_banner_2_overlay_strength', 'medium'); ?>
                    <?php $b2Size = site_setting_get($pdo, 'home_banner_2_layout_size', 'full'); ?>
                    <select name="home_banner_2_text_position"><?php foreach (['left','center','right','bottom-left','bottom-center','bottom-right'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b2Pos === $opt ? 'selected' : '' ?>><?= e('Banner2 '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_banner_2_overlay_strength"><?php foreach (['none','light','medium','dark'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b2Overlay === $opt ? 'selected' : '' ?>><?= e('Overlay '.$opt) ?></option><?php endforeach; ?></select>
                    <select name="home_banner_2_layout_size"><?php foreach (['full','half','third'] as $opt): ?><option value="<?= e($opt) ?>" <?= $b2Size === $opt ? 'selected' : '' ?>><?= e('Size '.$opt) ?></option><?php endforeach; ?></select>
                </div>

                <h3 style="margin:14px 0 8px;">Shop by Goal (Admin)</h3>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <input type="text" name="home_goal_1_label" value="<?= e(site_setting_get($pdo, 'home_goal_1_label', 'First Telescope')) ?>" placeholder="Goal 1 label">
                    <input type="text" name="home_goal_1_url" value="<?= e(site_setting_get($pdo, 'home_goal_1_url', '/best-beginner-telescopes')) ?>" placeholder="Goal 1 URL">
                    <input type="text" name="home_goal_2_label" value="<?= e(site_setting_get($pdo, 'home_goal_2_label', 'Budget Under $500')) ?>" placeholder="Goal 2 label">
                    <input type="text" name="home_goal_2_url" value="<?= e(site_setting_get($pdo, 'home_goal_2_url', '/best-telescopes-under-500')) ?>" placeholder="Goal 2 URL">
                    <input type="text" name="home_goal_3_label" value="<?= e(site_setting_get($pdo, 'home_goal_3_label', 'Upgrade Accessories')) ?>" placeholder="Goal 3 label">
                    <input type="text" name="home_goal_3_url" value="<?= e(site_setting_get($pdo, 'home_goal_3_url', '/best-telescope-accessories')) ?>" placeholder="Goal 3 URL">
                    <input type="text" name="home_goal_4_label" value="<?= e(site_setting_get($pdo, 'home_goal_4_label', 'Astrophotography Path')) ?>" placeholder="Goal 4 label">
                    <input type="text" name="home_goal_4_url" value="<?= e(site_setting_get($pdo, 'home_goal_4_url', '/guides')) ?>" placeholder="Goal 4 URL">
                </div>

                <label>Most Loved Product IDs (comma-separated, max 4)</label>
                <input id="home_featured_product_ids" type="text" name="home_featured_product_ids" value="<?= e((string) ($homeHeroSettings['featured_ids'] ?? '')) ?>" placeholder="123,456,789,101">
                <label>Featured Product Picker (max 4)</label>
                <select id="home_featured_picker" multiple size="8" style="width:100%;">
                    <?php foreach ($mediaFeaturedProductOptions as $productOption): ?>
                        <?php
                        $pid = (string) (int) ($productOption['id'] ?? 0);
                        $ptitle = trim((string) ($productOption['title'] ?? ''));
                        ?>
                        <option value="<?= e($pid) ?>" <?= in_array($pid, $publishedFeaturedIdsArray, true) ? 'selected' : '' ?>>#<?= e($pid) ?> - <?= e($ptitle) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="muted" style="font-size:12px;margin-top:4px;">Tip: hold Ctrl/Cmd for multi-select.</div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;">
                    <select id="preset_select" style="min-width:220px;">
                        <option value="">Select preset</option>
                    </select>
                    <button class="btn" type="button" id="preset_refresh_btn">Refresh Presets</button>
                    <button class="btn" type="button" id="preset_save_btn">Save Preset</button>
                    <button class="btn" type="button" id="preset_load_btn">Load Preset</button>
                    <button class="btn" type="button" id="preset_delete_btn">Delete Preset</button>
                </div>
                <div class="sticky-save-bar" style="position:sticky;bottom:8px;z-index:20;margin-top:12px;padding:10px;border:1px solid #d7e0ed;border-radius:10px;background:#fff;box-shadow:0 8px 16px rgba(10,20,34,.08);display:flex;gap:8px;flex-wrap:wrap;">
                    <button class="btn" type="submit" id="save_draft_btn_sticky">Save Draft</button>
                    <button class="btn" type="submit" id="publish_btn_sticky">Publish To Home</button>
                </div>
            </form>
            <form method="post" style="margin-top:8px;">
                <input type="hidden" name="action" value="home_publish_draft">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Publish Draft to Live (One Click)</button>
            </form>

            <div style="margin-top:14px;padding:12px;border:1px solid #d7e0ed;border-radius:10px;background:#f8fbff;">
                <h3 style="margin:0 0 8px;">Image Prompt Shortcuts</h3>
                <p class="muted" style="margin:0 0 10px;">Copy, generate image, upload here, then click Use in Hero/Tile buttons in Media Library.</p>
                <label>Prompt Variant</label>
                <select id="prompt_variant">
                    <option value="cinematic">Cinematic</option>
                    <option value="product">Product-focused</option>
                    <option value="lifestyle">Lifestyle</option>
                    <option value="deepsky">Deep-sky</option>
                </select>

                <label>Hero Image Prompt</label>
                <textarea id="prompt_home_hero" rows="4" readonly>Ultra-detailed astrophotography-style hero background for a beginner telescope website, cinematic Milky Way over mountains, a modern telescope in foreground, high contrast, dark blue and orange accents, premium ecommerce look, no logos, no text, 16:9 composition, realistic lighting, web-ready.</textarea>
                <button class="btn" type="button" style="margin-top:6px;" data-copy-target="prompt_home_hero" data-copy-status="prompt_home_hero_status">Copy Hero Prompt</button>
                <div id="prompt_home_hero_status" class="copy-status" style="display:block;margin-top:4px;"></div>

                <label style="margin-top:12px;">Promo Tile 1 Prompt</label>
                <textarea id="prompt_tile_1" rows="4" readonly>High-quality lifestyle astronomy image, person setting up a beginner telescope outdoors at dusk, warm natural light, actionable beginner vibe, shallow depth of field, premium brand visual style, no logos, no text, 16:9 crop-safe for tile card.</textarea>
                <button class="btn" type="button" style="margin-top:6px;" data-copy-target="prompt_tile_1" data-copy-status="prompt_tile_1_status">Copy Tile 1 Prompt</button>
                <div id="prompt_tile_1_status" class="copy-status" style="display:block;margin-top:4px;"></div>

                <label style="margin-top:12px;">Promo Tile 2 Prompt</label>
                <textarea id="prompt_tile_2" rows="4" readonly>Stunning deep-space nebula scene with rich detail, vivid but natural colors, astrophotography inspiration mood, clean composition with dark areas for text overlay, no logos, no text, premium ecommerce campaign aesthetic, 16:9 crop-safe.</textarea>
                <button class="btn" type="button" style="margin-top:6px;" data-copy-target="prompt_tile_2" data-copy-status="prompt_tile_2_status">Copy Tile 2 Prompt</button>
                <div id="prompt_tile_2_status" class="copy-status" style="display:block;margin-top:4px;"></div>
            </div>

            <div style="margin-top:14px;padding:12px;border:1px solid #d7e0ed;border-radius:10px;background:#f8fbff;">
                <h3 style="margin:0 0 8px;">Quick Upload for Home Visuals</h3>
                <p class="muted" style="margin:0 0 10px;">Upload from here directly. JPG/PNG/GIF will auto-convert to WEBP and appear in Media Library.</p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="media_upload">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="quick_assign_autosave" value="1">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div>
                            <label>Title (optional)</label>
                            <input type="text" name="media_title" placeholder="e.g. Home hero visual">
                        </div>
                        <div>
                            <label>Alt Text (optional)</label>
                            <input type="text" name="media_alt_text" placeholder="Accessible description">
                        </div>
                    </div>
                    <label>Notes (optional)</label>
                    <textarea name="media_notes" rows="2" placeholder="Hero / Tile 1 / Tile 2"></textarea>
                    <label>Assign After Upload</label>
                    <select name="quick_assign_target">
                        <option value="">None</option>
                        <option value="hero">Hero</option>
                        <option value="tile1">Tile 1</option>
                        <option value="tile2">Tile 2</option>
                    </select>
                    <label>File</label>
                    <input type="file" name="media_file" required style="padding:6px;">
                    <button class="btn" type="submit" <?= !$mediaTableReady ? 'disabled' : '' ?>>Upload to Media Library</button>
                </form>
            </div>

            <?php if ($mediaImageOptions !== []): ?>
                <datalist id="media-image-urls">
                    <?php foreach ($mediaImageOptions as $imageOption): ?>
                        <?php
                        $imageUrl = trim((string) ($imageOption['file_url'] ?? ''));
                        $imageLabel = trim((string) ($imageOption['title'] ?? ''));
                        if ($imageLabel === '') {
                            $imageLabel = trim((string) ($imageOption['original_name'] ?? ''));
                        }
                        if ($imageUrl === '') {
                            continue;
                        }
                        ?>
                        <option value="<?= e($imageUrl) ?>" label="<?= e($imageLabel) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>
        </section>

        <section id="media-upload" class="box ops-anchor-offset">
            <h2>Upload Media</h2>
            <?php if (!$mediaTableReady): ?>
                <div class="error">Media table not found yet. It is created automatically on first upload attempt.</div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="media_upload">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Title (optional)</label>
                        <input type="text" name="media_title" placeholder="e.g. Celestron NexStar hero image">
                    </div>
                    <div>
                        <label>Alt Text (optional)</label>
                        <input type="text" name="media_alt_text" placeholder="Accessible description">
                    </div>
                </div>
                <label>Notes (optional)</label>
                <textarea name="media_notes" rows="2" placeholder="Usage notes, source, etc."></textarea>
                <label>File</label>
                <input type="file" name="media_file" required style="padding:6px;">
                <button class="btn" type="submit" <?= !$mediaTableReady ? 'disabled' : '' ?>>Upload to Media Library</button>
            </form>
            <p class="muted" style="margin:10px 0 0;">Allowed: JPG, PNG, WEBP, GIF, SVG, MP4, WEBM, MOV, PDF, TXT, ZIP (max 25MB). JPG/PNG/GIF uploads are auto-converted to WEBP.</p>
        </section>

        <section id="media-list" class="box ops-anchor-offset">
            <h2>Media Assets</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin:0 0 10px;">
                <button class="btn" type="button" data-media-filter="all">All</button>
                <button class="btn" type="button" data-media-filter="recent">Recent</button>
                <button class="btn" type="button" data-media-filter="webp">Only WEBP</button>
                <button class="btn" type="button" data-media-filter="landscape">Landscape 16:9+</button>
                <button class="btn" type="button" data-media-filter="used">Used In Home</button>
            </div>
            <?php if (!$mediaTableReady): ?>
                <div class="empty">Media table not ready yet. Upload one file to initialize it automatically.</div>
            <?php elseif ($allMedia === []): ?>
                <div class="empty">No media assets yet.</div>
            <?php else: ?>
                <table>
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>File</th>
                        <th>URL</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allMedia as $media): ?>
                        <?php
                        $mediaId = (int) ($media['id'] ?? 0);
                        $mediaType = trim((string) ($media['media_type'] ?? 'document'));
                        $mediaUrl = trim((string) ($media['file_url'] ?? ''));
                        $title = trim((string) ($media['title'] ?? ''));
                        $originalName = trim((string) ($media['original_name'] ?? ''));
                        $mimeType = trim((string) ($media['mime_type'] ?? ''));
                        $sizeBytes = (int) ($media['file_size'] ?? 0);
                        $copyStatusId = 'media_copy_status_' . $mediaId;
                        $createdTs = strtotime((string) ($media['created_at'] ?? ''));
                        $isRecentMedia = $createdTs !== false && (time() - $createdTs) <= 7 * 86400;
                        $isWebp = strtolower((string) ($media['file_ext'] ?? '')) === 'webp' || strtolower($mimeType) === 'image/webp';
                        $isLandscape = false;
                        if ($mediaType === 'image') {
                            $storedName = trim((string) ($media['stored_name'] ?? ''));
                            if ($storedName !== '' && preg_match('/^[A-Za-z0-9_.-]+$/', $storedName) === 1) {
                                $localImagePath = __DIR__ . '/../assets/uploads/media/' . $storedName;
                                if (is_file($localImagePath)) {
                                    $size = @getimagesize($localImagePath);
                                    if (is_array($size) && isset($size[0], $size[1]) && (int) $size[0] >= (int) $size[1]) {
                                        $isLandscape = true;
                                    }
                                }
                            }
                        }
                        $isUsedInHome = isset($homeUsedImageUrls[$mediaUrl]);
                        ?>
                        <tr data-media-row="1" data-recent="<?= $isRecentMedia ? '1' : '0' ?>" data-webp="<?= $isWebp ? '1' : '0' ?>" data-landscape="<?= $isLandscape ? '1' : '0' ?>" data-used="<?= $isUsedInHome ? '1' : '0' ?>">
                            <td><?= $mediaId ?></td>
                            <td style="width:100px;">
                                <?php if ($mediaType === 'image' && $mediaUrl !== ''): ?>
                                    <img src="<?= e($mediaUrl) ?>" alt="<?= e($title !== '' ? $title : $originalName) ?>" style="display:block;width:84px;height:64px;object-fit:contain;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;" loading="lazy">
                                <?php elseif ($mediaType === 'video'): ?>
                                    <div style="display:flex;align-items:center;justify-content:center;width:84px;height:64px;border-radius:8px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;font-size:11px;font-weight:700;">VIDEO</div>
                                <?php else: ?>
                                    <div style="display:flex;align-items:center;justify-content:center;width:84px;height:64px;border-radius:8px;border:1px solid #e2e8f0;background:#f1f5f9;color:#334155;font-size:11px;font-weight:700;">FILE</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><strong><?= e($title !== '' ? $title : '(untitled)') ?></strong></div>
                                <div class="muted" style="font-size:12px;"><?= e($media['created_at'] ?? '') ?></div>
                                <?php if ($isUsedInHome): ?>
                                    <div style="font-size:11px;font-weight:700;color:#134b8a;">Used in Home</div>
                                <?php endif; ?>
                            </td>
                            <td><?= e(strtoupper($mediaType)) ?></td>
                            <td>
                                <div><?= e($originalName) ?></div>
                                <div class="muted" style="font-size:12px;"><?= e($mimeType) ?> | <?= number_format($sizeBytes / 1024, 1) ?> KB</div>
                            </td>
                            <td style="max-width:280px;">
                                <a href="<?= e($mediaUrl) ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;word-break:break-all;"><?= e($mediaUrl) ?></a>
                            </td>
                            <td>
                                <button
                                    class="btn"
                                    type="button"
                                    style="padding:6px 10px;font-size:12px;margin-right:6px;"
                                    data-copy-text="<?= e($mediaUrl) ?>"
                                    data-copy-status="<?= e($copyStatusId) ?>"
                                >Copy URL</button>
                                <?php if ($mediaType === 'image' && $mediaUrl !== ''): ?>
                                    <button class="btn" type="button" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;" data-media-assign="hero" data-media-url="<?= e($mediaUrl) ?>" data-media-title="<?= e($title !== '' ? $title : $originalName) ?>">Use in Hero</button>
                                    <button class="btn" type="button" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;" data-media-assign="tile1" data-media-url="<?= e($mediaUrl) ?>" data-media-title="<?= e($title !== '' ? $title : $originalName) ?>">Use in Tile 1</button>
                                    <button class="btn" type="button" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;" data-media-assign="tile2" data-media-url="<?= e($mediaUrl) ?>" data-media-title="<?= e($title !== '' ? $title : $originalName) ?>">Use in Tile 2</button>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="home_media_assign">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="assign_target" value="hero">
                                        <input type="hidden" name="assign_url" value="<?= e($mediaUrl) ?>">
                                        <input type="hidden" name="assign_title" value="<?= e($title !== '' ? $title : $originalName) ?>">
                                        <input type="hidden" name="assign_mode" value="publish">
                                        <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;">Set Hero + Save</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="home_media_assign">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="assign_target" value="tile1">
                                        <input type="hidden" name="assign_url" value="<?= e($mediaUrl) ?>">
                                        <input type="hidden" name="assign_title" value="<?= e($title !== '' ? $title : $originalName) ?>">
                                        <input type="hidden" name="assign_mode" value="publish">
                                        <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;">Set Tile 1 + Save</button>
                                    </form>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="home_media_assign">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="assign_target" value="tile2">
                                        <input type="hidden" name="assign_url" value="<?= e($mediaUrl) ?>">
                                        <input type="hidden" name="assign_title" value="<?= e($title !== '' ? $title : $originalName) ?>">
                                        <input type="hidden" name="assign_mode" value="publish">
                                        <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;margin:6px 6px 0 0;">Set Tile 2 + Save</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this media asset?');">
                                    <input type="hidden" name="action" value="media_delete">
                                    <input type="hidden" name="id" value="<?= $mediaId ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:12px;">Delete</button>
                                </form>
                                <div id="<?= e($copyStatusId) ?>" class="copy-status" style="display:block;margin-top:4px;"></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?= $mediaPagination ?>
            <?php endif; ?>
        </section>
        <?php elseif ($activeTab === 'posts'): ?>
        <section class="box">
            <h2>Posts Workspace</h2>
            <p class="muted" style="margin:0 0 10px;">Draft faster with SEO helpers, filter content state, and keep editorial flow focused.</p>
            <div class="ops-kpis">
                <div class="ops-kpi"><div class="k">Visible Rows</div><div class="v"><?= number_format(count($allPosts)) ?></div></div>
                <div class="ops-kpi"><div class="k">Total Posts</div><div class="v"><?= number_format($postsTotal) ?></div></div>
                <div class="ops-kpi"><div class="k">Drafts</div><div class="v"><?= number_format($postsDraftCount) ?></div></div>
                <div class="ops-kpi"><div class="k">Published</div><div class="v"><?= number_format($postsPublishedCount) ?></div></div>
            </div>
            <div class="ops-nav">
                <a class="ops-link" href="#posts-add">Add Post</a>
                <a class="ops-link" href="#posts-list">Post List</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=indexation')) ?>">Indexation Tracker</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Go to Prompts Workspace</a>
            </div>
        </section>
        <?php if ($editingPost): ?>
        <section id="posts-edit" class="box ops-anchor-offset">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h2 style="margin:0;">Edit Post: <?= e($editingPost['title']) ?></h2>
                <div style="display:flex;align-items:center;gap:12px;">
                    <?php $editingPostPublicPath = enma_post_public_path((array) $editingPost); ?>
                    <?php if ($editingPostPublicPath !== ''): ?>
                        <a href="<?= e(url($editingPostPublicPath)) ?>" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#0b1f3a;text-decoration:none;font-weight:700;">View Live</a>
                    <?php endif; ?>
                    <a href="<?= e(url('/enma/?tab=posts')) ?>" style="font-size:13px;">&larr; Cancel Edit</a>
                </div>
            </div>
                <?php $editDraftKey = 'post-' . (int) ($editingPost['id'] ?? 0); ?>
	            <form method="post" enctype="multipart/form-data" class="post-editor-form" data-autosave-enabled="<?= $postAutosaveEnabled ? '1' : '0' ?>">
	                <input type="hidden" name="action" value="update_post">
	                <input type="hidden" name="id" value="<?= (int)$editingPost['id'] ?>">
                    <input type="hidden" name="post_id" value="<?= (int)$editingPost['id'] ?>">
                    <input type="hidden" name="draft_key" value="<?= e($editDraftKey) ?>">
	                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required value="<?= e($editingPost['title']) ?>">
                    </div>
                    <div>
                        <label>Post Type</label>
                        <select name="post_type">
                            <option value="post" <?= $editingPost['post_type'] === 'post' ? 'selected' : '' ?>>Standard Post</option>
                            <option value="guide" <?= $editingPost['post_type'] === 'guide' ? 'selected' : '' ?>>Guide (Structured)</option>
                        </select>
                    </div>
	                </div>
	                <label>Excerpt (Short summary)</label>
	                <textarea name="excerpt" rows="2" required><?= e($editingPost['excerpt'] ?? '') ?></textarea>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Meta Title (Google title)</label>
	                        <input type="text" name="meta_title" value="<?= e((string) ($editingPost['meta_title'] ?? '')) ?>" placeholder="Optional SEO title">
	                    </div>
	                    <div>
	                        <label>Meta Description (Google description)</label>
	                        <textarea name="meta_description" rows="2" placeholder="Optional SEO description"><?= e((string) ($editingPost['meta_description'] ?? '')) ?></textarea>
	                    </div>
	                </div>
	                <label>Content (HTML allowed)</label>
	                <textarea id="edit_post_content_html" name="content_html" rows="16" style="font-family: Consolas, 'Courier New', monospace; line-height: 1.45;"><?= e($editingPost['content_html'] ?? '') ?></textarea>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Featured Image URL</label>
                        <input type="text" name="featured_image" value="<?= e($editingPost['featured_image'] ?? '') ?>">
                    </div>
                    <div>
                        <label>Upload New Image (Replaces current)</label>
                        <input type="file" name="featured_image_file" accept="image/*" style="padding: 6px;">
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;margin:8px 0 12px;">
                    <button
                        class="btn"
                        type="button"
                        data-copy-post-image-brief="1"
                        data-copy-status="edit_post_image_brief_status"
                    >Create Horizontal Image Brief From This Post</button>
                    <span id="edit_post_image_brief_status" class="copy-status"></span>
                </div>
	                <?php if (!empty($editingPost['featured_image'])): ?>
	                    <div style="margin-bottom:12px;">
	                        <span class="muted">Current Image:</span><br>
	                        <img src="<?= e(url($editingPost['featured_image'])) ?>" alt="Preview" style="max-width:100px;max-height:100px;border-radius:6px;margin-top:5px;border:1px solid #ddd;">
	                    </div>
	                <?php endif; ?>
	                <div class="post-preview-grid" style="margin:8px 0 14px;">
	                    <div class="post-preview-card">
	                        <h3 style="margin:0 0 10px;">Google Preview</h3>
	                        <div class="serp-preview-url" data-preview="serp-url"><?= e(absolute_url(enma_post_public_path($editingPost ?: ['slug' => 'preview-post', 'post_type' => 'post']) ?: '/blog/preview-post')) ?></div>
	                        <div class="serp-preview-title" data-preview="serp-title"><?= e((string) (($editingPost['meta_title'] ?? '') !== '' ? $editingPost['meta_title'] : $editingPost['title'])) ?></div>
	                        <p class="serp-preview-desc" data-preview="serp-description"><?= e((string) (($editingPost['meta_description'] ?? '') !== '' ? $editingPost['meta_description'] : ($editingPost['excerpt'] ?? ''))) ?></p>
	                    </div>
	                    <div class="post-render-preview">
	                        <div class="hero-preview">
	                            <span class="preview-kicker">Post Preview</span>
	                            <h3 data-preview="hero-title"><?= e($editingPost['title']) ?></h3>
	                            <p class="preview-muted" data-preview="hero-copy"><?= e((string) ($editingPost['excerpt'] ?? '')) ?></p>
	                            <div class="preview-hero-media">
	                                <img data-preview="hero-image" src="<?= e((string) ($editingPost['featured_image'] ?? '')) ?>" alt="" <?= empty($editingPost['featured_image']) ? 'style="display:none;"' : '' ?>>
	                            </div>
	                        </div>
	                        <div class="preview-article-body">
	                            <h4 data-preview="article-title"><?= e($editingPost['title']) ?></h4>
	                            <p class="preview-muted" data-preview="article-copy"><?= e((string) ($editingPost['excerpt'] ?? '')) ?></p>
	                            <p data-preview="article-body"><?= e(trim(strip_tags((string) ($editingPost['content_html'] ?? ''))) !== '' ? trim(preg_replace('/\s+/', ' ', strip_tags((string) ($editingPost['content_html'] ?? '')))) : 'Article body preview') ?></p>
	                        </div>
	                    </div>
	                </div>
                    <div class="editor-tools">
                        <div class="field">
                            <label>Insert Content Block</label>
                            <select data-editor-blocks>
                                <option value="review_intro">Review Intro</option>
                                <option value="pros_cons">Pros and Cons</option>
                                <option value="faq">FAQ Section</option>
                                <option value="cta">Final CTA</option>
                            </select>
                        </div>
                        <div>
                            <button class="btn" data-insert-block type="button">Insert Block</button>
                        </div>
                    </div>
                    <section class="seo-panel">
                        <h3>SEO Assistant</h3>
                        <div class="seo-score" data-seo="score">0/100</div>
                        <div class="seo-metrics">
                            <div class="seo-metric">Title: <strong data-seo="title-len">0</strong></div>
                            <div class="seo-metric">Meta title: <strong data-seo="meta-title-len">0</strong></div>
                            <div class="seo-metric">Meta desc: <strong data-seo="meta-desc-len">0</strong></div>
                            <div class="seo-metric">H2 tags: <strong data-seo="h2-count">0</strong></div>
                            <div class="seo-metric">Words: <strong data-seo="word-count">0</strong></div>
                            <div class="seo-metric">Internal links: <strong data-seo="internal-links">0</strong></div>
                        </div>
                        <ul class="seo-checklist">
                            <li data-seo-check="title"><span>Title length 40-65 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="meta-title"><span>Meta title 45-65 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="meta-desc"><span>Meta description 120-160 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="h2"><span>At least 2 H2 headings</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="words"><span>At least 600 words</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="links"><span>At least 2 internal links</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="image"><span>Featured image defined</span><strong data-seo-check-status>Needs work</strong></li>
                        </ul>
                        <p class="muted" data-autosave-status style="margin:10px 0 0;">Autosave idle</p>
                    </section>
	                <button class="btn" type="submit">Update Post</button>
	            </form>
	        </section>
        <?php endif; ?>

        <section id="posts-add" class="box ops-anchor-offset">
            <h2>Add New Post</h2>
                <?php $newDraftKey = 'new-' . substr(hash('sha256', (string) session_id() . '|add-post'), 0, 24); ?>
	            <form method="post" enctype="multipart/form-data" class="post-editor-form" data-autosave-enabled="<?= $postAutosaveEnabled ? '1' : '0' ?>">
	                <input type="hidden" name="action" value="add_post">
                    <input type="hidden" name="post_id" value="0">
                    <input type="hidden" name="draft_key" value="<?= e($newDraftKey) ?>">
	                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Title</label>
                        <input type="text" name="title" required placeholder="e.g. How to Clean Your Eyepieces">
                    </div>
                    <div>
                        <label>Post Type</label>
                        <select name="post_type">
                            <option value="post">Standard Post</option>
                            <option value="guide">Guide (Structured)</option>
                        </select>
                    </div>
	                </div>
	                <label>Excerpt (Short summary)</label>
	                <textarea name="excerpt" rows="2" required placeholder="A brief summary for listings..."></textarea>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Meta Title (Google title)</label>
	                        <input type="text" name="meta_title" placeholder="Optional SEO title">
	                    </div>
	                    <div>
	                        <label>Meta Description (Google description)</label>
	                        <textarea name="meta_description" rows="2" placeholder="Optional SEO description"></textarea>
	                    </div>
	                </div>
	                <label>Content (HTML allowed)</label>
	                <textarea id="add_post_content_html" name="content_html" rows="10" placeholder="<p>Full article content here...</p>"></textarea>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
                    <div>
                        <label>Featured Image URL (Optional)</label>
                        <input type="text" name="featured_image" placeholder="https://...">
                    </div>
                    <div>
                        <label>Upload Image (Optional)</label>
                        <input type="file" name="featured_image_file" accept="image/*" style="padding: 6px;">
	                    </div>
	                </div>
                <div style="display:flex;gap:8px;align-items:center;margin:8px 0 12px;">
                    <button
                        class="btn"
                        type="button"
                        data-copy-post-image-brief="1"
                        data-copy-status="add_post_image_brief_status"
                    >Create Horizontal Image Brief From This Post</button>
                    <span id="add_post_image_brief_status" class="copy-status"></span>
                </div>
	                <div class="post-preview-grid" style="margin:8px 0 14px;">
	                    <div class="post-preview-card">
	                        <h3 style="margin:0 0 10px;">Google Preview</h3>
	                        <div class="serp-preview-url" data-preview="serp-url"><?= e(absolute_url('/blog/preview-post')) ?></div>
	                        <div class="serp-preview-title" data-preview="serp-title">Post title preview</div>
	                        <p class="serp-preview-desc" data-preview="serp-description">Meta description preview</p>
	                    </div>
	                    <div class="post-render-preview">
	                        <div class="hero-preview">
	                            <span class="preview-kicker">Post Preview</span>
	                            <h3 data-preview="hero-title">Post title preview</h3>
	                            <p class="preview-muted" data-preview="hero-copy">Post excerpt preview</p>
	                            <div class="preview-hero-media">
	                                <img data-preview="hero-image" src="" alt="" style="display:none;">
	                            </div>
	                        </div>
	                        <div class="preview-article-body">
	                            <h4 data-preview="article-title">Post title preview</h4>
	                            <p class="preview-muted" data-preview="article-copy">Post excerpt preview</p>
	                            <p data-preview="article-body">Article body preview</p>
	                        </div>
	                    </div>
	                </div>
                    <div class="editor-tools">
                        <div class="field">
                            <label>Insert Content Block</label>
                            <select data-editor-blocks>
                                <option value="review_intro">Review Intro</option>
                                <option value="pros_cons">Pros and Cons</option>
                                <option value="faq">FAQ Section</option>
                                <option value="cta">Final CTA</option>
                            </select>
                        </div>
                        <div>
                            <button class="btn" data-insert-block type="button">Insert Block</button>
                        </div>
                    </div>
                    <section class="seo-panel">
                        <h3>SEO Assistant</h3>
                        <div class="seo-score" data-seo="score">0/100</div>
                        <div class="seo-metrics">
                            <div class="seo-metric">Title: <strong data-seo="title-len">0</strong></div>
                            <div class="seo-metric">Meta title: <strong data-seo="meta-title-len">0</strong></div>
                            <div class="seo-metric">Meta desc: <strong data-seo="meta-desc-len">0</strong></div>
                            <div class="seo-metric">H2 tags: <strong data-seo="h2-count">0</strong></div>
                            <div class="seo-metric">Words: <strong data-seo="word-count">0</strong></div>
                            <div class="seo-metric">Internal links: <strong data-seo="internal-links">0</strong></div>
                        </div>
                        <ul class="seo-checklist">
                            <li data-seo-check="title"><span>Title length 40-65 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="meta-title"><span>Meta title 45-65 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="meta-desc"><span>Meta description 120-160 chars</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="h2"><span>At least 2 H2 headings</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="words"><span>At least 600 words</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="links"><span>At least 2 internal links</span><strong data-seo-check-status>Needs work</strong></li>
                            <li data-seo-check="image"><span>Featured image defined</span><strong data-seo-check-status>Needs work</strong></li>
                        </ul>
                        <p class="muted" data-autosave-status style="margin:10px 0 0;">Autosave idle</p>
                    </section>
	                <button class="btn" type="submit">Publish Post</button>
	            </form>
	        </section>

        <section id="posts-list" class="box ops-anchor-offset">
            <div class="copy-toolbar">
                <h2>Existing Posts</h2>
                <div class="copy-actions">
                    <button class="btn btn-copy" type="button" data-copy-target="posts_copy_source" data-copy-status="posts_copy_status">Copy Post List</button>
                    <span id="posts_copy_status" class="copy-status"></span>
                </div>
            </div>
            <form method="get" class="toolbar" style="margin-bottom:8px;">
                <input type="hidden" name="tab" value="posts">
                <input type="hidden" name="posts_page" value="1">
                <div class="field" style="max-width:220px;">
                    <label>Status Filter</label>
                    <select name="posts_status">
                        <option value="all" <?= $postsStatusFilter === 'all' ? 'selected' : '' ?>>All statuses</option>
                        <option value="draft" <?= $postsStatusFilter === 'draft' ? 'selected' : '' ?>>Draft only</option>
                        <option value="published" <?= $postsStatusFilter === 'published' ? 'selected' : '' ?>>Published only</option>
                    </select>
                </div>
                <button class="btn" type="submit">Apply</button>
            </form>
            <?php if ($allPosts === []): ?>
                <div class="empty">No posts or guides found in database.</div>
            <?php else: ?>
                <textarea id="posts_copy_source" class="copy-source" readonly><?= e($postsCopyText) ?></textarea>
                <p class="muted">Showing <?= number_format(count($allPosts)) ?> of <?= number_format($postsTotal) ?> posts<?= $postsStatusFilter !== 'all' ? ' (' . e($postsStatusFilter) . ')' : '' ?>. Drafts are prioritized at the top.</p>
                <table>
                    <thead>
                    <tr>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allPosts as $p): ?>
                        <tr>
                            <td>
                                <strong><?= e($p['title']) ?></strong><br>
                                <span class="muted"><?= e($p['slug']) ?></span>
                            </td>
                            <td><span class="badge" style="background:#eef2f7;padding:2px 6px;border-radius:4px;font-size:11px;"><?= e(strtoupper($p['post_type'])) ?></span></td>
                            <td><?= e($p['status']) ?></td>
                            <td><?= e(substr((string)$p['published_at'], 0, 10)) ?></td>
                            <td>
                                <?php $postPublicPath = enma_post_public_path((array) $p); ?>
                                <?php if ($postPublicPath !== ''): ?>
                                    <a href="<?= e(url($postPublicPath)) ?>" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">View</a>
                                <?php endif; ?>
                                <a href="<?= e(url('/enma/?tab=posts&edit_post=' . $p['id'])) ?>" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this post?');">
                                    <input type="hidden" name="action" value="delete_post">
                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:13px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                  </table>
                  <?= $postsPagination ?>
              <?php endif; ?>
  	        </section>
            <?php elseif ($activeTab === 'indexation'): ?>
            <?php require __DIR__ . '/views/tabs/indexation.php'; ?>
            <?php elseif ($activeTab === 'prompts'): ?>
            <?php require __DIR__ . '/views/tabs/prompts.php'; ?>
	        <?php elseif ($activeTab === 'users'): ?>
            <section class="box">
                <h2>Users Workspace</h2>
                <p class="muted" style="margin:0 0 10px;">Manage access, roles, and user status with quick filtering and activity visibility.</p>
                <div class="ops-kpis">
                    <div class="ops-kpi"><div class="k">Visible Rows</div><div class="v"><?= number_format(count($allUsers)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Total Users</div><div class="v"><?= number_format($usersTotal) ?></div></div>
                    <div class="ops-kpi"><div class="k">Active</div><div class="v"><?= number_format($usersActiveCount) ?></div></div>
                    <div class="ops-kpi"><div class="k">Inactive</div><div class="v"><?= number_format($usersInactiveCount) ?></div></div>
                </div>
                <div class="ops-nav">
                    <a class="ops-link" href="#users-add">Add User</a>
                    <a class="ops-link" href="#users-list">User List</a>
                    <a class="ops-link" href="#users-activity">Activity Log</a>
                </div>
            </section>
	        <?php if ($editingUser): ?>
	        <section id="users-edit" class="box ops-anchor-offset">
	            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
	                <h2 style="margin:0;">Edit User: <?= e($editingUser['username']) ?></h2>
	                <a href="<?= e(url('/enma/?tab=users')) ?>" style="font-size:13px;">&larr; Cancel Edit</a>
	            </div>
	            <form method="post">
	                <input type="hidden" name="action" value="update_user">
	                <input type="hidden" name="id" value="<?= (int) $editingUser['id'] ?>">
	                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Email</label>
	                        <input type="email" name="email" required value="<?= e((string) ($editingUser['email'] ?? '')) ?>">
	                    </div>
	                    <div>
	                        <label>Username</label>
	                        <input type="text" name="username" required value="<?= e((string) ($editingUser['username'] ?? '')) ?>">
	                    </div>
	                </div>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Display Name</label>
	                        <input type="text" name="display_name" value="<?= e((string) ($editingUser['display_name'] ?? '')) ?>">
	                    </div>
	                    <div>
	                        <label>New Password</label>
	                        <input type="password" name="password" placeholder="Leave blank to keep current password">
	                    </div>
	                </div>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Role</label>
	                        <select name="role">
	                            <option value="admin" <?= ($editingUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
	                            <option value="user" <?= ($editingUser['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
	                        </select>
	                    </div>
	                    <div>
	                        <label>Status</label>
	                        <select name="status">
	                            <option value="active" <?= ($editingUser['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
	                            <option value="inactive" <?= ($editingUser['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
	                        </select>
	                    </div>
	                </div>
	                <button class="btn" type="submit">Update User</button>
	            </form>
	        </section>
	        <?php endif; ?>

	        <section id="users-add" class="box ops-anchor-offset">
	            <h2>Add User</h2>
	            <form method="post">
	                <input type="hidden" name="action" value="add_user">
	                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Email</label>
	                        <input type="email" name="email" required>
	                    </div>
	                    <div>
	                        <label>Username</label>
	                        <input type="text" name="username" required>
	                    </div>
	                </div>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Display Name</label>
	                        <input type="text" name="display_name">
	                    </div>
	                    <div>
	                        <label>Password</label>
	                        <input type="password" name="password" required minlength="8">
	                    </div>
	                </div>
	                <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;">
	                    <div>
	                        <label>Role</label>
	                        <select name="role">
	                            <option value="admin">Admin</option>
	                            <option value="user">User</option>
	                        </select>
	                    </div>
	                    <div>
	                        <label>Status</label>
	                        <select name="status">
	                            <option value="active">Active</option>
	                            <option value="inactive">Inactive</option>
	                        </select>
	                    </div>
	                </div>
	                <button class="btn" type="submit">Create User</button>
	            </form>
	        </section>

	        <section id="users-list" class="box ops-anchor-offset">
 	            <h2>Users</h2>
  	            <form method="get" class="toolbar">
  	                <input type="hidden" name="tab" value="users">
                    <input type="hidden" name="users_page" value="1">
  	                <div class="field">
  	                    <label>Search</label>
  	                    <input type="text" name="user_q" value="<?= e($userSearch) ?>" placeholder="email, username, display name">
  	                </div>
  	                <button class="btn" type="submit">Filter</button>
  	            </form>
  	            <?php if ($allUsers === []): ?>
  	                <div class="empty">No users found for this filter.</div>
  	            <?php else: ?>
                    <p class="muted">Showing <?= number_format(count($allUsers)) ?> of <?= number_format($usersTotal) ?> users.</p>
  	                <table>
	                    <thead>
	                        <tr>
	                            <th>ID</th>
	                            <th>Identity</th>
	                            <th>Role</th>
	                            <th>Status</th>
	                            <th>Last Login</th>
	                            <th>Actions</th>
	                        </tr>
	                    </thead>
	                    <tbody>
	                    <?php foreach ($allUsers as $userRow): ?>
	                        <tr>
	                            <td><?= (int) $userRow['id'] ?></td>
	                            <td>
	                                <strong><?= e((string) ($userRow['display_name'] ?: $userRow['username'])) ?></strong><br>
	                                <span class="muted"><?= e((string) $userRow['username']) ?> · <?= e((string) $userRow['email']) ?></span>
	                            </td>
	                            <td><?= e((string) $userRow['role']) ?></td>
	                            <td><?= e((string) $userRow['status']) ?></td>
	                            <td><?= e((string) ($userRow['last_login_at'] ?: 'Never')) ?></td>
	                            <td>
	                                <a href="<?= e(url('/enma/?tab=users&edit_user=' . $userRow['id'])) ?>" style="font-size:13px;color:#0b1f3a;margin-right:10px;text-decoration:none;font-weight:700;">Edit</a>
	                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this user?');">
	                                    <input type="hidden" name="action" value="delete_user">
	                                    <input type="hidden" name="id" value="<?= (int) $userRow['id'] ?>">
	                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
	                                    <button type="submit" style="background:none;border:none;color:#d00;cursor:pointer;padding:0;font-size:13px;">Delete</button>
	                                </form>
	                            </td>
	                        </tr>
  	                    <?php endforeach; ?>
  	                    </tbody>
  	                </table>
                    <?= $usersPagination ?>
  	            <?php endif; ?>
  	        </section>

	        <section id="users-activity" class="box ops-anchor-offset">
	            <h2>Admin Activity Log</h2>
  	            <?php if ($recentAdminActivity === []): ?>
 	                <div class="empty">No admin activity recorded yet.</div>
  	            <?php else: ?>
                    <p class="muted">Showing <?= number_format(count($recentAdminActivity)) ?> of <?= number_format($activityTotal) ?> activity records.</p>
  	                <table>
	                    <thead>
	                        <tr>
	                            <th>Date</th>
	                            <th>Admin</th>
	                            <th>Action</th>
	                            <th>Entity</th>
	                            <th>Details</th>
	                        </tr>
	                    </thead>
	                    <tbody>
	                    <?php foreach ($recentAdminActivity as $activity): ?>
	                        <?php
	                        $details = [];
	                        if (!empty($activity['details_json'])) {
	                            $decoded = json_decode((string) $activity['details_json'], true);
	                            if (is_array($decoded)) {
	                                $details = $decoded;
	                            }
	                        }
	                        ?>
	                        <tr>
	                            <td><?= e((string) ($activity['created_at'] ?? '')) ?></td>
	                            <td><?= e((string) ($activity['admin_username'] ?? '')) ?></td>
	                            <td><?= e((string) ($activity['action_key'] ?? '')) ?></td>
	                            <td><?= e(trim((string) (($activity['entity_type'] ?? '') . ' #' . ($activity['entity_id'] ?? '')), ' #')) ?></td>
	                            <td><code><?= e($details === [] ? '' : json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></td>
	                        </tr>
  	                    <?php endforeach; ?>
  	                    </tbody>
  	                </table>
                    <?= $activityPagination ?>
  	            <?php endif; ?>
	        </section>
	        <?php elseif ($activeTab === 'analytics'): ?>
        <?php $analyticsPeriods = $analyticsDashboard['stats']['periods'] ?? []; ?>
        <section class="box">
            <h2>Analytics & Seguridad</h2>
            <div class="stats">
                <div class="stat"><div class="stat-k">Total Views</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['total_views'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Unique Visitors</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['unique_ips'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Outbound Clicks</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['total_clicks'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Human Traffic</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['human_traffic'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Suspected Bots</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['suspected_bots'] ?? 0)) ?></div></div>
                <div class="stat"><div class="stat-k">Suspected Attacks</div><div class="stat-v"><?= number_format((int) ($analyticsDashboard['stats']['suspected_attacks'] ?? 0)) ?></div></div>
            </div>
            <p class="muted">This analytics panel now runs inside the same ENMA layout to avoid theme/page jumps.</p>
        </section>

        <section class="box">
            <h2>Standard Windows (UTC)</h2>
            <div class="stats">
                <?php foreach (['today', 'this_week', 'this_month'] as $periodKey): ?>
                    <?php $period = $analyticsPeriods[$periodKey] ?? []; ?>
                    <div class="stat">
                        <div class="stat-k"><?= e((string) ($period['label'] ?? strtoupper($periodKey))) ?></div>
                        <div class="stat-v"><?= number_format((int) ($period['views'] ?? 0)) ?> views</div>
                        <div class="muted"><?= number_format((int) ($period['unique_visitors'] ?? 0)) ?> unique visitors</div>
                        <div class="muted"><?= number_format((int) ($period['clicks'] ?? 0)) ?> outbound clicks</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="muted">Owner/admin traffic is excluded from new tracking events after login.</p>
        </section>

        <section class="box">
            <h2>Top User Agents</h2>
            <?php if (($analyticsDashboard['top_agents'] ?? []) === []): ?>
                <div class="empty">No traffic data yet.</div>
            <?php else: ?>
            <p class="muted">Showing <?= number_format(count($analyticsAgentsRows)) ?> of <?= number_format(count($analyticsAgentsAll)) ?> user agents.</p>
            <table>
                <thead>
                    <tr><th>User Agent</th><th>Hits</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($analyticsAgentsRows as $agent): ?>
                    <tr>
                        <td><?= e((string) ($agent['user_agent'] ?? '')) ?></td>
                        <td><?= number_format((int) ($agent['count'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= $analyticsAgentsPagination ?>
            <?php endif; ?>
        </section>

        <section class="box">
            <h2>Recent Logs</h2>
            <?php if (($analyticsDashboard['recent_logs'] ?? []) === []): ?>
                <div class="empty">No recent logs found.</div>
            <?php else: ?>
            <p class="muted">Showing <?= number_format(count($analyticsLogsRows)) ?> of <?= number_format(count($analyticsLogsAll)) ?> logs.</p>
            <table>
                <thead>
                    <tr><th>ID</th><th>Date</th><th>URL</th><th>IP/Hash</th><th>User Agent</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($analyticsLogsRows as $log): ?>
                    <tr>
                        <td><?= (int) ($log['id'] ?? 0) ?></td>
                        <td><?= e((string) ($log['created_at'] ?? '')) ?></td>
                        <td><code><?= e((string) ($log['url'] ?? '')) ?></code></td>
                        <td><?= e((string) ($log['ip_address'] ?? '')) ?></td>
                        <td><?= e((string) ($log['user_agent'] ?? '')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?= $analyticsLogsPagination ?>
            <?php endif; ?>
        </section>

        <?php elseif ($activeTab === 'views'): ?>
        <section class="box">
            <h2>Views Workspace</h2>
            <p class="muted" style="margin:0 0 10px;">Analyze traffic window, compare deltas, and jump directly to funnel/top pages/sources.</p>
            <div class="ops-kpis">
                <div class="ops-kpi"><div class="k">Window</div><div class="v"><?= number_format((int) ($viewsDashboard['days'] ?? $viewDays)) ?>d</div></div>
                <div class="ops-kpi"><div class="k">Total Views</div><div class="v"><?= number_format((int) (($viewsDashboard['totals']['total_views'] ?? 0))) ?></div></div>
                <div class="ops-kpi"><div class="k">Outbound Clicks</div><div class="v"><?= number_format((int) (($viewsDashboard['clicks']['total_clicks'] ?? 0))) ?></div></div>
                <div class="ops-kpi"><div class="k">CTR</div><div class="v"><?= number_format((float) (($viewsDashboard['clicks']['ctr_percent'] ?? 0.0)), 2) ?>%</div></div>
            </div>
            <div class="ops-nav">
                <a class="ops-link" href="#views-overview">Overview</a>
                <a class="ops-link" href="#views-funnel">Funnel</a>
                <a class="ops-link" href="#views-top-pages">Top Pages</a>
                <a class="ops-link" href="#views-sources">Sources</a>
                <a class="ops-link" href="#views-referrers">Referrers</a>
            </div>
        </section>
        <section id="views-overview" class="box ops-anchor-offset">
            <h2>Views Dashboard</h2>
            <p style="margin: 0 0 10px; font-size: 14px; color: #334155;">Tracking window: last <?= (int) ($viewsDashboard['days'] ?? $viewDays) ?> days (from <?= e((string) ($viewsDashboard['from_date'] ?? '')) ?> UTC)</p>
            <p class="muted" style="margin: 0 0 10px;">Compared against previous window: <?= e((string) ($viewsDashboard['previous_range']['from_date'] ?? '-')) ?> to <?= e((string) ($viewsDashboard['previous_range']['to_date'] ?? '-')) ?>.</p>
            <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
                <input type="hidden" name="tab" value="views">
                <div style="max-width:160px;">
                    <label>Days</label>
                    <input type="number" name="days" min="1" max="180" value="<?= (int) $viewDays ?>">
                </div>
                <button class="btn" type="submit">Refresh</button>
            </form>

            <div class="stats">
                <div class="stat">
                    <div class="stat-k">Total Views</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['total_views'] ?? 0))) ?></div>
                    <div class="muted <?= ((int) ($viewsCompareDelta['views'] ?? 0)) >= 0 ? 'seo-ok' : 'seo-warn' ?>"><?= e(enma_signed_number((float) ($viewsCompareDelta['views'] ?? 0))) ?> vs previous</div>
                </div>
                <div class="stat">
                    <div class="stat-k">Tracked Paths</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['unique_paths'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Aggregated Rows</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['totals']['rows_count'] ?? 0))) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Outbound Clicks</div>
                    <div class="stat-v"><?= number_format((int) (($viewsDashboard['clicks']['total_clicks'] ?? 0))) ?></div>
                    <div class="muted <?= ((int) ($viewsCompareDelta['clicks'] ?? 0)) >= 0 ? 'seo-ok' : 'seo-warn' ?>"><?= e(enma_signed_number((float) ($viewsCompareDelta['clicks'] ?? 0))) ?> vs previous</div>
                </div>
                <div class="stat">
                    <div class="stat-k">CTR</div>
                    <div class="stat-v"><?= number_format((float) (($viewsDashboard['clicks']['ctr_percent'] ?? 0.0)), 2) ?>%</div>
                    <div class="muted <?= ((float) ($viewsCompareDelta['ctr_percent'] ?? 0.0)) >= 0 ? 'seo-ok' : 'seo-warn' ?>"><?= e(enma_signed_number((float) ($viewsCompareDelta['ctr_percent'] ?? 0.0), 2)) ?> pp vs previous</div>
                </div>
            </div>
            <p style="margin: 0; font-size: 12px; color: #5b6678;">Country is best-effort from server/CDN geo headers (fallback: Accept-Language).</p>
        </section>

        <section id="views-funnel" class="box ops-anchor-offset">
            <h2>Traffic Funnel</h2>
            <div class="stats">
                <div class="stat">
                    <div class="stat-k">Discovery Views</div>
                    <div class="stat-v"><?= number_format((int) ($viewsFunnel['discovery_views'] ?? 0)) ?></div>
                </div>
                <div class="stat">
                    <div class="stat-k">Product Page Views</div>
                    <div class="stat-v"><?= number_format((int) ($viewsFunnel['product_views'] ?? 0)) ?></div>
                    <div class="muted"><?= number_format((float) ($viewsFunnel['discovery_to_product_percent'] ?? 0.0), 2) ?>% from discovery</div>
                </div>
                <div class="stat">
                    <div class="stat-k">Outbound Clicks</div>
                    <div class="stat-v"><?= number_format((int) ($viewsFunnel['outbound_clicks'] ?? 0)) ?></div>
                    <div class="muted"><?= number_format((float) ($viewsFunnel['product_to_click_percent'] ?? 0.0), 2) ?>% from product pages</div>
                </div>
            </div>
        </section>

        <section class="box">
            <h2>Winners and Losers</h2>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <h3 style="margin:0 0 8px;">Top Growth Pages</h3>
                    <?php if ($viewsTopWinners === []): ?>
                        <div class="empty">No growth deltas available yet.</div>
                    <?php else: ?>
                    <table>
                        <thead>
                        <tr><th>Path</th><th>Current</th><th>Previous</th><th>Delta</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($viewsTopWinners, 0, 10) as $row): ?>
                            <tr>
                                <td><?= e((string) ($row['path'] ?? '')) ?></td>
                                <td><?= number_format((int) ($row['current_views'] ?? 0)) ?></td>
                                <td><?= number_format((int) ($row['previous_views'] ?? 0)) ?></td>
                                <td class="seo-ok"><?= e(enma_signed_number((float) ($row['delta_views'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="margin:0 0 8px;">Top Decline Pages</h3>
                    <?php if ($viewsTopLosers === []): ?>
                        <div class="empty">No decline deltas available yet.</div>
                    <?php else: ?>
                    <table>
                        <thead>
                        <tr><th>Path</th><th>Current</th><th>Previous</th><th>Delta</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach (array_slice($viewsTopLosers, 0, 10) as $row): ?>
                            <tr>
                                <td><?= e((string) ($row['path'] ?? '')) ?></td>
                                <td><?= number_format((int) ($row['current_views'] ?? 0)) ?></td>
                                <td><?= number_format((int) ($row['previous_views'] ?? 0)) ?></td>
                                <td class="seo-warn"><?= e(enma_signed_number((float) ($row['delta_views'] ?? 0))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <section id="views-top-pages" class="box ops-anchor-offset">
            <h2>Top Pages</h2>
            <p class="muted">Showing <?= number_format(count($viewsTopPagesRows)) ?> of <?= number_format(count($viewsTopPagesAll)) ?> rows.</p>
            <table>
                <thead>
                <tr>
                    <th>Path</th>
                    <th>Type</th>
                    <th>Views</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewsTopPagesRows as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['path'] ?? '')) ?></td>
                        <td><?= e((string) ($row['page_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['total_views'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= $viewsTopPagesPagination ?>
        </section>

        <section class="box">
            <h2>Top Product Pages</h2>
            <p class="muted">Showing <?= number_format(count($viewsTopProductsRows)) ?> of <?= number_format(count($viewsTopProductsAll)) ?> rows.</p>
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Slug</th>
                    <th>Views</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewsTopProductsRows as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['title'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['total_views'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= $viewsTopProductsPagination ?>
        </section>

        <section class="box">
            <h2>Top Clicked Products</h2>
            <p class="muted">Showing <?= number_format(count($viewsTopClickedRows)) ?> of <?= number_format(count($viewsTopClickedAll)) ?> rows.</p>
            <table>
                <thead>
                <tr>
                    <th>Product</th>
                    <th>Slug</th>
                    <th>Clicks</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewsTopClickedRows as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['title'] ?? '')) ?></td>
                        <td><?= e((string) ($row['slug'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['clicks'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= $viewsTopClickedPagination ?>
        </section>

        <section id="views-sources" class="box ops-anchor-offset">
            <h2>Traffic Source Breakdown</h2>
            <table>
                <thead>
                <tr>
                    <th>Source</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['source_breakdown'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['source_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="box">
            <h2>Top Countries</h2>
            <table>
                <thead>
                <tr>
                    <th>Country</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (($viewsDashboard['top_countries'] ?? []) as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['country_code'] ?? 'UNK')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section id="views-referrers" class="box ops-anchor-offset">
            <h2>Top Referrers</h2>
            <p class="muted">Showing <?= number_format(count($viewsReferrersRows)) ?> of <?= number_format(count($viewsReferrersAll)) ?> rows.</p>
            <table>
                <thead>
                <tr>
                    <th>Referrer</th>
                    <th>Type</th>
                    <th>Hits</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($viewsReferrersRows as $row): ?>
                    <tr>
                        <td><?= e((string) ($row['referrer_host'] ?? 'direct')) ?></td>
                        <td><?= e((string) ($row['source_type'] ?? '')) ?></td>
                        <td><?= number_format((int) ($row['hits'] ?? 0)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?= $viewsReferrersPagination ?>
        </section>
        <?php else: ?>
        <?php
        $availableMaintenanceTasks = $availableMaintenanceTasks ?? [];
        $availableAdvancedTasks = $availableAdvancedTasks ?? [];
        $maintenanceUsageMap = $maintenanceUsageMap ?? [];
        $promptToolsCount = 3;
        $automationToolsCount = 2;
        $routineToolsCount = count($availableMaintenanceTasks);
        $advancedToolsCount = count($availableAdvancedTasks);
        ?>
        <section class="box">
            <h2>Maintenance Workspace</h2>
            <p class="muted" style="margin: 0 0 10px;">Workflow focused: copy prompt, paste AI result, run update, then execute routine tasks. Last usage is tracked automatically.</p>
            <div class="ops-kpis">
                <div class="ops-kpi"><div class="k">Prompt Tools</div><div class="v"><?= number_format($promptToolsCount) ?></div></div>
                <div class="ops-kpi"><div class="k">AI Actions</div><div class="v"><?= number_format($automationToolsCount) ?></div></div>
                <div class="ops-kpi"><div class="k">Routine Tasks</div><div class="v"><?= number_format($routineToolsCount) ?></div></div>
                <div class="ops-kpi"><div class="k">Advanced Tasks</div><div class="v"><?= number_format($advancedToolsCount) ?></div></div>
            </div>
            <div class="ops-nav">
                <a class="ops-link" href="#ops-progress">Progress</a>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Prompts Workspace</a>
                <a class="ops-link" href="#ops-safe-check">Safe Availability Check</a>
                <a class="ops-link" href="#ops-routines">Routine Tasks</a>
                <a class="ops-link" href="#ops-not-found-review">Not Found Review</a>
                <a class="ops-link" href="#ops-output">Task Output</a>
                <a class="ops-link" href="#ops-db">DB Snapshot</a>
                <?php if ($advancedEnabled): ?>
                    <a class="ops-link" href="#ops-advanced">Advanced</a>
                <?php endif; ?>
            </div>
            <textarea id="sitemap_public_url_source" class="copy-source" readonly><?= e($sitemapCopyText) ?></textarea>
            <textarea id="db_schema_copy_source" class="copy-source" readonly><?= e($dbSchemaCopyText) ?></textarea>
            <textarea id="products_sql_copy_source" class="copy-source" readonly><?= e($productsSqlCopyText) ?></textarea>
            <textarea id="posts_json_copy_source" class="copy-source" readonly><?= e($postsJsonCopyText) ?></textarea>
            <textarea id="seo_prompt_copy_source" class="copy-source" readonly><?= e($seoPromptTemplate) ?></textarea>
            <textarea id="seo_prompt_sitemap_copy_source" class="copy-source" readonly><?= e($promptPlusSitemapCopyText) ?></textarea>
            <textarea id="catalog_prompt_copy_source" class="copy-source" readonly><?= e($catalogPromptTemplate) ?></textarea>
            <?php $maintenanceProgress = is_array($maintenanceProgress ?? null) ? $maintenanceProgress : []; ?>
            <?php
            $imageWeekly = is_array($maintenanceProgress['image_weekly'] ?? null) ? $maintenanceProgress['image_weekly'] : ['runs' => 0, 'checked' => 0, 'updated' => 0, 'failed_fetches' => 0];
            $imageLastRun = is_array($maintenanceProgress['image_last_run'] ?? null) ? $maintenanceProgress['image_last_run'] : null;
            $safeCheckLastRun = is_array($maintenanceProgress['safe_check_last_run'] ?? null) ? $maintenanceProgress['safe_check_last_run'] : null;
            $recentRuns = is_array($maintenanceProgress['recent_runs'] ?? null) ? $maintenanceProgress['recent_runs'] : [];
            ?>
            <div id="ops-progress" class="box ops-anchor-offset" style="margin-top:12px; margin-bottom:12px;">
                <h3 style="margin:0 0 8px;">Progress & Queue</h3>
                <p class="muted" style="margin:0 0 10px;">Use this to confirm work is moving: run volume, updated items, failure rate, and estimated remaining runs.</p>
                <div class="ops-kpis">
                    <div class="ops-kpi"><div class="k">Image Runs (7d)</div><div class="v"><?= number_format((int) ($imageWeekly['runs'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Checked (7d)</div><div class="v"><?= number_format((int) ($imageWeekly['checked'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Updated (7d)</div><div class="v"><?= number_format((int) ($imageWeekly['updated'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Success %</div><div class="v"><?= number_format((float) ($maintenanceProgress['image_success_rate'] ?? 0), 2) ?>%</div></div>
                    <div class="ops-kpi"><div class="k">Failure %</div><div class="v"><?= number_format((float) ($maintenanceProgress['image_failure_rate'] ?? 0), 2) ?>%</div></div>
                    <div class="ops-kpi"><div class="k">Remaining</div><div class="v"><?= number_format((int) ($maintenanceProgress['image_remaining'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Avg Updates/Run</div><div class="v"><?= number_format((float) ($maintenanceProgress['image_avg_updates_per_run'] ?? 0), 2) ?></div></div>
                    <div class="ops-kpi"><div class="k">ETA Runs</div><div class="v"><?= $maintenanceProgress['image_eta_runs'] === null ? 'n/a' : number_format((int) $maintenanceProgress['image_eta_runs']) ?></div></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;">
                    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fbff;">
                        <h4 style="margin:0 0 6px;">Last Image Fix Run</h4>
                        <?php if ($imageLastRun !== null): ?>
                            <div class="muted" style="margin:0;font-size:12px;">At: <?= e((string) ($imageLastRun['created_at'] ?? '')) ?></div>
                            <div class="muted" style="margin:4px 0 0;font-size:12px;">Status: <?= e(strtoupper((string) ($imageLastRun['status'] ?? ''))) ?> | Duration: <?= number_format((float) ($imageLastRun['duration_seconds'] ?? 0), 3) ?>s</div>
                            <div class="muted" style="margin:4px 0 0;font-size:12px;"><?= e((string) ($imageLastRun['message'] ?? '')) ?></div>
                        <?php else: ?>
                            <div class="empty">No run recorded yet.</div>
                        <?php endif; ?>
                    </div>
                    <div style="border:1px solid #e2e8f0;border-radius:10px;padding:10px;background:#f8fbff;">
                        <h4 style="margin:0 0 6px;">Last Safe Check</h4>
                        <?php if ($safeCheckLastRun !== null): ?>
                            <div class="muted" style="margin:0;font-size:12px;">At: <?= e((string) ($safeCheckLastRun['created_at'] ?? '')) ?></div>
                            <div class="muted" style="margin:4px 0 0;font-size:12px;">Status: <?= e(strtoupper((string) ($safeCheckLastRun['status'] ?? ''))) ?> | Duration: <?= number_format((float) ($safeCheckLastRun['duration_seconds'] ?? 0), 3) ?>s</div>
                            <div class="muted" style="margin:4px 0 0;font-size:12px;"><?= e((string) ($safeCheckLastRun['message'] ?? '')) ?></div>
                        <?php else: ?>
                            <div class="empty">No safe check recorded yet.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:10px;">
                    <h4 style="margin:0 0 6px;">Recent Job Runs</h4>
                    <?php if ($recentRuns === []): ?>
                        <div class="empty">No recent maintenance runs yet.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                            <tr>
                                <th>Task</th>
                                <th>Status</th>
                                <th>Duration</th>
                                <th>When</th>
                                <th>Message</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recentRuns as $run): ?>
                                <tr>
                                    <td><?= e((string) ($run['task_key'] ?? '')) ?></td>
                                    <td><?= e(strtoupper((string) ($run['status'] ?? ''))) ?></td>
                                    <td><?= number_format((float) ($run['duration_seconds'] ?? 0), 3) ?>s</td>
                                    <td><?= e((string) ($run['created_at'] ?? '')) ?></td>
                                    <td><?= e((string) ($run['message'] ?? '')) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="box ops-anchor-offset" style="margin-top:12px; margin-bottom:12px;">
                <h3 style="margin:0 0 8px;">Prompts Workspace</h3>
                <p class="muted" style="margin:0 0 10px;">Prompt tools, catalog import y AI draft se movieron a una seccion dedicada para mantener Maintenance limpio.</p>
                <a class="ops-link" href="<?= e(url('/enma/?tab=prompts')) ?>">Open Prompts Workspace</a>
            </div>
            <div id="ops-safe-check" class="box ops-anchor-offset" style="margin-top:12px; margin-bottom:12px;">
                <h3 style="margin:0 0 8px;">Safe Availability Check (One Product per Click)</h3>
                <p class="muted" style="margin:0 0 10px;">
                    This checker processes exactly one product per click, waits 3-6 seconds before request, rotates User-Agent, and logs into
                    <code>availability_log.txt</code>.
                </p>

                <?php $availabilityCheckerDashboard = is_array($availabilityCheckerDashboard ?? null) ? $availabilityCheckerDashboard : []; ?>
                <?php $availabilityCheckerResult = is_array($availabilityCheckerResult ?? null) ? $availabilityCheckerResult : null; ?>

                <div class="ops-kpis">
                    <div class="ops-kpi"><div class="k">Total Products</div><div class="v"><?= number_format((int) ($availabilityCheckerDashboard['total_products'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Available</div><div class="v"><?= number_format((int) ($availabilityCheckerDashboard['available_products'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Unavailable</div><div class="v"><?= number_format((int) ($availabilityCheckerDashboard['unavailable_products'] ?? 0)) ?></div></div>
                    <div class="ops-kpi"><div class="k">Last Check</div><div class="v" style="font-size:12px;line-height:1.35;"><?= e((string) (($availabilityCheckerDashboard['last_checked_at'] ?? '') !== '' ? $availabilityCheckerDashboard['last_checked_at'] : 'Never')) ?></div></div>
                </div>

                <p class="muted" style="margin:8px 0;">
                    Next product URL:
                    <?php $nextSafeUrl = trim((string) ($availabilityCheckerDashboard['next_product_url'] ?? '')); ?>
                    <?php if ($nextSafeUrl !== ''): ?>
                        <a href="<?= e($nextSafeUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($nextSafeUrl) ?></a>
                    <?php else: ?>
                        <span>n/a</span>
                    <?php endif; ?>
                </p>

                <?php if ($availabilityCheckerResult !== null): ?>
                    <div class="<?= !empty($availabilityCheckerResult['ok']) ? 'ok' : 'error' ?>" style="margin-bottom:10px;">
                        <?= e((string) ($availabilityCheckerResult['message'] ?? 'Safe check finished.')) ?>
                        <?php if (!empty($availabilityCheckerResult['url'])): ?>
                            <div style="margin-top:6px;font-size:12px;"><?= e((string) $availabilityCheckerResult['url']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form id="availability_safe_check_form" method="post" style="margin:0;">
                    <input type="hidden" name="action" value="availability_safe_check_next">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button id="availability_safe_check_button" class="btn" type="submit" style="font-size:18px;padding:14px 22px;">
                        Check Next Product (Safe Mode)
                    </button>
                    <span id="availability_safe_check_status" class="muted" style="display:none;margin-left:10px;font-weight:700;">Checking...</span>
                </form>
                <p class="muted" style="margin:10px 0 0;">
                    Click repeatedly to process your catalog. Wait a few seconds between clicks if doing many manually, though the script handles the delay.
                </p>
            </div>
            <?php
            $maintenanceGroups = [
                'seo' => 'SEO',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'as_needed' => 'As Needed',
            ];
            ?>
            <h3 id="ops-routines" class="ops-section-title ops-anchor-offset">Routine Tasks</h3>
            <?php foreach ($maintenanceGroups as $groupKey => $groupLabel): ?>
                <?php
                $groupTasks = array_filter(
                    $availableMaintenanceTasks,
                    static fn(array $meta): bool => (($meta['group'] ?? '') === $groupKey)
                );
                ?>
                <?php if ($groupTasks !== []): ?>
                <div class="box" style="margin-top:12px; margin-bottom:0;">
                    <h3 style="margin:0 0 6px;"><?= e($groupLabel) ?></h3>
                    <div class="maintenance-grid">
                        <?php foreach ($groupTasks as $taskKey => $taskMeta): ?>
                            <?php
                            $usage = $maintenanceUsageMap[$taskKey] ?? null;
                            $lastRunAt = is_array($usage) ? (string) ($usage['last_run_at'] ?? '') : '';
                            $lastStatus = strtolower((string) (is_array($usage) ? ($usage['last_status'] ?? '') : ''));
                            $statusClass = $lastStatus === 'ok' ? 'ok' : ($lastStatus === 'fail' ? 'fail' : '');
                            $runCount = (int) (is_array($usage) ? ($usage['run_count'] ?? 0) : 0);
                            $isSingleUse = !empty($taskMeta['single_use']);
                            $singleUseConsumed = $isSingleUse && $runCount > 0;
                            ?>
                            <article class="maintenance-card">
                                <h4>
                                    <?= e((string) ($taskMeta['label'] ?? $taskKey)) ?>
                                    <?php if ($isSingleUse): ?>
                                        <span class="maintenance-badge">Single use<?= $singleUseConsumed ? ' used' : '' ?></span>
                                    <?php endif; ?>
                                </h4>
                                <p class="maintenance-meta">Frequency: <?= e((string) ($taskMeta['frequency'] ?? 'As needed')) ?></p>
                                <p class="maintenance-desc"><?= e((string) ($taskMeta['description'] ?? '')) ?></p>
                                <p class="maintenance-last">
                                    Last run: <?= e(enma_human_last_run($lastRunAt !== '' ? $lastRunAt : null)) ?>
                                    <?php if ($statusClass !== ''): ?>
                                        | <strong class="<?= e($statusClass) ?>"><?= e(strtoupper($lastStatus)) ?></strong>
                                    <?php endif; ?>
                                    | Runs: <?= number_format($runCount) ?>
                                </p>
                                <form method="post" style="margin:0;">
                                    <input type="hidden" name="action" value="maintenance_run">
                                    <input type="hidden" name="task" value="<?= e((string) $taskKey) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <button class="btn" type="submit" <?= $singleUseConsumed ? 'disabled' : '' ?>>
                                        <?= $singleUseConsumed ? 'Already used' : e((string) ($taskMeta['label'] ?? 'Run task')) ?>
                                    </button>
                                </form>
                                <?php if ((string) $taskKey === 'generate_sitemap'): ?>
                                    <div class="copy-actions" style="margin-top:8px;">
                                        <button class="btn btn-copy" type="button" data-copy-target="sitemap_public_url_source" data-copy-status="generate_sitemap_copy_status">Copy Updated Sitemap</button>
                                        <span id="generate_sitemap_copy_status" class="copy-status"></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ((string) $taskKey === 'export_db_schema'): ?>
                                    <div class="copy-actions" style="margin-top:8px;">
                                        <button class="btn btn-copy" type="button" data-copy-target="db_schema_copy_source" data-copy-status="db_schema_copy_status">Copy DB Schema</button>
                                        <span id="db_schema_copy_status" class="copy-status"></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ((string) $taskKey === 'export_products_sql'): ?>
                                    <div class="copy-actions" style="margin-top:8px;">
                                        <button class="btn btn-copy" type="button" data-copy-target="products_sql_copy_source" data-copy-status="products_sql_copy_status">Copy Products SQL</button>
                                        <span id="products_sql_copy_status" class="copy-status"></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ((string) $taskKey === 'export_posts_pastebin'): ?>
                                    <div class="copy-actions" style="margin-top:8px;">
                                        <button class="btn btn-copy" type="button" data-copy-target="posts_json_copy_source" data-copy-status="posts_json_copy_status">Copy Posts JSON</button>
                                        <span id="posts_json_copy_status" class="copy-status"></span>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div id="ops-not-found-review" class="box ops-anchor-offset" style="margin-top:12px; margin-bottom:12px;">
                <h3 style="margin:0 0 8px;">Not Found Review (Manual Eyeball)</h3>
                <p class="muted" style="margin:0 0 10px;">Rows flagged by link checks as <code>not_found</code> or <code>warning</code>. Use this for manual cleanup when auto-detection is uncertain.</p>
                <?php if (($notFoundReviewRows ?? []) === []): ?>
                    <div class="empty">No flagged products found. Run <strong>Clean Not Found Products</strong> first.</div>
                <?php else: ?>
                    <p class="muted">Showing <?= number_format(count((array) $notFoundReviewRows)) ?> of <?= number_format((int) ($notFoundReviewTotal ?? 0)) ?> flagged rows.</p>
                    <table>
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>ASIN</th>
                            <th>Image</th>
                            <th>Title</th>
                            <th>State</th>
                            <th>HTTP</th>
                            <th>Checked</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ((array) $notFoundReviewRows as $row): ?>
                            <tr>
                                <td><?= (int) ($row['id'] ?? 0) ?></td>
                                <td><?= e((string) ($row['asin'] ?? '')) ?></td>
                                <td style="width:84px;">
                                    <img
                                        src="<?= e(product_image_url((array) $row)) ?>"
                                        alt="<?= e((string) ($row['title'] ?? 'Product image')) ?>"
                                        loading="lazy"
                                        decoding="async"
                                        style="display:block;width:68px;height:68px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;background:#f8fafc;"
                                        onerror="this.onerror=null;this.src='<?= e(product_image_fallback_url()) ?>';"
                                    >
                                </td>
                                <td>
                                    <div><?= e((string) ($row['title'] ?? '')) ?></div>
                                    <div class="muted" style="font-size:12px;"><a href="<?= e((string) ($row['affiliate_url'] ?? '#')) ?>" target="_blank" rel="noopener noreferrer">Open link</a></div>
                                </td>
                                <td><?= e((string) ($row['state'] ?? 'unknown')) ?></td>
                                <td><?= (int) ($row['http_status'] ?? 0) ?></td>
                                <td><?= e((string) ($row['checked_at'] ?? '')) ?></td>
                                <td>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Archive this product?');">
                                        <input type="hidden" name="action" value="maintenance_archive_review_product">
                                        <input type="hidden" name="product_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button class="btn" type="submit" style="padding:6px 10px;font-size:12px;">Archive</button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product permanently?');">
                                        <input type="hidden" name="action" value="maintenance_delete_review_product">
                                        <input type="hidden" name="product_id" value="<?= (int) ($row['id'] ?? 0) ?>">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <button type="submit" style="background:#b91c1c;border:none;color:#fff;cursor:pointer;padding:6px 10px;font-size:12px;border-radius:6px;margin-left:8px;">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?= $notFoundReviewPagination ?>
                <?php endif; ?>
            </div>

            <?php if ($maintenanceLog !== []): ?>
                <div id="ops-output" class="ops-anchor-offset" style="background:#f6f9fc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;">
                    <div class="copy-toolbar" style="margin-bottom:10px;">
                        <h3>Latest Task Output</h3>
                        <div class="copy-actions">
                            <button class="btn btn-copy" type="button" data-copy-target="maintenance_sitemap_copy_source" data-copy-status="maintenance_task_copy_status">Copy Task Output</button>
                            <span id="maintenance_task_copy_status" class="copy-status"></span>
                        </div>
                    </div>
                    <textarea id="maintenance_sitemap_copy_source" class="copy-source" readonly><?= e($maintenanceLogCopyText) ?></textarea>
                    <?php foreach ($maintenanceLog as $line): ?>
                        <div style="font-family:monospace;font-size:13px;"><?= e($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section id="ops-db" class="box ops-anchor-offset">
            <h2>Database Snapshot</h2>
            <table>
                <thead>
                    <tr><th>Table</th><th>Rows</th></tr>
                </thead>
                <tbody>
                <?php foreach ($dbTables as $t): ?>
                    <tr>
                        <td><?= e((string) $t['name']) ?></td>
                        <td><?= $t['rows'] >= 0 ? number_format((int) $t['rows']) : 'n/a' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ($advancedEnabled): ?>
        <section id="ops-advanced" class="box ops-anchor-offset">
            <h2>Advanced Mode</h2>
            <p style="margin: 0 0 12px; font-size: 14px; color: #8a1f1f;">
                High-impact tasks. Use only if you understand the effect on catalog data.
            </p>
            <?php $availableAdvancedTasks = $availableAdvancedTasks ?? []; ?>
            <?php if ($availableAdvancedTasks === []): ?>
                <div class="empty">No advanced scripts are currently available on this host.</div>
            <?php else: ?>
                <?php
                $runnableAdvancedTasks = array_filter(
                    $availableAdvancedTasks,
                    static function (array $taskMeta, string $taskKey) use ($maintenanceUsageMap): bool {
                        $runCount = (int) (($maintenanceUsageMap[$taskKey]['run_count'] ?? 0));
                        if (!empty($taskMeta['single_use']) && $runCount > 0) {
                            return false;
                        }
                        return true;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                ?>
                <div class="maintenance-grid" style="margin-bottom:12px;">
                    <?php foreach ($availableAdvancedTasks as $taskKey => $taskMeta): ?>
                        <?php
                        $usage = ($maintenanceUsageMap ?? [])[$taskKey] ?? null;
                        $lastRunAt = is_array($usage) ? (string) ($usage['last_run_at'] ?? '') : '';
                        $lastStatus = strtolower((string) (is_array($usage) ? ($usage['last_status'] ?? '') : ''));
                        $statusClass = $lastStatus === 'ok' ? 'ok' : ($lastStatus === 'fail' ? 'fail' : '');
                        $runCount = (int) (is_array($usage) ? ($usage['run_count'] ?? 0) : 0);
                        $isSingleUse = !empty($taskMeta['single_use']);
                        $singleUseConsumed = $isSingleUse && $runCount > 0;
                        ?>
                        <article class="maintenance-card">
                            <h4>
                                <?= e((string) ($taskMeta['label'] ?? $taskKey)) ?>
                                <?php if ($isSingleUse): ?>
                                    <span class="maintenance-badge">Single use<?= $singleUseConsumed ? ' used' : '' ?></span>
                                <?php endif; ?>
                            </h4>
                            <p class="maintenance-meta">Frequency: <?= e((string) ($taskMeta['frequency'] ?? 'Rare / supervised')) ?></p>
                            <p class="maintenance-desc"><?= e((string) ($taskMeta['description'] ?? '')) ?></p>
                            <p class="maintenance-last">
                                Last run: <?= e(enma_human_last_run($lastRunAt !== '' ? $lastRunAt : null)) ?>
                                <?php if ($statusClass !== ''): ?>
                                    | <strong class="<?= e($statusClass) ?>"><?= e(strtoupper($lastStatus)) ?></strong>
                                <?php endif; ?>
                                | Runs: <?= number_format($runCount) ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="maintenance_advanced_run">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

                    <label>Task</label>
                    <select name="task" required>
                        <?php foreach ($availableAdvancedTasks as $taskKey => $taskMeta): ?>
                            <?php
                            $usage = ($maintenanceUsageMap ?? [])[$taskKey] ?? null;
                            $runCount = (int) (is_array($usage) ? ($usage['run_count'] ?? 0) : 0);
                            $singleUseConsumed = !empty($taskMeta['single_use']) && $runCount > 0;
                            ?>
                            <option value="<?= e((string) $taskKey) ?>" <?= $singleUseConsumed ? 'disabled' : '' ?>>
                                <?= e((string) ($taskMeta['label'] ?? $taskKey)) ?><?= $singleUseConsumed ? ' (already used)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Advanced Key</label>
                    <input type="password" name="advanced_key" required <?= $runnableAdvancedTasks === [] ? 'disabled' : '' ?>>

                    <label>Confirmation Text</label>
                    <input type="text" name="confirm_text" required placeholder="RUN TASK_NAME" <?= $runnableAdvancedTasks === [] ? 'disabled' : '' ?>>

                    <button class="btn" type="submit" <?= $runnableAdvancedTasks === [] ? 'disabled' : '' ?>>Run Advanced Task</button>
                </form>
                <?php if ($runnableAdvancedTasks === []): ?>
                    <p class="muted" style="margin:8px 0 0;">All available advanced tasks are already consumed or unavailable.</p>
                <?php endif; ?>
                <p style="margin: 10px 0 0; font-size: 12px; color: #555;">
                    Confirmation must match: <code>RUN &lt;task_value&gt;</code> in uppercase.
                </p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
        <?php endif; ?>

        <section class="box">
            <form method="post">
                <input type="hidden" name="action" value="logout">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="btn" type="submit">Logout</button>
            </form>
        </section>
    <?php endif; ?>
</div>
</body>
</html>
