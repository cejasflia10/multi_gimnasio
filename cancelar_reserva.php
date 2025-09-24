<?php
/* cancelar_reserva.php — cancelar reserva cliente (robusto)
   - Detecta tabla: reservas | reservas_clientes
   - Parámetros POST esperados:
       - csrf (token de sesión)
       - reserva_id  (opcional, preferible)
       - turno_id + fecha (opcional, respaldo)
   - Redirige a ver_turnos_clientes.php?fecha=YYYY-MM-DD&ok=... or &err=...
*/

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

const VIEW = 'ver_turnos_cliente.php'; // a dónde volvemos

function abs_view(string $qs = ''): string {
  $base = VIEW;
  return $base . ($qs !== '' ? ('?' . $qs) : '');
}
function q(string $s){ return rawurlencode($s); }

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  header('Location: '.abs_view(http_build_query(['err'=>'Sin conexión a BD'])));
  exit;
}

/* ---------- POST + CSRF ---------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: '.abs_view(http_build_query(['err'=>'Método no permitido'])));
  exit;
}
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
  header('Location: '.abs_view(http_build_query(['err'=>'Acción no autorizada'])));
  exit;
}

/* ---------- datos cliente + inputs ---------- */
$cliente_id = (int)($_SESSION['cliente_id'] ?? 0);
if ($cliente_id <= 0) {
  header('Location: '.abs_view(http_build_query(['err'=>'Debes iniciar sesión'])));
  exit;
}

$reserva_id = isset($_POST['reserva_id']) && ctype_digit((string)$_POST['reserva_id']) ? (int)$_POST['reserva_id'] : 0;
$turno_id   = isset($_POST['turno_id'])   && ctype_digit((string)$_POST['turno_id'])   ? (int)$_POST['turno_id']   : 0;
$fecha_ui   = trim($_POST['fecha'] ?? ''); // formato YYYY-MM-DD (opcional)

/* validar fecha para devolver a la vista */
$fecha_param = '';
if ($fecha_ui !== '') {
  $dt = DateTime::createFromFormat('Y-m-d', $fecha_ui);
  if ($dt && $dt->format('Y-m-d') === $fecha_ui) $fecha_param = $fecha_ui;
}

/* ---------- detectar tabla de reservas ---------- */
function table_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $res = $db->query("SHOW TABLES LIKE '$t'");
  return $res && $res->num_rows > 0;
}
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $res = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return $res && $res->num_rows > 0;
}

$tabla = null;
if (table_exists($conexion, 'reservas_clientes')) $tabla = 'reservas_clientes';
elseif (table_exists($conexion, 'reservas')) $tabla = 'reservas';
else {
  header('Location: '.abs_view(http_build_query(['fecha'=>$fecha_param,'err'=>'No existe tabla de reservas'])));
  exit;
}

/* columnas útiles */
$tiene_estado = col_exists($conexion, $tabla, 'estado');
$tiene_cancelado_en = col_exists($conexion, $tabla, 'cancelado_en');
$tiene_cliente = col_exists($conexion, $tabla, 'cliente_id');
$tiene_turno   = col_exists($conexion, $tabla, 'turno_id');
$tiene_fecha   = col_exists($conexion, $tabla, 'fecha_reserva') || col_exists($conexion, $tabla, 'fecha');

/* ---------- buscar reserva (seguridad) ---------- */
$res = null;
$err_msg = '';

if ($reserva_id > 0) {
  // preferimos buscar por id
  $sql = "SELECT * FROM `{$tabla}` WHERE id = ? LIMIT 1";
  $st = $conexion->prepare($sql);
  if (!$st) $err_msg = 'SQL prepare error: '.$conexion->error;
  else {
    $st->bind_param('i', $reserva_id);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close();
  }
} else {
  // respaldo: buscar por cliente+turno+fecha (si hay columnas)
  if ($turno_id <= 0 || $fecha_param === '' || !$tiene_turno || !$tiene_fecha) {
    $err_msg = 'Faltan datos (reserva_id o turno+fecha).';
  } else {
    // la columna de fecha puede llamarse fecha_reserva o fecha; buscamos ambas
    $fecha_col = col_exists($conexion, $tabla, 'fecha_reserva') ? 'fecha_reserva' : (col_exists($conexion, $tabla, 'fecha') ? 'fecha' : null);
    if (!$fecha_col) $err_msg = 'La tabla no tiene columna de fecha.';
    else {
      // construir consulta
      $sql = "SELECT * FROM `{$tabla}` WHERE turno_id = ? AND {$fecha_col} = ? AND " . ($tiene_cliente ? "cliente_id = ?" : "1=1") . " LIMIT 1";
      $st = $conexion->prepare($sql);
      if (!$st) $err_msg = 'SQL prepare error: '.$conexion->error;
      else {
        if ($tiene_cliente) $st->bind_param('isi', $turno_id, $fecha_param, $cliente_id);
        else $st->bind_param('is', $turno_id, $fecha_param);
        $st->execute();
        $res = $st->get_result()->fetch_assoc();
        $st->close();
      }
    }
  }
}

if (!$res) {
  $msg = $err_msg ?: 'No se encontró la reserva.';
  header('Location: '.abs_view(http_build_query(['fecha'=>$fecha_param,'err'=>$msg])));
  exit;
}

/* verificar que la reserva pertenezca al cliente (si existe la columna cliente_id) */
if ($tiene_cliente) {
  $res_cliente = (int)($res['cliente_id'] ?? 0);
  if ($res_cliente !== $cliente_id) {
    header('Location: '.abs_view(http_build_query(['fecha'=>$fecha_param,'err'=>'La reserva no te pertenece.'])));
    exit;
  }
}

/* ---------- realizar cancelación ---------- */
$ok = false;
$detalle_err = '';

$conexion->begin_transaction();
try {
  // si hay columna 'estado' hacemos soft-update
  if ($tiene_estado) {
    if ($tiene_cancelado_en) {
      $sql = "UPDATE `{$tabla}` SET estado = 'cancelada', cancelado_en = NOW() WHERE id = ? LIMIT 1";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('SQL prepare (update) error: '.$conexion->error);
      $id = (int)$res['id'];
      $st->bind_param('i', $id);
      $st->execute();
      if ($st->affected_rows <= 0) throw new Exception('No se pudo actualizar la reserva.');
      $st->close();
    } else {
      $sql = "UPDATE `{$tabla}` SET estado = 'cancelada' WHERE id = ? LIMIT 1";
      $st = $conexion->prepare($sql);
      if (!$st) throw new Exception('SQL prepare (update) error: '.$conexion->error);
      $id = (int)$res['id'];
      $st->bind_param('i', $id);
      $st->execute();
      if ($st->affected_rows <= 0) throw new Exception('No se pudo actualizar la reserva.');
      $st->close();
    }
    $ok = true;
  } else {
    // si no existe columna estado hacemos DELETE
    $sql = "DELETE FROM `{$tabla}` WHERE id = ? LIMIT 1";
    $st = $conexion->prepare($sql);
    if (!$st) throw new Exception('SQL prepare (delete) error: '.$conexion->error);
    $id = (int)$res['id'];
    $st->bind_param('i', $id);
    $st->execute();
    if ($st->affected_rows <= 0) throw new Exception('No se pudo eliminar la reserva.');
    $st->close();
    $ok = true;
  }

  $conexion->commit();

} catch (Throwable $e) {
  $conexion->rollback();
  $detalle_err = $e->getMessage();
  $ok = false;
}

/* ---------- resultado y redirección ---------- */
if ($ok) {
  header('Location: '.abs_view(http_build_query(['fecha'=>$fecha_param,'ok'=>'Reserva cancelada'])));
  exit;
} else {
  $msg = 'Error al cancelar reserva';
  if ($detalle_err) $msg .= ': '.$detalle_err;
  header('Location: '.abs_view(http_build_query(['fecha'=>$fecha_param,'err'=>$msg])));
  exit;
}
