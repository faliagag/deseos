<?php
// public/admin/dashboard.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios (ajusta las rutas según tu estructura)
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
require_once __DIR__ . '/../../includes/auth.php';  // Aquí está la función isAdmin()

// Verificar que el usuario actual es administrador
// Primero, asegurémonos de que la función isAdmin() existe
if (!function_exists('isAdmin')) {
    // Definición alternativa si no existe
    function isAdmin() {
        return isset($_SESSION['user']) && isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    }
}

// Ahora verificamos si es admin
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Continuar con el resto del código...
// ...

// Instanciar el controlador administrativo
$admin = new AdminController($pdo);

// Obtener datos para cada sección
$users        = $admin->listUsers();             // Gestión de Usuarios
$giftLists    = $admin->listGiftLists();           // Gestión de Listas
$transactions = $admin->listTransactions();        // Transacciones

// Datos de ejemplo para Productos (en un sistema real, se consultaría a un ProductController)
$products = [
    [
        "id"       => 101,
        "name"     => "Cafetera Espresso",
        "category" => "Electrodomésticos",
        "price"    => 149.99,
        "stock"    => 10
    ],
    [
        "id"       => 102,
        "name"     => "Juego de Cuchillos",
        "category" => "Cocina",
        "price"    => 89.99,
        "stock"    => 20
    ]
];

// Datos de ejemplo para Moderación de Contenido
$comments = [
    [
        "id"      => 1,
        "user"    => "Carlos",
        "content" => "Comentario inapropiado",
        "status"  => "pendiente"
    ],
    [
        "id"      => 2,
        "user"    => "Laura",
        "content" => "Comentario positivo",
        "status"  => "aprobado"
    ]
];

// Datos de ejemplo para Configuración del Sitio (placeholder)
$siteConfig = [
    "logo"            => "logo.png",
    "color_theme"     => "Azul",
    "payment_methods" => "Tarjeta, PayPal",
    "shipping_options"=> "Envío estándar, Express"
];

// Datos de ejemplo para Reportes y Estadísticas
$reportMetrics = [
    "ventas_totales"  => "$10,000",
    "usuarios_activos"=> 150,
    "listas_populares"=> 20
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Administrativo - GiftList App</title>
  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <div class="container mt-5">
    <h1>Panel de Administración</h1>
    <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user']['name']); ?></p>
    
    <!-- Nav Tabs para organizar funcionalidades -->
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
      <!-- Gestión de Usuarios -->
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true">
          Gestión de Usuarios
        </button>
      </li>
      <!-- Gestión de Productos -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="false">
          Gestión de Productos
        </button>
      </li>
      <!-- Gestión de Listas -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="lists-tab" data-bs-toggle="tab" data-bs-target="#lists" type="button" role="tab" aria-controls="lists" aria-selected="false">
          Gestión de Listas
        </button>
      </li>
      <!-- Listas Predeterminadas -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="preset-lists-tab" data-bs-toggle="tab" data-bs-target="#preset-lists" type="button" role="tab" aria-controls="preset-lists" aria-selected="false">
          Listas Predeterminadas
        </button>
      </li>
      <!-- Transacciones -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="transactions-tab" data-bs-toggle="tab" data-bs-target="#transactions" type="button" role="tab" aria-controls="transactions" aria-selected="false">
          Transacciones
        </button>
      </li>
      <!-- Moderación de Contenido -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="moderation-tab" data-bs-toggle="tab" data-bs-target="#moderation" type="button" role="tab" aria-controls="moderation" aria-selected="false">
          Moderación de Contenido
        </button>
      </li>
      <!-- Configuración del Sitio -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="configuration-tab" data-bs-toggle="tab" data-bs-target="#configuration" type="button" role="tab" aria-controls="configuration" aria-selected="false">
          Configuración del Sitio
        </button>
      </li>
      <!-- Reportes y Estadísticas -->
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab" aria-controls="reports" aria-selected="false">
          Reportes y Estadísticas
        </button>
      </li>
    </ul>

    <div class="tab-content mt-3" id="adminTabsContent">
      <!-- Gestión de Usuarios -->
      <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
        <h3>Gestión de Usuarios</h3>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Nombre</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($users)): ?>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?php echo $u['id']; ?></td>
                  <td><?php echo htmlspecialchars($u['name'] . " " . $u['lastname']); ?></td>
                  <td><?php echo htmlspecialchars($u['email']); ?></td>
                  <td><?php echo htmlspecialchars($u['role']); ?></td>
                  <td>
                    <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="delete_user.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar usuario?');">Eliminar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5"><div class="alert alert-info">No se encontraron usuarios.</div></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Gestión de Productos (Placeholder) -->
      <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
        <h3>Gestión de Productos</h3>
        <div class="alert alert-info">Sección en construcción: gestión de productos.</div>
      </div>
      
      <!-- Gestión de Listas -->
      <div class="tab-pane fade" id="lists" role="tabpanel" aria-labelledby="lists-tab">
        <h3>Gestión de Listas de Regalos</h3>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Título</th>
              <th>Descripción</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($giftLists)): ?>
              <?php foreach ($giftLists as $list): ?>
                <tr>
                  <td><?php echo $list['id']; ?></td>
                  <td><?php echo htmlspecialchars($list['title']); ?></td>
                  <td><?php echo htmlspecialchars($list['description']); ?></td>
                  <td>
                    <span class="badge bg-secondary">Pendiente</span>
                  </td>
                  <td>
                    <a href="edit_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                    <a href="delete_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar lista?');">Eliminar</a>
                    <a href="approve_list.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-success">Aprobar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5"><div class="alert alert-info">No se encontraron listas de regalos.</div></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Listas Predeterminadas -->
      <div class="tab-pane fade" id="preset-lists" role="tabpanel" aria-labelledby="preset-lists-tab">
        <h3>Listas de Productos Predeterminadas</h3>
        <p>Aquí puede cargar y gestionar listas de productos predeterminadas agrupadas por temario.</p>
        <div class="mb-3">
          <a href="preset_product_lists.php" class="btn btn-primary">Gestionar Listas Predeterminadas</a>
          <a href="upload_txt_list.php" class="btn btn-secondary">Cargar Archivo TXT</a>
        </div>
      </div>
      
      <!-- Transacciones -->
      <div class="tab-pane fade" id="transactions" role="tabpanel" aria-labelledby="transactions-tab">
        <h3>Transacciones</h3>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>ID</th>
              <th>Lista</th>
              <th>Usuario</th>
              <th>Monto</th>
              <th>Fecha</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($transactions)): ?>
              <?php foreach ($transactions as $tx): ?>
                <tr>
                  <td><?php echo $tx['id']; ?></td>
                  <td><?php echo $tx['gift_list_id']; ?></td>
                  <td><?php echo $tx['user_id'] ?? '-'; ?></td>
                  <td>$<?php echo number_format($tx['amount'],2); ?></td>
                  <td><?php echo $tx['created_at']; ?></td>
                  <td><?php echo htmlspecialchars($tx['status']); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="6"><div class="alert alert-info">No se encontraron transacciones.</div></td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      
      <!-- Moderación de Contenido -->
      <div class="tab-pane fade" id="moderation" role="tabpanel" aria-labelledby="moderation-tab">
        <h3>Moderación de Contenido</h3>
        <?php if (!empty($comments)): ?>
          <table class="table table-bordered">
            <thead>
              <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Contenido</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($comments as $c): ?>
                <tr>
                  <td><?php echo $c['id']; ?></td>
                  <td><?php echo htmlspecialchars($c['user']); ?></td>
                  <td><?php echo htmlspecialchars($c['content']); ?></td>
                  <td><?php echo htmlspecialchars($c['status']); ?></td>
                  <td>
                    <a href="approve_comment.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-success">Aprobar</a>
                    <a href="delete_comment.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este comentario?');">Eliminar</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="alert alert-info">No hay comentarios pendientes.</div>
        <?php endif; ?>
      </div>
      
      <!-- Configuración del Sitio -->
      <div class="tab-pane fade" id="configuration" role="tabpanel" aria-labelledby="configuration-tab">
        <h3>Configuración del Sitio</h3>
        <div class="alert alert-info">
          Sección de configuración en construcción.
        </div>
      </div>
      
      <!-- Reportes y Estadísticas -->
      <div class="tab-pane fade" id="reports" role="tabpanel" aria-labelledby="reports-tab">
        <h3>Reportes y Estadísticas</h3>
        <div class="row">
          <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
              <div class="card-body">
                <h5 class="card-title">Ventas Totales</h5>
                <p class="card-text"><?php echo $reportMetrics['ventas_totales']; ?></p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
              <div class="card-body">
                <h5 class="card-title">Usuarios Activos</h5>
                <p class="card-text"><?php echo $reportMetrics['usuarios_activos']; ?></p>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card text-white bg-warning mb-3">
              <div class="card-body">
                <h5 class="card-title">Listas Populares</h5>
                <p class="card-text"><?php echo $reportMetrics['listas_populares']; ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <a href="../logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>
  </div>

  <!-- Bootstrap Bundle JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
