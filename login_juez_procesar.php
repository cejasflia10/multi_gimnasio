<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { 
  header('Location: login_juez.php?err='.urlencode('Sin conexión a BD')); 
  exit; 
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '$t'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

$dni = isset($_POST['dni']) ? preg_replace('/\D+/', '', (string)$_POST['dni']) : '';
if ($dni === '') {
  header('Location: login_juez.php?err='.urlencode('Ingresá un DNI válido.'));
  exit;
}

if (!table_exists($conexion,'jueces_evento')) {
  header('Location: login_juez.php?err='.urlencode('No existe la tabla jueces_evento.'));
  exit;
}

$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
$tiene_evt = has_col($conexion,'jueces_evento','evento_id');

$juez_id = 0;
if ($tiene_evt && $evento_id > 0) {
  // Buscar por DNI dentro del evento
  $sql = "SELECT id FROM `jueces_evento` WHERE dni=? AND evento_id=? ORDER BY id DESC LIMIT 1";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('si', $dni, $evento_id);
    $st->execute(); $st->bind_result($jid);
    if ($st->fetch()) { $juez_id = (int)$jid; }
    $st->close();
  }
} 

// Fallback: buscar por DNI sin evento
if ($juez_id <= 0) {
  $sql = "SELECT id FROM `jueces_evento` WHERE dni=? ORDER BY id DESC LIMIT 1";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('s', $dni);
    $st->execute(); $st->bind_result($jid);
    if ($st->fetch()) { $juez_id = (int)$jid; }
    $st->close();
  }
}

if ($juez_id <= 0) {
  $msg = ($tiene_evt && $evento_id>0)
    ? "No se encontró un juez con ese DNI para el evento #$evento_id."
    : "No se encontró un juez con ese DNI.";
  header('Location: login_juez.php?err='.urlencode($msg));
  exit;
}

// ✅ No guardamos juez en sesión: redirigimos con ?juez_id=...
$qs = 'juez_id='.$juez_id;
if ($evento_id > 0) { $qs .= '&evento_id='.$evento_id; }

header('Location: tarjeta_juez.php?'.$qs);
exit;
