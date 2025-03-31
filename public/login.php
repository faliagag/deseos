<?php
// public/login.php

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../includes/helpers.php';

// Redirigir si ya está autenticado
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = "";

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $auth = new AuthController($pdo);
    $result = $auth->login($_POST);
    
    // Si hay un error, lo guardamos
    if (isset($result['success']) && !$result['success']) {
        $error = $result['message'];
    }
    // Nota: Si la autenticación es exitosa, login() redirige directamente
}

// Cargar configuración global
$config = require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .login-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .card-header {
            background-color: #3498db;
            color: white;
            font-weight: bold;
        }
        
        .btn-primary {
            background-color: #3498db;
            border-color: #3498db;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">GiftList App</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="login-container">
            <div class="card shadow">
                <div class="card-header bg-primary text-white py-3">
                    <h3 class="mb-0">Iniciar Sesión</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php 
                    // Mostrar mensaje flash si existe
                    $flash = get_flash_message();
                    if ($flash): 
                    ?>
                        <div class="alert alert-<?php echo $flash['type']; ?>">
                            <?php echo $flash['message']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="correo@ejemplo.com" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Iniciar Sesión</button>
                            <a href="password_recovery.php" class="btn btn-link">¿Olvidaste tu contraseña?</a>
                        </div>
                    </form>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    ¿No tienes una cuenta? <a href="register.php" class="text-decoration-none">Regístrate aquí</a>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>