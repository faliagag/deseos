<?php
// controllers/AuthController.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $pdo;
    private $userModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    /**
     * Realiza el proceso de login.
     * Si las credenciales son correctas, inicia la sesión y redirige según el rol del usuario.
     *
     * @param array $data Contiene 'email' y 'password'
     * @return array Si falla, retorna ['success' => false, 'message' => '...'].
     */
    public function login($data) {
        if (!isset($data['email']) || !isset($data['password'])) {
            return ['success' => false, 'message' => 'Faltan credenciales'];
        }

        // Intenta obtener el usuario a través del modelo
        $user = $this->userModel->login($data['email'], $data['password']);

        if ($user) {
            // Inicia la sesión si no está activa
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            // Guarda los datos del usuario en la sesión
            $_SESSION['user'] = $user;

            // Redirecciona según el rol del usuario
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
    
    // Otros métodos (register, updateProfile, etc.) se definen aquí...
}
