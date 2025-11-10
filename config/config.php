<?php
/**
 * CONFIGURACIÓN PRINCIPAL - VERSIÓN 2.1 COMPLETA
 * Actualizada con todas las funcionalidades estilo milistaderegalos.cl
 * ⚠️ IMPORTANTE: Mover credenciales sensibles a variables de entorno en producción
 */

return [
    // Configuración de la aplicación
    'application' => [
        'name' => 'Mi Lista de Regalos',
        'version' => '2.1',
        'url' => 'https://tu-dominio.com',
        'email' => 'contacto@tu-dominio.com',
        'timezone' => 'America/Santiago',
        'locale' => 'es_CL'
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
                'color' => '#e91e63',
                'description' => 'Celebra un año más juntos'
            ],
            'babyshower' => [
                'name' => 'Babyshower',
                'icon' => '👶',
                'color' => '#ffb74d',
                'description' => 'Bienvenida al nuevo bebé'
            ],
            'bautismo' => [
                'name' => 'Bautismo',
                'icon' => '🙏',
                'color' => '#90caf9',
                'description' => 'Primer sacramento'
            ],
            'bodas_de_oro' => [
                'name' => 'Bodas de oro',
                'icon' => '👑',
                'color' => '#ffd700',
                'description' => '50 años de amor'
            ],
            'celebracion' => [
                'name' => 'Celebración',
                'icon' => '🎉',
                'color' => '#4caf50',
                'description' => 'Cualquier motivo para celebrar'
            ],
            'colecta' => [
                'name' => 'Colecta',
                'icon' => '🤝',
                'color' => '#9c27b0',
                'description' => 'Recaudación solidaria'
            ],
            'cumpleanos' => [
                'name' => 'Cumpleaños',
                'icon' => '🎂',
                'color' => '#f44336',
                'description' => 'Un año más de vida'
            ],
            'depto_shower' => [
                'name' => 'Depto shower',
                'icon' => '🏠',
                'color' => '#607d8b',
                'description' => 'Nuevo hogar, nuevos sueños'
            ],
            'matrimonio' => [
                'name' => 'Matrimonio',
                'icon' => '💒',
                'color' => '#e91e63',
                'description' => 'El día más especial'
            ],
            'graduacion' => [
                'name' => 'Graduación',
                'icon' => '🎓',
                'color' => '#3f51b5',
                'description' => 'Logro académico'
            ]
        ]
    ],
    
    // Configuración de pagos (calendario 2025 basado en milistaderegalos.cl)
    'payouts' => [
        'schedule' => 'biweekly_wednesday',
        'cutoff_time' => '14:00',
        'cutoff_day' => 'monday',
        'minimum_amount' => 1000, // CLP
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
        'fee_percentage' => 10.0,
        'enabled' => true
    ],
    
    // Configuración de Transbank
    'transbank' => [
        'commerce_code' => 'YOUR_TRANSBANK_COMMERCE_CODE',
        'api_key' => 'YOUR_TRANSBANK_API_KEY',
        'environment' => 'integration', // 'production' para producción
        'enabled' => true
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
        ],
        'auto_send' => [
            'purchase_confirmation' => true,
            'payout_notification' => true,
            'event_reminder' => true,
            'testimonial_request' => true
        ]
    ],
    
    // Sistema de fees del 10% (estilo milistaderegalos.cl)
    'fees' => [
        'user_commission' => 0, // Festejado recibe 100%
        'buyer_commission' => 10, // Compradores pagan 10% extra
        'currency' => 'CLP',
        'include_in_payment' => true, // Fee cobrado al comprador
        'description' => 'El festejado recibe el 100% del monto. Los compradores pagan un 10% extra que incluye costos de plataforma y Transbank.'
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
            'size' => 300,
            'format' => 'png',
            'quality' => 90
        ],
        'gallery' => [
            'path' => 'uploads/gallery/',
            'max_images' => 10,
            'thumbnail_size' => [200, 200]
        ]
    ],
    
    // Configuración de seguridad
    'security' => [
        'password_min_length' => 8,
        'session_lifetime' => 120, // minutos
        'rate_limiting' => [
            'login_attempts' => 5,
            'lockout_duration' => 900 // 15 minutos
        ],
        'csrf_protection' => true,
        'force_https' => false // Cambiar a true en producción
    ],
    
    // Configuración de búsqueda (estilo milistaderegalos.cl)
    'search' => [
        'prioritize_names' => true, // Priorizar nombres como milistaderegalos.cl
        'enable_filters' => true,
        'max_results' => 50,
        'highlight_matches' => true,
        'min_characters' => 2,
        'enable_autocomplete' => true,
        'cache_results' => true
    ],
    
    // Testimonios dinámicos
    'testimonials' => [
        'enabled' => true,
        'moderation_required' => true,
        'max_per_page' => 6,
        'auto_approve_5_stars' => false,
        'min_length' => 50,
        'max_length' => 500,
        'auto_request_days_after_event' => 7
    ],
    
    // FAQs dinámicas
    'faqs' => [
        'enabled' => true,
        'categories' => [
            'general' => 'Preguntas Generales',
            'pagos' => 'Pagos y Depósitos', 
            'listas' => 'Listas de Regalos',
            'cuenta' => 'Mi Cuenta'
        ],
        'auto_expand_first' => true,
        'enable_search' => true
    ],
    
    // Sistema de QR codes
    'qr_codes' => [
        'enabled' => true,
        'size' => 300,
        'format' => 'png',
        'margin' => 10,
        'foreground_color' => '#000000',
        'background_color' => '#FFFFFF',
        'auto_generate' => true
    ],
    
    // Compartir en redes sociales
    'sharing' => [
        'whatsapp_enabled' => true,
        'facebook_enabled' => true,
        'twitter_enabled' => true,
        'email_enabled' => true,
        'copy_link_enabled' => true,
        'qr_enabled' => true,
        'default_message' => '¡Mira mi lista de regalos! 🎁'
    ],
    
    // Información de contacto y redes
    'contact' => [
        'email' => 'contacto@tudominio.cl',
        'phone' => '+56 9 1234 5678',
        'whatsapp' => '+56912345678',
        'address' => 'Santiago, Chile',
        'social' => [
            'instagram' => '@tuinstagram',
            'facebook' => 'tupagina',
            'twitter' => '@tutwitter'
        ]
    ],
    
    // Estadísticas públicas
    'stats' => [
        'show_public_stats' => true,
        'cache_duration' => 3600, // 1 hora
        'display' => [
            'total_lists' => true,
            'happy_users' => true,
            'total_delivered' => true,
            'satisfaction_rate' => true
        ]
    ],
    
    // Sistema de cupones
    'coupons' => [
        'enabled' => true,
        'allow_multiple' => false,
        'case_sensitive' => false
    ],
    
    // Sistema de referidos
    'referrals' => [
        'enabled' => false,
        'commission_percentage' => 5,
        'minimum_payout' => 10000
    ],
    
    // Meta tags para redes sociales
    'seo' => [
        'site_name' => 'Mi Lista de Regalos',
        'default_image' => '/assets/images/og-image.jpg',
        'twitter_handle' => '@milistaderegalos',
        'keywords' => 'lista de regalos, regalos, eventos, matrimonio, cumpleaños, baby shower'
    ],
    
    // Analytics
    'analytics' => [
        'enabled' => true,
        'google_analytics_id' => 'UA-XXXXXXXXX-X',
        'facebook_pixel_id' => '',
        'track_events' => true
    ],
    
    // Límites del sistema
    'limits' => [
        'max_lists_per_user' => 20,
        'max_gifts_per_list' => 100,
        'max_gallery_images' => 10,
        'max_upload_size' => 10, // MB
        'session_timeout' => 120 // minutos
    ],
    
    // Mantenimiento
    'maintenance' => [
        'mode' => false,
        'message' => 'Estamos realizando mejoras. Volveremos pronto.',
        'allowed_ips' => []
    ],
    
    // Logs
    'logging' => [
        'enabled' => true,
        'level' => 'error', // debug, info, warning, error
        'path' => 'logs/',
        'max_files' => 30
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