<?php
/**
 * Arranque común: sesión, helpers de auth y de rutas.
 * Incluir al inicio de TODA página del panel.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/conexion.php';

/**
 * Secciones del panel que están HABILITADAS.
 * Para desbloquear una, agrega su nombre a la lista: 'actualidad', 'boletines', 'imagenes'.
 */
const SECCIONES_ACTIVAS = ['reportajes', 'boletines', 'actualidad', 'imagenes', 'podcast', 'especiales'];

function seccion_activa(string $s): bool
{
    return in_array($s, SECCIONES_ACTIVAS, true);
}

/** Corta el acceso a una sección deshabilitada (usar al inicio de su página). */
function requiere_seccion(string $s): void
{
    if (!seccion_activa($s)) {
        flash_set('Esa sección todavía no está disponible.', 'warning');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/** URL base del panel, p. ej. /reportaje/admin */
function base_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'], 1)), '/');
    // dirname del script actual da .../admin cuando el script está en admin/
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    // Normaliza para que siempre apunte a la carpeta admin/
    $adminPos = strpos($dir, '/admin');
    if ($adminPos !== false) {
        $dir = substr($dir, 0, $adminPos) . '/admin';
    }
    return $dir . ($path ? '/' . ltrim($path, '/') : '');
}

/** ¿Hay una sesión iniciada? */
function esta_logueado(): bool
{
    return !empty($_SESSION['usuario_id']);
}

/** Exige sesión; si no hay, manda al login. */
function requiere_login(): void
{
    if (!esta_logueado()) {
        header('Location: ' . base_url('login.php'));
        exit;
    }
}

/** Datos del usuario en sesión (o null). */
function usuario_actual(): ?array
{
    if (!esta_logueado()) {
        return null;
    }
    return [
        'id'     => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'] ?? '',
        'email'  => $_SESSION['usuario_email'] ?? '',
        'rol'    => $_SESSION['usuario_rol'] ?? '',
    ];
}

/**
 * Relee nombre/correo/rol del usuario desde la base en cada carga, para que
 * un cambio de rol o el borrado de la cuenta surta efecto al instante
 * (la sesión nunca conserva un rol viejo más alto del que le toca).
 */
function refrescar_usuario_sesion(): void
{
    if (!esta_logueado()) {
        return;
    }
    try {
        $st = db()->prepare('SELECT nombres, ap_paterno, email, rol FROM usuarios WHERE id = ? LIMIT 1');
        $st->execute([(int) $_SESSION['usuario_id']]);
        $u = $st->fetch();
    } catch (Throwable $e) {
        return; // si la BD falla, no tumbamos la sesión
    }
    if (!$u) {
        // el usuario ya no existe: cerrar sesión
        $_SESSION = [];
        session_destroy();
        return;
    }
    $_SESSION['usuario_nombre'] = trim($u['nombres'] . ' ' . $u['ap_paterno']);
    $_SESSION['usuario_email']  = $u['email'];
    $_SESSION['usuario_rol']    = $u['rol'];
}
refrescar_usuario_sesion();

/** Rol del usuario en sesión ('' si no hay). */
function rol_actual(): string
{
    return $_SESSION['usuario_rol'] ?? '';
}

/** ¿El usuario en sesión es superadministrador? (único que gestiona ese rol) */
function es_superadmin(): bool
{
    return rol_actual() === 'superadmin';
}

/** ¿Puede administrar todo el panel y publicar? (superadmin o admin) */
function es_admin(): bool
{
    return in_array(rol_actual(), ['superadmin', 'admin'], true);
}

/** ¿Es autor? (solo crea borradores de su propio contenido) */
function es_autor(): bool
{
    return rol_actual() === 'autor';
}

/** Corta el acceso si no es superadministrador. */
function requiere_superadmin(): void
{
    if (!es_superadmin()) {
        flash_set('Solo el superadministrador puede entrar aquí.', 'warning');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/** Corta el acceso si no es admin o superadmin (deja fuera a 'autor'). */
function requiere_admin(): void
{
    if (!es_admin()) {
        flash_set('No tienes permiso para entrar aquí.', 'warning');
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/** Escape corto para HTML. */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

/** Token CSRF de la sesión. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Valida el token CSRF enviado por POST. */
function csrf_ok(): bool
{
    return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

/** Guarda un mensaje flash para la siguiente carga. */
function flash_set(string $msg, string $tipo = 'success'): void
{
    $_SESSION['flash'] = $msg;
    $_SESSION['flash_tipo'] = $tipo;
}

/** Devuelve [mensaje, tipo] y lo limpia. */
function flash_get(): array
{
    $m = $_SESSION['flash'] ?? '';
    $t = $_SESSION['flash_tipo'] ?? 'success';
    unset($_SESSION['flash'], $_SESSION['flash_tipo']);
    return [$m, $t];
}

/** Redirige a una página del panel y corta la ejecución. */
function redir(string $pagina): void
{
    header('Location: ' . base_url($pagina));
    exit;
}
