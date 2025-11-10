<?php
/**
 * GENERACIÓN DE QR - VERSIÓN 2.1
 * Endpoint para generar QR codes
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/QRCodeModel.php';

header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$config = include __DIR__ . '/../config/config.php';

if (!$config['qr_codes']['enabled']) {
    echo json_encode(['success' => false, 'error' => 'Funcionalidad no disponible']);
    exit;
}

$listId = $_POST['list_id'] ?? null;

if (!$listId) {
    echo json_encode(['success' => false, 'error' => 'ID de lista requerido']);
    exit;
}

try {
    $pdo = getConnection();
    $qrModel = new QRCodeModel($pdo, $config);
    
    // Verificar que el usuario sea dueño de la lista
    $stmt = $pdo->prepare("SELECT user_id FROM gift_lists WHERE id = ?");
    $stmt->execute([$listId]);
    $list = $stmt->fetch();
    
    if (!$list || $list['user_id'] != $_SESSION['user']['id']) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    
    // Generar QR
    $result = $qrModel->generateQRCode($listId);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Error generando QR: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al generar QR']);
}
