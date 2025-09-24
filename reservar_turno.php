<?php
// reservar_turno.php — versión con cutoff: mañana hasta 23:59:59 del día anterior
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

const VIEW_FILE = 'ver_turnos_cliente.php'; // ajustá si tu vista se llama distinto
function volver(array $params = []) {
  $qs = http_build_query($params);
  header('Location: '.VIEW_FILE.($qs ? '?'.$qs : ''));
  exit;
}

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0 || $gimnasio_id<=0) { header('Location: login.php'); exit; }

// 1) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_log("[reservar_turno] Bloqueado NO-POST desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err'=>'Seleccioná un turno para reservar.']);
}

// 2) Debe venir el botón "reservar"
if (!isset($_POST['reservar'])) {
  error_log("[reservar_turno] Falta boton reservar (posible auto-llamada) desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err'=>'Acción inválida.']);
}

// 3) CSRF
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
  error_log("[reservar_turno] CSRF inválido desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err'=>'Acción no autorizada.']);
}

$turno_id = isset($_POST['turno_id']) && ctype_digit((string)$_POST['turno_id']) ? (int)$_POST['turno_id'] : 0;
$fecha_ui = $_POST['fecha'] ?? '';

if ($turno_id <= 0) volver(['err' => 'Turno inválido.']);
if (!$fecha_ui) volver(['err' => 'Fecha requerida.']);

$dt = DateTime::createFromFormat('Y-m-d', $fecha_ui);
if (!$dt || $dt->format('Y-m-d') !== $fecha_ui) volver(['err' => 'Fecha inválida.']);
$fecha_turno = $fecha_ui;

/* Traer turno base */
$stm = $conexion->prepare("SELECT id, profesor_id, dia, hora_inicio, hora_fin FROM turnos_disponibles WHERE id=? AND gimnasio_id=?");
if (!$stm) { volver(['fecha'=>$fecha_turno, 'err'=>'SQL: '.$conexion->error]); }
$stm->bind_param("ii", $turno_id, $gimnasio_id);
$stm->execute();
$turno = $stm->get_result()->fetch_assoc();
$stm->close();
if (!$turno) volver(['fecha'=>$fecha_turno, 'err' => 'Turno no encontrado.']);

$dia         = (string)$turno['dia'];
$hora_inicio = substr((string)$turno['hora_inicio'],0,8);
$hora_fin    = substr((string)$turno['hora_fin'],0,8);
$profesor_id = (int)$turno['profesor_id'];

/* ---------------------------
   REGLA DE CIERRES (cutoffs)
   ---------------------------
   - Mañana: cutoff = 23:59:59 del día anterior  (cambio solicitado)
   - Tarde : cutoff = 12:00:00 del mismo día
   - Noche : cutoff = 18:00:00 del mismo día
   --------------------------------- */

// Determinar periodo según hora de inicio
$h = intval(substr($hora_inicio,0,2)); // hora como entero 0..23

if ($h < 12) {
  // MAÑANA: cutoff = 23:59:59 del día anterior (para que puedas ver la gente la noche anterior)
  $cutoff_dt = new DateTime($fecha_turno . ' 23:59:59');
  $cutoff_dt->modify('-1 day');
  $cutoff_label = "23:59 (día anterior)";
} elseif ($h < 18) {
  // TARDE => cutoff = 12:00 del mismo día
  $cutoff_dt = new DateTime($fecha_turno . ' 12:00:00');
  $cutoff_label = "12:00 (mismo día)";
} else {
  // NOCHE => cutoff = 18:00 del mismo día
  $cutoff_dt = new DateTime($fecha_turno . ' 18:00:00');
  $cutoff_label = "18:00 (mismo día)";
}

// Comparar con ahora (zona horaria del servidor)
$now = new DateTime();

if ($now >= $cutoff_dt) {
  volver(['fecha'=>$fecha_turno, 'err'=>'El plazo de reserva cerró: '.$cutoff_label.'.']);
}

/* Lista blanca (si existe bandera pid=0 00:00–00:00) */
$flagLB = $conexion->prepare("
  SELECT 1 FROM turnos_permitidos_fecha
  WHERE gimnasio_id=? AND fecha=? AND profesor_id=0 AND hora_inicio='00:00:00' AND hora_fin='00:00:00' LIMIT 1
");
$flagLB->bind_param("is", $gimnasio_id, $fecha_turno);
$flagLB->execute();
$hayListaBlanca = (bool)$flagLB->get_result()->num_rows;
$flagLB->close();

if ($hayListaBlanca) {
  $qPerm = $conexion->prepare("
    SELECT 1 FROM turnos_permitidos_fecha
    WHERE gimnasio_id=? AND fecha=? AND profesor_id=? AND hora_inicio=? AND hora_fin=? LIMIT 1
  ");
  $qPerm->bind_param("isiss", $gimnasio_id, $fecha_turno, $profesor_id, $hora_inicio, $hora_fin);
  $qPerm->execute();
  $okPerm = (bool)$qPerm->get_result()->num_rows;
  $qPerm->close();
  if (!$okPerm) volver(['fecha'=>$fecha_turno, 'err'=>'Esta franja no está habilitada para esa fecha.']);
}

/* Excepciones (cierres) */
$qEx = $conexion->prepare("SELECT profesor_id, cerrado FROM turnos_profesor_excepciones WHERE gimnasio_id=? AND fecha=?");
$qEx->bind_param("is", $gimnasio_id, $fecha_turno);
$qEx->execute();
$exRows = $qEx->get_result()->fetch_all(MYSQLI_ASSOC);
$qEx->close();

$cerradoGlobal=false; $cerradosPorProfe=[];
foreach($exRows as $ex){
  $pid=(int)$ex['profesor_id']; $cerr=((int)$ex['cerrado']===1);
  if($pid===0 && $cerr) $cerradoGlobal=true;
  if($pid>0  && $cerr) $cerradosPorProfe[$pid]=true;
}
if ($cerradoGlobal) volver(['fecha'=>$fecha_turno,'err'=>'Día cerrado por feriado.']);
if (!empty($cerradosPorProfe[$profesor_id])) volver(['fecha'=>$fecha_turno,'err'=>'El profesor está cerrado por excepción.']);

/* Duplicados */
$qDup = $conexion->prepare("
  SELECT id FROM reservas_clientes
  WHERE cliente_id=? AND turno_id=? AND fecha_reserva=? AND gimnasio_id=? LIMIT 1
");
$qDup->bind_param("iisi", $cliente_id, $turno_id, $fecha_turno, $gimnasio_id);
$qDup->execute();
$dup = $qDup->get_result()->fetch_assoc();
$qDup->close();
if ($dup) volver(['fecha'=>$fecha_turno,'err'=>'Ya reservaste este turno en esa fecha.']);

/* Insertar reserva */
$ins = $conexion->prepare("
  INSERT INTO reservas_clientes
  (cliente_id, turno_id, profesor_id, dia_semana, hora_inicio, fecha_reserva, gimnasio_id)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
if(!$ins){ volver(['fecha'=>$fecha_turno,'err'=>'SQL(2): '.$conexion->error]); }
$ins->bind_param("iiisssi", $cliente_id, $turno_id, $profesor_id, $dia, $hora_inicio, $fecha_turno, $gimnasio_id);
$ok = $ins->execute();
$err = $ins->error;
$ins->close();

if (!$ok) {
  if (stripos($err, 'incorrect')!==false || stripos($err, 'enum')!==false) {
    volver(['fecha'=>$fecha_turno,'err'=>'El valor de "día" no está permitido por el ENUM (¿falta "Domingo"?).']);
  }
  volver(['fecha'=>$fecha_turno,'err'=>'No se pudo guardar la reserva.']);
}

/* (Opcional) lógica de membresía/deuda */
// ...

volver(['fecha'=>$fecha_turno,'ok'=>'Reserva confirmada']);
