<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth = new AuthController($pdo);
    $result = $auth->register($_POST);
    if ($result["success"]) {
        header("Location: login.php");
        exit;
    } else {
        $error = $result["message"];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrarse - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Registrarse</h1>
        <?php if(isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Nombre:</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Apellido:</label>
                <input type="text" name="lastname" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Teléfono:</label>
                <input type="text" name="phone" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Banco:</label>
                <input type="text" name="bank" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Tipo de Cuenta:</label>
                <input type="text" name="account_type" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">Número de Cuenta:</label>
                <input type="text" name="account_number" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label">RUT:</label>
                <input type="text" name="rut" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email:</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">Registrarse</button>
        </form>
        <p class="mt-3">¿Ya tienes cuenta? <a href="login.php">Inicia sesión aquí</a></p>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>