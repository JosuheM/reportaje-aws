<?php
require_once __DIR__ . '/config/init.php';
requiere_login();
$yo       = usuario_actual();
$soyAdmin = es_admin();
$miId     = (int) $yo['id'];

/** Cuenta filas de una consulta preparada; 0 si algo falla. */
function contar_sql(string $sql, array $args = []): int
{
    try {
        $st = db()->prepare($sql);
        $st->execute($args);
        return (int) $st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}
/** Devuelve filas de una consulta preparada; [] si algo falla. */
function filas_sql(string $sql, array $args = []): array
{
    try {
        $st = db()->prepare($sql);
        $st->execute($args);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
/** Fecha corta d/m/Y a partir de un valor DATE o NULL. */
function f_fecha(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    return $t ? date('d/m/Y', $t) : '—';
}

$titulo = 'Escritorio';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid">
        <h1 class="m-0">Escritorio</h1>
        <p class="text-muted">Bienvenido, <?= e($yo['nombre'] ?: $yo['email']) ?>
            <span class="badge badge-<?= $soyAdmin ? 'dark' : 'secondary' ?>"><?= e($yo['rol']) ?></span>
            · Hoy es <?= date('d/m/Y') ?>.</p>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

    <?php if (!$soyAdmin): /* ================= PANEL DEL AUTOR ================= */
        $misPub  = contar_sql("SELECT COUNT(*) FROM reportajes WHERE usuario_id = ? AND estado = 'publicado'", [$miId]);
        $misBorr = contar_sql("SELECT COUNT(*) FROM reportajes WHERE usuario_id = ? AND estado = 'borrador'",  [$miId]);
        $misNot  = contar_sql("SELECT COUNT(*) FROM noticias   WHERE usuario_id = ?", [$miId]);
        $misBorradores = filas_sql("SELECT id, titulo, fecha_publicacion, estado
                                    FROM reportajes WHERE usuario_id = ?
                                    ORDER BY (estado='borrador') DESC, updated_at DESC, id DESC LIMIT 8", [$miId]);
    ?>
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="small-box bg-info">
                    <div class="inner"><h3><?= $misPub ?></h3><p>Mis reportajes publicados</p></div>
                    <div class="icon"><i class="far fa-newspaper"></i></div>
                    <a href="<?= e(base_url('reportajes.php')) ?>" class="small-box-footer">Ver <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="small-box bg-warning">
                    <div class="inner"><h3><?= $misBorr ?></h3><p>Mis borradores (sin publicar)</p></div>
                    <div class="icon"><i class="fas fa-pencil-alt"></i></div>
                    <a href="<?= e(base_url('reportajes.php')) ?>" class="small-box-footer">Ver <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="small-box bg-primary">
                    <div class="inner"><h3><?= $misNot ?></h3><p>Mis noticias</p></div>
                    <div class="icon"><i class="fas fa-bolt"></i></div>
                    <a href="<?= e(base_url('actualidad.php')) ?>" class="small-box-footer">Ver <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title"><i class="far fa-newspaper mr-2"></i>Mis reportajes</h3></div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>Título</th><th style="width:110px">Fecha</th><th style="width:110px">Estado</th><th style="width:60px"></th></tr></thead>
                            <tbody>
                            <?php if (!$misBorradores): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Todavía no has creado ningún reportaje.</td></tr>
                            <?php else: foreach ($misBorradores as $r): ?>
                                <tr>
                                    <td><?= e($r['titulo']) ?></td>
                                    <td><?= f_fecha($r['fecha_publicacion']) ?></td>
                                    <td>
                                        <?php if ($r['estado'] === 'borrador'): ?>
                                            <span class="badge badge-warning">Borrador</span>
                                        <?php else: ?>
                                            <span class="badge badge-success">Publicado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right"><a class="btn btn-xs btn-outline-primary" href="<?= e(base_url('reportajes.php?editar=' . (int) $r['id'])) ?>"><i class="fas fa-edit"></i></a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Acciones</h3></div>
                    <div class="card-body">
                        <a href="<?= e(base_url('reportajes.php?nuevo=1')) ?>" class="btn btn-info btn-block text-left"><i class="fas fa-plus mr-2"></i>Nuevo reportaje</a>
                        <a href="<?= e(base_url('actualidad.php?nuevo=1')) ?>" class="btn btn-primary btn-block text-left"><i class="fas fa-plus mr-2"></i>Nueva noticia</a>
                    </div>
                    <div class="card-footer text-muted small">
                        <i class="fas fa-info-circle mr-1"></i>Lo que creas queda en <b>borrador</b>. Un administrador lo revisa y lo publica.
                    </div>
                </div>
            </div>
        </div>

    <?php else: /* ================= PANEL DEL ADMIN ================= */
        $tot = [
            'reportajes' => contar_sql('SELECT COUNT(*) FROM reportajes'),
            'borradores' => contar_sql("SELECT COUNT(*) FROM reportajes WHERE estado='borrador'")
                          + contar_sql("SELECT COUNT(*) FROM noticias WHERE estado='borrador'"),
            'actualidad' => contar_sql('SELECT COUNT(*) FROM noticias'),
            'boletines'  => contar_sql('SELECT COUNT(*) FROM boletines'),
            'podcast'    => contar_sql('SELECT COUNT(*) FROM podcasts'),
            'especiales' => contar_sql('SELECT COUNT(*) FROM especiales'),
            'imagenes'   => count(glob(__DIR__ . '/../assets/images/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: []),
            'usuarios'   => contar_sql('SELECT COUNT(*) FROM usuarios'),
        ];
        $ultReportajes = filas_sql('SELECT id, titulo, fecha_publicacion, es_destacado, estado
                                    FROM reportajes ORDER BY fecha_publicacion DESC, id DESC LIMIT 6');
        $ultBoletines  = filas_sql('SELECT id, numero_boletin, fecha_publicacion
                                    FROM boletines ORDER BY fecha_publicacion DESC, id DESC LIMIT 5');
        $ultNoticias   = filas_sql('SELECT id, titulo, fecha_publicacion, estado
                                    FROM noticias ORDER BY fecha_publicacion DESC, id DESC LIMIT 5');
        $ultPodcasts   = filas_sql('SELECT id, titulo, fecha_publicacion
                                    FROM podcasts ORDER BY fecha_publicacion DESC, id DESC LIMIT 5');
        $tarjetas = [
            ['reportajes.php',            'bg-info',      'far fa-newspaper', 'Reportajes',   $tot['reportajes']],
            ['reportajes.php',            'bg-warning',   'fas fa-pencil-alt','Borradores por revisar', $tot['borradores']],
            ['actualidad.php',            'bg-primary',   'fas fa-bolt',      'Actualidad',   $tot['actualidad']],
            ['boletines-admin.php',       'bg-success',   'far fa-file-pdf',  'Boletín NTEP', $tot['boletines']],
            ['podcast.php',               'bg-teal',      'fas fa-podcast',   'Podcast',      $tot['podcast']],
            ['especiales.php',            'bg-orange',    'fas fa-star',      'Especiales',   $tot['especiales']],
            ['usuarios.php',              'bg-dark',      'fas fa-users-cog', 'Usuarios',     $tot['usuarios']],
        ];
    ?>
        <div class="row">
            <?php foreach ($tarjetas as [$arch, $bg, $ic, $lbl, $num]): ?>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="small-box <?= $bg ?>">
                    <div class="inner"><h3><?= (int) $num ?></h3><p><?= $lbl ?></p></div>
                    <div class="icon"><i class="<?= $ic ?>"></i></div>
                    <a href="<?= e(base_url($arch)) ?>" class="small-box-footer">Administrar <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="far fa-newspaper mr-2"></i>Últimos reportajes</h3>
                        <a href="<?= e(base_url('reportajes.php')) ?>" class="btn btn-tool btn-sm">Ver todos</a>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover text-nowrap mb-0">
                            <thead><tr><th>Título</th><th style="width:110px">Fecha</th><th style="width:110px">Estado</th><th style="width:60px"></th></tr></thead>
                            <tbody>
                            <?php if (!$ultReportajes): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aún no hay reportajes.</td></tr>
                            <?php else: foreach ($ultReportajes as $r): ?>
                                <tr>
                                    <td class="text-wrap" style="max-width:420px"><?= e($r['titulo']) ?></td>
                                    <td><?= f_fecha($r['fecha_publicacion']) ?></td>
                                    <td><?= $r['estado'] === 'borrador'
                                            ? '<span class="badge badge-warning">Borrador</span>'
                                            : '<span class="badge badge-success">Publicado</span>' ?></td>
                                    <td class="text-right"><a class="btn btn-xs btn-outline-primary" href="<?= e(base_url('reportajes.php?editar=' . (int) $r['id'])) ?>"><i class="fas fa-edit"></i></a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Actualidad reciente</h3></div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (!$ultNoticias): ?>
                                        <li class="list-group-item text-muted">Sin noticias todavía.</li>
                                    <?php else: foreach ($ultNoticias as $n): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <span class="pr-2"><?= e($n['titulo']) ?>
                                                <?= ($n['estado'] ?? '') === 'borrador' ? ' <span class="badge badge-warning">borrador</span>' : '' ?></span>
                                            <small class="text-muted text-nowrap"><?= f_fecha($n['fecha_publicacion']) ?></small>
                                        </li>
                                    <?php endforeach; endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title"><i class="fas fa-podcast mr-2"></i>Podcasts recientes</h3></div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (!$ultPodcasts): ?>
                                        <li class="list-group-item text-muted">Sin podcasts todavía.</li>
                                    <?php else: foreach ($ultPodcasts as $p): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <span class="pr-2"><?= e($p['titulo']) ?></span>
                                            <small class="text-muted text-nowrap"><?= f_fecha($p['fecha_publicacion']) ?></small>
                                        </li>
                                    <?php endforeach; endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-bolt mr-2"></i>Accesos rápidos</h3></div>
                    <div class="card-body">
                        <a href="<?= e(base_url('reportajes.php?nuevo=1')) ?>" class="btn btn-info btn-block text-left"><i class="fas fa-plus mr-2"></i>Nuevo reportaje</a>
                        <a href="<?= e(base_url('actualidad.php?nuevo=1')) ?>" class="btn btn-primary btn-block text-left"><i class="fas fa-plus mr-2"></i>Nueva noticia</a>
                        <a href="<?= e(base_url('boletines-admin.php?nuevo=1')) ?>" class="btn btn-success btn-block text-left"><i class="fas fa-plus mr-2"></i>Nuevo boletín</a>
                        <a href="<?= e(base_url('podcast.php?nuevo=1')) ?>" class="btn btn-warning btn-block text-left"><i class="fas fa-plus mr-2"></i>Nuevo podcast</a>
                        <a href="<?= e(base_url('medios.php')) ?>" class="btn btn-secondary btn-block text-left"><i class="fas fa-upload mr-2"></i>Subir imágenes</a>
                        <a href="<?= e(base_url('apariencia.php')) ?>" class="btn btn-default btn-block text-left"><i class="fas fa-paint-brush mr-2"></i>Apariencia del sitio</a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title"><i class="far fa-file-pdf mr-2"></i>Últimos boletines</h3>
                        <a href="<?= e(base_url('boletines-admin.php')) ?>" class="btn btn-tool btn-sm">Ver todos</a>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <?php if (!$ultBoletines): ?>
                                <li class="list-group-item text-muted">Sin boletines todavía.</li>
                            <?php else: foreach ($ultBoletines as $b): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>Boletín N.º <strong><?= e($b['numero_boletin']) ?></strong></span>
                                    <small class="text-muted"><?= f_fecha($b['fecha_publicacion']) ?></small>
                                </li>
                            <?php endforeach; endif; ?>
                        </ul>
                    </div>
                </div>

                <div class="card card-outline card-secondary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-toggle-on mr-2"></i>Estado del panel</h3></div>
                    <div class="card-body">
                        <p class="mb-2"><span class="text-success"><i class="fas fa-check-circle mr-1"></i></span>
                            Activas: <strong>Actualidad, Reportajes, Boletín NTEP, Podcast, Especiales, Imágenes</strong>.</p>
                        <p class="mb-0"><span class="text-muted"><i class="far fa-clock mr-1"></i></span>
                            Pendientes: Alianzas y Sobre D&amp;D.</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
