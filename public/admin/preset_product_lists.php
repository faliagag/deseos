<?php
// public/admin/preset_product_lists.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario actual es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$error = "";
$success = "";

// Procesar el formulario de creación de lista predeterminada
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['theme'])) {
    $theme = trim($_POST['theme']);
    $description = trim($_POST['description'] ?? "");
    
    if (!empty($theme)) {
        // Insertar la nueva lista predeterminada
        $stmt = $pdo->prepare("INSERT INTO preset_product_lists (theme, description) VALUES (?, ?)");
        if ($stmt->execute([$theme, $description])) {
            $preset_list_id = $pdo->lastInsertId();
            // Procesar los productos ingresados
            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                foreach ($_POST['product_name'] as $index => $pName) {
                    $pName = trim($pName);
                    $pPrice = floatval($_POST['price'][$index] ?? 0);
                    $pStock = intval($_POST['stock'][$index] ?? 0);
                    if (!empty($pName)) {
                        $stmtProd = $pdo->prepare("INSERT INTO preset_products (preset_list_id, name, price, stock) VALUES (?, ?, ?, ?)");
                        $stmtProd->execute([$preset_list_id, $pName, $pPrice, $pStock]);
                    }
                }
            }
            $success = "Lista de productos predeterminada creada exitosamente.";
        } else {
            $error = "Error al crear la lista.";
        }
    } else {
        $error = "El tema es obligatorio.";
    }
}

// Consultar las listas predeterminadas existentes
$stmt = $pdo->query("SELECT * FROM preset_product_lists ORDER BY created_at DESC");
$presetLists = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Listas de Productos Predeterminadas - Administración</title>
  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
    <h1>Listas de Productos Predeterminadas</h1>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <!-- Formulario para crear una nueva lista predeterminada -->
    <form method="post" action="">
      <div class="mb-3">
        <label class="form-label">Tema (Ej. Viaje a la Luna, Acampar, Treking):</label>
        <input type="text" name="theme" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción (Opcional):</label>
        <textarea name="description" class="form-control"></textarea>
      </div>
      <h3>Productos</h3>
      <div id="products_container">
        <div class="product-group">
          <div class="row">
            <div class="col-md-4">
              <label class="form-label">Nombre del Producto:</label>
              <input type="text" name="product_name[]" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Precio (CLP):</label>
              <input type="number" name="price[]" class="form-control" step="1" min="0" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Cantidad:</label>
              <input type="number" name="stock[]" class="form-control" min="0" required>
            </div>
          </div>
          <button type="button" class="btn btn-sm btn-danger mt-2 remove-product">Eliminar Producto</button>
        </div>
      </div>
      <button type="button" id="add_product" class="btn btn-outline-secondary mb-3">Agregar otro producto</button>
      <br>
      <button type="submit" class="btn btn-primary">Crear Lista Predeterminada</button>
      <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </form>
    
    <hr>
    <h2>Listas Predeterminadas Existentes</h2>
    <?php if (!empty($presetLists)): ?>
      <table class="table table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Tema</th>
            <th>Descripción</th>
            <th>Fecha de Creación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($presetLists as $list): ?>
            <tr>
              <td><?php echo $list['id']; ?></td>
              <td><?php echo htmlspecialchars($list['theme']); ?></td>
              <td><?php echo htmlspecialchars($list['description']); ?></td>
              <td><?php echo $list['created_at']; ?></td>
              <td>
                <a href="edit_preset_list.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-warning">Editar</a>
                <a href="delete_preset_list.php?id=<?php echo $list['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta lista predeterminada?');">Eliminar</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="alert alert-info">No se encontraron listas predeterminadas.</div>
    <?php endif; ?>
  </div>
  
  <!-- Bootstrap Bundle JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Agregar un nuevo grupo de producto personalizado
    document.getElementById('add_product').addEventListener('click', function(){
        var container = document.getElementById('products_container');
        var group = container.querySelector('.product-group');
        var clone = group.cloneNode(true);
        clone.querySelectorAll('input').forEach(function(input) {
            input.value = "";
        });
        container.appendChild(clone);
    });
    
    // Eliminar grupo de producto
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