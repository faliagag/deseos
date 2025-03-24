<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}
$admin = new AdminController($pdo);
$giftLists = $admin->listGiftLists();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Listas de Regalos - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Gestión de Listas de Regalos</h1>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Título</th>
                    <th>Descripción</th>
                    <th>Fecha de Creación</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($giftLists as $list): ?>
                <tr>
                    <td><?php echo $list["id"]; ?></td>
                    <td><?php echo htmlspecialchars($list["title"]); ?></td>
                    <td><?php echo htmlspecialchars($list["description"]); ?></td>
                    <td><?php echo $list["created_at"]; ?></td>
                    <td>
                        <a href="edit_giftlist.php?id=<?php echo $list["id"]; ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="delete_giftlist.php?id=<?php echo $list["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar lista?');">Eliminar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
