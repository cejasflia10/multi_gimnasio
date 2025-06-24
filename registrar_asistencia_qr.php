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
        .datos {
            margin-top: 15px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>

    <form method="POST" id="form-qr">
        <input type="text" name="dni" id="dni" autofocus autocomplete="off">
    </form>

    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $dni = $conexion->real_escape_string($_POST["dni"]);
        $fecha_actual = date("Y-m-d");
        $hora_actual = date("H:i:s");

        $clientes_result = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");
        if ($clientes_result->num_rows === 0) {
            echo "<div class='alerta'>❌ DNI no encontrado.</div>";
        } else {
            $cliente = $clientes_result->fetch_assoc();
            $cliente_id = $cliente['id'];
            $nombre_completo = $cliente['apellido'] . ' ' . $cliente['nombre'];

            // Verificar membresía
            $membresia_result = $conexion->query("
                SELECT * FROM membresias 
                WHERE cliente_id = $cliente_id 
                AND fecha_vencimiento >= CURDATE() 
                AND clases_restantes > 0 
                ORDER BY fecha_vencimiento DESC 
                LIMIT 1
            ");

            if ($membresia_result->num_rows === 0) {
                echo "<div class='alerta'>⚠️ $nombre_completo no tiene una membresía activa o clases disponibles.</div>";
            } else {
                $membresia = $membresia_result->fetch_assoc();
                $id_membresia = $membresia['id'];
                $clases_restantes = $membresia['clases_restantes'] - 1;
                $fecha_vencimiento = $membresia['fecha_vencimiento'];

                // Descontar clase
                $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

                // Registrar asistencia
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

                echo "<div class='exito'>✅ $nombre_completo - Asistencia registrada</div>";
                echo "<div class='datos'>
                        Clases restantes: $clases_restantes<br>
                        Fecha de vencimiento: $fecha_vencimiento<br>
                        Hora: $hora_actual
                      </div>";
            }
        }

        // Refrescar en 3 segundos para nuevo escaneo
        echo "<script>
                setTimeout(() => {
                    document.getElementById('dni').value = '';
                    document.getElementById('dni').focus();
                }, 3000);
              </script>";
    }
    ?>

    <br><br>
    <a href="index.php"><button>Volver al menú</button></a>

    <script>
        // Enfocar automáticamente en el input cada vez
        window.onload = () => {
            document.getElementById('dni').focus();
        }
    </script>
</body>
</html>
