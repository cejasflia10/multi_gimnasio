<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$gimnasio_id = intval($_GET['id'] ?? 0);
if ($gimnasio_id === 0) {
    exit("❌ Acceso denegado.");
}

$mensaje = '';
$fecha_actual = date('Y-m-d');

// Guardar renovación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nueva_fecha = $_POST['fecha_vencimiento'] ?? '';
    $monto = floatval($_POST['monto'] ?? 0);
    $observacion = trim($_POST['observacion'] ?? '');

    // Actualizar en la tabla principal
    $conexion->query("UPDATE gimnasios SET fecha_vencimiento = '$nueva_fecha' WHERE id = $gimnasio_id");

    // Registrar en historial de pagos
    $stmt = $conexion->prepare("INSERT INTO pagos_gimnasios (gimnasio_id, fecha_pago, fecha_vencimiento, monto, observacion) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issds", $gimnasio_id, $fecha_actual, $nueva_fecha, $monto, $observacion);
    $stmt->execute();
    $stmt->close();

    $mensaje = "✅ Renovación guardada correctamente.";
}

// Obtener datos del gimnasio
$gimnasio = $conexion->query("SELECT nombre, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Renovar Gimnasio</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body { background: #000; color: gold; padding: 30px; font-family: Arial; }
        form { max-width: 600px; margin: auto; background: #111; padding: 20px; border-radius: 10px; }
        label { display: block; margin-top: 15px; }
        input, textarea { width: 100%; padding: 10px; margin-top: 5px; background: #222; color: gold; border: 1px solid #444; border-radius: 6px; }
        .boton { margin-top: 20px; background: gold; color: black; padding: 10px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; }
        .mensaje { text-align: center; color: lightgreen; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>

<h2 style="text-align:center;">🔁 Renovar Plan - <?= htmlspecialchars($gimnasio['nombre']) ?></h2>

<?php if ($mensaje): ?>
    <div class="mensaje"><?= $mensaje ?></div>
<?php endif; ?>

<form method="POST">
    <label>Fecha de vencimiento actual:</label>
    <input type="text" value="<?= htmlspecialchars($gimnasio['fecha_vencimiento']) ?>" readonly>

    <label>Nueva fecha de vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required>

    <label>Monto abonado ($):</label>
    <input type="number" name="monto" step="0.01" required>

    <label>Observación (opcional):</label>
    <textarea name="observacion" rows="3"></textarea>

    <button type="submit" class="boton">💾 Guardar Renovación</button>
</form>

<div style="text-align:center; margin-top: 20px;">
    <a href="ver_gimnasios.php" class="boton">↩️ Volver</a>
</div>

</body>
</html>
