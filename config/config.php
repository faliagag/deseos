<?php
/**
 * CONFIGURACIÓN PRINCIPAL DEL SISTEMA - VERSIÓN 2.1
 * Actualizada con credenciales reales y mejoras de compatibilidad
 * 
 * Base de datos: misdeseo_web
 * Usuario: misdeseo_web
 * Optimizada para hosting compartido con máxima compatibilidad
 */

return [
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
    'application' => [
        'name' => 'Mis Deseos - Lista de Regalos',
        'email' => 'support@misdeseos.cl',
        'url' => 'https://misdeseos.cl',
        'timezone' => 'America/Santiago',
        'session_lifetime' => 3600,
        'version' => '2.1.0',
        'environment' => 'production',
        'debug' => false,
        'maintenance_mode' => false,
        'force_https' => true
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'noreply@misdeseos.cl',
        'password' => 'app_password_here',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => 'noreply@misdeseos.cl',
        'from_name' => 'Mis Deseos',
        'timeout' => 30
    ],
    // Integración MercadoPago
    'mercadopago' => [
        'access_token' => 'YOUR_MERCADOPAGO_ACCESS_TOKEN',
        'public_key' => 'YOUR_MERCADOPAGO_PUBLIC_KEY',
        'sandbox' => false, // Cambiar a false en producción
        'webhook_secret' => 'YOUR_WEBHOOK_SECRET',
        'enabled' => true,
        'timeout' => 30
    ],
    // Integración Transbank (método principal en Chile)
    'transbank' => [
        'commerce_code' => 'YOUR_TRANSBANK_COMMERCE_CODE',
        'api_key' => 'YOUR_TRANSBANK_API_KEY',
        'environment' => 'production', // 'integration' para pruebas
        'webpay_url' => 'https://webpay3g.transbank.cl/rswebpaytransaction/api/webpay/v1.2/transactions',
        'enabled' => true,
        'timeout' => 60
    ],
    'twilio' => [
        'sid' => 'your_twilio_sid',
        'token' => 'your_twilio_token',
        'from' => '+56912345678',
        'enabled' => false
    ],
    'notifications' => [
        'email_enabled' => true,
        'sms_enabled' => false,
        'push_enabled' => false,
        'queue_enabled' => true,
        'templates_path' => '../templates/notifications/',
        'instant_notifications' => true,
        'batch_size' => 100
    ],
    'analytics' => [
        'enabled' => true,
        'retention_days' => 365,
        'track_user_activity' => true,
        'track_payment_events' => true,
        'track_testimonials' => true,
        'track_fees' => true,
        'google_analytics_id' => 'GA_MEASUREMENT_ID',
        'sample_rate' => 100 // Porcentaje de eventos a registrar
    ],
    'security' => [
        'csrf_lifetime' => 3600,
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900,
        'session_regeneration' => true,
        'secure_cookies' => true,
        'same_site_cookies' => 'Lax',
        'ip_whitelist' => [], // IPs permitidas para admin
        'roles' => ['admin', 'user', 'moderator']
    ],
    'uploads' => [
        'path' => '../uploads/',
        'max_size' => 5242880, // 5MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'create_thumbnails' => true,
        'thumbnail_sizes' => [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600]
        ],
        'quality' => 85,
        'watermark_enabled' => false
    ],
    // Sistema de fees del 10% (estilo milistaderegalos.cl)
    'fees' => [
        'percentage' => 10,
        'include_in_payment' => true, // Fee cobrado al comprador, no al festejado
        'description' => 'Incluye cargos de procesamiento, Transbank y servicio',
        'admin_commission' => 0, // Sin comisión adicional
        'minimum_transaction' => 500, // Mínimo CLP $500
        'currency' => 'CLP'
    ],
    // Sistema de depósitos quincenales
    'payouts' => [
        'schedule' => 'biweekly_wednesday',
        'cutoff_time' => '14:00',
        'processing_day' => 'wednesday',
        'minimum_amount' => 1000, // Mínimo $1.000 CLP
        'processing_fee' => 0,
        'notification_days_before' => 2,
        'auto_process' => false, // Requiere confirmación manual
        'supported_methods' => ['bank_transfer', 'paypal'],
        'calendar_2025' => [
            'Enero' => ['8 Enero', '22 Enero'],
            'Febrero' => ['5 Febrero', '19 Febrero'], 
            'Marzo' => ['5 Marzo', '19 Marzo'],
            'Abril' => ['2 Abril', '16 Abril', '30 Abril'],
            'Mayo' => ['14 Mayo', '28 Mayo'],
            'Junio' => ['11 Junio', '25 Junio'],
            'Julio' => ['9 Julio', '23 Julio'],
            'Agosto' => ['6 Agosto', '20 Agosto'],
            'Septiembre' => ['3 Septiembre', '17 Septiembre'],
            'Octubre' => ['1 Octubre', '15 Octubre', '29 Octubre'],
            'Noviembre' => ['12 Noviembre', '26 Noviembre'],
            'Diciembre' => ['10 Diciembre', '24 Diciembre']
        ]
    ],
    // Eventos predefinidos (estilo milistaderegalos.cl)
    'events' => [
        'types' => [
            'aniversario' => ['name' => 'Aniversario', 'icon' => '💕', 'color' => '#ff6b6b'],
            'babyshower' => ['name' => 'Baby Shower', 'icon' => '👶', 'color' => '#74b9ff'],
            'bautismo' => ['name' => 'Bautismo', 'icon' => '✨', 'color' => '#fdcb6e'],
            'bodas_oro' => ['name' => 'Bodas de Oro', 'icon' => '💍', 'color' => '#ffd700'],
            'celebracion' => ['name' => 'Celebración', 'icon' => '🎉', 'color' => '#a29bfe'],
            'colecta' => ['name' => 'Colecta', 'icon' => '🤝', 'color' => '#6c5ce7'],
            'cumpleanos' => ['name' => 'Cumpleaños', 'icon' => '🎂', 'color' => '#fd79a8'],
            'depto_shower' => ['name' => 'Depto Shower', 'icon' => '🏠', 'color' => '#00b894'],
            'graduacion' => ['name' => 'Graduación', 'icon' => '🎓', 'color' => '#0984e3'],
            'matrimonio' => ['name' => 'Matrimonio', 'icon' => '💒', 'color' => '#e84393'],
            'primera_comunion' => ['name' => 'Primera Comunión', 'icon' => '🕊️', 'color' => '#00cec9'],
            'quinceanera' => ['name' => 'Quinceañera', 'icon' => '👑', 'color' => '#fd79a8']
        ],
        'allow_custom' => true,
        'default_duration_days' => 0 // Sin límite como milistaderegalos.cl
    ],
    // Sistema de QR codes
    'qr_codes' => [
        'enabled' => true,
        'size' => 200,
        'margin' => 2,
        'format' => 'png',
        'error_correction' => 'M',
        'logo_enabled' => true,
        'logo_size' => 50,
        'foreground_color' => '#000000',
        'background_color' => '#ffffff'
    ],
    // Testimonios dinámicos
    'testimonials' => [
        'enabled' => true,
        'moderation_required' => true,
        'max_per_page' => 6,
        'auto_approve_verified' => false,
        'auto_approve_5_stars' => true,
        'require_purchase' => false,
        'allow_anonymous' => false,
        'max_length' => 500,
        'featured_duration_days' => 30
    ],
    // FAQs dinámicas
    'faqs' => [
        'enabled' => true,
        'categories' => [
            'general' => 'Preguntas Generales',
            'pagos' => 'Pagos y Depósitos', 
            'listas' => 'Listas de Regalos',
            'regalos' => 'Regalos y Compras',
            'cuenta' => 'Mi Cuenta'
        ],
        'allow_search' => true,
        'track_helpfulness' => true,
        'auto_expand_first' => true
    ],
    // Búsqueda optimizada
    'search' => [
        'prioritize_names' => true, // Como milistaderegalos.cl
        'enable_filters' => true,
        'max_results' => 50,
        'highlight_matches' => true,
        'fuzzy_search' => true,
        'min_characters' => 2,
        'cache_results' => true,
        'cache_ttl' => 1800 // 30 minutos
    ],
    // Regalos imaginativos
    'gifts' => [
        'allow_imaginative' => true,
        'require_price_estimate' => false,
        'categories' => [
            'tangible' => 'Objetos Físicos',
            'experiencia' => 'Experiencias',
            'servicio' => 'Servicios',
            'imaginativo' => 'Deseos Imaginativos'
        ],
        'max_per_list' => 100,
        'min_price' => 100, // CLP $100 mínimo
        'max_price' => 10000000, // CLP $10M máximo
        'default_stock' => 1
    ],
    // Sistema de invitaciones
    'invitations' => [
        'track_clicks' => true,
        'track_views' => true,
        'allow_social_share' => true,
        'platforms' => [
            'whatsapp' => ['enabled' => true, 'api_url' => 'https://wa.me/'],
            'facebook' => ['enabled' => true, 'app_id' => 'YOUR_FB_APP_ID'],
            'email' => ['enabled' => true],
            'link' => ['enabled' => true]
        ],
        'short_links' => false, // Usar URLs completas en hosting compartido
        'referral_tracking' => true
    ],
    // Sistema de caché
    'cache' => [
        'enabled' => true,
        'driver' => 'file', // Ideal para hosting compartido
        'path' => '../cache/',
        'default_ttl' => 3600,
        'cleanup_probability' => 1, // 1% probabilidad de limpieza automática
        'max_files' => 1000,
        'compress' => false // No comprimir en hosting compartido
    ],
    // Sistema de backups
    'backup' => [
        'enabled' => true,
        'path' => '../backups/',
        'retention_days' => 30,
        'auto_backup' => true,
        'schedule' => 'daily', // diario a las 2:00 AM
        'compress' => true,
        'include_uploads' => false, // Solo DB por espacio
        'max_backups' => 10
    ],
    // Límites del sistema
    'limits' => [
        'max_lists_per_user' => 20,
        'max_gifts_per_list' => 100,
        'max_file_uploads' => 5,
        'max_session_time' => 7200, // 2 horas
        'max_daily_transactions' => 1000,
        'rate_limit_requests' => 60, // por minuto
        'rate_limit_window' => 60 // segundos
    ],
    // Configuración de logs
    'logging' => [
        'enabled' => true,
        'level' => 'WARNING', // ERROR, WARNING, INFO, DEBUG
        'path' => '../logs/',
        'max_file_size' => 10485760, // 10MB
        'max_files' => 10,
        'log_queries' => false, // Solo en desarrollo
        'log_slow_queries' => true,
        'slow_query_threshold' => 1.0 // segundos
    ],
    // Configuración de emails
    'email_templates' => [
        'welcome' => [
            'subject' => 'Bienvenido a Mis Deseos',
            'template' => 'welcome.html'
        ],
        'payout_notification' => [
            'subject' => 'Depósito procesado - Mis Deseos',
            'template' => 'payout.html'
        ],
        'purchase_confirmation' => [
            'subject' => 'Confirmación de compra - Mis Deseos',
            'template' => 'purchase.html'
        ],
        'list_shared' => [
            'subject' => 'Te invitaron a ver una lista de regalos',
            'template' => 'invitation.html'
        ]
    ],
    // Configuración de seguridad
    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://js.mercadopago.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;"
    ],
    // Configuración de APIs externas
    'external_apis' => [
        'google_maps' => [
            'enabled' => false,
            'api_key' => 'YOUR_GOOGLE_MAPS_API_KEY'
        ],
        'firebase' => [
            'enabled' => false,
            'server_key' => 'YOUR_FIREBASE_SERVER_KEY',
            'sender_id' => 'YOUR_FIREBASE_SENDER_ID'
        ]
    ],
    // Configuración de performance
    'performance' => [
        'enable_compression' => true,
        'cache_static_files' => true,
        'minify_html' => false, // Puede causar problemas en algunos hostings
        'lazy_load_images' => true,
        'cdn_enabled' => false,
        'cdn_url' => ''
    ]
];