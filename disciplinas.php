<?php
include 'conexion.php';
session_start();
if (!isset($_SESSION['gimnasio_id'])) die("Acceso denegado.");
$gimnasio_id = $_SESSION['gimnasio_id'];

$result = $conexion->query("SELECT * FROM disciplinas WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html><head><meta charset="UTF-8"><title>Disciplinas</title>
<style>body{background:#111;color:#FFD700;font-family:sans-serif;padding:20px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #FFD700}a{color:#FFD700;text-decoration:none}</style>
</head><body>
<h2>Disciplinas</h2>
<a href="crear_disciplina.php">➕ Nueva Disciplina</a><br><br>
<table>
<tr><th>Nombre</th><th>Acciones</th></tr>
<?php while($row = $result->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars($row['nombre']) ?></td>
    <td>
        <a href="editar_disciplina.php?id=<?= $row['id'] ?>">Editar</a> |
        <a href="eliminar_disciplina.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar disciplina?')">Eliminar</a>
    </td>
</tr>
<?php endwhile; ?>
</table>
</body></html>