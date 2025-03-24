<?php
// controllers/AuthController.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/User.php';

// Asegúrate de que estas funciones sean definidas o incluidas
if (!function_exists('error_log')) {
    function error_log($message) {
        // Implementación básica si no existe
        file_put_contents('error_log.txt', $message . "\n", FILE_APPEND);
    }
}

class AuthController {
    private $pdo;
    private $userModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    // Método login simplificado para depuración
    public function login($data) {
        // Validar datos de entrada
        if (!isset($data['email']) || !isset($data['password'])) {
            return ['success' => false, 'message' => 'Email y contraseña son obligatorios'];
        }

        // Intentar obtener el usuario a través del modelo
        $user = $this->userModel->login($data['email'], $data['password']);

        if ($user) {
            // Inicia la sesión si no está activa
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Limpiar datos sensibles antes de guardar en sesión
            unset($user['password']);
            
            // Guardar los datos del usuario en la sesión
            $_SESSION['user'] = $user;
            
            // Redireccionar según el rol del usuario
            if (isset($user['role']) && $user['role'] === 'admin') {
                header("Location: admin/dashboard.php");
                exit;
            } else {
                header("Location: dashboard.php");
                exit;
            }
        } else {
            return ['success' => false, 'message' => 'Credenciales incorrectas'];
        }
    }
}