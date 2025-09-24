<?php
/**
 * MODELO DE PAGOS OPTIMIZADO PARA HOSTING COMPARTIDO
 * 
 * Características:
 * - Sin dependencias externas complejas
 * - Uso eficiente de memoria
 * - Compatible con limitaciones de hosting compartido
 * - Manejo robusto de errores
 * - Logging simplificado
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
     * Crear preferencia de MercadoPago (optimizada para hosting compartido)
     */
    public function createMercadoPagoPreference($giftId, $quantity, $buyerInfo) {
        try {
            // Validaciones básicas
            if (!$giftId || !$quantity || !$buyerInfo['email']) {
                throw new Exception('Datos insuficientes para crear la preferencia');
            }
            
            // Obtener información del regalo
            $gift = $this->getGiftDetails($giftId);
            if (!$gift) {
                throw new Exception('Regalo no encontrado');
            }
            
            // Verificar stock disponible
            $availableStock = $gift['stock'] - $gift['sold_quantity'];
            if ($quantity > $availableStock) {
                throw new Exception('Stock insuficiente. Disponible: ' . $availableStock);
            }
            
            $totalAmount = $gift['price'] * $quantity;
            $externalReference = 'deseos_' . $giftId . '_' . time() . '_' . rand(1000, 9999);
            
            // Datos de la preferencia
            $preferenceData = [
                "items" => [[
                    "id" => (string)$giftId,
                    "title" => $this->sanitizeString($gift['name']),
                    "description" => $this->sanitizeString($gift['description'] ?? ''),
                    "quantity" => (int)$quantity,
                    "currency_id" => "CLP",
                    "unit_price" => (float)$gift['price']
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
                    "buyer_email" => $buyerInfo['email'],
                    "list_id" => $gift['gift_list_id']
                ]
            ];
            
            // Realizar petición a MercadoPago
            $response = $this->makeMercadoPagoRequest('POST', 'checkout/preferences', $preferenceData);
            
            if (!$response || !isset($response['id'])) {
                throw new Exception('Error al crear preferencia en MercadoPago');
            }
            
            // Guardar transacción pendiente
            $transactionId = $this->createPendingTransaction([
                'gift_id' => $giftId,
                'quantity' => $quantity,
                'amount' => $totalAmount,
                'buyer_email' => $buyerInfo['email'],
                'buyer_name' => $buyerInfo['name'],
                'buyer_phone' => $buyerInfo['phone'] ?? null,
                'external_reference' => $externalReference,
                'preference_id' => $response['id']
            ]);
            
            // Log del evento
            $this->logPaymentEvent('preference_created', $transactionId, [
                'preference_id' => $response['id'],
                'amount' => $totalAmount,
                'gift_id' => $giftId
            ]);
            
            return [
                'preference_id' => $response['id'],
                'init_point' => $response['init_point'],
                'sandbox_init_point' => $response['sandbox_init_point'] ?? null,
                'transaction_id' => $transactionId,
                'external_reference' => $externalReference
            ];
            
        } catch (Exception $e) {
            $this->logPaymentEvent('preference_error', null, [
                'error' => $e->getMessage(),
                'gift_id' => $giftId ?? null
            ]);
            throw $e;
        }
    }
    
    /**
     * Procesar webhook de MercadoPago
     */
    public function processWebhook($webhookData) {
        try {
            // Validar webhook
            if (!isset($webhookData['type']) || $webhookData['type'] !== 'payment') {
                return ['status' => 'ignored', 'reason' => 'Not a payment webhook'];
            }
            
            if (!isset($webhookData['data']['id'])) {
                throw new Exception('Payment ID not provided in webhook');
            }
            
            $paymentId = $webhookData['data']['id'];
            
            // Obtener información del pago
            $paymentInfo = $this->getMercadoPagoPayment($paymentId);
            
            if (!$paymentInfo) {
                throw new Exception('Could not retrieve payment information');
            }
            
            // Buscar transacción por referencia externa
            $transaction = $this->getTransactionByReference($paymentInfo['external_reference']);
            
            if (!$transaction) {
                throw new Exception('Transaction not found for reference: ' . $paymentInfo['external_reference']);
            }
            
            // Actualizar transacción
            $this->updateTransaction($transaction['id'], [
                'status' => $this->mapMercadoPagoStatus($paymentInfo['status']),
                'payment_id' => $paymentId,
                'payment_method' => $paymentInfo['payment_method_id'] ?? null,
                'payment_data' => json_encode($paymentInfo),
                'processed_at' => date('Y-m-d H:i:s')
            ]);
            
            // Si el pago fue aprobado, actualizar regalo
            if ($paymentInfo['status'] === 'approved') {
                $this->processApprovedPayment($transaction, $paymentInfo);
            }
            
            $this->logPaymentEvent('webhook_processed', $transaction['id'], [
                'payment_id' => $paymentId,
                'status' => $paymentInfo['status'],
                'amount' => $paymentInfo['transaction_amount']
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
     * Procesar pago aprobado
     */
    private function processApprovedPayment($transaction, $paymentInfo) {
        try {
            // Actualizar stock del regalo
            $this->updateGiftStock($transaction['gift_id'], $transaction['quantity']);
            
            // Registrar evento de analytics
            $this->trackAnalyticsEvent('purchase_completed', null, [
                'gift_id' => $transaction['gift_id'],
                'amount' => $transaction['amount'],
                'quantity' => $transaction['quantity']
            ]);
            
            // Enviar notificaciones (simplificadas)
            $this->sendPaymentNotifications($transaction, $paymentInfo);
            
        } catch (Exception $e) {
            $this->logPaymentEvent('post_payment_error', $transaction['id'], [
                'error' => $e->getMessage()
            ]);
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
            'User-Agent: DeseosList/2.0',
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
     * Obtener información de pago de MercadoPago
     */
    private function getMercadoPagoPayment($paymentId) {
        try {
            return $this->makeMercadoPagoRequest('GET', 'v1/payments/' . $paymentId);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Crear transacción pendiente
     */
    private function createPendingTransaction($data) {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (gift_id, quantity, amount, buyer_email, buyer_name, buyer_phone,
                                    external_reference, preference_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->execute([
            $data['gift_id'],
            $data['quantity'],
            $data['amount'],
            $data['buyer_email'],
            $data['buyer_name'],
            $data['buyer_phone'],
            $data['external_reference'],
            $data['preference_id']
        ]);
        
        return $this->db->lastInsertId();
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
     * Obtener detalles del regalo
     */
    private function getGiftDetails($giftId) {
        $stmt = $this->db->prepare("
            SELECT g.*, gl.user_id as list_owner_id, gl.title as list_title
            FROM gifts g
            JOIN gift_lists gl ON g.gift_list_id = gl.id
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
     * Mapear estado de MercadoPago a nuestro sistema
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
     * Limpiar string para MercadoPago
     */
    private function sanitizeString($string, $maxLength = 255) {
        $clean = strip_tags(trim($string));
        return mb_substr($clean, 0, $maxLength, 'UTF-8');
    }
    
    /**
     * Registrar evento de pago (simplificado)
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
            // Log silencioso - no interrumpir el flujo principal
            error_log('Payment log error: ' . $e->getMessage());
        }
    }
    
    /**
     * Registrar evento de analytics (simplificado)
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
            // Log silencioso
            error_log('Analytics tracking error: ' . $e->getMessage());
        }
    }
    
    /**
     * Enviar notificaciones simplificadas
     */
    private function sendPaymentNotifications($transaction, $paymentInfo) {
        try {
            // Notificar al comprador por email simple
            $this->sendSimpleEmail(
                $transaction['buyer_email'],
                'Confirmación de Compra - Lista de Deseos',
                $this->buildPaymentConfirmationEmail($transaction, $paymentInfo)
            );
            
            // Notificar al propietario de la lista
            $gift = $this->getGiftDetails($transaction['gift_id']);
            if ($gift && $gift['list_owner_id']) {
                $owner = $this->getUserById($gift['list_owner_id']);
                if ($owner) {
                    $this->sendSimpleEmail(
                        $owner['email'],
                        '¡Nueva compra en tu lista de deseos!',
                        $this->buildOwnerNotificationEmail($transaction, $gift, $owner)
                    );
                }
            }
            
        } catch (Exception $e) {
            // Log silencioso - las notificaciones no deben interrumpir el flujo de pago
            error_log('Notification error: ' . $e->getMessage());
        }
    }
    
    /**
     * Envío de email simplificado (compatible con hosting compartido)
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
    
    /**
     * Construir email de confirmación de pago
     */
    private function buildPaymentConfirmationEmail($transaction, $paymentInfo) {
        $gift = $this->getGiftDetails($transaction['gift_id']);
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #4CAF50;'>¡Compra Confirmada!</h2>
                
                <p>Hola <strong>" . htmlspecialchars($transaction['buyer_name']) . "</strong>,</p>
                
                <p>Tu compra ha sido procesada exitosamente:</p>
                
                <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #4CAF50; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0;'>" . htmlspecialchars($gift['name']) . "</h3>
                    <p style='margin: 5px 0;'><strong>Cantidad:</strong> " . $transaction['quantity'] . "</p>
                    <p style='margin: 5px 0;'><strong>Total:</strong> $" . number_format($transaction['amount'], 0, ',', '.') . "</p>
                    <p style='margin: 5px 0;'><strong>ID de Pago:</strong> " . ($paymentInfo['id'] ?? 'N/A') . "</p>
                </div>
                
                <p>Gracias por tu compra. El propietario de la lista ha sido notificado.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>" . ($this->config['application']['name'] ?? 'Lista de Deseos') . "</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Construir email de notificación al propietario
     */
    private function buildOwnerNotificationEmail($transaction, $gift, $owner) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #2196F3;'>¡Nueva Compra en tu Lista!</h2>
                
                <p>Hola <strong>" . htmlspecialchars($owner['name']) . "</strong>,</p>
                
                <p>Alguien acaba de comprar de tu lista de deseos:</p>
                
                <div style='background: #f9f9f9; padding: 15px; border-left: 4px solid #2196F3; margin: 20px 0;'>
                    <h3 style='margin: 0 0 10px 0;'>" . htmlspecialchars($gift['name']) . "</h3>
                    <p style='margin: 5px 0;'><strong>Comprador:</strong> " . htmlspecialchars($transaction['buyer_name']) . "</p>
                    <p style='margin: 5px 0;'><strong>Cantidad:</strong> " . $transaction['quantity'] . "</p>
                    <p style='margin: 5px 0;'><strong>Total:</strong> $" . number_format($transaction['amount'], 0, ',', '.') . "</p>
                </div>
                
                <p>¡Felicidades! Tu lista está funcionando muy bien.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                <p style='font-size: 12px; color: #666;'>" . ($this->config['application']['name'] ?? 'Lista de Deseos') . "</p>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Obtener usuario por ID
     */
    private function getUserById($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener estadísticas de pagos
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
                SUM(amount) as total_revenue,
                AVG(amount) as avg_transaction,
                COUNT(DISTINCT buyer_email) as unique_buyers
            FROM transactions 
            $whereClause
        ");
        
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener transacciones de usuario
     */
    public function getUserTransactions($userEmail, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT t.*, g.name as gift_name, gl.title as list_title
            FROM transactions t
            JOIN gifts g ON t.gift_id = g.id
            JOIN gift_lists gl ON g.gift_list_id = gl.id
            WHERE t.buyer_email = ?
            ORDER BY t.created_at DESC
            LIMIT ?
        ");
        
        $stmt->execute([$userEmail, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}