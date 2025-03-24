<?php
// public/login.php

// Asegúrate de que las rutas de inclusión son correctas
// Usa __DIR__ para obtener rutas absolutas
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Para depuración - Verifica si el archivo existe
if (!file_exists(__DIR__ . '/../controllers/AuthController.php')) {
    die('Error: No se puede encontrar el archivo AuthController.php. Ruta buscada: ' . __DIR__ . '/../controllers/AuthController.php');
}

// Redirigir si ya está autenticado
if (isset($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

// Variable para almacenar mensaje de error (si lo hay)
$error = "";

// Procesar el formulario al enviar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $auth = new AuthController($pdo);
        
        // Debug temporal - quitar en producción
        error_log("LOGIN ATTEMPT: " . print_r($_POST, true));
        
        // Llamar al método login con los datos del formulario
        $result = $auth->login($_POST);

        // Si falla, $auth->login() retorna ['success' => false, 'message' => '...']
        if (isset($result['success']) && !$result['success']) {
            $error = $result['message'];
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        error_log("Login exception: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - GiftList App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">Iniciar Sesión</div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email:</label>
                                <input type="email" id="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña:</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Entrar</button>
                        </form>
                        <p class="mt-3">¿No tienes cuenta? <a href="register.php">Regístrate aquí</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>