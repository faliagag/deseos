<?php
// public/admin/delete_preset_list.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID de la lista predeterminada a eliminar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='alert alert-warning'>No se especificó la lista a eliminar.</div>";
    exit;
}

// Ejecutar la eliminación (la relación ON DELETE CASCADE en la tabla preset_products eliminará los productos asociados)
$stmt = $pdo->prepare("DELETE FROM preset_product_lists WHERE id = ?");
if ($stmt->execute([$id])) {
    header("Location: preset_product_lists.php"); // Redirige a la lista de preset lists
    exit;
} else {
    echo "<div class='alert alert-danger'>Error al eliminar la lista predeterminada.</div>";
}
?>
