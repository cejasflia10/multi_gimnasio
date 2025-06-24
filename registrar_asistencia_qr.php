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
        .mensaje {
            margin-top: 20px;
            font-size: 20px;
        }
        .exito {
            color: lime;
        }
        .alerta {
            color: red;
        }
    </style>
    <script>
        // Reiniciar escaneo automáticamente después de 4 segundos
        function reiniciarEscaneo() {
            setTimeout(() => {
                window.location.reload();
            }, 4000);
        }
    </script>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Escaneá tu QR" autofocus required>
        <br>
        <button type="submit">Registrar</button>
    </form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $conexion->real_escape_string($_POST["dni"]);
    $fecha_actual = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $cliente_result = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni'");
    if ($cliente_result->num_rows === 0) {
        echo "<div class='mensaje alerta'>❌ DNI no encontrado en la base de datos.</div>";
        echo "<script>reiniciarEscaneo();</script>";
    } else {
        $cliente = $cliente_result->fetch_assoc();
        $cliente_id = $cliente['id'];
        $nombre_completo = $cliente['apellido'] . ' ' . $cliente['nombre'];

        $membresia_result = $conexion->query("
            SELECT * FROM membresias 
            WHERE cliente_id = $cliente_id 
              AND fecha_vencimiento >= CURDATE() 
              AND clases_restantes > 0 
            ORDER BY fecha_vencimiento DESC 
            LIMIT 1
        ");

        if ($membresia_result->num_rows === 0) {
            echo "<div class='mensaje alerta'>⚠️ $nombre_completo no tiene membresía activa o clases disponibles.</div>";
            echo "<script>reiniciarEscaneo();</script>";
        } else {
            $membresia = $membresia_result->fetch_assoc();
            $id_membresia = $membresia['id'];
            $clases_restantes = $membresia['clases_restantes'] - 1;
            $fecha_vencimiento = $membresia['fecha_vencimiento'];

            // Descontar clase y registrar asistencia
            $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");
            $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

            echo "<div class='mensaje exito'>
                    ✅ $nombre_completo<br>
                    Fecha de vencimiento: $fecha_vencimiento<br>
                    Clases restantes: $clases_restantes
                  </div>";
            echo "<script>reiniciarEscaneo();</script>";
        }
    }
}
?>
</body>
</html>
