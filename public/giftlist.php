<?php
// public/giftlist.php?link=xxxxx

// Activar reporte de errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../controllers/GiftListController.php";

// Capturar el parámetro link de la URL
$unique_link = $_GET["link"] ?? "";

// Validar que se proporcionó un link
if (empty($unique_link)) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>No se proporcionó un enlace de lista.</div></div>";
    exit;
}

// Imprimir información de depuración
error_log("Buscando lista con enlace: $unique_link");

// Crear instancia del controlador
$controller = new GiftListController($pdo);

// Intentar obtener la lista
$giftList = $controller->show($unique_link);

// Verificar si se encontró la lista
if (!$giftList) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
    // Imprimir información de depuración
    error_log("No se encontró la lista con enlace: $unique_link");
    exit;
}

// Imprimir información de depuración
error_log("Lista encontrada: " . print_r($giftList, true));
error_log("Regalos encontrados: " . (isset($giftList["gifts"]) ? count($giftList["gifts"]) : "ninguno"));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($giftList["title"]); ?> - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><?php echo htmlspecialchars($giftList["title"]); ?></h2>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h4>Descripción:</h4>
                            <p class="lead"><?php echo htmlspecialchars($giftList["description"]); ?></p>
                        </div>
                        
                        <h4 class="mt-4 mb-3">Lista de Regalos:</h4>
                        
                        <?php if (isset($giftList["gifts"]) && !empty($giftList["gifts"])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Regalo</th>
                                            <th>Precio</th>
                                            <th>Disponible</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($giftList["gifts"] as $gift): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($gift["name"]); ?></strong>
                                                    <?php if (!empty($gift["description"])): ?>
                                                        <p class="text-muted small mb-0"><?php echo htmlspecialchars($gift["description"]); ?></p>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($gift["price"], 2); ?></td>
                                                <td>
                                                    <?php
                                                    $availableStock = $gift["stock"] - $gift["sold"];
                                                    if ($availableStock > 0) {
                                                        echo "<span class='badge bg-success'>$availableStock disponibles</span>";
                                                    } else {
                                                        echo "<span class='badge bg-danger'>Agotado</span>";
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($availableStock > 0): ?>
                                                    <form method="post" action="process_payment.php" class="d-flex align-items-center">
                                                        <input type="hidden" name="gift_list_id" value="<?php echo $giftList["id"]; ?>">
                                                        <input type="hidden" name="gift_id" value="<?php echo $gift["id"]; ?>">
                                                        <div class="input-group input-group-sm me-2" style="max-width: 120px;">
                                                            <span class="input-group-text">$</span>
                                                            <input type="number" name="amount" class="form-control" placeholder="Monto" required>
                                                        </div>
                                                        <div class="input-group input-group-sm me-2" style="max-width: 100px;">
                                                            <input type="number" name="quantity" class="form-control" placeholder="Cant." min="1" max="<?php echo $availableStock; ?>" value="1" required>
                                                        </div>
                                                        <input type="hidden" name="currency" value="usd">
                                                        <button type="submit" class="btn btn-sm btn-success">Comprar</button>
                                                    </form>
                                                    <?php else: ?>
                                                        <button disabled class="btn btn-sm btn-secondary">Agotado</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">Esta lista no tiene regalos disponibles.</div>
                        <?php endif; ?>
                        
                        <div class="mt-4 text-center">
                            <a href="index.php" class="btn btn-secondary">Volver al inicio</a>
                        </div>
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
</body>
</html>