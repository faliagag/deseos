<?php
/**
 * AJAX: Rechazar testimonio
 */

session_start();
require_once '../../../includes/db.php';
require_once '../../../models/AdminModel.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Leer datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID de testimonio requerido']);
    exit;
}

try {
    $adminModel = new AdminModel($pdo);
    $reason = $input['reason'] ?? '';
    $result = $adminModel->rejectTestimonial($input['id'], $reason);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Testimonio rechazado']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al rechazar testimonio']);
    }
    
} catch (Exception $e) {
    error_log("Error rechazando testimonio: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno']);
}
?>