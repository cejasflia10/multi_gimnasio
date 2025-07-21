<?php
session_start();
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_GET['id'] ?? 0);

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

// Validar que el turno pertenece al gimnasio antes de eliminar
$verificar = $conexion->prepare("SELECT id FROM asistencias_profesores WHERE id = ? AND gimnasio_id = ?");
$verificar->bind_param("ii", $id, $gimnasio_id);
$verificar->execute();
$resultado = $verificar->get_result();

if ($resultado->num_rows === 0) {
    echo "<script>alert('❌ No se puede eliminar este turno. No pertenece a tu gimnasio.'); window.location.href='reporte_horas_profesor.php';</script>";
    exit;
}

// Eliminar turno
$eliminar = $conexion->prepare("DELETE FROM asistencias_profesores WHERE id = ? AND gimnasio_id = ?");
$eliminar->bind_param("ii", $id, $gimnasio_id);
if ($eliminar->execute()) {
    echo "<script>alert('✅ Turno eliminado correctamente.'); window.location.href='reporte_horas_profesor.php';</script>";
} else {
    echo "<script>alert('❌ Error al eliminar el turno.'); window.location.href='reporte_horas_profesor.php';</script>";
}
?>
