<?php
/**
 * AJAX: Obtener próximos pagos quincenales para el dashboard
 */

session_start();
require_once '../../../includes/db.php';
require_once '../../../models/AdminModel.php';

header('Content-Type: application/json');

// Verificar autenticación admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

try {
    $adminModel = new AdminModel($pdo);
    $payouts = $adminModel->getUpcomingPayouts(10);
    
    // Formatear datos para el frontend
    $formattedPayouts = [];
    foreach ($payouts as $payout) {
        $formattedPayouts[] = [
            'user_id' => $payout['user_id'],
            'user_name' => $payout['user_name'],
            'user_email' => $payout['user_email'],
            'amount' => (float)$payout['amount'],
            'transaction_count' => (int)$payout['transaction_count'],
            'payout_date' => $payout['payout_date'],
            'status' => $payout['status'],
            'oldest_transaction' => $payout['oldest_transaction']
        ];
    }
    
    echo json_encode($formattedPayouts);
    
} catch (Exception $e) {
    error_log("Error en get_upcoming_payouts: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>