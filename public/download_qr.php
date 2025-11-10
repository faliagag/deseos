<?php
/**
 * DESCARGA DE CÓDIGOS QR - VERSIÓN 2.1
 * Permite descargar el QR de una lista de regalos
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/QRCodeModel.php';

$config = include __DIR__ . '/../config/config.php';

if (!$config['qr_codes']['enabled']) {
    http_response_code(404);
    die('Funcionalidad no disponible');
}

$listId = $_GET['list_id'] ?? null;

if (!$listId) {
    http_response_code(400);
    die('ID de lista requerido');
}

try {
    $pdo = getConnection();
    $qrModel = new QRCodeModel($pdo, $config);
    
    // Obtener o generar QR
    $qr = $qrModel->getQRCode($listId);
    
    if (!$qr) {
        // Generar nuevo QR
        $result = $qrModel->generateQRCode($listId);
        if (!$result['success']) {
            throw new Exception('Error generando QR');
        }
        $qr = $qrModel->getQRCode($listId);
    }
    
    // Incrementar contador de descargas
    $qrModel->incrementDownloads($qr['id']);
    
    // Si es data URI, extraer imagen
    if (strpos($qr['qr_data'], 'data:image') === 0) {
        // Extraer base64
        $data = explode(',', $qr['qr_data']);
        $imageData = base64_decode($data[1]);
        
        // Determinar tipo de imagen
        if (strpos($data[0], 'png') !== false) {
            $contentType = 'image/png';
            $extension = 'png';
        } elseif (strpos($data[0], 'svg') !== false) {
            $contentType = 'image/svg+xml';
            $extension = 'svg';
        } else {
            $contentType = 'image/jpeg';
            $extension = 'jpg';
        }
        
        // Headers para descarga
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="lista-qr-' . $listId . '.' . $extension . '"');
        header('Content-Length: ' . strlen($imageData));
        
        echo $imageData;
        exit;
    }
    
    // Si es ruta de archivo
    if (file_exists($qr['qr_image_path'])) {
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="lista-qr-' . $listId . '.png"');
        readfile($qr['qr_image_path']);
        exit;
    }
    
    throw new Exception('QR no disponible');
    
} catch (Exception $e) {
    error_log("Error descargando QR: " . $e->getMessage());
    http_response_code(500);
    die('Error al descargar QR');
}
