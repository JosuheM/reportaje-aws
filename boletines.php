<?php
/** Listado público de Boletín NTEP: 9 por página, con paginación. */
require_once __DIR__ . '/admin/config/conexion.php';
require_once __DIR__ . '/inc/publico.php';

$total = (int) db()->query('SELECT COUNT(*) FROM boletines')->fetchColumn();
$pg = paginacion($total, 9);

$st = db()->prepare(
    'SELECT id, numero_boletin, resumen, fecha_publicacion, foto_portada, archivo_pdf
     FROM boletines
     ORDER BY orden ASC, id ASC
     LIMIT :lim OFFSET :off'
);
$st->bindValue(':lim', $pg['por_pagina'], PDO::PARAM_INT);
$st->bindValue(':off', $pg['offset'], PDO::PARAM_INT);
$st->execute();
$boletines = $st->fetchAll();

frente_header('DDP Noticias | Boletín NTEP', 'boletines');
?>
<section class="breadcrumb-area py-sm-5 py-4">
    <div class="container">
        <div class="row"><div class="col-md-12"><div class="breadcrumb-contents">
            <h2 class="title-big">Boletines NTEP</h2>
            <div class="breadcrumb"><ul>
                <li><a href="index.php">Inicio</a></li>
                <li class="active"> Boletines<?= $pg['total_paginas'] > 1 ? ' · página ' . $pg['pagina'] . ' de ' . $pg['total_paginas'] : '' ?></li>
            </ul></div>
        </div></div></div>
    </div>
</section>

<div class="grids-block-5 boletines-grid py-5">
    <section class="py-lg-4 py-md-3">
        <div class="container">
            <div class="row">
                <?php if (!$boletines): ?>
                    <div class="col-12 text-center text-muted py-5">Aún no hay boletines publicados.</div>
                <?php endif; ?>
                <?php foreach ($boletines as $i => $b):
                    $pdf = $b['archivo_pdf'] ? 'boletines/' . rawurlencode($b['archivo_pdf']) : '#';
                    $img = $b['foto_portada'] ? 'assets/images/' . $b['foto_portada'] : 'assets/images/logo.png';
                ?>
                <div class="col-lg-4 col-md-6 grids5-info <?= $i >= 3 ? 'mt-5' : '' ?>">
                    <a target="_blank" href="<?= h($pdf) ?>" class="d-block"><img src="<?= h($img) ?>" alt="" class="img-fluid" loading="lazy" decoding="async" /></a>
                    <div class="blog-info">
                        <h5><?= h(fecha_larga($b['fecha_publicacion'])) ?><?= $b['numero_boletin'] ? ' · ' . h($b['numero_boletin']) : '' ?></h5>
                        <?php if (!empty($b['resumen'])): ?>
                            <p><?= h(mb_strimwidth($b['resumen'], 0, 130, '…')) ?></p>
                        <?php endif; ?>
                        <a target="_blank" href="<?= h($pdf) ?>" class="btn mt-4 p-0">Ver boletín <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php paginador($pg['pagina'], $pg['total_paginas'], 'boletines.php'); ?>
        </div>
</div>
<?php frente_footer(); ?>
