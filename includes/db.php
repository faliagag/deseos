<?php
/**
 * CONEXIÓN BÁSICA Y DIRECTA A BASE DE DATOS
 * Versión ultracompatible para hosting compartido
 */

// Configuración directa (sin archivos externos)
$DB_HOST = 'localhost';
$DB_NAME = 'misdeseo_web';
$DB_USER = 'misdeseo_web';
$DB_PASS = 'Aliaga.2018';
$DB_CHARSET = 'utf8mb4';

// Función getConnection() requerida por install.php
function getConnection() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    
    try {
        $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        // Log del error con detalles completos
        $errorDetails = [
            'Mensaje: ' . $e->getMessage(),
            'Código: ' . $e->getCode(),
            'Host: ' . $DB_HOST,
            'Base de datos: ' . $DB_NAME,
            'Usuario: ' . $DB_USER,
            'Archivo: ' . __FILE__,
            'Línea: ' . __LINE__,
            'Fecha: ' . date('Y-m-d H:i:s')
        ];
        
        // Escribir error a log
        error_log("DB CONNECTION ERROR: " . implode(' | ', $errorDetails));
        
        throw $e; // Relanzar la excepción
    }
}

// Crear conexión global
try {
    $pdo = getConnection();
} catch (Exception $e) {
    $pdo = null; // Marcar como fallida
    
    // Error crítico - mostrar detalles para debugging
    die("<h3>Error de Conexión a Base de Datos</h3>
         <p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
         <p><strong>Host:</strong> $DB_HOST</p>
         <p><strong>Base de datos:</strong> $DB_NAME</p>
         <p><strong>Usuario:</strong> $DB_USER</p>
         <p><strong>Error MySQL:</strong> " . $e->getCode() . "</p>
         <hr>
         <h4>Pasos para solucionar:</h4>
         <ol>
            <li>Verificar que la base de datos '$DB_NAME' existe en cPanel/phpMyAdmin</li>
            <li>Verificar que el usuario '$DB_USER' tiene permisos completos</li>
            <li>Verificar que la contraseña 'Aliaga.2018' es correcta</li>
            <li>Contactar al proveedor de hosting si el problema persiste</li>
         </ol>
         <p><a href='test_connection.php'>Ejecutar prueba de conexión detallada</a></p>");
}

// Función simple para ejecutar consultas
function executeQuery($query, $params = []) {
    global $pdo;
    
    if ($pdo === null) {
        $pdo = getConnection();
    }
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("SQL Error: " . $e->getMessage() . " | Query: " . $query);
        throw $e;
    }
}
?>