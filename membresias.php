<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Gimnasio no especificado.");
}

$sql = "SELECT m.id, c.nombre, c.apellido, p.nombre AS plan_nombre, m.fecha_inicio, m.fecha_vencimiento, m.clases_disponibles, m.total 
        FROM membresias m
        JOIN clientes c ON m.cliente_id = c.id
        JOIN planes p ON m.plan_id = p.id
        WHERE m.gimnasio_id = $gimnasio_id
        ORDER BY m.fecha_vencimiento ASC";

$resultado = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ESTILOS -->
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .buscar {
            width: 100%;
            max-width: 500px;
            padding: 10px;
            font-size: 16px;
            margin: 10px auto 20px;
            display: block;
            background: #222;
            color: #ffd700;
            border: 1px solid #ffd700;
            border-radius: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #444;
        }
        th {
            background-color: #222;
        }
        tr:hover {
            background-color: #333;
        }
        .boton {
            background-color: #ffd700;
            color: #111;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            margin-right: 5px;
        }
        .boton:hover {
            background-color: #e5c100;
        }
        @media (max-width: 768px) {
            .buscar {
                font-size: 14px;
            }
            th, td {
                font-size: 14px;
                padding: 8px;
            }
            .boton {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<h2>Listado de Membresías</h2>

<input type="text" class="buscar" id="buscador" placeholder="Buscar cliente, plan o pago...">

<table id="tabla">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Inicio</th>
            <th>Vencimiento</th>
            <th>Clases Disponibles</th>
            <th>Total</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($row = $resultado->fetch_assoc()) { ?>
            <tr>
                <td><?php echo $row['apellido'] . ", " . $row['nombre']; ?></td>
                <td><?php echo $row['plan_nombre']; ?></td>
                <td><?php echo $row['fecha_inicio']; ?></td>
                <td><?php echo $row['fecha_vencimiento']; ?></td>
                <td><?php echo $row['clases_disponibles'] . " clases"; ?></td>
                <td>$<?php echo number_format($row['total'], 2, ',', '.'); ?></td>
                <td>
                    <a class="boton" href="editar_membresia.php?id=<?php echo $row['id']; ?>">Editar</a>
                    <a class="boton" href="eliminar_membresia.php?id=<?php echo $row['id']; ?>" onclick="return confirm('¿Eliminar esta membresía?')">Eliminar</a>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<script>
    const buscador = document.getElementById('buscador');
    buscador.addEventListener('input', function () {
        const filtro = this.value.toLowerCase();
        const filas = document.querySelectorAll('#tabla tbody tr');
        filas.forEach(fila => {
            const texto = fila.textContent.toLowerCase();
            fila.style.display = texto.includes(filtro) ? '' : 'none';
        });
    });
</script>

</body>
</html>
