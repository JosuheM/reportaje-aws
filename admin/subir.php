<?php
/** Endpoint para subir imágenes desde el editor Summernote. Devuelve JSON {url:"assets/images/..."}. */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/config/uploads.php';

header('Content-Type: application/json; charset=utf-8');

if (!esta_logueado()) {
    http_response_code(401);
    exit(json_encode(['error' => 'no autorizado']));
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    exit(json_encode(['error' => 'petición inválida']));
}
// Mismo origen + token CSRF (lo envía el editor junto con la imagen).
if (!csrf_ok()) {
    http_response_code(403);
    exit(json_encode(['error' => 'token de seguridad inválido, recarga la página']));
}

$r = subir_imagen($_FILES['file'], pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
if (!$r['ok']) {
    http_response_code(422);
    exit(json_encode(['error' => $r['error']]));
}

echo json_encode(['url' => 'assets/images/' . $r['nombre'], 'nombre' => $r['nombre'], 'aviso' => $r['aviso'] ?? null]);
