<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) {
    echo "<div style='color:red; text-align:center; font-size:20px;'>❌ Acceso denegado.</div>";
    exit;
}

$filtro = $_GET['filtro'] ?? 'mensual'; // opciones: semanal, mensual, anual

switch ($filtro) {
    case 'semanal':
        $where = "fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'anual':
        $where = "YEAR(fecha) = YEAR(CURDATE())";
        break;
    case 'mensual':
    default:
        $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        break;
}

$query = $conexion->query("
    SELECT fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades
    FROM progreso_cliente
    WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id AND $where
    ORDER BY fecha DESC
");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📊 Mi Progreso</title>
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .contenedor {
            max-width: 800px;
            margin: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: #111;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: center;
        }
        th {
            background: #222;
        }
        .filtros {
            text-align: center;
            margin-bottom: 20px;
        }
        .filtros a {
            color: gold;
            margin: 0 10px;
            text-decoration: none;
            font-weight: bold;
        }
        .filtros a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>📈 Historial de Progreso Físico</h2>

    <div class="filtros">
        <a href="?filtro=semanal">📅 Semanal</a> |
        <a href="?filtro=mensual">🗓️ Mensual</a> |
        <a href="?filtro=anual">📆 Anual</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>📅 Fecha</th>
                <th>Peso Antes</th>
                <th>Peso Después</th>
                <th>Duración (min)</th>
                <th>Esfuerzo</th>
                <th>Calorías Est.</th>
                <th>Enfermedades</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($query->num_rows > 0): ?>
                <?php while($row = $query->fetch_assoc()): ?>
                    <tr>
                        <td><?= $row['fecha'] ?></td>
                        <td><?= $row['peso_antes'] ?> kg</td>
                        <td><?= $row['peso_despues'] ?> kg</td>
                        <td><?= $row['duracion_entrenamiento'] ?></td>
                        <td><?= htmlspecialchars($row['esfuerzo']) ?></td>
                        <td><?= $row['calorias_estimadas'] ?> kcal</td>
                        <td><?= htmlspecialchars($row['enfermedades']) ?: '-' ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="7">No se encontraron registros.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
