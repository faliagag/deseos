<?php
/**
 * CONEXIÓN A BASE DE DATOS MEJORADA
 * Compatible con nuevos modelos y funcionalidades
 */

// Cargar configuración
$config = require_once __DIR__ . '/../config/config.php';
$dbConfig = $config['database'];

// Construir DSN para PDO
$host    = $dbConfig['host'];
$db      = $dbConfig['name'];
$user    = $dbConfig['user'];
$pass    = $dbConfig['pass'];
$charset = $dbConfig['charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false, // No usar conexiones persistentes en hosting compartido
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    // Configurar timezone
    if (isset($config['application']['timezone'])) {
        $pdo->exec("SET time_zone = '+00:00'"); // UTC en DB, convertir en PHP
        date_default_timezone_set($config['application']['timezone']);
    }
    
} catch (PDOException $e) {
    // Log error si el directorio existe
    if (is_dir(__DIR__ . '/../logs')) {
        error_log("DB Connection Error: " . $e->getMessage(), 3, __DIR__ . '/../logs/db_errors.log');
    }
    
    // En producción, no mostrar detalles del error
    if (($config['application']['debug'] ?? false) === false) {
        die("Error en la conexión a la base de datos. Por favor, contacte al administrador.");
    } else {
        die("Error de conexión: " . $e->getMessage());
    }
}

/**
 * Función global para obtener la conexión (compatibilidad con modelos)
 */
function getConnection() {
    global $pdo;
    return $pdo;
}

/**
 * Función global para ejecutar consultas con manejo de errores mejorado
 */
function executeQuery($query, $params = [], $returnType = 'all') {
    global $pdo, $config;
    
    try {
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($params);
        
        if (!$success) {
            throw new PDOException('Query execution failed');
        }
        
        switch ($returnType) {
            case 'fetch':
                return $stmt->fetch(PDO::FETCH_ASSOC);
            case 'fetchAll':
            case 'all':
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            case 'count':
                return $stmt->rowCount();
            case 'lastId':
                return $pdo->lastInsertId();
            case 'statement':
                return $stmt;
            default:
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        // Log error
        if (is_dir(__DIR__ . '/../logs')) {
            $errorData = [
                'query' => $query,
                'params' => $params,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
            error_log(json_encode($errorData) . "\n", 3, __DIR__ . '/../logs/sql_errors.log');
        }
        
        // En modo debug, mostrar error
        if (($config['application']['debug'] ?? false) === true) {
            throw $e;
        }
        
        // En producción, retornar false
        return false;
    }
}

/**
 * Verificar si una tabla existe
 */
function tableExists($tableName) {
    global $pdo;
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$tableName'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verificar integridad de la base de datos
 */
function checkDatabaseIntegrity() {
    $requiredTables = [
        'users', 'gift_lists', 'gifts', 'gift_categories',
        'transactions', 'payment_logs', 'testimonials', 'payouts',
        'faqs', 'admin_activity_logs', 'system_config',
        'analytics_events', 'notification_logs'
    ];
    
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!tableExists($table)) {
            $missingTables[] = $table;
        }
    }
    
    return $missingTables;
}
?>