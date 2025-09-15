<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

function volver($params = []) {
  $qs = http_build_query($params);
  header("Location: ver_turnos_clientes.php".($qs ? "?$qs" : ""));
  exit;
}

// 🔒 1) Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  error_log("[reservar_turno] Bloqueado NO-POST desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err' => 'Seleccioná un turno para reservar.']);
}

// 🔒 2) Debe venir el botón "reservar"
if (!isset($_POST['reservar'])) {
  error_log("[reservar_turno] Falta boton reservar (posible auto-llamada) desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err' => 'Acción inválida.']);
}

// 🔒 3) CSRF token
if (empty($_SESSION['csrf_token']) || empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf'])) {
  error_log("[reservar_turno] CSRF inválido desde IP ".$_SERVER['REMOTE_ADDR']);
  volver(['err' => 'Acción no autorizada.']);
}

$turno_id = isset($_POST['turno_id']) && ctype_digit((string)$_POST['turno_id']) ? (int)$_POST['turno_id'] : 0;
$fecha_ui = $_POST['fecha'] ?? '';

if ($turno_id <= 0) volver(['err' => 'Turno inválido.']);
if (!$fecha_ui) volver(['err' => 'Fecha requerida.']);

$dt = DateTime::createFromFormat('Y-m-d', $fecha_ui);
if (!$dt || $dt->format('Y-m-d') !== $fecha_ui) volver(['err' => 'Fecha inválida.']);
$fecha_turno = $fecha_ui;

/* Traer turno base */
$stm = $conexion->prepare("SELECT * FROM turnos_disponibles WHERE id=? AND gimnasio_id=?");
$stm->bind_param("ii", $turno_id, $gimnasio_id);
$stm->execute();
$turno = $stm->get_result()->fetch_assoc();
$stm->close();
if (!$turno) volver(['err' => 'Turno no encontrado.', 'fecha'=>$fecha_turno]);

$dia         = $turno['dia'];
$hora_inicio = substr($turno['hora_inicio'],0,8);
$hora_fin    = substr($turno['hora_fin'],0,8);
$profesor_id = (int)$turno['profesor_id'];

/* Lista blanca (bandera pid=0 00:00–00:00) */
$flagLB = $conexion->prepare("
  SELECT 1 FROM turnos_permitidos_fecha
  WHERE gimnasio_id=? AND fecha=? AND profesor_id=0
    AND hora_inicio='00:00:00' AND hora_fin='00:00:00'
  LIMIT 1
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

/* Excepciones simples: solo cierres bloquean */
$qEx = $conexion->prepare("
  SELECT profesor_id, cerrado
  FROM turnos_profesor_excepciones
  WHERE gimnasio_id=? AND fecha=?
");
$qEx->bind_param("is", $gimnasio_id, $fecha_turno);
$qEx->execute();
$exRows = $qEx->get_result()->fetch_all(MYSQLI_ASSOC);
$qEx->close();

$cerradoGlobal = false; $cerradosPorProfe = [];
foreach ($exRows as $ex) {
  $pid  = (int)$ex['profesor_id'];
  $cerr = ((int)$ex['cerrado'] === 1);
  if ($pid === 0 && $cerr) $cerradoGlobal = true;
  if ($pid > 0  && $cerr) $cerradosPorProfe[$pid] = true;
}
if ($cerradoGlobal) volver(['fecha'=>$fecha_turno, 'err'=>'Día cerrado por feriado.']);
if (!empty($cerradosPorProfe[$profesor_id])) volver(['fecha'=>$fecha_turno, 'err'=>'El profesor está cerrado por excepción.']);

/* Duplicados */
$qDup = $conexion->prepare("
  SELECT id FROM reservas_clientes
  WHERE cliente_id=? AND turno_id=? AND fecha_reserva=? AND gimnasio_id=? LIMIT 1
");
$qDup->bind_param("iisi", $cliente_id, $turno_id, $fecha_turno, $gimnasio_id);
$qDup->execute();
$dup = $qDup->get_result()->fetch_assoc();
$qDup->close();
if ($dup) volver(['fecha'=>$fecha_turno, 'err'=>'Ya reservaste este turno en esa fecha.']);

/* Insertar reserva */
$ins = $conexion->prepare("
  INSERT INTO reservas_clientes
  (cliente_id, turno_id, profesor_id, dia_semana, hora_inicio, fecha_reserva, gimnasio_id)
  VALUES (?, ?, ?, ?, ?, ?, ?)
");
$ins->bind_param("iiisssi", $cliente_id, $turno_id, $profesor_id, $dia, $hora_inicio, $fecha_turno, $gimnasio_id);
$ins->execute();
$ins->close();

/* Deuda si no tiene clases */
$membresia = $conexion->query("
  SELECT * FROM membresias 
  WHERE cliente_id={$cliente_id} AND fecha_vencimiento>=CURDATE() AND gimnasio_id={$gimnasio_id}
  ORDER BY fecha_inicio DESC LIMIT 1
")->fetch_assoc();

$hoy = date('Y-m-d');
$monto_deuda_por_clase = -1000; // ajustar valor real
if ($membresia) {
  if ((int)$membresia['clases_disponibles'] <= 0) {
    $conexion->query("
      INSERT INTO pagos (cliente_id, metodo_pago, monto, fecha, fecha_pago, gimnasio_id)
      VALUES ({$cliente_id}, 'Cuenta Corriente', {$monto_deuda_por_clase}, '{$hoy}', '{$hoy}', {$gimnasio_id})
    ");
    $_SESSION['aviso_deuda'] = true;
  }
} else {
  $conexion->query("
    INSERT INTO pagos (cliente_id, metodo_pago, monto, fecha, fecha_pago, gimnasio_id)
    VALUES ({$cliente_id}, 'Cuenta Corriente', {$monto_deuda_por_clase}, '{$hoy}', '{$hoy}', {$gimnasio_id})
  ");
  $_SESSION['aviso_deuda'] = true;
}

volver(['ok'=>'Reserva registrada','fecha'=>$fecha_turno]);
