<?php
// public/admin/edit_giftlist.php - Versión mejorada

// Inicia la sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/GiftListController.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID de la lista a editar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó la lista a editar.</div></div>";
    exit;
}

// Instanciar el controlador y obtener la información de la lista
$glc = new GiftListController($pdo);

// Obtener la lista por ID
$stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
$stmt->execute([$id]);
$list = $stmt->fetch();

if (!$list) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
    exit;
}

// Obtener los regalos de la lista
$stmt = $pdo->prepare("SELECT * FROM gifts WHERE gift_list_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$gifts = $stmt->fetchAll();

// Obtener información del creador
$stmt = $pdo->prepare("SELECT name, lastname FROM users WHERE id = ?");
$stmt->execute([$list['user_id']]);
$creator = $stmt->fetch();

$error = "";
$successMessage = "";

// Procesar el formulario al enviar
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger los datos enviados
    $data = [
        'title'         => trim($_POST['title'] ?? ''),
        'description'   => trim($_POST['description'] ?? ''),
        'event_type'    => trim($_POST['event_type'] ?? ''),
        'beneficiary1'  => trim($_POST['beneficiary1'] ?? ''),
        'beneficiary2'  => trim($_POST['beneficiary2'] ?? ''),
        'expiry_date'   => !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null,
        'visibility'    => $_POST['visibility'] ?? 'link_only'
    ];
    
    // Actualizar la lista mediante el controlador
    if ($glc->update($id, $data)) {
        // Procesar regalos existentes que no fueron eliminados
        if (isset($_POST['gift_id']) && is_array($_POST['gift_id'])) {
            foreach ($_POST['gift_id'] as $index => $giftId) {
                if (!empty($giftId)) {
                    $giftData = [
                        'name'        => $_POST['gift_name'][$index] ?? '',
                        'price'       => floatval($_POST['gift_price'][$index] ?? 0),
                        'stock'       => intval($_POST['gift_stock'][$index] ?? 0),
                    ];
                    
                    $glc->updateGift($giftId, $giftData);
                }
            }
        }
        
        // Procesar nuevos regalos
        if (isset($_POST['new_gift_name']) && is_array($_POST['new_gift_name'])) {
            foreach ($_POST['new_gift_name'] as $index => $name) {
                if (!empty($name)) {
                    $newGiftData = [
                        'name'        => $name,
                        'price'       => floatval($_POST['new_gift_price'][$index] ?? 0),
                        'stock'       => intval($_POST['new_gift_stock'][$index] ?? 0),
                    ];
                    
                    $glc->addGift($id, $newGiftData);
                }
            }
        }
        
        // Procesar eliminaciones de regalos
        if (isset($_POST['delete_gift_id']) && is_array($_POST['delete_gift_id'])) {
            foreach ($_POST['delete_gift_id'] as $giftId) {
                if (!empty($giftId)) {
                    // Eliminar regalo
                    $stmt = $pdo->prepare("DELETE FROM gifts WHERE id = ? AND gift_list_id = ?");
                    $stmt->execute([$giftId, $id]);
                }
            }
        }
        
        $successMessage = "Lista y regalos actualizados exitosamente.";
        
        // Refrescar la información de la lista y regalos
        $stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
        $stmt->execute([$id]);
        $list = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM gifts WHERE gift_list_id = ? ORDER BY id ASC");
        $stmt->execute([$id]);
        $gifts = $stmt->fetchAll();
    } else {
        $error = "Error al actualizar la lista.";
    }
}

// Formatear la fecha de expiración para el input date
$expiry_date_formatted = '';
if (!empty($list['expiry_date'])) {
    $expiry_date_formatted = date('Y-m-d', strtotime($list['expiry_date']));
}

// Cargar configuración global
$config = require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Lista de Regalos - Administración</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .gift-row {
            background-color: #f9f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .gift-row:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .gift-row.new-gift {
            background-color: #e9f7fe;
            border: 1px dashed #0d6efd;
        }
        
        .gift-row.deleted {
            opacity: 0.6;
            background-color: #ffebee;
            border: 1px solid #ffcdd2;
        }
        
        .visibility-info {
            font-size: 0.9rem;
            display: none;
            margin-top: 5px;
        }
        
        .total-field {
            font-weight: bold;
            background-color: #e9f7fe !important;
            color: #0d6efd;
            text-align: right;
        }
        
        .admin-content {
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .admin-card {
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,.1);
            margin-bottom: 20px;
        }
        
        .admin-card .card-header {
            background-color: #343a40;
            color: white;
            border-radius: 5px 5px 0 0;
        }
        
        /* Animación para elementos eliminados */
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0.6; }
        }
        
        .fade-out {
            animation: fadeOut 0.5s forwards;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">GiftList Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="users.php">Usuarios</a></li>
                    <li class="nav-item"><a class="nav-link active" href="giftlists.php">Listas de Regalo</a></li>
                    <li class="nav-item"><a class="nav-link" href="preset_product_lists.php">Listas Predeterminadas</a></li>
                    <li class="nav-item"><a class="nav-link" href="transactions.php">Transacciones</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Editar Lista de Regalos</h1>
            <a href="giftlists.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver a Listas
            </a>
        </div>
        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="alert alert-info">
            <h5><i class="bi bi-info-circle-fill"></i> Información de la lista</h5>
            <p class="mb-1"><strong>Creador:</strong> <?php echo htmlspecialchars($creator['name'] . ' ' . $creator['lastname']); ?></p>
            <p class="mb-1"><strong>Fecha de creación:</strong> <?php echo date('d/m/Y H:i', strtotime($list['created_at'])); ?></p>
            <p class="mb-0"><strong>Enlace único:</strong> <code><?php echo htmlspecialchars($list['unique_link']); ?></code></p>
        </div>
        
        <form method="post" action="" id="editForm">
            <!-- Inputs ocultos para IDs de regalos a eliminar -->
            <div id="deleted-gifts"></div>
            
            <!-- Datos generales de la lista -->
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Datos Generales</h4>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Título:</label>
                        <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($list['title']); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción:</label>
                        <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($list['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="event_type" class="form-label">Tipo de Evento:</label>
                                <select id="event_type" name="event_type" class="form-select">
                                    <option value="">Seleccione un tipo de evento</option>
                                    <option value="Cumpleaños" <?php echo $list['event_type'] === 'Cumpleaños' ? 'selected' : ''; ?>>Cumpleaños</option>
                                    <option value="Matrimonio" <?php echo $list['event_type'] === 'Matrimonio' ? 'selected' : ''; ?>>Matrimonio</option>
                                    <option value="Baby Shower" <?php echo $list['event_type'] === 'Baby Shower' ? 'selected' : ''; ?>>Baby Shower</option>
                                    <option value="Aniversario" <?php echo $list['event_type'] === 'Aniversario' ? 'selected' : ''; ?>>Aniversario</option>
                                    <option value="Graduación" <?php echo $list['event_type'] === 'Graduación' ? 'selected' : ''; ?>>Graduación</option>
                                    <option value="Otro" <?php echo $list['event_type'] === 'Otro' ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="beneficiarySingle" class="mb-3 <?php echo $list['event_type'] === 'Matrimonio' ? 'd-none' : ''; ?>">
                                <label for="beneficiary1" class="form-label">Beneficiario:</label>
                                <input type="text" id="beneficiary1" name="beneficiary1" class="form-control" 
                                       value="<?php echo htmlspecialchars($list['beneficiary1'] ?? ''); ?>">
                            </div>
                            
                            <div id="beneficiaryDouble" class="mb-3 <?php echo $list['event_type'] === 'Matrimonio' ? '' : 'd-none'; ?>">
                                <label class="form-label">Beneficiarios (novios):</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" name="beneficiary1" class="form-control mb-2" 
                                               placeholder="Primer beneficiario" 
                                               value="<?php echo htmlspecialchars($list['beneficiary1'] ?? ''); ?>"
                                               <?php echo $list['event_type'] !== 'Matrimonio' ? 'disabled' : ''; ?>>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" name="beneficiary2" class="form-control" 
                                               placeholder="Segundo beneficiario" 
                                               value="<?php echo htmlspecialchars($list['beneficiary2'] ?? ''); ?>"
                                               <?php echo $list['event_type'] !== 'Matrimonio' ? 'disabled' : ''; ?>>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fecha límite y visibilidad -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">Fecha Límite (opcional):</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="<?php echo $expiry_date_formatted; ?>">
                                <small class="form-text text-muted">Deja en blanco si no hay fecha límite.</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="visibility" class="form-label">Visibilidad de la Lista:</label>
                                <select id="visibility" name="visibility" class="form-select" required>
                                    <option value="link_only" <?php echo $list['visibility'] === 'link_only' ? 'selected' : ''; ?>>Solo con enlace</option>
                                    <option value="public" <?php echo $list['visibility'] === 'public' ? 'selected' : ''; ?>>Pública</option>
                                    <option value="private" <?php echo $list['visibility'] === 'private' ? 'selected' : ''; ?>>Privada</option>
                                </select>
                                
                                <!-- Información de visibilidad -->
                                <div id="link_only_info" class="visibility-info text-info">
                                    <i class="bi bi-info-circle"></i> Solo personas con el enlace podrán ver esta lista.
                                </div>
                                <div id="public_info" class="visibility-info text-success">
                                    <i class="bi bi-globe"></i> Esta lista será visible públicamente y aparecerá en búsquedas.
                                </div>
                                <div id="private_info" class="visibility-info text-warning">
                                    <i class="bi bi-lock"></i> Solo el propietario podrá ver esta lista. Tendrá que compartir el enlace manualmente.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Regalos existentes -->
            <div class="card admin-card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Regalos de la Lista</h4>
                        <button type="button" id="add-gift-btn" class="btn btn-sm btn-light">
                            <i class="bi bi-plus-circle"></i> Añadir Nuevo Regalo
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="gifts-container">
                        <?php if (!empty($gifts)): ?>
                            <?php foreach ($gifts as $index => $gift): ?>
                                <div class="gift-row" id="gift-row-<?php echo $gift['id']; ?>" data-gift-id="<?php echo $gift['id']; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">
                                            Regalo #<?php echo $index + 1; ?>
                                        </h5>
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-gift-btn" 
                                                data-gift-id="<?php echo $gift['id']; ?>">
                                            <i class="bi bi-trash"></i> Eliminar
                                        </button>
                                    </div>
                                    
                                    <div class="row">
                                        <input type="hidden" name="gift_id[]" value="<?php echo $gift['id']; ?>">
                                        
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Nombre:</label>
                                            <input type="text" name="gift_name[]" class="form-control" 
                                                   value="<?php echo htmlspecialchars($gift['name']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Precio:</label>
                                            <input type="number" name="gift_price[]" class="form-control price-input" 
                                                   step="0.01" min="0" value="<?php echo $gift['price']; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Stock:</label>
                                            <input type="number" name="gift_stock[]" class="form-control stock-input" 
                                                   min="0" value="<?php echo $gift['stock']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-0">
                                                <label class="form-label">Estadísticas:</label>
                                                <div class="bg-light p-2 rounded">
                                                    <small>
                                                        <span class="fw-bold">Vendidos:</span> <?php echo $gift['sold']; ?> | 
                                                        <span class="fw-bold">Contribuido:</span> <?php echo function_exists('format_money') ? format_money($gift['contributed'], 'CLP') : '$' . number_format($gift['contributed'], 0, ',', '.'); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Total:</label>
                                            <div class="form-control bg-light total-field" data-gift-id="<?php echo $gift['id']; ?>">
                                                <?php echo number_format($gift['price'] * $gift['stock'], 2); ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No hay regalos en esta lista. Agrega algunos usando el botón de arriba.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Contenedor para nuevos regalos -->
                    <div id="new-gifts-container"></div>
                </div>
            </div>
            
            <div class="card mb-5">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">Resumen</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grand_total" class="form-label">Total General:</label>
                                <div id="grand_total" class="form-control bg-light fs-4">
                                    <?php 
                                    $grandTotal = 0;
                                    foreach ($gifts as $gift) {
                                        $grandTotal += $gift['price'] * $gift['stock'];
                                    }
                                    echo number_format($grandTotal, 2);
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="d-grid gap-2 w-100">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-save"></i> Guardar Cambios
                                </button>
                            </div>
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

    <!-- Template para nuevos regalos -->
    <template id="new-gift-template">
        <div class="gift-row new-gift">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Nuevo Regalo</h5>
                <button type="button" class="btn btn-sm btn-outline-danger remove-new-gift-btn">
                    <i class="bi bi-trash"></i> Cancelar
                </button>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Nombre:</label>
                    <input type="text" name="new_gift_name[]" class="form-control" required>
                </div>
                
                <div class="col-md-4 mb-2">
                    <label class="form-label">Precio:</label>
                    <input type="number" name="new_gift_price[]" class="form-control new-price-input" step="0.01" min="0" value="0" required>
                </div>
                
                <div class="col-md-4 mb-2">
                    <label class="form-label">Stock:</label>
                    <input type="number" name="new_gift_stock[]" class="form-control new-stock-input" min="0" value="1" required>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 offset-md-6">
                    <label class="form-label">Total:</label>
                    <div class="form-control bg-light new-total-field">0.00</div>
                </div>
            </div>
        </div>
    </template>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addGiftBtn = document.getElementById('add-gift-btn');
            const newGiftsContainer = document.getElementById('new-gifts-container');
            const newGiftTemplate = document.getElementById('new-gift-template');
            const visibilitySelect = document.getElementById('visibility');
            const deletedGiftsContainer = document.getElementById('deleted-gifts');
            const grandTotalElement = document.getElementById('grand_total');
            
            // Control de beneficiarios según tipo de evento
            const eventTypeSelect = document.getElementById('event_type');
            const beneficiarySingle = document.getElementById('beneficiarySingle');
            const beneficiaryDouble = document.getElementById('beneficiaryDouble');
            
            // Array para llevar registro de regalos eliminados
            let deletedGifts = [];
            
            // Iniciar calculando los totales
            calculateAllTotals();
            
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
            
            // Mostrar la información de visibilidad inicial
            updateVisibilityInfo();
            
            if (eventTypeSelect) {
                eventTypeSelect.addEventListener('change', function() {
                    handleEventTypeChange();
                });
                
                // Inicializar el estado de los beneficiarios
                handleEventTypeChange();
            }
            
            // Función para manejar el cambio de tipo de evento
            function handleEventTypeChange() {
                const eventType = eventTypeSelect.value;
                
                // Obtener referencias a ambos inputs de beneficiario1
                const singleBeneficiary = document.querySelector('#beneficiarySingle input[name="beneficiary1"]');
                const doubleBeneficiary1 = document.querySelector('#beneficiaryDouble input[name="beneficiary1"]');
                const doubleBeneficiary2 = document.querySelector('#beneficiaryDouble input[name="beneficiary2"]');
                
                if (eventType === "Matrimonio") {
                    // Transferir valor de single a double si es necesario
                    if (!doubleBeneficiary1.value && singleBeneficiary.value) {
                        doubleBeneficiary1.value = singleBeneficiary.value;
                    }
                    
                    // Mostrar double, ocultar single
                    beneficiarySingle.classList.add('d-none');
                    beneficiaryDouble.classList.remove('d-none');
                    
                    // Deshabilitar input single para asegurar que no se envíe
                    singleBeneficiary.disabled = true;
                    // Habilitar inputs double
                    doubleBeneficiary1.disabled = false;
                    doubleBeneficiary2.disabled = false;
                } else {
                    // Transferir valor de double a single si es necesario
                    if (!singleBeneficiary.value && doubleBeneficiary1.value) {
                        singleBeneficiary.value = doubleBeneficiary1.value;
                    }
                    
                    // Mostrar single, ocultar double
                    beneficiarySingle.classList.remove('d-none');
                    beneficiaryDouble.classList.add('d-none');
                    
                    // Habilitar input single
                    singleBeneficiary.disabled = false;
                    // Deshabilitar inputs double para asegurar que no se envíen
                    doubleBeneficiary1.disabled = true;
                    doubleBeneficiary2.disabled = true;
                }
            }
            
            // Función para calcular todos los totales
            function calculateAllTotals() {
                // Calcular totales de regalos existentes
                document.querySelectorAll('.price-input, .stock-input').forEach(input => {
                    // Encontrar el contenedor del regalo
                    const giftRow = input.closest('.gift-row');
                    if (giftRow) {
                        const giftId = giftRow.dataset.giftId;
                        if (giftId && !deletedGifts.includes(giftId)) {
                            calculateGiftTotal(giftId);
                        }
                    }
                });
                
                // Calcular totales de nuevos regalos
                calculateNewGiftTotals();
                
                // Actualizar el total general
                updateGrandTotal();
            }
            
            // Función para calcular el total de un regalo específico
            function calculateGiftTotal(giftId) {
                const giftRow = document.getElementById(`gift-row-${giftId}`);
                if (!giftRow) return;
                
                const priceInput = giftRow.querySelector('.price-input');
                const stockInput = giftRow.querySelector('.stock-input');
                const totalField = giftRow.querySelector(`.total-field[data-gift-id="${giftId}"]`);
                
                if (priceInput && stockInput && totalField) {
                    const price = parseFloat(priceInput.value) || 0;
                    const stock = parseInt(stockInput.value) || 0;
                    const total = price * stock;
                    totalField.textContent = total.toFixed(2);
                }
            }
            
            // Función para calcular totales de nuevos regalos
            function calculateNewGiftTotals() {
                document.querySelectorAll('.new-gift').forEach(giftRow => {
                    const priceInput = giftRow.querySelector('.new-price-input');
                    const stockInput = giftRow.querySelector('.new-stock-input');
                    const totalField = giftRow.querySelector('.new-total-field');
                    
                    if (priceInput && stockInput && totalField) {
                        const price = parseFloat(priceInput.value) || 0;
                        const stock = parseInt(stockInput.value) || 0;
                        const total = price * stock;
                        totalField.textContent = total.toFixed(2);
                    }
                });
            }
            
            // Función para actualizar el total general
            function updateGrandTotal() {
                let grandTotal = 0;
                
                // Sumar totales de regalos existentes (no eliminados)
                document.querySelectorAll('.gift-row:not(.deleted) .total-field').forEach(field => {
                    grandTotal += parseFloat(field.textContent) || 0;
                });
                
                // Sumar totales de nuevos regalos
                document.querySelectorAll('.new-gift .new-total-field').forEach(field => {
                    grandTotal += parseFloat(field.textContent) || 0;
                });
                
                // Actualizar el elemento del total general
                if (grandTotalElement) {
                    grandTotalElement.textContent = grandTotal.toFixed(2);
                }
            }
            
            // Función para marcar un regalo como eliminado
            function markGiftAsDeleted(giftId) {
                const giftRow = document.getElementById(`gift-row-${giftId}`);
                if (!giftRow) return;
                
                // Añadir clase de eliminado y animación
                giftRow.classList.add('deleted', 'fade-out');
                
                // Agregar el ID a la lista de regalos eliminados
                if (!deletedGifts.includes(giftId)) {
                    deletedGifts.push(giftId);
                    
                    // Crear input oculto para enviar este ID al servidor
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_gift_id[]';
                    input.value = giftId;
                    deletedGiftsContainer.appendChild(input);
                }
                
                // Recalcular totales
                calculateAllTotals();
            }
            
            // Restaurar un regalo eliminado
            function restoreGift(giftId) {
                const giftRow = document.getElementById(`gift-row-${giftId}`);
                if (!giftRow) return;
                
                // Quitar clases de eliminado
                giftRow.classList.remove('deleted', 'fade-out');
                
                // Quitar de la lista de eliminados
                deletedGifts = deletedGifts.filter(id => id !== giftId);
                
                // Quitar input oculto
                const inputs = deletedGiftsContainer.querySelectorAll('input');
                for (let input of inputs) {
                    if (input.value === giftId) {
                        input.remove();
                        break;
                    }
                }
                
                // Recalcular totales
                calculateAllTotals();
            }
            
            // Función para añadir un nuevo regalo
            addGiftBtn.addEventListener('click', function() {
                // Clonar el template
                const newGift = document.importNode(newGiftTemplate.content, true);
                
                // Agregar al contenedor
                newGiftsContainer.appendChild(newGift);
                
                // Configurar event listeners para inputs de precio y stock
                const newGiftRow = newGiftsContainer.lastElementChild;
                const priceInput = newGiftRow.querySelector('.new-price-input');
                const stockInput = newGiftRow.querySelector('.new-stock-input');
                
                if (priceInput) {
                    priceInput.addEventListener('input', function() {
                        calculateNewGiftTotals();
                        updateGrandTotal();
                    });
                }
                
                if (stockInput) {
                    stockInput.addEventListener('input', function() {
                        calculateNewGiftTotals();
                        updateGrandTotal();
                    });
                }
                
                // Configurar el botón de eliminar
                const removeBtn = newGiftRow.querySelector('.remove-new-gift-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function() {
                        newGiftRow.remove();
                        updateGrandTotal();
                    });
                }
                
                // Calcular totales iniciales
                calculateNewGiftTotals();
                updateGrandTotal();
                
                // Hacer scroll hasta el nuevo regalo
                newGiftRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
            
            // Event listeners para los botones de eliminar regalo existente
            document.querySelectorAll('.remove-gift-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const giftId = this.dataset.giftId;
                    if (giftId) {
                        // Verificar si ya está marcado como eliminado
                        const giftRow = document.getElementById(`gift-row-${giftId}`);
                        if (giftRow && giftRow.classList.contains('deleted')) {
                            // Si ya está eliminado, restaurarlo
                            restoreGift(giftId);
                            this.innerHTML = '<i class="bi bi-trash"></i> Eliminar';
                            this.classList.remove('btn-outline-success');
                            this.classList.add('btn-outline-danger');
                        } else {
                            // Si no está eliminado, marcarlo como eliminado
                            markGiftAsDeleted(giftId);
                            this.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Restaurar';
                            this.classList.remove('btn-outline-danger');
                            this.classList.add('btn-outline-success');
                        }
                    }
                });
            });
            
            // Event listeners para todos los inputs de precio y stock existentes
            document.querySelectorAll('.price-input, .stock-input').forEach(input => {
                input.addEventListener('input', function() {
                    const giftRow = this.closest('.gift-row');
                    if (giftRow) {
                        const giftId = giftRow.dataset.giftId;
                        if (giftId) {
                            calculateGiftTotal(giftId);
                            updateGrandTotal();
                        }
                    }
                });
            });
            
            // Delegación de eventos para eliminar nuevos regalos añadidos dinámicamente
            newGiftsContainer.addEventListener('click', function(e) {
                if (e.target.matches('.remove-new-gift-btn, .remove-new-gift-btn *')) {
                    const button = e.target.closest('.remove-new-gift-btn');
                    const giftRow = button.closest('.new-gift');
                    if (giftRow) {
                        giftRow.remove();
                        updateGrandTotal();
                    }
                }
            });
        });
    </script>
</body>
</html>