<?php
// Funciones de utilidad general

// Sanitizar datos de entrada
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
        return $input;
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generar token CSRF
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verificar token CSRF
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para formatear dinero
function format_money($amount, $currency = 'USD') {
    return number_format($amount, 2) . ' ' . $currency;
}

// Función para formatear fechas
function format_date($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Función para redireccionar
function redirect($url) {
    header("Location: $url");
    exit;
}

// Mostrar mensajes flash
function set_flash_message($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}