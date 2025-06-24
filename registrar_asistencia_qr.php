
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");
$mensaje = "";
$color = "";
$icono = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    if (!empty($dni)) {
        $stmt = $conexion->prepare("SELECT * FROM clientes WHERE dni = ?");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $resultado = $stmt->get_result();
        if ($resultado->num_rows > 0) {
            $cliente = $resultado->fetch_assoc();
            $cliente_id = $cliente["id"];
            $membresia = $conexion->query("
                SELECT * FROM membresias 
                WHERE cliente_id = $cliente_id 
                AND fecha_vencimiento >= CURDATE() 
                AND clases_restantes > 0 
                ORDER BY fecha_vencimiento DESC 
                LIMIT 1
            ");
            if ($membresia && $membresia->num_rows > 0) {
                $datos = $membresia->fetch_assoc();
                $clases_restantes = $datos["clases_restantes"] - 1;
                $id_membresia = $datos["id"];
                $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, CURDATE(), CURTIME())");
                $mensaje = "✅ Asistencia registrada correctamente. Clases restantes: $clases_restantes";
                $color = "green";
                $icono = "✅";
            } else {
                $mensaje = "❌ No hay membresías activas o clases disponibles.";
                $color = "red";
                $icono = "❌";
            }
        } else {
            $mensaje = "❌ Cliente no encontrado.";
            $color = "red";
            $icono = "❌";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            text-align: center;
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        input[type="text"] {
            padding: 12px;
            font-size: 20px;
            width: 80%;
            max-width: 400px;
            border-radius: 5px;
            border: none;
            text-align: center;
        }
        button, .boton {
            background-color: gold;
            color: black;
            border: none;
            padding: 12px 25px;
            font-size: 18px;
            margin-top: 15px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
        }
        .boton-volver {
            background-color: #eee;
            color: black;
            margin-top: 30px;
        }
        .mensaje {
            margin-top: 25px;
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese o escanee DNI" autofocus required><br>
        <button type="submit">Registrar</button>
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje" style="color: <?= $color ?>;">
            <?= $icono ?> <?= $mensaje ?>
        </div>
        <a href="registrar_asistencia_qr.php" class="boton">Volver a escanear</a>
    <?php endif; ?>

    <br><br>
    <a href="index.php" class="boton boton-volver">Volver al menú</a>
</body>
</html>
