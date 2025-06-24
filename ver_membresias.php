<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Consulta con JOIN para obtener nombre y apellido del cliente
if ($rol === 'admin') {
    $query = "SELECT membresias.*, clientes.nombre, clientes.apellido 
              FROM membresias 
              JOIN clientes ON membresias.cliente_id = clientes.id";
} else {
    $query = "SELECT membresias.*, clientes.nombre, clientes.apellido 
              FROM membresias 
              JOIN clientes ON membresias.cliente_id = clientes.id
              WHERE membresias.id_gimnasio = $gimnasio_id";
}
$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h2 {
            text-align: center;
            padding-top: 20px;
        }
        table {
            width: 95%;
            margin: 20px auto;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #f1c40f;
        }
        th {
            background-color: #000;
        }
        .btn {
            padding: 5px 10px;
            margin: 2px;
            background-color: #f1c40f;
            color: black;
            font-weight: bold;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .volver {
            display: block;
            width: 200px;
            margin: 30px auto;
            text-align: center;
        }
    </style>
</head>
<body>
    <h2>Membresías Activas</h2>
    <table>
        <tr>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Fecha Inicio</th>
            <th>Fecha Vencimiento</th>
            <th>Total</th>
            <th>Clases Disp.</th>
            <th>Clases Rest.</th>
            <th>Acciones</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['nombre']; ?></td>
                <td><?php echo $row['apellido']; ?></td>
                <td><?php echo $row['fecha_inicio']; ?></td>
                <td><?php echo $row['fecha_vencimiento']; ?></td>
                <td>$<?php echo number_format($row['total'], 2, ',', '.'); ?></td>
                <td><?php echo $row['clases_disponibles']; ?></td>
                <td><?php echo $row['clases_restantes']; ?></td>
                <td>
                    <a href="editar_membresia.php?id=<?php echo $row['id']; ?>" class="btn">Editar</a>
                    <a href="eliminar_membresia.php?id=<?php echo $row['id']; ?>" class="btn" onclick="return confirm('¿Eliminar esta membresía?');">Eliminar</a>
                </td>
            </tr>
        <?php } ?>
    </table>

    <div class="volver">
        <a href="index.php" class="btn">Volver al Menú</a>
    </div>
</body>
</html>
