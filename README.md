# GiftList App

Esta aplicación en PHP, MySQL y JavaScript permite a los usuarios crear y gestionar listas de regalos para eventos.

## Funcionalidades

- Registro e inicio de sesión.
- Edición del perfil (nombre y contraseña).
- Creación, edición y eliminación de listas de regalos.
- Cada lista contiene regalos con stock, precio, cantidad vendida y dinero recaudado.
- Buscador de listas de regalos.
- Panel de administración para gestionar usuarios, listas y transacciones.
- Interfaz responsiva con Bootstrap 5.

## Estructura del Proyecto

- **assets/**: Archivos CSS y JavaScript.
- **controllers/**: Lógica de negocio.
- **includes/**: Conexión a la base de datos y funciones auxiliares.
- **models/**: Clases para interactuar con la base de datos.
- **public/**: Páginas públicas (inicio, registro, login, dashboard, creación/edición de listas, etc.) y área administrativa.
- **install.php**: Script de instalación para crear las tablas y crear un usuario administrador (admin@admin.com / admin).
- **.htaccess**: Opcional, para redirección y seguridad.

## Instalación

1. Sube el proyecto a tu hosting y extrae el ZIP.
2. Configura **includes/db.php** con tus credenciales de MySQL.
3. Accede a `install.php` (por ejemplo, `https://tu-dominio.com/install.php`) para crear las tablas. Luego, elimina o protege este archivo.
4. Asegúrate de que el directorio público de tu hosting apunte a la carpeta **public/**.

## Uso

- **index.php**: Página de bienvenida con buscador y listado de listas.
- **register.php** y **login.php**: Registro e inicio de sesión.
- **dashboard.php**: Área de usuario para ver y gestionar listas y editar el perfil.
- **create_giftlist.php**, **edit_giftlist.php**: Gestión de listas de regalos.
- **giftlist.php**: Página pública para ver detalles de una lista y comprar regalos.
- **admin/**: Área administrativa para gestionar usuarios, listas y transacciones.

## Requisitos

- PHP 8.0 o superior
- MySQL
- Servidor web (Apache, Nginx, etc.)
- Composer (para dependencias como PHPMailer y Stripe)
