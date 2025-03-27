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
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/helpers.php';

// Verificar si es una solicitud AJAX para búsqueda en tiempo real
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Implementamos la clase para búsqueda
class SearchController {
    protected $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function searchAll($keyword) {
        try {
            // Búsqueda en múltiples campos relacionados con la lista y el usuario
            $sql = "
                SELECT gl.* 
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
                ORDER BY gl.created_at DESC
                LIMIT 20
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $searchPattern = "%$keyword%";
            $params = array_fill(0, 7, $searchPattern);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error en búsqueda ampliada: " . $e->getMessage());
            return [];
        }
    }
    
    public function getRecentLists($limit = 5) {
        try {
            // Consulta para obtener las últimas listas, independientemente de la visibilidad
            $sql = "
                SELECT * FROM gift_lists 
                ORDER BY created_at DESC 
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
}

// Búsqueda normal o AJAX
$keyword = $_GET["q"] ?? "";
$search = new SearchController($pdo);

// Si es una solicitud AJAX, devolvemos solo los resultados como JSON
if ($isAjaxRequest) {
    header('Content-Type: application/json');
    
    if (!empty($keyword)) {
        $basicLists = $search->searchAll($keyword);
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
        // Si no hay keyword, devolver un array vacío
        echo json_encode([
            'success' => true,
            'count' => 0,
            'results' => []
        ]);
    }
    exit;
}

// Obtener listas de regalos con información detallada para carga inicial
if ($keyword != "") {
    // Usamos el controlador de búsqueda para buscar en todos los campos
    $basicLists = $search->searchAll($keyword);
    $giftLists = [];
    
    // Para cada lista básica, obtenemos la información completa
    foreach ($basicLists as $basicList) {
        $giftLists[] = enrichGiftListData($pdo, $basicList);
    }
} else {
    // Si no hay búsqueda, obtener las últimas 5 listas
    try {
        // Obtener las listas más recientes
        $basicLists = $search->getRecentLists(5);
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
        
        /* Estilos para el indicador de carga */
        .search-loader {
            display: none;
            margin-left: 10px;
        }
        
        .search-loader.active {
            display: inline-block;
        }
        
        /* Transición suave para resultados */
        #search-results {
            transition: opacity 0.3s ease;
        }
        
        #search-results.loading {
            opacity: 0.5;
        }
        
        /* Mensaje cuando no hay resultados */
        .no-results {
            display: none;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .no-results.active {
            display: block;
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
            <div class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="search-input" class="form-control form-control-lg" 
                            placeholder="Buscar por título, evento, creador, beneficiario..." 
                            value="<?php echo htmlspecialchars($keyword); ?>">
                        <div id="search-loader" class="search-loader">
                            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                        </div>
                    </div>
                    <small class="text-muted mt-1">Los resultados se actualizarán automáticamente mientras escribes</small>
                </div>
                <div class="col-md-2">
                    <button type="button" id="clear-search" class="btn btn-outline-secondary btn-lg w-100">Limpiar</button>
                </div>
            </div>
        </div>
        
        <!-- Resultados de la búsqueda o listado general -->
        <div id="results-header">
            <?php if (empty($keyword)): ?>
                <h2 class="mb-4">Últimas Listas de Regalos</h2>
            <?php else: ?>
                <h2 class="mb-4">Resultados para "<?php echo htmlspecialchars($keyword); ?>"</h2>
            <?php endif; ?>
        </div>
        
        <!-- Contenedor para mensajes de no resultados -->
        <div id="no-results-message" class="no-results">
            <i class="bi bi-search" style="font-size: 3rem; color: #ccc;"></i>
            <h3 class="mt-3">No se encontraron resultados</h3>
            <p class="text-muted">Intenta con otros términos de búsqueda</p>
        </div>
        
        <!-- Tabla de resultados -->
        <div id="search-results">
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
                        <tbody id="results-tbody">
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
                                Mostrando las últimas listas creadas.
                                <br>Usa el buscador para encontrar más listas.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        document.getElementById('no-results-message').classList.add('active');
                    });
                </script>
            <?php endif; ?>
        </div>
    </div>
    
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> GiftList App. Todos los derechos reservados.</p>
        </div>
    </footer>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script para búsqueda en tiempo real -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('search-input');
        const searchLoader = document.getElementById('search-loader');
        const searchResults = document.getElementById('search-results');
        const resultsHeader = document.getElementById('results-header');
        const noResultsMessage = document.getElementById('no-results-message');
        const clearSearchBtn = document.getElementById('clear-search');
        
        // Variables para controlar la búsqueda
        let searchTimeout = null;
        let currentSearchTerm = searchInput.value;
        
        // Función para realizar la búsqueda
        function performSearch(searchTerm) {
            // Si la búsqueda está vacía, mostrar resultados iniciales
            if (!searchTerm.trim()) {
                window.location.href = 'index.php';
                return;
            }
            
            // Activar indicador de carga
            searchLoader.classList.add('active');
            searchResults.classList.add('loading');
            
            // Realizar solicitud AJAX
            const xhr = new XMLHttpRequest();
            xhr.open('GET', `index.php?q=${encodeURIComponent(searchTerm)}&ajax=1`, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        // Actualizar el encabezado de resultados
                        resultsHeader.innerHTML = `<h2 class="mb-4">Resultados para "${searchTerm}"</h2>`;
                        
                        // Actualizar resultados
                        if (response.count > 0) {
                            // Ocultar mensaje de no resultados
                            noResultsMessage.classList.remove('active');
                            
                            // Construir la tabla de resultados
                            let resultsHTML = `
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
                                    <tbody>`;
                            
                            // Añadir filas para cada resultado
                            response.results.forEach(list => {
                                // Preparar la descripción truncada
                                let desc = list.description;
                                if (desc.length > 80) {
                                    desc = desc.substring(0, 80) + '...';
                                }
                                
                                // Resaltar coincidencias en el título
                                const highlightedTitle = list.title.replace(
                                    new RegExp('(' + searchTerm + ')', 'gi'), 
                                    '<span class="highlighted">$1</span>'
                                );
                                
                                // Resaltar coincidencias en la descripción
                                const highlightedDesc = desc.replace(
                                    new RegExp('(' + searchTerm + ')', 'gi'), 
                                    '<span class="highlighted">$1</span>'
                                );
                                
                                // Resaltar coincidencias en el tipo de evento
                                const highlightedEvent = list.event_type.replace(
                                    new RegExp('(' + searchTerm + ')', 'gi'), 
                                    '<span class="highlighted">$1</span>'
                                );
                                
                                // Resaltar coincidencias en los beneficiarios
                                const highlightedBeneficiaries = list.beneficiaries.replace(
                                    new RegExp('(' + searchTerm + ')', 'gi'), 
                                    '<span class="highlighted">$1</span>'
                                );
                                
                                // Resaltar coincidencias en el creador
                                const highlightedCreator = list.creator_name.replace(
                                    new RegExp('(' + searchTerm + ')', 'gi'), 
                                    '<span class="highlighted">$1</span>'
                                );
                                
                                resultsHTML += `
                                <tr>
                                    <td>
                                        <strong>${highlightedTitle}</strong>
                                        <br>
                                        <small class="text-muted">${highlightedDesc}</small>
                                    </td>
                                    <td>
                                        <span class="badge bg-info">${highlightedEvent}</span>
                                    </td>
                                    <td>${highlightedBeneficiaries}</td>
                                    <td>${highlightedCreator}</td>
                                    <td>${list.formatted_date}</td>
                                    <td>
                                        <a href="giftlist.php?link=${encodeURIComponent(list.unique_link)}" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye"></i> Ver
                                        </a>
                                    </td>
                                </tr>`;
                            });
                            
                            resultsHTML += `</tbody>
                                </table>
                            </div>`;
                            
                            searchResults.innerHTML = resultsHTML;
                        } else {
                            // Mostrar mensaje de no resultados
                            searchResults.innerHTML = '';
                            noResultsMessage.classList.add('active');
                        }
                    } catch (e) {
                        console.error('Error al procesar la respuesta:', e);
                    }
                }
                
                // Desactivar indicador de carga
                searchLoader.classList.remove('active');
                searchResults.classList.remove('loading');
            };
            
            xhr.onerror = function() {
                console.error('Error en la solicitud AJAX');
                searchLoader.classList.remove('active');
                searchResults.classList.remove('loading');
            };
            
            xhr.send();
        }
        
        // Event listener para el campo de búsqueda
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            // Cancelar la búsqueda anterior si existe
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            // Si la búsqueda es igual a la actual, no hacer nada
            if (searchTerm === currentSearchTerm) {
                return;
            }
            
            // Si la búsqueda está vacía, mostrar resultados iniciales
            if (!searchTerm) {
                if (currentSearchTerm) {
                    // Redirigir a la página principal
                    window.location.href = 'index.php';
                }
                return;
            }
            
            // Esperar 300ms antes de realizar la búsqueda
            searchTimeout = setTimeout(() => {
                currentSearchTerm = searchTerm;
                performSearch(searchTerm);
            }, 300);
        });
        
        // Event listener para el botón de limpiar búsqueda
        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            window.location.href = 'index.php';
        });
        
        // Si hay un término de búsqueda en la URL, actualizarlo en el campo
        const urlParams = new URLSearchParams(window.location.search);
        const queryParam = urlParams.get('q');
        if (queryParam) {
            searchInput.value = queryParam;
            currentSearchTerm = queryParam;
        }
    });
    </script>
</body>
</html>