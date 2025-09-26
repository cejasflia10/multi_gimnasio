<?php
/* promociones_admin.php — Cloudinary activado con tu bootstrap (sin Composer)
   Corregido: request_promo (cliente solicita promoción -> guarda en promo_requests y redirige al link)
   No se modificaron las claves CLOUD_*
*/
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
require_once __DIR__ . '/menu_horizontal.php';

@date_default_timezone_set('America/Argentina/San_Luis');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$gym_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gym_id <= 0) { http_response_code(403); exit('Gimnasio no identificado.'); }

/* ============================================================
   CSRF
   ============================================================ */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

/* ============================================================
   Cloudy (Cloudinary) — Init con tu bootstrap existente
   ============================================================ */
if (!defined('CLOUD_ENABLED'))      define('CLOUD_ENABLED', true);
if (!defined('CLOUD_NAME'))         define('CLOUD_NAME', 'ddfugds9b');         // tus claves - NO MODIFICAR
if (!defined('CLOUD_API_KEY'))      define('CLOUD_API_KEY', '657814174747186'); // tus claves - NO MODIFICAR
if (!defined('CLOUD_API_SECRET'))   define('CLOUD_API_SECRET', 'TKo5BRiKCEjxSLFzn2DLbz_ji4c'); // tus claves - NO MODIFICAR
if (!defined('CLOUD_FOLDER_ROOT'))  define('CLOUD_FOLDER_ROOT', 'ROOT');

$__cloud_ready = false;
$__cloud_err   = null;

@include_once __DIR__.'/cloudy_boot_constants.php'; // no rompe si no existe
if (function_exists('cloud_init')) {
  try { cloud_init(); } catch (Throwable $e) { $__cloud_err = 'Init Cloudy falló: '.$e->getMessage(); }
}

/* ------------------------
  Fallback DIRECTO a Cloudinary (sin SDK ni bootstrap)
  ------------------------ */

/**
 * Firma para Cloudinary: recibir array de params (no incluir api_key ni file),
 * ordenarlos por key y concatenar "k1=v1&k2=v2..."+api_secret -> sha1.
 */
function cloud_sign_params(array $params, string $api_secret): string {
  ksort($params);
  $pairs = [];
  foreach ($params as $k => $v) {
    if ($v === null || $v === '') continue;
    $pairs[] = $k . '=' . $v;
  }
  $to_sign = implode('&', $pairs) . $api_secret;
  return sha1($to_sign);
}

/**
 * Subida directa por REST a Cloudinary usando cURL.
 * Devuelve array (resultado decodificado) o null en fallo.
 */
function cloudary_direct_upload(string $file_path, array $options = []): ?array {
  if (!function_exists('curl_version')) return null;
  $timestamp = time();
  $params = [
    'timestamp' => $timestamp,
  ];
  if (!empty($options['folder'])) $params['folder'] = $options['folder'];
  if (!empty($options['public_id'])) $params['public_id'] = $options['public_id'];
  if (!empty($options['resource_type'])) $params['resource_type'] = $options['resource_type'];
  // utilizar constantes definidas
  $api_secret = defined('CLOUD_API_SECRET') ? CLOUD_API_SECRET : '';
  $api_key = defined('CLOUD_API_KEY') ? CLOUD_API_KEY : '';
  if (!$api_secret || !$api_key) return null;
  $signature = cloud_sign_params($params, $api_secret);
  $post = $params;
  $post['signature'] = $signature;
  $post['api_key'] = $api_key;
  $post['file'] = new CURLFile($file_path);

  $cloud_name = defined('CLOUD_NAME') ? CLOUD_NAME : '';
  $url = "https://api.cloudinary.com/v1_1/".rawurlencode($cloud_name)."/image/upload";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_errno($ch) ? curl_error($ch) : null;
  curl_close($ch);
  if ($err) {
    error_log('Cloud direct upload curl err: '.$err);
    return null;
  }
  if ($http < 200 || $http >= 300) {
    error_log('Cloud direct upload http '.$http.' resp: '.$resp);
    return null;
  }
  $j = json_decode($resp, true);
  return is_array($j) ? $j : null;
}

/**
 * Destruir recurso directo (destroy) por REST.
 */
function cloudary_direct_destroy(string $public_id): bool {
  if (!function_exists('curl_version')) return false;
  $timestamp = time();
  $api_secret = defined('CLOUD_API_SECRET') ? CLOUD_API_SECRET : '';
  $api_key = defined('CLOUD_API_KEY') ? CLOUD_API_KEY : '';
  if (!$api_secret || !$api_key) return false;
  $params = [
    'public_id' => $public_id,
    'timestamp' => $timestamp
  ];
  $signature = cloud_sign_params($params, $api_secret);
  $post = [
    'public_id' => $public_id,
    'timestamp' => $timestamp,
    'api_key' => $api_key,
    'signature' => $signature
  ];
  $cloud_name = defined('CLOUD_NAME') ? CLOUD_NAME : '';
  $url = "https://api.cloudinary.com/v1_1/".rawurlencode($cloud_name)."/image/destroy";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_errno($ch) ? curl_error($ch) : null;
  curl_close($ch);
  if ($err) { error_log('Cloud destroy curl err: '.$err); return false; }
  if ($http < 200 || $http >= 300) { error_log('Cloud destroy http '.$http.' resp: '.$resp); return false; }
  $j = json_decode($resp, true);
  return !empty($j['result']) && in_array($j['result'], ['ok','not_found','deleted'], true);
}

/* Si no hay helpers ni SDK, exponemos nuestras funciones directas como helpers:
   cloud_upload, cloud_destroy — así tu código ya escrito las detectará */
if (!function_exists('cloud_upload')) {
  function cloud_upload($file_path, $opts = []) {
    $res = cloudary_direct_upload($file_path, $opts);
    if (is_array($res) && !empty($res['secure_url'])) return $res;
    return $res ? $res : null;
  }
}
if (!function_exists('cloud_destroy')) {
  function cloud_destroy($public_id, $opts = []) {
    return cloudary_direct_destroy($public_id);
  }
}

/* Re-evaluar disponibilidad Cloudy: si CURL existe y credenciales parecen puestas */
if (function_exists('curl_version') && defined('CLOUD_NAME') && defined('CLOUD_API_KEY') && defined('CLOUD_API_SECRET')
    && CLOUD_NAME && CLOUD_API_KEY && CLOUD_API_SECRET) {
  $__cloud_ready = true;
}

/* Marcamos listo si:
   - hay helpers (cloud_upload / cloudy_upload), o
   - el bootstrap ya cargó las clases del SDK */
$__cloud_ready = (defined('CLOUD_ENABLED') && CLOUD_ENABLED === true) && (
  function_exists('cloud_upload') ||
  function_exists('cloudy_upload') ||
  class_exists('\\Cloudinary\\Api\\Upload\\UploadApi')
);

/* ============================================================
   DB: asegurar tablas
   ============================================================ */
$conexion->query("
  CREATE TABLE IF NOT EXISTS promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    titulo VARCHAR(120) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen_url VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(255) DEFAULT NULL,
    color_fondo VARCHAR(20) DEFAULT '#111111',
    color_texto VARCHAR(20) DEFAULT '#FFD700',
    fecha_inicio DATE DEFAULT NULL,
    fecha_fin DATE DEFAULT NULL,
    prioridad INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (gimnasio_id),
    INDEX (activo),
    INDEX (fecha_fin)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

/* Tabla solicitudes de promoción (clientes que piden la promo) */
$conexion->query("
  CREATE TABLE IF NOT EXISTS promo_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promocion_id INT NOT NULL,
    gimnasio_id INT NOT NULL,
    cliente_id INT DEFAULT NULL,
    nombre_cliente VARCHAR(255) DEFAULT NULL,
    email_cliente VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX(promocion_id),
    INDEX(gimnasio_id),
    INDEX(cliente_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$msg = '';
$last_uploaded_url = null;

/* ============================================================
   Helpers Cloudy / archivos
   ============================================================ */
function is_cloud_url(string $url): bool {
  if ($url === '') return false;
  $host = parse_url($url, PHP_URL_HOST) ?: '';
  return (bool)preg_match('~(^|\.)res\.cloudinary\.com$~i', $host);
}
function cloud_public_id_from_url(string $url): ?string {
  $path = parse_url($url, PHP_URL_PATH) ?: '';
  if (!$path) return null;
  $parts = explode('/upload/', $path, 2);
  if (count($parts) < 2) return null;
  $tail = $parts[1];
  $tail = preg_replace('~\.[a-z0-9]+$~i', '', $tail);
  return ltrim($tail, '/');
}

/**
 * Sube imagen de promo a Cloudinary usando TU bootstrap (sin Composer).
 * Si no está disponible, guarda local en /uploads/promociones.
 */
function subir_imagen_promocion(?array $file, int $gym_id, bool $cloud_ready): ?string {
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
  if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) return null;

  // Validaciones básicas
  $permitidos = [
    'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp','image/heic'=>'heic','image/heif'=>'heif'
  ];
  $mime = @mime_content_type($file['tmp_name']);
  if (!$mime || !isset($permitidos[$mime])) return null;
  if (($file['size'] ?? 0) > 10*1024*1024) return null; // 10MB

  // Intentar Cloudinary con tu bootstrap
  if ($cloud_ready) {
    $folder   = rtrim(CLOUD_FOLDER_ROOT ?: 'ROOT', '/').'/promociones/'.$gym_id;
    $basename = 'promo_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
    try {
      // Caso 1: helper propio
      if (function_exists('cloud_upload')) {
        $res = cloud_upload($file['tmp_name'], [
          'folder'=>$folder, 'public_id'=>$basename, 'resource_type'=>'image',
          'overwrite'=>false, 'invalidate'=>true
        ]);
        if (is_array($res) && !empty($res['secure_url'])) return $res['secure_url'];
        if (is_string($res) && str_starts_with($res, 'http')) return $res;
      }
      if (function_exists('cloudy_upload')) {
        $res = cloudy_upload($file['tmp_name'], [
          'folder'=>$folder, 'public_id'=>$basename, 'resource_type'=>'image',
          'overwrite'=>false, 'invalidate'=>true
        ]);
        if (is_array($res) && !empty($res['secure_url'])) return $res['secure_url'];
        if (is_string($res) && str_starts_with($res, 'http')) return $res;
      }
      // Caso 2: clases del SDK ya cargadas por tu bootstrap
      if (class_exists('\\Cloudinary\\Api\\Upload\\UploadApi')) {
        $uploader = new \Cloudinary\Api\Upload\UploadApi();
        $res = $uploader->upload($file['tmp_name'], [
          'folder'          => $folder,
          'public_id'       => $basename,
          'overwrite'       => false,
          'resource_type'   => 'image',
          'use_filename'    => true,
          'unique_filename' => true,
          'invalidate'      => true,
          'format'          => 'jpg',
          'quality'         => 'auto',
          'fetch_format'    => 'auto'
        ]);
        if (!empty($res['secure_url'])) return $res['secure_url'];
      }
    } catch (\Throwable $e) {
      // guardamos error en log y seguimos con fallback local
      error_log('Cloud upload error: '.$e->getMessage());
    }
  }

  // Fallback local
  $dir = __DIR__ . '/uploads/promociones';
  if (!is_dir($dir)) @mkdir($dir, 0777, true);
  $ext = $permitidos[$mime];
  $name = 'promo_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
  $dest = $dir.'/'.$name;
  if (!move_uploaded_file($file['tmp_name'], $dest)) return null;
  return 'uploads/promociones/'.$name;
}

/** Elimina en Cloudinary si la URL es cloud y tu bootstrap está disponible */
function borrar_en_cloudinary(string $url): void {
  global $__cloud_ready;
  if (!$__cloud_ready || !is_cloud_url($url)) return;
  try {
    $pid = cloud_public_id_from_url($url);
    if ($pid) {
      if (function_exists('cloud_destroy')) {
        @cloud_destroy($pid, ['resource_type'=>'image','invalidate'=>true]);
        return;
      }
      if (class_exists('\\Cloudinary\\Api\\Upload\\UploadApi')) {
        $uploader = new \Cloudinary\Api\Upload\UploadApi();
        $uploader->destroy($pid, ['resource_type'=>'image','invalidate'=>true]);
      } else {
        // fallback directo
        if (function_exists('cloudary_direct_destroy')) {
          @cloudary_direct_destroy($pid);
        }
      }
    }
  } catch (\Throwable $e) {
    // no interrumpir
  }
}

/* ============================================================
   Crear / actualizar / activar / eliminar + request_promo
   ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['act'] ?? '';

  // Validar CSRF para acciones que modifican
  $skip_csrf_for = []; // ninguna
  if (!in_array($act, $skip_csrf_for, true)) {
    if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf'])) {
      $msg = '❌ CSRF inválido.';
      // no exit — mostramos error en UI
    }
  }

  if ($act === 'save') {
    $id  = (int)($_POST['id'] ?? 0);
    $tit = trim($_POST['titulo'] ?? '');
    $desc= trim($_POST['descripcion'] ?? '');
    $img_url_form = trim($_POST['imagen_url'] ?? ''); // URL manual (opcional)
    $lnk = trim($_POST['link_url'] ?? '');
    $bg  = trim($_POST['color_fondo'] ?? '#111111');
    $fg  = trim($_POST['color_texto'] ?? '#FFD700');

    $fi  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_inicio'] ?? '') ? $_POST['fecha_inicio'] : null;
    $ff  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['fecha_fin'] ?? '') ? $_POST['fecha_fin'] : null;

    $pri = (int)($_POST['prioridad'] ?? 0);
    $actv= isset($_POST['activo']) ? 1 : 0;

    if ($tit==='') { $msg = '❌ Título requerido.'; }
    else {
      // 1) Subida prioritaria a Cloudinary (o local fallback)
      $img_subida = subir_imagen_promocion($_FILES['imagen_file'] ?? null, $gym_id, $__cloud_ready);
      // 2) Si no subieron archivo, usar URL manual
      $img_final = $img_subida ?: ($img_url_form ?: null);

      if ($id>0) {
        // Si estamos reemplazando imagen (archivo nuevo o URL nueva), borrar la previa
        if ($img_subida || ($img_url_form && $img_url_form !== '')) {
          $qprev = $conexion->query("SELECT imagen_url FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
          if ($qprev && $prev = $qprev->fetch_assoc()) {
            $prev_url = (string)$prev['imagen_url'];
            if ($prev_url) {
              if (is_cloud_url($prev_url)) borrar_en_cloudinary($prev_url);
              elseif (str_starts_with($prev_url, 'uploads/promociones/')) {
                $abs = __DIR__ . '/' . $prev_url;
                if (is_file($abs)) @unlink($abs);
              }
            }
          }
        }

        $sql = "UPDATE promociones 
                SET titulo=?, descripcion=?, imagen_url=?, link_url=?, color_fondo=?, color_texto=?, 
                    fecha_inicio=?, fecha_fin=?, prioridad=?, activo=?
                WHERE id=? AND gimnasio_id=?";
        $st = $conexion->prepare($sql);
        if ($st) {
          $st->bind_param('ssssssssiii',
            $tit,$desc,$img_final,$lnk,$bg,$fg,$fi,$ff,$pri,$actv,$id,$gym_id
          );
        }
      } else {
        $sql = "INSERT INTO promociones 
                (gimnasio_id,titulo,descripcion,imagen_url,link_url,color_fondo,color_texto,fecha_inicio,fecha_fin,prioridad,activo)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)";
        $st = $conexion->prepare($sql);
        if ($st) {
          $st->bind_param('issssssssii',
            $gym_id,$tit,$desc,$img_final,$lnk,$bg,$fg,$fi,$ff,$pri,$actv
          );
        }
      }

      if ($st && $st->execute()) {
        $msg = '✅ Promoción guardada.';
        if ($img_subida) {
          $last_uploaded_url = is_array($img_subida) && !empty($img_subida['secure_url']) ? $img_subida['secure_url'] : (is_string($img_subida) ? $img_subida : null);
          if ($last_uploaded_url) {
            $msg .= ' URL subida: ';
            $msg .= '<a href="'.h($last_uploaded_url).'" target="_blank" style="color:#9fe6ff">'.h($last_uploaded_url).'</a>';
            $msg .= ' <button type="button" class="btn-copy" data-url="'.h($last_uploaded_url).'">Copiar</button>';
          }
        } elseif ($img_final && is_cloud_url($img_final)) {
          $msg .= ' (URL Cloud detectada).';
        }
      } else {
        $msg = '❌ Error al guardar.';
      }
      if ($st) $st->close();
    }
  }

  if ($act === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $v  = (int)($_POST['v'] ?? 0);
    $conexion->query("UPDATE promociones SET activo={$v} WHERE id={$id} AND gimnasio_id={$gym_id}");
  }

  if ($act === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    // borrar del bucket/local si corresponde
    $q = $conexion->query("SELECT imagen_url FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
    if ($q && $row=$q->fetch_assoc()) {
      $url = (string)$row['imagen_url'];
      if ($url) {
        if (is_cloud_url($url)) {
          borrar_en_cloudinary($url);
        } elseif (str_starts_with($url, 'uploads/promociones/')) {
          $abs = __DIR__ . '/' . $url;
          if (is_file($abs)) @unlink($abs);
        }
      }
    }
    $conexion->query("DELETE FROM promociones WHERE id={$id} AND gimnasio_id={$gym_id}");
  }

  // NUEVO: request_promo (cliente solicita la promo -> guarda en promo_requests y redirige al link si existe)
  if ($act === 'request_promo') {
    // Validación CSRF (ya validada arriba en general)
    $promo_id = (int)($_POST['promo_id'] ?? 0);
    if ($promo_id <= 0) {
      $msg = '❌ Promoción inválida.';
    } else {
      $cliente_id = (int)($_SESSION['cliente_id'] ?? 0);
      $nombre_cliente = trim($_POST['nombre_cliente'] ?? ($_SESSION['cliente_nombre'] ?? ''));
      $email_cliente  = trim($_POST['email_cliente'] ?? ($_SESSION['cliente_email'] ?? ''));

      $ins = $conexion->prepare("INSERT INTO promo_requests (promocion_id,gimnasio_id,cliente_id,nombre_cliente,email_cliente) VALUES (?,?,?,?,?)");
      if ($ins) {
        $ins->bind_param('iiiss', $promo_id, $gym_id, $cliente_id, $nombre_cliente, $email_cliente);
        $okreq = $ins->execute();
        $ins->close();
      } else {
        $okreq = false;
      }

      if ($okreq) {
        // opcional: notificar admin si definido
        if (defined('ADMIN_ALERT_EMAIL') && filter_var(ADMIN_ALERT_EMAIL, FILTER_VALIDATE_EMAIL)) {
          $sub = "Nueva solicitud de promoción #{$promo_id}";
          $body = "Gimnasio: {$gym_id}\nPromoción: {$promo_id}\nCliente: ".($nombre_cliente?:'N/D')."\nEmail: ".($email_cliente?:'N/D')."\nFecha: ".date('Y-m-d H:i:s')."\n\nRevisar panel de Promociones.";
          @mail(ADMIN_ALERT_EMAIL, $sub, $body);
        }

        // obtener link de la promoción para redirigir
        $qr = $conexion->query("SELECT link_url FROM promociones WHERE id={$promo_id} AND gimnasio_id={$gym_id} LIMIT 1");
        $link = null;
        if ($qr && $r = $qr->fetch_assoc()) $link = trim($r['link_url']);
        if ($link && filter_var($link, FILTER_VALIDATE_URL)) {
          // redirigir al link de venta (usa header redirect)
          header('Location: '.$link);
          exit;
        } else {
          $msg = '✅ Solicitud registrada. Gracias — no hay link de venta configurado para esta promoción.';
        }
      } else {
        $msg = '❌ No se pudo registrar la solicitud. Intentá de nuevo.';
      }
    }
  }
}

/* ============================================================
   Cargar para edición + Listado
   ============================================================ */
$edit = null;
if (!empty($_GET['edit'])) {
  $idEd = (int)$_GET['edit'];
  $q = $conexion->query("SELECT * FROM promociones WHERE id={$idEd} AND gimnasio_id={$gym_id}");
  $edit = $q? $q->fetch_assoc(): null;
}

$rs = $conexion->query("
  SELECT * 
  FROM promociones 
  WHERE gimnasio_id={$gym_id} 
  ORDER BY activo DESC, prioridad DESC, fecha_fin DESC, id DESC
");
$items = [];
if ($rs) { while($r=$rs->fetch_assoc()) $items[]=$r; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>📣 Promociones</title>
<style>
  :root{--bg:#000;--fg:gold;--card:#101114;--line:#262a33;--muted:#a0a7b4;}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--fg);font-family:Arial,Helvetica,sans-serif}
  .wrap{max-width:1100px;margin:0 auto;padding:16px}
  .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;margin:12px 0}
  input,textarea,select{width:100%;padding:10px;border-radius:8px;border:1px solid var(--line);background:#0d0f14;color:var(--fg)}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .btn{display:inline-block;padding:8px 12px;border-radius:8px;border:1px solid var(--line);background:#1a1f2b;color:#fff;text-decoration:none;cursor:pointer}
  .btn:hover{background:#21293a}
  .btn-copy{padding:4px 8px;margin-left:6px;background:#0a6;border:color:#0a6;border-radius:6px;border:0;color:#000;cursor:pointer}
  .btn-copy:hover{opacity:0.9}
  table{width:100%;border-collapse:collapse}
  th,td{border:1px solid var(--line);padding:8px;text-align:left}
  th{background:#141824}
  .muted{color:var(--muted);font-size:12px}
  .thumb{width:70px;height:40px;object-fit:cover;border-radius:6px;border:1px solid #333;background:#000}
  .swatch{display:flex;gap:6px;align-items:center;margin-top:6px;flex-wrap:wrap}
  .dot{width:18px;height:18px;border-radius:50%;border:1px solid #333;cursor:pointer}
  .cloud-badge{display:inline-block;padding:2px 6px;border-radius:8px;background:#044;border;color:#bff;font-size:12px;margin-left:6px}
  .cloud-off{display:inline-block;padding:2px 6px;border-radius:8px;background:#440;border;color:#fbb;font-size:12px;margin-left:6px}
  .request-form-inline{display:flex;gap:6px;align-items:center;flex-wrap:wrap}
  .request-form-inline input{width:auto;padding:8px}
  @media (max-width:900px){ .grid,.grid-3{grid-template-columns:1fr} .request-form-inline{flex-direction:column;align-items:stretch} }
</style>
</head>
<body>
<div class="wrap">
  <h1>📣 Promociones</h1>

  <!-- Cloudy status -->
  <div style="margin-bottom:8px">
    <?php if ($__cloud_ready): ?>
      <span class="cloud-badge">Cloudy activo: <?= h(defined('CLOUD_NAME')?CLOUD_NAME:'(sin nombre)') ?></span>
    <?php else: ?>
      <span class="cloud-off">Cloudy no disponible</span>
      <?php if (!empty($__cloud_err)): ?>
        <span class="muted" style="margin-left:8px">Error: <?= h($__cloud_err) ?></span>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if (!empty($msg)): ?>
    <div class="card"><?= $msg ?></div>
  <?php endif; ?>

  <div class="card">
    <h3 style="margin-top:0"><?= $edit ? 'Editar promoción' : 'Nueva promoción' ?></h3>
    <form method="POST" enctype="multipart/form-data">
      <input type="hidden" name="act" value="save">
      <input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">

      <div class="grid">
        <div>
          <label>Título</label>
          <input name="titulo" required value="<?= h($edit['titulo'] ?? '') ?>">
        </div>
        <div>
          <label>Prioridad (número)</label>
          <input name="prioridad" type="number" value="<?= h((string)($edit['prioridad'] ?? '0')) ?>">
        </div>
      </div>

      <div>
        <label>Descripción</label>
        <textarea name="descripcion"><?= h($edit['descripcion'] ?? '') ?></textarea>
      </div>

      <div class="grid">
        <div>
          <label>Imagen (archivo → sube a Cloudinary con tu bootstrap)</label>
          <input type="file" name="imagen_file" accept="image/*">
          <?php if (!empty($edit['imagen_url'])): ?>
            <div class="muted" style="margin-top:6px">Actual: <?= h($edit['imagen_url']) ?>
              <?php if (is_cloud_url($edit['imagen_url'])): ?>
                <span class="cloud-badge">Cloudy</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <label>Imagen (URL opcional)</label>
          <input name="imagen_url" value="<?= h($edit['imagen_url'] ?? '') ?>" placeholder="https://...">
        </div>
      </div>

      <div>
        <label>Link (opcional)</label>
        <input name="link_url" value="<?= h($edit['link_url'] ?? '') ?>" placeholder="https://...">
      </div>

      <div class="grid-3">
        <div>
          <label>Color fondo</label>
          <input name="color_fondo" type="color" value="<?= h($edit['color_fondo'] ?? '#111111') ?>">
        </div>
        <div>
          <label>Color texto</label>
          <input name="color_texto" type="color" value="<?= h($edit['color_texto'] ?? '#FFD700') ?>">
        </div>
        <div>
          <label style="display:block">Paletas rápidas</label>
          <div class="swatch">
            <span class="dot" style="background:#111" data-bg="#111111" data-fg="#FFD700" title="Oscuro/Dorado"></span>
            <span class="dot" style="background:#001f3f" data-bg="#001f3f" data-fg="#66b2ff" title="Azul/Claro"></span>
            <span class="dot" style="background:#660000" data-bg="#660000" data-fg="#ffcccc" title="Rojo"></span>
            <span class="dot" style="background:#004d26" data-bg="#004d26" data-fg="#d9f2e6" title="Verde"></span>
            <span class="dot" style="background:#1a1d23" data-bg="#1a1d23" data-fg="#f1f5f9" title="Gris/Blanco"></span>
          </div>
        </div>
      </div>

      <div class="grid">
        <div>
          <label>Desde</label>
          <input name="fecha_inicio" type="date" value="<?= h($edit['fecha_inicio'] ?? date('Y-m-d')) ?>">
        </div>
        <div>
          <label>Hasta</label>
          <input name="fecha_fin" type="date" value="<?= h($edit['fecha_fin'] ?? date('Y-m-d')) ?>">
        </div>
      </div>

      <label style="display:inline-block;margin-top:8px">
        <input type="checkbox" name="activo" <?= ((int)($edit['activo'] ?? 1)===1)?'checked':''; ?>> Activa
      </label>

      <div style="margin-top:10px">
        <button class="btn" type="submit">💾 Guardar</button>
        <?php if ($edit): ?>
          <a class="btn" href="promociones_admin.php">Nueva</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card">
    <h3 style="margin-top:0">Listado</h3>
    <div style="overflow:auto">
      <table>
        <thead>
          <tr>
            <th>#</th><th>Img</th><th>Título</th><th>Vigencia</th><th>Prioridad</th>
            <th>Colores</th><th>Estado</th><th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($items as $it): ?>
            <tr>
              <td><?= (int)$it['id'] ?></td>
              <td>
                <?php if (!empty($it['imagen_url'])): ?>
                  <img class="thumb" src="<?= h($it['imagen_url']) ?>" alt="thumb">
                  <?php if (is_cloud_url($it['imagen_url'])): ?>
                    <div style="margin-top:6px">
                      <span class="cloud-badge">Cloudy</span>
                      <button class="btn-copy" data-url="<?= h($it['imagen_url']) ?>">Copiar URL</button>
                    </div>
                  <?php else: ?>
                    <div style="margin-top:6px"><button class="btn-copy" data-url="<?= h($it['imagen_url']) ?>">Copiar URL</button></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-weight:bold"><?= h($it['titulo']) ?></div>
                <div class="muted" style="max-width:360px"><?= nl2br(h($it['descripcion'] ?? '')) ?></div>
                <?php if (!empty($it['link_url'])): ?>
                  <div class="muted">🔗 <a href="<?= h($it['link_url']) ?>" target="_blank" style="color:#66b2ff"><?= h($it['link_url']) ?></a></div>
                <?php endif; ?>
              </td>
              <td><?= h($it['fecha_inicio'] ?: '—') ?> → <?= h($it['fecha_fin'] ?: '—') ?></td>
              <td><?= (int)$it['prioridad'] ?></td>
              <td>
                <div class="swatch">
                  <span class="dot" style="background:<?= h($it['color_fondo'] ?: '#111') ?>;"></span>
                  <span class="dot" style="background:<?= h($it['color_texto'] ?: '#FFD700') ?>;"></span>
                </div>
                <div class="muted"><?= h($it['color_fondo'] ?: '#111') ?> / <?= h($it['color_texto'] ?: '#FFD700') ?></div>
              </td>
              <td><?= ((int)$it['activo']===1)?'✅ Activa':'⛔ Inactiva' ?></td>
              <td style="white-space:nowrap">
                <a class="btn" href="?edit=<?= (int)$it['id'] ?>">✏️ Editar</a>

                <form method="POST" style="display:inline" onsubmit="return confirm('¿Cambiar estado?');">
                  <input type="hidden" name="act" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="v" value="<?= ((int)$it['activo']===1)?0:1 ?>">
                  <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
                  <button class="btn" type="submit"><?= ((int)$it['activo']===1)?'Desactivar':'Activar' ?></button>
                </form>

                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar promoción?');">
                  <input type="hidden" name="act" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
                  <button class="btn" type="submit">🗑️</button>
                </form>

                <!-- FORMULARIO PÚBLICO: Quiero esta promoción -->
                <div style="margin-top:8px">
                  <?php if (empty($_SESSION['cliente_id'])): ?>
                    <form method="POST" class="request-form-inline" onsubmit="return confirm('Enviar solicitud?');">
                      <input type="hidden" name="act" value="request_promo">
                      <input type="hidden" name="promo_id" value="<?= (int)$it['id'] ?>">
                      <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
                      <input name="nombre_cliente" placeholder="Tu nombre" required>
                      <input name="email_cliente" placeholder="tu@correo.com" type="email" required>
                      <button class="btn" type="submit">Quiero esta promoción</button>
                    </form>
                  <?php else: ?>
                    <form method="POST" class="request-form-inline" onsubmit="return true;">
                      <input type="hidden" name="act" value="request_promo">
                      <input type="hidden" name="promo_id" value="<?= (int)$it['id'] ?>">
                      <input type="hidden" name="csrf" value="<?= h($csrf_token) ?>">
                      <button class="btn" type="submit">Quiero esta promoción</button>
                    </form>
                  <?php endif; ?>
                </div>

              </td>
            </tr>
          <?php endforeach; if (empty($items)): ?>
            <tr><td colspan="8" class="muted">Sin promociones cargadas.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
  // Paletas rápidas
  document.addEventListener('DOMContentLoaded', () => {
    const dots = document.querySelectorAll('.dot[data-bg]');
    const bgInp = document.querySelector('input[name="color_fondo"]');
    const fgInp = document.querySelector('input[name="color_texto"]');
    dots.forEach(d => {
      d.addEventListener('click', () => {
        if (!bgInp || !fgInp) return;
        bgInp.value = d.getAttribute('data-bg') || '#111111';
        fgInp.value = d.getAttribute('data-fg') || '#FFD700';
      });
    });

    // copiar URL
    document.querySelectorAll('.btn-copy').forEach(btn => {
      btn.addEventListener('click', () => {
        const url = btn.getAttribute('data-url') || '';
        if (!url) return alert('No hay URL para copiar');
        navigator.clipboard?.writeText(url).then(() => {
          const prev = btn.textContent;
          btn.textContent = 'Copiado ✓';
          setTimeout(()=> btn.textContent = prev, 1800);
        }).catch(()=> {
          prompt('Copiar manualmente (CTRL+C):', url);
        });
      });
    });
  });
</script>
</body>
</html>
