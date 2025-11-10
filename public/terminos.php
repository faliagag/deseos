<?php
session_start();
$config = include __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Términos y Condiciones - <?php echo $config['application']['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-gift"></i> <?php echo $config['application']['name']; ?>
            </a>
            <a href="index.php" class="btn btn-outline-light">Volver al Inicio</a>
        </div>
    </nav>
    
    <div class="container my-5">
        <h1 class="mb-4">Términos y Condiciones</h1>
        <p class="text-muted">Última actualización: <?php echo date('d/m/Y'); ?></p>
        
        <div class="card mb-4">
            <div class="card-body">
                <h3>1. Aceptación de los Términos</h3>
                <p>Al acceder y utilizar <?php echo $config['application']['name']; ?>, usted acepta estar sujeto a estos términos y condiciones.</p>
                
                <h3>2. Descripción del Servicio</h3>
                <p><?php echo $config['application']['name']; ?> es una plataforma que permite crear listas de regalos y recibir contribuciones monetarias de amigos y familiares.</p>
                
                <h3>3. Comisiones y Tarifas</h3>
                <ul>
                    <li><strong>Para creadores de listas:</strong> El servicio es gratuito. Recibes el 100% del monto de los regalos.</li>
                    <li><strong>Para compradores:</strong> Se aplica una comisión del <?php echo $config['fees']['buyer_commission']; ?>% sobre el monto del regalo, que incluye costos de procesamiento y plataforma.</li>
                </ul>
                
                <h3>4. Pagos y Depósitos</h3>
                <p>Los depósitos se realizan cada dos semanas, los días miércoles, según el calendario publicado. Se incluyen las transacciones aprobadas hasta las 14:00 horas del lunes anterior.</p>
                
                <h3>5. Responsabilidades del Usuario</h3>
                <ul>
                    <li>Proporcionar información veraz y actualizada</li>
                    <li>Mantener la confidencialidad de su cuenta</li>
                    <li>No utilizar el servicio para fines ilegales</li>
                    <li>Respetar los derechos de otros usuarios</li>
                </ul>
                
                <h3>6. Propiedad Intelectual</h3>
                <p>Todo el contenido del sitio web es propiedad de <?php echo $config['application']['name']; ?> y está protegido por leyes de propiedad intelectual.</p>
                
                <h3>7. Limitación de Responsabilidad</h3>
                <p>No nos hacemos responsables por pérdidas indirectas, daños especiales o consecuenciales derivados del uso del servicio.</p>
                
                <h3>8. Modificaciones</h3>
                <p>Nos reservamos el derecho de modificar estos términos en cualquier momento. Las modificaciones serán efectivas inmediatamente después de su publicación.</p>
                
                <h3>9. Ley Aplicable</h3>
                <p>Estos términos se rigen por las leyes de Chile.</p>
                
                <h3>10. Contacto</h3>
                <p>Para consultas sobre estos términos: <a href="mailto:<?php echo $config['application']['email']; ?>"><?php echo $config['application']['email']; ?></a></p>
            </div>
        </div>
    </div>
    
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $config['application']['name']; ?>. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>
