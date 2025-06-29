<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['cliente_id'])) {
    die("Acceso denegado.");
}

$cliente_id = $_SESSION['cliente_id'];
$dia_seleccionado = $_GET['dia'] ?? date('N'); // Día de la semana actual (1 = lunes)
$hoy = date('Y-m-d');

// Validar membresía activa
$membresia = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE() AND clases_disponibles > 0 ORDER BY id DESC LIMIT 1");
if ($membresia->num_rows === 0) {
    die("<p style='color: red;'>No tenés una membresía activa o sin clases disponibles.</p>");
}

// Obtener días
$dias = $conexion->query("SELECT * FROM dias");

// Calcular fecha del día seleccionado
$fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_seleccionado - 1) . " days"));

// Obtener turnos disponibles
$turnos = $conexion->query("
SELECT t.id, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupos_maximos,
  (SELECT COUNT(*) FROM reservas r WHERE r.turno_id = t.id AND r.fecha = '$fecha_reserva') AS cupos_ocupados
FROM turnos t
JOIN horarios h ON t.id_horario = h.id
JOIN profesores p ON t.id_profesor = p.id
WHERE t.id_dia = $dia_seleccionado
ORDER BY h.hora_inicio");

// Verificar si ya tiene reserva ese día
$reserva_actual = $conexion->query("SELECT * FROM reservas WHERE cliente_id = $cliente_id AND fecha = '$fecha_reserva'")->fetch_assoc();
$reservado_turno_id = $reserva_actual['turno_id'] ?? null;
?>
<!-- MODIFICADO OK: cliente_reservas.php -->


<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mis Turnos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
    h2 { text-align: center; }
    select, button { width: 100%; padding: 10px; margin-top: 10px; background-color: #222; color: gold; border: 1px solid gold; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border: 1px solid gold; text-align: center; }
    .reservar { background: gold; color: black; font-weight: bold; cursor: pointer; }
    .disabled { background: #555; color: #999; cursor: not-allowed; }
  </style>
</head>
<body>
<h2>📆 Turnos disponibles</h2>
<form method="GET">
  <label>Seleccioná un día:</label>
  <select name="dia" onchange="this.form.submit()">
    <?php while ($d = $dias->fetch_assoc()) { ?>
      <option value="<?= $d['id'] ?>" <?= $dia_seleccionado == $d['id'] ? 'selected' : '' ?>>
        <?= $d['nombre'] ?>
      </option>
    <?php } ?>
  </select>
</form>

<table>
  <tr>
    <th>Horario</th>
    <th>Profesor</th>
    <th>Cupos</th>
    <th>Acción</th>
  </tr>
  <?php while ($t = $turnos->fetch_assoc()) { 
    $ocupados = (int)$t['cupos_ocupados'];
    $disponibles = $t['cupos_maximos'] - $ocupados;
  ?>
    <tr>
      <td><?= $t['hora_inicio'] ?> - <?= $t['hora_fin'] ?></td>
      <td><?= $t['profesor'] ?></td>
      <td><?= $disponibles ?> / <?= $t['cupos_maximos'] ?></td>
      <td>
        <?php if ($reservado_turno_id == $t['id']) { ?>
          <form action="cancelar_reserva.php" method="POST" onsubmit="return confirm('Cancelar turno?')">
            <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
            <input type="hidden" name="fecha" value="<?= $fecha_reserva ?>">
            <button class="reservar" style="background:red;color:white">Cancelar</button>
          </form>
        <?php } elseif ($reservado_turno_id) { ?>
          <button class="reservar disabled" disabled>Ya tenés un turno</button>
        <?php } elseif ($disponibles <= 0) { ?>
          <button class="reservar disabled" disabled>Sin cupos</button>
        <?php } else { ?>
          <form action="cliente_reservas.php" method="GET">
            <input type="hidden" name="id_turno" value="<?= $t['id'] ?>">
            <button class="reservar">Reservar</button>
          </form>
        <?php } ?>
      </td>
    </tr>
  <?php } ?>
</table>
</body>
</html>
