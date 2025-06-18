<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Variables
$mensaje = "";
$nombre = "";
$apellido = "";
$clases = "";
$disciplina = "";
$fecha_vencimiento = "";

// Procesar si se recibe un DNI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);

    $stmt = $conexion->prepare("SELECT id, nombre, apellido, clases_restantes, disciplina, fecha_vencimiento, gimnasio_id FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $id_cliente = $cliente["id"];
        $nombre = $cliente["nombre"];
        $apellido = $cliente["apellido"];
        $clases = $cliente["clases_restantes"];
        $disciplina = $cliente["disciplina"];
        $fecha_vencimiento = $cliente["fecha_vencimiento"];
        $gimnasio_id = $cliente["gimnasio_id"];

        // Validar vencimiento y clases disponibles
        $fecha_actual = date("Y-m-d");
        if ($fecha_vencimiento < $fecha_actual) {
            $mensaje = "El plan está vencido.";
        } elseif ($clases <= 0) {
            $mensaje = "No tiene clases disponibles.";
        } else {
            // Descontar una clase
            $nuevas_clases = $clases - 1;
            $stmt_update = $conexion->prepare("UPDATE clientes SET clases_restantes = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $nuevas_clases, $id_cliente);
            $stmt_update->execute();
            $stmt_update->close();

            // Registrar asistencia
            $conexion->query("INSERT INTO asistencias (id_cliente, gimnasio_id, fecha_hora) VALUES ($id_cliente, $gimnasio_id, NOW())");

            $mensaje = "Asistencia registrada correctamente.";
            $clases = $nuevas_clases;
        }
    } else {
        $mensaje = "Cliente no encontrado.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 20px;
        }
        input[type="text"] {
            padding: 12px;
            font-size: 18px;
            width: 80%;
            margin: 10px auto;
            display: block;
        }
        .info {
            margin-top: 20px;
            font-size: 20px;
        }
        .mensaje {
            margin-top: 15px;
            font-weight: bold;
            font-size: 18px;
        }
        img.logo {
            width: 120px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <img src="logo.png" alt="Logo" class="logo">
    <form method="POST">
        <input type="text" name="dni" placeholder="Escanear QR o ingresar DNI" autofocus>
    </form>

    <div class="info">
        <?php if ($nombre): ?>
            <p><strong>Nombre:</strong> <?php echo $apellido . ", " . $nombre; ?></p>
            <p><strong>Disciplina:</strong> <?php echo $disciplina; ?></p>
            <p><strong>Clases restantes:</strong> <?php echo $clases; ?></p>
            <p><strong>Vencimiento:</strong> <?php echo date("d/m/Y", strtotime($fecha_vencimiento)); ?></p>
        <?php endif; ?>
        <?php if ($mensaje): ?>
            <div class="mensaje"><?php echo $mensaje; ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
