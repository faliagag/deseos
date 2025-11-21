<?php
// controllers/AuthController.php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/helpers.php';

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

    /**
     * Registra un nuevo usuario
     * 
     * @param array $data Datos del formulario de registro
     * @return array Resultado de la operación
     */
    public function register($data) {
        try {
            // Validar datos requeridos
            if (empty($data['name']) || empty($data['lastname']) || empty($data['email']) || 
                empty($data['password']) || empty($data['phone']) || empty($data['rut'])) {
                return ['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados'];
            }

            // Sanitizar datos de entrada
            $name = sanitize($data['name']);
            $lastname = sanitize($data['lastname']);
            $phone = sanitize($data['phone']);
            $bank_name = sanitize($data['bank_name'] ?? '');
            $account_type = sanitize($data['account_type'] ?? '');
            $bank_account = sanitize($data['bank_account'] ?? '');
            $rut = sanitize($data['rut']);
            $email = sanitize($data['email']);
            $password = $data['password']; // No sanitizar la contraseña

            // Validar formato de email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'El formato del email no es válido'];
            }

            // Verificar si el email ya está registrado
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'El email ya está registrado'];
            }

            // Verificar si el RUT ya está registrado
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE rut = ?");
            $stmt->execute([$rut]);
            if ($stmt->fetchColumn() > 0) {
                return ['success' => false, 'message' => 'El RUT ya está registrado'];
            }

            // Cifrar la contraseña
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insertar nuevo usuario en la base de datos
            $stmt = $this->pdo->prepare("
                INSERT INTO users 
                (name, lastname, phone, bank_name, account_type, bank_account, rut, email, password, role, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW())
            ");

            $result = $stmt->execute([
                $name, 
                $lastname, 
                $phone, 
                $bank_name, 
                $account_type, 
                $bank_account, 
                $rut, 
                $email, 
                $hashedPassword
            ]);

            if ($result) {
                return ['success' => true, 'message' => 'Usuario registrado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al registrar el usuario'];
            }
        } catch (Exception $e) {
            error_log("Error en AuthController::register: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en el registro: ' . $e->getMessage()];
        }
    }

    /**
     * Actualiza el perfil de un usuario
     * 
     * @param array $data Datos del formulario
     * @param int $userId ID del usuario
     * @return array Resultado de la operación
     */
    public function updateProfile($data, $userId) {
        try {
            // Validar datos requeridos
            if (empty($data['name'])) {
                return ['success' => false, 'message' => 'El nombre es obligatorio'];
            }

            // Sanitizar datos de entrada
            $name = sanitize($data['name']);
            $password = $data['password'] ?? ''; // No sanitizar la contraseña

            // Si se proporciona una nueva contraseña, actualizarla
            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("UPDATE users SET name = ?, password = ? WHERE id = ?");
                $result = $stmt->execute([$name, $hashedPassword, $userId]);
            } else {
                // Si no se proporciona contraseña, solo actualizar el nombre
                $stmt = $this->pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
                $result = $stmt->execute([$name, $userId]);
            }

            if ($result) {
                return ['success' => true, 'message' => 'Perfil actualizado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'Error al actualizar el perfil'];
            }
        } catch (Exception $e) {
            error_log("Error en AuthController::updateProfile: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error en la actualización: ' . $e->getMessage()];
        }
    }

    /**
     * Realiza el inicio de sesión de un usuario
     * 
     * @param array $data Datos del formulario de login
     * @return array|void Resultado en caso de error, o redirección en caso de éxito
     */
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
