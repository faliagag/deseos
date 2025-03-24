<?php
// models/Transaction.php
require_once __DIR__ . '/../includes/ErrorHandler.php';

class Transaction {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crea una nueva transacción.
     *
     * @param int|null $user_id
     * @param int $gift_list_id
     * @param int|null $gift_id
     * @param float $amount
     * @param string $currency
     * @param string $status
     * @param string|null $metadata
     * @return int|bool ID de la transacción o false.
     */
    public function create($user_id, $gift_list_id, $gift_id, $amount, $currency, $status = "succeeded", $metadata = null) {
        try {
            // Validar datos obligatorios
            if (empty($gift_list_id) || $amount <= 0) {
                throw new Exception("ID de lista y monto son obligatorios");
            }
            
            // Insertar la transacción
            $stmt = $this->pdo->prepare("
                INSERT INTO transactions 
                (user_id, gift_list_id, gift_id, amount, currency, status, metadata, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $result = $stmt->execute([
                $user_id, $gift_list_id, $gift_id, $amount, $currency, $status, $metadata
            ]);
            
            if (!$result) {
                throw new Exception("Error al crear la transacción");
            }
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene todas las transacciones.
     *
     * @return array Lista de transacciones.
     */
    public function getAll() {
        try {
            $stmt = $this->pdo->query("
                SELECT t.*, gl.title as list_title, g.name as gift_name
                FROM transactions t
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                LEFT JOIN gifts g ON t.gift_id = g.id
                ORDER BY t.created_at DESC
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene una transacción por su ID.
     *
     * @param int $id
     * @return array|bool Datos de la transacción o false.
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, gl.title as list_title, g.name as gift_name
                FROM transactions t
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                LEFT JOIN gifts g ON t.gift_id = g.id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene las transacciones de un usuario.
     *
     * @param int $user_id
     * @return array Transacciones del usuario.
     */
    public function getByUser($user_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, gl.title as list_title, g.name as gift_name
                FROM transactions t
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                LEFT JOIN gifts g ON t.gift_id = g.id
                WHERE t.user_id = ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Obtiene las transacciones de una lista de regalos.
     *
     * @param int $gift_list_id
     * @return array Transacciones de la lista.
     */
    public function getByGiftList($gift_list_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, gl.title as list_title, g.name as gift_name,
                       u.name as user_name, u.lastname as user_lastname
                FROM transactions t
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                LEFT JOIN gifts g ON t.gift_id = g.id
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.gift_list_id = ?
                ORDER BY t.created_at DESC
            ");
            $stmt->execute([$gift_list_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Actualiza el estado de una transacción.
     *
     * @param int $id
     * @param string $status
     * @return bool Resultado de la operación.
     */
    public function updateStatus($id, $status) {
        try {
            // Validar estado válido
            if (!in_array($status, ['pending', 'succeeded', 'failed'])) {
                throw new Exception("Estado no válido");
            }
            
            $stmt = $this->pdo->prepare("UPDATE transactions SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
}