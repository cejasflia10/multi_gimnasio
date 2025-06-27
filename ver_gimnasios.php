<?php include 'verificar_sesion.php'; ?>
<?php
include 'conexion.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
$resultado = $conexion->query("SELECT * FROM gimnasios");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gimnasios Registrados</title>
  <style>
    body {
      background-color: #111;
      color: #f1f1f1;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h1 {
      color: #ffc107;
      text-align: center;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #222;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid #333;
      padding: 10px;
      text-align: center;
    }
    th {
      background-color: #333;
      color: #ffc107;
    }
    a.accion {
      color: gold;
      text-decoration: none;
      font-weight: bold;
      margin: 0 5px;
    }
    a.accion:hover {
      color: red;
    }
  </style>
</head>
<body>

  <h1>Gimnasios Registrados</h1>

  <table>
    <tr>
      <th>Nombre</th>
      <th>Email</th>
      <th>Clientes</th>
      <th>Plan</th>
      <th>Vencimiento</th>
      <th>Acciones</th>
    </tr>
    <?php while ($row = $resultado->fetch_assoc()): ?>
    <tr>
      <td><?= htmlspecialchars($row['nombre']) ?></td>
      <td><?= htmlspecialchars($row['email']) ?></td>
      <td><?= htmlspecialchars($row['cantidad_clientes']) ?></td>
      <td><?= htmlspecialchars($row['plan']) ?></td>
      <td><?= htmlspecialchars($row['fecha_vencimiento']) ?></td>
      <td>
        <a class="accion" href="editar_gimnasio.php?id=<?= $row['id'] ?>">✏️ Editar</a> |
        <a class="accion" href="eliminar_gimnasio.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Estás seguro de que deseas eliminar este gimnasio?')">🗑️ Eliminar</a>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>

</body>
</html>
