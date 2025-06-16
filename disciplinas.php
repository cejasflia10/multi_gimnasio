<?php
include 'conexion.php';
include 'menu.php';
$consulta = "SELECT * FROM disciplinas";
$resultado = mysqli_query($conexion, $consulta);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Disciplinas</title>
    <style>
        body { background-color: #111; color: #f1c40f; font-family: Arial, sans-serif; margin: 0; }
        .contenido { margin-left: 240px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #1a1a1a; color: white; }
        th, td { padding: 10px; border-bottom: 1px solid #f1c40f; }
        th { background: #222; color: #f1c40f; }
        a.btn { background: #f1c40f; color: #111; padding: 6px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px; }
        a.btn:hover { background: #d4ac0d; }
        .top-btn { margin-bottom: 15px; display: inline-block; }
    </style>
</head>
<body>
<div class="contenido">
    <h2>Disciplinas</h2>
    <a class="btn top-btn" href="agregar_disciplina.php">Agregar Disciplina</a>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Descripción</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while($row = mysqli_fetch_assoc($resultado)): ?>
            <tr>
                <td><?php echo $row['nombre']; ?></td>
                <td><?php echo $row['descripcion']; ?></td>
                <td>
                    <a class="btn" href="editar_disciplina.php?id=<?php echo $row['id']; ?>">Editar</a>
                    <a class="btn" href="eliminar_disciplina.php?id=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar esta disciplina?')">Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
</body>
</html>
