<?php
// controllers/PaymentController.php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/Transaction.php";
require_once __DIR__ . "/../models/Gift.php";
require_once __DIR__ . "/../models/GiftList.php";

// Requiere la librería de Stripe (instalar con composer require stripe/stripe-php)
require_once __DIR__ . "/../vendor/autoload.php";

class PaymentController {
    private $pdo;
    private $transactionModel;
    private $giftModel;
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->transactionModel = new Transaction($pdo);
        $this->giftModel = new Gift($pdo);
    }
    public function processPayment($data, $user_id = null) {
        // Se espera: gift_list_id, gift_id, amount, currency, quantity
        $quantity = isset($data["quantity"]) ? (int)$data["quantity"] : 1;
        $amount = $data["amount"];
        if (isset($data["gift_id"])) {
            $purchaseSuccess = $this->giftModel->purchase($data["gift_id"], $quantity);
            if (!$purchaseSuccess) {
                return ["success" => false, "message" => "No hay suficiente stock para este regalo."];
            }
        }
        $this->transactionModel->create($user_id, $data["gift_list_id"], $data["gift_id"] ?? null, $amount, $data["currency"], "succeeded");
        return ["success" => true, "message" => "Pago procesado exitosamente"];
    }
}
