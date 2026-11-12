<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/../inc/publico.php';   // para embed_media()
requiere_login();
requiere_seccion('podcast');
requiere_admin();   // 'autor' no entra aquí
$yo = usuario_actual();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('podcast.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'guardar') {
        $id     = (int) ($_POST['id'] ?? 0);
        $tit    = trim($_POST['titulo'] ?? '');
        $embed  = trim($_POST['url_embed'] ?? '');
        $fecha  = trim($_POST['fecha_publicacion'] ?? '');
        $fecha  = $fecha !== '' ? substr(str_replace('T', ' ', $fecha), 0, 10) : date('Y-m-d');

        if ($tit === '')   { flash_set('El título es obligatorio.', 'warning'); redir('podcast.php'); }
        if ($embed === '') { flash_set('Pega el enlace o el código para embeber el episodio.', 'warning'); redir('podcast.php'); }

        if ($id > 0) {
            db()->prepare('UPDATE podcasts SET titulo=?, url_embed=?, fecha_publicacion=? WHERE id=?')
               ->execute([$tit, $embed, $fecha, $id]);
        } else {
            $ord = (int) db()->query('SELECT COALESCE(MAX(orden),0)+10 FROM podcasts')->fetchColumn();
            db()->prepare('INSERT INTO podcasts (titulo, url_embed, fecha_publicacion, orden, usuario_id)
                           VALUES (?, ?, ?, ?, ?)')
               ->execute([$tit, $embed, $fecha, $ord, $yo['id']]);
        }
        flash_set('Podcast guardado.');
        redir('podcast.php');
    }

    if ($accion === 'eliminar') {
        db()->prepare('DELETE FROM podcasts WHERE id=?')->execute([(int) $_POST['id']]);
        flash_set('Podcast eliminado.');
        redir('podcast.php');
    }

    if ($accion === 'ordenar') {
        $id  = (int) ($_POST['id'] ?? 0);
        $ord = (int) ($_POST['orden'] ?? 0);
        if ($id > 0) {
            db()->prepare('UPDATE podcasts SET orden = ? WHERE id = ?')->execute([$ord, $id]);
            flash_set('Orden actualizado.');
        }
        redir('podcast.php');
    }
    redir('podcast.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = db()->prepare('SELECT * FROM podcasts WHERE id=?');
    $st->execute([(int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$modo = isset($_GET['nuevo']) || $editar ? 'form' : 'lista';

[$flash, $flashTipo] = flash_get();
$filas = db()->query('SELECT * FROM podcasts ORDER BY orden ASC, id ASC')->fetchAll();

$titulo = 'Podcast';
$head_extra = '<style>.ratio-16x9{position:relative;width:100%;padding-top:56.25%;border-radius:8px;overflow:hidden;background:#f0f0f0}.ratio-16x9>iframe,.ratio-16x9>*{position:absolute;inset:0;width:100%;height:100%;border:0}</style>';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid d-flex justify-content-between align-items-center">
        <h1 class="m-0">Podcast</h1>
        <?php if ($modo === 'lista'): ?>
            <div>
                <a class="btn btn-outline-secondary" target="_blank" href="<?= e(base_url('../podcast.php')) ?>"><i class="fas fa-external-link-alt mr-1"></i>Ver en la web</a>
                <a class="btn btn-primary" href="?nuevo=1"><i class="fas fa-plus mr-1"></i>Nuevo episodio</a>
            </div>
        <?php else: ?>
            <a class="btn btn-default" href="podcast.php"><i class="fas fa-arrow-left mr-1"></i>Volver a la lista</a>
        <?php endif; ?>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <?php if ($modo === 'lista'): ?>
        <div class="card">
            <div class="card-body table-responsive p-0">
                <p class="text-muted px-3 pt-3 mb-2"><i class="fas fa-info-circle mr-1"></i>Escribe el número de orden (menor = primero). En la portada salen los 4 primeros.</p>
                <table class="table table-hover align-middle">
                    <thead><tr><th style="width:110px">Orden</th><th>Título</th><th>Fuente</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$filas): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin episodios todavía.</td></tr>
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
                            <td><?= e($r['titulo']) ?></td>
                            <td class="text-truncate" style="max-width:260px"><small class="text-muted"><?= e(mb_strimwidth(strip_tags($r['url_embed']), 0, 70, '…')) ?></small></td>
                            <td class="text-nowrap"><?= e($r['fecha_publicacion'] ?? '') ?></td>
                            <td class="text-right text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $r['id'] ?>"><i class="fas fa-edit"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este episodio?');">
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
            $d = $editar ?: ['id'=>0,'titulo'=>'','url_embed'=>'','fecha_publicacion'=>''];
        ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card"><div class="card-body">
                        <div class="form-group">
                            <label>Título del episodio</label>
                            <input class="form-control" name="titulo" value="<?= e($d['titulo']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Enlace o código para embeber</label>
                            <textarea class="form-control" name="url_embed" rows="4" required placeholder="Pega el enlace de Spotify / YouTube, o el código &lt;iframe&gt; completo"><?= e($d['url_embed'] ?? '') ?></textarea>
                            <small class="text-muted">
                                Sirve un enlace normal de <b>YouTube</b> (youtu.be/…), de <b>Spotify</b>
                                (open.spotify.com/episode/…) o el <code>&lt;iframe&gt;</code> que da el botón "Compartir → Insertar".
                            </small>
                        </div>
                        <div class="form-group">
                            <label>Fecha</label>
                            <input type="date" class="form-control" name="fecha_publicacion"
                                   value="<?= e($d['fecha_publicacion'] ? substr($d['fecha_publicacion'],0,10) : date('Y-m-d')) ?>">
                        </div>
                    </div></div>
                </div>
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Vista previa</h3></div>
                        <div class="card-body">
                            <?php if (!empty($d['url_embed'])): ?>
                                <div class="ratio-16x9"><?= embed_media($d['url_embed']) ?></div>
                            <?php else: ?>
                                <p class="text-muted mb-0">Guarda y vuelve a editar para ver la vista previa.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary btn-block">Guardar episodio</button></div>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
