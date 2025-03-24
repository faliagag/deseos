<?php
// models/User.php

class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Método simplificado para depuración
    public function login($email, $password) {
        try {
            // Buscar al usuario por email
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            // Si el usuario existe y la contraseña coincide
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Exception in User::login: " . $e->getMessage());
            return false;
        }
    }
}