<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';
requiere_login();
$yo       = usuario_actual();
$soyAdmin = es_admin();          // superadmin|admin: publica, ve todo, borra, ordena, destaca
$miId     = (int) $yo['id'];

/**
 * Registra en `reportajes_fotos` TODAS las imágenes del reportaje:
 * la portada + cada <img> que haya en el cuerpo. Se rehace en cada guardado
 * para que la tabla refleje siempre las imágenes actuales. (Solo BD, no se muestra en la web.)
 * `descripcion` incluye el título del reportaje para que se entienda en phpMyAdmin.
 */
function sincronizar_fotos(int $rid, string $tituloReportaje, ?string $portada, string $cuerpo): void
{
    $urls = [];
    if ($portada) { $urls[] = $portada; }
    if (preg_match_all('~<img[^>]+src=["\'][^"\']*assets/images/([^"\'/?#]+)~i', $cuerpo, $m)) {
        foreach ($m[1] as $f) { $urls[] = rawurldecode($f); }
    }
    $urls = array_values(array_unique($urls));

    db()->prepare('DELETE FROM reportajes_fotos WHERE reportaje_id = ?')->execute([$rid]);
    if (!$urls) { return; }

    $t = mb_substr(trim($tituloReportaje), 0, 180);
    $ins = db()->prepare('INSERT INTO reportajes_fotos (reportaje_id, url_foto, orden, descripcion) VALUES (?, ?, ?, ?)');
    $ord = 0;
    foreach ($urls as $u) {
        $ord += 10;
        $tipo = ($ord === 10 && $portada) ? 'Portada' : 'Imagen del cuerpo';
        $ins->execute([$rid, $u, $ord, "Reportaje: {$t} — {$tipo}"]);
    }
}

/** Devuelve el id de un autor por su nombre; lo crea si no existe. Vacío => "Redacción". */
function autor_id_de(string $texto): int
{
    $texto = trim(preg_replace('/^\s*Por\s+/iu', '', $texto));
    if ($texto === '') { $texto = 'Redacción'; }
    $q = db()->prepare('SELECT id FROM autores WHERE nombres = ? LIMIT 1');
    $q->execute([$texto]);
    $id = $q->fetchColumn();
    if (!$id) {
        db()->prepare('INSERT INTO autores (nombres, es_nickname) VALUES (?, ?)')
           ->execute([$texto, $texto === 'Redacción' ? 1 : 0]);
        $id = (int) db()->lastInsertId();
    }
    return (int) $id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('reportajes.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id       = (int) ($_POST['id'] ?? 0);
        $titulo   = trim($_POST['titulo'] ?? '');
        $autorTxt = trim($_POST['autor'] ?? '');
        $fecha    = trim($_POST['fecha_publicacion'] ?? '');
        $slug     = trim($_POST['slug'] ?? '');
        $resumen  = trim($_POST['resumen_corto'] ?? '');
        $cuerpo   = $_POST['desarrollo'] ?? '';
        // El editor muestra las imágenes con ../assets/; se guardan relativas a la raíz del sitio.
        $cuerpo = preg_replace('#(src=["\'])(?:\.\./|https?://[^/"\']+/reportaje/|/reportaje/)assets/#i', '$1assets/', $cuerpo);
        $fotoActual = trim($_POST['foto_actual'] ?? '');

        // --- Permisos: un autor solo toca lo suyo; no destaca; su guardado queda en borrador ---
        $filaPrev = null;
        if ($id > 0) {
            $q = db()->prepare('SELECT usuario_id, es_destacado FROM reportajes WHERE id = ?');
            $q->execute([$id]);
            $filaPrev = $q->fetch();
            if (!$filaPrev) { flash_set('Ese reportaje no existe.', 'warning'); redir('reportajes.php'); }
            if (!$soyAdmin && (int) $filaPrev['usuario_id'] !== $miId) {
                flash_set('Solo puedes editar tus propios reportajes.', 'warning'); redir('reportajes.php');
            }
        }
        if ($soyAdmin) {
            $destacado = isset($_POST['es_destacado']) ? 1 : 0;
            $estado    = (($_POST['estado'] ?? 'publicado') === 'borrador') ? 'borrador' : 'publicado';
        } else {
            // el autor no cambia el destacado (se conserva) y su versión SIEMPRE queda en borrador
            $destacado = $filaPrev ? (int) $filaPrev['es_destacado'] : 0;
            $estado    = 'borrador';
        }

        $fecha = $fecha !== '' ? substr(str_replace('T', ' ', $fecha), 0, 10) : date('Y-m-d');
        if ($slug === '' && $titulo !== '') {
            $slug = slugify($titulo) . '.html';
        }

        if ($titulo === '') { flash_set('El título es obligatorio.', 'warning'); redir('reportajes.php'); }

        $autorId  = autor_id_de($autorTxt);
        $resumen  = $resumen !== '' ? mb_substr($resumen, 0, 500) : null;
        $cuerpo   = $cuerpo !== '' ? $cuerpo : '<p></p>';

        // Portada: si suben una nueva, reemplaza; si no, se conserva.
        $foto = $fotoActual;
        $avisoPeso = null;
        if (!empty($_FILES['foto_file']['name'])) {
            $up = subir_imagen($_FILES['foto_file'], slugify($titulo) . '-portada');
            if (!$up['ok']) { flash_set('Portada: ' . $up['error'], 'warning'); redir('reportajes.php'); }
            $foto = $up['nombre'];
            $avisoPeso = $up['aviso'] ?? null;
        }

        if ($id > 0) {
            db()->prepare('UPDATE reportajes SET titulo=?, slug=?, resumen_corto=?, desarrollo=?, foto_principal=?,
                    fecha_publicacion=?, es_destacado=?, estado=?, autor_id=? WHERE id=?')
               ->execute([$titulo, $slug, $resumen, $cuerpo, ($foto ?: null), $fecha, $destacado, $estado, $autorId, $id]);
        } else {
            $ordNuevo = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM reportajes')->fetchColumn();
            db()->prepare('INSERT INTO reportajes
                    (titulo, slug, resumen_corto, desarrollo, foto_principal, fecha_publicacion,
                     es_destacado, estado, orden, origen, autor_id, usuario_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "panel", ?, ?)')
               ->execute([$titulo, $slug, $resumen, $cuerpo, ($foto ?: null), $fecha, $destacado, $estado, $ordNuevo, $autorId, $yo['id']]);
            $id = (int) db()->lastInsertId();
        }
        sincronizar_fotos($id, $titulo, ($foto ?: null), $cuerpo);
        $msg = $estado === 'borrador'
            ? ($soyAdmin ? 'Guardado como borrador (no se ve en la web).' : 'Guardado. Un administrador lo revisará y lo publicará.')
            : 'Reportaje guardado y publicado.';
        flash_set($avisoPeso ? $msg . ' ' . $avisoPeso : $msg, $avisoPeso ? 'warning' : 'success');
        redir('reportajes.php');
    }

    if ($accion === 'eliminar') {
        if (!$soyAdmin) { flash_set('Solo un administrador puede eliminar reportajes.', 'warning'); redir('reportajes.php'); }
        db()->prepare('DELETE FROM reportajes WHERE id=?')->execute([(int) $_POST['id']]);
        flash_set('Reportaje eliminado.');
        redir('reportajes.php');
    }

    if ($accion === 'estado') {   // publicar / pasar a borrador (solo admin)
        if (!$soyAdmin) { redir('reportajes.php'); }
        $id    = (int) ($_POST['id'] ?? 0);
        $nuevo = (($_POST['valor'] ?? '') === 'publicado') ? 'publicado' : 'borrador';
        if ($id > 0) {
            db()->prepare('UPDATE reportajes SET estado = ? WHERE id = ?')->execute([$nuevo, $id]);
            flash_set($nuevo === 'publicado' ? 'Reportaje publicado.' : 'Reportaje pasado a borrador.');
        }
        redir('reportajes.php');
    }

    if ($accion === 'ordenar') {
        if (!$soyAdmin) { redir('reportajes.php'); }
        $id  = (int) ($_POST['id'] ?? 0);
        $ord = (int) ($_POST['orden'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE reportajes SET orden = ? WHERE id = ?')->execute([$ord, $id]);
            flash_set('Orden actualizado.');
        }
        redir('reportajes.php');
    }

    redir('reportajes.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT r.*, a.nombres AS autor_nombre
                         FROM reportajes r LEFT JOIN autores a ON a.id = r.autor_id
                         WHERE r.id = ?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
    if ($editar && !$soyAdmin && (int) $editar['usuario_id'] !== $miId) {
        flash_set('Solo puedes editar tus propios reportajes.', 'warning');
        redir('reportajes.php');
    }
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
$sqlLista = 'SELECT r.id, r.titulo, r.fecha_publicacion, r.es_destacado, r.estado, r.foto_principal, r.slug, r.orden, r.usuario_id,
                    a.nombres AS autor_nombre
             FROM reportajes r LEFT JOIN autores a ON a.id = r.autor_id ';
if ($soyAdmin) {
    $filas = db()->query($sqlLista . 'ORDER BY r.orden ASC, r.id ASC')->fetchAll();
} else {
    $st = db()->prepare($sqlLista . 'WHERE r.usuario_id = ? ORDER BY r.orden ASC, r.id ASC');
    $st->execute([$miId]);
    $filas = $st->fetchAll();
}

$titulo = 'Reportajes';
$head_extra = '<link rel="stylesheet" href="' . e(base_url('plugins/summernote/summernote-bs4.min.css')) . '">';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Reportajes</h1>
        <?php if ($modo === 'lista'): ?>
            <div>
                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('../reportajes.php')) ?>"><i class="fas fa-external-link-alt mr-1"></i>Ver en la web</a>
                <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nuevo reportaje</a>
            </div>
        <?php else: ?>
            <a class="btn btn-default" href="reportajes.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
        <?php endif; ?>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <?php if ($modo === 'lista'): ?>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <div class="px-3 pt-3 text-muted small">
                    <?php if ($soyAdmin): ?>
                    <p class="mb-1"><i class="fas fa-info-circle mr-1"></i><b>Orden</b>: número menor = va primero. En la web se muestran <b>todos</b> los publicados, de 9 en 9 por página.</p>
                    <p class="mb-1"><i class="fas fa-star mr-1"></i><b>En portada</b>: activa "Mostrar en portada" al editar. De los marcados, el de menor orden ocupa el <b>recuadro grande</b> y los 3 siguientes las <b>tarjetas</b>.</p>
                    <p class="mb-2"><i class="fas fa-eye-slash mr-1"></i><b>Borrador</b> = no se ve en la web. Pulsa <b>Publicar</b> para sacarlo al sitio.</p>
                    <?php else: ?>
                    <p class="mb-2"><i class="fas fa-info-circle mr-1"></i>Aquí ves <b>solo tus reportajes</b>. Al guardar quedan en <b>borrador</b> hasta que un administrador los revise y publique.</p>
                    <?php endif; ?>
                </div>
                <table class="table table-hover align-middle">
                    <thead><tr>
                        <?php if ($soyAdmin): ?><th style="width:110px">Orden</th><?php endif; ?>
                        <th>Portada</th><th>Título</th><th>Autor</th><th>Fecha</th><th>Estado</th>
                        <?php if ($soyAdmin): ?><th>En portada</th><?php endif; ?>
                        <th></th>
                    </tr></thead>
                    <tbody>
                    <?php $ncols = $soyAdmin ? 8 : 6; if (!$filas): ?>
                        <tr><td colspan="<?= $ncols ?>" class="text-center text-muted py-4">Sin reportajes todavía.</td></tr>
                    <?php endif; ?>
                    <?php $nDest = 0; $nPub = 0; foreach ($filas as $idx => $r): if ($r['es_destacado'] && $r['estado'] === 'publicado') { $nDest++; }
                        $esBorrador = $r['estado'] === 'borrador';
                        $pagPub = $esBorrador ? 0 : (intdiv($nPub, 9) + 1); if (!$esBorrador) { $nPub++; } ?>
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
                            <td>
                                <?php if ($r['foto_principal']): ?>
                                    <img src="<?= e(base_url('../assets/images/' . $r['foto_principal'])) ?>" alt="" style="width:64px;height:44px;object-fit:cover;border-radius:4px;">
                                <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
                            </td>
                            <td><?= e($r['titulo']) ?><?php if ($pagPub > 1) echo ' <span class="badge badge-light" title="En la web aparece en la página '.$pagPub.'">pág. '.$pagPub.'</span>'; ?></td>
                            <td><?= e($r['autor_nombre'] ?? '') ?></td>
                            <td class="text-nowrap"><?= e($r['fecha_publicacion'] ?? '') ?></td>
                            <td>
                                <?php if ($esBorrador): ?>
                                    <span class="badge badge-warning"><i class="fas fa-pencil-alt mr-1"></i>Borrador</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fas fa-check mr-1"></i>Publicado</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($soyAdmin): ?>
                            <td>
                                <?php if (!$r['es_destacado']): ?>
                                    <span class="badge badge-light">No</span>
                                <?php elseif ($esBorrador): ?>
                                    <span class="badge badge-light" title="Marcado, pero está en borrador">Marcado</span>
                                <?php elseif ($nDest === 1): ?>
                                    <span class="badge badge-danger" title="Ocupa el recuadro grande de la portada"><i class="fas fa-star mr-1"></i>Recuadro grande</span>
                                <?php elseif ($nDest <= 4): ?>
                                    <span class="badge badge-success">Tarjeta <?= $nDest - 1 ?></span>
                                <?php else: ?>
                                    <span class="badge badge-secondary" title="Marcado, pero ya hay 4 en portada">Marcado (no entra)</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td class="text-right text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url('../articulo.php?id=' . $r['id'])) ?>" title="Ver"><i class="fas fa-eye"></i></a>
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>" title="Editar"><i class="fas fa-edit"></i></a>
                                <?php if ($soyAdmin): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                        <?php if ($esBorrador): ?>
                                            <input type="hidden" name="valor" value="publicado">
                                            <button class="btn btn-sm btn-success" title="Publicar en la web"><i class="fas fa-upload mr-1"></i>Publicar</button>
                                        <?php else: ?>
                                            <input type="hidden" name="valor" value="borrador">
                                            <button class="btn btn-sm btn-outline-warning" title="Quitar de la web"><i class="fas fa-eye-slash"></i></button>
                                        <?php endif; ?>
                                    </form>
                                    <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este reportaje?');">
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

        <?php else: /* ---------- FORMULARIO ---------- */
            $d = $editar ?: ['id'=>0,'titulo'=>'','autor_nombre'=>'','fecha_publicacion'=>'',
                             'slug'=>'','resumen_corto'=>'','desarrollo'=>'','foto_principal'=>'','es_destacado'=>0,'estado'=>'publicado'];
        ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <input type="hidden" name="foto_actual" value="<?= e($d['foto_principal']) ?>">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Título</label>
                                <input class="form-control form-control-lg" name="titulo" value="<?= e($d['titulo']) ?>" required>
                            </div>
                            <div class="form-row">
                                <div class="form-group col-md-7">
                                    <label>Autor <small class="text-muted">(la firma; vacío = Redacción)</small></label>
                                    <input class="form-control" name="autor" value="<?= e($d['autor_nombre'] ?? '') ?>" placeholder="Yuri Castro" list="lista-autores">
                                    <datalist id="lista-autores">
                                        <?php foreach (db()->query('SELECT nombres FROM autores ORDER BY nombres')->fetchAll(PDO::FETCH_COLUMN) as $an): ?>
                                        <option value="<?= e($an) ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="form-group col-md-5">
                                    <label>Fecha de publicación</label>
                                    <input type="date" class="form-control" name="fecha_publicacion"
                                           value="<?= e($d['fecha_publicacion'] ? substr($d['fecha_publicacion'],0,10) : date('Y-m-d')) ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Bajada / resumen <small class="text-muted">(párrafo destacado, máx 500)</small></label>
                                <textarea class="form-control" name="resumen_corto" rows="2" maxlength="500"><?= e($d['resumen_corto'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label>Cuerpo del reportaje</label>
                                <textarea id="editor" name="desarrollo"><?= e(str_replace(['src="assets/', "src='assets/"], ['src="../assets/', "src='../assets/"], $d['desarrollo'] ?? '')) ?></textarea>
                                <small class="text-muted">Escribe el texto largo, pon subtítulos en <b>negrita</b> y arrastra imágenes dentro del editor.</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Publicación</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Portada (imagen principal)</label>
                                <?php if ($d['foto_principal']): ?>
                                    <div class="mb-2"><img src="<?= e(base_url('../assets/images/' . $d['foto_principal'])) ?>" class="img-fluid rounded" alt=""></div>
                                    <p class="small text-muted mb-1"><?= e($d['foto_principal']) ?></p>
                                <?php endif; ?>
                                <div class="custom-file">
                                    <input type="file" class="custom-file-input" name="foto_file" id="foto_file" accept="image/*">
                                    <label class="custom-file-label" for="foto_file">Elegir imagen…</label>
                                </div>
                                <small class="text-muted">JPG/PNG/WEBP, máx 8 MB. Déjalo vacío para conservar la actual.</small>
                            </div>
                            <div class="form-group">
                                <label>Archivo de la página (slug)</label>
                                <input class="form-control" name="slug" value="<?= e($d['slug'] ?? '') ?>" placeholder="se genera del título">
                            </div>
                            <?php if ($soyAdmin): ?>
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" class="custom-control-input" name="es_destacado" id="es_destacado" value="1" <?= $d['es_destacado'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="es_destacado">Mostrar en portada</label>
                            </div>
                            <div class="form-group mb-0">
                                <label>Estado</label>
                                <select class="form-control" name="estado">
                                    <option value="publicado" <?= ($d['estado'] ?? 'publicado') === 'publicado' ? 'selected' : '' ?>>Publicado (se ve en la web)</option>
                                    <option value="borrador"  <?= ($d['estado'] ?? '') === 'borrador' ? 'selected' : '' ?>>Borrador (oculto)</option>
                                </select>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning small mb-0">
                                <i class="fas fa-info-circle mr-1"></i>Al guardar, el reportaje queda en <b>borrador</b>.
                                Un administrador lo revisará y lo publicará.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary btn-block"><?= $soyAdmin ? 'Guardar reportaje' : 'Guardar borrador' ?></button>
                            <?php if ($d['id']): ?>
                                <a class="btn btn-outline-secondary btn-block" target="_blank" href="<?= e(base_url('../articulo.php?id=' . $d['id'])) ?>">Ver cómo queda</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<?php if ($modo === 'form'):
    $foot_scripts = '
    <script src="' . e(base_url('plugins/summernote/summernote-bs4.min.js')) . '"></script>
    <script src="' . e(base_url('plugins/summernote/lang/summernote-es-ES.min.js')) . '"></script>
    <script>
    $(function () {
        $("#editor").summernote({
            height: 420, lang: "es-ES",
            placeholder: "Escribe aquí el reportaje...",
            toolbar: [
                ["style", ["style"]],
                ["font", ["bold", "italic", "underline", "clear"]],
                ["para", ["ul", "ol", "paragraph"]],
                ["insert", ["link", "picture"]],
                ["view", ["codeview"]]
            ],
            callbacks: {
                onImageUpload: function (files) {
                    var ed = $("#editor");
                    for (var i = 0; i < files.length; i++) {
                        var fd = new FormData();
                        fd.append("file", files[i]);
                        fd.append("csrf", "' . e(csrf_token()) . '");
                        fetch("' . e(base_url('subir.php')) . '", {method: "POST", body: fd, credentials: "same-origin"})
                            .then(function (r) { return r.json(); })
                            .then(function (j) {
                                if (j.url) {
                                    ed.summernote("insertImage", "../" + j.url, function ($img) {
                                        $img.addClass("img-fluid w-100 radius-image");
                                    });
                                    if (j.aviso) { console.warn(j.aviso); }
                                } else { alert(j.error || "No se pudo subir la imagen"); }
                            })
                            .catch(function () { alert("Error al subir la imagen"); });
                    }
                }
            }
        });
        $(document).on("change", ".custom-file-input", function () {
            var n = this.files.length === 1 ? this.files[0].name
                  : (this.files.length > 1 ? this.files.length + " archivos" : "Elegir…");
            $(this).next(".custom-file-label").text(n);
        });
    });
    </script>';
endif;
require __DIR__ . '/partials/footer.php';
?>
