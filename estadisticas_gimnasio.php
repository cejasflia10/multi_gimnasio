<?php include 'verificar_sesion.php'; ?>
<?php
include 'conexion.php';

$totalClientes = $conexion->query("SELECT COUNT(*) AS total FROM clientes")->fetch_assoc()['total'];
$totalMembresias = $conexion->query("SELECT COUNT(*) AS total FROM membresias")->fetch_assoc()['total'];
$totalProfesores = $conexion->query("SELECT COUNT(*) AS total FROM profesores")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas del Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        h1 {
            color: #ffc107;
            text-align: center;
        }

        .cards {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
        }

        .card {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            width: 200px;
            box-shadow: 0 0 10px #000;
        }

        .card h2 {
            color: #ffc107;
            margin-bottom: 10px;
        }

        .card span {
            font-size: 32px;
            font-weight: bold;
            color: #00e676;
        }
    </style>
</head>
<body>
    <h1>📊 Estadísticas del Gimnasio</h1>
    <div class="cards">
        <div class="card">
            <h2>Clientes</h2>
            <span><?= $totalClientes ?></span>
        </div>
        <div class="card">
            <h2>Membresías</h2>
            <span><?= $totalMembresias ?></span>
        </div>
        <div class="card">
            <h2>Profesores</h2>
            <span><?= $totalProfesores ?></span>
        </div>
    </div>
</body>
</html>
