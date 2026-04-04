<?php
namespace Enma\Models;

use Enma\Core\Database;

class Analytics {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene estadísticas generales y de seguridad
     */
    public function getDashboardStats() {
        $stats = [];
        
        // Totales básicos
        $stats['total_views'] = $this->db->query("SELECT COUNT(*) FROM page_views")->fetchColumn();
        $stats['total_clicks'] = $this->db->query("SELECT COUNT(*) FROM outbound_clicks")->fetchColumn();
        $stats['unique_ips'] = $this->db->query("SELECT COUNT(DISTINCT ip_address) FROM page_views")->fetchColumn();

        // Detección de Bots y Ataques (Lógica heurística sobre datos existentes)
        $stats['suspected_bots'] = $this->countSuspectedBots();
        $stats['suspected_attacks'] = $this->countSuspectedAttacks();
        $stats['human_traffic'] = max(0, $stats['total_views'] - $stats['suspected_bots'] - $stats['suspected_attacks']);
        
        return $stats;
    }

    /**
     * Detecta bots basándose en User Agent
     */
    private function countSuspectedBots() {
        $sql = "SELECT COUNT(*) FROM page_views WHERE 
                user_agent LIKE '%bot%' OR 
                user_agent LIKE '%crawler%' OR 
                user_agent LIKE '%spider%' OR 
                user_agent LIKE '%googlebot%' OR 
                user_agent LIKE '%bingbot%' OR 
                user_agent LIKE '%slurp%' OR 
                user_agent LIKE '%duckduck%' OR 
                user_agent LIKE '%baidu%'";
        return $this->db->query($sql)->fetchColumn();
    }

    /**
     * Detecta posibles ataques (SQLi, XSS, Scanners) en URLs y User Agents
     */
    private function countSuspectedAttacks() {
        $sql = "SELECT COUNT(*) FROM page_views WHERE 
                url LIKE '%union%' OR 
                url LIKE '%select%' OR 
                url LIKE '%drop%' OR 
                url LIKE '%<script%' OR 
                url LIKE '%../%' OR 
                url LIKE '%etc/passwd%' OR 
                user_agent LIKE '%sqlmap%' OR 
                user_agent LIKE '%nikto%' OR 
                user_agent LIKE '%nmap%' OR 
                user_agent LIKE '%masscan%'";
        return $this->db->query($sql)->fetchColumn();
    }

    /**
     * Datos para gráfico de tráfico (últimos 7 días)
     */
    public function getTrafficChartData() {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM page_views 
                GROUP BY DATE(created_at) 
                ORDER BY date DESC LIMIT 7";
        $results = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
        return array_reverse($results);
    }

    /**
     * Top User Agents
     */
    public function getTopUserAgents($limit = 5) {
        $sql = "SELECT user_agent, COUNT(*) as count 
                FROM page_views 
                GROUP BY user_agent 
                ORDER BY count DESC LIMIT $limit";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Últimos registros crudos para exportación
     */
    public function getRecentLogs($limit = 50) {
        $sql = "SELECT * FROM page_views ORDER BY created_at DESC LIMIT $limit";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Lista de IPs sospechosas
     */
    public function getSuspiciousIPs() {
        $sql = "SELECT ip_address, COUNT(*) as attempts, MAX(user_agent) as last_agent
                FROM page_views 
                WHERE user_agent LIKE '%bot%' OR user_agent LIKE '%sqlmap%' OR user_agent LIKE '%nikto%'
                GROUP BY ip_address
                ORDER BY attempts DESC
                LIMIT 10";
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }
}
