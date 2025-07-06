<?php
session_start();
include 'conexion.php';

if (!in_array($_SESSION['rol'] ?? '', ['profesor', 'admin'])) {
    die("Acceso denegado.");
}

$cliente_id = $_GET['id'] ?? null;
if (!$cliente_id) die("ID de cliente requerido.");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['peso'])) {
    $peso = $_POST['peso'];
    $recomendaciones = $_POST['recomendaciones'];
    $observaciones = $_POST['observaciones'];
    $fecha = $_POST['fecha'];
    $profesor_id = $_SESSION['usuario_id'] ?? 0;

    $stmt = $conexion->prepare("INSERT INTO seguimiento_nutricional (cliente_id, fecha, peso, recomendaciones, observaciones, profesor_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdssi", $cliente_id, $fecha, $peso, $recomendaciones, $observaciones, $profesor_id);
    $stmt->execute();
    $stmt->close();
}

$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$seguimientos = $conexion->query("SELECT * FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Seguimiento Nutricional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
  <h2>🥗 Seguimiento Nutricional de <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

  <a class="btn-nueva" href="panel_cliente.php?id=<?= $cliente_id ?>">← Volver al Panel del Cliente</a>

  <form method="POST">
    <label>Fecha:</label>
    <input type="date" name="fecha" required>

    <label>Peso actual (kg):</label>
    <input type="number" step="0.01" name="peso" required>

    <label>Recomendaciones:</label>
    <textarea name="recomendaciones" rows="3"></textarea>

    <label>Observaciones:</label>
    <textarea name="observaciones" rows="3"></textarea>

    <button type="submit">💾 Guardar Seguimiento</button>
  </form>

  <h3>📊 Historial de Seguimientos</h3>
  <div style="overflow-x:auto;">
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Peso</th>
          <th>Recomendaciones</th>
          <th>Observaciones</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($s = $seguimientos->fetch_assoc()): ?>
          <tr>
            <td><?= $s['fecha'] ?></td>
            <td><?= $s['peso'] ?> kg</td>
            <td><?= htmlspecialchars($s['recomendaciones']) ?></td>
            <td><?= htmlspecialchars($s['observaciones']) ?></td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
</body>
</html>
