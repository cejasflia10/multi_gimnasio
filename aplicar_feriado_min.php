<?php
session_start();
require 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) { header("Location: login.php"); exit; }

function nombreDiaEs($fechaYmd) {
  $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  return $dias[(int)date('w', strtotime($fechaYmd))];
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $fecha       = $_POST['fecha'] ?? '';
  $hora_inicio = $_POST['hora_inicio'] ?? '';
  $hora_fin    = $_POST['hora_fin'] ?? '';

  // Validar fecha (YYYY-MM-DD)
  $dt = DateTime::createFromFormat('Y-m-d', $fecha);
  if (!$dt || $dt->format('Y-m-d') !== $fecha) {
    header("Location: turnos_profesor.php?err=Fecha%20inv%C3%A1lida"); exit;
  }

  // Validar horas
  if (!$hora_inicio || !$hora_fin || $hora_inicio >= $hora_fin) {
    header("Location: turnos_profesor.php?err=Horario%20inv%C3%A1lido"); exit;
  }

  $dia = nombreDiaEs($fecha);

  // Profes que normalmente trabajan ese día en este gimnasio
  $sql = "
    SELECT DISTINCT p.id AS profesor_id
    FROM profesores p
    JOIN turnos_profesor t ON t.profesor_id = p.id
    WHERE p.gimnasio_id = ? AND t.dia = ?
  ";
  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("is", $gimnasio_id, $dia);
  $stmt->execute();
  $res = $stmt->get_result();

  try {
    $conexion->begin_transaction();

    // Limpiar excepciones previas de ese día (re-aplicar)
    $del = $conexion->prepare("DELETE FROM turnos_profesor_excepciones WHERE gimnasio_id=? AND fecha=?");
    $del->bind_param("is", $gimnasio_id, $fecha);
    $del->execute();
    $del->close();

    // Insert preparado
    $ins = $conexion->prepare("
      INSERT INTO turnos_profesor_excepciones
        (gimnasio_id, profesor_id, fecha, cerrado, hora_inicio, hora_fin, motivo)
      VALUES (?,?,?,?,?,?, 'Feriado')
    ");

    $count = 0;
    while ($row = $res->fetch_assoc()) {
      $pid = (int)$row['profesor_id'];
      $cerrado = 0; // horario reducido (no cerrado)
      // tipos: i (gimnasio_id), i (profesor_id), s (fecha), i (cerrado), s (hora_inicio), s (hora_fin)
      $ins->bind_param("iisiss", $gimnasio_id, $pid, $fecha, $cerrado, $hora_inicio, $hora_fin);
      $ins->execute();
      $count++;
    }

    $ins->close();
    $stmt->close();

    $conexion->commit();

    header("Location: turnos_profesor.php?ok=Aplicado%20a%20${count}%20profesores%20para%20$fecha");
    exit;

  } catch (Throwable $e) {
    $conexion->rollback();
    $msg = urlencode("Error al aplicar feriado: ".$e->getMessage());
    header("Location: turnos_profesor.php?err=$msg"); exit;
  }
}

header("Location: turnos_profesor.php");
