<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h1 {
            text-align: center;
            padding: 20px;
        }
        .container {
            padding: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            border: 1px solid #FFD700;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #000;
        }
        .vencida {
            background-color: #550000 !important;
            color: #FFD700;
        }
        .buscar {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background: #222;
            color: #FFD700;
            border: 1px solid #FFD700;
        }
        .acciones a {
            padding: 5px 10px;
            background-color: #FFD700;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <h1>Listado de Membresías</h1>
    <div class="container">
        <input type="text" id="buscador" class="buscar" placeholder="Buscar cliente, plan o pago...">
        <table id="tabla">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Plan</th>
                    <th>Inicio</th>
                    <th>Vencimiento</th>
                    <th>Días Restantes</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT 
                        m.id, 
                        m.fecha_inicio,
                        m.fecha_vencimiento,
                        m.total,
                        p.nombre AS plan,
                        CONCAT(c.apellido, ', ', c.nombre) AS cliente
                    FROM membresias m
                    INNER JOIN clientes c ON m.cliente_id = c.id
                    INNER JOIN planes p ON m.plan_id = p.id
                    WHERE m.gimnasio_id = $gimnasio_id
                    ORDER BY m.fecha_inicio DESC";
            $resultado = $conexion->query($sql);
            $hoy = new DateTime();

            while ($row = $resultado->fetch_assoc()) {
                $vencimiento = new DateTime($row['fecha_vencimiento']);
                $dias = (int)$hoy->diff($vencimiento)->format('%r%a');
                $clase = $dias < 0 ? 'vencida' : '';
                $texto_dias = $dias < 0 ? "Vencida hace " . abs($dias) . " días" : "$dias días";
                echo "<tr class='$clase'>";
                echo "<td>{$row['cliente']}</td>";
                echo "<td>{$row['plan']}</td>";
                echo "<td>{$row['fecha_inicio']}</td>";
                echo "<td>{$row['fecha_vencimiento']}</td>";
                echo "<td>$texto_dias</td>";
                echo "<td>\${$row['total']}</td>";
                echo "<td class='acciones'>
                        <a href='editar_membresia.php?id={$row['id']}'>Editar</a>
                        <a href='eliminar_membresia.php?id={$row['id']}' onclick=\"return confirm('¿Seguro que deseas eliminar esta membresía?')\">Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</body>
</html>
