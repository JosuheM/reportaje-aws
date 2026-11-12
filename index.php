<?php
require_once __DIR__ . '/admin/config/conexion.php';
require_once __DIR__ . '/inc/publico.php';

// Portada: reportajes con "Mostrar en portada" activado, en el orden definido.
$sel = "SELECT id, titulo, resumen_corto, fecha_publicacion, foto_principal FROM reportajes WHERE estado = 'publicado' ";
$rs = db()->query($sel . 'AND es_destacado = 1 ORDER BY orden ASC, id ASC LIMIT 4')->fetchAll();
if (!$rs) {   // si no hay ninguno marcado, mostramos los primeros para no dejar la portada vacía
    $rs = db()->query($sel . 'ORDER BY orden ASC, id ASC LIMIT 4')->fetchAll();
}
$destacado = $rs[0] ?? null;
$grid = array_slice($rs, 1, 3);

$notas = db()->query("SELECT titulo, link_externo, fecha_publicacion, foto
                      FROM noticias WHERE estado = 'publicado' ORDER BY orden ASC, id ASC LIMIT 3")->fetchAll();

$bol = db()->query('SELECT numero_boletin, resumen, fecha_publicacion, foto_portada, archivo_pdf
                    FROM boletines ORDER BY orden ASC, id ASC LIMIT 1')->fetch();

$podcasts = db()->query('SELECT id, titulo, url_embed, fecha_publicacion
                         FROM podcasts ORDER BY orden ASC, id ASC LIMIT 4')->fetchAll();

$especiales = db()->query('SELECT id, titulo, resumen, foto_portada, url_embed
                           FROM especiales ORDER BY orden ASC, id ASC LIMIT 3')->fetchAll();
?>
<!--
Author: W3layouts
Author URL: http://w3layouts.com
-->
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <title>DDP Noticias - Diálogo y Desarrollo Perú</title>

    <!-- Google fonts -->
    
	<link href="https://fonts.googleapis.com/css?family=Cabin:400,500,600&amp;subset=latin-ext,vietnamese" rel="stylesheet">
    
    <!-- Template CSS -->
    <link rel="stylesheet" href="assets/css/style-starter.css">
    <link rel="stylesheet" href="assets/css/ddp.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/ddp.css') ?: 1; ?>">
    <?= estilos_ajustes() ?>
  </head>
  <body class="<?= body_clases_publico() ?>">
<!-- header -->
<header id="site-header" class="fixed-top">
  <div class="container">
      <nav class="navbar navbar-expand-lg stroke">
          <!--<a class="navbar-brand" href="index.php">
              <span class="fa fa-video-camera"></span> V-Conference
          </a>
           if logo is image enable this   -->
      <a class="navbar-brand" href="#index.html">
          <img src="assets/images/logo.png" alt="Your logo" title="Your logo" style="height:75px;" />
      </a> 
          <button class="navbar-toggler  collapsed bg-gradient" type="button" data-toggle="collapse"
              data-target="#navbarTogglerDemo02" aria-controls="navbarTogglerDemo02" aria-expanded="false"
              aria-label="Toggle navigation">
              <span class="navbar-toggler-icon fa icon-expand fa-bars"></span>
              <span class="navbar-toggler-icon fa icon-close fa-times"></span>
              </span>
          </button>

          <div class="collapse navbar-collapse" id="navbarTogglerDemo02">
              <ul class="navbar-nav ml-auto">
                  <li class="nav-item active">
                      <a class="nav-link" href="index.php">Inicio <span class="sr-only">(current)</span></a>
                  </li>
                  <li class="nav-item @@about__active">
                      <a class="nav-link" href="#actualidad">Actualidad</a>
                  </li>
				  <li class="nav-item @@about__active">
                      <a class="nav-link" href="reportajes.php">Reportajes</a>
                  </li>
				  <li class="nav-item @@about__active">
                      <a class="nav-link" href="podcast.php">Podcast</a>
                  </li>
				  <li class="nav-item @@about__active">
                      <a class="nav-link" href="boletines.php">Boletín NTEP</a>
                  </li>
				  <li class="nav-item @@about__active">
                      <a class="nav-link disabled" href="#" onclick="return false">Alianzas</a>
                  </li>
                  <li class="nav-item @@contact__active">
                      <a class="nav-link disabled" href="#" onclick="return false">Sobre D&D</a>
                  </li>				  
                  <li class="ml-2">
                      <a href="#btn" class="btn btn-style btn-outline-secondary">Contacto</a>
                  </li>
              </ul>
          </div>
          <!-- toggle switch for light and dark theme --
          <div class="mobile-position">
              <nav class="navigation">
                  <div class="theme-switch-wrapper">
                      <label class="theme-switch" for="checkbox">
                          <input type="checkbox" id="checkbox">
                          <div class="mode-container">
                              <i class="gg-sun"></i>
                              <i class="gg-moon"></i>
                          </div>
                      </label>
                  </div>
              </nav>
          </div>
          <!-- //toggle switch for light and dark theme -->
      </nav>
  </div>
</header>
<!-- //header -->
<section class="breadcrumb-area py-sm-5 py-4">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="breadcrumb-contents">
                    <h2 class="title-big">Reportajes</h2>
                    <!--<div class="breadcrumb">
                        <ul>
                            <li>
                                <a href="index.php">Home</a>
                            </li>
                            <li class="active">
                                 Blog posts
                            </li>
                        </ul>
                    </div>-->
                </div>
            </div><!-- end .col-md-12 -->
        </div><!-- end .row -->
    </div><!-- end .container -->
</section>
<section class="w3l-video w3l-homeblock3 " id="video">
    <!-- /video-6-->
    <div class="container-fluid">
        <div class="video-grids-info row">
<?php if ($destacado): $dU = url_reportaje($destacado);
      $dImg = $destacado["foto_principal"] ? 'assets/images/' . $destacado["foto_principal"] : 'assets/images/video.jpg'; ?>
            <div class="video-gd-right col-lg-6 p-0">
                <div class="position-relative">
                    <a href="<?= h($dU) ?>"><img src="<?= h($dImg) ?>" alt="" class="img-fluid"></a>
                </div>
            </div>
            <div class="video-gd-left col-lg-6 p-lg-5 p-4 align-self">
                <div class="p-xl-4 p-0 video-wrap">
                    <h5><?= h(fecha_larga($destacado["fecha_publicacion"])) ?></h5>
					<h3 class="title-big text-left mb-4"><a href="<?= h($dU) ?>"><?= h($destacado["titulo"]) ?></a></h3>
                    <p><?= h(mb_strimwidth((string) $destacado['resumen_corto'], 0, 240, '...')) ?></p>
					<a href="<?= h($dU) ?>" class="btn mt-4 p-0">Leer <span class="fa fa-arrow-right"></span> </a>
                </div>
            </div>
<?php endif; ?>
        </div>
    </div>
</section>

<div class="grids-block-5 py-1">
    <!-- grids block 5 -->
    <section class="py-lg-4 py-md-3">
        <div class="container">
            <div class="row">
                <?php foreach ($grid as $g): $gU = url_reportaje($g);
                      $gImg = $g["foto_principal"] ? 'assets/images/' . $g["foto_principal"] : 'assets/images/logo.png'; ?>
                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="<?= h($gU) ?>" class="d-block"><img src="<?= h($gImg) ?>" alt="" class="img-fluid" loading="lazy" decoding="async" /></a>
                    <div class="blog-info">
                        <h5><?= h(fecha_larga($g["fecha_publicacion"])) ?></h5>
                        <h4><a href="<?= h($gU) ?>" class="d-block"><?= h($g["titulo"]) ?></a></h4>
                        <a href="<?= h($gU) ?>" class="btn mt-4 p-0">Leer <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="pagination">
                <ul>
                    <li><a href="reportajes.php">Ver todos</a></li>
                </ul>
            </div>
        </div>
</div>
<!--<section class="w3l-homeblock5 py-0">
    <div class="container py-lg-5 py-4">
        <div class="row">
            <div class="col-lg-8 align-self">
                <h5 class="title-small mb-2">DDP Noticias</h5>
                    <h3 class="title-banner">La minería ilegal no genera desarrollo para los territorios donde opera</h3>
                    <p class="mt-4">Informe del instituto VIDENZA analiza y compara el Índice de Desarrollo Humano en distritos del país, con presencia de minería informal e ilegal y las localidades sin presencia de actividad minera y los que tienen presencia de minería formal...</p>
                        <a href="mapa.html" class="btn btn-style btn-primary mt-md-5 mt-4">Ver Mapa</a>
            </div>
            <div class="col-lg-4 mt-lg-0 mt-4">
                <img src="assets/images/mapa-interactivo.png" class="img-fluid radius-image" alt="">
            </div>
        </div>
    </div>
</section>-->
<section class="breadcrumb-area py-sm-5 py-1">
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="breadcrumb-contents">
                    <h2 class="title-big">Noticias Recientes</h2><a class="anchor" id="actualidad"></a>
                    <!--<div class="breadcrumb">
                        <ul>
                            <li>
                                <a href="index.php">Home</a>
                            </li>
                            <li class="active">
                                 Blog posts
                            </li>
                        </ul>
                    </div>-->
                </div>
            </div><!-- end .col-md-12 -->
        </div><!-- end .row -->
    </div><!-- end .container -->
</section>
<div class="grids-block-5 noticias-grid py-5">
    <!-- grids block 5 -->
    <section class="py-lg-4 py-md-3">
        <div class="container">
            <div class="row">
                <?php foreach ($notas as $i => $n):
                      $nImg = $n["foto"] ? 'assets/images/' . $n["foto"] : 'assets/images/logo.png';
                      $nUrl = $n["link_externo"] ?: '#';
                      $mt = $i === 0 ? '' : 'mt-lg-0 mt-5'; ?>
                <div class="col-lg-4 col-md-6 grids5-info <?= $mt ?>">
                    <a target="_blank" href="<?= h($nUrl) ?>" class="d-block"><img src="<?= h($nImg) ?>" alt="" class="img-fluid" loading="lazy" decoding="async" /></a>
                    <div class="blog-info">
                        <h5><?= h(fecha_larga($n["fecha_publicacion"])) ?></h5>
                        <h4><a target="_blank" href="<?= h($nUrl) ?>" class="d-block"><?= h($n["titulo"]) ?></a></h4>
                        <a target="_blank" href="<?= h($nUrl) ?>" class="btn mt-4 p-0">Leer <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$notas): ?>
                <div class="col-12 text-center text-muted py-4">Aún no hay noticias recientes cargadas.</div>
                <?php endif; ?>
                <!--<div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog8.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">Experience the breathtaking views and perspectives</a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog9.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">The absolute best foods for getting that youthful glow</a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">Our quiet not heart along scale sense timed practice</a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog1.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">Increasing your advantage by aligning strategy.</a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog2.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">Business performance, Design incubator </a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 grids5-info mt-5">
                    <a href="blog-single.html" class="d-block"><img src="assets/images/blog4.jpg" alt=""
                            class="img-fluid" /></a>
                    <div class="blog-info">
                        <h5>Posted on May 20, 2022</h5>
                        <ul class="blog-info">
                            <li><a href="#admin"><span class="fa fa-user"></span> admin</a></li>
                            <li><a href="#comments"><span class="fa fa-comments"></span>3 comments</a></li>
                            <li><a href="#shares"><span class="fa fa-share"></span>3 shares</a></li>
                        </ul>
                        <h4><a href="blog-single.html" class="d-block">Preparing for a new global economy</a>
                        </h4>
                        <a href="blog-single.html" class="btn mt-4 p-0">Read More <span class="fa fa-arrow-right"></span> </a>
                    </div>
                </div>-->
            </div>
            <div class="pagination">
                <ul>
                    <li><a target="_blank" href="https://www.facebook.com/DialogoyDesarrolloPeru">Ver todos</a></li>
                </ul>
            </div>
        </div>
</div>
<!-- // grids block 5 --

<!-- middle grid -->
<section class="w3l-homeblock5 py-0">
    <div class="container py-lg-5 py-4">
        <div class="row">
            <?php
            $bolPdf = $bol && $bol["archivo_pdf"] ? 'boletines/' . rawurlencode($bol["archivo_pdf"]) : 'boletines.php';
            $bolImg = $bol && $bol["foto_portada"] ? 'assets/images/' . $bol["foto_portada"] : 'assets/images/logo.png';
            $bolLineas = $bol && $bol["resumen"]
                ? array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $bol["resumen"])))
                : [];
            ?>
            <div class="col-lg-8 align-self">
                <h3 class="title-big mb-4"> Boletín NTEP </h3>
                <?php foreach (array_slice($bolLineas, 0, 4) as $ln): ?>
                <p class=""><?= h($ln) ?></p>
                <?php endforeach; ?>
                <div class="row mt-sm-4 mt-2 px-3">
                    <div class="col-6 p-0">
                        <span><?= h($bol["numero_boletin"] ?? '') ?></span>
                        <h4><?= h(fecha_larga($bol["fecha_publicacion"] ?? null)) ?></h4>
                    </div>
                    <div class="col-6 p-0">
                        <span><a target="_blank" href="<?= h($bolPdf) ?>" class="facebook"><span class="fa fa-download"></span></a></span>
                        <h4>Ver Boletín</h4>
                    </div>
					<center><a href="boletines.php" class="btn btn-style btn-primary mt-md-5 mt-4">Ver todos</a></center>
                </div>
            </div>
            <div class="col-lg-4 mt-lg-0 mt-4">
                <img src="<?= h($bolImg) ?>" class="img-fluid radius-image" alt="">
            </div>
        </div>
    </div>
</section>
<!-- //middle grid -->
<section class="w3l-homeblock3 py-5">
    <div class="container py-lg-5 py-md-4">
        <!--<h5 class="title-small mb-1 text-center">12 speakers and 20 fun events.</h5>-->
        <h3 class="title-big mb-5 text-center">Podcast</h3>
        <div class="row">
            <?php if (!$podcasts): ?>
                <div class="col-12 text-center text-muted">Aún no hay episodios de podcast.</div>
            <?php endif; ?>
            <?php foreach ($podcasts as $i => $pod): ?>
            <div class="col-lg-3 col-sm-6 <?= $i > 0 ? 'mt-sm-0 mt-5 mt-lg-0' : '' ?>">
                <?= tarjeta_podcast($pod['url_embed'], $pod['titulo'], 'home-pod-' . $pod['id']) ?>
            </div>
            <?php endforeach; ?>
        </div>
		<center><a href="podcast.php" class="btn btn-style btn-primary mt-md-5 mt-4">Ver todos</a></center>
    </div>
</section>

<?php if ($especiales): ?>
<section class="w3l-homeblock3 py-5" style="background:var(--bg-grey,#f7f9fc)">
    <div class="container py-lg-5 py-md-4">
        <h3 class="title-big mb-5 text-center">Especiales</h3>
        <div class="row">
            <?php foreach ($especiales as $i => $esp): ?>
            <div class="col-lg-4 <?= $i > 0 ? 'mt-lg-0 mt-5' : '' ?>">
                <?php if ($esp['url_embed']): ?>
                    <?= tarjeta_podcast($esp['url_embed'], $esp['titulo'], 'home-esp-' . $esp['id']) ?>
                <?php else: ?>
                    <div class="area-box">
                        <?php if ($esp['foto_portada']): ?>
                            <img src="assets/images/<?= h($esp['foto_portada']) ?>" alt="" class="img-fluid mb-2" style="border-radius:8px;aspect-ratio:16/9;object-fit:cover;width:100%" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <p class="mb-0"><a href="especiales.php#e<?= (int) $esp['id'] ?>" class="text-dark font-weight-bold"><?= h($esp['titulo']) ?></a></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <center><a href="especiales.php" class="btn btn-style btn-primary mt-md-5 mt-4">Ver todos</a></center>
    </div>
</section>
<?php endif; ?>

<!-- logos Section --
<section class="w3l-logos w3l-homeblock3 py-5">
    <div class="container py-lg-3">
        <h5 class="title-small mb-1 text-center">DyD Perú</h5>
        <h3 class="title-big mb-md-5 mb-4 text-center">Alianzas</h3>
        <div class="row">
            <div class="col-lg-12 mx-auto">
                <div class="owl-logos owl-carousel owl-theme logo-view">
                    <div class="item">
                        <img src="assets/images/logo1.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">
                    </div>
                    <div class="item">
                        <img src="assets/images/logo2.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">
                    </div>
                    <div class="item">
                        <img src="assets/images/logo3.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">
                    </div>
                    <div class="item">
                        <img src="assets/images/logo4.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">

                    </div>
                    <div class="item">
                        <img src="assets/images/logo5.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">

                    </div>
                    <div class="item">
                        <img src="assets/images/logo6.png" alt="company-logo radius-image" class="img-fluid" loading="lazy" decoding="async">

                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- //logos Section -->

<section class="w3l-banner py-0" id="work">
    <div class="midd-w3 py-lg-4 py-md-3">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 mt-lg-0 mt-lg-5 about-right-faq align-self">
                    <h5 class="title-small mb-2">DDP Noticias</h5>
                    <h3 class="title-banner">Diálogo y Desarrollo Perú</h3>
                    <p class="mt-4">Somos un espacio de periodismo independiente que busca visibilizar las acciones de diálogo en el país desde una mirada constructiva.</p>
                        <a href="#btn" class="btn btn-style btn-primary mt-md-5 mt-4">Nosotros</a>
                 </div>
                <div class="col-md-6 left-wthree-img mt-lg-0 mt-4">
                    <div class="position-relative">
                        <img src="assets/images/bannerimg.jpg" alt="" class="img-fluid" loading="lazy" decoding="async">
                        <!--<a href="#small-dialog" class="popup-with-zoom-anim play-view text-center position-absolute">
                            <span class="video-play-icon">
                                <span class="fa fa-play"></span>
                            </span>
                        </a>
                         dialog itself, mfp-hide class is required to make dialog hidden -->
                        <div id="small-dialog" class="zoom-anim-dialog mfp-hide">
                            <iframe src="https://www.youtube.com/embed/2jI6fHBtRJU" allow="autoplay; fullscreen" allowfullscreen=""></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- middle grid --
<section class="w3l-homeblock5 py-5">
    <div class="container py-lg-5 py-4">
        <div class="row">
            <div class="col-lg-6 align-self">
                <h3 class="title-big mb-4"> Don’t miss out on the fun and join the community! </h3>
                <p class="">Lorem ipsum dolor sit amet consectetur adipisicing elit. Voluptates maiores ipsum quos
                    voluptate, cumque perspiciatis dolorem tempora fugit facere ducimus?.</p>
                <div class="row mt-sm-4 mt-2 px-3">
                    <div class="col-6 p-0">
                        <span>80+</span>
                        <h4>Speakers</h4>
                    </div>
                    <div class="col-6 p-0">
                        <span>50+</span>
                        <h4>Workshops</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mt-lg-0 mt-4">
                <img src="assets/images/stats.jpg" class="img-fluid radius-image" alt="">
            </div>
        </div>
    </div>
</section>
<!-- //middle grid -->
<!-- middle -->
<div class="middle py-5">
    <div class="container py-xl-5 py-lg-3">
        <div class="welcome-left text-center py-md-5 py-3">
            <h3 class="title-big">Síguenos en nuestras Redes Sociales</h3>
            <div class="main-social-footer-29">
            <a target="_blank" href="https://www.facebook.com/DialogoyDesarrolloPeru" class="facebook"><span class="fa fa-facebook-square fa-2x"></span></a>
            <a target="_blank" href="https://www.tiktok.com/@dialogo.y.desarrollo" class="twitter"><img src="assets/images/tiktokg.png"></a>
            <a target="_blank" href="https://www.instagram.com/dialogo.y.desarrollo/" class="instagram"><span class="fa fa-instagram fa-2x"></span></a>
            <!--<a href="#youtube" class="youtube"><span class="fa fa-youtube fa-2x"></span></a>
            <a href="#linkedin" class="linkedin"><span class="fa fa-linkedin fa-2x"></span></a>-->
          </div>
        </div>
    </div>
</div>
<!-- //middle -->


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
            <!--<a href="#youtube" class="youtube"><span class="fa fa-youtube"></span></a>
            <a href="#linkedin" class="linkedin"><span class="fa fa-linkedin"></span></a>-->
          </div>
        </div>
        <div class="col-lg-3 col-md-6 footer-list-29 footer-2 mt-md-0 mt-5">
          <ul>
            <h6 class="footer-title-29">Contenido</h6>
            <li><a href="#url">Noticias</a></li>
            <li><a href="#url">Videos</a></li>
            <li><a href="#url">Posdcast.</a></li>
          </ul>
        </div>
        <div class="col-lg-3 col-md-6 mt-lg-0 mt-5 footer-list-29 footer-3">
          <div class="properties">
            <h6 class="footer-title-29">Contacto</h6>
            <ul>
            <!--<!--<li><a href="#url">Celulares</a></li>-->
            <!--<li><a href="#url">Celulares</a></li>-->
            <li><a href="#url">info@dialogoydesarrollo.com.pe</a></li>
          </ul>
          </div>
        </div>
      </div>
      <div class="bottom-copies text-center">
			<p class="copy-footer-29">© 2026 Diálogo y Desarrollo Perú. All rights reserved | Designed by <a target="_blank" href="https://www.wsperu.info">WebSolutions</a></p>
		</div>
    </div>
  </div>
  <!-- move top -->
  <button onclick="topFunction()" id="movetop" title="Go to top">
    <span class="fa fa-angle-up"></span>
  </button>
  <script>
    // When the user scrolls down 20px from the top of the document, show the button
    window.onscroll = function () {
      scrollFunction()
    };

    function scrollFunction() {
      if (document.body.scrollTop > 20 || document.documentElement.scrollTop > 20) {
        document.getElementById("movetop").style.display = "block";
      } else {
        document.getElementById("movetop").style.display = "none";
      }
    }

    // When the user clicks on the button, scroll to the top of the document
    function topFunction() {
      document.body.scrollTop = 0;
      document.documentElement.scrollTop = 0;
    }
  </script>
  <!-- /move top -->
</section>
<!-- //footer block -->

<!-- Template JavaScript -->
<script src="assets/js/jquery-3.3.1.min.js"></script>

<script src="assets/js/theme-change.js"></script><!-- theme switch js (light and dark)-->

<!-- responsive tabs -->
<script src="assets/js/easyResponsiveTabs.js"></script>
<!--Plug-in Initialisation-->
<script type="text/javascript">
  $(document).ready(function () {
    //Horizontal Tab
    $('#parentHorizontalTab').easyResponsiveTabs({
      type: 'default', //Types: default, vertical, accordion
      width: 'auto', //auto or any width like 600px
      fit: true, // 100% fit in a container
      tabidentify: 'hor_1', // The tab groups identifier
      activate: function (event) { // Callback function if tab is switched
        var $tab = $(this);
        var $info = $('#nested-tabInfo');
        var $name = $('span', $info);
        $name.text($tab.text());
        $info.show();
      }
    });
  });
</script>


<script src="assets/js/owl.carousel.js"></script>
<!-- logos for customers -->
<script>
  $(document).ready(function () {
    $('.owl-logos').owlCarousel({
      loop: true,
      margin: 0,
      nav: false,
      responsiveClass: true,
      autoplay: true,
      autoplayTimeout: 5000,
      autoplaySpeed: 1000,
      autoplayHoverPause: false,
      responsive: {
        0: {
          items: 2,
          nav: false
        },
        480: {
          items: 2,
          nav: false
        },
        568: {
          items: 3,
          nav: false
        },
        1000: {
          items: 5,
          nav: false
        }
      }
    })
  })
</script>
<!-- //logos owlcarousel -->

<!-- for tesimonials carousel slider -->
<script>
  $(document).ready(function () {
    $("#owl-demo1").owlCarousel({
      loop: true,
      margin: 20,
      responsiveClass: true,
      responsive: {
        0: {
          items: 1,
          nav: true
        },
        768: {
          items: 2,
          nav: false
        },
        1000: {
          items: 3,
          nav: true,
          loop: false
        }
      }
    })
  })
</script>
<!-- //script -->

<!-- script for teams -->
<script>
  $(document).ready(function () {
    $('.owl-carousel').owlCarousel({
      loop: true,
      margin: 0,
      responsiveClass: true,
      responsive: {
        0: {
          items: 1,
          nav: true
        },
        400: {
          items: 2,
          nav: true,
          margin: 20
        },
        768: {
          items: 3,
          nav: true,
          margin: 20
        },
        1000: {
          items: 4,
          nav: true,
          loop: true,
          margin: 25
        }
      }
    })
  })
</script>
<!-- //script for teams-->

<!-- Script for counter -->
<script>
  (() => {
    // Specify the deadline date
    const deadlineDate = new Date('January 27, 2025 23:59:59').getTime();

    // Cache all countdown boxes into consts
    const countdownDays = document.querySelector('.countdown__days .number');
    const countdownHours = document.querySelector('.countdown__hours .number');
    const countdownMinutes = document.querySelector('.countdown__minutes .number');
    const countdownSeconds = document.querySelector('.countdown__seconds .number');

    // Esta página no tiene contador; salir si no existen los elementos.
    if (!countdownDays || !countdownHours || !countdownMinutes || !countdownSeconds) {
      return;
    }

    // Update the count down every 1 second (1000 milliseconds)
    setInterval(() => {
      // Get current date and time
      const currentDate = new Date().getTime();

      // Calculate the distance between current date and time and the deadline date and time
      const distance = deadlineDate - currentDate;

      // Calculations the data for remaining days, hours, minutes and seconds
      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const seconds = Math.floor((distance % (1000 * 60)) / 1000);

      // Insert the result data into individual countdown boxes
      countdownDays.innerHTML = days;
      countdownHours.innerHTML = hours;
      countdownMinutes.innerHTML = minutes;
      countdownSeconds.innerHTML = seconds;
    }, 1000);
  })();
</script>
<!-- //Script for counter -->

<script src="assets/js/jquery.magnific-popup.min.js"></script>
<script>
  $(document).ready(function () {
    $('.popup-with-zoom-anim').magnificPopup({
      type: 'inline',

      fixedContentPos: false,
      fixedBgPos: true,

      overflowY: 'auto',

      closeBtnInside: true,
      preloader: false,

      midClick: true,
      removalDelay: 300,
      mainClass: 'my-mfp-zoom-in'
    });

    $('.popup-with-move-anim').magnificPopup({
      type: 'inline',

      fixedContentPos: false,
      fixedBgPos: true,

      overflowY: 'auto',

      closeBtnInside: true,
      preloader: false,

      midClick: true,
      removalDelay: 300,
      mainClass: 'my-mfp-slide-bottom'
    });
  });
</script>

<!-- disable body scroll which navbar is in active -->
<script>
  $(function () {
    $('.navbar-toggler').click(function () {
      $('body').toggleClass('noscroll');
    })
  });
</script>
<!-- disable body scroll which navbar is in active -->

<!--/MENU-JS-->
<script>
  $(window).on("scroll", function () {
    var scroll = $(window).scrollTop();

    if (scroll >= 80) {
      $("#site-header").addClass("nav-fixed");
    } else {
      $("#site-header").removeClass("nav-fixed");
    }
  });

  //Main navigation Active Class Add Remove
  $(".navbar-toggler").on("click", function () {
    $("header").toggleClass("active");
  });
  $(document).on("ready", function () {
    if ($(window).width() > 991) {
      $("header").removeClass("active");
    }
    $(window).on("resize", function () {
      if ($(window).width() > 991) {
        $("header").removeClass("active");
      }
    });
  });
</script>
<!--//MENU-JS-->

<script src="assets/js/bootstrap.min.js"></script>

</body>

</html>