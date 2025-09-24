<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

// ====== DEBUG ======
define('DEBUG_SAVE', isset($_GET['debug']) ? (bool)$_GET['debug'] : true);
function FAIL($msg, $http=500){ if(DEBUG_SAVE){ http_response_code($http); echo '❌ '.$msg; } else { http_response_code($http); echo '❌ Error guardando la reserva.'; } exit; }
function OK_REDIRECT($url){ header('Location: '.$url); exit; }
// ====================

if (!isset($conexion) || !($conexion instanceof mysqli)) FAIL('Sin conexión a BD', 500);
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

// --- Auth básica ---
$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id <= 0) { header('Location: login.php'); exit; }

// --- CSRF ---
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) FAIL('CSRF inválido', 400);

// --- Inputs ---
$evento_id  = (int)($_POST['evento_id'] ?? 0);
$cantidad   = (int)($_POST['cantidad'] ?? 0);
$nombres    = trim($_POST['nombres'] ?? '');
$comentario = trim($_POST['comentario'] ?? '');
$modo_pago  = $_POST['pago'] ?? 'pendiente'; // sena_efectivo | total_efectivo | sena_transferencia | total_transferencia | pendiente
if ($evento_id<=0 || $cantidad<=0) FAIL('Datos incompletos (evento/cantidad)', 400);

// --- Verificación columnas nuevas (evita prepare=false por columnas faltantes) ---
$need_cols = ['comprobante_url','comprobante_public_id'];
$cols_ok = true;
$schema = $conexion->query("SHOW COLUMNS FROM cenas_reservas");
if ($schema) {
  $tcols = [];
  while($r=$schema->fetch_assoc()){ $tcols[$r['Field']] = 1; }
  foreach($need_cols as $c){ if(!isset($tcols[$c])) $cols_ok=false; }
}
if(!$cols_ok){
  FAIL("Faltan columnas en 'cenas_reservas'. Ejecutá el ALTER para agregar: comprobante_url, comprobante_public_id", 500);
}

// --- Traer evento y cupo ---
$stmt = $conexion->prepare("SELECT id, precio_cubierto, sena_minima, cupo_total, cupo_reservado
                            FROM cenas_eventos WHERE id=? AND gimnasio_id=? AND estado='activo' LIMIT 1");
if(!$stmt) FAIL('SQL evento: '.$conexion->error, 500);
$stmt->bind_param('ii', $evento_id, $gimnasio_id);
$stmt->execute();
$e = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$e) FAIL('Evento no disponible', 404);

$cupo_disp = max(0, (int)$e['cupo_total'] - (int)$e['cupo_reservado']);
if ($cantidad > $cupo_disp) FAIL('No hay cupo suficiente', 409);

// --- Cálculos (10% OFF si >=4) ---
$precio_unit = (float)$e['precio_cubierto'];
$subtotal    = $precio_unit * $cantidad;
$descuento   = ($cantidad >= 4) ? $subtotal * 0.10 : 0.00;
$total       = round($subtotal - $descuento, 2);
$sena_unit   = (float)$e['sena_minima'];
$sena_total  = round($sena_unit * $cantidad, 2);

$estado_pago  = 'pendiente';
$monto_pagado = 0.00;
$metodo_pago  = null;

// --- CLOUDINARY (CONSTANTES) ---
require_once __DIR__.'/cloudy_boot_constants.php';
$CLOUDY = cloudy_constants_init(); // ['ok'=>bool,...]

// --- Archivo comprobante (si transferencia) ---
$comprobante_url = null;
$comprobante_pid = null;
$is_transfer = in_array($modo_pago, ['sena_transferencia','total_transferencia'], true);

if ($is_transfer && isset($_FILES['comprobante']) && is_array($_FILES['comprobante']) && $_FILES['comprobante']['error'] !== UPLOAD_ERR_NO_FILE) {
  // Validaciones de PHP ini
  $max_upload = ini_get('upload_max_filesize');
  $max_post   = ini_get('post_max_size');
  $f = $_FILES['comprobante'];
  if ($f['error'] !== UPLOAD_ERR_OK) FAIL('Archivo de comprobante: error #'.$f['error'], 400);
  if ($f['size']  > 6 * 1024 * 1024) FAIL('Comprobante demasiado grande (máx 6MB). Ajustá upload_max_filesize/post_max_size si hace falta', 400);

  if (!($CLOUDY['ok'] ?? false)) {
    if(DEBUG_SAVE) echo '⚠️ Cloudy no inicializado ('.(($CLOUDY['reason']??'desconocido')).") — se guarda sin comprobante<br>";
  } else {
    try {
      $folderRoot = defined('CLOUD_FOLDER_ROOT') ? CLOUD_FOLDER_ROOT : 'ROOT';
      $folder = rtrim($folderRoot, '/').'/cenas_comprobantes/gimnasio_'.$gimnasio_id.'/evento_'.$evento_id;

      $upload = \Cloudinary\Uploader::upload($f['tmp_name'], [
        'folder'          => $folder,
        'resource_type'   => 'auto',
        'use_filename'    => true,
        'unique_filename' => true,
        'overwrite'       => false,
      ]);
      $comprobante_url = $upload['secure_url'] ?? null;
      $comprobante_pid = $upload['public_id'] ?? null;
    } catch (\Throwable $eup) {
      if(DEBUG_SAVE) echo '⚠️ Cloudinary subida falló: '.$eup->getMessage().'<br>';
      // seguimos sin comprobante
    }
  }
}

// --- Estado/montos por modo ---
switch ($modo_pago) {
  case 'sena_efectivo':
    $estado_pago  = 'sena';
    $monto_pagado = $sena_total;
    $metodo_pago  = 'efectivo';
    break;
  case 'total_efectivo':
    $estado_pago  = 'pagado';
    $monto_pagado = $total;
    $metodo_pago  = 'efectivo';
    break;
  case 'sena_transferencia':
    $estado_pago  = 'sena';
    $monto_pagado = $sena_total;
    $metodo_pago  = 'transferencia';
    break;
  case 'total_transferencia':
    $estado_pago  = 'pagado';
    $monto_pagado = $total;
    $metodo_pago  = 'transferencia';
    break;
  default:
    $estado_pago  = 'pendiente';
    $monto_pagado = 0.00;
    $metodo_pago  = null;
}

// --- Guardado ---
$conexion->begin_transaction();
try {
  $sql = "INSERT INTO cenas_reservas
            (evento_id, gimnasio_id, cliente_id, cantidad, nombres_acomp, comentario,
             total, estado_reserva, estado_pago, monto_pagado, metodo_pago,
             comprobante_url, comprobante_public_id)
          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
  $stmt = $conexion->prepare($sql);
  if(!$stmt) FAIL('SQL INSERT reservas: '.$conexion->error, 500);

  $estado_reserva = 'reservado';
  // Tipos: i i i i s s d s s d s s s
  $stmt->bind_param(
    'iiiissdssdsss',
    $evento_id, $gimnasio_id, $cliente_id, $cantidad, $nombres, $comentario,
    $total, $estado_reserva, $estado_pago, $monto_pagado, $metodo_pago,
    $comprobante_url, $comprobante_pid
  );
  if(!$stmt->execute()) { $err=$stmt->error; $stmt->close(); throw new Exception('Execute INSERT: '.$err); }
  $reserva_id = $stmt->insert_id;
  $stmt->close();

  // Cupo
  $stmt = $conexion->prepare("UPDATE cenas_eventos SET cupo_reservado = cupo_reservado + ? WHERE id=?");
  if(!$stmt) throw new Exception('SQL UPDATE cupo: '.$conexion->error);
  $stmt->bind_param('ii', $cantidad, $evento_id);
  if(!$stmt->execute()) { $err=$stmt->error; $stmt->close(); throw new Exception('Execute UPDATE cupo: '.$err); }
  $stmt->close();

  $conexion->commit();

  OK_REDIRECT('mis_reservas_cena.php?ok=1&id='.$reserva_id);

} catch (Throwable $ex) {
  $conexion->rollback();
  FAIL('Transacción falló: '.$ex->getMessage(), 500);
}
