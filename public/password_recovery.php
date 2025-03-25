<?php
// public/password_recovery.php

// Activar reporte de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Redirigir si ya está autenticado
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

// Crear tabla password_resets si no existe
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
} catch (PDOException $e) {
    error_log("Error creando la tabla password_resets: " . $e->getMessage());
    // No interrumpimos el flujo, pero registramos el error
}

// Variables para mensajes
$error = '';
$success = '';
$emailSent = false;
$step = isset($_GET['step']) ? $_GET['step'] : 'request';

// Proceso para solicitar restablecimiento de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $identifier = trim($_POST['identifier'] ?? '');
    
    // Validar que se proporcionó un identificador (email o RUT)
    if (empty($identifier)) {
        $error = 'Por favor ingrese su email o RUT';
    } else {
        // Determinar si es email o RUT
        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        
        if ($isEmail) {
            // Buscar usuario por email
            $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE email = ?");
            $stmt->execute([$identifier]);
        } else {
            // Formatear RUT si es posible
            $rut = str_replace(['.', '-'], '', $identifier);
            if (strlen($rut) >= 2) {
                // Intentar formatear como RUT chileno
                $formattedRut = formatearRut($rut);
                $stmt = $pdo->prepare("SELECT id, email, name FROM users WHERE rut = ?");
                $stmt->execute([$formattedRut]);
            } else {
                $error = 'El identificador ingresado no es válido';
                $stmt = null;
            }
        }
        
        // Verificar si se encontró el usuario
        if (isset($stmt) && $stmt) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generar token único de recuperación (válido por 1 hora)
                $token = bin2hex(random_bytes(32));
                $expiryTime = time() + 3600; // 1 hora
                
                // Guardar token en la base de datos
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO password_resets (user_id, token, expires_at, created_at)
                        VALUES (?, ?, FROM_UNIXTIME(?), NOW())
                    ");
                    $stmt->execute([$user['id'], $token, $expiryTime]);
                    
                    // Generar enlace de recuperación
                    $resetLink = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . 
                                 $_SERVER['HTTP_HOST'] . 
                                 dirname($_SERVER['PHP_SELF']) . 
                                 '/password_recovery.php?step=reset&token=' . $token;
                    
                    // En un entorno real, aquí enviarías el email con el enlace de recuperación
                    // Por ahora, mostraremos el enlace en la página
                    $emailSent = true;
                    $userEmail = $user['email'];
                    $userName = $user['name'];
                    
                    // Mensaje de éxito
                    $success = 'Se ha enviado un enlace de recuperación a tu correo electrónico.';
                    
                    // En un entorno de producción, llamaríamos a una función para enviar el email aquí
                    // sendPasswordResetEmail($userEmail, $userName, $resetLink);
                } catch (PDOException $e) {
                    error_log("Error al guardar token de recuperación: " . $e->getMessage());
                    $error = "Error al procesar la solicitud. Por favor, intenta nuevamente.";
                }
            } else {
                // No se encontró usuario con ese email/RUT
                $error = 'No se encontró ninguna cuenta con esa información';
            }
        }
    }
}

// Proceso para establecer nueva contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $token = trim($_GET['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $password_confirm = trim($_POST['password_confirm'] ?? '');
    
    // Validar token
    if (empty($token)) {
        $error = 'Token de recuperación inválido';
    } 
    // Validar contraseña
    elseif (empty($password)) {
        $error = 'La nueva contraseña es obligatoria';
    }
    elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    }
    elseif ($password !== $password_confirm) {
        $error = 'Las contraseñas no coinciden';
    }
    else {
        try {
            // Verificar que el token es válido y no ha expirado
            $stmt = $pdo->prepare("
                SELECT pr.user_id 
                FROM password_resets pr
                WHERE pr.token = ? AND pr.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reset) {
                // Actualizar contraseña
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                
                if ($stmt->execute([$hashedPassword, $reset['user_id']])) {
                    // Eliminar token usado
                    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                    $stmt->execute([$token]);
                    
                    // Mensaje de éxito
                    $success = 'Tu contraseña ha sido actualizada correctamente. Ahora puedes iniciar sesión.';
                    
                    // Redireccionar después de 3 segundos
                    header('refresh:3;url=login.php');
                } else {
                    $error = 'Error al actualizar la contraseña. Inténtalo nuevamente.';
                }
            } else {
                $error = 'El enlace de recuperación es inválido o ha expirado';
            }
        } catch (PDOException $e) {
            error_log("Error al restablecer contraseña: " . $e->getMessage());
            $error = 'Error al procesar la solicitud. Por favor, intenta nuevamente.';
        }
    }
}

// Verificar token para reseteo de contraseña
    if ($step === 'reset' && !isset($_POST['password'])) {
    $token = trim($_GET['token'] ?? '');
    
    if (empty($token)) {
        $error = 'Token de recuperación inválido';
        $step = 'invalid';
    } else {
        try {
            // Verificar que el token es válido y no ha expirado
            $stmt = $pdo->prepare("
                SELECT user_id 
                FROM password_resets
                WHERE token = ? AND expires_at > NOW()
            ");
            $stmt->execute([$token]);
            
            if (!$stmt->fetch()) {
                $error = 'El enlace de recuperación es inválido o ha expirado';
                $step = 'invalid';
            }
        } catch (PDOException $e) {
            error_log("Error verificando token: " . $e->getMessage());
            $error = 'Error al verificar el enlace de recuperación';
            $step = 'invalid';
        }
    }
}

// Función para formatear RUT (XX.XXX.XXX-Y)
function formatearRut($rut) {
    // Quitar puntos y guión
    $rut = str_replace(['.', '-'], '', $rut);
    
    // Obtener dígito verificador
    $dv = substr($rut, -1);
    
    // Obtener número sin DV
    $numero = substr($rut, 0, -1);
    
    // Si no es un número, devolver el original
    if (!is_numeric($numero)) {
        return $rut;
    }
    
    // Formatear con puntos
    $rutFormateado = number_format($numero, 0, '', '.');
    
    // Agregar guión y dígito verificador
    return $rutFormateado . '-' . $dv;
}

// Función simulada para enviar email (en producción, esto usaría PHPMailer o similar)
function sendPasswordResetEmail($email, $name, $resetLink) {
    // Código para enviar email usando PHPMailer u otra biblioteca
    return true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .password-feedback {
            display: none;
            margin-top: 0.25rem;
            font-size: 0.875em;
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
                        <a class="nav-link" href="login.php">Iniciar Sesión</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container my-5">
        <div class="form-container">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0 fs-4">Recuperación de Contraseña</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($step === 'request' && !$emailSent): ?>
                        <!-- Paso 1: Solicitar recuperación -->
                        <form method="post" action="password_recovery.php?step=request" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="identifier" class="form-label">Email o RUT registrado</label>
                                <input type="text" id="identifier" name="identifier" class="form-control" 
                                       placeholder="ejemplo@correo.com o 12.345.678-9" required>
                                <div class="form-text">
                                    Ingresa el correo electrónico o RUT con el que te registraste.
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Solicitar Recuperación</button>
                                <a href="login.php" class="btn btn-outline-secondary">Volver a Iniciar Sesión</a>
                            </div>
                        </form>
                    <?php elseif ($step === 'request' && $emailSent): ?>
                        <!-- Confirmación de email enviado -->
                        <div class="text-center my-4">
                            <i class="bi bi-envelope-check text-success" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Email Enviado</h3>
                            <p>Hemos enviado un enlace de recuperación a <strong><?php echo htmlspecialchars($userEmail); ?></strong></p>
                            <p class="text-muted">Revisa tu bandeja de entrada y sigue las instrucciones para restablecer tu contraseña.</p>
                            
                            <div class="alert alert-warning mt-4">
                                <strong>Nota:</strong> En este entorno de desarrollo, el email no se envía realmente. 
                                Utiliza el siguiente enlace para simular el proceso:
                                <br><br>
                                <a href="<?php echo htmlspecialchars($resetLink); ?>" class="btn btn-warning btn-sm">
                                    Abrir enlace de recuperación
                                </a>
                            </div>
                            
                            <div class="mt-4">
                                <a href="login.php" class="btn btn-outline-secondary">Volver a Iniciar Sesión</a>
                            </div>
                        </div>
                    <?php elseif ($step === 'reset'): ?>
                        <!-- Paso 2: Restablecer contraseña -->
                        <form method="post" action="password_recovery.php?step=reset&token=<?php echo htmlspecialchars($token); ?>" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="password" class="form-label">Nueva Contraseña</label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password" class="form-control" 
                                           required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">Mínimo 6 caracteres</div>
                                <div id="passwordStrength" class="password-feedback"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                                <input type="password" id="password_confirm" name="password_confirm" class="form-control" 
                                       required>
                                <div id="passwordMatch" class="form-text"></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Cambiar Contraseña</button>
                            </div>
                        </form>
                    <?php elseif ($step === 'invalid'): ?>
                        <!-- Enlace inválido o expirado -->
                        <div class="text-center my-4">
                            <i class="bi bi-exclamation-circle text-danger" style="font-size: 4rem;"></i>
                            <h3 class="mt-3">Enlace Inválido</h3>
                            <p>El enlace de recuperación que utilizaste no es válido o ha expirado.</p>
                            <p class="text-muted">Los enlaces de recuperación son válidos solo por 1 hora después de ser solicitados.</p>
                            
                            <div class="mt-4">
                                <a href="password_recovery.php" class="btn btn-primary">Solicitar Nuevo Enlace</a>
                                <a href="login.php" class="btn btn-outline-secondary">Volver a Iniciar Sesión</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($step === 'request' && !$emailSent): ?>
                <div class="mt-4 text-center">
                    <p class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        Si no recuerdas el email con el que te registraste, contacta a 
                        <a href="mailto:soporte@giftlistapp.com">soporte@giftlistapp.com</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            if (togglePassword && password) {
                togglePassword.addEventListener('click', function() {
                    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                    password.setAttribute('type', type);
                    
                    // Cambiar el ícono del botón
                    const icon = togglePassword.querySelector('i');
                    icon.classList.toggle('bi-eye');
                    icon.classList.toggle('bi-eye-slash');
                });
            }
            
            // Evaluar fortaleza de la contraseña
            const passwordStrength = document.getElementById('passwordStrength');
            
            if (password && passwordStrength) {
                password.addEventListener('input', function() {
                    const value = password.value;
                    
                    // Mostrar feedback solo si hay caracteres
                    if (value.length > 0) {
                        passwordStrength.style.display = 'block';
                    } else {
                        passwordStrength.style.display = 'none';
                        return;
                    }
                    
                    // Evaluar fortaleza
                    let strength = 0;
                    
                    // Longitud
                    if (value.length >= 8) strength += 1;
                    
                    // Letras minúsculas y mayúsculas
                    if (/[a-z]/.test(value)) strength += 1;
                    if (/[A-Z]/.test(value)) strength += 1;
                    
                    // Números y caracteres especiales
                    if (/[0-9]/.test(value)) strength += 1;
                    if (/[^A-Za-z0-9]/.test(value)) strength += 1;
                    
                    // Mostrar retroalimentación según la fortaleza
                    switch (strength) {
                        case 0:
                        case 1:
                            passwordStrength.textContent = 'Contraseña débil';
                            passwordStrength.className = 'password-feedback text-danger';
                            break;
                        case 2:
                        case 3:
                            passwordStrength.textContent = 'Contraseña moderada';
                            passwordStrength.className = 'password-feedback text-warning';
                            break;
                        case 4:
                        case 5:
                            passwordStrength.textContent = 'Contraseña fuerte';
                            passwordStrength.className = 'password-feedback text-success';
                            break;
                    }
                });
            }
            
            // Verificar coincidencia de contraseñas
            const passwordConfirm = document.getElementById('password_confirm');
            const passwordMatch = document.getElementById('passwordMatch');
            
            if (password && passwordConfirm && passwordMatch) {
                function checkPasswordMatch() {
                    if (passwordConfirm.value.length > 0) {
                        if (password.value === passwordConfirm.value) {
                            passwordMatch.textContent = 'Las contraseñas coinciden';
                            passwordMatch.className = 'form-text text-success';
                        } else {
                            passwordMatch.textContent = 'Las contraseñas no coinciden';
                            passwordMatch.className = 'form-text text-danger';
                        }
                    } else {
                        passwordMatch.textContent = '';
                    }
                }
                
                password.addEventListener('input', checkPasswordMatch);
                passwordConfirm.addEventListener('input', checkPasswordMatch);
            }
            
            // Formateo de RUT en el campo de identificador
            const identifier = document.getElementById('identifier');
            
            if (identifier) {
                identifier.addEventListener('input', function(e) {
                    // Si parece un RUT (tiene números y posiblemente K)
                    if (/^[0-9kK.\-]*$/.test(e.target.value)) {
                        let value = e.target.value.replace(/[^\dkK]/g, '');
                        
                        if (value.length > 1) {
                            // Separar dígito verificador
                            const dv = value.charAt(value.length - 1);
                            const rut = value.substring(0, value.length - 1);
                            
                            // Formatear con puntos
                            let rutFormateado = '';
                            for (let i = rut.length - 1, j = 0; i >= 0; i--, j++) {
                                rutFormateado = rut.charAt(i) + rutFormateado;
                                if ((j + 1) % 3 === 0 && i !== 0) {
                                    rutFormateado = '.' + rutFormateado;
                                }
                            }
                            
                            // Añadir guión y dígito verificador
                            if (rutFormateado) {
                                value = rutFormateado + '-' + dv;
                                e.target.value = value;
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>