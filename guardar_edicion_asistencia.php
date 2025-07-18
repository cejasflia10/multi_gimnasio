<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    $hora_ingreso = $_POST['hora_ingreso'] ?? '';
    $hora_salida = $_POST['hora_salida'] ?? '';
    $alumnos = intval($_POST['alumnos'] ?? 0);
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    if ($id > 0 && $fecha && $hora_ingreso && $hora_salida) {
        $stmt = $conexion->prepare("UPDATE asistencias_profesores SET fecha = ?, hora_ingreso = ?, hora_salida = ?, alumnos = ? WHERE id = ? AND gimnasio_id = ?");
        $stmt->bind_param("sssiii", $fecha, $hora_ingreso, $hora_salida, $alumnos, $id, $gimnasio_id);
        $stmt->execute();

        header("Location: pagar_horas_profesor.php?editado=1");
        exit;
    } else {
        echo "<p style='color:red;'>❌ Datos incompletos.</p>";
    }
}
?>
