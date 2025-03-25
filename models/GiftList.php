<?php
// models/GiftList.php - Versión actualizada con soporte para nuevas funcionalidades

require_once __DIR__ . '/../includes/ErrorHandler.php';

class GiftList {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Inserta una nueva lista de regalos con soporte para fecha límite y visibilidad.
     *
     * @param int    $user_id
     * @param string $title
     * @param string $description
     * @param string $event_type
     * @param string $beneficiary1
     * @param string|null $beneficiary2
     * @param int|null $preset_theme
     * @param string|null $expiry_date Formato YYYY-MM-DD
     * @param string $visibility ('public', 'private', 'link_only')
     * @return bool|int Resultado de la inserción o ID de la lista creada.
     */
    public function create($user_id, $title, $description, $event_type, $beneficiary1, $beneficiary2 = null, $preset_theme = null, $expiry_date = null, $visibility = 'link_only') {
        try {
            // Validar datos obligatorios
            if (!$user_id || !$title) {
                throw new Exception("Faltan datos obligatorios (user_id, title)");
            }
            
            // Generar un unique_link único
            $unique_link = $this->generateUniqueLink();
            
            // Preparar la consulta con los nuevos campos
            $sql = "INSERT INTO gift_lists (user_id, title, description, event_type, beneficiary1, beneficiary2, preset_theme, unique_link, expiry_date, visibility, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $user_id, 
                $title, 
                $description, 
                $event_type, 
                $beneficiary1, 
                $beneficiary2, 
                $preset_theme, 
                $unique_link,
                $expiry_date,
                $visibility
            ]);
            
            if (!$result) {
                error_log("GiftList::create error: " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza una lista de regalos con soporte para nuevos campos.
     *
     * @param int    $id
     * @param string $title
     * @param string $description
     * @param string $event_type
     * @param string $beneficiary1
     * @param string|null $beneficiary2
     * @param int|null $preset_theme
     * @param string|null $expiry_date Formato YYYY-MM-DD
     * @param string $visibility ('public', 'private', 'link_only')
     * @return bool Resultado de la actualización.
     */
    public function update($id, $title, $description, $event_type = null, $beneficiary1 = null, $beneficiary2 = null, $preset_theme = null, $expiry_date = null, $visibility = null) {
        try {
            // Validar datos obligatorios
            if (!$id || !$title) {
                throw new Exception("Faltan datos obligatorios (id, title)");
            }
            
            // Comprobar si necesitamos actualizar todos los campos o solo algunos
            if ($visibility === null || $expiry_date === null) {
                // Obtenemos los valores actuales para los campos que no se actualizan
                $stmt = $this->pdo->prepare("SELECT visibility, expiry_date FROM gift_lists WHERE id = ?");
                $stmt->execute([$id]);
                $currentValues = $stmt->fetch();
                
                if ($visibility === null) {
                    $visibility = $currentValues['visibility'];
                }
                
                if ($expiry_date === null) {
                    $expiry_date = $currentValues['expiry_date'];
                }
            }
            
            // Preparar la consulta con los nuevos campos
            $sql = "UPDATE gift_lists 
                    SET title = ?, description = ?, event_type = ?, 
                        beneficiary1 = ?, beneficiary2 = ?, preset_theme = ?,
                        expiry_date = ?, visibility = ?
                    WHERE id = ?";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $title, 
                $description, 
                $event_type, 
                $beneficiary1, 
                $beneficiary2, 
                $preset_theme,
                $expiry_date,
                $visibility,
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
     * Obtiene todas las listas públicas.
     *
     * @return array Lista de regalos públicas.
     */
    public function getPublicLists() {
        try {
            $sql = "SELECT * FROM gift_lists WHERE visibility = 'public' ORDER BY created_at DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Comprueba si una lista ha expirado
     * 
     * @param int $id ID de la lista
     * @return bool true si ha expirado, false si no
     */
    public function hasExpired($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT expiry_date FROM gift_lists WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            
            if (!$row || $row['expiry_date'] === null) {
                return false; // No tiene fecha de expiración
            }
            
            $expiryDate = new DateTime($row['expiry_date']);
            $today = new DateTime();
            
            return $today > $expiryDate;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    // Los métodos existentes se mantienen y siguen funcionando
    public function delete($id) { /* ... */ }
    public function getById($id) { /* ... */ }
    public function getByUniqueLink($unique_link) { /* ... */ }
    public function getAll() { /* ... */ }
    public function search($keyword) { /* ... */ }
    public function getByUser($user_id) { /* ... */ }
    
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