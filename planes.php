<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes del Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
            color: gold;
        }
        td {
            background-color: #1c1c1c;
        }
        .btn {
            background-color: gold;
            color: black;
            padding: 6px 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #ffd700;
        }
        .acciones {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .top-buttons {
            margin-bottom: 20px;
            text-align: center;
        }
        .top-buttons a {
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <h1>Planes del Gimnasio</h1>
    <div class="top-buttons">
        <a href="agregar_plan.php" class="btn">Crear nuevo plan</a>
        <a href="index.php" class="btn">Volver al menú</a>
    </div>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Precio</th>
            <th>Días disponibles</th>
            <th>Duración (meses)</th>
            <th>Acciones</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
            <td><?php echo '$' . number_format($fila['precio'], 2, ',', '.'); ?></td>
            <td><?php echo $fila['dias_disponibles'] ?? '0'; ?></td>
            <td><?php echo $fila['duracion'] ?? '0'; ?></td>
            <td class="acciones">
                <a href="editar_plan.php?id=<?php echo $fila['id']; ?>" class="btn">Editar</a>
                <a href="eliminar_plan.php?id=<?php echo $fila['id']; ?>" class="btn" onclick="return confirm('¿Estás seguro de eliminar este plan?')">Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
