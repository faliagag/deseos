<?php
// public/giftlist.php

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/helpers.php";

// Inicializar carrito si no existe
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Procesar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Acción: Agregar al carrito
    if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
        $gift_id = (int)$_POST['gift_id'];
        $gift_list_id = (int)$_POST['gift_list_id'];
        $name = $_POST['gift_name'];
        $price = (float)$_POST['gift_price'];
        $quantity = (int)$_POST['quantity'];
        
        // Verificar si el regalo ya está en el carrito
        $found = false;
        foreach ($_SESSION['cart'] as $key => $item) {
            if ($item['gift_id'] === $gift_id) {
                // Actualizar cantidad si ya existe
                $_SESSION['cart'][$key]['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        
        // Si no existe, añadir al carrito
        if (!$found) {
            $_SESSION['cart'][] = [
                'gift_id' => $gift_id,
                'gift_list_id' => $gift_list_id,
                'name' => $name,
                'price' => $price,
                'quantity' => $quantity
            ];
        }
        
        // Redirigir para evitar reenvío del formulario
        header("Location: giftlist.php?link=" . urlencode($_GET["link"]));
        exit;
    }
    
    // Acción: Remover del carrito
    if (isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
        $index = (int)$_POST['item_index'];
        if (isset($_SESSION['cart'][$index])) {
            unset($_SESSION['cart'][$index]);
            // Reindexar el array
            $_SESSION['cart'] = array_values($_SESSION['cart']);
        }
        
        // Redirigir para evitar reenvío del formulario
        header("Location: giftlist.php?link=" . urlencode($_GET["link"]));
        exit;
    }
    
    // Acción: Vaciar carrito - CORREGIDO
    if (isset($_POST['action']) && $_POST['action'] === 'clear_cart') {
        // Vaciar completamente el carrito
        $_SESSION['cart'] = [];
        
        // Redirigir para evitar reenvío del formulario
        header("Location: giftlist.php?link=" . urlencode($_GET["link"]));
        exit;
    }
    
    // Acción: Procesar pago
    if (isset($_POST['action']) && $_POST['action'] === 'checkout') {
        // Guardar mensaje de checkout si existe
        $_SESSION['checkout_message'] = $_POST['message'] ?? '';
        
        // Redirigir a la página de checkout
        header("Location: checkout.php?from_list=" . urlencode($_GET["link"]));
        exit;
    }
}

// Capturar el parámetro link de la URL
$unique_link = $_GET["link"] ?? "";

// Validar que se proporcionó un link
if (empty($unique_link)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se proporcionó un enlace de lista.</div></div>";
    exit;
}

// Consultar la lista directamente con PDO
try {
    // Intentar obtener la lista
    $stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE unique_link = ?");
    $stmt->execute([$unique_link]);
    $giftList = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si se encontró la lista
    if (!$giftList) {
        echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
        exit;
    }
    
    // Obtener los regalos asociados a la lista
    $stmtGifts = $pdo->prepare("SELECT * FROM gifts WHERE gift_list_id = ?");
    $stmtGifts->execute([$giftList['id']]);
    $giftList['gifts'] = $stmtGifts->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener información del creador de la lista
    $stmt = $pdo->prepare("SELECT name, lastname FROM users WHERE id = ?");
    $stmt->execute([$giftList['user_id']]);
    $creator = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$creator) {
        $creator = ['name' => 'Usuario', 'lastname' => ''];
    }
} catch (Exception $e) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Error al consultar la base de datos: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    exit;
}

// Calcular totales del carrito
$cartTotal = 0;
$cartItems = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartTotal += $item['price'] * $item['quantity'];
    $cartItems += $item['quantity'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($giftList["title"]); ?> - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            font-size: 0.7rem;
        }
        
        .gift-card {
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .gift-card:hover {
            transform: translateY(-5px);
        }
        
        .wishlist-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .creator-info {
            font-style: italic;
            color: #6c757d;
        }
        
        /* Fix para asegurar que los controles de cantidad se muestren correctamente */
        .input-group .btn {
            z-index: 0;
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
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <button type="button" class="btn btn-outline-light position-relative" data-bs-toggle="offcanvas" data-bs-target="#cartOffcanvas">
                            <i class="bi bi-cart"></i>
                            <?php if ($cartItems > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-badge">
                                <?php echo $cartItems; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <!-- Encabezado de la lista de regalos -->
        <div class="row wishlist-header">
            <div class="col-md-8">
                <h1 class="mb-2"><?php echo htmlspecialchars($giftList["title"]); ?></h1>
                <p class="creator-info">
                    Creada por: <?php echo htmlspecialchars($creator['name'] . ' ' . $creator['lastname']); ?>
                </p>
                <p class="lead"><?php echo htmlspecialchars($giftList["description"]); ?></p>
            </div>
            <div class="col-md-4 text-end">
                <!-- Botón para compartir la lista en redes sociales -->
                <div class="d-flex justify-content-end">
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-share"></i> Compartir
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="https://wa.me/?text=<?php echo urlencode('¡Mira esta lista de regalos! ' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank">
                                    <i class="bi bi-whatsapp me-2"></i> WhatsApp
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank">
                                    <i class="bi bi-facebook me-2"></i> Facebook
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="mailto:?subject=Lista de regalos: <?php echo urlencode($giftList["title"]); ?>&body=<?php echo urlencode('¡Mira esta lista de regalos! ' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>">
                                    <i class="bi bi-envelope me-2"></i> Email
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button class="dropdown-item copy-link" data-link="<?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
                                    <i class="bi bi-clipboard me-2"></i> Copiar enlace
                                </button>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($giftList["gifts"]) && !empty($giftList["gifts"])): ?>
            <div class="row">
                <?php foreach ($giftList["gifts"] as $gift): ?>
                    <?php 
                    $availableStock = $gift["stock"] - $gift["sold"];
                    $isAvailable = $availableStock > 0;
                    ?>
                    <div class="col-md-4 mb-4">
                        <div class="card gift-card shadow-sm <?php echo !$isAvailable ? 'opacity-75' : ''; ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($gift["name"]); ?></h5>
                                <?php if (!empty($gift["description"])): ?>
                                    <p class="card-text text-muted"><?php echo htmlspecialchars($gift["description"]); ?></p>
                                <?php endif; ?>
                                <p class="card-text fw-bold">
                                    Precio: <?php echo (function_exists('format_money')) ? format_money($gift["price"], 'CLP') : '$'.number_format($gift["price"], 0, ',', '.'); ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <?php if ($isAvailable): ?>
                                        <span class="badge bg-success"><?php echo $availableStock; ?> disponibles</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Agotado</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white">
                                <form method="post" action="" class="mt-2">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="gift_id" value="<?php echo $gift["id"]; ?>">
                                    <input type="hidden" name="gift_list_id" value="<?php echo $giftList["id"]; ?>">
                                    <input type="hidden" name="gift_name" value="<?php echo htmlspecialchars($gift["name"]); ?>">
                                    <input type="hidden" name="gift_price" value="<?php echo $gift["price"]; ?>">
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="input-group me-2" style="max-width: 130px;">
                                            <button type="button" class="btn btn-outline-secondary" style="width: 38px;" onclick="decrementQuantity(this)">-</button>
                                            <input type="number" name="quantity" class="form-control text-center" style="width: 45px;" min="1" max="<?php echo $availableStock; ?>" value="1" readonly>
                                            <button type="button" class="btn btn-outline-secondary" style="width: 38px;" onclick="incrementQuantity(this, <?php echo $availableStock; ?>)">+</button>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary" <?php echo $isAvailable ? '' : 'disabled'; ?>>
                                            <i class="bi bi-cart-plus"></i> Añadir
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">Esta lista no tiene regalos disponibles.</div>
        <?php endif; ?>
    </div>
    
    <!-- Offcanvas para el carrito -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="cartOffcanvas" aria-labelledby="cartOffcanvasLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="cartOffcanvasLabel">
                <i class="bi bi-cart"></i> Tu Carrito
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php if (empty($_SESSION['cart'])): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Tu carrito está vacío
                </div>
            <?php else: ?>
                <div class="list-group mb-3">
                    <?php foreach ($_SESSION['cart'] as $index => $item): ?>
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0"><?php echo htmlspecialchars($item['name']); ?></h6>
                                <form method="post" action="" class="d-inline">
                                    <input type="hidden" name="action" value="remove_from_cart">
                                    <input type="hidden" name="item_index" value="<?php echo $index; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <small class="text-muted">
                                    <?php echo (function_exists('format_money')) ? format_money($item['price'], 'CLP') : '$'.number_format($item['price'], 0, ',', '.'); ?> x <?php echo $item['quantity']; ?>
                                </small>
                                <span class="fw-bold">
                                    <?php echo (function_exists('format_money')) ? format_money($item['price'] * $item['quantity'], 'CLP') : '$'.number_format($item['price'] * $item['quantity'], 0, ',', '.'); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Total:</h5>
                            <h5 class="mb-0"><?php echo (function_exists('format_money')) ? format_money($cartTotal, 'CLP') : '$'.number_format($cartTotal, 0, ',', '.'); ?></h5>
                        </div>
                    </div>
                </div>
                
                <!-- Formulario para proceder al pago -->
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="message" class="form-label">Mensaje para <?php echo htmlspecialchars($creator['name']); ?>:</label>
                        <textarea class="form-control" id="message" name="message" rows="3" placeholder="Escribe un mensaje especial para acompañar tu regalo..."></textarea>
                    </div>
                    
                    <div class="d-grid">
                        <input type="hidden" name="action" value="checkout">
                        <input type="hidden" name="currency" value="clp">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-credit-card"></i> Proceder al pago
                        </button>
                    </div>
                </form>
                
                <!-- Formulario separado para vaciar el carrito -->
                <form method="post" action="">
                    <input type="hidden" name="action" value="clear_cart">
                    <div class="d-grid mt-2">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash"></i> Vaciar carrito
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Toast para copiar al portapapeles -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="linkToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <i class="bi bi-clipboard-check me-2"></i>
                <strong class="me-auto">Enlace copiado</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                El enlace ha sido copiado al portapapeles.
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
        // Funciones para incrementar y decrementar la cantidad
        function incrementQuantity(button, max) {
            const input = button.parentNode.querySelector('input');
            let value = parseInt(input.value, 10);
            if (value < max) {
                input.value = value + 1;
            }
        }
        
        function decrementQuantity(button) {
            const input = button.parentNode.querySelector('input');
            let value = parseInt(input.value, 10);
            if (value > 1) {
                input.value = value - 1;
            }
        }
        
        // Función para formatear montos en pesos chilenos (sin decimales)
        function formatMoneyCLP(amount) {
            return '$' + new Intl.NumberFormat('es-CL', {
                maximumFractionDigits: 0,
                minimumFractionDigits: 0
            }).format(Math.round(amount));
        }
        
        // Copiar enlace al portapapeles
        document.addEventListener('DOMContentLoaded', function() {
            const copyButtons = document.querySelectorAll('.copy-link');
            const toast = new bootstrap.Toast(document.getElementById('linkToast'));
            
            copyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const link = this.getAttribute('data-link');
                    navigator.clipboard.writeText(link).then(() => {
                        toast.show();
                    });
                });
            });
            
            // Mostrar automáticamente el carrito si contiene elementos
            <?php if (count($_SESSION['cart']) > 0 && !isset($_POST['action'])): ?>
            const cartOffcanvas = new bootstrap.Offcanvas(document.getElementById('cartOffcanvas'));
            setTimeout(() => {
                cartOffcanvas.show();
            }, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>