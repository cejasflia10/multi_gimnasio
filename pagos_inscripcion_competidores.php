<?php
session_start();
include 'conexion.php';

$eventos = $conexion->query("SELECT id, titulo FROM eventos_deportivos ORDER BY fecha ASC");
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $evento_id = intval($_POST['evento_id']);
    $dni = trim($_POST['dni']);
    $nombre = trim($_POST['nombre']);
    $monto = floatval($_POST['monto']);
    $metodo_pago = $_POST['metodo_pago'];

    $competidor_q = $conexion->query("SELECT id FROM competidores_evento WHERE dni = '$dni' AND evento_id = $evento_id");
    if ($c = $competidor_q->fetch_assoc()) {
        $competidor_id = $c['id'];

        $stmt = $conexion->prepare("INSERT INTO pagos_inscripcion_evento (evento_id, competidor_id, nombre, dni, monto, metodo_pago) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissds", $evento_id, $competidor_id, $nombre, $dni, $monto, $metodo_pago);

        if ($stmt->execute()) {
            $mensaje = "✅ Pago registrado correctamente.";
        } else {
            $mensaje = "❌ Error al registrar el pago.";
        }
    } else {
        $mensaje = "❌ Competidor no encontrado en este evento.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos de Inscripción</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>💰 Pagos de Inscripción</h2>
    <?php if ($mensaje) echo "<p style='color: gold;'>$mensaje</p>"; ?>

    <form method="POST">
        <label>Evento:</label>
        <select name="evento_id" required>
            <option value="">-- Seleccionar Evento --</option>
            <?php while ($e = $eventos->fetch_assoc()): ?>
                <option value="<?= $e['id'] ?>"><?= $e['titulo'] ?></option>
            <?php endwhile; ?>
        </select>

        <label>DNI del competidor:</label>
        <input type="text" name="dni" required>

        <label>Nombre del competidor:</label>
        <input type="text" name="nombre" required>

        <label>Monto pagado:</label>
        <input type="number" step="0.01" name="monto" required>

        <label>Método de pago:</label>
        <select name="metodo_pago" required>
            <option value="Efectivo">Efectivo</option>
            <option value="Transferencia">Transferencia</option>
            <option value="Débito">Débito</option>
            <option value="Crédito">Crédito</option>
        </select>

        <button type="submit">💾 Registrar Pago</button>
        <a href="index.php" class="boton-volver">⬅ Volver</a>
    </form>
</div>
</body>
</html>
