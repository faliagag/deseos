<?php
/**
 * CONFIGURACIÓN PRINCIPAL - VERSIÓN 2.1
 * Actualizada con eventos y características de milistaderegalos.cl
 * ⚠️ IMPORTANTE: Mover credenciales a variables de entorno en producción
 */

return [
    // Configuración de la aplicación
    'application' => [
        'name' => 'Mi Lista de Regalos',
        'version' => '2.1',
        'url' => 'https://tu-dominio.com',
        'email' => 'contacto@tu-dominio.com',
        'timezone' => 'America/Santiago'
    ],
    
    // ⚠️ TEMPORAL - Mover a .env en producción
    'database' => [
        'host' => 'localhost',
        'name' => 'misdeseo_web',
        'user' => 'misdeseo_web',
        'pass' => 'Aliaga.2018',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ],
    
    // Configuración de eventos (basado en milistaderegalos.cl)
    'events' => [
        'types' => [
            'aniversario' => [
                'name' => 'Aniversario',
                'icon' => '💕',
                'color' => '#e91e63'
            ],
            'babyshower' => [
                'name' => 'Babyshower',
                'icon' => '👶',
                'color' => '#ffb74d'
            ],
            'bautismo' => [
                'name' => 'Bautismo',
                'icon' => '🙏',
                'color' => '#90caf9'
            ],
            'bodas_de_oro' => [
                'name' => 'Bodas de oro',
                'icon' => '👑',
                'color' => '#ffd700'
            ],
            'celebracion' => [
                'name' => 'Celebración',
                'icon' => '🎉',
                'color' => '#4caf50'
            ],
            'colecta' => [
                'name' => 'Colecta',
                'icon' => '🤝',
                'color' => '#9c27b0'
            ],
            'cumpleanos' => [
                'name' => 'Cumpleaños',
                'icon' => '🎂',
                'color' => '#f44336'
            ],
            'depto_shower' => [
                'name' => 'Depto shower',
                'icon' => '🏠',
                'color' => '#607d8b'
            ],
            'matrimonio' => [
                'name' => 'Matrimonio',
                'icon' => '💒',
                'color' => '#e91e63'
            ],
            'graduacion' => [
                'name' => 'Graduación',
                'icon' => '🎓',
                'color' => '#3f51b5'
            ]
        ]
    ],
    
    // Configuración de pagos (calendario 2025 basado en milistaderegalos.cl)
    'payouts' => [
        'schedule' => 'biweekly_wednesday',
        'cutoff_time' => '14:00',
        'cutoff_day' => 'monday',
        'calendar_2025' => [
            'Enero' => ['8 de Enero', '22 de Enero'],
            'Febrero' => ['5 de Febrero', '19 de Febrero'],
            'Marzo' => ['5 de Marzo', '19 de Marzo'],
            'Abril' => ['2 de Abril', '16 de Abril', '30 de Abril'],
            'Mayo' => ['14 de Mayo', '28 de Mayo'],
            'Junio' => ['11 de Junio', '25 de Junio'],
            'Julio' => ['9 de Julio', '23 de Julio'],
            'Agosto' => ['6 de Agosto', '20 de Agosto'],
            'Septiembre' => ['3 de Septiembre', '17 de Septiembre'],
            'Octubre' => ['1 de Octubre', '15 de Octubre', '29 de Octubre'],
            'Noviembre' => ['12 de Noviembre', '26 de Noviembre'],
            'Diciembre' => ['10 de Diciembre', '24 de Diciembre']
        ]
    ],
    
    // Configuración de MercadoPago
    'mercadopago' => [
        'access_token' => 'YOUR_MERCADOPAGO_ACCESS_TOKEN',
        'public_key' => 'YOUR_MERCADOPAGO_PUBLIC_KEY',
        'sandbox' => true, // Cambiar a false en producción
        'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
        'fee_percentage' => 10.0
    ],
    
    // Configuración de Transbank
    'transbank' => [
        'commerce_code' => 'YOUR_TRANSBANK_COMMERCE_CODE',
        'api_key' => 'YOUR_TRANSBANK_API_KEY',
        'environment' => 'integration' // 'production' para producción
    ],
    
    // Configuración de notificaciones
    'notifications' => [
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'noreply@tu-dominio.com',
            'password' => 'your-app-password',
            'encryption' => 'tls',
            'from_email' => 'noreply@tu-dominio.com',
            'from_name' => 'Mi Lista de Regalos'
        ],
        'twilio' => [
            'sid' => 'your_twilio_sid',
            'token' => 'your_twilio_token',
            'from' => '+56912345678',
            'enabled' => false
        ]
    ],
    
    // Sistema de fees del 10% (estilo milistaderegalos.cl)
    'fees' => [
        'percentage' => 10,
        'include_in_payment' => true, // Fee cobrado al comprador, no al festejado
        'description' => 'Incluye cargos de procesamiento, Transbank y servicio',
        'currency' => 'CLP'
    ],
    
    // Configuración de archivos
    'files' => [
        'uploads' => [
            'path' => 'uploads/',
            'max_size' => 10485760, // 10MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp']
        ],
        'qr_codes' => [
            'path' => 'uploads/qr-codes/',
            'size' => 200
        ]
    ],
    
    // Configuración de seguridad
    'security' => [
        'password_min_length' => 8,
        'session_lifetime' => 120, // minutos
        'rate_limiting' => [
            'login_attempts' => 5,
            'lockout_duration' => 900 // 15 minutos
        ]
    ],
    
    // Configuración de búsqueda (estilo milistaderegalos.cl)
    'search' => [
        'prioritize_names' => true, // Priorizar nombres como milistaderegalos.cl
        'enable_filters' => true,
        'max_results' => 50,
        'highlight_matches' => true,
        'min_characters' => 2
    ],
    
    // Testimonios dinámicos
    'testimonials' => [
        'enabled' => true,
        'moderation_required' => true,
        'max_per_page' => 6,
        'auto_approve_5_stars' => true
    ],
    
    // FAQs dinámicas
    'faqs' => [
        'enabled' => true,
        'categories' => [
            'general' => 'Preguntas Generales',
            'pagos' => 'Pagos y Depósitos', 
            'listas' => 'Listas de Regalos'
        ],
        'auto_expand_first' => true
    ],
    
    // Sistema de QR codes
    'qr_codes' => [
        'enabled' => true,
        'size' => 200,
        'format' => 'png'
    ],
    
    // Límites del sistema
    'limits' => [
        'max_lists_per_user' => 20,
        'max_gifts_per_list' => 100
    ]
];

/**
 * Función auxiliar para obtener variables de entorno
 * Usar en futuras versiones cuando se migren las credenciales
 */
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Convertir valores string a tipos apropiados
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
        }
        
        // Si es un número, convertir
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        
        return $value;
    }
}