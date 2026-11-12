<?php
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';
requiere_login();
requiere_seccion('imagenes');
requiere_admin();   // 'autor' no entra aquí

const DIR_IMG = __DIR__ . '/../assets/images';
const DIR_PDF = __DIR__ . '/../boletines';
const RAIZ    = __DIR__ . '/..';

/**
 * Imágenes que NUNCA se deben borrar aunque no estén en la base de datos:
 * son parte de la plantilla (logo, iconos de redes, banners de secciones…).
 */
const IMG_PROTEGIDAS = [
    'logo.png', 'logo1.png', 'logo2.png', 'logo3.png', 'logo4.png', 'logo5.png', 'logo6.png',
    'tiktokp.png', 'tiktokg.png', 'bannerimg.jpg', 'video.jpg', 'stats.jpg', 'podcast.png',
    'team1.jpg', 'team2.jpg', 'team3.jpg', 'team4.jpg', 'team5.jpg', 'team6.jpg', 'team7.jpg', 'team8.jpg',
    's1.jpg', 'mapa-interactivo.png',
];

/** Nombres de imagen referenciados en las plantillas (.php / .css), no en la BD. */
function imagenes_en_plantillas(): array
{
    $set = [];
    $archivos = array_merge(
        glob(RAIZ . '/*.php') ?: [],
        glob(RAIZ . '/inc/*.php') ?: [],
        glob(RAIZ . '/assets/css/*.css') ?: []
    );
    foreach ($archivos as $f) {
        $txt = (string) @file_get_contents($f);
        if ($txt === '') { continue; }
        if (preg_match_all('~assets/images/([A-Za-z0-9._-]+\.(?:jpg|jpeg|png|gif|webp|svg))~i', $txt, $m)) {
            foreach ($m[1] as $n) { $set[$n] = 1; }
        }
    }
    return $set;
}

/** Conjunto de nombres de archivo usados en cualquier parte (BD + plantillas + protegidas). */
function imagenes_usadas(): array
{
    $set = [];
    foreach (db()->query('SELECT foto_principal FROM reportajes WHERE foto_principal IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $v) { $set[$v] = 1; }
    foreach (db()->query('SELECT url_foto FROM reportajes_fotos')->fetchAll(PDO::FETCH_COLUMN) as $v) { $set[$v] = 1; }
    foreach (db()->query('SELECT foto FROM noticias WHERE foto IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $v) { $set[$v] = 1; }
    foreach (db()->query('SELECT foto_portada FROM boletines WHERE foto_portada IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $v) { $set[$v] = 1; }
    foreach (IMG_PROTEGIDAS as $n) { $set[$n] = 1; }
    return $set + imagenes_en_plantillas();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('medios.php'); }
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'subir') {
        $files = $_FILES['imgs'] ?? null;
        if (!$files || empty($files['name'][0])) { flash_set('Elige al menos una imagen.', 'warning'); redir('medios.php'); }
        $ok = 0;
        $pesadas = 0;
        for ($i = 0, $n = count($files['name']); $i < $n; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) { continue; }
            $one = ['name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i],
                    'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
            $r = subir_imagen($one, pathinfo($files['name'][$i], PATHINFO_FILENAME));
            if ($r['ok']) { $ok++; if (!empty($r['aviso'])) { $pesadas++; } } else { flash_set($files['name'][$i] . ': ' . $r['error'], 'warning'); redir('medios.php'); }
        }
        $msg = "$ok imagen(es) subida(s).";
        if ($pesadas > 0) { $msg .= " $pesadas de ellas son pesadas para la web (revisa el peso en la lista de abajo)."; }
        flash_set($msg, $pesadas > 0 ? 'warning' : 'success');
        redir('medios.php');
    }

    if ($accion === 'borrar') {
        $nombre = basename($_POST['nombre'] ?? '');
        $ruta = DIR_IMG . '/' . $nombre;
        if ($nombre !== '' && is_file($ruta)) {
            @unlink($ruta);
            flash_set('Imagen eliminada: ' . $nombre);
        }
        redir('medios.php');
    }

    if ($accion === 'borrar_sin_usar') {
        $usadas = imagenes_usadas();
        $n = 0;
        foreach (glob(DIR_IMG . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $ruta) {
            if (!isset($usadas[basename($ruta)]) && @unlink($ruta)) { $n++; }
        }
        flash_set("$n imagen(es) sin usar eliminadas.");
        redir('medios.php');
    }
    redir('medios.php');
}

/* ---- lista de imágenes en disco ---- */
$imagenes = [];
foreach (glob(DIR_IMG . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [] as $ruta) {
    $imagenes[] = [
        'nombre' => basename($ruta),
        'peso'   => filesize($ruta),
        'fecha'  => filemtime($ruta),
    ];
}
usort($imagenes, fn($a, $b) => $b['fecha'] <=> $a['fecha']);   // más nuevas primero

/* ---- en qué REPORTAJE se usa cada imagen (tabla reportajes_fotos) ---- */
$usoReportaje = [];   // nombre_archivo => [ 'Título del reportaje', ... ]
$q = db()->query('SELECT rf.url_foto, r.titulo
                  FROM reportajes_fotos rf JOIN reportajes r ON r.id = rf.reportaje_id
                  ORDER BY r.orden');
foreach ($q->fetchAll() as $row) {
    $usoReportaje[$row['url_foto']][$row['titulo']] = true;   // clave para no repetir
}

/* ---- uso en otras secciones (no son reportajes) ---- */
$usoOtro = [];   // nombre_archivo => 'Actualidad' | 'Boletín NTEP' | 'Plantilla del sitio'
foreach (db()->query('SELECT foto FROM noticias WHERE foto IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $v) { $usoOtro[$v] = 'Actualidad'; }
foreach (db()->query('SELECT foto_portada FROM boletines WHERE foto_portada IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN) as $v) { $usoOtro[$v] = 'Boletín NTEP'; }
/* imágenes de la plantilla (logo, iconos, banners): no están en la BD pero se usan */
$desdePlantilla = imagenes_en_plantillas() + array_fill_keys(IMG_PROTEGIDAS, 1);
foreach ($desdePlantilla as $v => $_) { if (!isset($usoOtro[$v])) { $usoOtro[$v] = 'Plantilla del sitio'; } }

$totalPeso = array_sum(array_column($imagenes, 'peso'));
function humano($b) { return $b > 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024) . ' KB'; }

// cuántas están sin usar en ninguna parte
$sinUsar = 0;
$pesadas = 0;
foreach ($imagenes as $im) {
    if (!isset($usoReportaje[$im['nombre']]) && !isset($usoOtro[$im['nombre']])) { $sinUsar++; }
    if ($im['peso'] > IMG_PESO_PESADA) { $pesadas++; }
}

/* ---- PDFs de boletines ---- */
$pdfs = [];
foreach (glob(DIR_PDF . '/*.pdf') ?: [] as $ruta) {
    $pdfs[] = ['nombre' => basename($ruta), 'peso' => filesize($ruta), 'fecha' => filemtime($ruta)];
}
usort($pdfs, fn($a, $b) => $b['fecha'] <=> $a['fecha']);

[$flash, $flashTipo] = flash_get();
$titulo = 'Imágenes';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid">
        <h1 class="m-0">Imágenes y archivos</h1>
        <p class="text-muted mb-0"><?= count($imagenes) ?> imágenes en <code>assets/images/</code> · <?= humano($totalPeso) ?> · <?= count($pdfs) ?> PDF en <code>boletines/</code>
            <?php if ($pesadas > 0): ?> · <span class="text-danger"><?= $pesadas ?> pesada<?= $pesadas === 1 ? '' : 's' ?> (&gt;<?= humano(IMG_PESO_PESADA) ?>)</span><?php endif; ?>
        </p>
        <p class="small mb-0 mt-1">
            <span class="text-success">●</span> ideal (≤<?= humano(IMG_PESO_IDEAL) ?>) &nbsp;
            <span class="text-warning">●</span> aceptable &nbsp;
            <span class="text-danger">●</span> pesada, conviene comprimir (&gt;<?= humano(IMG_PESO_PESADA) ?>)
        </p>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center" style="gap:.75rem">
                <form method="post" enctype="multipart/form-data" class="form-inline" style="gap:.5rem">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="subir">
                    <div class="custom-file" style="width:260px">
                        <input type="file" class="custom-file-input" name="imgs[]" id="imgs" accept="image/*" multiple required>
                        <label class="custom-file-label" for="imgs">Elegir imágenes…</label>
                    </div>
                    <button class="btn btn-primary"><i class="fas fa-upload mr-1"></i>Subir</button>
                </form>
                <?php if ($sinUsar > 0): ?>
                <form method="post" class="ml-auto" onsubmit="return confirm('Se borrarán <?= $sinUsar ?> imágenes que no usa ningún reportaje, noticia ni boletín. ¿Continuar?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="borrar_sin_usar">
                    <button class="btn btn-outline-danger"><i class="fas fa-broom mr-1"></i>Borrar las <?= $sinUsar ?> sin usar</button>
                </form>
                <?php endif; ?>
                <input type="search" id="buscar" class="form-control <?= $sinUsar > 0 ? '' : 'ml-auto' ?>" style="max-width:280px" placeholder="Buscar por nombre de imagen o reportaje…">
            </div>
            <div class="card-body">
                <div class="row" id="grid">
                    <?php foreach ($imagenes as $im):
                        $reps = isset($usoReportaje[$im['nombre']]) ? array_keys($usoReportaje[$im['nombre']]) : [];
                        $otro = $usoOtro[$im['nombre']] ?? null;
                        $uso  = $reps || $otro;
                        $filtro = strtolower($im['nombre'] . ' ' . implode(' ', $reps) . ' ' . ($otro ?? ''));
                    ?>
                    <div class="col-6 col-md-3 col-xl-2 mb-4 media-item" data-filtro="<?= e($filtro) ?>">
                        <div class="border rounded p-1 h-100 d-flex flex-column">
                            <a href="<?= e(base_url('../assets/images/' . $im['nombre'])) ?>" target="_blank">
                                <img src="<?= e(base_url('../assets/images/' . $im['nombre'])) ?>" alt=""
                                     style="width:100%;height:110px;object-fit:cover;border-radius:3px;background:#f4f4f4;">
                            </a>
                            <div class="small text-truncate mt-1" title="<?= e($im['nombre']) ?>"><?= e($im['nombre']) ?></div>
                            <?php $claseP = $im['peso'] > IMG_PESO_PESADA ? 'text-danger' : ($im['peso'] > IMG_PESO_IDEAL ? 'text-warning' : 'text-success'); ?>
                            <div class="small"><span class="<?= $claseP ?>" title="Rendimiento: ideal ≤<?= humano(IMG_PESO_IDEAL) ?>"><?= humano($im['peso']) ?></span> <span class="text-muted">· <?= date('d/m/y', $im['fecha']) ?></span></div>
                            <div class="mt-1 flex-grow-1">
                                <?php if ($reps): ?>
                                    <?php foreach ($reps as $t): ?>
                                        <div class="small"><span class="badge badge-info">Reportaje</span>
                                            <span class="text-truncate d-inline-block align-bottom" style="max-width:100%" title="<?= e($t) ?>"><?= e($t) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php elseif ($otro): ?>
                                    <span class="badge badge-secondary"><?= e($otro) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-light border">sin usar</span>
                                <?php endif; ?>
                            </div>
                            <div class="btn-group btn-group-sm mt-1">
                                <button type="button" class="btn btn-outline-secondary copiar" data-n="<?= e($im['nombre']) ?>" title="Copiar nombre"><i class="fas fa-copy"></i></button>
                                <?php if (!$uso): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('¿Borrar <?= e($im['nombre']) ?>? No se puede deshacer.');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="borrar">
                                    <input type="hidden" name="nombre" value="<?= e($im['nombre']) ?>">
                                    <button class="btn btn-outline-danger" title="Borrar"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <p id="sinResultados" class="text-muted p-3" hidden>Ninguna imagen coincide con la búsqueda.</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">PDF de boletines (<code>boletines/</code>)</h3></div>
            <div class="card-body table-responsive p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($pdfs as $p): ?>
                        <tr>
                            <td><i class="far fa-file-pdf text-danger mr-1"></i><?= e($p['nombre']) ?></td>
                            <td><?= humano($p['peso']) ?></td>
                            <td><?= date('d/m/y', $p['fecha']) ?></td>
                            <td class="text-right"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= e(base_url('../boletines/' . rawurlencode($p['nombre']))) ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php
$foot_scripts = '<script>
$(function () {
    $(document).on("change", ".custom-file-input", function () {
        var n = this.files.length === 1 ? this.files[0].name : (this.files.length > 1 ? this.files.length + " archivos" : "Elegir imágenes…");
        $(this).next(".custom-file-label").text(n);
    });
    $("#buscar").on("input", function () {
        var q = this.value.toLowerCase().trim(), vis = 0;
        $("#grid .media-item").each(function () {
            var m = String($(this).data("filtro")).indexOf(q) !== -1;
            $(this).toggle(m); if (m) vis++;
        });
        $("#sinResultados").prop("hidden", vis > 0);
    });
    $(document).on("click", ".copiar", function () {
        var n = $(this).data("n");
        navigator.clipboard.writeText(n).then(function () {
            var b = $(event.target).closest(".copiar");
            b.html("<i class=\'fas fa-check\'></i>");
            setTimeout(function(){ b.html("<i class=\'fas fa-copy\'></i>"); }, 1200);
        });
    });
});
</script>';
require __DIR__ . '/partials/footer.php';
?>
