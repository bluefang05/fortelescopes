<?php
namespace Enma\Controllers;

use Enma\Core\Auth;
use Enma\Core\Database;
use Enma\Models\Analytics;

class AnalyticsController {
    
    public function index() {
        if (!Auth::check()) {
            header('Location: ?action=login');
            exit;
        }

        $analytics = new Analytics();
        $countryFilter = strtoupper(trim((string) ($_GET['country'] ?? '')));
        $countryFilter = preg_replace('/[^A-Z]/', '', $countryFilter ?? '');
        $countryFilter = substr((string) $countryFilter, 0, 8);
        $excludeMine = (($_GET['exclude_mine'] ?? '1') === '1');
        $user = Auth::user();
        $currentUserId = (int) ($user['id'] ?? ($_SESSION['admin_user_id'] ?? 0));
        $excludeAuthorId = ($excludeMine && $currentUserId > 0) ? $currentUserId : 0;
        
        $data = [
            'stats' => $analytics->getDashboardStats(),
            'chart_data' => $analytics->getTrafficChartData(),
            'top_agents' => $analytics->getTopUserAgents(),
            'top_countries' => $analytics->getTopCountries(),
            'top_sources' => $analytics->getTopTrafficSources(),
            'top_referrers' => $analytics->getTopReferrers(),
            'suspicious_ips' => $analytics->getSuspiciousIPs(),
            'recent_logs' => $analytics->getRecentLogs(50),
            'post_visit_countries' => $analytics->getPostVisitCountries(80),
            'recent_post_visits' => $analytics->getRecentPostVisits(60, $countryFilter, $excludeAuthorId),
            'not_my_post_views' => $analytics->getViewsForPostsNotMine($currentUserId, $countryFilter, 40),
            'country_filter' => $countryFilter,
            'exclude_mine' => $excludeMine,
            'user' => $user
        ];

        // Preparar datos para gráficos
        $labels = array_column($data['chart_data'], 'date');
        $counts = array_column($data['chart_data'], 'count');
        
        $data['chart_labels_json'] = json_encode($labels);
        $data['chart_values_json'] = json_encode($counts);
        $data['raw_json'] = json_encode($data['recent_logs'], JSON_PRETTY_PRINT);

        include __DIR__ . '/../views/analytics/dashboard.php';
    }
}
