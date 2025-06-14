<?php include 'verificar_sesion.php'; ?>
<?php
include 'conexion.php';
$id = $_GET['id'];
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $id")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $plan = $_POST['plan'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $conexion->query("UPDATE gimnasios SET plan = '$plan', fecha_vencimiento = '$fecha_vencimiento' WHERE id = $id");
    header("Location: ver_gimnasios.php");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Gimnasio</title>
  <style>
    body { background-color: #111; color: #f1f1f1; font-family: Arial, sans-serif; padding: 20px; }
    label, input, select { display: block; margin-bottom: 10px; }
    input, select { padding: 8px; width: 300px; }
    button { background: #ffc107; border: none; padding: 10px; cursor: pointer; }
  </style>
</head>
<body>
  <h2>Editar Gimnasio: <?= $gimnasio['nombre'] ?></h2>
  <form method="POST">
    <label>Plan:</label>
    <select name="plan">
      <option value="mensual" <?= $gimnasio['plan'] == 'mensual' ? 'selected' : '' ?>>Mensual</option>
      <option value="bimestral" <?= $gimnasio['plan'] == 'bimestral' ? 'selected' : '' ?>>Bimestral</option>
      <option value="anual" <?= $gimnasio['plan'] == 'anual' ? 'selected' : '' ?>>Anual</option>
    </select>

    <label>Fecha de Vencimiento:</label>
    <input type="date" name="fecha_vencimiento" value="<?= $gimnasio['fecha_vencimiento'] ?>" required>

    <button type="submit">Guardar Cambios</button>
  </form>
</body>
</html>
