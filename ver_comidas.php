<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Traer historial de comidas agrupado por fecha
$sql = "SELECT fecha, SUM(total_calorias) AS total_dia 
        FROM registro_comidas 
        WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id 
        GROUP BY fecha ORDER BY fecha DESC";
$fechas = $conexion->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🍽 Historial de Comidas</title>
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial;
            padding: 20px;
        }
        h2 {
            color: gold;
        }
        .dia {
            background: #111;
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 10px;
        }
        .comidas {
            padding-left: 20px;
        }
    </style>
</head>
<body>

<h2>📅 Historial de comidas registradas</h2>

<?php if ($fechas->num_rows > 0): ?>
    <?php while ($f = $fechas->fetch_assoc()):
        $fecha = $f['fecha'];
        $total_dia = $f['total_dia'];

        $comidas = $conexion->query("SELECT * FROM registro_comidas 
            WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id AND fecha = '$fecha'");
    ?>
        <div class="dia">
            <h3>🗓️ <?= date("d/m/Y", strtotime($fecha)) ?> | Total: <?= round($total_dia) ?> kcal</h3>
            <ul class="comidas">
                <?php while ($c = $comidas->fetch_assoc()): ?>
                    <li>
                        🍽 <?= htmlspecialchars($c['comida']) ?> |
                        Porciones: <?= $c['porciones'] ?> |
                        Calorías: <?= $c['total_calorias'] ?> kcal
                    </li>
                <?php endwhile; ?>
            </ul>
        </div>
    <?php endwhile; ?>
<?php else: ?>
    <p>No hay comidas registradas aún.</p>
<?php endif; ?>

</body>
</html>
