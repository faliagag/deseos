<?php
// public/admin/transactions.php - Versión mejorada

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Cargar configuración
$config = require_once __DIR__ . '/../../config/config.php';

// Inicializar el controlador de administración
$admin = new AdminController($pdo);

// Obtener parámetros de filtro
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$filter_status = isset($_GET['status']) ? $_GET['status'] : null;
$filter_transfer = isset($_GET['transfer_status']) ? $_GET['transfer_status'] : null;
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Procesar la acción para cambiar el estado de transferencia
if (isset($_POST['action']) && $_POST['action'] === 'update_transfer') {
    $transaction_id = (int)$_POST['transaction_id'];
    $transferred = (int)$_POST['transferred'];
    
    // Actualizar el estado de transferencia
    try {
        $stmt = $pdo->prepare("UPDATE transactions SET transferred = ? WHERE id = ?");
        $stmt->execute([$transferred, $transaction_id]);
        set_flash_message('success', 'Estado de transferencia actualizado correctamente.');
    } catch (Exception $e) {
        set_flash_message('danger', 'Error al actualizar el estado de transferencia: ' . $e->getMessage());
    }
    
    // Redireccionar para evitar reenvío del formulario
    header("Location: transactions.php");
    exit;
}

// Procesar solicitud de reporte por usuario
if (isset($_GET['generate_report']) && $_GET['generate_report'] === 'true' && $filter_user) {
    // Configurar encabezados para descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_transacciones_usuario_' . $filter_user . '.csv');
    
    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT name, lastname, email FROM users WHERE id = ?");
    $stmt->execute([$filter_user]);
    $user_data = $stmt->fetch();
    
    // Crear el archivo CSV
    $output = fopen('php://output', 'w');
    
    // Escribir BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezado del informe
    fputcsv($output, ['Reporte de Transacciones - ' . $user_data['name'] . ' ' . $user_data['lastname'] . ' (' . $user_data['email'] . ')']);
    fputcsv($output, ['Generado el:', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Encabezados de columnas
    fputcsv($output, ['ID', 'Lista', 'Regalo', 'Monto', 'Moneda', 'Estado', 'Transferido', 'Fecha']);
    
    // Consultar transacciones con filtros
    $params = [$filter_user];
    $sql = "
        SELECT t.*, gl.title as list_title, g.name as gift_name 
        FROM transactions t
        LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
        LEFT JOIN gifts g ON t.gift_id = g.id
        WHERE t.user_id = ?
    ";
    
    // Añadir filtros adicionales si existen
    if ($filter_status) {
        $sql .= " AND t.status = ?";
        $params[] = $filter_status;
    }
    
    if ($filter_transfer !== null) {
        $sql .= " AND t.transferred = ?";
        $params[] = $filter_transfer;
    }
    
    if ($filter_date_from) {
        $sql .= " AND t.created_at >= ?";
        $params[] = $filter_date_from . ' 00:00:00';
    }
    
    if ($filter_date_to) {
        $sql .= " AND t.created_at <= ?";
        $params[] = $filter_date_to . ' 23:59:59';
    }
    
    $sql .= " ORDER BY t.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $user_transactions = $stmt->fetchAll();
    
    // Escribir datos
    foreach ($user_transactions as $tx) {
        $transfer_status = isset($tx['transferred']) && $tx['transferred'] == 1 ? 'Sí' : 'No';
        
        fputcsv($output, [
            $tx['id'],
            $tx['list_title'] ?? 'N/A',
            $tx['gift_name'] ?? 'N/A',
            number_format($tx['amount'], 2, ',', '.'),
            strtoupper($tx['currency'] ?? 'CLP'),
            $tx['status'],
            $transfer_status,
            $tx['created_at']
        ]);
    }
    
    // Añadir resumen al final
    fputcsv($output, []);
    
    // Contar transacciones exitosas
    $successful = array_filter($user_transactions, function($tx) {
        return $tx['status'] === 'succeeded';
    });
    
    // Contar transacciones fallidas
    $failed = array_filter($user_transactions, function($tx) {
        return $tx['status'] === 'failed';
    });
    
    // Contar transacciones pendientes
    $pending = array_filter($user_transactions, function($tx) {
        return $tx['status'] === 'pending';
    });
    
    // Contar transacciones transferidas
    $transferred = array_filter($user_transactions, function($tx) {
        return isset($tx['transferred']) && $tx['transferred'] == 1;
    });
    
    // Contar transacciones no transferidas
    $not_transferred = array_filter($user_transactions, function($tx) {
        return isset($tx['transferred']) && $tx['transferred'] == 0;
    });
    
    // Calcular monto total
    $total_amount = array_reduce($successful, function($carry, $tx) {
        return $carry + $tx['amount'];
    }, 0);
    
    fputcsv($output, ['Resumen de Transacciones']);
    fputcsv($output, ['Total de transacciones:', count($user_transactions)]);
    fputcsv($output, ['Transacciones exitosas:', count($successful)]);
    fputcsv($output, ['Transacciones fallidas:', count($failed)]);
    fputcsv($output, ['Transacciones pendientes:', count($pending)]);
    fputcsv($output, ['Transacciones transferidas:', count($transferred)]);
    fputcsv($output, ['Transacciones no transferidas:', count($not_transferred)]);
    fputcsv($output, ['Monto total:', number_format($total_amount, 2, ',', '.') . ' ' . (isset($user_transactions[0]) ? strtoupper($user_transactions[0]['currency'] ?? 'CLP') : 'CLP')]);
    
    fclose($output);
    exit;
}

// Consulta para obtener la lista de usuarios (para filtro)
$stmt = $pdo->query("SELECT id, name, lastname, email FROM users ORDER BY name, lastname");
$users = $stmt->fetchAll();

// Consulta transacciones con todos los filtros
$transactions = [];
$sql_params = [];
$sql = "
    SELECT t.*, 
           gl.title as list_title, 
           g.name as gift_name,
           u.name as user_name, 
           u.lastname as user_lastname,
           u.email as user_email
    FROM transactions t
    LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
    LEFT JOIN gifts g ON t.gift_id = g.id
    LEFT JOIN users u ON t.user_id = u.id
    WHERE 1=1
";

// Aplicar filtros a la consulta SQL
if ($filter_user) {
    $sql .= " AND t.user_id = ?";
    $sql_params[] = $filter_user;
}

if ($filter_status) {
    $sql .= " AND t.status = ?";
    $sql_params[] = $filter_status;
}

if ($filter_transfer !== null) {
    $sql .= " AND t.transferred = ?";
    $sql_params[] = $filter_transfer;
}

if ($filter_date_from) {
    $sql .= " AND t.created_at >= ?";
    $sql_params[] = $filter_date_from . ' 00:00:00';
}

if ($filter_date_to) {
    $sql .= " AND t.created_at <= ?";
    $sql_params[] = $filter_date_to . ' 23:59:59';
}

$sql .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($sql_params);
$transactions = $stmt->fetchAll();

// Obtener estadísticas generales de transacciones
$stats = [
    'total' => count($transactions),
    'succeeded' => 0,
    'failed' => 0,
    'pending' => 0,
    'transferred' => 0,
    'not_transferred' => 0,
    'total_amount' => 0,
];

// Estadísticas por usuario
$user_stats = [];

foreach ($transactions as $tx) {
    // Estadísticas generales
    if ($tx['status'] === 'succeeded') {
        $stats['succeeded']++;
        $stats['total_amount'] += $tx['amount'];
    } elseif ($tx['status'] === 'failed') {
        $stats['failed']++;
    } elseif ($tx['status'] === 'pending') {
        $stats['pending']++;
    }
    
    if (isset($tx['transferred'])) {
        if ($tx['transferred'] == 1) {
            $stats['transferred']++;
        } else {
            $stats['not_transferred']++;
        }
    }
    
    // Estadísticas por usuario
    if ($tx['user_id']) {
        if (!isset($user_stats[$tx['user_id']])) {
            $user_stats[$tx['user_id']] = [
                'user_name' => $tx['user_name'] . ' ' . $tx['user_lastname'],
                'user_email' => $tx['user_email'],
                'total' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'pending' => 0,
                'transferred' => 0,
                'not_transferred' => 0,
                'total_amount' => 0,
            ];
        }
        
        $user_stats[$tx['user_id']]['total']++;
        
        if ($tx['status'] === 'succeeded') {
            $user_stats[$tx['user_id']]['succeeded']++;
            $user_stats[$tx['user_id']]['total_amount'] += $tx['amount'];
        } elseif ($tx['status'] === 'failed') {
            $user_stats[$tx['user_id']]['failed']++;
        } elseif ($tx['status'] === 'pending') {
            $user_stats[$tx['user_id']]['pending']++;
        }
        
        if (isset($tx['transferred'])) {
            if ($tx['transferred'] == 1) {
                $user_stats[$tx['user_id']]['transferred']++;
            } else {
                $user_stats[$tx['user_id']]['not_transferred']++;
            }
        }
    }
}

// Verificar si la columna 'transferred' existe en la tabla, si no, la creamos
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'transactions' 
        AND COLUMN_NAME = 'transferred'
    ");
    
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("ALTER TABLE transactions ADD COLUMN transferred TINYINT DEFAULT 0 AFTER status");
    }
} catch (Exception $e) {
    // Ignorar errores y continuar
}

// Título de la página
$title = "Gestión de Transacciones";
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
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .stats-card {
            margin-bottom: 20px;
        }
        
        .stats-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        
        .status-badge {
            font-size: 0.85rem;
        }
        
        .transfer-badge {
            cursor: pointer;
        }
        
        .filter-section {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .user-stats-table th, .user-stats-table td {
            vertical-align: middle;
        }
        
        .admin-table td, .admin-table th {
            vertical-align: middle;
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
                    <li class="nav-item"><a class="nav-link" href="preset_product_lists.php">Listas Predeterminadas</a></li>
                    <li class="nav-item"><a class="nav-link active" href="transactions.php">Transacciones</a></li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><?php echo $title; ?></h1>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
        
        <?php 
        // Mostrar mensaje flash si existe
        $flash = get_flash_message();
        if ($flash): 
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Estadísticas generales -->
        <div class="row stats-card">
            <div class="col-md-2">
                <div class="card text-white bg-primary">
                    <div class="card-body text-center">
                        <h5 class="card-title">Total Transacciones</h5>
                        <div class="stats-value"><?php echo $stats['total']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-success">
                    <div class="card-body text-center">
                        <h5 class="card-title">Exitosas</h5>
                        <div class="stats-value"><?php echo $stats['succeeded']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-danger">
                    <div class="card-body text-center">
                        <h5 class="card-title">Fallidas</h5>
                        <div class="stats-value"><?php echo $stats['failed']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-dark bg-warning">
                    <div class="card-body text-center">
                        <h5 class="card-title">Pendientes</h5>
                        <div class="stats-value"><?php echo $stats['pending']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-info">
                    <div class="card-body text-center">
                        <h5 class="card-title">Transferidas</h5>
                        <div class="stats-value"><?php echo $stats['transferred']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-white bg-secondary">
                    <div class="card-body text-center">
                        <h5 class="card-title">Monto Total</h5>
                        <div class="stats-value"><?php echo format_money($stats['total_amount'], 'CLP'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filtros -->
        <div class="filter-section">
            <form method="get" action="transactions.php" class="row g-3">
                <div class="col-md-3">
                    <label for="user_id" class="form-label">Usuario</label>
                    <select name="user_id" id="user_id" class="form-select">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>" <?php echo ($filter_user == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name'] . ' ' . $user['lastname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Estado</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="succeeded" <?php echo ($filter_status === 'succeeded') ? 'selected' : ''; ?>>Exitosa</option>
                        <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="failed" <?php echo ($filter_status === 'failed') ? 'selected' : ''; ?>>Fallida</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="transfer_status" class="form-label">Transferencia</label>
                    <select name="transfer_status" id="transfer_status" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" <?php echo ($filter_transfer === '1') ? 'selected' : ''; ?>>Transferida</option>
                        <option value="0" <?php echo ($filter_transfer === '0') ? 'selected' : ''; ?>>No transferida</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Desde</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $filter_date_from; ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Hasta</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $filter_date_to; ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
                
                <?php if($filter_user): ?>
                <div class="col-12 mt-2">
                    <a href="transactions.php?generate_report=true&user_id=<?php echo $filter_user; ?>&status=<?php echo $filter_status; ?>&transfer_status=<?php echo $filter_transfer; ?>&date_from=<?php echo $filter_date_from; ?>&date_to=<?php echo $filter_date_to; ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Generar Reporte CSV
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if($filter_user): ?>
        <!-- Estadísticas del usuario filtrado -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Estadísticas de Usuario: <?php echo htmlspecialchars($user_stats[$filter_user]['user_name']); ?></h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Total Transacciones</h6>
                                <div class="stats-value text-primary"><?php echo $user_stats[$filter_user]['total']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Exitosas</h6>
                                <div class="stats-value text-success"><?php echo $user_stats[$filter_user]['succeeded']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Fallidas</h6>
                                <div class="stats-value text-danger"><?php echo $user_stats[$filter_user]['failed']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Pendientes</h6>
                                <div class="stats-value text-warning"><?php echo $user_stats[$filter_user]['pending']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Transferidas</h6>
                                <div class="stats-value text-info"><?php echo $user_stats[$filter_user]['transferred']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6 class="card-title">Monto Total</h6>
                                <div class="stats-value text-secondary"><?php echo format_money($user_stats[$filter_user]['total_amount'], 'CLP'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Estadísticas por usuario -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Estadísticas por Usuario</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover user-stats-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Total</th>
                                <th>Exitosas</th>
                                <th>Fallidas</th>
                                <th>Pendientes</th>
                                <th>Transferidas</th>
                                <th>No Transferidas</th>
                                <th>Monto Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_stats as $user_id => $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($stat['user_email']); ?></td>
                                    <td><?php echo $stat['total']; ?></td>
                                    <td><?php echo $stat['succeeded']; ?></td>
                                    <td><?php echo $stat['failed']; ?></td>
                                    <td><?php echo $stat['pending']; ?></td>
                                    <td><?php echo $stat['transferred']; ?></td>
                                    <td><?php echo $stat['not_transferred']; ?></td>
                                    <td><?php echo format_money($stat['total_amount'], 'CLP'); ?></td>
                                    <td>
                                        <a href="transactions.php?user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                        <a href="transactions.php?generate_report=true&user_id=<?php echo $user_id; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-file-earmark-excel"></i> Reporte
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($user_stats)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No hay estadísticas disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Listado de transacciones -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Listado de Transacciones<?php echo $filter_user ? ' - Usuario: ' . htmlspecialchars($user_stats[$filter_user]['user_name']) : ''; ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Lista</th>
                                <th>Regalo</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Transferido</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $tx): ?>
                                <tr>
                                    <td><?php echo $tx['id']; ?></td>
                                    <td>
                                        <?php if (!empty($tx['user_name'])): ?>
                                            <?php echo htmlspecialchars($tx['user_name'] . ' ' . $tx['user_lastname']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Anónimo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tx['list_title'])): ?>
                                            <?php echo htmlspecialchars($tx['list_title']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($tx['gift_name'])): ?>
                                            <?php echo htmlspecialchars($tx['gift_name']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo format_money($tx['amount'], $tx['currency'] ?? 'CLP'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'secondary';
                                        $status_text = 'Desconocido';
                                        
                                        if ($tx['status'] === 'succeeded') {
                                            $status_class = 'success';
                                            $status_text = 'Exitosa';
                                        } elseif ($tx['status'] === 'failed') {
                                            $status_class = 'danger';
                                            $status_text = 'Fallida';
                                        } elseif ($tx['status'] === 'pending') {
                                            $status_class = 'warning';
                                            $status_text = 'Pendiente';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $status_class; ?> status-badge">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($tx['transferred'])): ?>
                                            <form method="post" action="" class="d-inline">
                                                <input type="hidden" name="action" value="update_transfer">
                                                <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                                <input type="hidden" name="transferred" value="<?php echo $tx['transferred'] ? 0 : 1; ?>">
                                                
                                                <?php if ($tx['transferred'] == 1): ?>
                                                    <button type="submit" class="badge bg-success border-0 transfer-badge">
                                                        <i class="bi bi-check-circle"></i> Sí
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="badge bg-danger border-0 transfer-badge">
                                                        <i class="bi bi-x-circle"></i> No
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y H:i', strtotime($tx['created_at'])); ?>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $tx['id']; ?>">
                                            <i class="bi bi-info-circle"></i> Detalles
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Modal de detalles -->
                                <div class="modal fade" id="detailsModal<?php echo $tx['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $tx['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="detailsModalLabel<?php echo $tx['id']; ?>">Detalles de Transacción #<?php echo $tx['id']; ?></h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-3">
                                                    <div class="col-md-6">
                                                        <h6>Información General</h6>
                                                        <table class="table table-bordered">
                                                            <tbody>
                                                                <tr>
                                                                    <th>ID:</th>
                                                                    <td><?php echo $tx['id']; ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Fecha:</th>
                                                                    <td><?php echo date('d/m/Y H:i:s', strtotime($tx['created_at'])); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Estado:</th>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo $status_class; ?>">
                                                                            <?php echo $status_text; ?>
                                                                        </span>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Transferido:</th>
                                                                    <td>
                                                                        <?php if (isset($tx['transferred'])): ?>
                                                                            <?php if ($tx['transferred'] == 1): ?>
                                                                                <span class="badge bg-success">Sí</span>
                                                                            <?php else: ?>
                                                                                <span class="badge bg-danger">No</span>
                                                                            <?php endif; ?>
                                                                        <?php else: ?>
                                                                            <span class="badge bg-secondary">N/A</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Información Financiera</h6>
                                                        <table class="table table-bordered">
                                                            <tbody>
                                                                <tr>
                                                                    <th>Monto:</th>
                                                                    <td><?php echo format_money($tx['amount'], $tx['currency'] ?? 'CLP'); ?></td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Moneda:</th>
                                                                    <td><?php echo strtoupper($tx['currency'] ?? 'CLP'); ?></td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <h6>Usuario</h6>
                                                        <table class="table table-bordered">
                                                            <tbody>
                                                                <tr>
                                                                    <th>Nombre:</th>
                                                                    <td>
                                                                        <?php if (!empty($tx['user_name'])): ?>
                                                                            <?php echo htmlspecialchars($tx['user_name'] . ' ' . $tx['user_lastname']); ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">Anónimo</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Email:</th>
                                                                    <td>
                                                                        <?php if (!empty($tx['user_email'])): ?>
                                                                            <?php echo htmlspecialchars($tx['user_email']); ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <h6>Lista y Regalo</h6>
                                                        <table class="table table-bordered">
                                                            <tbody>
                                                                <tr>
                                                                    <th>Lista:</th>
                                                                    <td>
                                                                        <?php if (!empty($tx['list_title'])): ?>
                                                                            <?php echo htmlspecialchars($tx['list_title']); ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                                <tr>
                                                                    <th>Regalo:</th>
                                                                    <td>
                                                                        <?php if (!empty($tx['gift_name'])): ?>
                                                                            <?php echo htmlspecialchars($tx['gift_name']); ?>
                                                                        <?php else: ?>
                                                                            <span class="text-muted">N/A</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($tx['metadata'])): ?>
                                                    <div class="mt-3">
                                                        <h6>Metadatos</h6>
                                                        <div class="bg-light p-3 rounded">
                                                            <pre class="mb-0"><?php echo htmlspecialchars(json_encode(json_decode($tx['metadata']), JSON_PRETTY_PRINT)); ?></pre>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <?php if (isset($tx['transferred'])): ?>
                                                    <form method="post" action="" class="me-auto">
                                                        <input type="hidden" name="action" value="update_transfer">
                                                        <input type="hidden" name="transaction_id" value="<?php echo $tx['id']; ?>">
                                                        <input type="hidden" name="transferred" value="<?php echo $tx['transferred'] ? 0 : 1; ?>">
                                                        
                                                        <?php if ($tx['transferred'] == 1): ?>
                                                            <button type="submit" class="btn btn-warning">
                                                                <i class="bi bi-x-circle"></i> Marcar como No Transferido
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" class="btn btn-success">
                                                                <i class="bi bi-check-circle"></i> Marcar como Transferido
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                <?php endif; ?>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No hay transacciones disponibles</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
</body>
</html>