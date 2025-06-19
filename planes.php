<?php
include 'conexion.php';

// Agregar nuevo plan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_nombre'])) {
    $nombre = $_POST['nuevo_nombre'];
    $precio = $_POST['nuevo_precio'];
    $conexion->query("INSERT INTO planes (nombre, precio) VALUES ('$nombre', '$precio')");
    header("Location: planes.php");
    exit();
}

// Eliminar plan
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conexion->query("DELETE FROM planes WHERE id = $id");
    header("Location: planes.php");
    exit();
}

$planes = $conexion->query("SELECT * FROM planes ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Planes</title>
  <style>
    body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
    .container { padding: 30px; }
    h1 { color: #ffc107; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { padding: 10px; border-bottom: 1px solid #333; text-align: left; }
    th { background: #222; color: #ffc107; }
    .btn { padding: 5px 10px; margin-right: 5px; background: #ffc107; color: #111; text-decoration: none; border-radius: 5px; }
    .btn:hover { background: #e0a800; }
    input { padding: 5px; margin-right: 10px; }
  </style>
</head>
<body>
<div class="container">
  <h1>Planes</h1>
  <form method="POST" style="margin-bottom: 20px;">
    <input type="text" name="nuevo_nombre" placeholder="Nombre del plan" required>
    <input type="number" step="0.01" name="nuevo_precio" placeholder="Precio" required>
    <button type="submit" class="btn">Agregar Plan</button>
  </form>

  <table>
    <tr>
      <th>Nombre</th>
      <th>Precio</th>
      <th>Acciones</th>
    </tr>
    <?php while ($p = $planes->fetch_assoc()): ?>
    <tr>
      <td><?= $p['nombre'] ?></td>
      <td>$<?= number_format($p['precio'], 2) ?></td>
      <td>
        <a href="planes.php?eliminar=<?= $p['id'] ?>" class="btn" onclick="return confirm('¿Eliminar este plan?')">Eliminar</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>
