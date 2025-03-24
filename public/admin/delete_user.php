<?php
// public/admin/delete_user.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir la conexión a la base de datos y las funciones de autenticación
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID del usuario a eliminar desde la URL
$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó el usuario a eliminar.</div></div>";
    exit;
}

// Instanciar el controlador administrativo
$adminController = new AdminController($pdo);

// Intentar eliminar el usuario
if ($adminController->deleteUser($id)) {
    // Redirigir a la página de gestión de usuarios
    header("Location: users.php");
    exit;
} else {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error al eliminar el usuario.</div></div>";
}
?>