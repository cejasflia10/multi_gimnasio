<?php
/* guardar_pedido_indumentaria.php (seña 80%) */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$csrf_sess   = (string)($_SESSION['csrf_token'] ?? '');
$cart        = $_SESSION['cart'] ?? [];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function must_prepare(mysqli $db, string $sql){ $st=$db->prepare($sql); if(!$st) die('❌ SQL error: '.$db->error.'<br><code>'.$sql.'</code>'); return $st; }

/* ========= Helpers de esquema ========= */
function col_exists(mysqli $db, string $table, string $column): bool {
  $st = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
  if(!$st) return false;
  $st->bind_param('s', $column);
  $st->execute();
  $res = $st->get_result();
  $ok  = (bool)$res->num_rows;
  $st->close();
  return $ok;
}
function ensure_schema(mysqli $db){
  // Crea tablas base si no existen
  $db->query("
    CREATE TABLE IF NOT EXISTS ind_pedidos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      gimnasio_id INT NOT NULL,
      cliente_id INT NOT NULL,
      total DECIMAL(12,2) NOT NULL DEFAULT 0,
      creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX (gimnasio_id),
      INDEX (cliente_id),
      INDEX (creado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  $db->query("
    CREATE TABLE IF NOT EXISTS ind_pedidos_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      pedido_id INT NOT NULL,
      producto_id INT NOT NULL,
      titulo VARCHAR(160) NOT NULL,
      talle VARCHAR(40) DEFAULT NULL,
      cantidad INT NOT NULL DEFAULT 1,
      precio DECIMAL(12,2) NOT NULL DEFAULT 0,
      guia_tipo VARCHAR(40) DEFAULT NULL,
      talle_calc VARCHAR(40) DEFAULT NULL,
      medidas_json TEXT DEFAULT NULL,
      FOREIGN KEY (pedido_id) REFERENCES ind_pedidos(id) ON DELETE CASCADE,
      INDEX (pedido_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Agrega columnas nuevas si faltan
  if (!col_exists($db,'ind_pedidos','sena_monto')) {
    $db->query("ALTER TABLE ind_pedidos ADD COLUMN sena_monto DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total");
  }
  if (!col_exists($db,'ind_pedidos','pago_tipo')) {
    $db->query("ALTER TABLE ind_pedidos ADD COLUMN pago_tipo VARCHAR(40) DEFAULT NULL AFTER sena_monto");
  }
  if (!col_exists($db,'ind_pedidos','estado')) {
    $db->query("ALTER TABLE ind_pedidos ADD COLUMN estado VARCHAR(30) DEFAULT 'pendiente' AFTER pago_tipo");
  }
  if (!col_exists($db,'ind_pedidos','comprobante_url')) {
    $db->query("ALTER TABLE ind_pedidos ADD COLUMN comprobante_url VARCHAR(255) DEFAULT NULL AFTER estado");
  }
}
ensure_schema($conexion);

/* ===== Cloud opcional (usa tu bootstrap si está) ===== */
if (!defined('CLOUD_ENABLED')) define('CLOUD_ENABLED', true);
if (!defined('CLOUD_FOLDER_ROOT')) define('CLOUD_FOLDER_ROOT','ROOT');
@include_once __DIR__.'/cloudy_boot_constants.php';
if (function_exists('cloud_init')) { try { cloud_init(); } catch(Throwable $e){} }

$__cloud_ready = (CLOUD_ENABLED===true) && (
  function_exists('cloud_upload') ||
  function_exists('cloudy_upload') ||
  class_exists('\\Cloudinary\\Api\\Upload\\UploadApi')
);

function subir_comprobante(?array $file, int $gimnasio_id, int $cliente_id, bool $cloud_ready): ?string {
  if (!$file || ($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
  if (($file['error']??0)!==UPLOAD_ERR_OK) return null;

  $mime = @mime_content_type($file['tmp_name']);
  $permit = [
    'image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
    'application/pdf'=>'pdf'
  ];
  if (!$mime || !isset($permit[$mime])) return null;
  if (($file['size']??0) > 15*1024*1024) return null;

  // 1) Cloud
  if ($cloud_ready) {
    $folder = rtrim(CLOUD_FOLDER_ROOT ?: 'ROOT','/').'/indumentaria/comprobantes/'.$gimnasio_id.'/'.$cliente_id;
    $basename = 'comp_'.date('Ymd_His').'_'.bin2hex(random_bytes(3));
    try {
      if (function_exists('cloud_upload')) {
        $r = cloud_upload($file['tmp_name'], ['folder'=>$folder,'public_id'=>$basename,'resource_type'=>'auto','invalidate'=>true]);
        if (is_array($r) && !empty($r['secure_url'])) return $r['secure_url'];
        if (is_string($r) && str_starts_with($r,'http')) return $r;
      }
      if (function_exists('cloudy_upload')) {
        $r = cloudy_upload($file['tmp_name'], ['folder'=>$folder,'public_id'=>$basename,'resource_type'=>'auto','invalidate'=>true]);
        if (is_array($r) && !empty($r['secure_url'])) return $r['secure_url'];
        if (is_string($r) && str_starts_with($r,'http')) return $r;
      }
      if (class_exists('\\Cloudinary\\Api\\Upload\\UploadApi')) {
        $u = new \Cloudinary\Api\Upload\UploadApi();
        $r = $u->upload($file['tmp_name'], ['folder'=>$folder,'public_id'=>$basename,'resource_type'=>'auto','invalidate'=>true]);
        if (!empty($r['secure_url'])) return $r['secure_url'];
      }
    } catch (Throwable $e) { /* fallback local */ }
  }

  // 2) Local
  $dir = __DIR__.'/uploads/comprobantes';
  if (!is_dir($dir)) @mkdir($dir,0777,true);
  $ext = $permit[$mime];
  $name = 'comp_'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'.'.$ext;
  $dest = $dir.'/'.$name;
  if (!move_uploaded_file($file['tmp_name'],$dest)) return null;
  return 'uploads/comprobantes/'.$name;
}

/* ===== Validaciones ===== */
if ($cliente_id<=0 || $gimnasio_id<=0) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($csrf_sess, $_POST['csrf'] ?? '')) { http_response_code(400); exit('CSRF'); }
if (empty($cart)) { header('Location: tienda_indumentaria.php?err=carrito'); exit; }

$pago = $_POST['pago'] ?? '';
if (!in_array($pago, ['sena_efectivo','total_efectivo','sena_transferencia','total_transferencia'], true)) {
  header('Location: tienda_indumentaria.php?err=pago'); exit;
}

/* ===== Totales ===== */
$total = 0.0;
foreach ($cart as $it) $total += ((float)$it['precio'] * (int)$it['cantidad']);

$es_total = str_starts_with($pago, 'total_');
$es_sena  = !$es_total;

/* *** SEÑA 80% *** */
$sena_monto = $es_sena ? round($total * 0.80, 2) : 0.00;

$comp_url = null;
if (str_contains($pago,'transferencia')) {
  $comp_url = subir_comprobante($_FILES['comprobante'] ?? null, $gimnasio_id, $cliente_id, $__cloud_ready);
}

/* ===== Guardar cabecera ===== */
$sql = "INSERT INTO ind_pedidos (gimnasio_id,cliente_id,total,sena_monto,pago_tipo,estado,comprobante_url)
        VALUES (?,?,?,?,?,'pendiente',?)";
$st = must_prepare($conexion,$sql);
$st->bind_param('iiddss',$gimnasio_id,$cliente_id,$total,$sena_monto,$pago,$comp_url);
$ok = $st->execute();
if (!$ok) { $err = $st->error; $st->close(); die('❌ INSERT ind_pedidos: '.$err); }
$pedido_id = (int)$conexion->insert_id;
$st->close();

/* ===== Guardar items ===== */
$sqlI = "INSERT INTO ind_pedidos_items
  (pedido_id,producto_id,titulo,talle,cantidad,precio,guia_tipo,talle_calc,medidas_json)
  VALUES (?,?,?,?,?,?,?,?,?)";
$sti = must_prepare($conexion,$sqlI);
foreach ($cart as $it) {
  $pid = (int)$it['producto_id'];
  $tit = (string)$it['titulo'];
  $talle = (string)$it['talle'];
  $cant = (int)$it['cantidad'];
  $precio = (float)$it['precio'];
  $guia = (string)($it['guia_tipo'] ?? '');
  $tcalc= (string)($it['talle_calc'] ?? '');
  $mjson= (string)($it['medidas_json'] ?? '');
  $sti->bind_param('iissidsss', $pedido_id,$pid,$tit,$talle,$cant,$precio,$guia,$tcalc,$mjson);
  $sti->execute();
}
$sti->close();

/* Vaciar carrito y redirigir a comprobante / factura imprimible */
$_SESSION['cart'] = [];
header('Location: factura_pedido.php?id='.$pedido_id);
exit;
