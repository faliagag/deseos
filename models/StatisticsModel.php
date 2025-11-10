<?php
/**
 * MODELO DE ESTADÍSTICAS - VERSIÓN 2.1
 * Generación y obtención de estadísticas públicas
 */

class StatisticsModel {
    private $pdo;
    private $config;
    private $cacheFile = 'cache/public_stats.json';
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?? include __DIR__ . '/../config/config.php';
    }
    
    /**
     * Obtener estadísticas públicas (con caché)
     */
    public function getPublicStats() {
        // Verificar caché
        $cacheDuration = $this->config['stats']['cache_duration'] ?? 3600;
        
        if (file_exists($this->cacheFile)) {
            $cacheTime = filemtime($this->cacheFile);
            if ((time() - $cacheTime) < $cacheDuration) {
                $cached = json_decode(file_get_contents($this->cacheFile), true);
                if ($cached) {
                    return $cached;
                }
            }
        }
        
        // Generar nuevas estadísticas
        $stats = $this->generateStats();
        
        // Guardar en caché
        @file_put_contents($this->cacheFile, json_encode($stats));
        
        return $stats;
    }
    
    /**
     * Generar estadísticas desde la base de datos
     */
    private function generateStats() {
        try {
            $stats = [];
            
            // Total de listas creadas
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gift_lists");
            $stats['total_lists'] = $stmt->fetchColumn();
            
            // Total de usuarios (festejados felices)
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
            $stats['happy_users'] = $stmt->fetchColumn();
            
            // Monto total entregado
            $stmt = $this->pdo->query("
                SELECT COALESCE(SUM(total_amount), 0) 
                FROM payouts 
                WHERE status = 'completed'
            ");
            $stats['total_delivered'] = $stmt->fetchColumn();
            
            // Calcular tasa de satisfacción (basado en testimonios)
            $stmt = $this->pdo->query("
                SELECT AVG(rating) 
                FROM testimonials 
                WHERE status = 'approved'
            ");
            $avgRating = $stmt->fetchColumn();
            $stats['satisfaction_rate'] = $avgRating ? round(($avgRating / 5) * 100) : 98;
            
            // Estadísticas adicionales
            $stats['active_lists'] = $this->getActiveLists();
            $stats['total_gifts'] = $this->getTotalGifts();
            $stats['total_transactions'] = $this->getTotalTransactions();
            
            // Formato amigable
            $stats['formatted'] = [
                'total_lists' => $this->formatNumber($stats['total_lists']),
                'happy_users' => $this->formatNumber($stats['happy_users']),
                'total_delivered' => $this->formatMoney($stats['total_delivered']),
                'satisfaction_rate' => $stats['satisfaction_rate'] . '%'
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Error generando estadísticas: " . $e->getMessage());
            return $this->getDefaultStats();
        }
    }
    
    private function getActiveLists() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gift_lists WHERE status = 'active'");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTotalGifts() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gifts");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function getTotalTransactions() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM transactions WHERE status = 'approved'");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    private function formatNumber($number) {
        if ($number >= 1000) {
            return number_format($number / 1000, 1) . 'K+';
        }
        return number_format($number);
    }
    
    private function formatMoney($amount) {
        if ($amount >= 1000000) {
            return '$' . number_format($amount / 1000000, 0) . 'M+';
        } elseif ($amount >= 1000) {
            return '$' . number_format($amount / 1000, 0) . 'K+';
        }
        return '$' . number_format($amount);
    }
    
    private function getDefaultStats() {
        return [
            'total_lists' => 0,
            'happy_users' => 0,
            'total_delivered' => 0,
            'satisfaction_rate' => 98,
            'formatted' => [
                'total_lists' => '0',
                'happy_users' => '0',
                'total_delivered' => '$0',
                'satisfaction_rate' => '98%'
            ]
        ];
    }
    
    /**
     * Invalidar caché
     */
    public function clearCache() {
        if (file_exists($this->cacheFile)) {
            return @unlink($this->cacheFile);
        }
        return true;
    }
}
