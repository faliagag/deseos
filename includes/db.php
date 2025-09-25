<?php
/**
 * CONEXIÓN A BASE DE DATOS MEJORADA - VERSIÓN 2.1
 * Compatible con hosting compartido y nuevas funcionalidades
 * 
 * Credenciales actualizadas:
 * - Base de datos: misdeseo_web  
 * - Usuario: misdeseo_web
 * - Contraseña: Aliaga.2018
 */

// Cargar configuración
$config = require_once __DIR__ . '/../config/config.php';
$dbConfig = $config['database'];

// Construir DSN para PDO
$host = $dbConfig['host'];
$db = $dbConfig['name'];
$user = $dbConfig['user'];
$pass = $dbConfig['pass'];
$charset = $dbConfig['charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false, // Evitar conexiones persistentes en hosting compartido
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset COLLATE {$charset}_unicode_ci"
];

/**
 * Función global getConnection() requerida por install.php y modelos
 */
function getConnection() {
    global $dsn, $user, $pass, $options, $config;
    
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Configurar zona horaria según configuración
        if (isset($config['application']['timezone'])) {
            date_default_timezone_set($config['application']['timezone']);
            $pdo->exec("SET time_zone = '-03:00'"); // Chile UTC-3
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error si el sistema de logs está disponible
        if (file_exists(__DIR__ . '/ErrorHandler.php')) {
            require_once __DIR__ . '/ErrorHandler.php';
            ErrorHandler::logError("Error de conexión a base de datos", [
                'message' => $e->getMessage(),
                'host' => $host,
                'database' => $db,
                'user' => $user,
                'code' => $e->getCode()
            ]);
        } else {
            error_log("DB Connection Error: " . $e->getMessage());
        }
        
        // En producción, mostrar error amigable
        if (($config['application']['environment'] ?? 'development') === 'production') {
            header('HTTP/1.1 503 Service Temporarily Unavailable');
            header('Status: 503 Service Temporarily Unavailable');
            header('Retry-After: 300');
            
            if (file_exists(__DIR__ . '/../templates/errors/database_error.html')) {
                include __DIR__ . '/../templates/errors/database_error.html';
            } else {
                echo '<!DOCTYPE html><html><head><title>Error de Conexión</title></head><body style="font-family:Arial;text-align:center;padding:50px;"><h1>Servicio Temporalmente No Disponible</h1><p>Estamos experimentando problemas técnicos. Por favor, intente más tarde.</p></body></html>';
            }
            exit;
        } else {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
}

// Crear conexión global principal
try {
    $pdo = getConnection();
    
    // Verificar que estamos conectados a la base de datos correcta
    $dbCheck = $pdo->query("SELECT DATABASE() as current_db")->fetch(PDO::FETCH_ASSOC);
    if ($dbCheck['current_db'] !== 'misdeseo_web') {
        throw new Exception("Conectado a base de datos incorrecta: {$dbCheck['current_db']}. Esperado: misdeseo_web");
    }
    
} catch (Exception $e) {
    // Cargar manejador de errores si está disponible
    if (file_exists(__DIR__ . '/ErrorHandler.php')) {
        require_once __DIR__ . '/ErrorHandler.php';
        ErrorHandler::logError("Error de conexión crítica", [
            'message' => $e->getMessage(),
            'file' => __FILE__,
            'line' => __LINE__
        ]);
    }
    
    // Error crítico - detener ejecución
    if (($config['application']['debug'] ?? false) === true) {
        die("Error crítico: " . $e->getMessage());
    } else {
        die("Error en la conexión a la base de datos. Por favor, contacte al administrador.");
    }
}

/**
 * Función mejorada para ejecutar consultas con logging
 */
function executeQuery($query, $params = [], $returnType = 'fetchAll') {
    global $pdo, $config;
    
    try {
        $startTime = microtime(true);
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($params);
        $executionTime = microtime(true) - $startTime;
        
        // Log consultas lentas si está habilitado
        if (($config['logging']['log_slow_queries'] ?? false) && 
            $executionTime > ($config['logging']['slow_query_threshold'] ?? 1.0)) {
            
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError("Consulta lenta detectada", [
                    'query' => $query,
                    'execution_time' => $executionTime,
                    'params' => $params
                ], 'WARNING');
            }
        }
        
        if (!$success) {
            throw new PDOException('Query execution failed');
        }
        
        // Retornar según el tipo solicitado
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
            case 'bool':
                return true;
            default:
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        // Log error
        if (class_exists('ErrorHandler')) {
            ErrorHandler::logError("Error en consulta SQL", [
                'query' => $query,
                'params' => $params,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => debug_backtrace()[1]['file'] ?? 'unknown',
                'line' => debug_backtrace()[1]['line'] ?? 'unknown'
            ]);
        } else {
            error_log("SQL Error: " . $e->getMessage() . " | Query: " . $query);
        }
        
        // En modo debug, relanzar excepción
        if (($config['application']['debug'] ?? false) === true) {
            throw $e;
        }
        
        // En producción, retornar false o array vacío
        switch ($returnType) {
            case 'fetch':
                return false;
            case 'fetchAll':
            case 'all':
                return [];
            case 'count':
            case 'lastId':
                return 0;
            case 'bool':
                return false;
            default:
                return [];
        }
    }
}

/**
 * Ejecutar transacción con múltiples consultas
 */
function executeTransaction($queries) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        $results = [];
        
        foreach ($queries as $queryData) {
            $query = $queryData['sql'];
            $params = $queryData['params'] ?? [];
            $returnType = $queryData['return'] ?? 'bool';
            
            $result = executeQuery($query, $params, $returnType);
            if ($result === false && $returnType !== 'bool') {
                throw new Exception("Error en transacción: consulta fallida");
            }
            
            $results[] = $result;
        }
        
        $pdo->commit();
        return ['success' => true, 'results' => $results];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        
        if (class_exists('ErrorHandler')) {
            ErrorHandler::logError("Error en transacción", [
                'message' => $e->getMessage(),
                'queries_count' => count($queries)
            ]);
        }
        
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verificar si una tabla existe
 */
function tableExists($tableName) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() > 0;
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
        'analytics_events', 'notification_logs', 'user_activity'
    ];
    
    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!tableExists($table)) {
            $missingTables[] = $table;
        }
    }
    
    return [
        'complete' => empty($missingTables),
        'missing_tables' => $missingTables,
        'existing_tables' => array_diff($requiredTables, $missingTables)
    ];
}

/**
 * Verificar salud de la conexión
 */
function checkDatabaseHealth() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT 1 as health_check");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['health_check'] === 1;
    } catch (PDOException $e) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::logError("Database health check failed", [
                'message' => $e->getMessage()
            ]);
        }
        return false;
    }
}

/**
 * Optimizar tablas (ejecutar periódicamente)
 */
function optimizeTables() {
    global $pdo;
    try {
        // Obtener todas las tablas de la base de datos
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $pdo->exec("OPTIMIZE TABLE `$table`");
        }
        
        return true;
    } catch (Exception $e) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::logError("Error optimizando tablas", [
                'message' => $e->getMessage()
            ]);
        }
        return false;
    }
}

/**
 * Limpiar datos antiguos (mantenimiento automático)
 */
function cleanupOldData() {
    global $pdo, $config;
    
    $retentionDays = $config['analytics']['retention_days'] ?? 365;
    
    try {
        $cleanupQueries = [
            "DELETE FROM analytics_events WHERE created_at < DATE_SUB(NOW(), INTERVAL $retentionDays DAY)",
            "DELETE FROM payment_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $retentionDays DAY)",
            "DELETE FROM notification_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) AND status = 'sent'",
            "DELETE FROM page_visits WHERE visited_at < DATE_SUB(NOW(), INTERVAL 180 DAY)"
        ];
        
        $totalDeleted = 0;
        foreach ($cleanupQueries as $query) {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $totalDeleted += $stmt->rowCount();
        }
        
        if ($totalDeleted > 0 && class_exists('ErrorHandler')) {
            ErrorHandler::logError("Limpieza de datos completada", [
                'deleted_records' => $totalDeleted
            ], 'INFO');
        }
        
        return $totalDeleted;
        
    } catch (Exception $e) {
        if (class_exists('ErrorHandler')) {
            ErrorHandler::logError("Error en limpieza de datos", [
                'message' => $e->getMessage()
            ]);
        }
        return 0;
    }
}

// Auto-inicializar manejador de errores si existe
if (file_exists(__DIR__ . '/ErrorHandler.php')) {
    require_once __DIR__ . '/ErrorHandler.php';
    if (!defined('ERROR_HANDLER_INITIALIZED')) {
        ErrorHandler::init(__DIR__ . '/../logs');
        define('ERROR_HANDLER_INITIALIZED', true);
    }
}
?>