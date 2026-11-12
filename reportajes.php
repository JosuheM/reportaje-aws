<?php
/** Listado público de reportajes: 9 por página, con paginación. Admite ?mes=YYYY-MM (bloque Archivos). */
require_once __DIR__ . '/admin/config/conexion.php';
require_once __DIR__ . '/inc/publico.php';

$mes = trim($_GET['mes'] ?? '');
$mesValido = preg_match('/^\d{4}-\d{2}$/', $mes) === 1;
$where = "estado = 'publicado'" . ($mesValido ? " AND DATE_FORMAT(fecha_publicacion, '%Y-%m') = " . db()->quote($mes) : '');

$total = (int) db()->query("SELECT COUNT(*) FROM reportajes WHERE $where")->fetchColumn();
$pg = paginacion($total, 9);

$st = db()->prepare(
    "SELECT id, titulo, resumen_corto, fecha_publicacion, foto_principal
     FROM reportajes
     WHERE $where
     ORDER BY orden ASC, id ASC
     LIMIT :lim OFFSET :off"
);
$st->bindValue(':lim', $pg['por_pagina'], PDO::PARAM_INT);
$st->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$st->execute();
$reportajes = $st->fetchAll();

$extraQs = $mesValido ? ['mes' => $mes] : [];
if ($mesValido) {
    [$y, $m] = explode('-', $mes);
    $mesEtiqueta = mes_largo((int) $m) . ' ' . $y;
}

frente_header('DDP Noticias | Reportajes', 'reportajes');
?>
<section class="breadcrumb-area py-sm-5 py-4">
    <div class="container">
        <div class="row"><div class="col-md-12"><div class="breadcrumb-contents">
            <h2 class="title-big">Reportajes<?= $mesValido ? ' · ' . h($mesEtiqueta) : '' ?></h2>
            <div class="breadcrumb"><ul>
                <li><a href="index.php">Inicio</a></li>
                <li class="active"> Reportajes<?= $pg['total_paginas'] > 1 ? ' · página ' . $pg['pagina'] . ' de ' . $pg['total_paginas'] : '' ?></li>
                <?php if ($mesValido): ?><li><a href="reportajes.php">Quitar filtro de mes</a></li><?php endif; ?>
            </ul></div>
        </div></div></div>
    </div>
</section>

<div class="grids-block-5 py-5">
    <section class="py-lg-4 py-md-3">
        <div class="container">
            <div class="row">
                <?php if (!$reportajes): ?>
                    <div class="col-12 text-center text-muted py-5">
                        <?= $mesValido ? 'No hay reportajes publicados en ' . h($mesEtiqueta) . '.' : 'Aún no hay reportajes publicados.' ?>
                    </div>
                <?php endif; ?>
                <?php foreach ($reportajes as $i => $r):
                    $url = url_reportaje($r);
                    $img = $r['foto_principal'] ? 'assets/images/' . $r['foto_principal'] : 'assets/images/logo.png';
                ?>
                <div class="col-lg-4 col-md-6 grids5-info <?= $i >= 3 ? 'mt-5' : '' ?>">
                    <a href="<?= h($url) ?>" class="d-block"><img src="<?= h($img) ?>" alt="" class="img-fluid" loading="lazy" decoding="async" /></a>
                    <div class="blog-info">
                        <h5><?= h(fecha_larga($r['fecha_publicacion'])) ?></h5>
                        <h4><a href="<?= h($url) ?>" class="d-block"><?= h($r['titulo']) ?></a></h4>
                        <?php if (!empty($r['resumen_corto'])): ?>
                            <p><?= h(mb_strimwidth($r['resumen_corto'], 0, 140, '…')) ?></p>
                        <?php endif; ?>
                        <a href="<?= h($url) ?>" class="btn mt-4 p-0">Leer <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php paginador($pg['pagina'], $pg['total_paginas'], 'reportajes.php', $extraQs); ?>
        </div>
</div>
<?php frente_footer(); ?>
