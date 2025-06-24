<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 30px;
        }
        input {
            padding: 10px;
            font-size: 18px;
            width: 90%;
            max-width: 400px;
            margin-bottom: 15px;
        }
        button {
            padding: 10px 20px;
            font-size: 18px;
            background-color: gold;
            border: none;
            color: black;
            cursor: pointer;
        }
        .alerta {
            color: yellow;
            margin-top: 20px;
        }
        .exito {
            color: lime;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese o escanee DNI" autofocus required>
        <br>
        <button type="submit">Registrar</button>
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $dni = $conexion->real_escape_string($_POST["dni"]);
        $fecha_actual = date("Y-m-d");
        $hora_actual = date("H:i:s");

        // Obtener cliente(s) con ese DNI
        $clientes_result = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
        if ($clientes_result->num_rows === 0) {
            echo "<div class='alerta'>❌ DNI no encontrado en la base de datos.</div>";
        } else {
            // Obtener membresía activa y con clases restantes
            $membresia_result = $conexion->query("
                SELECT * FROM membresias 
                WHERE cliente_id IN (SELECT id FROM clientes WHERE dni = '$dni') 
                AND fecha_vencimiento >= CURDATE() 
                AND clases_restantes > 0 
                ORDER BY fecha_vencimiento DESC 
                LIMIT 1
            ");

            if ($membresia_result->num_rows === 0) {
                echo "<div class='alerta'>⚠️ El DNI $dni no tiene una membresía activa o clases disponibles.</div>";
            } else {
                $membresia = $membresia_result->fetch_assoc();
                $id_membresia = $membresia['id'];
                $clases_restantes = $membresia['clases_restantes'] - 1;

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

                // Registrar asistencia
                $cliente_id = $membresia['cliente_id'];
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

                echo "<div class='exito'>✅ Asistencia registrada correctamente. Clases restantes: $clases_restantes</div>";
            }
        }
    }
    ?>

    <br><br>
    <a href="index.php"><button>Volver al menú</button></a>
</body>
</html>
