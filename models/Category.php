<?php
// models/Category.php
require_once __DIR__ . '/../includes/ErrorHandler.php';

class Category {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Obtiene todas las categorías
     *
     * @return array Lista de categorías
     */
    public function getAll() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM gift_categories ORDER BY name ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene una categoría por su ID
     *
     * @param int $id ID de la categoría
     * @return array|bool Datos de la categoría o false
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM gift_categories WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Crea una nueva categoría
     *
     * @param string $name Nombre de la categoría
     * @param string $description Descripción (opcional)
     * @param string $icon Nombre del icono (Bootstrap Icons)
     * @return int|bool ID de la categoría creada o false
     */
    public function create($name, $description = '', $icon = 'gift') {
        try {
            if (empty($name)) {
                throw new Exception("El nombre de la categoría es obligatorio");
            }
            
            $stmt = $this->pdo->prepare("INSERT INTO gift_categories (name, description, icon) VALUES (?, ?, ?)");
            $result = $stmt->execute([$name, $description, $icon]);
            
            if (!$result) {
                throw new Exception("Error al crear la categoría");
            }
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza una categoría existente
     *
     * @param int $id ID de la categoría
     * @param string $name Nombre nuevo
     * @param string $description Descripción nueva
     * @param string $icon Icono nuevo
     * @return bool Resultado de la operación
     */
    public function update($id, $name, $description = '', $icon = 'gift') {
        try {
            if (empty($id) || empty($name)) {
                throw new Exception("ID y nombre son obligatorios");
            }
            
            $stmt = $this->pdo->prepare("UPDATE gift_categories SET name = ?, description = ?, icon = ? WHERE id = ?");
            return $stmt->execute([$name, $description, $icon, $id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Elimina una categoría
     *
     * @param int $id ID de la categoría
     * @return bool Resultado de la operación
     */
    public function delete($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID de categoría no proporcionado");
            }
            
            // Verificar si hay regalos usando esta categoría
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM gifts WHERE category_id = ?");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();
            
            if ($count > 0) {
                // Si hay regalos, solo desasociamos la categoría
                $stmt = $this->pdo->prepare("UPDATE gifts SET category_id = NULL WHERE category_id = ?");
                $stmt->execute([$id]);
            }
            
            // Ahora eliminamos la categoría
            $stmt = $this->pdo->prepare("DELETE FROM gift_categories WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene estadísticas sobre el uso de categorías
     *
     * @return array Estadísticas de uso
     */
    public function getStats() {
        try {
            $stmt = $this->pdo->query("
                SELECT c.id, c.name, COUNT(g.id) as gift_count
                FROM gift_categories c
                LEFT JOIN gifts g ON g.category_id = c.id
                GROUP BY c.id
                ORDER BY gift_count DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Busca categorías por nombre
     *
     * @param string $keyword Término de búsqueda
     * @return array Categorías que coinciden
     */
    public function search($keyword) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM gift_categories WHERE name LIKE ? ORDER BY name ASC");
            $stmt->execute(["%$keyword%"]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
}