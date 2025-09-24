<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
@date_default_timezone_set('America/Argentina/San_Luis'); // o Buenos_Aires si preferís

/* NO toco tu vista: uso el nombre que vos tenés */
const VIEW_FILE = 'ver_turnos_clientes.php';

/* Redirección helper */
function volver(array $params = []){
  $qs = http_build_query($params);
  header('Location: '.VIEW_FILE.($qs ? '?'.$qs : ''));
  exit;
}

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0 || $gimnasio_id<=0) { header('Location: login.php'); exit; }

/* Seguridad básica */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') volver(['err'=>'Seleccioná un turno para reservar.']);
if (!isset($_POST['reservar']))          volver(['err'=>'Acción inválida.']);
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf']))
  volver(['err'=>'Acción no autorizada.']);

$turno_id = isset($_POST['turno_id']) && ctype_digit((string)$_POST['turno_id']) ? (int)$_POST['turno_id'] : 0;
$fecha_ui = trim($_POST['fecha'] ?? '');
if ($turno_id<=0) volver(['err'=>'Turno inválido.']);

$dt = DateTime::createFromFormat('Y-m-d', $fecha_ui);
if (!$dt || $dt->format('Y-m-d') !== $fecha_ui) volver(['err'=>'Fecha inválida.']);
$fecha_turno = $fecha_ui;

/* Traer turno base */
$stm = $conexion->prepare("SELECT id, profesor_id, dia, hora_inicio, hora_fin FROM turnos_disponibles WHERE id=? AND gimnasio_id=?");
if(!$stm){ volver(['fecha'=>$fecha_turno,'err'=>'SQL(1): '.$conexion->error]); }
$stm->bind_param('ii', $turno_id, $gimnasio_id);
$stm->execute();
$turno = $stm->get_result()->fetch_assoc();
$stm->close();
if (!$turno) volver(['fecha'=>$fecha_turno,'err'=>'Turno no encontrado.']);

$dia         = (string)$turno['dia'];
$hora_inicio = substr((string)$turno['hora_inicio'],0,8);
$hora_fin    = substr((string)$turno['hora_fin'],0,8);
$profesor_id = (int)$turno['profesor_id'];

/* =======================
   BLOQUEOS DE RESERVA
   - Mañana  (<12:00):   hasta las 00:00 del día ANTERIOR
   - Tarde   (12–18:59): hasta las 12:00 del MISMO día
   - Noche   (>=19:00):  hasta las 18:00 del MISMO día
   ======================= */
function corte_reserva_datetime(string $fecha, string $hora_inicio): string {
  $h = (int)substr($hora_inicio,0,2);
  if ($h < 12) {
    // mañana: 00:00 del día anterior
    return date('Y-m-d 00:00:00', strtotime($fecha.' -1 day'));
  } elseif ($h < 19) {
    // tarde: 12:00 del mismo día
    return $fecha.' 12:00:00';
  } else {
    // noche: 18:00 del mismo día
    return $fecha.' 18:00:00';
  }
}

$corte = corte_reserva_datetime($fecha_turno, $hora_inicio);
$ahora = date('Y-m-d H:i:s');
if (!($ahora < $corte)) {
  $h = (int)substr($hora_inicio,0,2);
  $detalle = ($h < 12) ? '00:00 (día anterior)' : (($h < 19) ? '12:00 (mismo día)' : '18:00 (mismo día)');
  volver(['fecha'=>$fecha_turno,'err'=>"El plazo de reserva cerró: {$detalle}."]);
}

/* Lista blanca (opcional) */
$flagLB = $conexion->prepare("
  SELECT 1 FROM turnos_permitidos_fecha
  WHERE gimnasio_id=? AND fecha=? AND profesor_id=0
    AND hora_inicio='00:00:00' AND hora_fin='00:00:00'
  LIMIT 1
");
$flagLB->bind_param('is', $gimnasio_id, $fecha_turno);
$flagLB->execute();
$hayListaBlanca = (bool)$flagLB->get_result()->num_rows;
$flagLB->close();

if ($hayListaBlanca) {
  $qPerm = $conexion->prepare("
    SELECT 1 FROM turnos_permitidos_fecha
    WHERE gimnasio_id=? AND fecha=? AND profesor_id=? AND hora_inicio=? AND hora_fin=? LIMIT 1
  ");
  $qPerm->bind_param('isiss', $gimnasio_id, $fecha_turno, $profesor_id, $hora_inicio, $hora_fin);
  $qPerm->execute();
  $okPerm = (bool)$qPerm->get_result()->num_rows;
  $qPerm->close();
  if (!$okPerm) volver(['fecha'=>$fecha_turno,'err'=>'Esta franja no está habilitada para esa fecha.']);
}

/* Excepciones (cierres) */
$qEx = $conexion->prepare("SELECT profesor_id, cerrado FROM turnos_profesor_excepciones WHERE gimnasio_id=? AND fecha=?");
$qEx->bind_param('is', $gimnasio_id, $fecha_turno);
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
$qDup->bind_param('iisi', $cliente_id, $turno_id, $fecha_turno, $gimnasio_id);
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
$ins->bind_param('iiisssi', $cliente_id, $turno_id, $profesor_id, $dia, $hora_inicio, $fecha_turno, $gimnasio_id);
$ok = $ins->execute();
$err = $ins->error;
$ins->close();

if (!$ok) {
  if (stripos($err, 'incorrect')!==false || stripos($err, 'enum')!==false) {
    volver(['fecha'=>$fecha_turno,'err'=>'El valor de "día" no está permitido por el ENUM de dia_semana.']);
  }
  volver(['fecha'=>$fecha_turno,'err'=>'No se pudo guardar la reserva.']);
}

volver(['fecha'=>$fecha_turno,'ok'=>'Reserva confirmada']);
