<?php
// public/admin/get_preset_products.php

// Activar reporte de errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Opcional: Verificar que el usuario sea administrador
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Acceso no autorizado']);
    exit;
}

header('Content-Type: application/json');

// Verificar que se envíe el parámetro preset_id
if (!isset($_GET['preset_id']) || empty($_GET['preset_id'])) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó un preset_id']);
    exit;
}

$preset_id = intval($_GET['preset_id']);

try {
    // Consultar todos los productos asociados al preset (temario)
    $stmt = $pdo->prepare("SELECT id, name FROM preset_products WHERE preset_list_id = ?");
    $stmt->execute([$preset_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
