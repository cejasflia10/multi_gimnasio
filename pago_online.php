<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    die("Acceso denegado.");
}

include 'menu_cliente.php';

$mensaje = "";

// Obtener planes y adicionales del gimnasio logueado
$planes = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");
$adicionales = $conexion->query("SELECT * FROM planes_adicionales WHERE gimnasio_id = $gimnasio_id ORDER BY nombre");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan_id = $_POST['plan_id'] ?? 0;
    $monto = $_POST['monto'];
    $fecha = date('Y-m-d H:i:s');
    
    $archivo = $_FILES['comprobante']['name'];
    $ruta_tmp = $_FILES['comprobante']['tmp_name'];

    // RUTA ABSOLUTA PARA GUARDAR FÍSICAMENTE
    $nombre_final = uniqid() . "_" . basename($archivo);
    $carpeta = "multi_gimnasio/comprobantes/";
    $ruta_destino = $carpeta . $nombre_final;

    // Guardar adicionales como JSON
    $adicionales_seleccionados = $_POST['adicionales'] ?? [];
    $adicionales_json = json_encode($adicionales_seleccionados);

    // Intentar mover el archivo
    if (move_uploaded_file($ruta_tmp, $ruta_destino)) {
        $stmt = $conexion->prepare("INSERT INTO pagos_pendientes 
            (cliente_id, gimnasio_id, plan_id, monto, archivo_comprobante, fecha_envio, estado, adicionales) 
            VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)");
        $stmt->bind_param("iiissss", 
            $cliente_id, 
            $gimnasio_id, 
            $plan_id, 
            $monto, 
            $ruta_destino, 
            $fecha, 
            $adicionales_json
        );
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
    <script>
        function calcularTotal() {
            let plan = document.getElementById('plan_id');
            let monto = 0;
            if (plan && plan.value !== "0") {
                monto += parseFloat(plan.selectedOptions[0].dataset.precio || 0);
            }
            document.querySelectorAll('input[name="adicionales[]"]:checked').forEach(el => {
                monto += parseFloat(el.dataset.precio || 0);
            });
            document.getElementById('monto').value = monto.toFixed(2);
            document.getElementById('total_mostrar').innerText = "$" + monto.toFixed(2);
        }
    </script>
</head>
<body>
<div class="contenedor">
<h1>💳 Pago por Transferencia</h1>

<?php
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

<form method="POST" enctype="multipart/form-data" oninput="calcularTotal()">
    <label>Seleccioná un plan:</label>
    <select name="plan_id" id="plan_id">
        <option value="0" data-precio="0">-- Solo adicionales --</option>
        <?php while ($p = $planes->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio'] ?>">
                <?= $p['nombre'] ?> - $<?= number_format($p['precio'], 2, ',', '.') ?>
            </option>
        <?php endwhile; ?>
    </select>

    <label>Seleccioná adicionales (opcionales):</label><br>
    <?php while ($a = $adicionales->fetch_assoc()): ?>
        <label>
            <input type="checkbox" name="adicionales[]" value="<?= $a['id'] ?>" data-precio="<?= $a['precio'] ?>">
            <?= $a['nombre'] ?> ($<?= number_format($a['precio'], 2, ',', '.') ?>)
        </label><br>
    <?php endwhile; ?>

    <p><strong>Total a pagar: <span id="total_mostrar">$0.00</span></strong></p>
    <input type="hidden" name="monto" id="monto" value="0">

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
