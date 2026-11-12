<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';
requiere_login();
requiere_seccion('actualidad');
$yo       = usuario_actual();
$soyAdmin = es_admin();
$miId     = (int) $yo['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('actualidad.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $tit     = trim($_POST['titulo'] ?? '');
        $url     = trim($_POST['link_externo'] ?? '');
        $fecha   = trim($_POST['fecha_publicacion'] ?? '');
        $fotoActual = trim($_POST['foto_actual'] ?? '');
        $fecha = $fecha !== '' ? substr(str_replace('T', ' ', $fecha), 0, 10) : date('Y-m-d');

        if ($tit === '') { flash_set('El título es obligatorio.', 'warning'); redir('actualidad.php'); }

        // Permisos: el autor solo edita lo suyo y su versión queda en borrador.
        if ($id > 0) {
            $q = db()->prepare('SELECT usuario_id FROM noticias WHERE id = ?');
            $q->execute([$id]);
            $dueno = $q->fetchColumn();
            if ($dueno === false) { flash_set('Esa noticia no existe.', 'warning'); redir('actualidad.php'); }
            if (!$soyAdmin && (int) $dueno !== $miId) {
                flash_set('Solo puedes editar tus propias noticias.', 'warning'); redir('actualidad.php');
            }
        }
        $estado = $soyAdmin
            ? ((($_POST['estado'] ?? 'publicado') === 'borrador') ? 'borrador' : 'publicado')
            : 'borrador';

        $foto = $fotoActual;
        $avisoPeso = null;
        if (!empty($_FILES['foto_file']['name'])) {
            $up = subir_imagen($_FILES['foto_file'], slugify($tit) . '-nota');
            if (!$up['ok']) { flash_set('Foto: ' . $up['error'], 'warning'); redir('actualidad.php'); }
            $foto = $up['nombre'];
            $avisoPeso = $up['aviso'] ?? null;
        }

        if ($id > 0) {
            db()->prepare('UPDATE noticias SET titulo=?, link_externo=?, fecha_publicacion=?, foto=?, estado=? WHERE id=?')
               ->execute([$tit, ($url ?: null), $fecha, ($foto ?: null), $estado, $id]);
        } else {
            $ord = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM noticias')->fetchColumn();
            db()->prepare('INSERT INTO noticias (titulo, link_externo, fecha_publicacion, foto, estado, orden, usuario_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$tit, ($url ?: null), $fecha, ($foto ?: null), $estado, $ord, $yo['id']]);
        }
        $msg = $estado === 'borrador'
            ? ($soyAdmin ? 'Guardada como borrador.' : 'Guardada. Un administrador la revisará y la publicará.')
            : 'Noticia guardada y publicada.';
        flash_set($avisoPeso ? $msg . ' ' . $avisoPeso : $msg, $avisoPeso ? 'warning' : 'success');
        redir('actualidad.php');
    }

    if ($accion === 'eliminar') {
        if (!$soyAdmin) { flash_set('Solo un administrador puede eliminar noticias.', 'warning'); redir('actualidad.php'); }
        db()->prepare('DELETE FROM noticias WHERE id=?')->execute([(int) $_POST['id']]);
        flash_set('Noticia eliminada.');
        redir('actualidad.php');
    }

    if ($accion === 'estado') {
        if (!$soyAdmin) { redir('actualidad.php'); }
        $id    = (int) ($_POST['id'] ?? 0);
        $nuevo = (($_POST['valor'] ?? '') === 'publicado') ? 'publicado' : 'borrador';
        if ($id > 0) {
            db()->prepare('UPDATE noticias SET estado = ? WHERE id = ?')->execute([$nuevo, $id]);
            flash_set($nuevo === 'publicado' ? 'Noticia publicada.' : 'Noticia pasada a borrador.');
        }
        redir('actualidad.php');
    }

    if ($accion === 'ordenar') {
        if (!$soyAdmin) { redir('actualidad.php'); }
        $id  = (int) ($_POST['id'] ?? 0);
        $ord = (int) ($_POST['orden'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE noticias SET orden = ? WHERE id = ?')->execute([$ord, $id]);
            flash_set('Orden actualizado.');
        }
        redir('actualidad.php');
    }
    redir('actualidad.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM noticias WHERE id=?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
    if ($editar && !$soyAdmin && (int) $editar['usuario_id'] !== $miId) {
        flash_set('Solo puedes editar tus propias noticias.', 'warning');
        redir('actualidad.php');
    }
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
if ($soyAdmin) {
    $filas = db()->query('SELECT * FROM noticias ORDER BY orden ASC, id ASC')->fetchAll();
} else {
    $st = db()->prepare('SELECT * FROM noticias WHERE usuario_id = ? ORDER BY orden ASC, id ASC');
    $st->execute([$miId]);
    $filas = $st->fetchAll();
}

$titulo = 'Actualidad';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Actualidad <small class="text-muted">— Noticias recientes</small></h1>
        <?php if ($modo === 'lista'): ?>
            <div>
                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('../index.php#actualidad')) ?>"><i class="fas fa-external-link-alt mr-1"></i>Ver en la web</a>
                <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nueva noticia</a>
            </div>
        <?php else: ?>
            <a class="btn btn-default" href="actualidad.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
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
                    <?php if ($soyAdmin): ?>
                        Orden: menor = primero (en la web salen las 3 primeras). <b>Borrador</b> = no se ve en la web; pulsa <b>Publicar</b>.
                    <?php else: ?>
                        Aquí ves <b>solo tus noticias</b>. Al guardar quedan en <b>borrador</b> hasta que un administrador las publique.
                    <?php endif; ?>
                </p>
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <?php if ($soyAdmin): ?><th style="width:110px">Orden</th><?php endif; ?>
                        <th>Foto</th><th>Título</th><th>Enlace</th><th>Fecha</th><th>Estado</th><th></th>
                    </tr></thead>
                    <tbody>
                    <?php $ncols = $soyAdmin ? 7 : 6; if (!$filas): ?>
                        <tr><td colspan="<?= $ncols ?>" class="text-center text-muted py-4">Sin noticias todavía.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($filas as $r): $esBorrador = ($r['estado'] ?? 'publicado') === 'borrador'; ?>
                        <tr class="<?= $esBorrador ? 'table-warning' : '' ?>">
                            <?php if ($soyAdmin): ?>
                            <td class="text-nowrap">
                                <form method="post" class="form-inline m-0">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="ordenar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <input type="number" name="orden" value="<?= (int) $r['orden'] ?>" style="width:62px" class="form-control form-control-sm mr-1">
                                    <button class="btn btn-sm btn-outline-secondary" title="Guardar orden"><i class="fas fa-check"></i></button>
                                </form>
                            </td>
                            <?php endif; ?>
                            <td><?php if ($r['foto']): ?>
                                <img src="<?= e(base_url('../assets/images/' . $r['foto'])) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:4px;">
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                            <td><?= e($r['titulo']) ?></td>
                            <td class="text-truncate" style="max-width:240px"><?= e($r['link_externo'] ?? '') ?></td>
                            <td class="text-nowrap"><?= e($r['fecha_publicacion'] ?? '') ?></td>
                            <td>
                                <?php if ($esBorrador): ?>
                                    <span class="badge badge-warning"><i class="fas fa-pencil-alt mr-1"></i>Borrador</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Publicado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>" title="Editar"><i class="fas fa-edit"></i></a>
                                <?php if ($soyAdmin): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <?php if ($esBorrador): ?>
                                            <input type="hidden" name="valor" value="publicado">
                                            <button class="btn btn-sm btn-success" title="Publicar"><i class="fas fa-upload mr-1"></i>Publicar</button>
                                        <?php else: ?>
                                            <input type="hidden" name="valor" value="borrador">
                                            <button class="btn btn-sm btn-outline-warning" title="Quitar de la web"><i class="fas fa-eye-slash"></i></button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta noticia?');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else:
            $d = $editar ?: ['id'=>0,'titulo'=>'','link_externo'=>'','fecha_publicacion'=>'','foto'=>'','estado'=>'publicado'];
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="foto_actual" value="<?= e($d['foto']) ?>">
            <div class="row">
                <div class="col-lg-7">
                    <div class="card"><div class="card-body">
                        <div class="form-group">
                            <label>Título</label>
                            <input class="form-control" name="titulo" value="<?= e($d['titulo']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Enlace (URL de la noticia externa)</label>
                            <input class="form-control" name="link_externo" value="<?= e($d['link_externo'] ?? '') ?>" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label>Fecha de publicación</label>
                            <input type="date" class="form-control" name="fecha_publicacion"
                                   value="<?= e($d['fecha_publicacion'] ? substr($d['fecha_publicacion'],0,10) : date('Y-m-d')) ?>">
                        </div>
                    </div></div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Foto</h3></div>
                        <div class="card-body">
                            <?php if ($d['foto']): ?>
                                <div class="mb-2"><img src="<?= e(base_url('../assets/images/' . $d['foto'])) ?>" class="img-fluid rounded" alt=""></div>
                            <?php endif; ?>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="foto_file" id="foto_file" accept="image/*">
                                <label class="custom-file-label" for="foto_file">Elegir imagen…</label>
                            </div>
                            <small class="text-muted">JPG/PNG/WEBP, máx 8 MB.</small>
                        </div>
                        <?php if ($soyAdmin): ?>
                        <div class="card-body pt-0">
                            <label>Estado</label>
                            <select class="form-control" name="estado">
                                <option value="publicado" <?= ($d['estado'] ?? 'publicado') === 'publicado' ? 'selected' : '' ?>>Publicado (se ve en la web)</option>
                                <option value="borrador"  <?= ($d['estado'] ?? '') === 'borrador' ? 'selected' : '' ?>>Borrador (oculto)</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <div class="card-body pt-0">
                            <div class="alert alert-warning small mb-0"><i class="fas fa-info-circle mr-1"></i>Al guardar queda en <b>borrador</b>; un administrador la publicará.</div>
                        </div>
                        <?php endif; ?>
                        <div class="card-footer"><button class="btn btn-primary btn-block"><?= $soyAdmin ? 'Guardar noticia' : 'Guardar borrador' ?></button></div>
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
