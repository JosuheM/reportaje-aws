<?php
/** Listado público de Especiales: piezas destacadas con podcast embebido. */
require_once __DIR__ . '/admin/config/conexion.php';
require_once __DIR__ . '/inc/publico.php';

$especiales = db()->query(
    "SELECT e.*, r.titulo AS reportaje_titulo
     FROM especiales e LEFT JOIN reportajes r ON r.id = e.reportaje_id AND r.estado = 'publicado'
     ORDER BY e.orden ASC, e.id ASC"
)->fetchAll();

frente_header('DDP Noticias | Especiales', 'especiales');
?>
<section class="breadcrumb-area py-sm-5 py-4">
    <div class="container">
        <div class="row"><div class="col-md-12"><div class="breadcrumb-contents">
            <h2 class="title-big">Especiales</h2>
            <div class="breadcrumb"><ul>
                <li><a href="index.php">Inicio</a></li>
                <li class="active"> Especiales</li>
            </ul></div>
        </div></div></div>
    </div>
</section>

<section class="w3l-homeblock3 py-5">
    <div class="container py-lg-4">
        <div class="row">
            <?php if (!$especiales): ?>
                <div class="col-12 text-center text-muted py-5">Aún no hay especiales publicados.</div>
            <?php endif; ?>
            <?php foreach ($especiales as $esp): ?>
            <div class="col-lg-6 mb-5" id="e<?= (int) $esp['id'] ?>">
                <div class="area-box h-100 d-flex flex-column">
                    <?php if ($esp['url_embed']): ?>
                        <div class="mb-3"><?= tarjeta_podcast($esp['url_embed'], $esp['titulo'], 'esp-' . $esp['id']) ?></div>
                    <?php elseif ($esp['foto_portada']): ?>
                        <a href="<?= $esp['reportaje_id'] ? h(url_reportaje($esp['reportaje_id'])) : '#' ?>" class="d-block mb-3">
                            <img src="assets/images/<?= h($esp['foto_portada']) ?>" alt="" class="img-fluid" style="border-radius:8px;aspect-ratio:16/9;object-fit:cover;width:100%" loading="lazy" decoding="async">
                        </a>
                    <?php endif; ?>
                    <h4 class="mb-2"><?= h($esp['titulo']) ?></h4>
                    <?php if ($esp['fecha_publicacion']): ?><span class="text-muted small mb-2 d-block"><?= h(fecha_larga($esp['fecha_publicacion'])) ?></span><?php endif; ?>
                    <?php if (!empty($esp['resumen'])): ?><p><?= h($esp['resumen']) ?></p><?php endif; ?>
                    <?php if ($esp['reportaje_id'] && $esp['reportaje_titulo']): ?>
                        <a href="<?= h(url_reportaje($esp['reportaje_id'])) ?>" class="btn mt-auto p-0">
                            Leer el reportaje: <?= h(mb_strimwidth($esp['reportaje_titulo'], 0, 50, '…')) ?> <span class="fa fa-arrow-right"></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php frente_footer(); ?>
