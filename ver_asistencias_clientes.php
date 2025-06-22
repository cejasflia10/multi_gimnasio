<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Acceso denegado.");
}

// Fecha actual
$mes = date('m');
$anio = date('Y');

// Consulta asistencias del mes actual para este gimnasio
$sql = "SELECT a.id, c.nombre, c.apellido, c.disciplina, a.fecha, a.hora
        FROM asistencias a
        JOIN clientes c ON a.cliente_id = c.id
        WHERE a.gimnasio_id = ?
        AND MONTH(a.fecha) = ? AND YEAR(a.fecha) = ?
        ORDER BY a.fecha DESC, a.hora DESC";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("iii", $gimnasio_id, $mes, $anio);
$stmt->execute();
$resultado = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Asistencias del Mes</title>
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .tabla-contenedor {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            padding: 10px;
            text-align: center;
            border-bottom: 1px solid #444;
        }
        th {
            background-color: #222;
        }
        tr:hover {
            background-color: #333;
        }
        .volver {
            display: block;
            width: fit-content;
            margin: 0 auto;
            background-color: #ffd700;
            color: #111;
            padding: 10px 20px;
            border-radius: 5px;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            th, td {
                font-size: 14px;
                padding: 8px;
            }
        }
    </style>
</head>
<body>

<h2>Asistencias del Mes - <?php echo date('F Y'); ?></h2>

<div class="tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Disciplina</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado->num_rows > 0): ?>
                <?php while ($row = $resultado->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($row['apellido']); ?></td>
                        <td><?php echo htmlspecialchars($row['disciplina']); ?></td>
                        <td><?php echo htmlspecialchars($row['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($row['hora']); ?></td>
                    </tr>
                <?php } ?>
            <?php else: ?>
                <tr><td colspan="5">Sin asistencias registradas este mes.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href='index.php' class='volver'>Volver al Menú</a>

</body>
</html>
