<?php
/**
 * MODELO ADMINISTRATIVO COMPLETO - VERSIÓN 2.1
 * 
 * Funcionalidades avanzadas para el panel de administración:
 * - Métricas de ingresos y fees
 * - Gestión de payouts quincenales
 * - Moderación de testimonios
 * - Analytics avanzados
 * - Sistema de alertas
 * - Reportes y exportación
 */

class AdminModel {
    private $pdo;
    private $config;

    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    // ==================== MÉTRICAS DE REVENUE ====================
    public function getRevenueMetrics($period = 'month') {
        $sql = "SELECT 
                    SUM(base_amount) as total_revenue,
                    SUM(fee_amount) as total_fees,
                    COUNT(*) as total_transactions,
                    AVG(base_amount) as avg_transaction
                FROM transactions 
                WHERE status = 'approved' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        // Datos mensuales para gráficos
        $monthlySql = "SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          SUM(base_amount) as revenue,
                          SUM(fee_amount) as fees,
                          COUNT(*) as transactions
                      FROM transactions 
                      WHERE status = 'approved' 
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                      GROUP BY month 
                      ORDER BY month";
        
        $monthlyStmt = $this->pdo->prepare($monthlySql);
        $monthlyStmt->execute();
        $monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

        $months = array_column($monthlyData, 'month');
        $revenues = array_column($monthlyData, 'revenue');
        $fees = array_column($monthlyData, 'fees');

        return [
            'total' => $totals['total_revenue'] ?: 0,
            'fees' => $totals['total_fees'] ?: 0,
            'transactions' => $totals['total_transactions'] ?: 0,
            'avg_transaction' => $totals['avg_transaction'] ?: 0,
            'months' => $months,
            'monthly_data' => $revenues,
            'monthly_fees' => $fees
        ];
    }

    // ==================== MÉTRICAS DE USUARIOS ====================
    public function getUserMetrics() {
        // Usuarios activos (últimos 30 días)
        $activeSql = "SELECT COUNT(DISTINCT gl.user_id) as active_users
                     FROM gift_lists gl
                     WHERE gl.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     OR EXISTS (
                         SELECT 1 FROM transactions t 
                         WHERE t.gift_id IN (SELECT g.id FROM gifts g WHERE g.gift_list_id = gl.id)
                         AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                     )";
        
        $activeStmt = $this->pdo->prepare($activeSql);
        $activeStmt->execute();
        $activeUsers = $activeStmt->fetch(PDO::FETCH_ASSOC)['active_users'] ?: 0;

        // Nuevos usuarios este mes
        $newSql = "SELECT COUNT(*) as new_users 
                  FROM users 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $newStmt = $this->pdo->prepare($newSql);
        $newStmt->execute();
        $newUsers = $newStmt->fetch(PDO::FETCH_ASSOC)['new_users'] ?: 0;

        // Tasa de conversión aproximada
        $conversionSql = "SELECT 
                            (SELECT COUNT(*) FROM gift_lists WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as lists_created,
                            (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as new_users";
        
        $conversionStmt = $this->pdo->prepare($conversionSql);
        $conversionStmt->execute();
        $conversion = $conversionStmt->fetch(PDO::FETCH_ASSOC);
        
        $conversionRate = $conversion['new_users'] > 0 
            ? ($conversion['lists_created'] / $conversion['new_users']) * 100 
            : 0;

        return [
            'active' => $activeUsers,
            'new_this_month' => $newUsers,
            'conversion_rate' => round($conversionRate, 2),
            'total_registered' => $this->getTotalUsers()
        ];
    }

    // ==================== MÉTRICAS DE PAYOUTS ====================
    public function getPayoutMetrics() {
        // Próximo payout
        $nextPayoutSql = "SELECT 
                            SUM(t.base_amount) as pending_amount,
                            COUNT(DISTINCT t.buyer_email) as pending_users
                         FROM transactions t
                         WHERE t.status = 'approved' 
                         AND (t.payout_status = 'pending' OR t.payout_status IS NULL)";
        
        $nextStmt = $this->pdo->prepare($nextPayoutSql);
        $nextStmt->execute();
        $nextPayout = $nextStmt->fetch(PDO::FETCH_ASSOC);

        // Historial de payouts
        $historySql = "SELECT 
                         DATE(created_at) as date,
                         SUM(total_amount) as total_paid,
                         COUNT(*) as users_paid
                       FROM payouts 
                       WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                       GROUP BY DATE(created_at)
                       ORDER BY date DESC";
        
        $historyStmt = $this->pdo->prepare($historySql);
        $historyStmt->execute();
        $payoutHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'next_amount' => $nextPayout['pending_amount'] ?: 0,
            'pending_users' => $nextPayout['pending_users'] ?: 0,
            'next_date' => $this->getNextPayoutDate(),
            'history' => $payoutHistory
        ];
    }

    // ==================== MÉTRICAS DE TESTIMONIOS ====================
    public function getTestimonialMetrics() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    AVG(rating) as avg_rating
                FROM testimonials";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: [
            'total' => 0,
            'pending' => 0,
            'approved' => 0,
            'rejected' => 0,
            'avg_rating' => 0
        ];
    }

    // ==================== MÉTRICAS DE TRANSACCIONES ====================
    public function getTransactionMetrics() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN payment_method LIKE '%transbank%' THEN 1 ELSE 0 END) as transbank_count,
                    SUM(CASE WHEN payment_method LIKE '%mercadopago%' THEN 1 ELSE 0 END) as mercadopago_count
                FROM transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ==================== MÉTRICAS DE EVENTOS ====================
    public function getEventMetrics() {
        $sql = "SELECT 
                    gl.event_type,
                    COUNT(*) as count,
                    SUM(COALESCE(t.base_amount, 0)) as revenue
                FROM gift_lists gl
                LEFT JOIN gifts g ON gl.id = g.gift_list_id
                LEFT JOIN transactions t ON g.id = t.gift_id AND t.status = 'approved'
                WHERE gl.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY gl.event_type
                ORDER BY count DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $eventData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'types' => array_column($eventData, 'event_type'),
            'counts' => array_column($eventData, 'count'),
            'revenues' => array_column($eventData, 'revenue')
        ];
    }

    // ==================== GESTIÓN DE TESTIMONIOS ====================
    public function getPendingTestimonials($limit = 10) {
        $sql = "SELECT 
                    t.id, t.content, t.rating, t.created_at,
                    u.name as user_name, u.email as user_email
                FROM testimonials t
                JOIN users u ON t.user_id = u.id
                WHERE t.status = 'pending'
                ORDER BY t.created_at DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveTestimonial($testimonialId) {
        $sql = "UPDATE testimonials SET status = 'approved', approved_at = NOW() WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$testimonialId]);
        
        if ($result) {
            $this->logAdminActivity('testimonial_approved', ['testimonial_id' => $testimonialId]);
        }
        
        return $result;
    }

    public function rejectTestimonial($testimonialId, $reason = '') {
        $sql = "UPDATE testimonials SET status = 'rejected', rejected_at = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute([$reason, $testimonialId]);
        
        if ($result) {
            $this->logAdminActivity('testimonial_rejected', [
                'testimonial_id' => $testimonialId,
                'reason' => $reason
            ]);
        }
        
        return $result;
    }

    // ==================== GESTIÓN DE PAYOUTS ====================
    public function getUpcomingPayouts($limit = 20) {
        $sql = "SELECT 
                    u.name as user_name,
                    u.email as user_email,
                    u.id as user_id,
                    SUM(t.base_amount) as amount,
                    COUNT(t.id) as transaction_count,
                    MIN(t.created_at) as oldest_transaction
                FROM transactions t
                JOIN gifts g ON t.gift_id = g.id
                JOIN gift_lists gl ON g.gift_list_id = gl.id
                JOIN users u ON gl.user_id = u.id
                WHERE t.status = 'approved' 
                AND (t.payout_status = 'pending' OR t.payout_status IS NULL)
                AND t.created_at <= DATE_SUB(NOW(), INTERVAL 1 DAY)
                GROUP BY u.id
                HAVING amount > 1000
                ORDER BY amount DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        $payouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar fecha del próximo payout
        $nextPayoutDate = $this->getNextPayoutDate();
        foreach ($payouts as &$payout) {
            $payout['payout_date'] = $nextPayoutDate;
            $payout['status'] = 'pending';
        }

        return $payouts;
    }

    public function processPayout($userId, $amount, $method = 'bank_transfer') {
        try {
            $this->pdo->beginTransaction();

            // Insertar payout
            $payoutSql = "INSERT INTO payouts (user_id, total_amount, method, status, payout_date, created_at) 
                         VALUES (?, ?, ?, 'processed', NOW(), NOW())";
            $payoutStmt = $this->pdo->prepare($payoutSql);
            $payoutStmt->execute([$userId, $amount, $method]);
            $payoutId = $this->pdo->lastInsertId();

            // Actualizar transacciones relacionadas
            $updateSql = "UPDATE transactions t
                         JOIN gifts g ON t.gift_id = g.id
                         JOIN gift_lists gl ON g.gift_list_id = gl.id
                         SET t.payout_status = 'paid', t.payout_date = NOW(), t.payout_id = ?
                         WHERE gl.user_id = ? AND t.status = 'approved' 
                         AND (t.payout_status = 'pending' OR t.payout_status IS NULL)";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$payoutId, $userId]);

            // Log de actividad
            $this->logAdminActivity('payout_processed', [
                'user_id' => $userId,
                'amount' => $amount,
                'method' => $method,
                'payout_id' => $payoutId
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error processing payout: " . $e->getMessage());
            return false;
        }
    }

    // ==================== ALERTAS DEL SISTEMA ====================
    public function getSystemAlerts() {
        $alerts = [];

        // Verificar pagos pendientes hace más de 2 semanas
        $overdueSql = "SELECT COUNT(*) as count 
                      FROM transactions t
                      JOIN gifts g ON t.gift_id = g.id
                      JOIN gift_lists gl ON g.gift_list_id = gl.id
                      WHERE t.status = 'approved' 
                      AND (t.payout_status = 'pending' OR t.payout_status IS NULL)
                      AND t.created_at <= DATE_SUB(NOW(), INTERVAL 14 DAY)";
        $overdueStmt = $this->pdo->prepare($overdueSql);
        $overdueStmt->execute();
        $overdueCount = $overdueStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($overdueCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Pagos Atrasados',
                'message' => "Hay {$overdueCount} pagos pendientes de más de 2 semanas.",
                'action_url' => 'payouts.php?filter=overdue'
            ];
        }

        // Verificar testimonios pendientes
        $pendingTestimonialsSql = "SELECT COUNT(*) as count FROM testimonials WHERE status = 'pending'";
        $pendingStmt = $this->pdo->prepare($pendingTestimonialsSql);
        $pendingStmt->execute();
        $pendingCount = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($pendingCount > 10) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Testimonios Pendientes',
                'message' => "Hay {$pendingCount} testimonios esperando moderación.",
                'action_url' => 'testimonials.php?status=pending'
            ];
        }

        // Verificar transacciones fallidas recientes
        $failedSql = "SELECT COUNT(*) as count 
                     FROM transactions 
                     WHERE status = 'rejected' 
                     AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $failedStmt = $this->pdo->prepare($failedSql);
        $failedStmt->execute();
        $failedCount = $failedStmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($failedCount > 5) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Transacciones Fallidas',
                'message' => "Se han registrado {$failedCount} transacciones fallidas en las últimas 24 horas.",
                'action_url' => 'transactions.php?status=failed'
            ];
        }

        return $alerts;
    }

    // ==================== FUNCIONES DE APOYO ====================
    private function getNextPayoutDate() {
        // Calcular próxima fecha de pago (miércoles quincenal)
        $today = new DateTime();
        $currentDay = $today->format('w'); // 0=domingo, 3=miércoles
        
        if ($currentDay <= 3) {
            // Si es antes del miércoles, el próximo pago es este miércoles
            $daysToAdd = 3 - $currentDay;
        } else {
            // Si es después del miércoles, el próximo pago es el próximo miércoles
            $daysToAdd = 7 - $currentDay + 3;
        }
        
        $nextPayout = clone $today;
        $nextPayout->add(new DateInterval("P{$daysToAdd}D"));
        
        return $nextPayout->format('Y-m-d');
    }

    private function getTotalUsers() {
        $sql = "SELECT COUNT(*) as total FROM users";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    private function logAdminActivity($action, $data = []) {
        try {
            $sql = "INSERT INTO admin_activity_logs (admin_id, action_type, data, ip_address, user_agent, created_at) 
                   VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $_SESSION['user_id'] ?? 0, 
                $action, 
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error logging admin activity: " . $e->getMessage());
        }
    }

    // ==================== REPORTES Y EXPORTACIÓN ====================
    public function generateReport($type, $params = []) {
        switch ($type) {
            case 'revenue':
                return $this->generateRevenueReport($params);
            case 'users':
                return $this->generateUsersReport($params);
            case 'events':
                return $this->generateEventsReport($params);
            case 'dashboard':
                return $this->generateDashboardReport($params);
            default:
                return [];
        }
    }

    private function generateRevenueReport($params) {
        $startDate = $params['start_date'] ?? date('Y-m-01');
        $endDate = $params['end_date'] ?? date('Y-m-t');
        
        $sql = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as transactions,
                    SUM(t.base_amount) as revenue,
                    SUM(t.fee_amount) as fees,
                    t.payment_method
                FROM transactions t
                WHERE t.status = 'approved' 
                AND DATE(t.created_at) BETWEEN ? AND ?
                GROUP BY DATE(t.created_at), t.payment_method
                ORDER BY date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function generateDashboardReport($params) {
        return [
            'revenue' => $this->getRevenueMetrics(),
            'users' => $this->getUserMetrics(),
            'payouts' => $this->getPayoutMetrics(),
            'testimonials' => $this->getTestimonialMetrics(),
            'transactions' => $this->getTransactionMetrics(),
            'events' => $this->getEventMetrics(),
            'generated_at' => date('Y-m-d H:i:s')
        ];
    }

    // ==================== GESTIÓN DE CONFIGURACIÓN ====================
    public function updateSystemConfig($key, $value) {
        try {
            $sql = "INSERT INTO system_config (config_key, config_value, updated_at, updated_by) 
                    VALUES (?, ?, NOW(), ?) 
                    ON DUPLICATE KEY UPDATE config_value = ?, updated_at = NOW(), updated_by = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $key, 
                $value, 
                $_SESSION['user_id'] ?? 0, 
                $value, 
                $_SESSION['user_id'] ?? 0
            ]);
            
            if ($result) {
                $this->logAdminActivity('config_updated', ['key' => $key, 'value' => $value]);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error updating system config: " . $e->getMessage());
            return false;
        }
    }

    public function getSystemConfig($key = null) {
        try {
            if ($key) {
                $sql = "SELECT config_value FROM system_config WHERE config_key = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$key]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ? $result['config_value'] : null;
            } else {
                $sql = "SELECT config_key, config_value FROM system_config";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $result = [];
                foreach ($configs as $config) {
                    $result[$config['config_key']] = $config['config_value'];
                }
                return $result;
            }
        } catch (Exception $e) {
            error_log("Error getting system config: " . $e->getMessage());
            return $key ? null : [];
        }
    }

    // ==================== ESTADÍSTICAS AVANZADAS ====================
    public function getAdvancedStats($period = '30 days') {
        $sql = "SELECT 
                    -- Métricas de conversión
                    COUNT(DISTINCT gl.id) as total_lists,
                    COUNT(DISTINCT t.id) as total_purchases,
                    COUNT(DISTINCT gl.user_id) as active_users,
                    
                    -- Métricas financieras
                    SUM(t.base_amount) as gross_revenue,
                    SUM(t.fee_amount) as net_profit,
                    AVG(t.base_amount) as avg_purchase,
                    
                    -- Métricas de engagement
                    AVG(gifts_per_list.gift_count) as avg_gifts_per_list,
                    SUM(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(DISTINCT gl.id) * 100 as list_conversion_rate
                
                FROM gift_lists gl
                LEFT JOIN gifts g ON gl.id = g.gift_list_id
                LEFT JOIN transactions t ON g.id = t.gift_id AND t.status = 'approved'
                LEFT JOIN (
                    SELECT gift_list_id, COUNT(*) as gift_count
                    FROM gifts
                    GROUP BY gift_list_id
                ) gifts_per_list ON gl.id = gifts_per_list.gift_list_id
                
                WHERE gl.created_at >= DATE_SUB(NOW(), INTERVAL {$period})";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity($limit = 20) {
        $sql = "SELECT 
                    'transaction' as type,
                    CONCAT('Nueva compra de $', FORMAT(t.base_amount, 0), ' en lista \"', gl.title, '\"') as description,
                    t.created_at,
                    'success' as status_type
                FROM transactions t
                JOIN gifts g ON t.gift_id = g.id
                JOIN gift_lists gl ON g.gift_list_id = gl.id
                WHERE t.status = 'approved' AND t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'list_created' as type,
                    CONCAT('Nueva lista creada: \"', gl.title, '\" (', gl.event_type, ')') as description,
                    gl.created_at,
                    'info' as status_type
                FROM gift_lists gl
                WHERE gl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                UNION ALL
                
                SELECT 
                    'testimonial' as type,
                    CONCAT('Nuevo testimonio de ', u.name, ' (', t.rating, ' estrellas)') as description,
                    t.created_at,
                    CASE WHEN t.rating >= 4 THEN 'success' ELSE 'warning' END as status_type
                FROM testimonials t
                JOIN users u ON t.user_id = u.id
                WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                
                ORDER BY created_at DESC
                LIMIT ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatear tiempo relativo
        foreach ($activities as &$activity) {
            $activity['time_ago'] = $this->timeAgo($activity['created_at']);
        }
        
        return $activities;
    }

    private function timeAgo($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'hace ' . $time . ' segundos';
        if ($time < 3600) return 'hace ' . round($time/60) . ' minutos';
        if ($time < 86400) return 'hace ' . round($time/3600) . ' horas';
        if ($time < 2592000) return 'hace ' . round($time/86400) . ' días';
        
        return date('d/m/Y', strtotime($datetime));
    }
}
?>