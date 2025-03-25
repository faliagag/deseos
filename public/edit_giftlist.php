<?php
// public/edit_giftlist.php - Versión actualizada

// Inicia la sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Verifica que el usuario esté autenticado
$auth = new Auth($pdo);
$auth->require('login.php');

// Obtener el ID de la lista a editar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se especificó la lista a editar.</div></div>";
    exit;
}

// Instanciar el controlador y obtener la información de la lista
$glc = new GiftListController($pdo);

// Obtener categorías para los regalos
$categories = $glc->getAllCategories();

// Obtener la lista por ID
$stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
$stmt->execute([$id]);
$list = $stmt->fetch();

if (!$list) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
    exit;
}

// Verificar que el usuario es propietario de la lista
if ($list['user_id'] !== $_SESSION['user']['id'] && $_SESSION['user']['role'] !== 'admin') {
    echo "<div class='container mt-5'><div class='alert alert-danger'>No tienes permisos para editar esta lista.</div></div>";
    exit;
}

// Obtener los regalos de la lista
$stmt = $pdo->prepare("
    SELECT g.*, c.name as category_name 
    FROM gifts g
    LEFT JOIN gift_categories c ON g.category_id = c.id
    WHERE g.gift_list_id = ?
    ORDER BY g.id ASC
");
$stmt->execute([$id]);
$gifts = $stmt->fetchAll();

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
        // Procesar actualización de regalos existentes
        if (isset($_POST['gift_id']) && is_array($_POST['gift_id'])) {
            foreach ($_POST['gift_id'] as $index => $giftId) {
                if (!empty($giftId)) {
                    $giftData = [
                        'name'        => $_POST['gift_name'][$index] ?? '',
                        'description' => $_POST['gift_description'][$index] ?? '',
                        'price'       => floatval($_POST['gift_price'][$index] ?? 0),
                        'stock'       => intval($_POST['gift_stock'][$index] ?? 0),
                        'category_id' => !empty($_POST['gift_category'][$index]) ? intval($_POST['gift_category'][$index]) : null
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
                        'description' => $_POST['new_gift_description'][$index] ?? '',
                        'price'       => floatval($_POST['new_gift_price'][$index] ?? 0),
                        'stock'       => intval($_POST['new_gift_stock'][$index] ?? 0),
                        'category_id' => !empty($_POST['new_gift_category'][$index]) ? intval($_POST['new_gift_category'][$index]) : null
                    ];
                    
                    $glc->addGift($id, $newGiftData);
                }
            }
        }
        
        $successMessage = "Lista y regalos actualizados exitosamente.";
        
        // Refrescar la información de la lista y regalos
        $stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE id = ?");
        $stmt->execute([$id]);
        $list = $stmt->fetch();
        
        $stmt = $pdo->prepare("
            SELECT g.*, c.name as category_name 
            FROM gifts g
            LEFT JOIN gift_categories c ON g.category_id = c.id
            WHERE g.gift_list_id = ?
            ORDER BY g.id ASC
        ");
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Lista de Regalos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .gift-row {
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
        }
        .gift-row:hover {
            background-color: #f0f0f0;
            border-color: #ccc;
        }
        .new-gift-row {
            background-color: #e9f7fe;
            border: 1px dashed #0d6efd;
        }
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
                        <a class="nav-link" href="create_giftlist.php">Crear Lista</a>
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
        <h1>Editar Lista de Regalos</h1>
        
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="post" action="">
            <!-- Datos generales de la lista -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
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
                            <div class="mb-3">
                                <label for="beneficiary1" class="form-label">Beneficiario:</label>
                                <input type="text" id="beneficiary1" name="beneficiary1" class="form-control" value="<?php echo htmlspecialchars($list['beneficiary1'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nuevos campos para fecha límite y visibilidad -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="expiry_date" class="form-label">Fecha Límite (opcional):</label>
                                <input type="date" id="expiry_date" name="expiry_date" class="form-control" value="<?php echo $expiry_date_formatted; ?>">
                                <small class="form-text text-muted">Deja en blanco si no deseas fecha límite.</small>
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
                                    <i class="bi bi-lock"></i> Solo tú podrás ver esta lista. Tendrás que compartir el enlace manualmente.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Regalos existentes -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
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
                                <div class="gift-row">
                                    <div class="row mb-2">
                                        <div class="col-md-12">
                                            <h5 class="mb-0">Regalo #<?php echo $index + 1; ?></h5>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <input type="hidden" name="gift_id[]" value="<?php echo $gift['id']; ?>">
                                        
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Nombre:</label>
                                            <input type="text" name="gift_name[]" class="form-control" value="<?php echo htmlspecialchars($gift['name']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-8 mb-2">
                                            <label class="form-label">Descripción:</label>
                                            <input type="text" name="gift_description[]" class="form-control" value="<?php echo htmlspecialchars($gift['description']); ?>">
                                        </div>
                                        
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Precio:</label>
                                            <input type="number" name="gift_price[]" class="form-control" step="0.01" min="0" value="<?php echo $gift['price']; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Stock:</label>
                                            <input type="number" name="gift_stock[]" class="form-control" min="0" value="<?php echo $gift['stock']; ?>" required>
                                        </div>
                                        
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Vendidos:</label>
                                            <input type="text" class="form-control" value="<?php echo $gift['sold']; ?>" readonly>
                                        </div>
                                        
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Categoría:</label>
                                            <select name="gift_category[]" class="form-select">
                                                <option value="">Sin categoría</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>" <?php echo $gift['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
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
            
            <div class="d-flex justify-content-between">
                <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
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
        <div class="gift-row new-gift-row">
            <div class="row mb-2">
                <div class="col-md-12">
                    <h5 class="mb-0">Nuevo Regalo</h5>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Nombre:</label>
                    <input type="text" name="new_gift_name[]" class="form-control" required>
                </div>
                
                <div class="col-md-8 mb-2">
                    <label class="form-label">Descripción:</label>
                    <input type="text" name="new_gift_description[]" class="form-control">
                </div>
                
                <div class="col-md-3 mb-2">
                    <label class="form-label">Precio:</label>
                    <input type="number" name="new_gift_price[]" class="form-control" step="0.01" min="0" value="0" required>
                </div>
                
                <div class="col-md-3 mb-2">
                    <label class="form-label">Stock:</label>
                    <input type="number" name="new_gift_stock[]" class="form-control" min="0" value="1" required>
                </div>
                
                <div class="col-md-6 mb-2">
                    <label class="form-label">Categoría:</label>
                    <select name="new_gift_category[]" class="form-select">
                        <option value="">Sin categoría</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger remove-gift mt-2">
                <i class="bi bi-trash"></i> Eliminar
            </button>
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
            
            // Función para añadir un nuevo regalo
            addGiftBtn.addEventListener('click', function() {
                // Clonar el template
                const newGift = document.importNode(newGiftTemplate.content, true);
                
                // Agregar al contenedor
                newGiftsContainer.appendChild(newGift);
                
                // Configurar el botón de eliminar
                const removeBtn = newGiftsContainer.querySelector('.new-gift-row:last-child .remove-gift');
                removeBtn.addEventListener('click', function() {
                    this.closest('.new-gift-row').remove();
                });
            });
            
            // Configurar los botones de eliminar para nuevos regalos añadidos dinámicamente
            newGiftsContainer.addEventListener('click', function(e) {
                if (e.target.matches('.remove-gift, .remove-gift *')) {
                    const button = e.target.closest('.remove-gift');
                    button.closest('.new-gift-row').remove();
                }
            });
        });
    </script>
</body>
</html>