<?php
/*******************************************************
 * crear_evento.php — con Cloudinary (Cloudy)
 *******************************************************/
session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__.'/menu_eventos.php';

/* =========================================================
   Cloudinary (opcional) — “Cloudy”
   =========================================================
   Opción 1 (recomendada): setear CLOUDINARY_URL en el entorno
     cloudinary://API_KEY:API_SECRET@CLOUD_NAME
   Opción 2: activar constantes acá:
*/
const CLOUD_ENABLED     = false;   // ← poné true si usás constantes
const CLOUD_NAME        = '';
const CLOUD_API_KEY     = '';
const CLOUD_API_SECRET  = '';

function cloud_init(): void {
  static $inited=false; if ($inited) return; $inited=true;
  $vendor1 = __DIR__.'/vendor/autoload.php';
  $vendor2 = dirname(__DIR__).'/vendor/autoload.php';
  if (file_exists($vendor1)) require_once $vendor1;
  elseif (file_exists($vendor2)) require_once $vendor2;
}
function cloud_configured(): bool {
  $url = getenv('CLOUDINARY_URL');
  if ($url && is_string($url) && preg_match('~^cloudinary://[^:]+:[^@]+@[^/]+$~',$url)) return true;
  if (CLOUD_ENABLED && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) return true;
  return false;
}
/** Sube un archivo local a Cloudinary. Devuelve secure_url o null. */
function cloud_upload(string $abs_path, string $folder, string $public_id): ?string {
  if (!cloud_configured()) return null;
  try {
    cloud_init();
    if (!getenv('CLOUDINARY_URL') && CLOUD_ENABLED) {
      \Cloudinary\Configuration\Configuration::instance([
        'cloud'=>['cloud_name'=>CLOUD_NAME,'api_key'=>CLOUD_API_KEY,'api_secret'=>CLOUD_API_SECRET],
        'url'=>['secure'=>true],
      ]);
    }
    $uploader = new \Cloudinary\Api\Upload\UploadApi();
    $res = $uploader->upload($abs_path, [
      'folder'        => $folder,
      'public_id'     => $public_id,
      'resource_type' => 'auto',
      'overwrite'     => true,
    ]);
    return $res['secure_url'] ?? null;
  } catch (\Throwable $e) {
    // Podés loguear $e->getMessage()
    return null;
  }
}

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/**
 * Guarda el archivo (crea carpeta si falta) y si Cloudinary está activo,
 * además lo sube a la nube y retorna la secure_url. Si no, retorna ruta relativa local.
 * $subdir: 'flyers_eventos' o 'media_eventos'
 */
function save_asset(string $field, string $subdir, string $cloudFolder): ?string {
  if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
  $err = $_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE;
  if ($err !== UPLOAD_ERR_OK) return null;

  $tmp  = (string)($_FILES[$field]['tmp_name'] ?? '');
  if (!$tmp || !is_uploaded_file($tmp)) return null;

  $orig = basename((string)($_FILES[$field]['name'] ?? 'archivo'));
  $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
  $allow= ['jpg','jpeg','png','webp','gif','pdf'];
  if (!in_array($ext,$allow,true)) $ext='jpg';

  $base = pathinfo($orig, PATHINFO_FILENAME);
  $base = preg_replace('/[^\p{L}\p{N}\-_]+/u','-',$base);
  $base = trim($base,'-_') ?: 'archivo';

  $dirAbs = __DIR__ . DIRECTORY_SEPARATOR . $subdir;
  if (!is_dir($dirAbs)) @mkdir($dirAbs, 0775, true); // evita "No such file or directory"
  if (!is_dir($dirAbs)) return null;

  $uniq = date('Ymd_His') . '_' . mt_rand(1000,9999);
  $file = $base . '_' . $uniq . '.' . $ext;
  $destAbs = $dirAbs . DIRECTORY_SEPARATOR . $file;
  $destRel = $subdir . '/' . $file;

  if (!@move_uploaded_file($tmp, $destAbs)) {
    // reintento con nombre simple (Windows/XAMPP a veces falla por espacios/acentos)
    $file2 = 'file_' . $uniq . '.' . $ext;
    $destAbs2 = $dirAbs . DIRECTORY_SEPARATOR . $file2;
    $destRel2 = $subdir . '/' . $file2;
    if (!@move_uploaded_file($tmp, $destAbs2)) return null;
    @chmod($destAbs2, 0644);
    $destAbs = $destAbs2; $destRel = $destRel2; $file = $file2;
  } else {
    @chmod($destAbs, 0644);
  }

  // Cloudinary
  $publicId = $field . '_' . $base . '_' . $uniq;
  $cloudUrl = cloud_upload($destAbs, $cloudFolder, $publicId);
  if ($cloudUrl) return $cloudUrl;

  return $destRel;
}

/* ===== Conexión ===== */
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo = trim($_POST['titulo'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $fecha = $_POST['fecha'] ?? '';
  $hora  = $_POST['hora']  ?? '';
  $lugar = trim($_POST['lugar'] ?? '');
  $video = trim($_POST['video'] ?? '');

  if ($titulo && $fecha && $hora && $lugar) {
    // Subidas
    $cloudFolder = 'multi_gimnasio/eventos/crear_'.date('Ymd');
    $ruta_flyer   = save_asset('flyer',   'flyers_eventos', $cloudFolder); // puede devolver URL Cloud
    $ruta_logo    = save_asset('logo',    'media_eventos',  $cloudFolder); // opcional
    $ruta_portada = save_asset('portada', 'media_eventos',  $cloudFolder); // opcional

    // INSERT flexible: siempre estos 7 campos base
    $sql = "INSERT INTO eventos_deportivos (titulo, descripcion, fecha, hora, lugar, flyer, video";
    $vals = [$titulo, $descripcion, $fecha, $hora, $lugar, (string)($ruta_flyer ?? ''), $video];
    $types = "sssssss";

    // Si existen columnas extra (logo/portada), las agrego sin romper
    if (has_col($conexion,'eventos_deportivos','logo')) {
      $sql .= ", logo";
      $vals[] = (string)($ruta_logo ?? '');
      $types .= "s";
    }
    if (has_col($conexion,'eventos_deportivos','portada')) {
      $sql .= ", portada";
      $vals[] = (string)($ruta_portada ?? '');
      $types .= "s";
    }
    $sql .= ") VALUES (" . rtrim(str_repeat('?,', strlen($types)), ',') . ")";

    $stmt = $conexion->prepare($sql);
    if ($stmt) {
      $bind = [$types];
      foreach ($vals as $k => $v) { $bind[] = &$vals[$k]; }
      call_user_func_array([$stmt,'bind_param'],$bind);

      if ($stmt->execute()) {
        $mensaje = "✅ Evento creado correctamente.";
      } else {
        $mensaje = "❌ Error al guardar el evento: ".$stmt->error;
      }
      $stmt->close();
    } else {
      $mensaje = "❌ Error al preparar consulta: ".$conexion->error;
    }
  } else {
    $mensaje = "⚠️ Completa todos los campos obligatorios.";
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
    .contenedor { width:80%; max-width:1200px; margin:40px auto; background:#111; padding:35px; border-radius:12px; border:2px solid gold; box-shadow:0 0 15px rgba(255,215,0,.3); }
    h2,h3 { color:gold; margin-bottom:25px; }
    form label { display:block; margin-top:15px; font-weight:600; }
    input[type="text"], input[type="date"], input[type="time"], input[type="file"], textarea {
      width:100%; padding:12px; margin-top:5px; border-radius:6px; border:1px solid #555; background:#1a1a1a; color:gold; font-size:16px;
    }
    textarea { resize:vertical; }
    .boton { margin-top:25px; padding:12px 24px; background:linear-gradient(to right,gold,#d4af37); color:#000; border:none; border-radius:8px; font-weight:bold; cursor:pointer; transition:.3s; font-size:16px; }
    .boton:hover { background:linear-gradient(to right,#ffe600,gold); transform:scale(1.05); }
    .boton-volver { text-decoration:none; padding:12px 20px; background:#222; color:gold; border:1px solid gold; border-radius:8px; margin-left:15px; transition:.3s; }
    .boton-volver:hover { background:gold; color:#000; }
    .acciones { margin-top:40px; display:flex; flex-wrap:wrap; gap:15px; }
    .boton-accion { flex:1 1 200px; text-align:center; background:#222; color:gold; padding:15px 20px; border:2px solid gold; border-radius:10px; text-decoration:none; font-weight:bold; transition:.3s; }
    .boton-accion:hover { background:gold; color:#111; transform:scale(1.05); }
    .mensaje { background:#222; padding:10px 20px; border-left:5px solid gold; margin-bottom:20px; border-radius:8px; }
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

    <label>Flyer del Evento (imagen/PDF):</label>
    <input type="file" name="flyer" accept="image/*,application/pdf">

    <!-- Opcionales: si tu tabla tiene columnas logo/portada, se guardan; si no, se ignoran -->
    <label>Logo del Evento (opcional):</label>
    <input type="file" name="logo" accept="image/*,application/pdf">

    <label>Portada/Banner (opcional):</label>
    <input type="file" name="portada" accept="image/*,application/pdf">

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
