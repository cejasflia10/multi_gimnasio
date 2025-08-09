<?php
session_start();
include 'conexion.php';

// --- Validaciones iniciales ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    exit("ID no especificado.");
}
if ($gimnasio_id === 0) {
    http_response_code(403);
    exit("Gimnasio no definido.");
}

$debug = isset($_GET['debug']) ? (int)$_GET['debug'] : 0;

// --- Buscar registro ---
$sql = "SELECT archivo_comprobante
        FROM pagos_pendientes
        WHERE id = ? AND gimnasio_id = ?
        LIMIT 1";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("ii", $id, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;

if (!$row || empty($row['archivo_comprobante'])) {
    http_response_code(404);
    exit("No se encontró el comprobante.");
}

// --- Normalizar ruta web guardada ---
$archivo = trim((string)$row['archivo_comprobante']);

// Si guardaron URL completa (http/https), tomar solo el path
if (preg_match('#^https?://#i', $archivo)) {
    $u = parse_url($archivo);
    $archivo = isset($u['path']) ? $u['path'] : '';
    $archivo = (string)$archivo;
}

if ($archivo === '') {
    http_response_code(404);
    exit("No se encontró el comprobante.");
}

// Asegurar "/" inicial
if ($archivo[0] !== '/') {
    $archivo = '/' . $archivo;
}

// --- Generar candidatos de ruta física ---
$docRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), "/\\");
$here    = rtrim(__DIR__, "/\\");
$candidatos = [];

// 1) DOCUMENT_ROOT + ruta tal cual
if ($docRoot !== '') $candidatos[] = $docRoot . $archivo;
// 2) __DIR__ + ruta tal cual
$candidatos[] = $here . $archivo;

// 3) Si no empieza con /multi_gimnasio, probar agregándolo al inicio
if (strpos($archivo, '/multi_gimnasio/') !== 0) {
    if ($docRoot !== '') $candidatos[] = $docRoot . '/multi_gimnasio' . $archivo;
    $candidatos[] = $here . '/multi_gimnasio' . $archivo;
}

// 4) También probar sin el primer slash (por si quedó doble carpeta al concatenar)
$sinSlash = ltrim($archivo, '/');
if ($docRoot !== '') $candidatos[] = $docRoot . '/' . $sinSlash;
$candidatos[] = $here . '/' . $sinSlash;

// --- Elegir el primero que exista ---
$ruta = null;
foreach ($candidatos as $fs) {
    if (is_file($fs)) { $ruta = $fs; break; }
}

if (!$ruta) {
    if ($debug) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "No se encontró el comprobante.\n\n";
        echo "Valor en BD: " . $row['archivo_comprobante'] . "\n";
        echo "Normalizado: " . $archivo . "\n\n";
        echo "DOCUMENT_ROOT: " . ($docRoot ?: '(vacío)') . "\n";
        echo "__DIR__: " . $here . "\n\n";
        echo "Candidatos probados:\n";
        foreach ($candidatos as $fs) echo " - $fs\n";
    } else {
        http_response_code(404);
        echo "No se encontró el comprobante.";
    }
    exit;
}

// --- Detectar MIME y servir inline ---
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $ruta) ?: 'application/octet-stream';
finfo_close($finfo);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
readfile($ruta);
exit;
