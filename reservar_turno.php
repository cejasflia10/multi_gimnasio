<?php
include 'conexion.php';
session_start();

$cliente_id = $_SESSION['cliente_id'] ?? 0; // si hay login de cliente
$hoy = date('Y-m-d');

// Obtener días
$dias = $conexion->query("SELECT * FROM dias");

// Obtener turnos por día seleccionado
$dia_seleccionado = $_GET['dia'] ?? 1;

$query = "
SELECT t.id, d.nombre AS dia, h.hora_inicio, h.hora_fin, p.apellido AS profesor
FROM turnos t
JOIN dias d ON t.id_dia = d.id
JOIN horarios h ON t.id_horario = h.id
JOIN profesores p ON t.id_profesor = p.id
WHERE t.id_dia = $dia_seleccionado
";
$turnos = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reservar Turno</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      text-align: center;
      color: gold;
    }
    select, button {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      background-color: #222;
      color: gold;
      border: 1px solid gold;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
    }
    th, td {
      padding: 10px;
      border: 1px solid gold;
    }
    tr:nth-child(even) {
      background-color: #1a1a1a;
    }
    button.reservar {
      background-color: gold;
      color: black;
      font-weight: bold;
      cursor: pointer;
    }
  </style>
</head>
<body>

<h2>Reservar Turno</h2>

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
    <th>Acción</th>
  </tr>
  <?php while ($t = $turnos->fetch_assoc()) { ?>
    <tr>
      <td><?= $t['hora_inicio'] . ' - ' . $t['hora_fin'] ?></td>
      <td><?= $t['profesor'] ?></td>
      <td>
        <form method="POST" action="reservar_turno.php">
          <input type="hidden" name="turno_id" value="<?= $t['id'] ?>">
          <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
          <button type="submit" class="reservar">Reservar</button>
        </form>
      </td>
    </tr>
  <?php } ?>
</table>

</body>
</html>
