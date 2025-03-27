<?php
// public/delete_giftlist.php

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

// Verificar autenticación
$auth = new Auth($pdo);
$auth->require('login.php');

// Obtener el ID de la lista a eliminar desde la URL
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($id)) {
    set_flash_message('danger', 'ID de lista no especificado.');
    header("Location: dashboard.php");
    exit;
}

// Verificar que el usuario es propietario de la lista
$stmt = $pdo->prepare("SELECT user_id FROM gift_lists WHERE id = ?");
$stmt->execute([$id]);
$list = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$list) {
    set_flash_message('danger', 'Lista de regalos no encontrada.');
    header("Location: dashboard.php");
    exit;
}

// Verificar que el usuario actual es el propietario o un administrador
if ($list['user_id'] != $_SESSION['user']['id'] && $_SESSION['user']['role'] !== 'admin') {
    set_flash_message('danger', 'No tienes permisos para eliminar esta lista.');
    header("Location: dashboard.php");
    exit;
}

// Implementar la eliminación
try {
    // Primero eliminamos los regalos asociados a la lista
    $stmt = $pdo->prepare("DELETE FROM gifts WHERE gift_list_id = ?");
    $stmt->execute([$id]);
    
    // Luego eliminamos la lista
    $stmt = $pdo->prepare("DELETE FROM gift_lists WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        set_flash_message('success', 'Lista de regalos eliminada exitosamente.');
    } else {
        set_flash_message('danger', 'Error al eliminar la lista de regalos.');
    }
} catch (Exception $e) {
    error_log("Error al eliminar lista: " . $e->getMessage());
    set_flash_message('danger', 'Error al eliminar la lista: ' . $e->getMessage());
}

// Redirigir al dashboard
header("Location: dashboard.php");
exit;