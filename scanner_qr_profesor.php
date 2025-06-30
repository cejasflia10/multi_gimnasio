<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $codigo = $_POST["codigo"] ?? "";

    if (str_starts_with($codigo, "P")) {
        $dni = substr($codigo, 1);
        $dni = intval($dni);

        $query = "SELECT * FROM profesores WHERE dni = $dni AND gimnasio_id = {$_SESSION['gimnasio_id']}";
        $resultado = $conexion->query($query);

        if ($resultado && $resultado->num_rows > 0) {
            $profesor = $resultado->fetch_assoc();
            $profesor_id = $profesor['id'];
            $fecha = date("Y-m-d");
            $hora_actual = date("H:i:s");

            // Verificar si ya hay ingreso sin salida
            $check = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL");

            if ($check && $check->num_rows > 0) {
                // Registrar salida
                $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora_actual' WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL");
                echo "<script>alert('✅ Salida registrada correctamente.'); window.location.href='scanner_qr_profesor.php';</script>";
            } else {
                // Registrar ingreso
                $conexion->query("INSERT INTO asistencias_profesor (profesor_id, hora_entrada, fecha, gimnasio_id) VALUES ($profesor_id, '$hora_actual', '$fecha', {$_SESSION['gimnasio_id']})");
                echo "<script>alert('✅ Ingreso registrado correctamente.'); window.location.href='scanner_qr_profesor.php';</script>";
            }
        } else {
            echo "<script>alert('Profesor no encontrado (DNI: $dni) en este gimnasio.'); window.location.href='scanner_qr_profesor.php';</script>";
        }
    } else {
        echo "Código inválido.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear QR Profesor</title>
</head>
<body style="background-color: black; color: gold; text-align: center;">
    <h1>Escanear QR del Profesor</h1>
    <form method="POST">
        <input type="text" name="codigo" placeholder="Código QR escaneado" autofocus>
        <button type="submit">Registrar</button>
    </form>
</body>
</html>
