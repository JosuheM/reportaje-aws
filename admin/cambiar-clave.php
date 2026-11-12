<?php
/**
 * Cambiar correo y/o contraseña de un usuario DESDE CONSOLA.
 *
 * Uso (desde C:\xampp\htdocs\reportaje):
 *   C:\xampp\php\php.exe admin\cambiar-clave.php <correo_actual> <clave_nueva> [correo_nuevo]
 *
 * Ejemplos:
 *   C:\xampp\php\php.exe admin\cambiar-clave.php admin@dialogoydesarrollo.com.pe MiClaveSegura123
 *   C:\xampp\php\php.exe admin\cambiar-clave.php admin@dialogoydesarrollo.com.pe MiClaveSegura123 nuevo@correo.com
 *
 * Borra este archivo cuando termines.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por consola.\n");
}

require_once __DIR__ . '/config/conexion.php';

$correoActual = $argv[1] ?? '';
$claveNueva   = $argv[2] ?? '';
$correoNuevo  = $argv[3] ?? $correoActual;

if ($correoActual === '' || $claveNueva === '') {
    fwrite(STDERR, "Faltan datos.\n  php admin\\cambiar-clave.php <correo_actual> <clave_nueva> [correo_nuevo]\n");
    exit(1);
}
if (strlen($claveNueva) < 6) {
    fwrite(STDERR, "La contraseña debe tener al menos 6 caracteres.\n");
    exit(1);
}
if (!filter_var($correoNuevo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El correo nuevo no es válido: {$correoNuevo}\n");
    exit(1);
}

$sel = db()->prepare('SELECT id AS usuario_id FROM usuarios WHERE email = ?');
$sel->execute([$correoActual]);
$row = $sel->fetch();
if (!$row) {
    fwrite(STDERR, "No existe un usuario con el correo: {$correoActual}\n");
    exit(1);
}

$hash = password_hash($claveNueva, PASSWORD_DEFAULT);
$upd = db()->prepare(
    'UPDATE usuarios SET email = ?, password_hash = ? WHERE id = ?'
);
$upd->execute([$correoNuevo, $hash, $row['usuario_id']]);

echo "OK. Usuario #{$row['usuario_id']} actualizado.\n";
echo "  Correo    : {$correoNuevo}\n";
echo "  Contraseña: (nueva, guardada como hash bcrypt)\n";
echo "Ahora borra este archivo: admin\\cambiar-clave.php\n";
