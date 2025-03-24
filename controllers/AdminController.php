<?php
// controllers/AdminController.php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/GiftList.php";
require_once __DIR__ . "/../models/Transaction.php";

class AdminController {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Actualizado para incluir la columna 'lastname'
    public function listUsers() {
        $stmt = $this->pdo->query("SELECT id, name, lastname, email, role FROM users");
        return $stmt->fetchAll();
    }
    
    public function listGiftLists() {
        $stmt = $this->pdo->query("SELECT * FROM gift_lists ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function listTransactions() {
        $stmt = $this->pdo->query("SELECT * FROM transactions ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>
