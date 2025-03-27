<?php
// public/notifications.php

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/NotificationController.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Verificar que el usuario esté autenticado
$auth = new Auth($pdo);
$auth->require('login.php');
$user = $auth->user();

// Instanciar controlador de notificaciones
$notificationController = new NotificationController($pdo);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        // Marcar notificación como leída
        if ($_POST['action'] === 'mark_read' && isset($_POST['id'])) {
            $notificationController->markAsRead($_POST['id'], $user['id']);
        }
        
        // Marcar todas como leídas
        if ($_POST['action'] === 'mark_all_read') {
            $notificationController->markAllAsRead($user['id']);
        }
        
        // Eliminar notificación
        if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
            $notificationController->deleteNotification($_POST['id'], $user['id']);
        }
        
        // Redirigir para evitar envíos duplicados
        header("Location: notifications.php");
        exit;
    }
}

// Obtener notificaciones
$showAll = isset($_GET['show']) && $_GET['show'] === 'all';
$notifications = $notificationController->getUserNotifications($user['id'], !$showAll);

// Cargar configuración global
$config = require_once __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .notification {
            border-left: 4px solid #dee2e6;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .notification:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .notification.unread {
            border-left-color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.05);
        }
        
        .notification-type {
            position: absolute;
            top: 15px;
            right: 15px;
        }
        
        .notification-icon {
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .notification-time {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .notification-empty {
            text-align: center;
            padding: 40px 0;
        }
        
        .notification-filters {
            margin-bottom: 20px;
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
                        <a class="nav-link active" href="notifications.php">Notificaciones</a>
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
        <h1>Mis Notificaciones</h1>
        
        <div class="notification-filters d-flex justify-content-between align-items-center">
            <div>
                <?php if ($showAll): ?>
                    <a href="notifications.php" class="btn btn-outline-primary">
                        <i class="bi bi-bell"></i> Mostrar no leídas
                    </a>
                <?php else: ?>
                    <a href="notifications.php?show=all" class="btn btn-outline-primary">
                        <i class="bi bi-bell-fill"></i> Mostrar todas
                    </a>
                <?php endif; ?>
            </div>
            
            <form method="post" action="" class="d-inline">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-success">
                    <i class="bi bi-check2-all"></i> Marcar todas como leídas
                </button>
            </form>
        </div>
        
        <?php if (empty($notifications)): ?>
            <div class="notification-empty">
                <i class="bi bi-bell-slash" style="font-size: 3rem; color: #dee2e6;"></i>
                <h3 class="mt-3">No hay notificaciones</h3>
                <p class="text-muted">
                    <?php echo $showAll ? 'No tienes ninguna notificación.' : 'No tienes notificaciones no leídas.'; ?>
                </p>
                <?php if (!$showAll): ?>
                    <a href="notifications.php?show=all" class="btn btn-primary mt-2">Ver todas las notificaciones</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    // Determinar el ícono según el tipo
                    $icon = 'bell';
                    $typeClass = 'secondary';
                    
                    switch ($notification['type']) {
                        case 'transaction':
                            $icon = 'cart-check';
                            $typeClass = 'success';
                            break;
                        case 'reservation':
                            $icon = 'calendar-check';
                            $typeClass = 'info';
                            break;
                        case 'thank_you':
                            $icon = 'envelope-heart';
                            $typeClass = 'danger';
                            break;
                        case 'expiry':
                            $icon = 'alarm';
                            $typeClass = 'warning';
                            break;
                    }
                    
                    // Formatear la fecha
                    $date = new DateTime($notification['created_at']);
                    $now = new DateTime();
                    $diff = $now->diff($date);
                    
                    if ($diff->days == 0) {
                        if ($diff->h == 0) {
                            if ($diff->i == 0) {
                                $time = "Hace unos segundos";
                            } else {
                                $time = "Hace " . $diff->i . " minuto" . ($diff->i > 1 ? 's' : '');
                            }
                        } else {
                            $time = "Hace " . $diff->h . " hora" . ($diff->h > 1 ? 's' : '');
                        }
                    } elseif ($diff->days == 1) {
                        $time = "Ayer";
                    } else {
                        $time = $date->format('d/m/Y');
                    }
                    ?>
                    <div class="card notification <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                        <div class="card-body position-relative">
                            <span class="position-absolute notification-type badge bg-<?php echo $typeClass; ?>">
                                <?php 
                                switch ($notification['type']) {
                                    case 'transaction': echo 'Compra'; break;
                                    case 'reservation': echo 'Reserva'; break;
                                    case 'thank_you': echo 'Agradecimiento'; break;
                                    case 'expiry': echo 'Expiración'; break;
                                    default: echo 'Sistema'; break;
                                }
                                ?>
                            </span>
                            
                            <div class="d-flex">
                                <div class="notification-icon text-<?php echo $typeClass; ?>">
                                    <i class="bi bi-<?php echo $icon; ?>"></i>
                                </div>
                                <div>
                                    <h5 class="card-title"><?php echo htmlspecialchars($notification['title']); ?></h5>
                                    <p class="card-text"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <p class="notification-time">
                                        <i class="bi bi-clock"></i> <?php echo $time; ?>
                                    </p>
                                    
                                    <div class="notification-actions">
                                        <?php if (!empty($notification['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> Ver detalles
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (!$notification['is_read']): ?>
                                            <form method="post" action="" class="d-inline">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-check2"></i> Marcar como leída
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" action="" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de eliminar esta notificación?')">
                                                <i class="bi bi-trash"></i> Eliminar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>