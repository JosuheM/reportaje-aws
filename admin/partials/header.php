<?php
/** Cabecera común del panel. Requiere init.php ya incluido y sesión válida. */
$u = usuario_actual();
$titulo = $titulo ?? 'Panel';
$actual = basename($_SERVER['SCRIPT_NAME']);
/** class="active" si estamos en esa página. */
function nav_activo(string $archivo): string
{
    global $actual;
    return $actual === $archivo ? ' active' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> · DDP Noticias</title>
    <link rel="stylesheet" href="<?= e(base_url('plugins/fontawesome-free/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('dist/css/adminlte.min.css')) ?>">
    <?= $head_extra ?? '' ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?= e(base_url('../index.php')) ?>" target="_blank" class="nav-link">Ver sitio</a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <span class="nav-link"><i class="far fa-user mr-1"></i><?= e($u['nombre'] ?: $u['email']) ?></span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?= e(base_url('logout.php')) ?>"><i class="fas fa-sign-out-alt mr-1"></i>Salir</a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="<?= e(base_url('index.php')) ?>" class="brand-link">
            <span class="brand-text font-weight-light ml-2"><b>DDP</b> Noticias</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="<?= e(base_url('index.php')) ?>" class="nav-link<?= nav_activo('index.php') ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i><p>Escritorio</p>
                        </a>
                    </li>
                    <?php
                    /** Ítem del menú: activo (con enlace) o gris (sin enlace) según SECCIONES_ACTIVAS. */
                    function nav_item(string $seccion, string $archivo, string $icono, string $texto): void
                    {
                        if (seccion_activa($seccion)) {
                            echo '<li class="nav-item"><a href="' . e(base_url($archivo)) . '" class="nav-link' . nav_activo($archivo) . '">'
                               . '<i class="nav-icon ' . $icono . '"></i><p>' . $texto . '</p></a></li>';
                        } else {
                            echo '<li class="nav-item"><a class="nav-link text-muted" style="cursor:default">'
                               . '<i class="nav-icon ' . $icono . '"></i><p>' . $texto . '</p></a></li>';
                        }
                    }
                    ?>
                    <?php $puedeAdmin = es_admin(); ?>
                    <li class="nav-header">CONTENIDO</li>
                    <?php
                    nav_item('actualidad', 'actualidad.php',       'fas fa-bolt',      'Actualidad');
                    nav_item('reportajes', 'reportajes.php',        'far fa-newspaper', 'Reportajes');
                    if ($puedeAdmin) {
                        nav_item('boletines', 'boletines-admin.php', 'far fa-file-pdf', 'Boletín NTEP');
                    }
                    ?>

                    <?php if ($puedeAdmin): ?>
                    <li class="nav-header">MULTIMEDIA</li>
                    <?php
                    nav_item('imagenes',   'medios.php',     'far fa-images', 'Imágenes');
                    nav_item('podcast',    'podcast.php',    'fas fa-podcast', 'Podcast');
                    nav_item('especiales', 'especiales.php', 'fas fa-star',    'Especiales');
                    ?>

                    <li class="nav-header">INSTITUCIONAL</li>
                    <?php
                    nav_item('alianzas', '#', 'fas fa-handshake',   'Alianzas');
                    nav_item('sobre',    '#', 'fas fa-info-circle', 'Sobre D&amp;D');
                    ?>

                    <li class="nav-header">AJUSTES</li>
                    <li class="nav-item">
                        <a href="<?= e(base_url('usuarios.php')) ?>" class="nav-link<?= nav_activo('usuarios.php') ?>">
                            <i class="nav-icon fas fa-users-cog"></i><p>Usuarios</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= e(base_url('apariencia.php')) ?>" class="nav-link<?= nav_activo('apariencia.php') ?>">
                            <i class="nav-icon fas fa-paint-brush"></i><p>Apariencia</p>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
