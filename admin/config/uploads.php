<?php
/**
 * Subida de archivos del panel.
 *  - Imágenes  -> ../assets/images/   (las usa el sitio y el renderer)
 *  - PDF       -> ../boletines/
 * Devuelve el nombre de archivo final (no la ruta completa).
 */

const RUTA_IMAGENES = __DIR__ . '/../../assets/images';
const RUTA_PDF      = __DIR__ . '/../../boletines';

const IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const IMG_MAX = 8 * 1024 * 1024;   // 8 MB: tope duro, rechaza el archivo
const IMG_MIN = 64;                // bytes: descarta archivos vacíos/rotos
const IMG_MAX_PIXELS = 40000000;   // 40 MP: freno a "bombas" de descompresión
const PDF_MAX = 25 * 1024 * 1024;  // 25 MB

/**
 * Estándares de RENDIMIENTO (no bloquean la subida, solo avisan):
 * una foto para web no necesita pesar varios MB ni medir miles de píxeles.
 */
const IMG_PESO_IDEAL       = 300 * 1024;   // ≤300 KB: bien
const IMG_PESO_PESADA      = 800 * 1024;   // >800 KB: pesada, conviene comprimir
const IMG_ANCHO_RECOMENDADO = 2000;        // más de esto no aporta nitidez en pantalla y solo pesa más

/** MIME (según getimagesize) -> extensión canónica que se guardará. */
const IMG_MIME_EXT = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

/** Texto corto "123 KB" / "1.2 MB". */
function peso_legible(int $bytes): string
{
    return $bytes > 1048576 ? round($bytes / 1048576, 1) . ' MB' : round($bytes / 1024) . ' KB';
}

/**
 * Aviso de rendimiento (o null si la imagen ya es liviana). No bloquea nada,
 * solo recomienda comprimirla o achicarla antes de subir la próxima vez.
 */
function evaluar_rendimiento(int $bytes, int $ancho): ?string
{
    $avisos = [];
    if ($bytes > IMG_PESO_PESADA) {
        $avisos[] = 'pesa ' . peso_legible($bytes) . ' (ideal: menos de ' . peso_legible(IMG_PESO_IDEAL) . ')';
    }
    if ($ancho > IMG_ANCHO_RECOMENDADO) {
        $avisos[] = 'mide ' . $ancho . 'px de ancho (con ' . IMG_ANCHO_RECOMENDADO . 'px se ve igual de nítida en pantalla y carga más rápido)';
    }
    return $avisos ? 'Imagen pesada para la web: ' . implode('; ', $avisos) . '. Comprímela con una app de fotos antes de subirla.' : null;
}

/** Convierte "Mi Título ñ" -> "mi-titulo-n". */
function slugify(string $texto): string
{
    $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
    $texto = strtolower($texto);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

/** Nombre único dentro de $dir a partir de un nombre base. */
function nombre_unico(string $dir, string $base, string $ext): string
{
    $base = slugify($base) ?: 'archivo';
    $nombre = "$base.$ext";
    $i = 2;
    while (file_exists("$dir/$nombre")) {
        $nombre = "$base-$i.$ext";
        $i++;
    }
    return $nombre;
}

/**
 * Procesa un $_FILES[...] de imagen.
 * @return array{ok:bool, nombre?:string, error?:string}
 */
function subir_imagen(array $file, string $nombreBase = ''): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'sin archivo'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'error de subida (' . $file['error'] . ')'];
    }
    // Debe venir de un POST real (no una ruta arbitraria del servidor).
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'origen de archivo no válido'];
    }
    $size = (int) @filesize($file['tmp_name']);
    if ($size < IMG_MIN) {
        return ['ok' => false, 'error' => 'el archivo está vacío o dañado'];
    }
    if ($size > IMG_MAX) {
        return ['ok' => false, 'error' => 'la imagen supera 8 MB'];
    }

    // 1) La extensión del nombre subido debe ser de imagen.
    $extNombre = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extNombre, IMG_EXT, true)) {
        return ['ok' => false, 'error' => 'formato no permitido (usa JPG, PNG, GIF o WEBP)'];
    }

    // 2) El contenido tiene que ser realmente una imagen de un tipo permitido.
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || empty($info['mime'])) {
        return ['ok' => false, 'error' => 'el archivo no es una imagen válida'];
    }
    $mime = strtolower($info['mime']);
    if (!isset(IMG_MIME_EXT[$mime])) {
        return ['ok' => false, 'error' => 'tipo de imagen no permitido'];
    }

    // 3) Doble verificación con fileinfo (por si getimagesize se deja engañar).
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime2 = $fi ? strtolower((string) finfo_file($fi, $file['tmp_name'])) : '';
        if ($fi) { finfo_close($fi); }
        if ($mime2 !== '' && !isset(IMG_MIME_EXT[$mime2])) {
            return ['ok' => false, 'error' => 'el contenido del archivo no coincide con una imagen'];
        }
    }

    // 4) Freno a imágenes desproporcionadas (bombas de descompresión).
    if (($info[0] * $info[1]) > IMG_MAX_PIXELS || $info[0] > 15000 || $info[1] > 15000) {
        return ['ok' => false, 'error' => 'la imagen tiene dimensiones excesivas'];
    }

    // 5) Rechazar poliglotos: una imagen real no lleva código PHP embebido.
    $raw = (string) @file_get_contents($file['tmp_name'], false, null, 0, IMG_MAX);
    if ($raw !== '' && preg_match('/<\?php|<\?=|<script\b/i', $raw)) {
        return ['ok' => false, 'error' => 'el archivo contiene código y fue rechazado'];
    }

    // 6) La extensión guardada la decide el TIPO REAL, no el nombre del usuario.
    $ext = IMG_MIME_EXT[$mime];

    if (!is_dir(RUTA_IMAGENES)) {
        @mkdir(RUTA_IMAGENES, 0775, true);
    }
    $nombre = nombre_unico(RUTA_IMAGENES, $nombreBase ?: pathinfo($file['name'], PATHINFO_FILENAME), $ext);
    $destino = RUTA_IMAGENES . '/' . $nombre;
    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        return ['ok' => false, 'error' => 'no se pudo guardar la imagen'];
    }
    @chmod($destino, 0644);   // nunca ejecutable
    return ['ok' => true, 'nombre' => $nombre, 'aviso' => evaluar_rendimiento($size, (int) $info[0])];
}

/**
 * Procesa un $_FILES[...] de PDF.
 * @return array{ok:bool, nombre?:string, error?:string}
 */
function subir_pdf(array $file, string $nombreBase = ''): array
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'sin archivo'];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'error de subida (' . $file['error'] . ')'];
    }
    if ($file['size'] > PDF_MAX) {
        return ['ok' => false, 'error' => 'el PDF supera 25 MB'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['ok' => false, 'error' => 'solo se permiten archivos PDF'];
    }
    $fh = fopen($file['tmp_name'], 'rb');
    $firma = $fh ? fread($fh, 5) : '';
    if ($fh) {
        fclose($fh);
    }
    if (strpos($firma, '%PDF') !== 0) {
        return ['ok' => false, 'error' => 'el archivo no es un PDF válido'];
    }
    if (!is_dir(RUTA_PDF)) {
        @mkdir(RUTA_PDF, 0775, true);
    }
    $nombre = nombre_unico(RUTA_PDF, $nombreBase ?: pathinfo($file['name'], PATHINFO_FILENAME), 'pdf');
    if (!move_uploaded_file($file['tmp_name'], RUTA_PDF . '/' . $nombre)) {
        return ['ok' => false, 'error' => 'no se pudo guardar el PDF'];
    }
    return ['ok' => true, 'nombre' => $nombre];
}
