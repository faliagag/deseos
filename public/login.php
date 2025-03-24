<?php
// public/login.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Iniciar sesión solo si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Variable para almacenar mensaje de error (si lo hay)
$error = "";

// Procesar el formulario al enviar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth = new AuthController($pdo);
    // Llamar al método login con los datos del formulario
    $result = $auth->login($_POST);

    // Si falla, $auth->login() retorna ['success' => false, 'message' => '...']
    if (!$result['success']) {
        $error = $result['message'];
        // No hay redirección aquí, la redirección exitosa ocurre dentro de AuthController->login()
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - GiftList App</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Iniciar Sesión</h1>
        <!-- Mostrar error si existe -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="mb-3">
                <label class="form-label">Email:</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Contraseña:</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-success">Entrar</button>
        </form>
        <p class="mt-3">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
    </div>
    <!-- Bootstrap Bundle JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
