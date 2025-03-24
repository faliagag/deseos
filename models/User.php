<?php
// models/User.php
class User {
    private $pdo;
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    // Método actualizado de registro
    public function register($name, $lastname, $phone, $bank, $account_type, $account_number, $rut, $email, $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (name, lastname, phone, bank, account_type, account_number, rut, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', NOW())");
        return $stmt->execute([$name, $lastname, $phone, $bank, $account_type, $account_number, $rut, $email, $hash]);
    }
    public function login($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    public function updateProfile($id, $name, $lastname, $phone, $bank, $account_type, $account_number, $rut, $email, $password = null) {
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, account_number = ?, rut = ?, email = ?, password = ? WHERE id = ?");
            return $stmt->execute([$name, $lastname, $phone, $bank, $account_type, $account_number, $rut, $email, $hash, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE users SET name = ?, lastname = ?, phone = ?, bank = ?, account_type = ?, account_number = ?, rut = ?, email = ? WHERE id = ?");
            return $stmt->execute([$name, $lastname, $phone, $bank, $account_type, $account_number, $rut, $email, $id]);
        }
    }
}
