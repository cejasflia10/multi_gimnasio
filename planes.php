<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        a.boton {
            display: inline-block;
            padding: 10px 15px;
            margin: 10px 5px;
            background-color: gold;
            color: black;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
        }
        @media screen and (max-width: 768px) {
            table, thead, tbody, th, td, tr {
                display: block;
            }
            th {
                display: none;
            }
            td {
                border: none;
                position: relative;
                padding-left: 50%%;
                text-align: left;
            }
            td:before {
                position: absolute;
                top: 10px;
                left: 10px;
                width: 45%%;
                white-space: nowrap;
                font-weight: bold;
            }
            td:nth-of-type(1):before { content: "Nombre"; }
            td:nth-of-type(2):before { content: "Precio"; }
            td:nth-of-type(3):before { content: "Días disponibles"; }
            td:nth-of-type(4):before { content: "Duración"; }
            td:nth-of-type(5):before { content: "Acciones"; }
        }
    </style>
</head>
<body>
    <h2>Planes del Gimnasio</h2>
    <a class="boton" href="agregar_plan.php">Crear nuevo plan</a>
    <a class="boton" href="index.php">Volver al menú</a>
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Precio</th>
                <th>Días disponibles</th>
                <th>Duración (meses)</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($fila = $resultado->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($fila['nombre']); ?></td>
                    <td>$<?php echo number_format($fila['precio'], 2, ',', '.'); ?></td>
                    <td><?php echo (int)$fila['dias_disponibles']; ?></td>
                    <td><?php echo (int)$fila['duracion_meses']; ?></td>
                    <td>
                        <a class="boton" href="editar_plan.php?id=<?php echo $fila['id']; ?>">Editar</a>
                        <a class="boton" href="eliminar_plan.php?id=<?php echo $fila['id']; ?>" onclick="return confirm('¿Seguro que deseas eliminar este plan?')">Eliminar</a>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</body>
</html>
