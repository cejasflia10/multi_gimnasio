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
  <title>Ver Disciplinas</title>
  <link rel="stylesheet" href="estilo_unificado.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<script src="fullscreen.js"></script>

<body>
<div class="contenedor">
  <h2>📋 Disciplinas del Gimnasio</h2>
  <a href="agregar_disciplina.php" class="btn-nueva">➕ Nueva Disciplina</a>

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
            <a href="editar_disciplina.php?id=<?= $row['id'] ?>" class="btn-editar"><i class="fas fa-edit"></i> Editar</a>
            <a href="eliminar_disciplina.php?id=<?= $row['id'] ?>" class="btn-eliminar" onclick="return confirm('¿Eliminar esta disciplina?');"><i class="fas fa-trash"></i> Eliminar</a>
          </td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
</body>
</html>
