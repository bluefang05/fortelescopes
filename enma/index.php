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
require_once __DIR__ . '/users_handler.php';
require_once __DIR__ . '/maintenance.php';

// Refresh auth state in case auth.php changed the session
$authenticated = !empty($_SESSION['admin_ok']);

$activeTab = $authenticated ? (string) ($_GET['tab'] ?? 'overview') : 'overview';
if (!in_array($activeTab, ['overview', 'products', 'posts', 'users', 'views', 'analytics', 'maintenance'], true)) {
    $activeTab = 'overview';
}
$viewDays = $authenticated ? max(7, min(180, (int) ($_GET['days'] ?? 30))) : 30;
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

$productQuery = $authenticated ? trim((string) ($_GET['q'] ?? '')) : '';
$allProducts = [];
$productsPage = $authenticated ? enma_page_value('products_page') : 1;
$productsPerPage = 25;
$productsTotal = 0;
$productsTotalPages = 1;
if ($authenticated && $activeTab === 'products') {
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
            'SELECT id, asin, title, category_name, last_synced_at, affiliate_url
             FROM products
             WHERE asin LIKE :q OR title LIKE :q OR category_name LIKE :q
             ORDER BY id DESC
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
            'SELECT id, asin, title, category_name, last_synced_at, affiliate_url
             FROM products
             ORDER BY id DESC
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
if ($authenticated && $activeTab === 'users') {
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
$allPosts = [];
$postsStatusFilter = $authenticated ? strtolower(trim((string) ($_GET['posts_status'] ?? 'all'))) : 'all';
if (!in_array($postsStatusFilter, ['all', 'published', 'draft'], true)) {
    $postsStatusFilter = 'all';
}
if ($authenticated && $activeTab === 'posts') {
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

$productsPagination = $authenticated && $activeTab === 'products'
    ? enma_render_pagination('products', 'products_page', $productsPage, $productsTotalPages, $productQuery !== '' ? ['q' => $productQuery] : [])
    : '';
$postsPagination = $authenticated && $activeTab === 'posts'
    ? enma_render_pagination('posts', 'posts_page', $postsPage, $postsTotalPages, $postsStatusFilter !== 'all' ? ['posts_status' => $postsStatusFilter] : [])
    : '';
$usersPagination = $authenticated && $activeTab === 'users'
    ? enma_render_pagination('users', 'users_page', $usersPage, $usersTotalPages, $userSearch !== '' ? ['user_q' => $userSearch] : [])
    : '';
$activityPagination = $authenticated && ($activeTab === 'users' || $activeTab === 'overview')
    ? enma_render_pagination($activeTab === 'overview' ? 'overview' : 'users', 'activity_page', $activityPage, $activityTotalPages, $activeTab === 'users' && $userSearch !== '' ? ['user_q' => $userSearch] : [])
    : '';

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

        function updatePostPreview($form) {
          if (!$form || $form.length === 0) {
            return;
          }

          var title = ($form.find('input[name="title"]').val() || '').trim();
          var excerpt = ($form.find('textarea[name="excerpt"]').val() || '').trim();
          var metaTitle = ($form.find('input[name="meta_title"]').val() || '').trim();
          var metaDescription = ($form.find('textarea[name="meta_description"]').val() || '').trim();
          var imageUrl = ($form.find('input[name="featured_image"]').val() || '').trim();
          var html = getContentHtml($form);
          var plainBody = stripPreviewHtml(html);
          var serpTitle = metaTitle || title || 'Post title preview';
          var serpDescription = metaDescription || excerpt || plainBody || 'Meta description preview';
          var cardTitle = title || 'Post title preview';
          var cardExcerpt = excerpt || metaDescription || plainBody || 'Post excerpt preview';

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
        .wrap { max-width: 1180px; margin: 26px auto; padding: 0 14px 28px; }
        .box {
            background: var(--panel);
            border-radius: 14px;
            border: 1px solid #e4ebf5;
            box-shadow: 0 10px 30px rgba(8, 29, 66, 0.08);
            padding: 18px;
            margin-bottom: 16px;
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
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; border-bottom: 1px solid #e9eef6; padding: 10px 8px; font-size: 14px; }
        th { color: #2c3e57; background: #f6f9fd; }
        .error { background: #ffe5e5; color: #8a1f1f; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .ok { background: #e4f8ea; color: #165f2b; padding: 10px; border-radius: 8px; margin-bottom: 10px; }
        .toplink { display: inline-block; margin-bottom: 12px; color: var(--brand); font-weight: 700; text-decoration: none; }
        .tabs { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
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
	            .post-preview-grid {
	                grid-template-columns:1fr;
	            }
	        }
	    </style>
</head>
<body>
<div class="wrap">
    <a class="toplink" href="<?= e(url('/')) ?>">Back to site</a>

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
            <a class="tab <?= $activeTab === 'overview' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=overview')) ?>">Overview</a>
            <a class="tab <?= $activeTab === 'products' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=products')) ?>">Products</a>
            <a class="tab <?= $activeTab === 'posts' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=posts')) ?>">Posts</a>
            <a class="tab <?= $activeTab === 'users' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=users')) ?>">Users</a>
            <a class="tab <?= $activeTab === 'views' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=views&days=' . $viewDays)) ?>">Views</a>
            <a class="tab <?= $activeTab === 'analytics' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=analytics')) ?>">Analytics & Seguridad</a>
            <a class="tab <?= $activeTab === 'maintenance' ? 'active' : '' ?>" href="<?= e(url('/enma/?tab=maintenance')) ?>">Maintenance</a>
        </div>

        <?php if ($activeTab === 'overview'): ?>
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
        <?php if ($editingProduct): ?>
        <section class="box">
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

        <section class="box">
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

        <section class="box">
            <h2>Products</h2>
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
            <p class="muted">Showing <?= number_format(count($allProducts)) ?> of <?= number_format($productsTotal) ?> products.</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ASIN</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Tag</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($allProducts as $item): ?>
                    <tr>
                        <td><?= (int) $item['id'] ?></td>
                        <td><?= e($item['asin']) ?></td>
                        <td><?= e($item['title']) ?></td>
                        <td><?= e($item['category_name']) ?></td>
                        <td><?= amazon_tag_present((string) ($item['affiliate_url'] ?? '')) ? 'OK' : 'Missing' ?></td>
                        <td>
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
        <?php elseif ($activeTab === 'posts'): ?>
        <?php if ($editingPost): ?>
        <section class="box">
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
	                <?php if (!empty($editingPost['featured_image'])): ?>
	                    <div style="margin-bottom:12px;">
	                        <span class="muted">Current Image:</span><br>
	                        <img src="<?= e(url($editingPost['featured_image'])) ?>" alt="Preview" style="max-width:100px;max-height:100px;border-radius:6px;margin-top:5px;border:1px solid #ddd;">
	                    </div>
	                <?php endif; ?>
	                <div class="post-preview-grid" style="margin:8px 0 14px;">
	                    <div class="post-preview-card">
	                        <h3 style="margin:0 0 10px;">Google Preview</h3>
	                        <div class="serp-preview-url"><?= e(absolute_url('/blog/' . ((string) ($editingPost['slug'] ?? 'preview-post')))) ?></div>
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

        <section class="box">
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
	                <div class="post-preview-grid" style="margin:8px 0 14px;">
	                    <div class="post-preview-card">
	                        <h3 style="margin:0 0 10px;">Google Preview</h3>
	                        <div class="serp-preview-url"><?= e(absolute_url('/blog/preview-post')) ?></div>
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

        <section class="box">
            <h2>Existing Posts</h2>
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
	        <?php elseif ($activeTab === 'users'): ?>
	        <?php if ($editingUser): ?>
	        <section class="box">
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

	        <section class="box">
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

	        <section class="box">
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

	        <section class="box">
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
            <h2>Views Dashboard</h2>
            <p style="margin: 0 0 10px; font-size: 14px; color: #334155;">Tracking window: last <?= (int) ($viewsDashboard['days'] ?? $viewDays) ?> days (from <?= e((string) ($viewsDashboard['from_date'] ?? '')) ?> UTC)</p>
            <p class="muted" style="margin: 0 0 10px;">Compared against previous window: <?= e((string) ($viewsDashboard['previous_range']['from_date'] ?? '-')) ?> to <?= e((string) ($viewsDashboard['previous_range']['to_date'] ?? '-')) ?>.</p>
            <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px;">
                <input type="hidden" name="tab" value="views">
                <div style="max-width:160px;">
                    <label>Days</label>
                    <input type="number" name="days" min="7" max="180" value="<?= (int) $viewDays ?>">
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

        <section class="box">
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

        <section class="box">
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

        <section class="box">
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

        <section class="box">
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
        <section class="box">
            <h2>Maintenance Tools</h2>
            <p class="muted" style="margin: 0 0 10px;">Only available/working tasks are shown. Last usage is tracked automatically.</p>
            <div class="box" style="margin-top:12px; margin-bottom:12px;">
                <h3 style="margin:0 0 8px;">AI Draft Generator (Gemini)</h3>
                <p class="muted" style="margin:0 0 10px;">One click in Auto mode will pick a commercial gap and create a draft. No auto-publish.</p>
                <?php $affiliateDraftForm = is_array($affiliateDraftForm ?? null) ? $affiliateDraftForm : ['auto_mode' => '1', 'topic' => '', 'keyword' => '', 'product' => '', 'category' => '', 'model' => 'gemini-2.0-flash']; ?>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="action" value="maintenance_generate_affiliate_post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div style="display:flex;gap:8px;align-items:center;margin:0 0 10px;">
                        <input type="checkbox" id="ai_auto_mode" name="auto_mode" value="1" <?= (($affiliateDraftForm['auto_mode'] ?? '1') === '1') ? 'checked' : '' ?> style="width:auto;margin:0;">
                        <label for="ai_auto_mode" style="margin:0;">Auto mode (recommended, one-click draft)</label>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label>Topic (manual override)</label>
                            <input type="text" name="topic" value="<?= e((string) ($affiliateDraftForm['topic'] ?? '')) ?>" placeholder="Best beginner telescope for city skies">
                        </div>
                        <div>
                            <label>Keyword (manual override)</label>
                            <input type="text" name="keyword" value="<?= e((string) ($affiliateDraftForm['keyword'] ?? '')) ?>" placeholder="best beginner telescope for city skies">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label>Main Product (manual override)</label>
                            <input type="text" name="product" value="<?= e((string) ($affiliateDraftForm['product'] ?? '')) ?>" placeholder="Celestron NexStar 4SE">
                        </div>
                        <div>
                            <label>Category (manual override)</label>
                            <input type="text" name="category" value="<?= e((string) ($affiliateDraftForm['category'] ?? '')) ?>" placeholder="telescopes">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end;">
                        <div>
                            <label>Model</label>
                            <input type="text" name="model" value="<?= e((string) ($affiliateDraftForm['model'] ?? 'gemini-2.0-flash')) ?>" placeholder="gemini-2.0-flash">
                        </div>
                        <div>
                            <button class="btn" type="submit">Generate Draft (One Click)</button>
                        </div>
                    </div>
                </form>
                <?php if (is_array($affiliateDraftResult ?? null)): ?>
                    <div style="margin-top:10px;border:1px solid #e2e8f0;border-radius:8px;padding:10px;background:#f8fbff;">
                        <p style="margin:0 0 8px;font-size:13px;">
                            Result:
                            <strong class="<?= !empty($affiliateDraftResult['ok']) ? 'ok' : 'fail' ?>">
                                <?= !empty($affiliateDraftResult['ok']) ? 'OK' : 'FAIL' ?>
                            </strong>
                            | Exit code: <?= (int) ($affiliateDraftResult['exit_code'] ?? 1) ?>
                        </p>
                        <?php if (!empty($affiliateDraftResult['php_binary'])): ?>
                            <p class="muted" style="margin:0 0 8px;font-size:12px;">PHP CLI used: <code><?= e((string) $affiliateDraftResult['php_binary']) ?></code></p>
                        <?php endif; ?>
                        <?php if (!empty($affiliateDraftResult['output_lines']) && is_array($affiliateDraftResult['output_lines'])): ?>
                            <?php foreach ($affiliateDraftResult['output_lines'] as $line): ?>
                                <div style="font-family:monospace;font-size:12px;"><?= e((string) $line) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            $maintenanceGroups = [
                'seo' => 'SEO',
                'daily' => 'Daily',
                'weekly' => 'Weekly',
                'as_needed' => 'As Needed',
            ];
            $availableMaintenanceTasks = $availableMaintenanceTasks ?? [];
            $maintenanceUsageMap = $maintenanceUsageMap ?? [];
            ?>
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
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($maintenanceLog !== []): ?>
                <div style="background:#f6f9fc;border:1px solid #e2e8f0;border-radius:8px;padding:10px;">
                    <?php foreach ($maintenanceLog as $line): ?>
                        <div style="font-family:monospace;font-size:13px;"><?= e($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="box">
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
        <section class="box">
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
