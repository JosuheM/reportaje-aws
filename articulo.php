<?php
/**
 * Renderiza un reportaje guardado en la base, con la misma plantilla del sitio.
 * Uso:  articulo.php?id=3   (o ?slug=mi-reportaje.html)
 */
require_once __DIR__ . '/admin/config/init.php';   // sesión + conexión (para previsualizar borradores logueado)
require_once __DIR__ . '/inc/publico.php';         // estilos de marca, visor de imagen, archivos por mes
if (!headers_sent()) { header('Cache-Control: no-cache, no-store, must-revalidate'); header('Pragma: no-cache'); }

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

$id   = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$sql = 'SELECT r.*, a.nombres AS autor_nombre, a.ap_paterno AS autor_ap, a.es_nickname AS autor_nick
        FROM reportajes r LEFT JOIN autores a ON a.id = r.autor_id WHERE ';
if ($id > 0) {
    $st = db()->prepare($sql . 'r.id = ?');
    $st->execute([$id]);
} elseif ($slug !== '') {
    $st = db()->prepare($sql . 'r.slug = ?');
    $st->execute([$slug]);
} else {
    http_response_code(404);
    exit('Reportaje no encontrado.');
}
$r = $st->fetch();
if (!$r) {
    http_response_code(404);
    exit('Reportaje no encontrado.');
}
// Un borrador solo lo puede ver alguien con sesión en el panel.
if (($r['estado'] ?? 'publicado') !== 'publicado' && !esta_logueado()) {
    http_response_code(404);
    exit('Reportaje no encontrado.');
}
// nombre de autor para mostrar
$r['autor'] = trim(($r['autor_nombre'] ?? '') . ' ' . ($r['autor_ap'] ?? ''));
if (($r['autor_nombre'] ?? '') === 'Redacción') { $r['autor'] = 'Redacción'; }

// meses en español para la fecha "Ago 18, 2026"
$meses = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Oct',11=>'Nov',12=>'Dic'];
$fechaTxt = '';
if (!empty($r['fecha_publicacion'])) {
    $ts = strtotime($r['fecha_publicacion']);
    $fechaTxt = $meses[(int) date('n', $ts)] . ' ' . date('d', $ts) . ', ' . date('Y', $ts);
}

$ultimas = db()->query("SELECT id, titulo, slug, fecha_publicacion
                        FROM reportajes WHERE estado = 'publicado' ORDER BY orden ASC, id ASC LIMIT 3")->fetchAll();
$archivos = archivos_reportajes(12);
function enlace_reportaje(array $x): string {
    return 'articulo.php?id=' . (int) $x['id'];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>DyD Perú | <?= h($r['titulo']) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Cabin:400,500,600&amp;subset=latin-ext,vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style-starter.css">
    <link rel="stylesheet" href="assets/css/ddp.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/ddp.css') ?: 1; ?>">
    <style>
        .single-post-content p { text-align: justify; margin-bottom: 1.5rem; }
        .single-post-content img { max-width: 100%; height: auto; border-radius: 12px; margin: .5rem 0; }
        .single-post-content h2, .single-post-content h3, .single-post-content h4 { margin: 1.5rem 0 .75rem; font-weight: 700; }
    </style>
    <?= estilos_ajustes() ?>
</head>
<body>
<!-- header -->
<header id="site-header" class="fixed-top">
  <div class="container">
      <nav class="navbar navbar-expand-lg stroke">
      <a class="navbar-brand" href="index.php">
          <img src="assets/images/logo.png" alt="DDP Noticias" title="DDP Noticias" style="height:75px;" />
      </a>
          <button class="navbar-toggler collapsed bg-gradient" type="button" data-toggle="collapse"
              data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false"
              aria-label="Toggle navigation">
              <span class="navbar-toggler-icon fa icon-expand fa-bars"></span>
              <span class="navbar-toggler-icon fa icon-close fa-times"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
              <ul class="navbar-nav ml-auto">
                  <li class="nav-item"><a class="nav-link" href="index.php">Inicio</a></li>
                  <li class="nav-item"><a class="nav-link" href="index.php#actualidad">Actualidad</a></li>
                  <li class="nav-item active"><a class="nav-link" href="reportajes.php">Reportajes</a></li>
                  <li class="nav-item"><a class="nav-link" href="podcast.php">Podcast</a></li>
                  <li class="nav-item"><a class="nav-link" href="especiales.php">Especiales</a></li>
                  <li class="nav-item"><a class="nav-link" href="boletines.php">Boletín NTEP</a></li>
                  <li class="nav-item"><a class="nav-link disabled" href="#" onclick="return false">Alianzas</a></li>
                  <li class="nav-item"><a class="nav-link disabled" href="#" onclick="return false">Sobre D&D</a></li>
                  <li class="ml-2"><a href="#btn" class="btn btn-style btn-outline-secondary">Contacto</a></li>
              </ul>
          </div>
      </nav>
  </div>
</header>
<!-- //header -->

<section class="breadcrumb-area py-sm-5 py-4">
    <div class="container">
        <div class="row"><div class="col-md-12"><div class="breadcrumb-contents">
            <h2 class="title-big">Reportajes</h2>
            <div class="breadcrumb"><ul>
                <li><a href="index.php">Inicio</a></li>
                <li class="active"><a href="reportajes.php">Reportajes</a></li>
            </ul></div>
        </div></div></div>
    </div>
</section>

<section class="w3l-blog mt-lg-5">
    <div class="text-element-9 py-5 mt-lg-5">
        <div class="container py-lg-3">
            <div class="row grid-text-9">
                <div class="col-lg-8">
                    <div class="blog-single-post">
                        <div class="post-content">
                            <h2 class="title-single mb-3"><?= h($r['titulo']) ?></h2>
                            <?php if ($fechaTxt): ?><p class="text-muted"><?= h($fechaTxt) ?></p><?php endif; ?>
                        </div>

                        <?php if (!empty($r['foto_principal'])): ?>
                        <div class="single-post-image mb-2 text-center">
                            <img src="assets/images/<?= h($r['foto_principal']) ?>" class="img-fluid w-100 radius-image" alt="<?= h($r['titulo']) ?>" decoding="async" />
                        </div>
                        <p class="text-muted small text-center mb-4"><i class="fa fa-search-plus mr-1"></i>Clic en la imagen para verla completa</p>
                        <?php endif; ?>

                        <div class="single-post-content">
                            <?php if (!empty($r['resumen_corto'])): ?>
                            <blockquote class="blockquote my-5">
                                <q class="mb-3 d-block"><?= h($r['resumen_corto']) ?></q>
                            </blockquote>
                            <?php endif; ?>

                            <?php if (!empty($r['autor'])): ?>
                            <p class="mb-4"><strong><?= $r['autor'] === 'Redacción' ? 'Redacción' : 'Por ' . h($r['autor']) ?></strong></p>
                            <?php endif; ?>

                            <?= agregar_carga_diferida($r['desarrollo'] ?? '') /* HTML del editor / importado, contenido de confianza */ ?>
                        </div>

                        <nav class="post-navigation row mb-5 py-4">
                            <div class="post-prev col-md-6 pr-sm-5">
                                <span class="nav-title"><span class="fa fa-arrow-left mr-2"></span>
                                    <a href="reportajes.php">Reportajes</a></span>
                            </div>
                        </nav>
                    </div>
                </div>

                <div class="col-lg-4 left-text-9 mt-lg-0 mt-5 pl-lg-4">
                    <div class="left-top-9 mt-5 pt-sm-3">
                        <h6 class="heading-small-text-9 mb-3">Últimas noticias</h6>
                        <?php foreach ($ultimas as $x):
                            $ts = $x['fecha_publicacion'] ? strtotime($x['fecha_publicacion']) : null; ?>
                        <a href="<?= enlace_reportaje($x) ?>" class="p-post d-block py-2">
                            <h6 class="text-left-inner-9"><?= h($x['titulo']) ?></h6>
                            <?php if ($ts): ?><span class="sub-inner-text-9"><?= h($meses[(int) date('n', $ts)] . ' ' . date('d', $ts) . ', ' . date('Y', $ts)) ?></span><?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($archivos): ?>
                    <div class="left-top-9 mt-5 pt-sm-3">
                        <h6 class="heading-small-text-9 mb-3">Archivos</h6>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($archivos as $a): ?>
                            <li class="py-1">
                                <a href="<?= h('reportajes.php?mes=' . $a['ym']) ?>" class="text-left-inner-9">
                                    <?= h($a['etiqueta']) ?> <span class="text-muted small">(<?= (int) $a['n'] ?>)</span>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- footer block -->
<section class="w3l-footer-29-main py-5" id="footer">
  <div class="footer-29 py-md-3">
    <div class="container">
      <div class="row footer-top-29">
        <div class="col-lg-6 col-md-6 footer-list-29 footer-1">
          <h6 class="footer-title-29">Quiénes Somos</h6>
          <p>Somos un espacio de periodismo independiente que busca visibilizar las acciones de diálogo en el país desde una mirada constructiva.</p>
          <div class="main-social-footer-29">
            <a target="_blank" href="https://www.facebook.com/DialogoyDesarrolloPeru" class="facebook"><span class="fa fa-facebook-square"></span></a>
            <a target="_blank" href="https://www.tiktok.com/@dialogo.y.desarrollo" class="twitter"><img src="assets/images/tiktokp.png"></a>
            <a target="_blank" href="https://www.instagram.com/dialogo.y.desarrollo/" class="instagram"><span class="fa fa-instagram"></span></a>
          </div>
        </div>
        <div class="col-lg-3 col-md-6 footer-list-29 footer-2 mt-md-0 mt-5">
          <ul>
            <h6 class="footer-title-29">Contenido</h6>
            <li><a href="reportajes.php">Reportajes</a></li>
            <li><a href="boletines.php">Boletín NTEP</a></li>
          </ul>
        </div>
        <div class="col-lg-3 col-md-6 mt-lg-0 mt-5 footer-list-29 footer-3">
          <div class="properties">
            <h6 class="footer-title-29">Contacto</h6>
            <ul><li><a href="#url">info@dialogoydesarrollo.com.pe</a></li></ul>
          </div>
        </div>
      </div>
      <div class="bottom-copies text-center">
        <p class="copy-footer-29">© <?= date('Y') ?> Diálogo y Desarrollo Perú.</p>
      </div>
    </div>
  </div>
  <button onclick="topFunction()" id="movetop" title="Ir arriba"><span class="fa fa-angle-up"></span></button>
  <script>
    window.onscroll = function () { scrollFunction(); };
    function scrollFunction() {
      var b = document.getElementById("movetop");
      b.style.display = (document.documentElement.scrollTop > 20) ? "block" : "none";
    }
    function topFunction() { document.documentElement.scrollTop = 0; document.body.scrollTop = 0; }
  </script>
</section>
<!-- //footer block -->

<script src="assets/js/jquery-3.3.1.min.js"></script>
<script src="assets/js/theme-change.js"></script>
<script>
  $(window).on("scroll", function () {
    ($(window).scrollTop() >= 80) ? $("#site-header").addClass("nav-fixed") : $("#site-header").removeClass("nav-fixed");
  });
  $(".navbar-toggler").on("click", function () { $("header").toggleClass("active"); $('body').toggleClass('noscroll'); });
</script>
<script src="assets/js/bootstrap.min.js"></script>
<?= imagen_ampliable_css_js() ?>
</body>
</html>
