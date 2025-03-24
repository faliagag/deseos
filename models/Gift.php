<?php
// models/Gift.php
require_once __DIR__ . '/../includes/ErrorHandler.php';

class Gift {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Crea un nuevo regalo para una lista.
/**
     * Crea un nuevo regalo para una lista.
     *
     * @param int $gift_list_id
     * @param string $name
     * @param string $description
     * @param float $price
     * @param int $stock
     * @return bool Resultado de la operación.
     */
    public function create($gift_list_id, $name, $description, $price, $stock) {
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
            
            // Insertar el regalo
            $stmt = $this->pdo->prepare("
                INSERT INTO gifts 
                (gift_list_id, name, description, price, stock, sold, contributed, created_at) 
                VALUES (?, ?, ?, ?, ?, 0, 0, NOW())
            ");
            
            return $stmt->execute([$gift_list_id, $name, $description, $price, $stock]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene un regalo por su ID.
     *
     * @param int $id
     * @return array|bool Datos del regalo o false.
     */
    public function getById($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM gifts WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene los regalos de una lista.
     *
     * @param int $gift_list_id
     * @return array Regalos de la lista.
     */
    public function getByGiftList($gift_list_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM gifts WHERE gift_list_id = ?");
            $stmt->execute([$gift_list_id]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Actualiza un regalo.
     *
     * @param int $id
     * @param string $name
     * @param string $description
     * @param float $price
     * @param int $stock
     * @return bool Resultado de la operación.
     */
    public function update($id, $name, $description, $price, $stock) {
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
            $stmt = $this->pdo->prepare("
                UPDATE gifts 
                SET name = ?, description = ?, price = ?, stock = ? 
                WHERE id = ?
            ");
            
            return $stmt->execute([$name, $description, $price, $stock, $id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Elimina un regalo.
     *
     * @param int $id
     * @return bool Resultado de la operación.
     */
    public function delete($id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM gifts WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Realiza una compra de regalo.
     *
     * @param int $gift_id
     * @param int $quantity
     * @return bool Resultado de la operación.
     */
    public function purchase($gift_id, $quantity) {
        try {
            // Iniciar transacción
            $this->pdo->beginTransaction();
            
            // Verificar stock disponible
            $stmt = $this->pdo->prepare("SELECT * FROM gifts WHERE id = ? FOR UPDATE");
            $stmt->execute([$gift_id]);
            $gift = $stmt->fetch();
            
            if (!$gift) {
                $this->pdo->rollBack();
                throw new Exception("Regalo no encontrado");
            }
            
            if ($gift['stock'] < $quantity) {
                $this->pdo->rollBack();
                throw new Exception("Stock insuficiente");
            }
            
            // Calcular el importe de la contribución
            $contribution = $gift['price'] * $quantity;
            
            // Actualizar el regalo
            $stmt = $this->pdo->prepare("
                UPDATE gifts 
                SET stock = stock - ?, sold = sold + ?, contributed = contributed + ? 
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$quantity, $quantity, $contribution, $gift_id]);
            
            if (!$result) {
                $this->pdo->rollBack();
                throw new Exception("Error al actualizar stock");
            }
            
            // Confirmar transacción
            $this->pdo->commit();
            
            return true;
        } catch (Exception $e) {
            // Revertir cambios en caso de error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            ErrorHandler::handleException($e);
            return false;
        }
    }
}