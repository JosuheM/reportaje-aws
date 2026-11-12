<?php
require_once __DIR__ . '/config/init.php';

// Si ya está logueado, al panel.
if (esta_logueado()) {
    header('Location: ' . base_url('index.php'));
    exit;
}

$error = '';

// Token CSRF simple.
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');
    $csrf  = $_POST['csrf'] ?? '';

    if (!hash_equals($_SESSION['csrf'], $csrf)) {
        $error = 'Sesión expirada, vuelve a intentar.';
    } elseif ($email === '' || $pass === '') {
        $error = 'Completa correo y contraseña.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, nombres, ap_paterno, email, password_hash, rol
             FROM usuarios WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if ($u && password_verify($pass, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id']     = (int) $u['id'];
            $_SESSION['usuario_nombre'] = trim($u['nombres'] . ' ' . $u['ap_paterno']);
            $_SESSION['usuario_email']  = $u['email'];
            $_SESSION['usuario_rol']    = $u['rol'] ?? 'redactor';
            unset($_SESSION['csrf']);
            header('Location: ' . base_url('index.php'));
            exit;
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · Panel DDP Noticias</title>
    <link rel="stylesheet" href="<?= e(base_url('plugins/fontawesome-free/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('dist/css/adminlte.min.css')) ?>">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <b>DDP</b> Noticias
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">Inicia sesión para administrar el sitio</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" action="">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Correo"
                           value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-envelope"></span></div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                    <div class="input-group-append">
                        <div class="input-group-text"><span class="fas fa-lock"></span></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary btn-block">Ingresar</button>
                    </div>
                </div>
            </form>
            <p class="mt-3 mb-1 text-center">
                <a href="<?= e(base_url('recuperar.php')) ?>">¿Olvidaste tu contraseña?</a>
            </p>
            <?php if (defined('CODIGO_REGISTRO') && CODIGO_REGISTRO !== ''): ?>
            <p class="mb-0 text-center">
                <a href="<?= e(base_url('registrarse.php')) ?>">Crear una cuenta</a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="<?= e(base_url('plugins/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(base_url('plugins/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(base_url('dist/js/adminlte.min.js')) ?>"></script>
</body>
</html>
