<?php
// Controlador para la página create_giftlist.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/GiftListController.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth($pdo);
$auth->require('login.php');

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $glc = new GiftListController($pdo);
    // Crear la lista y obtener el ID de la lista recién creada
    $gift_list_id = $glc->create($_POST, $_SESSION["user"]["id"]);
    if ($gift_list_id) {
        // Verificar si se enviaron productos
        if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
            foreach ($_POST['product_name'] as $index => $productName) {
                // Solo procesar si el nombre del producto no está vacío
                if (!empty($productName)) {
                    $productData = [
                        'name' => $productName,
                        'description' => $_POST['product_description'][$index] ?? '',
                        'price' => $_POST['product_price'][$index] ?? 0,
                        'stock' => $_POST['product_stock'][$index] ?? 0
                    ];
                    $glc->addGift($gift_list_id, $productData);
                }
            }
        }
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Error al crear la lista de regalos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Lista de Regalos - GiftList App</title>
    <!-- Bootstrap CSS (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
      .product-group {
          border: 1px solid #ddd;
          padding: 15px;
          margin-bottom: 15px;
          border-radius: 5px;
      }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1>Crear Lista de Regalos</h1>
        <?php if (!empty($error)) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <!-- Datos de la lista -->
            <div class="mb-3">
                <label class="form-label">Título de la Lista:</label>
                <input type="text" name="title" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Descripción de la Lista:</label>
                <textarea name="description" class="form-control" required></textarea>
            </div>

            <!-- Sección para agregar productos -->
            <h3>Productos</h3>
            <div id="products_container">
                <div class="product-group">
                    <div class="mb-3">
                        <label class="form-label">Nombre del Producto:</label>
                        <input type="text" name="product_name[]" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción del Producto:</label>
                        <textarea name="product_description[]" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Precio (USD):</label>
                        <input type="number" step="0.01" name="product_price[]" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Stock:</label>
                        <input type="number" name="product_stock[]" class="form-control">
                    </div>
                </div>
            </div>
            <button type="button" id="add_product" class="btn btn-outline-secondary mb-3">Agregar otro producto</button>
            <br>
            <button type="submit" class="btn btn-primary">Crear Lista</button>
            <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
        </form>
    </div>

    <!-- Bootstrap Bundle JS (CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Script para clonar la sección de producto -->
    <script>
      document.getElementById("add_product").addEventListener("click", function(){
          var container = document.getElementById("products_container");
          // Clonar el primer grupo de producto
          var productGroup = container.querySelector(".product-group");
          var clone = productGroup.cloneNode(true);
          // Limpiar los valores del clone
          clone.querySelectorAll("input, textarea").forEach(function(input){
              input.value = "";
          });
          container.appendChild(clone);
      });
    </script>
</body>
</html>
