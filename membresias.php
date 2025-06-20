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
        .buscar {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            background: #222;
            color: #FFD700;
            border: 1px solid #FFD700;
        }
        .acciones {
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .acciones a {
            padding: 5px 10px;
            background-color: #FFD700;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>Listado de Membresías</h1>
    <div class="container">
        <input type="text" id="buscador" class="buscar" placeholder="Buscar cliente, disciplina o pago...">
        <table id="tabla">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Disciplina</th>
                    <th>Plan</th>
                    <th>Inicio</th>
                    <th>Vencimiento</th>
                    <th>Método de Pago</th>
                    <th>Total</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $sql = "SELECT 
                        m.id, 
                        CONCAT(c.apellido, ', ', c.nombre) AS cliente,
                        d.nombre AS disciplina,
                        p.nombre AS plan,
                        m.fecha_inicio,
                        m.fecha_vencimiento,
                        m.metodo_pago,
                        m.total
                    FROM membresias m
                    INNER JOIN clientes c ON m.cliente_id = c.id
                    INNER JOIN disciplinas d ON m.disciplina_id = d.id
                    INNER JOIN planes p ON m.plan_id = p.id
                    WHERE m.gimnasio_id = $gimnasio_id
                    ORDER BY m.fecha_inicio DESC";

            $resultado = $conexion->query($sql);
            while ($row = $resultado->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['cliente']}</td>";
                echo "<td>{$row['disciplina']}</td>";
                echo "<td>{$row['plan']}</td>";
                echo "<td>{$row['fecha_inicio']}</td>";
                echo "<td>{$row['fecha_vencimiento']}</td>";
                echo "<td>{$row['metodo_pago']}</td>";
                echo "<td>\${$row['total']}</td>";
                echo "<td class='acciones'>
                        <a href='editar_membresia.php?id={$row['id']}'>Editar</a>
                        <a href='eliminar_membresia.php?id={$row['id']}' onclick='return confirm("¿Seguro que deseas eliminar esta membresía?")'>Eliminar</a>
                      </td>";
                echo "</tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</body>
</html>
