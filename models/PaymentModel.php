<?php
require_once '../includes/db.php';

class PaymentModel {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getConnection();
        $this->config = include '../config/config.php';
    }
    
    /**
     * Crear una preferencia de pago en MercadoPago
     */
    public function createMercadoPagoPreference($giftId, $quantity, $buyerInfo) {
        try {
            // Obtener información del regalo
            $gift = $this->getGiftById($giftId);
            if (!$gift) {
                throw new Exception('Regalo no encontrado');
            }
            
            $totalAmount = $gift['price'] * $quantity;
            
            // Configurar MercadoPago
            $accessToken = $this->config['mercadopago']['access_token'];
            
            $preferenceData = [
                "items" => [[
                    "title" => $gift['name'],
                    "quantity" => (int)$quantity,
                    "currency_id" => "CLP",
                    "unit_price" => (float)$gift['price']
                ]],
                "payer" => [
                    "name" => $buyerInfo['name'],
                    "email" => $buyerInfo['email'],
                    "phone" => [
                        "area_code" => "56",
                        "number" => $buyerInfo['phone'] ?? ""
                    ]
                ],
                "back_urls" => [
                    "success" => $this->config['application']['url'] . "/public/payment_success.php",
                    "failure" => $this->config['application']['url'] . "/public/payment_failure.php",
                    "pending" => $this->config['application']['url'] . "/public/payment_pending.php"
                ],
                "auto_return" => "approved",
                "notification_url" => $this->config['application']['url'] . "/public/webhook_mercadopago.php",
                "external_reference" => uniqid('gift_' . $giftId . '_'),
                "metadata" => [
                    "gift_id" => $giftId,
                    "quantity" => $quantity,
                    "buyer_email" => $buyerInfo['email']
                ]
            ];
            
            // Realizar petición a MercadoPago
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/checkout/preferences');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preferenceData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 201) {
                throw new Exception('Error al crear preferencia de pago: ' . $response);
            }
            
            $responseData = json_decode($response, true);
            
            // Guardar la transacción pendiente
            $this->createPendingTransaction([
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'amount' => $totalAmount,
                'buyer_email' => $buyerInfo['email'],
                'buyer_name' => $buyerInfo['name'],
                'external_reference' => $responseData['external_reference'],
                'preference_id' => $responseData['id'],
                'status' => 'pending'
            ]);
            
            return $responseData;
            
        } catch (Exception $e) {
            error_log('Error MercadoPago: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Procesar webhook de MercadoPago
     */
    public function processWebhook($data) {
        try {
            if ($data['type'] !== 'payment') {
                return false;
            }
            
            $paymentId = $data['data']['id'];
            $paymentInfo = $this->getMercadoPagoPayment($paymentId);
            
            if (!$paymentInfo) {
                throw new Exception('No se pudo obtener información del pago');
            }
            
            // Actualizar transacción
            $this->updateTransactionByReference(
                $paymentInfo['external_reference'],
                $paymentInfo['status'],
                $paymentId,
                $paymentInfo
            );
            
            // Si el pago fue aprobado, actualizar el regalo
            if ($paymentInfo['status'] === 'approved') {
                $transaction = $this->getTransactionByReference($paymentInfo['external_reference']);
                if ($transaction) {
                    $this->updateGiftSales($transaction['gift_id'], $transaction['quantity']);
                    
                    // Enviar notificaciones
                    $this->sendPaymentNotifications($transaction, $paymentInfo);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Error webhook MercadoPago: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información de pago de MercadoPago
     */
    private function getMercadoPagoPayment($paymentId) {
        $accessToken = $this->config['mercadopago']['access_token'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mercadopago.com/v1/payments/' . $paymentId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        }
        
        return null;
    }
    
    /**
     * Crear transacción pendiente
     */
    private function createPendingTransaction($data) {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (gift_id, quantity, amount, buyer_email, buyer_name, 
                                    external_reference, preference_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        return $stmt->execute([
            $data['gift_id'],
            $data['quantity'],
            $data['amount'],
            $data['buyer_email'],
            $data['buyer_name'],
            $data['external_reference'],
            $data['preference_id'],
            $data['status']
        ]);
    }
    
    /**
     * Actualizar transacción por referencia externa
     */
    private function updateTransactionByReference($externalRef, $status, $paymentId, $paymentData) {
        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET status = ?, payment_id = ?, payment_data = ?, updated_at = NOW()
            WHERE external_reference = ?
        ");
        
        return $stmt->execute([
            $status,
            $paymentId,
            json_encode($paymentData),
            $externalRef
        ]);
    }
    
    /**
     * Actualizar ventas del regalo
     */
    private function updateGiftSales($giftId, $quantity) {
        $stmt = $this->db->prepare("
            UPDATE gifts 
            SET sold_quantity = sold_quantity + ?, 
                collected_amount = collected_amount + (price * ?)
            WHERE id = ?
        ");
        
        return $stmt->execute([$quantity, $quantity, $giftId]);
    }
    
    /**
     * Obtener regalo por ID
     */
    private function getGiftById($giftId) {
        $stmt = $this->db->prepare("SELECT * FROM gifts WHERE id = ?");
        $stmt->execute([$giftId]);
        return $stmt->fetch();
    }
    
    /**
     * Obtener transacción por referencia externa
     */
    private function getTransactionByReference($externalRef) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE external_reference = ?");
        $stmt->execute([$externalRef]);
        return $stmt->fetch();
    }
    
    /**
     * Obtener transacciones del usuario
     */
    public function getUserTransactions($userId, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT t.*, g.name as gift_name, gl.title as list_title, u.name as list_owner
            FROM transactions t
            JOIN gifts g ON t.gift_id = g.id
            JOIN gift_lists gl ON g.gift_list_id = gl.id
            JOIN users u ON gl.user_id = u.id
            WHERE t.buyer_email = (SELECT email FROM users WHERE id = ?)
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Estadísticas de pagos para admin
     */
    public function getPaymentStats($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE t.status = 'approved'";
        $params = [];
        
        if ($dateFrom) {
            $whereClause .= " AND DATE(t.created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND DATE(t.created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(t.amount) as total_revenue,
                AVG(t.amount) as avg_transaction,
                COUNT(DISTINCT t.buyer_email) as unique_buyers
            FROM transactions t
            $whereClause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    /**
     * Enviar notificaciones de pago
     */
    private function sendPaymentNotifications($transaction, $paymentInfo) {
        require_once '../models/NotificationModel.php';
        $notificationModel = new NotificationModel();
        
        // Notificar al comprador
        $notificationModel->sendPaymentConfirmation($transaction['buyer_email'], $transaction, $paymentInfo);
        
        // Notificar al propietario de la lista
        $gift = $this->getGiftById($transaction['gift_id']);
        if ($gift) {
            $stmt = $this->db->prepare("
                SELECT u.email FROM users u 
                JOIN gift_lists gl ON u.id = gl.user_id 
                JOIN gifts g ON gl.id = g.gift_list_id 
                WHERE g.id = ?
            ");
            $stmt->execute([$transaction['gift_id']]);
            $owner = $stmt->fetch();
            
            if ($owner) {
                $notificationModel->sendGiftPurchaseNotification($owner['email'], $transaction, $gift);
            }
        }
    }
}