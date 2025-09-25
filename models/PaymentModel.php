<?php
/**
 * MODELO DE PAGOS COMPLETO - VERSIÓN 2.1
 * 
 * Características mejoradas:
 * - Integración dual MercadoPago + Transbank
 * - Sistema de fees del 10% (estilo milistaderegalos.cl)
 * - Depósitos quincenales automatizados
 * - Notificaciones mejoradas
 * - Analytics de testimonios
 * - Tracking de conversiones
 */

require_once '../includes/db.php';

class PaymentModel {
    private $db;
    private $config;
    
    public function __construct() {
        $this->db = getConnection();
        $this->config = include '../config/config.php';
    }
    
    /**
     * Crear preferencia de MercadoPago con fees incluidos
     */
    public function createMercadoPagoPreference($giftId, $quantity, $buyerInfo) {
        try {
            if (!$giftId || !$quantity || !$buyerInfo['email']) {
                throw new Exception('Datos insuficientes para crear la preferencia');
            }
            
            $gift = $this->getGiftDetails($giftId);
            if (!$gift) {
                throw new Exception('Regalo no encontrado');
            }
            
            // Verificar stock disponible
            $availableStock = $gift['stock'] - $gift['sold_quantity'];
            if ($quantity > $availableStock) {
                throw new Exception('Stock insuficiente. Disponible: ' . $availableStock);
            }
            
            // Calcular monto con fee incluido (estilo milistaderegalos.cl)
            $baseAmount = $gift['price'] * $quantity;
            $feePercentage = $this->config['fees']['percentage'] / 100;
            $totalAmount = $baseAmount * (1 + $feePercentage); // Fee incluido en el pago
            $externalReference = 'deseos_mp_' . $giftId . '_' . time() . '_' . rand(1000, 9999);
            
            $preferenceData = [
                "items" => [[
                    "id" => (string)$giftId,
                    "title" => $this->sanitizeString($gift['name']),
                    "description" => $this->sanitizeString($gift['description'] ?? ''),
                    "quantity" => (int)$quantity,
                    "currency_id" => "CLP",
                    "unit_price" => (float)$totalAmount // Precio con fee incluido
                ]],
                "payer" => [
                    "name" => $this->sanitizeString($buyerInfo['name']),
                    "surname" => $this->sanitizeString($buyerInfo['lastname'] ?? ''),
                    "email" => $buyerInfo['email']
                ],
                "back_urls" => [
                    "success" => $this->config['application']['url'] . "/public/payment_success.php",
                    "failure" => $this->config['application']['url'] . "/public/payment_failure.php",
                    "pending" => $this->config['application']['url'] . "/public/payment_pending.php"
                ],
                "auto_return" => "approved",
                "notification_url" => $this->config['application']['url'] . "/public/webhook_mercadopago.php",
                "external_reference" => $externalReference,
                "expires" => true,
                "expiration_date_from" => date('c'),
                "expiration_date_to" => date('c', strtotime('+30 minutes')),
                "metadata" => [
                    "gift_id" => $giftId,
                    "quantity" => $quantity,
                    "base_amount" => $baseAmount,
                    "fee_amount" => $totalAmount - $baseAmount,
                    "buyer_email" => $buyerInfo['email'],
                    "list_id" => $gift['gift_list_id'],
                    "payment_method" => 'mercadopago'
                ]
            ];
            
            $response = $this->makeMercadoPagoRequest('POST', 'checkout/preferences', $preferenceData);
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Error al crear preferencia en MercadoPago');
            }
            
            // Guardar transacción pendiente con fee tracking
            $transactionId = $this->createPendingTransaction([
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'base_amount' => $baseAmount,
                'fee_amount' => $totalAmount - $baseAmount,
                'total_amount' => $totalAmount,
                'buyer_email' => $buyerInfo['email'],
                'buyer_name' => $buyerInfo['name'],
                'buyer_phone' => $buyerInfo['phone'] ?? null,
                'external_reference' => $externalReference,
                'preference_id' => $response['id'],
                'payment_method' => 'mercadopago'
            ]);
            
            $this->logPaymentEvent('preference_created', $transactionId, [
                'preference_id' => $response['id'],
                'total_amount' => $totalAmount,
                'base_amount' => $baseAmount,
                'fee_amount' => $totalAmount - $baseAmount,
                'gift_id' => $giftId
            ]);
            
            return [
                'preference_id' => $response['id'],
                'init_point' => $response['init_point'],
                'sandbox_init_point' => $response['sandbox_init_point'] ?? null,
                'transaction_id' => $transactionId,
                'external_reference' => $externalReference,
                'total_amount' => $totalAmount,
                'base_amount' => $baseAmount,
                'fee_amount' => $totalAmount - $baseAmount
            ];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('preference_error', null, [
                'error' => $e->getMessage(),
                'gift_id' => $giftId ?? null,
                'payment_method' => 'mercadopago'
            ]);
            throw $e;
        }
    }
    
    /**
     * NUEVO: Crear transacción con Transbank (método principal en Chile)
     */
    public function createTransbankTransaction($giftId, $quantity, $buyerInfo) {
        try {
            if (!$this->config['transbank']['enabled']) {
                throw new Exception('Transbank no está habilitado');
            }
            
            $gift = $this->getGiftDetails($giftId);
            if (!$gift) {
                throw new Exception('Regalo no encontrado');
            }
            
            $availableStock = $gift['stock'] - $gift['sold_quantity'];
            if ($quantity > $availableStock) {
                throw new Exception('Stock insuficiente. Disponible: ' . $availableStock);
            }
            
            // Calcular monto con fee del 10% incluido
            $baseAmount = $gift['price'] * $quantity;
            $feePercentage = $this->config['fees']['percentage'] / 100;
            $totalAmount = round($baseAmount * (1 + $feePercentage));
            $externalReference = 'deseos_tb_' . $giftId . '_' . time() . '_' . rand(1000, 9999);
            
            // Datos para Transbank WebPay Plus
            $transactionData = [
                'buy_order' => $externalReference,
                'session_id' => session_id(),
                'amount' => $totalAmount,
                'return_url' => $this->config['application']['url'] . '/public/transbank_return.php'
            ];
            
            // Crear transacción en Transbank
            $response = $this->makeTransbankRequest('POST', 'transactions', $transactionData);
            
            if (!$response || !isset($response['token'])) {
                throw new Exception('Error al crear transacción en Transbank');
            }
            
            // Guardar transacción pendiente
            $transactionId = $this->createPendingTransaction([
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'base_amount' => $baseAmount,
                'fee_amount' => $totalAmount - $baseAmount,
                'total_amount' => $totalAmount,
                'buyer_email' => $buyerInfo['email'],
                'buyer_name' => $buyerInfo['name'],
                'buyer_phone' => $buyerInfo['phone'] ?? null,
                'external_reference' => $externalReference,
                'preference_id' => $response['token'],
                'payment_method' => 'transbank'
            ]);
            
            $this->logPaymentEvent('transbank_created', $transactionId, [
                'token' => $response['token'],
                'buy_order' => $externalReference,
                'amount' => $totalAmount,
                'fee_included' => $totalAmount - $baseAmount
            ]);
            
            return [
                'token' => $response['token'],
                'url' => $response['url'],
                'transaction_id' => $transactionId,
                'external_reference' => $externalReference,
                'total_amount' => $totalAmount,
                'base_amount' => $baseAmount,
                'fee_amount' => $totalAmount - $baseAmount
            ];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('transbank_error', null, [
                'error' => $e->getMessage(),
                'gift_id' => $giftId ?? null
            ]);
            throw $e;
        }
    }
    
    /**
     * NUEVO: Confirmar transacción Transbank
     */
    public function confirmTransbankTransaction($token) {
        try {
            $response = $this->makeTransbankRequest('PUT', 'transactions/' . $token, []);
            
            if (!$response) {
                throw new Exception('Error al confirmar transacción Transbank');
            }
            
            // Buscar transacción por token
            $transaction = $this->getTransactionByPreferenceId($token);
            if (!$transaction) {
                throw new Exception('Transacción no encontrada');
            }
            
            // Mapear estado de Transbank
            $status = 'pending';
            if (isset($response['status']) && $response['status'] === 'AUTHORIZED') {
                $status = 'approved';
            } elseif (isset($response['status']) && in_array($response['status'], ['FAILED', 'NULLIFIED'])) {
                $status = 'rejected';
            }
            
            // Actualizar transacción
            $this->updateTransaction($transaction['id'], [
                'status' => $status,
                'payment_id' => $response['authorization_code'] ?? null,
                'payment_method' => 'transbank_webpay',
                'payment_data' => json_encode($response),
                'processed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Si fue aprobado, procesar pago
            if ($status === 'approved') {
                $this->processApprovedPayment($transaction, $response);
            }
            
            return [
                'status' => $status,
                'transaction_id' => $transaction['id'],
                'payment_data' => $response
            ];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('transbank_confirm_error', null, [
                'error' => $e->getMessage(),
                'token' => $token
            ]);
            throw $e;
        }
    }
    
    /**
     * Procesar webhook de MercadoPago (mejorado)
     */
    public function processWebhook($webhookData) {
        try {
            if (!isset($webhookData['type']) || $webhookData['type'] !== 'payment') {
                return ['status' => 'ignored', 'reason' => 'Not a payment webhook'];
            }
            
            if (!isset($webhookData['data']['id'])) {
                throw new Exception('Payment ID not provided in webhook');
            }
            
            $paymentId = $webhookData['data']['id'];
            $paymentInfo = $this->getMercadoPagoPayment($paymentId);
            
            if (!$paymentInfo) {
                throw new Exception('Could not retrieve payment information');
            }
            
            $transaction = $this->getTransactionByReference($paymentInfo['external_reference']);
            if (!$transaction) {
                throw new Exception('Transaction not found for reference: ' . $paymentInfo['external_reference']);
            }
            
            $this->updateTransaction($transaction['id'], [
                'status' => $this->mapMercadoPagoStatus($paymentInfo['status']),
                'payment_id' => $paymentId,
                'payment_method' => 'mercadopago_' . ($paymentInfo['payment_method_id'] ?? 'unknown'),
                'payment_data' => json_encode($paymentInfo),
                'processed_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($paymentInfo['status'] === 'approved') {
                $this->processApprovedPayment($transaction, $paymentInfo);
            }
            
            $this->logPaymentEvent('webhook_processed', $transaction['id'], [
                'payment_id' => $paymentId,
                'status' => $paymentInfo['status'],
                'amount' => $paymentInfo['transaction_amount'],
                'fee_included' => $transaction['fee_amount']
            ]);
            
            return ['status' => 'success', 'transaction_id' => $transaction['id']];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('webhook_error', null, [
                'error' => $e->getMessage(),
                'webhook_data' => json_encode($webhookData)
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * NUEVO: Sistema de depósitos quincenales (estilo milistaderegalos.cl)
     */
    public function processBiweeklyPayouts() {
        try {
            // Obtener fecha de corte (lunes 14:00)
            $cutoffTime = date('Y-m-d ' . $this->config['payouts']['cutoff_time'] . ':00', strtotime('last monday'));
            
            // Obtener transacciones aprobadas pendientes de pago
            $stmt = $this->db->prepare("
                SELECT 
                    t.*, 
                    g.gift_list_id, 
                    gl.user_id as list_owner_id,
                    u.email as owner_email,
                    u.name as owner_name,
                    u.bank_account,
                    u.bank_name,
                    u.account_type
                FROM transactions t
                JOIN gifts g ON t.gift_id = g.id
                JOIN gift_lists gl ON g.gift_list_id = gl.id
                JOIN users u ON gl.user_id = u.id
                WHERE t.status = 'approved' 
                AND t.payout_status = 'pending'
                AND t.processed_at <= ?
                AND t.base_amount >= ?
                ORDER BY u.id, t.processed_at
            ");
            
            $stmt->execute([$cutoffTime, $this->config['payouts']['minimum_amount']]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Agrupar por usuario
            $userPayouts = [];
            foreach ($transactions as $transaction) {
                $userId = $transaction['list_owner_id'];
                if (!isset($userPayouts[$userId])) {
                    $userPayouts[$userId] = [
                        'user_data' => $transaction,
                        'transactions' => [],
                        'total_base' => 0,
                        'total_fees' => 0
                    ];
                }
                $userPayouts[$userId]['transactions'][] = $transaction;
                $userPayouts[$userId]['total_base'] += $transaction['base_amount'];
                $userPayouts[$userId]['total_fees'] += $transaction['fee_amount'];
            }
            
            $processedPayouts = [];
            
            foreach ($userPayouts as $userId => $payoutData) {
                // Crear registro de payout
                $payoutId = $this->createPayout([
                    'user_id' => $userId,
                    'total_amount' => $payoutData['total_base'],
                    'fee_amount' => $payoutData['total_fees'],
                    'transaction_count' => count($payoutData['transactions']),
                    'payout_date' => date('Y-m-d'),
                    'bank_account' => $payoutData['user_data']['bank_account'],
                    'bank_name' => $payoutData['user_data']['bank_name']
                ]);
                
                // Marcar transacciones como pagadas
                foreach ($payoutData['transactions'] as $transaction) {
                    $this->updateTransaction($transaction['id'], [
                        'payout_status' => 'paid',
                        'payout_id' => $payoutId,
                        'payout_date' => date('Y-m-d H:i:s')
                    ]);
                }
                
                // Enviar notificación de depósito
                $this->sendPayoutNotification($payoutData['user_data'], $payoutData['total_base'], $payoutId);
                
                $processedPayouts[] = [
                    'user_id' => $userId,
                    'amount' => $payoutData['total_base'],
                    'transaction_count' => count($payoutData['transactions']),
                    'payout_id' => $payoutId
                ];
                
                $this->logPaymentEvent('payout_processed', null, [
                    'payout_id' => $payoutId,
                    'user_id' => $userId,
                    'amount' => $payoutData['total_base'],
                    'transaction_count' => count($payoutData['transactions'])
                ]);
            }
            
            return [
                'status' => 'success',
                'processed_payouts' => count($processedPayouts),
                'total_amount' => array_sum(array_column($processedPayouts, 'amount')),
                'payouts' => $processedPayouts
            ];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('payout_error', null, ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * Procesar pago aprobado (mejorado con fees y analytics)
     */
    private function processApprovedPayment($transaction, $paymentInfo) {
        try {
            // Actualizar stock del regalo
            $this->updateGiftStock($transaction['gift_id'], $transaction['quantity']);
            
            // Registrar analytics mejorados
            $this->trackAnalyticsEvent('purchase_completed', null, [
                'gift_id' => $transaction['gift_id'],
                'base_amount' => $transaction['base_amount'],
                'fee_amount' => $transaction['fee_amount'],
                'total_amount' => $transaction['total_amount'],
                'quantity' => $transaction['quantity'],
                'payment_method' => $transaction['payment_method'],
                'buyer_email' => $transaction['buyer_email']
            ]);
            
            // Actualizar estadísticas del sistema
            $this->updateSystemStats($transaction);
            
            // Enviar notificaciones mejoradas
            $this->sendPaymentNotifications($transaction, $paymentInfo);
            
            // Programar solicitud de testimonio (después de 1 semana)
            $this->scheduleTestimonialRequest($transaction);
            
        } catch (Exception $e) {
            $this->logPaymentEvent('post_payment_error', $transaction['id'], [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // ... [continúa con más métodos - este archivo es muy extenso, continúo en siguiente actualización]
}