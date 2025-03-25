<?php
// public/checkout.php

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../controllers/PaymentController.php";
require_once __DIR__ . "/../includes/helpers.php";

// Verificar si hay un carrito de compras
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user']['id'] ?? null;
$message = $_SESSION['checkout_message'] ?? '';

// Procesar el pago cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar datos de pago (esto es solo un ejemplo)
    $errors = [];
    
    if (empty($_POST['cardholder'])) {
        $errors[] = "El nombre del titular es obligatorio";
    }
    
    if (empty($_POST['cardnumber']) || !preg_match('/^[0-9]{16}$/', str_replace(' ', '', $_POST['cardnumber']))) {
        $errors[] = "El número de tarjeta debe tener 16 dígitos";
    }
    
    if (empty($_POST['expiry']) || !preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $_POST['expiry'])) {
        $errors[] = "La fecha de expiración debe tener el formato MM/YY";
    } else {
        // Validar que la fecha no ha expirado
        list($month, $year) = explode('/', $_POST['expiry']);
        $expiry_date = \DateTime::createFromFormat('my', $month . $year);
        $now = new \DateTime();
        
        if ($expiry_date < $now) {
            $errors[] = "La tarjeta ha expirado";
        }
    }
    
    if (empty($_POST['cvv']) || !preg_match('/^[0-9]{3,4}$/', $_POST['cvv'])) {
        $errors[] = "El código de seguridad debe tener 3 o 4 dígitos";
    }
    
    // Si no hay errores, procesar cada elemento del carrito
    if (empty($errors)) {
        $paymentController = new PaymentController($pdo);
        $transactionResults = [];
        
        foreach ($_SESSION['cart'] as $item) {
            $paymentData = [
                'gift_list_id' => $item['gift_list_id'],
                'gift_id' => $item['gift_id'],
                'amount' => $item['price'] * $item['quantity'],
                'currency' => 'usd',
                'quantity' => $item['quantity'],
                'message' => $message
            ];
            
            $result = $paymentController->processPayment($paymentData, $user_id);
            $transactionResults[] = $result;
        }
        
        // Si todos los pagos fueron exitosos
        $allSuccessful = true;
        foreach ($transactionResults as $result) {
            if (!$result['success']) {
                $allSuccessful = false;
                break;
            }
        }
        
        if ($allSuccessful) {
            // Limpiar carrito y mensaje de checkout
            $_SESSION['cart'] = [];
            $_SESSION['checkout_message'] = '';
            
            // Guardar mensaje de éxito
            set_flash_message('success', '¡Compra realizada con éxito! Gracias por tu contribución.');
            header("Location: index.php");
            exit;
        } else {
            $errors[] = "Hubo un problema al procesar alguno de los pagos. Por favor, intenta nuevamente.";
        }
    }
}

// Calcular totales del carrito
$cartTotal = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checkout - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .card-payment {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .payment-summary {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
            border-color: #3498db;
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
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="card card-payment shadow">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">Finalizar Compra</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="payment-summary">
                    <h5 class="card-title mb-3">Resumen de tu compra</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Regalo</th>
                                    <th>Precio</th>
                                    <th>Cantidad</th>
                                    <th class="text-end">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($_SESSION['cart'] as $item): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td class="text-end">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th class="text-end">$<?php echo number_format($cartTotal, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <?php if (!empty($message)): ?>
                    <div class="mt-3">
                        <h6>Tu mensaje:</h6>
                        <p class="bg-light p-2 rounded">
                            <?php echo nl2br(htmlspecialchars($message)); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <form method="post" action="" class="needs-validation" novalidate>
                    <h5 class="card-title mb-3">Información de pago</h5>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cardholder" class="form-label">Nombre del titular</label>
                            <input type="text" class="form-control" id="cardholder" name="cardholder" required>
                            <div class="invalid-feedback">
                                Por favor ingresa el nombre del titular.
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="cardnumber" class="form-label">Número de tarjeta</label>
                            <input type="text" class="form-control" id="cardnumber" name="cardnumber" 
                                placeholder="XXXX XXXX XXXX XXXX" maxlength="19" required>
                            <div class="invalid-feedback">
                                Por favor ingresa un número de tarjeta válido.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="expiry" class="form-label">Fecha de expiración</label>
                            <input type="text" class="form-control" id="expiry" name="expiry" 
                                placeholder="MM/YY" maxlength="5" required>
                            <div class="invalid-feedback">
                                Por favor ingresa una fecha de expiración válida.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="cvv" class="form-label">Código de seguridad (CVV)</label>
                            <input type="text" class="form-control" id="cvv" name="cvv" 
                                placeholder="XXX" maxlength="4" required>
                            <div class="invalid-feedback">
                                Por favor ingresa el código de seguridad.
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="zipcode" class="form-label">Código postal</label>
                            <input type="text" class="form-control" id="zipcode" name="zipcode" required>
                            <div class="invalid-feedback">
                                Por favor ingresa tu código postal.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4 d-flex justify-content-between">
                        <a href="giftlist.php?link=<?php echo urlencode($_GET['from_list'] ?? ''); ?>" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver
                        </a>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-credit-card"></i> Pagar $<?php echo number_format($cartTotal, 2); ?>
                        </button>
                    </div>
                </form>
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
            // Validación del formulario
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Formateo del número de tarjeta
            const cardNumberInput = document.getElementById('cardnumber');
            if (cardNumberInput) {
                cardNumberInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    let formattedValue = '';
                    
                    for (let i = 0; i < value.length; i++) {
                        if (i > 0 && i % 4 === 0) {
                            formattedValue += ' ';
                        }
                        formattedValue += value[i];
                    }
                    
                    e.target.value = formattedValue;
                });
            }
            
            // Formateo de la fecha de expiración
            const expiryInput = document.getElementById('expiry');
            if (expiryInput) {
                expiryInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    let formattedValue = '';
                    
                    if (value.length > 0) {
                        formattedValue = value.substring(0, 2);
                        
                        if (value.length > 2) {
                            formattedValue += '/' + value.substring(2, 4);
                        }
                    }
                    
                    e.target.value = formattedValue;
                });
            }
        });
    </script>
</body>
</html>