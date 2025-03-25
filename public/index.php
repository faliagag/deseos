<?php
// public/index.php

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/SearchController.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/helpers.php';

// Implementar búsqueda ampliada para incluir más campos
class EnhancedSearchController extends SearchController {
    public function searchAll($keyword) {
        try {
            // Búsqueda en múltiples campos relacionados con la lista y el usuario
            $sql = "
                SELECT gl.* 
                FROM gift_lists gl
                LEFT JOIN users u ON gl.user_id = u.id
                WHERE gl.title LIKE ? 
                OR gl.description LIKE ? 
                OR gl.event_type LIKE ?
                OR gl.beneficiary1 LIKE ?
                OR gl.beneficiary2 LIKE ?
                OR u.name LIKE ?
                OR u.lastname LIKE ?
                ORDER BY gl.created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $searchPattern = "%$keyword%";
            $stmt->execute([
                $searchPattern, 
                $searchPattern,
                $searchPattern,
                $searchPattern,
                $searchPattern,
                $searchPattern,
                $searchPattern
            ]);
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Error en búsqueda ampliada: " . $e->getMessage());
            return [];
        }
    }
}

// Buscar listas según el término de búsqueda
$keyword = $_GET["q"] ?? "";
$search = new EnhancedSearchController($pdo);

// Obtener listas de regalos con información detallada
if ($keyword != "") {
    // Usamos el controlador de búsqueda mejorado para buscar en todos los campos
    $basicLists = $search->searchAll($keyword);
    $giftLists = [];
    
    // Para cada lista básica, obtenemos la información completa
    foreach ($basicLists as $basicList) {
        $giftLists[] = enrichGiftListData($pdo, $basicList);
    }
} else {
    // Si no hay búsqueda, obtener solo las últimas 10 listas
    try {
        $stmt = $pdo->query("SELECT * FROM gift_lists ORDER BY created_at DESC LIMIT 10");
        $basicLists = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $giftLists = [];
        
        foreach ($basicLists as $basicList) {
            $giftLists[] = enrichGiftListData($pdo, $basicList);
        }
    } catch (Exception $e) {
        error_log("Error obteniendo las últimas listas: " . $e->getMessage());
        $giftLists = [];
    }
}

// Función para enriquecer los datos de la lista de regalos
function enrichGiftListData($pdo, $list) {
    // Obtener información del creador
    try {
        $stmt = $pdo->prepare("SELECT name, lastname FROM users WHERE id = ?");
        $stmt->execute([$list['user_id']]);
        $creator = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $list['creator_name'] = $creator ? $creator['name'] . ' ' . $creator['lastname'] : 'Usuario desconocido';
    } catch (Exception $e) {
        $list['creator_name'] = 'Usuario desconocido';
    }
    
    // Si la lista tiene beneficiarios, formatearlos apropiadamente
    $beneficiaries = [];
    if (!empty($list['beneficiary1'])) {
        $beneficiaries[] = $list['beneficiary1'];
    }
    if (!empty($list['beneficiary2'])) {
        $beneficiaries[] = $list['beneficiary2'];
    }
    
    $list['beneficiaries'] = !empty($beneficiaries) ? implode(' y ', $beneficiaries) : 'No especificado';
    
    // Formatear fecha de creación
    $list['formatted_date'] = format_date($list['created_at'], 'd/m/Y');
    
    // Si no hay tipo de evento, establecer uno predeterminado
    $list['event_type'] = !empty($list['event_type']) ? $list['event_type'] : 'Evento';
    
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
    <title>GiftList App - Listas de Regalos</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-section {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .search-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .highlighted {
            background-color: #ffff99;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        /* Table specific styles */
        .table {
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table thead th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .table tbody tr {
            transition: background-color 0.2s;
        }
        
        .table tbody tr:hover {
            background-color: rgba(0,123,255,0.05) !important;
        }
        
        .table td {
            vertical-align: middle;
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
                        <a class="nav-link active" href="index.php">Inicio</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if(isset($_SESSION["user"])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Mi Panel</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Iniciar Sesión</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Registrarse</a>
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
        <div class="hero-section text-center">
            <h1>Encuentra la lista de regalos perfecta</h1>
            <p class="lead">Busca por nombre, evento, creador o beneficiario y encuentra la lista que estás buscando.</p>
        </div>
        
        <!-- Sección de búsqueda -->
        <div class="search-section">
            <form method="get" action="index.php" class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control form-control-lg" 
                            placeholder="Buscar por título, evento, creador, beneficiario..." 
                            value="<?php echo htmlspecialchars($keyword); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-lg w-100">Buscar</button>
                </div>
            </form>
        </div>
        
        <!-- Resultados de la búsqueda o listado general -->
        <?php if (empty($keyword)): ?>
            <h2 class="mb-4">Últimas Listas de Regalos</h2>
        <?php else: ?>
            <h2 class="mb-4">Resultados para "<?php echo htmlspecialchars($keyword); ?>"</h2>
            <?php if (empty($giftLists)): ?>
                <div class="alert alert-info">
                    No se encontraron listas que coincidan con tu búsqueda. Intenta con otros términos.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if (!empty($giftLists)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead class="table-primary">
                        <tr>
                            <th>Título</th>
                            <th>Evento</th>
                            <th>Beneficiario(s)</th>
                            <th>Creador</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($giftLists as $list): ?>
                            <tr>
                                <td>
                                    <strong>
                                        <?php 
                                        if (!empty($keyword)) {
                                            echo preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlighted">$1</span>', htmlspecialchars($list["title"]));
                                        } else {
                                            echo htmlspecialchars($list["title"]);
                                        }
                                        ?>
                                    </strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php 
                                        if (mb_strlen($list['description']) > 80) {
                                            $desc = mb_substr($list['description'], 0, 80) . '...';
                                        } else {
                                            $desc = $list['description'];
                                        }
                                        
                                        if (!empty($keyword)) {
                                            echo preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlighted">$1</span>', htmlspecialchars($desc));
                                        } else {
                                            echo htmlspecialchars($desc);
                                        }
                                        ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php 
                                        if (!empty($keyword)) {
                                            echo preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlighted">$1</span>', htmlspecialchars($list["event_type"]));
                                        } else {
                                            echo htmlspecialchars($list["event_type"]);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($keyword)) {
                                        echo preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlighted">$1</span>', htmlspecialchars($list["beneficiaries"]));
                                    } else {
                                        echo htmlspecialchars($list["beneficiaries"]);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($keyword)) {
                                        echo preg_replace('/(' . preg_quote($keyword, '/') . ')/i', '<span class="highlighted">$1</span>', htmlspecialchars($list["creator_name"]));
                                    } else {
                                        echo htmlspecialchars($list["creator_name"]);
                                    }
                                    ?>
                                </td>
                                <td><?php echo $list["formatted_date"]; ?></td>
                                <td>
                                    <a href="giftlist.php?link=<?php echo urlencode($list["unique_link"]); ?>" class="btn btn-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver
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
                            Mostrando las 10 listas más recientes. 
                            <br>Usa el buscador para encontrar más listas.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Opcional: puedes agregar scripts personalizados aquí
    </script>
</body>
</html>