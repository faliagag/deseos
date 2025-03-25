<?php
// includes/helpers.php

// Sanitizar datos de entrada
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitize($value);
        }
        return $input;
    }
    
    // Verificar si el input es null o no es string
    if ($input === null) {
        return '';
    }
    
    // Convertir a string si es necesario
    if (!is_string($input)) {
        $input = (string)$input;
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

// Función para formatear dinero (actualizada para pesos chilenos sin decimales)
function format_money($amount, $currency = 'CLP') {
    if (strtoupper($currency) === 'CLP') {
        // Pesos chilenos: sin decimales, con punto como separador de miles
        return '$' . number_format(round($amount), 0, ',', '.');
    } elseif (strtoupper($currency) === 'USD') {
        // Dólares: con 2 decimales, formato internacional
        return 'US$' . number_format($amount, 2, '.', ',');
    } else {
        // Otras monedas: 2 decimales por defecto
        return number_format($amount, 2, '.', ',') . ' ' . $currency;
    }
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