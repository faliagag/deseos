<?php
// public/admin/upload_txt_list.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario es administrador
$auth = new Auth($pdo);
if (!$auth->isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verificar que se haya subido el archivo y sin error
    if (isset($_FILES["txt_file"]) && $_FILES["txt_file"]["error"] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES["txt_file"]["tmp_name"];
        $fileName = $_FILES["txt_file"]["name"];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        if ($fileExtension !== "txt") {
            $error = "Por favor, suba un archivo de texto (.txt)";
        } else {
            // Leer el archivo línea por línea (ignorando líneas vacías)
            $lines = file($fileTmpPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                $error = "Error al leer el archivo.";
            } else {
                // Obtener datos opcionales para la lista
                $listTitle = trim($_POST["list_title"] ?? "Lista TXT " . date("Y-m-d H:i:s"));
                $listDescription = trim($_POST["list_description"] ?? "");

                // Insertar la nueva lista en preset_product_lists
                $stmt = $pdo->prepare("INSERT INTO preset_product_lists (theme, description) VALUES (?, ?)");
                if ($stmt->execute([$listTitle, $listDescription])) {
                    $preset_list_id = $pdo->lastInsertId();

                    // Insertar cada producto del archivo en preset_products
                    $insertStmt = $pdo->prepare("INSERT INTO preset_products (preset_list_id, name, price, stock) VALUES (?, ?, 0, 0)");
                    foreach ($lines as $line) {
                        $productName = trim($line);
                        if (!empty($productName)) {
                            $insertStmt->execute([$preset_list_id, $productName]);
                        }
                    }
                    $success = "Lista de productos cargada exitosamente.";
                } else {
                    $error = "Error al crear la lista en la base de datos.";
                }
            }
        }
    } else {
        $error = "Error en la carga del archivo.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cargar Lista TXT - Administración</title>
  <!-- Bootstrap CSS (CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <div class="container mt-5">
    <h1>Cargar Lista de Productos (TXT)</h1>
    <?php if (!empty($error)): ?>
      <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
      <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <form method="post" action="" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label">Título de la Lista (opcional):</label>
        <input type="text" name="list_title" class="form-control" placeholder="Ingrese un título para la lista">
      </div>
      <div class="mb-3">
        <label class="form-label">Descripción de la Lista (opcional):</label>
        <textarea name="list_description" class="form-control" placeholder="Ingrese una descripción"></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Archivo TXT:</label>
        <input type="file" name="txt_file" class="form-control" accept=".txt" required>
      </div>
      <button type="submit" class="btn btn-primary">Cargar Lista</button>
      <a href="dashboard.php" class="btn btn-secondary">Volver al Dashboard</a>
    </form>
  </div>
  
  <!-- Bootstrap Bundle JS (CDN) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>