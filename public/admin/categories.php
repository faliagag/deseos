<?php
// public/admin/categories.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../models/Category.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Instanciar el modelo de categorías
$categoryModel = new Category($pdo);

// Procesar acciones
$success = '';
$error = '';

// Acción: Crear categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'gift');
    
    if (empty($name)) {
        $error = "El nombre de la categoría es obligatorio";
    } else {
        $result = $categoryModel->create($name, $description, $icon);
        if ($result) {
            $success = "Categoría creada exitosamente";
        } else {
            $error = "Error al crear la categoría";
        }
    }
}

// Acción: Actualizar categoría
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? 'gift');
    
    if (empty($id) || empty($name)) {
        $error = "ID y nombre son obligatorios";
    } else {
        $result = $categoryModel->update($id, $name, $description, $icon);
        if ($result) {
            $success = "Categoría actualizada exitosamente";
        } else {
            $error = "Error al actualizar la categoría";
        }
    }
}

// Acción: Eliminar categoría
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $result = $categoryModel->delete($id);
    if ($result) {
        $success = "Categoría eliminada exitosamente";
    } else {
        $error = "Error al eliminar la categoría";
    }
}

// Obtener categoría para editar
$editCategory = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $editCategory = $categoryModel->getById($id);
}

// Obtener todas las categorías
$categories = $categoryModel->getAll();

// Lista de iconos de Bootstrap Icons populares
$popularIcons = [
    'gift', 'bag', 'cart', 'house', 'star', 'heart', 'gem', 'currency-dollar', 
    'book', 'music-note', 'film', 'camera', 'laptop', 'phone', 'controller', 
    'bicycle', 'car-front', 'airplane', 'balloon', 'cup-hot', 'egg', 'flower1',
    'sun', 'moon', 'cloud', 'snow', 'umbrella', 'tree', 'bug', 'emoji-smile'
];

// Cargar configuración global
$config = require_once __DIR__ . '/../../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Categorías - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .icon-preview {
            font-size: 1.5rem;
            margin-right: 10px;
        }
        
        .icon-select {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        .icon-option {
            display: flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .icon-option:hover {
            background-color: #f0f0f0;
        }
        
        .icon-option.selected {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
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
                    <li class="nav-item"><a class="nav-link" href="giftlists.php">Listas de Regalo</a></li>
                    <li class="nav-item"><a class="nav-link active" href="categories.php">Categorías</a></li>
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
        <h1>Gestión de Categorías</h1>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <?php echo $editCategory ? 'Editar Categoría' : 'Nueva Categoría'; ?>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
                            <?php if ($editCategory): ?>
                                <input type="hidden" name="id" value="<?php echo $editCategory['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Nombre</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo $editCategory ? htmlspecialchars($editCategory['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Descripción</label>
                                <textarea id="description" name="description" class="form-control" rows="3"><?php echo $editCategory ? htmlspecialchars($editCategory['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Icono</label>
                                <div class="input-group">
                                    <span class="input-group-text icon-preview">
                                        <i class="bi bi-<?php echo $editCategory ? htmlspecialchars($editCategory['icon']) : 'gift'; ?>"></i>
                                    </span>
                                    <input type="text" id="icon" name="icon" class="form-control" 
                                           value="<?php echo $editCategory ? htmlspecialchars($editCategory['icon']) : 'gift'; ?>" required>
                                </div>
                                <small class="form-text text-muted">Nombre del icono de Bootstrap Icons (sin el prefijo bi-)</small>
                                
                                <div class="icon-select mt-2">
                                    <?php foreach ($popularIcons as $icon): ?>
                                        <div class="icon-option <?php echo ($editCategory && $editCategory['icon'] === $icon) || (!$editCategory && $icon === 'gift') ? 'selected' : ''; ?>" 
                                             data-icon="<?php echo $icon; ?>">
                                            <i class="bi bi-<?php echo $icon; ?> me-2"></i>
                                            <?php echo $icon; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $editCategory ? 'Actualizar Categoría' : 'Crear Categoría'; ?>
                                </button>
                                <?php if ($editCategory): ?>
                                    <a href="categories.php" class="btn btn-outline-secondary">Cancelar</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        Categorías Existentes
                    </div>
                    <div class="card-body">
                        <?php if (empty($categories)): ?>
                            <div class="alert alert-info">No hay categorías definidas aún.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Icono</th>
                                            <th>Descripción</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td><?php echo $category['id']; ?></td>
                                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                                <td>
                                                    <i class="bi bi-<?php echo htmlspecialchars($category['icon']); ?>"></i>
                                                    <?php echo htmlspecialchars($category['icon']); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($category['description']); ?></td>
                                                <td>
                                                    <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i> Editar
                                                    </a>
                                                    <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>" 
                                                       class="btn btn-sm btn-danger"
                                                       onclick="return confirm('¿Está seguro de eliminar esta categoría? Los regalos asociados se desasociarán de esta categoría.')">
                                                        <i class="bi bi-trash"></i> Eliminar
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
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
            // Manejar selección de iconos
            const iconInput = document.getElementById('icon');
            const iconPreview = document.querySelector('.icon-preview i');
            const iconOptions = document.querySelectorAll('.icon-option');
            
            iconInput.addEventListener('input', function() {
                // Actualizar la vista previa del icono
                iconPreview.className = 'bi bi-' + this.value;
                
                // Actualizar la opción seleccionada
                iconOptions.forEach(option => {
                    option.classList.remove('selected');
                    if (option.dataset.icon === this.value) {
                        option.classList.add('selected');
                    }
                });
            });
            
            // Manejar clic en opciones de iconos
            iconOptions.forEach(option => {
                option.addEventListener('click', function() {
                    const iconName = this.dataset.icon;
                    
                    // Actualizar input y preview
                    iconInput.value = iconName;
                    iconPreview.className = 'bi bi-' + iconName;
                    
                    // Actualizar selección
                    iconOptions.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });
        });
    </script>
</body>
</html>