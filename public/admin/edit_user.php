<?php
// public/admin/edit_user.php

// Inicia la sesión solo si no está activa
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

// Obtener el ID del usuario a editar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó el usuario a editar.</div></div>";
    exit;
}

// Consultar la información del usuario a editar
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Usuario no encontrado.</div></div>";
    exit;
}

$error = "";
$successMessage = "";

// Procesar el formulario al enviarlo
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos del formulario (excepto rut, que no se modifica)
    $name           = $_POST['name'] ?? "";
    $lastname       = $_POST['lastname'] ?? "";
    $phone          = $_POST['phone'] ?? "";
    $bank           = $_POST['bank'] ?? "";
    $account_type   = $_POST['account_type'] ?? "";
    $account_number = $_POST['account_number'] ?? "";
    // $rut no se actualizará
    $email          = $_POST['email'] ?? "";
    $role           = $_POST['role'] ?? "user";
    $password       = $_POST['password'] ?? "";

    try {
        if (!empty($password)) {
            $stmt = $pdo->prepare("UPDATE users 
                                   SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, account_number = ?, email = ?, password = ?, role = ?
                                   WHERE id = ?");
            $updated = $stmt->execute([
                $name, $lastname, $phone, $bank, $account_type, $account_number, $email, password_hash($password, PASSWORD_DEFAULT), $role, $id
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE users 
                                   SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, account_number = ?, email = ?, role = ?
                                   WHERE id = ?");
            $updated = $stmt->execute([
                $name, $lastname, $phone, $bank, $account_type, $account_number, $email, $role, $id
            ]);
        }

        if ($updated) {
            $successMessage = "Usuario actualizado exitosamente.";
            // Actualizar los datos en $user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
        } else {
            $error = "Error al actualizar el usuario.";
        }
    } catch (Exception $ex) {
        $error = "Excepción: " . $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - GiftList App</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Editar Usuario</h1>
        <?php if (!empty($successMessage)) : ?>
            <div class="alert alert-success"><?php echo $successMessage; ?></div>
        <?php endif; ?>
        <?php if (!empty($error)) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apellido:</label>
                <input type="text" name="lastname" class="form-control" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Teléfono:</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Banco:</label>
                <input type="text" name="bank" class="form-control" value="<?php echo htmlspecialchars($user['bank']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo de Cuenta:</label>
                <input type="text" name="account_type" class="form-control" value="<?php echo htmlspecialchars($user['account_type']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Número de Cuenta:</label>
                <input type="text" name="account_number" class="form-control" value="<?php echo htmlspecialchars($user['account_number']); ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">RUT:</label>
                <input type="text" name="rut" class="form-control" value="<?php echo htmlspecialchars($user['rut']); ?>" readonly>
            </div>
            <div class="mb-3">
                <label class="form-label">Email:</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Rol:</label>
                <select name="role" class="form-select" required>
                    <option value="user" <?php if ($user['role'] === 'user') echo 'selected'; ?>>Usuario Estándar</option>
                    <option value="admin" <?php if ($user['role'] === 'admin') echo 'selected'; ?>>Administrador</option>
                    <!-- Agrega otros roles si es necesario -->
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Nueva Contraseña (dejar en blanco para mantener la actual):</label>
                <input type="password" name="password" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
            <a href="users.php" class="btn btn-secondary">Volver a Usuarios</a>
        </form>
    </div>
    <!-- Bootstrap Bundle JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
