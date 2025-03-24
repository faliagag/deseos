<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$admin = new AdminController($pdo);
$transactions = $admin->listTransactions();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Transacciones - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Transacciones</h1>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Lista</th>
                    <th>Producto</th>
                    <th>Usuario</th>
                    <th>Monto</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $tx): ?>
                <tr>
                    <td><?php echo $tx["id"]; ?></td>
                    <td><?php echo $tx["gift_list_id"]; ?></td>
                    <td><?php echo $tx["gift_id"] ?? "-"; ?></td>
                    <td><?php echo $tx["user_id"] ?? "-"; ?></td>
                    <td>$<?php echo number_format($tx["amount"],2); ?></td>
                    <td><?php echo $tx["created_at"]; ?></td>
                    <td><?php echo htmlspecialchars($tx["status"]); ?></td>
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