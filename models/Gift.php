<?php
// models/Gift.php
class Gift {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    // Agrega regalo con stock, sold y contributed
    public function create($gift_list_id, $name, $description, $price, $stock) {
        $stmt = $this->pdo->prepare("INSERT INTO gifts (gift_list_id, name, description, price, stock, sold, contributed, created_at) VALUES (?, ?, ?, ?, ?, 0, 0, NOW())");
        return $stmt->execute([$gift_list_id, $name, $description, $price, $stock]);
    }
    public function getByGiftList($gift_list_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM gifts WHERE gift_list_id = ?");
        $stmt->execute([$gift_list_id]);
        return $stmt->fetchAll();
    }
    // Realiza una compra: reduce stock, incrementa sold y actualiza contributed (usando price * quantity)
    public function purchase($gift_id, $quantity) {
        $stmt = $this->pdo->prepare("UPDATE gifts SET stock = stock - ?, sold = sold + ?, contributed = contributed + (price * ?) WHERE id = ? AND stock >= ?");
        return $stmt->execute([$quantity, $quantity, $quantity, $gift_id, $quantity]);
    }
}
