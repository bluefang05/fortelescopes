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
        
        $data = [
            'stats' => $analytics->getDashboardStats(),
            'chart_data' => $analytics->getTrafficChartData(),
            'top_agents' => $analytics->getTopUserAgents(),
            'suspicious_ips' => $analytics->getSuspiciousIPs(),
            'recent_logs' => $analytics->getRecentLogs(50),
            'user' => Auth::user()
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
