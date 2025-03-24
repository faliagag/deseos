<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Ejemplo: consulta de productos (debes tener una tabla 'products')
$stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Productos - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Gestión de Productos</h1>
        <!-- Aquí se pueden agregar botones para importar/exportar -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Stock</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $prod): ?>
                <tr>
                    <td><?php echo $prod["id"]; ?></td>
                    <td><?php echo htmlspecialchars($prod["name"]); ?></td>
                    <td><?php echo htmlspecialchars($prod["category"] ?? "N/A"); ?></td>
                    <td>$<?php echo number_format($prod["price"], 2); ?></td>
                    <td><?php echo $prod["stock"]; ?></td>
                    <td>
                        <a href="edit_product.php?id=<?php echo $prod["id"]; ?>" class="btn btn-sm btn-warning">Editar</a>
                        <a href="delete_product.php?id=<?php echo $prod["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar producto?');">Eliminar</a>
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
