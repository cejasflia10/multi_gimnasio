<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$cliente_id = intval($_POST['cliente_id'] ?? 0);
$profesor_id = intval($_POST['profesor_id'] ?? 0);
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);
$disciplina = trim($_POST['disciplina'] ?? '');
$categoria = trim($_POST['categoria'] ?? '');
$observaciones = trim($_POST['observaciones'] ?? '');

if ($cliente_id && $profesor_id && $gimnasio_id && $disciplina !== '') {
    $stmt = $conexion->prepare("INSERT INTO competidores (cliente_id, profesor_id, gimnasio_id, disciplina, categoria, observaciones, fecha_registro)
                                VALUES (?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->bind_param("iiisss", $cliente_id, $profesor_id, $gimnasio_id, $disciplina, $categoria, $observaciones);

    if ($stmt->execute()) {
        echo "<p style='color:lime; text-align:center;'>✅ Competidor registrado correctamente.</p>";
    } else {
        echo "<p style='color:red; text-align:center;'>❌ Error al registrar competidor.</p>";
    }
} else {
    echo "<p style='color:red; text-align:center;'>❌ Datos incompletos.</p>";
}
echo '<div style="text-align:center; margin-top:20px;"><a href="registrar_competidor.php" style="color:gold;">🔙 Volver</a></div>';
?>
