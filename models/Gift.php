<?php
// models/Gift.php - Versión actualizada con soporte para categorías
require_once __DIR__ . '/../includes/ErrorHandler.php';

class Gift {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crea un nuevo regalo para una lista con soporte para categorías.
     *
     * @param int $gift_list_id
     * @param string $name
     * @param string $description
     * @param float $price
     * @param int $stock
     * @param int|null $category_id ID de la categoría del regalo
     * @return bool Resultado de la operación.
     */
    public function create($gift_list_id, $name, $description, $price, $stock, $category_id = null) {
        try {
            // Validar datos obligatorios
            if (empty($gift_list_id) || empty($name)) {
                throw new Exception("ID de lista y nombre del regalo son obligatorios");
            }
            
            // Validar valores numéricos
            $price = floatval($price);
            $stock = intval($stock);
            
            if ($price < 0) {
                throw new Exception("El precio no puede ser negativo");
            }
            
            if ($stock < 0) {
                throw new Exception("El stock no puede ser negativo");
            }
            
            // Insertar el regalo con soporte para categoría
            $sql = "INSERT INTO gifts 
                   (gift_list_id, name, description, price, stock, sold, contributed, category_id, created_at) 
                   VALUES (?, ?, ?, ?, ?, 0, 0, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$gift_list_id, $name, $description, $price, $stock, $category_id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza un regalo con soporte para categorías.
     *
     * @param int $id
     * @param string $name
     * @param string $description
     * @param float $price
     * @param int $stock
     * @param int|null $category_id
     * @return bool Resultado de la operación.
     */
    public function update($id, $name, $description, $price, $stock, $category_id = null) {
        try {
            // Validar datos obligatorios
            if (empty($id) || empty($name)) {
                throw new Exception("ID y nombre del regalo son obligatorios");
            }
            
            // Validar valores numéricos
            $price = floatval($price);
            $stock = intval($stock);
            
            if ($price < 0) {
                throw new Exception("El precio no puede ser negativo");
            }
            
            if ($stock < 0) {
                throw new Exception("El stock no puede ser negativo");
            }
            
            // Actualizar el regalo
            $sql = "UPDATE gifts 
                   SET name = ?, description = ?, price = ?, stock = ?, category_id = ? 
                   WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$name, $description, $price, $stock, $category_id, $id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene todas las categorías disponibles.
     *
     * @return array Lista de categorías.
     */
    public function getAllCategories() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM gift_categories ORDER BY name ASC");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene los regalos por categoría de una lista específica.
     *
     * @param int $gift_list_id
     * @param int $category_id
     * @return array Lista de regalos en esa categoría.
     */
    public function getByCategory($gift_list_id, $category_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM gifts 
                WHERE gift_list_id = ? AND category_id = ?
                ORDER BY name ASC
            ");
            $stmt->execute([$gift_list_id, $category_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene los regalos agrupados por categoría para una lista específica.
     *
     * @param int $gift_list_id
     * @return array Regalos agrupados por categoría.
     */
    public function getGroupedByCategory($gift_list_id) {
        try {
            $result = [];
            
            // Primero obtenemos todas las categorías
            $categories = $this->getAllCategories();
            
            // Luego obtenemos todos los regalos de la lista
            $stmt = $this->pdo->prepare("
                SELECT g.*, c.name as category_name 
                FROM gifts g
                LEFT JOIN gift_categories c ON g.category_id = c.id
                WHERE g.gift_list_id = ?
                ORDER BY g.category_id, g.name
            ");
            $stmt->execute([$gift_list_id]);
            $gifts = $stmt->fetchAll();
            
            // Creamos un array con todas las categorías, incluso las vacías
            foreach ($categories as $category) {
                $result[$category['id']] = [
                    'category' => $category,
                    'gifts' => []
                ];
            }
            
            // Añadimos una categoría "Sin categoría" para regalos sin categorizar
            $result['uncategorized'] = [
                'category' => ['id' => 'uncategorized', 'name' => 'Sin categoría', 'description' => 'Regalos sin categoría asignada'],
                'gifts' => []
            ];
            
            // Agrupamos los regalos por categoría
            foreach ($gifts as $gift) {
                $catId = $gift['category_id'] ? $gift['category_id'] : 'uncategorized';
                $result[$catId]['gifts'][] = $gift;
            }
            
            // Filtramos las categorías sin regalos si es necesario
            $result = array_filter($result, function($item) {
                return !empty($item['gifts']);
            });
            
            return $result;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    // Los métodos existentes se mantienen y siguen funcionando
    public function getById($id) { /* ... */ }
    public function getByGiftList($gift_list_id) { /* ... */ }
    public function delete($id) { /* ... */ }
    public function purchase($gift_id, $quantity) { /* ... */ }
}