<?php
// models/GiftList.php

require_once __DIR__ . '/../includes/ErrorHandler.php';

class GiftList {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Inserta una nueva lista de regalos.
     *
     * @param int    $user_id
     * @param string $title
     * @param string $description
     * @param string $event_type
     * @param string $beneficiary1
     * @param string|null $beneficiary2
     * @param int|null $preset_theme
     * @return bool Resultado de la inserción.
     */
    public function create($user_id, $title, $description, $event_type, $beneficiary1, $beneficiary2 = null, $preset_theme = null) {
        try {
            // Validar datos obligatorios
            if (!$user_id || !$title) {
                throw new Exception("Faltan datos obligatorios (user_id, title)");
            }
            
            // Generar un unique_link único
            $unique_link = $this->generateUniqueLink();
            
            // Preparar la consulta
            $sql = "INSERT INTO gift_lists (user_id, title, description, event_type, beneficiary1, beneficiary2, preset_theme, unique_link, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $user_id, 
                $title, 
                $description, 
                $event_type, 
                $beneficiary1, 
                $beneficiary2, 
                $preset_theme, 
                $unique_link
            ]);
            
            if (!$result) {
                error_log("GiftList::create error: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza una lista de regalos.
     *
     * @param int    $id
     * @param string $title
     * @param string $description
     * @param string $event_type
     * @param string $beneficiary1
     * @param string|null $beneficiary2
     * @param int|null $preset_theme
     * @return bool Resultado de la actualización.
     */
    public function update($id, $title, $description, $event_type, $beneficiary1, $beneficiary2 = null, $preset_theme = null) {
        try {
            // Validar datos obligatorios
            if (!$id || !$title) {
                throw new Exception("Faltan datos obligatorios (id, title)");
            }
            
            // Preparar la consulta
            $sql = "UPDATE gift_lists 
                    SET title = ?, description = ?, event_type = ?, beneficiary1 = ?, beneficiary2 = ?, preset_theme = ? 
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $title, 
                $description, 
                $event_type, 
                $beneficiary1, 
                $beneficiary2, 
                $preset_theme, 
                $id
            ]);
            
            if (!$result) {
                error_log("GiftList::update error: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Elimina una lista.
     *
     * @param int $id
     * @return bool Resultado de la eliminación.
     */
    public function delete($id) {
        try {
            // Validar datos obligatorios
            if (!$id) {
                throw new Exception("Falta el ID de la lista");
            }
            
            // Preparar la consulta
            $sql = "DELETE FROM gift_lists WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([$id]);
            
            if (!$result) {
                error_log("GiftList::delete error: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene una lista por su ID.
     *
     * @param int $id
     * @return array|bool Datos de la lista o false.
     */
    public function getById($id) {
        try {
            $sql = "SELECT * FROM gift_lists WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene una lista por unique_link.
     *
     * @param string $unique_link
     * @return array|bool Datos de la lista o false.
     */
    public function getByUniqueLink($unique_link) {
        try {
            $sql = "SELECT * FROM gift_lists WHERE unique_link = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$unique_link]);
            return $stmt->fetch();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Retorna todas las listas.
     *
     * @return array Lista de regalos.
     */
    public function getAll() {
        try {
            $sql = "SELECT * FROM gift_lists ORDER BY created_at DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Busca listas por título o descripción.
     *
     * @param string $keyword
     * @return array Listas que coinciden con la búsqueda.
     */
    public function search($keyword) {
        try {
            $sql = "SELECT * FROM gift_lists WHERE title LIKE ? OR description LIKE ? ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(["%$keyword%", "%$keyword%"]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene listas de regalo de un usuario específico.
     *
     * @param int $user_id
     * @return array Listas del usuario.
     */
    public function getByUser($user_id) {
        try {
            $sql = "SELECT * FROM gift_lists WHERE user_id = ? ORDER BY created_at DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Genera un enlace único para una lista.
     *
     * @return string Enlace único.
     */
    private function generateUniqueLink() {
        $unique = md5(uniqid(rand(), true));
        
        // Verificar que no existe ya en la base de datos
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM gift_lists WHERE unique_link = ?");
        $stmt->execute([$unique]);
        
        // Si ya existe, generar otro
        if ($stmt->fetchColumn() > 0) {
            return $this->generateUniqueLink();
        }
        
        return $unique;
    }
}