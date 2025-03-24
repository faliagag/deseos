<?php
// public/admin/users.php

// Inicia la sesión solo si aún no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir la conexión a la base de datos y la función de autenticación
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es un administrador
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el término de búsqueda enviado por GET
$searchTerm = $_GET['q'] ?? "";

if ($searchTerm != "") {
    // Agregar comodines para la búsqueda parcial
    $searchWildcard = "%" . $searchTerm . "%";
    // Consulta preparada para buscar en varias columnas
    $stmt = $pdo->prepare("
        SELECT * FROM users 
        WHERE name LIKE ? 
           OR lastname LIKE ? 
           OR phone LIKE ? 
           OR bank LIKE ? 
           OR account_type LIKE ? 
           OR account_number LIKE ? 
           OR rut LIKE ? 
           OR email LIKE ?
        ORDER BY id DESC
    ");
    $stmt->execute([
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard,
        $searchWildcard
    ]);
    $users = $stmt->fetchAll();
} else {
    // Si no se envía búsqueda, listar todos los usuarios
    $stmt = $pdo->query("SELECT * FROM users ORDER BY id DESC");
    $users = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Usuarios - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Gestión de Usuarios</h1>
        <!-- Buscador de Usuarios -->
        <form method="get" action="users.php" class="d-flex mb-3">
            <input type="text" name="q" class="form-control me-2" placeholder="Buscar por nombre, apellido, email, RUT, etc." value="<?php echo htmlspecialchars($searchTerm); ?>">
            <button type="submit" class="btn btn-outline-primary">Buscar</button>
        </form>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Teléfono</th>
                    <th>Banco</th>
                    <th>Tipo de Cuenta</th>
                    <th>Número de Cuenta</th>
                    <th>RUT</th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user["id"]; ?></td>
                            <td><?php echo htmlspecialchars($user["name"]); ?></td>
                            <td><?php echo htmlspecialchars($user["lastname"]); ?></td>
                            <td><?php echo htmlspecialchars($user["phone"]); ?></td>
                            <td><?php echo htmlspecialchars($user["bank"]); ?></td>
                            <td><?php echo htmlspecialchars($user["account_type"]); ?></td>
                            <td><?php echo htmlspecialchars($user["account_number"]); ?></td>
                            <td><?php echo htmlspecialchars($user["rut"]); ?></td>
                            <td><?php echo htmlspecialchars($user["email"]); ?></td>
                            <td><?php echo htmlspecialchars($user["role"]); ?></td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $user["id"]; ?>" class="btn btn-sm btn-warning">Editar</a>
                                <a href="delete_user.php?id=<?php echo $user["id"]; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar usuario?');">Eliminar</a>
                                <!-- Puedes agregar botones para bloquear o asignar roles adicionales aquí -->
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11"><div class="alert alert-info">No se encontraron usuarios.</div></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
