<?php
// public/create_giftlist.php - Versión actualizada

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Instanciar controlador para obtener las categorías de regalos
$glc = new GiftListController($pdo);
$categories = $glc->getAllCategories();

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
    // Para depuración
    error_log("POST data: " . print_r($_POST, true));
    
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
            'beneficiary2'  => trim($_POST['beneficiary2'] ?? ''),
            'preset_theme'  => !empty($_POST['preset_theme']) ? intval($_POST['preset_theme']) : null,
            // Nuevos campos
            'expiry_date'   => trim($_POST['expiry_date'] ?? ''),
            'visibility'    => trim($_POST['visibility'] ?? 'link_only')
        ];
        
        // Validaciones básicas
        if (empty($data['title'])) {
            $error = "El título de la lista es obligatorio.";
        } elseif (empty($data['description'])) {
            $error = "La descripción de la lista es obligatoria.";
        } elseif (!in_array($data['visibility'], ['public', 'private', 'link_only'])) {
            $error = "La visibilidad seleccionada no es válida.";
        } else {
            $list_type = $_POST['list_type'] ?? 'predeterminada';
            
            if (empty($error)) {
                // Crear la lista usando el controlador y obtener el ID numérico
                try {
                    // Intentamos llamar al método create y capturamos cualquier excepción
                    $gift_list_id = $glc->create($data, $user["id"]);
                    
                    if (!$gift_list_id) {
                        $error = "No se pudo crear la lista. Verifica que todos los campos sean correctos.";
                        // Mostrar más detalles para depurar
                        error_log("Error creando lista: " . print_r($data, true));
                    } else {
                        $productsAdded = 0; // Para contar los productos añadidos
                        
                        // Procesar productos según el tipo de lista
                        if ($list_type === 'predeterminada') {
                            if (isset($_POST['product_id']) && is_array($_POST['product_id'])) {
                                foreach ($_POST['product_id'] as $index => $presetProductId) {
                                    if (!empty($presetProductId)) {
                                        $price = floatval($_POST['price'][$index] ?? 0);
                                        $quantity = intval($_POST['quantity'][$index] ?? 0);
                                        $category_id = intval($_POST['category_id'][$index] ?? null);
                                        
                                        // Si es un producto personalizado en lista predeterminada
                                        if ($presetProductId === 'custom') {
                                            // Asegúrate de que product_name_custom existe para este índice
                                            if (isset($_POST['product_name_custom']) && 
                                                is_array($_POST['product_name_custom']) && 
                                                isset($_POST['product_name_custom'][$index])) {
                                                
                                                $name = trim($_POST['product_name_custom'][$index]);
                                                error_log("Procesando producto personalizado en lista predeterminada: $name");
                                            } else {
                                                // Si no está en product_name_custom, busca en otro lugar
                                                // En caso de que la estructura de datos sea diferente
                                                $customIndex = $index - count($_POST['product_id']) + count($_POST['product_name_custom']);
                                                if (isset($_POST['product_name_custom']) && 
                                                    is_array($_POST['product_name_custom']) && 
                                                    isset($_POST['product_name_custom'][$customIndex])) {
                                                    
                                                    $name = trim($_POST['product_name_custom'][$customIndex]);
                                                    error_log("Procesando producto personalizado (índice ajustado): $name");
                                                } else {
                                                    // Si todo falla, usa un nombre predeterminado
                                                    $name = "Producto personalizado";
                                                    error_log("No se pudo encontrar el nombre del producto personalizado para el índice $index");
                                                }
                                            }
                                            $description = '';
                                        } else {
                                            // Consultar el nombre real del producto desde la tabla preset_products
                                            $stmtProd = $pdo->prepare("SELECT name FROM preset_products WHERE id = ?");
                                            $stmtProd->execute([$presetProductId]);
                                            $presetProd = $stmtProd->fetch(PDO::FETCH_ASSOC);
                                            $name = $presetProd ? $presetProd['name'] : "";
                                            $description = '';
                                            error_log("Procesando producto predeterminado: $name");
                                        }
                                        
                                        // Insertar el producto en la lista con categoría
                                        $result = $glc->addGift($gift_list_id, [
                                            "name"        => $name,
                                            "description" => $description,
                                            "price"       => $price,
                                            "stock"       => $quantity,
                                            "category_id" => $category_id
                                        ]);
                                        
                                        if ($result) {
                                            $productsAdded++;
                                            error_log("Producto añadido: $name, precio: $price, cantidad: $quantity, categoría: $category_id");
                                        } else {
                                            error_log("Error al añadir regalo: $name, precio: $price, cantidad: $quantity, categoría: $category_id");
                                        }
                                    }
                                }
                            }
                        } else { // Lista personalizada
                            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                                foreach ($_POST['product_name'] as $index => $prod_name) {
                                    if (!empty(trim($prod_name))) {
                                        $price = floatval($_POST['price_custom'][$index] ?? 0);
                                        $quantity = intval($_POST['quantity_custom'][$index] ?? 0);
                                        $category_id = intval($_POST['category_id_custom'][$index] ?? null);
                                        
                                        $result = $glc->addGift($gift_list_id, [
                                            "name"        => trim($prod_name),
                                            "description" => "",
                                            "price"       => $price,
                                            "stock"       => $quantity,
                                            "category_id" => $category_id
                                        ]);
                                        
                                        if ($result) {
                                            $productsAdded++;
                                            error_log("Producto personalizado añadido: $prod_name, precio: $price, cantidad: $quantity, categoría: $category_id");
                                        } else {
                                            error_log("Error al añadir regalo personalizado: $prod_name, precio: $price, cantidad: $quantity, categoría: $category_id");
                                        }
                                    }
                                }
                            }
                        }
                        
                        error_log("Total de productos añadidos: $productsAdded");
                        
                        // Establecer mensaje flash y redirigir
                        set_flash_message('success', 'Lista de regalos creada exitosamente con ' . $productsAdded . ' productos.');
                        header("Location: dashboard.php");
                        exit;
                    }
                } catch (Exception $e) {
                    $error = "Error al crear la lista: " . $e->getMessage();
                    // Registra el error en el log
                    error_log("Excepción creando lista: " . $e->getMessage());
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        .visibility-info {
            font-size: 0.9rem;
            display: none;
            margin-top: 5px;
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
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Tipo de Evento:</label>
                                <select id="event_type" name="event_type" class="form-select">
                                    <option value="">Seleccione un tipo de evento</option>
                                    <option value="Cumpleaños">Cumpleaños</option>
                                    <option value="Matrimonio">Matrimonio</option>
                                    <option value="Baby Shower">Baby Shower</option>
                                    <option value="Aniversario">Aniversario</option>
                                    <option value="Graduación">Graduación</option>
                                    <option value="Otro">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <!-- Sección para beneficiarios con lógica para matrimonio -->
                            <div id="beneficiarySingle" class="mb-3">
                                <label for="beneficiary1" class="form-label">Beneficiario:</label>
                                <input type="text" id="beneficiary1" name="beneficiary1" class="form-control">
                            </div>
                            
                            <div id="beneficiaryDouble" class="mb-3 d-none">
                                <label class="form-label">Beneficiarios (novios):</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" name="beneficiary1" class="form-control mb-2" placeholder="Primer beneficiario">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="beneficiary2" class="form-control" placeholder="Segundo beneficiario">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nuevos campos para fecha límite y visibilidad -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">Fecha Límite (opcional):</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control">
                                <small class="form-text text-muted">Deja en blanco si no deseas fecha límite.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="visibility" class="form-label">Visibilidad de la Lista:</label>
                                <select id="visibility" name="visibility" class="form-select" required>
                                    <option value="link_only" selected>Solo con enlace</option>
                                    <option value="public">Pública</option>
                                    <option value="private">Privada</option>
                                </select>
                                
                                <!-- Información de visibilidad -->
                                <div id="link_only_info" class="visibility-info text-info">
                                    <i class="bi bi-info-circle"></i> Solo personas con el enlace podrán ver esta lista.
                                </div>
                                <div id="public_info" class="visibility-info text-success">
                                    <i class="bi bi-globe"></i> Esta lista será visible públicamente y aparecerá en búsquedas.
                                </div>
                                <div id="private_info" class="visibility-info text-warning">
                                    <i class="bi bi-lock"></i> Solo tú podrás ver esta lista. Tendrás que compartir el enlace manualmente.
                                </div>
                            </div>
                        </div>
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
                        
                        <!-- Botón para agregar productos adicionales a la lista predeterminada -->
                        <div class="mt-3">
                            <button type="button" id="add_preset_product" class="btn btn-outline-secondary mb-3">
                                <i class="bi bi-plus"></i> Agregar producto adicional
                            </button>
                        </div>
                    </div>
                    
                    <!-- Sección para Lista Personalizada -->
                    <div id="personalizada_section" class="d-none">
                        <h5 class="mb-3">Productos Personalizados</h5>
                        <div id="custom_products_container">
                            <div class="product-custom-group">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Nombre del Producto:</label>
                                        <input type="text" name="product_name[]" class="form-control" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Precio:</label>
                                        <input type="number" name="price_custom[]" class="form-control price-input" step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Cantidad:</label>
                                        <input type="number" name="quantity_custom[]" class="form-control quantity-input" min="0" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Categoría:</label>
                                        <select name="category_id_custom[]" class="form-select">
                                            <option value="">Sin categoría</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
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
    
    <!-- Script directo integrado -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Referencias a elementos DOM
        const listTypeSelect = document.getElementById('list_type');
        const presetSection = document.getElementById('predeterminada_section');
        const customSection = document.getElementById('personalizada_section');
        const presetThemeSelect = document.getElementById('preset_theme');
        const presetProductsContainer = document.getElementById('preset_products_container');
        const customProductsContainer = document.getElementById('custom_products_container');
        const addCustomProductBtn = document.getElementById('add_custom_product');
        const addPresetProductBtn = document.getElementById('add_preset_product');
        const grandTotalElement = document.getElementById('grand_total');
        const visibilitySelect = document.getElementById('visibility');
        const eventTypeSelect = document.getElementById('event_type');
        const beneficiarySingle = document.getElementById('beneficiarySingle');
        const beneficiaryDouble = document.getElementById('beneficiaryDouble');
        
        // Mostrar información de visibilidad
        function updateVisibilityInfo() {
            // Ocultar todos los mensajes de info
            document.querySelectorAll('.visibility-info').forEach(el => {
                el.style.display = 'none';
            });
            
            // Mostrar solo el mensaje correspondiente a la selección actual
            const selectedVisibility = visibilitySelect.value;
            document.getElementById(selectedVisibility + '_info').style.display = 'block';
        }
        
        // Agregar event listener para el cambio de visibilidad
        visibilitySelect.addEventListener('change', updateVisibilityInfo);
        
        // Inicialización
        initApp();
        
        /**
         * Inicializa la aplicación y configura los event listeners
         */
        function initApp() {
            console.log("Inicializando aplicación de listas de regalos...");
            
            // Event listeners para cambios de tipo de lista
            listTypeSelect.addEventListener('change', handleListTypeChange);
            
            // Event listener para cambios de tipo de evento (para beneficiarios)
            if (eventTypeSelect) {
                eventTypeSelect.addEventListener('change', function() {
                    handleEventTypeChange();
                });
            }
            
            // Función para manejar el cambio de tipo de evento
            function handleEventTypeChange() {
                const eventType = eventTypeSelect.value;
                
                if (eventType === "Matrimonio") {
                    beneficiarySingle.classList.add('d-none');
                    beneficiaryDouble.classList.remove('d-none');
                } else {
                    beneficiarySingle.classList.remove('d-none');
                    beneficiaryDouble.classList.add('d-none');
                }
            }
            
            // Inicializar el estado de los beneficiarios
            handleEventTypeChange();
            
            // Event listener para selección de tema predeterminado
            if (presetThemeSelect) {
                presetThemeSelect.addEventListener('change', function() {
                    console.log("Cambiando preset a: " + this.value);
                    loadPresetProducts(this.value);
                });
            }
            
            // Event listener para agregar producto personalizado
            if (addCustomProductBtn) {
                addCustomProductBtn.addEventListener('click', addCustomProduct);
            }
            
            // Event listener para agregar producto a lista predeterminada
            if (addPresetProductBtn) {
                addPresetProductBtn.addEventListener('click', addPresetProduct);
            }
            
            // Event listeners para eliminar productos
            if (customProductsContainer) {
                customProductsContainer.addEventListener('click', function(e) {
                    if (e.target && e.target.matches('.remove-custom-product')) {
                        removeCustomProduct(e);
                    }
                });
            }
            
            if (presetProductsContainer) {
                presetProductsContainer.addEventListener('click', function(e) {
                    if (e.target && e.target.matches('.remove-product')) {
                        removePresetProduct(e);
                    }
                });
            }
            
            // Event listener global para cambios en inputs de precio o cantidad
            document.addEventListener('input', function(e) {
                if (e.target.matches('input[name="price_custom[]"], input[name="quantity_custom[]"]')) {
                    calculateTotalsCustom();
                    calculateGrandTotal();
                }
                if (e.target.matches('input[name="price[]"], input[name="quantity[]"]')) {
                    calculateTotalsPreset();
                    calculateGrandTotal();
                }
            });
            
            // Inicializar el estado actual
            handleListTypeChange();
            
            // Mostrar la información de visibilidad predeterminada
            updateVisibilityInfo();
        }
        
        /**
         * Maneja el cambio entre tipos de lista (predeterminada/personalizada)
         */
        function handleListTypeChange() {
            const listType = listTypeSelect.value;
            console.log("Cambiando tipo de lista a: " + listType);
            
            if (listType === 'predeterminada') {
                presetSection.classList.remove('d-none');
                customSection.classList.add('d-none');
            } else {
                presetSection.classList.add('d-none');
                customSection.classList.remove('d-none');
            }
            
            calculateGrandTotal();
        }
        
        /**
         * Carga productos predeterminados para un tema específico
         * @param {number} presetId - ID del preset seleccionado
         */
        function loadPresetProducts(presetId) {
            if (!presetId) {
                presetProductsContainer.innerHTML = "";
                return;
            }
            
            // Mostrar indicador de carga
            presetProductsContainer.innerHTML = '<div class="alert alert-info">Cargando productos...</div>';
            
            // Realizar solicitud AJAX para obtener productos
            fetch('get_preset_products.php?preset_id=' + encodeURIComponent(presetId))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Datos recibidos:", data);
                    presetProductsContainer.innerHTML = ""; // Limpiar contenedor
                    
                    if (data.success && data.products && data.products.length > 0) {
                        data.products.forEach(function(prod) {
                            const productElement = createPresetProductElement(prod);
                            presetProductsContainer.appendChild(productElement);
                        });
                        calculateTotalsPreset();
                        calculateGrandTotal();
                    } else {
                        presetProductsContainer.innerHTML = "<div class='alert alert-warning'>No se encontraron productos para este temario.</div>";
                    }
                })
                .catch(error => {
                    console.error("Error al cargar productos:", error);
                    presetProductsContainer.innerHTML = 
                        "<div class='alert alert-danger'>Error al cargar los productos predeterminados. " + 
                        "Detalles: " + error.message + "</div>";
                });
        }
        
        /**
         * Crea un elemento DOM para un producto predeterminado
         * @param {Object} product - Datos del producto
         * @returns {HTMLElement} Elemento DOM del producto
         */
        function createPresetProductElement(product) {
            const div = document.createElement('div');
            div.className = "product-group border p-3 mb-3 rounded";
            
            // Obtener las opciones de categoría
            let categoryOptions = '<option value="">Sin categoría</option>';
            <?php foreach ($categories as $category): ?>
                categoryOptions += `<option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>`;
            <?php endforeach; ?>
            
            div.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Producto:</label>
                        <input type="hidden" name="product_id[]" value="${product.id}">
                        <input type="text" class="form-control" value="${product.name}" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio:</label>
                        <input type="number" name="price[]" class="form-control" step="0.01" min="0" value="${product.price || 0}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cantidad:</label>
                        <input type="number" name="quantity[]" class="form-control" min="0" value="${product.stock || 1}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría:</label>
                        <select name="category_id[]" class="form-select">
                            ${categoryOptions}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total:</label>
                        <div class="form-control bg-light total-field">0.00</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger mt-2 remove-product">Eliminar</button>
            `;
            return div;
        }
        
        /**
         * Agrega un producto adicional a la lista predeterminada
         */
        function addPresetProduct() {
            const customProductCount = document.querySelectorAll('.product-group[data-custom="true"]').length;
            const div = document.createElement('div');
            div.className = "product-group border p-3 mb-3 rounded";
            div.setAttribute('data-custom', 'true');
            
            // Obtener las opciones de categoría
            let categoryOptions = '<option value="">Sin categoría</option>';
            <?php foreach ($categories as $category): ?>
                categoryOptions += `<option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>`;
            <?php endforeach; ?>
            
            div.innerHTML = `
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Producto:</label>
                        <input type="hidden" name="product_id[]" value="custom">
                        <input type="text" name="product_name_custom[${customProductCount}]" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio:</label>
                        <input type="number" name="price[]" class="form-control" step="0.01" min="0" value="0" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cantidad:</label>
                        <input type="number" name="quantity[]" class="form-control" min="0" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría:</label>
                        <select name="category_id[]" class="form-select">
                            ${categoryOptions}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Total:</label>
                        <div class="form-control bg-light total-field">0.00</div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger mt-2 remove-product">Eliminar</button>
            `;
            
            presetProductsContainer.appendChild(div);
            calculateTotalsPreset();
            calculateGrandTotal();
        }
        
        /**
         * Agrega un nuevo producto personalizado
         */
        function addCustomProduct() {
            console.log("Agregando nuevo producto personalizado");
            const firstGroup = customProductsContainer.querySelector('.product-custom-group');
            if (!firstGroup) return;
            
            const newGroup = firstGroup.cloneNode(true);
            
            // Limpiar valores
            newGroup.querySelectorAll('input').forEach(function(input) {
                input.value = "";
            });
            
            // Resetear selección de categoría
            const categorySelect = newGroup.querySelector('select');
            if (categorySelect) {
                categorySelect.selectedIndex = 0;
            }
            
            // Reiniciar total
            newGroup.querySelector('.total-field-custom').textContent = "0.00";
            
            // Añadir al contenedor
            customProductsContainer.appendChild(newGroup);
        }
        
        /**
         * Elimina un producto personalizado
         * @param {Event} e - Evento de clic
         */
        function removeCustomProduct(e) {
            const groups = document.querySelectorAll('.product-custom-group');
            if (groups.length > 1) {
                e.target.closest('.product-custom-group').remove();
                calculateTotalsCustom();
                calculateGrandTotal();
            } else {
                alert("Debe haber al menos un producto.");
            }
        }
        
        /**
         * Elimina un producto predeterminado
         * @param {Event} e - Evento de clic
         */
        function removePresetProduct(e) {
            const groups = document.querySelectorAll('.product-group');
            if (groups.length > 1) {
                e.target.closest('.product-group').remove();
                calculateTotalsPreset();
                calculateGrandTotal();
            } else {
                alert("Debe haber al menos un producto.");
            }
        }
        
        /**
         * Calcula totales para productos personalizados
         */
        function calculateTotalsCustom() {
            document.querySelectorAll('.product-custom-group').forEach(function(group) {
                const priceInput = group.querySelector('input[name="price_custom[]"]');
                const quantityInput = group.querySelector('input[name="quantity_custom[]"]');
                const totalField = group.querySelector('.total-field-custom');
                
                if (priceInput && quantityInput && totalField) {
                    const price = parseFloat(priceInput.value) || 0;
                    const quantity = parseFloat(quantityInput.value) || 0;
                    const total = price * quantity;
                    totalField.textContent = total.toFixed(2);
                }
            });
        }
        
        /**
         * Calcula totales para productos predeterminados
         */
        function calculateTotalsPreset() {
            document.querySelectorAll('.product-group').forEach(function(group) {
                const priceInput = group.querySelector('input[name="price[]"]');
                const quantityInput = group.querySelector('input[name="quantity[]"]');
                const totalField = group.querySelector('.total-field');
                
                if (priceInput && quantityInput && totalField) {
                    const price = parseFloat(priceInput.value) || 0;
                    const quantity = parseFloat(quantityInput.value) || 0;
                    const total = price * quantity;
                    totalField.textContent = total.toFixed(2);
                }
            });
        }
        
        /**
         * Calcula el total general según el tipo de lista activa
         */
        function calculateGrandTotal() {
            let grandTotal = 0;
            const listType = listTypeSelect.value;
            
            if (listType === 'predeterminada') {
                document.querySelectorAll('.product-group .total-field').forEach(function(field) {
                    grandTotal += parseFloat(field.textContent) || 0;
                });
            } else {
                document.querySelectorAll('.product-custom-group .total-field-custom').forEach(function(field) {
                    grandTotal += parseFloat(field.textContent) || 0;
                });
            }
            
            if (grandTotalElement) {
                grandTotalElement.textContent = grandTotal.toFixed(2);
            }
        }
    });
    </script>
</body>
</html>