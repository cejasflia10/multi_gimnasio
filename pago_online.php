
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

$mensaje = "";
$planes = $conexion->query("SELECT * FROM planes ORDER BY nombre");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'];
    $monto = $_POST['monto'];
    $fecha = date('Y-m-d H:i:s');
    $archivo = $_FILES['comprobante']['name'];
    $ruta_tmp = $_FILES['comprobante']['tmp_name'];
    $ruta_destino = "comprobantes/" . uniqid() . "_" . basename($archivo);

    if (move_uploaded_file($ruta_tmp, $ruta_destino)) {
        $stmt = $conexion->prepare("INSERT INTO pagos_pendientes (cliente_id, plan_id, monto, archivo_comprobante, fecha_envio, estado) VALUES (?, ?, ?, ?, ?, 'pendiente')");
        $stmt->bind_param("iisss", $cliente_id, $plan_id, $monto, $ruta_destino, $fecha);
        $stmt->execute();
        $mensaje = "✅ Comprobante enviado correctamente. Será validado en breve.";
    } else {
        $mensaje = "❌ Error al subir el comprobante.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>💳 Pago Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        form {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border: 1px solid gold;
            border-radius: 10px;
        }
        label { display: block; margin-top: 10px; }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 6px;
            border: none;
        }
        button {
            margin-top: 20px;
            background: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .alias {
            text-align: center;
            font-size: 20px;
            margin-bottom: 15px;
            background: #222;
            padding: 10px;
            border-radius: 10px;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            color: lightgreen;
        }
    </style>
</head>
<body>

<h1>💳 Pago por Transferencia</h1>

<div class="alias">
    ALIAS: <strong>FIGHT.ACADEMY.SCORPIONS</strong>
</div>

<form method="POST" enctype="multipart/form-data">
    <label>Seleccioná un plan:</label>
    <select name="plan_id" required>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2, ',', '.') ?></option>
        <?php endwhile; ?>
    </select>

    <label>Monto transferido:</label>
    <input type="number" name="monto" step="0.01" required>

    <label>Comprobante (imagen o PDF):</label>
    <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>

    <button type="submit">Enviar Comprobante</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>
