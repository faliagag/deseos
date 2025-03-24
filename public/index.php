<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../controllers/SearchController.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$keyword = $_GET["q"] ?? "";
$search = new SearchController($pdo);
if ($keyword != "") {
    $giftLists = $search->search($keyword);
} else {
    require_once __DIR__ . '/../controllers/GiftListController.php';
    $glc = new GiftListController($pdo);
    $giftLists = $glc->getAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>GiftList App - Bienvenido</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
      <div class="container">
        <a class="navbar-brand" href="index.php">GiftList App</a>
        <div class="collapse navbar-collapse">
          <ul class="navbar-nav ms-auto">
            <?php if(isset($_SESSION["user"])): ?>
              <li class="nav-item"><a class="nav-link" href="dashboard.php">Dashboard</a></li>
              <li class="nav-item"><a class="nav-link" href="logout.php">Cerrar Sesión</a></li>
            <?php else: ?>
              <li class="nav-item"><a class="nav-link" href="login.php">Iniciar Sesión</a></li>
              <li class="nav-item"><a class="nav-link" href="register.php">Registrarse</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </div>
    </nav>
    <div class="container mt-4">
        <form method="get" action="index.php" class="d-flex mb-3">
            <input type="text" name="q" class="form-control me-2" placeholder="Buscar listas de regalos..." value="<?php echo htmlspecialchars($keyword); ?>">
            <button type="submit" class="btn btn-outline-success">Buscar</button>
        </form>
        <h2 class="mb-3">Listas de Regalos</h2>
        <?php if (!empty($giftLists)): ?>
            <ul class="list-group">
            <?php foreach ($giftLists as $list): ?>
                <li class="list-group-item">
                    <a href="giftlist.php?link=<?php echo urlencode($list["unique_link"]); ?>">
                        <?php echo htmlspecialchars($list["title"]); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="alert alert-warning">No se encontraron listas.</div>
        <?php endif; ?>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
