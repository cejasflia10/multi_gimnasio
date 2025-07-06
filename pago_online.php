<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

$mensaje = "";
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");

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
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
<div class="contenedor">
<h1>💳 Pago por Transferencia</h1>

<div class="alias">
</div>

<?php
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$alias = '';
$res_alias = $conexion->query("SELECT alias FROM gimnasios WHERE id = $gimnasio_id");
if ($res_alias && $row = $res_alias->fetch_assoc()) {
    $alias = $row['alias'];
}
?>
<div style="text-align:center; color:gold; font-size:18px; margin-top: 20px;">
    💰 Alias para transferencia:<br>
    <strong style="color:white;"><?= htmlspecialchars($alias ?: 'No disponible') ?></strong>
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
</div>
</body>
</html>
