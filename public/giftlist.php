<?php
// public/giftlist.php?link=xxxxx
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../controllers/GiftListController.php";

$unique_link = $_GET["link"] ?? "";
$controller = new GiftListController($pdo);
$giftList = $controller->show($unique_link);
if (!$giftList) {
    echo "<div class='container mt-5'><div class='alert alert-warning'>Lista de regalos no encontrada.</div></div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($giftList["title"]); ?> - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="assets/js/script.js"></script>
</head>
<body>
    <div class="container mt-5">
        <h1><?php echo htmlspecialchars($giftList["title"]); ?></h1>
        <p><?php echo htmlspecialchars($giftList["description"]); ?></p>
        <h2 class="mt-4">Regalos</h2>
        <ul class="list-group">
            <?php foreach ($giftList["gifts"] as $gift): ?>
                <li class="list-group-item">
                    <div class="row">
                        <div class="col-md-8">
                            <strong><?php echo htmlspecialchars($gift["name"]); ?></strong><br>
                            Precio: $<?php echo number_format($gift["price"],2); ?><br>
                            Stock: <?php echo $gift["stock"]; ?>, Vendidos: <?php echo $gift["sold"]; ?><br>
                            Recaudado: $<?php echo number_format($gift["contributed"],2); ?>
                        </div>
                        <div class="col-md-4">
                            <form method="post" action="process_payment.php" class="d-flex align-items-center">
                                <input type="hidden" name="gift_list_id" value="<?php echo $giftList["id"]; ?>">
                                <input type="hidden" name="gift_id" value="<?php echo $gift["id"]; ?>">
                                <input type="number" name="amount" class="form-control me-2" placeholder="Monto" required style="max-width:100px;">
                                <input type="number" name="quantity" class="form-control me-2" placeholder="Cant." required style="max-width:80px;">
                                <input type="hidden" name="currency" value="usd">
                                <button type="submit" class="btn btn-success">Comprar</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="mt-4"><a href="index.php" class="btn btn-secondary">Volver al inicio</a></p>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
