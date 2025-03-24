<?php
// includes/db.php

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
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Manejar error de conexión
    require_once __DIR__ . '/ErrorHandler.php';
    ErrorHandler::logError("Error de conexión a base de datos", [
        'message' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    die("Error en la conexión a la base de datos. Por favor, contacte al administrador.");
}

// Función global para ejecutar consultas con manejo de errores
function executeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $success = $stmt->execute($params);
        return [
            'success' => $success,
            'stmt' => $stmt,
            'lastInsertId' => $success ? $pdo->lastInsertId() : null
        ];
    } catch (PDOException $e) {
        require_once __DIR__ . '/ErrorHandler.php';
        ErrorHandler::logError("Error en consulta SQL", [
            'query' => $query,
            'params' => $params,
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}