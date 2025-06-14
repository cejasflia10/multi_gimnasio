<?php
include 'conexion.php';

// Agregar nuevo adicional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_nombre'])) {
    $nombre = $_POST['nuevo_nombre'];
    $precio = $_POST['nuevo_precio'];
    $conexion->query("INSERT INTO planes_adicionales (nombre, precio) VALUES ('$nombre', '$precio')");
    header("Location: planes_adicionales.php");
    exit();
}

// Eliminar adicional
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $conexion->query("DELETE FROM planes_adicionales WHERE id = $id");
    header("Location: planes_adicionales.php");
    exit();
}

$adicionales = $conexion->query("SELECT * FROM planes_adicionales ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Planes Adicionales</title>
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
<?php include 'menu.php'; ?>
<div class="container">
  <h1>Planes Adicionales</h1>
  <form method="POST" style="margin-bottom: 20px;">
    <input type="text" name="nuevo_nombre" placeholder="Nombre adicional" required>
    <input type="number" step="0.01" name="nuevo_precio" placeholder="Precio" required>
    <button type="submit" class="btn">Agregar Adicional</button>
  </form>

  <table>
    <tr>
      <th>Nombre</th>
      <th>Precio</th>
      <th>Acciones</th>
    </tr>
    <?php while ($a = $adicionales->fetch_assoc()): ?>
    <tr>
      <td><?= $a['nombre'] ?></td>
      <td>$<?= number_format($a['precio'], 2) ?></td>
      <td>
        <a href="planes_adicionales.php?eliminar=<?= $a['id'] ?>" class="btn" onclick="return confirm('¿Eliminar este adicional?')">Eliminar</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>
</body>
</html>
