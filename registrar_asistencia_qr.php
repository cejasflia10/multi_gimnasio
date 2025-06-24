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
            background-color: #111;
            border: 1px solid gold;
            color: gold;
        }
        .alerta {
            color: yellow;
            margin-top: 20px;
        }
        .exito {
            color: lime;
            margin-top: 20px;
        }
        .info {
            font-size: 16px;
            margin-top: 10px;
        }
        button {
            display: none;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>

    <form method="POST" id="formulario">
        <input type="text" name="dni" id="dni" placeholder="Escanear QR" autofocus autocomplete="off">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $dni = trim($_POST["dni"]);
        $fecha_actual = date("Y-m-d");
        $hora_actual = date("H:i:s");

        $cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

        if ($cliente_q && $cliente_q->num_rows > 0) {
            $cliente = $cliente_q->fetch_assoc();
            $cliente_id = $cliente['id'];
            $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

            $membresia_q = $conexion->query("
                SELECT * FROM membresias 
                WHERE cliente_id = $cliente_id 
                AND fecha_vencimiento >= CURDATE() 
                AND clases_restantes > 0 
                ORDER BY fecha_vencimiento DESC 
                LIMIT 1
            ");

            if ($membresia_q && $membresia_q->num_rows > 0) {
                $membresia = $membresia_q->fetch_assoc();
                $id_membresia = $membresia['id'];
                $clases_restantes = $membresia['clases_restantes'] - 1;
                $fecha_vencimiento = $membresia['fecha_vencimiento'];

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

                // Registrar asistencia
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

                echo "<div class='exito'>✅ Asistencia registrada correctamente</div>";
                echo "<div class='info'>
                        👤 $nombre<br>
                        📆 Vence: $fecha_vencimiento<br>
                        🎯 Clases restantes: $clases_restantes<br>
                        🕒 Hora: $hora_actual
                      </div>";
            } else {
                echo "<div class='alerta'>⚠️ $nombre no tiene membresía activa o clases disponibles.</div>";
            }
        } else {
            echo "<div class='alerta'>❌ DNI no encontrado: $dni</div>";
        }

        // Auto reset para volver a escanear
        echo "<script>
                setTimeout(() => {
                    document.getElementById('dni').value = '';
                    document.getElementById('dni').focus();
                }, 3000);
              </script>";
    }
    ?>

    <script>
        window.onload = () => {
            document.getElementById("dni").focus();
        };
    </script>
</body>
</html>
