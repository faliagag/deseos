<?php
// controllers/GiftListController.php - Versión actualizada

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/GiftList.php";
require_once __DIR__ . "/../models/Gift.php";
require_once __DIR__ . "/../includes/ErrorHandler.php";
require_once __DIR__ . "/../includes/helpers.php";

class GiftListController {
    private $pdo;
    private $giftListModel;
    private $giftModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->giftListModel = new GiftList($pdo);
        $this->giftModel = new Gift($pdo);
    }
    
    /**
     * Crea una nueva lista de regalos con soporte para fecha límite y visibilidad.
     * 
     * @param array $data Datos de la lista
     * @param int $user_id ID del usuario
     * @return int|bool ID de la lista creada o false en caso de error
     */
    public function create($data, $user_id) {
        try {
            // Sanitiza y valida los datos
            $data = sanitize($data);
            
            if (empty($data['title']) || empty($user_id)) {
                throw new Exception("Faltan datos requeridos (título)");
            }
            
            // Extraer todos los datos necesarios con valores por defecto para nuevos campos
            $title = $data["title"];
            $description = $data["description"] ?? "";
            $event_type = $data["event_type"] ?? null;
            $beneficiary1 = $data["beneficiary1"] ?? null;
            $beneficiary2 = $data["beneficiary2"] ?? null;
            $preset_theme = $data["preset_theme"] ?? null;
            
            // Nuevos campos
            $expiry_date = !empty($data["expiry_date"]) ? $data["expiry_date"] : null;
            $visibility = $data["visibility"] ?? "link_only";
            
            // Crear la lista usando el método actualizado
            $gift_list_id = $this->giftListModel->create(
                $user_id,
                $title,
                $description,
                $event_type,
                $beneficiary1,
                $beneficiary2,
                $preset_theme,
                $expiry_date,
                $visibility
            );
            
            if (!$gift_list_id) {
                ErrorHandler::logError("Error al crear lista de regalos", [
                    'user_id' => $user_id,
                    'data' => $data
                ]);
                return false;
            }
            
            return $gift_list_id;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza una lista de regalos existente con soporte para nuevos campos.
     * 
     * @param int $id ID de la lista
     * @param array $data Datos actualizados
     * @return bool Resultado de la operación
     */
    public function update($id, $data) {
        try {
            if (empty($id)) {
                throw new Exception("ID de lista no proporcionado");
            }
            
            // Sanitiza y valida los datos
            $data = sanitize($data);
            
            if (empty($data['title'])) {
                throw new Exception("El título es obligatorio");
            }
            
            // Extraer los datos actualizados
            $title = $data["title"];
            $description = $data["description"] ?? '';
            $event_type = $data["event_type"] ?? null;
            $beneficiary1 = $data["beneficiary1"] ?? null;
            $beneficiary2 = $data["beneficiary2"] ?? null;
            $preset_theme = $data["preset_theme"] ?? null;
            
            // Nuevos campos
            $expiry_date = isset($data["expiry_date"]) ? $data["expiry_date"] : null;
            $visibility = isset($data["visibility"]) ? $data["visibility"] : null;
            
            // Actualizar la lista
            $result = $this->giftListModel->update(
                $id,
                $title,
                $description,
                $event_type,
                $beneficiary1,
                $beneficiary2,
                $preset_theme,
                $expiry_date,
                $visibility
            );
            
            if (!$result) {
                ErrorHandler::logError("Error al actualizar lista de regalos", [
                    'id' => $id,
                    'data' => $data
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Agrega un regalo a una lista con soporte para categorías.
     * 
     * @param int $gift_list_id ID de la lista
     * @param array $data Datos del regalo
     * @return bool Resultado de la operación
     */
    public function addGift($gift_list_id, $data) {
        try {
            if (empty($gift_list_id)) {
                throw new Exception("ID de lista no proporcionado");
            }
            
            // Sanitiza y valida los datos
            $data = sanitize($data);
            
            if (empty($data['name'])) {
                throw new Exception("El nombre del regalo es obligatorio");
            }
            
            $name = $data['name'];
            $description = $data['description'] ?? '';
            $price = isset($data['price']) ? floatval($data['price']) : 0;
            $stock = isset($data['stock']) ? intval($data['stock']) : 0;
            $category_id = isset($data['category_id']) ? intval($data['category_id']) : null;
            
            // Usar el método actualizado del modelo Gift
            $result = $this->giftModel->create(
                $gift_list_id,
                $name,
                $description,
                $price,
                $stock,
                $category_id
            );
            
            if (!$result) {
                ErrorHandler::logError("Error al agregar regalo", [
                    'gift_list_id' => $gift_list_id,
                    'data' => $data
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza un regalo existente con soporte para categorías.
     * 
     * @param int $gift_id ID del regalo
     * @param array $data Datos actualizados
     * @return bool Resultado de la operación
     */
    public function updateGift($gift_id, $data) {
        try {
            if (empty($gift_id)) {
                throw new Exception("ID de regalo no proporcionado");
            }
            
            // Sanitiza y valida los datos
            $data = sanitize($data);
            
            if (empty($data['name'])) {
                throw new Exception("El nombre del regalo es obligatorio");
            }
            
            $name = $data['name'];
            $description = $data['description'] ?? '';
            $price = isset($data['price']) ? floatval($data['price']) : 0;
            $stock = isset($data['stock']) ? intval($data['stock']) : 0;
            $category_id = isset($data['category_id']) ? intval($data['category_id']) : null;
            
            // Usar el método actualizado del modelo Gift
            $result = $this->giftModel->update(
                $gift_id,
                $name,
                $description,
                $price,
                $stock,
                $category_id
            );
            
            if (!$result) {
                ErrorHandler::logError("Error al actualizar regalo", [
                    'gift_id' => $gift_id,
                    'data' => $data
                ]);
            }
            
            return $result;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Muestra una lista de regalos específica con soporte para categorías.
     * 
     * @param string $unique_link Enlace único de la lista
     * @param bool $groupByCategory Si los regalos deben agruparse por categoría
     * @return array|bool Datos de la lista o false en caso de error
     */
    public function show($unique_link, $groupByCategory = false) {
        try {
            if (empty($unique_link)) {
                throw new Exception("Enlace de lista no proporcionado");
            }
            
            $list = $this->giftListModel->getByUniqueLink($unique_link);
            if (!$list) {
                return false;
            }
            
            // Verificar si la lista ha expirado
            if (isset($list['expiry_date']) && !empty($list['expiry_date'])) {
                $expiry_date = new DateTime($list['expiry_date']);
                $today = new DateTime();
                $list['expired'] = ($today > $expiry_date);
            } else {
                $list['expired'] = false;
            }
            
            // Obtener los regalos asociados según el modo solicitado
            if ($groupByCategory) {
                $list["gifts_by_category"] = $this->giftModel->getGroupedByCategory($list["id"]);
                $list["categories"] = $this->giftModel->getAllCategories();
            } else {
                $list["gifts"] = $this->giftModel->getByGiftList($list["id"]);
            }
            
            return $list;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene todas las listas públicas disponibles.
     * 
     * @return array Lista de listas públicas
     */
    public function getPublicLists() {
        try {
            return $this->giftListModel->getPublicLists();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene todas las categorías disponibles para regalos.
     * 
     * @return array Lista de categorías
     */
    public function getAllCategories() {
        try {
            return $this->giftModel->getAllCategories();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    // Los métodos existentes se mantienen y siguen funcionando
    public function delete($id) { /* ... */ }
    public function getAll() { /* ... */ }
    public function search($keyword) { /* ... */ }
    public function getByUser($user_id) { /* ... */ }
}