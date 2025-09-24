# 🎁 Lista de Deseos - Sistema Completo

**Versión 2.0 - Optimizada para Hosting Compartido**

Sistema completo de listas de deseos con funcionalidades avanzadas, diseñado especialmente para funcionar en hosting compartido con excelente rendimiento y estabilidad.

## ✨ Características Principales

### 🔥 **Funcionalidades Destacadas**
- **💳 Pagos con MercadoPago**: Integración completa con webhooks
- **📧 Sistema de Notificaciones**: Email automatizado y SMS opcional
- **📊 Analytics Avanzados**: Métricas completas y reportes exportables
- **👑 Panel de Administración**: Gestión completa con dashboard visual
- **👤 Panel de Usuario**: Interfaz intuitiva para gestionar listas
- **🔒 Seguridad Robusta**: Protección contra ataques y validaciones

### 🚀 **Optimizado para Hosting Compartido**
- **Sin dependencias complejas**: Funciona con PHP nativo
- **Uso eficiente de memoria**: Optimizado para recursos limitados
- **Base de datos optimizada**: Índices y consultas eficientes
- **Carga rápida**: Código optimizado y caché inteligente
- **Instalación simple**: Un solo archivo de instalación

## 📋 Requisitos del Sistema

### **Mínimos**
- PHP 7.4 o superior
- MySQL 5.7 o superior (o MariaDB 10.2+)
- Extensión cURL habilitada
- Extensión JSON habilitada
- Al menos 64MB de memoria PHP

### **Recomendados**
- PHP 8.0 o superior
- MySQL 8.0 o superior
- 128MB de memoria PHP
- Extensión GD para imágenes
- OpenSSL para conexiones seguras

## 🛠 Instalación Rápida

### **Paso 1: Descarga y Extracción**
```bash
# Descarga el proyecto
wget https://github.com/faliagag/deseos/archive/main.zip

# Extrae en tu hosting
unzip main.zip
mv deseos-main/* public_html/
```

### **Paso 2: Configuración de Base de Datos**
1. Edita `includes/db.php` con tus credenciales:
```php
<?php
define('DB_HOST', 'localhost');      // Tu servidor MySQL
define('DB_NAME', 'tu_base_datos');  // Nombre de tu base de datos
define('DB_USER', 'tu_usuario');     // Usuario MySQL
define('DB_PASS', 'tu_password');    // Contraseña MySQL
```

### **Paso 3: Ejecutar Instalador**
1. Visita: `https://tu-dominio.com/install.php`
2. El instalador creará todas las tablas automáticamente
3. **¡IMPORTANTE!** Elimina `install.php` después de la instalación

### **Paso 4: Configuración Inicial**
1. Accede al panel de admin: `https://tu-dominio.com/public/admin/`
2. **Credenciales por defecto:**
   - Email: `admin@admin.com`
   - Contraseña: `admin123`
3. **¡Cambia estas credenciales inmediatamente!**

## ⚙️ Configuración Avanzada

### **MercadoPago (Pagos)**
1. Obtén tus credenciales en [MercadoPago Developers](https://www.mercadopago.cl/developers/)
2. Edita `config/config.php`:
```php
'mercadopago' => [
    'access_token' => 'TU_ACCESS_TOKEN',
    'public_key' => 'TU_PUBLIC_KEY',
    'sandbox' => false, // true para pruebas
    'webhook_secret' => 'tu_secreto_webhook'
]
```
3. Configura webhook en MercadoPago: `https://tu-dominio.com/public/webhook_mercadopago.php`

### **Notificaciones por Email**
```php
'smtp' => [
    'host' => 'smtp.gmail.com',
    'username' => 'tu-email@gmail.com',
    'password' => 'tu-app-password',
    'secure' => 'tls',
    'port' => 587
]
```

### **SMS (Opcional - Twilio)**
```php
'twilio' => [
    'sid' => 'tu_twilio_sid',
    'token' => 'tu_twilio_token',
    'from' => '+1234567890',
    'enabled' => false
]
```

## 📁 Estructura del Proyecto

```
deseos/
├── config/
│   └── config.php          # Configuración principal
├── includes/
│   └── db.php              # Conexión a base de datos
├── models/
│   ├── PaymentModel.php    # Modelo de pagos
│   ├── NotificationModel.php # Modelo de notificaciones
│   └── AnalyticsModel.php  # Modelo de analytics
├── public/
│   ├── admin/              # Panel de administración
│   ├── index.php           # Página principal
│   ├── dashboard.php       # Panel de usuario
│   ├── giftlist.php        # Visualización de listas
│   └── webhook_mercadopago.php # Webhook de pagos
├── templates/
│   └── notifications/      # Templates de notificaciones
├── assets/              # CSS y JavaScript
└── install.php          # Instalador (¡eliminar después!)
```

## 🎯 Funcionalidades Detalladas

### **Panel de Usuario**
- ✅ Creación y edición de listas
- ✅ Gestión de regalos con categorías
- ✅ Configuración de precios y stock
- ✅ Enlaces compartibles únicos
- ✅ Notificaciones de compras
- ✅ Historial de transacciones
- ✅ Estadísticas personales

### **Panel de Administración**
- 📊 **Dashboard con métricas en tiempo real**
- 👥 **Gestión completa de usuarios**
- 💳 **Control de pagos y transacciones**
- 📧 **Centro de notificaciones masivas**
- 📈 **Reportes y analytics avanzados**
- ⚙️ **Configuración del sistema**
- 🔧 **Herramientas de mantenimiento**

### **Sistema de Pagos**
- 💰 **Integración completa con MercadoPago**
- 🔔 **Webhooks automáticos**
- 📧 **Notificaciones post-pago**
- 🛡️ **Validaciones de seguridad**
- 📊 **Tracking de conversiones**
- 🔄 **Manejo de reembolsos**

### **Analytics y Reportes**
- 📈 **Métricas de usuarios activos**
- 💹 **Reportes de ingresos**
- 🎯 **Estadísticas de engagement**
- 🏆 **Top listas más populares**
- 📊 **Gráficos interactivos**
- 📄 **Exportación a CSV**

## 🔧 Mantenimiento

### **Logs del Sistema**
- `logs/webhook.log` - Eventos de MercadoPago
- `logs/payment.log` - Transacciones
- `logs/notification.log` - Envío de emails/SMS
- `logs/error.log` - Errores del sistema

### **Tareas de Mantenimiento**
```php
// Limpiar logs antiguos (ejecutar mensualmente)
php scripts/cleanup_logs.php

// Procesar notificaciones pendientes
php scripts/process_notifications.php

// Generar reportes diarios
php scripts/daily_reports.php
```

### **Backup Recomendado**
1. **Base de datos**: Backup diario automático
2. **Archivos subidos**: Backup semanal
3. **Configuración**: Backup mensual

## 🚨 Seguridad

### **Medidas Implementadas**
- ✅ Protección CSRF
- ✅ Validación de entrada
- ✅ Escape de salida
- ✅ Prepared statements
- ✅ Rate limiting
- ✅ Logging de seguridad

### **Recomendaciones Adicionales**
1. **Usar HTTPS**: Certificado SSL obligatorio
2. **Firewall**: Configurar reglas básicas
3. **Actualizaciones**: Mantener PHP y MySQL actualizados
4. **Backups**: Configurar backups automáticos
5. **Monitoreo**: Revisar logs regularmente

## 🎨 Personalización

### **Templates de Email**
- Ubicación: `templates/notifications/email/`
- Formato: HTML con variables `{{variable}}`
- Personalizables por evento

### **Estilos CSS**
- Archivo principal: `assets/css/style.css`
- Framework: Bootstrap 5
- Totalmente personalizable

### **Configuración de Aplicación**
```php
'application' => [
    'name' => 'Tu Nombre de App',
    'email' => 'tu-email@dominio.com',
    'url' => 'https://tu-dominio.com',
    'timezone' => 'America/Santiago'
]
```

## 📞 Soporte

### **Problemas Comunes**

**Error: "No se puede conectar a la base de datos"**
- Verifica las credenciales en `includes/db.php`
- Comprueba que el servidor MySQL esté funcionando

**Error: "Webhook de MercadoPago no funciona"**
- Verifica que la URL del webhook sea accesible
- Revisa los logs en `logs/webhook.log`
- Confirma que el certificado SSL sea válido

**Error: "No se envían emails"**
- Configura correctamente SMTP en `config/config.php`
- Verifica que las credenciales sean correctas
- Revisa los logs de notificaciones

### **Obtener Ayuda**
- 📧 Email: soporte@listadedeseos.com
- 🐛 Issues: [GitHub Issues](https://github.com/faliagag/deseos/issues)
- 📖 Wiki: [GitHub Wiki](https://github.com/faliagag/deseos/wiki)

## 🔄 Actualizaciones

### **Versión 2.0 (Actual)**
- ✅ Sistema de pagos con MercadoPago
- ✅ Panel de administración avanzado
- ✅ Analytics y reportes completos
- ✅ Sistema de notificaciones
- ✅ Optimización para hosting compartido

### **Próximas Características**
- 🔄 API REST completa
- 📱 Aplicación móvil
- 🌐 Soporte multi-idioma
- 💬 Chat en vivo
- 🎨 Constructor de temas visual

## 📄 Licencia

Este proyecto está licenciado bajo la Licencia MIT. Ver el archivo `LICENSE` para más detalles.

---

**Desarrollado con ❤️ para hacer realidad los sueños**

© 2025 Lista de Deseos - Sistema Completo