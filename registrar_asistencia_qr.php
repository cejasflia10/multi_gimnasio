
<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) session_start();

date_default_timezone_set('America/Argentina/Buenos_Aires');

$dni = $_POST['dni'] ?? '';
$tipo = $_POST['tipo'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha = date('Y-m-d');
$hora = date('H:i:s');

if (!$dni || !$tipo) {
    echo "<span style='color:red;'>❌ Datos inválidos.</span>";
    exit;
}

if ($tipo === 'C') {
    $consulta = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
    if ($consulta->num_rows === 0) {
        echo "<span style='color:red;'>❌ Cliente no encontrado.</span>";
        exit;
    }

    $cliente = $consulta->fetch_assoc();
    $cliente_id = $cliente['id'];

    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES ($cliente_id, '$fecha', '$hora', $gimnasio_id)");
    echo "<span style='color:lime;'>✅ Cliente registrado correctamente.</span>";

} elseif ($tipo === 'P') {
    $consulta = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
    if ($consulta->num_rows === 0) {
        echo "<span style='color:red;'>❌ Profesor no encontrado.</span>";
        exit;
    }

    $profesor = $consulta->fetch_assoc();
    $profesor_id = $profesor['id'];

    $check = $conexion->query("SELECT id FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL");

    if ($check->num_rows > 0) {
        $row = $check->fetch_assoc();
        $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE id = " . $row['id']);
        echo "<span style='color:gold;'>✅ Salida registrada.</span>";
    } else {
        $conexion->query("INSERT INTO asistencias_profesor (profesor_id, hora_entrada, fecha, gimnasio_id) VALUES ($profesor_id, '$hora', '$fecha', $gimnasio_id)");
        echo "<span style='color:gold;'>✅ Ingreso registrado.</span>";
    }
} else {
    echo "<span style='color:red;'>❌ Tipo de código no válido.</span>";
}
?>
