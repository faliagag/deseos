<?php
/**
 * INSTALADOR COMPLETO - VERSIÓN 2.1
 * Compatible con todas las nuevas funcionalidades:
 * - Transbank + MercadoPago
 * - Sistema de fees y payouts
 * - Testimonios y FAQs
 * - Panel administrativo avanzado
 * - Analytics completos
 */

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
ini_set('memory_limit', '128M');
ini_set('max_execution_time', 180);

// Verificar si ya está instalado
if (file_exists('.installed') && !isset($_GET['force'])) {
    die('El sistema ya está instalado. Si deseas reinstalar, agrega ?force=1 a la URL');
}

// Variables de configuración
$errors = [];
$success = [];
$warnings = [];

try {
    // Incluir conexión a base de datos
    require_once __DIR__ . '/includes/db.php';
    $pdo = getConnection();
    $success[] = "✓ Conexión a base de datos establecida";
} catch (Exception $e) {
    $errors[] = "✗ Error de conexión a base de datos: " . $e->getMessage();
}

if (empty($errors)) {
    try {
        // ==================== TABLAS PRINCIPALES ====================
        $mainTables = "
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            lastname VARCHAR(255) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            bank_name VARCHAR(255) DEFAULT NULL,
            account_type VARCHAR(50) DEFAULT NULL,
            bank_account VARCHAR(50) DEFAULT NULL,
            rut VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'user') DEFAULT 'user',
            email_verified BOOLEAN DEFAULT FALSE,
            verification_token VARCHAR(100) NULL,
            last_login DATETIME NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_rut (rut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gift_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            event_type VARCHAR(100) DEFAULT NULL,
            event_date DATE NULL,
            beneficiary1 VARCHAR(255) DEFAULT NULL,
            beneficiary2 VARCHAR(255) DEFAULT NULL,
            unique_link VARCHAR(255) NOT NULL UNIQUE,
            qr_code_path VARCHAR(500) NULL,
            expiry_date DATE DEFAULT NULL,
            visibility ENUM('public', 'private', 'link_only') DEFAULT 'link_only',
            status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
            view_count INT DEFAULT 0,
            share_count INT DEFAULT 0,
            last_activity DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_visibility (visibility),
            INDEX idx_status (status),
            INDEX idx_unique_link (unique_link),
            INDEX idx_event_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gift_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            icon VARCHAR(50) DEFAULT 'gift',
            color VARCHAR(7) DEFAULT '#6c757d',
            is_active BOOLEAN DEFAULT TRUE,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS gifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gift_list_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            stock INT DEFAULT 0,
            sold_quantity INT DEFAULT 0,
            collected_amount DECIMAL(12,2) DEFAULT 0,
            category_id INT DEFAULT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            is_group_gift BOOLEAN DEFAULT 0,
            is_imaginative BOOLEAN DEFAULT 0,
            min_contribution DECIMAL(10,2) DEFAULT NULL,
            priority INT DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES gift_categories(id) ON DELETE SET NULL,
            INDEX idx_list (gift_list_id),
            INDEX idx_category (category_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($mainTables);
        $success[] = "✓ Tablas principales creadas";
        
        // ==================== TABLAS DE PAGOS MEJORADAS ====================
        $paymentTables = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gift_id INT NOT NULL,
            quantity INT DEFAULT 1,
            base_amount DECIMAL(12,2) NOT NULL COMMENT 'Monto sin fees',
            fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0 COMMENT 'Fee del 10%',
            total_amount DECIMAL(12,2) NOT NULL COMMENT 'Monto total pagado',
            buyer_email VARCHAR(255) NOT NULL,
            buyer_name VARCHAR(255) NOT NULL,
            buyer_phone VARCHAR(50) NULL,
            external_reference VARCHAR(100) NOT NULL UNIQUE,
            preference_id VARCHAR(100) NULL,
            payment_id VARCHAR(100) NULL,
            status ENUM('pending','approved','rejected','cancelled','refunded') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            payment_data JSON NULL,
            payout_status ENUM('pending','paid','cancelled') DEFAULT 'pending',
            payout_id INT NULL,
            payout_date DATETIME NULL,
            processed_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
            INDEX idx_status (status),
            INDEX idx_payout_status (payout_status),
            INDEX idx_external_ref (external_reference),
            INDEX idx_payment_id (payment_id),
            INDEX idx_buyer_email (buyer_email),
            INDEX idx_created (created_at),
            INDEX idx_payment_method (payment_method)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            total_amount DECIMAL(12,2) NOT NULL,
            fee_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            transaction_count INT NOT NULL DEFAULT 0,
            bank_name VARCHAR(255) NULL,
            bank_account VARCHAR(100) NULL,
            account_type VARCHAR(50) NULL,
            payout_method ENUM('bank_transfer','manual') DEFAULT 'bank_transfer',
            status ENUM('pending','processed','completed','failed') DEFAULT 'pending',
            payout_date DATE NOT NULL,
            processed_at DATETIME NULL,
            completed_at DATETIME NULL,
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_payout_date (payout_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NULL,
            event_type VARCHAR(50) NOT NULL,
            event_data JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
            INDEX idx_type (event_type),
            INDEX idx_transaction (transaction_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($paymentTables);
        $success[] = "✓ Tablas de pagos y payouts creadas";
        
        // ==================== TABLAS DE CONTENIDO DINÁMICO ====================
        $contentTables = "
        CREATE TABLE IF NOT EXISTS testimonials (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            gift_list_id INT NULL,
            transaction_id INT NULL,
            content TEXT NOT NULL,
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            is_featured BOOLEAN DEFAULT FALSE,
            approved_at DATETIME NULL,
            approved_by INT NULL,
            rejected_at DATETIME NULL,
            rejection_reason TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE SET NULL,
            FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_rating (rating),
            INDEX idx_featured (is_featured),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS faqs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            question TEXT NOT NULL,
            answer TEXT NOT NULL,
            order_index INT DEFAULT 0,
            status ENUM('active','inactive') DEFAULT 'active',
            view_count INT DEFAULT 0,
            helpful_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_category (category),
            INDEX idx_status (status),
            INDEX idx_order (order_index)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($contentTables);
        $success[] = "✓ Tablas de contenido dinámico creadas";
        
        // ==================== TABLAS ADMINISTRATIVAS ====================
        $adminTables = "
        CREATE TABLE IF NOT EXISTS admin_activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            target_type VARCHAR(50) NULL,
            target_id INT NULL,
            data JSON NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_admin (admin_id),
            INDEX idx_action (action_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS system_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            config_key VARCHAR(100) NOT NULL UNIQUE,
            config_value TEXT NULL,
            config_type ENUM('string','integer','boolean','json','float') DEFAULT 'string',
            description TEXT NULL,
            is_public BOOLEAN DEFAULT FALSE,
            updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_key (config_key),
            INDEX idx_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($adminTables);
        $success[] = "✓ Tablas administrativas creadas";
        
        // ==================== TABLAS DE ANALYTICS ====================
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
        
        // ==================== TABLAS DE NOTIFICACIONES ====================
        $notificationTables = "
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
            INDEX idx_recipient (recipient),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($notificationTables);
        $success[] = "✓ Tablas de notificaciones creadas";
        
        // ==================== TABLAS EXISTENTES DEL PROYECTO ====================
        $existingTables = "
        CREATE TABLE IF NOT EXISTS preset_product_lists (
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme VARCHAR(255) NOT NULL,
            description TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        
        CREATE TABLE IF NOT EXISTS preset_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            preset_list_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) DEFAULT 0,
            stock INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (preset_list_id) REFERENCES preset_product_lists(id) ON DELETE CASCADE,
            INDEX idx_preset_list (preset_list_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($existingTables);
        $success[] = "✓ Tablas de listas predeterminadas creadas";
        
    } catch (Exception $e) {
        $errors[] = "✗ Error creando tablas: " . $e->getMessage();
    }
    
    // ==================== DATOS INICIALES ====================
    if (empty($errors)) {
        try {
            // Crear usuario administrador
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@admin.com'");
            $stmt->execute();
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                $hashedPassword = password_hash("admin123", PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, lastname, phone, bank_name, account_type, bank_account, rut, email, password, role, email_verified, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin', TRUE, NOW())
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
        
        // Insertar categorías predeterminadas
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gift_categories");
            $stmt->execute();
            $categoryCount = $stmt->fetchColumn();
            
            if ($categoryCount == 0) {
                $categories = [
                    ['Electrónica', 'Dispositivos electrónicos y accesorios', 'laptop', '#4e73df', 1],
                    ['Hogar', 'Artículos para el hogar y decoración', 'house', '#1cc88a', 2],
                    ['Ropa y Accesorios', 'Prendas de vestir y accesorios', 'tshirt', '#36b9cc', 3],
                    ['Juguetes', 'Juguetes para niños de todas las edades', 'puzzle-piece', '#f6c23e', 4],
                    ['Belleza', 'Productos de belleza y cuidado personal', 'gem', '#e74a3b', 5],
                    ['Cocina', 'Utensilios y electrodomésticos de cocina', 'utensils', '#858796', 6],
                    ['Libros', 'Libros, e-books y audiolibros', 'book', '#5a5c69', 7],
                    ['Deportes', 'Artículos deportivos y fitness', 'dumbbell', '#2e59d9', 8],
                    ['Viajes', 'Experiencias de viaje y accesorios', 'plane', '#17a673', 9],
                    ['Experiencias', 'Experiencias y regalos imaginativos', 'heart', '#fd79a8', 10],
                    ['Otros', 'Otros productos no categorizados', 'gift', '#6c757d', 99]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO gift_categories (name, description, icon, color, sort_order) VALUES (?, ?, ?, ?, ?)");
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
        
        // Insertar FAQs iniciales
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faqs");
            $stmt->execute();
            $faqCount = $stmt->fetchColumn();
            
            if ($faqCount == 0) {
                $faqs = [
                    ['general', '¿Cómo funciona?', 'Elige un evento, crea tu lista con regalos que te representen, comparte esa lista con tus invitados y recibe el dinero en tu cuenta por cada regalo que tus invitados compren.', 1],
                    ['general', '¿Puedo crear cualquier deseo?', 'Sí, tu lista puede incluir lo que quieras. Un viaje a la luna, un concierto de tu banda favorita o simplemente un abrazo. Lo que esté al alcance de tu imaginación.', 2],
                    ['pagos', '¿Cuánto cuesta el servicio?', 'Ninguno, es absolutamente gratuito. Si tu regalo vale $50.000 y alguien lo compra, entonces tu recibes $50.000. El servicio no tiene costos para los festejados.', 3],
                    ['pagos', '¿Cómo cobran entonces?', 'Nosotros cobramos un 10% extra asociado a cada regalo, pero ese costo va incluido en el pago. Es un extra, no dinero que sacamos de tu regalo. En ese costo se incluyen también los cargos de Transbank.', 4],
                    ['pagos', '¿Cuándo recibo mi dinero?', 'Los días miércoles, cada dos semanas, nuestro equipo depositará en tu cuenta el dinero acumulado hasta las 14:00 horas del día lunes.', 5],
                    ['listas', '¿Cómo encuentran mi lista?', 'Para encontrar la lista deberán usar el buscador que se encuentra en la portada del sitio y escribir el nombre o el apellido de alguno de los festejados.', 6],
                    ['listas', '¿Por cuánto tiempo está activa mi lista?', 'La lista se activa inmediatamente cuando la creen. Sobre la duración, no hay fecha límite. Seguirá activa después del evento por lo que podrán seguir recibiendo regalos.', 7]
                ];
                
                $stmt = $pdo->prepare("INSERT INTO faqs (category, question, answer, order_index) VALUES (?, ?, ?, ?)");
                foreach ($faqs as $faq) {
                    $stmt->execute($faq);
                }
                $success[] = "✓ FAQs iniciales creadas (" . count($faqs) . ")";
            } else {
                $warnings[] = "⚠ FAQs ya existen";
            }
        } catch (Exception $e) {
            $warnings[] = "⚠ No se pudieron crear las FAQs: " . $e->getMessage();
        }
        
        // Insertar configuraciones del sistema
        try {
            $defaultConfigs = [
                ['app_name', 'Mi Lista de Regalos', 'string', 'Nombre de la aplicación', true],
                ['app_version', '2.1', 'string', 'Versión de la aplicación', true],
                ['maintenance_mode', '0', 'boolean', 'Modo mantenimiento activado', false],
                ['fees_percentage', '10', 'float', 'Porcentaje de fees (10%)', false],
                ['payout_minimum', '1000', 'float', 'Monto mínimo para payout (CLP)', false],
                ['payout_schedule', 'biweekly_wednesday', 'string', 'Programación de payouts', false],
                ['email_notifications', '1', 'boolean', 'Notificaciones por email habilitadas', false],
                ['sms_notifications', '0', 'boolean', 'Notificaciones por SMS habilitadas', false],
                ['qr_codes_enabled', '1', 'boolean', 'Códigos QR habilitados', false],
                ['testimonial_moderation', '1', 'boolean', 'Moderación de testimonios requerida', false],
                ['max_lists_per_user', '20', 'integer', 'Máximo de listas por usuario', false],
                ['max_gifts_per_list', '100', 'integer', 'Máximo de regalos por lista', false]
            ];
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO system_config (config_key, config_value, config_type, description, is_public) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $configCount = 0;
            foreach ($defaultConfigs as $configItem) {
                if ($stmt->execute($configItem)) {
                    $configCount++;
                }
            }
            
            if ($configCount > 0) {
                $success[] = "✓ Configuraciones del sistema creadas (" . $configCount . ")";
            } else {
                $warnings[] = "⚠ Configuraciones ya existen";
            }
        } catch (Exception $e) {
            $warnings[] = "⚠ No se pudieron crear las configuraciones: " . $e->getMessage();
        }
    }
    
    // ==================== CREAR DIRECTORIOS ====================
    $directories = [
        'uploads',
        'uploads/images',
        'uploads/qr-codes',
        'uploads/temp',
        'cache',
        'logs',
        'exports',
        'backups',
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
    
    // ==================== ARCHIVOS DE SEGURIDAD ====================
    $securityFiles = [
        'uploads/.htaccess' => "deny from all\nOptions -Indexes\n<FilesMatch '\\.(jpg|jpeg|png|gif|webp)$'>\n    allow from all\n</FilesMatch>",
        'logs/.htaccess' => "deny from all\nOptions -Indexes",
        'cache/.htaccess' => "deny from all\nOptions -Indexes",
        'config/.htaccess' => "deny from all\nOptions -Indexes",
        'uploads/qr-codes/.htaccess' => "Options -Indexes\n<FilesMatch '\\.(png|jpg|jpeg)$'>\n    allow from all\n</FilesMatch>",
        'backups/.htaccess' => "deny from all\nOptions -Indexes"
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
    
    // ==================== FINALIZAR INSTALACIÓN ====================
    if (empty($errors)) {
        $installInfo = [
            'installed_at' => date('Y-m-d H:i:s'),
            'version' => '2.1',
            'php_version' => PHP_VERSION,
            'mysql_version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
            'features' => [
                'payments' => 'MercadoPago + Transbank',
                'fees' => '10% incluido en pagos',
                'payouts' => 'Quincenales automatizados',
                'notifications' => 'Email + SMS',
                'analytics' => 'Métricas avanzadas',
                'admin_panel' => 'Panel avanzado con gráficos',
                'testimonials' => 'Sistema de moderación',
                'qr_codes' => 'Generación automática'
            ]
        ];
        
        if (file_put_contents('.installed', json_encode($installInfo, JSON_PRETTY_PRINT))) {
            $success[] = "✓ Instalación completada exitosamente";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Mi Lista de Regalos v2.1</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .install-container { 
            max-width: 900px; 
            margin: 30px auto; 
            padding: 0 15px;
        }
        .install-card { 
            background: white; 
            border-radius: 20px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.3); 
            overflow: hidden;
        }
        .install-header { 
            background: linear-gradient(45deg, #667eea, #764ba2); 
            color: white; 
            padding: 40px 30px; 
            text-align: center;
        }
        .status-item { 
            padding: 12px 15px; 
            margin: 8px 0; 
            border-radius: 8px; 
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .status-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745;
        }
        .status-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545;
        }
        .status-warning { 
            background: #fff3cd; 
            color: #856404; 
            border-left: 4px solid #ffc107;
        }
        .feature-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
            gap: 20px; 
            margin: 30px 0;
        }
        .feature-card { 
            text-align: center; 
            padding: 25px 20px; 
            background: #f8f9fa; 
            border-radius: 15px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        .version-badge {
            background: rgba(255,255,255,0.2);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: inline-block;
            margin-top: 10px;
        }
        .credentials-box {
            background: #f8f9fa;
            border: 2px dashed #6c757d;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container install-container">
        <div class="install-card">
            <div class="install-header">
                <h1><i class="fas fa-gift me-3"></i>Mi Lista de Regalos</h1>
                <p class="mb-2">Sistema Completo de Listas de Deseos</p>
                <div class="version-badge">
                    <i class="fas fa-code-branch me-1"></i>Versión 2.1 - Estilo milistaderegalos.cl
                </div>
            </div>
            
            <div class="p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores de Instalación</h5>
                        <p class="mb-3">Se encontraron errores que impiden completar la instalación:</p>
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
                        <h2 class="text-success">
                            <i class="fas fa-check-circle me-2"></i>
                            ¡Instalación Completada Exitosamente!
                        </h2>
                        <p class="text-muted">Tu sistema de listas de deseos está listo para usar</p>
                    </div>
                    
                    <div class="feature-grid">
                        <div class="feature-card">
                            <i class="fas fa-credit-card fa-3x text-primary mb-3"></i>
                            <h6>Pagos Duales</h6>
                            <small>MercadoPago + Transbank con fees del 10%</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-money-bill-wave fa-3x text-success mb-3"></i>
                            <h6>Payouts Quincenales</h6>
                            <small>Depósitos automáticos cada 2 miércoles</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-star fa-3x text-warning mb-3"></i>
                            <h6>Testimonios</h6>
                            <small>Sistema de moderación integrado</small>
                        </div>
                        <div class="feature-card">
                            <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                            <h6>Analytics Avanzados</h6>
                            <small>Métricas y reportes detallados</small>
                        </div>
                    </div>
                    
                    <div class="credentials-box">
                        <h5><i class="fas fa-user-shield me-2 text-primary"></i>Credenciales de Administrador</h5>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <strong>Email:</strong> <code>admin@admin.com</code>
                            </div>
                            <div class="col-md-6">
                                <strong>Contraseña:</strong> <code>admin123</code>
                            </div>
                        </div>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡IMPORTANTE!</strong> Cambia estas credenciales inmediatamente después del primer acceso.
                        </div>
                    </div>
                    
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-shield-alt me-2"></i>Pasos de Seguridad Obligatorios</h6>
                        <ol class="mb-2">
                            <li><strong>Elimina este archivo</strong> <code>install.php</code> inmediatamente</li>
                            <li>Cambia las credenciales del administrador</li>
                            <li>Configura MercadoPago y Transbank en <code>config/config.php</code></li>
                            <li>Configura SMTP para notificaciones por email</li>
                            <li>Establece webhooks en MercadoPago y Transbank</li>
                        </ol>
                        <div class="text-center mt-3">
                            <button class="btn btn-danger" onclick="deleteInstaller()">
                                <i class="fas fa-trash me-2"></i>Eliminar Instalador Ahora
                            </button>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="fas fa-cog me-2"></i>Configuración Adicional</h6>
                        <ul class="mb-0">
                            <li>Webhook MercadoPago: <code><?php echo ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/public/webhook_mercadopago.php</code></li>
                            <li>Return URL Transbank: <code><?php echo ($_SERVER['HTTPS'] ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']; ?>/public/transbank_return.php</code></li>
                            <li>Cron para payouts: <code>0 14 * * 3 php <?php echo __DIR__; ?>/scripts/process_payouts.php</code></li>
                        </ul>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="public/" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-home me-2"></i>Ir al Sitio Web
                        </a>
                        <a href="public/admin/" class="btn btn-secondary btn-lg">
                            <i class="fas fa-user-shield me-2"></i>Panel de Administración
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center mt-4">
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-times-circle me-2"></i>Instalación Fallida</h5>
                            <p>Por favor, corrige los errores mostrados arriba y vuelve a intentar.</p>
                        </div>
                        <a href="?force=1" class="btn btn-warning btn-lg">
                            <i class="fas fa-redo me-2"></i>Reintentar Instalación
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-light p-3 text-center">
                <small class="text-muted">
                    <i class="fas fa-server me-1"></i>
                    Optimizado para Hosting Compartido | PHP <?= PHP_VERSION ?> | 
                    <i class="fas fa-heart me-1 text-danger"></i> Desarrollado con amor
                </small>
            </div>
        </div>
    </div>

    <script>
    function deleteInstaller() {
        if (confirm('¿Estás seguro de que quieres eliminar el instalador? Esta acción no se puede deshacer.')) {
            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'delete_installer=1'
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('success')) {
                    alert('Instalador eliminado exitosamente. Serás redirigido al sitio principal.');
                    window.location.href = 'public/';
                } else {
                    alert('Error al eliminar el instalador. Por favor, elimínalo manualmente.');
                }
            })
            .catch(error => {
                alert('Error: ' + error.message + '. Por favor, elimina el archivo install.php manualmente.');
            });
        }
    }
    </script>
</body>
</html>

<?php
// Manejar eliminación del instalador
if (isset($_POST['delete_installer'])) {
    if (unlink(__FILE__)) {
        echo 'success';
    } else {
        echo 'error';
    }
    exit;
}
?>