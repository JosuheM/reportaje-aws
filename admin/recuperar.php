<?php
/**
 * Recuperar la contraseña del panel con el CÓDIGO MAESTRO.
 * El código está en admin/config/conexion.php -> CODIGO_RECUPERACION.
 */
require_once __DIR__ . '/config/init.php';

if (esta_logueado()) {
    header('Location: ' . base_url('index.php'));
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $p1     = (string) ($_POST['password'] ?? '');
    $p2     = (string) ($_POST['password2'] ?? '');
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Sesión expirada, vuelve a intentar.';
    } elseif ($codigo === '' || $email === '' || $p1 === '') {
        $error = 'Completa todos los campos.';
    } elseif (!hash_equals(CODIGO_RECUPERACION, $codigo)) {
        // pequeña espera para desalentar prueba y error
        sleep(1);
        $error = 'Código maestro incorrecto.';
    } elseif (strlen($p1) < 6) {
        $error = 'La contraseña nueva debe tener al menos 6 caracteres.';
    } elseif ($p1 !== $p2) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        $st = db()->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $st->execute([$email]);
        $u = $st->fetch();
        if (!$u) {
            $error = 'No hay ningún usuario con ese correo.';
        } else {
            db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
               ->execute([password_hash($p1, PASSWORD_DEFAULT), $u['id']]);
            unset($_SESSION['csrf']);
            $ok = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · Panel DDP Noticias</title>
    <link rel="stylesheet" href="<?= e(base_url('plugins/fontawesome-free/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('dist/css/adminlte.min.css')) ?>">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo"><b>DDP</b> Noticias</div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Recuperar contraseña</p>

            <?php if ($ok): ?>
                <div class="alert alert-success">
                    Contraseña cambiada. Ya puedes <a href="<?= e(base_url('login.php')) ?>">iniciar sesión</a>.
                </div>
            <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <div class="input-group mb-3">
                        <input type="text" name="codigo" class="form-control" placeholder="Código maestro" required autofocus>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-key"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Correo del usuario"
                               value="<?= e($_POST['email'] ?? '') ?>" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Contraseña nueva" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password2" class="form-control" placeholder="Repite la contraseña" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Cambiar contraseña</button>
                </form>
            <?php endif; ?>

            <a class="btn btn-link btn-block mt-2" href="<?= e(base_url('login.php')) ?>">Volver al login</a>
        </div>
    </div>
</div>
</body>
</html>
