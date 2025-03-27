<?php
// install.php
// Este script crea (o actualiza) las tablas necesarias en la base de datos y
// crea un usuario administrador (admin@admin.com / admin) si no existe.
// Una vez instalado, elimina o protege este archivo.
require_once __DIR__ . '/includes/db.php';

try {
    $sql = "
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS gift_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        event_type VARCHAR(50) DEFAULT NULL,
        beneficiary1 VARCHAR(255) DEFAULT NULL,
        beneficiary2 VARCHAR(255) DEFAULT NULL,
        preset_theme INT DEFAULT NULL,
        unique_link VARCHAR(255) NOT NULL UNIQUE,
        expiry_date DATE DEFAULT NULL,
        visibility ENUM('public', 'private', 'link_only') DEFAULT 'link_only',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    -- Tabla de categorías de regalos
    CREATE TABLE IF NOT EXISTS gift_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        icon VARCHAR(50) DEFAULT 'gift', -- Referencia a un icono de Bootstrap Icons
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS gifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gift_list_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        stock INT DEFAULT 0,
        sold INT DEFAULT 0,
        contributed DECIMAL(10,2) DEFAULT 0,
        category_id INT DEFAULT NULL,
        image_url VARCHAR(255) DEFAULT NULL,
        is_group_gift BOOLEAN DEFAULT 0,
        min_contribution DECIMAL(10,2) DEFAULT NULL,
        reserved_until DATETIME DEFAULT NULL,
        reserved_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES gift_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (reserved_by) REFERENCES users(id) ON DELETE SET NULL
    );
    
    CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gift_list_id INT NOT NULL,
        gift_id INT,
        user_id INT,
        amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) DEFAULT 'usd',
        status ENUM('pending','succeeded','failed') DEFAULT 'pending',
        metadata TEXT,
        is_group_contribution BOOLEAN DEFAULT 0,
        group_gift_id INT DEFAULT NULL,
        thanked BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
        FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    );
    
    -- Tabla para regalos grupales (crowdfunding)
    CREATE TABLE IF NOT EXISTS group_gifts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gift_id INT NOT NULL,
        target_amount DECIMAL(10,2) NOT NULL,
        current_amount DECIMAL(10,2) DEFAULT 0,
        min_contribution DECIMAL(10,2) DEFAULT 0,
        status ENUM('active','completed','expired') DEFAULT 'active',
        expiry_date DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE
    );
    
    -- Tabla para contribuciones a regalos grupales
    CREATE TABLE IF NOT EXISTS group_gift_contributions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_gift_id INT NOT NULL,
        user_id INT,
        transaction_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (group_gift_id) REFERENCES group_gifts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
    );
    
    -- Tabla para reservas de regalos
    CREATE TABLE IF NOT EXISTS gift_reservations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        gift_id INT NOT NULL,
        user_id INT NOT NULL,
        quantity INT DEFAULT 1,
        reserved_until DATETIME NOT NULL,
        status ENUM('active','completed','expired','cancelled') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    -- Tabla para agradecimientos
    CREATE TABLE IF NOT EXISTS thank_you_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id INT NOT NULL,
        sender_id INT NOT NULL,
        recipient_id INT NOT NULL,
        message TEXT,
        read_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    -- Tabla de notificaciones
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT,
        link VARCHAR(255) DEFAULT NULL,
        type ENUM('transaction', 'reservation', 'thank_you', 'expiry', 'system') DEFAULT 'system',
        is_read BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    
    CREATE TABLE IF NOT EXISTS preset_product_lists (
        id INT AUTO_INCREMENT PRIMARY KEY,
        theme VARCHAR(255) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    CREATE TABLE IF NOT EXISTS preset_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        preset_list_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) DEFAULT 0,
        stock INT DEFAULT 0,
        FOREIGN KEY (preset_list_id) REFERENCES preset_product_lists(id) ON DELETE CASCADE
    );
    ";
    $pdo->exec($sql);
    echo "Instalación: Tablas creadas o actualizadas.<br>";

    // Crear usuario administrador si no existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@admin.com'");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();

    if ($adminCount == 0) {
        $hashedPassword = password_hash("admin", PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, lastname, phone, bank, account_type, account_number, rut, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'admin', NOW())");
        // Ejemplo de datos para el usuario administrador:
        $stmt->execute(["admin", "admin", "0000000000", "Banco Ejemplo", "Corriente", "123456789", "12345678-9", "admin@admin.com", $hashedPassword]);
        echo "Instalación: Usuario administrador (admin@admin.com / admin) creado exitosamente.<br>";
    } else {
        echo "Instalación: El usuario administrador ya existe.<br>";
    }
    
    // Insertar categorías predeterminadas si no existen
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gift_categories");
    $stmt->execute();
    $categoryCount = $stmt->fetchColumn();
    
    if ($categoryCount == 0) {
        $categories = [
            ['Electrónica', 'Dispositivos electrónicos y accesorios', 'laptop'],
            ['Hogar', 'Artículos para el hogar y decoración', 'house'],
            ['Ropa', 'Prendas de vestir y accesorios', 'bag'],
            ['Juguetes', 'Juguetes para niños de todas las edades', 'controller'],
            ['Belleza', 'Productos de belleza y cuidado personal', 'gem'],
            ['Cocina', 'Utensilios y electrodomésticos de cocina', 'cup-hot'],
            ['Libros', 'Libros, e-books y audiolibros', 'book'],
            ['Deportes', 'Artículos deportivos y fitness', 'bicycle'],
            ['Viajes', 'Experiencias de viaje y accesorios', 'airplane'],
            ['Mascotas', 'Productos para mascotas', 'bug']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO gift_categories (name, description, icon) VALUES (?, ?, ?)");
        foreach ($categories as $category) {
            $stmt->execute($category);
        }
        
        echo "Instalación: Categorías predeterminadas creadas exitosamente.<br>";
    }
    
    echo "<br>Instalación completada exitosamente.";
} catch (Exception $e) {
    echo "Error en la instalación: " . $e->getMessage();
}