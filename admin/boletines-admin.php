<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';
requiere_login();
requiere_seccion('boletines');
requiere_admin();   // 'autor' no entra aquí
$yo = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('boletines-admin.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id      = (int) ($_POST['id'] ?? 0);
        $numero  = trim($_POST['numero_boletin'] ?? '');
        $fecha   = trim($_POST['fecha_publicacion'] ?? '');
        $resumen = trim($_POST['resumen'] ?? '');
        $fotoActual    = trim($_POST['foto_actual'] ?? '');
        $archivoActual = trim($_POST['archivo_actual'] ?? '');
        $fecha = $fecha !== '' ? substr(str_replace('T', ' ', $fecha), 0, 10) : date('Y-m-d');

        if ($numero === '') { flash_set('El número es obligatorio.', 'warning'); redir('boletines-admin.php'); }

        // numero_boletin es único
        $chk = db()->prepare('SELECT id FROM boletines WHERE numero_boletin = ? AND id <> ?');
        $chk->execute([$numero, $id]);
        if ($chk->fetch()) { flash_set('Ya existe un boletín con el número "' . $numero . '".', 'warning'); redir('boletines-admin.php'); }

        $base = slugify('boletin-ntep-' . $numero);

        $foto = $fotoActual;
        $avisoPeso = null;
        if (!empty($_FILES['foto_file']['name'])) {
            $up = subir_imagen($_FILES['foto_file'], $base);
            if (!$up['ok']) { flash_set('Portada: ' . $up['error'], 'warning'); redir('boletines-admin.php'); }
            $foto = $up['nombre'];
            $avisoPeso = $up['aviso'] ?? null;
        }

        $archivo = $archivoActual;
        if (!empty($_FILES['archivo_file']['name'])) {
            $up = subir_pdf($_FILES['archivo_file'], $base);
            if (!$up['ok']) { flash_set('PDF: ' . $up['error'], 'warning'); redir('boletines-admin.php'); }
            $archivo = $up['nombre'];
        }
        if ($archivo === '') { flash_set('Debes subir el archivo PDF del boletín.', 'warning'); redir('boletines-admin.php'); }

        $resumen = mb_substr($resumen, 0, 500);

        if ($id > 0) {
            db()->prepare('UPDATE boletines SET numero_boletin=?, fecha_publicacion=?, resumen=?, foto_portada=?, archivo_pdf=? WHERE id=?')
               ->execute([$numero, $fecha, ($resumen ?: null), ($foto ?: null), $archivo, $id]);
        } else {
            $ord = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM boletines')->fetchColumn();
            db()->prepare('INSERT INTO boletines (numero_boletin, resumen, foto_portada, archivo_pdf, fecha_publicacion, orden, usuario_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
               ->execute([$numero, ($resumen ?: null), ($foto ?: null), $archivo, $fecha, $ord, $yo['id']]);
        }
        flash_set($avisoPeso ? 'Boletín guardado. ' . $avisoPeso : 'Boletín guardado.', $avisoPeso ? 'warning' : 'success');
        redir('boletines-admin.php');
    }

    if ($accion === 'eliminar') {
        db()->prepare('DELETE FROM boletines WHERE id=?')->execute([(int) $_POST['id']]);
        flash_set('Boletín eliminado.');
        redir('boletines-admin.php');
    }

    if ($accion === 'ordenar') {
        $id  = (int) ($_POST['id'] ?? 0);
        $ord = (int) ($_POST['orden'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE boletines SET orden = ? WHERE id = ?')->execute([$ord, $id]);
            flash_set('Orden actualizado.');
        }
        redir('boletines-admin.php');
    }
    redir('boletines-admin.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM boletines WHERE id=?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
$filas = db()->query('SELECT * FROM boletines ORDER BY orden ASC, id ASC')->fetchAll();

$titulo = 'Boletín NTEP';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Boletín NTEP</h1>
        <?php if ($modo === 'lista'): ?>
            <div>
                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('../boletines.php')) ?>"><i class="fas fa-external-link-alt mr-1"></i>Ver en la web</a>
                <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nuevo boletín</a>
            </div>
        <?php else: ?>
            <a class="btn btn-default" href="boletines-admin.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
        <?php endif; ?>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <?php if ($modo === 'lista'): ?>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <p class="text-muted px-3 pt-3 mb-2"><i class="fas fa-info-circle mr-1"></i>Escribe el número de orden (menor = primero) y pulsa Enter o el check. Ese es el orden de la web.</p>
                <table class="table table-hover align-middle">
                    <thead><tr><th style="width:110px">Orden</th><th>Portada</th><th>Número</th><th>Fecha</th><th>PDF</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$filas): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">Sin boletines todavía.</td></tr>
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
                            <td><?php if ($r['foto_portada']): ?>
                                <img src="<?= e(base_url('../assets/images/' . $r['foto_portada'])) ?>" alt="" style="width:48px;height:60px;object-fit:cover;border-radius:4px;">
                            <?php else: ?><span class="text-muted small">—</span><?php endif; ?></td>
                            <td><?= e($r['numero_boletin'] ?? '') ?></td>
                            <td class="text-nowrap"><?= e($r['fecha_publicacion'] ?? '') ?></td>
                            <td>
                                <?php if ($r['archivo_pdf']): ?>
                                    <a target="_blank" href="<?= e(base_url('../boletines/' . $r['archivo_pdf'])) ?>"><i class="far fa-file-pdf"></i> ver</a>
                                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                            </td>
                            <td class="text-right text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>"><i class="fas fa-edit"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este boletín?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php else:
            $d = $editar ?: ['id'=>0,'numero_boletin'=>'','fecha_publicacion'=>'','resumen'=>'','foto_portada'=>'','archivo_pdf'=>''];
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="foto_actual" value="<?= e($d['foto_portada']) ?>">
            <input type="hidden" name="archivo_actual" value="<?= e($d['archivo_pdf']) ?>">

            <div class="row">
                <div class="col-lg-7">
                    <div class="card"><div class="card-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Número <small class="text-muted">(ej. Nº 45)</small></label>
                                <input class="form-control" name="numero_boletin" value="<?= e($d['numero_boletin'] ?? '') ?>" required>
                            </div>
                            <div class="form-group col-md-6">
                                <label>Fecha de publicación</label>
                                <input type="date" class="form-control" name="fecha_publicacion"
                                       value="<?= e($d['fecha_publicacion'] ? substr($d['fecha_publicacion'],0,10) : date('Y-m-d')) ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Resumen / titulares <small class="text-muted">(máx 500 caracteres)</small></label>
                            <textarea class="form-control" name="resumen" rows="5" maxlength="500"><?= e($d['resumen'] ?? '') ?></textarea>
                        </div>
                    </div></div>
                </div>
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Archivos</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Portada (imagen)</label>
                                <?php if ($d['foto_portada']): ?>
                                    <div class="mb-2"><img src="<?= e(base_url('../assets/images/' . $d['foto_portada'])) ?>" class="img-fluid rounded" style="max-height:180px" alt=""></div>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="foto_file" id="foto_file" accept="image/*">
                                    <label class="custom-file-label" for="foto_file">Elegir imagen…</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Archivo PDF del boletín <?= $d['id'] ? '' : '<span class="text-danger">*</span>' ?></label>
                                <?php if ($d['archivo_pdf']): ?>
                                    <p class="small mb-1"><a target="_blank" href="<?= e(base_url('../boletines/' . $d['archivo_pdf'])) ?>"><i class="far fa-file-pdf"></i> <?= e($d['archivo_pdf']) ?></a></p>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="archivo_file" id="archivo_file" accept="application/pdf" <?= $d['id'] ? '' : 'required' ?>>
                                    <label class="custom-file-label" for="archivo_file">Elegir PDF…</label>
                                </div>
                                <small class="text-muted">Máx 25 MB. Al editar, déjalo vacío para conservar el actual.</small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary btn-block">Guardar boletín</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php
$foot_scripts = '<script>
$(function(){
  $(".custom-file-input").on("change", function(){
    var n = this.files.length ? this.files[0].name : ($(this).attr("accept").indexOf("pdf")>-1 ? "Elegir PDF…" : "Elegir imagen…");
    $(this).next(".custom-file-label").text(n);
  });
});
</script>';
require __DIR__ . '/partials/footer.php';
?>
