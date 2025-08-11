<?php
session_start();
require 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function nombreDiaEs($fechaYmd) {
  $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  $n = (int)date('w', strtotime($fechaYmd));
  return $dias[$n];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fecha       = $_POST['fecha'];           // ej: 2025-08-16
  $cerrado     = isset($_POST['cerrado']) ? 1 : 0;
  $hora_inicio = $_POST['hora_inicio'] ?: null;
  $hora_fin    = $_POST['hora_fin']    ?: null;

  if (!$cerrado && (!$hora_inicio || !$hora_fin || $hora_inicio >= $hora_fin)) {
    die("Horario inválido.");
  }

  $dia = nombreDiaEs($fecha);

  // Profes que normalmente trabajan ese día (en este gimnasio)
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

  $conexion->begin_transaction();

  // Opcional: limpiá lo que ya hubiera para esa fecha (para re-aplicar)
  $del = $conexion->prepare("DELETE FROM turnos_profesor_excepciones WHERE gimnasio_id=? AND fecha=?");
  $del->bind_param("is", $gimnasio_id, $fecha);
  $del->execute();

  $ins = $conexion->prepare("
    INSERT INTO turnos_profesor_excepciones
      (gimnasio_id, profesor_id, fecha, cerrado, hora_inicio, hora_fin, motivo)
    VALUES (?,?,?,?,?,?, 'Feriado')
  ");

  $count = 0;
  while ($row = $res->fetch_assoc()) {
    $pid = (int)$row['profesor_id'];
    $ins->bind_param("iiisss", $gimnasio_id, $pid, $fecha, $cerrado, $hora_inicio, $hora_fin);
    $ins->execute();
    $count++;
  }

  $conexion->commit();
  echo "Aplicado feriado a $count profesor(es) para $fecha.";
}
