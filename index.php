<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['usuario'])) {
    die('Acceso denegado.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Multi Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #fff;
        }
        .sidebar {
            width: 250px;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            background-color: #000;
            color: gold;
            padding-top: 60px;
        }
        .contenido {
            margin-left: 250px;
            padding: 20px;
        }
        h1 {
            color: gold;
        }
        .tarjeta {
            background-color: #222;
            padding: 15px;
            margin: 10px 0;
            border-radius: 10px;
        }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="contenido">
    <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h1>

    <div class="tarjeta">
        <h2>Clientes activos: <!-- número desde la base de datos --></h2>
        <p>Total de clientes que tienen membresía activa.</p>
    </div>

    <div class="tarjeta">
        <h2>Clases del día: <!-- número dinámico --></h2>
        <p>Cantidad de clases programadas hoy.</p>
    </div>

    <div class="tarjeta">
        <h2>Ingresos de hoy: $<!-- total --></h2>
        <p>Total recaudado en el día.</p>
    </div>

    <div class="tarjeta">
        <h2>Próximos cumpleaños</h2>
        <ul>
            <li>Juan Pérez - 18 de junio</li>
            <li>Ana Gómez - 22 de junio</li>
        </ul>
    </div>
</div>
</body>
</html>
