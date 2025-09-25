<?php
/**
 * AJAX: Obtener actividad reciente del sistema
 */

session_start();
require_once '../../../includes/db.php';
require_once '../../../models/AdminModel.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

try {
    $adminModel = new AdminModel($pdo);
    $activities = $adminModel->getRecentActivity(15);
    
    echo json_encode($activities);
    
} catch (Exception $e) {
    error_log("Error obteniendo actividad reciente: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>