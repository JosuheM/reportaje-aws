<?php
/** Cabecera y pie compartidos de las páginas públicas generadas con PHP. */

// Evita que el navegador guarde en caché una versión vieja de la portada/listados.
if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
}

if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
}

/** Lee un ajuste de admin/config/ajustes.json. */
function ajuste(string $clave, $porDefecto = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $ruta = __DIR__ . '/../admin/config/ajustes.json';
        $cfg = is_file($ruta) ? (json_decode((string) file_get_contents($ruta), true) ?: []) : [];
    }
    return $cfg[$clave] ?? $porDefecto;
}

/** Clases para el <body> del sitio público según los ajustes. */
function body_clases_publico(): string
{
    $c = [];
    if (ajuste('marco_tarjetas', true)) { $c[] = 'con-marco-tarjetas'; }
    return implode(' ', $c);
}

/**
 * <style> con el color de marca de los ajustes del panel (Apariencia).
 * Se usa como --marco-rojo (esquina del marco decorativo) Y como --primary-color
 * y --primary (botones, enlaces, acentos de TODA la plantilla), así que cambiarlo
 * en el panel recolorea el sitio completo, no solo las tarjetas.
 */
function estilos_ajustes(): string
{
    $color = preg_replace('/[^#0-9a-fA-F]/', '', (string) ajuste('marco_color', '#e0020d')) ?: '#e0020d';
    return '<style>:root{--marco-rojo:' . $color . ';--primary-color:' . $color . ';--primary:' . $color . '}</style>';
}

/** meses cortos en español */
function mes_corto(int $m): string
{
    return ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Set','Oct','Nov','Dic'][$m] ?? '';
}
/** meses completos en español, para el bloque "Archivos" */
function mes_largo(int $m): string
{
    return ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio',
            'Agosto','Septiembre','Octubre','Noviembre','Diciembre'][$m] ?? '';
}
function fecha_larga(?string $sql): string
{
    if (!$sql) return '';
    $ts = strtotime($sql);
    return mes_corto((int) date('n', $ts)) . ' ' . date('d', $ts) . ', ' . date('Y', $ts);
}

/**
 * Meses/años con reportajes publicados, para el bloque "Archivos" del sitio
 * (más reciente primero). Cada fila: ['ym'=>'2026-08', 'etiqueta'=>'Agosto 2026', 'n'=>3].
 */
function archivos_reportajes(int $limite = 12): array
{
    $filas = db()->query(
        "SELECT DATE_FORMAT(fecha_publicacion, '%Y-%m') AS ym, COUNT(*) AS n
         FROM reportajes
         WHERE estado = 'publicado' AND fecha_publicacion IS NOT NULL
         GROUP BY ym ORDER BY ym DESC LIMIT " . (int) $limite
    )->fetchAll();
    foreach ($filas as &$f) {
        [$y, $m] = explode('-', $f['ym']);
        $f['etiqueta'] = mes_largo((int) $m) . ' ' . $y;
    }
    return $filas;
}

/**
 * Añade loading="lazy" decoding="async" a los <img> de un HTML (cuerpo de un
 * reportaje) que todavía no lo tengan, para no cargar de golpe todas las
 * imágenes de un artículo largo. Contenido de confianza (viene del editor).
 */
function agregar_carga_diferida(string $html): string
{
    return preg_replace_callback('/<img\b([^>]*)>/i', function ($m) {
        $attrs = $m[1];
        if (stripos($attrs, 'loading=') === false) { $attrs .= ' loading="lazy"'; }
        if (stripos($attrs, 'decoding=') === false) { $attrs .= ' decoding="async"'; }
        return '<img' . $attrs . '>';
    }, $html);
}

/** URL pública de un reportaje (siempre por id para reflejar ediciones del panel). */
function url_reportaje($r): string
{
    $id = is_array($r) ? ($r['id'] ?? $r['reportaje_id'] ?? 0) : $r;
    return 'articulo.php?id=' . (int) $id;
}

/**
 * Datos de paginación para un listado público.
 * @return array{pagina:int, total_paginas:int, offset:int, por_pagina:int}
 */
function paginacion(int $totalFilas, int $porPagina = 9): array
{
    $totalPaginas = max(1, (int) ceil($totalFilas / $porPagina));
    $pagina = (int) ($_GET['p'] ?? 1);
    if ($pagina < 1) { $pagina = 1; }
    if ($pagina > $totalPaginas) { $pagina = $totalPaginas; }
    return [
        'pagina'        => $pagina,
        'total_paginas' => $totalPaginas,
        'offset'        => ($pagina - 1) * $porPagina,
        'por_pagina'    => $porPagina,
    ];
}

/**
 * Pinta la paginación con el estilo de la plantilla (.grids-block-5 .pagination).
 * $extra: otros parámetros de la URL a conservar entre páginas (p. ej. ['mes' => '2026-08']).
 */
function paginador(int $pagina, int $totalPaginas, string $base, array $extra = []): void
{
    if ($totalPaginas <= 1) { return; }
    $url = function (int $n) use ($base, $extra) {
        $qs = $extra;
        if ($n > 1) { $qs['p'] = $n; }
        return h($qs ? $base . '?' . http_build_query($qs) : $base);
    };
    ?>
    <div class="pagination">
        <ul>
            <li class="prev">
                <?php if ($pagina > 1): ?><a href="<?= $url($pagina - 1) ?>">&laquo; Ant</a>
                <?php else: ?><span class="text-muted">&laquo; Ant</span><?php endif; ?>
            </li>
            <?php for ($n = 1; $n <= $totalPaginas; $n++): ?>
                <li><a href="<?= $url($n) ?>" class="<?= $n === $pagina ? 'active' : '' ?>"><?= $n ?></a></li>
            <?php endfor; ?>
            <li class="next">
                <?php if ($pagina < $totalPaginas): ?><a href="<?= $url($pagina + 1) ?>">Sig &raquo;</a>
                <?php else: ?><span class="text-muted">Sig &raquo;</span><?php endif; ?>
            </li>
        </ul>
    </div>
    <?php
}

/**
 * Convierte lo que pegó el editor (un <iframe> completo, o una URL de
 * YouTube / Spotify / Vimeo) en un reproductor embebido.
 */
function embed_media(?string $v): string
{
    $v = trim((string) $v);
    if ($v === '') {
        return '<div class="text-muted small p-3">Sin contenido</div>';
    }
    // Ya viene un iframe/embed: se confía en el contenido del panel.
    if (stripos($v, '<iframe') !== false || stripos($v, '<blockquote') !== false) {
        return $v;
    }
    // URL suelta -> armar el iframe según la plataforma
    $src = $v;
    if (preg_match('~youtu\.be/([\w-]+)~', $v, $m) || preg_match('~youtube\.com/watch\?v=([\w-]+)~', $v, $m)) {
        $src = 'https://www.youtube.com/embed/' . $m[1];
    } elseif (preg_match('~youtube\.com/embed/[\w-]+~', $v)) {
        $src = $v;
    } elseif (preg_match('~open\.spotify\.com/(episode|show|track|playlist)/([\w]+)~', $v, $m)) {
        $src = 'https://open.spotify.com/embed/' . $m[1] . '/' . $m[2];
    } elseif (preg_match('~vimeo\.com/(\d+)~', $v, $m)) {
        $src = 'https://player.vimeo.com/video/' . $m[1];
    }
    return '<iframe src="' . h($src) . '" loading="lazy" allowfullscreen
                    allow="autoplay; clipboard-write; encrypted-media; picture-in-picture"
                    style="width:100%;height:100%;border:0;border-radius:10px;"></iframe>';
}

/** ¿Qué plataforma es? (para elegir el tamaño correcto del reproductor: video 16:9 vs. audio). */
function tipo_embed(?string $v): string
{
    $v = trim((string) $v);
    if ($v === '') { return 'vacio'; }
    if (stripos($v, 'spotify.com') !== false) { return 'audio'; }   // Spotify: tarjeta tipo audio, no 16:9
    return 'video';   // YouTube, Vimeo, iframe suelto: se trata como video
}

/**
 * Tarjeta "portada + botón de reproducir" para un podcast/especial. El
 * reproductor NO se muestra directo en la tarjeta (ahí se ve recortado si
 * el recuadro es angosto, sobre todo con Spotify); se abre en una ventana
 * emergente (modal de Bootstrap) al hacer clic.
 */
function tarjeta_podcast(string $urlEmbed, string $titulo, string $idUnico): string
{
    if (trim($urlEmbed) === '') { return ''; }
    $tipo = tipo_embed($urlEmbed);
    $icono = stripos($urlEmbed, 'spotify.com') !== false ? 'fa-spotify'
           : (stripos($urlEmbed, 'youtu') !== false ? 'fa-youtube' : 'fa-play-circle');
    $modalId = 'pod-' . preg_replace('/[^A-Za-z0-9_-]/', '', $idUnico);
    ob_start();
    ?>
    <div class="podcast-card" data-toggle="modal" data-target="#<?= h($modalId) ?>">
        <div class="podcast-card-play"><i class="fa <?= $icono ?>" aria-hidden="true"></i></div>
        <span class="podcast-card-label">Escuchar / ver</span>
        <p class="podcast-card-title mb-0"><?= h($titulo) ?></p>
    </div>
    <div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><?= h($titulo) ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body p-0">
                    <div class="<?= $tipo === 'audio' ? 'modal-embed-audio' : 'ratio-16x9' ?>"><?= embed_media($urlEmbed) ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * CSS + JS de un visor de imagen a pantalla completa (sin librerías externas):
 * al hacer clic en cualquier <img> del artículo que no sea ya un enlace,
 * se abre una capa oscura con la imagen a tamaño completo (clic o Esc para cerrar).
 * Imita el "Clic en la imagen para ver la imagen completa" del sitio oficial.
 */
function imagen_ampliable_css_js(): string
{
    return <<<'HTML'
<style>
.single-post-image img, .single-post-content img { cursor: zoom-in; }
.single-post-content a img { cursor: pointer; } /* imagen ya enlazada: la deja como está */
#visor-imagen {
    display: none; position: fixed; inset: 0; z-index: 2000;
    background: rgba(10,10,10,.92); text-align: center; padding: 4vh 3vw;
    cursor: zoom-out;
}
#visor-imagen.abierto { display: flex; align-items: center; justify-content: center; }
#visor-imagen img { max-width: 100%; max-height: 92vh; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,.5); }
#visor-imagen .cerrar {
    position: absolute; top: 18px; right: 24px; color: #fff; font-size: 34px;
    line-height: 1; opacity: .85; background: none; border: 0;
}
</style>
<div id="visor-imagen"><button class="cerrar" aria-label="Cerrar" onclick="event.stopPropagation();cerrarVisorImagen();">&times;</button><img alt=""></div>
<script>
function cerrarVisorImagen(){ document.getElementById('visor-imagen').classList.remove('abierto'); }
document.addEventListener('DOMContentLoaded', function () {
    var visor = document.getElementById('visor-imagen');
    var img = visor.querySelector('img');
    document.querySelectorAll('.single-post-image img, .single-post-content img').forEach(function (el) {
        if (el.closest('a')) { return; } // ya tiene su propio enlace (p. ej. a un PDF): no se toca
        el.addEventListener('click', function () {
            img.src = el.getAttribute('src');
            visor.classList.add('abierto');
        });
    });
    visor.addEventListener('click', cerrarVisorImagen);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { cerrarVisorImagen(); } });
});
</script>
HTML;
}

function frente_header(string $titulo, string $activo = ''): void
{
    $act = fn($k) => $activo === $k ? ' active' : '';
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?= h($titulo) ?></title>
    <link href="https://fonts.googleapis.com/css?family=Cabin:400,500,600&amp;subset=latin-ext,vietnamese" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style-starter.css">
    <link rel="stylesheet" href="assets/css/ddp.css?v=<?php echo @filemtime(__DIR__ . '/../assets/css/ddp.css') ?: 1; ?>">
    <style>
        .single-post-content p { text-align: justify; margin-bottom: 1.5rem; }
        .single-post-content img { max-width: 100%; height: auto; border-radius: 12px; margin: .5rem 0; }
        .single-post-content h2,.single-post-content h3,.single-post-content h4 { margin: 1.5rem 0 .75rem; font-weight: 700; }
    </style>
    <?= estilos_ajustes() ?>
</head>
<body class="<?= body_clases_publico() ?>">
<header id="site-header" class="fixed-top">
  <div class="container">
      <nav class="navbar navbar-expand-lg stroke">
      <a class="navbar-brand" href="index.php">
          <img src="assets/images/logo.png" alt="DDP Noticias" title="DDP Noticias" style="height:75px;" />
      </a>
          <button class="navbar-toggler collapsed bg-gradient" type="button" data-toggle="collapse"
              data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon fa icon-expand fa-bars"></span>
              <span class="navbar-toggler-icon fa icon-close fa-times"></span>
          </button>
          <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
              <ul class="navbar-nav ml-auto">
                  <li class="nav-item<?= $act('inicio') ?>"><a class="nav-link" href="index.php">Inicio</a></li>
                  <li class="nav-item<?= $act('actualidad') ?>"><a class="nav-link" href="index.php#actualidad">Actualidad</a></li>
                  <li class="nav-item<?= $act('reportajes') ?>"><a class="nav-link" href="reportajes.php">Reportajes</a></li>
                  <li class="nav-item<?= $act('podcast') ?>"><a class="nav-link" href="podcast.php">Podcast</a></li>
                  <li class="nav-item<?= $act('especiales') ?>"><a class="nav-link" href="especiales.php">Especiales</a></li>
                  <li class="nav-item<?= $act('boletines') ?>"><a class="nav-link" href="boletines.php">Boletín NTEP</a></li>
                  <li class="nav-item"><a class="nav-link disabled" href="#" onclick="return false">Alianzas</a></li>
                  <li class="nav-item"><a class="nav-link disabled" href="#" onclick="return false">Sobre D&D</a></li>
                  <li class="ml-2"><a href="#btn" class="btn btn-style btn-outline-secondary">Contacto</a></li>
              </ul>
          </div>
      </nav>
  </div>
</header>
<?php
}

function frente_footer(): void
{
    ?>
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
    window.onscroll = function () { var b=document.getElementById('movetop'); b.style.display=(document.documentElement.scrollTop>20)?'block':'none'; };
    function topFunction(){ document.documentElement.scrollTop=0; document.body.scrollTop=0; }
  </script>
</section>
<script src="assets/js/jquery-3.3.1.min.js"></script>
<script src="assets/js/theme-change.js"></script>
<script>
  $(window).on("scroll", function () {
    ($(window).scrollTop() >= 80) ? $("#site-header").addClass("nav-fixed") : $("#site-header").removeClass("nav-fixed");
  });
  $(".navbar-toggler").on("click", function () { $("header").toggleClass("active"); $('body').toggleClass('noscroll'); });
</script>
<script src="assets/js/bootstrap.min.js"></script>
</body>
</html>
<?php
}
