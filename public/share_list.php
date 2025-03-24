<?php
// public/share_list.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/auth.php';

// Se puede verificar la autenticación si es necesario, por ejemplo:
// if (!isset($_SESSION['user'])) {
//     header("Location: login.php");
//     exit;
// }

// Obtener el ID de la lista a compartir desde la URL
$id = $_GET['id'] ?? '';

if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó la lista a compartir.</div></div>";
    exit;
}

// Instanciar el controlador para obtener el unique_link
$glc = new GiftListController($pdo);
$stmt = $pdo->prepare("SELECT unique_link FROM gift_lists WHERE id = ?");
$stmt->execute([$id]);
$list = $stmt->fetch();

if (!$list) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista no encontrada.</div></div>";
    exit;
}

// Generar la URL compartible
// Se asume que la página pública para ver la lista es giftlist.php y se utiliza el parámetro 'link'
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
// Suponiendo que giftlist.php se encuentra en la carpeta public/
$baseUrl = $protocol . "://" . $host . dirname($_SERVER['PHP_SELF']) . "/giftlist.php";
$shareUrl = $baseUrl . "?link=" . urlencode($list['unique_link']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Compartir Lista de Regalos</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Compartir Lista de Regalos</h1>
        <p>Utiliza el siguiente enlace para compartir la lista:</p>
        <div class="mb-3">
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($shareUrl); ?>" readonly onclick="this.select();">
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
    <!-- Bootstrap Bundle JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
