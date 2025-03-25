<?php
// public/dashboard.php

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/Auth.php';

// Verificar autenticación
$auth = new Auth($pdo);
$auth->require('login.php');

$user = $auth->user();
$glc = new GiftListController($pdo);
$paymentController = new PaymentController($pdo);

// Obtener listas del usuario
$myLists = $glc->getByUser($user['id']);

// Obtener historial de transacciones
$transactions = $paymentController->getUserTransactionHistory($user['id']);

// Corregir la inicialización para evitar NULL
if (!is_array($transactions)) {
    $transactions = []; // Asegurarse de que transactions sea siempre un array
}

// Obtener mensaje flash
$flash = get_flash_message();

// Cargar configuración global
$config = require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
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
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="create_giftlist.php">Crear Lista</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($user['name'] . ' ' . $user['lastname']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="edit_profile.php">Editar Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Mensaje flash -->
        <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
                <?php echo $flash['message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <h1 class="mb-4">Bienvenido, <?php echo htmlspecialchars($user['name']); ?></h1>

        <!-- Tarjetas de estadísticas -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="dashboard-stat blue">
                    <h3><?php echo is_array($myLists) ? count($myLists) : 0; ?></h3>
                    <p>Listas Creadas</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-stat green">
                    <h3><?php echo count($transactions); ?></h3>
                    <p>Transacciones</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-stat orange">
                    <h3>
                        <?php 
                            $totalAmount = 0;
                            foreach ($transactions as $tx) {
                                if ($tx['status'] === 'succeeded') {
                                    $totalAmount += $tx['amount'];
                                }
                            }
                            echo format_money($totalAmount, 'CLP');
                        ?>
                    </h3>
                    <p>Total Recaudado</p>
                </div>
            </div>
        </div>

        <!-- Nav Tabs para organizar las funcionalidades -->
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="lists-tab" data-bs-toggle="tab" data-bs-target="#lists" type="button" role="tab" aria-controls="lists" aria-selected="true">
                    Mis Listas de Regalos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">
                    Historial de Transacciones
                </button>
            </li>
        </ul>

        <div class="tab-content mt-3" id="dashboardTabsContent">
            <!-- Tab: Mis Listas de Regalos -->
            <div class="tab-pane fade show active" id="lists" role="tabpanel" aria-labelledby="lists-tab">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Mis Listas de Regalos</h3>
                    <a href="create_giftlist.php" class="btn btn-primary">
                        <i class="bi bi-plus"></i> Crear Nueva Lista
                    </a>
                </div>
                
                <?php if (is_array($myLists) && !empty($myLists)): ?>
                    <div class="row">
                        <?php foreach ($myLists as $list): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100 dashboard-panel">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($list['title']); ?></h5>
                                        <p class="card-text">
                                            <?php 
                                                echo mb_strlen($list['description']) > 100 
                                                    ? htmlspecialchars(mb_substr($list['description'], 0, 100)) . '...' 
                                                    : htmlspecialchars($list['description']); 
                                            ?>
                                        </p>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                Creada: <?php echo format_date($list['created_at']); ?>
                                            </small>
                                        </p>
                                    </div>
                                    <div class="card-footer bg-transparent">
                                        <div class="btn-group" role="group">
                                            <a href="edit_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                Editar
                                            </a>
                                            <a href="delete_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-outline-danger" 
                                                onclick="return confirm('¿Estás seguro de eliminar esta lista?');">
                                                Eliminar
                                            </a>
                                            <a href="share_list.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-outline-info">
                                                Compartir
                                            </a>
                                            <a href="giftlist.php?link=<?php echo $list['unique_link']; ?>" class="btn btn-sm btn-outline-success">
                                                Ver
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <p>No has creado ninguna lista de regalos aún.</p>
                        <a href="create_giftlist.php" class="btn btn-primary mt-2">Crear mi primera lista</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Historial de Transacciones -->
            <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
                <h3>Historial de Transacciones</h3>
                
                <?php if (!empty($transactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Lista</th>
                                    <th>Regalo</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td><?php echo format_date($tx['created_at']); ?></td>
                                        <td><?php echo htmlspecialchars($tx['list_title']); ?></td>
                                        <td><?php echo $tx['gift_name'] ? htmlspecialchars($tx['gift_name']) : 'N/A'; ?></td>
                                        <td><?php echo format_money($tx['amount'], 'CLP'); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = 'secondary';
                                                if ($tx['status'] === 'succeeded') $statusClass = 'success';
                                                if ($tx['status'] === 'failed') $statusClass = 'danger';
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>">
                                                <?php echo ucfirst($tx['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No tienes transacciones registradas.</div>
                <?php endif; ?>
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
    <script src="assets/js/script.js"></script>
</body>
</html>