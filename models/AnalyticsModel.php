<?php
require_once '../includes/db.php';

class AnalyticsModel {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getConnection();
        $this->config = include '../config/config.php';
    }
    
    /**
     * Registrar evento de analytics
     */
    public function trackEvent($eventType, $userId = null, $data = []) {
        if (!$this->config['analytics']['enabled']) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO analytics_events (event_type, user_id, session_id, ip_address, 
                                        user_agent, data, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $eventType,
            $userId,
            session_id(),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($data)
        ]);
    }
    
    /**
     * Dashboard principal con métricas generales
     */
    public function getDashboardMetrics($dateFrom = null, $dateTo = null) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo);
        
        // Usuarios activos
        $activeUsers = $this->getActiveUsersCount($dateFrom, $dateTo);
        
        // Nuevos usuarios
        $newUsers = $this->getNewUsersCount($dateFrom, $dateTo);
        
        // Listas creadas
        $listsCreated = $this->getListsCreatedCount($dateFrom, $dateTo);
        
        // Transacciones
        $transactions = $this->getTransactionsMetrics($dateFrom, $dateTo);
        
        // Engagement
        $engagement = $this->getEngagementMetrics($dateFrom, $dateTo);
        
        return [
            'users' => [
                'active' => $activeUsers,
                'new' => $newUsers,
                'total' => $this->getTotalUsersCount()
            ],
            'lists' => [
                'created' => $listsCreated,
                'total' => $this->getTotalListsCount(),
                'with_purchases' => $this->getListsWithPurchasesCount($dateFrom, $dateTo)
            ],
            'transactions' => $transactions,
            'engagement' => $engagement,
            'revenue' => $this->getRevenueMetrics($dateFrom, $dateTo)
        ];
    }
    
    /**
     * Obtener usuarios activos
     */
    private function getActiveUsersCount($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo);
        
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT user_id) as active_users
            FROM analytics_events 
            WHERE user_id IS NOT NULL
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        $result = $stmt->fetch();
        return $result['active_users'] ?? 0;
    }
    
    /**
     * Obtener nuevos usuarios
     */
    private function getNewUsersCount($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as new_users
            FROM users 
            WHERE 1=1
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        $result = $stmt->fetch();
        return $result['new_users'] ?? 0;
    }
    
    /**
     * Métricas de transacciones
     */
    private function getTransactionsMetrics($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_transactions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'approved' THEN amount END) as avg_transaction_amount
            FROM transactions 
            WHERE 1=1
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        return $stmt->fetch();
    }
    
    /**
     * Métricas de engagement
     */
    private function getEngagementMetrics($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo);
        
        // Páginas vistas
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as page_views
            FROM analytics_events 
            WHERE event_type = 'page_view'
            {$dateFilter['where']}
        ");
        $stmt->execute($dateFilter['params']);
        $pageViews = $stmt->fetch()['page_views'] ?? 0;
        
        // Sesiones únicas
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT session_id) as unique_sessions
            FROM analytics_events 
            WHERE 1=1
            {$dateFilter['where']}
        ");
        $stmt->execute($dateFilter['params']);
        $uniqueSessions = $stmt->fetch()['unique_sessions'] ?? 0;
        
        // Tiempo promedio en sitio (estimado)
        $avgSessionDuration = $this->calculateAvgSessionDuration($dateFrom, $dateTo);
        
        return [
            'page_views' => $pageViews,
            'unique_sessions' => $uniqueSessions,
            'avg_session_duration' => $avgSessionDuration,
            'bounce_rate' => $this->calculateBounceRate($dateFrom, $dateTo)
        ];
    }
    
    /**
     * Métricas de ingresos
     */
    private function getRevenueMetrics($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT 
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as total_revenue,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as successful_payments,
                AVG(CASE WHEN status = 'approved' THEN amount END) as avg_order_value
            FROM transactions 
            WHERE 1=1
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        $result = $stmt->fetch();
        
        // Calcular crecimiento comparado con período anterior
        $growth = $this->calculateRevenueGrowth($dateFrom, $dateTo);
        
        return [
            'total_revenue' => $result['total_revenue'] ?? 0,
            'successful_payments' => $result['successful_payments'] ?? 0,
            'avg_order_value' => $result['avg_order_value'] ?? 0,
            'growth_percentage' => $growth
        ];
    }
    
    /**
     * Reportes detallados por período
     */
    public function getDetailedReport($type, $dateFrom, $dateTo, $groupBy = 'day') {
        switch ($type) {
            case 'users':
                return $this->getUsersReport($dateFrom, $dateTo, $groupBy);
            case 'revenue':
                return $this->getRevenueReport($dateFrom, $dateTo, $groupBy);
            case 'lists':
                return $this->getListsReport($dateFrom, $dateTo, $groupBy);
            case 'engagement':
                return $this->getEngagementReport($dateFrom, $dateTo, $groupBy);
            default:
                return [];
        }
    }
    
    /**
     * Reporte de usuarios
     */
    private function getUsersReport($dateFrom, $dateTo, $groupBy) {
        $dateFormat = $this->getDateFormat($groupBy);
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                COUNT(*) as new_users
            FROM users 
            WHERE 1=1
            {$dateFilter['where']}
            GROUP BY period
            ORDER BY period ASC
        ");
        
        $stmt->execute($dateFilter['params']);
        return $stmt->fetchAll();
    }
    
    /**
     * Reporte de ingresos
     */
    private function getRevenueReport($dateFrom, $dateTo, $groupBy) {
        $dateFormat = $this->getDateFormat($groupBy);
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '{$dateFormat}') as period,
                SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END) as revenue,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as transactions
            FROM transactions 
            WHERE 1=1
            {$dateFilter['where']}
            GROUP BY period
            ORDER BY period ASC
        ");
        
        $stmt->execute($dateFilter['params']);
        return $stmt->fetchAll();
    }
    
    /**
     * Top listas más populares
     */
    public function getTopLists($limit = 10, $dateFrom = null, $dateTo = null) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 't.created_at');
        
        $stmt = $this->db->prepare("
            SELECT 
                gl.id,
                gl.title,
                u.name as owner_name,
                COUNT(t.id) as total_purchases,
                SUM(t.amount) as total_revenue,
                gl.created_at
            FROM gift_lists gl
            JOIN users u ON gl.user_id = u.id
            LEFT JOIN transactions t ON gl.id = (
                SELECT g.gift_list_id FROM gifts g WHERE g.id = t.gift_id
            ) AND t.status = 'approved'
            {$dateFilter['where']}
            GROUP BY gl.id, gl.title, u.name, gl.created_at
            ORDER BY total_revenue DESC
            LIMIT ?
        ");
        
        $params = array_merge($dateFilter['params'], [$limit]);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Estadísticas de retención de usuarios
     */
    public function getUserRetentionStats($cohortDate = null) {
        if (!$cohortDate) {
            $cohortDate = date('Y-m-01', strtotime('-3 months'));
        }
        
        $stmt = $this->db->prepare("
            WITH user_cohorts AS (
                SELECT 
                    user_id,
                    DATE_FORMAT(MIN(created_at), '%Y-%m') as cohort_month
                FROM analytics_events 
                WHERE user_id IS NOT NULL
                GROUP BY user_id
            ),
            retention_data AS (
                SELECT 
                    uc.cohort_month,
                    COUNT(DISTINCT uc.user_id) as cohort_size,
                    COUNT(DISTINCT CASE WHEN ae.created_at >= DATE_ADD(STR_TO_DATE(CONCAT(uc.cohort_month, '-01'), '%Y-%m-%d'), INTERVAL 1 MONTH) THEN uc.user_id END) as retained_users
                FROM user_cohorts uc
                LEFT JOIN analytics_events ae ON uc.user_id = ae.user_id
                WHERE uc.cohort_month >= ?
                GROUP BY uc.cohort_month
            )
            SELECT 
                cohort_month,
                cohort_size,
                retained_users,
                ROUND((retained_users / cohort_size) * 100, 2) as retention_rate
            FROM retention_data
            ORDER BY cohort_month ASC
        ");
        
        $stmt->execute([$cohortDate]);
        return $stmt->fetchAll();
    }
    
    /**
     * Exportar datos a CSV
     */
    public function exportToCSV($reportType, $data, $filename = null) {
        if (!$filename) {
            $filename = $reportType . '_report_' . date('Y-m-d') . '.csv';
        }
        
        $filepath = '../exports/' . $filename;
        
        // Crear directorio si no existe
        if (!is_dir('../exports')) {
            mkdir('../exports', 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        if (empty($data)) {
            fclose($file);
            return false;
        }
        
        // Escribir encabezados
        $headers = array_keys($data[0]);
        fputcsv($file, $headers);
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($file, array_values($row));
        }
        
        fclose($file);
        return $filepath;
    }
    
    /**
     * Funciones auxiliares
     */
    
    private function buildDateFilter($dateFrom, $dateTo, $dateColumn = 'created_at') {
        $where = '';
        $params = [];
        
        if ($dateFrom) {
            $where .= " AND $dateColumn >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $where .= " AND $dateColumn <= ?";
            $params[] = $dateTo . ' 23:59:59';
        }
        
        return ['where' => $where, 'params' => $params];
    }
    
    private function getDateFormat($groupBy) {
        switch ($groupBy) {
            case 'hour':
                return '%Y-%m-%d %H:00:00';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            case 'year':
                return '%Y';
            default:
                return '%Y-%m-%d';
        }
    }
    
    private function getTotalUsersCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users");
        return $stmt->fetch()['total'] ?? 0;
    }
    
    private function getTotalListsCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM gift_lists");
        return $stmt->fetch()['total'] ?? 0;
    }
    
    private function getListsCreatedCount($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 'created_at');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as lists_created
            FROM gift_lists 
            WHERE 1=1
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        return $stmt->fetch()['lists_created'] ?? 0;
    }
    
    private function getListsWithPurchasesCount($dateFrom, $dateTo) {
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo, 't.created_at');
        
        $stmt = $this->db->prepare("
            SELECT COUNT(DISTINCT g.gift_list_id) as lists_with_purchases
            FROM gifts g
            JOIN transactions t ON g.id = t.gift_id
            WHERE t.status = 'approved'
            {$dateFilter['where']}
        ");
        
        $stmt->execute($dateFilter['params']);
        return $stmt->fetch()['lists_with_purchases'] ?? 0;
    }
    
    private function calculateAvgSessionDuration($dateFrom, $dateTo) {
        // Implementación simplificada
        // En una implementación real, calcularías basado en eventos de inicio y fin de sesión
        return 300; // 5 minutos promedio (placeholder)
    }
    
    private function calculateBounceRate($dateFrom, $dateTo) {
        // Implementación simplificada
        // Sesiones con solo una página vista
        $dateFilter = $this->buildDateFilter($dateFrom, $dateTo);
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT session_id) as total_sessions,
                COUNT(DISTINCT CASE WHEN page_views = 1 THEN session_id END) as bounce_sessions
            FROM (
                SELECT session_id, COUNT(*) as page_views
                FROM analytics_events
                WHERE event_type = 'page_view'
                {$dateFilter['where']}
                GROUP BY session_id
            ) session_stats
        ");
        
        $stmt->execute($dateFilter['params']);
        $result = $stmt->fetch();
        
        $totalSessions = $result['total_sessions'] ?? 1;
        $bounceSessions = $result['bounce_sessions'] ?? 0;
        
        return round(($bounceSessions / $totalSessions) * 100, 2);
    }
    
    private function calculateRevenueGrowth($dateFrom, $dateTo) {
        if (!$dateFrom || !$dateTo) {
            return 0;
        }
        
        // Calcular período anterior del mismo tamaño
        $periodDiff = (new DateTime($dateTo))->diff(new DateTime($dateFrom))->days;
        $prevDateTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        $prevDateFrom = date('Y-m-d', strtotime($prevDateTo . ' -' . $periodDiff . ' days'));
        
        // Revenue actual
        $currentRevenue = $this->getRevenueForPeriod($dateFrom, $dateTo);
        
        // Revenue período anterior
        $prevRevenue = $this->getRevenueForPeriod($prevDateFrom, $prevDateTo);
        
        if ($prevRevenue == 0) {
            return $currentRevenue > 0 ? 100 : 0;
        }
        
        return round((($currentRevenue - $prevRevenue) / $prevRevenue) * 100, 2);
    }
    
    private function getRevenueForPeriod($dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT SUM(amount) as revenue
            FROM transactions 
            WHERE status = 'approved' 
            AND created_at >= ? 
            AND created_at <= ?
        ");
        
        $stmt->execute([$dateFrom, $dateTo . ' 23:59:59']);
        $result = $stmt->fetch();
        return $result['revenue'] ?? 0;
    }
}