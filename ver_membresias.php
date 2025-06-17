<?php
include 'conexion.php';
include 'menu.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Membresías registradas</title>
    <style>
        body {
            background-color: #111;
            color: #ffc107;
            font-family: Arial, sans-serif;
            margin: 0;
            padding-top: 60px;
        }

        .contenedor {
            margin-left: 250px;
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            border: 1px solid #444;
            text-align: center;
        }

        th {
            background-color: #333;
            color: #ffc107;
        }

        td {
            color: #eee;
        }

        h1 {
            text-align: center;
            color: #ffc107;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Membresías registradas</h1>
        <table>
            <tr>
                <th>Cliente</th>
                <th>Plan</th>
                <th>Inicio</th>
                <th>Vencimiento</th>
                <th>Método de pago</th>
                <th>Monto abonado</th>
                <th>Clases totales</th>
                <th>Clases usadas</th>
                <th>Clases restantes</th>
            </tr>

            <?php
            $query = "SELECT m.*, c.apellido, c.nombre, p.nombre AS plan_nombre, p.precio, p.cantidad_clases 
                      FROM membresias m
                      JOIN clientes c ON m.cliente_id = c.id
                      JOIN planes p ON m.plan_id = p.id";

            $result = $conexion->query($query);

            while ($row = $result->fetch_assoc()) {
                $cliente = $row['apellido'] . " " . $row['nombre'];
                $plan = $row['plan_nombre'] . "<br>(" . $row['cantidad_clases'] . " clases - $" . number_format($row['precio'], 2) . ")";
                $inicio = $row['fecha_inicio'];
                $vencimiento = $row['fecha_vencimiento'];
                $metodo_pago = !empty($row['metodo_pago']) ? ucfirst($row['metodo_pago']) : '-';
                $monto = is_numeric($row['monto_pago']) ? '$' . number_format($row['monto_pago'], 2) : '-';
                $clases_totales = $row['cantidad_clases'];

                // Consultar clases realizadas
                $membresia_id = $row['id'];
                $usadas_result = $conexion->query("SELECT COUNT(*) AS usadas FROM clases_realizadas WHERE membresia_id = $membresia_id");
                $usadas_row = $usadas_result ? $usadas_result->fetch_assoc() : ['usadas' => 0];
                $usadas = $usadas_row['usadas'];
                $restantes = $clases_totales - $usadas;

                echo "<tr>
                        <td>$cliente</td>
                        <td>$plan</td>
                        <td>$inicio</td>
                        <td>$vencimiento</td>
                        <td>$metodo_pago</td>
                        <td>$monto</td>
                        <td>$clases_totales</td>
                        <td>$usadas</td>
                        <td>$restantes</td>
                      </tr>";
            }
            ?>
        </table>
    </div>
</body>
</html>
