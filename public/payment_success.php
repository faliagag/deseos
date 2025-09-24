<?php
session_start();
require_once '../includes/db.php';
require_once '../models/PaymentModel.php';

// Obtener parámetros de MercadoPago
$paymentId = $_GET['payment_id'] ?? null;
$status = $_GET['status'] ?? null;
$externalReference = $_GET['external_reference'] ?? null;
$merchantOrder = $_GET['merchant_order_id'] ?? null;

$paymentModel = new PaymentModel();
$transaction = null;
$gift = null;

if ($externalReference) {
    try {
        // Buscar transacción
        $stmt = getConnection()->prepare("SELECT * FROM transactions WHERE external_reference = ?");
        $stmt->execute([$externalReference]);
        $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($transaction) {
            // Obtener detalles del regalo
            $stmt = getConnection()->prepare("
                SELECT g.*, gl.title as list_title, u.name as owner_name 
                FROM gifts g 
                JOIN gift_lists gl ON g.gift_list_id = gl.id 
                JOIN users u ON gl.user_id = u.id 
                WHERE g.id = ?
            ");
            $stmt->execute([$transaction['gift_id']]);
            $gift = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log('Error al obtener transacción: ' . $e->getMessage());
    }
}

$config = include '../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Pago Exitoso! - <?= htmlspecialchars($config['application']['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .success-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            animation: checkmark 0.6s ease-in-out 0.3s both;
        }
        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }
        .transaction-details {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .btn-custom {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 25px;
            padding: 12px 30px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container success-container">
        <div class="success-card">
            <div class="success-header">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="mb-0">¡Pago Exitoso!</h1>
                <p class="mb-0 opacity-75">Tu compra ha sido procesada correctamente</p>
            </div>
            
            <div class="p-4">
                <?php if ($transaction && $gift): ?>
                    <div class="transaction-details">
                        <h5 class="text-primary mb-3">
                            <i class="fas fa-gift me-2"></i>
                            Detalles de la Compra
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Producto:</strong><br>
                                    <?= htmlspecialchars($gift['name']) ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Lista:</strong><br>
                                    <?= htmlspecialchars($gift['list_title']) ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Propietario:</strong><br>
                                    <?= htmlspecialchars($gift['owner_name']) ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-2">
                                    <strong>Cantidad:</strong><br>
                                    <?= $transaction['quantity'] ?>
                                </p>
                                <p class="mb-2">
                                    <strong>Total Pagado:</strong><br>
                                    <span class="h5 text-success">
                                        $<?= number_format($transaction['amount'], 0, ',', '.') ?>
                                    </span>
                                </p>
                                <?php if ($paymentId): ?>
                                <p class="mb-2">
                                    <strong>ID de Pago:</strong><br>
                                    <code><?= htmlspecialchars($paymentId) ?></code>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>¡Felicidades!</strong> Tu compra se ha registrado exitosamente. 
                        El propietario de la lista ha sido notificado automáticamente.
                    </div>
                    
                    <?php if (!empty($transaction['buyer_email'])): ?>
                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-envelope me-2"></i>
                        Hemos enviado un comprobante de compra a <strong><?= htmlspecialchars($transaction['buyer_email']) ?></strong>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        No pudimos encontrar los detalles de la transacción, pero tu pago ha sido procesado correctamente.
                        Si tienes alguna duda, contáctanos con el ID de pago: <code><?= htmlspecialchars($paymentId ?? 'N/A') ?></code>
                    </div>
                <?php endif; ?>
                
                <div class="d-grid gap-2 mt-4">
                    <a href="index.php" class="btn btn-custom btn-lg">
                        <i class="fas fa-home me-2"></i>
                        Volver al Inicio
                    </a>
                    
                    <?php if ($transaction): ?>
                    <a href="giftlist.php?id=<?= urlencode($gift['list_title']) ?>" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i>
                        Ver Lista Completa
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        <i class="fas fa-shield-alt me-1"></i>
                        Pago procesado de forma segura por MercadoPago
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Mensaje de agradecimiento adicional -->
        <div class="text-center mt-4">
            <div class="bg-white rounded-3 p-3 shadow-sm">
                <h6 class="text-primary mb-2">
                    <i class="fas fa-heart text-danger me-2"></i>
                    ¡Gracias por hacer realidad los sueños!
                </h6>
                <p class="text-muted mb-0 small">
                    Tu contribución hace que momentos especiales sean aún más memorables.
                </p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Confetti animation -->
    <script>
        // Simple confetti effect
        function createConfetti() {
            const colors = ['#f43f5e', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b'];
            
            for (let i = 0; i < 50; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = '10px';
                confetti.style.height = '10px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-10px';
                confetti.style.borderRadius = '50%';
                confetti.style.pointerEvents = 'none';
                confetti.style.zIndex = '9999';
                
                document.body.appendChild(confetti);
                
                const fallDuration = Math.random() * 3 + 2;
                confetti.animate([
                    { transform: 'translateY(-10px) rotate(0deg)', opacity: 1 },
                    { transform: `translateY(100vh) rotate(360deg)`, opacity: 0 }
                ], {
                    duration: fallDuration * 1000,
                    easing: 'linear'
                }).onfinish = () => confetti.remove();
            }
        }
        
        // Trigger confetti after page loads
        setTimeout(createConfetti, 500);
    </script>
</body>
</html>