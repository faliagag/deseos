<?php
/**
 * DIAGNÓSTICO COMPLETO DE CONEXIÓN
 * Ejecutar este archivo para identificar el problema exacto
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Test de Conexión - Mis Deseos</title><style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; }
body { font-family: Arial, sans-serif; margin: 20px; }
</style></head><body>";

echo "<h1>Diagnóstico de Conexión - Mis Deseos</h1>";
echo "<p>Fecha: " . date('d/m/Y H:i:s') . "</p><hr>";

// Test 1: Verificar extensión PDO
echo "<h2>1. Verificación de Extensiones PHP</h2>";
if (extension_loaded('pdo')) {
    echo "<p class='success'>✓ PDO está disponible</p>";
} else {
    echo "<p class='error'>✗ PDO NO está disponible - contactar hosting</p>";
}

if (extension_loaded('pdo_mysql')) {
    echo "<p class='success'>✓ PDO MySQL está disponible</p>";
} else {
    echo "<p class='error'>✗ PDO MySQL NO está disponible - contactar hosting</p>";
}

echo "<p class='info'>Versión PHP: " . PHP_VERSION . "</p>";

// Test 2: Conexión paso a paso
echo "<h2>2. Prueba de Conexión Paso a Paso</h2>";

$host = 'localhost';
$dbname = 'misdeseo_web';
$username = 'misdeseo_web';
$password = 'Aliaga.2018';

echo "<p class='info'>Host: $host</p>";
echo "<p class='info'>Base de datos: $dbname</p>";
echo "<p class='info'>Usuario: $username</p>";
echo "<p class='info'>Contraseña: [OCULTA]</p>";

// Paso 2a: Conexión sin especificar base de datos
echo "<h3>2a. Conexión al servidor MySQL (sin BD especificada)</h3>";
try {
    $pdo_test = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "<p class='success'>✓ Conexión al servidor MySQL exitosa</p>";
    
    // Listar bases de datos disponibles
    $databases = $pdo_test->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p class='info'>Bases de datos disponibles para este usuario:</p><ul>";
    foreach ($databases as $db) {
        echo "<li>$db</li>";
    }
    echo "</ul>";
    
    if (in_array($dbname, $databases)) {
        echo "<p class='success'>✓ La base de datos '$dbname' existe</p>";
    } else {
        echo "<p class='error'>✗ La base de datos '$dbname' NO existe</p>";
        echo "<p class='warning'>SOLUCIÓN: Crear la base de datos '$dbname' en cPanel/phpMyAdmin</p>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error conectando al servidor: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Código: " . $e->getCode() . "</p>";
    
    // Analizar tipo de error
    if (strpos($e->getMessage(), 'Access denied') !== false) {
        echo "<p class='warning'>PROBLEMA: Credenciales incorrectas</p>";
        echo "<p class='warning'>SOLUCIÓN: Verificar usuario '$username' y contraseña en cPanel</p>";
    } elseif (strpos($e->getMessage(), 'Connection refused') !== false) {
        echo "<p class='warning'>PROBLEMA: MySQL no está corriendo o host incorrecto</p>";
        echo "<p class='warning'>SOLUCIÓN: Contactar al proveedor de hosting</p>";
    }
}

// Paso 2b: Conexión a la base de datos específica
echo "<h3>2b. Conexión a la base de datos específica</h3>";
try {
    $pdo_full = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<p class='success'>✓ Conexión completa exitosa</p>";
    
    // Verificar información de la sesión
    $info = $pdo_full->query("SELECT 
        DATABASE() as current_db,
        USER() as current_user,
        VERSION() as mysql_version,
        NOW() as server_time,
        @@time_zone as timezone
    ")->fetch();
    
    echo "<p class='info'>Base de datos actual: {$info['current_db']}</p>";
    echo "<p class='info'>Usuario actual: {$info['current_user']}</p>";
    echo "<p class='info'>Versión MySQL: {$info['mysql_version']}</p>";
    echo "<p class='info'>Hora del servidor: {$info['server_time']}</p>";
    echo "<p class='info'>Zona horaria: {$info['timezone']}</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>✗ Error conectando a la base de datos: " . $e->getMessage() . "</p>";
    echo "<p class='error'>Código: " . $e->getCode() . "</p>";
    
    if (strpos($e->getMessage(), 'Unknown database') !== false) {
        echo "<p class='warning'>PROBLEMA: La base de datos '$dbname' no existe</p>";
        echo "<p class='warning'>SOLUCIÓN: Crear la base de datos en cPanel</p>";
    }
}

// Test 3: Verificar archivo includes/db.php
echo "<h2>3. Verificación de includes/db.php</h2>";

if (file_exists('includes/db.php')) {
    echo "<p class='success'>✓ Archivo includes/db.php existe</p>";
    
    try {
        require_once 'includes/db.php';
        
        if (function_exists('getConnection')) {
            echo "<p class='success'>✓ Función getConnection() disponible</p>";
            
            $test_pdo = getConnection();
            if ($test_pdo instanceof PDO) {
                echo "<p class='success'>✓ getConnection() funciona correctamente</p>";
            } else {
                echo "<p class='error'>✗ getConnection() no retorna PDO</p>";
            }
            
        } else {
            echo "<p class='error'>✗ Función getConnection() no encontrada</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>✗ Error cargando includes/db.php: " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p class='error'>✗ Archivo includes/db.php NO existe</p>";
}

// Test 4: Información del entorno
echo "<h2>4. Información del Entorno</h2>";
echo "<ul>";
echo "<li>Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido') . "</li>";
echo "<li>Sistema operativo: " . PHP_OS . "</li>";
echo "<li>Directorio actual: " . __DIR__ . "</li>";
echo "<li>Script ejecutado: " . $_SERVER['SCRIPT_FILENAME'] . "</li>";
echo "<li>URL actual: " . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '') . "</li>";
echo "</ul>";

echo "<h2>Conclusión</h2>";
echo "<p>Si todos los tests anteriores son exitosos, la conexión debería funcionar.</p>";
echo "<p>Si hay errores, siga las soluciones sugeridas.</p>";
echo "<p><strong>Después de solucionar los problemas, elimine este archivo por seguridad.</strong></p>";

echo "</body></html>";
?>