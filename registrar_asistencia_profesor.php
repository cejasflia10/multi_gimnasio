<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$rfid = $_POST['rfid'] ?? '';
$fecha_hora = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $rfid != '') {
    // Buscar profesor
    $buscar = $conexion->prepare("SELECT id FROM profesores WHERE rfid = ? AND id_gimnasio = ?");
    $buscar->bind_param("si", $rfid, $id_gimnasio);
    $buscar->execute();
    $res = $buscar->get_result();

    if ($res->num_rows == 1) {
        $profesor = $res->fetch_assoc();
        $profesor_id = $profesor['id'];

        // Verificar si ya hay una entrada sin egreso
        $sql = "SELECT id FROM asistencias_profesor 
                WHERE profesor_id = ? AND id_gimnasio = ? AND hora_salida IS NULL 
                ORDER BY hora_entrada DESC LIMIT 1";
        $check = $conexion->prepare($sql);
        $check->bind_param("ii", $profesor_id, $id_gimnasio);
        $check->execute();
        $resCheck = $check->get_result();

        if ($resCheck->num_rows == 1) {
            // Registrar salida
            $row = $resCheck->fetch_assoc();
            $asistencia_id = $row['id'];
            $update = $conexion->prepare("UPDATE asistencias_profesor SET hora_salida = ? WHERE id = ?");
            $update->bind_param("si", $fecha_hora, $asistencia_id);
            $update->execute();
            echo "✅ Salida registrada.";
        } else {
            // Registrar entrada
            $insert = $conexion->prepare("INSERT INTO asistencias_profesor (profesor_id, hora_entrada, id_gimnasio) VALUES (?, ?, ?)");
            $insert->bind_param("isi", $profesor_id, $fecha_hora, $id_gimnasio);
            $insert->execute();
            echo "✅ Entrada registrada.";
        }
    } else {
        echo "❌ Profesor no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencia de Profesor</title>
</head>
<body style="background:#111; color:#fff; font-family:Arial; padding:40px;">
    <h2>Ingreso/Egreso Profesor</h2>
    <form method="post">
        RFID Profesor: <input type="text" name="rfid" autofocus required><br><br>
        <input type="submit" value="Registrar">
    </form>
</body>
</html>
