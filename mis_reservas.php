<?php
include 'conexion.php';
session_start();

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if (!$cliente_id) {
    die("Acceso no autorizado.");
}

// Obtener reservas del cliente
$query = "
SELECT r.id, r.fecha, h.hora_inicio, h.hora_fin, d.nombre AS dia, p.apellido AS profesor
FROM reservas r
JOIN turnos t ON r.turno_id = t.id
JOIN dias d ON t.id_dia = d.id
JOIN horarios h ON t.id_horario = h.id
JOIN profesores p ON t.id_profesor = p.id
WHERE r.cliente_id = $cliente_id
ORDER BY r.fecha DESC
";
$reservas = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mis Reservas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
<h2>Mis Reservas</h2>

<table>
  <thead>
    <tr>
      <th>Fecha</th>
      <th>Día</th>
      <th>Horario</th>
      <th>Profesor</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($r = $reservas->fetch_assoc()) { ?>
      <tr>
        <td><?= $r['fecha'] ?></td>
        <td><?= $r['dia'] ?></td>
        <td><?= substr($r['hora_inicio'], 0, 5) . " - " . substr($r['hora_fin'], 0, 5) ?></td>
        <td><?= $r['profesor'] ?></td>
        <td class="acciones">
          <form method="POST" action="editar_reserva.php">
            <input type="hidden" name="turno_id" value="<?= $r['id'] ?>">
            <button class="editar">Editar</button>
          </form>
          <form method="POST" action="cancelar_reserva.php" onsubmit="return confirm('¿Cancelar esta reserva?')">
            <input type="hidden" name="turno_id" value="<?= $r['id'] ?>">
            <button class="cancelar">Cancelar</button>
          </form>
        </td>
      </tr>
    <?php } ?>
  </tbody>
</table>
</div>
</body>
</html>
