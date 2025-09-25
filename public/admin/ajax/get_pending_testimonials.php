<?php
/**
 * AJAX: Obtener testimonios pendientes para el dashboard
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
    $testimonials = $adminModel->getPendingTestimonials(10);
    
    // Formatear datos para el frontend
    $formattedTestimonials = [];
    foreach ($testimonials as $testimonial) {
        $formattedTestimonials[] = [
            'id' => $testimonial['id'],
            'user_name' => $testimonial['user_name'],
            'user_email' => $testimonial['user_email'],
            'rating' => (int)$testimonial['rating'],
            'content' => mb_substr($testimonial['content'], 0, 100) . (mb_strlen($testimonial['content']) > 100 ? '...' : ''),
            'created_at' => $testimonial['created_at']
        ];
    }
    
    echo json_encode($formattedTestimonials);
    
} catch (Exception $e) {
    error_log("Error en get_pending_testimonials: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>