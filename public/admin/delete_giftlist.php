<?php
// public/admin/delete_giftlist.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/GiftListController.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID de la lista a eliminar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó la lista a eliminar.</div></div>";
    exit;
}

// Instanciar el controlador
$glc = new GiftListController($pdo);

// Intentar eliminar la lista
if ($glc->delete($id)) {
    header("Location: giftlists.php");
    exit;
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error al eliminar la lista de regalos.</div></div>";
}
?>