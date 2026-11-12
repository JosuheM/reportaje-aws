<?php
/**
 * Crear una cuenta desde el login, con el CÓDIGO DE INVITACIÓN.
 * El código está en admin/config/conexion.php -> CODIGO_REGISTRO.
 * Toda cuenta creada aquí es de rol 'autor' (solo crea borradores;
 * un administrador revisa y publica). Nunca crea admin ni superadmin.
 */
require_once __DIR__ . '/config/init.php';

if (esta_logueado()) {
    header('Location: ' . base_url('index.php'));
    exit;
}

// Registro desactivado si no hay código configurado.
$registroActivo = defined('CODIGO_REGISTRO') && CODIGO_REGISTRO !== '';

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$ok    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registroActivo) {
    $codigo = trim($_POST['codigo'] ?? '');
    $nom    = trim($_POST['nombres'] ?? '');
    $apPat  = trim($_POST['ap_paterno'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $p1     = (string) ($_POST['password'] ?? '');
    $p2     = (string) ($_POST['password2'] ?? '');
    $csrf   = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Sesión expirada, vuelve a intentar.';
    } elseif ($codigo === '' || $nom === '' || $apPat === '' || $email === '' || $p1 === '') {
        $error = 'Completa todos los campos.';
    } elseif (!hash_equals(CODIGO_REGISTRO, $codigo)) {
        sleep(1);   // frena la prueba y error
        $error = 'Código de invitación incorrecto.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no es válido.';
    } elseif (strlen($p1) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif ($p1 !== $p2) {
        $error = 'Las dos contraseñas no coinciden.';
    } else {
        $chk = db()->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'Ya existe una cuenta con ese correo.';
        } else {
            db()->prepare('INSERT INTO usuarios (nombres, ap_paterno, email, password_hash, rol, created_at)
                           VALUES (?, ?, ?, ?, "autor", NOW())')
               ->execute([$nom, $apPat, $email, password_hash($p1, PASSWORD_DEFAULT)]);
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
    <title>Crear cuenta · Panel DDP Noticias</title>
    <link rel="stylesheet" href="<?= e(base_url('plugins/fontawesome-free/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('dist/css/adminlte.min.css')) ?>">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo"><b>DDP</b> Noticias</div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Crear una cuenta de autor</p>

            <?php if (!$registroActivo): ?>
                <div class="alert alert-warning">
                    El registro está desactivado. Pide a un administrador que te cree la cuenta.
                </div>
            <?php elseif ($ok): ?>
                <div class="alert alert-success">
                    Cuenta creada. Ya puedes <a href="<?= e(base_url('login.php')) ?>">iniciar sesión</a>.
                    Tu contenido quedará en borrador hasta que un administrador lo publique.
                </div>
            <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
                <p class="text-muted small">Necesitas el <b>código de invitación</b> que te dio un administrador.</p>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <div class="input-group mb-3">
                        <input type="text" name="codigo" class="form-control" placeholder="Código de invitación" required autofocus
                               value="<?= e($_POST['codigo'] ?? '') ?>">
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-key"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="text" name="nombres" class="form-control" placeholder="Nombres" required
                               value="<?= e($_POST['nombres'] ?? '') ?>">
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="text" name="ap_paterno" class="form-control" placeholder="Apellido paterno" required
                               value="<?= e($_POST['ap_paterno'] ?? '') ?>">
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-user"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="email" name="email" class="form-control" placeholder="Correo (con este inicias sesión)" required
                               value="<?= e($_POST['email'] ?? '') ?>">
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Contraseña (mínimo 6)" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password2" class="form-control" placeholder="Repite la contraseña" required>
                        <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Crear cuenta</button>
                </form>
            <?php endif; ?>

            <a class="btn btn-link btn-block mt-2" href="<?= e(base_url('login.php')) ?>">Volver al login</a>
        </div>
    </div>
</div>
</body>
</html>
