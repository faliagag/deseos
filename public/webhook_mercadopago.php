<?php
/**
 * WEBHOOK DE MERCADOPAGO - OPTIMIZADO PARA HOSTING COMPARTIDO
 * 
 * Este archivo procesa las notificaciones de MercadoPago
 * Optimizado para funcionar en hosting compartido con limitaciones de recursos
 */

// Configuración inicial para hosting compartido
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);
ini_set('memory_limit', '64M');
ini_set('max_execution_time', 30);

// Headers de respuesta rápida
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Función para respuesta rápida y log
function quickResponse($status, $message, $logData = []) {
    $response = ['status' => $status, 'message' => $message];
    
    // Log simple para debugging
    $logEntry = date('Y-m-d H:i:s') . " - " . $status . ": " . $message;
    if (!empty($logData)) {
        $logEntry .= " - Data: " . json_encode($logData);
    }
    $logEntry .= "\n";
    
    @file_put_contents('../logs/webhook.log', $logEntry, FILE_APPEND | LOCK_EX);
    
    echo json_encode($response);
    exit;
}

try {
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        quickResponse('error', 'Only POST method allowed');
    }
    
    // Obtener datos del webhook
    $input = file_get_contents('php://input');
    if (empty($input)) {
        quickResponse('error', 'Empty webhook data');
    }
    
    $webhookData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        quickResponse('error', 'Invalid JSON data');
    }
    
    // Validaciones básicas
    if (!isset($webhookData['type'])) {
        quickResponse('error', 'Missing webhook type');
    }
    
    // Solo procesar webhooks de pagos
    if ($webhookData['type'] !== 'payment') {
        quickResponse('ignored', 'Not a payment webhook', ['type' => $webhookData['type']]);
    }
    
    if (!isset($webhookData['data']['id'])) {
        quickResponse('error', 'Missing payment ID');
    }
    
    // Incluir dependencias
    require_once '../includes/db.php';
    require_once '../models/PaymentModel.php';
    
    // Crear instancia del modelo de pagos
    $paymentModel = new PaymentModel();
    
    // Procesar webhook
    $result = $paymentModel->processWebhook($webhookData);
    
    if ($result['status'] === 'success') {
        quickResponse('success', 'Webhook processed successfully', [
            'transaction_id' => $result['transaction_id'] ?? null
        ]);
    } else {
        quickResponse('error', $result['message'] ?? 'Processing failed', $result);
    }
    
} catch (Exception $e) {
    quickResponse('error', 'Exception: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} catch (Error $e) {
    quickResponse('error', 'Fatal error: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// Respuesta por defecto (no debería llegar aquí)
quickResponse('error', 'Unexpected end of script');
?>