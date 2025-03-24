<?php
// public/get_preset_products.php

// Activar reporte de errores (para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/db.php';

// Establecer cabecera para JSON
header('Content-Type: application/json');

// Verificar que se envíe el parámetro preset_id
if (!isset($_GET['preset_id']) || empty($_GET['preset_id'])) {
    echo json_encode(['success' => false, 'error' => 'No se proporcionó un preset_id']);
    exit;
}

$preset_id = intval($_GET['preset_id']);

try {
    // Registrar la solicitud para depuración
    error_log("Solicitud de productos para preset_id: $preset_id");
    
    // Consultar todos los productos asociados al preset (temario)
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM preset_products WHERE preset_list_id = ?");
    $stmt->execute([$preset_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Registrar el número de productos encontrados
    error_log("Productos encontrados: " . count($products));
    
    // Devolver resultado
    echo json_encode(['success' => true, 'products' => $products]);
} catch (PDOException $e) {
    // Registrar el error
    error_log("Error al obtener productos: " . $e->getMessage());
    
    // Devolver error
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}