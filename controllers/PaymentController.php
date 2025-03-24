<?php
// controllers/PaymentController.php
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../models/Transaction.php";
require_once __DIR__ . "/../models/Gift.php";
require_once __DIR__ . "/../models/GiftList.php";
require_once __DIR__ . "/../includes/ErrorHandler.php";
require_once __DIR__ . "/../includes/helpers.php";

// Requiere la librería de Stripe (se asume instalación con composer require stripe/stripe-php)
// require_once __DIR__ . "/../vendor/autoload.php";

class PaymentController {
    private $pdo;
    private $transactionModel;
    private $giftModel;
    private $giftListModel;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->transactionModel = new Transaction($pdo);
        $this->giftModel = new Gift($pdo);
        $this->giftListModel = new GiftList($pdo);
    }
    
    /**
     * Procesa un pago para una lista de regalos o un regalo específico
     * 
     * @param array $data Datos del pago (gift_list_id, gift_id, amount, currency, quantity)
     * @param int $user_id ID del usuario (opcional)
     * @return array Resultado del procesamiento
     */
    public function processPayment($data, $user_id = null) {
        try {
            // Validar token CSRF
            if (isset($data['csrf_token']) && !verify_csrf_token($data['csrf_token'])) {
                return ["success" => false, "message" => "Error de verificación. Por favor, recargue la página."];
            }
            
            // Validar datos obligatorios
            if (empty($data["gift_list_id"]) || empty($data["amount"]) || empty($data["currency"])) {
                return ["success" => false, "message" => "Faltan datos requeridos para procesar el pago."];
            }
            
            // Sanitizar y validar datos
            $gift_list_id = (int)$data["gift_list_id"];
            $gift_id = isset($data["gift_id"]) ? (int)$data["gift_id"] : null;
            $amount = (float)$data["amount"];
            $currency = strtolower(sanitize($data["currency"]));
            $quantity = isset($data["quantity"]) ? (int)$data["quantity"] : 1;
            
            // Validaciones adicionales
            if ($amount <= 0) {
                return ["success" => false, "message" => "El monto debe ser mayor a cero."];
            }
            
            if ($quantity <= 0) {
                return ["success" => false, "message" => "La cantidad debe ser mayor a cero."];
            }
            
            // Verificar que la lista existe
            $list = $this->giftListModel->getById($gift_list_id);
            if (!$list) {
                return ["success" => false, "message" => "Lista de regalos no encontrada."];
            }
            
            // Si se proporciona gift_id, verificar que existe y tiene stock
            if ($gift_id) {
                $gift = $this->giftModel->getById($gift_id);
                if (!$gift) {
                    return ["success" => false, "message" => "Regalo no encontrado."];
                }
                
                if ($gift["stock"] < $quantity) {
                    return ["success" => false, "message" => "No hay suficiente stock para este regalo."];
                }
                
                // Actualizar stock y contabilizar la venta
                $purchaseSuccess = $this->giftModel->purchase($gift_id, $quantity);
                if (!$purchaseSuccess) {
                    return ["success" => false, "message" => "Error al actualizar el inventario del regalo."];
                }
            }
            
            // Comenzar transacción en la base de datos
            $this->pdo->beginTransaction();
            
            try {
                // Registrar la transacción
                $transaction = $this->transactionModel->create(
                    $user_id, 
                    $gift_list_id, 
                    $gift_id, 
                    $amount, 
                    $currency, 
                    "succeeded", 
                    json_encode([
                        "quantity" => $quantity,
                        "timestamp" => date("Y-m-d H:i:s"),
                        "ip" => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ])
                );
                
                if (!$transaction) {
                    throw new Exception("Error al registrar la transacción.");
                }
                
                // Confirmar transacción
                $this->pdo->commit();
                
                // Establecer mensaje flash
                set_flash_message("success", "¡Pago procesado exitosamente! Gracias por tu contribución.");
                
                return [
                    "success" => true, 
                    "message" => "Pago procesado exitosamente",
                    "transaction_id" => $transaction
                ];
            } catch (Exception $e) {
                // Revertir cambios en caso de error
                $this->pdo->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            ErrorHandler::handleException($e);
            return ["success" => false, "message" => "Error al procesar el pago: " . $e->getMessage()];
        }
    }
    
    /**
     * Obtiene el historial de transacciones de un usuario
     * 
     * @param int $user_id ID del usuario
     * @return array Historial de transacciones
     */
    public function getUserTransactionHistory($user_id) {
        try {
            if (empty($user_id)) {
                throw new Exception("ID de usuario no proporcionado");
            }
            
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
}