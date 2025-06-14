<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['id_gimnasio'])) {
    die('Acceso no autorizado.');
}

$id_gimnasio = $_SESSION['id_gimnasio'];
$identificador = $_POST['identificador'] ?? '';
$fecha_hora = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $identificador != '') {
    // Primero: verificar si es un cliente con membresía activa
    $buscar_cliente = $conexion->prepare("SELECT c.id, m.clases_restantes
        FROM clientes c
        JOIN membresias m ON c.id = m.cliente_id
        WHERE (c.dni = ? OR c.rfid = ? OR c.email = ?) AND c.id_gimnasio = ? AND m.id_gimnasio = ?");
    $buscar_cliente->bind_param("sssii", $identificador, $identificador, $identificador, $id_gimnasio, $id_gimnasio);
    $buscar_cliente->execute();
    $res_cliente = $buscar_cliente->get_result();

    if ($res_cliente->num_rows > 0) {
        $cliente = $res_cliente->fetch_assoc();
        $cliente_id = $cliente['id'];
        $clases_restantes = $cliente['clases_restantes'];

        if ($clases_restantes > 0) {
            $insertar = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha_hora, id_gimnasio) VALUES (?, ?, ?)");
            $insertar->bind_param("isi", $cliente_id, $fecha_hora, $id_gimnasio);
            $insertar->execute();

            $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE cliente_id = $cliente_id AND id_gimnasio = $id_gimnasio");

            echo "✅ Asistencia registrada para cliente.";
        } else {
            echo "❌ Sin clases disponibles. Debe renovar membresía.";
        }

    } else {
        // Si no es cliente, buscar si es un profesor
        $buscar_prof = $conexion->prepare("SELECT id FROM profesores WHERE (rfid = ? OR email = ?) AND id_gimnasio = ?");
        $buscar_prof->bind_param("ssi", $identificador, $identificador, $id_gimnasio);
        $buscar_prof->execute();
        $res_prof = $buscar_prof->get_result();

        if ($res_prof->num_rows == 1) {
            $prof = $res_prof->fetch_assoc();
            $profesor_id = $prof['id'];

            $sql = "SELECT id FROM asistencias_profesor 
                    WHERE profesor_id = ? AND id_gimnasio = ? AND hora_salida IS NULL 
                    ORDER BY hora_entrada DESC LIMIT 1";
            $check = $conexion->prepare($sql);
            $check->bind_param("ii", $profesor_id, $id_gimnasio);
            $check->execute();
            $resCheck = $check->get_result();

            if ($resCheck->num_rows == 1) {
                $row = $resCheck->fetch_assoc();
                $asistencia_id = $row['id'];
                $update = $conexion->prepare("UPDATE asistencias_profesor SET hora_salida = ? WHERE id = ?");
                $update->bind_param("si", $fecha_hora, $asistencia_id);
                $update->execute();
                echo "✅ Salida registrada para profesor.";
            } else {
                $insert = $conexion->prepare("INSERT INTO asistencias_profesor (profesor_id, hora_entrada, id_gimnasio) VALUES (?, ?, ?)");
                $insert->bind_param("isi", $profesor_id, $fecha_hora, $id_gimnasio);
                $insert->execute();
                echo "✅ Entrada registrada para profesor.";
            }
        } else {
            echo "❌ Identificador no encontrado en clientes ni profesores.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso General</title>
</head>
<body style="background:#111; color:#fff; font-family:Arial; padding:40px;">
    <h2>Ingreso (Cliente o Profesor)</h2>
    <form method="post">
        DNI / RFID / Email / QR: <input type="text" name="identificador" autofocus required><br><br>
        <input type="submit" value="Registrar ingreso/egreso">
    </form>
</body>
</html>
