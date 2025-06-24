<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $semana = $_POST['semana'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $peso_inicio = $_POST['peso_inicio'];
    $peso_fin = $_POST['peso_fin'];
    $satisfaccion = $_POST['satisfaccion'];
    $adherencia = $_POST['adherencia'];
    $dificultades = $_POST['dificultades'];
    $desayuno = $_POST['desayuno'];
    $almuerzo = $_POST['almuerzo'];
    $merienda = $_POST['merienda'];
    $cena = $_POST['cena'];
    $lunes = $_POST['lunes'];
    $martes = $_POST['martes'];
    $miercoles = $_POST['miercoles'];
    $jueves = $_POST['jueves'];
    $viernes = $_POST['viernes'];
    $sabado = $_POST['sabado'];
    $domingo = $_POST['domingo'];
    $seguimiento = $_POST['seguimiento'];
    $fecha_registro = date('Y-m-d');

    $sql = "INSERT INTO fichas_seguimiento (
        cliente_id, semana, fecha_inicio, peso_inicio, peso_fin, satisfaccion, adherencia,
        dificultades, desayuno, almuerzo, merienda, cena, lunes, martes, miercoles,
        jueves, viernes, sabado, domingo, seguimiento, fecha_registro
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("iisddsssssssssssssss",
        $cliente_id, $semana, $fecha_inicio, $peso_inicio, $peso_fin, $satisfaccion, $adherencia,
        $dificultades, $desayuno, $almuerzo, $merienda, $cena, $lunes, $martes, $miercoles,
        $jueves, $viernes, $sabado, $domingo, $seguimiento, $fecha_registro
    );

    if ($stmt->execute()) {
        echo "<script>alert('Seguimiento guardado correctamente'); window.location.href='ficha_seguimiento.php?id=$cliente_id';</script>";
    } else {
        echo "<script>alert('Error al guardar el seguimiento: " . $stmt->error . "'); window.history.back();</script>";
    }
    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Método no permitido'); window.history.back();</script>";
}
?>
