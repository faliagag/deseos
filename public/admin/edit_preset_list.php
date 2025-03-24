<?php
// public/admin/edit_preset_list.php

// Inicia la sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Obtener el ID de la lista predeterminada a editar desde la URL
$id = $_GET['id'] ?? '';
if (empty($id)) {
    echo "<div class='alert alert-warning'>No se especificó la lista a editar.</div>";
    exit;
}

// Consultar la información actual de la lista predeterminada
$stmt = $pdo->prepare("SELECT * FROM preset_product_lists WHERE id = ?");
$stmt->execute([$id]);
$presetList = $stmt->fetch();

if (!$presetList) {
    echo "<div class='alert alert-warning'>Lista predeterminada no encontrada.</div>";
    exit;
}

// Consultar los productos asociados a esta lista
$stmtProducts = $pdo->prepare("SELECT * FROM preset_products WHERE preset_list_id = ? ORDER BY id ASC");
$stmtProducts->execute([$id]);
$products = $stmtProducts->fetchAll();

$error = "";
$success = "";

// Procesar el formulario al enviarlo
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Actualizar la lista predeterminada: tema y descripción
    $theme = trim($_POST['theme'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!empty($theme)) {
        $stmtUpdate = $pdo->prepare("UPDATE preset_product_lists SET theme = ?, description = ? WHERE id = ?");
        if ($stmtUpdate->execute([$theme, $description, $id])) {
            $success = "Lista actualizada exitosamente.";
        } else {
            $error = "Error al actualizar la lista.";
        }
    } else {
        $error = "El tema es obligatorio.";
    }
    
    // Procesar productos
    // Los arrays enviados son: product_id[], product_name[], price[], stock[]
    $product_ids   = $_POST['product_id'] ?? [];
    $product_names = $_POST['product_name'] ?? [];
    $prices        = $_POST['price'] ?? [];
    $stocks        = $_POST['stock'] ?? [];
    
    // Para cada producto, se actualizará si tiene ID; si no, se insertará uno nuevo.
    for ($i = 0; $i < count($product_names); $i++) {
        $pName = trim($product_names[$i]);
        $pPrice = floatval($prices[$i]);
        $pStock = intval($stocks[$i]);
        $pid = trim($product_ids[$i] ?? '');
        
        if (!empty($pName)) {
            if (!empty($pid)) {
                // Actualizar producto existente
                $stmtProd = $pdo->prepare("UPDATE preset_products SET name = ?, price = ?, stock = ? WHERE id = ?");
                $stmtProd->execute([$pName, $pPrice, $pStock, $pid]);
            } else {
                // Insertar nuevo producto
                $stmtProd = $pdo->prepare("INSERT INTO preset_products (preset_list_id, name, price, stock) VALUES (?, ?, ?, ?)");
                $stmtProd->execute([$id, $pName, $pPrice, $pStock]);
            }
        }
    }
    
    // Reconsultar la lista y productos actualizados
    $stmt = $pdo->prepare("SELECT * FROM preset_product_lists WHERE id = ?");
    $stmt->execute([$id]);
    $presetList = $stmt->fetch();
    
    $stmtProducts = $pdo->prepare("SELECT * FROM preset_products WHERE preset_list_id = ? ORDER BY id ASC");
    $stmtProducts->execute([$id]);
    $products = $stmtProducts->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Lista Predeterminada - Administración</title>
  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .product-group {
        border: 1px solid #ddd;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 5px;
    }
    .remove-product {
        margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="container mt-5">
    <h1>Editar Lista Predeterminada</h1>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="post" action="">
      <!-- Datos de la lista -->
      <div class="mb-3">
        <label class="form-label">Tema:</label>
        <input type="text" name="theme" class="form-control" value="<?php echo htmlspecialchars($presetList['theme']); ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción:</label>
        <textarea name="description" class="form-control"><?php echo htmlspecialchars($presetList['description']); ?></textarea>
      </div>
      
      <!-- Sección para listar y editar productos -->
      <h3>Productos</h3>
      <div id="products_container">
        <?php if (!empty($products)): ?>
          <?php foreach ($products as $product): ?>
            <div class="product-group">
              <input type="hidden" name="product_id[]" value="<?php echo $product['id']; ?>">
              <div class="mb-3">
                <label class="form-label">Nombre del Producto:</label>
                <input type="text" name="product_name[]" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Precio (CLP):</label>
                <input type="number" name="price[]" class="form-control" step="1" min="0" value="<?php echo $product['price']; ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Cantidad:</label>
                <input type="number" name="stock[]" class="form-control" min="0" value="<?php echo $product['stock']; ?>" required>
              </div>
              <button type="button" class="btn btn-sm btn-danger remove-product">Eliminar Producto</button>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="product-group">
            <input type="hidden" name="product_id[]" value="">
            <div class="mb-3">
              <label class="form-label">Nombre del Producto:</label>
              <input type="text" name="product_name[]" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Precio (CLP):</label>
              <input type="number" name="price[]" class="form-control" step="1" min="0" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Cantidad:</label>
              <input type="number" name="stock[]" class="form-control" min="0" required>
            </div>
            <button type="button" class="btn btn-sm btn-danger remove-product">Eliminar Producto</button>
          </div>
        <?php endif; ?>
      </div>
      <button type="button" id="add_product" class="btn btn-outline-secondary mb-3">Agregar otro producto</button>
      
      <button type="submit" class="btn btn-primary">Actualizar Lista</button>
      <a href="preset_product_lists.php" class="btn btn-secondary">Volver a Listas Predeterminadas</a>
    </form>
  </div>
  
  <!-- Bootstrap Bundle JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Agregar un nuevo grupo de producto
    document.getElementById('add_product').addEventListener('click', function(){
        var container = document.getElementById('products_container');
        var group = container.querySelector('.product-group');
        var clone = group.cloneNode(true);
        // Limpiar valores de inputs clonados
        clone.querySelectorAll('input').forEach(function(input) {
            // Si es el campo oculto de product_id, vaciarlo
            if (input.getAttribute('type') === 'hidden') {
                input.value = "";
            } else {
                input.value = "";
            }
        });
        container.appendChild(clone);
    });
    
    // Eliminar un grupo de producto
    document.getElementById('products_container').addEventListener('click', function(e){
        if (e.target && e.target.matches('.remove-product')) {
            var groups = document.querySelectorAll('.product-group');
            if (groups.length > 1) {
                e.target.closest('.product-group').remove();
            } else {
                alert("Debe haber al menos un producto.");
            }
        }
    });
  </script>
</body>
</html>
