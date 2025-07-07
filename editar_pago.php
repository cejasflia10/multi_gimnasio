<?php
session_start();
include 'conexion.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die("ID inválido");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hora_ingreso = $_POST['hora_ingreso'];
    $hora_egreso = $_POST['hora_egreso'];
    $alumnos = intval($_POST['alumnos']);
    $pago = floatval($_POST['pago']);

    $update = $conexion->prepare("
        UPDATE asistencias_profesor 
        SET hora_ingreso = ?, hora_egreso = ?, alumnos = ?, pago = ?
        WHERE id = ?
    ");
    $update->bind_param("ssidi", $hora_ingreso, $hora_egreso, $alumnos, $pago, $id);
    $update->execute();

    header("Location: pagar_horas_profesor.php?editado=1");
    exit;
}

$data = $conexion->query("SELECT * FROM asistencias_profesor WHERE id = $id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Registro de Pago</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 30px; }
    input, label, button {
      display: block; margin: 10px 0;
    }
    input[type='text'], input[type='number'], input[type='time'] {
      padding: 5px;
    }
    button {
      background: gold; color: black; padding: 10px 15px; border: none; border-radius: 5px;
    }
    button:hover { background: orange; }
  </style>
</head>
<body>
  <h2>📝 Editar Registro de Pago</h2>
  <form method="POST">
    <label>Hora de ingreso:</label>
    <input type="time" name="hora_ingreso" value="<?= $data['hora_ingreso'] ?>" required>

    <label>Hora de egreso:</label>
    <input type="time" name="hora_egreso" value="<?= $data['hora_egreso'] ?>" required>

    <label>Cantidad de alumnos:</label>
    <input type="number" name="alumnos" value="<?= $data['alumnos'] ?>" required>

    <label>Monto a pagar ($):</label>
    <input type="number" name="pago" value="<?= $data['pago'] ?>" step="100" required>

    <button type="submit">💾 Guardar Cambios</button>
    <a href="pagar_horas_profesor.php" style="color:gold; text-decoration:underline;">⬅ Volver</a>
  </form>
</body>
</html>
