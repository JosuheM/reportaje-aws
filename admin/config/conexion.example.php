<?php
/**
 * Plantilla de conexión.
 *
 * LOCAL (XAMPP): copia este archivo como "conexion.php" y ajusta los valores por
 * defecto de abajo — "conexion.php" NO se sube a git (está en .gitignore).
 *
 *   cp admin/config/conexion.example.php admin/config/conexion.php
 *
 * SERVIDOR (AWS): cada valor se lee primero de una variable de entorno
 * (SetEnv de Apache, ver deploy/aws/setup-ec2.sh). El flujo de despliegue copia esta
 * plantilla como conexion.php, así que en producción NO hay claves en el código.
 *
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_SSL (1 = conexión cifrada, solo si la base es remota)
 *   CODIGO_RECUPERACION, CODIGO_REGISTRO
 */

function ddp_env(string $clave, string $porDefecto): string
{
    // PHP-FPM no siempre expone las variables de entorno con su
    // nombre; se prueba también con el prefijo APPSETTING_.
    foreach ([$clave, 'APPSETTING_' . $clave] as $nombre) {
        foreach ([getenv($nombre), $_SERVER[$nombre] ?? false, $_ENV[$nombre] ?? false] as $valor) {
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }
    }
    return $porDefecto;
}

define('DB_HOST', ddp_env('DB_HOST', 'localhost'));
define('DB_NAME', ddp_env('DB_NAME', 'revista_digital'));
define('DB_USER', ddp_env('DB_USER', 'root'));
define('DB_PASS', ddp_env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL', ddp_env('DB_SSL', '0') === '1');

/**
 * Código maestro para recuperar la contraseña del panel (admin/recuperar.php).
 * CÁMBIALO por algo tuyo y guárdalo en un lugar seguro. Quien lo tenga puede
 * poner una contraseña nueva a cualquier usuario, así que no lo compartas.
 * En producción defínelo SIEMPRE como variable de entorno.
 */
define('CODIGO_RECUPERACION', ddp_env('CODIGO_RECUPERACION', 'CAMBIA-ESTE-CODIGO'));

/**
 * Código de invitación para crear una cuenta desde el login (admin/registrarse.php).
 * Las cuentas creadas así son siempre de rol 'autor' (solo crean borradores).
 * CÁMBIALO y compártelo únicamente con quien deba tener cuenta.
 * Si queda vacío ('') se desactiva el registro público.
 */
define('CODIGO_REGISTRO', ddp_env('CODIGO_REGISTRO', 'CAMBIA-ESTE-CODIGO'));

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    if (DB_SSL) {
        // Una base MySQL remota puede exigir TLS. Se cifra la conexión con el
        // almacén de certificados del sistema.
        $opciones[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
        $opciones[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
    } catch (PDOException $e) {
        http_response_code(500);
        // No mostrar el mensaje real: puede revelar el host o el usuario.
        error_log('Error de conexión a la base de datos: ' . $e->getMessage());
        exit('Error de conexión a la base de datos.');
    }

    return $pdo;
}
