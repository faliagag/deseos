<?php
// models/Transaction.php
class Transaction {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    public function create($user_id, $gift_list_id, $gift_id, $amount, $currency, $status = "succeeded", $metadata = null) {
        $stmt = $this->pdo->prepare("INSERT INTO transactions (user_id, gift_list_id, gift_id, amount, currency, status, metadata, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        return $stmt->execute([$user_id, $gift_list_id, $gift_id, $amount, $currency, $status, $metadata]);
    }
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM transactions ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
}
