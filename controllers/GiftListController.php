<?php
// controllers/GiftListController.php

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
     * Crea una nueva lista de regalos.
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
            
            $preset_theme = isset($data["preset_theme"]) ? $data["preset_theme"] : null;
            
            $result = $this->giftListModel->create(
                $user_id,
                $data["title"],
                $data["description"],
                $data["event_type"] ?? null,
                $data["beneficiary1"] ?? null,
                $data["beneficiary2"] ?? null,
                $preset_theme
            );
            
            if (!$result) {
                ErrorHandler::logError("Error al crear lista de regalos", [
                    'user_id' => $user_id,
                    'data' => $data
                ]);
                return false;
            }
            
            $lastId = $this->pdo->lastInsertId();
            if (!$lastId) {
                ErrorHandler::logError("GiftListController::create: lastInsertId() devolvió false");
                return false;
            }
            
            return $lastId;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Muestra una lista de regalos específica.
     * 
     * @param string $unique_link Enlace único de la lista
     * @return array|bool Datos de la lista o false en caso de error
     */
    public function show($unique_link) {
        try {
            if (empty($unique_link)) {
                throw new Exception("Enlace de lista no proporcionado");
            }
            
            $list = $this->giftListModel->getByUniqueLink($unique_link);
            if (!$list) {
                return false;
            }
            
            // Obtener los regalos asociados a esta lista
            $list["gifts"] = $this->giftModel->getByGiftList($list["id"]);
            
            return $list;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza una lista de regalos existente.
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
            
            $preset_theme = isset($data["preset_theme"]) ? $data["preset_theme"] : null;
            
            $result = $this->giftListModel->update(
                $id,
                $data["title"],
                $data["description"] ?? '',
                $data["event_type"] ?? null,
                $data["beneficiary1"] ?? null,
                $data["beneficiary2"] ?? null,
                $preset_theme
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
     * Elimina una lista de regalos.
     * 
     * @param int $id ID de la lista
     * @return bool Resultado de la operación
     */
    public function delete($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID de lista no proporcionado");
            }
            
            // Verificar que la lista existe
            $list = $this->giftListModel->getById($id);
            if (!$list) {
                throw new Exception("Lista no encontrada");
            }
            
            // Eliminar todos los regalos asociados primero (esto se maneja automáticamente a través de ON DELETE CASCADE)
            $result = $this->giftListModel->delete($id);
            
            if (!$result) {
                ErrorHandler::logError("Error al eliminar lista de regalos", ['id' => $id]);
            }
            
            return $result;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Agrega un regalo a una lista.
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
            
            $price = isset($data['price']) ? floatval($data['price']) : 0;
            $stock = isset($data['stock']) ? intval($data['stock']) : 0;
            
            $result = $this->giftModel->create(
                $gift_list_id,
                $data["name"],
                isset($data["description"]) ? $data["description"] : "",
                $price,
                $stock
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
     * Obtiene todas las listas de regalos.
     * 
     * @return array Lista de regalos
     */
    public function getAll() {
        try {
            return $this->giftListModel->getAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Busca listas de regalos por palabra clave.
     * 
     * @param string $keyword Palabra clave
     * @return array Listas encontradas
     */
    public function search($keyword) {
        try {
            // Sanitizar la palabra clave
            $keyword = sanitize($keyword);
            return $this->giftListModel->search($keyword);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene listas de regalo de un usuario específico.
     * 
     * @param int $user_id ID del usuario
     * @return array Listas del usuario
     */
    public function getByUser($user_id) {
        try {
            if (empty($user_id)) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            return $this->giftListModel->getByUser($user_id);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
}