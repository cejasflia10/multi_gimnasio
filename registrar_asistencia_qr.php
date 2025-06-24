<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$mensaje = "";
$clases_restantes = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $_POST["dni"] ?? '';

    if ($dni != "") {
        // Obtener cliente
        $consulta_cliente = $conexion->prepare("SELECT * FROM clientes WHERE dni = ?");
        $consulta_cliente->bind_param("s", $dni);
        $consulta_cliente->execute();
        $resultado_cliente = $consulta_cliente->get_result();

        if ($resultado_cliente->num_rows > 0) {
            $cliente = $resultado_cliente->fetch_assoc();
            $cliente_id = $cliente['id'];

            // Verificar membresía activa con clases
            $consulta_membresia = $conexion->prepare("SELECT * FROM membresias WHERE cliente_id = ? AND fecha_vencimiento >= CURDATE() AND clases_restantes > 0 ORDER BY fecha_vencimiento DESC LIMIT 1");
            $consulta_membresia->bind_param("i", $cliente_id);
            $consulta_membresia->execute();
            $resultado_membresia = $consulta_membresia->get_result();

            if ($resultado_membresia->num_rows > 0) {
                $membresia = $resultado_membresia->fetch_assoc();
                $membresia_id = $membresia['id'];
                $clases_restantes = $membresia['clases_restantes'] - 1;

                // Registrar asistencia
                $insert_asistencia = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha_hora) VALUES (?, NOW())");
                $insert_asistencia->bind_param("i", $cliente_id);
                $insert_asistencia->execute();

                // Actualizar clases restantes
                $update_clases = $conexion->prepare("UPDATE membresias SET clases_restantes = ? WHERE id = ?");
                $update_clases->bind_param("ii", $clases_restantes, $membresia_id);
                $update_clases->execute();

                $mensaje = "<div class='exito'>✅ Asistencia registrada correctamente. Clases restantes: $clases_restantes</div>";
                $mensaje .= "<br><button onclick='reiniciar()'>Volver a escanear</button>";
            } else {
                $mensaje = "<div class='error'>❌ No hay membresías activas o clases disponibles.</div>";
            }
        } else {
            $mensaje = "<div class='error'>❌ Cliente no encontrado.</div>";
        }
    } else {
        $mensaje = "<div class='error'>❌ DNI no ingresado.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escaneo QR - Asistencia</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        input, button {
            padding: 10px;
            font-size: 18px;
            margin: 10px;
        }
        .exito {
            color: limegreen;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese o escanee DNI" autofocus>
        <br>
        <button type="submit">Registrar</button>
    </form>
    <br>
    <?= $mensaje ?>
    <br><br>
    <a href="index.php"><button>Volver al menú</button></a>

    <script>
    function reiniciar() {
        window.location.href = window.location.pathname;
    }
    </script>
</body>
</html>
