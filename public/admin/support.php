<?php
// public/admin/support.php (Placeholder)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/auth.php';
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Soporte al Cliente - GiftList App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Soporte al Cliente</h1>
        <p>Aquí se gestionarán los tickets de soporte y consultas de los usuarios.</p>
        <!-- Implementa aquí la lógica para ver, responder y gestionar tickets -->
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
