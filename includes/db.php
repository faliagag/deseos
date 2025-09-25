<?php
/**
 * CONEXIÓN SIMPLE Y ROBUSTA PARA HOSTING COMPARTIDO
 * Base de datos: misdeseo_web
 * Usuario: misdeseo_web
 * Contraseña: Aliaga.2018
 */

// Configuración directa de base de datos (más confiable para hosting compartido)
$DB_HOST = 'localhost';
$DB_NAME = 'misdeseo_web';
$DB_USER = 'misdeseo_web';
$DB_PASS = 'Aliaga.2018';
$DB_CHARSET = 'utf8mb4';

// DSN y opciones optimizadas para hosting compartido
$dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=$DB_CHARSET";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false // Nunca usar conexiones persistentes en hosting compartido
];

/**
 * Función getConnection() simplificada y robusta
 */
function getConnection() {
    global $dsn, $DB_USER, $DB_PASS, $options;
    
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
        
        // Configuraciones básicas
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET time_zone = '-03:00'"); // Zona horaria Chile
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log del error
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] DB Error: " . $e->getMessage() . "\n";
        
        // Intentar escribir log si el directorio existe
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        if (is_writable($logDir)) {
            file_put_contents($logDir . '/connection_errors.log', $errorMessage, FILE_APPEND | LOCK_EX);
        }
        
        // Usar error_log como fallback
        error_log($errorMessage);
        
        // Mostrar error detallado para debugging
        $debugInfo = [
            'Host: ' . $dsn,
            'Usuario: ' . $DB_USER,
            'Error: ' . $e->getMessage(),
            'Código: ' . $e->getCode(),
            'Archivo: ' . $e->getFile() . ':' . $e->getLine()
        ];
        
        die("<h3>Error de Conexión a Base de Datos</h3><pre>" . implode("\n", $debugInfo) . "</pre><p><strong>Pasos para solucionarlo:</strong><br>1. Verificar que la base de datos 'misdeseo_web' existe<br>2. Verificar que el usuario 'misdeseo_web' tiene permisos<br>3. Verificar que la contraseña 'Aliaga.2018' es correcta<br>4. Contactar al proveedor de hosting si el problema persiste</p>");
    }
}

// Intentar crear conexión global
try {
    $pdo = getConnection();
    
    // Verificación adicional de la conexión
    $test = $pdo->query("SELECT 'OK' as status")->fetch();
    if ($test['status'] !== 'OK') {
        throw new Exception("La conexión no responde correctamente");
    }
    
} catch (Exception $e) {
    // Si falla la conexión global, registrar y continuar
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] Global Connection Failed: " . $e->getMessage() . "\n";
    error_log($errorMessage);
    
    // En este caso, $pdo será null y cada archivo deberá crear su propia conexión
    $pdo = null;
}

/**
 * Función simplificada para ejecutar consultas
 */
function executeQuery($query, $params = [], $returnType = 'fetchAll') {
    global $pdo;
    
    // Si no hay conexión global, crear una nueva
    if ($pdo === null) {
        $pdo = getConnection();
    }
    
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        switch ($returnType) {
            case 'fetch':
                return $stmt->fetch(PDO::FETCH_ASSOC);
            case 'fetchAll':
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            case 'count':
                return $stmt->rowCount();
            case 'lastId':
                return $pdo->lastInsertId();
            case 'bool':
                return true;
            default:
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
    } catch (PDOException $e) {
        $errorMessage = "[" . date('Y-m-d H:i:s') . "] SQL Error: " . $e->getMessage() . " | Query: " . $query . "\n";
        error_log($errorMessage);
        
        // Retornar valor por defecto según el tipo
        switch ($returnType) {
            case 'fetch':
                return false;
            case 'fetchAll':
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
 * Verificar si una tabla existe (método simple)
 */
function tableExists($tableName) {
    try {
        $pdo = getConnection();
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Test de conexión simple
 */
function testConnection() {
    try {
        $pdo = getConnection();
        $result = $pdo->query("SELECT 'Conexión exitosa' as message, NOW() as timestamp, DATABASE() as db_name")->fetch();
        return [
            'success' => true,
            'message' => $result['message'],
            'timestamp' => $result['timestamp'],
            'database' => $result['db_name']
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ];
    }
}
?>