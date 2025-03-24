<?php
// controllers/AdminController.php

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/GiftList.php";
require_once __DIR__ . "/../models/Transaction.php";
require_once __DIR__ . "/../includes/ErrorHandler.php";
require_once __DIR__ . "/../includes/helpers.php";

class AdminController {
    private $pdo;
    private $userModel;
    private $giftListModel;
    private $transactionModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
        $this->giftListModel = new GiftList($pdo);
        $this->transactionModel = new Transaction($pdo);
    }
    
    /**
     * Lista todos los usuarios
     * 
     * @param string $searchTerm Término de búsqueda opcional
     * @return array Lista de usuarios
     */
    public function listUsers($searchTerm = '') {
        try {
            if (!empty($searchTerm)) {
                $searchTerm = sanitize($searchTerm);
                $stmt = $this->pdo->prepare("
                    SELECT id, name, lastname, email, phone, bank, account_type, account_number, rut, role, created_at 
                    FROM users 
                    WHERE name LIKE ? OR lastname LIKE ? OR email LIKE ? OR phone LIKE ? OR rut LIKE ?
                    ORDER BY created_at DESC
                ");
                $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
            } else {
                $stmt = $this->pdo->query("
                    SELECT id, name, lastname, email, phone, bank, account_type, account_number, rut, role, created_at 
                    FROM users 
                    ORDER BY created_at DESC
                ");
            }
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Lista todas las listas de regalos
     * 
     * @param string $searchTerm Término de búsqueda opcional
     * @return array Lista de listas de regalos
     */
    public function listGiftLists($searchTerm = '') {
        try {
            if (!empty($searchTerm)) {
                $searchTerm = sanitize($searchTerm);
                $stmt = $this->pdo->prepare("
                    SELECT gl.*, u.name as creator_name, u.lastname as creator_lastname
                    FROM gift_lists gl
                    JOIN users u ON gl.user_id = u.id
                    WHERE gl.title LIKE ? OR gl.description LIKE ? OR u.name LIKE ? OR u.lastname LIKE ?
                    ORDER BY gl.created_at DESC
                ");
                $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
            } else {
                $stmt = $this->pdo->query("
                    SELECT gl.*, u.name as creator_name, u.lastname as creator_lastname
                    FROM gift_lists gl
                    JOIN users u ON gl.user_id = u.id
                    ORDER BY gl.created_at DESC
                ");
            }
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
    /**
     * Lista todas las transacciones
     * 
     * @param string $searchTerm Término de búsqueda opcional
     * @return array Lista de transacciones
     */
    public function listTransactions($searchTerm = '') {
        try {
            if (!empty($searchTerm)) {
                $searchTerm = sanitize($searchTerm);
                $stmt = $this->pdo->prepare("
                    SELECT t.*, gl.title as list_title, 
                           CONCAT(u.name, ' ', u.lastname) as user_name,
                           g.name as gift_name
                    FROM transactions t
                    LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                    LEFT JOIN users u ON t.user_id = u.id
                    LEFT JOIN gifts g ON t.gift_id = g.id
                    WHERE gl.title LIKE ? OR g.name LIKE ? OR u.name LIKE ? OR u.lastname LIKE ?
                    ORDER BY t.created_at DESC
                ");
                $stmt->execute(["%$searchTerm%", "%$searchTerm%", "%$searchTerm%", "%$searchTerm%"]);
            } else {
                $stmt = $this->pdo->query("
                    SELECT t.*, gl.title as list_title, 
                           CONCAT(u.name, ' ', u.lastname) as user_name,
                           g.name as gift_name
                    FROM transactions t
                    LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                    LEFT JOIN users u ON t.user_id = u.id
                    LEFT JOIN gifts g ON t.gift_id = g.id
                    ORDER BY t.created_at DESC
                ");
            }
            return $stmt->fetchAll();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [];
        }
    }
    
/**
     * Elimina un usuario
     * 
     * @param int $id ID del usuario
     * @return bool Resultado de la operación
     */
    public function deleteUser($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            // Verificar que el usuario existe
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception("Usuario no encontrado");
            }
            
            // Verificar que no es el último administrador
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $role = $stmt->fetch()['role'];
            
            if ($role === 'admin' && $adminCount <= 1) {
                throw new Exception("No se puede eliminar el último administrador");
            }
            
            // Eliminar el usuario (las listas y transacciones asociadas se eliminarán por ON DELETE CASCADE)
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Elimina una lista de regalos
     * 
     * @param int $id ID de la lista
     * @return bool Resultado de la operación
     */
    public function deleteGiftList($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID de lista no proporcionado");
            }
            
            // Verificar que la lista existe
            $stmt = $this->pdo->prepare("SELECT id FROM gift_lists WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                throw new Exception("Lista no encontrada");
            }
            
            // Eliminar la lista (los regalos y transacciones asociadas se eliminarán por ON DELETE CASCADE)
            $stmt = $this->pdo->prepare("DELETE FROM gift_lists WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene un usuario por su ID
     * 
     * @param int $id ID del usuario
     * @return array|bool Datos del usuario o false en caso de error
     */
    public function getUser($id) {
        try {
            if (empty($id)) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Actualiza un usuario
     * 
     * @param int $id ID del usuario
     * @param array $data Datos actualizados
     * @return bool Resultado de la operación
     */
    public function updateUser($id, $data) {
        try {
            if (empty($id)) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
            // Sanitizar y validar datos
            $data = sanitize($data);
            
            if (empty($data['name']) || empty($data['lastname']) || empty($data['email'])) {
                throw new Exception("Faltan datos requeridos");
            }
            
            // Verificar que el email no esté en uso por otro usuario
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Email ya en uso por otro usuario");
            }
            
            // Si hay contraseña nueva, actualizarla; si no, dejarla igual
            if (!empty($data['password'])) {
                $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, 
                        account_number = ?, email = ?, password = ?, role = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $data['name'], 
                    $data['lastname'], 
                    $data['phone'], 
                    $data['bank'] ?? null, 
                    $data['account_type'] ?? null, 
                    $data['account_number'] ?? null, 
                    $data['email'], 
                    $hashedPassword, 
                    $data['role'] ?? 'user',
                    $id
                ]);
            } else {
                $stmt = $this->pdo->prepare("
                    UPDATE users 
                    SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, 
                        account_number = ?, email = ?, role = ?
                    WHERE id = ?
                ");
                return $stmt->execute([
                    $data['name'], 
                    $data['lastname'], 
                    $data['phone'], 
                    $data['bank'] ?? null, 
                    $data['account_type'] ?? null, 
                    $data['account_number'] ?? null, 
                    $data['email'], 
                    $data['role'] ?? 'user',
                    $id
                ]);
            }
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return false;
        }
    }
    
    /**
     * Obtiene estadísticas del panel de administración
     * 
     * @return array Estadísticas
     */
    public function getDashboardStats() {
        try {
            $stats = [];
            
            // Total de usuarios
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
            $stats['total_users'] = $stmt->fetchColumn();
            
            // Total de listas
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gift_lists");
            $stats['total_lists'] = $stmt->fetchColumn();
            
            // Total de transacciones
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM transactions");
            $stats['total_transactions'] = $stmt->fetchColumn();
            
            // Suma total recaudada
            $stmt = $this->pdo->query("SELECT SUM(amount) FROM transactions WHERE status = 'succeeded'");
            $stats['total_amount'] = $stmt->fetchColumn() ?: 0;
            
            // Usuarios nuevos este mes
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM users 
                WHERE created_at >= DATE_FORMAT(NOW() ,'%Y-%m-01')
            ");
            $stats['new_users_month'] = $stmt->fetchColumn();
            
            // Transacciones este mes
            $stmt = $this->pdo->query("
                SELECT COUNT(*) FROM transactions 
                WHERE created_at >= DATE_FORMAT(NOW() ,'%Y-%m-01')
            ");
            $stats['transactions_month'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return [
                'total_users' => 0,
                'total_lists' => 0,
                'total_transactions' => 0,
                'total_amount' => 0,
                'new_users_month' => 0,
                'transactions_month' => 0
            ];
        }
    }
}