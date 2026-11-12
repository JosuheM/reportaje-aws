# DDP Noticias — Diálogo y Desarrollo Perú

Réplica funcional de [dialogoydesarrollo.com.pe](https://www.dialogoydesarrollo.com.pe) con panel de administración propio, hecha en **PHP + MySQL** y desplegada en **Amazon Web Services** (EC2 con Apache + PHP + MariaDB, capa gratuita).

**URL pública (AWS):** `http://<IP-de-tu-EC2>/` (ver DESPLIEGUE_AWS.md).
**Panel de administración:** `/admin/login.php` en la misma URL (las credenciales no se publican aquí).

## Funcionalidades

- Sitio público dinámico: Reportajes (con paginación), Noticias, Boletín NTEP, Podcast y Especiales.
- Reproductor de podcast en ventana emergente (Spotify / YouTube).
- Imágenes de los reportajes ampliables con clic y con carga diferida (`loading="lazy"`).
- Archivo de reportajes por mes/año.
- Panel de administración (AdminLTE 3.2): CRUD de contenido, biblioteca de medios, apariencia, usuarios y roles (admin / autor).

## Tecnologías

PHP 8.2 · MySQL/MariaDB (PDO con consultas preparadas) · Apache · Bootstrap 4 · AdminLTE 3.2 · AWS EC2

## Estructura

```
├── index.php, reportajes.php, articulo.php, boletines.php, podcast.php, especiales.php
├── inc/                     Funciones del sitio público
├── assets/                  CSS, JS e imágenes
├── admin/                   Panel de administración
│   └── config/
│       ├── conexion.example.php   Plantilla de configuración (SÍ se versiona)
│       └── conexion.php           Credenciales reales (NO se versiona, ver .gitignore)
├── database/schema.sql      Estructura de la base de datos (sin datos)
└── .htaccess                Redirecciones de las URLs antiguas (.html)
```

## Instalación local (XAMPP)

1. Clonar en `C:\xampp\htdocs\reportaje`.
2. En phpMyAdmin crear la base `revista_digital` (cotejamiento `utf8mb4_unicode_ci`) e importar `database/schema.sql`.
3. Copiar `admin/config/conexion.example.php` a `admin/config/conexion.php` y completar usuario, contraseña y códigos.
4. Cambiar `RewriteBase` en `.htaccess` a `/reportaje/` si se sirve desde subcarpeta.
5. Crear el primer usuario administrador directamente en la base (el script `setup.php` se eliminó por seguridad). Primero generar el hash de la contraseña:

   ```
   C:\xampp\php\php.exe -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT);"
   ```

   y luego, en phpMyAdmin → pestaña SQL:

   ```sql
   INSERT INTO usuarios (nombres, ap_paterno, email, password_hash, rol, created_at)
   VALUES ('Tu nombre', '', 'tu@correo.com', 'EL_HASH_GENERADO', 'admin', NOW());
   ```

## Despliegue en AWS

Guía en [DESPLIEGUE_AWS.md](DESPLIEGUE_AWS.md): una instancia EC2 (Amazon Linux 2023, capa gratuita) con Apache + PHP 8.2 + MariaDB, instalada con un solo script (`deploy/aws/setup-ec2.sh`) y actualizada con `deploy/aws/actualizar.sh`.

## Acceso al panel

Las credenciales del panel **no se publican en este repositorio**. Se entregan por un canal privado.

## Seguridad y buenas prácticas

- Contraseñas y códigos maestros fuera del repositorio (`.gitignore` + plantilla `conexion.example.php`).
- Contraseñas de usuarios con `password_hash` (bcrypt); consultas con PDO preparadas; salida escapada con `htmlspecialchars`.
- Subidas de imágenes validadas por tipo MIME real y con nombre generado.
- Scripts de uso único (migraciones, importadores) eliminados del repositorio.
- Historial de Git limpio y commits atómicos con mensajes descriptivos.


