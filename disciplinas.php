<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$disciplinas = $conexion->query("SELECT id, nombre FROM disciplinas WHERE gimnasio_id = $gimnasio_id ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Disciplinas</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    h2 {
      text-align: center;
      margin-bottom: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background-color: #1c1c1c;
      margin-top: 20px;
    }
    th, td {
      border: 1px solid gold;
      padding: 10px;
      text-align: left;
    }
    th {
      background-color: #222;
    }
    .acciones a {
      margin-right: 10px;
      text-decoration: none;
      font-weight: bold;
    }
    .editar {
      color: orange;
    }
    .eliminar {
      color: red;
    }
    .btn-nueva {
      display: inline-block;
      padding: 10px 20px;
      background-color: gold;
      color: black;
      text-decoration: none;
      font-weight: bold;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    .btn-nueva:hover {
      background-color: #e5c100;
    }
    @media (max-width: 768px) {
      table, thead, tbody, th, td, tr {
        font-size: 14px;
      }
    }
  </style>
</head>
<body>

<h2>📋 Disciplinas del Gimnasio</h2>
<a href="agregar_disciplina.php" class="btn-nueva">➕ Nueva Disciplina</a>

<?php if ($disciplinas && $disciplinas->num_rows > 0): ?>
<table>
  <thead>
    <tr>
      <th>Nombre</th>
      <th>Acciones</th>
    </tr>
  </thead>
  <tbody>
    <?php while ($row = $disciplinas->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['nombre']) ?></td>
        <td class="acciones">
          <a href="editar_disciplina.php?id=<?= $row['id'] ?>" class="editar"><i class="fas fa-edit"></i> Editar</a>
          <a href="eliminar_disciplina.php?id=<?= $row['id'] ?>" class="eliminar" onclick="return confirm('¿Eliminar esta disciplina?');"><i class="fas fa-trash"></i> Eliminar</a>
        </td>
      </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php else: ?>
  <p style="text-align:center;">No hay disciplinas registradas aún.</p>
<?php endif; ?>

</body>
</html>
