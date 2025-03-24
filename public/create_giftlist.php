<?php
// public/create_giftlist.php

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Auth.php';

// Verificar autenticación
$auth = new Auth($pdo);
$auth->require('login.php');

$user = $auth->user();

// Consultar los presets (temarios) creados por el administrador
try {
    $stmt = $pdo->query("SELECT id, theme FROM preset_product_lists ORDER BY theme ASC");
    $presetLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error al obtener los presets: " . $e->getMessage();
    $presetLists = [];
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verificar token CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = "Error de verificación. Por favor, recargue la página.";
    } else {
        $glc = new GiftListController($pdo);
        
        // Recopilar datos generales de la lista
        $data = [
            'title'         => trim($_POST['title'] ?? ''),
            'description'   => trim($_POST['description'] ?? ''),
            'event_type'    => trim($_POST['event_type'] ?? ''),
            'beneficiary1'  => trim($_POST['beneficiary1'] ?? ''),
            'beneficiary2'  => trim($_POST['beneficiary2'] ?? '')
        ];
        
        // Validaciones básicas
        if (empty($data['title'])) {
            $error = "El título de la lista es obligatorio.";
        } elseif (empty($data['description'])) {
            $error = "La descripción de la lista es obligatoria.";
        } else {
            $list_type = $_POST['list_type'] ?? 'predeterminada';
            
// Si es lista predeterminada, se debe enviar el ID del preset
            if ($list_type === 'predeterminada') {
                $data['preset_theme'] = trim($_POST['preset_theme'] ?? '');
                if (empty($data['preset_theme'])) {
                    $error = "Debe seleccionar un temario para la lista predeterminada.";
                }
            } else {
                $data['preset_theme'] = null;
            }
            
            if (empty($error)) {
                // Crear la lista usando el controlador y obtener el ID numérico
                try {
                    $gift_list_id = $glc->create($data, $user["id"]);
                    
                    if (!$gift_list_id) {
                        $error = "No se pudo crear la lista. Verifica que todos los campos sean correctos.";
                    } else {
                        // Procesar productos según el tipo de lista
                        if ($list_type === 'predeterminada') {
                            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                                foreach ($_POST['product_id'] as $index => $presetProductId) {
                                    if (!empty($presetProductId)) {
                                        $price = floatval($_POST['price'][$index] ?? 0);
                                        $quantity = intval($_POST['quantity'][$index] ?? 0);
                                        // Consultar el nombre real del producto desde la tabla preset_products
                                        $stmtProd = $pdo->prepare("SELECT name FROM preset_products WHERE id = ?");
                                        $stmtProd->execute([$presetProductId]);
                                        $presetProd = $stmtProd->fetch(PDO::FETCH_ASSOC);
                                        $name = $presetProd ? $presetProd['name'] : "";
                                        // Insertar el producto en la lista (como copia para el usuario)
                                        $glc->addGift($gift_list_id, [
                                            "name"        => $name,
                                            "description" => "",
                                            "price"       => $price,
                                            "stock"       => $quantity
                                        ]);
                                    }
                                }
                            }
                        } else { // Lista personalizada
                            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                                foreach ($_POST['product_name'] as $index => $prod_name) {
                                    if (!empty(trim($prod_name))) {
                                        $price = floatval($_POST['price_custom'][$index] ?? 0);
                                        $quantity = intval($_POST['quantity_custom'][$index] ?? 0);
                                        $glc->addGift($gift_list_id, [
                                            "name"        => trim($prod_name),
                                            "description" => "",
                                            "price"       => $price,
                                            "stock"       => $quantity
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        // Establecer mensaje flash y redirigir
                        set_flash_message('success', 'Lista de regalos creada exitosamente');
                        header("Location: dashboard.php");
                        exit;
                    }
                } catch (Exception $e) {
                    $error = "Error al crear la lista: " . $e->getMessage();
                }
            }
        }
    }
}

// Cargar configuración global
$config = require_once __DIR__ . '/../config/config.php';

// Establecer título de la página
$title = "Crear Lista de Regalos";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .product-group, .product-custom-group {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .d-none { display: none; }
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
                        <a class="nav-link" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="create_giftlist.php">Crear Lista</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><?php echo $title; ?></h1>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="post" action="" class="needs-validation" novalidate>
            <!-- Token CSRF -->
            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
            
            <!-- Datos generales -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Datos Generales</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Título de la Lista:</label>
                        <input type="text" id="title" name="title" class="form-control" required>
                        <div class="invalid-feedback">El título es obligatorio</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción de la Lista:</label>
                        <textarea id="description" name="description" class="form-control" rows="3" required></textarea>
                        <div class="invalid-feedback">La descripción es obligatoria</div>
                    </div>
                    
                    <!-- Tipo de Evento y Beneficiarios -->
                    <div class="mb-3">
                        <label for="event_type" class="form-label">Tipo de Evento:</label>
                        <select id="event_type" name="event_type" class="form-select">
                            <option value="">Seleccione un tipo de evento</option>
                            <option value="Cumpleaños">Cumpleaños</option>
                            <option value="Bautizo">Bautizo</option>
                            <option value="Babyshower">Babyshower</option>
                            <option value="Matrimonio">Matrimonio</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="beneficiarySingle">
                        <label for="beneficiary1" class="form-label">Nombre del Beneficiario:</label>
                        <input type="text" id="beneficiary1" name="beneficiary1" class="form-control">
                    </div>
                    
                    <div class="mb-3 d-none" id="beneficiaryDouble">
                        <label for="beneficiary1_double" class="form-label">Nombre del Beneficiario 1:</label>
                        <input type="text" id="beneficiary1_double" name="beneficiary1" class="form-control mb-2">
                        <label for="beneficiary2" class="form-label">Nombre del Beneficiario 2:</label>
                        <input type="text" id="beneficiary2" name="beneficiary2" class="form-control">
                    </div>
                </div>
            </div>
            
            <!-- Tipo de Lista -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Tipo de Lista</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <select name="list_type" id="list_type" class="form-select" required>
                            <option value="predeterminada">Lista Predeterminada</option>
                            <option value="personalizada">Lista Personalizada</option>
                        </select>
                    </div>
                    
                    <!-- Sección para Lista Predeterminada -->
                    <div id="predeterminada_section">
                        <div class="mb-3">
                            <label for="preset_theme" class="form-label">Temario de la Lista:</label>
                            <select name="preset_theme" id="preset_theme" class="form-select">
                                <option value="">Seleccione un temario</option>
                                <?php foreach ($presetLists as $preset): ?>
                                    <option value="<?php echo $preset['id']; ?>"><?php echo htmlspecialchars($preset['theme']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <!-- Contenedor para productos predeterminados, se cargan desde get_preset_products.php vía AJAX -->
                        <div id="preset_products_container" class="mt-4"></div>
                    </div>
                    
                    <!-- Sección para Lista Personalizada -->
                    <div id="personalizada_section" class="d-none">
                        <h5 class="mb-3">Productos Personalizados</h5>
                        <div id="custom_products_container">
                            <div class="product-custom-group">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Nombre del Producto:</label>
                                        <input type="text" name="product_name[]" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Precio:</label>
                                        <input type="number" name="price_custom[]" class="form-control price-input" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Cantidad:</label>
                                        <input type="number" name="quantity_custom[]" class="form-control quantity-input" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Total:</label>
                                        <div class="form-control bg-light total-field-custom">0.00</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger remove-custom-product mt-2">Eliminar</button>
                            </div>
                        </div>
                        <button type="button" id="add_custom_product" class="btn btn-outline-secondary mb-3">
                            <i class="bi bi-plus"></i> Agregar otro producto
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Total General -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Resumen</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grand_total" class="form-label">Total General:</label>
                                <div id="grand_total" class="form-control bg-light">0.00</div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex justify-content-end align-items-end">
                            <button type="submit" class="btn btn-primary btn-lg">Crear Lista</button>
                            <a href="dashboard.php" class="btn btn-secondary btn-lg ms-2">Cancelar</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mostrar u ocultar secciones según el tipo de lista
        document.getElementById('list_type').addEventListener('change', function() {
            var listType = this.value;
            if (listType === 'predeterminada') {
                document.getElementById('predeterminada_section').classList.remove('d-none');
                document.getElementById('personalizada_section').classList.add('d-none');
            } else {
                document.getElementById('predeterminada_section').classList.add('d-none');
                document.getElementById('personalizada_section').classList.remove('d-none');
            }
            calculateGrandTotal();
        });
        
        // Mostrar u ocultar beneficiarios según el tipo de evento (Matrimonio)
        document.querySelector('select[name="event_type"]').addEventListener('change', function() {
            var eventType = this.value;
            var beneficiarySingle = document.getElementById('beneficiarySingle');
            var beneficiaryDouble = document.getElementById('beneficiaryDouble');
            if (eventType === "Matrimonio") {
                beneficiarySingle.classList.add('d-none');
                beneficiaryDouble.classList.remove('d-none');
            } else {
                beneficiarySingle.classList.remove('d-none');
                beneficiaryDouble.classList.add('d-none');
            }
        });
        
        // Funciones para productos personalizados
        function calculateTotalsCustom() {
            document.querySelectorAll('.product-custom-group').forEach(function(group, index) {
                var price = parseFloat(group.querySelector('input[name="price_custom[]"]').value) || 0;
                var quantity = parseFloat(group.querySelector('input[name="quantity_custom[]"]').value) || 0;
                var total = price * quantity;
                group.querySelector('.total-field-custom').textContent = total.toFixed(2);
            });
        }
        
        function calculateGrandTotalCustom() {
            var grandTotal = 0;
            document.querySelectorAll('.product-custom-group').forEach(function(group) {
                var total = parseFloat(group.querySelector('.total-field-custom').textContent) || 0;
                grandTotal += total;
            });
            document.getElementById('grand_total').textContent = grandTotal.toFixed(2);
        }
        
        // Funciones para productos predeterminados
        function calculateTotalsPreset() {
            document.querySelectorAll('.product-group').forEach(function(group) {
                var price = parseFloat(group.querySelector('input[name="price[]"]').value) || 0;
                var quantity = parseFloat(group.querySelector('input[name="quantity[]"]').value) || 0;
                var total = price * quantity;
                group.querySelector('.total-field').textContent = total.toFixed(2);
            });
        }
        
        function calculateGrandTotalPreset() {
            var grandTotal = 0;
            document.querySelectorAll('.product-group').forEach(function(group) {
                var total = parseFloat(group.querySelector('.total-field').textContent) || 0;
                grandTotal += total;
            });
            document.getElementById('grand_total').textContent = grandTotal.toFixed(2);
        }
        
        function calculateGrandTotal() {
            var listType = document.getElementById('list_type').value;
            if (listType === 'predeterminada') {
                calculateTotalsPreset();
                calculateGrandTotalPreset();
            } else {
                calculateTotalsCustom();
                calculateGrandTotalCustom();
            }
        }
        
        // Actualizar totales al modificar precio o cantidad
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name="price_custom[]"], input[name="quantity_custom[]"]')) {
                calculateTotalsCustom();
                calculateGrandTotalCustom();
            }
            if (e.target.matches('input[name="price[]"], input[name="quantity[]"]')) {
                calculateTotalsPreset();
                calculateGrandTotalPreset();
            }
        });
        
        // Agregar nuevo producto personalizado
        document.getElementById('add_custom_product').addEventListener('click', function() {
            var container = document.getElementById('custom_products_container');
            var group = container.querySelector('.product-custom-group');
            var clone = group.cloneNode(true);
            clone.querySelectorAll('input').forEach(function(input) {
                input.value = "";
            });
            clone.querySelector('.total-field-custom').textContent = "0.00";
            container.appendChild(clone);
        });
        
        // Cargar productos predeterminados mediante AJAX al seleccionar un preset
        document.getElementById('preset_theme').addEventListener('change', function() {
            loadPresetProducts(this.value);
        });
        
        function loadPresetProducts(presetId) {
            var container = document.getElementById('preset_products_container');
            container.innerHTML = "";
            if (!presetId) return;
            
            // Mostrar indicador de carga
            container.innerHTML = '<div class="alert alert-info">Cargando productos...</div>';
            
            // Cambiar la ruta según la ubicación real de get_preset_products.php
            fetch('admin/get_preset_products.php?preset_id=' + encodeURIComponent(presetId))
                .then(response => response.json())
                .then(data => {
                    container.innerHTML = ""; // Limpiar contenedor
                    
                    if (data.success && data.products.length > 0) {
                        data.products.forEach(function(prod, index) {
                            var div = document.createElement('div');
                            div.className = "product-group border p-3 mb-3 rounded";
                            div.innerHTML = `
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Producto:</label>
                                        <input type="hidden" name="product_id[]" value="${prod.id}">
                                        <input type="text" class="form-control" value="${prod.name}" readonly>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Precio:</label>
                                        <input type="number" name="price[]" class="form-control" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Cantidad:</label>
                                        <input type="number" name="quantity[]" class="form-control" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Total:</label>
                                        <div class="form-control bg-light total-field">0.00</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-danger mt-2 remove-product">Eliminar</button>
                            `;
                            container.appendChild(div);
                        });
                    } else {
                        container.innerHTML = "<div class='alert alert-warning'>No se encontraron productos para este temario.</div>";
                    }
                    calculateGrandTotalPreset();
                })
                .catch(error => {
                    console.error("Error al cargar productos:", error);
                    container.innerHTML = "<div class='alert alert-danger'>Error al cargar los productos predeterminados.</div>";
                });
        }
        
        // Eliminar producto personalizado
        document.getElementById('custom_products_container').addEventListener('click', function(e) {
            if (e.target && e.target.matches('.remove-custom-product')) {
                var groups = document.querySelectorAll('.product-custom-group');
                if (groups.length > 1) {
                    e.target.closest('.product-custom-group').remove();
                    calculateGrandTotalCustom();
                } else {
                    alert("Debe haber al menos un producto.");
                }
            }
        });
        
        // Eliminar producto predeterminado
        document.getElementById('preset_products_container').addEventListener('click', function(e) {
            if (e.target && e.target.matches('.remove-product')) {
                var groups = document.querySelectorAll('.product-group');
                if (groups.length > 1) {
                    e.target.closest('.product-group').remove();
                    calculateGrandTotalPreset();
                } else {
                    alert("Debe haber al menos un producto.");
                }
            }
        });
        
        // Form validation using Bootstrap
        (function () {
            'use strict'
            
            var forms = document.querySelectorAll('.needs-validation')
            
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html>