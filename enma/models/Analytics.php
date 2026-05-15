<?php
namespace Enma\Models;

use Enma\Core\Database;

class Analytics {
    private $db;
    private $tableCache = [];
    private $columnCache = [];

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    private function tableExists(string $table): bool {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :table
             LIMIT 1'
        );
        $stmt->execute([':table' => $table]);
        $this->tableCache[$table] = (bool) $stmt->fetchColumn();

        return $this->tableCache[$table];
    }

    private function columnExists(string $table, string $column): bool {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        if (!$this->tableExists($table)) {
            $this->columnCache[$cacheKey] = false;
            return false;
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table
               AND column_name = :column
             LIMIT 1'
        );
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);
        $this->columnCache[$cacheKey] = (bool) $stmt->fetchColumn();

        return $this->columnCache[$cacheKey];
    }

    private function analyticsTable(): string {
        if ($this->tableExists('page_view_hits')) {
            return 'page_view_hits';
        }

        return 'page_views';
    }

    private function urlColumn(string $table): ?string {
        if ($this->columnExists($table, 'url')) {
            return 'url';
        }
        if ($this->columnExists($table, 'path')) {
            return 'path';
        }

        return null;
    }

    private function ipColumn(string $table): ?string {
        if ($this->columnExists($table, 'ip_address')) {
            return 'ip_address';
        }
        if ($this->columnExists($table, 'ip_hash')) {
            return 'ip_hash';
        }

        return null;
    }

    private function userAgentColumn(string $table): ?string {
        return $this->columnExists($table, 'user_agent') ? 'user_agent' : null;
    }

    private function createdAtColumn(string $table): ?string {
        if ($this->columnExists($table, 'created_at')) {
            return 'created_at';
        }
        if ($this->columnExists($table, 'viewed_at')) {
            return 'viewed_at';
        }
        if ($this->columnExists($table, 'last_viewed_at')) {
            return 'last_viewed_at';
        }

        return null;
    }

    private function standardPeriodsUtc(): array {
        $today = gmdate('Y-m-d');
        $weekDay = (int) gmdate('N');
        $weekStart = gmdate('Y-m-d', strtotime($today . ' -' . ($weekDay - 1) . ' days'));
        $monthStart = gmdate('Y-m-01');

        return [
            'today' => [
                'label' => 'Today',
                'from' => $today,
                'to' => $today,
            ],
            'this_week' => [
                'label' => 'This Week',
                'from' => $weekStart,
                'to' => $today,
            ],
            'this_month' => [
                'label' => 'This Month',
                'from' => $monthStart,
                'to' => $today,
            ],
        ];
    }

    private function viewsBetween(string $fromDate, string $toDate): int {
        $table = $this->analyticsTable();

        if (
            $table === 'page_views'
            && $this->columnExists('page_views', 'views')
            && $this->columnExists('page_views', 'view_date')
        ) {
            $stmt = $this->db->prepare(
                'SELECT COALESCE(SUM(views), 0)
                 FROM page_views
                 WHERE view_date BETWEEN :from_date AND :to_date'
            );
            $stmt->execute([
                ':from_date' => $fromDate,
                ':to_date' => $toDate,
            ]);
            return (int) $stmt->fetchColumn();
        }

        $dateCol = $this->columnExists($table, 'view_date') ? 'view_date' : $this->createdAtColumn($table);
        if ($dateCol === null) {
            return 0;
        }

        $where = $dateCol === 'view_date'
            ? '`view_date` BETWEEN :from_date AND :to_date'
            : "DATE(`$dateCol`) BETWEEN :from_date AND :to_date";

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM `$table`
             WHERE $where"
        );
        $stmt->execute([
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function uniqueVisitorsBetween(string $fromDate, string $toDate): int {
        $table = $this->analyticsTable();
        $ipCol = $this->ipColumn($table);
        if ($ipCol === null) {
            return 0;
        }

        $dateCol = $this->columnExists($table, 'view_date') ? 'view_date' : $this->createdAtColumn($table);
        if ($dateCol === null) {
            return 0;
        }

        $where = $dateCol === 'view_date'
            ? '`view_date` BETWEEN :from_date AND :to_date'
            : "DATE(`$dateCol`) BETWEEN :from_date AND :to_date";

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT `$ipCol`)
             FROM `$table`
             WHERE $where"
        );
        $stmt->execute([
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function clicksBetween(string $fromDate, string $toDate): int {
        if (!$this->tableExists('outbound_clicks')) {
            return 0;
        }

        if ($this->columnExists('outbound_clicks', 'click_date')) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM outbound_clicks
                 WHERE click_date BETWEEN :from_date AND :to_date'
            );
            $stmt->execute([
                ':from_date' => $fromDate,
                ':to_date' => $toDate,
            ]);
            return (int) $stmt->fetchColumn();
        }

        $dateCol = null;
        if ($this->columnExists('outbound_clicks', 'clicked_at')) {
            $dateCol = 'clicked_at';
        } elseif ($this->columnExists('outbound_clicks', 'created_at')) {
            $dateCol = 'created_at';
        }

        if ($dateCol === null) {
            return 0;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM outbound_clicks
             WHERE DATE(`$dateCol`) BETWEEN :from_date AND :to_date"
        );
        $stmt->execute([
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function buildStandardPeriodsStats(): array {
        $result = [];
        foreach ($this->standardPeriodsUtc() as $key => $period) {
            $fromDate = (string) ($period['from'] ?? '');
            $toDate = (string) ($period['to'] ?? '');
            $result[$key] = [
                'label' => (string) ($period['label'] ?? ''),
                'from' => $fromDate,
                'to' => $toDate,
                'views' => $this->viewsBetween($fromDate, $toDate),
                'unique_visitors' => $this->uniqueVisitorsBetween($fromDate, $toDate),
                'clicks' => $this->clicksBetween($fromDate, $toDate),
            ];
        }

        return $result;
    }

    /**
     * Obtiene estadisticas generales y de seguridad.
     */
    public function getDashboardStats(): array {
        $stats = [];
        $table = $this->analyticsTable();
        $ipCol = $this->ipColumn($table);

        if ($table === 'page_views' && $this->columnExists('page_views', 'views')) {
            $stats['total_views'] = (int) $this->db->query('SELECT COALESCE(SUM(views), 0) FROM page_views')->fetchColumn();
        } else {
            $stats['total_views'] = (int) $this->db->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        }

        $stats['total_clicks'] = (int) $this->db->query('SELECT COUNT(*) FROM outbound_clicks')->fetchColumn();
        $stats['unique_ips'] = $ipCol !== null
            ? (int) $this->db->query("SELECT COUNT(DISTINCT `$ipCol`) FROM `$table`")->fetchColumn()
            : 0;

        $stats['suspected_bots'] = (int) $this->countSuspectedBots();
        $stats['suspected_attacks'] = (int) $this->countSuspectedAttacks();
        $stats['human_traffic'] = max(0, $stats['total_views'] - $stats['suspected_bots'] - $stats['suspected_attacks']);
        $stats['periods'] = $this->buildStandardPeriodsStats();

        return $stats;
    }

    /**
     * Monetization-focused metrics for the dashboard.
     */
    public function getMonetizationMetrics(int $days = 30): array {
        $days = max(1, min(180, $days));
        $fromDate = gmdate('Y-m-d', strtotime('-' . ($days - 1) . ' days'));

        $pageViews = 0;
        $productViews = 0;
        $outboundClicks = 0;

        if ($this->tableExists('page_views') && $this->columnExists('page_views', 'view_date') && $this->columnExists('page_views', 'views')) {
            $pageViewsStmt = $this->db->prepare(
                'SELECT COALESCE(SUM(views), 0)
                 FROM page_views
                 WHERE view_date >= :from_date'
            );
            $pageViewsStmt->execute([':from_date' => $fromDate]);
            $pageViews = (int) $pageViewsStmt->fetchColumn();

            $productViewsStmt = $this->db->prepare(
                'SELECT COALESCE(SUM(views), 0)
                 FROM page_views
                 WHERE view_date >= :from_date
                   AND page_type = "product"'
            );
            $productViewsStmt->execute([':from_date' => $fromDate]);
            $productViews = (int) $productViewsStmt->fetchColumn();
        }

        if ($this->tableExists('outbound_clicks') && $this->columnExists('outbound_clicks', 'click_date')) {
            $clicksStmt = $this->db->prepare(
                'SELECT COUNT(*)
                 FROM outbound_clicks
                 WHERE click_date >= :from_date'
            );
            $clicksStmt->execute([':from_date' => $fromDate]);
            $outboundClicks = (int) $clicksStmt->fetchColumn();
        }

        $ctrByPage = $this->getCtrByPageRows($fromDate);
        $ctrByProduct = $this->getCtrByProductRows($fromDate);
        $ctrByGuide = $this->getCtrByGuideRows($fromDate);

        $topPagesLowCtr = array_values(array_filter($ctrByPage, static function (array $row): bool {
            return ((int) ($row['views'] ?? 0)) >= 30;
        }));
        usort($topPagesLowCtr, static function (array $a, array $b): int {
            $ctrCmp = ((float) ($a['ctr_percent'] ?? 0.0)) <=> ((float) ($b['ctr_percent'] ?? 0.0));
            if ($ctrCmp !== 0) {
                return $ctrCmp;
            }
            return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
        });
        $topPagesLowCtr = array_slice($topPagesLowCtr, 0, 15);

        $topProductsHighCtr = $ctrByProduct;
        usort($topProductsHighCtr, static function (array $a, array $b): int {
            $ctrCmp = ((float) ($b['ctr_percent'] ?? 0.0)) <=> ((float) ($a['ctr_percent'] ?? 0.0));
            if ($ctrCmp !== 0) {
                return $ctrCmp;
            }
            return ((int) ($b['clicks'] ?? 0)) <=> ((int) ($a['clicks'] ?? 0));
        });
        $topProductsHighCtr = array_values(array_filter($topProductsHighCtr, static function (array $row): bool {
            return ((int) ($row['views'] ?? 0)) >= 20;
        }));
        $topProductsHighCtr = array_slice($topProductsHighCtr, 0, 15);

        return [
            'days' => $days,
            'from_date' => $fromDate,
            'funnel' => [
                'page_views' => $pageViews,
                'product_page_views' => $productViews,
                'outbound_clicks' => $outboundClicks,
                'page_to_product_percent' => $pageViews > 0 ? (($productViews / $pageViews) * 100) : 0.0,
                'product_to_click_percent' => $productViews > 0 ? (($outboundClicks / $productViews) * 100) : 0.0,
            ],
            'ctr_by_page' => array_slice($ctrByPage, 0, 40),
            'ctr_by_product' => array_slice($ctrByProduct, 0, 40),
            'ctr_by_guide' => array_slice($ctrByGuide, 0, 40),
            'top_pages_low_ctr' => $topPagesLowCtr,
            'top_products_high_ctr' => $topProductsHighCtr,
        ];
    }

    private function getCtrByPageRows(string $fromDate): array {
        if (!$this->tableExists('page_views') || !$this->tableExists('outbound_clicks')) {
            return [];
        }

        $viewsStmt = $this->db->prepare(
            'SELECT path, SUM(views) AS views
             FROM page_views
             WHERE view_date >= :from_date
             GROUP BY path'
        );
        $viewsStmt->execute([':from_date' => $fromDate]);
        $viewsRows = $viewsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $clicksStmt = $this->db->prepare(
            'SELECT from_path AS path, COUNT(*) AS clicks
             FROM outbound_clicks
             WHERE click_date >= :from_date
             GROUP BY from_path'
        );
        $clicksStmt->execute([':from_date' => $fromDate]);
        $clickRows = $clicksStmt->fetchAll(\PDO::FETCH_ASSOC);

        $byPath = [];
        foreach ($viewsRows as $row) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }
            $byPath[$path] = [
                'path' => $path,
                'views' => (int) ($row['views'] ?? 0),
                'clicks' => 0,
                'ctr_percent' => 0.0,
            ];
        }

        foreach ($clickRows as $row) {
            $path = (string) ($row['path'] ?? '');
            if ($path === '') {
                continue;
            }
            if (!isset($byPath[$path])) {
                $byPath[$path] = ['path' => $path, 'views' => 0, 'clicks' => 0, 'ctr_percent' => 0.0];
            }
            $byPath[$path]['clicks'] = (int) ($row['clicks'] ?? 0);
        }

        foreach ($byPath as &$row) {
            $views = (int) ($row['views'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $row['ctr_percent'] = $views > 0 ? (($clicks / $views) * 100) : 0.0;
        }
        unset($row);

        $rows = array_values($byPath);
        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
        });

        return $rows;
    }

    private function getCtrByProductRows(string $fromDate): array {
        if (!$this->tableExists('page_views') || !$this->tableExists('outbound_clicks') || !$this->tableExists('products')) {
            return [];
        }

        $viewsStmt = $this->db->prepare(
            'SELECT pv.product_id, MAX(p.title) AS title, MAX(p.slug) AS slug, SUM(pv.views) AS views
             FROM page_views pv
             JOIN products p ON p.id = pv.product_id
             WHERE pv.view_date >= :from_date
               AND pv.page_type = "product"
               AND pv.product_id > 0
             GROUP BY pv.product_id'
        );
        $viewsStmt->execute([':from_date' => $fromDate]);
        $viewsRows = $viewsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $clicksStmt = $this->db->prepare(
            'SELECT product_id, COUNT(*) AS clicks
             FROM outbound_clicks
             WHERE click_date >= :from_date
               AND product_id > 0
             GROUP BY product_id'
        );
        $clicksStmt->execute([':from_date' => $fromDate]);
        $clickRows = $clicksStmt->fetchAll(\PDO::FETCH_ASSOC);

        $byProduct = [];
        foreach ($viewsRows as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $byProduct[$id] = [
                'product_id' => $id,
                'title' => (string) ($row['title'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'views' => (int) ($row['views'] ?? 0),
                'clicks' => 0,
                'ctr_percent' => 0.0,
            ];
        }

        foreach ($clickRows as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            if ($id <= 0 || !isset($byProduct[$id])) {
                continue;
            }
            $byProduct[$id]['clicks'] = (int) ($row['clicks'] ?? 0);
        }

        foreach ($byProduct as &$row) {
            $views = (int) ($row['views'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $row['ctr_percent'] = $views > 0 ? (($clicks / $views) * 100) : 0.0;
        }
        unset($row);

        $rows = array_values($byProduct);
        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
        });

        return $rows;
    }

    private function getCtrByGuideRows(string $fromDate): array {
        if (!$this->tableExists('page_views') || !$this->tableExists('outbound_clicks') || !$this->tableExists('posts')) {
            return [];
        }

        $viewsStmt = $this->db->prepare(
            'SELECT pv.page_slug, MAX(p.title) AS title, SUM(pv.views) AS views
             FROM page_views pv
             LEFT JOIN posts p ON p.slug = pv.page_slug AND p.post_type = "guide"
             WHERE pv.view_date >= :from_date
               AND pv.page_type = "guide"
               AND pv.page_slug <> ""
             GROUP BY pv.page_slug'
        );
        $viewsStmt->execute([':from_date' => $fromDate]);
        $viewsRows = $viewsStmt->fetchAll(\PDO::FETCH_ASSOC);

        $clicksStmt = $this->db->prepare(
            'SELECT from_path, COUNT(*) AS clicks
             FROM outbound_clicks
             WHERE click_date >= :from_date
               AND from_path LIKE "/%"
             GROUP BY from_path'
        );
        $clicksStmt->execute([':from_date' => $fromDate]);
        $clickRows = $clicksStmt->fetchAll(\PDO::FETCH_ASSOC);

        $clicksBySlug = [];
        foreach ($clickRows as $row) {
            $path = trim((string) ($row['from_path'] ?? ''));
            if (!preg_match('#^/([^/?]+)$#', $path, $m)) {
                continue;
            }
            $slug = (string) ($m[1] ?? '');
            if ($slug === '') {
                continue;
            }
            $clicksBySlug[$slug] = (int) ($row['clicks'] ?? 0);
        }

        $rows = [];
        foreach ($viewsRows as $row) {
            $slug = (string) ($row['page_slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $views = (int) ($row['views'] ?? 0);
            $clicks = (int) ($clicksBySlug[$slug] ?? 0);
            $rows[] = [
                'slug' => $slug,
                'title' => (string) (($row['title'] ?? '') !== '' ? $row['title'] : $slug),
                'views' => $views,
                'clicks' => $clicks,
                'ctr_percent' => $views > 0 ? (($clicks / $views) * 100) : 0.0,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return ((int) ($b['views'] ?? 0)) <=> ((int) ($a['views'] ?? 0));
        });

        return $rows;
    }

    /**
     * Detecta bots basandose en User Agent.
     */
    private function countSuspectedBots(): int {
        $table = $this->analyticsTable();
        $uaCol = $this->userAgentColumn($table);
        if ($uaCol === null) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `$table` WHERE
                `$uaCol` LIKE '%bot%' OR
                `$uaCol` LIKE '%crawler%' OR
                `$uaCol` LIKE '%spider%' OR
                `$uaCol` LIKE '%googlebot%' OR
                `$uaCol` LIKE '%bingbot%' OR
                `$uaCol` LIKE '%slurp%' OR
                `$uaCol` LIKE '%duckduck%' OR
                `$uaCol` LIKE '%baidu%'";

        return (int) $this->db->query($sql)->fetchColumn();
    }

    /**
     * Detecta posibles ataques (SQLi, XSS, scanners) en URL y User Agent.
     */
    private function countSuspectedAttacks(): int {
        $table = $this->analyticsTable();
        $urlCol = $this->urlColumn($table);
        $uaCol = $this->userAgentColumn($table);

        $conditions = [];

        if ($urlCol !== null) {
            $conditions[] = "`$urlCol` LIKE '%union%'";
            $conditions[] = "`$urlCol` LIKE '%select%'";
            $conditions[] = "`$urlCol` LIKE '%drop%'";
            $conditions[] = "`$urlCol` LIKE '%<script%'";
            $conditions[] = "`$urlCol` LIKE '%../%'";
            $conditions[] = "`$urlCol` LIKE '%etc/passwd%'";
        }

        if ($uaCol !== null) {
            $conditions[] = "`$uaCol` LIKE '%sqlmap%'";
            $conditions[] = "`$uaCol` LIKE '%nikto%'";
            $conditions[] = "`$uaCol` LIKE '%nmap%'";
            $conditions[] = "`$uaCol` LIKE '%masscan%'";
        }

        if ($conditions === []) {
            return 0;
        }

        $sql = "SELECT COUNT(*) FROM `$table` WHERE " . implode(' OR ', $conditions);

        return (int) $this->db->query($sql)->fetchColumn();
    }

    /**
     * Datos para grafico de trafico (ultimos 7 dias).
     */
    public function getTrafficChartData(): array {
        $table = $this->analyticsTable();
        $dateCol = $this->columnExists($table, 'view_date') ? 'view_date' : $this->createdAtColumn($table);

        if ($dateCol === null) {
            return [];
        }

        $sql = "SELECT DATE(`$dateCol`) AS date, COUNT(*) AS count
                FROM `$table`
                GROUP BY DATE(`$dateCol`)
                ORDER BY date DESC
                LIMIT 7";

        $results = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return array_reverse($results);
    }

    /**
     * Top User Agents.
     */
    public function getTopUserAgents($limit = 5): array {
        $table = $this->analyticsTable();
        $uaCol = $this->userAgentColumn($table);
        if ($uaCol === null) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $sql = "SELECT `$uaCol` AS user_agent, COUNT(*) AS count
                FROM `$table`
                GROUP BY `$uaCol`
                ORDER BY count DESC
                LIMIT $limit";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Top paises de origen por codigo.
     */
    public function getTopCountries(int $limit = 8): array {
        $table = $this->analyticsTable();
        if (!$this->columnExists($table, 'country_code')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $sql = "SELECT country_code, COUNT(*) AS count
                FROM `$table`
                GROUP BY country_code
                ORDER BY count DESC
                LIMIT $limit";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Top fuentes de trafico.
     */
    public function getTopTrafficSources(int $limit = 8): array {
        $table = $this->analyticsTable();
        if (!$this->columnExists($table, 'source_type')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $sql = "SELECT source_type, COUNT(*) AS count
                FROM `$table`
                GROUP BY source_type
                ORDER BY count DESC
                LIMIT $limit";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Top sitios de referencia.
     */
    public function getTopReferrers(int $limit = 8): array {
        $table = $this->analyticsTable();
        if (!$this->columnExists($table, 'referrer_host')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $sql = "SELECT referrer_host, COUNT(*) AS count
                FROM `$table`
                GROUP BY referrer_host
                ORDER BY count DESC
                LIMIT $limit";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Country codes available for post/guide visits.
     */
    public function getPostVisitCountries(int $limit = 50): array {
        if (!$this->tableExists('page_view_hits') || !$this->columnExists('page_view_hits', 'country_code')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $stmt = $this->db->prepare(
            "SELECT country_code, COUNT(*) AS count
             FROM page_view_hits
             WHERE page_type IN ('post', 'guide')
             GROUP BY country_code
             ORDER BY count DESC
             LIMIT $limit"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Latest raw visits for post and guide pages.
     */
    public function getRecentPostVisits(int $limit = 50, string $countryCode = '', int $excludeAuthorUserId = 0): array {
        if (!$this->tableExists('page_view_hits')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $params = [];
        $where = ["h.page_type IN ('post', 'guide')"];

        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode !== '') {
            $where[] = 'h.country_code = :country_code';
            $params[':country_code'] = substr($countryCode, 0, 8);
        }

        if ($excludeAuthorUserId > 0 && $this->columnExists('posts', 'created_by_user_id')) {
            $where[] = '(p.created_by_user_id IS NULL OR p.created_by_user_id <> :exclude_author_user_id)';
            $params[':exclude_author_user_id'] = $excludeAuthorUserId;
        }

        $sql = "SELECT
                    h.id,
                    h.viewed_at,
                    h.country_code,
                    h.path,
                    h.page_type,
                    h.page_slug,
                    h.source_type,
                    h.referrer_host,
                    p.id AS post_id,
                    p.title AS post_title,
                    p.post_type,
                    p.created_by_user_id
                FROM page_view_hits h
                LEFT JOIN posts p
                    ON p.slug = h.page_slug
                   AND p.post_type = h.page_type
                WHERE " . implode(' AND ', $where) . "
                ORDER BY h.id DESC
                LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * View counts for posts not authored by a specific admin.
     */
    public function getViewsForPostsNotMine(int $authorUserId, string $countryCode = '', int $limit = 30): array {
        if ($authorUserId <= 0 || !$this->tableExists('page_view_hits') || !$this->columnExists('posts', 'created_by_user_id')) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $params = [':author_user_id' => $authorUserId];
        $where = [
            "h.page_type IN ('post', 'guide')",
            '(p.created_by_user_id IS NULL OR p.created_by_user_id <> :author_user_id)',
        ];

        $countryCode = strtoupper(trim($countryCode));
        if ($countryCode !== '') {
            $where[] = 'h.country_code = :country_code';
            $params[':country_code'] = substr($countryCode, 0, 8);
        }

        $sql = "SELECT
                    p.id AS post_id,
                    p.title AS post_title,
                    p.slug AS post_slug,
                    p.post_type,
                    p.created_by_user_id,
                    COUNT(*) AS total_views
                FROM page_view_hits h
                LEFT JOIN posts p
                    ON p.slug = h.page_slug
                   AND p.post_type = h.page_type
                WHERE " . implode(' AND ', $where) . "
                GROUP BY p.id, p.title, p.slug, p.post_type, p.created_by_user_id
                ORDER BY total_views DESC
                LIMIT $limit";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Ultimos registros crudos para exportacion.
     */
    public function getRecentLogs($limit = 50): array {
        $table = $this->analyticsTable();
        $urlCol = $this->urlColumn($table);
        $ipCol = $this->ipColumn($table);
        $uaCol = $this->userAgentColumn($table);
        $createdCol = $this->createdAtColumn($table);

        if ($createdCol === null) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $urlExpr = $urlCol !== null ? "`$urlCol`" : "''";
        $ipExpr = $ipCol !== null ? "`$ipCol`" : "''";
        $uaExpr = $uaCol !== null ? "`$uaCol`" : "''";

        $sql = "SELECT
                    `id`,
                    `$createdCol` AS created_at,
                    $urlExpr AS url,
                    $ipExpr AS ip_address,
                    $uaExpr AS user_agent
                FROM `$table`
                ORDER BY `$createdCol` DESC
                LIMIT $limit";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Lista de IPs sospechosas.
     */
    public function getSuspiciousIPs(): array {
        $table = $this->analyticsTable();
        $ipCol = $this->ipColumn($table);
        $uaCol = $this->userAgentColumn($table);

        if ($ipCol === null || $uaCol === null) {
            return [];
        }

        $sql = "SELECT `$ipCol` AS ip_address, COUNT(*) AS attempts, MAX(`$uaCol`) AS last_agent
                FROM `$table`
                WHERE `$uaCol` LIKE '%bot%' OR `$uaCol` LIKE '%sqlmap%' OR `$uaCol` LIKE '%nikto%'
                GROUP BY `$ipCol`
                ORDER BY attempts DESC
                LIMIT 10";

        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
