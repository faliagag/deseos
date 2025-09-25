<?php
/**
 * AJAX: Procesar pagos rápidamente
 */

session_start();
require_once '../../../includes/db.php';
require_once '../../../models/AdminModel.php';
require_once '../../../models/PaymentModel.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

try {
    $paymentModel = new PaymentModel();
    $result = $paymentModel->processBiweeklyPayouts();
    
    if ($result['status'] === 'success') {
        echo json_encode([
            'success' => true, 
            'processed' => $result['processed_payouts'],
            'total_amount' => $result['total_amount']
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error en el procesamiento']);
    }
    
} catch (Exception $e) {
    error_log("Error procesando payouts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>