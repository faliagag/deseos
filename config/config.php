<?php
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'giftlist_db',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],
    'application' => [
        'name' => 'Lista de Deseos',
        'email' => 'support@listadedeseos.com',
        'url' => 'http://localhost/deseos',
        'timezone' => 'America/Santiago',
        'session_lifetime' => 3600
    ],
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'your_email@gmail.com',
        'password' => 'your_app_password',
        'secure' => 'tls',
        'port' => 587,
        'from_email' => 'noreply@listadedeseos.com',
        'from_name' => 'Lista de Deseos'
    ],
    'mercadopago' => [
        'access_token' => 'YOUR_MERCADOPAGO_ACCESS_TOKEN',
        'public_key' => 'YOUR_MERCADOPAGO_PUBLIC_KEY',
        'sandbox' => true,
        'webhook_secret' => 'YOUR_WEBHOOK_SECRET'
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
        'queue_enabled' => true,
        'templates_path' => '../templates/notifications/'
    ],
    'analytics' => [
        'enabled' => true,
        'retention_days' => 365,
        'track_user_activity' => true,
        'track_payment_events' => true
    ],
    'security' => [
        'csrf_lifetime' => 3600,
        'password_min_length' => 8,
        'max_login_attempts' => 5,
        'lockout_duration' => 900
    ],
    'uploads' => [
        'path' => '../uploads/',
        'max_size' => 5242880,
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
        'create_thumbnails' => true
    ]
];