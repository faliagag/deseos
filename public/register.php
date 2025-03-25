<?php
// public/register.php

// Activar reporte de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/AuthController.php';
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

// Lista de bancos chilenos
$bancos_chilenos = [
    "Banco de Chile",
    "Banco Edwards (Banco de Chile)",
    "Banco Estado",
    "Scotiabank Chile",
    "Banco de Crédito e Inversiones (BCI)",
    "Corpbanca (Itaú-Corpbanca)",
    "Banco Bice",
    "HSBC Bank (Chile)",
    "Banco Santander-Chile",
    "Banco Itaú Chile",
    "Banco Security",
    "Banco Falabella",
    "Banco Ripley",
    "Banco Consorcio",
    "Banco Internacional",
    "Coopeuch"
];

// Tipos de cuenta
$tipos_cuenta = [
    "Cuenta Corriente",
    "Cuenta Vista"
];

// Variable para almacenar errores
$errors = [];
$data = []; // Para mantener los datos del formulario

// Procesar el formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Almacenar datos del formulario para evitar que el usuario tenga que reingresarlos
    $data = $_POST;
    
    // Validar RUT chileno
    $rut = isset($_POST['rut']) ? trim($_POST['rut']) : '';
    
    if (empty($rut)) {
        $errors['rut'] = "El RUT es obligatorio";
    } elseif (!validarRutChileno($rut)) {
        $errors['rut'] = "El formato del RUT no es válido o el dígito verificador es incorrecto";
    } else {
        // Verificar que el RUT no esté ya registrado
        $stmt = $pdo->prepare("SELECT id, email FROM users WHERE rut = ?");
        $stmt->execute([formatearRut($rut)]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            // El RUT ya está registrado, verificar si el email coincide
            $registeredEmail = $existingUser['email'];
            $enteredEmail = isset($_POST['email']) ? trim($_POST['email']) : '';
            
            // Configurar variables para mostrar mensaje de recuperación
            $rutRegistered = true;
            $emailMatches = ($registeredEmail === $enteredEmail);
            
            $errors['rut'] = "Este RUT ya está registrado en el sistema";
        }
    }
    
    // Validar email
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email)) {
        $errors['email'] = "El email es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "El formato del email no es válido";
    } else {
        // Verificar que el email no esté ya registrado
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = "Este email ya está registrado";
        }
    }
    
    // Validar nombre
    if (empty($_POST['name'])) {
        $errors['name'] = "El nombre es obligatorio";
    }
    
    // Validar apellido
    if (empty($_POST['lastname'])) {
        $errors['lastname'] = "El apellido es obligatorio";
    }
    
    // Validar teléfono
    if (empty($_POST['phone'])) {
        $errors['phone'] = "El teléfono es obligatorio";
    } elseif (!preg_match('/^[0-9+\s]+$/', $_POST['phone'])) {
        $errors['phone'] = "El teléfono debe contener solo números y el signo +";
    }
    
    // Validar banco (opcional pero debe ser de la lista)
    if (!empty($_POST['bank']) && !in_array($_POST['bank'], $bancos_chilenos)) {
        $errors['bank'] = "Por favor seleccione un banco de la lista";
    }
    
    // Validar tipo de cuenta (opcional pero debe ser de la lista)
    if (!empty($_POST['account_type']) && !in_array($_POST['account_type'], $tipos_cuenta)) {
        $errors['account_type'] = "Por favor seleccione un tipo de cuenta válido";
    }
    
    // Validar contraseña
    if (empty($_POST['password'])) {
        $errors['password'] = "La contraseña es obligatoria";
    } elseif (strlen($_POST['password']) < 6) {
        $errors['password'] = "La contraseña debe tener al menos 6 caracteres";
    }
    
    // Si no hay errores, proceder con el registro
    if (empty($errors)) {
        try {
            // Formatear el RUT antes de guardarlo
            $_POST['rut'] = formatearRut($rut);
            
            $auth = new AuthController($pdo);
            $result = $auth->register($_POST);
            
            if ($result["success"]) {
                // Guardar mensaje de éxito
                set_flash_message('success', '¡Registro exitoso! Ahora puedes iniciar sesión.');
                header("Location: login.php");
                exit;
            } else {
                $errors['general'] = $result["message"];
            }
        } catch (Exception $e) {
            $errors['general'] = "Error en el registro: " . $e->getMessage();
        }
    }
}

// Función para validar RUT chileno
function validarRutChileno($rut) {
    // Quitar puntos y guión
    $rut = str_replace(['.', '-'], '', $rut);
    
    // Obtener dígito verificador
    $dv = substr($rut, -1);
    
    // Obtener número sin DV
    $numero = substr($rut, 0, -1);
    
    // Validar que sea un número
    if (!is_numeric($numero)) {
        return false;
    }
    
    // Calcular dígito verificador
    $suma = 0;
    $multiplo = 2;
    
    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $suma += $numero[$i] * $multiplo;
        $multiplo = $multiplo < 7 ? $multiplo + 1 : 2;
    }
    
    $dvEsperado = 11 - ($suma % 11);
    
    if ($dvEsperado == 11) {
        $dvEsperado = '0';
    } elseif ($dvEsperado == 10) {
        $dvEsperado = 'K';
    } else {
        $dvEsperado = (string)$dvEsperado;
    }
    
    // Comparar dígito verificador
    return strtoupper($dv) == strtoupper($dvEsperado);
}

// Función para formatear RUT (XX.XXX.XXX-Y)
function formatearRut($rut) {
    // Quitar puntos y guión
    $rut = str_replace(['.', '-'], '', $rut);
    
    // Obtener dígito verificador
    $dv = substr($rut, -1);
    
    // Obtener número sin DV
    $numero = substr($rut, 0, -1);
    
    // Formatear con puntos
    $rutFormateado = number_format($numero, 0, '', '.');
    
    // Agregar guión y dígito verificador
    return $rutFormateado . '-' . $dv;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrarse - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
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
                        <a class="nav-link active" href="register.php">Registrarse</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container my-5">
        <div class="form-container">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">Crear una cuenta</h2>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['general'])): ?>
                        <div class="alert alert-danger"><?php echo $errors['general']; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="" id="registerForm" class="needs-validation" novalidate>
                        <div class="row g-3">
                            <!-- Datos personales -->
                            <div class="col-md-6">
                                <label for="name" class="form-label required-field">Nombre</label>
                                <input type="text" name="name" id="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" 
                                    value="<?php echo htmlspecialchars($data['name'] ?? ''); ?>" required>
                                <?php if (isset($errors['name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="lastname" class="form-label required-field">Apellido</label>
                                <input type="text" name="lastname" id="lastname" class="form-control <?php echo isset($errors['lastname']) ? 'is-invalid' : ''; ?>" 
                                    value="<?php echo htmlspecialchars($data['lastname'] ?? ''); ?>" required>
                                <?php if (isset($errors['lastname'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['lastname']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="rut" class="form-label required-field">RUT</label>
                                <input type="text" name="rut" id="rut" class="form-control <?php echo isset($errors['rut']) ? 'is-invalid' : ''; ?>" 
                                    placeholder="12.345.678-9" value="<?php echo htmlspecialchars($data['rut'] ?? ''); ?>" required>
                                <?php if (isset($errors['rut'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['rut']; ?></div>
                                    <?php if (isset($rutRegistered) && $rutRegistered): ?>
                                        <div class="mt-2 alert alert-warning">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <?php if (isset($emailMatches) && $emailMatches): ?>
                                                Este RUT ya está registrado con el email que ingresaste.
                                                <a href="password_recovery.php" class="alert-link">¿Olvidaste tu contraseña?</a>
                                            <?php else: ?>
                                                Si olvidaste tu contraseña y conoces el email de registro, puedes
                                                <a href="password_recovery.php" class="alert-link">recuperar tu contraseña</a>.
                                                <br>Si no recuerdas el email, contacta a 
                                                <a href="mailto:soporte@giftlistapp.com" class="alert-link">soporte@giftlistapp.com</a>.
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="form-text">Formato: 12.345.678-9</div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="phone" class="form-label required-field">Teléfono</label>
                                <input type="text" name="phone" id="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                    placeholder="+56912345678" value="<?php echo htmlspecialchars($data['phone'] ?? ''); ?>" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Datos bancarios -->
                            <div class="col-md-4">
                                <label for="bank" class="form-label">Banco</label>
                                <select name="bank" id="bank" class="form-select <?php echo isset($errors['bank']) ? 'is-invalid' : ''; ?>">
                                    <option value="">Seleccione un banco (opcional)</option>
                                    <?php foreach ($bancos_chilenos as $banco): ?>
                                        <option value="<?php echo htmlspecialchars($banco); ?>" <?php echo (isset($data['bank']) && $data['bank'] === $banco) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($banco); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['bank'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['bank']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="account_type" class="form-label">Tipo de Cuenta</label>
                                <select name="account_type" id="account_type" class="form-select <?php echo isset($errors['account_type']) ? 'is-invalid' : ''; ?>">
                                    <option value="">Seleccione tipo (opcional)</option>
                                    <?php foreach ($tipos_cuenta as $tipo): ?>
                                        <option value="<?php echo htmlspecialchars($tipo); ?>" <?php echo (isset($data['account_type']) && $data['account_type'] === $tipo) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['account_type'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['account_type']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="account_number" class="form-label">Número de Cuenta</label>
                                <input type="text" name="account_number" id="account_number" class="form-control <?php echo isset($errors['account_number']) ? 'is-invalid' : ''; ?>" 
                                    value="<?php echo htmlspecialchars($data['account_number'] ?? ''); ?>">
                                <?php if (isset($errors['account_number'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['account_number']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Datos de acceso -->
                            <div class="col-md-6">
                                <label for="email" class="form-label required-field">Email</label>
                                <input type="email" name="email" id="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                    placeholder="correo@ejemplo.com" value="<?php echo htmlspecialchars($data['email'] ?? ''); ?>" required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="password" class="form-label required-field">Contraseña</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                                        required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['password']; ?></div>
                                <?php else: ?>
                                    <div class="form-text">Mínimo 6 caracteres</div>
                                <?php endif; ?>
                                <div id="passwordStrength" class="password-feedback"></div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <small>
                                    <i class="bi bi-info-circle"></i> 
                                    Los campos marcados con <span class="text-danger">*</span> son obligatorios.
                                    <br>
                                    Si olvida su contraseña, podrá recuperarla a través de su correo electrónico.
                                    <br>
                                    Si no recuerda su correo, deberá comunicarse con soporte.
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Crear Cuenta</button>
                            <a href="login.php" class="btn btn-outline-secondary">¿Ya tienes cuenta? Inicia sesión</a>
                        </div>
                    </form>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Formateo automático del RUT
            const rutInput = document.getElementById('rut');
            if (rutInput) {
                rutInput.addEventListener('input', function(e) {
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
                        value = rutFormateado + '-' + dv;
                    }
                    
                    e.target.value = value;
                });
            }
            
            // Mostrar/ocultar contraseña
            const togglePassword = document.getElementById('togglePassword');
            const password = document.getElementById('password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                
                // Cambiar el ícono del botón
                const icon = togglePassword.querySelector('i');
                icon.classList.toggle('bi-eye');
                icon.classList.toggle('bi-eye-slash');
            });
            
            // Evaluar fortaleza de la contraseña
            const passwordStrength = document.getElementById('passwordStrength');
            
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
        });
    </script>
</body>
</html>