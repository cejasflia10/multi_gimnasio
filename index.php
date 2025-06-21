<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "menu.php";
include "conexion.php";

$fecha_actual = date("Y-m-d");
$gimnasio_id = $_SESSION['gimnasio_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .contenido {
            margin-left: 240px;
            padding: 20px;
        }
        h2 {
            color: #FFD700;
        }
        .card {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            color: white;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
<div class="contenido">
    <div class="logo">
        <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h1>
        <p>Panel de control de <strong>Fight Academy</strong></p>
    </div>

    <div class="card">
        <h2>☑ Asistencias del día - <?php echo date("d/m/Y"); ?></h2>
        <?php include("asistencias_index.php"); ?>
    </div>

    <!-- Agregar aquí otras tarjetas como pagos, ventas, próximos cumpleaños y vencimientos -->
    <!-- Puedes usar el mismo estilo .card -->
</div>
</body>
</html>
