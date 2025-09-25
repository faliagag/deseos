<?php
/**
 * PÁGINA PRINCIPAL MEJORADA - VERSIÓN 2.1
 * 
 * Características nuevas estilo milistaderegalos.cl:
 * - Búsqueda priorizada por nombres/apellidos
 * - Sección de eventos destacados
 * - Testimonios dinámicos
 * - FAQs colapsables
 * - Calendario de pagos
 * - QR codes para listas
 */

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/helpers.php';

// Cargar configuración
$config = include __DIR__ . '/../config/config.php';

// Verificar si es una solicitud AJAX para búsqueda en tiempo real
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

/**
 * Controlador de Búsqueda Mejorado (estilo milistaderegalos.cl)
 */
class SearchController {
    protected $pdo;
    protected $config;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
    }
    
    /**
     * Búsqueda priorizada por nombres como milistaderegalos.cl
     */
    public function searchAll($keyword) {
        try {
            // Búsqueda con prioridad en nombres/apellidos
            $sql = "
                SELECT gl.*, u.name as creator_name, u.lastname as creator_lastname,
                    CASE 
                        WHEN (gl.beneficiary1 LIKE ? OR gl.beneficiary2 LIKE ? OR u.name LIKE ? OR u.lastname LIKE ?) THEN 1
                        WHEN gl.title LIKE ? THEN 2
                        WHEN gl.event_type LIKE ? THEN 3
                        ELSE 4
                    END as priority
                FROM gift_lists gl
                LEFT JOIN users u ON gl.user_id = u.id
                WHERE (gl.title LIKE ? 
                OR gl.description LIKE ? 
                OR gl.event_type LIKE ?
                OR gl.beneficiary1 LIKE ?
                OR gl.beneficiary2 LIKE ?
                OR u.name LIKE ?
                OR u.lastname LIKE ?)
                AND (gl.visibility = 'public' OR gl.visibility = 'link_only')
                AND gl.status = 'active'
                ORDER BY priority ASC, gl.created_at DESC
                LIMIT 50
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $searchPattern = "%$keyword%";
            
            // Ejecutar con prioridad en nombres
            $params = [
                $searchPattern, $searchPattern, $searchPattern, $searchPattern, // prioridad 1
                $searchPattern, // prioridad 2
                $searchPattern, // prioridad 3
                $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern, $searchPattern // resto
            ];
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error en búsqueda mejorada: " . $e->getMessage());
            return [];
        }
    }
    
    public function getRecentLists($limit = 5) {
        try {
            $sql = "
                SELECT gl.*, u.name as creator_name, u.lastname as creator_lastname
                FROM gift_lists gl
                LEFT JOIN users u ON gl.user_id = u.id
                WHERE gl.status = 'active'
                ORDER BY gl.created_at DESC 
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo listas recientes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * NUEVO: Obtener listas por tipo de evento
     */
    public function getListsByEvent($eventType, $limit = 10) {
        try {
            $sql = "
                SELECT gl.*, u.name as creator_name, u.lastname as creator_lastname,
                       COUNT(g.id) as gift_count
                FROM gift_lists gl
                LEFT JOIN users u ON gl.user_id = u.id
                LEFT JOIN gifts g ON gl.id = g.gift_list_id
                WHERE gl.event_type = ? AND gl.status = 'active'
                AND (gl.visibility = 'public' OR gl.visibility = 'link_only')
                GROUP BY gl.id
                ORDER BY gl.created_at DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$eventType, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo listas por evento: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * NUEVO: Controlador de Testimonios
 */
class TestimonialController {
    protected $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getApprovedTestimonials($limit = 6) {
        try {
            $sql = "
                SELECT t.*, u.name, gl.title as list_title, gl.event_type
                FROM testimonials t
                JOIN users u ON t.user_id = u.id
                LEFT JOIN gift_lists gl ON t.gift_list_id = gl.id
                WHERE t.status = 'approved' AND t.is_featured = 1
                ORDER BY t.created_at DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo testimonios: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * NUEVO: Controlador de FAQs
 */
class FAQController {
    protected $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getFAQsByCategory($category = null, $limit = 10) {
        try {
            $sql = "SELECT * FROM faqs WHERE status = 'active'";
            $params = [];
            
            if ($category) {
                $sql .= " AND category = ?";
                $params[] = $category;
            }
            
            $sql .= " ORDER BY order_index ASC, created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Error obteniendo FAQs: " . $e->getMessage());
            return [];
        }
    }
}

// Inicializar controladores
$search = new SearchController($pdo, $config);
$testimonialController = new TestimonialController($pdo);
$faqController = new FAQController($pdo);

// Búsqueda normal o AJAX
$keyword = $_GET["q"] ?? "";
$eventFilter = $_GET["event"] ?? "";

// Si es una solicitud AJAX, devolvemos solo los resultados como JSON
if ($isAjaxRequest) {
    header('Content-Type: application/json');
    
    if (!empty($keyword)) {
        $basicLists = $eventFilter ? 
            $search->getListsByEvent($eventFilter, 20) : 
            $search->searchAll($keyword);
        
        $giftLists = [];
        foreach ($basicLists as $basicList) {
            $giftLists[] = enrichGiftListData($pdo, $basicList);
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($giftLists),
            'results' => $giftLists
        ]);
    } else {
        echo json_encode(['success' => true, 'count' => 0, 'results' => []]);
    }
    exit;
}

// Obtener listas de regalos con información detallada para carga inicial
if ($keyword != "") {
    $basicLists = $eventFilter ? 
        $search->getListsByEvent($eventFilter, 20) : 
        $search->searchAll($keyword);
    
    $giftLists = [];
    foreach ($basicLists as $basicList) {
        $giftLists[] = enrichGiftListData($pdo, $basicList);
    }
} else {
    // Si no hay búsqueda, obtener las últimas 5 listas
    try {
        $basicLists = $search->getRecentLists(8);
        $giftLists = [];
        foreach ($basicLists as $basicList) {
            $giftLists[] = enrichGiftListData($pdo, $basicList);
        }
    } catch (Exception $e) {
        error_log("Error obteniendo las últimas listas: " . $e->getMessage());
        $giftLists = [];
    }
}

// Obtener datos adicionales para la home
$testimonials = $testimonialController->getApprovedTestimonials(6);
$faqs = $faqController->getFAQsByCategory('general', 5);

// Función para enriquecer los datos de la lista de regalos
function enrichGiftListData($pdo, $list) {
    // Información del creador
    $list['creator_name'] = ($list['creator_name'] ?? 'Usuario') . ' ' . ($list['creator_lastname'] ?? '');
    
    // Formatear beneficiarios
    $beneficiaries = [];
    if (!empty($list['beneficiary1'])) {
        $beneficiaries[] = $list['beneficiary1'];
    }
    if (!empty($list['beneficiary2'])) {
        $beneficiaries[] = $list['beneficiary2'];
    }
    
    $list['beneficiaries'] = !empty($beneficiaries) ? implode(' y ', $beneficiaries) : 'No especificado';
    
    // Formatear fecha
    $list['formatted_date'] = format_date($list['created_at'], 'd/m/Y');
    
    // Evento predeterminado
    $list['event_type'] = !empty($list['event_type']) ? $list['event_type'] : 'Evento';
    
    // Contar regalos en la lista
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as gift_count FROM gifts WHERE gift_list_id = ?");
        $stmt->execute([$list['id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $list['gift_count'] = $result['gift_count'] ?? 0;
    } catch (Exception $e) {
        $list['gift_count'] = 0;
    }
    
    return $list;
}

// Obtener mensaje flash si existe
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['application']['name']; ?> - Transforma tus regalos en dinero</title>
    <meta name="description" content="Crea tu lista de regalos y recibe dinero cada 2 miércoles. Sin costos para ti, tus invitados pagan solo un 10% extra.">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 60px 40px;
            margin-bottom: 40px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .hero-section h1 {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .hero-section .lead {
            font-size: 1.3rem;
            margin-bottom: 30px;
        }
        
        .event-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .faq-item {
            border: none;
            margin-bottom: 10px;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .calendar-month {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .process-step {
            text-align: center;
            padding: 30px 20px;
        }
        
        .process-step .step-number {
            background: #667eea;
            color: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 20px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
        }
        
        .search-filters {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <!-- Navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-gift"></i> <?php echo $config['application']['name']; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#eventos">Eventos</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#testimonios">Testimonios</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#faqs">Preguntas</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION["user"])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-speedometer2"></i> Mi Panel
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_giftlist.php">
                                <i class="bi bi-plus-circle"></i> Crear Lista
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-outline-light ms-2 px-3" href="register.php">Registrarse</a>
                        </li>
                    <?php endif; ?>
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
        
        <!-- Sección Hero -->
        <div class="hero-section">
            <h1>Realiza una búsqueda</h1>
            <p class="lead">ingresando un nombre</p>
            
            <!-- Buscador principal -->
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-0">
                            <i class="bi bi-search text-primary"></i>
                        </span>
                        <input type="text" id="search-input" class="form-control border-0" 
                            placeholder="Buscar por nombre, apellido, evento..." 
                            value="<?php echo htmlspecialchars($keyword); ?>">
                        <div id="search-loader" class="search-loader">
                            <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                        </div>
                    </div>
                    <small class="text-light opacity-75 mt-2 d-block">
                        Encuentra la lista perfecta escribiendo el nombre del festejado
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Filtros de búsqueda -->
        <div class="search-filters" id="search-filters" style="display: none;">
            <div class="row g-3">
                <div class="col-md-6">
                    <select class="form-select" id="event-filter">
                        <option value="">Todos los eventos</option>
                        <?php foreach ($config['events']['types'] as $key => $event): ?>
                            <option value="<?php echo $key; ?>" <?php echo $eventFilter === $key ? 'selected' : ''; ?>>
                                <?php echo $event['icon'] . ' ' . $event['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="button" id="clear-filters" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Limpiar filtros
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Resultados de búsqueda -->
        <div id="results-section">
            <div id="results-header">
                <?php if (empty($keyword)): ?>
                    <h2 class="mb-4">Últimas Listas de Regalos</h2>
                    <p class="text-muted">Explora las listas más recientes o usa el buscador para encontrar una lista específica</p>
                <?php else: ?>
                    <h2 class="mb-4">Resultados para "<?php echo htmlspecialchars($keyword); ?>"</h2>
                <?php endif; ?>
            </div>
            
            <!-- Mensaje de sin resultados -->
            <div id="no-results-message" class="text-center py-5" style="display: none;">
                <i class="bi bi-search" style="font-size: 4rem; color: #ccc;"></i>
                <h3 class="mt-3 text-muted">No se encontraron resultados</h3>
                <p class="text-muted">Intenta con otros términos de búsqueda o explora nuestros eventos</p>
            </div>
            
            <!-- Tabla de resultados -->
            <div id="search-results">
                <?php if (!empty($giftLists)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Lista</th>
                                    <th>Evento</th>
                                    <th>Beneficiario(s)</th>
                                    <th>Creador</th>
                                    <th>Regalos</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="results-tbody">
                                <?php foreach ($giftLists as $list): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($list["title"]); ?></strong>
                                            <?php if (!empty($list['description'])): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars(mb_substr($list['description'], 0, 60)) . (mb_strlen($list['description']) > 60 ? '...' : ''); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $eventType = $list["event_type"];
                                            $eventConfig = null;
                                            foreach ($config['events']['types'] as $key => $event) {
                                                if ($key === $eventType || $event['name'] === $eventType) {
                                                    $eventConfig = $event;
                                                    break;
                                                }
                                            }
                                            ?>
                                            <span class="badge" style="background-color: <?php echo $eventConfig['color'] ?? '#6c757d'; ?>">
                                                <?php echo ($eventConfig['icon'] ?? '') . ' ' . htmlspecialchars($eventType); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($list["beneficiaries"]); ?></td>
                                        <td><?php echo htmlspecialchars($list["creator_name"]); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $list['gift_count'] ?? 0; ?> regalos</span>
                                        </td>
                                        <td><?php echo $list["formatted_date"]; ?></td>
                                        <td>
                                            <a href="giftlist.php?link=<?php echo urlencode($list["unique_link"]); ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="bi bi-eye"></i> Ver Lista
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <?php if (empty($keyword) && !empty($giftLists)): ?>
                            <div class="text-center mt-3">
                                <p class="text-muted">
                                    <i class="bi bi-info-circle"></i> 
                                    Mostrando las últimas listas creadas.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Sección: ¿Cómo funciona? -->
    <div class="bg-light py-5 mt-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>¿Cómo creas tu lista de regalos?</h2>
                <p class="lead">Solo 4 pasos simples para recibir dinero en lugar de regalos</p>
            </div>
            
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">1</div>
                        <h5>Regístrate</h5>
                        <p class="text-muted">Ingresa tus datos y crea tu cuenta</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">2</div>
                        <h5>Crea</h5>
                        <p class="text-muted">Elige un evento y crea tu lista</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">3</div>
                        <h5>Invita</h5>
                        <p class="text-muted">Invita a todos a que se sumen y te hagan regalos</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="process-step">
                        <div class="step-number">4</div>
                        <h5>Recibe</h5>
                        <p class="text-muted">Recibe cada 2 miércoles tu depósito</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <div class="stats-card d-inline-block">
                    <h3>¿Por qué nosotros?</h3>
                    <p class="mb-0">Porque con nosotros tus regalos se transforman en dinero para que puedas comprarte lo que realmente quieres y necesitas.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Sección: Nuestros Eventos -->
    <div class="container py-5" id="eventos">
        <div class="text-center mb-5">
            <h2>Nuestros Eventos</h2>
            <p class="lead">Encuentra el tipo de evento perfecto para tu celebración</p>
        </div>
        
        <div class="row g-4">
            <?php foreach ($config['events']['types'] as $eventKey => $event): ?>
                <div class="col-md-3 col-sm-6">
                    <div class="card event-card h-100" style="border-left: 4px solid <?php echo $event['color']; ?>">
                        <div class="card-body text-center">
                            <div class="display-4 mb-3"><?php echo $event['icon']; ?></div>
                            <h5 class="card-title"><?php echo $event['name']; ?></h5>
                            <a href="?event=<?php echo $eventKey; ?>" class="btn btn-outline-primary btn-sm">
                                Ver Listas <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Sección: Testimonios -->
    <?php if (!empty($testimonials)): ?>
    <div class="bg-light py-5" id="testimonios">
        <div class="container">
            <div class="text-center mb-5">
                <h2>¿Qué dicen de nosotros?</h2>
                <p class="lead">Testimonios que tocan el corazón.</p>
            </div>
            
            <div class="row g-4">
                <?php foreach ($testimonials as $testimonial): ?>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <div class="mb-3">
                                <?php for ($i = 0; $i < 5; $i++): ?>
                                    <i class="bi bi-star-fill text-warning"></i>
                                <?php endfor; ?>
                            </div>
                            <p class="fst-italic mb-3">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                            <div class="d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($testimonial['name']); ?></strong>
                                    <?php if (!empty($testimonial['event_type'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($testimonial['event_type']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sección: Preguntas Frecuentes -->
    <?php if (!empty($faqs)): ?>
    <div class="container py-5" id="faqs">
        <div class="text-center mb-5">
            <h2>¿Tienes dudas?</h2>
            <p class="lead">Encuentra respuestas a las preguntas más frecuentes</p>
        </div>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="accordion" id="faqAccordion">
                    <?php foreach ($faqs as $index => $faq): ?>
                        <div class="accordion-item faq-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" 
                                        type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#faq<?php echo $index; ?>">
                                    <?php echo htmlspecialchars($faq['question']); ?>
                                </button>
                            </h2>
                            <div id="faq<?php echo $index; ?>" 
                                 class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" 
                                 data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <?php echo nl2br(htmlspecialchars($faq['answer'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sección: Calendario de Pagos -->
    <div class="bg-primary text-white py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Calendario de Pagos <?php echo date('Y'); ?></h2>
                <p class="lead">Los días miércoles, cada dos semanas, depositamos en tu cuenta el dinero acumulado</p>
            </div>
            
            <div class="calendar-grid">
                <?php foreach ($config['payouts']['calendar_2025'] as $month => $dates): ?>
                    <div class="calendar-month">
                        <h6 class="fw-bold text-primary mb-2"><?php echo $month; ?></h6>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($dates as $date): ?>
                                <li class="mb-1">
                                    <i class="bi bi-calendar-event"></i> <?php echo $date; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <small class="opacity-75">
                    <i class="bi bi-info-circle"></i>
                    Los depósitos incluyen todas las transacciones hasta las 14:00 horas del lunes anterior.
                </small>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo $config['application']['name']; ?></h5>
                    <p class="mb-1">Transforma tus regalos en dinero</p>
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> Todos los derechos reservados.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1">Desarrollado con ❤️ para hacer realidad los sueños</p>
                    <p class="mb-0">Versión <?php echo $config['application']['version']; ?></p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script mejorado para búsqueda en tiempo real -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const searchLoader = document.getElementById('search-loader');
        const searchResults = document.getElementById('search-results');
        const resultsHeader = document.getElementById('results-header');
        const noResultsMessage = document.getElementById('no-results-message');
        const searchFilters = document.getElementById('search-filters');
        const eventFilter = document.getElementById('event-filter');
        const clearFilters = document.getElementById('clear-filters');
        
        let searchTimeout = null;
        let currentSearchTerm = searchInput.value;
        
        // Mostrar filtros cuando se empiece a escribir
        searchInput.addEventListener('focus', function() {
            if (this.value.length > 0) {
                searchFilters.style.display = 'block';
            }
        });
        
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            if (searchTerm.length > 0) {
                searchFilters.style.display = 'block';
            } else {
                searchFilters.style.display = 'none';
            }
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            if (searchTerm === currentSearchTerm) {
                return;
            }
            
            if (!searchTerm) {
                if (currentSearchTerm) {
                    window.location.href = 'index.php';
                }
                return;
            }
            
            searchTimeout = setTimeout(() => {
                currentSearchTerm = searchTerm;
                performSearch(searchTerm);
            }, 300);
        });
        
        eventFilter.addEventListener('change', function() {
            const searchTerm = searchInput.value.trim();
            if (searchTerm) {
                performSearch(searchTerm, this.value);
            }
        });
        
        clearFilters.addEventListener('click', function() {
            searchInput.value = '';
            eventFilter.value = '';
            window.location.href = 'index.php';
        });
        
        function performSearch(searchTerm, eventType = '') {
            searchLoader.style.display = 'inline-block';
            searchResults.style.opacity = '0.5';
            
            let url = `index.php?q=${encodeURIComponent(searchTerm)}`;
            if (eventType) {
                url += `&event=${encodeURIComponent(eventType)}`;
            }
            url += '&ajax=1';
            
            const xhr = new XMLHttpRequest();
            xhr.open('GET', url, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        updateResults(response, searchTerm);
                    } catch (e) {
                        console.error('Error parsing response:', e);
                    }
                }
                searchLoader.style.display = 'none';
                searchResults.style.opacity = '1';
            };
            
            xhr.onerror = function() {
                searchLoader.style.display = 'none';
                searchResults.style.opacity = '1';
            };
            
            xhr.send();
        }
        
        function updateResults(response, searchTerm) {
            resultsHeader.innerHTML = `<h2 class="mb-4">Resultados para "${searchTerm}"</h2>`;
            
            if (response.count > 0) {
                noResultsMessage.style.display = 'none';
                
                let tableHTML = `
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-primary">
                                <tr>
                                    <th>Lista</th>
                                    <th>Evento</th>
                                    <th>Beneficiario(s)</th>
                                    <th>Creador</th>
                                    <th>Regalos</th>
                                    <th>Fecha</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>`;
                
                response.results.forEach(list => {
                    const highlightedTitle = list.title.replace(
                        new RegExp('(' + searchTerm + ')', 'gi'), 
                        '<mark>$1</mark>'
                    );
                    
                    tableHTML += `
                        <tr>
                            <td>
                                <strong>${highlightedTitle}</strong>
                                ${list.description ? `<br><small class="text-muted">${list.description.substring(0, 60)}${list.description.length > 60 ? '...' : ''}</small>` : ''}
                            </td>
                            <td>
                                <span class="badge bg-info">${list.event_type}</span>
                            </td>
                            <td>${list.beneficiaries}</td>
                            <td>${list.creator_name}</td>
                            <td>
                                <span class="badge bg-info">${list.gift_count || 0} regalos</span>
                            </td>
                            <td>${list.formatted_date}</td>
                            <td>
                                <a href="giftlist.php?link=${encodeURIComponent(list.unique_link)}" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye"></i> Ver Lista
                                </a>
                            </td>
                        </tr>`;
                });
                
                tableHTML += '</tbody></table></div>';
                searchResults.innerHTML = tableHTML;
            } else {
                searchResults.innerHTML = '';
                noResultsMessage.style.display = 'block';
            }
        }
    });
    </script>
</body>
</html>