<?php
/**
 * INSTALADOR OPTIMIZADO PARA HOSTING COMPARTIDO
 * Lista de Deseos - Sistema Completo con Analytics y Pagos
 * 
 * Características adaptadas para hosting compartido:
 * - Sin dependencias externas complejas
 * - Configuración mediante archivos
 * - Optimización de memoria y recursos
 * - Compatibilidad con PHP 7.4+
 * - Gestión de errores robusta
 */

// Configuración inicial
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 120);

// Verificar si ya está instalado
if (file_exists('.installed') && !isset($_GET['force'])) {
    die('El sistema ya está instalado. Si deseas reinstalar, agrega ?force=1 a la URL');
}

// Incluir conexión a base de datos
require_once __DIR__ . '/includes/db.php';

// Variables de configuración
$errors = [];
$success = [];
$warnings = [];

try {
    // Validar conexión a base de datos
    $pdo = getConnection();
    $success[] = "✓ Conexión a base de datos establecida";
} catch (Exception $e) {
    $errors[] = "✗ Error de conexión a base de datos: " . $e->getMessage();
}

if (empty($errors)) {
    try {
        // Crear tablas principales del sistema original
        $mainTables = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            lastname VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            bank VARCHAR(255) DEFAULT NULL,
            account_type VARCHAR(50) DEFAULT NULL,
            account_number VARCHAR(50) DEFAULT NULL,
            rut VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            last_login DATETIME NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gift_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_type VARCHAR(50) DEFAULT NULL,
            event_date DATE NULL,
            beneficiary1 VARCHAR(255) DEFAULT NULL,
            beneficiary2 VARCHAR(255) DEFAULT NULL,
            preset_theme INT DEFAULT NULL,
            unique_link VARCHAR(255) NOT NULL UNIQUE,
            expiry_date DATE DEFAULT NULL,
            visibility ENUM('public', 'private', 'link_only') DEFAULT 'link_only',
            view_count INT DEFAULT 0,
            last_activity DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_visibility (visibility),
            INDEX idx_unique_link (unique_link)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gift_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'gift',
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gift_list_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(12,2) NOT NULL,
            stock INT DEFAULT 0,
            sold_quantity INT DEFAULT 0,
            collected_amount DECIMAL(12,2) DEFAULT 0,
            category_id INT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            is_group_gift BOOLEAN DEFAULT 0,
            min_contribution DECIMAL(10,2) DEFAULT NULL,
            priority INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES gift_categories(id) ON DELETE SET NULL,
            INDEX idx_list (gift_list_id),
            INDEX idx_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($mainTables);
        $success[] = "✓ Tablas principales creadas";
        
        // Crear tablas de pagos y transacciones (optimizadas para hosting compartido)
        $paymentTables = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gift_id INT NOT NULL,
            quantity INT DEFAULT 1,
            amount DECIMAL(12,2) NOT NULL,
            buyer_email VARCHAR(255) NOT NULL,
            buyer_name VARCHAR(255) NOT NULL,
            buyer_phone VARCHAR(50) NULL,
            external_reference VARCHAR(100) NOT NULL,
            preference_id VARCHAR(100) NULL,
            payment_id VARCHAR(100) NULL,
            status ENUM('pending','approved','rejected','cancelled','refunded') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            payment_data TEXT NULL,
            processed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_external_ref (external_reference),
            INDEX idx_payment_id (payment_id),
            INDEX idx_buyer_email (buyer_email),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
            INDEX idx_type (event_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($paymentTables);
        $success[] = "✓ Tablas de pagos creadas";
        
        // Crear tablas de notificaciones (optimizadas)
        $notificationTables = "
        CREATE TABLE IF NOT EXISTS system_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info','success','warning','error') DEFAULT 'info',
            data JSON NULL,
            read_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_unread (user_id, read_at),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('email','sms','push') NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            template VARCHAR(100) NULL,
            subject VARCHAR(255) NULL,
            message TEXT NULL,
            status ENUM('pending','sent','failed','error') DEFAULT 'pending',
            data JSON NULL,
            error_message TEXT NULL,
            external_id VARCHAR(100) NULL,
            attempts INT DEFAULT 0,
            sent_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_type (type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS scheduled_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type ENUM('email','sms') NOT NULL,
            recipient VARCHAR(255) NOT NULL,
            template VARCHAR(100) NOT NULL,
            data JSON NULL,
            send_at DATETIME NOT NULL,
            status ENUM('pending','sent','failed','cancelled') DEFAULT 'pending',
            attempts INT DEFAULT 0,
            processed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_send_at (send_at, status),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($notificationTables);
        $success[] = "✓ Tablas de notificaciones creadas";
        
        // Crear tablas de analytics (optimizadas para hosting compartido)
        $analyticsTables = "
        CREATE TABLE IF NOT EXISTS analytics_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            user_id INT NULL,
            session_id VARCHAR(100) NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            page_url VARCHAR(500) NULL,
            referrer VARCHAR(500) NULL,
            data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_event_type (event_type),
            INDEX idx_user_date (user_id, created_at),
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS daily_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            stat_date DATE NOT NULL,
            metric_name VARCHAR(50) NOT NULL,
            metric_value DECIMAL(15,2) DEFAULT 0,
            metadata JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_daily_metric (stat_date, metric_name),
            INDEX idx_date (stat_date),
            INDEX idx_metric (metric_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($analyticsTables);
        $success[] = "✓ Tablas de analytics creadas";
        
        // Crear tablas adicionales del sistema original
        $additionalTables = "
        CREATE TABLE IF NOT EXISTS preset_product_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS preset_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            preset_list_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            stock INT DEFAULT 0,
            FOREIGN KEY (preset_list_id) REFERENCES preset_product_lists(id) ON DELETE CASCADE,
            INDEX idx_preset_list (preset_list_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS app_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
            description TEXT NULL,
            is_public BOOLEAN DEFAULT FALSE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key),
            INDEX idx_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($additionalTables);
        $success[] = "✓ Tablas adicionales creadas";
        
    } catch (Exception $e) {
        $errors[] = "✗ Error creando tablas: " . $e->getMessage();
    }
    
    // Crear usuario administrador
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@admin.com'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                $hashedPassword = password_hash("admin123", PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, lastname, phone, bank, account_type, account_number, rut, email, password, role, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin', NOW())
                ");
                $stmt->execute([
                    "Administrador", 
                    "Sistema", 
                    "000000000", 
                    "Banco Sistema", 
                    "Corriente", 
                    "000000000", 
                    "00000000-0", 
                    "admin@admin.com", 
                    $hashedPassword
                ]);
                $success[] = "✓ Usuario administrador creado (admin@admin.com / admin123)";
            } else {
                $warnings[] = "⚠ Usuario administrador ya existe";
            }
        } catch (Exception $e) {
            $errors[] = "✗ Error creando usuario administrador: " . $e->getMessage();
        }
    }
    
    // Insertar categorías predeterminadas
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gift_categories");
            $stmt->execute();
            $categoryCount = $stmt->fetchColumn();
            
            if ($categoryCount == 0) {
                $categories = [
                    ['Electrónica', 'Dispositivos electrónicos y accesorios', 'laptop', 1],
                    ['Hogar', 'Artículos para el hogar y decoración', 'house', 2],
                    ['Ropa y Accesorios', 'Prendas de vestir y accesorios', 'bag', 3],
                    ['Juguetes', 'Juguetes para niños de todas las edades', 'controller', 4],
                    ['Belleza', 'Productos de belleza y cuidado personal', 'gem', 5],
                    ['Cocina', 'Utensilios y electrodomésticos de cocina', 'cup-hot', 6],
                    ['Libros', 'Libros, e-books y audiolibros', 'book', 7],
                    ['Deportes', 'Artículos deportivos y fitness', 'bicycle', 8],
                    ['Viajes', 'Experiencias de viaje y accesorios', 'airplane', 9],
                    ['Mascotas', 'Productos para mascotas', 'bug', 10],
                    ['Otros', 'Otros productos no categorizados', 'gift', 99]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO gift_categories (name, description, icon, sort_order) VALUES (?, ?, ?, ?)");
                foreach ($categories as $category) {
                    $stmt->execute($category);
                }
                $success[] = "✓ Categorías predeterminadas creadas (" . count($categories) . ")";
            } else {
                $warnings[] = "⚠ Categorías ya existen";
            }
        } catch (Exception $e) {
            $warnings[] = "⚠ No se pudieron crear las categorías: " . $e->getMessage();
        }
    }
    
    // Insertar configuraciones por defecto
    if (empty($errors)) {
        try {
            $defaultSettings = [
                ['app_name', 'Lista de Deseos', 'string', 'Nombre de la aplicación', true],
                ['app_version', '2.0.0', 'string', 'Versión de la aplicación', true],
                ['maintenance_mode', '0', 'boolean', 'Modo mantenimiento activado', false],
                ['user_registration', '1', 'boolean', 'Permitir registro de usuarios', false],
                ['email_notifications', '1', 'boolean', 'Notificaciones por email habilitadas', false],
                ['sms_notifications', '0', 'boolean', 'Notificaciones por SMS habilitadas', false],
                ['analytics_enabled', '1', 'boolean', 'Sistema de analytics habilitado', false],
                ['max_lists_per_user', '10', 'integer', 'Máximo de listas por usuario', false],
                ['max_gifts_per_list', '50', 'integer', 'Máximo de regalos por lista', false],
                ['session_timeout', '3600', 'integer', 'Tiempo de sesión en segundos', false],
                ['upload_max_size', '5242880', 'integer', 'Tamaño máximo de archivos en bytes (5MB)', false]
            ];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO app_settings (setting_key, setting_value, setting_type, description, is_public) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $settingsCount = 0;
            foreach ($defaultSettings as $setting) {
                if ($stmt->execute($setting)) {
                    $settingsCount++;
                }
            }
            
            if ($settingsCount > 0) {
                $success[] = "✓ Configuraciones por defecto creadas (" . $settingsCount . ")";
            } else {
                $warnings[] = "⚠ Configuraciones ya existen";
            }
        } catch (Exception $e) {
            $warnings[] = "⚠ No se pudieron crear las configuraciones: " . $e->getMessage();
        }
    }
    
    // Crear directorios necesarios
    $directories = [
        'uploads',
        'uploads/images',
        'uploads/temp',
        'cache',
        'logs',
        'exports',
        'templates/notifications',
        'templates/notifications/email',
        'templates/notifications/sms'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            if (mkdir($dir, 0755, true)) {
                $success[] = "✓ Directorio creado: $dir";
            } else {
                $warnings[] = "⚠ No se pudo crear directorio: $dir";
            }
        }
    }
    
    // Crear archivos de seguridad
    $securityFiles = [
        'uploads/.htaccess' => "deny from all\nOptions -Indexes",
        'logs/.htaccess' => "deny from all\nOptions -Indexes",
        'cache/.htaccess' => "deny from all\nOptions -Indexes",
        'config/.htaccess' => "deny from all\nOptions -Indexes"
    ];
    
    foreach ($securityFiles as $file => $content) {
        if (!file_exists($file)) {
            if (file_put_contents($file, $content)) {
                $success[] = "✓ Archivo de seguridad creado: $file";
            } else {
                $warnings[] = "⚠ No se pudo crear archivo de seguridad: $file";
            }
        }
    }
    
    // Crear archivo de instalación completada
    if (empty($errors)) {
        $installInfo = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '2.0.0',
            'php_version' => PHP_VERSION,
            'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
            'features' => [
                'payments' => 'MercadoPago',
                'notifications' => 'Email + SMS',
                'analytics' => 'Full Analytics',
                'admin_panel' => 'Advanced Admin Panel'
            ]
        ];
        
        if (file_put_contents('.installed', json_encode($installInfo, JSON_PRETTY_PRINT))) {
            $success[] = "✓ Archivo de instalación creado";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Lista de Deseos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .install-container { max-width: 800px; margin: 50px auto; }
        .install-card { background: white; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden; }
        .install-header { background: linear-gradient(45deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
        .status-item { padding: 8px 15px; margin: 5px 0; border-radius: 5px; }
        .status-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        .feature-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .feature-card { text-align: center; padding: 20px; background: #f8f9fa; border-radius: 10px; }
    </style>
</head>
<body>
    <div class="container install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="fas fa-gift me-3"></i>Lista de Deseos</h1>
                <p class="mb-0">Sistema Completo para Hosting Compartido</p>
            </div>
            
            <div class="p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores de Instalación</h5>
                        <?php foreach ($errors as $error): ?>
                            <div class="status-item status-error"><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>Instalación Exitosa</h5>
                        <?php foreach ($success as $item): ?>
                            <div class="status-item status-success"><?= htmlspecialchars($item) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($warnings)): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-circle me-2"></i>Advertencias</h5>
                        <?php foreach ($warnings as $warning): ?>
                            <div class="status-item status-warning"><?= htmlspecialchars($warning) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($errors)): ?>
                    <div class="text-center my-4">
                        <h3 class="text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ¡Instalación Completada!
                        </h3>
                    </div>
                    
                    <div class="feature-grid">
                        <div class="feature-card">
                            <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                            <h6>Pagos con MercadoPago</h6>
                            <small>Sistema completo de pagos</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-bell fa-2x text-info mb-2"></i>
                            <h6>Notificaciones</h6>
                            <small>Email y SMS automáticos</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-chart-bar fa-2x text-success mb-2"></i>
                            <h6>Analytics</h6>
                            <small>Métricas y reportes completos</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-cog fa-2x text-warning mb-2"></i>
                            <h6>Panel Admin</h6>
                            <small>Gestión avanzada</small>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Credenciales de Administrador</h6>
                        <p class="mb-1"><strong>Email:</strong> admin@admin.com</p>
                        <p class="mb-1"><strong>Contraseña:</strong> admin123</p>
                        <small class="text-muted">⚠ Cambia estas credenciales inmediatamente después del primer acceso</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-shield-alt me-2"></i>Pasos Importantes</h6>
                        <ol class="mb-0">
                            <li>Elimina o renombra este archivo <code>install.php</code></li>
                            <li>Configura MercadoPago en <code>config/config.php</code></li>
                            <li>Configura SMTP para notificaciones por email</li>
                            <li>Cambia las credenciales del administrador</li>
                            <li>Configura las URL de webhook de MercadoPago</li>
                        </ol>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="public/" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-home me-2"></i>Ir al Sitio Web
                        </a>
                        <a href="public/admin/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-user-shield me-2"></i>Panel de Admin
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <p class="text-muted">Por favor, corrige los errores y vuelve a intentar.</p>
                        <a href="?force=1" class="btn btn-warning">
                            <i class="fas fa-redo me-2"></i>Reintentar Instalación
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-light p-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-server me-1"></i>
                    Optimizado para Hosting Compartido | PHP <?= PHP_VERSION ?>
                </small>
            </div>
        </div>
    </div>
</body>
</html>