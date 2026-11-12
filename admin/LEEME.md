# Panel de administración — DDP Noticias

Montado sobre **AdminLTE 3.2.0** (`dist/`, `plugins/`, `adminlte-ref/` intactas).
Lógica propia en `config/`, `partials/`, `*.php`.

## Base de datos: `revista_digital`

- Servidor `localhost` · usuario `root` · sin contraseña. Se configura en `config/conexion.php` (`DB_NAME`).
- Tablas: `usuarios`, `autores`, `reportajes`, `reportajes_fotos`, `noticias`, `boletines`, `podcasts`, `videos`.
- Login: tabla `usuarios`, columnas `email` + `password_hash` (bcrypt).
- La base `bdreportaje` quedó como respaldo; ya **no se usa**.

### Mapa rápido de columnas
| Sección | Tabla | Campos que edita el panel |
|---|---|---|
| Reportajes | `reportajes` | titulo, slug, resumen_corto, desarrollo (cuerpo HTML), foto_principal, fecha_publicacion (DATE), es_destacado, orden, autor_id → **autores** |
| Índice de imágenes | `reportajes_fotos` | se rellena SOLO (no hay pantalla): al guardar un reportaje, registra su portada + cada `<img>` del cuerpo. Es un índice interno, no se muestra en la web |
| Actualidad | `noticias` | titulo, link_externo, foto, fecha_publicacion, orden |
| Boletín NTEP | `boletines` | numero_boletin (único), resumen (máx 500), foto_portada, archivo_pdf (obligatorio), fecha_publicacion, orden |

El **autor** se escribe como texto en el formulario; el panel busca ese nombre en `autores` y lo crea si no existe (vacío = "Redacción").

## Usuarios (solo por código)
- Primer usuario: se inserta directo en la tabla `usuarios` (ver README, instalación local). `admin/setup.php` se eliminó por seguridad.
- Cambiar correo/clave: `C:\xampp\php\php.exe admin\cambiar-clave.php <correo> <clave_nueva> [correo_nuevo]` → **borrar tras usar**.
- Las credenciales del administrador no se documentan aquí.

## Rutas
| URL | Qué hace |
|-----|----------|
| `admin/login.php` / `logout.php` | Acceso |
| `admin/recuperar.php` | Recuperar contraseña con el **código maestro** (`CODIGO_RECUPERACION` en `config/conexion.php`) |
| `admin/index.php` | Escritorio |
| `admin/actualidad.php` | CRUD noticias |
| `admin/reportajes.php` | CRUD reportajes (editor Summernote, subida de portada e imágenes) |
| `admin/boletines-admin.php` | CRUD boletines (sube portada + PDF real) |
| `admin/podcast.php` | CRUD podcast (título + enlace YouTube/Spotify o `<iframe>`) |
| `admin/medios.php` | Biblioteca de imágenes: ver / buscar / subir / borrar las que no se usan |
| `admin/apariencia.php` | Ajustes de apariencia: marco decorativo de tarjetas (esquina roja/blanca) on/off + color |
| `admin/subir.php` | Endpoint de subida de imágenes del editor |
| `admin/cambiar-clave.php` | solo consola (responde 403 por web); **borrar tras usar** |

## Front dinámico (lee de `revista_digital`)
- `index.php` — recuadro grande + 3 tarjetas (reportajes con "Mostrar en portada"), noticias recientes, último boletín, 4 podcasts.
- `reportajes.php` — los 9 primeros por `orden`.
- `boletines.php` — todos por `orden` (portadas completas, sin recorte).
- `podcast.php` — todos los episodios (reproductores embebidos 16:9).
- `articulo.php?id=N` — la nota con la plantilla del sitio; la firma sale de `autores`.
- Miniaturas y embeds en `assets/css/ddp.css`. Redirecciones de URLs viejas en `.htaccess`.

## Siguiente paso
- Secciones **Videos**, **Alianzas** y **Sobre D&D** (tabla `videos` existe; falta admin + front).
- Cambiar `CODIGO_RECUPERACION` en `config/conexion.php` por uno propio.
