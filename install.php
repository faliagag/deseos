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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE
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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gift_list_id) REFERENCES gift_lists(id) ON DELETE CASCADE,
        FOREIGN KEY (gift_id) REFERENCES gifts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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
    echo "<br>Instalación completada exitosamente.";
} catch (Exception $e) {
    echo "Error en la instalación: " . $e->getMessage();
}