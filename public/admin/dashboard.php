<?php
/**
 * DASHBOARD ADMINISTRATIVO MEJORADO - VERSIÓN 2.1
 * 
 * Características avanzadas:
 * - Métricas de ingresos y fees en tiempo real
 * - Gestión de testimonios con aprobación rápida
 * - Sistema de payouts quincenales
 * - Alertas del sistema automáticas
 * - Gráficos interactivos con Chart.js
 * - Timeline de actividad reciente
 * - Acciones rápidas para administración
 */

session_start();
require_once '../../includes/db.php';
require_once '../../models/AdminModel.php';
require_once '../../includes/helpers.php';

// Cargar configuración
$config = include '../../config/config.php';

// Verificar acceso admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$adminModel = new AdminModel($pdo, $config);

// Obtener métricas principales
$dashboardData = [
    'revenue' => $adminModel->getRevenueMetrics(),
    'users' => $adminModel->getUserMetrics(),
    'payouts' => $adminModel->getPayoutMetrics(),
    'testimonials' => $adminModel->getTestimonialMetrics(),
    'transactions' => $adminModel->getTransactionMetrics(),
    'events' => $adminModel->getEventMetrics(),
    'alerts' => $adminModel->getSystemAlerts()
];

$pageTitle = 'Dashboard Administrativo';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Mi Lista de Regalos</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            padding-top: 1rem;
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
        }
        .card {
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        .border-start {
            border-left-width: 4px !important;
        }
        .text-xs {
            font-size: 0.75rem;
        }
        .timeline {
            position: relative;
        }
        .timeline-item {
            position: relative;
            padding-left: 40px;
            padding-bottom: 20px;
        }
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 5px;
            top: 17px;
            width: 2px;
            height: calc(100% - 12px);
            background-color: #dee2e6;
        }
        .timeline-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .timeline-text {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid;
        }
        .metric-card.primary { border-left-color: #007bff; }
        .metric-card.success { border-left-color: #28a745; }
        .metric-card.info { border-left-color: #17a2b8; }
        .metric-card.warning { border-left-color: #ffc107; }
        .quick-action-btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            margin: 0.25rem;
            transition: all 0.3s ease;
        }
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
            }
            .timeline-item {
                padding-left: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">
                            <i class="fas fa-cog me-2"></i>Panel Admin
                        </h5>
                        <small class="text-white-50">Versión 2.1</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="transactions.php">
                                <i class="fas fa-credit-card me-2"></i>Transacciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payouts.php">
                                <i class="fas fa-money-bill-wave me-2"></i>Pagos Quincenales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="testimonials.php">
                                <i class="fas fa-star me-2"></i>Testimonios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Usuarios
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="giftlists.php">
                                <i class="fas fa-gift me-2"></i>Listas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reportes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="configuration.php">
                                <i class="fas fa-cogs me-2"></i>Configuración
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2 text-primary"></i>Dashboard Administrativo
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportReport()">
                                <i class="fas fa-download"></i> Exportar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#quickActionModal">
                                <i class="fas fa-bolt"></i> Acciones Rápidas
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alertas del Sistema -->
                <?php if (!empty($dashboardData['alerts'])): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <?php foreach ($dashboardData['alerts'] as $alert): ?>
                        <div class="alert alert-<?php echo $alert['type']; ?> alert-dismissible fade show" role="alert">
                            <strong><i class="fas fa-exclamation-triangle me-2"></i><?php echo $alert['title']; ?></strong>
                            <?php echo $alert['message']; ?>
                            <?php if (isset($alert['action_url'])): ?>
                            <a href="<?php echo $alert['action_url']; ?>" class="btn btn-sm btn-outline-<?php echo $alert['type']; ?> ms-2">
                                Ver Detalles
                            </a>
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Métricas Principales -->
                <div class="row mb-4">
                    <!-- Ingresos Totales -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card metric-card primary shadow-sm h-100">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">
                                        Ingresos Totales
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-gray-800">
                                        $<?php echo number_format($dashboardData['revenue']['total'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-chart-line me-1"></i>
                                        Promedio: $<?php echo number_format($dashboardData['revenue']['avg_transaction'], 0, ',', '.'); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Fees Generados -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card metric-card success shadow-sm h-100">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">
                                        Fees Generados (10%)
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-gray-800">
                                        $<?php echo number_format($dashboardData['revenue']['fees'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-percentage me-1"></i>
                                        <?php echo $dashboardData['revenue']['transactions']; ?> transacciones
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Usuarios Activos -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card metric-card info shadow-sm h-100">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-info text-uppercase mb-1">
                                        Usuarios Activos (30 días)
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-gray-800">
                                        <?php echo number_format($dashboardData['users']['active'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-user-plus me-1"></i>
                                        +<?php echo $dashboardData['users']['new_this_month']; ?> nuevos
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Próximo Pago -->
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card metric-card warning shadow-sm h-100">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">
                                        Próximo Pago
                                    </div>
                                    <div class="h4 mb-0 fw-bold text-gray-800">
                                        $<?php echo number_format($dashboardData['payouts']['next_amount'], 0, ',', '.'); ?>
                                    </div>
                                    <div class="text-xs text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('d/m/Y', strtotime($dashboardData['payouts']['next_date'])); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Métricas Secundarias -->
                <div class="row mb-4">
                    <div class="col-xl-8">
                        <div class="row">
                            <!-- Tasa de Conversión -->
                            <div class="col-md-4 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-primary"><?php echo $dashboardData['users']['conversion_rate']; ?>%</h5>
                                        <p class="card-text small text-muted">Tasa de Conversión</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Testimonios Pendientes -->
                            <div class="col-md-4 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-warning"><?php echo $dashboardData['testimonials']['pending']; ?></h5>
                                        <p class="card-text small text-muted">Testimonios Pendientes</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Transacciones Completadas -->
                            <div class="col-md-4 mb-3">
                                <div class="card text-center">
                                    <div class="card-body">
                                        <h5 class="card-title text-success"><?php echo $dashboardData['transactions']['completed']; ?></h5>
                                        <p class="card-text small text-muted">Completadas (30d)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="m-0 fw-bold text-primary">Métodos de Pago</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Transbank</span>
                                    <span class="badge bg-primary"><?php echo $dashboardData['transactions']['transbank_count']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>MercadoPago</span>
                                    <span class="badge bg-info"><?php echo $dashboardData['transactions']['mercadopago_count']; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Fallidas</span>
                                    <span class="badge bg-danger"><?php echo $dashboardData['transactions']['failed']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="row mb-4">
                    <!-- Gráfico de Ingresos -->
                    <div class="col-xl-8 col-lg-7 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 fw-bold text-primary">
                                    <i class="fas fa-chart-area me-2"></i>Evolución de Ingresos
                                </h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-calendar me-1"></i>Último Año
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="changeChartPeriod('year')">Año</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="changeChartPeriod('month')">Mes</a></li>
                                        <li><a class="dropdown-item" href="#" onclick="changeChartPeriod('week')">Semana</a></li>
                                    </ul>
                                </div>
                            </div>
                            <div class="card-body">
                                <canvas id="revenueChart" style="height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Eventos -->
                    <div class="col-xl-4 col-lg-5 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 fw-bold text-primary">
                                    <i class="fas fa-chart-pie me-2"></i>Eventos Populares
                                </h6>
                            </div>
                            <div class="card-body">
                                <canvas id="eventsChart" style="height: 300px;"></canvas>
                                <div class="mt-3 text-center">
                                    <small class="text-muted">Últimos 6 meses</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tablas de Gestión Rápida -->
                <div class="row">
                    <!-- Testimonios Pendientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-primary">
                                    <i class="fas fa-star me-2"></i>Testimonios Pendientes
                                    <span class="badge bg-warning ms-2"><?php echo $dashboardData['testimonials']['pending']; ?></span>
                                </h6>
                                <a href="testimonials.php" class="btn btn-sm btn-primary">Ver Todos</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Rating</th>
                                                <th>Fecha</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pendingTestimonials">
                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Próximos Pagos -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 fw-bold text-primary">
                                    <i class="fas fa-money-bill-wave me-2"></i>Próximos Pagos Quincenales
                                </h6>
                                <a href="payouts.php" class="btn btn-sm btn-success">Procesar</a>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive" style="max-height: 300px;">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Monto</th>
                                                <th>Transacciones</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="upcomingPayouts">
                                            <tr>
                                                <td colspan="4" class="text-center">
                                                    <div class="spinner-border spinner-border-sm text-success" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actividad Reciente -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 fw-bold text-primary">
                                    <i class="fas fa-clock me-2"></i>Actividad Reciente del Sistema
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="timeline" id="recentActivity">
                                    <div class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Cargando actividad...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Modal de Acciones Rápidas -->
    <div class="modal fade" id="quickActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success quick-action-btn" onclick="processQuickPayout()">
                            <i class="fas fa-money-bill-wave me-2"></i>Procesar Pagos Pendientes
                        </button>
                        <button class="btn btn-info quick-action-btn" onclick="approveAllTestimonials()">
                            <i class="fas fa-star me-2"></i>Aprobar Testimonios (5★)
                        </button>
                        <button class="btn btn-warning quick-action-btn" onclick="sendNotificationBlast()">
                            <i class="fas fa-envelope me-2"></i>Notificación Masiva
                        </button>
                        <button class="btn btn-secondary quick-action-btn" onclick="generateBackup()">
                            <i class="fas fa-database me-2"></i>Generar Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div id="toastContainer" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
    // Inicialización del dashboard
    document.addEventListener('DOMContentLoaded', function() {
        initializeDashboard();
    });

    function initializeDashboard() {
        initializeCharts();
        loadPendingTestimonials();
        loadUpcomingPayouts();
        loadRecentActivity();
        
        // Auto-refresh cada 5 minutos
        setInterval(() => {
            refreshDashboardData();
        }, 300000);
    }

    function initializeCharts() {
        // Gráfico de ingresos
        const revenueCtx = document.getElementById('revenueChart');
        if (revenueCtx) {
            new Chart(revenueCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dashboardData['revenue']['months'] ?? []); ?>,
                    datasets: [
                        {
                            label: 'Ingresos Totales',
                            data: <?php echo json_encode($dashboardData['revenue']['monthly_data'] ?? []); ?>,
                            borderColor: 'rgb(78, 115, 223)',
                            backgroundColor: 'rgba(78, 115, 223, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Fees (10%)',
                            data: <?php echo json_encode($dashboardData['revenue']['monthly_fees'] ?? []); ?>,
                            borderColor: 'rgb(28, 200, 138)',
                            backgroundColor: 'rgba(28, 200, 138, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }

        // Gráfico de eventos
        const eventsCtx = document.getElementById('eventsChart');
        if (eventsCtx) {
            new Chart(eventsCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($dashboardData['events']['types'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($dashboardData['events']['counts'] ?? []); ?>,
                        backgroundColor: [
                            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', 
                            '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf'
                        ],
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 10,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        }
    }

    function loadPendingTestimonials() {
        fetch('ajax/get_pending_testimonials.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('pendingTestimonials');
                if (!data || data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay testimonios pendientes</td></tr>';
                    return;
                }
                
                tbody.innerHTML = '';
                data.slice(0, 5).forEach(testimonial => {
                    tbody.innerHTML += `
                        <tr>
                            <td>
                                <strong>${testimonial.user_name}</strong><br>
                                <small class="text-muted">${testimonial.user_email}</small>
                            </td>
                            <td>
                                <span class="text-warning">${'★'.repeat(testimonial.rating)}</span>
                            </td>
                            <td>
                                <small>${new Date(testimonial.created_at).toLocaleDateString('es-CL')}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-success btn-sm" onclick="approveTestimonial(${testimonial.id})" title="Aprobar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectTestimonial(${testimonial.id})" title="Rechazar">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Error loading testimonials:', error);
                document.getElementById('pendingTestimonials').innerHTML = 
                    '<tr><td colspan="4" class="text-center text-danger">Error al cargar datos</td></tr>';
            });
    }

    function loadUpcomingPayouts() {
        fetch('ajax/get_upcoming_payouts.php')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('upcomingPayouts');
                if (!data || data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No hay pagos pendientes</td></tr>';
                    return;
                }
                
                tbody.innerHTML = '';
                data.slice(0, 5).forEach(payout => {
                    tbody.innerHTML += `
                        <tr>
                            <td>
                                <strong>${payout.user_name}</strong><br>
                                <small class="text-muted">${payout.user_email}</small>
                            </td>
                            <td class="text-end">
                                <strong>$${parseInt(payout.amount).toLocaleString()}</strong>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark">${payout.transaction_count}</span>
                            </td>
                            <td>
                                <span class="badge bg-warning">Pendiente</span>
                            </td>
                        </tr>
                    `;
                });
            })
            .catch(error => {
                console.error('Error loading payouts:', error);
                document.getElementById('upcomingPayouts').innerHTML = 
                    '<tr><td colspan="4" class="text-center text-danger">Error al cargar datos</td></tr>';
            });
    }

    function loadRecentActivity() {
        fetch('ajax/get_recent_activity.php')
            .then(response => response.json())
            .then(data => {
                const timeline = document.getElementById('recentActivity');
                if (!data || data.length === 0) {
                    timeline.innerHTML = '<p class="text-center text-muted">No hay actividad reciente</p>';
                    return;
                }
                
                timeline.innerHTML = '';
                data.forEach(activity => {
                    timeline.innerHTML += `
                        <div class="timeline-item">
                            <div class="timeline-marker bg-${activity.status_type}"></div>
                            <div class="timeline-content">
                                <h6 class="timeline-title">${activity.type.replace('_', ' ').toUpperCase()}</h6>
                                <p class="timeline-text">${activity.description}</p>
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>${activity.time_ago}
                                </small>
                            </div>
                        </div>
                    `;
                });
            })
            .catch(error => {
                console.error('Error loading activity:', error);
                document.getElementById('recentActivity').innerHTML = 
                    '<p class="text-center text-danger">Error al cargar actividad</p>';
            });
    }

    function approveTestimonial(id) {
        if (confirm('¿Aprobar este testimonio?')) {
            fetch('ajax/approve_testimonial.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPendingTestimonials();
                    showToast('Testimonio aprobado exitosamente', 'success');
                } else {
                    showToast('Error al aprobar testimonio', 'error');
                }
            });
        }
    }

    function rejectTestimonial(id) {
        const reason = prompt('Razón del rechazo (opcional):');
        if (reason !== null) {
            fetch('ajax/reject_testimonial.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, reason: reason})
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPendingTestimonials();
                    showToast('Testimonio rechazado', 'info');
                } else {
                    showToast('Error al rechazar testimonio', 'error');
                }
            });
        }
    }

    function refreshDashboard() {
        location.reload();
    }

    function refreshDashboardData() {
        loadPendingTestimonials();
        loadUpcomingPayouts();
        loadRecentActivity();
    }

    function exportReport() {
        const params = new URLSearchParams({
            type: 'dashboard',
            format: 'excel',
            date_from: new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0],
            date_to: new Date().toISOString().split('T')[0]
        });
        
        window.open(`reports/export.php?${params.toString()}`, '_blank');
    }

    // Acciones rápidas
    function processQuickPayout() {
        if (confirm('¿Procesar todos los pagos pendientes elegibles?')) {
            fetch('ajax/process_quick_payout.php', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${data.processed} pagos procesados exitosamente`, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showToast('Error al procesar pagos', 'error');
                    }
                });
        }
    }

    function approveAllTestimonials() {
        if (confirm('¿Aprobar automáticamente todos los testimonios de 5 estrellas?')) {
            fetch('ajax/approve_all_5star_testimonials.php', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${data.approved} testimonios aprobados`, 'success');
                        loadPendingTestimonials();
                    } else {
                        showToast('Error al aprobar testimonios', 'error');
                    }
                });
        }
    }

    function sendNotificationBlast() {
        alert('Función de notificación masiva - Por implementar en próxima versión');
    }

    function generateBackup() {
        if (confirm('¿Generar backup completo de la base de datos?')) {
            fetch('ajax/generate_backup.php', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('Backup generado exitosamente', 'success');
                        if (data.download_url) {
                            window.open(data.download_url, '_blank');
                        }
                    } else {
                        showToast('Error al generar backup', 'error');
                    }
                });
        }
    }

    function showToast(message, type) {
        const toastContainer = document.getElementById('toastContainer');
        
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'info'} border-0`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bsToast = new bootstrap.Toast(toast, {delay: 5000});
        bsToast.show();
        
        // Remover toast después de que se oculte
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }
    </script>
</body>
</html>