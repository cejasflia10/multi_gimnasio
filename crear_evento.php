<?php
/*******************************************************
 * crear_evento.php — con Cloudinary integrado
 *******************************************************/
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_set_cookie_params([
    'lifetime'=>0,'path'=>'/','domain'=>'','secure'=>false,'httponly'=>true,'samesite'=>'Lax'
  ]);
  session_start();
}

require_once __DIR__.'/conexion.php';
@include_once __DIR__.'/menu_eventos.php';

/* =========================================================
   Cloudinary (Cloudy) — activado con tus credenciales
   ========================================================= */
const CLOUD_ENABLED      = true;                 // ← activado
const CLOUD_NAME         = 'ddfugds9b';
const CLOUD_API_KEY      = '657814174747186';
const CLOUD_API_SECRET   = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';
const CLOUD_FOLDER_ROOT  = 'ROOT';               // carpeta raíz en Cloudinary

$__cloud_inited = false;
function cloud_init(): void {
  global $__cloud_inited;
  if ($__cloud_inited) return;
  $__cloud_inited = true;

  // Si existe autoload del SDK, lo cargamos
  $v1 = __DIR__.'/vendor/autoload.php';
  $v2 = dirname(__DIR__).'/vendor/autoload.php';
  if (file_exists($v1)) { require_once $v1; }
  elseif (file_exists($v2)) { require_once $v2; }

  // Si hay SDK y NO tenemos CLOUDINARY_URL, configuramos instancia
  if (class_exists('\Cloudinary\Configuration\Configuration') && CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) {
    \Cloudinary\Configuration\Configuration::instance([
      'cloud' => [
        'cloud_name' => CLOUD_NAME,
        'api_key'    => CLOUD_API_KEY,
        'api_secret' => CLOUD_API_SECRET,
      ],
      'secure' => true
    ]);
  }
}
function cloud_configured(): bool {
  if (!CLOUD_ENABLED) return false;
  return (bool)(CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET);
}

/**
 * Fallback: firma para la API de subida si no hay SDK.
 * signature = sha1(query_string + api_secret)
 */
function cloud_sign(array $params): string {
  ksort($params);
  $toSign = [];
  foreach ($params as $k=>$v) {
    if ($v === '' || $v === null) continue;
    $toSign[] = $k.'='.$v;
  }
  $base = implode('&', $toSign) . CLOUD_API_SECRET;
  return sha1($base);
}

/**
 * Sube archivo a Cloudinary. Usa SDK si está; si no, cURL firmando.
 * @return string|null secure_url si ok
 */
function cloud_upload_file(string $abs_path, string $folder, string $public_id=null): ?string {
  cloud_init();
  if (!cloud_configured() || !is_file($abs_path)) return null;

  // Opción A: SDK
  if (class_exists('\Cloudinary\Api\Upload\UploadApi')) {
    try {
      $up = new \Cloudinary\Api\Upload\UploadApi();
      $opt = [
        'folder'        => $folder,
        'resource_type' => 'auto',
        'overwrite'     => true
      ];
      if ($public_id) $opt['public_id'] = $public_id;
      $res = $up->upload($abs_path, $opt);
      return $res['secure_url'] ?? null;
    } catch (\Throwable $e) {
      // si falla, probamos cURL
    }
  }

  // Opción B: cURL directo (firma server-side)
  $url = "https://api.cloudinary.com/v1_1/".rawurlencode(CLOUD_NAME)."/auto/upload";
  $timestamp = time();
  $params = [
    'timestamp'  => $timestamp,
    'folder'     => $folder,
    'public_id'  => $public_id ?: null,
    'overwrite'  => 'true',
    'api_key'    => CLOUD_API_KEY,
  ];
  // Para firmar NO se incluye api_key ni file
  $toSign = [
    'folder'     => $params['folder'],
    'overwrite'  => $params['overwrite'],
    'public_id'  => $params['public_id'],
    'timestamp'  => $params['timestamp'],
  ];
  $signature = cloud_sign($toSign);

  $cfile = function_exists('curl_file_create')
          ? curl_file_create($abs_path, mime_content_type($abs_path) ?: 'application/octet-stream', basename($abs_path))
          : '@'.$abs_path;

  $post = [
    'file'       => $cfile,
    'api_key'    => CLOUD_API_KEY,
    'timestamp'  => $timestamp,
    'signature'  => $signature,
    'folder'     => $params['folder'],
    'overwrite'  => $params['overwrite'],
  ];
  if ($public_id) $post['public_id'] = $public_id;

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_TIMEOUT        => 30
  ]);
  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  curl_close($ch);
  if ($err || !$resp) return null;

  $json = json_decode($resp, true);
  return $json['secure_url'] ?? null;
}

/* =========================================================
   DB & helpers
   ========================================================= */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function save_local_upload(string $field, string $subdir): ?array {
  if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
  $tmp = (string)($_FILES[$field]['tmp_name'] ?? '');
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $orig = basename((string)($_FILES[$field]['name'] ?? 'archivo'));
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $allow = ['jpg','jpeg','png','webp','gif','pdf'];
  if (!in_array($ext, $allow, true)) $ext = 'jpg';

  $base = pathinfo($orig, PATHINFO_FILENAME);
  $base = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', $base);
  $base = trim($base, '-_') ?: 'archivo';

  $dirAbs = __DIR__ . DIRECTORY_SEPARATOR . $subdir;
  if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true);
  if (!is_dir($dirAbs)) return null;

  $uniq = date('Ymd_His') . '_' . mt_rand(1000,9999);
  $file = $base . '_' . $uniq . '.' . $ext;

  $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $file;
  $destRel = $subdir . '/' . $file;

  if (!@move_uploaded_file($tmp, $destAbs)) return null;
  @chmod($destAbs, 0644);

  return ['abs'=>$destAbs, 'rel'=>$destRel, 'name'=>$base, 'ext'=>$ext];
}

/* =========================================================
   POST: crear evento
   ========================================================= */
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo      = trim($_POST['titulo'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $fecha       = trim($_POST['fecha'] ?? '');
  $hora        = trim($_POST['hora'] ?? '');
  $lugar       = trim($_POST['lugar'] ?? '');
  $video       = trim($_POST['video'] ?? '');

  if ($titulo && $fecha && $hora && $lugar) {
    // 1) Guardado local del flyer
    $local = save_local_upload('flyer', 'flyers_eventos'); // ['abs','rel','name','ext']|null
    $ruta_flyer = $local['rel'] ?? '';

    // 2) Cloudinary (si hay archivo subido y Cloudy está activo)
    $flyer_cloud = null;
    if ($local && cloud_configured()) {
      // carpeta: ROOT/eventos/aaaamm/titulo_slug
      $slug = preg_replace('/[^\p{L}\p{N}\-]+/u','-', strtolower($titulo));
      $slug = trim($slug,'-') ?: 'evento';
      $folder = CLOUD_FOLDER_ROOT . '/eventos/' . date('Ym') . '/' . $slug;
      $public = 'flyer_' . $local['name'] . '_' . date('Ymd_His');

      $flyer_cloud = cloud_upload_file($local['abs'], $folder, $public);
      if ($flyer_cloud) {
        // Priorizar CDN sobre local:
        $ruta_flyer = $flyer_cloud;
      }
    }

    // 3) Insert
    $stmt = $conexion->prepare("INSERT INTO eventos_deportivos (titulo, descripcion, fecha, hora, lugar, flyer, video) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
      $stmt->bind_param("sssssss", $titulo, $descripcion, $fecha, $hora, $lugar, $ruta_flyer, $video);
      if ($stmt->execute()) {
        $mensaje = "✅ Evento creado correctamente.";
      } else {
        $mensaje = "❌ Error al guardar el evento: ".$stmt->error;
      }
      $stmt->close();
    } else {
      $mensaje = "❌ Error preparando INSERT: ".$conexion->error;
    }
  } else {
    $mensaje = "⚠️ Completá todos los campos obligatorios.";
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear Evento Deportivo</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body { background-color:#000; color:gold; font-family:'Segoe UI',sans-serif; }
    .contenedor{ width:80%; max-width:1200px; margin:40px auto; background:#111; padding:35px; border-radius:12px; border:2px solid gold; box-shadow:0 0 15px rgba(255,215,0,.3) }
    h2,h3{ color:gold; margin-bottom:25px }
    form label{ display:block; margin-top:15px; font-weight:600 }
    input[type="text"],input[type="date"],input[type="time"],input[type="file"],textarea{
      width:100%; padding:12px; margin-top:5px; border-radius:6px; border:1px solid #555; background:#1a1a1a; color:gold; font-size:16px
    }
    textarea{ resize:vertical }
    .boton{ margin-top:25px; padding:12px 24px; background:linear-gradient(to right, gold, #d4af37); color:#000; border:none; border-radius:8px; font-weight:bold; cursor:pointer; transition:.3s; font-size:16px }
    .boton:hover{ background:linear-gradient(to right, #ffe600, gold); transform:scale(1.05) }
    .boton-volver{ text-decoration:none; padding:12px 20px; background:#222; color:gold; border:1px solid gold; border-radius:8px; margin-left:15px; transition:.3s }
    .boton-volver:hover{ background:gold; color:#000 }
    .acciones{ margin-top:40px; display:flex; flex-wrap:wrap; gap:15px }
    .boton-accion{ flex:1 1 200px; text-align:center; background:#222; color:gold; padding:15px 20px; border:2px solid gold; border-radius:10px; text-decoration:none; font-weight:bold; transition:.3s }
    .boton-accion:hover{ background:gold; color:#111; transform:scale(1.05) }
    .mensaje{ background:#222; padding:10px 20px; border-left:5px solid gold; margin-bottom:20px; border-radius:8px; color:#ffd700 }
  </style>
</head>
<body>
<div class="contenedor">
  <h2>🎯 Crear Evento Deportivo</h2>

  <?php if (!empty($mensaje)): ?>
    <div class="mensaje"><?= h($mensaje) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data">
    <label>Título del Evento:</label>
    <input type="text" name="titulo" required>

    <label>Descripción:</label>
    <textarea name="descripcion" rows="4"></textarea>

    <label>Fecha:</label>
    <input type="date" name="fecha" required>

    <label>Hora de Inicio:</label>
    <input type="time" name="hora" required>

    <label>Lugar:</label>
    <input type="text" name="lugar" required>

    <label>Flyer del Evento (imagen o PDF):</label>
    <input type="file" name="flyer" accept="image/*,application/pdf">

    <label>Video Promocional (YouTube o enlace directo):</label>
    <input type="text" name="video" placeholder="https://youtube.com/...">

    <button type="submit" class="boton">✅ Crear Evento</button>
    <a href="index.php" class="boton-volver">⬅ Volver</a>
  </form>

  <div class="acciones">
    <a href="ver_evento.php" class="boton-accion">📅 Ver Eventos</a>
    <a href="ver_tipos_entrada.php" class="boton-accion">🎫 Cargar Tipos de Entradas</a>
    <a href="vender_entrada.php" class="boton-accion">🛒 Vender Entradas</a>
    <a href="ver_entradas_vendidas.php" class="boton-accion">📥 Ver Entradas Vendidas</a>
    <a href="ver_inscriptos.php" class="boton-accion">📋 Ver Inscriptos</a>
    <a href="reporte_ganancias.php" class="boton-accion">💲 Ver Ganancias</a>
    <a href="informe_evento_pdf.php" class="boton-accion">🖨️ Generar Informe PDF</a>
  </div>
</div>
</body>
</html>
