<?php
// public/admin/edit_giftlist.php

// Inicia la sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/GiftListController.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID de la lista a editar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó la lista a editar.</div></div>";
    exit;
}

// Instanciar el controlador y obtener la información de la lista
$glc = new GiftListController($pdo);
$stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
$stmt->execute([$id]);
$list = $stmt->fetch();

if (!$list) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
    exit;
}

$error = "";
$successMessage = "";

// Procesar el formulario al enviar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos enviados
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    // Actualizar la lista mediante el controlador
    if ($glc->update($id, ['title' => $title, 'description' => $description])) {
        $successMessage = "Lista actualizada exitosamente.";
        // Refrescar la información de la lista
        $stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
        $stmt->execute([$id]);
        $list = $stmt->fetch();
    } else {
        $error = "Error al actualizar la lista.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Lista de Regalos</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Editar Lista de Regalos</h1>
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Título:</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($list['title']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción:</label>
                <textarea name="description" class="form-control" required><?php echo htmlspecialchars($list['description']); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Lista</button>
            <a href="giftlists.php" class="btn btn-secondary">Volver a Listas</a>
        </form>
    </div>
    <!-- Bootstrap Bundle JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>