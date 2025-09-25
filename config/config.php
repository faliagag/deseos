<?php
/**
 * Configuración Principal del Sistema de Lista de Deseos
 * Versión 2.1 - Optimizada con características de milistaderegalos.cl
 * 
 * Características nuevas:
 * - Integración Transbank + MercadoPago
 * - Sistema de fees del 10%
 * - Depósitos quincenales automatizados
 * - Eventos predefinidos
 * - QR codes para listas
 * - Testimonios y FAQs dinámicos
 */

return [
    'database' => [
        'host' => 'localhost',
        'name' => 'giftlist_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'application' => [
        'name' => 'Mi Lista de Regalos',
        'email' => 'support@milistaderegalos.com',
        'url' => 'http://localhost/deseos',
        'timezone' => 'America/Santiago',
        'session_lifetime' => 3600,
        'version' => '2.1',
        'debug' => false
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'your_email@gmail.com',
        'password' => 'your_app_password',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => 'noreply@milistaderegalos.com',
        'from_name' => 'Mi Lista de Regalos'
    ],
    // Integración MercadoPago (mantener existente)
    'mercadopago' => [
        'access_token' => 'YOUR_MERCADOPAGO_ACCESS_TOKEN',
        'public_key' => 'YOUR_MERCADOPAGO_PUBLIC_KEY',
        'sandbox' => true,
        'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
        'enabled' => true
    ],
    // Nueva: Integración Transbank para Chile
    'transbank' => [
        'commerce_code' => 'YOUR_TRANSBANK_COMMERCE_CODE',
        'api_key' => 'YOUR_TRANSBANK_API_KEY',
        'environment' => 'integration', // 'integration' o 'production'
        'webpay_url' => 'https://webpay3gint.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions',
        'enabled' => true,
        'timeout' => 60
    ],
    'twilio' => [
        'sid' => 'your_twilio_sid',
        'token' => 'your_twilio_token',
        'from' => '+1234567890',
        'enabled' => false
    ],
    'notifications' => [
        'email_enabled' => true,
        'sms_enabled' => false,
        'push_enabled' => false,
        'queue_enabled' => true,
        'templates_path' => '../templates/notifications/',
        'instant_notifications' => true // Notificaciones inmediatas como milistaderegalos.cl
    ],
    'analytics' => [
        'enabled' => true,
        'retention_days' => 365,
        'track_user_activity' => true,
        'track_payment_events' => true,
        'track_testimonials' => true,
        'track_fees' => true
    ],
    'security' => [
        'csrf_lifetime' => 3600,
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'session_regeneration' => true,
        'roles' => ['admin', 'user']
    ],
    'uploads' => [
        'path' => '../uploads/',
        'max_size' => 5242880, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'create_thumbnails' => true,
        'thumbnail_size' => 300
    ],
    // Nueva: Sistema de fees como milistaderegalos.cl
    'fees' => [
        'percentage' => 10, // 10% como en milistaderegalos.cl
        'include_in_payment' => true, // Fee cobrado al comprador, no al festejado
        'description' => 'Incluye cargos de procesamiento y Transbank',
        'admin_commission' => 0 // Sin comisión adicional para el admin
    ],
    // Nueva: Sistema de depósitos quincenales
    'payouts' => [
        'schedule' => 'biweekly_wednesday', // Cada 2 miércoles
        'cutoff_time' => '14:00', // Corte lunes 14:00 para depósito miércoles
        'minimum_amount' => 1000, // Mínimo $1.000 CLP para depósito
        'processing_fee' => 0, // Sin fee de procesamiento
        'notification_days_before' => 2, // Notificar 2 días antes del depósito
        'calendar_2025' => [
            'Enero' => ['8', '22'],
            'Febrero' => ['5', '19'],
            'Marzo' => ['5', '19'],
            'Abril' => ['2', '16', '30'],
            'Mayo' => ['14', '28'],
            'Junio' => ['11', '25'],
            'Julio' => ['9', '23'],
            'Agosto' => ['6', '20'],
            'Septiembre' => ['3', '17'],
            'Octubre' => ['1', '15', '29'],
            'Noviembre' => ['12', '26'],
            'Diciembre' => ['10', '24']
        ]
    ],
    // Nueva: Eventos predefinidos como milistaderegalos.cl
    'events' => [
        'types' => [
            'aniversario' => ['name' => 'Aniversario', 'icon' => '💕', 'color' => '#ff6b6b'],
            'babyshower' => ['name' => 'Baby Shower', 'icon' => '👶', 'color' => '#74b9ff'],
            'bautismo' => ['name' => 'Bautismo', 'icon' => '✨', 'color' => '#fdcb6e'],
            'bodas_oro' => ['name' => 'Bodas de Oro', 'icon' => '💍', 'color' => '#ffd700'],
            'celebracion' => ['name' => 'Celebración', 'icon' => '🎉', 'color' => '#a29bfe'],
            'colecta' => ['name' => 'Colecta', 'icon' => '🤝', 'color' => '#6c5ce7'],
            'cumpleanos' => ['name' => 'Cumpleaños', 'icon' => '🎂', 'color' => '#fd79a8'],
            'depto_shower' => ['name' => 'Depto Shower', 'icon' => '🏠', 'color' => '#00b894']
        ],
        'allow_custom' => true, // Permitir eventos personalizados
        'default_duration_days' => 0 // 0 = sin límite como milistaderegalos.cl
    ],
    // Nueva: Sistema de QR codes
    'qr_codes' => [
        'enabled' => true,
        'size' => 200,
        'margin' => 2,
        'format' => 'png',
        'error_correction' => 'M'
    ],
    // Nueva: Testimonios y contenido dinámico
    'testimonials' => [
        'enabled' => true,
        'moderation_required' => true,
        'max_per_page' => 6,
        'auto_approve_verified' => true
    ],
    // Nueva: FAQs dinámicas
    'faqs' => [
        'enabled' => true,
        'categories' => ['general', 'pagos', 'listas', 'regalos'],
        'allow_search' => true
    ],
    // Nueva: Búsqueda mejorada
    'search' => [
        'prioritize_names' => true, // Priorizar búsqueda por nombres como milistaderegalos.cl
        'enable_filters' => true,
        'max_results' => 50,
        'highlight_matches' => true
    ],
    // Nueva: Regalos imaginativos
    'gifts' => [
        'allow_imaginative' => true, // "Viaje a la luna", "abrazo", etc.
        'require_price_estimate' => false,
        'categories' => ['tangible', 'experiencia', 'servicio', 'imaginativo'],
        'max_per_list' => 100
    ],
    // Nueva: Sistema de invitaciones
    'invitations' => [
        'track_clicks' => true,
        'track_views' => true,
        'allow_social_share' => true,
        'platforms' => ['whatsapp', 'facebook', 'email', 'link']
    ]
];