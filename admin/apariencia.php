<?php
require_once __DIR__ . '/config/init.php';
requiere_login();
requiere_admin();   // 'autor' no entra aquí

const RUTA_AJUSTES = __DIR__ . '/config/ajustes.json';

function leer_ajustes(): array
{
    $x = is_file(RUTA_AJUSTES) ? json_decode((string) file_get_contents(RUTA_AJUSTES), true) : [];
    return is_array($x) ? $x : [];
}

$aj = leer_ajustes() + ['marco_tarjetas' => true, 'marco_color' => '#e0020d'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) { flash_set('Sesión expirada, reintenta.', 'warning'); redir('apariencia.php'); }

    $color = trim($_POST['marco_color'] ?? '#e0020d');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) { $color = '#e0020d'; }

    $nuevo = [
        'marco_tarjetas' => isset($_POST['marco_tarjetas']),
        'marco_color'    => $color,
    ];
    if (file_put_contents(RUTA_AJUSTES, json_encode($nuevo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
        flash_set('No se pudo guardar el archivo de ajustes (revisa permisos de admin/config/).', 'warning');
    } else {
        flash_set('Apariencia guardada.');
    }
    redir('apariencia.php');
}

[$flash, $flashTipo] = flash_get();
$titulo = 'Apariencia';
$head_extra = '<style>
.demo-card{max-width:260px}
.demo-card .thumb{position:relative;overflow:visible;border-radius:14px;line-height:0}
.demo-card .thumb img{width:100%;height:150px;object-fit:cover;display:block;position:relative;z-index:1;border-radius:14px}
.demo-card.on .thumb::before{content:"";position:absolute;inset:0;z-index:0;background:var(--c,#e0020d);border-radius:20px 16px 48% 20px / 20px 16px 60% 20px}
.demo-card.on .thumb img{border-radius:16px 44% 44% 16px / 16px 54% 54% 16px}
</style>';
require __DIR__ . '/partials/header.php';
?>
<section class="content-header">
    <div class="container-fluid"><h1 class="m-0">Apariencia del sitio</h1></div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if ($flash): ?><div class="alert alert-<?= e($flashTipo) ?>"><?= e($flash) ?></div><?php endif; ?>

        <div class="row">
            <div class="col-lg-7">
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Color de marca y marco decorativo</h3></div>
                        <div class="card-body">
                            <div class="form-group" style="max-width:220px">
                                <label>Color principal de la marca</label>
                                <input type="color" class="form-control form-control-color" name="marco_color" id="marco_color"
                                       value="<?= e($aj['marco_color']) ?>" style="height:42px">
                                <small class="text-muted d-block mt-1">
                                    Se aplica a <b>todo el sitio</b>: botones, enlaces, "Leer más", el menú activo, etc.
                                </small>
                            </div>
                            <div class="custom-control custom-switch custom-switch-lg mb-3">
                                <input type="checkbox" class="custom-control-input" name="marco_tarjetas" id="marco_tarjetas" value="1"
                                       <?= $aj['marco_tarjetas'] ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="marco_tarjetas">
                                    Además, mostrar la <b>esquina roja</b> y la <b>esquina blanca</b> en las tarjetas de Reportajes
                                </label>
                            </div>
                            <p class="text-muted small mb-0">El marco (esquinas) es solo para las tarjetas de <b>Reportajes</b>. El color de marca sí se ve en todo: portada, Actualidad, Boletines, Podcast y el artículo.</p>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary">Guardar</button></div>
                    </div>
                </form>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Vista previa</h3></div>
                    <div class="card-body d-flex flex-column align-items-center">
                        <div class="demo-card <?= $aj['marco_tarjetas'] ? 'on' : '' ?>" id="demo" style="--c: <?= e($aj['marco_color']) ?>">
                            <div class="thumb"><img src="<?= e(base_url('../assets/images/reportaje-12-08-26.jpg')) ?>" alt=""></div>
                            <div class="p-3 bg-light">
                                <div class="text-muted small">12 de agosto de 2026</div>
                                <div class="font-weight-bold mt-1">Cómo evitar que el canon del boom minero…</div>
                            </div>
                        </div>
                        <div class="mt-3 text-center" style="max-width:260px">
                            <button id="demo-btn" class="btn text-white w-100 mb-2" style="background:<?= e($aj['marco_color']) ?>">Botón de ejemplo</button>
                            <a id="demo-link" href="#" onclick="return false" style="color:<?= e($aj['marco_color']) ?>">Enlace / "Leer más" de ejemplo</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
$foot_scripts = '<script>
$(function () {
    function pintar(){
        var on = $("#marco_tarjetas").is(":checked");
        var c  = $("#marco_color").val();
        $("#demo").toggleClass("on", on).css("--c", c);
        $("#demo-btn").css("background", c);
        $("#demo-link").css("color", c);
    }
    $("#marco_tarjetas, #marco_color").on("input change", pintar);
});
</script>';
require __DIR__ . '/partials/footer.php';
?>
