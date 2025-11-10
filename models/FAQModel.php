<?php
/**
 * MODELO DE FAQs - VERSIÓN 2.1
 * Gestión de preguntas frecuentes
 */

class FAQModel {
    private $pdo;
    private $config;
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?? include __DIR__ . '/../config/config.php';
    }
    
    /**
     * Obtener FAQs por categoría
     */
    public function getByCategory($category = null, $limit = 20) {
        try {
            $sql = "SELECT * FROM faqs WHERE status = 'active'";
            $params = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY order_index ASC, created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Error obteniendo FAQs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener todas las categorías de FAQs
     */
    public function getCategories() {
        return $this->config['faqs']['categories'] ?? [];
    }
    
    /**
     * Crear nueva FAQ
     */
    public function create($category, $question, $answer, $orderIndex = 0) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO faqs (category, question, answer, order_index, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([$category, $question, $answer, $orderIndex]);
            
            return [
                'success' => true,
                'id' => $this->pdo->lastInsertId()
            ];
            
        } catch (Exception $e) {
            error_log("Error creando FAQ: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Actualizar FAQ
     */
    public function update($faqId, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['question'])) {
                $fields[] = "question = ?";
                $params[] = $data['question'];
            }
            if (isset($data['answer'])) {
                $fields[] = "answer = ?";
                $params[] = $data['answer'];
            }
            if (isset($data['category'])) {
                $fields[] = "category = ?";
                $params[] = $data['category'];
            }
            if (isset($data['order_index'])) {
                $fields[] = "order_index = ?";
                $params[] = $data['order_index'];
            }
            if (isset($data['status'])) {
                $fields[] = "status = ?";
                $params[] = $data['status'];
            }
            
            if (empty($fields)) {
                throw new Exception("No hay datos para actualizar");
            }
            
            $params[] = $faqId;
            $sql = "UPDATE faqs SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Error actualizando FAQ: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Eliminar FAQ
     */
    public function delete($faqId) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM faqs WHERE id = ?");
            $stmt->execute([$faqId]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            error_log("Error eliminando FAQ: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Incrementar contador de vistas
     */
    public function incrementViews($faqId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE faqs SET view_count = view_count + 1 WHERE id = ?");
            return $stmt->execute([$faqId]);
        } catch (Exception $e) {
            error_log("Error incrementando vistas: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Marcar FAQ como útil
     */
    public function markHelpful($faqId) {
        try {
            $stmt = $this->pdo->prepare("UPDATE faqs SET helpful_count = helpful_count + 1 WHERE id = ?");
            return $stmt->execute([$faqId]);
        } catch (Exception $e) {
            error_log("Error marcando como útil: " . $e->getMessage());
            return false;
        }
    }
}
