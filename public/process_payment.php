<?php
// public/process_payment.php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../controllers/PaymentController.php";

$data = $_POST;
$payment = new PaymentController($pdo);
$user_id = $_SESSION["user"]["id"] ?? null;
$result = $payment->processPayment($data, $user_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Confirmación de Compra - GiftList App</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-info">
            <?php echo $result["message"]; ?>
        </div>
        <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
    </div>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
