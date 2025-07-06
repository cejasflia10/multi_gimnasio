<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$cliente_id || !$gimnasio_id) {
    echo "<div style='color:red;'>Acceso denegado.</div>";
    exit;
}

$resultado = $conexion->query("
    SELECT nombre_competencia, lugar, fecha, resultado, observaciones
    FROM competencias
    WHERE cliente_id = $cliente_id
    ORDER BY fecha DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Competencias</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
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
            background: #222;
        }
        td {
            background: #111;
            color: white;
        }
    </style>
</head>
<body>

<h2>🥇 Mis Competencias</h2>

<?php if ($resultado->num_rows > 0): ?>
    <table>
        <thead>
            <tr>
                <th>Competencia</th>
                <th>Lugar</th>
                <th>Fecha</th>
                <th>Resultado</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre_competencia']) ?></td>
                    <td><?= htmlspecialchars($row['lugar']) ?></td>
                    <td><?= date('d/m/Y', strtotime($row['fecha'])) ?></td>
                    <td><?= htmlspecialchars($row['resultado']) ?></td>
                    <td><?= nl2br(htmlspecialchars($row['observaciones'])) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No hay competencias registradas aún.</p>
<?php endif; ?>

</body>
</html>
