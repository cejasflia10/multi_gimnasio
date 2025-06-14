<?php
session_start();
if (!isset($_SESSION['id_gimnasio'])) {
    header("Location: login_seguro.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal - Fight Academy</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #f1f1f1;
        }

        .main {
            margin-left: 240px;
            padding: 30px;
        }

        .tarjeta {
            background-color: #222;
            border-left: 6px solid #ffc107;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        .tarjeta h3 {
            color: #ffc107;
            margin: 0 0 10px;
        }

        .tarjeta p {
            font-size: 18px;
            margin: 0;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="main">
    <h1>Bienvenido al Sistema Multi-Gimnasio</h1>

    <div class="tarjeta">
        <h3>📅 Cumpleaños del mes</h3>
        <p>Juan Pérez - 12/06</p>
        <p>Lucía Gómez - 25/06</p>
    </div>

    <div class="tarjeta">
        <h3>💰 Ingresos del día</h3>
        <p>$ 12.000</p>
    </div>

    <div class="tarjeta">
        <h3>💸 Ingresos mensuales</h3>
        <p>$ 315.000</p>
    </div>

    <div class="tarjeta">
        <h3>⚠️ Próximos vencimientos</h3>
        <p>Carlos Rodríguez - vence 14/06</p>
        <p>Martina López - vence 15/06</p>
    </div>
</div>

</body>
</html>
