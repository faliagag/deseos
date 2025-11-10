<?php
session_start();
$config = include __DIR__ . '/../config/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - <?php echo $config['application']['name']; ?></title>
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
        <h1 class="mb-4">Política de Privacidad</h1>
        <p class="text-muted">Última actualización: <?php echo date('d/m/Y'); ?></p>
        
        <div class="card mb-4">
            <div class="card-body">
                <h3>1. Información que Recopilamos</h3>
                <p>Recopilamos la siguiente información:</p>
                <ul>
                    <li>Datos personales: nombre, apellido, email, teléfono, RUT</li>
                    <li>Datos bancarios: banco, tipo de cuenta, número de cuenta</li>
                    <li>Información de listas: título, descripción, regalos</li>
                    <li>Datos de transacciones: montos, fechas, métodos de pago</li>
                    <li>Información técnica: dirección IP, navegador, dispositivo</li>
                </ul>
                
                <h3>2. Cómo Usamos su Información</h3>
                <ul>
                    <li>Procesar pagos y depósitos</li>
                    <li>Gestionar su cuenta y listas de regalos</li>
                    <li>Enviar notificaciones sobre transacciones</li>
                    <li>Mejorar nuestros servicios</li>
                    <li>Cumplir con obligaciones legales</li>
                </ul>
                
                <h3>3. Compartir Información</h3>
                <p>Compartimos su información solo con:</p>
                <ul>
                    <li>Procesadores de pago (MercadoPago, Transbank)</li>
                    <li>Bancos para depósitos</li>
                    <li>Autoridades cuando sea legalmente requerido</li>
                </ul>
                <p><strong>Nunca vendemos su información personal a terceros.</strong></p>
                
                <h3>4. Seguridad de Datos</h3>
                <p>Implementamos medidas de seguridad técnicas y organizativas para proteger su información:</p>
                <ul>
                    <li>Encriptación SSL/TLS</li>
                    <li>Almacenamiento seguro de contraseñas</li>
                    <li>Acceso restringido a datos sensibles</li>
                    <li>Monitoreo continuo de seguridad</li>
                </ul>
                
                <h3>5. Cookies</h3>
                <p>Utilizamos cookies para:</p>
                <ul>
                    <li>Mantener su sesión activa</li>
                    <li>Recordar sus preferencias</li>
                    <li>Analizar el uso del sitio</li>
                </ul>
                <p>Puede configurar su navegador para rechazar cookies, pero esto puede afectar la funcionalidad del sitio.</p>
                
                <h3>6. Sus Derechos</h3>
                <p>Usted tiene derecho a:</p>
                <ul>
                    <li>Acceder a su información personal</li>
                    <li>Corregir datos inexactos</li>
                    <li>Solicitar la eliminación de sus datos</li>
                    <li>Oponerse al procesamiento de sus datos</li>
                    <li>Portabilidad de datos</li>
                </ul>
                
                <h3>7. Retención de Datos</h3>
                <p>Conservamos su información mientras su cuenta esté activa o según sea necesario para cumplir con obligaciones legales.</p>
                
                <h3>8. Menores de Edad</h3>
                <p>Nuestro servicio no está dirigido a menores de 18 años. No recopilamos intencionalmente información de menores.</p>
                
                <h3>9. Cambios a esta Política</h3>
                <p>Podemos actualizar esta política periódicamente. Le notificaremos sobre cambios significativos.</p>
                
                <h3>10. Contacto</h3>
                <p>Para consultas sobre privacidad: <a href="mailto:<?php echo $config['application']['email']; ?>"><?php echo $config['application']['email']; ?></a></p>
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
