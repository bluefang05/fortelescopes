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
