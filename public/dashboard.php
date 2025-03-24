<?php
// public/dashboard.php

// Inicia la sesión solo si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica que el usuario esté autenticado
require_once __DIR__ . '/../includes/auth.php';
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

// Conexión a la base de datos y controlador para listas de regalos
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';

$user = $_SESSION['user'];
$glc = new GiftListController($pdo);

// Obtener únicamente las listas creadas por el usuario actual
$stmt = $pdo->prepare("SELECT * FROM gift_lists WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$myLists = $stmt->fetchAll();

// Datos de ejemplo para interacciones, catálogo de productos y notificaciones
$otherInteractions = [
    "Carlos reservó un regalo en la lista 'Cumpleaños de María'.",
    "Laura dejó un comentario en la lista 'Boda de Juan y Ana'."
];

$catalogProducts = [
    [
        "id" => 101,
        "name" => "Cafetera Espresso",
        "description" => "Cafetera de alta calidad, ideal para amantes del café.",
        "price" => 149.99,
        "image" => "https://via.placeholder.com/150"
    ],
    [
        "id" => 102,
        "name" => "Juego de Cuchillos",
        "description" => "Juego de cuchillos de cocina de acero inoxidable.",
        "price" => 89.99,
        "image" => "https://via.placeholder.com/150"
    ]
];

$notifications = [
    "Juan reservó un regalo en tu lista 'Cumpleaños de María'.",
    "Ana compró un regalo en tu lista 'Boda de Juan y Ana'."
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Dashboard de Usuario - GiftList App</title>
  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <div class="container mt-5">
    <h1>Bienvenido, <?php echo htmlspecialchars($user['name']); ?></h1>

    <!-- Nav Tabs para organizar las funcionalidades -->
    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="lists-tab" data-bs-toggle="tab" data-bs-target="#lists" type="button" role="tab" aria-controls="lists" aria-selected="true">
          Mis Listas de Regalos
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="interactions-tab" data-bs-toggle="tab" data-bs-target="#interactions" type="button" role="tab" aria-controls="interactions" aria-selected="false">
          Interacción con Listas
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="false">
          Exploración de Productos
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications" aria-selected="false">
          Notificaciones
        </button>
      </li>
    </ul>

    <div class="tab-content mt-3" id="dashboardTabsContent">
      <!-- Tab: Mis Listas de Regalos -->
      <div class="tab-pane fade show active" id="lists" role="tabpanel" aria-labelledby="lists-tab">
        <h3>Mis Listas de Regalos</h3>
        <p>Aquí puedes crear, editar y eliminar tus listas, agregar productos desde el catálogo, compartir la lista y configurar su visibilidad (pública, privada, solo invitados).</p>
        <a href="create_giftlist.php" class="btn btn-primary mb-3">Crear Nueva Lista</a>
        <?php if (!empty($myLists)): ?>
          <?php foreach ($myLists as $list): ?>
            <div class="card mb-3">
              <div class="card-body">
                <h5 class="card-title"><?php echo htmlspecialchars($list['title']); ?></h5>
                <p class="card-text"><?php echo htmlspecialchars($list['description']); ?></p>
                <p class="card-text"><small class="text-muted">Creada: <?php echo $list['created_at']; ?></small></p>
                <a href="edit_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                <a href="delete_giftlist.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar lista?');">Eliminar</a>
                <a href="share_list.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-info">Compartir</a>
                <!-- Ejemplo de visibilidad: se muestra un badge; en un sistema real, se permitiría modificar -->
                <span class="badge bg-secondary">Pública</span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-info">No has creado ninguna lista.</div>
        <?php endif; ?>
      </div>

      <!-- Tab: Interacción con Listas -->
      <div class="tab-pane fade" id="interactions" role="tabpanel" aria-labelledby="interactions-tab">
        <h3>Interacción con Listas</h3>
        <p>Aquí puedes reservar regalos de listas de otros usuarios, dejar mensajes o comentarios y marcar listas como favoritas.</p>
        <?php if (!empty($otherInteractions["reservations"])): ?>
          <ul class="list-group">
            <?php foreach ($otherInteractions["reservations"] as $interaction): ?>
              <li class="list-group-item"><?php echo htmlspecialchars($interaction); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="alert alert-info">No hay interacciones registradas.</div>
        <?php endif; ?>
      </div>

      <!-- Tab: Exploración de Productos -->
      <div class="tab-pane fade" id="products" role="tabpanel" aria-labelledby="products-tab">
        <h3>Exploración de Productos</h3>
        <p>Busca productos por categorías, precios o palabras clave y consulta sus detalles (imágenes, descripción y precio).</p>
        <form class="d-flex mb-3">
          <input class="form-control me-2" type="search" placeholder="Buscar productos" aria-label="Search">
          <button class="btn btn-outline-success" type="submit">Buscar</button>
        </form>
        <?php if (!empty($catalogProducts)): ?>
          <?php foreach ($catalogProducts as $prod): ?>
            <div class="card mb-3" style="max-width: 540px;">
              <div class="row g-0">
                <div class="col-md-4">
                  <img src="<?php echo htmlspecialchars($prod['image']); ?>" class="img-fluid rounded-start" alt="<?php echo htmlspecialchars($prod['name']); ?>">
                </div>
                <div class="col-md-8">
                  <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($prod['name']); ?></h5>
                    <p class="card-text"><?php echo htmlspecialchars($prod['description']); ?></p>
                    <p class="card-text"><small class="text-muted">$<?php echo number_format($prod['price'],2); ?></small></p>
                    <a href="product_details.php?id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-info">Ver Detalles</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="alert alert-info">No hay productos disponibles.</div>
        <?php endif; ?>
      </div>

      <!-- Tab: Notificaciones -->
      <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
        <h3>Notificaciones</h3>
        <p>Recibe notificaciones cuando alguien reserve o compre un regalo de tus listas y configura tus preferencias de notificación.</p>
        <?php if (!empty($notifications)): ?>
          <ul class="list-group">
            <?php foreach ($notifications as $note): ?>
              <li class="list-group-item"><?php echo htmlspecialchars($note); ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="alert alert-info">No tienes notificaciones.</div>
        <?php endif; ?>
        <a href="notification_settings.php" class="btn btn-outline-primary mt-3">Configurar Notificaciones</a>
      </div>
    </div>

    <div class="mt-4">
      <a href="logout.php" class="btn btn-danger">Cerrar Sesión</a>
    </div>
  </div>

  <!-- Bootstrap Bundle JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
