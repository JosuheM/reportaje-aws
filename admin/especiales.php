<?php
/**
 * Sección "Especiales": piezas destacadas que embeben un episodio de Podcast
 * (u otro embed) junto con una portada, resumen y, opcionalmente, el reportaje
 * relacionado. Solo admin/superadmin la gestionan (como Boletines y Podcast).
 */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';
require_once __DIR__ . '/../inc/publico.php';   // embed_media()
requiere_login();
requiere_seccion('especiales');
requiere_admin();   // 'autor' no entra aquí
$yo = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('especiales.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $tit     = trim($_POST['titulo'] ?? '');
        $resumen = trim($_POST['resumen'] ?? '');
        $embed   = trim($_POST['url_embed'] ?? '');
        $repId   = (int) ($_POST['reportaje_id'] ?? 0);
        $fecha   = trim($_POST['fecha_publicacion'] ?? '');
        $fotoActual = trim($_POST['foto_actual'] ?? '');
        $fecha = $fecha !== '' ? substr(str_replace('T', ' ', $fecha), 0, 10) : date('Y-m-d');

        if ($tit === '') { flash_set('El título es obligatorio.', 'warning'); redir('especiales.php'); }

        // el reportaje relacionado (si se eligió) debe existir de verdad
        if ($repId > 0) {
            $chk = db()->prepare('SELECT id FROM reportajes WHERE id = ?');
            $chk->execute([$repId]);
            if (!$chk->fetch()) { $repId = 0; }
        }

        $foto = $fotoActual;
        $avisoPeso = null;
        if (!empty($_FILES['foto_file']['name'])) {
            $up = subir_imagen($_FILES['foto_file'], slugify($tit) . '-especial');
            if (!$up['ok']) { flash_set('Portada: ' . $up['error'], 'warning'); redir('especiales.php'); }
            $foto = $up['nombre'];
            $avisoPeso = $up['aviso'] ?? null;
        }

        $resumen = $resumen !== '' ? mb_substr($resumen, 0, 500) : null;

        if ($id > 0) {
            db()->prepare('UPDATE especiales SET titulo=?, resumen=?, foto_portada=?, url_embed=?, reportaje_id=?, fecha_publicacion=? WHERE id=?')
               ->execute([$tit, $resumen, ($foto ?: null), ($embed ?: null), ($repId ?: null), $fecha, $id]);
        } else {
            $ord = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM especiales')->fetchColumn();
            db()->prepare('INSERT INTO especiales (titulo, resumen, foto_portada, url_embed, reportaje_id, fecha_publicacion, orden, usuario_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
               ->execute([$tit, $resumen, ($foto ?: null), ($embed ?: null), ($repId ?: null), $fecha, $ord, $yo['id']]);
        }
        flash_set($avisoPeso ? 'Especial guardado. ' . $avisoPeso : 'Especial guardado.', $avisoPeso ? 'warning' : 'success');
        redir('especiales.php');
    }

    if ($accion === 'eliminar') {
        db()->prepare('DELETE FROM especiales WHERE id=?')->execute([(int) $_POST['id']]);
        flash_set('Especial eliminado.');
        redir('especiales.php');
    }

    if ($accion === 'ordenar') {
        $id  = (int) ($_POST['id'] ?? 0);
        $ord = (int) ($_POST['orden'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE especiales SET orden = ? WHERE id = ?')->execute([$ord, $id]);
            flash_set('Orden actualizado.');
        }
        redir('especiales.php');
    }
    redir('especiales.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM especiales WHERE id=?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
$filas = db()->query(
    "SELECT e.*, r.titulo AS reportaje_titulo
     FROM especiales e LEFT JOIN reportajes r ON r.id = e.reportaje_id
     ORDER BY e.orden ASC, e.id ASC"
)->fetchAll();

// reportajes publicados, para el selector "reportaje relacionado"
$reportajesDisp = db()->query(
    "SELECT id, titulo FROM reportajes WHERE estado = 'publicado' ORDER BY fecha_publicacion DESC, id DESC"
)->fetchAll();

$titulo = 'Especiales';
$head_extra = '<style>.ratio-16x9{position:relative;width:100%;padding-top:56.25%;border-radius:8px;overflow:hidden;background:#f0f0f0}.ratio-16x9>iframe,.ratio-16x9>*{position:absolute;inset:0;width:100%;height:100%;border:0}</style>';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Especiales</h1>
        <?php if ($modo === 'lista'): ?>
            <div>
                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('../especiales.php')) ?>"><i class="fas fa-external-link-alt mr-1"></i>Ver en la web</a>
                <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nuevo especial</a>
            </div>
        <?php else: ?>
            <a class="btn btn-default" href="especiales.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
        <?php endif; ?>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <?php if ($modo === 'lista'): ?>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <p class="text-muted px-3 pt-3 mb-2"><i class="fas fa-info-circle mr-1"></i>
                    Un "especial" es una pieza destacada con su propio podcast embebido y, si quieres, un reportaje relacionado. Orden: menor = primero.</p>
                <table class="table table-hover align-middle">
                    <thead><tr><th style="width:110px">Orden</th><th>Portada</th><th>Título</th><th>Reportaje relacionado</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$filas): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin especiales todavía.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($filas as $r): ?>
                        <tr>
                            <td class="text-nowrap">
                                <form method="post" class="form-inline m-0">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="ordenar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="number" name="orden" value="<?= (int) $r['orden'] ?>" style="width:62px" class="form-control form-control-sm mr-1">
                                    <button class="btn btn-sm btn-outline-secondary" title="Guardar orden"><i class="fas fa-check"></i></button>
                                </form>
                            </td>
                            <td>
                                <?php if ($r['foto_portada']): ?>
                                    <img src="<?= e(base_url('../assets/images/' . $r['foto_portada'])) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:4px;">
                                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                            </td>
                            <td><?= e($r['titulo']) ?><?= $r['url_embed'] ? '' : ' <span class="badge badge-light" title="Sin podcast embebido todavía">sin embed</span>' ?></td>
                            <td><?= $r['reportaje_titulo'] ? e(mb_strimwidth($r['reportaje_titulo'], 0, 40, '…')) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-nowrap"><?= e($r['fecha_publicacion'] ?? '') ?></td>
                            <td class="text-right text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url('../especiales.php#e' . (int) $r['id'])) ?>" title="Ver"><i class="fas fa-eye"></i></a>
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>" title="Editar"><i class="fas fa-edit"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este especial?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else:
            $d = $editar ?: ['id'=>0,'titulo'=>'','resumen'=>'','foto_portada'=>'','url_embed'=>'','reportaje_id'=>0,'fecha_publicacion'=>''];
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="foto_actual" value="<?= e($d['foto_portada'] ?? '') ?>">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card"><div class="card-body">
                        <div class="form-group">
                            <label>Título del especial</label>
                            <input class="form-control form-control-lg" name="titulo" value="<?= e($d['titulo']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Resumen <small class="text-muted">(máx 500)</small></label>
                            <textarea class="form-control" name="resumen" rows="3" maxlength="500"><?= e($d['resumen'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Podcast a embeber <small class="text-muted">(opcional)</small></label>
                            <textarea class="form-control" name="url_embed" rows="3" placeholder="Enlace de YouTube / Spotify, o el &lt;iframe&gt; completo"><?= e($d['url_embed'] ?? '') ?></textarea>
                            <small class="text-muted">Igual que en Podcast: pega un enlace de YouTube/Spotify o el código de "Insertar".</small>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-8">
                                <label>Reportaje relacionado <small class="text-muted">(opcional)</small></label>
                                <select class="form-control" name="reportaje_id">
                                    <option value="0">— Ninguno —</option>
                                    <?php foreach ($reportajesDisp as $rep): ?>
                                        <option value="<?= (int) $rep['id'] ?>" <?= (int) ($d['reportaje_id'] ?? 0) === (int) $rep['id'] ? 'selected' : '' ?>>
                                            <?= e(mb_strimwidth($rep['titulo'], 0, 70, '…')) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-4">
                                <label>Fecha</label>
                                <input type="date" class="form-control" name="fecha_publicacion"
                                       value="<?= e($d['fecha_publicacion'] ? substr($d['fecha_publicacion'],0,10) : date('Y-m-d')) ?>">
                            </div>
                        </div>
                    </div></div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Portada y vista previa</h3></div>
                        <div class="card-body">
                            <?php if ($d['foto_portada']): ?>
                                <div class="mb-2"><img src="<?= e(base_url('../assets/images/' . $d['foto_portada'])) ?>" class="img-fluid rounded" alt=""></div>
                            <?php endif; ?>
                            <div class="custom-file mb-3">
                                <input type="file" class="custom-file-input" name="foto_file" id="foto_file" accept="image/*">
                                <label class="custom-file-label" for="foto_file">Elegir imagen…</label>
                            </div>
                            <?php if (!empty($d['url_embed'])): ?>
                                <div class="ratio-16x9"><?= embed_media($d['url_embed']) ?></div>
                            <?php else: ?>
                                <p class="text-muted small mb-0">Sin podcast embebido (opcional). Guarda y vuelve a editar para ver la vista previa si pegas uno.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary btn-block">Guardar especial</button></div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php
$foot_scripts = '<script>
$(function(){ $("#foto_file").on("change", function(){
  $(this).next(".custom-file-label").text(this.files.length ? this.files[0].name : "Elegir imagen…");
}); });
</script>';
require __DIR__ . '/partials/footer.php';
?>
