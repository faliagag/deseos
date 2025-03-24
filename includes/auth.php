<?php
class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function check() {
        return isset($_SESSION['user']);
    }
    
    public function user() {
        return $_SESSION['user'] ?? null;
    }
    
    public function require($redirect = 'login.php') {
        if (!$this->check()) {
            set_flash_message('danger', 'Debe iniciar sesión para acceder a esta página.');
            redirect($redirect);
        }
    }
    
    public function isAdmin() {
        $user = $this->user();
        return $user && $user['role'] === 'admin';
    }
    
    public function requireAdmin($redirect = 'login.php') {
        if (!$this->isAdmin()) {
            set_flash_message('danger', 'No tiene permisos para acceder a esta página.');
            redirect($redirect);
        }
    }
    
    public function logout() {
        session_destroy();
        set_flash_message('success', 'Sesión cerrada exitosamente.');
        redirect('index.php');
    }
}