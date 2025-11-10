<?php
/**
 * MODELO DE TESTIMONIOS - VERSIÓN 2.1
 * Gestión de testimonios de usuarios
 */

class TestimonialModel {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?? include __DIR__ . '/../config/config.php';
    }
    
    /**
     * Crear nuevo testimonio
     */
    public function create($userId, $giftListId, $content, $rating = 5) {
        try {
            // Validar longitud
            $minLength = $this->config['testimonials']['min_length'];
            $maxLength = $this->config['testimonials']['max_length'];
            
            if (strlen($content) < $minLength || strlen($content) > $maxLength) {
                throw new Exception("El testimonio debe tener entre {$minLength} y {$maxLength} caracteres");
            }
            
            // Validar rating
            if ($rating < 1 || $rating > 5) {
                throw new Exception("La calificación debe estar entre 1 y 5");
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO testimonials (user_id, gift_list_id, content, rating, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$userId, $giftListId, $content, $rating]);
            
            return [
                'success' => true,
                'id' => $this->pdo->lastInsertId(),
                'message' => 'Testimonio creado. Será revisado por nuestro equipo.'
            ];
            
        } catch (Exception $e) {
            error_log("Error creando testimonio: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener testimonios aprobados
     */
    public function getApproved($limit = 6, $featured = null) {
        try {
            $sql = "
                SELECT t.*, u.name, u.lastname, gl.title as list_title, gl.event_type
                FROM testimonials t
                JOIN users u ON t.user_id = u.id
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                WHERE t.status = 'approved'
            ";
            
            if ($featured !== null) {
                $sql .= " AND t.is_featured = " . ($featured ? '1' : '0');
            }
            
            $sql .= " ORDER BY t.created_at DESC LIMIT ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error obteniendo testimonios: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Aprobar testimonio
     */
    public function approve($testimonialId, $adminId, $featured = false) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE testimonials 
                SET status = 'approved',
                    is_featured = ?,
                    approved_by = ?,
                    approved_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$featured ? 1 : 0, $adminId, $testimonialId]);
            
            return ['success' => true, 'message' => 'Testimonio aprobado'];
            
        } catch (Exception $e) {
            error_log("Error aprobando testimonio: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Rechazar testimonio
     */
    public function reject($testimonialId, $reason = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE testimonials 
                SET status = 'rejected',
                    rejection_reason = ?,
                    rejected_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$reason, $testimonialId]);
            
            return ['success' => true, 'message' => 'Testimonio rechazado'];
            
        } catch (Exception $e) {
            error_log("Error rechazando testimonio: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Obtener testimonios pendientes (para admin)
     */
    public function getPending($limit = 20) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, u.name, u.lastname, u.email,
                       gl.title as list_title, gl.event_type
                FROM testimonials t
                JOIN users u ON t.user_id = u.id
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                WHERE t.status = 'pending'
                ORDER BY t.created_at DESC
                LIMIT ?
            ");
            
            $stmt->execute([$limit]);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error obteniendo testimonios pendientes: " . $e->getMessage());
            return [];
        }
    }
}
