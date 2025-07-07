<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    die("Acceso denegado.");
}

$mes = $_GET['mes'] ?? date('Y-m');
$mes_inicio = $mes . '-01';

$sql = "SELECT a.id, p.nombre, p.apellido, a.profesor_id, a.fecha, a.hora_ingreso,
               IFNULL(a.hora_egreso, ADDTIME(a.hora_ingreso, '01:00:00')) AS hora_egreso,
               a.alumnos, a.pago
        FROM asistencias_profesor a
        JOIN profesores p ON a.profesor_id = p.id
        WHERE a.gimnasio_id = ? AND DATE_FORMAT(a.fecha, '%Y-%m') = ?
        ORDER BY p.apellido, a.fecha, a.hora_ingreso";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("is", $gimnasio_id, $mes);
$stmt->execute();
$result = $stmt->get_result();

$datos = [];

while ($row = $result->fetch_assoc()) {
    $profesor_id = $row['profesor_id'];
    $fecha = $row['fecha'];
    $hora_ini = $row['hora_ingreso'];
    $hora_fin = $row['hora_egreso'];

    $qr = $conexion->prepare("
        SELECT COUNT(*) AS total
        FROM asistencias_clientes ac
        JOIN reservas r ON ac.cliente_id = r.cliente_id
        JOIN turnos t ON r.turno_id = t.id
        WHERE ac.fecha = ? AND t.id_profesor = ? 
        AND t.horario_inicio BETWEEN ? AND ? 
        AND r.fecha = ac.fecha AND t.gimnasio_id = ?
    ");
    $qr->bind_param("sisii", $fecha, $profesor_id, $hora_ini, $hora_fin, $gimnasio_id);
    $qr->execute();
    $qr_result = $qr->get_result()->fetch_assoc();
    $alumnos = $qr_result['total'] ?? 0;

    $pago = 0;
    if ($alumnos >= 10) $pago = 1600;
    elseif ($alumnos >= 5) $pago = 1200;
    elseif ($alumnos >= 2) $pago = 800;

// Si ya tiene valores manuales, no recalcular
$row['alumnos'] = ($row['alumnos'] ?? 0) > 0 ? $row['alumnos'] : $alumnos;
$row['pago'] = ($row['pago'] ?? 0) > 0 ? $row['pago'] : $pago;

    $datos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago a Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; }
        th { background: #222; color: gold; }
        tr:nth-child(even) { background: #111; }
        input[type="month"], input[type="submit"] {
            padding: 6px 10px; font-size: 14px; background: gold; color: black; border: none; margin-top: 10px;
        }
        .boton {
            padding: 5px 10px; background: gold; color: black;
            border: none; border-radius: 4px; cursor: pointer;
            text-decoration: none;
        }
        .boton:hover { background: orange; }
        .contenedor { padding: 10px; }
    </style>
</head>
<body>
<div class="contenedor">
  <h2>💵 Pago por Horas Trabajadas - <?= date("F Y", strtotime($mes_inicio)) ?></h2>
  <form method="get">
      <label>Seleccionar mes:</label>
      <input type="month" name="mes" value="<?= $mes ?>">
      <input type="submit" value="Filtrar">
  </form>

  <table>
    <thead>
      <tr>
        <th>Apellido</th>
        <th>Nombre</th>
        <th>Fecha</th>
        <th>Ingreso</th>
        <th>Egreso</th>
        <th>Alumnos</th>
        <th>Pago</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $total_general = 0;
      foreach ($datos as $fila):
          $total_general += $fila['pago'];
      ?>
      <tr>
        <td><?= $fila['apellido'] ?></td>
        <td><?= $fila['nombre'] ?></td>
        <td><?= $fila['fecha'] ?></td>
        <td><?= $fila['hora_ingreso'] ?></td>
        <td><?= $fila['hora_egreso'] ?></td>
        <td><?= $fila['alumnos'] ?></td>
        <td>$<?= number_format($fila['pago'], 0) ?></td>
        <td>
          <a href="editar_pago.php?id=<?= $fila['id'] ?>" class="boton">Editar</a>
          <a href="eliminar_pago.php?id=<?= $fila['id'] ?>" class="boton" onclick="return confirm('¿Eliminar este pago?')">Eliminar</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="6" style="text-align:right;"><strong>Total a pagar:</strong></td>
        <td colspan="2"><strong>$<?= number_format($total_general, 0) ?></strong></td>
      </tr>
    </tbody>
  </table>
</div>
</body>
</html>
