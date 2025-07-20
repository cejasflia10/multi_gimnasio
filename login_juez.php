<?php
include 'conexion.php';
session_start();

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni'] ?? '');
    $q = $conexion->query("SELECT id, nombre FROM jueces_evento WHERE dni = '$dni'");
    if ($q && $q->num_rows > 0) {
        $juez = $q->fetch_assoc();
        $_SESSION['juez_id'] = $juez['id'];
        $_SESSION['juez_nombre'] = $juez['nombre'];
        header("Location: panel_juez.php");
        exit;
    } else {
        $mensaje = "❌ DNI no encontrado.";
    }
}
?>
<form method="POST">
    <label>Ingresar DNI:</label>
    <input type="text" name="dni" required>
    <button type="submit">Acceder</button>
    <?= $mensaje ?>
</form>
