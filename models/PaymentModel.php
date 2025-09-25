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
     * Realizar petición a MercadoPago (optimizada)
     */
    private function makeMercadoPagoRequest($method, $endpoint, $data = null) {
        $url = 'https://api.mercadopago.com/' . $endpoint;
        $accessToken = $this->config['mercadopago']['access_token'];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: DeseosList/2.1',
            'X-Idempotency-Key: ' . uniqid()
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception('HTTP Error: ' . $httpCode . ' - ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * NUEVO: Realizar petición a Transbank
     */
    private function makeTransbankRequest($method, $endpoint, $data = null) {
        $url = $this->config['transbank']['webpay_url'] . '/' . $endpoint;
        $apiKey = $this->config['transbank']['api_key'];
        $commerceCode = $this->config['transbank']['commerce_code'];
        
        $headers = [
            'Content-Type: application/json',
            'Tbk-Api-Key-Id: ' . $commerceCode,
            'Tbk-Api-Key-Secret: ' . $apiKey,
            'User-Agent: DeseosList/2.1'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $this->config['transbank']['timeout'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT' && $data) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Transbank cURL Error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception('Transbank HTTP Error: ' . $httpCode . ' - ' . $response);
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Crear transacción pendiente (mejorada con fees)
     */
    private function createPendingTransaction($data) {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (
                gift_id, quantity, base_amount, fee_amount, total_amount, 
                buyer_email, buyer_name, buyer_phone,
                external_reference, preference_id, payment_method, status, 
                payout_status, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
        ");
        
        $stmt->execute([
            $data['gift_id'],
            $data['quantity'],
            $data['base_amount'],
            $data['fee_amount'],
            $data['total_amount'],
            $data['buyer_email'],
            $data['buyer_name'],
            $data['buyer_phone'],
            $data['external_reference'],
            $data['preference_id'],
            $data['payment_method']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * NUEVO: Crear registro de payout
     */
    private function createPayout($data) {
        $stmt = $this->db->prepare("
            INSERT INTO payouts (
                user_id, total_amount, fee_amount, transaction_count,
                payout_date, bank_account, bank_name, status, created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $data['user_id'],
            $data['total_amount'],
            $data['fee_amount'],
            $data['transaction_count'],
            $data['payout_date'],
            $data['bank_account'],
            $data['bank_name']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Obtener detalles del regalo
     */
    private function getGiftDetails($giftId) {
        $stmt = $this->db->prepare("
            SELECT g.*, gl.user_id as list_owner_id, gl.title as list_title,
                   gl.event_type, u.email as owner_email, u.name as owner_name
            FROM gifts g
            JOIN gift_lists gl ON g.gift_list_id = gl.id
            JOIN users u ON gl.user_id = u.id
            WHERE g.id = ?
        ");
        $stmt->execute([$giftId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener transacción por referencia externa
     */
    private function getTransactionByReference($externalRef) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE external_reference = ?");
        $stmt->execute([$externalRef]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * NUEVO: Obtener transacción por preference ID (para Transbank token)
     */
    private function getTransactionByPreferenceId($preferenceId) {
        $stmt = $this->db->prepare("SELECT * FROM transactions WHERE preference_id = ?");
        $stmt->execute([$preferenceId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Actualizar transacción
     */
    private function updateTransaction($transactionId, $data) {
        $setParts = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            $setParts[] = "$key = ?";
            $values[] = $value;
        }
        
        $values[] = $transactionId;
        
        $stmt = $this->db->prepare("
            UPDATE transactions 
            SET " . implode(', ', $setParts) . ", updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute($values);
    }
    
    /**
     * Actualizar stock del regalo
     */
    private function updateGiftStock($giftId, $quantity) {
        $stmt = $this->db->prepare("
            UPDATE gifts 
            SET sold_quantity = sold_quantity + ?,
                collected_amount = collected_amount + (price * ?),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        return $stmt->execute([$quantity, $quantity, $giftId]);
    }
    
    /**
     * Mapear estado de MercadoPago
     */
    private function mapMercadoPagoStatus($mpStatus) {
        $statusMap = [
            'approved' => 'approved',
            'pending' => 'pending',
            'in_process' => 'pending',
            'rejected' => 'rejected',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded'
        ];
        
        return $statusMap[$mpStatus] ?? 'pending';
    }
    
    /**
     * Limpiar string para APIs de pago
     */
    private function sanitizeString($string, $maxLength = 255) {
        $clean = strip_tags(trim($string));
        return mb_substr($clean, 0, $maxLength, 'UTF-8');
    }
    
    /**
     * Registrar evento de pago
     */
    private function logPaymentEvent($eventType, $transactionId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_logs (transaction_id, event_type, event_data, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $transactionId,
                $eventType,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log('Payment log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Registrar evento de analytics
     */
    private function trackAnalyticsEvent($eventType, $userId, $data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO analytics_events (event_type, user_id, session_id, ip_address, data, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $eventType,
                $userId,
                session_id(),
                $_SERVER['REMOTE_ADDR'] ?? null,
                json_encode($data)
            ]);
        } catch (Exception $e) {
            error_log('Analytics tracking error: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener estadísticas de pagos (mejoradas con fees)
     */
    public function getPaymentStats($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE status = 'approved'";
        $params = [];
        
        if ($dateFrom) {
            $whereClause .= " AND DATE(created_at) >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND DATE(created_at) <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_transactions,
                SUM(base_amount) as total_revenue,
                SUM(fee_amount) as total_fees,
                SUM(total_amount) as total_processed,
                AVG(base_amount) as avg_transaction,
                COUNT(DISTINCT buyer_email) as unique_buyers,
                COUNT(DISTINCT payment_method) as payment_methods_used
            FROM transactions 
            $whereClause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * NUEVO: Enviar notificación de depósito
     */
    private function sendPayoutNotification($userData, $amount, $payoutId) {
        $subject = 'Depósito procesado - Mi Lista de Regalos';
        $message = $this->buildPayoutNotificationEmail($userData, $amount, $payoutId);
        
        return $this->sendSimpleEmail($userData['owner_email'], $subject, $message);
    }
    
    /**
     * NUEVO: Construir email de notificación de depósito
     */
    private function buildPayoutNotificationEmail($userData, $amount, $payoutId) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4CAF50;'>¡Depósito Procesado!</h2>
                
                <p>Hola <strong>" . htmlspecialchars($userData['owner_name']) . "</strong>,</p>
                
                <p>Tu depósito ha sido procesado exitosamente:</p>
                
                <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0;'>Detalles del Depósito</h3>
                    <p style='margin: 5px 0;'><strong>Monto:</strong> $" . number_format($amount, 0, ',', '.') . "</p>
                    <p style='margin: 5px 0;'><strong>ID de Depósito:</strong> #" . $payoutId . "</p>
                    <p style='margin: 5px 0;'><strong>Fecha:</strong> " . date('d/m/Y') . "</p>
                    <p style='margin: 5px 0;'><strong>Cuenta:</strong> " . htmlspecialchars($userData['bank_name'] ?? 'No especificada') . "</p>
                </div>
                
                <p>El dinero debería aparecer en tu cuenta en las próximas 24-48 horas.</p>
                <p>Recuerda que los depósitos se procesan cada 2 miércoles.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>" . $this->config['application']['name'] . "</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Envío de email simplificado
     */
    private function sendSimpleEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . ($this->config['smtp']['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST']),
            'Reply-To: ' . ($this->config['smtp']['from_email'] ?? 'noreply@' . $_SERVER['HTTP_HOST']),
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}