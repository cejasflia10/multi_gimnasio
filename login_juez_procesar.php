<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['__JUEZ_MODE__'] = 1; // BYPASS guard organizador

require_once __DIR__ . '/conexion.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function clean_dni($s){ return preg_replace('/\D+/', '', (string)$s); }

$dni = clean_dni($_POST['dni'] ?? '');
if ($dni === '' || strlen($dni) < 6) {
  header('Location: login_juez.php?err='.urlencode('DNI inválido.'));
  exit;
}

$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);

// Detectar si existe columna evento_id
$has_evento = false;
if ($res = $conexion->query("SHOW COLUMNS FROM `jueces_evento` LIKE 'evento_id'")) {
  $has_evento = (bool)$res->num_rows;
  $res->close();
}

if ($has_evento && $evento_id > 0) {
  $sql = "SELECT id, nombre, apellido, evento_id 
          FROM jueces_evento 
          WHERE dni = ? AND evento_id = ?
          ORDER BY id DESC 
          LIMIT 1";
  $st = $conexion->prepare($sql);
  if (!$st) { header('Location: login_juez.php?err='.urlencode('Error interno.')); exit; }
  $st->bind_param('si', $dni, $evento_id);
} else {
  $sql = "SELECT id, nombre, apellido, ".($has_evento?'evento_id':'0')." AS evento_id
          FROM jueces_evento 
          WHERE dni = ?
          ORDER BY ".($has_evento?'COALESCE(created_at, NOW()) DESC, ':'')." id DESC
          LIMIT 1";
  $st = $conexion->prepare($sql);
  if (!$st) { header('Location: login_juez.php?err='.urlencode('Error interno.')); exit; }
  $st->bind_param('s', $dni);
}

$st->execute();
$res = $st->get_result();
$row = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$row) {
  header('Location: login_juez.php?err='.urlencode('No se encontró un juez con ese DNI'.($evento_id>0?' en este evento.':'.')));
  exit;
}

// Sesión del juez
$_SESSION['juez_id']       = (int)$row['id'];
$_SESSION['juez_nombre']   = (string)$row['nombre'];
$_SESSION['juez_apellido'] = (string)$row['apellido'];

// Si no había evento en sesión pero la tabla lo tiene, sincronizamos
if ($evento_id <= 0 && $has_evento) {
  $_SESSION['evento_id_actual'] = (int)$row['evento_id'];
}

// Ir directo a la TARJETA (o cambialo a panel_juez.php si preferís)
header('Location: tarjeta_juez.php');
exit;
