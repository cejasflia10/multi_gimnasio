<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $edad = $_POST['edad'];
    $objetivo = $_POST['objetivo'];
    $motivacion = $_POST['motivacion'];
    $duerme7hs = $_POST['duerme7hs'];
    $trabaja8hs = $_POST['trabaja8hs'];
    $fuma = $_POST['fuma'];
    $alcohol = $_POST['alcohol'];
    $agua = $_POST['agua'];
    $entrena = $_POST['entrena'];
    $entrenos_por_semana = $_POST['entrenos_por_semana'];
    $horas_por_entreno = $_POST['horas_por_entreno'];
    $comidas_por_dia = $_POST['comidas_por_dia'];
    $se_salta_comidas = $_POST['se_salta_comidas'];
    $saludable = $_POST['saludable'];
    $gaseosas = $_POST['gaseosas'];
    $frutas_verduras = $_POST['frutas_verduras'];
    $fritos = $_POST['fritos'];
    $notas = $_POST['notas'];
    $fecha = date('Y-m-d');

    $sql = "INSERT INTO fichas_habitos (
                cliente_id, edad, objetivo, motivacion, duerme7hs, trabaja8hs, fuma,
                alcohol, agua, entrena, entrenos_por_semana, horas_por_entreno,
                comidas_por_dia, se_salta_comidas, saludable, gaseosas, frutas_verduras,
                fritos, notas, fecha
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("isssiiiididisssssiss", 
        $cliente_id, $edad, $objetivo, $motivacion, $duerme7hs, $trabaja8hs, $fuma,
        $alcohol, $agua, $entrena, $entrenos_por_semana, $horas_por_entreno,
        $comidas_por_dia, $se_salta_comidas, $saludable, $gaseosas, $frutas_verduras,
        $fritos, $notas, $fecha
    );

    if ($stmt->execute()) {
        echo "<script>alert('Ficha guardada correctamente'); window.location.href='ficha_habitos.php';</script>";
    } else {
        echo "<script>alert('Error al guardar la ficha: " . $stmt->error . "'); window.history.back();</script>";
    }
    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Método no permitido'); window.history.back();</script>";
}
?>
